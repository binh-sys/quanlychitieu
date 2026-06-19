<?php
/**
 * Dashboard API - Statistics and Overview
 */
declare(strict_types=1);
require_once __DIR__ . '/../includes/config.php';
session_start();

if (!Auth::check()) {
    Response::error('Unauthorized', 401);
}

$userId = Auth::userId();
$db = Database::getInstance();
$month = $_GET['month'] ?? date('m');
$year = $_GET['year'] ?? date('Y');

// Get summary statistics
$stmt = $db->prepare("
    SELECT 
        SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income,
        SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expense,
        COUNT(*) as total_transactions
    FROM transactions
    WHERE user_id = ? 
    AND MONTH(transaction_date) = ? 
    AND YEAR(transaction_date) = ?
");
$stmt->bind_param("iii", $userId, $month, $year);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();

// Get top categories
$stmt = $db->prepare("
    SELECT c.name, c.icon, c.color, SUM(t.amount) as total, COUNT(*) as count
    FROM transactions t
    LEFT JOIN categories c ON t.category_id = c.id
    WHERE t.user_id = ? 
    AND t.type = 'expense'
    AND MONTH(t.transaction_date) = ? 
    AND YEAR(t.transaction_date) = ?
    GROUP BY t.category_id
    ORDER BY total DESC
    LIMIT 6
");
$stmt->bind_param("iii", $userId, $month, $year);
$stmt->execute();
$result = $stmt->get_result();

$topCategories = [];
while ($row = $result->fetch_assoc()) {
    $topCategories[] = $row;
}

// Get daily spending (last 30 days)
$stmt = $db->prepare("
    SELECT 
        transaction_date,
        SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income,
        SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense
    FROM transactions
    WHERE user_id = ? 
    AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY transaction_date
    ORDER BY transaction_date ASC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();

$dailyData = [];
while ($row = $result->fetch_assoc()) {
    $dailyData[] = $row;
}

Response::success([
    'summary' => [
        'income' => (float)$summary['total_income'],
        'expense' => (float)$summary['total_expense'],
        'net' => (float)$summary['total_income'] - (float)$summary['total_expense'],
        'total_transactions' => (int)$summary['total_transactions']
    ],
    'top_categories' => $topCategories,
    'daily_data' => $dailyData
]);
