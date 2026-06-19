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

// Handle delete transaction
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $conn->query("DELETE FROM transactions WHERE id = $delete_id");
    header('Location: transactions.php?msg=deleted');
    exit();
}

// Filters
$filter_type = isset($_GET['type']) ? $_GET['type'] : '';
$filter_user = isset($_GET['user']) ? intval($_GET['user']) : 0;
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';

// Build query
$where = [];
if ($filter_type) $where[] = "t.type = '$filter_type'";
if ($filter_user) $where[] = "t.user_id = $filter_user";
if ($filter_date) $where[] = "DATE(t.transaction_date) = '$filter_date'";

$where_sql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

// Get transactions
$transactions_query = "SELECT t.*, u.full_name, u.email, c.name as category_name, c.icon, w.name as wallet_name
                       FROM transactions t
                       JOIN users u ON t.user_id = u.id
                       JOIN categories c ON t.category_id = c.id
                       JOIN wallets w ON t.wallet_id = w.id
                       $where_sql
                       ORDER BY t.transaction_date DESC, t.created_at DESC
                       LIMIT 100";
$transactions = $conn->query($transactions_query);

// Get statistics
$stats = [];
$stats['total'] = $conn->query("SELECT COUNT(*) as total FROM transactions t $where_sql")->fetch_assoc()['total'];

// Fix WHERE clause for income/expense queries
$income_where = $where_sql ? $where_sql . " AND t.type = 'income'" : "WHERE t.type = 'income'";
$expense_where = $where_sql ? $where_sql . " AND t.type = 'expense'" : "WHERE t.type = 'expense'";

$stats['income'] = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM transactions t $income_where")->fetch_assoc()['total'];
$stats['expense'] = $conn->query("SELECT COALESCE(SUM(amount), 0) as total FROM transactions t $expense_where")->fetch_assoc()['total'];
$stats['balance'] = $stats['income'] - $stats['expense'];

