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
    $recipient_type = $_POST['recipient_type'];
    $type = $_POST['type'];
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);
    $link = trim($_POST['link']);
    
    // Validation
    if (empty($title) || empty($message) || empty($type)) {
        $error = 'Vui lòng điền đầy đủ thông tin bắt buộc!';
    } else {
        // Get recipients
        $recipients = [];
        
        if ($recipient_type == 'all') {
            // Send to all users
            $users_query = $conn->query("SELECT id FROM users");
            while ($u = $users_query->fetch_assoc()) {
                $recipients[] = $u['id'];
            }
        } elseif ($recipient_type == 'specific' && !empty($_POST['user_ids'])) {
            // Send to specific users
            $recipients = $_POST['user_ids'];
        } else {
            $error = 'Vui lòng chọn người nhận!';
        }
        
        if (empty($error) && count($recipients) > 0) {
            // Insert notifications
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)");
            
            $success_count = 0;
            foreach ($recipients as $user_id) {
                $stmt->bind_param("issss", $user_id, $type, $title, $message, $link);
                if ($stmt->execute()) {
                    $success_count++;
                }
            }
            
            if ($success_count > 0) {
                header('Location: notifications.php?msg=added');
                exit();
            } else {
                $error = 'Có lỗi xảy ra khi gửi thông báo!';
            }
        }
    }
}

