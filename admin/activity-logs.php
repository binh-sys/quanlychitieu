<?php
require_once '../connect.php';

// Get user info
$user = [
    'id' => 1,
    'full_name' => 'Administrator',
    'email' => 'admin@fintrack.vn',
    'role' => 'admin'
];

// Handle AJAX request for log details
if (isset($_GET['ajax']) && isset($_GET['log_id'])) {
    header('Content-Type: application/json');
    $log_id = intval($_GET['log_id']);
    
    $query = "SELECT al.*, u.full_name, u.email
              FROM activity_logs al
              LEFT JOIN users u ON al.user_id = u.id
              WHERE al.id = $log_id";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $log = $result->fetch_assoc();
        $log['created_at'] = date('d/m/Y H:i:s', strtotime($log['created_at']));
        echo json_encode(['success' => true, 'log' => $log]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Log not found']);
    }
    exit();
}

// Handle delete log
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    $conn->query("DELETE FROM activity_logs WHERE id = $delete_id");
    header('Location: activity-logs.php?msg=deleted');
    exit();
}

// Handle clear old logs
if (isset($_GET['clear_old'])) {
    $days = intval($_GET['days']);
    $conn->query("DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL $days DAY)");
    header('Location: activity-logs.php?msg=cleared');
    exit();
}

// Filters
$filter_action = isset($_GET['action']) ? $_GET['action'] : '';
$filter_table = isset($_GET['table']) ? $_GET['table'] : '';
$filter_user = isset($_GET['user']) ? intval($_GET['user']) : 0;
$filter_date = isset($_GET['date']) ? $_GET['date'] : '';

// Build query
$where = [];
if ($filter_action) $where[] = "action = '$filter_action'";
if ($filter_table) $where[] = "table_name = '$filter_table'";
if ($filter_user > 0) $where[] = "user_id = $filter_user";
if ($filter_date) $where[] = "DATE(created_at) = '$filter_date'";

$where_sql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Get total count
$total_query = "SELECT COUNT(*) as total FROM activity_logs $where_sql";
$total_result = $conn->query($total_query);
$total_logs = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_logs / $per_page);

// Get activity logs
$logs_query = "SELECT al.*, u.full_name, u.email
               FROM activity_logs al
               LEFT JOIN users u ON al.user_id = u.id
               $where_sql
               ORDER BY al.created_at DESC
               LIMIT $per_page OFFSET $offset";
$logs = $conn->query($logs_query);

// Get statistics
$stats = [];
$stats['total'] = $conn->query("SELECT COUNT(*) as total FROM activity_logs")->fetch_assoc()['total'];
$stats['today'] = $conn->query("SELECT COUNT(*) as total FROM activity_logs WHERE DATE(created_at) = CURDATE()")->fetch_assoc()['total'];
$stats['this_week'] = $conn->query("SELECT COUNT(*) as total FROM activity_logs WHERE YEARWEEK(created_at) = YEARWEEK(CURDATE())")->fetch_assoc()['total'];
$stats['this_month'] = $conn->query("SELECT COUNT(*) as total FROM activity_logs WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")->fetch_assoc()['total'];

// Get unique actions
$actions = $conn->query("SELECT DISTINCT action FROM activity_logs WHERE action IS NOT NULL ORDER BY action");

// Get unique tables
$tables = $conn->query("SELECT DISTINCT table_name FROM activity_logs WHERE table_name IS NOT NULL ORDER BY table_name");

// Get users for filter
$users_list = $conn->query("SELECT id, full_name FROM users ORDER BY full_name");

