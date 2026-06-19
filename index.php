 <?php
/**
 * Expense Manager Pro — Main Entry
 */
declare(strict_types=1);

// Check if config exists, if not show setup instructions
if (!file_exists(__DIR__ . '/includes/config.php')) {
    die('Please run database setup first. Import database.sql into MySQL.');
}

require_once __DIR__ . '/includes/config.php';
session_start();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Expense Manager Pro</title>
<meta name="description" content="Quản lý chi tiêu cá nhân thông minh">

<!-- Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">

<style>
:root {
  --brand-primary: #7C3AED;
  --income-color: #10B981;
  --expense-color: #EF4444;
  --transfer-color: #3B82F6;
  --bg-page: #0F0F12;
  --bg-card: #18181C;
  --bg-card-hover: #1F1F25;
  --bg-input: #0F0F12;
  --border: rgba(255,255,255,0.08);
  --border-focus: rgba(124,58,237,0.6);
  --text-primary: #F4F4F6;
  --text-secondary: #9CA3AF;
  --text-muted: #6B7280;
  --font: 'Be Vietnam Pro', sans-serif;
  --font-mono: 'JetBrains Mono', monospace;
  --radius-sm: 8px;
  --radius-md: 12px;
  --radius-lg: 16px;
  --radius-xl: 24px;
  --shadow-elevated: 0 8px 32px rgba(0,0,0,0.5);
  --sidebar-w: 240px;
  --transition: 180ms cubic-bezier(0.4, 0, 0.2, 1);
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

html, body {
  font-family: var(--font);
  background: var(--bg-page);
  color: var(--text-primary);
  font-size: 14px;
  line-height: 1.6;
  min-height: 100vh;
}

::-webkit-scrollbar { width: 5px; height: 5px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.12); border-radius: 99px; }

#app { display: flex; min-height: 100vh; }

