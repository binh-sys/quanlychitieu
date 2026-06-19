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

// Common icons for categories
$common_icons = [
    '🍜', '🍕', '🍔', '🍱', '☕', '🍺', // Food & Drink
    '🚗', '🚕', '🚌', '🚇', '✈️', '🚲', // Transport
    '🏠', '🏢', '🏪', '🏥', '🏫', '⛪', // Buildings
    '💰', '💵', '💳', '💎', '🏦', '📊', // Money
    '📱', '💻', '⌚', '📷', '🎮', '📺', // Electronics
    '👕', '👔', '👗', '👠', '👜', '💄', // Fashion
    '📚', '✏️', '📝', '🎓', '📖', '🖊️', // Education
    '🎬', '🎵', '🎮', '🎯', '🎨', '🎭', // Entertainment
    '❤️', '💊', '🏥', '💉', '🩺', '⚕️', // Health
    '⚡', '💧', '🔥', '📡', '📞', '🌐', // Utilities
    '🎁', '🎂', '🎉', '🎈', '🎊', '🎀', // Gifts & Events
    '🌳', '🌺', '🌻', '🌸', '🌼', '🌷', // Nature
];

// Common colors
$common_colors = [
    '#FF6B6B', '#FF9F43', '#EE5A24', '#FFA502', '#FDCB6E',
    '#6C5CE7', '#A29BFE', '#74B9FF', '#0984E3', '#00B894',
    '#55EFC4', '#00D2D3', '#FD79A8', '#E17055', '#636E72',
    '#2D3436', '#B2BEC3', '#DFE6E9', '#F59E0B', '#10B981',
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $type = $_POST['type'];
    $icon = $_POST['icon'];
    $color = $_POST['color'];
    $description = trim($_POST['description']);
    $user_id = !empty($_POST['user_id']) ? intval($_POST['user_id']) : NULL;
    $is_system = isset($_POST['is_system']) ? 1 : 0;
    
    // Validation
    if (empty($name) || empty($type) || empty($icon) || empty($color)) {
        $error = 'Vui lòng điền đầy đủ thông tin bắt buộc!';
    } else {
        // Insert category
        $stmt = $conn->prepare("INSERT INTO categories (user_id, name, type, icon, color, description, is_system) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssi", $user_id, $name, $type, $icon, $color, $description, $is_system);
        
        if ($stmt->execute()) {
            header('Location: categories.php?msg=added');
            exit();
        } else {
            $error = 'Có lỗi xảy ra: ' . $conn->error;
        }
    }
}

// Get users for dropdown
$users_list = $conn->query("SELECT id, full_name FROM users ORDER BY full_name");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm Danh mục - Admin</title>
    <link rel="stylesheet" href="assets/css/admin.css">
    <style>
        .icon-picker {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(45px, 1fr));
            gap: 8px;
            max-height: 200px;
            overflow-y: auto;
            padding: 12px;
            border: 1.5px solid #E5E7EB;
            border-radius: 8px;
            background: #F9FAFB;
        }
        .icon-option {
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            border: 2px solid transparent;
            border-radius: 8px;
            cursor: pointer;
            transition: all .2s;
            background: white;
        }
        .icon-option:hover {
            border-color: #6366F1;
            transform: scale(1.1);
        }
        .icon-option.selected {
            border-color: #6366F1;
            background: #EFF6FF;
        }
        .color-picker {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(40px, 1fr));
            gap: 8px;
            padding: 12px;
            border: 1.5px solid #E5E7EB;
            border-radius: 8px;
            background: #F9FAFB;
        }
        .color-option {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            cursor: pointer;
            transition: all .2s;
            border: 3px solid transparent;
        }
        .color-option:hover {
            transform: scale(1.1);
        }
        .color-option.selected {
            border-color: #1F2937;
            box-shadow: 0 0 0 2px white, 0 0 0 4px #1F2937;
        }
    </style>