// Action icons and colors
$action_config = [
    'CREATE' => ['icon' => '➕', 'color' => '#10B981', 'label' => 'Tạo mới'],
    'UPDATE' => ['icon' => '✏️', 'color' => '#6366F1', 'label' => 'Cập nhật'],
    'DELETE' => ['icon' => '🗑️', 'color' => '#EF4444', 'label' => 'Xóa'],
    'LOGIN' => ['icon' => '🔐', 'color' => '#10B981', 'label' => 'Đăng nhập'],
    'LOGOUT' => ['icon' => '🚪', 'color' => '#6B7280', 'label' => 'Đăng xuất'],
    'VIEW' => ['icon' => '👁️', 'color' => '#6366F1', 'label' => 'Xem'],
    'EXPORT' => ['icon' => '📤', 'color' => '#F59E0B', 'label' => 'Xuất dữ liệu'],
    'IMPORT' => ['icon' => '📥', 'color' => '#8B5CF6', 'label' => 'Nhập dữ liệu'],
];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nhật ký Hệ thống - Admin</title>
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <?php include 'includes/header.php'; ?>

        <div class="content">
            <div class="page-header">
                <h1>📋 Nhật ký Hệ thống</h1>
                <p>Theo dõi tất cả hoạt động trong hệ thống</p>
            </div>

            <?php if (isset($_GET['msg'])): ?>
            <div style="padding: 12px 20px; background: #D1FAE5; color: #065F46; border-radius: 8px; margin-bottom: 20px; border: 1px solid #6EE7B7;">
                ✓ <?php 
                    if ($_GET['msg'] == 'deleted') echo 'Đã xóa log thành công!';
                    elseif ($_GET['msg'] == 'cleared') echo 'Đã xóa logs cũ thành công!';
                    else echo 'Thao tác thành công!';
                ?>
            </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(99, 102, 241, 0.1); color: #6366F1;">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Tổng logs</div>
                        <div class="stat-value"><?= number_format($stats['total']) ?></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(16, 185, 129, 0.1); color: #10B981;">
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
                        <div class="stat-label">Tuần này</div>
                        <div class="stat-value"><?= number_format($stats['this_week']) ?></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background: rgba(139, 92, 246, 0.1); color: #8B5CF6;">
                        <svg width="24" height="24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-label">Tháng này</div>
                        <div class="stat-value"><?= number_format($stats['this_month']) ?></div>
                    </div>
                </div>
            </div>

            <!-- Actions Bar -->
            <div style="display: flex; gap: 12px; margin-bottom: 24px; flex-wrap: wrap;">
                <button onclick="if(confirm('Xóa logs cũ hơn 30 ngày?')) window.location.href='activity-logs.php?clear_old=1&days=30'" style="padding: 10px 20px; background: white; border: 1px solid #E5E7EB; border-radius: 8px; font-weight: 600; cursor: pointer;">
                    🗑️ Xóa logs > 30 ngày
                </button>
                <button onclick="if(confirm('Xóa logs cũ hơn 90 ngày?')) window.location.href='activity-logs.php?clear_old=1&days=90'" style="padding: 10px 20px; background: white; border: 1px solid #E5E7EB; border-radius: 8px; font-weight: 600; cursor: pointer;">
                    🗑️ Xóa logs > 90 ngày
                </button>
                <button onclick="window.print()" style="padding: 10px 20px; background: white; border: 1px solid #E5E7EB; border-radius: 8px; font-weight: 600; cursor: pointer;">
                    🖨️ In báo cáo
                </button>
            </div>

            <!-- Filters -->
            <div class="card" style="margin-bottom: 24px;">
                <div class="card-header">
                    <h2>Bộ lọc</h2>
                    <button onclick="window.location.href='activity-logs.php'" style="padding: 6px 12px; border-radius: 6px; border: 1px solid #E5E7EB; background: white; cursor: pointer; font-size: 13px;">
                        🔄 Reset
                    </button>
                </div>
                <div style="padding: 20px;">
                    <form method="GET" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; align-items: end;">
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 600; color: #6B7280; margin-bottom: 6px;">Hành động</label>
                            <select name="action" style="width: 100%; padding: 8px 12px; border: 1px solid #E5E7EB; border-radius: 8px; font-size: 14px;">
                                <option value="">Tất cả</option>
                                <?php while ($act = $actions->fetch_assoc()): ?>
                                <option value="<?= $act['action'] ?>" <?= $filter_action == $act['action'] ? 'selected' : '' ?>>
                                    <?= $act['action'] ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 600; color: #6B7280; margin-bottom: 6px;">Bảng</label>
                            <select name="table" style="width: 100%; padding: 8px 12px; border: 1px solid #E5E7EB; border-radius: 8px; font-size: 14px;">
                                <option value="">Tất cả</option>
                                <?php while ($tbl = $tables->fetch_assoc()): ?>
                                <option value="<?= $tbl['table_name'] ?>" <?= $filter_table == $tbl['table_name'] ? 'selected' : '' ?>>
                                    <?= $tbl['table_name'] ?>
                                </option>
                                <?php endwhile; ?>
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
                            <label style="display: block; font-size: 12px; font-weight: 600; color: #6B7280; margin-bottom: 6px;">Ngày</label>
                            <input type="date" name="date" value="<?= $filter_date ?>" style="width: 100%; padding: 8px 12px; border: 1px solid #E5E7EB; border-radius: 8px; font-size: 14px;">
                        </div>
                        <div>
                            <button type="submit" style="width: 100%; padding: 10px; background: #6366F1; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                                🔍 Lọc
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Activity Logs Table -->
            <div class="card">
                <div class="card-header">
                    <h2>Nhật ký hoạt động (<?= number_format($total_logs) ?>)</h2>
                    <div style="font-size: 13px; color: #6B7280;">
                        Trang <?= $page ?> / <?= $total_pages ?>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Thời gian</th>
                                <th>Người dùng</th>
                                <th>Hành động</th>
                                <th>Bảng</th>
                                <th>Record ID</th>
                                <th>IP Address</th>
                                <th>Chi tiết</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($logs->num_rows > 0): ?>
                                <?php while ($log = $logs->fetch_assoc()): 
                                    $action_info = $action_config[$log['action']] ?? ['icon' => '📝', 'color' => '#6B7280', 'label' => $log['action']];
                                ?>
                                <tr>
                                    <td><?= $log['id'] ?></td>
                                    <td>
                                        <div style="font-weight: 600; font-size: 13px;">
                                            <?= date('d/m/Y', strtotime($log['created_at'])) ?>
                                        </div>
                                        <div style="font-size: 12px; color: #6B7280;">
                                            <?= date('H:i:s', strtotime($log['created_at'])) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($log['full_name']): ?>
                                        <div style="font-weight: 600; font-size: 13px;">
                                            <?= htmlspecialchars($log['full_name']) ?>
                                        </div>
                                        <div style="font-size: 12px; color: #6B7280;">
                                            <?= htmlspecialchars($log['email']) ?>
                                        </div>
                                        <?php else: ?>
                                        <span style="color: #9CA3AF;">System</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; background: <?= $action_info['color'] ?>15; color: <?= $action_info['color'] ?>; border-radius: 6px; font-size: 12px; font-weight: 600;">
                                            <?= $action_info['icon'] ?> <?= $action_info['label'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <code style="padding: 2px 6px; background: #F3F4F6; border-radius: 4px; font-size: 12px;">
                                            <?= htmlspecialchars($log['table_name']) ?>
                                        </code>
                                    </td>
                                    <td><?= $log['record_id'] ?></td>
                                    <td>
                                        <code style="font-size: 12px; color: #6B7280;">
                                            <?= htmlspecialchars($log['ip_address']) ?>
                                        </code>
                                    </td>
                                    <td>
                                        <?php if ($log['old_values'] || $log['new_values']): ?>
                                        <button onclick="showDetails(<?= $log['id'] ?>)" style="padding: 4px 10px; border-radius: 6px; border: 1px solid #E5E7EB; background: white; cursor: pointer; font-size: 12px;">
                                            👁️ Xem
                                        </button>
                                        <?php else: ?>
                                        <span style="color: #9CA3AF; font-size: 12px;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button onclick="if(confirm('Xóa log này?')) window.location.href='activity-logs.php?delete=<?= $log['id'] ?>'" style="padding: 4px 10px; border-radius: 6px; border: 1px solid #FEE2E2; background: white; color: #EF4444; cursor: pointer; font-size: 12px;">
                                            🗑️
                                        </button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 60px 20px; color: #6B7280;">
                                        <div style="font-size: 48px; margin-bottom: 16px;">📋</div>
                                        <div style="font-size: 18px; font-weight: 600; margin-bottom: 8px;">Không có logs</div>
                                        <div style="font-size: 14px;">Chưa có hoạt động nào được ghi nhận</div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div style="padding: 20px; border-top: 1px solid #E5E7EB; display: flex; align-items: center; justify-content: space-between;">
                    <div style="font-size: 14px; color: #6B7280;">
                        Hiển thị <?= min($offset + 1, $total_logs) ?> - <?= min($offset + $per_page, $total_logs) ?> trong tổng số <?= number_format($total_logs) ?> logs
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?><?= $filter_action ? '&action=' . $filter_action : '' ?><?= $filter_table ? '&table=' . $filter_table : '' ?><?= $filter_user ? '&user=' . $filter_user : '' ?><?= $filter_date ? '&date=' . $filter_date : '' ?>" style="padding: 8px 16px; border: 1px solid #E5E7EB; border-radius: 6px; text-decoration: none; color: #374151; font-weight: 600;">
                            ← Trước
                        </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <a href="?page=<?= $i ?><?= $filter_action ? '&action=' . $filter_action : '' ?><?= $filter_table ? '&table=' . $filter_table : '' ?><?= $filter_user ? '&user=' . $filter_user : '' ?><?= $filter_date ? '&date=' . $filter_date : '' ?>" style="padding: 8px 12px; border: 1px solid #E5E7EB; border-radius: 6px; text-decoration: none; color: #374151; font-weight: 600; <?= $i == $page ? 'background: #6366F1; color: white; border-color: #6366F1;' : '' ?>">
                            <?= $i ?>
                        </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?><?= $filter_action ? '&action=' . $filter_action : '' ?><?= $filter_table ? '&table=' . $filter_table : '' ?><?= $filter_user ? '&user=' . $filter_user : '' ?><?= $filter_date ? '&date=' . $filter_date : '' ?>" style="padding: 8px 16px; border: 1px solid #E5E7EB; border-radius: 6px; text-decoration: none; color: #374151; font-weight: 600;">
                            Sau →
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal Chi tiết Log -->
    <div id="logDetailModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
        <div style="background: white; border-radius: 12px; width: 90%; max-width: 800px; max-height: 90vh; overflow: hidden; display: flex; flex-direction: column;">
            <div style="padding: 20px; border-bottom: 1px solid #E5E7EB; display: flex; justify-content: space-between; align-items: center;">
                <h2 style="margin: 0; font-size: 20px;">📋 Chi tiết Log</h2>
                <button onclick="closeModal()" style="padding: 8px 12px; border: none; background: #F3F4F6; border-radius: 6px; cursor: pointer; font-size: 18px;">✕</button>
            </div>
            <div id="modalContent" style="padding: 20px; overflow-y: auto; flex: 1;">
                <div style="text-align: center; padding: 40px; color: #6B7280;">
                    <div style="font-size: 48px; margin-bottom: 16px;">⏳</div>
                    <div>Đang tải...</div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/admin.js"></script>
    <script>
        function showDetails(logId) {
            const modal = document.getElementById('logDetailModal');
            const content = document.getElementById('modalContent');
            
            modal.style.display = 'flex';
            content.innerHTML = '<div style="text-align: center; padding: 40px; color: #6B7280;"><div style="font-size: 48px; margin-bottom: 16px;">⏳</div><div>Đang tải...</div></div>';
            
            // Fetch log details
            fetch('activity-logs.php?ajax=1&log_id=' + logId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        content.innerHTML = formatLogDetail(data.log);
                    } else {
                        content.innerHTML = '<div style="text-align: center; padding: 40px; color: #EF4444;"><div style="font-size: 48px; margin-bottom: 16px;">❌</div><div>Không tìm thấy log</div></div>';
                    }
                })
                .catch(error => {
                    content.innerHTML = '<div style="text-align: center; padding: 40px; color: #EF4444;"><div style="font-size: 48px; margin-bottom: 16px;">❌</div><div>Lỗi tải dữ liệu</div></div>';
                });
        }

        function closeModal() {
            document.getElementById('logDetailModal').style.display = 'none';
        }

        function formatLogDetail(log) {
            const actionConfig = {
                'CREATE': {icon: '➕', color: '#10B981', label: 'Tạo mới'},
                'UPDATE': {icon: '✏️', color: '#6366F1', label: 'Cập nhật'},
                'DELETE': {icon: '🗑️', color: '#EF4444', label: 'Xóa'},
                'LOGIN': {icon: '🔐', color: '#10B981', label: 'Đăng nhập'},
                'LOGOUT': {icon: '🚪', color: '#6B7280', label: 'Đăng xuất'},
                'VIEW': {icon: '👁️', color: '#6366F1', label: 'Xem'},
                'EXPORT': {icon: '📤', color: '#F59E0B', label: 'Xuất dữ liệu'},
                'IMPORT': {icon: '📥', color: '#8B5CF6', label: 'Nhập dữ liệu'}
            };
            
            const action = actionConfig[log.action] || {icon: '📝', color: '#6B7280', label: log.action};
            
            let html = `
                <div style="display: grid; gap: 20px;">
                    <!-- Thông tin cơ bản -->
                    <div style="background: #F9FAFB; padding: 16px; border-radius: 8px;">
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;">
                            <div>
                                <div style="font-size: 12px; color: #6B7280; margin-bottom: 4px;">Log ID</div>
                                <div style="font-weight: 600;">#${log.id}</div>
                            </div>
                            <div>
                                <div style="font-size: 12px; color: #6B7280; margin-bottom: 4px;">Thời gian</div>
                                <div style="font-weight: 600;">${log.created_at}</div>
                            </div>
                            <div>
                                <div style="font-size: 12px; color: #6B7280; margin-bottom: 4px;">Người dùng</div>
                                <div style="font-weight: 600;">${log.full_name || 'System'}</div>
                                ${log.email ? '<div style="font-size: 12px; color: #6B7280;">' + log.email + '</div>' : ''}
                            </div>
                            <div>
                                <div style="font-size: 12px; color: #6B7280; margin-bottom: 4px;">Hành động</div>
                                <span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 10px; background: ${action.color}15; color: ${action.color}; border-radius: 6px; font-size: 12px; font-weight: 600;">
                                    ${action.icon} ${action.label}
                                </span>
                            </div>
                            <div>
                                <div style="font-size: 12px; color: #6B7280; margin-bottom: 4px;">Bảng</div>
                                <code style="padding: 2px 6px; background: #F3F4F6; border-radius: 4px; font-size: 12px;">${log.table_name || '-'}</code>
                            </div>
                            <div>
                                <div style="font-size: 12px; color: #6B7280; margin-bottom: 4px;">Record ID</div>
                                <div style="font-weight: 600;">${log.record_id || '-'}</div>
                            </div>
                            <div>
                                <div style="font-size: 12px; color: #6B7280; margin-bottom: 4px;">IP Address</div>
                                <code style="font-size: 12px; color: #6B7280;">${log.ip_address || '-'}</code>
                            </div>
                            <div>
                                <div style="font-size: 12px; color: #6B7280; margin-bottom: 4px;">User Agent</div>
                                <div style="font-size: 12px; color: #6B7280; word-break: break-all;">${log.user_agent || '-'}</div>
                            </div>
                        </div>
                    </div>
            `;
            
            // Old Values
            if (log.old_values) {
                html += `
                    <div>
                        <div style="font-weight: 600; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                            <span style="color: #EF4444;">🔴</span> Giá trị cũ
                        </div>
                        <div style="background: #FEF2F2; border: 1px solid #FEE2E2; border-radius: 8px; padding: 16px;">
                            ${formatJSON(log.old_values)}
                        </div>
                    </div>
                `;
            }
            
            // New Values
            if (log.new_values) {
                html += `
                    <div>
                        <div style="font-weight: 600; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                            <span style="color: #10B981;">🟢</span> Giá trị mới
                        </div>
                        <div style="background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 8px; padding: 16px;">
                            ${formatJSON(log.new_values)}
                        </div>
                    </div>
                `;
            }
            
            // Changes comparison (if both exist)
            if (log.old_values && log.new_values) {
                try {
                    const oldData = JSON.parse(log.old_values);
                    const newData = JSON.parse(log.new_values);
                    const changes = compareObjects(oldData, newData);
                    
                    if (changes.length > 0) {
                        html += `
                            <div>
                                <div style="font-weight: 600; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                                    <span>🔄</span> Thay đổi (${changes.length})
                                </div>
                                <div style="background: #FFFBEB; border: 1px solid #FEF3C7; border-radius: 8px; padding: 16px;">
                                    ${changes.map(change => `
                                        <div style="padding: 8px 0; border-bottom: 1px solid #FEF3C7; last-child:border-bottom: none;">
                                            <div style="font-weight: 600; font-size: 13px; margin-bottom: 4px;">${change.field}</div>
                                            <div style="display: flex; gap: 12px; font-size: 12px;">
                                                <div style="flex: 1;">
                                                    <span style="color: #EF4444;">❌</span> 
                                                    <code style="background: #FEE2E2; padding: 2px 6px; border-radius: 4px;">${change.old}</code>
                                                </div>
                                                <div style="flex: 1;">
                                                    <span style="color: #10B981;">✓</span> 
                                                    <code style="background: #D1FAE5; padding: 2px 6px; border-radius: 4px;">${change.new}</code>
                                                </div>
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        `;
                    }
                } catch (e) {}
            }
            
            html += '</div>';
            return html;
        }

        function formatJSON(jsonString) {
            if (!jsonString) return '<div style="color: #9CA3AF; font-style: italic;">Không có dữ liệu</div>';
            
            try {
                const obj = JSON.parse(jsonString);
                let html = '<table style="width: 100%; font-size: 13px;">';
                
                for (const [key, value] of Object.entries(obj)) {
                    html += `
                        <tr style="border-bottom: 1px solid #E5E7EB;">
                            <td style="padding: 8px 12px; font-weight: 600; width: 30%; vertical-align: top;">${key}</td>
                            <td style="padding: 8px 12px; word-break: break-all;">
                                <code style="background: white; padding: 4px 8px; border-radius: 4px; display: inline-block;">${formatValue(value)}</code>
                            </td>
                        </tr>
                    `;
                }
                
                html += '</table>';
                return html;
            } catch (e) {
                return `<pre style="margin: 0; white-space: pre-wrap; word-break: break-all; font-size: 12px;">${jsonString}</pre>`;
            }
        }

        function formatValue(value) {
            if (value === null) return '<span style="color: #9CA3AF;">null</span>';
            if (value === '') return '<span style="color: #9CA3AF;">(empty)</span>';
            if (typeof value === 'object') return JSON.stringify(value);
            return String(value);
        }

        function compareObjects(oldObj, newObj) {
            const changes = [];
            const allKeys = new Set([...Object.keys(oldObj), ...Object.keys(newObj)]);
            
            allKeys.forEach(key => {
                const oldVal = oldObj[key];
                const newVal = newObj[key];
                
                if (JSON.stringify(oldVal) !== JSON.stringify(newVal)) {
                    changes.push({
                        field: key,
                        old: formatValue(oldVal),
                        new: formatValue(newVal)
                    });
                }
            });
            
            return changes;
        }

        // Close modal when clicking outside
        document.getElementById('logDetailModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Close modal with ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>
