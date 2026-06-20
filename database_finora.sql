-- ============================================================
-- FINORA - Hệ thống Quản lý Chi tiêu Cá nhân Thông minh
-- Database Schema - Version 2.0
-- ============================================================

DROP DATABASE IF EXISTS quanly_chitieu;
CREATE DATABASE quanly_chitieu CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE quanly_chitieu;

-- ══════════════════════════════════════════════════════════
-- I. BẢNG CƠ BẢN
-- ══════════════════════════════════════════════════════════

-- 1. Bảng người dùng
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    currency VARCHAR(10) DEFAULT 'VND',
    monthly_budget DECIMAL(15,2) DEFAULT 0,
    remember_token VARCHAR(100) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_email (email),
    INDEX idx_username (username),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Bảng danh mục (categories)
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    name VARCHAR(100) NOT NULL,
    type ENUM('income', 'expense') DEFAULT 'expense',
    icon VARCHAR(50) DEFAULT '📦',
    color VARCHAR(20) DEFAULT '#7C3AED',
    description TEXT,
    is_system TINYINT(1) DEFAULT 0,
    parent_id INT DEFAULT NULL,
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_user_type (user_id, type),
    INDEX idx_system (is_system)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Bảng ví tiền / tài khoản
CREATE TABLE wallets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    type ENUM('cash', 'bank', 'e_wallet', 'credit_card', 'investment') DEFAULT 'cash',
    balance DECIMAL(15,2) DEFAULT 0,
    bank_name VARCHAR(100) DEFAULT NULL,
    account_number VARCHAR(50) DEFAULT NULL,
    color VARCHAR(20) DEFAULT '#7C3AED',
    icon VARCHAR(50) DEFAULT '💰',
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_active (user_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Bảng giao dịch
CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    wallet_id INT NOT NULL,
    category_id INT NOT NULL,
    type ENUM('income', 'expense', 'transfer') DEFAULT 'expense',
    amount DECIMAL(15,2) NOT NULL,
    description VARCHAR(255),
    note TEXT,
    transaction_date DATE NOT NULL,
    receipt_image VARCHAR(255) DEFAULT NULL,
    location VARCHAR(255) DEFAULT NULL,
    tags VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    INDEX idx_user_date (user_id, transaction_date),
    INDEX idx_type (type),
    INDEX idx_wallet (wallet_id),
    INDEX idx_category (category_id),
    INDEX idx_date (transaction_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ══════════════════════════════════════════════════════════
-- II. BẢNG NÂNG CAO
-- ══════════════════════════════════════════════════════════

-- 5. Bảng ngân sách
CREATE TABLE budgets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    period ENUM('daily', 'weekly', 'monthly', 'yearly', 'custom') DEFAULT 'monthly',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    alert_threshold INT DEFAULT 80,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    INDEX idx_user_period (user_id, period),
    INDEX idx_dates (start_date, end_date),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Bảng mục tiêu tiết kiệm
CREATE TABLE savings_goals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    target_amount DECIMAL(15,2) NOT NULL,
    current_amount DECIMAL(15,2) DEFAULT 0,
    deadline DATE DEFAULT NULL,
    icon VARCHAR(50) DEFAULT '🎯',
    color VARCHAR(20) DEFAULT '#10B981',
    description TEXT,
    status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_status (user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Bảng khoản vay
CREATE TABLE debts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('lend', 'borrow') NOT NULL,
    person_name VARCHAR(100) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    paid_amount DECIMAL(15,2) DEFAULT 0,
    interest_rate DECIMAL(5,2) DEFAULT 0,
    start_date DATE NOT NULL,
    due_date DATE DEFAULT NULL,
    description TEXT,
    status ENUM('active', 'paid', 'overdue') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_status (user_id, status),
    INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Bảng hóa đơn định kỳ
CREATE TABLE recurring_bills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category_id INT NOT NULL,
    wallet_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    frequency ENUM('daily', 'weekly', 'monthly', 'yearly') DEFAULT 'monthly',
    start_date DATE NOT NULL,
    end_date DATE DEFAULT NULL,
    next_due_date DATE NOT NULL,
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    auto_pay TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON DELETE CASCADE,
    INDEX idx_user_active (user_id, is_active),
    INDEX idx_next_due (next_due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Bảng tài sản cá nhân
CREATE TABLE assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    type ENUM('vehicle', 'electronics', 'real_estate', 'other') DEFAULT 'other',
    purchase_price DECIMAL(15,2) DEFAULT 0,
    current_value DECIMAL(15,2) DEFAULT 0,
    purchase_date DATE DEFAULT NULL,
    description TEXT,
    image VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_type (user_id, type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ══════════════════════════════════════════════════════════
-- III. BẢNG HỆ THỐNG
-- ══════════════════════════════════════════════════════════

-- 10. Bảng thông báo
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type ENUM('budget_alert', 'bill_reminder', 'goal_progress', 'system') DEFAULT 'system',
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(255) DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, is_read),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Bảng nhật ký hoạt động
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50) DEFAULT NULL,
    record_id INT DEFAULT NULL,
    old_values TEXT DEFAULT NULL,
    new_values TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Bảng chia sẻ tài chính gia đình
CREATE TABLE family_sharing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    member_id INT NOT NULL,
    permission ENUM('view', 'edit', 'full') DEFAULT 'view',
    status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_sharing (owner_id, member_id),
    INDEX idx_member (member_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Bảng phân bổ thu nhập theo phần trăm
CREATE TABLE budget_allocations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    percentage DECIMAL(5,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ══════════════════════════════════════════════════════════
-- IV. DỮ LIỆU MẪU
-- ══════════════════════════════════════════════════════════

-- Insert admin user
INSERT INTO users (username, email, password, full_name, role, monthly_budget) VALUES
('admin', 'admin@finora.vn', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin', 20000000),
('demo', 'demo@finora.vn', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Nguyễn Văn Demo', 'user', 15000000);

-- Insert system categories (Expense)
INSERT INTO categories (user_id, name, type, icon, color, is_system) VALUES
(NULL, 'Ăn uống', 'expense', '🍜', '#FF6B6B', 1),
(NULL, 'Đi lại', 'expense', '🚗', '#FF9F43', 1),
(NULL, 'Mua sắm', 'expense', '🛍️', '#EE5A24', 1),
(NULL, 'Học tập', 'expense', '📚', '#6C5CE7', 1),
(NULL, 'Giải trí', 'expense', '🎬', '#A29BFE', 1),
(NULL, 'Sức khỏe', 'expense', '❤️', '#FD79A8', 1),
(NULL, 'Y tế', 'expense', '💊', '#E17055', 1),
(NULL, 'Tiền nhà', 'expense', '🏠', '#00B894', 1),
(NULL, 'Điện nước', 'expense', '⚡', '#FDCB6E', 1),
(NULL, 'Internet', 'expense', '📡', '#74B9FF', 1),
(NULL, 'Điện thoại', 'expense', '📱', '#A29BFE', 1),
(NULL, 'Quần áo', 'expense', '👕', '#74B9FF', 1),
(NULL, 'Làm đẹp', 'expense', '💄', '#FD79A8', 1),
(NULL, 'Thể thao', 'expense', '⚽', '#00B894', 1),
(NULL, 'Du lịch', 'expense', '✈️', '#0984E3', 1);

-- Insert system categories (Income)
INSERT INTO categories (user_id, name, type, icon, color, is_system) VALUES
(NULL, 'Lương', 'income', '💼', '#10B981', 1),
(NULL, 'Thưởng', 'income', '🎁', '#55EFC4', 1),
(NULL, 'Đầu tư', 'income', '📈', '#6C5CE7', 1),
(NULL, 'Kinh doanh', 'income', '💰', '#00B894', 1),
(NULL, 'Tiền làm thêm', 'income', '💵', '#A29BFE', 1),
(NULL, 'Thu nhập khác', 'income', '💸', '#74B9FF', 1);

-- Insert demo wallets
INSERT INTO wallets (user_id, name, type, balance, bank_name, icon, color) VALUES
(2, 'Ví tiền mặt', 'cash', 2500000, NULL, '💵', '#10B981'),
(2, 'Vietcombank', 'bank', 18700000, 'Vietcombank', '🏦', '#3B82F6'),
(2, 'MoMo', 'e_wallet', 850000, 'MoMo', '💜', '#A855F7'),
(2, 'ZaloPay', 'e_wallet', 450000, 'ZaloPay', '💙', '#0068FF'),
(2, 'BIDV Credit', 'credit_card', -3200000, 'BIDV', '💳', '#EF4444');

-- Insert demo transactions
INSERT INTO transactions (user_id, wallet_id, category_id, type, amount, description, transaction_date) VALUES
(2, 1, 1, 'expense', 45000, 'Bún bò Huế', CURDATE() - INTERVAL 1 DAY),
(2, 2, 2, 'expense', 35000, 'Grab đi làm', CURDATE() - INTERVAL 1 DAY),
(2, 3, 5, 'expense', 120000, 'CGV - Avengers', CURDATE() - INTERVAL 2 DAY),
(2, 1, 1, 'expense', 55000, 'Cà phê Highlands', CURDATE() - INTERVAL 2 DAY),
(2, 2, 16, 'income', 15000000, 'Lương tháng 5', CURDATE() - INTERVAL 3 DAY),
(2, 1, 3, 'expense', 320000, 'Shopee - Quần áo', CURDATE() - INTERVAL 4 DAY),
(2, 3, 2, 'expense', 52000, 'Grab về nhà', CURDATE() - INTERVAL 5 DAY),
(2, 1, 1, 'expense', 38000, 'Cơm trưa', CURDATE() - INTERVAL 5 DAY),
(2, 2, 17, 'income', 2500000, 'Thưởng dự án', CURDATE() - INTERVAL 6 DAY),
(2, 1, 9, 'expense', 380000, 'Tiền điện tháng 5', CURDATE() - INTERVAL 7 DAY);

-- Insert demo budgets
INSERT INTO budgets (user_id, category_id, name, amount, period, start_date, end_date) VALUES
(2, 1, 'Ăn uống tháng 5', 3000000, 'monthly', '2026-05-01', '2026-05-31'),
(2, 5, 'Giải trí', 1500000, 'monthly', '2026-05-01', '2026-05-31'),
(2, 3, 'Mua sắm', 2000000, 'monthly', '2026-05-01', '2026-05-31'),
(2, 2, 'Đi lại', 800000, 'monthly', '2026-05-01', '2026-05-31');

-- Insert demo savings goals
INSERT INTO savings_goals (user_id, name, target_amount, current_amount, deadline, icon, description) VALUES
(2, 'Mua Laptop mới', 25000000, 8500000, '2026-12-31', '💻', 'MacBook Pro M3'),
(2, 'Du lịch Đà Lạt', 10000000, 3200000, '2026-08-15', '✈️', 'Kỳ nghỉ hè gia đình'),
(2, 'Mua xe máy', 45000000, 12000000, '2027-06-30', '🏍️', 'Honda SH 350i');

-- Insert demo recurring bills
INSERT INTO recurring_bills (user_id, category_id, wallet_id, name, amount, frequency, start_date, next_due_date) VALUES
(2, 9, 2, 'Tiền điện', 400000, 'monthly', '2026-01-01', '2026-06-05'),
(2, 9, 2, 'Tiền nước', 150000, 'monthly', '2026-01-01', '2026-06-10'),
(2, 10, 2, 'Internet FPT', 200000, 'monthly', '2026-01-01', '2026-06-01'),
(2, 8, 2, 'Tiền thuê nhà', 5000000, 'monthly', '2026-01-01', '2026-06-01');

-- ══════════════════════════════════════════════════════════
-- V. VIEWS & PROCEDURES (Optional)
-- ══════════════════════════════════════════════════════════

-- View: Tổng quan tài chính người dùng
CREATE VIEW v_user_financial_summary AS
SELECT 
    u.id as user_id,
    u.full_name,
    COALESCE(SUM(CASE WHEN w.type != 'credit_card' THEN w.balance ELSE 0 END), 0) as total_assets,
    COALESCE(SUM(CASE WHEN w.type = 'credit_card' AND w.balance < 0 THEN ABS(w.balance) ELSE 0 END), 0) as total_debt,
    COALESCE(SUM(CASE WHEN t.type = 'income' AND MONTH(t.transaction_date) = MONTH(CURDATE()) THEN t.amount ELSE 0 END), 0) as monthly_income,
    COALESCE(SUM(CASE WHEN t.type = 'expense' AND MONTH(t.transaction_date) = MONTH(CURDATE()) THEN t.amount ELSE 0 END), 0) as monthly_expense
FROM users u
LEFT JOIN wallets w ON u.id = w.user_id AND w.is_active = 1
LEFT JOIN transactions t ON u.id = t.user_id
GROUP BY u.id, u.full_name;

-- ══════════════════════════════════════════════════════════
-- DONE! Database created successfully
-- Default login: demo@finora.vn / demo123
-- ══════════════════════════════════════════════════════════
