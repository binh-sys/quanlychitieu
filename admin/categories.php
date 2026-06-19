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

// Handle delete category
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $conn->query("DELETE FROM categories WHERE id = $delete_id");
    header('Location: categories.php?msg=deleted');
    exit();
}

// Get categories with transaction count
$categories_query = "SELECT c.*, 
                     COUNT(t.id) as transaction_count,
                     COALESCE(SUM(t.amount), 0) as total_amount
                     FROM categories c
                     LEFT JOIN transactions t ON c.id = t.category_id
                     GROUP BY c.id
                     ORDER BY c.type, c.name";
$categories = $conn->query($categories_query);

// Get statistics
$stats = [];
$stats['total'] = $conn->query("SELECT COUNT(*) as total FROM categories")->fetch_assoc()['total'];
$stats['income_cat'] = $conn->query("SELECT COUNT(*) as total FROM categories WHERE type = 'income'")->fetch_assoc()['total'];
$stats['expense_cat'] = $conn->query("SELECT COUNT(*) as total FROM categories WHERE type = 'expense'")->fetch_assoc()['total'];
$stats['system_cat'] = $conn->query("SELECT COUNT(*) as total FROM categories WHERE is_system = 1")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Danh mục - Admin</title>
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <?php include 'includes/header.php'; ?>

        <div class="content">
            <div class="page-header">
                <h1>Quản lý Danh mục</h1>
                <p>Quản lý danh mục thu chi trong hệ thống</p>
            </div>

            <?php if (isset($_GET['msg'])): ?>
            <div style="padding: 12px 20px; background: #D1FAE5; color: #065F46; border-radius: 8px; margin-bottom: 20px; border: 1px solid #6EE7B7;">
                ✓ <?php 
                    if ($_GET['msg'] == 'deleted') echo 'Đã xóa danh mục thành công!';
                    elseif ($_GET['msg'] == 'added') echo 'Đã thêm danh mục thành công!';
                    elseif ($_GET['msg'] == 'updated') echo 'Đã cập nhật danh mục thành công!';
                    else echo 'Thao tác thành công!';
                ?>
            </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(99, 102, 241, 0.1); color: #6366F1;">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
                            <line x1="7" y1="7" x2="7.01" y2="7"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Tổng danh mục</div>
                        <div class="stat-value"><?= number_format($stats['total']) ?></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: #10B981;">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 1v22m5-18H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Danh mục thu nhập</div>
                        <div class="stat-value" style="color: #10B981;"><?= number_format($stats['income_cat']) ?></div>
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
                        <div class="stat-label">Danh mục chi tiêu</div>
                        <div class="stat-value" style="color: #EF4444;"><?= number_format($stats['expense_cat']) ?></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1); color: #F59E0B;">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Danh mục hệ thống</div>
                        <div class="stat-value"><?= number_format($stats['system_cat']) ?></div>
                    </div>
                </div>
            </div>

            <!-- Categories Grid -->
            <div style="margin-top: 24px;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
                    <h2 style="font-size: 18px; font-weight: 700;">Danh sách danh mục</h2>
                    <button onclick="window.location.href='category-add.php'" style="padding: 10px 20px; background: #6366F1; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                        + Thêm danh mục
                    </button>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px;">
                    <?php 
                    $categories->data_seek(0);
                    while ($cat = $categories->fetch_assoc()): 
                    ?>
                    <div style="background: white; border: 1px solid #E5E7EB; border-radius: 12px; padding: 20px; position: relative;">
                        <!-- Type Badge -->
                        <div style="position: absolute; top: 12px; right: 12px;">
                            <span style="padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600; background: <?= $cat['type'] == 'income' ? 'rgba(16, 185, 129, 0.1)' : 'rgba(239, 68, 68, 0.1)' ?>; color: <?= $cat['type'] == 'income' ? '#10B981' : '#EF4444' ?>;">
                                <?= $cat['type'] == 'income' ? 'Thu' : 'Chi' ?>
                            </span>
                        </div>

                        <!-- Icon & Name -->
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                            <div style="width: 48px; height: 48px; border-radius: 12px; background: <?= $cat['color'] ?>15; display: flex; align-items: center; justify-content: center; font-size: 24px;">
                                <?= $cat['icon'] ?>
                            </div>
                            <div style="flex: 1;">
                                <div style="font-weight: 700; font-size: 16px; margin-bottom: 2px;">
                                    <?= htmlspecialchars($cat['name']) ?>
                                </div>
                                <?php if ($cat['is_system']): ?>
                                <div style="font-size: 11px; color: #F59E0B;">
                                    🔒 Hệ thống
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Stats -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px; padding-top: 16px; border-top: 1px solid #E5E7EB;">
                            <div>
                                <div style="font-size: 11px; color: #6B7280; margin-bottom: 4px;">Giao dịch</div>
                                <div style="font-size: 18px; font-weight: 700;"><?= number_format($cat['transaction_count']) ?></div>
                            </div>
                            <div>
                                <div style="font-size: 11px; color: #6B7280; margin-bottom: 4px;">Tổng tiền</div>
                                <div style="font-size: 18px; font-weight: 700; color: <?= $cat['type'] == 'income' ? '#10B981' : '#EF4444' ?>;">
                                    <?= number_format($cat['total_amount'] / 1000000, 1) ?>M
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div style="display: flex; gap: 8px;">
                            <button onclick="window.location.href='category-edit.php?id=<?= $cat['id'] ?>'" style="flex: 1; padding: 8px; border: 1px solid #E5E7EB; background: white; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 500;">
                                ✏️ Sửa
                            </button>
                            <?php if (!$cat['is_system']): ?>
                            <button onclick="if(confirm('Bạn có chắc muốn xóa danh mục này?')) window.location.href='categories.php?delete=<?= $cat['id'] ?>'" style="padding: 8px 12px; border: 1px solid #FEE2E2; background: white; color: #EF4444; border-radius: 8px; cursor: pointer; font-size: 13px;">
                                🗑️
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/admin.js"></script>
</body>
</html>
