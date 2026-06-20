<?php
session_start();
require_once __DIR__ . '/../connect.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit;
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'];

// Xử lý thêm/sửa ngân sách
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $categoryId = $_POST['category_id'];
            $name = $_POST['name'];
            $amount = $_POST['amount'];
            $period = $_POST['period'];
            $startDate = $_POST['start_date'];
            $endDate = $_POST['end_date'];
            
            $stmt = $conn->prepare("INSERT INTO budgets (user_id, category_id, name, amount, period, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisdsss", $userId, $categoryId, $name, $amount, $period, $startDate, $endDate);
            $stmt->execute();
            
            header('Location: budgets.php?success=add');
            exit;
        }
    }
}

// Lấy danh sách ngân sách đang active
$stmt = $conn->prepare("
    SELECT b.*, c.name as category_name, c.icon as category_icon, c.color as category_color,
           COALESCE(SUM(t.amount), 0) as spent
    FROM budgets b
    LEFT JOIN categories c ON b.category_id = c.id
    LEFT JOIN transactions t ON t.category_id = b.category_id 
        AND t.user_id = b.user_id 
        AND t.type = 'expense'
        AND t.transaction_date BETWEEN b.start_date AND b.end_date
    WHERE b.user_id = ? AND b.is_active = 1
        AND CURDATE() BETWEEN b.start_date AND b.end_date
    GROUP BY b.id
    ORDER BY (COALESCE(SUM(t.amount), 0) / b.amount) DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$budgets = $stmt->get_result();

// Tổng thu chi tháng này
$stmt = $conn->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as total_income,
        COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as total_expense
    FROM transactions
    WHERE user_id = ? 
    AND MONTH(transaction_date) = MONTH(CURDATE())
    AND YEAR(transaction_date) = YEAR(CURDATE())
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();

// Lấy danh sách categories để chọn
$categoriesQuery = $conn->query("SELECT * FROM categories WHERE (user_id = $userId OR is_system = 1) AND type = 'expense' ORDER BY name");

// Tính toán số dư tháng này
$balance = $summary['total_income'] - $summary['total_expense'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ngân sách - Finora</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #2563EB;
            --success: #10B981;
            --danger: #EF4444;
            --warning: #F59E0B;
            --purple: #7C3AED;
            --orange: #F97316;
            --bg: #F8FAFC;
            --card: #FFFFFF;
            --text: #0F172A;
            --text-light: #475569;
            --text-lighter: #94A3B8;
            --border: #E2E8F0;
            --sidebar-width: 260px;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar - giống dashboard */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--card);
            border-right: 1px solid var(--border);
            padding: 24px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 100;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 20px;
            font-weight: 800;
            padding: 0 24px;
            margin-bottom: 32px;
        }

        .logo-icon {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--primary), var(--purple));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0 12px;
        }

        .menu-item {
            margin-bottom: 4px;
        }

        .menu-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-radius: 10px;
            color: var(--text-light);
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s;
        }

        .menu-link:hover {
            background: var(--bg);
            color: var(--primary);
        }

        .menu-link.active {
            background: linear-gradient(135deg, #EFF6FF, #F0F9FF);
            color: var(--primary);
            font-weight: 600;
        }

        .menu-icon {
            width: 20px;
            text-align: center;
            font-size: 16px;
        }

        .user-card {
            margin: 24px 12px 0;
            padding: 16px;
            background: linear-gradient(135deg, var(--primary), var(--purple));
            border-radius: 12px;
            color: white;
        }

        .user-avatar {
            width: 48px;
            height: 48px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 18px;
            margin-bottom: 12px;
        }

        .user-name-card {
            font-weight: 700;
            font-size: 15px;
            margin-bottom: 4px;
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            flex: 1;
            padding: 32px;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }

        .page-title {
            font-size: 28px;
            font-weight: 800;
        }

        .page-subtitle {
            color: var(--text-light);
            font-size: 14px;
            margin-top: 4px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-family: inherit;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
        }

        .btn-ghost {
            background: var(--card);
            color: var(--text);
            border: 1px solid var(--border);
        }

        /* Summary Cards */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 32px;
        }

        .summary-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        .summary-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
        }

        .summary-card.income::before { background: var(--success); }
        .summary-card.expense::before { background: var(--danger); }
        .summary-card.balance::before { background: var(--primary); }
        .summary-card.saving::before { background: var(--warning); }

        .summary-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 12px;
        }

        .summary-label {
            font-size: 13px;
            color: var(--text-light);
            margin-bottom: 8px;
        }

        .summary-amount {
            font-size: 24px;
            font-weight: 800;
        }

        .summary-change {
            font-size: 12px;
            font-weight: 600;
            margin-top: 6px;
        }

        /* Budget Jars Grid */
        .budget-jars-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .budget-jars-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .budget-jar {
            background: var(--card);
            border: 2px solid var(--border);
            border-radius: 20px;
            padding: 24px;
            transition: all 0.3s;
            position: relative;
        }

        .budget-jar:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.08);
            border-color: var(--primary);
        }

        .budget-jar.warning {
            border-color: var(--warning);
            background: linear-gradient(to bottom, #FFFBEB 0%, var(--card) 30%);
        }

        .budget-jar.danger {
            border-color: var(--danger);
            background: linear-gradient(to bottom, #FEF2F2 0%, var(--card) 30%);
        }

        .jar-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
        }

        .jar-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .jar-info {
            flex: 1;
        }

        .jar-name {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .jar-period {
            font-size: 12px;
            color: var(--text-light);
        }

        .jar-amounts {
            margin-bottom: 16px;
        }

        .jar-spent {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 4px;
        }

        .jar-budget {
            font-size: 13px;
            color: var(--text-light);
        }

        .jar-progress {
            margin-bottom: 12px;
        }

        .jar-progress-bar {
            height: 10px;
            background: var(--border);
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 8px;
        }

        .jar-progress-fill {
            height: 100%;
            border-radius: 10px;
            transition: width 0.6s ease;
            position: relative;
        }

        .jar-progress-fill.safe {
            background: linear-gradient(90deg, var(--success), #34D399);
        }

        .jar-progress-fill.warning {
            background: linear-gradient(90deg, var(--warning), #FBBF24);
        }

        .jar-progress-fill.danger {
            background: linear-gradient(90deg, var(--danger), #F87171);
        }

        .jar-progress-info {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            font-weight: 600;
        }

        .jar-progress-pct {
            color: var(--text-light);
        }

        .jar-remaining {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .jar-actions {
            display: flex;
            gap: 8px;
            margin-top: 16px;
        }

        .jar-btn {
            flex: 1;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid var(--border);
            background: var(--card);
            cursor: pointer;
            transition: all 0.2s;
        }

        .jar-btn:hover {
            background: var(--bg);
            border-color: var(--primary);
            color: var(--primary);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: var(--card);
            border-radius: 20px;
            padding: 32px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .modal-title {
            font-size: 22px;
            font-weight: 800;
        }

        .modal-close {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            border: none;
            background: var(--bg);
            cursor: pointer;
            font-size: 18px;
            color: var(--text-light);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text);
        }

        .form-input, .form-select {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.2s;
        }

        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--card);
            border: 2px dashed var(--border);
            border-radius: 20px;
        }

        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .empty-state-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .empty-state-text {
            color: var(--text-light);
            margin-bottom: 24px;
        }

        @media (max-width: 1024px) {
            .summary-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                overflow: hidden;
            }
            
            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .summary-cards {
                grid-template-columns: 1fr;
            }

            .budget-jars-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="logo">
            <div class="logo-icon">💰</div>
            <span>Fi<span style="color: var(--primary)">no</span>ra</span>
        </div>

        <ul class="sidebar-menu">
            <li class="menu-item">
                <a href="dashboard.php" class="menu-link">
                    <i class="menu-icon fas fa-chart-line"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="accounts.php" class="menu-link">
                    <i class="menu-icon fas fa-wallet"></i>
                    <span>Tài khoản</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="transactions.php" class="menu-link">
                    <i class="menu-icon fas fa-exchange-alt"></i>
                    <span>Giao dịch</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="budgets.php" class="menu-link active">
                    <i class="menu-icon fas fa-chart-pie"></i>
                    <span>Ngân sách</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="budget-allocation.php" class="menu-link">
                    <i class="menu-icon fas fa-percentage"></i>
                    <span>Phân bổ Thu nhập</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="reports.php" class="menu-link">
                    <i class="menu-icon fas fa-file-alt"></i>
                    <span>Báo cáo</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="cards.php" class="menu-link">
                    <i class="menu-icon fas fa-credit-card"></i>
                    <span>Thẻ</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="settings.php" class="menu-link">
                    <i class="menu-icon fas fa-cog"></i>
                    <span>Cài đặt</span>
                </a>
            </li>
        </ul>

        <div class="user-card">
            <div class="user-avatar">
                <?php 
                $names = explode(' ', $userName);
                echo strtoupper(substr($names[0], 0, 1));
                if (count($names) > 1) {
                    echo strtoupper(substr($names[count($names) - 1], 0, 1));
                }
                ?>
            </div>
            <div class="user-name-card"><?= htmlspecialchars($userName) ?></div>
            <div style="font-size: 12px;">
                <a href="logout.php" style="color: rgba(255,255,255,0.9); text-decoration: none;">
                    <i class="fas fa-sign-out-alt"></i> Đăng xuất
                </a>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div>
                <h1 class="page-title">💰 Ngân sách & Hũ chi tiêu</h1>
                <p class="page-subtitle">Quản lý ngân sách theo từng danh mục chi tiêu</p>
            </div>
            <button class="btn btn-primary" onclick="openAddModal()">
                <i class="fas fa-plus"></i>
                Thêm ngân sách
            </button>
        </div>

        <!-- Summary Cards -->
        <div class="summary-cards">
            <div class="summary-card income">
                <div class="summary-icon" style="background: #D1FAE5; color: var(--success);">
                    <i class="fas fa-arrow-down"></i>
                </div>
                <div class="summary-label">Thu nhập</div>
                <div class="summary-amount" style="color: var(--success);">
                    <?= number_format($summary['total_income'], 0, ',', '.') ?>đ
                </div>
                <div class="summary-change" style="color: var(--success);">
                    <i class="fas fa-arrow-up"></i> Tháng này
                </div>
            </div>

            <div class="summary-card expense">
                <div class="summary-icon" style="background: #FEE2E2; color: var(--danger);">
                    <i class="fas fa-arrow-up"></i>
                </div>
                <div class="summary-label">Chi tiêu</div>
                <div class="summary-amount" style="color: var(--danger);">
                    <?= number_format($summary['total_expense'], 0, ',', '.') ?>đ
                </div>
                <div class="summary-change" style="color: var(--danger);">
                    <i class="fas fa-arrow-up"></i> Tháng này
                </div>
            </div>

            <div class="summary-card balance">
                <div class="summary-icon" style="background: #DBEAFE; color: var(--primary);">
                    <i class="fas fa-wallet"></i>
                </div>
                <div class="summary-label">Số dư</div>
                <div class="summary-amount" style="color: var(--primary);">
                    <?= number_format($balance, 0, ',', '.') ?>đ
                </div>
                <div class="summary-change" style="color: <?= $balance >= 0 ? 'var(--success)' : 'var(--danger)' ?>;">
                    <?= $balance >= 0 ? 'Dương' : 'Âm' ?>
                </div>
            </div>

            <div class="summary-card saving">
                <div class="summary-icon" style="background: #FEF3C7; color: var(--warning);">
                    <i class="fas fa-piggy-bank"></i>
                </div>
                <div class="summary-label">Tiết kiệm</div>
                <div class="summary-amount" style="color: var(--warning);">
                    <?= number_format(max(0, $balance), 0, ',', '.') ?>đ
                </div>
                <div class="summary-change" style="color: var(--warning);">
                    <i class="fas fa-star"></i> Tháng này
                </div>
            </div>
        </div>

        <!-- Budget Jars -->
        <div class="budget-jars-header">
            <h2 style="font-size: 22px; font-weight: 800;">
                <i class="fas fa-jar"></i> Hũ chi tiêu của bạn
            </h2>
            <a href="#" style="color: var(--primary); text-decoration: none; font-weight: 600; font-size: 14px;">
                Quản lý hũ <i class="fas fa-arrow-right"></i>
            </a>
        </div>

        <?php if ($budgets->num_rows > 0): ?>
            <div class="budget-jars-grid">
                <?php while ($budget = $budgets->fetch_assoc()): 
                    $percentage = $budget['amount'] > 0 ? ($budget['spent'] / $budget['amount'] * 100) : 0;
                    $remaining = $budget['amount'] - $budget['spent'];
                    
                    // Xác định trạng thái
                    $status = 'safe';
                    $jarClass = '';
                    if ($percentage >= 100) {
                        $status = 'danger';
                        $jarClass = 'danger';
                    } elseif ($percentage >= 80) {
                        $status = 'warning';
                        $jarClass = 'warning';
                    }
                ?>
                <div class="budget-jar <?= $jarClass ?>">
                    <div class="jar-header">
                        <div class="jar-icon" style="background: <?= htmlspecialchars($budget['category_color']) ?>20;">
                            <span><?= htmlspecialchars($budget['category_icon']) ?></span>
                        </div>
                        <div class="jar-info">
                            <div class="jar-name"><?= htmlspecialchars($budget['category_name']) ?></div>
                            <div class="jar-period">
                                <?php
                                $periodNames = [
                                    'daily' => 'Hàng ngày',
                                    'weekly' => 'Hàng tuần',
                                    'monthly' => 'Hàng tháng',
                                    'yearly' => 'Hàng năm'
                                ];
                                echo $periodNames[$budget['period']] ?? 'Tùy chỉnh';
                                ?>
                            </div>
                        </div>
                    </div>

                    <div class="jar-amounts">
                        <div class="jar-spent" style="color: <?= $status === 'danger' ? 'var(--danger)' : ($status === 'warning' ? 'var(--warning)' : 'var(--text)') ?>;">
                            <?= number_format($budget['spent'], 0, ',', '.') ?>đ
                        </div>
                        <div class="jar-budget">
                            Hạn mức: <strong><?= number_format($budget['amount'], 0, ',', '.') ?>đ</strong>
                        </div>
                    </div>

                    <div class="jar-progress">
                        <div class="jar-progress-bar">
                            <div class="jar-progress-fill <?= $status ?>" style="width: <?= min($percentage, 100) ?>%"></div>
                        </div>
                        <div class="jar-progress-info">
                            <span class="jar-progress-pct"><?= number_format($percentage, 1) ?>%</span>
                            <span class="jar-remaining" style="color: <?= $remaining >= 0 ? 'var(--success)' : 'var(--danger)' ?>;">
                                <i class="fas fa-<?= $remaining >= 0 ? 'check-circle' : 'exclamation-circle' ?>"></i>
                                Còn <?= number_format(abs($remaining), 0, ',', '.') ?>đ
                            </span>
                        </div>
                    </div>

                    <div class="jar-actions">
                        <button class="jar-btn" onclick="editBudget(<?= $budget['id'] ?>)">
                            <i class="fas fa-edit"></i> Sửa
                        </button>
                        <button class="jar-btn" onclick="viewDetails(<?= $budget['id'] ?>)">
                            <i class="fas fa-chart-bar"></i> Chi tiết
                        </button>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">🏺</div>
                <div class="empty-state-title">Chưa có hũ chi tiêu nào</div>
                <p class="empty-state-text">
                    Tạo hũ chi tiêu đầu tiên để kiểm soát ngân sách theo từng danh mục
                </p>
                <button class="btn btn-primary" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Tạo hũ chi tiêu
                </button>
            </div>
        <?php endif; ?>
    </main>

    <!-- Modal Thêm ngân sách -->
    <div class="modal" id="addModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Tạo hũ chi tiêu mới</h3>
                <button class="modal-close" onclick="closeAddModal()">×</button>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="action" value="add">

                <div class="form-group">
                    <label class="form-label">Danh mục</label>
                    <select name="category_id" class="form-select" required>
                        <option value="">-- Chọn danh mục --</option>
                        <?php while ($cat = $categoriesQuery->fetch_assoc()): ?>
                            <option value="<?= $cat['id'] ?>">
                                <?= htmlspecialchars($cat['icon']) ?> <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Tên ngân sách</label>
                    <input type="text" name="name" class="form-input" placeholder="VD: Ăn uống tháng 6" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Hạn mức (VNĐ)</label>
                    <input type="number" name="amount" class="form-input" placeholder="3000000" min="0" step="1000" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Chu kỳ</label>
                    <select name="period" class="form-select" required>
                        <option value="daily">Hàng ngày</option>
                        <option value="weekly">Hàng tuần</option>
                        <option value="monthly" selected>Hàng tháng</option>
                        <option value="yearly">Hàng năm</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Ngày bắt đầu</label>
                    <input type="date" name="start_date" class="form-input" value="<?= date('Y-m-01') ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Ngày kết thúc</label>
                    <input type="date" name="end_date" class="form-input" value="<?= date('Y-m-t') ?>" required>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
                    <i class="fas fa-save"></i> Tạo hũ chi tiêu
                </button>
            </form>
        </div>
    </div>

    <script>
        // Mở modal thêm ngân sách
        function openAddModal() {
            document.getElementById('addModal').classList.add('show');
        }

        // Đóng modal
        function closeAddModal() {
            document.getElementById('addModal').classList.remove('show');
        }

        // Đóng modal khi click bên ngoài
        document.getElementById('addModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAddModal();
            }
        });

        // Sửa ngân sách
        function editBudget(id) {
            alert('Chức năng sửa ngân sách đang được phát triển! Budget ID: ' + id);
        }

        // Xem chi tiết
        function viewDetails(id) {
            window.location.href = 'budget-detail.php?id=' + id;
        }

        // Auto-close success message
        <?php if (isset($_GET['success'])): ?>
            setTimeout(function() {
                const url = new URL(window.location);
                url.searchParams.delete('success');
                window.history.replaceState({}, '', url);
            }, 3000);
        <?php endif; ?>

        // Format số tiền khi nhập
        const amountInput = document.querySelector('input[name="amount"]');
        if (amountInput) {
            amountInput.addEventListener('input', function(e) {
                // Cho phép nhập số
                this.value = this.value.replace(/[^\d]/g, '');
            });
        }

        // Tự động set end_date khi thay đổi period hoặc start_date
        const periodSelect = document.querySelector('select[name="period"]');
        const startDateInput = document.querySelector('input[name="start_date"]');
        const endDateInput = document.querySelector('input[name="end_date"]');

        function updateEndDate() {
            if (!startDateInput.value || !periodSelect.value) return;

            const startDate = new Date(startDateInput.value);
            let endDate = new Date(startDate);

            switch(periodSelect.value) {
                case 'daily':
                    // Cùng ngày
                    endDate = new Date(startDate);
                    break;
                case 'weekly':
                    // +7 ngày
                    endDate.setDate(startDate.getDate() + 6);
                    break;
                case 'monthly':
                    // Cuối tháng
                    endDate = new Date(startDate.getFullYear(), startDate.getMonth() + 1, 0);
                    break;
                case 'yearly':
                    // Cuối năm
                    endDate = new Date(startDate.getFullYear(), 11, 31);
                    break;
            }

            endDateInput.value = endDate.toISOString().split('T')[0];
        }

        if (periodSelect && startDateInput) {
            periodSelect.addEventListener('change', updateEndDate);
            startDateInput.addEventListener('change', updateEndDate);
        }
    </script>
</body>
</html>
