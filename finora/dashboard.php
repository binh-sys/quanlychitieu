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

// Xử lý nạp tiền
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'deposit') {
    $toWalletId = intval($_POST['to_wallet_id']);
    $amount = floatval($_POST['amount']);
    $description = trim($_POST['description']);
    $transactionDate = $_POST['transaction_date'];
    
    if ($toWalletId > 0 && $amount > 0) {
        $conn->begin_transaction();
        try {
            // Cộng tiền vào tài khoản
            $conn->query("UPDATE wallets SET balance = balance + $amount WHERE id = $toWalletId AND user_id = $userId");
            // Tạo giao dịch thu nhập
            $stmt = $conn->prepare("INSERT INTO transactions (user_id, wallet_id, category_id, type, amount, description, transaction_date) VALUES (?, ?, 17, 'income', ?, ?, ?)");
            $stmt->bind_param("iidss", $userId, $toWalletId, $amount, $description, $transactionDate);
            $stmt->execute();
            $conn->commit();
            header('Location: dashboard.php?money=deposit_success');
            exit;
        } catch (Exception $e) {
            $conn->rollback();
        }
    }
}

// Xử lý rút tiền (chuyển tiền)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'transfer') {
    $fromWalletId = intval($_POST['from_wallet_id']);
    $toWalletId = intval($_POST['to_wallet_id_withdraw']);
    $amount = floatval($_POST['amount']);
    $description = trim($_POST['description']);
    $transferDate = $_POST['transaction_date'];
    
    if ($fromWalletId !== $toWalletId && $amount > 0) {
        // Kiểm tra số dư
        $checkBalance = $conn->query("SELECT balance FROM wallets WHERE id = $fromWalletId AND user_id = $userId");
        $fromWallet = $checkBalance->fetch_assoc();
        
        if ($fromWallet && $fromWallet['balance'] >= $amount) {
            $conn->begin_transaction();
            try {
                // Trừ tiền từ tài khoản nguồn
                $conn->query("UPDATE wallets SET balance = balance - $amount WHERE id = $fromWalletId");
                // Cộng tiền vào tài khoản đích
                $conn->query("UPDATE wallets SET balance = balance + $amount WHERE id = $toWalletId");
                // Tạo giao dịch chuyển tiền
                $stmt = $conn->prepare("INSERT INTO transactions (user_id, wallet_id, category_id, type, amount, description, transaction_date) VALUES (?, ?, 2, 'expense', ?, ?, ?)");
                $stmt->bind_param("iidss", $userId, $fromWalletId, $amount, $description, $transferDate);
                $stmt->execute();
                $stmt = $conn->prepare("INSERT INTO transactions (user_id, wallet_id, category_id, type, amount, description, transaction_date) VALUES (?, ?, 17, 'income', ?, ?, ?)");
                $stmt->bind_param("iidss", $userId, $toWalletId, $amount, $description, $transferDate);
                $stmt->execute();
                $conn->commit();
                header('Location: dashboard.php?money=withdraw_success');
                exit;
            } catch (Exception $e) {
                $conn->rollback();
            }
        }
    }
}

// Lấy thông tin tổng quan
$stmt = $conn->prepare("
    SELECT 
        COALESCE(SUM(w.balance), 0) as total_balance,
        COALESCE(SUM(CASE WHEN w.balance < 0 THEN ABS(w.balance) ELSE 0 END), 0) as total_debt,
        COALESCE(SUM(CASE WHEN w.balance > 0 THEN w.balance ELSE 0 END), 0) as total_assets
    FROM wallets w
    WHERE w.user_id = ? AND w.is_active = 1
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();

// Thu nhập tháng này
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(amount), 0) as monthly_income
    FROM transactions
    WHERE user_id = ? AND type = 'income' 
    AND MONTH(transaction_date) = MONTH(CURDATE())
    AND YEAR(transaction_date) = YEAR(CURDATE())
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$income = $stmt->get_result()->fetch_assoc();

