# 💰 Expense Manager Pro

Hệ thống quản lý chi tiêu cá nhân hiện đại với giao diện đẹp mắt và đầy đủ tính năng.

## ✨ Tính năng

### 📊 Dashboard
- Tổng quan thu nhập, chi tiêu, số dư
- Biểu đồ chi tiêu theo danh mục
- Danh sách giao dịch gần đây
- Theo dõi ngân sách

### 💳 Quản lý giao dịch
- Thêm/sửa/xóa giao dịch
- Phân loại: Thu nhập, Chi tiêu, Chuyển khoản
- Lọc theo tháng, năm, loại giao dịch
- Ghi chú chi tiết cho mỗi giao dịch

### 🏦 Quản lý tài khoản
- Nhiều loại tài khoản: Tiền mặt, Ngân hàng, Ví điện tử, Thẻ tín dụng
- Theo dõi số dư từng tài khoản
- Tùy chỉnh màu sắc và icon

### 🎯 Ngân sách
- Đặt ngân sách theo danh mục
- Theo dõi % chi tiêu so với ngân sách
- Cảnh báo khi vượt ngân sách

### 🏷️ Danh mục
- Danh mục mặc định cho thu nhập và chi tiêu
- Tạo danh mục tùy chỉnh
- Icon và màu sắc đa dạng

## 🚀 Cài đặt

### Yêu cầu
- XAMPP (hoặc PHP 7.4+, MySQL 5.7+)
- Trình duyệt web hiện đại

### Các bước cài đặt

1. **Clone hoặc copy dự án vào thư mục htdocs của XAMPP**
   ```
   C:\xampp\htdocs\quanly_chitieu\
   ```

2. **Khởi động XAMPP**
   - Mở XAMPP Control Panel
   - Start Apache
   - Start MySQL

3. **Tạo cơ sở dữ liệu**
   - Truy cập: http://localhost/phpmyadmin
   - Tạo database mới tên `quanly_chitieu`
   - Import file `database.sql` vào database vừa tạo
   
   **Hoặc chạy lệnh SQL:**
   - Mở tab SQL trong phpMyAdmin
   - Copy toàn bộ nội dung file `database.sql`
   - Paste và Execute

4. **Cấu hình kết nối database** (nếu cần)
   - Mở file `includes/config.php`
   - Chỉnh sửa thông tin kết nối:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   define('DB_NAME', 'quanly_chitieu');
   ```

5. **Truy cập ứng dụng**
   ```
   http://localhost/quanly_chitieu/
   ```

## 🔐 Tài khoản demo

Sau khi import database, bạn có thể đăng nhập với:

- **Email:** demo@expense.vn
- **Mật khẩu:** demo123

Database đã có sẵn:
- 1 user demo
- 4 tài khoản mẫu
- 15 danh mục mặc định
- 10 giao dịch mẫu
- 4 ngân sách mẫu

## 📁 Cấu trúc thư mục

```
quanly_chitieu/
├── api/                    # Backend API endpoints
│   ├── auth.php           # Đăng nhập, đăng ký, đăng xuất
│   ├── transactions.php   # CRUD giao dịch
│   ├── accounts.php       # CRUD tài khoản
│   ├── categories.php     # CRUD danh mục
│   ├── budgets.php        # CRUD ngân sách
│   └── dashboard.php      # Thống kê dashboard
├── includes/              # Core files
│   └── config.php         # Database & Auth classes
├── js/                    # Frontend JavaScript
│   └── app.js            # Main application logic
├── index.php             # Entry point
├── database.sql          # Database schema & demo data
├── connect.php           # Legacy connection file
└── README.md             # Tài liệu này
```

## 🎨 Giao diện

- **Design:** Dark theme với màu tím chủ đạo
- **Font:** Be Vietnam Pro (UI), JetBrains Mono (số liệu)
- **Responsive:** Tối ưu cho desktop và mobile
- **Icons:** Emoji native

## 🔧 API Endpoints

### Authentication
- `POST /api/auth.php?action=login` - Đăng nhập
- `POST /api/auth.php?action=register` - Đăng ký
- `POST /api/auth.php?action=logout` - Đăng xuất
- `GET /api/auth.php?action=user` - Lấy thông tin user

### Transactions
- `GET /api/transactions.php` - Lấy danh sách giao dịch
- `GET /api/transactions.php?id={id}` - Lấy chi tiết giao dịch
- `POST /api/transactions.php` - Tạo giao dịch mới
- `PUT /api/transactions.php` - Cập nhật giao dịch
- `DELETE /api/transactions.php?id={id}` - Xóa giao dịch

### Accounts
- `GET /api/accounts.php` - Lấy danh sách tài khoản
- `POST /api/accounts.php` - Tạo tài khoản mới
- `PUT /api/accounts.php` - Cập nhật tài khoản
- `DELETE /api/accounts.php?id={id}` - Xóa tài khoản

### Categories
- `GET /api/categories.php` - Lấy danh sách danh mục
- `POST /api/categories.php` - Tạo danh mục mới

### Budgets
- `GET /api/budgets.php` - Lấy danh sách ngân sách
- `POST /api/budgets.php` - Tạo ngân sách mới

### Dashboard
- `GET /api/dashboard.php` - Lấy thống kê tổng quan

## 🛠️ Công nghệ sử dụng

### Backend
- PHP 7.4+ (OOP, PDO prepared statements)
- MySQL 5.7+
- RESTful API design

### Frontend
- Vanilla JavaScript (ES6+)
- Module pattern
- Fetch API
- No framework dependency

### Security
- Password hashing (bcrypt)
- SQL injection prevention (prepared statements)
- XSS protection
- Session management

## 📝 Tính năng sắp tới

- [ ] Báo cáo chi tiết với biểu đồ
- [ ] Export dữ liệu (Excel, PDF)
- [ ] Nhắc nhở thanh toán định kỳ
- [ ] Đa người dùng và phân quyền
- [ ] Backup/Restore dữ liệu
- [ ] Dark/Light theme toggle
- [ ] Multi-currency support

## 🐛 Troubleshooting

### Lỗi kết nối database
- Kiểm tra MySQL đã chạy chưa
- Kiểm tra thông tin kết nối trong `includes/config.php`
- Kiểm tra database `quanly_chitieu` đã được tạo chưa

### Lỗi 404 Not Found
- Kiểm tra đường dẫn: `http://localhost/quanly_chitieu/`
- Kiểm tra Apache đã chạy chưa

### Không hiển thị dữ liệu
- Mở Console (F12) để xem lỗi JavaScript
- Kiểm tra Network tab để xem API response
- Kiểm tra đã import `database.sql` chưa

## 📄 License

MIT License - Free to use for personal and commercial projects.

## 👨‍💻 Author

Developed with ❤️ by Kiro AI Assistant

---

**Chúc bạn quản lý chi tiêu hiệu quả! 💰✨**
