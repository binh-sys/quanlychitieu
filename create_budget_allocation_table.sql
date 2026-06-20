-- Tạo bảng budget_allocations cho tính năng phân bổ thu nhập
-- Chạy file này trong MySQL để thêm bảng mới

USE quanly_chitieu;

CREATE TABLE IF NOT EXISTS budget_allocations (
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

-- Thêm dữ liệu mẫu cho user demo (user_id = 2)
INSERT INTO budget_allocations (user_id, category_id, name, percentage) VALUES
(2, 1, 'Tiền ăn uống', 30.0),
(2, 8, 'Tiền nhà', 40.0),
(2, 2, 'Tiền đi lại', 15.0),
(2, 5, 'Giải trí', 10.0);

SELECT 'Bảng budget_allocations đã được tạo thành công!' as message;
