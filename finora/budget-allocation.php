<?php
session_start();
require_once __DIR__ . '/../connect.php';

// Kiểm tra đăng nhập
if (!isset($_SESSION['user_id'])) {
    header('Location: auth.php');
    exit;
}

$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'];

// Xử lý thêm/sửa/xóa phân bổ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $categoryId = intval($_POST['category_id']);
            $percentage = floatval($_POST['percentage']);
            $name = trim($_POST['name']);
            
            $stmt = $conn->prepare("INSERT INTO budget_allocations (user_id, category_id, name, percentage) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("iisd", $userId, $categoryId, $name, $percentage);
            $stmt->execute();
            
            header('Location: budget-allocation.php?msg=added');
            exit;
        }
        
        if ($_POST['action'] === 'update') {
            $id = intval($_POST['id']);
            $categoryId = intval($_POST['category_id']);
            $percentage = floatval($_POST['percentage']);
            $name = trim($_POST['name']);
            
            $stmt = $conn->prepare("UPDATE budget_allocations SET category_id = ?, name = ?, percentage = ? WHERE id = ? AND user_id = ?");
            $stmt->bind_param("isdii", $categoryId, $name, $percentage, $id, $userId);
            $stmt->execute();
            
            header('Location: budget-allocation.php?msg=updated');
            exit;
        }
        
        if ($_POST['action'] === 'delete') {
            $id = intval($_POST['id']);
            $conn->query("DELETE FROM budget_allocations WHERE id = $id AND user_id = $userId");
            
            header('Location: budget-allocation.php?msg=deleted');
            exit;
        }
    }
}

// Lấy tổng thu nhập tháng này
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(amount), 0) as monthly_income
    FROM transactions
    WHERE user_id = ? AND type = 'income' 
    AND MONTH(transaction_date) = MONTH(CURDATE())
    AND YEAR(transaction_date) = YEAR(CURDATE())
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$income = $stmt->get_result()->fetch_assoc();
$monthlyIncome = $income['monthly_income'];

