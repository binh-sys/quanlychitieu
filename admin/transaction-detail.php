<?php
require_once '../connect.php';

// Get user info
$user = [
    'id' => 1,
    'full_name' => 'Administrator',
    'email' => 'admin@fintrack.vn',
    'role' => 'admin'
];

// Get transaction ID
if (!isset($_GET['id'])) {
    header('Location: transactions.php');
    exit();
}

$tx_id = intval($_GET['id']);

// Get transaction details
$stmt = $conn->prepare("SELECT t.*, 
                        u.full_name, u.email, u.phone,
                        c.name as category_name, c.icon as category_icon, c.color as category_color,
                        w.name as wallet_name, w.type as wallet_type, w.balance as wallet_balance
                        FROM transactions t
                        JOIN users u ON t.user_id = u.id
                        JOIN categories c ON t.category_id = c.id
                        JOIN wallets w ON t.wallet_id = w.id
                        WHERE t.id = ?");
$stmt->bind_param("i", $tx_id);
$stmt->execute();
$tx = $stmt->get_result()->fetch_assoc();

if (!$tx) {
    header('Location: transactions.php?msg=notfound');
    exit();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chi tiết Giao dịch - Admin</title>
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <?php include 'includes/header.php'; ?>

        <div class="content">
            <div class="page-header">
                <h1>Chi tiết Giao dịch #<?= $tx_id ?></h1>
                <div style="display: flex; gap: 8px;">
                    <button onclick="if(confirm('Bạn có chắc muốn xóa giao dịch này?')) window.location.href='transactions.php?delete=<?= $tx_id ?>'" style="padding: 10px 20px; background: #EF4444; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                        🗑️ Xóa
                    </button>
                    <button onclick="window.location.href='transactions.php'" style="padding: 10px 20px; background: white; border: 1px solid #E5E7EB; border-radius: 8px; font-weight: 600; cursor: pointer;">
                        ← Quay lại
                    </button>
                </div>
            </div>

            <!-- Transaction Info Card -->
            <div class="card" style="margin-bottom: 24px;">
                <div style="padding: 32px;">
                    <!-- Amount Badge -->
                    <div style="text-align: center; margin-bottom: 32px; padding: 24px; background: <?= $tx['type'] == 'income' ? 'rgba(16, 185, 129, 0.05)' : 'rgba(239, 68, 68, 0.05)' ?>; border-radius: 12px; border: 2px solid <?= $tx['type'] == 'income' ? '#10B981' : '#EF4444' ?>;">
                        <div style="font-size: 14px; color: #6B7280; margin-bottom: 8px;">
                            <?= $tx['type'] == 'income' ? '📈 Thu nhập' : '📉 Chi tiêu' ?>
                        </div>
                        <div style="font-size: 42px; font-weight: 800; color: <?= $tx['type'] == 'income' ? '#10B981' : '#EF4444' ?>; letter-spacing: -1px;">
                            <?= $tx['type'] == 'income' ? '+' : '-' ?><?= number_format($tx['amount']) ?>đ
                        </div>
                        <?php if ($tx['description']): ?>
                        <div style="font-size: 16px; color: #374151; margin-top: 8px; font-weight: 500;">
                            <?= htmlspecialchars($tx['description']) ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Details Grid -->
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 24px;">
                        <!-- User Info -->
                        <div style="padding: 20px; background: #F9FAFB; border-radius: 10px;">
                            <div style="font-size: 12px; color: #6B7280; margin-bottom: 8px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                                👤 Người dùng
                            </div>
                            <div style="font-weight: 700; font-size: 16px; margin-bottom: 4px;">
                                <?= htmlspecialchars($tx['full_name']) ?>
                            </div>
                            <div style="font-size: 13px; color: #6B7280;">
                                <?= htmlspecialchars($tx['email']) ?>
                            </div>
                            <?php if ($tx['phone']): ?>
                            <div style="font-size: 13px; color: #6B7280; margin-top: 2px;">
                                📱 <?= htmlspecialchars($tx['phone']) ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Category Info -->
                        <div style="padding: 20px; background: #F9FAFB; border-radius: 10px;">
                            <div style="font-size: 12px; color: #6B7280; margin-bottom: 8px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                                🏷️ Danh mục
                            </div>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="width: 40px; height: 40px; border-radius: 10px; background: <?= $tx['category_color'] ?>15; display: flex; align-items: center; justify-content: center; font-size: 20px;">
                                    <?= $tx['category_icon'] ?>
                                </div>
                                <div>
                                    <div style="font-weight: 700; font-size: 16px;">
                                        <?= htmlspecialchars($tx['category_name']) ?>
                                    </div>
                                    <div style="font-size: 12px; color: #6B7280;">
                                        <?= $tx['type'] == 'income' ? 'Thu nhập' : 'Chi tiêu' ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Wallet Info -->
                        <div style="padding: 20px; background: #F9FAFB; border-radius: 10px;">
                            <div style="font-size: 12px; color: #6B7280; margin-bottom: 8px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                                💰 Ví tiền
                            </div>
                            <div style="font-weight: 700; font-size: 16px; margin-bottom: 4px;">
                                <?= htmlspecialchars($tx['wallet_name']) ?>
                            </div>
                            <div style="font-size: 13px; color: #6B7280;">
                                Loại: <?= ucfirst($tx['wallet_type']) ?>
                            </div>
                            <div style="font-size: 13px; color: <?= $tx['wallet_balance'] >= 0 ? '#10B981' : '#EF4444' ?>; margin-top: 4px; font-weight: 600;">
                                Số dư: <?= number_format($tx['wallet_balance']) ?>đ
                            </div>
                        </div>

                        <!-- Date Info -->
                        <div style="padding: 20px; background: #F9FAFB; border-radius: 10px;">
                            <div style="font-size: 12px; color: #6B7280; margin-bottom: 8px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                                📅 Thời gian
                            </div>
                            <div style="font-weight: 700; font-size: 16px; margin-bottom: 4px;">
                                <?= date('d/m/Y', strtotime($tx['transaction_date'])) ?>
                            </div>
                            <div style="font-size: 13px; color: #6B7280;">
                                Ngày giao dịch
                            </div>
                            <div style="font-size: 12px; color: #9CA3AF; margin-top: 8px;">
                                Tạo lúc: <?= date('d/m/Y H:i', strtotime($tx['created_at'])) ?>
                            </div>
                        </div>
                    </div>

                    <!-- Note Section -->
                    <?php if ($tx['note']): ?>
                    <div style="margin-top: 24px; padding: 20px; background: #FFFBEB; border-left: 4px solid #F59E0B; border-radius: 8px;">
                        <div style="font-size: 12px; color: #92400E; margin-bottom: 6px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                            📝 Ghi chú
                        </div>
                        <div style="font-size: 14px; color: #78350F; line-height: 1.6;">
                            <?= nl2br(htmlspecialchars($tx['note'])) ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Additional Info -->
                    <?php if ($tx['location'] || $tx['tags'] || $tx['receipt_image']): ?>
                    <div style="margin-top: 24px; padding-top: 24px; border-top: 1px solid #E5E7EB;">
                        <div style="font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 12px;">
                            Thông tin bổ sung
                        </div>
                        <div style="display: grid; gap: 8px;">
                            <?php if ($tx['location']): ?>
                            <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #6B7280;">
                                <span>📍</span>
                                <span><?= htmlspecialchars($tx['location']) ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($tx['tags']): ?>
                            <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #6B7280;">
                                <span>🏷️</span>
                                <span><?= htmlspecialchars($tx['tags']) ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($tx['receipt_image']): ?>
                            <div style="display: flex; align-items: center; gap: 8px; font-size: 13px; color: #6B7280;">
                                <span>📎</span>
                                <span>Có hóa đơn đính kèm</span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Action Buttons -->
            <div style="display: flex; gap: 12px; justify-content: center;">
                <button onclick="window.location.href='user-detail.php?id=<?= $tx['user_id'] ?>'" style="padding: 12px 24px; background: white; border: 1px solid #E5E7EB; border-radius: 8px; font-weight: 600; cursor: pointer;">
                    👤 Xem người dùng
                </button>
                <button onclick="window.location.href='transactions.php?user=<?= $tx['user_id'] ?>'" style="padding: 12px 24px; background: white; border: 1px solid #E5E7EB; border-radius: 8px; font-weight: 600; cursor: pointer;">
                    📊 Xem tất cả GD của user này
                </button>
            </div>
        </div>
    </div>

    <script src="assets/js/admin.js"></script>
</body>
</html>
