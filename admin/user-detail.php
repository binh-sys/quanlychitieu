<?php
require_once '../connect.php';

// Get user info
$user = [
    'id' => 1,
    'full_name' => 'Administrator',
    'email' => 'admin@fintrack.vn',
    'role' => 'admin'
];

// Get user ID
if (!isset($_GET['id'])) {
    header('Location: users.php');
    exit();
}

$user_id = intval($_GET['id']);

// Get user data with statistics
$stmt = $conn->prepare("SELECT u.*, 
                        COUNT(DISTINCT t.id) as total_transactions,
                        COALESCE(SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE 0 END), 0) as total_income,
                        COALESCE(SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END), 0) as total_expense,
                        COUNT(DISTINCT w.id) as total_wallets,
                        COUNT(DISTINCT b.id) as total_budgets,
                        COUNT(DISTINCT sg.id) as total_goals
                        FROM users u
                        LEFT JOIN transactions t ON u.id = t.user_id
                        LEFT JOIN wallets w ON u.id = w.user_id
                        LEFT JOIN budgets b ON u.id = b.user_id
                        LEFT JOIN savings_goals sg ON u.id = sg.user_id
                        WHERE u.id = ?
                        GROUP BY u.id");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$detail_user = $stmt->get_result()->fetch_assoc();

if (!$detail_user) {
    header('Location: users.php?msg=notfound');
    exit();
}