// Lấy danh sách phân bổ với số tiền đã chi
$allocations = $conn->query("
    SELECT 
        ba.*,
        c.name as category_name, 
        c.icon as category_icon, 
        c.color as category_color,
        COALESCE(SUM(t.amount), 0) as spent_amount
    FROM budget_allocations ba
    LEFT JOIN categories c ON ba.category_id = c.id
    LEFT JOIN transactions t ON t.category_id = ba.category_id 
        AND t.user_id = ba.user_id 
        AND t.type = 'expense'
        AND MONTH(t.transaction_date) = MONTH(CURDATE())
        AND YEAR(t.transaction_date) = YEAR(CURDATE())
    WHERE ba.user_id = $userId
    GROUP BY ba.id, ba.user_id, ba.category_id, ba.name, ba.percentage, ba.created_at, ba.updated_at,
             c.name, c.icon, c.color
    ORDER BY ba.percentage DESC
");

// Tính tổng phần trăm và số tiền
$totalPercentage = 0;
$totalAllocated = 0;
$totalSpent = 0;
$allocationsData = [];
if ($allocations) {
    while ($row = $allocations->fetch_assoc()) {
        $totalPercentage += $row['percentage'];
        $allocatedAmount = $monthlyIncome * ($row['percentage'] / 100);
        $totalAllocated += $allocatedAmount;
        $totalSpent += $row['spent_amount'];
        $allocationsData[] = $row;
    }
}

// Lấy danh mục chi tiêu
$categories = $conn->query("SELECT * FROM categories WHERE (user_id = $userId OR is_system = 1) AND type = 'expense' ORDER BY name");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Phân bổ Thu nhập - Finora</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        
        * { margin: 0; padding: 0; box-sizing: border-box; }

        :root {
            --primary: #2563EB; --success: #10B981; --danger: #EF4444; --warning: #F59E0B;
            --purple: #7C3AED; --orange: #F97316; --bg: #F8FAFC; --card: #FFFFFF;
            --text: #0F172A; --text-light: #475569; --text-lighter: #94A3B8; --border: #E2E8F0;
            --sidebar-width: 260px;
        }

        body { font-family: 'Plus Jakarta Sans', sans-serif; background: var(--bg); color: var(--text); display: flex; min-height: 100vh; }

        /* Sidebar - Copy từ dashboard */
        .sidebar { width: var(--sidebar-width); background: var(--card); border-right: 1px solid var(--border); padding: 24px 0; position: fixed; height: 100vh; overflow-y: auto; z-index: 100; }
        .logo { display: flex; align-items: center; gap: 10px; font-size: 20px; font-weight: 800; padding: 0 24px; margin-bottom: 32px; }
        .logo-icon { width: 36px; height: 36px; background: linear-gradient(135deg, var(--primary), var(--purple)); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; }
        .sidebar-menu { list-style: none; padding: 0 12px; }
        .menu-item { margin-bottom: 4px; }
        .menu-link { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-radius: 10px; color: var(--text-light); text-decoration: none; font-weight: 500; font-size: 14px; transition: all 0.2s; }
        .menu-link:hover { background: var(--bg); color: var(--primary); }
        .menu-link.active { background: linear-gradient(135deg, #EFF6FF, #F0F9FF); color: var(--primary); font-weight: 600; }
        .menu-icon { width: 20px; text-align: center; font-size: 16px; }
        .user-card { margin: 24px 12px 0; padding: 16px; background: linear-gradient(135deg, var(--primary), var(--purple)); border-radius: 12px; color: white; }
        .user-avatar { width: 48px; height: 48px; background: rgba(255, 255, 255, 0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 18px; margin-bottom: 12px; }
        .user-name-card { font-weight: 700; font-size: 15px; margin-bottom: 4px; }

        /* Main */
        .main-content { margin-left: var(--sidebar-width); flex: 1; padding: 32px; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .page-title { font-size: 28px; font-weight: 800; }
        .page-subtitle { color: var(--text-light); font-size: 14px; margin-top: 4px; }
        .btn { padding: 10px 20px; border-radius: 10px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; border: none; cursor: pointer; font-size: 14px; font-family: inherit; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: #1d4ed8; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3); }
        .btn-ghost { background: var(--card); color: var(--text); border: 1px solid var(--border); }
        .btn-sm { padding: 8px 16px; font-size: 13px; }

        /* Income Card */
        .income-card { background: linear-gradient(135deg, var(--success), #059669); border-radius: 20px; padding: 32px; color: white; margin-bottom: 24px; position: relative; overflow: hidden; }
        .income-card::before { content: ''; position: absolute; width: 300px; height: 300px; background: rgba(255, 255, 255, 0.1); border-radius: 50%; top: -150px; right: -100px; }
        .income-label { font-size: 14px; opacity: 0.9; margin-bottom: 8px; }
        .income-amount { font-size: 42px; font-weight: 800; position: relative; z-index: 1; }
        .income-note { font-size: 12px; opacity: 0.85; margin-top: 8px; }

        /* Stats */
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 24px; }
        .stat-card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 20px; text-align: center; }
        .stat-value { font-size: 28px; font-weight: 800; margin-bottom: 6px; }
        .stat-label { font-size: 13px; color: var(--text-light); }

        /* Allocation List */
        .allocation-section { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 24px; margin-bottom: 24px; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .section-title { font-size: 18px; font-weight: 700; }
        .allocation-list { display: flex; flex-direction: column; gap: 12px; }
        .allocation-item { display: flex; align-items: center; gap: 16px; padding: 20px; background: var(--bg); border-radius: 12px; transition: all 0.2s; }
        .allocation-item:hover { background: #EFF6FF; }
        .allocation-icon { width: 56px; height: 56px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 28px; border: 2px solid; }
        .allocation-info { flex: 1; }
        .allocation-name { font-weight: 600; font-size: 15px; margin-bottom: 6px; }
        .allocation-category { font-size: 12px; color: var(--text-lighter); }
        .allocation-bar { height: 8px; background: var(--border); border-radius: 10px; overflow: hidden; margin-top: 8px; }
        .allocation-fill { height: 100%; border-radius: 10px; transition: width 0.6s; }
        .allocation-percentage { font-size: 24px; font-weight: 800; color: var(--primary); margin-right: 12px; }
        .allocation-amount { font-size: 16px; font-weight: 700; color: var(--text-light); margin-right: 12px; }
        .allocation-actions { display: flex; gap: 6px; }
        .btn-icon { width: 36px; height: 36px; border-radius: 8px; border: 1px solid var(--border); background: white; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
        .btn-icon:hover { background: var(--bg); border-color: var(--primary); color: var(--primary); }

        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal.show { display: flex; }
        .modal-content { background: var(--card); border-radius: 24px; padding: 32px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .modal-title { font-size: 24px; font-weight: 800; }
        .modal-close { width: 36px; height: 36px; border-radius: 8px; border: none; background: var(--bg); cursor: pointer; font-size: 20px; color: var(--text-light); }
        .form-group { margin-bottom: 18px; }
        .form-label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px; color: var(--text); }
        .form-input, .form-select { width: 100%; padding: 12px 16px; border: 1.5px solid var(--border); border-radius: 10px; font-size: 14px; font-family: inherit; transition: all 0.2s; }
        .form-input:focus, .form-select:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }

        .alert { padding: 16px 20px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; font-size: 14px; }
        .alert-success { background: #D1FAE5; color: #065F46; border: 1px solid #6EE7B7; }
        .alert-warning { background: #FEF3C7; color: #92400E; border: 1px solid #FCD34D; }
        .alert-danger { background: #FEE2E2; color: #991B1B; border: 1px solid #FCA5A5; }

        .empty-state { text-align: center; padding: 60px 20px; color: var(--text-light); }
        .empty-icon { font-size: 64px; opacity: 0.3; margin-bottom: 16px; }

        @media (max-width: 768px) {
            .sidebar { width: 0; overflow: hidden; }
            .main-content { margin-left: 0; padding: 20px; }
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="logo"><div class="logo-icon">💰</div><span>Fi<span style="color: var(--primary)">no</span>ra</span></div>
        <ul class="sidebar-menu">
            <li class="menu-item"><a href="dashboard.php" class="menu-link"><i class="menu-icon fas fa-chart-line"></i><span>Dashboard</span></a></li>
            <li class="menu-item"><a href="accounts.php" class="menu-link"><i class="menu-icon fas fa-wallet"></i><span>Tài khoản</span></a></li>
            <li class="menu-item"><a href="transactions.php" class="menu-link"><i class="menu-icon fas fa-exchange-alt"></i><span>Giao dịch</span></a></li>
            <li class="menu-item"><a href="budgets.php" class="menu-link"><i class="menu-icon fas fa-chart-pie"></i><span>Ngân sách</span></a></li>
            <li class="menu-item"><a href="budget-allocation.php" class="menu-link active"><i class="menu-icon fas fa-percentage"></i><span>Phân bổ Thu nhập</span></a></li>
            <li class="menu-item"><a href="reports.php" class="menu-link"><i class="menu-icon fas fa-file-alt"></i><span>Báo cáo</span></a></li>
            <li class="menu-item"><a href="cards.php" class="menu-link"><i class="menu-icon fas fa-credit-card"></i><span>Thẻ</span></a></li>
            <li class="menu-item"><a href="settings.php" class="menu-link"><i class="menu-icon fas fa-cog"></i><span>Cài đặt</span></a></li>
        </ul>
        <div class="user-card">
            <div class="user-avatar"><?php 
                $names = explode(' ', $userName);
                echo strtoupper(substr($names[0], 0, 1));
                if (count($names) > 1) echo strtoupper(substr($names[count($names) - 1], 0, 1));
            ?></div>
            <div class="user-name-card"><?= htmlspecialchars($userName) ?></div>
            <div style="font-size: 12px;"><a href="logout.php" style="color: rgba(255,255,255,0.9); text-decoration: none;"><i class="fas fa-sign-out-alt"></i> Đăng xuất</a></div>
        </div>
    </aside>

    <main class="main-content">
        <div class="top-bar">
            <div>
                <h1 class="page-title">📊 Phân bổ Thu nhập</h1>
                <p class="page-subtitle">Quản lý và phân chia thu nhập theo % cho từng khoản chi tiêu</p>
            </div>
            <button class="btn btn-primary" onclick="openAddModal()"><i class="fas fa-plus"></i> Thêm phân bổ</button>
        </div>

        <?php if (isset($_GET['msg'])): ?>
            <?php if ($_GET['msg'] === 'added'): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> Đã thêm phân bổ thành công!</div>
            <?php elseif ($_GET['msg'] === 'updated'): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> Đã cập nhật phân bổ thành công!</div>
            <?php elseif ($_GET['msg'] === 'deleted'): ?>
                <div class="alert alert-success"><i class="fas fa-check-circle"></i> Đã xóa phân bổ thành công!</div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Income Card -->
        <div class="income-card">
            <div class="income-label">💰 Thu nhập tháng này</div>
            <div class="income-amount"><?= number_format($monthlyIncome, 0, ',', '.') ?>đ</div>
            <div class="income-note">Tháng <?= date('m/Y') ?></div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value" style="color: var(--primary);"><?= number_format($totalAllocated, 0, ',', '.') ?>đ</div>
                <div class="stat-label">Tổng phân bổ (<?= number_format($totalPercentage, 1) ?>%)</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: var(--danger);"><?= number_format($totalSpent, 0, ',', '.') ?>đ</div>
                <div class="stat-label">Đã chi tiêu</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: var(--success);"><?= number_format($totalAllocated - $totalSpent, 0, ',', '.') ?>đ</div>
                <div class="stat-label">Còn lại</div>
            </div>
        </div>

        <?php if ($totalPercentage > 100): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i>
                Cảnh báo: Tổng phần trăm phân bổ vượt quá 100%! Vui lòng điều chỉnh lại.
            </div>
        <?php elseif ($totalPercentage < 100 && count($allocationsData) > 0): ?>
            <div class="alert alert-warning">
                <i class="fas fa-info-circle"></i>
                Bạn còn <?= number_format(100 - $totalPercentage, 1) ?>% thu nhập chưa phân bổ.
            </div>
        <?php endif; ?>

        <!-- Allocation List -->
        <div class="allocation-section">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-list"></i> Danh sách phân bổ</h2>
            </div>

            <?php if (count($allocationsData) > 0): ?>
                <div class="allocation-list">
                    <?php foreach ($allocationsData as $allocation): 
                        $allocatedAmount = $monthlyIncome * ($allocation['percentage'] / 100);
                        $spentAmount = $allocation['spent_amount'];
                        $remainingAmount = $allocatedAmount - $spentAmount;
                        $spentPercentage = $allocatedAmount > 0 ? ($spentAmount / $allocatedAmount * 100) : 0;
                        $spentPercentage = min($spentPercentage, 100); // Cap at 100%
                    ?>
                        <div class="allocation-item">
                            <div class="allocation-icon" style="background: <?= $allocation['category_color'] ?>20; border-color: <?= $allocation['category_color'] ?>;">
                                <?= htmlspecialchars($allocation['category_icon']) ?>
                            </div>
                            <div class="allocation-info">
                                <div class="allocation-name"><?= htmlspecialchars($allocation['name']) ?></div>
                                <div class="allocation-category">
                                    <i class="fas fa-tag"></i> <?= htmlspecialchars($allocation['category_name']) ?>
                                </div>
                                <div style="font-size: 12px; color: var(--text-lighter); margin-top: 4px;">
                                    <span>Đã chi: <strong style="color: var(--danger);"><?= number_format($spentAmount, 0, ',', '.') ?>đ</strong></span>
                                    <span style="margin-left: 12px;">Còn lại: <strong style="color: var(--success);"><?= number_format($remainingAmount, 0, ',', '.') ?>đ</strong></span>
                                </div>
                                <div class="allocation-bar">
                                    <div class="allocation-fill" style="width: <?= $spentPercentage ?>%; background: <?= $spentPercentage >= 100 ? 'var(--danger)' : ($spentPercentage >= 80 ? 'var(--warning)' : $allocation['category_color']) ?>;"></div>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div class="allocation-percentage" style="color: <?= $spentPercentage >= 100 ? 'var(--danger)' : ($spentPercentage >= 80 ? 'var(--warning)' : 'var(--primary)') ?>;">
                                    <?= number_format($spentPercentage, 1) ?>%
                                </div>
                                <div style="font-size: 13px; color: var(--text-light); margin-bottom: 8px;">
                                    <?= number_format($allocation['percentage'], 0) ?>% thu nhập
                                </div>
                                <div class="allocation-amount"><?= number_format($allocatedAmount, 0, ',', '.') ?>đ</div>
                            </div>
                            <div class="allocation-actions">
                                <button class="btn-icon" onclick="openEditModal(<?= htmlspecialchars(json_encode($allocation)) ?>)" title="Sửa">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn-icon" onclick="deleteAllocation(<?= $allocation['id'] ?>)" title="Xóa" style="color: var(--danger);">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">📊</div>
                    <h3>Chưa có phân bổ nào</h3>
                    <p>Thêm phân bổ đầu tiên để bắt đầu quản lý thu nhập</p>
                    <button class="btn btn-primary" style="margin-top: 16px;" onclick="openAddModal()">
                        <i class="fas fa-plus"></i> Thêm phân bổ
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal Add/Edit -->
    <div class="modal" id="allocationModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Thêm phân bổ mới</h3>
                <button class="modal-close" onclick="closeModal()">×</button>
            </div>

            <form method="POST" action="" id="allocationForm">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="allocationId">

                <div class="form-group">
                    <label class="form-label">Tên khoản chi tiêu *</label>
                    <input type="text" name="name" id="allocationName" class="form-input" placeholder="VD: Tiền ăn, Tiền nhà..." required>
                </div>

                <div class="form-group">
                    <label class="form-label">Danh mục *</label>
                    <select name="category_id" id="allocationCategory" class="form-select" required>
                        <option value="">-- Chọn danh mục --</option>
                        <?php while ($cat = $categories->fetch_assoc()): ?>
                            <option value="<?= $cat['id'] ?>">
                                <?= htmlspecialchars($cat['icon']) ?> <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Phần trăm (%) *</label>
                    <input type="number" name="percentage" id="allocationPercentage" class="form-input" 
                           placeholder="VD: 30" min="0" max="100" step="0.1" required>
                    <div style="font-size: 12px; color: var(--text-lighter); margin-top: 6px;">
                        <i class="fas fa-info-circle"></i> Số tiền tương ứng: <span id="estimatedAmount">0đ</span>
                    </div>
                </div>

                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="button" class="btn btn-ghost" onclick="closeModal()" style="flex: 1;">Hủy</button>
                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                        <i class="fas fa-save"></i> Lưu
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const monthlyIncome = <?= $monthlyIncome ?>;

        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Thêm phân bổ mới';
            document.getElementById('formAction').value = 'add';
            document.getElementById('allocationForm').reset();
            document.getElementById('allocationModal').classList.add('show');
        }

        function openEditModal(allocation) {
            document.getElementById('modalTitle').textContent = 'Sửa phân bổ';
            document.getElementById('formAction').value = 'update';
            document.getElementById('allocationId').value = allocation.id;
            document.getElementById('allocationName').value = allocation.name;
            document.getElementById('allocationCategory').value = allocation.category_id;
            document.getElementById('allocationPercentage').value = allocation.percentage;
            updateEstimatedAmount();
            document.getElementById('allocationModal').classList.add('show');
        }

        function closeModal() {
            document.getElementById('allocationModal').classList.remove('show');
        }

        function deleteAllocation(id) {
            if (confirm('Bạn có chắc muốn xóa phân bổ này?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'id';
                idInput.value = id;
                
                form.appendChild(actionInput);
                form.appendChild(idInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        function updateEstimatedAmount() {
            const percentage = parseFloat(document.getElementById('allocationPercentage').value) || 0;
            const amount = monthlyIncome * (percentage / 100);
            document.getElementById('estimatedAmount').textContent = 
                new Intl.NumberFormat('vi-VN').format(Math.round(amount)) + 'đ';
        }

        document.getElementById('allocationPercentage').addEventListener('input', updateEstimatedAmount);

        document.getElementById('allocationModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
    </script>
</body>
</html>
