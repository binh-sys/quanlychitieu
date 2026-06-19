<?php
/**
 * Authentication API
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/config.php';
session_start();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Login
if ($method === 'POST' && $action === 'login') {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';

    if (empty($email) || empty($password)) {
        Response::error('Email và mật khẩu không được để trống');
    }

    $db = Database::getInstance();
    $stmt = $db->prepare("SELECT id, email, password, name FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    if (!$user || !password_verify($password, $user['password'])) {
        Response::error('Email hoặc mật khẩu không đúng', 401);
    }

    Auth::login($user['id']);
    Response::success([
        'user' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name']
        ]
    ], 'Đăng nhập thành công');
}

// Register
if ($method === 'POST' && $action === 'register') {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';
    $name = $data['name'] ?? '';

    if (empty($email) || empty($password) || empty($name)) {
        Response::error('Vui lòng điền đầy đủ thông tin');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        Response::error('Email không hợp lệ');
    }

    if (strlen($password) < 6) {
        Response::error('Mật khẩu phải có ít nhất 6 ký tự');
    }

    $db = Database::getInstance();
    
    // Check if email exists
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        Response::error('Email đã được sử dụng');
    }

    // Create user
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO users (email, password, name) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $email, $hashedPassword, $name);
    
    if ($stmt->execute()) {
        $userId = $db->lastInsertId();
        
        // Create default account
        $stmt = $db->prepare("INSERT INTO accounts (user_id, name, type, balance, icon) VALUES (?, 'Ví tiền mặt', 'cash', 0, '💵')");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        
        Auth::login($userId);
        Response::success(['user_id' => $userId], 'Đăng ký thành công');
    } else {
        Response::error('Đăng ký thất bại');
    }
}

// Logout
if ($method === 'POST' && $action === 'logout') {
    Auth::logout();
    Response::success([], 'Đăng xuất thành công');
}

// Get current user
if ($method === 'GET' && $action === 'user') {
    if (!Auth::check()) {
        Response::error('Chưa đăng nhập', 401);
    }
    Response::success(['user' => Auth::user()]);
}

Response::error('Invalid request', 404);
