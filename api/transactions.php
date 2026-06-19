<?php
/**
 * Transactions API
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

// Get all transactions
if ($method === 'GET' && !isset($_GET['id'])) {
    $month = $_GET['month'] ?? date('m');
    $year = $_GET['year'] ?? date('Y');
    $type = $_GET['type'] ?? '';
    
    $sql = "SELECT t.*, 
            c.name as category_name, c.icon as category_icon, c.color as category_color,
            a.name as account_name, a.icon as account_icon
            FROM transactions t
            LEFT JOIN categories c ON t.category_id = c.id
            LEFT JOIN accounts a ON t.account_id = a.id
            WHERE t.user_id = ?";
    
    $params = [$userId];
    $types = "i";
    
    if ($month && $year) {
        $sql .= " AND MONTH(t.transaction_date) = ? AND YEAR(t.transaction_date) = ?";
        $params[] = $month;
        $params[] = $year;
        $types .= "ii";
    }
    
    if ($type) {
        $sql .= " AND t.type = ?";
        $params[] = $type;
        $types .= "s";
    }
    
    $sql .= " ORDER BY t.transaction_date DESC, t.id DESC";
    
    $stmt = $db->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }
    
    Response::success(['transactions' => $transactions]);
}

// Get single transaction
if ($method === 'GET' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    $stmt = $db->prepare("SELECT t.*, 
            c.name as category_name, c.icon as category_icon,
            a.name as account_name
            FROM transactions t
            LEFT JOIN categories c ON t.category_id = c.id
            LEFT JOIN accounts a ON t.account_id = a.id
            WHERE t.id = ? AND t.user_id = ?");
    $stmt->bind_param("ii", $id, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        Response::error('Transaction not found', 404);
    }
    
    Response::success(['transaction' => $result->fetch_assoc()]);
}

// Create transaction
if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $accountId = (int)($data['account_id'] ?? 0);
    $categoryId = (int)($data['category_id'] ?? 0);
    $type = $data['type'] ?? 'expense';
    $amount = (float)($data['amount'] ?? 0);
    $description = $data['description'] ?? '';
    $note = $data['note'] ?? '';
    $transactionDate = $data['transaction_date'] ?? date('Y-m-d');
    
    if ($amount <= 0) {
        Response::error('Số tiền phải lớn hơn 0');
    }
    
    if (!in_array($type, ['expense', 'income', 'transfer'])) {
        Response::error('Loại giao dịch không hợp lệ');
    }
    
    // Verify account belongs to user
    $stmt = $db->prepare("SELECT id FROM accounts WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $accountId, $userId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        Response::error('Tài khoản không hợp lệ');
    }
    
    // Insert transaction
    $stmt = $db->prepare("INSERT INTO transactions (user_id, account_id, category_id, type, amount, description, note, transaction_date) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iiisdsss", $userId, $accountId, $categoryId, $type, $amount, $description, $note, $transactionDate);
    
    if ($stmt->execute()) {
        // Update account balance
        $multiplier = $type === 'income' ? 1 : -1;
        $stmt = $db->prepare("UPDATE accounts SET balance = balance + ? WHERE id = ?");
        $balanceChange = $amount * $multiplier;
        $stmt->bind_param("di", $balanceChange, $accountId);
        $stmt->execute();
        
        Response::success(['id' => $db->lastInsertId()], 'Thêm giao dịch thành công');
    } else {
        Response::error('Thêm giao dịch thất bại');
    }
}

// Update transaction
if ($method === 'PUT') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = (int)($data['id'] ?? 0);
    
    // Get old transaction
    $stmt = $db->prepare("SELECT * FROM transactions WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $userId);
    $stmt->execute();
    $oldTxn = $stmt->get_result()->fetch_assoc();
    
    if (!$oldTxn) {
        Response::error('Transaction not found', 404);
    }
    
    $accountId = (int)($data['account_id'] ?? $oldTxn['account_id']);
    $categoryId = (int)($data['category_id'] ?? $oldTxn['category_id']);
    $type = $data['type'] ?? $oldTxn['type'];
    $amount = (float)($data['amount'] ?? $oldTxn['amount']);
    $description = $data['description'] ?? $oldTxn['description'];
    $note = $data['note'] ?? $oldTxn['note'];
    $transactionDate = $data['transaction_date'] ?? $oldTxn['transaction_date'];
    
    // Revert old balance change
    $oldMultiplier = $oldTxn['type'] === 'income' ? -1 : 1;
    $stmt = $db->prepare("UPDATE accounts SET balance = balance + ? WHERE id = ?");
    $oldBalanceChange = $oldTxn['amount'] * $oldMultiplier;
    $stmt->bind_param("di", $oldBalanceChange, $oldTxn['account_id']);
    $stmt->execute();
    
    // Update transaction
    $stmt = $db->prepare("UPDATE transactions SET account_id = ?, category_id = ?, type = ?, amount = ?, 
                         description = ?, note = ?, transaction_date = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param("iisdsssii", $accountId, $categoryId, $type, $amount, $description, $note, $transactionDate, $id, $userId);
    
    if ($stmt->execute()) {
        // Apply new balance change
        $newMultiplier = $type === 'income' ? 1 : -1;
        $stmt = $db->prepare("UPDATE accounts SET balance = balance + ? WHERE id = ?");
        $newBalanceChange = $amount * $newMultiplier;
        $stmt->bind_param("di", $newBalanceChange, $accountId);
        $stmt->execute();
        
        Response::success([], 'Cập nhật giao dịch thành công');
    } else {
        Response::error('Cập nhật giao dịch thất bại');
    }
}

// Delete transaction
if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    
    // Get transaction
    $stmt = $db->prepare("SELECT * FROM transactions WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $userId);
    $stmt->execute();
    $txn = $stmt->get_result()->fetch_assoc();
    
    if (!$txn) {
        Response::error('Transaction not found', 404);
    }
    
    // Revert balance change
    $multiplier = $txn['type'] === 'income' ? -1 : 1;
    $stmt = $db->prepare("UPDATE accounts SET balance = balance + ? WHERE id = ?");
    $balanceChange = $txn['amount'] * $multiplier;
    $stmt->bind_param("di", $balanceChange, $txn['account_id']);
    $stmt->execute();
    
    // Delete transaction
    $stmt = $db->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $userId);
    
    if ($stmt->execute()) {
        Response::success([], 'Xóa giao dịch thành công');
    } else {
        Response::error('Xóa giao dịch thất bại');
    }
}

Response::error('Invalid request', 404);