// Chi tiêu tháng này
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(amount), 0) as monthly_expense
    FROM transactions
    WHERE user_id = ? AND type = 'expense' 
    AND MONTH(transaction_date) = MONTH(CURDATE())
    AND YEAR(transaction_date) = YEAR(CURDATE())
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$expense = $stmt->get_result()->fetch_assoc();

// Savings goals (tiến độ tiết kiệm)
$stmt = $conn->prepare("
    SELECT id, name, target_amount, current_amount, deadline, icon, color
    FROM savings_goals
    WHERE user_id = ? AND status = 'active'
    ORDER BY deadline ASC
    LIMIT 3
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$savingsGoals = $stmt->get_result();

// Giao dịch gần đây
$stmt = $conn->prepare("
    SELECT t.*, c.name as category_name, c.icon as category_icon, w.name as wallet_name
    FROM transactions t
    LEFT JOIN categories c ON t.category_id = c.id
    LEFT JOIN wallets w ON t.wallet_id = w.id
    WHERE t.user_id = ?
    ORDER BY t.transaction_date DESC, t.created_at DESC
    LIMIT 6
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$recentTransactions = $stmt->get_result();

// Chi tiêu theo tháng (6 tháng gần nhất)
$stmt = $conn->prepare("
    SELECT 
        DATE_FORMAT(transaction_date, '%m/%Y') as month,
        COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as income,
        COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as expense
    FROM transactions
    WHERE user_id = ? 
    AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
    ORDER BY transaction_date ASC
    LIMIT 6
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$monthlyData = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Finora</title>
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

        /* Sidebar */
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

        .user-email {
            font-size: 12px;
            opacity: 0.9;
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

        .top-actions {
            display: flex;
            gap: 12px;
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

        .btn-ghost:hover {
            background: var(--bg);
            border-color: var(--primary);
            color: var(--primary);
        }

        /* Balance Card với Gradient */
        .balance-card {
            background: linear-gradient(135deg, var(--purple) 0%, var(--orange) 100%);
            border-radius: 20px;
            padding: 32px;
            color: white;
            margin-bottom: 32px;
            position: relative;
            overflow: hidden;
        }

        .balance-card::before {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            top: -150px;
            right: -100px;
        }

        .balance-card-content {
            position: relative;
            z-index: 1;
        }

        .balance-label {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 8px;
        }

        .balance-amount {
            font-size: 42px;
            font-weight: 800;
            margin-bottom: 24px;
        }

        .balance-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
        }

        .balance-stat {
            display: flex;
            flex-direction: column;
        }

        .balance-stat-label {
            font-size: 12px;
            opacity: 0.85;
            margin-bottom: 6px;
        }

        .balance-stat-value {
            font-size: 18px;
            font-weight: 700;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 32px;
        }

        .action-btn {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            color: var(--text);
        }

        .action-btn:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
            border-color: var(--primary);
        }

        .action-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #EFF6FF, #F0F9FF);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            font-size: 20px;
            color: var(--primary);
        }

        .action-label {
            font-size: 14px;
            font-weight: 600;
        }

        /* Grid Layout */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
            margin-bottom: 24px;
        }

        .section {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-link {
            color: var(--primary);
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
        }

        /* Chart */
        .chart-container {
            height: 280px;
            display: flex;
            align-items: flex-end;
            gap: 12px;
            padding: 20px 0;
            border-bottom: 2px solid var(--border);
        }

        .chart-bar-group {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }

        .chart-bars {
            width: 100%;
            display: flex;
            align-items: flex-end;
            gap: 4px;
            height: 220px;
        }

        .chart-bar {
            flex: 1;
            border-radius: 6px 6px 0 0;
            transition: all 0.3s;
            cursor: pointer;
            position: relative;
        }

        .chart-bar:hover {
            opacity: 0.8;
        }

        .chart-bar.income {
            background: var(--success);
        }

        .chart-bar.expense {
            background: var(--danger);
        }

        .chart-label {
            font-size: 11px;
            color: var(--text-lighter);
            font-weight: 500;
        }

        .chart-legend {
            display: flex;
            justify-content: center;
            gap: 24px;
            margin-top: 16px;
            font-size: 13px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .legend-color {
            width: 12px;
            height: 12px;
            border-radius: 3px;
        }

        /* Savings Goals */
        .goal-item {
            padding: 16px;
            background: var(--bg);
            border-radius: 12px;
            margin-bottom: 16px;
        }

        .goal-item:last-child {
            margin-bottom: 0;
        }

        .goal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .goal-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .goal-icon {
            font-size: 24px;
        }

        .goal-name {
            font-weight: 600;
            font-size: 14px;
        }

        .goal-progress-pct {
            font-size: 13px;
            font-weight: 700;
            color: var(--primary);
        }

        .goal-progress-bar {
            height: 8px;
            background: var(--border);
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 8px;
        }

        .goal-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--success));
            border-radius: 10px;
            transition: width 0.6s ease;
        }

        .goal-amounts {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: var(--text-light);
        }

        .goal-deadline {
            color: var(--warning);
            font-weight: 600;
        }

        /* Transactions */
        .transaction-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .transaction-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-radius: 10px;
            background: var(--bg);
            transition: all 0.2s;
        }

        .transaction-item:hover {
            background: #EFF6FF;
            transform: translateX(4px);
        }

        .transaction-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            background: white;
            border: 1px solid var(--border);
        }

        .transaction-info {
            flex: 1;
        }

        .transaction-name {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 2px;
        }

        .transaction-meta {
            font-size: 12px;
            color: var(--text-lighter);
        }

        .transaction-amount {
            font-size: 15px;
            font-weight: 700;
        }

        /* Floating Action Button */
        .fab {
            position: fixed;
            bottom: 32px;
            right: 32px;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary), var(--purple));
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            box-shadow: 0 8px 24px rgba(37, 99, 235, 0.4);
            cursor: pointer;
            border: none;
            transition: all 0.3s;
            z-index: 99;
        }

        .fab:hover {
            transform: scale(1.1) rotate(90deg);
            box-shadow: 0 12px 32px rgba(37, 99, 235, 0.5);
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-light);
        }

        .empty-state-icon {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
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
            
            .balance-stats {
                grid-template-columns: 1fr;
                gap: 16px;
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
                <a href="dashboard.php" class="menu-link active">
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
                <a href="budgets.php" class="menu-link">
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
            <div class="user-email">
                <a href="logout.php" style="color: rgba(255,255,255,0.9); text-decoration: none; font-size: 12px;">
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
                <h1 class="page-title">Dashboard</h1>
                <p class="page-subtitle">Chào mừng trở lại, <?= htmlspecialchars(explode(' ', $userName)[0]) ?>! 👋</p>
            </div>
            <div class="top-actions">
                <button class="btn btn-ghost">
                    <i class="fas fa-download"></i>
                    Xuất báo cáo
                </button>
                <a href="index.php" class="btn btn-ghost">
                    <i class="fas fa-home"></i>
                    Trang chủ
                </a>
            </div>
        </div>

        <?php if (isset($_GET['money'])): ?>
            <?php if ($_GET['money'] === 'deposit_success'): ?>
                <div style="padding: 16px 20px; background: #D1FAE5; color: #065F46; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; border: 1px solid #6EE7B7;">
                    <i class="fas fa-check-circle"></i> Nạp tiền thành công!
                </div>
            <?php elseif ($_GET['money'] === 'withdraw_success'): ?>
                <div style="padding: 16px 20px; background: #D1FAE5; color: #065F46; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; border: 1px solid #6EE7B7;">
                    <i class="fas fa-check-circle"></i> Rút tiền thành công!
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Balance Card với Gradient -->
        <div class="balance-card">
            <div class="balance-card-content">
                <div class="balance-label">💰 Tổng tài sản</div>
                <div class="balance-amount"><?= number_format($summary['total_balance'], 0, ',', '.') ?>đ</div>
                
                <div class="balance-stats">
                    <div class="balance-stat">
                        <div class="balance-stat-label">📈 Thu nhập tháng này</div>
                        <div class="balance-stat-value">+<?= number_format($income['monthly_income'], 0, ',', '.') ?>đ</div>
                    </div>
                    <div class="balance-stat">
                        <div class="balance-stat-label">📉 Chi tiêu tháng này</div>
                        <div class="balance-stat-value">-<?= number_format($expense['monthly_expense'], 0, ',', '.') ?>đ</div>
                    </div>
                    <div class="balance-stat">
                        <div class="balance-stat-label">💳 Tổng nợ</div>
                        <div class="balance-stat-value"><?= number_format($summary['total_debt'], 0, ',', '.') ?>đ</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="#" class="action-btn" onclick="event.preventDefault(); openMoneyModal('deposit');">
                <div class="action-icon">
                    <i class="fas fa-coins"></i>
                </div>
                <div class="action-label">Nạp/Rút tiền</div>
            </a>
            <a href="#" class="action-btn" onclick="event.preventDefault(); openQuickAddModal();">
                <div class="action-icon">
                    <i class="fas fa-receipt"></i>
                </div>
                <div class="action-label">Chi tiêu</div>
            </a>
            <a href="budget-allocation.php" class="action-btn">
                <div class="action-icon">
                    <i class="fas fa-percentage"></i>
                </div>
                <div class="action-label">Phân bổ</div>
            </a>
            <a href="reports.php" class="action-btn">
                <div class="action-icon">
                    <i class="fas fa-chart-bar"></i>
                </div>
                <div class="action-label">Báo cáo</div>
            </a>
        </div>

        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <!-- Chi tiêu hàng tháng -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-chart-bar"></i>
                        Chi tiêu hàng tháng
                    </h2>
                    <a href="reports.php" class="section-link">Xem chi tiết →</a>
                </div>

                <?php if (count($monthlyData) > 0): ?>
                    <div class="chart-container">
                        <?php foreach ($monthlyData as $data): 
                            $maxAmount = max(array_merge(
                                array_column($monthlyData, 'income'),
                                array_column($monthlyData, 'expense')
                            ));
                            $incomeHeight = $maxAmount > 0 ? ($data['income'] / $maxAmount * 100) : 0;
                            $expenseHeight = $maxAmount > 0 ? ($data['expense'] / $maxAmount * 100) : 0;
                        ?>
                        <div class="chart-bar-group">
                            <div class="chart-bars">
                                <div class="chart-bar income" 
                                     style="height: <?= $incomeHeight ?>%"
                                     title="Thu nhập: <?= number_format($data['income'], 0, ',', '.') ?>đ"></div>
                                <div class="chart-bar expense" 
                                     style="height: <?= $expenseHeight ?>%"
                                     title="Chi tiêu: <?= number_format($data['expense'], 0, ',', '.') ?>đ"></div>
                            </div>
                            <div class="chart-label"><?= htmlspecialchars($data['month']) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="chart-legend">
                        <div class="legend-item">
                            <div class="legend-color" style="background: var(--success)"></div>
                            <span>Thu nhập</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: var(--danger)"></div>
                            <span>Chi tiêu</span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">📊</div>
                        <p>Chưa có dữ liệu thống kê</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tiến độ tiết kiệm -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-piggy-bank"></i>
                        Tiến độ tiết kiệm
                    </h2>
                    <a href="#" onclick="event.preventDefault(); alert('Tính năng đang phát triển - Sắp ra mắt!')" class="section-link">Thêm mục tiêu →</a>
                </div>

                <?php if ($savingsGoals->num_rows > 0): ?>
                    <?php while ($goal = $savingsGoals->fetch_assoc()): 
                        $progress = $goal['target_amount'] > 0 
                            ? ($goal['current_amount'] / $goal['target_amount'] * 100) 
                            : 0;
                        $progress = min($progress, 100);
                    ?>
                    <div class="goal-item">
                        <div class="goal-header">
                            <div class="goal-info">
                                <span class="goal-icon"><?= htmlspecialchars($goal['icon']) ?></span>
                                <div>
                                    <div class="goal-name"><?= htmlspecialchars($goal['name']) ?></div>
                                    <?php if ($goal['deadline']): ?>
                                        <div class="goal-deadline" style="font-size: 11px;">
                                            <i class="fas fa-calendar-alt"></i> 
                                            <?= date('d/m/Y', strtotime($goal['deadline'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="goal-progress-pct"><?= number_format($progress, 1) ?>%</div>
                        </div>
                        
                        <div class="goal-progress-bar">
                            <div class="goal-progress-fill" style="width: <?= $progress ?>%"></div>
                        </div>
                        
                        <div class="goal-amounts">
                            <span><?= number_format($goal['current_amount'], 0, ',', '.') ?>đ</span>
                            <span><?= number_format($goal['target_amount'], 0, ',', '.') ?>đ</span>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">🎯</div>
                        <p>Chưa có mục tiêu tiết kiệm</p>
                        <a href="#" class="btn btn-primary" style="margin-top: 16px;">
                            <i class="fas fa-plus"></i> Tạo mục tiêu
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Giao dịch gần đây -->
        <div class="section">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-history"></i>
                    Giao dịch gần đây
                </h2>
                <a href="transactions.php" class="section-link">Xem tất cả →</a>
            </div>

            <?php if ($recentTransactions->num_rows > 0): ?>
                <div class="transaction-list">
                    <?php while ($trans = $recentTransactions->fetch_assoc()): ?>
                        <div class="transaction-item">
                            <div class="transaction-icon">
                                <?= $trans['category_icon'] ?? '📦' ?>
                            </div>
                            <div class="transaction-info">
                                <div class="transaction-name"><?= htmlspecialchars($trans['description']) ?></div>
                                <div class="transaction-meta">
                                    <?= htmlspecialchars($trans['category_name']) ?> • 
                                    <?= htmlspecialchars($trans['wallet_name']) ?> • 
                                    <?= date('d/m/Y', strtotime($trans['transaction_date'])) ?>
                                </div>
                            </div>
                            <div class="transaction-amount" style="color: <?= $trans['type'] === 'income' ? 'var(--success)' : 'var(--danger)' ?>">
                                <?= $trans['type'] === 'income' ? '+' : '-' ?><?= number_format($trans['amount'], 0, ',', '.') ?>đ
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <p>Chưa có giao dịch nào</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Floating Action Button -->
    <button class="fab" onclick="openQuickAddModal()" title="Thêm chi tiêu">
        <i class="fas fa-plus"></i>
    </button>

    <!-- Modal Thêm giao dịch nhanh -->
    <div class="modal" id="quickAddModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">💳 Các khoản chi tiêu</h3>
                <button class="modal-close" onclick="closeQuickAddModal()">×</button>
            </div>

            <form method="POST" action="transactions.php" class="quick-form">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="type" value="expense">

                <div class="form-group">
                    <label class="form-label">Số tiền (VNĐ) *</label>
                    <input type="number" name="amount" class="form-input form-input-lg" 
                           placeholder="0" min="1" step="1" required autofocus>
                </div>

                <div class="form-group">
                    <label class="form-label">Danh mục chi tiêu *</label>
                    <select name="category_id" class="form-select" required id="categorySelect">
                        <option value="">-- Chọn danh mục --</option>
                        <?php 
                        $expenseCatsForModal = $conn->query("SELECT id, name, icon FROM categories WHERE (user_id = $userId OR is_system = 1) AND type = 'expense' ORDER BY name");
                        while ($cat = $expenseCatsForModal->fetch_assoc()): 
                        ?>
                            <option value="<?= $cat['id'] ?>">
                                <?= htmlspecialchars($cat['icon']) ?> <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Tài khoản thanh toán *</label>
                    <select name="wallet_id" class="form-select" required>
                        <option value="">-- Chọn tài khoản --</option>
                        <?php 
                        $walletsForModal = $conn->query("SELECT id, name, icon, balance FROM wallets WHERE user_id = $userId AND is_active = 1 ORDER BY name");
                        if ($walletsForModal && $walletsForModal->num_rows > 0):
                            while ($wallet = $walletsForModal->fetch_assoc()): 
                        ?>
                            <option value="<?= $wallet['id'] ?>">
                                <?= htmlspecialchars($wallet['icon']) ?> <?= htmlspecialchars($wallet['name']) ?> 
                                (<?= number_format($wallet['balance'], 0, ',', '.') ?>đ)
                            </option>
                        <?php 
                            endwhile;
                        endif;
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Mô tả</label>
                    <input type="text" name="description" class="form-input" placeholder="VD: Mua cà phê, Ăn trưa...">
                </div>

                <div class="form-group">
                    <label class="form-label">Ngày giao dịch *</label>
                    <input type="date" name="transaction_date" class="form-input" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-ghost" onclick="closeQuickAddModal()">Hủy</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> Thêm chi tiêu
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Chuyển tiền -->
    <div class="modal" id="transferModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title"> Nạp/Rút tiền</h3>
                <button class="modal-close" onclick="closeTransferModal()">×</button>
            </div>

            <!-- Tabs -->
            <div class="form-tabs">
                <button type="button" class="form-tab active" data-money-type="deposit" onclick="switchMoneyTab('deposit')">
                    <i class="fas fa-arrow-down"></i> Nạp tiền
                </button>
                <button type="button" class="form-tab" data-money-type="withdraw" onclick="switchMoneyTab('withdraw')">
                    <i class="fas fa-arrow-up"></i> Rút tiền
                </button>
            </div>

            <form method="POST" action="" class="quick-form" id="moneyForm">
                <input type="hidden" name="action" value="transfer" id="moneyAction">

                <!-- Nạp tiền: Chọn tài khoản đích -->
                <div class="form-group" id="depositSection">
                    <label class="form-label">Nạp vào tài khoản *</label>
                    <select name="to_wallet_id" class="form-select" id="depositWallet">
                        <option value="">-- Chọn tài khoản --</option>
                        <?php 
                        $walletsForDeposit = $conn->query("SELECT id, name, icon, balance FROM wallets WHERE user_id = $userId AND is_active = 1 ORDER BY name");
                        while ($wallet = $walletsForDeposit->fetch_assoc()): 
                        ?>
                            <option value="<?= $wallet['id'] ?>">
                                <?= htmlspecialchars($wallet['icon']) ?> <?= htmlspecialchars($wallet['name']) ?> 
                                (<?= number_format($wallet['balance'], 0, ',', '.') ?>đ)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <!-- Rút tiền: Chọn 2 tài khoản -->
                <div id="withdrawSection" style="display: none;">
                    <div class="form-group">
                        <label class="form-label">Rút từ tài khoản *</label>
                        <select name="from_wallet_id" class="form-select" id="fromWallet">
                            <option value="">-- Chọn tài khoản nguồn --</option>
                            <?php 
                            $walletsForWithdraw1 = $conn->query("SELECT id, name, icon, balance FROM wallets WHERE user_id = $userId AND is_active = 1 ORDER BY name");
                            while ($wallet = $walletsForWithdraw1->fetch_assoc()): 
                            ?>
                                <option value="<?= $wallet['id'] ?>" data-balance="<?= $wallet['balance'] ?>">
                                    <?= htmlspecialchars($wallet['icon']) ?> <?= htmlspecialchars($wallet['name']) ?> 
                                    (<?= number_format($wallet['balance'], 0, ',', '.') ?>đ)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div style="text-align: center; margin: 8px 0; color: var(--primary); font-size: 20px;">
                        <i class="fas fa-arrow-down"></i>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Chuyển đến tài khoản *</label>
                        <select name="to_wallet_id_withdraw" class="form-select" id="toWallet">
                            <option value="">-- Chọn tài khoản đích --</option>
                            <?php 
                            $walletsForWithdraw2 = $conn->query("SELECT id, name, icon, balance FROM wallets WHERE user_id = $userId AND is_active = 1 ORDER BY name");
                            while ($wallet = $walletsForWithdraw2->fetch_assoc()): 
                            ?>
                                <option value="<?= $wallet['id'] ?>">
                                    <?= htmlspecialchars($wallet['icon']) ?> <?= htmlspecialchars($wallet['name']) ?> 
                                    (<?= number_format($wallet['balance'], 0, ',', '.') ?>đ)
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Số tiền *</label>
                    <input type="number" name="amount" class="form-input form-input-lg" 
                           placeholder="0" min="1" step="1" required id="moneyAmount">
                    <div style="font-size: 11px; color: var(--text-lighter); margin-top: 4px;" id="moneyWarning"></div>
                </div>

                <div class="form-group">
                    <label class="form-label">Mô tả</label>
                    <input type="text" name="description" class="form-input" placeholder="VD: Nạp tiền mặt" id="moneyDescription">
                </div>

                <div class="form-group">
                    <label class="form-label">Ngày giao dịch</label>
                    <input type="date" name="transaction_date" class="form-input" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-ghost" onclick="closeTransferModal()">Hủy</button>
                    <button type="submit" class="btn btn-primary" id="moneySubmitBtn">
                        <i class="fas fa-check"></i> <span id="moneySubmitText">Nạp tiền</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <style>
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.2s;
        }

        .modal.show {
            display: flex;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background: var(--card);
            border-radius: 20px;
            padding: 32px;
            max-width: 500px;
            width: 90%;
            max-height: 95vh;
            overflow-y: auto;
            animation: slideUp 0.3s;
        }

        #transferModal .modal-content {
            max-height: 90vh;
            padding: 24px;
        }

        #transferModal .form-group {
            margin-bottom: 14px;
        }

        #transferModal .modal-header {
            margin-bottom: 20px;
        }

        #transferModal .form-tabs {
            margin-bottom: 20px;
        }

        #transferModal .form-label {
            margin-bottom: 6px;
            font-size: 13px;
        }

        #transferModal .form-input,
        #transferModal .form-select {
            padding: 10px 14px;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
            font-size: 24px;
            color: var(--text-light);
            transition: all 0.2s;
        }

        .modal-close:hover {
            background: var(--border);
        }

        .form-tabs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 24px;
        }

        .form-tab {
            padding: 12px 20px;
            border-radius: 10px;
            border: 2px solid var(--border);
            background: var(--card);
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .form-tab:hover {
            background: var(--bg);
        }

        .form-tab.active {
            border-color: var(--primary);
            background: linear-gradient(135deg, #EFF6FF, #F0F9FF);
            color: var(--primary);
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

        .form-input-lg {
            font-size: 24px;
            font-weight: 700;
            padding: 12px 16px;
        }

        #transferModal .form-input-lg {
            font-size: 22px;
            padding: 10px 14px;
        }

        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }

        .form-actions .btn {
            flex: 1;
        }
    </style>

    <script>
        // Categories data
        <?php
        $expenseCats = $conn->query("SELECT id, name, icon FROM categories WHERE (user_id = $userId OR is_system = 1) AND type = 'expense' ORDER BY name");
        $expenseCategoriesData = [];
        while ($cat = $expenseCats->fetch_assoc()) {
            $expenseCategoriesData[] = $cat;
        }
        
        $incomeCats = $conn->query("SELECT id, name, icon FROM categories WHERE (user_id = $userId OR is_system = 1) AND type = 'income' ORDER BY name");
        $incomeCategoriesData = [];
        while ($cat = $incomeCats->fetch_assoc()) {
            $incomeCategoriesData[] = $cat;
        }
        ?>
        const expenseCategories = <?= json_encode($expenseCategoriesData) ?>;
        const incomeCategories = <?= json_encode($incomeCategoriesData) ?>;

        // Quick Add Modal - Chỉ cho chi tiêu
        function openQuickAddModal() {
            document.getElementById('quickAddModal').classList.add('show');
        }

        function closeQuickAddModal() {
            document.getElementById('quickAddModal').classList.remove('show');
        }

        // Transfer Modal
        function openTransferModal() {
            document.getElementById('transferModal').classList.add('show');
            switchMoneyTab('deposit'); // Mặc định mở tab Nạp tiền
        }

        function openMoneyModal(type = 'deposit') {
            document.getElementById('transferModal').classList.add('show');
            switchMoneyTab(type);
        }

        function closeTransferModal() {
            document.getElementById('transferModal').classList.remove('show');
        }

        function switchMoneyTab(type) {
            const depositSection = document.getElementById('depositSection');
            const withdrawSection = document.getElementById('withdrawSection');
            const depositWallet = document.getElementById('depositWallet');
            const fromWallet = document.getElementById('fromWallet');
            const toWalletWithdraw = document.querySelector('[name="to_wallet_id_withdraw"]');
            const moneyDescription = document.getElementById('moneyDescription');
            const moneySubmitText = document.getElementById('moneySubmitText');
            const moneyAction = document.getElementById('moneyAction');
            
            // Update active tab
            document.querySelectorAll('[data-money-type]').forEach(t => t.classList.remove('active'));
            document.querySelector(`[data-money-type="${type}"]`).classList.add('active');
            
            if (type === 'deposit') {
                // Nạp tiền
                depositSection.style.display = 'block';
                withdrawSection.style.display = 'none';
                depositWallet.required = true;
                fromWallet.required = false;
                toWalletWithdraw.required = false;
                moneyDescription.placeholder = 'VD: Nạp tiền mặt';
                moneyDescription.value = 'Nạp tiền';
                moneySubmitText.textContent = 'Nạp tiền';
                moneyAction.value = 'deposit';
                
                // Clear withdraw fields
                fromWallet.value = '';
                toWalletWithdraw.value = '';
            } else {
                // Rút tiền (chuyển tiền)
                depositSection.style.display = 'none';
                withdrawSection.style.display = 'block';
                depositWallet.required = false;
                fromWallet.required = true;
                toWalletWithdraw.required = true;
                moneyDescription.placeholder = 'VD: Rút tiền về ví';
                moneyDescription.value = 'Rút tiền';
                moneySubmitText.textContent = 'Rút tiền';
                moneyAction.value = 'transfer';
                
                // Clear deposit field
                depositWallet.value = '';
            }
        }

        // Check balance when transfer amount changes
        document.addEventListener('DOMContentLoaded', function() {
            const fromWallet = document.getElementById('fromWallet');
            const moneyAmount = document.getElementById('moneyAmount');
            const moneyWarning = document.getElementById('moneyWarning');

            function checkBalance() {
                const moneyAction = document.getElementById('moneyAction');
                
                if (moneyAction.value === 'transfer') {
                    const selectedOption = fromWallet.options[fromWallet.selectedIndex];
                    const balance = parseFloat(selectedOption.dataset.balance) || 0;
                    const amount = parseFloat(moneyAmount.value) || 0;

                    if (amount > balance) {
                        moneyWarning.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Số dư không đủ!';
                        moneyWarning.style.color = 'var(--danger)';
                    } else if (amount > 0) {
                        moneyWarning.innerHTML = '<i class="fas fa-check-circle"></i> Số dư đủ để rút';
                        moneyWarning.style.color = 'var(--success)';
                    } else {
                        moneyWarning.innerHTML = '';
                    }
                } else {
                    moneyWarning.innerHTML = '';
                }
            }

            if (fromWallet && moneyAmount && moneyWarning) {
                fromWallet.addEventListener('change', checkBalance);
                moneyAmount.addEventListener('input', checkBalance);
            }
        });

        // Close modal on outside click
        document.getElementById('quickAddModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeQuickAddModal();
            }
        });

        document.getElementById('transferModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeTransferModal();
            }
        });

        // Close modal on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeQuickAddModal();
                closeTransferModal();
            }
        });

        // Animation on load
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.summary-card, .action-btn, .section, .goal-item, .transaction-item');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.4s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 50);
            });
        });
    </script>
</body>
</html>
