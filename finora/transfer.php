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

$error = '';
$success = '';

// Xử lý chuyển tiền
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'transfer') {
    $fromWalletId = intval($_POST['from_wallet_id']);
    $toWalletId = intval($_POST['to_wallet_id']);
    $amount = floatval($_POST['amount']);
    $description = trim($_POST['description']);
    $transferDate = $_POST['transfer_date'];
    $note = trim($_POST['note'] ?? '');
    
    // Validation
    if ($fromWalletId === $toWalletId) {
        $error = 'Không thể chuyển tiền vào cùng một tài khoản!';
    } elseif ($amount <= 0) {
        $error = 'Số tiền phải lớn hơn 0!';
    } else {
        // Kiểm tra số dư
        $checkBalance = $conn->query("SELECT balance, name FROM wallets WHERE id = $fromWalletId AND user_id = $userId");
        $fromWallet = $checkBalance->fetch_assoc();
        
        if (!$fromWallet) {
            $error = 'Tài khoản nguồn không tồn tại!';
        } elseif ($fromWallet['balance'] < $amount) {
            $error = 'Số dư không đủ! Số dư hiện tại: ' . number_format($fromWallet['balance'], 0, ',', '.') . 'đ';
        } else {
            // Bắt đầu transaction
            $conn->begin_transaction();
            
            try {
                // Trừ tiền từ tài khoản nguồn
                $conn->query("UPDATE wallets SET balance = balance - $amount WHERE id = $fromWalletId");
                
                // Cộng tiền vào tài khoản đích
                $conn->query("UPDATE wallets SET balance = balance + $amount WHERE id = $toWalletId");
                
                // Tạo giao dịch chuyển tiền (expense cho tài khoản nguồn)
                $stmt = $conn->prepare("INSERT INTO transactions (user_id, wallet_id, category_id, type, amount, description, note, transaction_date) VALUES (?, ?, 2, 'expense', ?, ?, ?, ?)");
                $stmt->bind_param("iidsss", $userId, $fromWalletId, $amount, $description, $note, $transferDate);
                $stmt->execute();
                
                // Tạo giao dịch nhận tiền (income cho tài khoản đích)
                $stmt = $conn->prepare("INSERT INTO transactions (user_id, wallet_id, category_id, type, amount, description, note, transaction_date) VALUES (?, ?, 17, 'income', ?, ?, ?, ?)");
                $stmt->bind_param("iidsss", $userId, $toWalletId, $amount, $description, $note, $transferDate);
                $stmt->execute();
                
                $conn->commit();
                $success = 'Chuyển tiền thành công!';
                
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Có lỗi xảy ra: ' . $e->getMessage();
            }
        }
    }
}

// Lấy danh sách tài khoản
$wallets = $conn->query("SELECT * FROM wallets WHERE user_id = $userId AND is_active = 1 ORDER BY name");

