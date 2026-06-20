<?php
session_start();
require_once __DIR__ . '/../connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit;
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'];

// Period filter
$period = $_GET['period'] ?? 'month';
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');

// Calculate date range
switch($period) {
    case 'week':
        $startDate = date('Y-m-d', strtotime('monday this week'));
        $endDate = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'month':
        $startDate = "$year-$month-01";
        $endDate = date('Y-m-t', strtotime($startDate));
        break;
    case 'year':
        $startDate = "$year-01-01";
        $endDate = "$year-12-31";
        break;
    default:
        $startDate = date('Y-m-01');
        $endDate = date('Y-m-d');
}

// Summary
$stmt = $conn->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as total_income,
        COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as total_expense,
        COUNT(CASE WHEN type = 'income' THEN 1 END) as income_count,
        COUNT(CASE WHEN type = 'expense' THEN 1 END) as expense_count
    FROM transactions
    WHERE user_id = ? AND transaction_date BETWEEN ? AND ?
");
$stmt->bind_param("iss", $userId, $startDate, $endDate);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();

// Chi tiêu theo danh mục
$stmt = $conn->prepare("
    SELECT c.name, c.icon, c.color, COALESCE(SUM(t.amount), 0) as total
    FROM categories c
    LEFT JOIN transactions t ON c.id = t.category_id 
        AND t.user_id = ? 
        AND t.type = 'expense'
        AND t.transaction_date BETWEEN ? AND ?
    WHERE (c.user_id = ? OR c.is_system = 1) AND c.type = 'expense'
    GROUP BY c.id
    HAVING total > 0
    ORDER BY total DESC
    LIMIT 10
");
$stmt->bind_param("issi", $userId, $startDate, $endDate, $userId);
$stmt->execute();
$expenseByCategory = $stmt->get_result();

// Thu nhập theo danh mục
$stmt = $conn->prepare("
    SELECT c.name, c.icon, c.color, COALESCE(SUM(t.amount), 0) as total
    FROM categories c
    LEFT JOIN transactions t ON c.id = t.category_id 
        AND t.user_id = ? 
        AND t.type = 'income'
        AND t.transaction_date BETWEEN ? AND ?
    WHERE (c.user_id = ? OR c.is_system = 1) AND c.type = 'income'
    GROUP BY c.id
    HAVING total > 0
    ORDER BY total DESC
    LIMIT 10
");
$stmt->bind_param("issi", $userId, $startDate, $endDate, $userId);
$stmt->execute();
$incomeByCategory = $stmt->get_result();

// Theo dõi theo ngày/tháng
if ($period === 'year') {
    // Theo tháng
    $stmt = $conn->prepare("
        SELECT 
            MONTH(transaction_date) as period_num,
            DATE_FORMAT(transaction_date, '%m/%Y') as period_label,
            COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as income,
            COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as expense
        FROM transactions
        WHERE user_id = ? AND YEAR(transaction_date) = ?
        GROUP BY MONTH(transaction_date)
        ORDER BY MONTH(transaction_date)
    ");
    $stmt->bind_param("is", $userId, $year);
} else {
    // Theo ngày
    $stmt = $conn->prepare("
        SELECT 
            DAY(transaction_date) as period_num,
            DATE_FORMAT(transaction_date, '%d/%m') as period_label,
            COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as income,
            COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as expense
        FROM transactions
        WHERE user_id = ? AND transaction_date BETWEEN ? AND ?
        GROUP BY DATE(transaction_date)
        ORDER BY transaction_date
    ");
    $stmt->bind_param("iss", $userId, $startDate, $endDate);
}
$stmt->execute();
$timeline = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Top 5 chi tiêu lớn nhất
$stmt = $conn->prepare("
    SELECT t.*, c.name as category_name, c.icon as category_icon, w.name as wallet_name
    FROM transactions t
    LEFT JOIN categories c ON t.category_id = c.id
    LEFT JOIN wallets w ON t.wallet_id = w.id
    WHERE t.user_id = ? AND t.type = 'expense' AND t.transaction_date BETWEEN ? AND ?
    ORDER BY t.amount DESC
    LIMIT 5
");
$stmt->bind_param("iss", $userId, $startDate, $endDate);
$stmt->execute();
$topExpenses = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Báo cáo - Finora</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root {
            --primary: #2563EB; --success: #10B981; --danger: #EF4444; --warning: #F59E0B;
            --purple: #7C3AED; --bg: #F8FAFC; --card: #FFFFFF; --text: #0F172A;
            --text-light: #475569; --text-lighter: #94A3B8; --border: #E2E8F0;
            --sidebar-width: 260px;
        }
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); color: var(--text); display: flex; min-height: 100vh; }
        
        /* Sidebar */
        .sidebar { width: var(--sidebar-width); background: var(--card); border-right: 1px solid var(--border); padding: 24px 0; position: fixed; height: 100vh; overflow-y: auto; z-index: 100; }
        .logo { display: flex; align-items: center; gap: 10px; font-size: 20px; font-weight: 800; padding: 0 24px; margin-bottom: 32px; }
        .logo-icon { width: 36px; height: 36px; background: linear-gradient(135deg, var(--primary), var(--purple)); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; }
        .sidebar-menu { list-style: none; padding: 0 12px; }
        .menu-item { margin-bottom: 4px; }
        .menu-link { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 10px; color: var(--text-light); text-decoration: none; font-weight: 500; font-size: 14px; transition: all 0.2s; }
        .menu-link:hover { background: var(--bg); color: var(--primary); }
        .menu-link.active { background: linear-gradient(135deg, #EFF6FF, #F0F9FF); color: var(--primary); font-weight: 600; }
        .menu-icon { width: 20px; text-align: center; font-size: 16px; }
        .user-card { margin: 24px 12px 0; padding: 16px; background: linear-gradient(135deg, var(--primary), var(--purple)); border-radius: 12px; color: white; }
        .user-avatar { width: 48px; height: 48px; background: rgba(255, 255, 255, 0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 18px; margin-bottom: 12px; }
        
        /* Main */
        .main-content { margin-left: var(--sidebar-width); flex: 1; padding: 32px; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .page-title { font-size: 28px; font-weight: 800; }
        .page-subtitle { color: var(--text-light); font-size: 14px; margin-top: 4px; }
        
        /* Period Selector */
        .period-selector { display: flex; gap: 12px; margin-bottom: 24px; align-items: center; flex-wrap: wrap; }
        .period-btn { padding: 10px 20px; border-radius: 10px; border: 2px solid var(--border); background: var(--card); color: var(--text); font-weight: 600; cursor: pointer; transition: all 0.2s; font-size: 14px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .period-btn:hover { border-color: var(--primary); color: var(--primary); }
        .period-btn.active { border-color: var(--primary); background: var(--primary); color: white; }
        .period-select { padding: 10px 16px; border: 2px solid var(--border); border-radius: 10px; font-family: inherit; font-size: 14px; font-weight: 600; }
        
        /* Summary */
        .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 32px; }
        .summary-card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 24px; }
        .summary-icon { width: 56px; height: 56px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 28px; margin-bottom: 16px; }
        .summary-label { font-size: 13px; color: var(--text-light); margin-bottom: 8px; }
        .summary-value { font-size: 28px; font-weight: 800; margin-bottom: 6px; }
        .summary-sub { font-size: 12px; color: var(--text-lighter); }
        
        /* Charts */
        .charts-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-bottom: 24px; }
        .chart-card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 24px; }
        .chart-header { margin-bottom: 20px; }
        .chart-title { font-size: 18px; font-weight: 700; margin-bottom: 4px; }
        .chart-subtitle { font-size: 13px; color: var(--text-light); }
        .chart-container { position: relative; height: 300px; }
        
        /* Category List */
        .category-list { display: flex; flex-direction: column; gap: 12px; }
        .category-item { display: flex; align-items: center; gap: 12px; padding: 12px; background: var(--bg); border-radius: 10px; }
        .category-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 22px; }
        .category-info { flex: 1; }
        .category-name { font-weight: 600; font-size: 14px; margin-bottom: 4px; }
        .category-bar { height: 6px; background: var(--border); border-radius: 3px; overflow: hidden; }
        .category-bar-fill { height: 100%; border-radius: 3px; transition: width 0.6s; }
        .category-amount { font-size: 15px; font-weight: 700; text-align: right; }
        .category-percent { font-size: 11px; color: var(--text-lighter); }
        
        /* Top Expenses */
        .top-list { display: flex; flex-direction: column; gap: 10px; }
        .top-item { display: flex; align-items: center; gap: 12px; padding: 12px; background: var(--bg); border-radius: 10px; }
        .top-rank { width: 32px; height: 32px; border-radius: 8px; background: linear-gradient(135deg, var(--primary), var(--purple)); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px; }
        .top-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
        .top-info { flex: 1; }
        .top-desc { font-weight: 600; font-size: 14px; margin-bottom: 2px; }
        .top-meta { font-size: 11px; color: var(--text-lighter); }
        .top-amount { font-size: 16px; font-weight: 700; color: var(--danger); }
        
        @media (max-width: 1024px) {
            .charts-grid { grid-template-columns: 1fr; }
            .summary-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .sidebar { width: 0; overflow: hidden; }
            .main-content { margin-left: 0; padding: 20px; }
            .summary-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="logo"><div class="logo-icon">💰</div><span>Fi<span style="color: var(--primary)">no</span>ra</span></div>
        <ul class="sidebar-menu">
            <li class="menu-item"><a href="dashboard.php" class="menu-link"><i class="menu-icon fas fa-chart-line"></i><span>Dashboard</span></a></li>
            <li class="menu-item"><a href="accounts.php" class="menu-link"><i class="menu-icon fas fa-wallet"></i><span>Tài khoản</span></a></li>
            <li class="menu-item"><a href="transactions.php" class="menu-link"><i class="menu-icon fas fa-exchange-alt"></i><span>Giao dịch</span></a></li>
            <li class="menu-item"><a href="budgets.php" class="menu-link"><i class="menu-icon fas fa-chart-pie"></i><span>Ngân sách</span></a></li>
            <li class="menu-item"><a href="budget-allocation.php" class="menu-link"><i class="menu-icon fas fa-percentage"></i><span>Phân bổ Thu nhập</span></a></li>
            <li class="menu-item"><a href="reports.php" class="menu-link active"><i class="menu-icon fas fa-file-alt"></i><span>Báo cáo</span></a></li>
            <li class="menu-item"><a href="cards.php" class="menu-link"><i class="menu-icon fas fa-credit-card"></i><span>Thẻ</span></a></li>
            <li class="menu-item"><a href="settings.php" class="menu-link"><i class="menu-icon fas fa-cog"></i><span>Cài đặt</span></a></li>
        </ul>
        <div class="user-card">
            <div class="user-avatar"><?php 
                $names = explode(' ', $userName);
                echo strtoupper(substr($names[0], 0, 1));
                if (count($names) > 1) echo strtoupper(substr($names[count($names) - 1], 0, 1));
            ?></div>
            <div style="font-weight: 700; font-size: 15px; margin-bottom: 4px;"><?= htmlspecialchars($userName) ?></div>
            <div style="font-size: 12px;"><a href="logout.php" style="color: rgba(255,255,255,0.9); text-decoration: none;"><i class="fas fa-sign-out-alt"></i> Đăng xuất</a></div>
        </div>
    </aside>

    <main class="main-content">
        <div class="top-bar">
            <div>
                <h1 class="page-title">📊 Báo cáo tài chính</h1>
                <p class="page-subtitle">Phân tích thu chi và xu hướng chi tiêu</p>
            </div>
        </div>

        <!-- Period Selector -->
        <div class="period-selector">
            <a href="?period=week" class="period-btn <?= $period === 'week' ? 'active' : '' ?>">
                <i class="fas fa-calendar-week"></i> Tuần này
            </a>
            <a href="?period=month&month=<?= $month ?>&year=<?= $year ?>" class="period-btn <?= $period === 'month' ? 'active' : '' ?>">
                <i class="fas fa-calendar-alt"></i> Tháng này
            </a>
            <a href="?period=year&year=<?= $year ?>" class="period-btn <?= $period === 'year' ? 'active' : '' ?>">
                <i class="fas fa-calendar"></i> Năm này
            </a>
            
            <?php if ($period === 'month'): ?>
                <select class="period-select" onchange="window.location.href='?period=month&month='+this.value.split('-')[1]+'&year='+this.value.split('-')[0]">
                    <?php for ($m = 1; $m <= 12; $m++): 
                        $mStr = str_pad($m, 2, '0', STR_PAD_LEFT);
                        $selected = ($mStr == $month && $year == date('Y')) ? 'selected' : '';
                    ?>
                        <option value="<?= $year ?>-<?= $mStr ?>" <?= $selected ?>><?= $mStr ?>/<?= $year ?></option>
                    <?php endfor; ?>
                </select>
            <?php endif; ?>
            
            <?php if ($period === 'year'): ?>
                <select class="period-select" onchange="window.location.href='?period=year&year='+this.value">
                    <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                        <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            <?php endif; ?>
        </div>

        <!-- Summary -->
        <div class="summary-grid">
            <div class="summary-card">
                <div class="summary-icon" style="background: #D1FAE5; color: var(--success);">
                    <i class="fas fa-arrow-down"></i>
                </div>
                <div class="summary-label">Tổng thu nhập</div>
                <div class="summary-value" style="color: var(--success);">
                    <?= number_format($summary['total_income'], 0, ',', '.') ?>đ
                </div>
                <div class="summary-sub"><?= $summary['income_count'] ?> giao dịch</div>
            </div>

            <div class="summary-card">
                <div class="summary-icon" style="background: #FEE2E2; color: var(--danger);">
                    <i class="fas fa-arrow-up"></i>
                </div>
                <div class="summary-label">Tổng chi tiêu</div>
                <div class="summary-value" style="color: var(--danger);">
                    <?= number_format($summary['total_expense'], 0, ',', '.') ?>đ
                </div>
                <div class="summary-sub"><?= $summary['expense_count'] ?> giao dịch</div>
            </div>

            <div class="summary-card">
                <div class="summary-icon" style="background: #DBEAFE; color: var(--primary);">
                    <i class="fas fa-wallet"></i>
                </div>
                <div class="summary-label">Chênh lệch</div>
                <div class="summary-value" style="color: <?= ($summary['total_income'] - $summary['total_expense']) >= 0 ? 'var(--success)' : 'var(--danger)' ?>;">
                    <?= number_format($summary['total_income'] - $summary['total_expense'], 0, ',', '.') ?>đ
                </div>
                <div class="summary-sub">
                    <?= ($summary['total_income'] - $summary['total_expense']) >= 0 ? 'Dương' : 'Âm' ?>
                </div>
            </div>

            <div class="summary-card">
                <div class="summary-icon" style="background: #FEF3C7; color: var(--warning);">
                    <i class="fas fa-percent"></i>
                </div>
                <div class="summary-label">Tỷ lệ tiết kiệm</div>
                <div class="summary-value" style="color: var(--warning);">
                    <?php 
                    $savingRate = $summary['total_income'] > 0 
                        ? (($summary['total_income'] - $summary['total_expense']) / $summary['total_income'] * 100)
                        : 0;
                    echo number_format($savingRate, 1);
                    ?>%
                </div>
                <div class="summary-sub">
                    <?= $savingRate >= 20 ? 'Tốt' : ($savingRate >= 10 ? 'Trung bình' : 'Cần cải thiện') ?>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="charts-grid">
            <!-- Timeline Chart -->
            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title">📈 Biểu đồ thu chi</div>
                    <div class="chart-subtitle">Theo dõi xu hướng <?= $period === 'year' ? 'theo tháng' : 'theo ngày' ?></div>
                </div>
                <div class="chart-container">
                    <canvas id="timelineChart"></canvas>
                </div>
            </div>

            <!-- Pie Chart -->
            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title">🥧 Chi tiêu theo danh mục</div>
                    <div class="chart-subtitle">Top 10 danh mục</div>
                </div>
                <div class="chart-container">
                    <canvas id="pieChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Bottom Grid -->
        <div class="charts-grid">
            <!-- Expense by Category -->
            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title">💸 Chi tiêu theo danh mục</div>
                    <div class="chart-subtitle">Chi tiết phân bổ ngân sách</div>
                </div>
                <?php if ($expenseByCategory->num_rows > 0): 
                    $totalExpense = $summary['total_expense'];
                ?>
                    <div class="category-list">
                        <?php while ($cat = $expenseByCategory->fetch_assoc()): 
                            $percent = $totalExpense > 0 ? ($cat['total'] / $totalExpense * 100) : 0;
                        ?>
                            <div class="category-item">
                                <div class="category-icon" style="background: <?= $cat['color'] ?>20;">
                                    <?= htmlspecialchars($cat['icon']) ?>
                                </div>
                                <div class="category-info">
                                    <div class="category-name"><?= htmlspecialchars($cat['name']) ?></div>
                                    <div class="category-bar">
                                        <div class="category-bar-fill" style="width: <?= $percent ?>%; background: <?= $cat['color'] ?>;"></div>
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <div class="category-amount" style="color: <?= $cat['color'] ?>;">
                                        <?= number_format($cat['total'], 0, ',', '.') ?>đ
                                    </div>
                                    <div class="category-percent"><?= number_format($percent, 1) ?>%</div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: var(--text-light);">
                        <div style="font-size: 48px; margin-bottom: 12px;">📭</div>
                        <p>Chưa có chi tiêu nào</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Top 5 Expenses -->
            <div class="chart-card">
                <div class="chart-header">
                    <div class="chart-title">🏆 Top 5 chi tiêu lớn nhất</div>
                    <div class="chart-subtitle">Các khoản chi nổi bật</div>
                </div>
                <?php if ($topExpenses->num_rows > 0): ?>
                    <div class="top-list">
                        <?php $rank = 1; while ($trans = $topExpenses->fetch_assoc()): ?>
                            <div class="top-item">
                                <div class="top-rank"><?= $rank ?></div>
                                <div class="top-icon" style="background: var(--bg);">
                                    <?= htmlspecialchars($trans['category_icon']) ?>
                                </div>
                                <div class="top-info">
                                    <div class="top-desc"><?= htmlspecialchars($trans['description']) ?></div>
                                    <div class="top-meta">
                                        <?= htmlspecialchars($trans['category_name']) ?> • 
                                        <?= date('d/m/Y', strtotime($trans['transaction_date'])) ?>
                                    </div>
                                </div>
                                <div class="top-amount">
                                    <?= number_format($trans['amount'], 0, ',', '.') ?>đ
                                </div>
                            </div>
                        <?php $rank++; endwhile; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: var(--text-light);">
                        <div style="font-size: 48px; margin-bottom: 12px;">📭</div>
                        <p>Chưa có chi tiêu nào</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        // Timeline Chart
        const timelineData = <?= json_encode($timeline) ?>;
        const timelineLabels = timelineData.map(d => d.period_label);
        const timelineIncome = timelineData.map(d => parseFloat(d.income));
        const timelineExpense = timelineData.map(d => parseFloat(d.expense));

        const timelineChart = new Chart(document.getElementById('timelineChart'), {
            type: 'line',
            data: {
                labels: timelineLabels,
                datasets: [
                    {
                        label: 'Thu nhập',
                        data: timelineIncome,
                        borderColor: '#10B981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Chi tiêu',
                        data: timelineExpense,
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
                    legend: {
                        display: true,
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return new Intl.NumberFormat('vi-VN').format(value) + 'đ';
                            }
                        }
                    }
                }
            }
        });

        // Pie Chart
        <?php 
        $expenseByCategory->data_seek(0);
        $pieData = [];
        $pieLabels = [];
        $pieColors = [];
        while ($cat = $expenseByCategory->fetch_assoc()) {
            $pieLabels[] = $cat['name'];
            $pieData[] = $cat['total'];
            $pieColors[] = $cat['color'];
        }
        ?>
        
        const pieChart = new Chart(document.getElementById('pieChart'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($pieLabels) ?>,
                datasets: [{
                    data: <?= json_encode($pieData) ?>,
                    backgroundColor: <?= json_encode($pieColors) ?>,
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = new Intl.NumberFormat('vi-VN').format(context.parsed);
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percent = ((context.parsed / total) * 100).toFixed(1);
                                return label + ': ' + value + 'đ (' + percent + '%)';
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