// Get users for filter
$users_list = $conn->query("SELECT id, full_name FROM users ORDER BY full_name");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Giao dịch - Admin</title>
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <?php include 'includes/header.php'; ?>

        <div class="content">
            <div class="page-header">
                <h1>Quản lý Giao dịch</h1>
                <p>Theo dõi và quản lý tất cả giao dịch trong hệ thống</p>
            </div>

            <?php if (isset($_GET['msg'])): ?>
            <div style="padding: 12px 20px; background: #D1FAE5; color: #065F46; border-radius: 8px; margin-bottom: 20px; border: 1px solid #6EE7B7;">
                ✓ <?php 
                    if ($_GET['msg'] == 'deleted') echo 'Đã xóa giao dịch thành công!';
                    elseif ($_GET['msg'] == 'added') echo 'Đã thêm giao dịch thành công!';
                    else echo 'Thao tác thành công!';
                ?>
            </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(99, 102, 241, 0.1); color: #6366F1;">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Tổng giao dịch</div>
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
                        <div class="stat-label">Tổng thu nhập</div>
                        <div class="stat-value" style="color: #10B981;">+<?= number_format($stats['income']) ?>đ</div>
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
                        <div class="stat-value" style="color: #EF4444;">-<?= number_format($stats['expense']) ?>đ</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(245, 158, 11, 0.1); color: #F59E0B;">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="1" x2="12" y2="23"/>
                            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Số dư</div>
                        <div class="stat-value" style="color: <?= $stats['balance'] >= 0 ? '#10B981' : '#EF4444' ?>;">
                            <?= number_format($stats['balance']) ?>đ
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card" style="margin-top: 24px;">
                <div class="card-header">
                    <h2>Bộ lọc</h2>
                    <button onclick="window.location.href='transactions.php'" style="padding: 6px 12px; border-radius: 6px; border: 1px solid #E5E7EB; background: white; cursor: pointer; font-size: 13px;">
                        🔄 Reset
                    </button>
                </div>
                <div style="padding: 20px;">
                    <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 600; color: #6B7280; margin-bottom: 6px;">Loại giao dịch</label>
                            <select name="type" style="width: 100%; padding: 8px 12px; border: 1px solid #E5E7EB; border-radius: 8px; font-size: 14px;">
                                <option value="">Tất cả</option>
                                <option value="income" <?= $filter_type == 'income' ? 'selected' : '' ?>>Thu nhập</option>
                                <option value="expense" <?= $filter_type == 'expense' ? 'selected' : '' ?>>Chi tiêu</option>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 600; color: #6B7280; margin-bottom: 6px;">Người dùng</label>
                            <select name="user" style="width: 100%; padding: 8px 12px; border: 1px solid #E5E7EB; border-radius: 8px; font-size: 14px;">
                                <option value="">Tất cả</option>
                                <?php while ($u = $users_list->fetch_assoc()): ?>
                                <option value="<?= $u['id'] ?>" <?= $filter_user == $u['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($u['full_name']) ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 600; color: #6B7280; margin-bottom: 6px;">Ngày</label>
                            <input type="date" name="date" value="<?= $filter_date ?>" style="width: 100%; padding: 8px 12px; border: 1px solid #E5E7EB; border-radius: 8px; font-size: 14px;">
                        </div>
                        <div style="display: flex; align-items: flex-end;">
                            <button type="submit" style="width: 100%; padding: 10px; background: #6366F1; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                                🔍 Lọc
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Transactions Table -->
            <div class="card" style="margin-top: 24px;">
                <div class="card-header">
                    <h2>Danh sách giao dịch (<?= number_format($stats['total']) ?>)</h2>
                    <button onclick="window.location.href='transaction-add.php'" style="padding: 10px 20px; background: #6366F1; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                        + Thêm giao dịch
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Người dùng</th>
                                <th>Danh mục</th>
                                <th>Ví</th>
                                <th>Mô tả</th>
                                <th>Số tiền</th>
                                <th>Loại</th>
                                <th>Ngày GD</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($transactions->num_rows > 0): ?>
                                <?php while ($tx = $transactions->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $tx['id'] ?></td>
                                    <td>
                                        <div style="font-weight: 500;"><?= htmlspecialchars($tx['full_name']) ?></div>
                                        <div style="font-size: 12px; color: #6B7280;"><?= htmlspecialchars($tx['email']) ?></div>
                                    </td>
                                    <td>
                                        <span style="margin-right: 4px;"><?= $tx['icon'] ?></span>
                                        <?= htmlspecialchars($tx['category_name']) ?>
                                    </td>
                                    <td><?= htmlspecialchars($tx['wallet_name']) ?></td>
                                    <td><?= htmlspecialchars($tx['description']) ?></td>
                                    <td style="font-weight: 700; color: <?= $tx['type'] == 'income' ? '#10B981' : '#EF4444' ?>;">
                                        <?= $tx['type'] == 'income' ? '+' : '-' ?><?= number_format($tx['amount']) ?>đ
                                    </td>
                                    <td>
                                        <span style="padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 500; background: <?= $tx['type'] == 'income' ? 'rgba(16, 185, 129, 0.1)' : 'rgba(239, 68, 68, 0.1)' ?>; color: <?= $tx['type'] == 'income' ? '#10B981' : '#EF4444' ?>;">
                                            <?= $tx['type'] == 'income' ? '📈 Thu' : '📉 Chi' ?>
                                        </span>
                                    </td>
                                    <td><?= date('d/m/Y', strtotime($tx['transaction_date'])) ?></td>
                                    <td>
                                        <div style="display: flex; gap: 6px;">
                                            <button onclick="window.location.href='transaction-detail.php?id=<?= $tx['id'] ?>'" style="padding: 6px 12px; border-radius: 6px; border: 1px solid #E5E7EB; background: white; cursor: pointer; font-size: 12px;" title="Chi tiết">
                                                👁️
                                            </button>
                                            <button onclick="if(confirm('Bạn có chắc muốn xóa giao dịch này?')) window.location.href='transactions.php?delete=<?= $tx['id'] ?>'" style="padding: 6px 12px; border-radius: 6px; border: 1px solid #FEE2E2; background: white; color: #EF4444; cursor: pointer; font-size: 12px;" title="Xóa">
                                                🗑️
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 40px; color: #6B7280;">
                                        Không có giao dịch nào
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/admin.js"></script>
</body>
</html>
