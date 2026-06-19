<?php
// session_start();
require_once '../connect.php';

// TEMPORARY: Disable login check for development
// if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
//     header('Location: login.php');
//     exit();
// }

// Use demo user for now
$user_id = 1; // Default admin user

// Get user info - if not exists, use default
$user_query = "SELECT * FROM users WHERE id = ? LIMIT 1";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
} else {
    // Default user if database is empty
    $user = [
        'id' => 1,
        'full_name' => 'Administrator',
        'email' => 'admin@fintrack.vn',
        'role' => 'admin'
    ];
}

// ============================================
// STATISTICS - REAL DATA FROM DATABASE
// ============================================

$stats = [];

// Total users
$result = $conn->query("SELECT COUNT(*) as total FROM users");
$stats['total_users'] = $result->fetch_assoc()['total'];

// Total transactions (all time)
$result = $conn->query("SELECT COUNT(*) as total FROM transactions");
$stats['total_transactions'] = $result->fetch_assoc()['total'];

// Total transactions this month
$result = $conn->query("SELECT COUNT(*) as total FROM transactions WHERE MONTH(transaction_date) = MONTH(CURDATE()) AND YEAR(transaction_date) = YEAR(CURDATE())");
$stats['monthly_transactions'] = $result->fetch_assoc()['total'];

// Total income this month
$result = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE type = 'income' AND MONTH(transaction_date) = MONTH(CURDATE()) AND YEAR(transaction_date) = YEAR(CURDATE())");
$stats['monthly_income'] = $result->fetch_assoc()['total'];

// Total expense this month
$result = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE type = 'expense' AND MONTH(transaction_date) = MONTH(CURDATE()) AND YEAR(transaction_date) = YEAR(CURDATE())");
$stats['monthly_expense'] = $result->fetch_assoc()['total'];

// Balance this month
$stats['monthly_balance'] = $stats['monthly_income'] - $stats['monthly_expense'];

// Total income (all time)
$result = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE type = 'income'");
$stats['total_income'] = $result->fetch_assoc()['total'];

// Total expense (all time)
$result = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE type = 'expense'");
$stats['total_expense'] = $result->fetch_assoc()['total'];

// Active users
$result = $conn->query("SELECT COUNT(*) as total FROM users");
$stats['active_users'] = $result->fetch_assoc()['total'];

// Total categories
$result = $conn->query("SELECT COUNT(*) as total FROM categories");
$stats['total_categories'] = $result->fetch_assoc()['total'];

// Total wallets
$result = $conn->query("SELECT COUNT(*) as total FROM wallets");
$stats['total_wallets'] = $result->fetch_assoc()['total'];

// Total budgets
$result = $conn->query("SELECT COUNT(*) as total FROM budgets");
$stats['total_budgets'] = $result->fetch_assoc()['total'];

// ============================================
// RECENT TRANSACTIONS
// ============================================
$recent_tx_query = "SELECT t.*, u.full_name, c.name as category_name, c.icon 
                    FROM transactions t 
                    JOIN users u ON t.user_id = u.id 
                    JOIN categories c ON t.category_id = c.id 
                    ORDER BY t.created_at DESC LIMIT 10";
$recent_transactions = $conn->query($recent_tx_query);

// ============================================
// MONTHLY CHART DATA (Last 6 months)
// ============================================
$chart_data = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $month_name = date('M Y', strtotime("-$i months"));
    
    $income_query = "SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE type = 'income' AND DATE_FORMAT(transaction_date, '%Y-%m') = '$month'";
    $expense_query = "SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE type = 'expense' AND DATE_FORMAT(transaction_date, '%Y-%m') = '$month'";
    
    $income = $conn->query($income_query)->fetch_assoc()['total'];
    $expense = $conn->query($expense_query)->fetch_assoc()['total'];
    
    $chart_data[] = [
        'month' => $month_name,
        'income' => $income,
        'expense' => $expense
    ];
}

// ============================================
// TOP USERS BY TRANSACTION COUNT
// ============================================
$top_users_query = "SELECT u.full_name, u.email, u.avatar,
                    COUNT(t.id) as tx_count, 
                    COALESCE(SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE 0 END), 0) as total_income,
                    COALESCE(SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END), 0) as total_expense
                    FROM users u 
                    LEFT JOIN transactions t ON u.id = t.user_id 
                    GROUP BY u.id 
                    HAVING tx_count > 0
                    ORDER BY tx_count DESC 
                    LIMIT 5";
$top_users = $conn->query($top_users_query);

// ============================================
// CATEGORY SPENDING (This month)
// ============================================
$cat_spending_query = "SELECT c.name, c.icon, c.color, COALESCE(SUM(t.amount), 0) as total 
                       FROM categories c 
                       LEFT JOIN transactions t ON c.id = t.category_id AND t.type = 'expense' 
                       AND MONTH(t.transaction_date) = MONTH(CURDATE()) 
                       AND YEAR(t.transaction_date) = YEAR(CURDATE())
                       WHERE c.type = 'expense'
                       GROUP BY c.id 
                       HAVING total > 0
                       ORDER BY total DESC 
                       LIMIT 6";
