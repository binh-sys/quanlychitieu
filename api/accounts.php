<?php
/**
 * Accounts API
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/config.php';
session_start();

if (!Auth::check()) {
    Response::error('Unauthorized', 401);
}

$method = $_SERVER['REQUEST_METHOD'];
$userId = Auth::userId();
$db = Database::getInstance();

// Get all accounts
if ($method === 'GET' && !isset($_GET['id'])) {
    $stmt = $db->prepare("SELECT * FROM accounts WHERE user_id = ? AND is_active = 1 ORDER BY created_at ASC");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $accounts = [];
    while ($row = $result->fetch_assoc()) {
        $accounts[] = $row;
    }
    
    Response::success(['accounts' => $accounts]);
}

// Get single account
if ($method === 'GET' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    $stmt = $db->prepare("SELECT * FROM accounts WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        Response::error('Account not found', 404);
    }
    
    Response::success(['account' => $result->fetch_assoc()]);
}

// Create account
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $name = $data['name'] ?? '';
    $type = $data['type'] ?? 'cash';
    $balance = (float)($data['balance'] ?? 0);
    $color = $data['color'] ?? '#7C3AED';
    $icon = $data['icon'] ?? '💰';
    
    if (empty($name)) {
        Response::error('Tên tài khoản không được để trống');
    }
    
    if (!in_array($type, ['cash', 'bank', 'e_wallet', 'credit', 'investment'])) {
        Response::error('Loại tài khoản không hợp lệ');
    }
    
    $stmt = $db->prepare("INSERT INTO accounts (user_id, name, type, balance, color, icon) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issdss", $userId, $name, $type, $balance, $color, $icon);
    
    if ($stmt->execute()) {
        Response::success(['id' => $db->lastInsertId()], 'Thêm tài khoản thành công');
    } else {
        Response::error('Thêm tài khoản thất bại');
    }
}

// Update account
if ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = (int)($data['id'] ?? 0);
    
    $stmt = $db->prepare("SELECT * FROM accounts WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $userId);
    $stmt->execute();
    $account = $stmt->get_result()->fetch_assoc();
    
    if (!$account) {
        Response::error('Account not found', 404);
    }
    
    $name = $data['name'] ?? $account['name'];
    $type = $data['type'] ?? $account['type'];
    $balance = isset($data['balance']) ? (float)$data['balance'] : $account['balance'];
    $color = $data['color'] ?? $account['color'];
    $icon = $data['icon'] ?? $account['icon'];
    
    $stmt = $db->prepare("UPDATE accounts SET name = ?, type = ?, balance = ?, color = ?, icon = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ssdssii", $name, $type, $balance, $color, $icon, $id, $userId);
    
    if ($stmt->execute()) {
        Response::success([], 'Cập nhật tài khoản thành công');
    } else {
        Response::error('Cập nhật tài khoản thất bại');
    }
}

// Delete account
if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    
    // Check if account has transactions
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM transactions WHERE account_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $userId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result['count'] > 0) {
        Response::error('Không thể xóa tài khoản có giao dịch. Vui lòng xóa giao dịch trước.');
    }
    
    $stmt = $db->prepare("DELETE FROM accounts WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $userId);
    
    if ($stmt->execute()) {
        Response::success([], 'Xóa tài khoản thành công');
    } else {
        Response::error('Xóa tài khoản thất bại');
    }
}

Response::error('Invalid request', 404);
