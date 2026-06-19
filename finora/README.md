# 🚀 Hướng dẫn sử dụng Finora - Hệ thống Đăng nhập & Đăng ký

## 📋 Tổng quan

Hệ thống đăng nhập và đăng ký đã được hoàn thiện với các tính năng:

### ✨ Tính năng chính

#### 🔐 Đăng nhập (`login.php`)
- Đăng nhập bằng email và mật khẩu
- Tính năng "Ghi nhớ đăng nhập" (Remember Me)
- Bảo mật với password hashing (bcrypt)
- Ghi log hoạt động đăng nhập
- Cập nhật thời gian đăng nhập cuối
- Thông báo lỗi thân thiện
- Giao diện đẹp, responsive

#### 📝 Đăng ký (`register.php`)
- Form đăng ký với validation đầy đủ
- Kiểm tra email hợp lệ
- Kiểm tra mật khẩu tối thiểu 6 ký tự
- Xác nhận mật khẩu khớp
- Thanh đo độ mạnh mật khẩu (Password Strength Meter)
- Checkbox đồng ý điều khoản
- Tự động tạo ví mặc định khi đăng ký
- Ghi log hoạt động đăng ký
- Chuyển hướng tự động sau khi đăng ký thành công

#### 🏠 Dashboard (`dashboard.php`)
- Hiển thị tổng quan tài chính
- Thống kê: Tổng tài sản, Thu nhập, Chi tiêu, Nợ
- Danh sách giao dịch gần đây
- Giao diện thân thiện, dễ sử dụng

#### 🚪 Đăng xuất (`logout.php`)
- Xóa session an toàn
- Xóa cookie remember me
- Ghi log hoạt động đăng xuất
- Chuyển hướng về trang đăng nhập

## 🗂️ Cấu trúc file

```
finora/
├── index.php          # Trang chủ landing page
├── login.php          # Trang đăng nhập
├── register.php       # Trang đăng ký
├── dashboard.php      # Trang dashboard sau khi đăng nhập
├── logout.php         # Xử lý đăng xuất
└── README.md          # File này
```

## 🔧 Cài đặt

### 1. Chuẩn bị database

Đảm bảo bạn đã import file `database_finora.sql` vào MySQL:

```sql
-- File: database_finora.sql đã có sẵn
-- Chạy trong phpMyAdmin hoặc MySQL command line
```

### 2. Cấu hình kết nối database

File `connect.php` đã được cấu hình:

```php
$conn = mysqli_connect(
    "localhost",
    "root",
    "",
    "quanly_chitieu"
);
```

### 3. Khởi động XAMPP

- Bật Apache
- Bật MySQL
- Truy cập: `http://localhost/quanly_chitieu/finora/`

## 🎯 Hướng dẫn sử dụng

### Đăng ký tài khoản mới

1. Truy cập: `http://localhost/quanly_chitieu/finora/register.php`
2. Điền thông tin:
   - Họ và tên
   - Email (phải là email hợp lệ)
   - Mật khẩu (tối thiểu 6 ký tự)
   - Xác nhận mật khẩu
3. Đồng ý điều khoản sử dụng
4. Click "Tạo tài khoản"
5. Hệ thống sẽ tự động chuyển đến trang đăng nhập sau 2 giây

### Đăng nhập

1. Truy cập: `http://localhost/quanly_chitieu/finora/login.php`
2. Nhập email và mật khẩu
3. (Tùy chọn) Chọn "Ghi nhớ đăng nhập" để không phải đăng nhập lại
4. Click "Đăng nhập"
5. Hệ thống chuyển đến Dashboard

### Sử dụng Dashboard

- Xem tổng quan tài chính
- Xem giao dịch gần đây
- Thêm giao dịch mới (chức năng sẽ được phát triển)

### Đăng xuất

- Click nút "Đăng xuất" ở góc phải trên
- Hệ thống xóa session và chuyển về trang đăng nhập

## 🔒 Bảo mật

### Các biện pháp bảo mật đã triển khai:

1. **Password Hashing**: Sử dụng `password_hash()` với bcrypt
2. **SQL Injection Prevention**: Sử dụng Prepared Statements
3. **XSS Prevention**: Sử dụng `htmlspecialchars()` cho output
4. **Session Security**: Session-based authentication
5. **Activity Logging**: Ghi log tất cả hoạt động đăng nhập/đăng xuất
6. **Input Validation**: Kiểm tra đầu vào ở cả client và server

## 📊 Database Schema

### Bảng `users`
```sql
- id (Primary Key)
- username (Unique)
- email (Unique)
- password (Hashed)
- full_name
- role (admin/user)
- is_active
- created_at
- last_login
- remember_token
```

### Bảng `activity_logs`
```sql
- id (Primary Key)
- user_id (Foreign Key)
- action (login/logout/register)
- ip_address
- user_agent
- created_at
```

## 🎨 Giao diện

- **Design**: Modern, clean, professional
- **Font**: Plus Jakarta Sans (Google Fonts)
- **Colors**: 
  - Primary: #2563EB (Blue)
  - Success: #10B981 (Green)
  - Danger: #EF4444 (Red)
  - Warning: #F59E0B (Amber)
- **Responsive**: Hoạt động tốt trên mobile và desktop
- **Animation**: Smooth transitions và hover effects

## 🧪 Tài khoản demo

Sau khi import database, bạn có thể sử dụng:

```
Email: demo@finora.vn
Password: demo123
```

## 🐛 Xử lý lỗi

### Lỗi thường gặp:

1. **"Kết nối thất bại"**
   - Kiểm tra MySQL đã chạy chưa
   - Kiểm tra thông tin kết nối trong `connect.php`

2. **"Email đã được sử dụng"**
   - Email đã tồn tại trong hệ thống
   - Sử dụng email khác hoặc đăng nhập

3. **"Email hoặc mật khẩu không đúng"**
   - Kiểm tra lại thông tin đăng nhập
   - Đảm bảo tài khoản đã được kích hoạt (is_active = 1)

## 📝 TODO - Tính năng sẽ phát triển

- [ ] Quên mật khẩu (Forgot Password)
- [ ] Xác thực email (Email Verification)
- [ ] Đăng nhập bằng Google/Facebook
- [ ] Two-Factor Authentication (2FA)
- [ ] Thay đổi mật khẩu
- [ ] Cập nhật thông tin cá nhân
- [ ] Upload avatar

## 🤝 Hỗ trợ

Nếu gặp vấn đề, vui lòng:
1. Kiểm tra console browser (F12)
2. Kiểm tra error log của PHP
3. Kiểm tra bảng `activity_logs` để debug

## 📄 License

MIT License - Sử dụng tự do cho mục đích học tập và thương mại.

---

**Phát triển bởi Finora Team** 💰
**Version**: 1.0.0
**Last Updated**: May 31, 2026
