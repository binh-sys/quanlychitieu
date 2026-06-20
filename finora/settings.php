<?php
session_start();
require_once __DIR__ . '/../connect.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit;
}

$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';

// Lấy thông tin user
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Xử lý cập nhật thông tin cá nhân
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fullName = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone'] ?? '');
    
    $stmt = $conn->prepare("UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?");
    $stmt->bind_param("sssi", $fullName, $email, $phone, $userId);
    
    if ($stmt->execute()) {
        $message = "Cập nhật thông tin thành công!";
        $messageType = "success";
        $_SESSION['user_name'] = $fullName;
        // Reload user data
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
    } else {
        $message = "Có lỗi xảy ra!";
        $messageType = "error";
    }
}

// Xử lý đổi mật khẩu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    if (password_verify($currentPassword, $user['password'])) {
        if ($newPassword === $confirmPassword) {
            if (strlen($newPassword) >= 6) {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hashedPassword, $userId);
                
                if ($stmt->execute()) {
                    $message = "Đổi mật khẩu thành công!";
                    $messageType = "success";
                } else {
                    $message = "Có lỗi xảy ra!";
                    $messageType = "error";
                }
            } else {
                $message = "Mật khẩu mới phải có ít nhất 6 ký tự!";
                $messageType = "error";
            }
        } else {
            $message = "Mật khẩu xác nhận không khớp!";
            $messageType = "error";
        }
    } else {
        $message = "Mật khẩu hiện tại không đúng!";
        $messageType = "error";
    }
}

// Xử lý cập nhật tùy chọn
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_preferences'])) {
    $currency = $_POST['currency'] ?? 'VND';
    $monthlyBudget = floatval($_POST['monthly_budget'] ?? 0);
    
    $stmt = $conn->prepare("UPDATE users SET currency = ?, monthly_budget = ? WHERE id = ?");
    $stmt->bind_param("sdi", $currency, $monthlyBudget, $userId);
    
    if ($stmt->execute()) {
        $message = "Cập nhật tùy chọn thành công!";
        $messageType = "success";
        // Reload user data
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
    } else {
        $message = "Có lỗi xảy ra!";
        $messageType = "error";
    }
}

// Thống kê tài khoản
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM wallets WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$walletCount = $stmt->get_result()->fetch_assoc()['total'];