</head>
<body>

    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <?php include 'includes/header.php'; ?>

        <div class="content">
            <div class="page-header">
                <h1>Thêm Danh mục Mới</h1>
                <p>Tạo danh mục thu/chi cho hệ thống</p>
            </div>

            <?php if ($error): ?>
            <div style="padding: 12px 20px; background: #FEE2E2; color: #991B1B; border-radius: 8px; margin-bottom: 20px; border: 1px solid #FCA5A5;">
                ⚠️ <?= $error ?>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h2>Thông tin danh mục</h2>
                    <button onclick="window.location.href='categories.php'" style="padding: 8px 16px; border-radius: 8px; border: 1px solid #E5E7EB; background: white; cursor: pointer; font-size: 13px;">
                        ← Quay lại
                    </button>
                </div>
                <div style="padding: 32px;">
                    <form method="POST" style="max-width: 700px;">
                        <div style="display: grid; gap: 24px;">
                            <!-- Name -->
                            <div>
                                <label style="display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px;">
                                    Tên danh mục <span style="color: #EF4444;">*</span>
                                </label>
                                <input type="text" name="name" required 
                                    style="width: 100%; padding: 10px 14px; border: 1.5px solid #E5E7EB; border-radius: 8px; font-size: 14px; transition: border .2s;"
                                    onfocus="this.style.borderColor='#6366F1'" onblur="this.style.borderColor='#E5E7EB'"
                                    placeholder="VD: Ăn uống, Đi lại, Lương...">
                            </div>

                            <!-- Type -->
                            <div>
                                <label style="display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px;">
                                    Loại danh mục <span style="color: #EF4444;">*</span>
                                </label>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                                    <label style="padding: 16px; border: 2px solid #E5E7EB; border-radius: 10px; cursor: pointer; display: flex; align-items: center; gap: 12px; transition: all .2s;" onclick="this.style.borderColor='#10B981'; this.style.background='rgba(16, 185, 129, 0.05)'">
                                        <input type="radio" name="type" value="income" required style="width: 20px; height: 20px;">
                                        <div>
                                            <div style="font-weight: 700; font-size: 15px; color: #10B981; margin-bottom: 2px;">📈 Thu nhập</div>
                                            <div style="font-size: 12px; color: #6B7280;">Danh mục cho tiền vào</div>
                                        </div>
                                    </label>
                                    <label style="padding: 16px; border: 2px solid #E5E7EB; border-radius: 10px; cursor: pointer; display: flex; align-items: center; gap: 12px; transition: all .2s;" onclick="this.style.borderColor='#EF4444'; this.style.background='rgba(239, 68, 68, 0.05)'">
                                        <input type="radio" name="type" value="expense" required style="width: 20px; height: 20px;">
                                        <div>
                                            <div style="font-weight: 700; font-size: 15px; color: #EF4444; margin-bottom: 2px;">📉 Chi tiêu</div>
                                            <div style="font-size: 12px; color: #6B7280;">Danh mục cho tiền ra</div>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Icon Picker -->
                            <div>
                                <label style="display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px;">
                                    Icon <span style="color: #EF4444;">*</span>
                                </label>
                                <input type="hidden" name="icon" id="selectedIcon" required>
                                <div style="margin-bottom: 8px; padding: 12px; background: #F3F4F6; border-radius: 8px; display: flex; align-items: center; gap: 10px;">
                                    <span style="font-size: 11px; color: #6B7280; font-weight: 600;">ĐÃ CHỌN:</span>
                                    <span id="iconPreview" style="font-size: 32px;">❓</span>
                                </div>
                                <div class="icon-picker">
                                    <?php foreach ($common_icons as $icon): ?>
                                    <div class="icon-option" onclick="selectIcon('<?= $icon ?>')"><?= $icon ?></div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Color Picker -->
                            <div>
                                <label style="display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px;">
                                    Màu sắc <span style="color: #EF4444;">*</span>
                                </label>
                                <input type="hidden" name="color" id="selectedColor" required>
                                <div style="margin-bottom: 8px; padding: 12px; background: #F3F4F6; border-radius: 8px; display: flex; align-items: center; gap: 10px;">
                                    <span style="font-size: 11px; color: #6B7280; font-weight: 600;">ĐÃ CHỌN:</span>
                                    <div id="colorPreview" style="width: 40px; height: 40px; border-radius: 8px; background: #E5E7EB; border: 2px solid #9CA3AF;"></div>
                                    <span id="colorCode" style="font-size: 13px; font-weight: 600; color: #374151;">#E5E7EB</span>
                                </div>
                                <div class="color-picker">
                                    <?php foreach ($common_colors as $color): ?>
                                    <div class="color-option" style="background: <?= $color ?>;" onclick="selectColor('<?= $color ?>')"></div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- User (Optional) -->
                            <div>
                                <label style="display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px;">
                                    Người dùng (Để trống nếu là danh mục chung)
                                </label>
                                <select name="user_id" 
                                    style="width: 100%; padding: 10px 14px; border: 1.5px solid #E5E7EB; border-radius: 8px; font-size: 14px; transition: border .2s;"
                                    onfocus="this.style.borderColor='#6366F1'" onblur="this.style.borderColor='#E5E7EB'">
                                    <option value="">-- Danh mục chung (tất cả user) --</option>
                                    <?php while ($u = $users_list->fetch_assoc()): ?>
                                    <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                                <small style="color: #6B7280; font-size: 12px;">Nếu chọn user, chỉ user đó mới thấy danh mục này</small>
                            </div>

                            <!-- System Category -->
                            <div>
                                <label style="display: flex; align-items: center; gap: 10px; cursor: pointer; padding: 12px; background: #FFFBEB; border: 1px solid #FCD34D; border-radius: 8px;">
                                    <input type="checkbox" name="is_system" style="width: 18px; height: 18px;">
                                    <div>
                                        <div style="font-weight: 600; font-size: 13px; color: #92400E;">🔒 Đánh dấu là danh mục hệ thống</div>
                                        <div style="font-size: 12px; color: #78350F;">Danh mục hệ thống không thể xóa bởi người dùng</div>
                                    </div>
                                </label>
                            </div>

                            <!-- Description -->
                            <div>
                                <label style="display: block; font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 6px;">
                                    Mô tả
                                </label>
                                <textarea name="description" rows="3"
                                    style="width: 100%; padding: 10px 14px; border: 1.5px solid #E5E7EB; border-radius: 8px; font-size: 14px; transition: border .2s; resize: vertical;"
                                    onfocus="this.style.borderColor='#6366F1'" onblur="this.style.borderColor='#E5E7EB'"
                                    placeholder="Mô tả về danh mục này..."></textarea>
                            </div>

                            <!-- Submit Buttons -->
                            <div style="display: flex; gap: 12px; margin-top: 12px;">
                                <button type="submit" style="flex: 1; padding: 12px; background: #6366F1; color: white; border: none; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer; transition: background .2s;">
                                    ✓ Thêm danh mục
                                </button>
                                <button type="button" onclick="window.location.href='categories.php'" style="padding: 12px 24px; background: white; color: #6B7280; border: 1.5px solid #E5E7EB; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer;">
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
        function selectIcon(icon) {
            document.getElementById('selectedIcon').value = icon;
            document.getElementById('iconPreview').textContent = icon;
            
            // Update UI
            document.querySelectorAll('.icon-option').forEach(el => el.classList.remove('selected'));
            event.target.classList.add('selected');
        }

        function selectColor(color) {
            document.getElementById('selectedColor').value = color;
            document.getElementById('colorPreview').style.background = color;
            document.getElementById('colorCode').textContent = color;
            
            // Update UI
            document.querySelectorAll('.color-option').forEach(el => el.classList.remove('selected'));
            event.target.classList.add('selected');
        }
    </script>
</body>
</html>