// Get users for selection
$users_list = $conn->query("SELECT id, full_name, email FROM users ORDER BY full_name");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gửi Thông báo - Admin</title>
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <?php include 'includes/header.php'; ?>

        <div class="content">
            <div class="page-header">
                <h1>📤 Gửi Thông báo Mới</h1>
                <p>Tạo và gửi thông báo đến người dùng</p>
            </div>

            <?php if ($error): ?>
            <div style="padding: 12px 20px; background: #FEE2E2; color: #991B1B; border-radius: 8px; margin-bottom: 20px; border: 1px solid #FCA5A5;">
                ⚠️ <?= $error ?>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h2>Thông tin thông báo</h2>
                    <button onclick="window.location.href='notifications.php'" style="padding: 8px 16px; border-radius: 8px; border: 1px solid #E5E7EB; background: white; cursor: pointer; font-size: 13px;">
                        ← Quay lại
                    </button>
                </div>
                <div style="padding: 32px;">
                    <form method="POST" style="max-width: 800px;">
                        <div style="display: grid; gap: 24px;">
                            <!-- Recipient Type -->
                            <div>
                                <label style="display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px;">
                                    Người nhận <span style="color: #EF4444;">*</span>
                                </label>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                    <label style="padding: 16px; border: 2px solid #E5E7EB; border-radius: 10px; cursor: pointer; display: flex; align-items: center; gap: 12px; transition: all .2s;" onclick="toggleRecipients('all')">
                                        <input type="radio" name="recipient_type" value="all" id="recipient_all" required style="width: 20px; height: 20px;">
                                        <div>
                                            <div style="font-weight: 700; font-size: 15px; color: #6366F1; margin-bottom: 2px;">👥 Tất cả người dùng</div>
                                            <div style="font-size: 12px; color: #6B7280;">Gửi đến toàn bộ người dùng</div>
                                        </div>
                                    </label>
                                    <label style="padding: 16px; border: 2px solid #E5E7EB; border-radius: 10px; cursor: pointer; display: flex; align-items: center; gap: 12px; transition: all .2s;" onclick="toggleRecipients('specific')">
                                        <input type="radio" name="recipient_type" value="specific" id="recipient_specific" required style="width: 20px; height: 20px;">
                                        <div>
                                            <div style="font-weight: 700; font-size: 15px; color: #F59E0B; margin-bottom: 2px;">👤 Người dùng cụ thể</div>
                                            <div style="font-size: 12px; color: #6B7280;">Chọn người nhận</div>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Specific Users Selection -->
                            <div id="specificUsers" style="display: none;">
                                <label style="display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px;">
                                    Chọn người dùng
                                </label>
                                <div style="max-height: 300px; overflow-y: auto; border: 1.5px solid #E5E7EB; border-radius: 8px; padding: 12px; background: #F9FAFB;">
                                    <?php while ($u = $users_list->fetch_assoc()): ?>
                                    <label style="display: flex; align-items: center; gap: 10px; padding: 10px; border-radius: 6px; cursor: pointer; transition: background .15s;" onmouseover="this.style.background='#EFF6FF'" onmouseout="this.style.background='transparent'">
                                        <input type="checkbox" name="user_ids[]" value="<?= $u['id'] ?>" style="width: 18px; height: 18px;">
                                        <div style="width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #6366F1, #8B5CF6); color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px; flex-shrink: 0;">
                                            <?= strtoupper(substr($u['full_name'], 0, 2)) ?>
                                        </div>
                                        <div style="flex: 1;">
                                            <div style="font-weight: 600; font-size: 14px;"><?= htmlspecialchars($u['full_name']) ?></div>
                                            <div style="font-size: 12px; color: #6B7280;"><?= htmlspecialchars($u['email']) ?></div>
                                        </div>
                                    </label>
                                    <?php endwhile; ?>
                                </div>
                            </div>

                            <!-- Notification Type -->
                            <div>
                                <label style="display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px;">
                                    Loại thông báo <span style="color: #EF4444;">*</span>
                                </label>
                                <select name="type" required style="width: 100%; padding: 10px 14px; border: 1.5px solid #E5E7EB; border-radius: 8px; font-size: 14px; transition: border .2s;" onfocus="this.style.borderColor='#6366F1'" onblur="this.style.borderColor='#E5E7EB'">
                                    <option value="">-- Chọn loại --</option>
                                    <option value="budget_alert">⚠️ Cảnh báo ngân sách</option>
                                    <option value="bill_reminder">🔔 Nhắc hóa đơn</option>
                                    <option value="goal_progress">🎯 Tiến độ mục tiêu</option>
                                    <option value="system">⚙️ Thông báo hệ thống</option>
                                </select>
                            </div>

                            <!-- Title -->
                            <div>
                                <label style="display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px;">
                                    Tiêu đề <span style="color: #EF4444;">*</span>
                                </label>
                                <input type="text" name="title" required 
                                    style="width: 100%; padding: 10px 14px; border: 1.5px solid #E5E7EB; border-radius: 8px; font-size: 14px; transition: border .2s;"
                                    onfocus="this.style.borderColor='#6366F1'" onblur="this.style.borderColor='#E5E7EB'"
                                    placeholder="VD: Cảnh báo vượt ngân sách">
                            </div>

                            <!-- Message -->
                            <div>
                                <label style="display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px;">
                                    Nội dung <span style="color: #EF4444;">*</span>
                                </label>
                                <textarea name="message" required rows="5"
                                    style="width: 100%; padding: 10px 14px; border: 1.5px solid #E5E7EB; border-radius: 8px; font-size: 14px; transition: border .2s; resize: vertical;"
                                    onfocus="this.style.borderColor='#6366F1'" onblur="this.style.borderColor='#E5E7EB'"
                                    placeholder="Nhập nội dung thông báo..."></textarea>
                            </div>

                            <!-- Link (Optional) -->
                            <div>
                                <label style="display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px;">
                                    Link (Tùy chọn)
                                </label>
                                <input type="text" name="link" 
                                    style="width: 100%; padding: 10px 14px; border: 1.5px solid #E5E7EB; border-radius: 8px; font-size: 14px; transition: border .2s;"
                                    onfocus="this.style.borderColor='#6366F1'" onblur="this.style.borderColor='#E5E7EB'"
                                    placeholder="VD: /budgets.php">
                                <small style="color: #6B7280; font-size: 12px;">Link để người dùng xem chi tiết (nếu có)</small>
                            </div>

                            <!-- Preview -->
                            <div style="padding: 20px; background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 10px;">
                                <div style="font-size: 12px; font-weight: 600; color: #6B7280; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px;">
                                    👁️ Xem trước
                                </div>
                                <div style="background: white; border: 1px solid #E5E7EB; border-radius: 8px; padding: 16px;">
                                    <div style="font-weight: 700; font-size: 15px; margin-bottom: 8px;">
                                        [Tiêu đề thông báo]
                                    </div>
                                    <div style="font-size: 14px; color: #374151; line-height: 1.6;">
                                        [Nội dung thông báo sẽ hiển thị ở đây]
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Buttons -->
                            <div style="display: flex; gap: 12px; margin-top: 12px;">
                                <button type="submit" style="flex: 1; padding: 12px; background: #6366F1; color: white; border: none; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer; transition: background .2s;">
                                    📤 Gửi thông báo
                                </button>
                                <button type="button" onclick="window.location.href='notifications.php'" style="padding: 12px 24px; background: white; color: #6B7280; border: 1.5px solid #E5E7EB; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer;">
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
    <script>
        function toggleRecipients(type) {
            const specificDiv = document.getElementById('specificUsers');
            if (type === 'specific') {
                specificDiv.style.display = 'block';
            } else {
                specificDiv.style.display = 'none';
            }
        }

        // Auto-update preview
        document.querySelector('input[name="title"]').addEventListener('input', function() {
            document.querySelector('.preview-title').textContent = this.value || '[Tiêu đề thông báo]';
        });

        document.querySelector('textarea[name="message"]').addEventListener('input', function() {
            document.querySelector('.preview-message').textContent = this.value || '[Nội dung thông báo sẽ hiển thị ở đây]';
        });
    </script>
</body>
</html>
