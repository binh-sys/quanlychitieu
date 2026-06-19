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

// Get user ID
if (!isset($_GET['id'])) {
    header('Location: users.php');
    exit();
}

$user_id = intval($_GET['id']);

// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$edit_user = $stmt->get_result()->fetch_assoc();

if (!$edit_user) {
    header('Location: users.php?msg=notfound');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $role = $_POST['role'];
    $monthly_budget = floatval($_POST['monthly_budget']);
    $new_password = $_POST['new_password'];
    
    // Validation
    if (empty($username) || empty($email) || empty($full_name)) {
        $error = 'Vui lòng điền đầy đủ thông tin bắt buộc!';
    } else {
        // Check if username or email exists (except current user)
        $check = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $check->bind_param("ssi", $username, $email, $user_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $error = 'Username hoặc email đã tồn tại!';
        } else {
            // Update user
            if (!empty($new_password)) {
                // Update with new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, password = ?, full_name = ?, phone = ?, role = ?, monthly_budget = ? WHERE id = ?");
                $stmt->bind_param("ssssssdi", $username, $email, $hashed_password, $full_name, $phone, $role, $monthly_budget, $user_id);
            } else {
                // Update without password
                $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, full_name = ?, phone = ?, role = ?, monthly_budget = ? WHERE id = ?");
                $stmt->bind_param("sssssdi", $username, $email, $full_name, $phone, $role, $monthly_budget, $user_id);
            }
            
            if ($stmt->execute()) {
                header('Location: users.php?msg=updated');
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
    <title>Sửa Người dùng - Admin</title>
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <?php include 'includes/header.php'; ?>

        <div class="content">
            <div class="page-header">
                <h1>Sửa Thông tin Người dùng</h1>
                <p>Cập nhật thông tin cho: <strong><?= htmlspecialchars($edit_user['full_name']) ?></strong></p>
            </div>

            <?php if ($error): ?>
            <div style="padding: 12px 20px; background: #FEE2E2; color: #991B1B; border-radius: 8px; margin-bottom: 20px; border: 1px solid #FCA5A5;">
                ⚠️ <?= $error ?>
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
                                <input type="text" name="username" required value="<?= htmlspecialchars($edit_user['username']) ?>"
                                    style="width: 100%; padding: 10px 14px; border: 1.5px solid #E5E7EB; border-radius: 8px; font-size: 14px; transition: border .2s;"
                                    onfocus="this.style.borderColor='#6366F1'" onblur="this.style.borderColor='#E5E7EB'">
                            </div>

                            <!-- Email -->
                            <div>
                                <label style="display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px;">
                                    Email <span style="color: #EF4444;">*</span>
                                </label>
                                <input type="email" name="email" required value="<?= htmlspecialchars($edit_user['email']) ?>"
                                    style="width: 100%; padding: 10px 14px; border: 1.5px solid #E5E7EB; border-radius: 8px; font-size: 14px; transition: border .2s;"
                                    onfocus="this.style.borderColor='#6366F1'" onblur="this.style.borderColor='#E5E7EB'">
                            </div>

                            <!-- New Password -->
                            <div>
                                <label style="display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px;">
                                    Mật khẩu mới
                                </label>
                                <input type="password" name="new_password" 
                                    style="width: 100%; padding: 10px 14px; border: 1.5px solid #E5E7EB; border-radius: 8px; font-size: 14px; transition: border .2s;"
                                    onfocus="this.style.borderColor='#6366F1'" onblur="this.style.borderColor='#E5E7EB'"
                                    placeholder="Để trống nếu không đổi mật khẩu">
                                <small style="color: #6B7280; font-size: 12px;">Để trống nếu không muốn thay đổi mật khẩu</small>
                            </div>

                            <!-- Full Name -->
                            <div>
                                <label style="display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px;">
                                    Họ và tên <span style="color: #EF4444;">*</span>
                                </label>
                                <input type="text" name="full_name" required value="<?= htmlspecialchars($edit_user['full_name']) ?>"
                                    style="width: 100%; padding: 10px 14px; border: 1.5px solid #E5E7EB; border-radius: 8px; font-size: 14px; transition: border .2s;"
                                    onfocus="this.style.borderColor='#6366F1'" onblur="this.style.borderColor='#E5E7EB'">
                            </div>

                            <!-- Phone -->
                            <div>
                                <label style="display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px;">
                                    Số điện thoại
                                </label>
                                <input type="text" name="phone" value="<?= htmlspecialchars($edit_user['phone']) ?>"
                                    style="width: 100%; padding: 10px 14px; border: 1.5px solid #E5E7EB; border-radius: 8px; font-size: 14px; transition: border .2s;"
                                    onfocus="this.style.borderColor='#6366F1'" onblur="this.style.borderColor='#E5E7EB'">
                            </div>

                            <!-- Role -->
                            <div>
                                <label style="display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px;">
                                    Vai trò <span style="color: #EF4444;">*</span>
                                </label>
                                <select name="role" required 
                                    style="width: 100%; padding: 10px 14px; border: 1.5px solid #E5E7EB; border-radius: 8px; font-size: 14px; transition: border .2s;"
                                    onfocus="this.style.borderColor='#6366F1'" onblur="this.style.borderColor='#E5E7EB'">
                                    <option value="user" <?= $edit_user['role'] == 'user' ? 'selected' : '' ?>>User - Người dùng</option>
                                    <option value="admin" <?= $edit_user['role'] == 'admin' ? 'selected' : '' ?>>Admin - Quản trị viên</option>
                                </select>
                            </div>

                            <!-- Monthly Budget -->
                            <div>
                                <label style="display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px;">
                                    Ngân sách tháng (VNĐ)
                                </label>
                                <input type="number" name="monthly_budget" value="<?= $edit_user['monthly_budget'] ?>" min="0" step="1000"
                                    style="width: 100%; padding: 10px 14px; border: 1.5px solid #E5E7EB; border-radius: 8px; font-size: 14px; transition: border .2s;"
                                    onfocus="this.style.borderColor='#6366F1'" onblur="this.style.borderColor='#E5E7EB'">
                            </div>

                            <!-- Submit Buttons -->
                            <div style="display: flex; gap: 12px; margin-top: 12px;">
                                <button type="submit" style="flex: 1; padding: 12px; background: #6366F1; color: white; border: none; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer; transition: background .2s;">
                                    ✓ Cập nhật
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
