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

// Xử lý thêm tài khoản
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $name = $_POST['name'];
        $type = $_POST['type'];
        $balance = $_POST['balance'];
        $bankName = $_POST['bank_name'] ?? null;
        $accountNumber = $_POST['account_number'] ?? null;
        $color = $_POST['color'];
        $icon = $_POST['icon'];
        
        $stmt = $conn->prepare("INSERT INTO wallets (user_id, name, type, balance, bank_name, account_number, color, icon) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issdssss", $userId, $name, $type, $balance, $bankName, $accountNumber, $color, $icon);
        $stmt->execute();
        
        header('Location: accounts.php?success=add');
        exit;
    }
}

// Lấy danh sách tài khoản
$stmt = $conn->prepare("
    SELECT * FROM wallets 
    WHERE user_id = ? AND is_active = 1
    ORDER BY type, created_at DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$wallets = $stmt->get_result();

// Tính tổng theo loại
$stmt = $conn->prepare("
    SELECT 
        type,
        COUNT(*) as count,
        COALESCE(SUM(CASE WHEN type != 'credit_card' THEN balance ELSE 0 END), 0) as total_balance,
        COALESCE(SUM(CASE WHEN type = 'credit_card' AND balance < 0 THEN ABS(balance) ELSE 0 END), 0) as total_debt
    FROM wallets
    WHERE user_id = ? AND is_active = 1
    GROUP BY type
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$summary = $stmt->get_result();

// Convert to array
$summaryData = [];
while ($row = $summary->fetch_assoc()) {
    $summaryData[$row['type']] = $row;
}

// Tính tổng tất cả
$totalAssets = 0;
$totalDebt = 0;
foreach ($summaryData as $data) {
    $totalAssets += $data['total_balance'];
    $totalDebt += $data['total_debt'];
}
$netWorth = $totalAssets - $totalDebt;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tài khoản - Finora</title>
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

        /* Sidebar - copy từ dashboard */
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

        /* Overview Cards */
        .overview-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 32px;
        }

        .overview-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 28px;
            position: relative;
            overflow: hidden;
        }

        .overview-card.primary {
            background: linear-gradient(135deg, var(--primary), var(--purple));
            color: white;
            border: none;
        }

        .overview-card.success {
            background: linear-gradient(135deg, var(--success), #34D399);
            color: white;
            border: none;
        }

        .overview-card.danger {
            background: linear-gradient(135deg, var(--danger), #F87171);
            color: white;
            border: none;
        }

        .overview-icon {
            width: 56px;
            height: 56px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 16px;
        }

        .overview-label {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 8px;
        }

        .overview-amount {
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 8px;
        }

        .overview-info {
            font-size: 13px;
            opacity: 0.85;
        }

        /* Wallet Cards */
        .wallets-section {
            margin-bottom: 32px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-title {
            font-size: 20px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .wallets-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }

        .wallet-card {
            background: var(--card);
            border: 2px solid var(--border);
            border-radius: 20px;
            padding: 24px;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .wallet-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 150px;
            height: 150px;
            border-radius: 50%;
            opacity: 0.1;
            transform: translate(50%, -50%);
        }

        .wallet-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.1);
            border-color: var(--primary);
        }

        .wallet-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }

        .wallet-info {
            flex: 1;
        }

        .wallet-type {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-lighter);
            margin-bottom: 6px;
        }

        .wallet-name {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .wallet-details {
            font-size: 12px;
            color: var(--text-light);
        }

        .wallet-icon-big {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .wallet-balance {
            margin-bottom: 16px;
            position: relative;
            z-index: 1;
        }

        .wallet-balance-label {
            font-size: 12px;
            color: var(--text-light);
            margin-bottom: 6px;
        }

        .wallet-balance-amount {
            font-size: 28px;
            font-weight: 800;
        }

        .wallet-actions {
            display: flex;
            gap: 8px;
            position: relative;
            z-index: 1;
        }

        .wallet-btn {
            flex: 1;
            padding: 10px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid var(--border);
            background: var(--bg);
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .wallet-btn:hover {
            background: var(--card);
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-2px);
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
            border-radius: 24px;
            padding: 32px;
            max-width: 520px;
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
            font-size: 24px;
            font-weight: 800;
        }

        .modal-close {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            border: none;
            background: var(--bg);
            cursor: pointer;
            font-size: 20px;
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

        .icon-selector {
            display: grid;
            grid-template-columns: repeat(8, 1fr);
            gap: 8px;
        }

        .icon-option {
            width: 48px;
            height: 48px;
            border: 2px solid var(--border);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .icon-option:hover, .icon-option.selected {
            border-color: var(--primary);
            background: #EFF6FF;
        }

        .color-selector {
            display: grid;
            grid-template-columns: repeat(8, 1fr);
            gap: 8px;
        }

        .color-option {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            cursor: pointer;
            border: 3px solid transparent;
            transition: all 0.2s;
        }

        .color-option:hover, .color-option.selected {
            border-color: var(--text);
            transform: scale(1.1);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: var(--card);
            border: 2px dashed var(--border);
            border-radius: 20px;
        }

        .empty-icon {
            font-size: 64px;
            margin-bottom: 16px;
            opacity: 0.4;
        }

        @media (max-width: 1024px) {
            .overview-cards {
                grid-template-columns: 1fr;
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

            .wallets-grid {
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
                <a href="accounts.php" class="menu-link active">
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
                <h1 class="page-title">💳 Tài khoản & Ví tiền</h1>
                <p class="page-subtitle">Quản lý tất cả tài khoản ngân hàng, ví điện tử và tiền mặt</p>
            </div>
            <button class="btn btn-primary" onclick="openAddModal()">
                <i class="fas fa-plus"></i>
                Thêm tài khoản
            </button>
        </div>

        <!-- Overview Cards -->
        <div class="overview-cards">
            <div class="overview-card primary">
                <div class="overview-icon">💰</div>
                <div class="overview-label">Tổng tài sản</div>
                <div class="overview-amount"><?= number_format($totalAssets, 0, ',', '.') ?>đ</div>
                <div class="overview-info">
                    <i class="fas fa-wallet"></i> 
                    <?= $wallets->num_rows ?> tài khoản
                </div>
            </div>

            <div class="overview-card success">
                <div class="overview-icon">📈</div>
                <div class="overview-label">Giá trị ròng</div>
                <div class="overview-amount"><?= number_format($netWorth, 0, ',', '.') ?>đ</div>
                <div class="overview-info">
                    <?= $netWorth >= 0 ? 'Tài sản > Nợ' : 'Nợ > Tài sản' ?>
                </div>
            </div>

            <div class="overview-card danger">
                <div class="overview-icon">💳</div>
                <div class="overview-label">Tổng nợ</div>
                <div class="overview-amount"><?= number_format($totalDebt, 0, ',', '.') ?>đ</div>
                <div class="overview-info">
                    <?= isset($summaryData['credit_card']) ? $summaryData['credit_card']['count'] : 0 ?> thẻ tín dụng
                </div>
            </div>
        </div>

        <?php
        // Reset pointer
        $wallets->data_seek(0);
        
        // Group wallets by type
        $walletsByType = [];
        while ($wallet = $wallets->fetch_assoc()) {
            $walletsByType[$wallet['type']][] = $wallet;
        }

        $typeNames = [
            'cash' => '💵 Tiền mặt',
            'bank' => '🏦 Tài khoản ngân hàng',
            'e_wallet' => '📱 Ví điện tử',
            'credit_card' => '💳 Thẻ tín dụng',
            'investment' => '📈 Đầu tư'
        ];
        ?>

        <?php if (count($walletsByType) > 0): ?>
            <?php foreach ($typeNames as $type => $typeName): ?>
                <?php if (isset($walletsByType[$type])): ?>
                    <div class="wallets-section">
                        <div class="section-header">
                            <h2 class="section-title"><?= $typeName ?></h2>
                            <span style="color: var(--text-light); font-size: 14px;">
                                <?= count($walletsByType[$type]) ?> tài khoản
                            </span>
                        </div>

                        <div class="wallets-grid">
                            <?php foreach ($walletsByType[$type] as $wallet): ?>
                                <div class="wallet-card">
                                    <div class="wallet-header">
                                        <div class="wallet-info">
                                            <div class="wallet-type"><?= $typeName ?></div>
                                            <div class="wallet-name"><?= htmlspecialchars($wallet['name']) ?></div>
                                            <?php if ($wallet['bank_name']): ?>
                                                <div class="wallet-details">
                                                    <?= htmlspecialchars($wallet['bank_name']) ?>
                                                    <?php if ($wallet['account_number']): ?>
                                                        • <?= htmlspecialchars($wallet['account_number']) ?>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="wallet-icon-big" style="background: <?= htmlspecialchars($wallet['color']) ?>20;">
                                            <span><?= htmlspecialchars($wallet['icon']) ?></span>
                                        </div>
                                    </div>

                                    <div class="wallet-balance">
                                        <div class="wallet-balance-label">Số dư hiện tại</div>
                                        <div class="wallet-balance-amount" style="color: <?= $wallet['balance'] >= 0 ? 'var(--success)' : 'var(--danger)' ?>;">
                                            <?= $wallet['balance'] >= 0 ? '' : '-' ?><?= number_format(abs($wallet['balance']), 0, ',', '.') ?>đ
                                        </div>
                                    </div>

                                    <div class="wallet-actions">
                                        <button class="wallet-btn" onclick="topUp(<?= $wallet['id'] ?>)">
                                            <i class="fas fa-plus"></i> Nạp
                                        </button>
                                        <button class="wallet-btn" onclick="withdraw(<?= $wallet['id'] ?>)">
                                            <i class="fas fa-minus"></i> Rút
                                        </button>
                                        <button class="wallet-btn" onclick="editWallet(<?= $wallet['id'] ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">💳</div>
                <h3 style="margin-bottom: 8px;">Chưa có tài khoản nào</h3>
                <p style="color: var(--text-light); margin-bottom: 24px;">
                    Thêm tài khoản đầu tiên để bắt đầu quản lý tài chính
                </p>
                <button class="btn btn-primary" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Thêm tài khoản
                </button>
            </div>
        <?php endif; ?>
    </main>

    <!-- Modal Thêm tài khoản -->
    <div class="modal" id="addModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Thêm tài khoản mới</h3>
                <button class="modal-close" onclick="closeAddModal()">×</button>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="action" value="add">

                <div class="form-group">
                    <label class="form-label">Tên tài khoản *</label>
                    <input type="text" name="name" class="form-input" placeholder="VD: Ví tiền mặt" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Loại tài khoản *</label>
                    <select name="type" class="form-select" id="accountType" onchange="toggleBankFields()" required>
                        <option value="cash">💵 Tiền mặt</option>
                        <option value="bank">🏦 Tài khoản ngân hàng</option>
                        <option value="e_wallet">📱 Ví điện tử</option>
                        <option value="credit_card">💳 Thẻ tín dụng</option>
                        <option value="investment">📈 Đầu tư</option>
                    </select>
                </div>

                <div id="bankFields" style="display: none;">
                    <div class="form-group">
                        <label class="form-label">Tên ngân hàng</label>
                        <input type="text" name="bank_name" class="form-input" placeholder="VD: Vietcombank">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Số tài khoản</label>
                        <input type="text" name="account_number" class="form-input" placeholder="VD: 0123456789">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Số dư ban đầu (VNĐ) *</label>
                    <input type="number" name="balance" class="form-input" placeholder="0" step="1000" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Chọn icon</label>
                    <div class="icon-selector">
                        <div class="icon-option selected" data-icon="💰" onclick="selectIcon(this)">💰</div>
                        <div class="icon-option" data-icon="💵" onclick="selectIcon(this)">💵</div>
                        <div class="icon-option" data-icon="💳" onclick="selectIcon(this)">💳</div>
                        <div class="icon-option" data-icon="🏦" onclick="selectIcon(this)">🏦</div>
                        <div class="icon-option" data-icon="📱" onclick="selectIcon(this)">📱</div>
                        <div class="icon-option" data-icon="💜" onclick="selectIcon(this)">💜</div>
                        <div class="icon-option" data-icon="💙" onclick="selectIcon(this)">💙</div>
                        <div class="icon-option" data-icon="📈" onclick="selectIcon(this)">📈</div>
                    </div>
                    <input type="hidden" name="icon" id="selectedIcon" value="💰">
                </div>

                <div class="form-group">
                    <label class="form-label">Chọn màu</label>
                    <div class="color-selector">
                        <div class="color-option selected" style="background: #2563EB" data-color="#2563EB" onclick="selectColor(this)"></div>
                        <div class="color-option" style="background: #10B981" data-color="#10B981" onclick="selectColor(this)"></div>
                        <div class="color-option" style="background: #EF4444" data-color="#EF4444" onclick="selectColor(this)"></div>
                        <div class="color-option" style="background: #F59E0B" data-color="#F59E0B" onclick="selectColor(this)"></div>
                        <div class="color-option" style="background: #7C3AED" data-color="#7C3AED" onclick="selectColor(this)"></div>
                        <div class="color-option" style="background: #EC4899" data-color="#EC4899" onclick="selectColor(this)"></div>
                        <div class="color-option" style="background: #06B6D4" data-color="#06B6D4" onclick="selectColor(this)"></div>
                        <div class="color-option" style="background: #8B5CF6" data-color="#8B5CF6" onclick="selectColor(this)"></div>
                    </div>
                    <input type="hidden" name="color" id="selectedColor" value="#2563EB">
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; margin-top: 8px;">
                    <i class="fas fa-save"></i> Thêm tài khoản
                </button>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function openAddModal() {
            document.getElementById('addModal').classList.add('show');
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.remove('show');
        }

        // Click outside to close
        document.getElementById('addModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAddModal();
            }
        });

        // Toggle bank fields
        function toggleBankFields() {
            const accountType = document.getElementById('accountType').value;
            const bankFields = document.getElementById('bankFields');
            
            if (accountType === 'bank' || accountType === 'credit_card') {
                bankFields.style.display = 'block';
            } else {
                bankFields.style.display = 'none';
            }
        }

        // Icon selector
        function selectIcon(element) {
            document.querySelectorAll('.icon-option').forEach(opt => opt.classList.remove('selected'));
            element.classList.add('selected');
            document.getElementById('selectedIcon').value = element.dataset.icon;
        }

        // Color selector
        function selectColor(element) {
            document.querySelectorAll('.color-option').forEach(opt => opt.classList.remove('selected'));
            element.classList.add('selected');
            document.getElementById('selectedColor').value = element.dataset.color;
        }

        // Wallet actions
        function topUp(walletId) {
            alert('Chức năng nạp tiền đang được phát triển! Wallet ID: ' + walletId);
        }

        function withdraw(walletId) {
            alert('Chức năng rút tiền đang được phát triển! Wallet ID: ' + walletId);
        }

        function editWallet(walletId) {
            alert('Chức năng sửa tài khoản đang được phát triển! Wallet ID: ' + walletId);
        }
    </script>
</body>
</html>
