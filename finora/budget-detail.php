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
$budgetId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Lấy thông tin ngân sách
$stmt = $conn->prepare("
    SELECT b.*, c.name as category_name, c.icon as category_icon, c.color as category_color,
           COALESCE(SUM(t.amount), 0) as spent
    FROM budgets b
    LEFT JOIN categories c ON b.category_id = c.id
    LEFT JOIN transactions t ON t.category_id = b.category_id 
        AND t.user_id = b.user_id 
        AND t.type = 'expense'
        AND t.transaction_date BETWEEN b.start_date AND b.end_date
    WHERE b.id = ? AND b.user_id = ?
    GROUP BY b.id
");
$stmt->bind_param("ii", $budgetId, $userId);
$stmt->execute();
$budget = $stmt->get_result()->fetch_assoc();

if (!$budget) {
    header('Location: budgets.php');
    exit;
}

// Lấy giao dịch của ngân sách này
$stmt = $conn->prepare("
    SELECT t.*, w.name as wallet_name
    FROM transactions t
    LEFT JOIN wallets w ON t.wallet_id = w.id
    WHERE t.user_id = ? 
        AND t.category_id = ?
        AND t.type = 'expense'
        AND t.transaction_date BETWEEN ? AND ?
    ORDER BY t.transaction_date DESC, t.created_at DESC
");
$stmt->bind_param("iiss", $userId, $budget['category_id'], $budget['start_date'], $budget['end_date']);
$stmt->execute();
$transactions = $stmt->get_result();

// Tính toán
$percentage = $budget['amount'] > 0 ? ($budget['spent'] / $budget['amount'] * 100) : 0;
$remaining = $budget['amount'] - $budget['spent'];
$daysTotal = (strtotime($budget['end_date']) - strtotime($budget['start_date'])) / 86400 + 1;
$daysPassed = (time() - strtotime($budget['start_date'])) / 86400;
$daysPassed = max(0, min($daysPassed, $daysTotal));
$daysRemaining = max(0, $daysTotal - $daysPassed);
$avgPerDay = $daysTotal > 0 ? $budget['spent'] / max(1, $daysPassed) : 0;
$projectedTotal = $daysTotal > 0 ? $avgPerDay * $daysTotal : 0;

$status = 'safe';
if ($percentage >= 100) $status = 'danger';
elseif ($percentage >= 80) $status = 'warning';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi tiết ngân sách - <?= htmlspecialchars($budget['category_name']) ?> - Finora</title>
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
            --bg: #F8FAFC;
            --card: #FFFFFF;
            --text: #0F172A;
            --text-light: #475569;
            --text-lighter: #94A3B8;
            --border: #E2E8F0;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg);
            color: var(--text);
            padding: 32px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-light);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 24px;
            transition: all 0.2s;
        }

        .back-link:hover {
            color: var(--primary);
            transform: translateX(-4px);
        }

        .header-card {
            background: linear-gradient(135deg, var(--purple), #EC4899);
            border-radius: 24px;
            padding: 40px;
            color: white;
            margin-bottom: 32px;
            position: relative;
            overflow: hidden;
        }

        .header-card::before {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            top: -200px;
            right: -100px;
        }

        .header-content {
            position: relative;
            z-index: 1;
        }

        .header-top {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
        }

        .header-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
        }

        .header-info h1 {
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 8px;
        }

        .header-period {
            opacity: 0.9;
            font-size: 14px;
        }

        .header-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
        }

        .header-stat {
            text-align: center;
        }

        .header-stat-value {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 4px;
        }

        .header-stat-label {
            font-size: 13px;
            opacity: 0.85;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 16px;
        }

        .stat-label {
            font-size: 13px;
            color: var(--text-light);
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 800;
        }

        .section {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .transaction-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .transaction-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px;
            background: var(--bg);
            border-radius: 12px;
            transition: all 0.2s;
        }

        .transaction-item:hover {
            background: #EFF6FF;
            transform: translateX(4px);
        }

        .transaction-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            background: white;
            border: 1px solid var(--border);
        }

        .transaction-info {
            flex: 1;
        }

        .transaction-name {
            font-weight: 600;
            margin-bottom: 4px;
        }

        .transaction-meta {
            font-size: 13px;
            color: var(--text-lighter);
        }

        .transaction-amount {
            font-size: 18px;
            font-weight: 700;
            color: var(--danger);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-light);
        }

        .empty-icon {
            font-size: 64px;
            opacity: 0.3;
            margin-bottom: 16px;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.2s;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="budgets.php" class="back-link">
            <i class="fas fa-arrow-left"></i>
            Quay lại danh sách ngân sách
        </a>

        <!-- Header Card -->
        <div class="header-card">
            <div class="header-content">
                <div class="header-top">
                    <div class="header-icon">
                        <?= htmlspecialchars($budget['category_icon']) ?>
                    </div>
                    <div class="header-info">
                        <h1><?= htmlspecialchars($budget['category_name']) ?></h1>
                        <div class="header-period">
                            <i class="fas fa-calendar"></i>
                            <?= date('d/m/Y', strtotime($budget['start_date'])) ?> - 
                            <?= date('d/m/Y', strtotime($budget['end_date'])) ?>
                            (<?= number_format($daysTotal) ?> ngày)
                        </div>
                    </div>
                </div>

                <div class="header-stats">
                    <div class="header-stat">
                        <div class="header-stat-value"><?= number_format($budget['spent'], 0, ',', '.') ?>đ</div>
                        <div class="header-stat-label">Đã chi tiêu</div>
                    </div>
                    <div class="header-stat">
                        <div class="header-stat-value"><?= number_format($budget['amount'], 0, ',', '.') ?>đ</div>
                        <div class="header-stat-label">Hạn mức</div>
                    </div>
                    <div class="header-stat">
                        <div class="header-stat-value" style="color: <?= $remaining >= 0 ? '#D1FAE5' : '#FEE2E2' ?>">
                            <?= number_format(abs($remaining), 0, ',', '.') ?>đ
                        </div>
                        <div class="header-stat-label"><?= $remaining >= 0 ? 'Còn lại' : 'Vượt mức' ?></div>
                    </div>
                    <div class="header-stat">
                        <div class="header-stat-value"><?= number_format($percentage, 1) ?>%</div>
                        <div class="header-stat-label">Tiến độ</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background: #DBEAFE; color: var(--primary);">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-label">Trung bình mỗi ngày</div>
                <div class="stat-value" style="color: var(--primary);">
                    <?= number_format($avgPerDay, 0, ',', '.') ?>đ
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #FEF3C7; color: var(--warning);">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-label">Ngày còn lại</div>
                <div class="stat-value" style="color: var(--warning);">
                    <?= number_format($daysRemaining, 0) ?> ngày
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: <?= $projectedTotal <= $budget['amount'] ? '#D1FAE5' : '#FEE2E2' ?>; color: <?= $projectedTotal <= $budget['amount'] ? 'var(--success)' : 'var(--danger)' ?>;">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="stat-label">Dự kiến cuối kỳ</div>
                <div class="stat-value" style="color: <?= $projectedTotal <= $budget['amount'] ? 'var(--success)' : 'var(--danger)' ?>;">
                    <?= number_format($projectedTotal, 0, ',', '.') ?>đ
                </div>
            </div>
        </div>

        <!-- Transactions -->
        <div class="section">
            <h2 class="section-title">
                <i class="fas fa-history"></i>
                Lịch sử giao dịch (<?= $transactions->num_rows ?> giao dịch)
            </h2>

            <?php if ($transactions->num_rows > 0): ?>
                <div class="transaction-list">
                    <?php while ($trans = $transactions->fetch_assoc()): ?>
                        <div class="transaction-item">
                            <div class="transaction-icon">
                                <?= htmlspecialchars($budget['category_icon']) ?>
                            </div>
                            <div class="transaction-info">
                                <div class="transaction-name"><?= htmlspecialchars($trans['description']) ?></div>
                                <div class="transaction-meta">
                                    <i class="fas fa-wallet"></i> <?= htmlspecialchars($trans['wallet_name']) ?> • 
                                    <i class="fas fa-calendar"></i> <?= date('d/m/Y', strtotime($trans['transaction_date'])) ?>
                                </div>
                            </div>
                            <div class="transaction-amount">
                                -<?= number_format($trans['amount'], 0, ',', '.') ?>đ
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">📭</div>
                    <p>Chưa có giao dịch nào trong kỳ ngân sách này</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
