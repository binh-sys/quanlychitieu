<?php
require_once '../connect.php';

// Get user info
$user = [
    'id' => 1,
    'full_name' => 'Administrator',
    'email' => 'admin@fintrack.vn',
    'role' => 'admin'
];

// Get filter parameters
$period = isset($_GET['period']) ? $_GET['period'] : 'month';
$user_filter = isset($_GET['user']) ? intval($_GET['user']) : 0;

// Calculate date range based on period
$date_condition = '';
switch ($period) {
    case 'today':
        $date_condition = "DATE(transaction_date) = CURDATE()";
        $period_label = "Hôm nay";
        break;
    case 'week':
        $date_condition = "YEARWEEK(transaction_date) = YEARWEEK(CURDATE())";
        $period_label = "Tuần này";
        break;
    case 'month':
        $date_condition = "MONTH(transaction_date) = MONTH(CURDATE()) AND YEAR(transaction_date) = YEAR(CURDATE())";
        $period_label = "Tháng này";
        break;
    case 'year':
        $date_condition = "YEAR(transaction_date) = YEAR(CURDATE())";
        $period_label = "Năm nay";
        break;
    case 'all':
        $date_condition = "1=1";
        $period_label = "Tất cả";
        break;
}

// Add user filter
$where_clause = $date_condition;
if ($user_filter > 0) {
    $where_clause .= " AND t.user_id = $user_filter";
}

// Get overview statistics
$stats = [];
$stats['total_income'] = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM transactions t WHERE type = 'income' AND $where_clause")->fetch_assoc()['total'];
$stats['total_expense'] = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM transactions t WHERE type = 'expense' AND $where_clause")->fetch_assoc()['total'];
$stats['balance'] = $stats['total_income'] - $stats['total_expense'];
$stats['transaction_count'] = $conn->query("SELECT COUNT(*) as total FROM transactions t WHERE $where_clause")->fetch_assoc()['total'];
$stats['avg_income'] = $stats['transaction_count'] > 0 ? $conn->query("SELECT COALESCE(AVG(amount), 0) as avg FROM transactions t WHERE type = 'income' AND $where_clause")->fetch_assoc()['avg'] : 0;
$stats['avg_expense'] = $stats['transaction_count'] > 0 ? $conn->query("SELECT COALESCE(AVG(amount), 0) as avg FROM transactions t WHERE type = 'expense' AND $where_clause")->fetch_assoc()['avg'] : 0;

// Get top spending categories
$top_categories = $conn->query("SELECT c.name, c.icon, c.color, COALESCE(SUM(t.amount), 0) as total, COUNT(t.id) as count
                                FROM categories c
                                LEFT JOIN transactions t ON c.id = t.category_id AND t.type = 'expense' AND $where_clause
                                WHERE c.type = 'expense'
                                GROUP BY c.id
                                HAVING total > 0
                                ORDER BY total DESC
                                LIMIT 10");

// Get top income categories
$top_income_cats = $conn->query("SELECT c.name, c.icon, c.color, COALESCE(SUM(t.amount), 0) as total, COUNT(t.id) as count
                                 FROM categories c
                                 LEFT JOIN transactions t ON c.id = t.category_id AND t.type = 'income' AND $where_clause
                                 WHERE c.type = 'income'
                                 GROUP BY c.id
                                 HAVING total > 0
                                 ORDER BY total DESC
                                 LIMIT 5");

// Get daily trend (last 30 days)
$daily_trend = $conn->query("SELECT DATE(transaction_date) as date,
                             COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as income,
                             COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as expense
                             FROM transactions t
                             WHERE transaction_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) " . ($user_filter > 0 ? "AND user_id = $user_filter" : "") . "
                             GROUP BY DATE(transaction_date)
                             ORDER BY date ASC");

// Get monthly comparison (last 6 months)
$monthly_data = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $month_name = date('M Y', strtotime("-$i months"));
    
    $income = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM transactions t WHERE type = 'income' AND DATE_FORMAT(transaction_date, '%Y-%m') = '$month'" . ($user_filter > 0 ? " AND user_id = $user_filter" : ""))->fetch_assoc()['total'];
    $expense = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM transactions t WHERE type = 'expense' AND DATE_FORMAT(transaction_date, '%Y-%m') = '$month'" . ($user_filter > 0 ? " AND user_id = $user_filter" : ""))->fetch_assoc()['total'];
    
    $monthly_data[] = [
        'month' => $month_name,
        'income' => $income,
        'expense' => $expense,
        'balance' => $income - $expense
    ];
}

// Get top users by spending
$top_users = $conn->query("SELECT u.full_name, u.email,
                          COALESCE(SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE 0 END), 0) as total_income,
                          COALESCE(SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END), 0) as total_expense,
                          COUNT(t.id) as transaction_count
                          FROM users u
                          LEFT JOIN transactions t ON u.id = t.user_id AND $where_clause
                          GROUP BY u.id
                          HAVING transaction_count > 0
                          ORDER BY total_expense DESC
                          LIMIT 10");

