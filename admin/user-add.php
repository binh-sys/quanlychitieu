<?php
require_once '../connect.php';

// Get user info
$user = [
    'id' => 1,
    'full_name' => 'Administrator',
    'email' => 'admin@fintrack.vn',
    'role' => 'admin'
];

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $role = $_POST['role'];
    $monthly_budget = floatval($_POST['monthly_budget']);
    
    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
        $error = 'Vui lòng điền đầy đủ thông tin bắt buộc!';
    } else {
        // Check if username or email exists
        $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check->bind_param("ss", $username, $email);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = 'Username hoặc email đã tồn tại!';
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, phone, role, monthly_budget) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("ssssssd", $username, $email, $hashed_password, $full_name, $phone, $role, $monthly_budget);
            
            if ($stmt->execute()) {
                $success = 'Thêm người dùng thành công!';
                header('Location: users.php?msg=added');
                exit();
            } else {
                $error = 'Có lỗi xảy ra: ' . $conn->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm Người dùng - Admin</title>
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <?php include 'includes/header.php'; ?>

        <div class="content">
            <div class="page-header">
                <h1>Thêm Người dùng Mới</h1>
                <p>Tạo tài khoản người dùng mới trong hệ thống</p>
            </div>

            <?php if ($error): ?>
            <div style="padding: 12px 20px; background: #FEE2E2; color: #991B1B; border-radius: 8px; margin-bottom: 20px; border: 1px solid #FCA5A5;">
                ⚠️ <?= $error ?>
            </div>
            <?php endif; ?>

            <?php if ($success): ?>
            <div style="padding: 12px 20px; background: #D1FAE5; color: #065F46; border-radius: 8px; margin-bottom: 20px; border: 1px solid #6EE7B7;">
                ✓ <?= $success ?>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h2>Thông tin người dùng</h2>
                    <button onclick="window.location.href='users.php'" style="padding: 8px 16px; border-radius: 8px; border: 1px solid #E5E7EB; background: white; cursor: pointer; font-size: 13px;">
                        ← Quay lại
                    </button>
                </div>
                <div style="padding: 32px;">
                    <form method="POST" style="max-width: 600px;">
                        <div style="display: grid; gap: 20px;">
                            <!-- Username -->
                            <div>
                                <label style="display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px;">
                                    Username <span style="color: #EF4444;">*</span>
                                </label>
                                <input type="text" name="username" required 
                                    style="width: 100%; padding: 10px 14px; border: 1.5px solid #E5E7EB; border-radius: 8px; font-size: 14px; transition: border .2s;"
                                    onfocus="this.style.borderColor='#6366F1'" onblur="this.style.borderColor='#E5E7EB'"
                                    placeholder="Nhập username">
                            </div>

                            <!-- Email -->
                            <div>
                                <label style="display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px;">
                                    Email <span style="color: #EF4444;">*</span>
                                </label>
                                <input type="email" name="email" required 
                                    style="width: 100%; padding: 10px 14px; border: 1.5px solid #E5E7EB; border-radius: 8px; font-size: 14px; transition: border .2s;"
                                    onfocus="this.style.borderColor='#6366F1'" onblur="this.style.borderColor='#E5E7EB'"
                                    placeholder="Nhập email">
                            </div>

                            <!-- Password -->
                            <div>
                                <label style="display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px;">
                                    Mật khẩu <span style="color: #EF4444;">*</span>
                                </label>
                                <input type="password" name="password" required 
                                    style="width: 100%; padding: 10px 14px; border: 1.5px solid #E5E7EB; border-radius: 8px; font-size: 14px; transition: border .2s;"
                                    onfocus="this.style.borderColor='#6366F1'" onblur="this.style.borderColor='#E5E7EB'"
                                    placeholder="Nhập mật khẩu">
                            </div>

                            <!-- Full Name -->
                            <div>
                                <label style="display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px;">
                                    Họ và tên <span style="color: #EF4444;">*</span>
                                </label>
                                <input type="text" name="full_name" required 
                                    style="width: 100%; padding: 10px 14px; border: 1.5px solid #E5E7EB; border-radius: 8px; font-size: 14px; transition: border .2s;"
                                    onfocus="this.style.borderColor='#6366F1'" onblur="this.style.borderColor='#E5E7EB'"
                                    placeholder="Nhập họ và tên">
                            </div>

                            <!-- Phone -->
                            <div>
                                <label style="display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px;">
                                    Số điện thoại
                                </label>
                                <input type="text" name="phone" 
                                    style="width: 100%; padding: 10px 14px; border: 1.5px solid #E5E7EB; border-radius: 8px; font-size: 14px; transition: border .2s;"
                                    onfocus="this.style.borderColor='#6366F1'" onblur="this.style.borderColor='#E5E7EB'"
                                    placeholder="Nhập số điện thoại">
                            </div>

                            <!-- Role -->
                            <div>
                                <label style="display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px;">
                                    Vai trò <span style="color: #EF4444;">*</span>
                                </label>
                                <select name="role" required 
                                    style="width: 100%; padding: 10px 14px; border: 1.5px solid #E5E7EB; border-radius: 8px; font-size: 14px; transition: border .2s;"
                                    onfocus="this.style.borderColor='#6366F1'" onblur="this.style.borderColor='#E5E7EB'">
                                    <option value="user">User - Người dùng</option>
                                    <option value="admin">Admin - Quản trị viên</option>
                                </select>
                            </div>

                            <!-- Monthly Budget -->
                            <div>
                                <label style="display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px;">
                                    Ngân sách tháng (VNĐ)
                                </label>
                                <input type="number" name="monthly_budget" value="0" min="0" step="1000"
                                    style="width: 100%; padding: 10px 14px; border: 1.5px solid #E5E7EB; border-radius: 8px; font-size: 14px; transition: border .2s;"
                                    onfocus="this.style.borderColor='#6366F1'" onblur="this.style.borderColor='#E5E7EB'"
                                    placeholder="Nhập ngân sách tháng">
                            </div>

                            <!-- Submit Buttons -->
                            <div style="display: flex; gap: 12px; margin-top: 12px;">
                                <button type="submit" style="flex: 1; padding: 12px; background: #6366F1; color: white; border: none; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer; transition: background .2s;">
                                    ✓ Thêm người dùng
                                </button>
                                <button type="button" onclick="window.location.href='users.php'" style="padding: 12px 24px; background: white; color: #6B7280; border: 1.5px solid #E5E7EB; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer;">
                                    Hủy
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/admin.js"></script>
</body>
</html>
