/* ============================================================
   EXPENSE MANAGER PRO — Frontend Application
   Architecture: Module pattern, API integration
============================================================ */

const App = (() => {
  // ── State ──────────────────────────────────────────────
  const state = {
    user: null,
    currentView: 'dashboard',
    transactions: [],
    accounts: [],
    categories: [],
    budgets: [],
    dashboard: null,
    currentMonth: new Date().getMonth() + 1,
    currentYear: new Date().getFullYear(),
    txnType: 'expense',
    chartInstance: null,
    loading: false,
  };

  // ── API Helper ──────────────────────────────────────────
  const API = {
    async request(url, options = {}) {
      try {
        const response = await fetch(url, {
          ...options,
          headers: {
            'Content-Type': 'application/json',
            ...options.headers,
          },
        });
        const data = await response.json();
        if (!data.success) {
          throw new Error(data.message || 'Request failed');
        }
        return data;
      } catch (error) {
        toast(error.message, 'error');
        throw error;
      }
    },

    async get(url) {
      return this.request(url);
    },

    async post(url, body) {
      return this.request(url, {
        method: 'POST',
        body: JSON.stringify(body),
      });
    },

    async put(url, body) {
      return this.request(url, {
        method: 'PUT',
        body: JSON.stringify(body),
      });
    },

    async delete(url) {
      return this.request(url, { method: 'DELETE' });
    },
  };

  // ── Utilities ──────────────────────────────────────────
  function fmtMoney(n) {
    if (Math.abs(n) >= 1e9) return (n/1e9).toFixed(1) + ' tỷ₫';
    if (Math.abs(n) >= 1e6) return (n/1e6).toFixed(1) + ' tr₫';
    return new Intl.NumberFormat('vi-VN').format(Math.round(n)) + '₫';
  }

  function fmtDate(str) {
    if (!str) return '';
    const d = new Date(str);
    return new Intl.DateTimeFormat('vi-VN', { day:'2-digit', month:'2-digit' }).format(d);
  }

  function toast(msg, type = 'success') {
    const el = document.createElement('div');
    el.className = `toast ${type}`;
    el.innerHTML = `<span>${type === 'success' ? '✅' : '❌'}</span><span>${msg}</span>`;
    document.getElementById('toast-container').appendChild(el);
    setTimeout(() => el.remove(), 3500);
  }

  // ── Data Loading ──────────────────────────────────────────
  async function loadDashboard() {
    try {
      const { data } = await API.get(`api/dashboard.php?month=${state.currentMonth}&year=${state.currentYear}`);
      state.dashboard = data;
    } catch (error) {
      console.error('Failed to load dashboard:', error);
    }
  }

  async function loadTransactions() {
    try {
      const { data } = await API.get(`api/transactions.php?month=${state.currentMonth}&year=${state.currentYear}`);
      state.transactions = data.transactions;
    } catch (error) {
      console.error('Failed to load transactions:', error);
    }
  }

  async function loadAccounts() {
    try {
      const { data } = await API.get('api/accounts.php');
      state.accounts = data.accounts;
    } catch (error) {
      console.error('Failed to load accounts:', error);
    }
  }

  async function loadCategories() {
    try {
      const { data } = await API.get('api/categories.php');
      state.categories = data.categories;
    } catch (error) {
      console.error('Failed to load categories:', error);
    }
  }

  async function loadBudgets() {
    try {
      const { data } = await API.get(`api/budgets.php?month=${state.currentMonth}&year=${state.currentYear}`);
      state.budgets = data.budgets;
    } catch (error) {
      console.error('Failed to load budgets:', error);
    }
  }

  // ── Init ──────────────────────────────────────────────
  async function init() {
    try {
      const { data } = await API.get('api/auth.php?action=user');
      state.user = data.user;
      
      await Promise.all([
        loadAccounts(),
        loadCategories(),
        loadTransactions(),
        loadBudgets(),
        loadDashboard(),
      ]);
      
      renderApp();
      document.getElementById('txn-date').value = new Date().toISOString().split('T')[0];
    } catch (error) {
      // User not logged in, show login page
      renderLoginPage();
    }
  }

  // ── Render Login Page ──────────────────────────────────
  function renderLoginPage() {
    const app = document.getElementById('app');
    app.innerHTML = `
      <div class="login-page">
        <div class="login-card">
          <div class="login-logo">
            <span class="logo-icon">💰</span>
            <h1>Expense Manager Pro</h1>
            <p>Quản lý chi tiêu thông minh</p>
          </div>
          
          <form id="login-form" onsubmit="App.handleLogin(event)">
            <div class="form-group">
              <label class="form-label">Email</label>
              <input type="email" class="form-control" id="login-email" value="demo@expense.vn" required>
            </div>
            
            <div class="form-group">
              <label class="form-label">Mật khẩu</label>
              <input type="password" class="form-control" id="login-password" value="demo123" required>
            </div>
            
            <button type="submit" class="btn btn-primary" style="width:100%; margin-top:8px">
              Đăng nhập
            </button>
          </form>
          
          <div style="text-align:center; margin-top:20px;">
            <p style="font-size:12px; color:var(--text-muted); margin-bottom:12px">
              Demo: demo@expense.vn / demo123
            </p>
            <p style="font-size:13px; color:var(--text-secondary)">
              Chưa có tài khoản? 
              <a href="#" onclick="App.openRegisterModal(); return false;" style="color:#7C3AED; text-decoration:none; font-weight:500">
                Đăng ký ngay
              </a>
            </p>
          </div>
        </div>
      </div>
    `;
  }

  // ── Register Modal ──────────────────────────────────
  function openRegisterModal() {
    document.getElementById('modal-register').classList.add('open');
  }

  function closeRegisterModal() {
    document.getElementById('modal-register').classList.remove('open');
    // Clear form
    document.getElementById('register-form').reset();
  }

  async function handleRegister(event) {
    event.preventDefault();
    
    const name = document.getElementById('register-name').value.trim();
    const email = document.getElementById('register-email').value.trim();
    const password = document.getElementById('register-password').value;
    const passwordConfirm = document.getElementById('register-password-confirm').value;
    
    // Validation
    if (!name || !email || !password) {
      toast('Vui lòng điền đầy đủ thông tin', 'error');
      return;
    }
    
    if (password.length < 6) {
      toast('Mật khẩu phải có ít nhất 6 ký tự', 'error');
      return;
    }
    
    if (password !== passwordConfirm) {
      toast('Mật khẩu xác nhận không khớp', 'error');
      return;
    }
    
    try {
      await API.post('api/auth.php?action=register', { name, email, password });
      toast('Đăng ký thành công! Đang đăng nhập...');
      closeRegisterModal();
      
      // Auto login after successful registration
      await init();
    } catch (error) {
      // Error already shown by API helper
    }
  }

  // ── Handle Login ──────────────────────────────────────
  async function handleLogin(event) {
    event.preventDefault();
    const email = document.getElementById('login-email').value;
    const password = document.getElementById('login-password').value;
    
    try {
      await API.post('api/auth.php?action=login', { email, password });
      toast('Đăng nhập thành công!');
      await init();
    } catch (error) {
      // Error already shown by API helper
    }
  }

  // ── Handle Logout ──────────────────────────────────────
  async function handleLogout() {
    try {
      await API.post('api/auth.php?action=logout');
      toast('Đã đăng xuất');
      renderLoginPage();
    } catch (error) {
      // Error already shown
    }
  }

  // ── Render App Shell ──────────────────────────────────
  function renderApp() {
    const app = document.getElementById('app');
    app.innerHTML = `
      <nav class="sidebar" id="sidebar">
        <div class="sidebar__logo">
          <div class="sidebar__logo-icon">💰</div>
          <div>
            <div class="sidebar__logo-text">ExpenseManager</div>
            <div class="sidebar__logo-sub">Pro Edition</div>
          </div>
        </div>

        <div class="sidebar__nav">
          <div class="nav-section">Tổng quan</div>
          <button class="nav-item active" data-view="dashboard" onclick="App.navigate('dashboard')">
            <span class="icon">📊</span> Bảng điều khiển
          </button>
          <button class="nav-item" data-view="transactions" onclick="App.navigate('transactions')">
            <span class="icon">💳</span> Giao dịch
            <span class="nav-badge">${state.transactions.length}</span>
          </button>
          <button class="nav-item" data-view="accounts" onclick="App.navigate('accounts')">
            <span class="icon">🏦</span> Tài khoản
          </button>

          <div class="nav-section">Kế hoạch</div>
          <button class="nav-item" data-view="budgets" onclick="App.navigate('budgets')">
            <span class="icon">🎯</span> Ngân sách
          </button>
          <button class="nav-item" data-view="reports" onclick="App.navigate('reports')">
            <span class="icon">📈</span> Báo cáo
          </button>

          <div class="nav-section">Hệ thống</div>
          <button class="nav-item" data-view="categories" onclick="App.navigate('categories')">
            <span class="icon">🏷️</span> Danh mục
          </button>
          <button class="nav-item" data-view="settings" onclick="App.navigate('settings')">
            <span class="icon">⚙️</span> Cài đặt
          </button>
        </div>

        <div class="sidebar__footer">
          <div class="sidebar__user" onclick="App.handleLogout()">
            <div class="user-avatar">${state.user?.name?.substring(0,2).toUpperCase() || 'NT'}</div>
            <div style="min-width:0">
              <div class="user-name">${state.user?.name || 'User'}</div>
              <div class="user-email">${state.user?.email || ''}</div>
            </div>
          </div>
        </div>
      </nav>

      <main class="main">
        <div class="topbar">
          <div class="topbar__title" id="page-title">Bảng điều khiển</div>
          <div class="topbar__actions">
            <button class="btn btn-primary" onclick="App.openAddTransaction()">
              <span>➕</span> Thêm giao dịch
            </button>
          </div>
        </div>
        <div class="content" id="main-content"></div>
      </main>
    `;
    
    navigate('dashboard');
  }

  // ── Navigation ──────────────────────────────────────────
  function navigate(view) {
    state.currentView = view;
    
    // Update active nav item
    document.querySelectorAll('.nav-item').forEach(item => {
      item.classList.toggle('active', item.dataset.view === view);
    });
    
    // Render view
    const content = document.getElementById('main-content');
    const title = document.getElementById('page-title');
    
    switch(view) {
      case 'dashboard':
        title.textContent = 'Bảng điều khiển';
        renderDashboard(content);
        break;
      case 'transactions':
        title.textContent = 'Giao dịch';
        renderTransactions(content);
        break;
      case 'accounts':
        title.textContent = 'Tài khoản';
        renderAccounts(content);
        break;
      case 'budgets':
        title.textContent = 'Ngân sách';
        renderBudgets(content);
        break;
      case 'categories':
        title.textContent = 'Danh mục';
        renderCategories(content);
        break;
      default:
        content.innerHTML = '<div class="empty-state"><div class="icon">🚧</div><p>Tính năng đang phát triển</p></div>';
    }
  }

  // ── Render Dashboard ──────────────────────────────────
  function renderDashboard(container) {
    if (!state.dashboard) {
      container.innerHTML = '<div class="skeleton" style="height:400px"></div>';
      return;
    }
    
    const { summary, top_categories } = state.dashboard;
    
    container.innerHTML = `
      <div class="kpi-grid">
        <div class="kpi-card income">
          <div class="kpi-icon">💰</div>
          <div class="kpi-label">Thu nhập</div>
          <div class="kpi-value">${fmtMoney(summary.income)}</div>
        </div>
        <div class="kpi-card expense">
          <div class="kpi-icon">💸</div>
          <div class="kpi-label">Chi tiêu</div>
          <div class="kpi-value">${fmtMoney(summary.expense)}</div>
        </div>
        <div class="kpi-card balance">
          <div class="kpi-icon">💎</div>
          <div class="kpi-label">Còn lại</div>
          <div class="kpi-value">${fmtMoney(summary.net)}</div>
        </div>
        <div class="kpi-card txns">
          <div class="kpi-icon">📊</div>
          <div class="kpi-label">Giao dịch</div>
          <div class="kpi-value">${summary.total_transactions}</div>
        </div>
      </div>

      <div class="dash-grid">
        <div class="card">
          <div class="card-header">
            <div>
              <div class="card-title">Giao dịch gần đây</div>
              <div class="card-subtitle">30 ngày qua</div>
            </div>
            <button class="btn btn-ghost btn-sm" onclick="App.navigate('transactions')">Xem tất cả</button>
          </div>
          <div class="txn-list">
            ${state.transactions.slice(0, 8).map(t => `
              <div class="txn-item">
                <div class="txn-icon" style="background:${t.category_color}22; color:${t.category_color}">
                  ${t.category_icon}
                </div>
                <div class="txn-desc">
                  <div class="txn-name">${t.description}</div>
                  <div class="txn-meta">${t.category_name} • ${fmtDate(t.transaction_date)}</div>
                </div>
                <div class="txn-amount ${t.type}">${t.type === 'income' ? '+' : '-'}${fmtMoney(t.amount)}</div>
              </div>
            `).join('')}
          </div>
        </div>

        <div>
          <div class="card" style="margin-bottom:20px">
            <div class="card-header">
              <div class="card-title">Chi tiêu theo danh mục</div>
            </div>
            <div class="card-body">
              <div class="cat-list">
                ${top_categories.map(c => `
                  <div class="cat-item">
                    <div class="cat-dot" style="background:${c.color}"></div>
                    <div class="cat-label">${c.icon} ${c.name}</div>
                    <div class="cat-amount">${fmtMoney(c.total)}</div>
                  </div>
                `).join('')}
              </div>
            </div>
          </div>

          <div class="card">
            <div class="card-header">
              <div class="card-title">Ngân sách</div>
            </div>
            <div class="card-body">
              ${state.budgets.slice(0, 4).map(b => `
                <div class="budget-item">
                  <div class="budget-top">
                    <div class="budget-name">${b.category_icon} ${b.name}</div>
                    <div class="budget-pct">${b.pct}%</div>
                  </div>
                  <div class="budget-bar">
                    <div class="budget-fill" style="width:${Math.min(b.pct, 100)}%; background:${b.pct > 100 ? '#EF4444' : b.category_color}"></div>
                  </div>
                  <div class="budget-amounts">
                    <span>${fmtMoney(b.spent)}</span>
                    <span>${fmtMoney(b.amount)}</span>
                  </div>
                </div>
              `).join('')}
            </div>
          </div>
        </div>
      </div>
    `;
  }

  // ── Render Transactions ──────────────────────────────────
  function renderTransactions(container) {
    container.innerHTML = `
      <div class="card">
        <div class="card-header">
          <div class="card-title">Tất cả giao dịch</div>
          <button class="btn btn-primary btn-sm" onclick="App.openAddTransaction()">➕ Thêm</button>
        </div>
        <div class="txn-list">
          ${state.transactions.map(t => `
            <div class="txn-item">
              <div class="txn-icon" style="background:${t.category_color}22; color:${t.category_color}">
                ${t.category_icon}
              </div>
              <div class="txn-desc">
                <div class="txn-name">${t.description}</div>
                <div class="txn-meta">${t.category_name} • ${t.account_name} • ${fmtDate(t.transaction_date)}</div>
              </div>
              <div class="txn-amount ${t.type}">${t.type === 'income' ? '+' : '-'}${fmtMoney(t.amount)}</div>
            </div>
          `).join('')}
        </div>
      </div>
    `;
  }

  // ── Render Accounts ──────────────────────────────────
  function renderAccounts(container) {
    const totalBalance = state.accounts.reduce((sum, a) => sum + parseFloat(a.balance), 0);
    
    container.innerHTML = `
      <div class="kpi-grid" style="margin-bottom:24px">
        <div class="kpi-card balance">
          <div class="kpi-icon">💰</div>
          <div class="kpi-label">Tổng tài sản</div>
          <div class="kpi-value">${fmtMoney(totalBalance)}</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-icon">🏦</div>
          <div class="kpi-label">Số tài khoản</div>
          <div class="kpi-value">${state.accounts.length}</div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <div class="card-title">Danh sách tài khoản</div>
        </div>
        <div class="card-body">
          <div class="acc-grid">
            ${state.accounts.map(a => `
              <div class="acc-card" style="border-color:${a.color}33">
                <div class="acc-icon">${a.icon}</div>
                <div class="acc-name">${a.name}</div>
                <div class="acc-type">${a.type}</div>
                <div class="acc-balance" style="color:${parseFloat(a.balance) < 0 ? '#EF4444' : 'inherit'}">
                  ${fmtMoney(a.balance)}
                </div>
              </div>
            `).join('')}
          </div>
        </div>
      </div>
    `;
  }

  // ── Render Budgets ──────────────────────────────────
  function renderBudgets(container) {
    container.innerHTML = `
      <div class="card">
        <div class="card-header">
          <div class="card-title">Ngân sách tháng ${state.currentMonth}/${state.currentYear}</div>
        </div>
        <div class="card-body">
          ${state.budgets.map(b => `
            <div class="budget-item" style="margin-bottom:24px">
              <div class="budget-top">
                <div class="budget-name">${b.category_icon} ${b.name}</div>
                <div class="budget-pct">${b.pct}%</div>
              </div>
              <div class="budget-bar">
                <div class="budget-fill" style="width:${Math.min(b.pct, 100)}%; background:${b.pct > 100 ? '#EF4444' : b.category_color}"></div>
              </div>
              <div class="budget-amounts">
                <span>Đã chi: ${fmtMoney(b.spent)}</span>
                <span>Ngân sách: ${fmtMoney(b.amount)}</span>
              </div>
            </div>
          `).join('')}
        </div>
      </div>
    `;
  }

  // ── Render Categories ──────────────────────────────────
  function renderCategories(container) {
    const expenseCats = state.categories.filter(c => c.type === 'expense');
    const incomeCats = state.categories.filter(c => c.type === 'income');
    
    container.innerHTML = `
      <div class="dash-grid">
        <div class="card">
          <div class="card-header">
            <div class="card-title">Danh mục chi tiêu</div>
          </div>
          <div class="card-body">
            <div class="cat-list">
              ${expenseCats.map(c => `
                <div class="cat-item">
                  <div class="cat-dot" style="background:${c.color}"></div>
                  <div class="cat-label">${c.icon} ${c.name}</div>
                </div>
              `).join('')}
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <div class="card-title">Danh mục thu nhập</div>
          </div>
          <div class="card-body">
            <div class="cat-list">
              ${incomeCats.map(c => `
                <div class="cat-item">
                  <div class="cat-dot" style="background:${c.color}"></div>
                  <div class="cat-label">${c.icon} ${c.name}</div>
                </div>
              `).join('')}
            </div>
          </div>
        </div>
      </div>
    `;
  }

  // ── Transaction Modal ──────────────────────────────────
  function openAddTransaction() {
    populateTransactionForm();
    document.getElementById('modal-add-txn').classList.add('open');
  }

  function closeModal() {
    document.getElementById('modal-add-txn').classList.remove('open');
  }

  function setTxnType(type) {
    state.txnType = type;
    document.querySelectorAll('.type-btn').forEach(btn => {
      btn.classList.toggle('active', btn.dataset.type === type);
    });
    populateTransactionForm();
  }

  function populateTransactionForm() {
    const catSelect = document.getElementById('txn-category');
    const accSelect = document.getElementById('txn-account');
    
    // Populate categories
    const cats = state.categories.filter(c => c.type === state.txnType);
    catSelect.innerHTML = cats.map(c => 
      `<option value="${c.id}">${c.icon} ${c.name}</option>`
    ).join('');
    
    // Populate accounts
    accSelect.innerHTML = state.accounts.map(a => 
      `<option value="${a.id}">${a.icon} ${a.name}</option>`
    ).join('');
  }

  async function submitTransaction() {
    const amount = parseFloat(document.getElementById('txn-amount').value);
    const categoryId = parseInt(document.getElementById('txn-category').value);
    const accountId = parseInt(document.getElementById('txn-account').value);
    const description = document.getElementById('txn-desc').value;
    const note = document.getElementById('txn-note').value;
    const transactionDate = document.getElementById('txn-date').value;
    
    if (!amount || amount <= 0) {
      toast('Vui lòng nhập số tiền hợp lệ', 'error');
      return;
    }
    
    if (!description) {
      toast('Vui lòng nhập mô tả', 'error');
      return;
    }
    
    try {
      await API.post('api/transactions.php', {
        type: state.txnType,
        amount,
        category_id: categoryId,
        account_id: accountId,
        description,
        note,
        transaction_date: transactionDate,
      });
      
      toast('Thêm giao dịch thành công!');
      closeModal();
      
      // Reload data
      await Promise.all([
        loadTransactions(),
        loadAccounts(),
        loadDashboard(),
      ]);
      
      // Re-render current view
      navigate(state.currentView);
      
      // Clear form
      document.getElementById('txn-amount').value = '';
      document.getElementById('txn-desc').value = '';
      document.getElementById('txn-note').value = '';
    } catch (error) {
      // Error already shown
    }
  }

  // ── Public API ──────────────────────────────────────────
  return {
    init,
    navigate,
    handleLogin,
    handleLogout,
    handleRegister,
    openRegisterModal,
    closeRegisterModal,
    openAddTransaction,
    closeModal,
    setTxnType,
    submitTransaction,
  };
})();

// Initialize app when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  App.init();
  
  // Close modals on overlay click
  document.getElementById('modal-add-txn').addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-overlay')) {
      App.closeModal();
    }
  });
  
  document.getElementById('modal-register').addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-overlay')) {
      App.closeRegisterModal();
    }
  });
});
