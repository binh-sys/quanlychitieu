<?php
// session_start();
require_once '../connect.php';

// Get user info
$user = [
    'id' => 1,
    'full_name' => 'Administrator',
    'email' => 'admin@fintrack.vn',
    'role' => 'admin'
];

// Handle delete user
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $conn->query("DELETE FROM users WHERE id = $delete_id");
    header('Location: users.php?msg=deleted');
    exit();
}

// Get all users with statistics
$users_query = "SELECT u.*, 
                COUNT(DISTINCT t.id) as total_transactions,
                COALESCE(SUM(CASE WHEN t.type = 'income' THEN t.amount ELSE 0 END), 0) as total_income,
                COALESCE(SUM(CASE WHEN t.type = 'expense' THEN t.amount ELSE 0 END), 0) as total_expense
                FROM users u
                LEFT JOIN transactions t ON u.id = t.user_id
                GROUP BY u.id
                ORDER BY u.created_at DESC";
$users = $conn->query($users_query);

// Get statistics
$stats = [];
$stats['total_users'] = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'];
$stats['total_transactions'] = $conn->query("SELECT COUNT(*) as total FROM transactions")->fetch_assoc()['total'];
$stats['total_income'] = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE type = 'income'")->fetch_assoc()['total'];
$stats['total_expense'] = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM transactions WHERE type = 'expense'")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Người dùng - Admin</title>
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <?php include 'includes/header.php'; ?>

        <div class="content">
            <div class="page-header">
                <h1>Quản lý Người dùng</h1>
                <p>Quản lý tất cả người dùng trong hệ thống</p>
            </div>

            <?php if (isset($_GET['msg'])): ?>
            <div style="padding: 12px 20px; background: #D1FAE5; color: #065F46; border-radius: 8px; margin-bottom: 20px; border: 1px solid #6EE7B7;">
                ✓ <?php 
                    if ($_GET['msg'] == 'deleted') echo 'Đã xóa người dùng thành công!';
                    elseif ($_GET['msg'] == 'added') echo 'Đã thêm người dùng thành công!';
                    elseif ($_GET['msg'] == 'updated') echo 'Đã cập nhật người dùng thành công!';
                    else echo 'Thao tác thành công!';
                ?>
            </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(99, 102, 241, 0.1); color: #6366F1;">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Tổng người dùng</div>
                        <div class="stat-value"><?= number_format($stats['total_users']) ?></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: #10B981;">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Tổng giao dịch</div>
                        <div class="stat-value"><?= number_format($stats['total_transactions']) ?></div>
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
                        <div class="stat-label">Tổng thu nhập</div>
                        <div class="stat-value" style="color: #10B981;"><?= number_format($stats['total_income']) ?>đ</div>
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
                        <div class="stat-value" style="color: #EF4444;"><?= number_format($stats['total_expense']) ?>đ</div>
                    </div>
                </div>
            </div>

            <!-- Users Table -->
            <div class="card" style="margin-top: 24px;">
                <div class="card-header">
                    <h2>Danh sách người dùng</h2>
                    <button class="btn-link" onclick="window.location.href='user-add.php'" style="background: #6366F1; color: white; padding: 8px 16px; border-radius: 8px; border: none; cursor: pointer;">
                        + Thêm người dùng
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tên đầy đủ</th>
                                <th>Email</th>
                                <th>Username</th>
                                <th>Vai trò</th>
                                <th>Giao dịch</th>
                                <th>Thu nhập</th>
                                <th>Chi tiêu</th>
                                <th>Ngày tạo</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($user_row = $users->fetch_assoc()): ?>
                            <tr>
                                <td><?= $user_row['id'] ?></td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div style="width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, #6366F1, #8B5CF6); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 12px;">
                                            <?= strtoupper(substr($user_row['full_name'], 0, 2)) ?>
                                        </div>
                                        <strong><?= htmlspecialchars($user_row['full_name']) ?></strong>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($user_row['email']) ?></td>
                                <td><?= htmlspecialchars($user_row['username']) ?></td>
                                <td>
                                    <span style="padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 500; background: <?= $user_row['role'] == 'admin' ? 'rgba(239, 68, 68, 0.1)' : 'rgba(99, 102, 241, 0.1)' ?>; color: <?= $user_row['role'] == 'admin' ? '#EF4444' : '#6366F1' ?>;">
                                        <?= $user_row['role'] == 'admin' ? '👑 Admin' : '👤 User' ?>
                                    </span>
                                </td>
                                <td><?= number_format($user_row['total_transactions']) ?></td>
                                <td style="color: #10B981; font-weight: 600;"><?= number_format($user_row['total_income']) ?>đ</td>
                                <td style="color: #EF4444; font-weight: 600;"><?= number_format($user_row['total_expense']) ?>đ</td>
                                <td><?= date('d/m/Y', strtotime($user_row['created_at'])) ?></td>
                                <td>
                                    <div style="display: flex; gap: 6px;">
                                        <button onclick="window.location.href='user-edit.php?id=<?= $user_row['id'] ?>'" style="padding: 6px 12px; border-radius: 6px; border: 1px solid #E5E7EB; background: white; cursor: pointer; font-size: 12px;" title="Sửa">
                                            ✏️
                                        </button>
                                        <button onclick="if(confirm('Bạn có chắc muốn xóa người dùng này?')) window.location.href='users.php?delete=<?= $user_row['id'] ?>'" style="padding: 6px 12px; border-radius: 6px; border: 1px solid #FEE2E2; background: white; color: #EF4444; cursor: pointer; font-size: 12px;" title="Xóa">
                                            🗑️
                                        </button>
                                        <button onclick="window.location.href='user-detail.php?id=<?= $user_row['id'] ?>'" style="padding: 6px 12px; border-radius: 6px; border: 1px solid #E5E7EB; background: white; cursor: pointer; font-size: 12px;" title="Chi tiết">
                                            👁️
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/admin.js"></script>
</body>
</html>