.sidebar {
  width: var(--sidebar-w);
  background: var(--bg-card);
  border-right: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  position: fixed;
  top: 0; left: 0; bottom: 0;
  z-index: 100;
}
.sidebar__logo {
  padding: 24px 20px;
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center; gap: 10px;
}
.sidebar__logo-icon {
  width: 36px; height: 36px;
  background: linear-gradient(135deg, #7C3AED, #A855F7);
  border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 18px;
}
.sidebar__logo-text { font-weight: 700; font-size: 15px; }
.sidebar__logo-sub { font-size: 10px; color: var(--text-muted); }

.sidebar__nav { flex: 1; padding: 12px 0; overflow-y: auto; }
.nav-section { padding: 16px 20px 6px; font-size: 10px; letter-spacing: 0.8px; text-transform: uppercase; color: var(--text-muted); font-weight: 600; }
.nav-item {
  display: flex; align-items: center; gap: 10px;
  padding: 9px 20px; margin: 1px 8px;
  border-radius: var(--radius-sm);
  color: var(--text-secondary);
  cursor: pointer;
  transition: all var(--transition);
  font-size: 13.5px;
  border: none; background: none;
  width: calc(100% - 16px);
  text-align: left;
}
.nav-item:hover { background: rgba(124,58,237,0.1); color: var(--text-primary); }
.nav-item.active { background: rgba(124,58,237,0.18); color: #A78BFA; font-weight: 500; }
.nav-item .icon { width: 18px; text-align: center; font-size: 16px; }
.nav-badge {
  margin-left: auto; background: #7C3AED; color: white;
  font-size: 10px; font-weight: 600; padding: 1px 6px;
  border-radius: 99px; min-width: 18px; text-align: center;
}

.sidebar__footer {
  padding: 16px;
  border-top: 1px solid var(--border);
}
.sidebar__user {
  display: flex; align-items: center; gap: 10px;
  padding: 8px 10px; border-radius: var(--radius-sm);
  cursor: pointer;
}
.sidebar__user:hover { background: var(--bg-card-hover); }
.user-avatar {
  width: 34px; height: 34px; border-radius: 50%;
  background: linear-gradient(135deg, #7C3AED, #EC4899);
  display: flex; align-items: center; justify-content: center;
  font-size: 12px; font-weight: 700;
}
.user-name { font-size: 13px; font-weight: 500; }
.user-email { font-size: 11px; color: var(--text-muted); }

.main {
  flex: 1;
  margin-left: var(--sidebar-w);
  display: flex;
  flex-direction: column;
}
.topbar {
  background: var(--bg-card);
  border-bottom: 1px solid var(--border);
  padding: 0 28px;
  height: 60px;
  display: flex; align-items: center; gap: 16px;
  position: sticky; top: 0; z-index: 50;
}
.topbar__title { font-weight: 600; font-size: 16px; flex: 1; }
.topbar__actions { display: flex; gap: 8px; }

.content { padding: 28px; flex: 1; }

.kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px; }
.kpi-card {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 20px;
  position: relative;
  overflow: hidden;
}
.kpi-card::before {
  content: '';
  position: absolute; top: 0; left: 0; right: 0; height: 2px;
}
.kpi-card.income::before  { background: var(--income-color); }
.kpi-card.expense::before { background: var(--expense-color); }
.kpi-card.balance::before { background: #A78BFA; }
.kpi-icon {
  width: 36px; height: 36px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 17px; margin-bottom: 14px;
}
.kpi-card.income  .kpi-icon { background: rgba(16,185,129,0.12); color: #10B981; }
.kpi-card.expense .kpi-icon { background: rgba(239,68,68,0.12);  color: #EF4444; }
.kpi-card.balance .kpi-icon { background: rgba(167,139,250,0.12); color: #A78BFA; }
.kpi-label { font-size: 12px; color: var(--text-muted); text-transform: uppercase; margin-bottom: 4px; }
.kpi-value { font-size: 22px; font-weight: 700; }

.dash-grid { display: grid; grid-template-columns: 1fr 340px; gap: 20px; }

.card {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  overflow: hidden;
}
.card-header {
  padding: 16px 20px;
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between;
}
.card-title { font-weight: 600; font-size: 14px; }
.card-subtitle { font-size: 12px; color: var(--text-muted); margin-top: 2px; }
.card-body { padding: 16px 20px; }

.txn-list { display: flex; flex-direction: column; }
.txn-item {
  display: flex; align-items: center; gap: 12px;
  padding: 12px 20px;
  border-bottom: 1px solid var(--border);
  cursor: pointer;
}
.txn-item:last-child { border-bottom: none; }
.txn-item:hover { background: var(--bg-card-hover); }
.txn-icon {
  width: 38px; height: 38px; border-radius: 10px;
  display: flex; align-items: center; justify-content: center;
  font-size: 16px;
}
.txn-desc { flex: 1; min-width: 0; }
.txn-name { font-size: 13.5px; font-weight: 500; }
.txn-meta { font-size: 11px; color: var(--text-muted); }
.txn-amount { font-family: var(--font-mono); font-size: 14px; font-weight: 600; }
.txn-amount.income   { color: var(--income-color); }
.txn-amount.expense  { color: var(--expense-color); }

.budget-item { margin-bottom: 16px; }
.budget-top { display: flex; justify-content: space-between; margin-bottom: 6px; }
.budget-name { font-size: 13px; font-weight: 500; }
.budget-pct { font-size: 12px; color: var(--text-muted); }
.budget-bar { height: 6px; background: rgba(255,255,255,0.08); border-radius: 99px; overflow: hidden; }
.budget-fill { height: 100%; border-radius: 99px; }
.budget-amounts { display: flex; justify-content: space-between; margin-top: 4px; font-size: 11px; color: var(--text-muted); }

.acc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; }
.acc-card {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius-md);
  padding: 16px;
  cursor: pointer;
}
.acc-icon { font-size: 20px; margin-bottom: 12px; }
.acc-name { font-size: 13px; font-weight: 500; margin-bottom: 2px; }
.acc-type { font-size: 11px; color: var(--text-muted); text-transform: uppercase; margin-bottom: 10px; }
.acc-balance { font-size: 18px; font-weight: 700; font-family: var(--font-mono); }

.btn {
  display: inline-flex; align-items: center; justify-content: center; gap: 6px;
  padding: 8px 16px; border-radius: var(--radius-sm);
  font-family: var(--font); font-size: 13px; font-weight: 500;
  cursor: pointer; border: none; transition: all var(--transition);
  text-decoration: none;
}
.btn-primary { background: #7C3AED; color: white; }
.btn-primary:hover { background: #6D28D9; }
.btn-ghost { background: transparent; color: var(--text-secondary); border: 1px solid var(--border); }
.btn-ghost:hover { background: var(--bg-card-hover); }
.btn-sm { padding: 5px 10px; font-size: 12px; }

.modal-overlay {
  position: fixed; inset: 0;
  background: rgba(0,0,0,0.65);
  backdrop-filter: blur(4px);
  z-index: 1000;
  display: none; align-items: center; justify-content: center;
}
.modal-overlay.open { display: flex; }
.modal {
  background: var(--bg-card);
  border: 1px solid rgba(255,255,255,0.1);
  border-radius: var(--radius-xl);
  padding: 28px;
  width: 100%; max-width: 480px;
}
.modal-title { font-size: 18px; font-weight: 700; margin-bottom: 4px; }
.modal-sub { font-size: 13px; color: var(--text-muted); margin-bottom: 24px; }

.form-group { margin-bottom: 16px; }
.form-label { display: block; font-size: 12px; font-weight: 500; color: var(--text-secondary); margin-bottom: 6px; text-transform: uppercase; }
.form-control {
  width: 100%; background: var(--bg-input);
  border: 1px solid var(--border); border-radius: var(--radius-sm);
  color: var(--text-primary); font-family: var(--font); font-size: 14px;
  padding: 10px 12px; outline: none;
}
.form-control:focus { border-color: var(--border-focus); }
select.form-control option { background: #1F1F25; }

.type-switcher {
  display: flex; background: var(--bg-input);
  border: 1px solid var(--border); border-radius: var(--radius-sm);
  padding: 3px; gap: 3px;
}
.type-btn {
  flex: 1; padding: 7px; border-radius: 6px; border: none;
  font-size: 12px; font-weight: 500;
  cursor: pointer;
  background: transparent; color: var(--text-muted);
}
.type-btn.active.expense  { background: rgba(239,68,68,0.15);  color: #EF4444; }
.type-btn.active.income   { background: rgba(16,185,129,0.15); color: #10B981; }

.input-amount {
  font-family: var(--font-mono);
  font-size: 28px;
  font-weight: 700;
  text-align: center;
  border: none !important;
  background: transparent !important;
  color: var(--text-primary);
  width: 100%;
  padding: 12px 0 8px;
  outline: none;
}
.amount-row { border-bottom: 2px solid var(--border); margin-bottom: 20px; }

.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.modal-actions { display: flex; gap: 8px; justify-content: flex-end; margin-top: 24px; }

.cat-list { display: flex; flex-direction: column; gap: 10px; }
.cat-item { display: flex; align-items: center; gap: 10px; }
.cat-dot { width: 10px; height: 10px; border-radius: 50%; }
.cat-label { flex: 1; font-size: 13px; }
.cat-amount { font-size: 12px; font-family: var(--font-mono); color: var(--text-muted); }

.login-page {
  min-height: 100vh; display: flex; align-items: center; justify-content: center;
  background: var(--bg-page);
}
.login-card {
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius-xl);
  padding: 40px 44px;
  width: 100%; max-width: 400px;
  box-shadow: var(--shadow-elevated);
}
.login-logo { text-align: center; margin-bottom: 32px; }
.login-logo .logo-icon { font-size: 40px; margin-bottom: 12px; }
.login-logo h1 { font-size: 22px; font-weight: 700; }
.login-logo p { font-size: 13px; color: var(--text-muted); margin-top: 4px; }

#toast-container { position: fixed; bottom: 24px; right: 24px; z-index: 9999; display: flex; flex-direction: column; gap: 8px; }
.toast {
  display: flex; align-items: center; gap: 10px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: var(--radius-md);
  padding: 12px 16px;
  font-size: 13px;
  box-shadow: var(--shadow-elevated);
  max-width: 320px;
}
.toast.success { border-left: 3px solid var(--income-color); }
.toast.error   { border-left: 3px solid var(--expense-color); }

.empty-state { text-align: center; padding: 40px 20px; color: var(--text-muted); }
.empty-state .icon { font-size: 40px; margin-bottom: 12px; opacity: 0.4; }

@media (max-width: 1200px) { .dash-grid { grid-template-columns: 1fr; } }
@media (max-width: 768px) {
  .sidebar { transform: translateX(-100%); }
  .main { margin-left: 0; }
  .kpi-grid { grid-template-columns: 1fr 1fr; }
}
</style>
</head>
<body>

<div id="app"></div>
<div id="toast-container"></div>

<!-- Register Modal -->
<div class="modal-overlay" id="modal-register">
  <div class="modal">
    <div class="modal-title">Đăng ký tài khoản</div>
    <div class="modal-sub">Tạo tài khoản mới để bắt đầu quản lý chi tiêu</div>

    <form id="register-form" onsubmit="App.handleRegister(event)">
      <div class="form-group">
        <label class="form-label">Họ và tên</label>
        <input type="text" class="form-control" id="register-name" placeholder="Nguyễn Văn A" required>
      </div>

      <div class="form-group">
        <label class="form-label">Email</label>
        <input type="email" class="form-control" id="register-email" placeholder="email@example.com" required>
      </div>

      <div class="form-group">
        <label class="form-label">Mật khẩu</label>
        <input type="password" class="form-control" id="register-password" placeholder="Tối thiểu 6 ký tự" required minlength="6">
      </div>

      <div class="form-group">
        <label class="form-label">Xác nhận mật khẩu</label>
        <input type="password" class="form-control" id="register-password-confirm" placeholder="Nhập lại mật khẩu" required>
      </div>

      <div class="modal-actions">
        <button type="button" class="btn btn-ghost" onclick="App.closeRegisterModal()">Huỷ</button>
        <button type="submit" class="btn btn-primary">Đăng ký</button>
      </div>
    </form>
  </div>
</div>

<!-- Add Transaction Modal -->
<div class="modal-overlay" id="modal-add-txn">
  <div class="modal">
    <div class="modal-title">Thêm giao dịch</div>
    <div class="modal-sub">Nhập thông tin giao dịch mới</div>

    <div class="type-switcher" style="margin-bottom:20px">
      <button class="type-btn active expense" data-type="expense" onclick="App.setTxnType('expense')">💸 Chi tiêu</button>
      <button class="type-btn income" data-type="income" onclick="App.setTxnType('income')">💰 Thu nhập</button>
    </div>

    <div class="amount-row">
      <input type="number" class="input-amount" id="txn-amount" placeholder="0" min="0" step="1000">
    </div>

    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Danh mục</label>
        <select class="form-control" id="txn-category"></select>
      </div>
      <div class="form-group">
        <label class="form-label">Tài khoản</label>
        <select class="form-control" id="txn-account"></select>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">Mô tả</label>
      <input type="text" class="form-control" id="txn-desc" placeholder="Nhập mô tả giao dịch...">
    </div>

    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Ngày</label>
        <input type="date" class="form-control" id="txn-date">
      </div>
      <div class="form-group">
        <label class="form-label">Ghi chú</label>
        <input type="text" class="form-control" id="txn-note" placeholder="Tuỳ chọn...">
      </div>
    </div>

    <div class="modal-actions">
      <button class="btn btn-ghost" onclick="App.closeModal()">Huỷ</button>
      <button class="btn btn-primary" onclick="App.submitTransaction()">Lưu giao dịch</button>
    </div>
  </div>
</div>

<script src="js/app.js"></script>

</body>
</html>
