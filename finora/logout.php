<?php
session_start();
require_once __DIR__ . '/../connect.php';

// Log activity before logout
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $ip = $_SERVER['REMOTE_ADDR'];
    $userAgent = $_SERVER['HTTP_USER_AGENT'];
    
    // Verify user exists before logging
    $checkUser = $conn->prepare("SELECT id FROM users WHERE id = ?");
    $checkUser->bind_param("i", $userId);
    $checkUser->execute();
    $result = $checkUser->get_result();
    
    if ($result->num_rows > 0) {
        $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, ip_address, user_agent) VALUES (?, 'logout', ?, ?)");
        $stmt->bind_param("iss", $userId, $ip, $userAgent);
        $stmt->execute();
        $stmt->close();
    }
    $checkUser->close();
}

// Clear session
session_unset();
session_destroy();

// Clear remember me cookie
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// Redirect to login
header('Location: login.php?logout=success');
exit;
?>
