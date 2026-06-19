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

// Xử lý thêm giao dịch
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add') {
        $walletId = $_POST['wallet_id'];
        $categoryId = $_POST['category_id'];
        $type = $_POST['type'];
        $amount = $_POST['amount'];
        $description = $_POST['description'];
        $transactionDate = $_POST['transaction_date'];
        $note = $_POST['note'] ?? null;
        
        // Insert transaction
        $stmt = $conn->prepare("INSERT INTO transactions (user_id, wallet_id, category_id, type, amount, description, note, transaction_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiisdsss", $userId, $walletId, $categoryId, $type, $amount, $description, $note, $transactionDate);
        $stmt->execute();
        
        // Update wallet balance
        if ($type === 'income') {
            $conn->query("UPDATE wallets SET balance = balance + $amount WHERE id = $walletId");
        } else {
            $conn->query("UPDATE wallets SET balance = balance - $amount WHERE id = $walletId");
        }
        
        header('Location: transactions.php?success=add');
        exit;
    }
    
    if ($_POST['action'] === 'delete') {
        $transactionId = $_POST['transaction_id'];
        
        // Get transaction info to update wallet
        $stmt = $conn->prepare("SELECT wallet_id, type, amount FROM transactions WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $transactionId, $userId);
        $stmt->execute();
        $trans = $stmt->get_result()->fetch_assoc();
        
        if ($trans) {
            // Delete transaction
            $conn->query("DELETE FROM transactions WHERE id = $transactionId AND user_id = $userId");
            
            // Reverse wallet balance
            if ($trans['type'] === 'income') {
                $conn->query("UPDATE wallets SET balance = balance - {$trans['amount']} WHERE id = {$trans['wallet_id']}");
            } else {
                $conn->query("UPDATE wallets SET balance = balance + {$trans['amount']} WHERE id = {$trans['wallet_id']}");
            }
        }
        
        header('Location: transactions.php?success=delete');
        exit;
    }
}

