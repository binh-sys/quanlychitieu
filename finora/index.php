<?php
session_start();
require_once __DIR__ . '/../connect.php';

$isLoggedIn = isset($_SESSION['user_id']);
$userName = '';
$userEmail = '';

if ($isLoggedIn) {
    $userId = $_SESSION['user_id'];
    $userName = $_SESSION['user_name'] ?? '';
    $userEmail = $_SESSION['user_email'] ?? '';
    
    $stmt = $conn->prepare("
        SELECT 
            COALESCE(SUM(CASE WHEN type = 'expense' AND MONTH(transaction_date) = MONTH(CURDATE()) THEN amount ELSE 0 END), 0) as monthly_expense,
            COALESCE(SUM(CASE WHEN type = 'income' AND MONTH(transaction_date) = MONTH(CURDATE()) THEN amount ELSE 0 END), 0) as monthly_income
        FROM transactions
        WHERE user_id = ?
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $userStats = $stmt->get_result()->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finora - Kiểm soát tài chính thông minh hơn với AI</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #2563EB;
            --primary-dark: #1d4ed8;
            --secondary: #10B981;
            --accent: #F59E0B;
            --danger: #EF4444;
            --dark: #0F172A;
            --dark-2: #1E293B;
            --gray: #64748B;
            --gray-light: #94A3B8;
            --bg-light: #F8FAFC;
            --border: #E2E8F0;
        }

        [data-theme="dark"] {
            --dark: #F8FAFC;
            --dark-2: #E2E8F0;
            --bg-light: #0F172A;
            --border: #1E293B;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-light);
            color: var(--dark);
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* Navigation */
        nav {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border);
            padding: 0 5%;
            height: 70px;
            display: flex;
            align-items: center;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            transition: all 0.3s;
        }

        nav.scrolled {
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .nav-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 800;
            font-size: 24px;
            text-decoration: none;
            color: var(--dark);
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .nav-links {
            display: flex;
            gap: 8px;
            margin-left: auto;
            margin-right: 20px;
        }

        .nav-link {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 500;
            color: var(--gray);
            text-decoration: none;
            transition: all 0.2s;
        }

        .nav-link:hover {
            background: var(--bg-light);
            color: var(--primary);
        }


        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            font-family: inherit;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), #1d4ed8);
            color: white;
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(37, 99, 235, 0.4);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--dark);
            color: var(--dark);
        }

        .btn-outline:hover {
            background: var(--dark);
            color: white;
        }

        /* User Menu */
        .user-menu {
            position: relative;
        }

        .user-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 6px 12px 6px 6px;
            background: var(--bg-light);
            border: 2px solid var(--border);
            border-radius: 100px;
            cursor: pointer;
            transition: all 0.2s;
        }

        .user-btn:hover {
            border-color: var(--primary);
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 14px;
        }

        .user-name {
            font-size: 14px;
            font-weight: 600;
            color: var(--dark);
        }

        .user-dropdown {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            min-width: 280px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s;
            z-index: 1001;
        }

        .user-dropdown.active {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-header {
            padding: 16px;
            border-bottom: 1px solid var(--border);
        }

        .dropdown-header-name {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .dropdown-header-email {
            font-size: 13px;
            color: var(--gray);
        }

        .dropdown-stats {
            padding: 12px 16px;
            background: var(--bg-light);
            border-bottom: 1px solid var(--border);
        }

        .dropdown-stat-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            font-size: 13px;
        }

        .stat-income { color: var(--secondary); font-weight: 700; }
        .stat-expense { color: var(--danger); font-weight: 700; }

        .dropdown-menu {
            padding: 8px;
        }

        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border-radius: 8px;
            text-decoration: none;
            color: var(--dark);
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .dropdown-item:hover {
            background: var(--bg-light);
            color: var(--primary);
        }

        .dropdown-item.logout {
            color: var(--danger);
        }

        .dropdown-item.logout:hover {
            background: rgba(239, 68, 68, 0.1);
        }


        /* Hero Section */
        .hero {
            padding: 140px 5% 80px;
            text-align: center;
            background: linear-gradient(180deg, #EFF6FF 0%, #F8FAFC 100%);
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(37, 99, 235, 0.1) 0%, transparent 70%);
            top: -200px;
            right: -200px;
            border-radius: 50%;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: white;
            border: 2px solid #BFDBFE;
            border-radius: 100px;
            padding: 8px 20px;
            font-size: 14px;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.1);
        }

        .badge-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--secondary);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.6; transform: scale(1.4); }
        }

        .hero h1 {
            font-size: 64px;
            font-weight: 900;
            line-height: 1.1;
            margin-bottom: 24px;
            letter-spacing: -2px;
        }

        .hero h1 .highlight {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-subtitle {
            font-size: 20px;
            color: var(--gray);
            max-width: 700px;
            margin: 0 auto 40px;
            line-height: 1.7;
        }

        .hero-cta {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            margin-bottom: 20px;
        }

        .hero-note {
            font-size: 14px;
            color: var(--gray-light);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .hero-image {
            max-width: 900px;
            margin: 60px auto 0;
            position: relative;
            z-index: 1;
        }

        .hero-image img {
            width: 100%;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
        }

        /* Features Section */
        .section {
            padding: 100px 5%;
        }

        .section-header {
            text-align: center;
            max-width: 700px;
            margin: 0 auto 60px;
        }

        .section-badge {
            display: inline-block;
            background: linear-gradient(135deg, #EFF6FF, #E0F2FE);
            color: var(--primary);
            padding: 6px 16px;
            border-radius: 100px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 16px;
        }

        .section h2 {
            font-size: 48px;
            font-weight: 800;
            margin-bottom: 16px;
            letter-spacing: -1px;
        }

        .section-subtitle {
            font-size: 18px;
            color: var(--gray);
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 30px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .feature-card {
            background: white;
            border: 2px solid var(--border);
            border-radius: 20px;
            padding: 40px 30px;
            transition: all 0.3s;
        }

        .feature-card:hover {
            border-color: var(--primary);
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(37, 99, 235, 0.1);
        }

        .feature-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 24px;
            background: linear-gradient(135deg, #EFF6FF, #DBEAFE);
        }

        .feature-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .feature-desc {
            font-size: 15px;
            color: var(--gray);
            line-height: 1.7;
        }


        /* Stats Section */
        .stats-section {
            background: linear-gradient(135deg, var(--dark), var(--dark-2));
            padding: 80px 5%;
            text-align: center;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 40px;
            max-width: 1000px;
            margin: 0 auto;
        }

        .stat-item {
            color: white;
        }

        .stat-number {
            font-size: 48px;
            font-weight: 800;
            margin-bottom: 8px;
            background: linear-gradient(135deg, white, rgba(255, 255, 255, 0.7));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .stat-label {
            font-size: 16px;
            color: rgba(255, 255, 255, 0.7);
        }

        /* CTA Section */
        .cta-section {
            padding: 100px 5%;
            text-align: center;
            background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%);
        }

        .cta-section h2 {
            font-size: 48px;
            font-weight: 800;
            margin-bottom: 20px;
        }

        .cta-section p {
            font-size: 20px;
            color: var(--gray);
            margin-bottom: 40px;
        }

        /* Footer */
        footer {
            background: var(--dark);
            color: rgba(255, 255, 255, 0.8);
            padding: 60px 5% 30px;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 40px;
            max-width: 1200px;
            margin: 0 auto 40px;
        }

        .footer-brand {
            color: white;
        }

        .footer-brand h3 {
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 16px;
        }

        .footer-brand p {
            font-size: 14px;
            line-height: 1.7;
            color: rgba(255, 255, 255, 0.6);
        }

        .footer-col h4 {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 20px;
            color: white;
        }

        .footer-links {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .footer-links a {
            color: rgba(255, 255, 255, 0.6);
            text-decoration: none;
            font-size: 14px;
            transition: color 0.2s;
        }

        .footer-links a:hover {
            color: white;
        }

        .footer-bottom {
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 14px;
            color: rgba(255, 255, 255, 0.5);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 40px;
            }
            
            .nav-links {
                display: none;
            }
            
            .features-grid {
                grid-template-columns: 1fr;
            }
            
            .footer-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<nav id="mainNav">
    <a href="index.php" class="nav-logo">
        <div class="logo-icon">💰</div>
        <span>Fi<span style="color: var(--primary)">no</span>ra</span>
    </a>
    
    <div class="nav-links">
        <a href="#features" class="nav-link">Tính năng</a>
        <a href="#how-it-works" class="nav-link">Cách dùng</a>
        <a href="#pricing" class="nav-link">Bảng giá</a>
    </div>
    
    <?php if ($isLoggedIn): ?>
        <div class="user-menu">
            <div class="user-btn" onclick="toggleUserMenu()">
                <div class="user-avatar"><?= strtoupper(substr($userName, 0, 2)) ?></div>
                <div class="user-name"><?= htmlspecialchars(explode(' ', $userName)[0]) ?></div>
                <i class="fas fa-chevron-down" style="font-size: 12px; color: var(--gray);"></i>
            </div>
            
            <div class="user-dropdown" id="userDropdown">
                <div class="dropdown-header">
                    <div class="dropdown-header-name"><?= htmlspecialchars($userName) ?></div>
                    <div class="dropdown-header-email"><?= htmlspecialchars($userEmail) ?></div>
                </div>
                <div class="dropdown-stats">
                    <div class="dropdown-stat-row">
                        <span>💰 Thu nhập tháng này</span>
                        <span class="stat-income"><?= number_format($userStats['monthly_income'] ?? 0, 0, ',', '.') ?>đ</span>
                    </div>
                    <div class="dropdown-stat-row">
                        <span>💸 Chi tiêu tháng này</span>
                        <span class="stat-expense"><?= number_format($userStats['monthly_expense'] ?? 0, 0, ',', '.') ?>đ</span>
                    </div>
                </div>
                <div class="dropdown-menu">
                    <a href="dashboard.php" class="dropdown-item">
                        <i class="fas fa-th-large"></i> Dashboard
                    </a>
                    <a href="#" class="dropdown-item">
                        <i class="fas fa-user"></i> Thông tin cá nhân
                    </a>
                    <a href="#" class="dropdown-item">
                        <i class="fas fa-wallet"></i> Chi tiêu cá nhân
                    </a>
                    <a href="logout.php" class="dropdown-item logout">
                        <i class="fas fa-sign-out-alt"></i> Đăng xuất
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <a href="auth.php" class="btn btn-outline">Đăng nhập</a>
        <a href="auth.php" class="btn btn-primary">Dùng thử miễn phí</a>
    <?php endif; ?>
</nav>


<!-- Hero Section -->
<section class="hero">
    <?php if ($isLoggedIn): ?>
        <div class="hero-badge">
            <div class="badge-dot"></div>
            Mới · Tính năng AI phân tích chi tiêu đã được cập nhật
        </div>
    <?php else: ?>
        <div class="hero-badge">
            <div class="badge-dot"></div>
            Mới · Tích hợp AI phân tích tài chính tức thì
        </div>
    <?php endif; ?>
    
    <h1>
        Kiểm soát tài chính<br>
        thông minh hơn với <span class="highlight">AI</span>
    </h1>
    
    <p class="hero-subtitle">
        Finora giúp bạn theo dõi thu chi, lập ngân sách thông minh và đạt mục tiêu tiết kiệm — 
        tất cả trong một nền tảng đẹp, dễ dùng.
    </p>
    
    <div class="hero-cta">
        <?php if ($isLoggedIn): ?>
            <a href="dashboard.php" class="btn btn-primary">
                <i class="fas fa-th-large"></i> Mở Dashboard ngay
            </a>
            <a href="#features" class="btn btn-outline">
                <i class="fas fa-play"></i> Xem demo
            </a>
        <?php else: ?>
            <a href="auth.php" class="btn btn-primary">
                <i class="fas fa-rocket"></i> Mở Dashboard ngay
            </a>
            <a href="#features" class="btn btn-outline">
                <i class="fas fa-play"></i> Xem demo
            </a>
        <?php endif; ?>
    </div>
    
    <div class="hero-note">
        <i class="fas fa-check-circle" style="color: var(--secondary);"></i>
        <span>Miễn phí mãi mãi · Không cần thẻ tín dụng · Cài đặt dưới 5 phút</span>
    </div>
    
    <div class="hero-image">
        <img src="data:image/svg+xml,%3Csvg width='900' height='500' xmlns='http://www.w3.org/2000/svg'%3E%3Crect width='900' height='500' fill='%230F172A' rx='20'/%3E%3Crect x='40' y='40' width='820' height='420' fill='%231E293B' rx='15'/%3E%3Ctext x='450' y='250' font-family='Arial' font-size='32' fill='%2394A3B8' text-anchor='middle'%3EDashboard Preview%3C/text%3E%3C/svg%3E" alt="Dashboard Preview">
    </div>
</section>

<!-- Stats Section -->
<section class="stats-section">
    <div class="stats-grid">
        <div class="stat-item">
            <div class="stat-number">15,000+</div>
            <div class="stat-label">Người dùng hoạt động</div>
        </div>
        <div class="stat-item">
            <div class="stat-number">₫2.1 Tỷ</div>
            <div class="stat-label">Tổng chi tiêu được theo dõi</div>
        </div>
        <div class="stat-item">
            <div class="stat-number">98%</div>
            <div class="stat-label">Khách hàng hài lòng</div>
        </div>
        <div class="stat-item">
            <div class="stat-number">4.9/5</div>
            <div class="stat-label">Đánh giá trung bình</div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="section" id="features">
    <div class="section-header">
        <span class="section-badge">🚀 Tính năng nổi bật</span>
        <h2>Mọi thứ bạn cần để làm chủ tài chính</h2>
        <p class="section-subtitle">Từ ghi chép hàng ngày đến phân tích AI chuyên sâu — Finora có tất cả</p>
    </div>
    
    <div class="features-grid">
        <div class="feature-card">
            <div class="feature-icon">📊</div>
            <h3 class="feature-title">Dashboard trực quan</h3>
            <p class="feature-desc">
                Toàn bộ bức tranh tài chính trên một màn hình. Biểu đồ realtime, KPI nổi bật, cảnh báo thông minh.
            </p>
        </div>
        
        <div class="feature-card">
            <div class="feature-icon">🤖</div>
            <h3 class="feature-title">AI phân tích chi tiêu</h3>
            <p class="feature-desc">
                Claude AI đọc hiểu dữ liệu của bạn, đưa ra gợi ý tiết kiệm và nhận xét xu hướng chi tiêu theo thời gian thực.
            </p>
        </div>
        
        <div class="feature-card">
            <div class="feature-icon">🎯</div>
            <h3 class="feature-title">Mục tiêu tiết kiệm</h3>
            <p class="feature-desc">
                Đặt mục tiêu, theo dõi tiến trình từng ngày. Progress bar trực quan và nhắc nhở thông minh giúp bạn không bỏ cuộc.
            </p>
        </div>
        
        <div class="feature-card">
            <div class="feature-icon">📋</div>
            <h3 class="feature-title">Ngân sách thông minh</h3>
            <p class="feature-desc">
                Tạo ngân sách theo danh mục. Cảnh báo tự động khi sắp vượt mức, giúp bạn chi tiêu có kỷ luật hơn.
            </p>
        </div>
        
        <div class="feature-card">
            <div class="feature-icon">📈</div>
            <h3 class="feature-title">Báo cáo chi tiết</h3>
            <p class="feature-desc">
                Báo cáo theo ngày, tuần, tháng, năm. Pie chart, bar chart, line chart — tất cả dữ liệu được hiển thị đẹp mắt.
            </p>
        </div>
        
        <div class="feature-card">
            <div class="feature-icon">🔒</div>
            <h3 class="feature-title">Bảo mật tuyệt đối</h3>
            <p class="feature-desc">
                Dữ liệu của bạn được mã hóa và bảo vệ bằng công nghệ hàng đầu. Chúng tôi cam kết không chia sẻ thông tin cá nhân.
            </p>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="cta-section">
    <h2>Sẵn sàng làm chủ tài chính?</h2>
    <p>Tham gia cùng 15,000+ người dùng đang kiểm soát tiền bạc thông minh hơn mỗi ngày</p>
    
    <div class="hero-cta">
        <a href="auth.php" class="btn btn-primary">
            <i class="fas fa-rocket"></i> Bắt đầu miễn phí ngay
        </a>
        <a href="#features" class="btn btn-outline">
            <i class="fas fa-book"></i> Xem tài liệu
        </a>
    </div>
</section>

<!-- Footer -->
<footer>
    <div class="footer-grid">
        <div class="footer-brand">
            <h3>Fi<span style="color: var(--primary)">no</span>ra</h3>
            <p>Ứng dụng quản lý tài chính cá nhân thông minh cho người Việt. Được xây dựng với ❤️ tại Việt Nam.</p>
        </div>
        
        <div class="footer-col">
            <h4>Sản phẩm</h4>
            <ul class="footer-links">
                <li><a href="#features">Tính năng</a></li>
                <li><a href="#pricing">Bảng giá</a></li>
                <li><a href="#">Roadmap</a></li>
                <li><a href="#">Changelog</a></li>
            </ul>
        </div>
        
        <div class="footer-col">
            <h4>Tài nguyên</h4>
            <ul class="footer-links">
                <li><a href="#">Tài liệu API</a></li>
                <li><a href="#">Hướng dẫn</a></li>
                <li><a href="#">Video tutorial</a></li>
                <li><a href="#">Cộng đồng</a></li>
            </ul>
        </div>
        
        <div class="footer-col">
            <h4>Công ty</h4>
            <ul class="footer-links">
                <li><a href="#">Về chúng tôi</a></li>
                <li><a href="#">Blog</a></li>
                <li><a href="#">Liên hệ</a></li>
                <li><a href="#">Careers</a></li>
            </ul>
        </div>
    </div>
    
    <div class="footer-bottom">
        <p>&copy; 2026 Finora. Made with ❤️ in Vietnam. All rights reserved.</p>
    </div>
</footer>

<script>
// Toggle user menu
function toggleUserMenu() {
    document.getElementById('userDropdown').classList.toggle('active');
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const userMenu = document.querySelector('.user-menu');
    if (userMenu && !userMenu.contains(event.target)) {
        document.getElementById('userDropdown')?.classList.remove('active');
    }
});

// Navbar scroll effect
window.addEventListener('scroll', function() {
    const nav = document.getElementById('mainNav');
    if (window.scrollY > 50) {
        nav.classList.add('scrolled');
    } else {
        nav.classList.remove('scrolled');
    }
});

// Smooth scroll for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }
    });
});
</script>

</body>
</html>
