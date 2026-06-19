<?php
require_once '../connect.php';

// Get user info
$user = [
    'id' => 1,
    'full_name' => 'Administrator',
    'email' => 'admin@fintrack.vn',
    'role' => 'admin'
];

// Handle delete notification
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $conn->query("DELETE FROM notifications WHERE id = $delete_id");
    header('Location: notifications.php?msg=deleted');
    exit();
}

// Handle mark as read
if (isset($_GET['mark_read'])) {
    $notif_id = intval($_GET['mark_read']);
    $conn->query("UPDATE notifications SET is_read = 1 WHERE id = $notif_id");
    header('Location: notifications.php?msg=marked');
    exit();
}

// Handle mark all as read
if (isset($_GET['mark_all_read'])) {
    $conn->query("UPDATE notifications SET is_read = 1");
    header('Location: notifications.php?msg=all_marked');
    exit();
}

// Filters
$filter_type = isset($_GET['type']) ? $_GET['type'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_user = isset($_GET['user']) ? intval($_GET['user']) : 0;

// Build query
$where = [];
if ($filter_type) $where[] = "n.type = '$filter_type'";
if ($filter_status == 'read') $where[] = "n.is_read = 1";
if ($filter_status == 'unread') $where[] = "n.is_read = 0";
if ($filter_user > 0) $where[] = "n.user_id = $filter_user";

$where_sql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

// Get notifications
$notifications_query = "SELECT n.*, u.full_name, u.email
                        FROM notifications n
                        JOIN users u ON n.user_id = u.id
                        $where_sql
                        ORDER BY n.created_at DESC
                        LIMIT 100";
$notifications = $conn->query($notifications_query);

// Get statistics
$stats = [];
$stats['total'] = $conn->query("SELECT COUNT(*) as total FROM notifications")->fetch_assoc()['total'];
$stats['unread'] = $conn->query("SELECT COUNT(*) as total FROM notifications WHERE is_read = 0")->fetch_assoc()['total'];
$stats['read'] = $conn->query("SELECT COUNT(*) as total FROM notifications WHERE is_read = 1")->fetch_assoc()['total'];
$stats['today'] = $conn->query("SELECT COUNT(*) as total FROM notifications WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['total'];

// Get users for filter
$users_list = $conn->query("SELECT id, full_name FROM users ORDER BY full_name");

// Notification types
$notification_types = [
    'budget_alert' => ['icon' => '⚠️', 'color' => '#F59E0B', 'label' => 'Cảnh báo ngân sách'],
    'bill_reminder' => ['icon' => '🔔', 'color' => '#6366F1', 'label' => 'Nhắc hóa đơn'],
    'goal_progress' => ['icon' => '🎯', 'color' => '#10B981', 'label' => 'Tiến độ mục tiêu'],
    'system' => ['icon' => '⚙️', 'color' => '#6B7280', 'label' => 'Hệ thống']
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Thông báo - Admin</title>
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <?php include 'includes/header.php'; ?>

        <div class="content">
            <div class="page-header">
                <h1>🔔 Quản lý Thông báo</h1>
                <p>Gửi và quản lý thông báo đến người dùng</p>
            </div>

            <?php if (isset($_GET['msg'])): ?>
            <div style="padding: 12px 20px; background: #D1FAE5; color: #065F46; border-radius: 8px; margin-bottom: 20px; border: 1px solid #6EE7B7;">
                ✓ <?php 
                    if ($_GET['msg'] == 'deleted') echo 'Đã xóa thông báo thành công!';
                    elseif ($_GET['msg'] == 'added') echo 'Đã gửi thông báo thành công!';
                    elseif ($_GET['msg'] == 'marked') echo 'Đã đánh dấu đã đọc!';
                    elseif ($_GET['msg'] == 'all_marked') echo 'Đã đánh dấu tất cả đã đọc!';
                    else echo 'Thao tác thành công!';
                ?>
            </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(99, 102, 241, 0.1); color: #6366F1;">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Tổng thông báo</div>
                        <div class="stat-value"><?= number_format($stats['total']) ?></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(239, 68, 68, 0.1); color: #EF4444;">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="8" x2="12" y2="12"/>
                            <line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Chưa đọc</div>
                        <div class="stat-value" style="color: #EF4444;"><?= number_format($stats['unread']) ?></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: #10B981;">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                            <polyline points="22 4 12 14.01 9 11.01"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Đã đọc</div>
                        <div class="stat-value" style="color: #10B981;"><?= number_format($stats['read']) ?></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1); color: #F59E0B;">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Hôm nay</div>
                        <div class="stat-value"><?= number_format($stats['today']) ?></div>
                    </div>
                </div>
            </div>

            <!-- Actions Bar -->
            <div style="display: flex; gap: 12px; margin-bottom: 24px;">
                <button onclick="window.location.href='notification-add.php'" style="padding: 10px 20px; background: #6366F1; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                    + Gửi thông báo mới
                </button>
                <button onclick="if(confirm('Đánh dấu tất cả đã đọc?')) window.location.href='notifications.php?mark_all_read=1'" style="padding: 10px 20px; background: white; border: 1px solid #E5E7EB; border-radius: 8px; font-weight: 600; cursor: pointer;">
                    ✓ Đánh dấu tất cả đã đọc
                </button>
            </div>

            <!-- Filters -->
            <div class="card" style="margin-bottom: 24px;">
                <div class="card-header">
                    <h2>Bộ lọc</h2>
                    <button onclick="window.location.href='notifications.php'" style="padding: 6px 12px; border-radius: 6px; border: 1px solid #E5E7EB; background: white; cursor: pointer; font-size: 13px;">
                        🔄 Reset
                    </button>
                </div>
                <div style="padding: 20px;">
                    <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; align-items: end;">
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 600; color: #6B7280; margin-bottom: 6px;">Loại thông báo</label>
                            <select name="type" style="width: 100%; padding: 8px 12px; border: 1px solid #E5E7EB; border-radius: 8px; font-size: 14px;">
                                <option value="">Tất cả</option>
                                <?php foreach ($notification_types as $key => $type): ?>
                                <option value="<?= $key ?>" <?= $filter_type == $key ? 'selected' : '' ?>>
                                    <?= $type['icon'] ?> <?= $type['label'] ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 600; color: #6B7280; margin-bottom: 6px;">Trạng thái</label>
                            <select name="status" style="width: 100%; padding: 8px 12px; border: 1px solid #E5E7EB; border-radius: 8px; font-size: 14px;">
                                <option value="">Tất cả</option>
                                <option value="unread" <?= $filter_status == 'unread' ? 'selected' : '' ?>>Chưa đọc</option>
                                <option value="read" <?= $filter_status == 'read' ? 'selected' : '' ?>>Đã đọc</option>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 600; color: #6B7280; margin-bottom: 6px;">Người dùng</label>
                            <select name="user" style="width: 100%; padding: 8px 12px; border: 1px solid #E5E7EB; border-radius: 8px; font-size: 14px;">
                                <option value="0">Tất cả</option>
                                <?php 
                                $users_list->data_seek(0);
                                while ($u = $users_list->fetch_assoc()): 
                                ?>
                                <option value="<?= $u['id'] ?>" <?= $filter_user == $u['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($u['full_name']) ?>
                                </option>
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

            <!-- Notifications List -->
            <div class="card">
                <div class="card-header">
                    <h2>Danh sách thông báo (<?= $notifications->num_rows ?>)</h2>
                </div>
                <div style="padding: 20px;">
                    <?php if ($notifications->num_rows > 0): ?>
                        <?php while ($notif = $notifications->fetch_assoc()): 
                            $type_info = $notification_types[$notif['type']] ?? $notification_types['system'];
                        ?>
                        <div style="display: flex; gap: 16px; padding: 16px; border: 1px solid #E5E7EB; border-radius: 10px; margin-bottom: 12px; background: <?= $notif['is_read'] ? 'white' : '#F0F9FF' ?>;">
                            <!-- Icon -->
                            <div style="width: 48px; height: 48px; border-radius: 12px; background: <?= $type_info['color'] ?>15; display: flex; align-items: center; justify-content: center; font-size: 24px; flex-shrink: 0;">
                                <?= $type_info['icon'] ?>
                            </div>

                            <!-- Content -->
                            <div style="flex: 1; min-width: 0;">
                                <div style="display: flex; align-items: start; justify-content: space-between; margin-bottom: 6px;">
                                    <div>
                                        <div style="font-weight: 700; font-size: 15px; margin-bottom: 2px;">
                                            <?= htmlspecialchars($notif['title']) ?>
                                            <?php if (!$notif['is_read']): ?>
                                            <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #EF4444; margin-left: 6px;"></span>
                                            <?php endif; ?>
                                        </div>
                                        <div style="font-size: 12px; color: #6B7280;">
                                            <span style="padding: 2px 8px; background: <?= $type_info['color'] ?>15; color: <?= $type_info['color'] ?>; border-radius: 4px; font-weight: 600;">
                                                <?= $type_info['label'] ?>
                                            </span>
                                            <span style="margin: 0 8px;">•</span>
                                            <strong><?= htmlspecialchars($notif['full_name']) ?></strong>
                                            <span style="margin: 0 8px;">•</span>
                                            <?= date('d/m/Y H:i', strtotime($notif['created_at'])) ?>
                                        </div>
                                    </div>
                                </div>
                                <div style="font-size: 14px; color: #374151; line-height: 1.6; margin-bottom: 8px;">
                                    <?= nl2br(htmlspecialchars($notif['message'])) ?>
                                </div>
                                <?php if ($notif['link']): ?>
                                <div style="margin-top: 8px;">
                                    <a href="<?= htmlspecialchars($notif['link']) ?>" style="font-size: 13px; color: #6366F1; text-decoration: none; font-weight: 600;">
                                        → Xem chi tiết
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Actions -->
                            <div style="display: flex; flex-direction: column; gap: 6px; flex-shrink: 0;">
                                <?php if (!$notif['is_read']): ?>
                                <button onclick="window.location.href='notifications.php?mark_read=<?= $notif['id'] ?>'" style="padding: 6px 12px; border-radius: 6px; border: 1px solid #E5E7EB; background: white; cursor: pointer; font-size: 12px; white-space: nowrap;" title="Đánh dấu đã đọc">
                                    ✓ Đã đọc
                                </button>
                                <?php endif; ?>
                                <button onclick="if(confirm('Bạn có chắc muốn xóa thông báo này?')) window.location.href='notifications.php?delete=<?= $notif['id'] ?>'" style="padding: 6px 12px; border-radius: 6px; border: 1px solid #FEE2E2; background: white; color: #EF4444; cursor: pointer; font-size: 12px;" title="Xóa">
                                    🗑️
                                </button>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 60px 20px; color: #6B7280;">
                            <div style="font-size: 48px; margin-bottom: 16px;">🔔</div>
                            <div style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">Không có thông báo</div>
                            <div style="font-size: 14px;">Chưa có thông báo nào trong hệ thống</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/admin.js"></script>
</body>
</html>
