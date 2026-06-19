<?php
require_once '../connect.php';

// Get user info
$user = [
    'id' => 1,
    'full_name' => 'Administrator',
    'email' => 'admin@fintrack.vn',
    'role' => 'admin'
];

// Handle form submissions
$success_msg = '';
$error_msg = '';

// 1. Update General Settings
if (isset($_POST['update_general'])) {
    $site_name = $conn->real_escape_string($_POST['site_name']);
    $site_description = $conn->real_escape_string($_POST['site_description']);
    $admin_email = $conn->real_escape_string($_POST['admin_email']);
    $timezone = $conn->real_escape_string($_POST['timezone']);
    $language = $conn->real_escape_string($_POST['language']);
    
    // In real app, save to settings table
    $success_msg = 'Đã cập nhật cài đặt chung thành công!';
}

// 2. Update Currency Settings
if (isset($_POST['update_currency'])) {
    $default_currency = $conn->real_escape_string($_POST['default_currency']);
    $currency_position = $conn->real_escape_string($_POST['currency_position']);
    $decimal_separator = $conn->real_escape_string($_POST['decimal_separator']);
    $thousand_separator = $conn->real_escape_string($_POST['thousand_separator']);
    
    $success_msg = 'Đã cập nhật cài đặt tiền tệ thành công!';
}

// 3. Update Email Settings
if (isset($_POST['update_email'])) {
    $smtp_host = $conn->real_escape_string($_POST['smtp_host']);
    $smtp_port = intval($_POST['smtp_port']);
    $smtp_username = $conn->real_escape_string($_POST['smtp_username']);
    $smtp_password = $conn->real_escape_string($_POST['smtp_password']);
    $smtp_encryption = $conn->real_escape_string($_POST['smtp_encryption']);
    
    $success_msg = 'Đã cập nhật cài đặt email thành công!';
}

// 4. Update Security Settings
if (isset($_POST['update_security'])) {
    $session_timeout = intval($_POST['session_timeout']);
    $max_login_attempts = intval($_POST['max_login_attempts']);
    $password_min_length = intval($_POST['password_min_length']);
    $require_strong_password = isset($_POST['require_strong_password']) ? 1 : 0;
    $enable_2fa = isset($_POST['enable_2fa']) ? 1 : 0;
    
    $success_msg = 'Đã cập nhật cài đặt bảo mật thành công!';
}

// 5. Update Notification Settings
if (isset($_POST['update_notification'])) {
    $enable_email_notifications = isset($_POST['enable_email_notifications']) ? 1 : 0;
    $enable_push_notifications = isset($_POST['enable_push_notifications']) ? 1 : 0;
    $budget_alert_threshold = intval($_POST['budget_alert_threshold']);
    $bill_reminder_days = intval($_POST['bill_reminder_days']);
    
    $success_msg = 'Đã cập nhật cài đặt thông báo thành công!';
}

// 6. Clear Cache
if (isset($_POST['clear_cache'])) {
    // Clear cache logic here
    $success_msg = 'Đã xóa cache thành công!';
}

// 7. Backup Database
if (isset($_POST['backup_database'])) {
    $backup_file = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
    // Backup logic here
    $success_msg = 'Đã sao lưu database thành công! File: ' . $backup_file;
}

// 8. Test Email
if (isset($_POST['test_email'])) {
    $test_email = $conn->real_escape_string($_POST['test_email_address']);
    // Send test email logic here
    $success_msg = 'Đã gửi email test đến: ' . $test_email;
}

// Get current settings (mock data - in real app, get from settings table)
$settings = [
    'site_name' => 'Finora - Quản lý Chi tiêu',
    'site_description' => 'Hệ thống quản lý tài chính cá nhân thông minh',
    'admin_email' => 'admin@finora.vn',
    'timezone' => 'Asia/Ho_Chi_Minh',
    'language' => 'vi',
    'default_currency' => 'VND',
    'currency_position' => 'after',
    'decimal_separator' => ',',
    'thousand_separator' => '.',
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_username' => 'noreply@finora.vn',
    'smtp_encryption' => 'tls',
    'session_timeout' => 30,
    'max_login_attempts' => 5,
    'password_min_length' => 8,
    'require_strong_password' => 1,
    'enable_2fa' => 0,
    'enable_email_notifications' => 1,
    'enable_push_notifications' => 0,
    'budget_alert_threshold' => 80,
    'bill_reminder_days' => 3
];

