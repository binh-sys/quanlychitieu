<?php
/**
 * Budgets API
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

// Get all budgets with spending
if ($method === 'GET' && !isset($_GET['id'])) {
    $month = $_GET['month'] ?? date('m');
    $year = $_GET['year'] ?? date('Y');
    
    $stmt = $db->prepare("
        SELECT b.*, 
               c.name as category_name, c.icon as category_icon, c.color as category_color,
               COALESCE(SUM(t.amount), 0) as spent
        FROM budgets b
        LEFT JOIN categories c ON b.category_id = c.id
        LEFT JOIN transactions t ON t.category_id = b.category_id 
            AND t.user_id = b.user_id 
            AND t.type = 'expense'
            AND MONTH(t.transaction_date) = ?
            AND YEAR(t.transaction_date) = ?
        WHERE b.user_id = ?
        GROUP BY b.id
        ORDER BY b.created_at DESC
    ");
    $stmt->bind_param("iii", $month, $year, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $budgets = [];
    while ($row = $result->fetch_assoc()) {
        $row['pct'] = $row['amount'] > 0 ? round(($row['spent'] / $row['amount']) * 100, 1) : 0;
        $budgets[] = $row;
    }
    
    Response::success(['budgets' => $budgets]);
}

// Create budget
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $categoryId = (int)($data['category_id'] ?? 0);
    $name = $data['name'] ?? '';
    $amount = (float)($data['amount'] ?? 0);
    $period = $data['period'] ?? 'monthly';
    $startDate = $data['start_date'] ?? date('Y-m-01');
    $endDate = $data['end_date'] ?? date('Y-m-t');
    
    if (empty($name)) {
        Response::error('Tên ngân sách không được để trống');
    }
    
    if ($amount <= 0) {
        Response::error('Số tiền phải lớn hơn 0');
    }
    
    $stmt = $db->prepare("INSERT INTO budgets (user_id, category_id, name, amount, period, start_date, end_date) 
                         VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iisdsss", $userId, $categoryId, $name, $amount, $period, $startDate, $endDate);
    
    if ($stmt->execute()) {
        Response::success(['id' => $db->lastInsertId()], 'Thêm ngân sách thành công');
    } else {
        Response::error('Thêm ngân sách thất bại');
    }
}

Response::error('Invalid request', 404);