$cat_spending = $conn->query($cat_spending_query);

// ============================================
// QUICK STATS FOR CARDS
// ============================================
// Growth comparison (this month vs last month)
$last_month_income = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE type = 'income' AND MONTH(transaction_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(transaction_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))")->fetch_assoc()['total'];
$last_month_expense = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE type = 'expense' AND MONTH(transaction_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(transaction_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))")->fetch_assoc()['total'];

$income_growth = $last_month_income > 0 ? (($stats['monthly_income'] - $last_month_income) / $last_month_income) * 100 : 0;
$expense_growth = $last_month_expense > 0 ? (($stats['monthly_expense'] - $last_month_expense) / $last_month_expense) * 100 : 0;

// System health
$system_health = [
    'users' => $stats['total_users'],
    'transactions' => $stats['total_transactions'],
    'categories' => $stats['total_categories'],
    'wallets' => $stats['total_wallets']
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - FinTrack Pro</title>
    <link rel="stylesheet" href="assets/css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <?php include 'includes/header.php'; ?>

        <div class="content">
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1>Dashboard</h1>
                    <p>Chào mừng trở lại, <strong><?= htmlspecialchars($user['full_name']) ?></strong> 👋</p>
                </div>
                <div style="display: flex; gap: 8px;">
                    <button onclick="window.location.href='reports.php'" style="padding: 10px 20px; background: white; border: 1px solid #E5E7EB; border-radius: 8px; font-weight: 600; cursor: pointer;">
                        📊 Xem báo cáo
                    </button>
                    <button onclick="window.location.href='transaction-add.php'" style="padding: 10px 20px; background: #6366F1; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                        + Thêm giao dịch
                    </button>
                </div>
            </div>

            <!-- Main Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(99, 102, 241, 0.1); color: #6366F1;">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Tổng người dùng</div>
                        <div class="stat-value"><?= number_format($stats['total_users']) ?></div>
                        <div style="font-size: 12px; color: #6B7280; margin-top: 4px;">
                            <?= number_format($stats['active_users']) ?> đang hoạt động
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: #10B981;">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="1" x2="12" y2="23"/>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Thu nhập tháng này</div>
                        <div class="stat-value" style="color: #10B981;">+<?= number_format($stats['monthly_income']) ?>đ</div>
                        <div style="font-size: 12px; color: <?= $income_growth >= 0 ? '#10B981' : '#EF4444' ?>; margin-top: 4px;">
                            <?= $income_growth >= 0 ? '↑' : '↓' ?> <?= number_format(abs($income_growth), 1) ?>% vs tháng trước
                        </div>
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
                        <div class="stat-label">Chi tiêu tháng này</div>
                        <div class="stat-value" style="color: #EF4444;">-<?= number_format($stats['monthly_expense']) ?>đ</div>
                        <div style="font-size: 12px; color: <?= $expense_growth >= 0 ? '#EF4444' : '#10B981' ?>; margin-top: 4px;">
                            <?= $expense_growth >= 0 ? '↑' : '↓' ?> <?= number_format(abs($expense_growth), 1) ?>% vs tháng trước
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1); color: #F59E0B;">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Giao dịch tháng này</div>
                        <div class="stat-value"><?= number_format($stats['monthly_transactions']) ?></div>
                        <div style="font-size: 12px; color: #6B7280; margin-top: 4px;">
                            Tổng: <?= number_format($stats['total_transactions']) ?> giao dịch
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Overview -->
            <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px;">
                <div style="background: white; border: 1px solid #E5E7EB; border-radius: 12px; padding: 16px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                        <span style="font-size: 12px; color: #6B7280; font-weight: 600;">DANH MỤC</span>
                        <span style="font-size: 20px;">🏷️</span>
                    </div>
                    <div style="font-size: 24px; font-weight: 700;"><?= number_format($stats['total_categories']) ?></div>
                </div>
                <div style="background: white; border: 1px solid #E5E7EB; border-radius: 12px; padding: 16px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                        <span style="font-size: 12px; color: #6B7280; font-weight: 600;">VÍ TIỀN</span>
                        <span style="font-size: 20px;">💰</span>
                    </div>
                    <div style="font-size: 24px; font-weight: 700;"><?= number_format($stats['total_wallets']) ?></div>
                </div>
                <div style="background: white; border: 1px solid #E5E7EB; border-radius: 12px; padding: 16px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                        <span style="font-size: 12px; color: #6B7280; font-weight: 600;">NGÂN SÁCH</span>
                        <span style="font-size: 20px;">📊</span>
                    </div>
                    <div style="font-size: 24px; font-weight: 700;"><?= number_format($stats['total_budgets']) ?></div>
                </div>
                <div style="background: white; border: 1px solid #E5E7EB; border-radius: 12px; padding: 16px;">
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                        <span style="font-size: 12px; color: #6B7280; font-weight: 600;">SỐ DƯ THÁNG</span>
                        <span style="font-size: 20px;">💵</span>
                    </div>
                    <div style="font-size: 24px; font-weight: 700; color: <?= $stats['monthly_balance'] >= 0 ? '#10B981' : '#EF4444' ?>;">
                        <?= number_format($stats['monthly_balance']) ?>đ
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 24px;">
                <div class="card">
                    <div class="card-header">
                        <h2>📈 Thu Chi 6 Tháng Gần Nhất</h2>
                    </div>
                    <div style="padding: 24px;">
                        <canvas id="incomeExpenseChart" height="300"></canvas>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2>🎯 Chi Tiêu Theo Danh Mục</h2>
                    </div>
                    <div style="padding: 24px;">
                        <canvas id="categoryChart" height="300"></canvas>
                    </div>
                </div>
            </div>

            <!-- Recent Transactions & Top Users -->
            <div style="display: grid; grid-template-columns: 1.5fr 1fr; gap: 24px; margin-bottom: 24px;">
                <!-- Recent Transactions -->
                <div class="card">
                    <div class="card-header">
                        <h2>🕐 Giao Dịch Gần Đây</h2>
                        <a href="transactions.php" style="color: #6366F1; text-decoration: none; font-size: 13px; font-weight: 600;">Xem tất cả →</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Người dùng</th>
                                    <th>Danh mục</th>
                                    <th>Mô tả</th>
                                    <th>Số tiền</th>
                                    <th>Ngày</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($recent_transactions->num_rows > 0): ?>
                                    <?php while ($tx = $recent_transactions->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($tx['full_name']) ?></td>
                                        <td>
                                            <span style="margin-right: 4px;"><?= $tx['icon'] ?></span>
                                            <?= htmlspecialchars($tx['category_name']) ?>
                                        </td>
                                        <td><?= htmlspecialchars($tx['description']) ?></td>
                                        <td style="font-weight: 600; color: <?= $tx['type'] == 'income' ? '#10B981' : '#EF4444' ?>;">
                                            <?= $tx['type'] == 'income' ? '+' : '-' ?><?= number_format($tx['amount']) ?>đ
                                        </td>
                                        <td><?= date('d/m/Y', strtotime($tx['transaction_date'])) ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center; padding: 40px; color: #6B7280;">
                                            Chưa có giao dịch nào
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Top Users -->
                <div class="card">
                    <div class="card-header">
                        <h2>👥 Top Người Dùng</h2>
                    </div>
                    <div style="padding: 16px;">
                        <?php if ($top_users->num_rows > 0): ?>
                            <?php 
                            $rank = 1;
                            while ($u = $top_users->fetch_assoc()): 
                            ?>
                            <div style="display: flex; align-items: center; gap: 12px; padding: 12px; border-bottom: 1px solid #E5E7EB;">
                                <div style="width: 28px; height: 28px; border-radius: 50%; background: <?= $rank <= 3 ? '#FCD34D' : '#E5E7EB' ?>; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px; flex-shrink: 0;">
                                    <?= $rank ?>
                                </div>
                                <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #6366F1, #8B5CF6); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; flex-shrink: 0;">
                                    <?= strtoupper(substr($u['full_name'], 0, 2)) ?>
                                </div>
                                <div style="flex: 1; min-width: 0;">
                                    <div style="font-weight: 600; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        <?= htmlspecialchars($u['full_name']) ?>
                                    </div>
                                    <div style="font-size: 12px; color: #6B7280;">
                                        <?= number_format($u['tx_count']) ?> giao dịch
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-size: 13px; font-weight: 600; color: #EF4444;">
                                        <?= number_format($u['total_expense']) ?>đ
                                    </div>
                                </div>
                            </div>
                            <?php 
                            $rank++;
                            endwhile; 
                            ?>
                        <?php else: ?>
                            <div style="text-align: center; padding: 40px; color: #6B7280;">
                                Chưa có dữ liệu
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/admin.js"></script>
    <script>
        // Chart data from PHP
        const chartData = <?= json_encode($chart_data) ?>;
        
        // Income vs Expense Chart
        const incomeExpenseCtx = document.getElementById('incomeExpenseChart').getContext('2d');
        new Chart(incomeExpenseCtx, {
            type: 'bar',
            data: {
                labels: chartData.map(d => d.month),
                datasets: [
                    {
                        label: 'Thu nhập',
                        data: chartData.map(d => d.income),
                        backgroundColor: 'rgba(16, 185, 129, 0.8)',
                        borderColor: 'rgba(16, 185, 129, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Chi tiêu',
                        data: chartData.map(d => d.expense),
                        backgroundColor: 'rgba(239, 68, 68, 0.8)',
                        borderColor: 'rgba(239, 68, 68, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
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

        // Category Spending Chart
        <?php 
        $cat_names = [];
        $cat_amounts = [];
        $cat_colors = [];
        if ($cat_spending->num_rows > 0) {
            $cat_spending->data_seek(0);
            while ($cat = $cat_spending->fetch_assoc()) {
                $cat_names[] = $cat['name'];
                $cat_amounts[] = $cat['total'];
                $cat_colors[] = $cat['color'];
            }
        }
        ?>
        
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        new Chart(categoryCtx, {
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
                    legend: {
                        position: 'right',
                    },
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
    </script>
</body>
</html>