// Filters
$typeFilter = $_GET['type'] ?? 'all';
$categoryFilter = $_GET['category'] ?? 'all';
$walletFilter = $_GET['wallet'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

// Build query
$whereConditions = ["t.user_id = $userId"];

if ($typeFilter !== 'all') {
    $whereConditions[] = "t.type = '$typeFilter'";
}
if ($categoryFilter !== 'all') {
    $whereConditions[] = "t.category_id = $categoryFilter";
}
if ($walletFilter !== 'all') {
    $whereConditions[] = "t.wallet_id = $walletFilter";
}
if (!empty($searchQuery)) {
    $searchQuery = $conn->real_escape_string($searchQuery);
    $whereConditions[] = "(t.description LIKE '%$searchQuery%' OR t.note LIKE '%$searchQuery%')";
}
if ($dateFrom) {
    $whereConditions[] = "t.transaction_date >= '$dateFrom'";
}
if ($dateTo) {
    $whereConditions[] = "t.transaction_date <= '$dateTo'";
}

$whereClause = implode(' AND ', $whereConditions);

// Get transactions
$query = "
    SELECT t.*, c.name as category_name, c.icon as category_icon, c.color as category_color,
           w.name as wallet_name, w.icon as wallet_icon
    FROM transactions t
    LEFT JOIN categories c ON t.category_id = c.id
    LEFT JOIN wallets w ON t.wallet_id = w.id
    WHERE $whereClause
    ORDER BY t.transaction_date DESC, t.created_at DESC
    LIMIT 100
";
$transactions = $conn->query($query);

// Get summary
$summaryQuery = "
    SELECT 
        COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as total_income,
        COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as total_expense,
        COUNT(*) as total_count
    FROM transactions t
    WHERE $whereClause
";
$summary = $conn->query($summaryQuery)->fetch_assoc();

// Get categories for filter
$categories = $conn->query("SELECT * FROM categories WHERE (user_id = $userId OR is_system = 1) ORDER BY type, name");

// Get wallets for filter
$wallets = $conn->query("SELECT * FROM wallets WHERE user_id = $userId AND is_active = 1 ORDER BY name");

// Get categories for add modal
$categoriesForAdd = $conn->query("SELECT * FROM categories WHERE (user_id = $userId OR is_system = 1) ORDER BY type, name");

// Get wallets for add modal
$walletsForAdd = $conn->query("SELECT * FROM wallets WHERE user_id = $userId AND is_active = 1 ORDER BY name");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giao dịch - Finora</title>
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

        /* Summary Cards */
        .summary-cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 24px; }
        .summary-card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 24px; display: flex; align-items: center; gap: 16px; }
        .summary-icon { width: 56px; height: 56px; border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 28px; }
        .summary-info { flex: 1; }
        .summary-label { font-size: 13px; color: var(--text-light); margin-bottom: 6px; }
        .summary-amount { font-size: 26px; font-weight: 800; }

        /* Filters */
        .filters-section { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 20px; margin-bottom: 24px; }
        .filters-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; }
        .filter-group { display: flex; flex-direction: column; gap: 6px; }
        .filter-label { font-size: 12px; font-weight: 600; color: var(--text-light); }
        .filter-input, .filter-select { padding: 10px 12px; border: 1.5px solid var(--border); border-radius: 8px; font-size: 13px; font-family: inherit; transition: all 0.2s; }
        .filter-input:focus, .filter-select:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
        .filter-actions { display: flex; gap: 8px; align-items: flex-end; }
        .btn-sm { padding: 10px 16px; font-size: 13px; }
        .btn-ghost { background: var(--bg); color: var(--text); border: 1px solid var(--border); }

        /* Transactions List */
        .transactions-section { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 24px; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .section-title { font-size: 18px; font-weight: 700; }
        .transaction-list { display: flex; flex-direction: column; gap: 10px; }
        .transaction-item { display: flex; align-items: center; gap: 12px; padding: 16px; background: var(--bg); border-radius: 12px; transition: all 0.2s; }
        .transaction-item:hover { background: #EFF6FF; transform: translateX(4px); }
        .transaction-icon { width: 52px; height: 52px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px; border: 2px solid; }
        .transaction-info { flex: 1; }
        .transaction-title { font-weight: 600; font-size: 15px; margin-bottom: 4px; }
        .transaction-meta { font-size: 12px; color: var(--text-lighter); display: flex; align-items: center; gap: 12px; }
        .transaction-amount { font-size: 18px; font-weight: 700; margin-right: 12px; }
        .transaction-actions { display: flex; gap: 6px; }
        .btn-icon { width: 36px; height: 36px; border-radius: 8px; border: 1px solid var(--border); background: white; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
        .btn-icon:hover { background: var(--bg); border-color: var(--primary); color: var(--primary); }

        /* Modal */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; align-items: center; justify-content: center; }
        .modal.show { display: flex; }
        .modal-content { background: var(--card); border-radius: 24px; padding: 32px; max-width: 540px; width: 90%; max-height: 90vh; overflow-y: auto; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
        .modal-title { font-size: 24px; font-weight: 800; }
        .modal-close { width: 36px; height: 36px; border-radius: 8px; border: none; background: var(--bg); cursor: pointer; font-size: 20px; color: var(--text-light); }
        .form-group { margin-bottom: 18px; }
        .form-label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px; color: var(--text); }
        .form-input, .form-select, .form-textarea { width: 100%; padding: 12px 16px; border: 1.5px solid var(--border); border-radius: 10px; font-size: 14px; font-family: inherit; transition: all 0.2s; }
        .form-input:focus, .form-select:focus, .form-textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
        .form-textarea { resize: vertical; min-height: 80px; }
        .type-selector { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .type-option { padding: 16px; border: 2px solid var(--border); border-radius: 12px; cursor: pointer; text-align: center; transition: all 0.2s; }
        .type-option:hover { border-color: var(--primary); background: #EFF6FF; }
        .type-option.selected { border-color: var(--primary); background: #EFF6FF; }
        .type-option-icon { font-size: 32px; margin-bottom: 8px; }
        .type-option-label { font-size: 14px; font-weight: 600; }
        .empty-state { text-align: center; padding: 60px 20px; color: var(--text-light); }
        .empty-icon { font-size: 64px; opacity: 0.3; margin-bottom: 16px; }

        @media (max-width: 768px) {
            .sidebar { width: 0; overflow: hidden; }
            .main-content { margin-left: 0; padding: 20px; }
            .summary-cards { grid-template-columns: 1fr; }
            .filters-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="logo"><div class="logo-icon">💰</div><span>Fi<span style="color: var(--primary)">no</span>ra</span></div>
        <ul class="sidebar-menu">
            <li class="menu-item"><a href="dashboard.php" class="menu-link"><i class="menu-icon fas fa-chart-line"></i><span>Dashboard</span></a></li>
            <li class="menu-item"><a href="accounts.php" class="menu-link"><i class="menu-icon fas fa-wallet"></i><span>Tài khoản</span></a></li>
            <li class="menu-item"><a href="transactions.php" class="menu-link active"><i class="menu-icon fas fa-exchange-alt"></i><span>Giao dịch</span></a></li>
            <li class="menu-item"><a href="budgets.php" class="menu-link"><i class="menu-icon fas fa-chart-pie"></i><span>Ngân sách</span></a></li>
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
                <h1 class="page-title">💸 Giao dịch</h1>
                <p class="page-subtitle">Quản lý tất cả thu chi của bạn</p>
            </div>
            <button class="btn btn-primary" onclick="openAddModal()"><i class="fas fa-plus"></i> Thêm giao dịch</button>
        </div>

        <!-- Summary -->
        <div class="summary-cards">
            <div class="summary-card">
                <div class="summary-icon" style="background: #D1FAE5; color: var(--success);"><i class="fas fa-arrow-down"></i></div>
                <div class="summary-info">
                    <div class="summary-label">Tổng thu nhập</div>
                    <div class="summary-amount" style="color: var(--success);">+<?= number_format($summary['total_income'], 0, ',', '.') ?>đ</div>
                </div>
            </div>
            <div class="summary-card">
                <div class="summary-icon" style="background: #FEE2E2; color: var(--danger);"><i class="fas fa-arrow-up"></i></div>
                <div class="summary-info">
                    <div class="summary-label">Tổng chi tiêu</div>
                    <div class="summary-amount" style="color: var(--danger);">-<?= number_format($summary['total_expense'], 0, ',', '.') ?>đ</div>
                </div>
            </div>
            <div class="summary-card">
                <div class="summary-icon" style="background: #DBEAFE; color: var(--primary);"><i class="fas fa-receipt"></i></div>
                <div class="summary-info">
                    <div class="summary-label">Tổng giao dịch</div>
                    <div class="summary-amount" style="color: var(--primary);"><?= number_format($summary['total_count']) ?></div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <form method="GET" action="" class="filters-section">
            <div class="filters-grid">
                <div class="filter-group">
                    <label class="filter-label">Tìm kiếm</label>
                    <input type="text" name="search" class="filter-input" placeholder="Tìm theo mô tả..." value="<?= htmlspecialchars($searchQuery) ?>">
                </div>
                <div class="filter-group">
                    <label class="filter-label">Loại</label>
                    <select name="type" class="filter-select">
                        <option value="all" <?= $typeFilter === 'all' ? 'selected' : '' ?>>Tất cả</option>
                        <option value="income" <?= $typeFilter === 'income' ? 'selected' : '' ?>>Thu nhập</option>
                        <option value="expense" <?= $typeFilter === 'expense' ? 'selected' : '' ?>>Chi tiêu</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label">Danh mục</label>
                    <select name="category" class="filter-select">
                        <option value="all">Tất cả</option>
                        <?php while ($cat = $categories->fetch_assoc()): ?>
                            <option value="<?= $cat['id'] ?>" <?= $categoryFilter == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['icon']) ?> <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label">Ví</label>
                    <select name="wallet" class="filter-select">
                        <option value="all">Tất cả</option>
                        <?php while ($wallet = $wallets->fetch_assoc()): ?>
                            <option value="<?= $wallet['id'] ?>" <?= $walletFilter == $wallet['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($wallet['icon']) ?> <?= htmlspecialchars($wallet['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label class="filter-label">Từ ngày</label>
                    <input type="date" name="date_from" class="filter-input" value="<?= $dateFrom ?>">
                </div>
                <div class="filter-group">
                    <label class="filter-label">Đến ngày</label>
                    <input type="date" name="date_to" class="filter-input" value="<?= $dateTo ?>">
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Lọc</button>
                    <a href="transactions.php" class="btn btn-ghost btn-sm"><i class="fas fa-redo"></i></a>
                </div>
            </div>
        </form>

        <!-- Transactions -->
        <div class="transactions-section">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-list"></i> Danh sách giao dịch</h2>
            </div>

            <?php if ($transactions->num_rows > 0): ?>
                <div class="transaction-list">
                    <?php while ($trans = $transactions->fetch_assoc()): ?>
                        <div class="transaction-item">
                            <div class="transaction-icon" style="background: <?= $trans['category_color'] ?>20; border-color: <?= $trans['category_color'] ?>;">
                                <?= htmlspecialchars($trans['category_icon']) ?>
                            </div>
                            <div class="transaction-info">
                                <div class="transaction-title"><?= htmlspecialchars($trans['description']) ?></div>
                                <div class="transaction-meta">
                                    <span><i class="fas fa-tag"></i> <?= htmlspecialchars($trans['category_name']) ?></span>
                                    <span><i class="fas fa-wallet"></i> <?= htmlspecialchars($trans['wallet_name']) ?></span>
                                    <span><i class="fas fa-calendar"></i> <?= date('d/m/Y', strtotime($trans['transaction_date'])) ?></span>
                                </div>
                            </div>
                            <div class="transaction-amount" style="color: <?= $trans['type'] === 'income' ? 'var(--success)' : 'var(--danger)' ?>;">
                                <?= $trans['type'] === 'income' ? '+' : '-' ?><?= number_format($trans['amount'], 0, ',', '.') ?>đ
                            </div>
                            <div class="transaction-actions">
                                <button class="btn-icon" onclick="viewTransaction(<?= $trans['id'] ?>)" title="Xem"><i class="fas fa-eye"></i></button>
                                <button class="btn-icon" onclick="editTransaction(<?= $trans['id'] ?>)" title="Sửa"><i class="fas fa-edit"></i></button>
                                <button class="btn-icon" onclick="deleteTransaction(<?= $trans['id'] ?>)" title="Xóa" style="color: var(--danger);"><i class="fas fa-trash"></i></button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">📭</div>
                    <h3>Không có giao dịch nào</h3>
                    <p>Thử thay đổi bộ lọc hoặc thêm giao dịch mới</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal Add Transaction -->
    <div class="modal" id="addModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Thêm giao dịch mới</h3>
                <button class="modal-close" onclick="closeAddModal()">×</button>
            </div>

            <form method="POST" action="">
                <input type="hidden" name="action" value="add">

                <div class="form-group">
                    <label class="form-label">Loại giao dịch *</label>
                    <div class="type-selector">
                        <div class="type-option selected" data-type="expense" onclick="selectType(this)">
                            <div class="type-option-icon">📉</div>
                            <div class="type-option-label">Chi tiêu</div>
                        </div>
                        <div class="type-option" data-type="income" onclick="selectType(this)">
                            <div class="type-option-icon">📈</div>
                            <div class="type-option-label">Thu nhập</div>
                        </div>
                    </div>
                    <input type="hidden" name="type" id="selectedType" value="expense">
                </div>

                <div class="form-group">
                    <label class="form-label">Danh mục *</label>
                    <select name="category_id" class="form-select" id="categorySelect" required>
                        <option value="">-- Chọn danh mục --</option>
                        <?php while ($cat = $categoriesForAdd->fetch_assoc()): ?>
                            <option value="<?= $cat['id'] ?>" data-type="<?= $cat['type'] ?>">
                                <?= htmlspecialchars($cat['icon']) ?> <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Ví *</label>
                    <select name="wallet_id" class="form-select" required>
                        <option value="">-- Chọn ví --</option>
                        <?php while ($wallet = $walletsForAdd->fetch_assoc()): ?>
                            <option value="<?= $wallet['id'] ?>">
                                <?= htmlspecialchars($wallet['icon']) ?> <?= htmlspecialchars($wallet['name']) ?> 
                                (<?= number_format($wallet['balance'], 0, ',', '.') ?>đ)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Số tiền (VNĐ) *</label>
                    <input type="number" name="amount" class="form-input" placeholder="50000" min="0" step="1000" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Mô tả *</label>
                    <input type="text" name="description" class="form-input" placeholder="VD: Mua cà phê" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Ghi chú</label>
                    <textarea name="note" class="form-textarea" placeholder="Thêm ghi chú (tùy chọn)"></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Ngày giao dịch *</label>
                    <input type="date" name="transaction_date" class="form-input" value="<?= date('Y-m-d') ?>" required>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
                    <i class="fas fa-save"></i> Thêm giao dịch
                </button>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() { document.getElementById('addModal').classList.add('show'); filterCategories(); }
        function closeAddModal() { document.getElementById('addModal').classList.remove('show'); }
        
        document.getElementById('addModal').addEventListener('click', function(e) {
            if (e.target === this) closeAddModal();
        });

        function selectType(element) {
            document.querySelectorAll('.type-option').forEach(opt => opt.classList.remove('selected'));
            element.classList.add('selected');
            document.getElementById('selectedType').value = element.dataset.type;
            filterCategories();
        }

        function filterCategories() {
            const selectedType = document.getElementById('selectedType').value;
            const categorySelect = document.getElementById('categorySelect');
            const options = categorySelect.querySelectorAll('option');
            
            options.forEach(option => {
                if (option.value === '') {
                    option.style.display = 'block';
                } else if (option.dataset.type === selectedType) {
                    option.style.display = 'block';
                } else {
                    option.style.display = 'none';
                }
            });
            
            // Reset selection
            categorySelect.value = '';
        }

        function viewTransaction(id) {
            alert('Chức năng xem chi tiết đang được phát triển! Transaction ID: ' + id);
        }

        function editTransaction(id) {
            alert('Chức năng sửa giao dịch đang được phát triển! Transaction ID: ' + id);
        }

        function deleteTransaction(id) {
            if (confirm('Bạn có chắc muốn xóa giao dịch này?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'delete';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'transaction_id';
                idInput.value = id;
                
                form.appendChild(actionInput);
                form.appendChild(idInput);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