// Get system info
$system_info = [
    'php_version' => phpversion(),
    'mysql_version' => $conn->server_info,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'max_upload_size' => ini_get('upload_max_filesize'),
    'max_post_size' => ini_get('post_max_size'),
    'memory_limit' => ini_get('memory_limit'),
    'timezone' => date_default_timezone_get()
];

// Get database stats
$db_stats = [];
$db_stats['total_users'] = $conn->query("SELECT COUNT(*) as total FROM users")->fetch_assoc()['total'];
$db_stats['total_transactions'] = $conn->query("SELECT COUNT(*) as total FROM transactions")->fetch_assoc()['total'];
$db_stats['total_categories'] = $conn->query("SELECT COUNT(*) as total FROM categories")->fetch_assoc()['total'];
$db_stats['total_wallets'] = $conn->query("SELECT COUNT(*) as total FROM wallets")->fetch_assoc()['total'];
$db_stats['total_logs'] = $conn->query("SELECT COUNT(*) as total FROM activity_logs")->fetch_assoc()['total'];

// Get database size
$db_size_query = "SELECT 
    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
    FROM information_schema.TABLES 
    WHERE table_schema = 'quanly_chitieu'";
$db_stats['database_size'] = $conn->query($db_size_query)->fetch_assoc()['size_mb'] . ' MB';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cài đặt Hệ thống - Admin</title>
    <link rel="stylesheet" href="assets/css/admin.css">
    <style>
        .settings-tabs {
            display: flex;
            gap: 8px;
            border-bottom: 2px solid #E5E7EB;
            margin-bottom: 24px;
            overflow-x: auto;
        }
        .settings-tab {
            padding: 12px 20px;
            border: none;
            background: transparent;
            cursor: pointer;
            font-weight: 600;
            color: #6B7280;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            white-space: nowrap;
            transition: all 0.2s;
        }
        .settings-tab:hover {
            color: #374151;
            background: #F9FAFB;
        }
        .settings-tab.active {
            color: #6366F1;
            border-bottom-color: #6366F1;
        }
        .settings-content {
            display: none;
        }
        .settings-content.active {
            display: block;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
        }
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="number"],
        .form-group input[type="password"],
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #E5E7EB;
            border-radius: 8px;
            font-size: 14px;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        .form-group small {
            display: block;
            font-size: 12px;
            color: #6B7280;
            margin-top: 4px;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
        }
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        .checkbox-group label {
            margin: 0;
            cursor: pointer;
            font-weight: 500;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }
        .info-item {
            padding: 16px;
            background: #F9FAFB;
            border-radius: 8px;
            border: 1px solid #E5E7EB;
        }
        .info-item-label {
            font-size: 12px;
            color: #6B7280;
            margin-bottom: 4px;
        }
        .info-item-value {
            font-size: 16px;
            font-weight: 600;
            color: #111827;
        }
        .danger-zone {
            border: 2px solid #FEE2E2;
            border-radius: 8px;
            padding: 20px;
            background: #FEF2F2;
            margin-top: 24px;
        }
        .danger-zone h3 {
            color: #DC2626;
            margin-bottom: 12px;
        }
    </style>