// Get users list for filter
$users_list = $conn->query("SELECT id, full_name FROM users ORDER BY full_name");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Báo cáo Thống kê - Admin</title>
    <link rel="stylesheet" href="assets/css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <?php include 'includes/header.php'; ?>

        <div class="content">
            <div class="page-header">
                <h1>📊 Báo cáo Thống kê</h1>
                <p>Phân tích chi tiết về tài chính - <?= $period_label ?></p>
            </div>

            <!-- Filters -->
            <div class="card" style="margin-bottom: 24px;">
                <div style="padding: 20px;">
                    <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; align-items: end;">
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 600; color: #6B7280; margin-bottom: 6px;">Khoảng thời gian</label>
                            <select name="period" style="width: 100%; padding: 8px 12px; border: 1px solid #E5E7EB; border-radius: 8px; font-size: 14px;">
                                <option value="today" <?= $period == 'today' ? 'selected' : '' ?>>Hôm nay</option>
                                <option value="week" <?= $period == 'week' ? 'selected' : '' ?>>Tuần này</option>
                                <option value="month" <?= $period == 'month' ? 'selected' : '' ?>>Tháng này</option>
                                <option value="year" <?= $period == 'year' ? 'selected' : '' ?>>Năm nay</option>
                                <option value="all" <?= $period == 'all' ? 'selected' : '' ?>>Tất cả</option>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 600; color: #6B7280; margin-bottom: 6px;">Người dùng</label>
                            <select name="user" style="width: 100%; padding: 8px 12px; border: 1px solid #E5E7EB; border-radius: 8px; font-size: 14px;">
                                <option value="0">Tất cả người dùng</option>
                                <?php 
                                $users_list->data_seek(0);
                                while ($u = $users_list->fetch_assoc()): 
                                ?>
                                <option value="<?= $u['id'] ?>" <?= $user_filter == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['full_name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div>
                            <button type="submit" style="width: 100%; padding: 10px; background: #6366F1; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                                🔍 Lọc
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Overview Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: #10B981;">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 1v22m5-18H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Tổng thu nhập</div>
                        <div class="stat-value" style="color: #10B981;">+<?= number_format($stats['total_income']) ?>đ</div>
                        <div style="font-size: 12px; color: #6B7280; margin-top: 4px;">TB: <?= number_format($stats['avg_income']) ?>đ/GD</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(239, 68, 68, 0.1); color: #EF4444;">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
                            <line x1="1" y1="10" x2="23" y2="10"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Tổng chi tiêu</div>
                        <div class="stat-value" style="color: #EF4444;">-<?= number_format($stats['total_expense']) ?>đ</div>
                        <div style="font-size: 12px; color: #6B7280; margin-top: 4px;">TB: <?= number_format($stats['avg_expense']) ?>đ/GD</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1); color: #F59E0B;">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 1v22m5-18H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Số dư</div>
                        <div class="stat-value" style="color: <?= $stats['balance'] >= 0 ? '#10B981' : '#EF4444' ?>;">
                            <?= number_format($stats['balance']) ?>đ
                        </div>
                        <div style="font-size: 12px; color: #6B7280; margin-top: 4px;">
                            <?= $stats['total_income'] > 0 ? number_format(($stats['balance'] / $stats['total_income']) * 100, 1) : 0 ?>% tiết kiệm
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(99, 102, 241, 0.1); color: #6366F1;">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Giao dịch</div>
                        <div class="stat-value"><?= number_format($stats['transaction_count']) ?></div>
                        <div style="font-size: 12px; color: #6B7280; margin-top: 4px;">
                            <?= number_format($stats['total_income'] + $stats['total_expense']) ?>đ tổng
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 1 -->
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 24px;">
                <!-- Monthly Comparison -->
                <div class="card">
                    <div class="card-header">
                        <h2>📈 So sánh 6 tháng gần nhất</h2>
                    </div>
                    <div style="padding: 24px;">
                        <canvas id="monthlyChart" height="300"></canvas>
                    </div>
                </div>

                <!-- Expense Categories Pie -->
                <div class="card">
                    <div class="card-header">
                        <h2>🎯 Chi tiêu theo danh mục</h2>
                    </div>
                    <div style="padding: 24px;">
                        <canvas id="categoryPieChart" height="300"></canvas>
                    </div>
                </div>
            </div>

            <!-- Charts Row 2 -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
                <!-- Daily Trend -->
                <div class="card">
                    <div class="card-header">
                        <h2>📊 Xu hướng 30 ngày</h2>
                    </div>
                    <div style="padding: 24px;">
                        <canvas id="dailyTrendChart" height="250"></canvas>
                    </div>
                </div>

                <!-- Income Categories -->
                <div class="card">
                    <div class="card-header">
                        <h2>💰 Thu nhập theo nguồn</h2>
                    </div>
                    <div style="padding: 24px;">
                        <canvas id="incomeChart" height="250"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top Categories Table -->
            <div class="card" style="margin-bottom: 24px;">
                <div class="card-header">
                    <h2>🏆 Top 10 Danh mục Chi tiêu</h2>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Hạng</th>
                                <th>Danh mục</th>
                                <th>Số giao dịch</th>
                                <th>Tổng chi</th>
                                <th>Trung bình</th>
                                <th>% Tổng chi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $rank = 1;
                            $top_categories->data_seek(0);
                            while ($cat = $top_categories->fetch_assoc()): 
                                $percentage = $stats['total_expense'] > 0 ? ($cat['total'] / $stats['total_expense']) * 100 : 0;
                            ?>
                            <tr>
                                <td>
                                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; background: <?= $rank <= 3 ? '#FCD34D' : '#E5E7EB' ?>; font-weight: 700; font-size: 13px;">
                                        <?= $rank ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div style="width: 36px; height: 36px; border-radius: 10px; background: <?= $cat['color'] ?>15; display: flex; align-items: center; justify-content: center; font-size: 18px;">
                                            <?= $cat['icon'] ?>
                                        </div>
                                        <strong><?= htmlspecialchars($cat['name']) ?></strong>
                                    </div>
                                </td>
                                <td><?= number_format($cat['count']) ?></td>
                                <td style="font-weight: 700; color: #EF4444;"><?= number_format($cat['total']) ?>đ</td>
                                <td><?= number_format($cat['total'] / max($cat['count'], 1)) ?>đ</td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <div style="flex: 1; height: 8px; background: #E5E7EB; border-radius: 4px; overflow: hidden;">
                                            <div style="height: 100%; background: <?= $cat['color'] ?>; width: <?= $percentage ?>%;"></div>
                                        </div>
                                        <span style="font-weight: 600; font-size: 13px;"><?= number_format($percentage, 1) ?>%</span>
                                    </div>
                                </td>
                            </tr>
                            <?php 
                            $rank++;
                            endwhile; 
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Top Users Table -->
            <div class="card">
                <div class="card-header">
                    <h2>👥 Top Người dùng</h2>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Hạng</th>
                                <th>Người dùng</th>
                                <th>Giao dịch</th>
                                <th>Thu nhập</th>
                                <th>Chi tiêu</th>
                                <th>Số dư</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $rank = 1;
                            while ($u = $top_users->fetch_assoc()): 
                                $user_balance = $u['total_income'] - $u['total_expense'];
                            ?>
                            <tr>
                                <td>
                                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; background: <?= $rank <= 3 ? '#FCD34D' : '#E5E7EB' ?>; font-weight: 700; font-size: 13px;">
                                        <?= $rank ?>
                                    </span>
                                </td>
                                <td>
                                    <div>
                                        <div style="font-weight: 600;"><?= htmlspecialchars($u['full_name']) ?></div>
                                        <div style="font-size: 12px; color: #6B7280;"><?= htmlspecialchars($u['email']) ?></div>
                                    </div>
                                </td>
                                <td><?= number_format($u['transaction_count']) ?></td>
                                <td style="color: #10B981; font-weight: 600;">+<?= number_format($u['total_income']) ?>đ</td>
                                <td style="color: #EF4444; font-weight: 600;">-<?= number_format($u['total_expense']) ?>đ</td>
                                <td style="color: <?= $user_balance >= 0 ? '#10B981' : '#EF4444' ?>; font-weight: 700;">
                                    <?= number_format($user_balance) ?>đ
                                </td>
                            </tr>
                            <?php 
                            $rank++;
                            endwhile; 
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/admin.js"></script>
    <script>
        // Monthly Comparison Chart
        const monthlyData = <?= json_encode($monthly_data) ?>;
        new Chart(document.getElementById('monthlyChart'), {
            type: 'bar',
            data: {
                labels: monthlyData.map(d => d.month),
                datasets: [
                    {
                        label: 'Thu nhập',
                        data: monthlyData.map(d => d.income),
                        backgroundColor: 'rgba(16, 185, 129, 0.8)',
                        borderColor: '#10B981',
                        borderWidth: 1
                    },
                    {
                        label: 'Chi tiêu',
                        data: monthlyData.map(d => d.expense),
                        backgroundColor: 'rgba(239, 68, 68, 0.8)',
                        borderColor: '#EF4444',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.parsed.y.toLocaleString('vi-VN') + 'đ';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return (value / 1000000).toFixed(1) + 'M';
                            }
                        }
                    }
                }
            }
        });

        // Category Pie Chart
        <?php 
        $cat_names = [];
        $cat_amounts = [];
        $cat_colors = [];
        $top_categories->data_seek(0);
        while ($cat = $top_categories->fetch_assoc()) {
            $cat_names[] = $cat['name'];
            $cat_amounts[] = $cat['total'];
            $cat_colors[] = $cat['color'];
        }
        ?>
        new Chart(document.getElementById('categoryPieChart'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($cat_names) ?>,
                datasets: [{
                    data: <?= json_encode($cat_amounts) ?>,
                    backgroundColor: <?= json_encode($cat_colors) ?>,
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                return context.label + ': ' + context.parsed.toLocaleString('vi-VN') + 'đ (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });

        // Daily Trend Chart
        <?php 
        $daily_dates = [];
        $daily_income = [];
        $daily_expense = [];
        while ($day = $daily_trend->fetch_assoc()) {
            $daily_dates[] = date('d/m', strtotime($day['date']));
            $daily_income[] = $day['income'];
            $daily_expense[] = $day['expense'];
        }
        ?>
        new Chart(document.getElementById('dailyTrendChart'), {
            type: 'line',
            data: {
                labels: <?= json_encode($daily_dates) ?>,
                datasets: [
                    {
                        label: 'Thu nhập',
                        data: <?= json_encode($daily_income) ?>,
                        borderColor: '#10B981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Chi tiêu',
                        data: <?= json_encode($daily_expense) ?>,
                        borderColor: '#EF4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        tension: 0.4,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return (value / 1000).toFixed(0) + 'K';
                            }
                        }
                    }
                }
            }
        });

        // Income Categories Chart
        <?php 
        $income_names = [];
        $income_amounts = [];
        $income_colors = [];
        while ($inc = $top_income_cats->fetch_assoc()) {
            $income_names[] = $inc['name'];
            $income_amounts[] = $inc['total'];
            $income_colors[] = $inc['color'];
        }
        ?>
        new Chart(document.getElementById('incomeChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($income_names) ?>,
                datasets: [{
                    label: 'Thu nhập',
                    data: <?= json_encode($income_amounts) ?>,
                    backgroundColor: <?= json_encode($income_colors) ?>,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return (value / 1000000).toFixed(1) + 'M';
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
