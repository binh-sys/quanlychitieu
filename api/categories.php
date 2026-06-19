<?php
/**
 * Categories API
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

// Get all categories
if ($method === 'GET') {
    $type = $_GET['type'] ?? '';
    
    $sql = "SELECT * FROM categories WHERE (user_id = ? OR is_system = 1)";
    $params = [$userId];
    $types = "i";
    
    if ($type) {
        $sql .= " AND type = ?";
        $params[] = $type;
        $types .= "s";
    }
    
    $sql .= " ORDER BY is_system DESC, name ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
    
    Response::success(['categories' => $categories]);
}

// Create category
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $name = $data['name'] ?? '';
    $type = $data['type'] ?? 'expense';
    $icon = $data['icon'] ?? '📦';
    $color = $data['color'] ?? '#7C3AED';
    
    if (empty($name)) {
        Response::error('Tên danh mục không được để trống');
    }
    
    if (!in_array($type, ['expense', 'income'])) {
        Response::error('Loại danh mục không hợp lệ');
    }
    
    $stmt = $db->prepare("INSERT INTO categories (user_id, name, type, icon, color) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $userId, $name, $type, $icon, $color);
    
    if ($stmt->execute()) {
        Response::success(['id' => $db->lastInsertId()], 'Thêm danh mục thành công');
    } else {
        Response::error('Thêm danh mục thất bại');
    }
}

Response::error('Invalid request', 404);
