<?php
session_start();
require_once __DIR__ . '/../connect.php';

// Nếu đã đăng nhập, chuyển về dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $agree_terms = isset($_POST['agree_terms']);

    // Validation
    if (empty($full_name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Vui lòng điền đầy đủ thông tin';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email không hợp lệ';
    } elseif (strlen($password) < 6) {
        $error = 'Mật khẩu phải có ít nhất 6 ký tự';
    } elseif ($password !== $confirm_password) {
        $error = 'Mật khẩu xác nhận không khớp';
    } elseif (!$agree_terms) {
        $error = 'Bạn phải đồng ý với điều khoản sử dụng';
    } else {
        // Check if email exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error = 'Email đã được sử dụng';
        } else {
            // Create username from email
            $username = explode('@', $email)[0] . rand(100, 999);
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Insert user
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name, role) VALUES (?, ?, ?, ?, 'user')");
            $stmt->bind_param("ssss", $username, $email, $hashedPassword, $full_name);

            if ($stmt->execute()) {
                $userId = $conn->insert_id;

                // Create default wallet
                $walletStmt = $conn->prepare("INSERT INTO wallets (user_id, name, type, balance, icon, color) VALUES (?, 'Ví tiền mặt', 'cash', 0, '💵', '#10B981')");
                $walletStmt->bind_param("i", $userId);
                $walletStmt->execute();

                // Log activity
                $ip = $_SERVER['REMOTE_ADDR'];
                $userAgent = $_SERVER['HTTP_USER_AGENT'];
                $logStmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, ip_address, user_agent) VALUES (?, 'register', ?, ?)");
                $logStmt->bind_param("iss", $userId, $ip, $userAgent);
                $logStmt->execute();

                $success = 'Đăng ký thành công! Đang chuyển đến trang đăng nhập...';
                header("refresh:2;url=auth.php");
            } else {
                $error = 'Đăng ký thất bại. Vui lòng thử lại';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký - Finora</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #2563EB;
            --primary-dark: #1d4ed8;
            --success: #10B981;
            --danger: #EF4444;
            --bg: #F8FAFC;
            --card: #FFFFFF;
            --text: #0F172A;
            --text-light: #475569;
            --border: #E2E8F0;
        }


        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .register-container {
            background: var(--card);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 480px;
            padding: 48px 40px;
            animation: slideUp 0.5s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 12px;
        }

        .logo-icon {
            width: 48px;
            height: 48px;
            background: var(--primary);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .logo-text {
            font-size: 28px;
            font-weight: 800;
            color: var(--text);
            letter-spacing: -0.5px;
        }


        .logo-text span {
            color: var(--primary);
        }

        h1 {
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 8px;
        }

        .subtitle {
            text-align: center;
            color: var(--text-light);
            font-size: 14px;
            margin-bottom: 32px;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
        }

        .alert-error {
            background: #FEE2E2;
            color: #991B1B;
            border: 1px solid #FCA5A5;
        }

        .alert-success {
            background: #D1FAE5;
            color: #065F46;
            border: 1px solid #6EE7B7;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 8px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 18px;
            color: var(--text-light);
        }


        input[type="text"],
        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 12px 14px 12px 44px;
            border: 2px solid var(--border);
            border-radius: 10px;
            font-size: 15px;
            font-family: inherit;
            transition: all 0.2s;
            background: var(--bg);
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: var(--primary);
            background: white;
        }

        .terms {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin: 20px 0;
            font-size: 13px;
            color: var(--text-light);
        }

        .terms input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            margin-top: 2px;
        }

        .terms a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }

        .terms a:hover {
            text-decoration: underline;
        }

        .btn {
            width: 100%;
            padding: 14px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
        }


        .btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.3);
        }

        .btn:active {
            transform: translateY(0);
        }

        .divider {
            text-align: center;
            margin: 24px 0;
            position: relative;
            color: var(--text-light);
            font-size: 13px;
        }

        .divider::before,
        .divider::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 40%;
            height: 1px;
            background: var(--border);
        }

        .divider::before {
            left: 0;
        }

        .divider::after {
            right: 0;
        }

        .login-link {
            text-align: center;
            font-size: 14px;
            color: var(--text-light);
        }

        .login-link a {
            color: var(--primary);
            font-weight: 700;
            text-decoration: none;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

        .back-home {
            text-align: center;
            margin-top: 20px;
        }

        .back-home a {
            color: white;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            opacity: 0.9;
        }


        .back-home a:hover {
            opacity: 1;
            text-decoration: underline;
        }

        .password-strength {
            margin-top: 8px;
            font-size: 12px;
        }

        .strength-bar {
            height: 4px;
            background: var(--border);
            border-radius: 2px;
            margin-top: 6px;
            overflow: hidden;
        }

        .strength-fill {
            height: 100%;
            width: 0%;
            transition: all 0.3s;
            border-radius: 2px;
        }

        .strength-weak { width: 33%; background: #EF4444; }
        .strength-medium { width: 66%; background: #F59E0B; }
        .strength-strong { width: 100%; background: #10B981; }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="logo">
            <div class="logo-icon">💰</div>
            <div class="logo-text">Fi<span>no</span>ra</div>
        </div>
        
        <h1>Tạo tài khoản mới</h1>
        <p class="subtitle">Bắt đầu hành trình quản lý tài chính thông minh</p>

        <?php if ($error): ?>
            <div class="alert alert-error">
                ⚠️ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                ✅ <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>


        <form method="POST" action="" id="registerForm">
            <div class="form-group">
                <label for="full_name">Họ và tên</label>
                <div class="input-wrapper">
                    <span class="input-icon">👤</span>
                    <input 
                        type="text" 
                        id="full_name" 
                        name="full_name" 
                        placeholder="Nguyễn Văn A"
                        value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>"
                        required
                        autofocus
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <div class="input-wrapper">
                    <span class="input-icon">📧</span>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        placeholder="your@email.com"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        required
                    >
                </div>
            </div>

            <div class="form-group">
                <label for="password">Mật khẩu</label>
                <div class="input-wrapper">
                    <span class="input-icon">🔒</span>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="Ít nhất 6 ký tự"
                        required
                    >
                </div>
                <div class="password-strength">
                    <div class="strength-bar">
                        <div class="strength-fill" id="strengthFill"></div>
                    </div>
                    <span id="strengthText"></span>
                </div>
            </div>


            <div class="form-group">
                <label for="confirm_password">Xác nhận mật khẩu</label>
                <div class="input-wrapper">
                    <span class="input-icon">🔐</span>
                    <input 
                        type="password" 
                        id="confirm_password" 
                        name="confirm_password" 
                        placeholder="Nhập lại mật khẩu"
                        required
                    >
                </div>
            </div>

            <label class="terms">
                <input type="checkbox" name="agree_terms" id="agree_terms" required>
                <span>Tôi đồng ý với <a href="#">Điều khoản sử dụng</a> và <a href="#">Chính sách bảo mật</a> của Finora</span>
            </label>

            <button type="submit" class="btn">
                🚀 Tạo tài khoản
            </button>
        </form>

        <div class="divider">hoặc</div>

        <div class="login-link">
            Đã có tài khoản? <a href="auth.php">Đăng nhập ngay</a>
        </div>
    </div>

    <div class="back-home">
        <a href="index.php">← Quay về trang chủ</a>
    </div>

    <script>
        // Password strength checker
        const passwordInput = document.getElementById('password');
        const strengthFill = document.getElementById('strengthFill');
        const strengthText = document.getElementById('strengthText');

        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;

            if (password.length >= 6) strength++;
            if (password.length >= 10) strength++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/\d/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;


            strengthFill.className = 'strength-fill';
            
            if (strength <= 2) {
                strengthFill.classList.add('strength-weak');
                strengthText.textContent = 'Mật khẩu yếu';
                strengthText.style.color = '#EF4444';
            } else if (strength <= 3) {
                strengthFill.classList.add('strength-medium');
                strengthText.textContent = 'Mật khẩu trung bình';
                strengthText.style.color = '#F59E0B';
            } else {
                strengthFill.classList.add('strength-strong');
                strengthText.textContent = 'Mật khẩu mạnh';
                strengthText.style.color = '#10B981';
            }
        });

        // Confirm password validation
        const confirmPassword = document.getElementById('confirm_password');
        const form = document.getElementById('registerForm');

        form.addEventListener('submit', function(e) {
            if (passwordInput.value !== confirmPassword.value) {
                e.preventDefault();
                alert('Mật khẩu xác nhận không khớp!');
                confirmPassword.focus();
            }
        });
    </script>
</body>
</html>