// Lấy lịch sử chuyển tiền (lấy các giao dịch trong tháng)
$transferHistory = $conn->query("
    SELECT 
        t1.id,
        t1.amount,
        t1.description,
        t1.transaction_date,
        t1.created_at,
        w1.name as from_wallet_name,
        w1.icon as from_wallet_icon,
        w2.name as to_wallet_name,
        w2.icon as to_wallet_icon
    FROM transactions t1
    INNER JOIN transactions t2 ON t1.description = t2.description 
        AND t1.amount = t2.amount 
        AND t1.transaction_date = t2.transaction_date
        AND t1.type = 'expense' 
        AND t2.type = 'income'
        AND t1.wallet_id != t2.wallet_id
    INNER JOIN wallets w1 ON t1.wallet_id = w1.id
    INNER JOIN wallets w2 ON t2.wallet_id = w2.id
    WHERE t1.user_id = $userId
    AND MONTH(t1.transaction_date) = MONTH(CURDATE())
    AND YEAR(t1.transaction_date) = YEAR(CURDATE())
    GROUP BY t1.id
    ORDER BY t1.created_at DESC
    LIMIT 20
");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chuyển tiền - Finora</title>
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

        /* Sidebar */
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

        /* Alert */
        .alert { padding: 16px 20px; border-radius: 12px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; font-size: 14px; }
        .alert-success { background: #D1FAE5; color: #065F46; border: 1px solid #6EE7B7; }
        .alert-danger { background: #FEE2E2; color: #991B1B; border: 1px solid #FCA5A5; }

        /* Transfer Form */
        .transfer-card { background: var(--card); border: 1px solid var(--border); border-radius: 20px; padding: 32px; margin-bottom: 24px; max-width: 600px; }
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-size: 14px; font-weight: 600; margin-bottom: 8px; color: var(--text); }
        .form-input, .form-select, .form-textarea { width: 100%; padding: 12px 16px; border: 1.5px solid var(--border); border-radius: 10px; font-size: 14px; font-family: inherit; transition: all 0.2s; }
        .form-input:focus, .form-select:focus, .form-textarea:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
        .form-input-lg { font-size: 24px; font-weight: 700; text-align: center; }
        .form-textarea { resize: vertical; min-height: 80px; }
        .btn { padding: 12px 24px; border-radius: 10px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 8px; transition: all 0.2s; border: none; cursor: pointer; font-size: 14px; font-family: inherit; width: 100%; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-primary:hover { background: #1d4ed8; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3); }

        /* Transfer Arrow */
        .transfer-arrow { text-align: center; margin: 20px 0; color: var(--primary); font-size: 24px; }

        /* History */
        .history-section { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 24px; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .section-title { font-size: 18px; font-weight: 700; }
        .history-list { display: flex; flex-direction: column; gap: 12px; }
        .history-item { display: flex; align-items: center; gap: 16px; padding: 16px; background: var(--bg); border-radius: 12px; transition: all 0.2s; }
        .history-item:hover { background: #EFF6FF; }
        .history-icon { width: 48px; height: 48px; border-radius: 12px; background: linear-gradient(135deg, var(--primary), var(--purple)); display: flex; align-items: center; justify-content: center; font-size: 20px; color: white; }
        .history-info { flex: 1; }
        .history-title { font-weight: 600; font-size: 15px; margin-bottom: 4px; }
        .history-meta { font-size: 12px; color: var(--text-lighter); }
        .history-amount { font-size: 18px; font-weight: 700; color: var(--primary); }

        .empty-state { text-align: center; padding: 60px 20px; color: var(--text-light); }
        .empty-icon { font-size: 64px; opacity: 0.3; margin-bottom: 16px; }

        @media (max-width: 768px) {
            .sidebar { width: 0; overflow: hidden; }
            .main-content { margin-left: 0; padding: 20px; }
        }
    </style>
</head>
<body>
    <aside class="sidebar">
        <div class="logo"><div class="logo-icon">💰</div><span>Fi<span style="color: var(--primary)">no</span>ra</span></div>
        <ul class="sidebar-menu">
            <li class="menu-item"><a href="dashboard.php" class="menu-link"><i class="menu-icon fas fa-chart-line"></i><span>Dashboard</span></a></li>
            <li class="menu-item"><a href="accounts.php" class="menu-link active"><i class="menu-icon fas fa-wallet"></i><span>Tài khoản</span></a></li>
            <li class="menu-item"><a href="transactions.php" class="menu-link"><i class="menu-icon fas fa-exchange-alt"></i><span>Giao dịch</span></a></li>
            <li class="menu-item"><a href="budgets.php" class="menu-link"><i class="menu-icon fas fa-chart-pie"></i><span>Ngân sách</span></a></li>
            <li class="menu-item"><a href="budget-allocation.php" class="menu-link"><i class="menu-icon fas fa-percentage"></i><span>Phân bổ Thu nhập</span></a></li>
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
                <h1 class="page-title">💸 Chuyển tiền</h1>
                <p class="page-subtitle">Chuyển tiền giữa các tài khoản của bạn</p>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $success ?></div>
        <?php endif; ?>

        <!-- Transfer Form -->
        <div class="transfer-card">
            <h2 style="font-size: 20px; font-weight: 700; margin-bottom: 24px;">
                <i class="fas fa-exchange-alt"></i> Thực hiện chuyển tiền
            </h2>

            <form method="POST" action="">
                <input type="hidden" name="action" value="transfer">

                <div class="form-group">
                    <label class="form-label">Từ tài khoản *</label>
                    <select name="from_wallet_id" class="form-select" required>
                        <option value="">-- Chọn tài khoản nguồn --</option>
                        <?php 
                        $wallets->data_seek(0);
                        while ($wallet = $wallets->fetch_assoc()): 
                        ?>
                            <option value="<?= $wallet['id'] ?>">
                                <?= htmlspecialchars($wallet['icon']) ?> <?= htmlspecialchars($wallet['name']) ?> 
                                (<?= number_format($wallet['balance'], 0, ',', '.') ?>đ)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="transfer-arrow">
                    <i class="fas fa-arrow-down"></i>
                </div>

                <div class="form-group">
                    <label class="form-label">Đến tài khoản *</label>
                    <select name="to_wallet_id" class="form-select" required>
                        <option value="">-- Chọn tài khoản đích --</option>
                        <?php 
                        $wallets->data_seek(0);
                        while ($wallet = $wallets->fetch_assoc()): 
                        ?>
                            <option value="<?= $wallet['id'] ?>">
                                <?= htmlspecialchars($wallet['icon']) ?> <?= htmlspecialchars($wallet['name']) ?> 
                                (<?= number_format($wallet['balance'], 0, ',', '.') ?>đ)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Số tiền (VNĐ) *</label>
                    <input type="number" name="amount" class="form-input form-input-lg" 
                           placeholder="0" min="1" step="1" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Mô tả *</label>
                    <input type="text" name="description" class="form-input" 
                           placeholder="VD: Chuyển tiền tiết kiệm" value="Chuyển tiền" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Ghi chú</label>
                    <textarea name="note" class="form-textarea" placeholder="Ghi chú thêm (tùy chọn)"></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Ngày chuyển *</label>
                    <input type="date" name="transfer_date" class="form-input" value="<?= date('Y-m-d') ?>" required>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Thực hiện chuyển tiền
                </button>
            </form>
        </div>

        <!-- History -->
        <div class="history-section">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-history"></i> Lịch sử chuyển tiền tháng này</h2>
            </div>

            <?php if ($transferHistory->num_rows > 0): ?>
                <div class="history-list">
                    <?php while ($trans = $transferHistory->fetch_assoc()): ?>
                        <div class="history-item">
                            <div class="history-icon">
                                <i class="fas fa-exchange-alt"></i>
                            </div>
                            <div class="history-info">
                                <div class="history-title"><?= htmlspecialchars($trans['description']) ?></div>
                                <div class="history-meta">
                                    <span><?= htmlspecialchars($trans['from_wallet_icon']) ?> <?= htmlspecialchars($trans['from_wallet_name']) ?></span>
                                    <i class="fas fa-arrow-right" style="margin: 0 8px;"></i>
                                    <span><?= htmlspecialchars($trans['to_wallet_icon']) ?> <?= htmlspecialchars($trans['to_wallet_name']) ?></span>
                                    <span style="margin-left: 12px;"><i class="fas fa-calendar"></i> <?= date('d/m/Y', strtotime($trans['transaction_date'])) ?></span>
                                </div>
                            </div>
                            <div class="history-amount"><?= number_format($trans['amount'], 0, ',', '.') ?>đ</div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">📭</div>
                    <h3>Chưa có lịch sử chuyển tiền</h3>
                    <p>Các giao dịch chuyển tiền của bạn sẽ hiển thị ở đây</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
