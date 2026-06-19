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

// Get users for dropdown
$users_list = $conn->query("SELECT id, full_name FROM users ORDER BY full_name");

// Get categories for dropdown
$categories_list = $conn->query("SELECT id, name, type, icon FROM categories ORDER BY type, name");

// Get wallets for dropdown (will be filtered by user via AJAX in real app, but for now show all)
$wallets_list = $conn->query("SELECT w.id, w.name, w.type, u.full_name as user_name FROM wallets w JOIN users u ON w.user_id = u.id ORDER BY u.full_name, w.name");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = intval($_POST['user_id']);
    $wallet_id = intval($_POST['wallet_id']);
    $category_id = intval($_POST['category_id']);
    $type = $_POST['type'];
    $amount = floatval($_POST['amount']);
    $description = trim($_POST['description']);
    $note = trim($_POST['note']);
    $transaction_date = $_POST['transaction_date'];
    
    // Validation
    if (empty($user_id) || empty($wallet_id) || empty($category_id) || empty($type) || empty($amount) || empty($transaction_date)) {
        $error = 'Vui lòng điền đầy đủ thông tin bắt buộc!';
    } elseif ($amount <= 0) {
        $error = 'Số tiền phải lớn hơn 0!';
    } else {
        // Insert transaction
        $stmt = $conn->prepare("INSERT INTO transactions (user_id, wallet_id, category_id, type, amount, description, note, transaction_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiisdsss", $user_id, $wallet_id, $category_id, $type, $amount, $description, $note, $transaction_date);
        
        if ($stmt->execute()) {
            // Update wallet balance
            if ($type == 'income') {
                $conn->query("UPDATE wallets SET balance = balance + $amount WHERE id = $wallet_id");
            } else {
                $conn->query("UPDATE wallets SET balance = balance - $amount WHERE id = $wallet_id");
            }
            
            header('Location: transactions.php?msg=added');
            exit();
        } else {
            $error = 'Có lỗi xảy ra: ' . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm Giao dịch - Admin</title>
    <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body>

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <?php include 'includes/header.php'; ?>

        <div class="content">
            <div class="page-header">
                <h1>Thêm Giao dịch Mới</h1>
                <p>Tạo giao dịch thu/chi cho người dùng</p>
            </div>

            <?php if ($error): ?>
            <div style="padding: 12px 20px; background: #FEE2E2; color: #991B1B; border-radius: 8px; margin-bottom: 20px; border: 1px solid #FCA5A5;">
                ⚠️ <?= $error ?>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h2>Thông tin giao dịch</h2>
                    <button onclick="window.location.href='transactions.php'" style="padding: 8px 16px; border-radius: 8px; border: 1px solid #E5E7EB; background: white; cursor: pointer; font-size: 13px;">
                        ← Quay lại
                    </button>
                </div>
                <div style="padding: 32px;">
                    <form method="POST" style="max-width: 700px;">
                        <div style="display: grid; gap: 20px;">
                            <!-- User Selection -->
                            <div>
                                <label style="display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px;">
                                    Người dùng <span style="color: #EF4444;">*</span>
                                </label>
                                <select name="user_id" id="user_id" required 
                                    style="width: 100%; padding: 10px 14px; border: 1.5px solid #E5E7EB; border-radius: 8px; font-size: 14px; transition: border .2s;"
                                    onfocus="this.style.borderColor='#6366F1'" onblur="this.style.borderColor='#E5E7EB'">
                                    <option value="">-- Chọn người dùng --</option>
                                    <?php 
                                    $users_list->data_seek(0);
                                    while ($u = $users_list->fetch_assoc()): 
                                    ?>
                                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <!-- Type Selection -->
                            <div>
                                <label style="display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px;">
                                    Loại giao dịch <span style="color: #EF4444;">*</span>
                                </label>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                    <label style="padding: 12px; border: 2px solid #E5E7EB; border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 10px; transition: all .2s;" onclick="this.style.borderColor='#10B981'; this.style.background='rgba(16, 185, 129, 0.05)'">
                                        <input type="radio" name="type" value="income" required style="width: 18px; height: 18px;">
                                        <div>
                                            <div style="font-weight: 600; color: #10B981;">📈 Thu nhập</div>
                                            <div style="font-size: 12px; color: #6B7280;">Tiền vào</div>
                                        </div>
                                    </label>
                                    <label style="padding: 12px; border: 2px solid #E5E7EB; border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 10px; transition: all .2s;" onclick="this.style.borderColor='#EF4444'; this.style.background='rgba(239, 68, 68, 0.05)'">
                                        <input type="radio" name="type" value="expense" required style="width: 18px; height: 18px;">
                                        <div>
                                            <div style="font-weight: 600; color: #EF4444;">📉 Chi tiêu</div>
                                            <div style="font-size: 12px; color: #6B7280;">Tiền ra</div>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Amount -->
                            <div>
                                <label style="display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px;">
                                    Số tiền (VNĐ) <span style="color: #EF4444;">*</span>
                                </label>
                                <input type="number" name="amount" required min="1" step="1"
                                    style="width: 100%; padding: 10px 14px; border: 1.5px solid #E5E7EB; border-radius: 8px; font-size: 14px; transition: border .2s;"
                                    onfocus="this.style.borderColor='#6366F1'" onblur="this.style.borderColor='#E5E7EB'"
                                    placeholder="Nhập số tiền">
                            </div>

                            <!-- Category -->
                            <div>
                                <label style="display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px;">
                                    Danh mục <span style="color: #EF4444;">*</span>
                                </label>
                                <select name="category_id" required 
                                    style="width: 100%; padding: 10px 14px; border: 1.5px solid #E5E7EB; border-radius: 8px; font-size: 14px; transition: border .2s;"
                                    onfocus="this.style.borderColor='#6366F1'" onblur="this.style.borderColor='#E5E7EB'">
                                    <option value="">-- Chọn danh mục --</option>
                                    <?php 
                                    $categories_list->data_seek(0);
                                    $current_type = '';
                                    while ($cat = $categories_list->fetch_assoc()): 
                                        if ($current_type != $cat['type']) {
                                            if ($current_type != '') echo '</optgroup>';
                                            echo '<optgroup label="' . ($cat['type'] == 'income' ? '📈 Thu nhập' : '📉 Chi tiêu') . '">';
                                            $current_type = $cat['type'];
                                        }
                                    ?>
                                    <option value="<?= $cat['id'] ?>"><?= $cat['icon'] ?> <?= htmlspecialchars($cat['name']) ?></option>
                                    <?php endwhile; ?>
                                    </optgroup>
                                </select>
                            </div>

                            <!-- Wallet -->
                            <div>
                                <label style="display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px;">
                                    Ví tiền <span style="color: #EF4444;">*</span>
                                </label>
                                <select name="wallet_id" required 
                                    style="width: 100%; padding: 10px 14px; border: 1.5px solid #E5E7EB; border-radius: 8px; font-size: 14px; transition: border .2s;"
                                    onfocus="this.style.borderColor='#6366F1'" onblur="this.style.borderColor='#E5E7EB'">
                                    <option value="">-- Chọn ví --</option>
                                    <?php 
                                    $wallets_list->data_seek(0);
                                    while ($w = $wallets_list->fetch_assoc()): 
                                    ?>
                                    <option value="<?= $w['id'] ?>"><?= htmlspecialchars($w['user_name']) ?> - <?= htmlspecialchars($w['name']) ?> (<?= ucfirst($w['type']) ?>)</option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <!-- Transaction Date -->
                            <div>
                                <label style="display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px;">
                                    Ngày giao dịch <span style="color: #EF4444;">*</span>
                                </label>
                                <input type="date" name="transaction_date" required value="<?= date('Y-m-d') ?>"
                                    style="width: 100%; padding: 10px 14px; border: 1.5px solid #E5E7EB; border-radius: 8px; font-size: 14px; transition: border .2s;"
                                    onfocus="this.style.borderColor='#6366F1'" onblur="this.style.borderColor='#E5E7EB'">
                            </div>

                            <!-- Description -->
                            <div>
                                <label style="display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px;">
                                    Mô tả
                                </label>
                                <input type="text" name="description" 
                                    style="width: 100%; padding: 10px 14px; border: 1.5px solid #E5E7EB; border-radius: 8px; font-size: 14px; transition: border .2s;"
                                    onfocus="this.style.borderColor='#6366F1'" onblur="this.style.borderColor='#E5E7EB'"
                                    placeholder="VD: Mua sắm tại siêu thị">
                            </div>

                            <!-- Note -->
                            <div>
                                <label style="display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px;">
                                    Ghi chú
                                </label>
                                <textarea name="note" rows="3"
                                    style="width: 100%; padding: 10px 14px; border: 1.5px solid #E5E7EB; border-radius: 8px; font-size: 14px; transition: border .2s; resize: vertical;"
                                    onfocus="this.style.borderColor='#6366F1'" onblur="this.style.borderColor='#E5E7EB'"
                                    placeholder="Ghi chú thêm về giao dịch..."></textarea>
                            </div>

                            <!-- Submit Buttons -->
                            <div style="display: flex; gap: 12px; margin-top: 12px;">
                                <button type="submit" style="flex: 1; padding: 12px; background: #6366F1; color: white; border: none; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer; transition: background .2s;">
                                    ✓ Thêm giao dịch
                                </button>
                                <button type="button" onclick="window.location.href='transactions.php'" style="padding: 12px 24px; background: white; color: #6B7280; border: 1.5px solid #E5E7EB; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer;">
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
