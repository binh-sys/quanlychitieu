<?php
session_start();
require_once __DIR__ . '/../connect.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

if (isset($_GET['logout']) && $_GET['logout'] === 'success') {
    $success = 'Đăng xuất thành công!';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (empty($email) || empty($password)) {
        $error = 'Vui lòng nhập đầy đủ email và mật khẩu';
    } else {
        $stmt = $conn->prepare("SELECT id, email, password, full_name, role FROM users WHERE email = ? AND is_active = 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role'];

            $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->bind_param("i", $user['id']);
            $updateStmt->execute();

            if ($remember) {
                $token = bin2hex(random_bytes(32));
                setcookie('remember_token', $token, time() + (86400 * 30), '/');
                $updateToken = $conn->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
                $updateToken->bind_param("si", $token, $user['id']);
                $updateToken->execute();
            }

            $ip = $_SERVER['REMOTE_ADDR'];
            $userAgent = $_SERVER['HTTP_USER_AGENT'];
            $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, ip_address, user_agent) VALUES (?, 'login', ?, ?)");
            $logStmt->bind_param("iss", $user['id'], $ip, $userAgent);
            $logStmt->execute();

            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Email hoặc mật khẩu không đúng';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - Finora</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #E0F2FE 0%, #BAE6FD 50%, #7DD3FC 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        /* Animated background icons */
        body::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            background-image: 
                url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Ctext x='10' y='40' font-size='30' opacity='0.1'%3E💰%3C/text%3E%3C/svg%3E"),
                url("data:image/svg+xml,%3Csvg width='80' height='80' viewBox='0 0 80 80' xmlns='http://www.w3.org/2000/svg'%3E%3Ctext x='15' y='55' font-size='40' opacity='0.08'%3E📊%3C/text%3E%3C/svg%3E"),
                url("data:image/svg+xml,%3Csvg width='70' height='70' viewBox='0 0 70 70' xmlns='http://www.w3.org/2000/svg'%3E%3Ctext x='12' y='48' font-size='35' opacity='0.09'%3E💳%3C/text%3E%3C/svg%3E");
            background-position: 0 0, 40px 60px, 130px 270px;
            background-size: 200px 200px;
            animation: float 20s linear infinite;
            pointer-events: none;
        }

        @keyframes float {
            0% { background-position: 0 0, 40px 60px, 130px 270px; }
            100% { background-position: 200px 200px, 240px 260px, 330px 470px; }
        }

        .login-wrapper {
            display: flex;
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            max-width: 1000px;
            width: 100%;
            min-height: 600px;
            position: relative;
            z-index: 1;
            animation: slideIn 0.6s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }


        /* Left side - Illustration */
        .login-left {
            flex: 1;
            background: linear-gradient(135deg, #0EA5E9 0%, #2563EB 100%);
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .login-left::before {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            top: -100px;
            right: -100px;
        }

        .login-left::after {
            content: '';
            position: absolute;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 50%;
            bottom: -50px;
            left: -50px;
        }

        .illustration {
            position: relative;
            z-index: 1;
            text-align: center;
        }

        .illustration-title {
            color: white;
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 20px;
            line-height: 1.3;
        }

        .illustration-image {
            width: 100%;
            max-width: 400px;
            margin: 30px 0;
            filter: drop-shadow(0 10px 30px rgba(0, 0, 0, 0.2));
        }

        .feature-list {
            list-style: none;
            margin-top: 30px;
            text-align: left;
        }

        .feature-item {
            color: rgba(255, 255, 255, 0.95);
            font-size: 15px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .feature-item i {
            width: 24px;
            height: 24px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }


        /* Right side - Login Form */
        .login-right {
            flex: 1;
            padding: 60px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #1F2937;
        }

        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .brand-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 30px;
        }

        .brand-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #0EA5E9, #2563EB);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .brand-name {
            font-size: 32px;
            font-weight: 800;
            color: white;
        }

        .brand-name span {
            color: #0EA5E9;
        }

        .login-title {
            font-size: 24px;
            font-weight: 700;
            color: white;
            margin-bottom: 8px;
        }

        .login-subtitle {
            color: #9CA3AF;
            font-size: 14px;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: #FCA5A5;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: #6EE7B7;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }


        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            color: #D1D5DB;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #6B7280;
            font-size: 16px;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 14px 16px 14px 45px;
            background: #374151;
            border: 2px solid #4B5563;
            border-radius: 10px;
            color: white;
            font-size: 15px;
            font-family: inherit;
            transition: all 0.3s;
        }

        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #0EA5E9;
            background: #2D3748;
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
        }

        input::placeholder {
            color: #6B7280;
        }

        .password-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #6B7280;
            cursor: pointer;
            font-size: 16px;
            transition: color 0.2s;
        }

        .password-toggle:hover {
            color: #0EA5E9;
        }

        .form-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }


        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #D1D5DB;
            font-size: 14px;
            cursor: pointer;
        }

        .remember-me input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #0EA5E9;
        }

        .forgot-link {
            color: #0EA5E9;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: color 0.2s;
        }

        .forgot-link:hover {
            color: #38BDF8;
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #0EA5E9, #2563EB);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            font-family: inherit;
            box-shadow: 0 4px 15px rgba(14, 165, 233, 0.3);
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(14, 165, 233, 0.4);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .divider {
            text-align: center;
            margin: 30px 0;
            position: relative;
            color: #6B7280;
            font-size: 13px;
        }

        .divider::before,
        .divider::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 42%;
            height: 1px;
            background: #374151;
        }

        .divider::before { left: 0; }
        .divider::after { right: 0; }


        .social-login {
            display: flex;
            gap: 12px;
            margin-bottom: 30px;
        }

        .social-btn {
            flex: 1;
            padding: 12px;
            background: #374151;
            border: 2px solid #4B5563;
            border-radius: 10px;
            color: white;
            font-size: 20px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .social-btn:hover {
            background: #2D3748;
            border-color: #0EA5E9;
            transform: translateY(-2px);
        }

        .register-link {
            text-align: center;
            color: #9CA3AF;
            font-size: 14px;
        }

        .register-link a {
            color: #0EA5E9;
            font-weight: 700;
            text-decoration: none;
            transition: color 0.2s;
        }

        .register-link a:hover {
            color: #38BDF8;
        }

        .back-home {
            position: absolute;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 2;
        }

        .back-home a {
            color: #1F2937;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: white;
            border-radius: 100px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s;
        }

        .back-home a:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }


        /* Responsive */
        @media (max-width: 768px) {
            .login-wrapper {
                flex-direction: column;
                max-width: 500px;
            }

            .login-left {
                padding: 40px 30px;
                min-height: 300px;
            }

            .illustration-title {
                font-size: 22px;
            }

            .illustration-image {
                max-width: 250px;
            }

            .feature-list {
                display: none;
            }

            .login-right {
                padding: 40px 30px;
            }

            .back-home {
                position: static;
                transform: none;
                margin-top: 20px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <!-- Left Side - Illustration -->
        <div class="login-left">
            <div class="illustration">
                <h1 class="illustration-title">
                    PHẦN MỀM QUẢN LÝ<br>TÀI CHÍNH CÁ NHÂN
                </h1>
                
                <svg class="illustration-image" viewBox="0 0 400 300" xmlns="http://www.w3.org/2000/svg">
                    <!-- Phone with charts -->
                    <rect x="140" y="40" width="120" height="200" rx="15" fill="#fff" opacity="0.95"/>
                    <rect x="150" y="50" width="100" height="180" rx="8" fill="#E0F2FE"/>
                    
                    <!-- Chart bars -->
                    <rect x="160" y="140" width="15" height="60" rx="3" fill="#0EA5E9"/>
                    <rect x="180" y="120" width="15" height="80" rx="3" fill="#2563EB"/>
                    <rect x="200" y="100" width="15" height="100" rx="3" fill="#0EA5E9"/>
                    <rect x="220" y="130" width="15" height="70" rx="3" fill="#2563EB"/>
                    
                    <!-- Money coins -->
                    <circle cx="80" cy="180" r="25" fill="#FCD34D" opacity="0.9"/>
                    <text x="80" y="190" text-anchor="middle" font-size="24" fill="#92400E">$</text>
                    
                    <circle cx="320" cy="160" r="20" fill="#FCD34D" opacity="0.8"/>
                    <text x="320" y="168" text-anchor="middle" font-size="20" fill="#92400E">$</text>
                    
                    <!-- Credit card -->
                    <rect x="50" y="80" width="80" height="50" rx="8" fill="#1F2937" opacity="0.9"/>
                    <rect x="60" y="95" width="30" height="20" rx="3" fill="#FCD34D"/>
                    
                    <!-- Pie chart -->
                    <circle cx="320" cy="80" r="30" fill="#10B981" opacity="0.3"/>
                    <path d="M 320 50 A 30 30 0 0 1 340 100 L 320 80 Z" fill="#0EA5E9" opacity="0.8"/>
                    <path d="M 320 80 L 340 100 A 30 30 0 0 1 300 100 Z" fill="#2563EB" opacity="0.8"/>
                </svg>

                <ul class="feature-list">
                    <li class="feature-item">
                        <i class="fas fa-check"></i>
                        <span>Quản lý thu chi thông minh</span>
                    </li>
                    <li class="feature-item">
                        <i class="fas fa-check"></i>
                        <span>Báo cáo chi tiết trực quan</span>
                    </li>
                    <li class="feature-item">
                        <i class="fas fa-check"></i>
                        <span>Bảo mật tuyệt đối</span>
                    </li>
                </ul>
            </div>
        </div>


        <!-- Right Side - Login Form -->
        <div class="login-right">
            <div class="login-header">
                <div class="brand-logo">
                    <div class="brand-icon">💰</div>
                    <div class="brand-name">Fi<span>no</span>ra</div>
                </div>
                <h2 class="login-title">Đăng nhập</h2>
                <p class="login-subtitle">Chào mừng bạn quay trở lại!</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email hoặc Số điện thoại</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope input-icon"></i>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            placeholder="Email hoặc Số điện thoại"
                            value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                            required
                            autofocus
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Mật khẩu</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            placeholder="Nhập mật khẩu"
                            required
                        >
                        <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                    </div>
                </div>

                <div class="form-footer">
                    <label class="remember-me">
                        <input type="checkbox" name="remember" id="remember">
                        <span>Ghi nhớ đăng nhập</span>
                    </label>
                    <a href="#" class="forgot-link">Quên mật khẩu?</a>
                </div>

                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Đăng nhập
                </button>
            </form>

            <div class="divider">Hoặc đăng nhập bằng</div>

            <div class="social-login">
                <button class="social-btn" title="Đăng nhập với Google">
                    <i class="fab fa-google"></i>
                </button>
                <button class="social-btn" title="Đăng nhập với Facebook">
                    <i class="fab fa-facebook-f"></i>
                </button>
                <button class="social-btn" title="Đăng nhập với Apple">
                    <i class="fab fa-apple"></i>
                </button>
            </div>

            <div class="register-link">
                Chưa có tài khoản? <a href="register.php">Đăng ký ngay</a>
            </div>
        </div>
    </div>

    <div class="back-home">
        <a href="index.php">
            <i class="fas fa-arrow-left"></i>
            <span>Quay về trang chủ</span>
        </a>
    </div>

    <script>
        // Toggle password visibility
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');

        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    </script>
</body>
</html>