$stmt = $conn->prepare("SELECT COUNT(*) as total FROM transactions WHERE user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$transactionCount = $stmt->get_result()->fetch_assoc()['total'];

$userName = $user['full_name'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cài đặt - Finora</title>
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

        /* Sidebar */
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
            max-width: 1200px;
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

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #dc2626;
        }

        /* Alert */
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: #D1FAE5;
            color: #065F46;
            border: 1px solid #6EE7B7;
        }

        .alert-error {
            background: #FEE2E2;
            color: #991B1B;
            border: 1px solid #FCA5A5;
        }

        /* Settings Layout */
        .settings-container {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 24px;
        }

        .settings-tabs {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 16px;
            height: fit-content;
            position: sticky;
            top: 32px;
        }

        .settings-tab {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-radius: 10px;
            color: var(--text-light);
            cursor: pointer;
            transition: all 0.2s;
            margin-bottom: 4px;
            font-size: 14px;
            font-weight: 500;
        }

        .settings-tab:hover {
            background: var(--bg);
            color: var(--primary);
        }

        .settings-tab.active {
            background: linear-gradient(135deg, #EFF6FF, #F0F9FF);
            color: var(--primary);
            font-weight: 600;
        }

        .settings-tab-icon {
            width: 20px;
            text-align: center;
        }

        .settings-content {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .settings-panel {
            display: none;
        }

        .settings-panel.active {
            display: block;
        }

        /* Section */
        .section {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border);
        }

        .section-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #EFF6FF, #F0F9FF);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: var(--primary);
        }

        .section-title {
            font-size: 18px;
            font-weight: 700;
        }

        .section-subtitle {
            font-size: 13px;
            color: var(--text-light);
            margin-top: 4px;
        }

        /* Form */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text);
        }

        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.2s;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            background: white;
            cursor: pointer;
        }

        .form-help {
            font-size: 12px;
            color: var(--text-lighter);
            margin-top: 6px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-box {
            background: var(--bg);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 13px;
            color: var(--text-light);
        }

        /* Profile Avatar */
        .profile-avatar-section {
            display: flex;
            align-items: center;
            gap: 24px;
            padding: 24px;
            background: var(--bg);
            border-radius: 12px;
            margin-bottom: 24px;
        }

        .profile-avatar-large {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, var(--primary), var(--purple));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            font-weight: 800;
            color: white;
        }

        .profile-info h3 {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .profile-info p {
            color: var(--text-light);
            font-size: 14px;
            margin-bottom: 12px;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            gap: 4px;
        }

        .badge-primary {
            background: #DBEAFE;
            color: #1E40AF;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .settings-container {
                grid-template-columns: 1fr;
            }

            .settings-tabs {
                position: relative;
                top: 0;
            }

            .stats-grid {
                grid-template-columns: 1fr;
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

            .profile-avatar-section {
                flex-direction: column;
                text-align: center;
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
                <a href="cards.php" class="menu-link">
                    <i class="menu-icon fas fa-credit-card"></i>
                    <span>Thẻ</span>
                </a>
            </li>
            <li class="menu-item">
                <a href="settings.php" class="menu-link active">
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
                <h1 class="page-title">⚙️ Cài đặt</h1>
                <p class="page-subtitle">Quản lý thông tin cá nhân và tùy chỉnh hệ thống</p>
            </div>
            <div>
                <a href="dashboard.php" class="btn btn-ghost">
                    <i class="fas fa-arrow-left"></i>
                    Quay lại
                </a>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>">
                <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                <span><?= htmlspecialchars($message) ?></span>
            </div>
        <?php endif; ?>

        <!-- Settings Layout -->
        <div class="settings-container">
            <!-- Settings Tabs -->
            <div class="settings-tabs">
                <div class="settings-tab active" data-tab="profile">
                    <i class="settings-tab-icon fas fa-user"></i>
                    <span>Thông tin cá nhân</span>
                </div>
                <div class="settings-tab" data-tab="security">
                    <i class="settings-tab-icon fas fa-lock"></i>
                    <span>Bảo mật</span>
                </div>
                <div class="settings-tab" data-tab="preferences">
                    <i class="settings-tab-icon fas fa-sliders-h"></i>
                    <span>Tùy chọn</span>
                </div>
                <div class="settings-tab" data-tab="account">
                    <i class="settings-tab-icon fas fa-chart-bar"></i>
                    <span>Thống kê tài khoản</span>
                </div>
            </div>

            <!-- Settings Content -->
            <div class="settings-content">
                <!-- Profile Panel -->
                <div class="settings-panel active" id="profile">
                    <div class="section">
                        <div class="section-header">
                            <div class="section-icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <div>
                                <h2 class="section-title">Thông tin cá nhân</h2>
                                <p class="section-subtitle">Cập nhật thông tin tài khoản của bạn</p>
                            </div>
                        </div>

                        <div class="profile-avatar-section">
                            <div class="profile-avatar-large">
                                <?php 
                                $names = explode(' ', $userName);
                                echo strtoupper(substr($names[0], 0, 1));
                                if (count($names) > 1) {
                                    echo strtoupper(substr($names[count($names) - 1], 0, 1));
                                }
                                ?>
                            </div>
                            <div class="profile-info">
                                <h3><?= htmlspecialchars($user['full_name']) ?></h3>
                                <p><?= htmlspecialchars($user['email']) ?></p>
                                <span class="badge badge-primary">
                                    <i class="fas fa-crown"></i>
                                    <?= $user['role'] === 'admin' ? 'Quản trị viên' : 'Người dùng' ?>
                                </span>
                            </div>
                        </div>

                        <form method="POST">
                            <div class="form-group">
                                <label class="form-label">Họ và tên</label>
                                <input type="text" name="full_name" class="form-input" 
                                       value="<?= htmlspecialchars($user['full_name']) ?>" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-input" 
                                       value="<?= htmlspecialchars($user['email']) ?>" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Số điện thoại</label>
                                <input type="tel" name="phone" class="form-input" 
                                       value="<?= htmlspecialchars($user['phone'] ?? '') ?>" 
                                       placeholder="0987654321">
                                <p class="form-help">Số điện thoại dùng để liên hệ và xác thực</p>
                            </div>

                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="fas fa-save"></i>
                                Lưu thay đổi
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Security Panel -->
                <div class="settings-panel" id="security">
                    <div class="section">
                        <div class="section-header">
                            <div class="section-icon">
                                <i class="fas fa-lock"></i>
                            </div>
                            <div>
                                <h2 class="section-title">Bảo mật tài khoản</h2>
                                <p class="section-subtitle">Thay đổi mật khẩu và cài đặt bảo mật</p>
                            </div>
                        </div>

                        <form method="POST">
                            <div class="form-group">
                                <label class="form-label">Mật khẩu hiện tại</label>
                                <input type="password" name="current_password" class="form-input" 
                                       placeholder="Nhập mật khẩu hiện tại" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Mật khẩu mới</label>
                                <input type="password" name="new_password" class="form-input" 
                                       placeholder="Nhập mật khẩu mới (tối thiểu 6 ký tự)" required>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Xác nhận mật khẩu mới</label>
                                <input type="password" name="confirm_password" class="form-input" 
                                       placeholder="Nhập lại mật khẩu mới" required>
                                <p class="form-help">Mật khẩu phải có ít nhất 6 ký tự</p>
                            </div>

                            <button type="submit" name="change_password" class="btn btn-primary">
                                <i class="fas fa-key"></i>
                                Đổi mật khẩu
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Preferences Panel -->
                <div class="settings-panel" id="preferences">
                    <div class="section">
                        <div class="section-header">
                            <div class="section-icon">
                                <i class="fas fa-sliders-h"></i>
                            </div>
                            <div>
                                <h2 class="section-title">Tùy chọn hệ thống</h2>
                                <p class="section-subtitle">Cấu hình hiển thị và tùy chỉnh</p>
                            </div>
                        </div>

                        <form method="POST">
                            <div class="form-group">
                                <label class="form-label">Đơn vị tiền tệ</label>
                                <select name="currency" class="form-select">
                                    <option value="VND" <?= $user['currency'] === 'VND' ? 'selected' : '' ?>>🇻🇳 Việt Nam Đồng (VND)</option>
                                    <option value="USD" <?= $user['currency'] === 'USD' ? 'selected' : '' ?>>🇺🇸 US Dollar (USD)</option>
                                    <option value="EUR" <?= $user['currency'] === 'EUR' ? 'selected' : '' ?>>🇪🇺 Euro (EUR)</option>
                                    <option value="JPY" <?= $user['currency'] === 'JPY' ? 'selected' : '' ?>>🇯🇵 Japanese Yen (JPY)</option>
                                </select>
                                <p class="form-help">Đơn vị tiền tệ hiển thị trong hệ thống</p>
                            </div>

                            <div class="form-group">
                                <label class="form-label">Ngân sách tháng</label>
                                <input type="number" name="monthly_budget" class="form-input" 
                                       value="<?= $user['monthly_budget'] ?>" 
                                       placeholder="15000000" step="100000" min="0">
                                <p class="form-help">Đặt mục tiêu ngân sách chi tiêu hàng tháng</p>
                            </div>

                            <button type="submit" name="update_preferences" class="btn btn-primary">
                                <i class="fas fa-save"></i>
                                Lưu cài đặt
                            </button>
                        </form>
                    </div>

                    <div class="section">
                        <div class="section-header">
                            <div class="section-icon">
                                <i class="fas fa-palette"></i>
                            </div>
                            <div>
                                <h2 class="section-title">Giao diện</h2>
                                <p class="section-subtitle">Tùy chỉnh màu sắc và theme</p>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Chế độ hiển thị</label>
                            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;">
                                <div style="padding: 20px; border: 2px solid var(--border); border-radius: 12px; text-align: center; cursor: pointer; transition: all 0.2s;">
                                    <i class="fas fa-sun" style="font-size: 32px; color: var(--warning); margin-bottom: 8px;"></i>
                                    <div style="font-weight: 600;">Sáng</div>
                                    <small style="color: var(--text-light);">Đang sử dụng</small>
                                </div>
                                <div style="padding: 20px; border: 2px solid var(--border); border-radius: 12px; text-align: center; cursor: pointer; opacity: 0.5;">
                                    <i class="fas fa-moon" style="font-size: 32px; margin-bottom: 8px;"></i>
                                    <div style="font-weight: 600;">Tối</div>
                                    <small style="color: var(--text-light);">Sắp ra mắt</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Account Stats Panel -->
                <div class="settings-panel" id="account">
                    <div class="section">
                        <div class="section-header">
                            <div class="section-icon">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <div>
                                <h2 class="section-title">Thống kê tài khoản</h2>
                                <p class="section-subtitle">Tổng quan hoạt động và dữ liệu</p>
                            </div>
                        </div>

                        <div class="stats-grid">
                            <div class="stat-box">
                                <div class="stat-value"><?= $walletCount ?></div>
                                <div class="stat-label">Số ví/tài khoản</div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-value"><?= $transactionCount ?></div>
                                <div class="stat-label">Giao dịch</div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-value">
                                    <?php 
                                    $memberSince = new DateTime($user['created_at']);
                                    $now = new DateTime();
                                    $diff = $now->diff($memberSince);
                                    echo $diff->days;
                                    ?>
                                </div>
                                <div class="stat-label">Ngày sử dụng</div>
                            </div>
                        </div>

                        <div style="padding: 20px; background: var(--bg); border-radius: 12px; margin-bottom: 20px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 12px;">
                                <span style="font-weight: 600;">Tham gia từ:</span>
                                <span><?= date('d/m/Y', strtotime($user['created_at'])) ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between; margin-bottom: 12px;">
                                <span style="font-weight: 600;">Đăng nhập gần nhất:</span>
                                <span><?= $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Chưa có' ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span style="font-weight: 600;">Trạng thái:</span>
                                <span class="badge badge-primary">
                                    <i class="fas fa-check-circle"></i>
                                    Đang hoạt động
                                </span>
                            </div>
                        </div>

                        <div style="padding: 20px; background: #FEF2F2; border: 1px solid #FCA5A5; border-radius: 12px;">
                            <h3 style="color: var(--danger); margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-exclamation-triangle"></i>
                                Vùng nguy hiểm
                            </h3>
                            <p style="color: var(--text-light); font-size: 14px; margin-bottom: 16px;">
                                Xóa tài khoản sẽ xóa vĩnh viễn tất cả dữ liệu của bạn. Hành động này không thể hoàn tác.
                            </p>
                            <button class="btn btn-danger" onclick="if(confirm('Bạn có chắc chắn muốn xóa tài khoản? Hành động này không thể hoàn tác!')) alert('Tính năng đang phát triển')">
                                <i class="fas fa-trash"></i>
                                Xóa tài khoản
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Tab switching
        const tabs = document.querySelectorAll('.settings-tab');
        const panels = document.querySelectorAll('.settings-panel');

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const targetTab = tab.dataset.tab;

                // Remove active class from all tabs and panels
                tabs.forEach(t => t.classList.remove('active'));
                panels.forEach(p => p.classList.remove('active'));

                // Add active class to clicked tab and corresponding panel
                tab.classList.add('active');
                document.getElementById(targetTab).classList.add('active');
            });
        });

        // Auto hide alert after 5 seconds
        const alert = document.querySelector('.alert');
        if (alert) {
            setTimeout(() => {
                alert.style.animation = 'slideDown 0.3s ease reverse';
                setTimeout(() => alert.remove(), 300);
            }, 5000);
        }

        // Form validation
        const passwordForm = document.querySelector('form[name="change_password"]');
        if (passwordForm) {
            passwordForm.addEventListener('submit', (e) => {
                const newPassword = document.querySelector('input[name="new_password"]').value;
                const confirmPassword = document.querySelector('input[name="confirm_password"]').value;

                if (newPassword !== confirmPassword) {
                    e.preventDefault();
                    alert('Mật khẩu xác nhận không khớp!');
                    return false;
                }

                if (newPassword.length < 6) {
                    e.preventDefault();
                    alert('Mật khẩu phải có ít nhất 6 ký tự!');
                    return false;
                }
            });
        }
    </script>
</body>
</html>