</head>
<body>

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <?php include 'includes/header.php'; ?>

        <div class="content">
            <div class="page-header">
                <h1>⚙️ Cài đặt Hệ thống</h1>
                <p>Quản lý cấu hình và tùy chỉnh hệ thống</p>
            </div>

            <?php if ($success_msg): ?>
            <div style="padding: 12px 20px; background: #D1FAE5; color: #065F46; border-radius: 8px; margin-bottom: 20px; border: 1px solid #6EE7B7;">
                ✓ <?= $success_msg ?>
            </div>
            <?php endif; ?>

            <?php if ($error_msg): ?>
            <div style="padding: 12px 20px; background: #FEE2E2; color: #991B1B; border-radius: 8px; margin-bottom: 20px; border: 1px solid #FCA5A5;">
                ✕ <?= $error_msg ?>
            </div>
            <?php endif; ?>

            <!-- Settings Tabs -->
            <div class="settings-tabs">
                <button class="settings-tab active" onclick="switchTab('general')">
                    🏠 Chung
                </button>
                <button class="settings-tab" onclick="switchTab('currency')">
                    💰 Tiền tệ
                </button>
                <button class="settings-tab" onclick="switchTab('email')">
                    📧 Email
                </button>
                <button class="settings-tab" onclick="switchTab('security')">
                    🔒 Bảo mật
                </button>
                <button class="settings-tab" onclick="switchTab('notification')">
                    🔔 Thông báo
                </button>
                <button class="settings-tab" onclick="switchTab('system')">
                    💻 Hệ thống
                </button>
                <button class="settings-tab" onclick="switchTab('database')">
                    🗄️ Database
                </button>
            </div>

            <!-- Tab 1: General Settings -->
            <div id="tab-general" class="settings-content active">
                <div class="card">
                    <div class="card-header">
                        <h2>Cài đặt Chung</h2>
                    </div>
                    <div style="padding: 24px;">
                        <form method="POST">
                            <div class="form-group">
                                <label>Tên Website</label>
                                <input type="text" name="site_name" value="<?= htmlspecialchars($settings['site_name']) ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Mô tả Website</label>
                                <textarea name="site_description"><?= htmlspecialchars($settings['site_description']) ?></textarea>
                            </div>

                            <div class="form-group">
                                <label>Email Admin</label>
                                <input type="email" name="admin_email" value="<?= htmlspecialchars($settings['admin_email']) ?>" required>
                                <small>Email nhận thông báo hệ thống</small>
                            </div>

                            <div class="form-group">
                                <label>Múi giờ</label>
                                <select name="timezone">
                                    <option value="Asia/Ho_Chi_Minh" <?= $settings['timezone'] == 'Asia/Ho_Chi_Minh' ? 'selected' : '' ?>>Việt Nam (GMT+7)</option>
                                    <option value="Asia/Bangkok" <?= $settings['timezone'] == 'Asia/Bangkok' ? 'selected' : '' ?>>Bangkok (GMT+7)</option>
                                    <option value="Asia/Singapore" <?= $settings['timezone'] == 'Asia/Singapore' ? 'selected' : '' ?>>Singapore (GMT+8)</option>
                                    <option value="Asia/Tokyo" <?= $settings['timezone'] == 'Asia/Tokyo' ? 'selected' : '' ?>>Tokyo (GMT+9)</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Ngôn ngữ</label>
                                <select name="language">
                                    <option value="vi" <?= $settings['language'] == 'vi' ? 'selected' : '' ?>>Tiếng Việt</option>
                                    <option value="en" <?= $settings['language'] == 'en' ? 'selected' : '' ?>>English</option>
                                </select>
                            </div>

                            <button type="submit" name="update_general" class="btn btn-primary">
                                💾 Lưu thay đổi
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Tab 2: Currency Settings -->
            <div id="tab-currency" class="settings-content">
                <div class="card">
                    <div class="card-header">
                        <h2>Cài đặt Tiền tệ</h2>
                    </div>
                    <div style="padding: 24px;">
                        <form method="POST">
                            <div class="form-group">
                                <label>Đơn vị tiền tệ mặc định</label>
                                <select name="default_currency">
                                    <option value="VND" <?= $settings['default_currency'] == 'VND' ? 'selected' : '' ?>>VND - Việt Nam Đồng</option>
                                    <option value="USD" <?= $settings['default_currency'] == 'USD' ? 'selected' : '' ?>>USD - US Dollar</option>
                                    <option value="EUR" <?= $settings['default_currency'] == 'EUR' ? 'selected' : '' ?>>EUR - Euro</option>
                                    <option value="JPY" <?= $settings['default_currency'] == 'JPY' ? 'selected' : '' ?>>JPY - Japanese Yen</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Vị trí ký hiệu tiền tệ</label>
                                <select name="currency_position">
                                    <option value="before" <?= $settings['currency_position'] == 'before' ? 'selected' : '' ?>>Trước số tiền ($100)</option>
                                    <option value="after" <?= $settings['currency_position'] == 'after' ? 'selected' : '' ?>>Sau số tiền (100đ)</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Ký tự phân cách thập phân</label>
                                <select name="decimal_separator">
                                    <option value="," <?= $settings['decimal_separator'] == ',' ? 'selected' : '' ?>>, (dấu phẩy)</option>
                                    <option value="." <?= $settings['decimal_separator'] == '.' ? 'selected' : '' ?>>. (dấu chấm)</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label>Ký tự phân cách hàng nghìn</label>
                                <select name="thousand_separator">
                                    <option value="." <?= $settings['thousand_separator'] == '.' ? 'selected' : '' ?>>. (dấu chấm)</option>
                                    <option value="," <?= $settings['thousand_separator'] == ',' ? 'selected' : '' ?>>, (dấu phẩy)</option>
                                    <option value=" " <?= $settings['thousand_separator'] == ' ' ? 'selected' : '' ?>>(khoảng trắng)</option>
                                </select>
                            </div>

                            <div style="padding: 16px; background: #F0F9FF; border: 1px solid #BAE6FD; border-radius: 8px; margin-bottom: 20px;">
                                <strong>Ví dụ:</strong> 1.234.567,89 VND
                            </div>

                            <button type="submit" name="update_currency" class="btn btn-primary">
                                💾 Lưu thay đổi
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Tab 3: Email Settings -->
            <div id="tab-email" class="settings-content">
                <div class="card">
                    <div class="card-header">
                        <h2>Cài đặt Email (SMTP)</h2>
                    </div>
                    <div style="padding: 24px;">
                        <form method="POST">
                            <div class="form-group">
                                <label>SMTP Host</label>
                                <input type="text" name="smtp_host" value="<?= htmlspecialchars($settings['smtp_host']) ?>" required>
                                <small>Ví dụ: smtp.gmail.com, smtp.office365.com</small>
                            </div>

                            <div class="form-group">
                                <label>SMTP Port</label>
                                <input type="number" name="smtp_port" value="<?= $settings['smtp_port'] ?>" required>
                                <small>Thường là 587 (TLS) hoặc 465 (SSL)</small>
                            </div>

                            <div class="form-group">
                                <label>SMTP Username</label>
                                <input type="text" name="smtp_username" value="<?= htmlspecialchars($settings['smtp_username']) ?>" required>
                            </div>

                            <div class="form-group">
                                <label>SMTP Password</label>
                                <input type="password" name="smtp_password" placeholder="••••••••">
                                <small>Để trống nếu không muốn thay đổi</small>
                            </div>

                            <div class="form-group">
                                <label>Mã hóa</label>
                                <select name="smtp_encryption">
                                    <option value="tls" <?= $settings['smtp_encryption'] == 'tls' ? 'selected' : '' ?>>TLS</option>
                                    <option value="ssl" <?= $settings['smtp_encryption'] == 'ssl' ? 'selected' : '' ?>>SSL</option>
                                    <option value="none" <?= $settings['smtp_encryption'] == 'none' ? 'selected' : '' ?>>Không</option>
                                </select>
                            </div>

                            <div style="display: flex; gap: 12px;">
                                <button type="submit" name="update_email" class="btn btn-primary">
                                    💾 Lưu thay đổi
                                </button>
                                <button type="button" onclick="showTestEmailModal()" class="btn" style="background: white; border: 1px solid #E5E7EB;">
                                    📧 Gửi email test
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Tab 4: Security Settings -->
            <div id="tab-security" class="settings-content">
                <div class="card">
                    <div class="card-header">
                        <h2>Cài đặt Bảo mật</h2>
                    </div>
                    <div style="padding: 24px;">
                        <form method="POST">
                            <div class="form-group">
                                <label>Thời gian hết phiên (phút)</label>
                                <input type="number" name="session_timeout" value="<?= $settings['session_timeout'] ?>" min="5" max="1440" required>
                                <small>Tự động đăng xuất sau thời gian không hoạt động</small>
                            </div>

                            <div class="form-group">
                                <label>Số lần đăng nhập sai tối đa</label>
                                <input type="number" name="max_login_attempts" value="<?= $settings['max_login_attempts'] ?>" min="3" max="10" required>
                                <small>Khóa tài khoản sau số lần đăng nhập sai</small>
                            </div>

                            <div class="form-group">
                                <label>Độ dài mật khẩu tối thiểu</label>
                                <input type="number" name="password_min_length" value="<?= $settings['password_min_length'] ?>" min="6" max="20" required>
                            </div>

                            <div class="checkbox-group">
                                <input type="checkbox" name="require_strong_password" id="require_strong_password" <?= $settings['require_strong_password'] ? 'checked' : '' ?>>
                                <label for="require_strong_password">Yêu cầu mật khẩu mạnh (chữ hoa, chữ thường, số, ký tự đặc biệt)</label>
                            </div>

                            <div class="checkbox-group">
                                <input type="checkbox" name="enable_2fa" id="enable_2fa" <?= $settings['enable_2fa'] ? 'checked' : '' ?>>
                                <label for="enable_2fa">Bật xác thực 2 yếu tố (2FA)</label>
                            </div>

                            <button type="submit" name="update_security" class="btn btn-primary">
                                💾 Lưu thay đổi
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Tab 5: Notification Settings -->
            <div id="tab-notification" class="settings-content">
                <div class="card">
                    <div class="card-header">
                        <h2>Cài đặt Thông báo</h2>
                    </div>
                    <div style="padding: 24px;">
                        <form method="POST">
                            <div class="checkbox-group">
                                <input type="checkbox" name="enable_email_notifications" id="enable_email_notifications" <?= $settings['enable_email_notifications'] ? 'checked' : '' ?>>
                                <label for="enable_email_notifications">Bật thông báo qua Email</label>
                            </div>

                            <div class="checkbox-group">
                                <input type="checkbox" name="enable_push_notifications" id="enable_push_notifications" <?= $settings['enable_push_notifications'] ? 'checked' : '' ?>>
                                <label for="enable_push_notifications">Bật thông báo đẩy (Push Notification)</label>
                            </div>

                            <div class="form-group" style="margin-top: 24px;">
                                <label>Ngưỡng cảnh báo ngân sách (%)</label>
                                <input type="number" name="budget_alert_threshold" value="<?= $settings['budget_alert_threshold'] ?>" min="50" max="100" required>
                                <small>Gửi cảnh báo khi chi tiêu đạt % ngân sách</small>
                            </div>

                            <div class="form-group">
                                <label>Nhắc nhở hóa đơn trước (ngày)</label>
                                <input type="number" name="bill_reminder_days" value="<?= $settings['bill_reminder_days'] ?>" min="1" max="30" required>
                                <small>Gửi nhắc nhở trước ngày đến hạn</small>
                            </div>

                            <button type="submit" name="update_notification" class="btn btn-primary">
                                💾 Lưu thay đổi
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Tab 6: System Info -->
            <div id="tab-system" class="settings-content">
                <div class="card">
                    <div class="card-header">
                        <h2>Thông tin Hệ thống</h2>
                    </div>
                    <div style="padding: 24px;">
                        <h3 style="margin-bottom: 16px; font-size: 16px;">Server Information</h3>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-item-label">PHP Version</div>
                                <div class="info-item-value"><?= $system_info['php_version'] ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-item-label">MySQL Version</div>
                                <div class="info-item-value"><?= $system_info['mysql_version'] ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-item-label">Server Software</div>
                                <div class="info-item-value"><?= $system_info['server_software'] ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-item-label">Max Upload Size</div>
                                <div class="info-item-value"><?= $system_info['max_upload_size'] ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-item-label">Max POST Size</div>
                                <div class="info-item-value"><?= $system_info['max_post_size'] ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-item-label">Memory Limit</div>
                                <div class="info-item-value"><?= $system_info['memory_limit'] ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-item-label">Timezone</div>
                                <div class="info-item-value"><?= $system_info['timezone'] ?></div>
                            </div>
                        </div>

                        <h3 style="margin: 24px 0 16px; font-size: 16px;">PHP Extensions</h3>
                        <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                            <?php
                            $required_extensions = ['mysqli', 'pdo', 'json', 'mbstring', 'curl', 'gd', 'zip'];
                            foreach ($required_extensions as $ext):
                                $loaded = extension_loaded($ext);
                            ?>
                            <span style="padding: 6px 12px; border-radius: 6px; font-size: 13px; font-weight: 600; <?= $loaded ? 'background: #D1FAE5; color: #065F46;' : 'background: #FEE2E2; color: #991B1B;' ?>">
                                <?= $loaded ? '✓' : '✕' ?> <?= $ext ?>
                            </span>
                            <?php endforeach; ?>
                        </div>

                        <div style="margin-top: 24px; display: flex; gap: 12px;">
                            <form method="POST" style="display: inline;">
                                <button type="submit" name="clear_cache" class="btn" style="background: white; border: 1px solid #E5E7EB;">
                                    🗑️ Xóa Cache
                                </button>
                            </form>
                            <button onclick="window.location.reload()" class="btn" style="background: white; border: 1px solid #E5E7EB;">
                                🔄 Làm mới
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 7: Database -->
            <div id="tab-database" class="settings-content">
                <div class="card">
                    <div class="card-header">
                        <h2>Quản lý Database</h2>
                    </div>
                    <div style="padding: 24px;">
                        <h3 style="margin-bottom: 16px; font-size: 16px;">Thống kê Database</h3>
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-item-label">Tổng người dùng</div>
                                <div class="info-item-value"><?= number_format($db_stats['total_users']) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-item-label">Tổng giao dịch</div>
                                <div class="info-item-value"><?= number_format($db_stats['total_transactions']) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-item-label">Tổng danh mục</div>
                                <div class="info-item-value"><?= number_format($db_stats['total_categories']) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-item-label">Tổng ví tiền</div>
                                <div class="info-item-value"><?= number_format($db_stats['total_wallets']) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-item-label">Tổng logs</div>
                                <div class="info-item-value"><?= number_format($db_stats['total_logs']) ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-item-label">Kích thước Database</div>
                                <div class="info-item-value"><?= $db_stats['database_size'] ?></div>
                            </div>
                        </div>

                        <h3 style="margin: 24px 0 16px; font-size: 16px;">Sao lưu & Khôi phục</h3>
                        <div style="padding: 16px; background: #F0F9FF; border: 1px solid #BAE6FD; border-radius: 8px; margin-bottom: 16px;">
                            <strong>💡 Lưu ý:</strong> Nên sao lưu database định kỳ để tránh mất dữ liệu
                        </div>

                        <form method="POST" style="margin-bottom: 16px;">
                            <button type="submit" name="backup_database" class="btn btn-primary">
                                💾 Sao lưu Database ngay
                            </button>
                        </form>

                        <div style="padding: 16px; background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 8px;">
                            <strong>Lịch sử sao lưu gần đây:</strong>
                            <ul style="margin: 12px 0 0 20px; color: #6B7280;">
                                <li>backup_2026-05-31_14-30-00.sql (2.5 MB)</li>
                                <li>backup_2026-05-30_14-30-00.sql (2.4 MB)</li>
                                <li>backup_2026-05-29_14-30-00.sql (2.3 MB)</li>
                            </ul>
                        </div>

                        <!-- Danger Zone -->
                        <div class="danger-zone">
                            <h3>⚠️ Vùng Nguy hiểm</h3>
                            <p style="color: #6B7280; margin-bottom: 16px;">Các thao tác sau không thể hoàn tác. Hãy cẩn thận!</p>
                            
                            <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                                <button onclick="if(confirm('Xóa tất cả logs cũ hơn 90 ngày?')) alert('Đã xóa logs cũ!')" class="btn" style="background: white; border: 1px solid #FCA5A5; color: #DC2626;">
                                    🗑️ Xóa logs cũ
                                </button>
                                <button onclick="if(confirm('Tối ưu hóa database? Quá trình này có thể mất vài phút.')) alert('Đã tối ưu hóa database!')" class="btn" style="background: white; border: 1px solid #FCA5A5; color: #DC2626;">
                                    ⚡ Tối ưu hóa Database
                                </button>
                                <button onclick="if(confirm('CẢNH BÁO: Xóa tất cả dữ liệu test? Hành động này KHÔNG THỂ HOÀN TÁC!') && confirm('Bạn có CHẮC CHẮN muốn tiếp tục?')) alert('Đã xóa dữ liệu test!')" class="btn" style="background: #DC2626; color: white; border: none;">
                                    💣 Xóa dữ liệu test
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Test Email Modal -->
    <div id="testEmailModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
        <div style="background: white; border-radius: 12px; width: 90%; max-width: 500px; padding: 24px;">
            <h2 style="margin: 0 0 16px 0; font-size: 20px;">📧 Gửi Email Test</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Email nhận</label>
                    <input type="email" name="test_email_address" placeholder="example@domain.com" required>
                </div>
                <div style="display: flex; gap: 12px; justify-content: flex-end;">
                    <button type="button" onclick="closeTestEmailModal()" class="btn" style="background: white; border: 1px solid #E5E7EB;">
                        Hủy
                    </button>
                    <button type="submit" name="test_email" class="btn btn-primary">
                        Gửi
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/admin.js"></script>
    <script>
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.settings-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.settings-tab').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById('tab-' + tabName).classList.add('active');
            event.target.classList.add('active');
            
            // Save to localStorage
            localStorage.setItem('activeSettingsTab', tabName);
        }

        function showTestEmailModal() {
            document.getElementById('testEmailModal').style.display = 'flex';
        }

        function closeTestEmailModal() {
            document.getElementById('testEmailModal').style.display = 'none';
        }

        // Restore last active tab
        window.addEventListener('DOMContentLoaded', function() {
            const lastTab = localStorage.getItem('activeSettingsTab');
            if (lastTab) {
                const tabButton = document.querySelector(`[onclick="switchTab('${lastTab}')"]`);
                if (tabButton) {
                    tabButton.click();
                }
            }
        });

        // Close modal when clicking outside
        document.getElementById('testEmailModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeTestEmailModal();
            }
        });
    </script>
</body>
</html>
