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

// Lấy danh sách thẻ của người dùng
$stmt = $conn->prepare("
    SELECT * FROM wallets 
    WHERE user_id = ? AND type IN ('credit_card', 'e_wallet', 'bank')
    ORDER BY is_active DESC, created_at DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$cards = $stmt->get_result();

// Thống kê tổng quan
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_cards,
        SUM(CASE WHEN type = 'credit_card' THEN 1 ELSE 0 END) as credit_cards,
        SUM(CASE WHEN type IN ('bank', 'e_wallet') THEN 1 ELSE 0 END) as debit_cards,
        COALESCE(SUM(CASE WHEN type = 'credit_card' AND balance < 0 THEN ABS(balance) ELSE 0 END), 0) as total_debt
    FROM wallets
    WHERE user_id = ? AND is_active = 1
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

// Giao dịch gần đây từ thẻ
$stmt = $conn->prepare("
    SELECT t.*, c.name as category_name, c.icon as category_icon, w.name as wallet_name, w.type as wallet_type
    FROM transactions t
    LEFT JOIN categories c ON t.category_id = c.id
    LEFT JOIN wallets w ON t.wallet_id = w.id
    WHERE t.user_id = ? AND w.type IN ('credit_card', 'bank', 'e_wallet')
    ORDER BY t.transaction_date DESC, t.created_at DESC
    LIMIT 10
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$recentTransactions = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thẻ - Finora</title>
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

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin-bottom: 12px;
        }

        .stat-label {
            font-size: 12px;
            color: var(--text-lighter);
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 800;
            color: var(--text);
        }

        /* Credit Cards Grid */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .credit-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            padding: 28px;
            color: white;
            position: relative;
            overflow: hidden;
            min-height: 220px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .credit-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 16px 48px rgba(102, 126, 234, 0.4);
        }

        .credit-card::before {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            top: -150px;
            right: -100px;
        }

        .credit-card.visa {
            background: linear-gradient(135deg, #1A1F71 0%, #003399 100%);
        }

        .credit-card.mastercard {
            background: linear-gradient(135deg, #EB001B 0%, #F79E1B 100%);
        }

        .credit-card.debit {
            background: linear-gradient(135deg, #00C9FF 0%, #92FE9D 100%);
        }

        .credit-card.inactive {
            background: linear-gradient(135deg, #636363 0%, #a2ab58 100%);
            opacity: 0.6;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 32px;
            position: relative;
            z-index: 1;
        }

        .card-type {
            font-size: 12px;
            font-weight: 600;
            opacity: 0.9;
        }

        .card-brand {
            font-size: 24px;
            font-weight: 800;
        }

        .card-chip {
            width: 48px;
            height: 36px;
            background: linear-gradient(135deg, #FFD700, #FFA500);
            border-radius: 8px;
            margin-bottom: 24px;
            position: relative;
            z-index: 1;
        }

        .card-number {
            font-size: 20px;
            font-weight: 600;
            letter-spacing: 3px;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }

        .card-footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            position: relative;
            z-index: 1;
        }

        .card-holder {
            flex: 1;
        }

        .card-label {
            font-size: 10px;
            opacity: 0.8;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .card-value {
            font-size: 14px;
            font-weight: 600;
        }

        .card-balance {
            font-size: 16px;
            font-weight: 700;
        }

        /* Section */
        .section {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
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

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-light);
        }

        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        /* Badge */
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            gap: 4px;
        }

        .badge-success {
            background: #D1FAE5;
            color: #065F46;
        }

        .badge-danger {
            background: #FEE2E2;
            color: #991B1B;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .stats-grid {
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

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .cards-grid {
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
                <a href="cards.php" class="menu-link active">
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
                <h1 class="page-title">💳 Quản lý thẻ</h1>
                <p class="page-subtitle">Quản lý thẻ tín dụng, thẻ ghi nợ và tài khoản ngân hàng</p>
            </div>
            <div class="top-actions">
                <a href="accounts.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Thêm thẻ mới
                </a>
                <a href="dashboard.php" class="btn btn-ghost">
                    <i class="fas fa-arrow-left"></i>
                    Quay lại
                </a>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: #EFF6FF; color: var(--primary);">
                    <i class="fas fa-credit-card"></i>
                </div>
                <div class="stat-label">Tổng số thẻ</div>
                <div class="stat-value"><?= $stats['total_cards'] ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #FEF3C7; color: var(--warning);">
                    <i class="fas fa-credit-card"></i>
                </div>
                <div class="stat-label">Thẻ tín dụng</div>
                <div class="stat-value"><?= $stats['credit_cards'] ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #D1FAE5; color: var(--success);">
                    <i class="fas fa-wallet"></i>
                </div>
                <div class="stat-label">Thẻ ghi nợ</div>
                <div class="stat-value"><?= $stats['debit_cards'] ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #FEE2E2; color: var(--danger);">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-label">Tổng nợ</div>
                <div class="stat-value"><?= number_format($stats['total_debt'], 0, ',', '.') ?>đ</div>
            </div>
        </div>

        <!-- Cards Grid -->
        <?php if ($cards->num_rows > 0): ?>
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-credit-card"></i>
                        Danh sách thẻ
                    </h2>
                </div>

                <div class="cards-grid">
                    <?php 
                    $cards->data_seek(0);
                    while ($card = $cards->fetch_assoc()): 
                        // Xác định class thẻ
                        $cardClass = 'credit-card';
                        if ($card['type'] == 'credit_card') {
                            $cardClass .= ' visa';
                        } elseif ($card['type'] == 'e_wallet') {
                            $cardClass .= ' mastercard';
                        } else {
                            $cardClass .= ' debit';
                        }
                        if (!$card['is_active']) {
                            $cardClass .= ' inactive';
                        }

                        // Mask số thẻ - sử dụng account_number nếu có
                        $accountNum = $card['account_number'] ?? str_pad($card['id'], 16, '0', STR_PAD_LEFT);
                        $cardNumber = str_repeat('*', 12) . substr($accountNum, -4);
                        $cardNumber = chunk_split($cardNumber, 4, ' ');
                    ?>

                    <div class="<?= $cardClass ?>" onclick="window.location.href='account-detail.php?id=<?= $card['id'] ?>'">
                        <div class="card-header">
                            <div class="card-type">
                                <?php 
                                if ($card['type'] == 'credit_card') {
                                    echo '💳 Thẻ tín dụng';
                                } elseif ($card['type'] == 'e_wallet') {
                                    echo '💜 Ví điện tử';
                                } else {
                                    echo '🏦 Tài khoản ngân hàng';
                                }
                                ?>
                            </div>
                            <div class="card-brand">
                                <?php 
                                if ($card['bank_name']) {
                                    echo strtoupper(substr($card['bank_name'], 0, 4));
                                } elseif (strpos(strtolower($card['name']), 'visa') !== false) {
                                    echo 'VISA';
                                } elseif (strpos(strtolower($card['name']), 'master') !== false) {
                                    echo 'MC';
                                } else {
                                    echo '💳';
                                }
                                ?>
                            </div>
                        </div>

                        <div class="card-chip"></div>
                        <div class="card-number"><?= trim($cardNumber) ?></div>

                        <div class="card-footer">
                            <div class="card-holder">
                                <div class="card-label">Tên thẻ</div>
                                <div class="card-value"><?= htmlspecialchars($card['name']) ?></div>
                            </div>
                            
                            <div>
                                <div class="card-label">Số dư</div>
                                <div class="card-balance">
                                    <?php if ($card['type'] == 'credit_card' && $card['balance'] < 0): ?>
                                        -<?= number_format(abs($card['balance']), 0, ',', '.') ?>đ
                                    <?php else: ?>
                                        <?= number_format($card['balance'], 0, ',', '.') ?>đ
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>

        <?php else: ?>
            <div class="section">
                <div class="empty-state">
                    <div class="empty-state-icon">💳</div>
                    <h3>Chưa có thẻ nào</h3>
                    <p>Thêm thẻ tín dụng, thẻ ghi nợ hoặc tài khoản ngân hàng để bắt đầu</p>
                    <a href="accounts.php" class="btn btn-primary" style="margin-top: 20px;">
                        <i class="fas fa-plus"></i>
                        Thêm thẻ đầu tiên
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- Recent Transactions -->
        <?php if ($recentTransactions->num_rows > 0): ?>
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-history"></i>
                        Giao dịch gần đây
                    </h2>
                    <a href="transactions.php" class="btn btn-ghost" style="padding: 8px 16px; font-size: 13px;">
                        Xem tất cả
                    </a>
                </div>

                <div class="transaction-list">
                    <?php while ($trans = $recentTransactions->fetch_assoc()): ?>
                    <div class="transaction-item">
                        <div class="transaction-icon" style="color: <?= $trans['type'] == 'income' ? 'var(--success)' : 'var(--danger)' ?>;">
                            <?= htmlspecialchars($trans['category_icon'] ?? '💰') ?>
                        </div>
                        <div class="transaction-info">
                            <div class="transaction-name"><?= htmlspecialchars($trans['description'] ?: $trans['category_name']) ?></div>
                            <div class="transaction-meta">
                                <?= htmlspecialchars($trans['wallet_name']) ?> • 
                                <?= date('d/m/Y H:i', strtotime($trans['transaction_date'])) ?>
                            </div>
                        </div>
                        <div class="transaction-amount" style="color: <?= $trans['type'] == 'income' ? 'var(--success)' : 'var(--danger)' ?>;">
                            <?= $trans['type'] == 'income' ? '+' : '-' ?><?= number_format($trans['amount'], 0, ',', '.') ?>đ
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