// Get recent transactions
$recent_tx = $conn->prepare("SELECT t.*, c.name as category_name, c.icon, w.name as wallet_name
                             FROM transactions t
                             JOIN categories c ON t.category_id = c.id
                             JOIN wallets w ON t.wallet_id = w.id
                             WHERE t.user_id = ?
                             ORDER BY t.transaction_date DESC, t.created_at DESC
                             LIMIT 10");
$recent_tx->bind_param("i", $user_id);
$recent_tx->execute();
$transactions = $recent_tx->get_result();

// Get wallets
$wallets_query = $conn->prepare("SELECT * FROM wallets WHERE user_id = ? ORDER BY created_at DESC");
$wallets_query->bind_param("i", $user_id);
$wallets_query->execute();
$wallets = $wallets_query->get_result();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi tiết Người dùng - Admin</title>
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <?php include 'includes/header.php'; ?>

        <div class="content">
            <div class="page-header">
                <h1>Chi tiết Người dùng</h1>
                <div style="display: flex; gap: 8px;">
                    <button onclick="window.location.href='user-edit.php?id=<?= $user_id ?>'" style="padding: 10px 20px; background: #6366F1; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                        ✏️ Sửa
                    </button>
                    <button onclick="window.location.href='users.php'" style="padding: 10px 20px; background: white; border: 1px solid #E5E7EB; border-radius: 8px; font-weight: 600; cursor: pointer;">
                        ← Quay lại
                    </button>
                </div>
            </div>

            <!-- User Info Card -->
            <div class="card" style="margin-bottom: 24px;">
                <div style="padding: 32px;">
                    <div style="display: flex; align-items: center; gap: 24px; margin-bottom: 32px;">
                        <div style="width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, #6366F1, #8B5CF6); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 28px;">
                            <?= strtoupper(substr($detail_user['full_name'], 0, 2)) ?>
                        </div>
                        <div style="flex: 1;">
                            <h2 style="font-size: 24px; font-weight: 700; margin-bottom: 4px;">
                                <?= htmlspecialchars($detail_user['full_name']) ?>
                            </h2>
                            <div style="display: flex; align-items: center; gap: 12px; color: #6B7280;">
                                <span><?= htmlspecialchars($detail_user['email']) ?></span>
                                <span>•</span>
                                <span style="padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 500; background: <?= $detail_user['role'] == 'admin' ? 'rgba(239, 68, 68, 0.1)' : 'rgba(99, 102, 241, 0.1)' ?>; color: <?= $detail_user['role'] == 'admin' ? '#EF4444' : '#6366F1' ?>;">
                                    <?= $detail_user['role'] == 'admin' ? '👑 Admin' : '👤 User' ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; padding-top: 24px; border-top: 1px solid #E5E7EB;">
                        <div>
                            <div style="font-size: 12px; color: #6B7280; margin-bottom: 4px;">Username</div>
                            <div style="font-weight: 600;"><?= htmlspecialchars($detail_user['username']) ?></div>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: #6B7280; margin-bottom: 4px;">Số điện thoại</div>
                            <div style="font-weight: 600;"><?= $detail_user['phone'] ? htmlspecialchars($detail_user['phone']) : '-' ?></div>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: #6B7280; margin-bottom: 4px;">Ngân sách tháng</div>
                            <div style="font-weight: 600; color: #F59E0B;"><?= number_format($detail_user['monthly_budget']) ?>đ</div>
                        </div>
                        <div>
                            <div style="font-size: 12px; color: #6B7280; margin-bottom: 4px;">Ngày tạo</div>
                            <div style="font-weight: 600;"><?= date('d/m/Y H:i', strtotime($detail_user['created_at'])) ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(99, 102, 241, 0.1); color: #6366F1;">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Tổng giao dịch</div>
                        <div class="stat-value"><?= number_format($detail_user['total_transactions']) ?></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: #10B981;">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 1v22m5-18H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Tổng thu nhập</div>
                        <div class="stat-value" style="color: #10B981;">+<?= number_format($detail_user['total_income']) ?>đ</div>
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
                        <div class="stat-value" style="color: #EF4444;">-<?= number_format($detail_user['total_expense']) ?>đ</div>
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
                        <div class="stat-value" style="color: <?= ($detail_user['total_income'] - $detail_user['total_expense']) >= 0 ? '#10B981' : '#EF4444' ?>;">
                            <?= number_format($detail_user['total_income'] - $detail_user['total_expense']) ?>đ
                        </div>
                    </div>
                </div>
            </div>

            <!-- Wallets & Recent Transactions -->
            <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 24px; margin-top: 24px;">
                <!-- Wallets -->
                <div class="card">
                    <div class="card-header">
                        <h2>Ví tiền (<?= $detail_user['total_wallets'] ?>)</h2>
                    </div>
                    <div style="padding: 16px;">
                        <?php if ($wallets->num_rows > 0): ?>
                            <?php while ($wallet = $wallets->fetch_assoc()): ?>
                            <div style="padding: 12px; border: 1px solid #E5E7EB; border-radius: 8px; margin-bottom: 12px;">
                                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
                                    <span style="font-size: 20px;"><?= $wallet['icon'] ?></span>
                                    <strong><?= htmlspecialchars($wallet['name']) ?></strong>
                                </div>
                                <div style="font-size: 18px; font-weight: 700; color: <?= $wallet['balance'] >= 0 ? '#10B981' : '#EF4444' ?>;">
                                    <?= number_format($wallet['balance']) ?>đ
                                </div>
                                <div style="font-size: 11px; color: #6B7280; margin-top: 4px;">
                                    <?= ucfirst($wallet['type']) ?>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div style="text-align: center; padding: 20px; color: #6B7280;">
                                Chưa có ví nào
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Transactions -->
                <div class="card">
                    <div class="card-header">
                        <h2>Giao dịch gần đây</h2>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Danh mục</th>
                                    <th>Ví</th>
                                    <th>Mô tả</th>
                                    <th>Số tiền</th>
                                    <th>Ngày</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($transactions->num_rows > 0): ?>
                                    <?php while ($tx = $transactions->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <span style="margin-right: 4px;"><?= $tx['icon'] ?></span>
                                            <?= htmlspecialchars($tx['category_name']) ?>
                                        </td>
                                        <td><?= htmlspecialchars($tx['wallet_name']) ?></td>
                                        <td><?= htmlspecialchars($tx['description']) ?></td>
                                        <td style="font-weight: 700; color: <?= $tx['type'] == 'income' ? '#10B981' : '#EF4444' ?>;">
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
            </div>
        </div>
    </div>

    <script src="assets/js/admin.js"></script>
</body>
</html>
