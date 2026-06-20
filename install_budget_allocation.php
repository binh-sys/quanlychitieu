<?php
// Script tạo bảng budget_allocations
require_once 'connect.php';

echo "<!DOCTYPE html>
<html lang='vi'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Cài đặt Bảng Budget Allocations</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { background: #D1FAE5; color: #065F46; padding: 15px; border-radius: 8px; margin: 10px 0; border: 1px solid #6EE7B7; }
        .error { background: #FEE2E2; color: #991B1B; padding: 15px; border-radius: 8px; margin: 10px 0; border: 1px solid #FCA5A5; }
        .info { background: #DBEAFE; color: #1E40AF; padding: 15px; border-radius: 8px; margin: 10px 0; border: 1px solid #93C5FD; }
        h1 { color: #1F2937; }
        .btn { display: inline-block; padding: 12px 24px; background: #2563EB; color: white; text-decoration: none; border-radius: 8px; margin-top: 20px; }
    </style>
</head>
<body>
    <h1>🔧 Cài đặt Tính năng Phân bổ Thu nhập</h1>";

try {
    // Kiểm tra xem bảng đã tồn tại chưa
    $checkTable = $conn->query("SHOW TABLES LIKE 'budget_allocations'");
    
    if ($checkTable->num_rows > 0) {
        echo "<div class='info'>ℹ️ Bảng budget_allocations đã tồn tại. Đang kiểm tra...</div>";
        
        // Kiểm tra dữ liệu
        $count = $conn->query("SELECT COUNT(*) as total FROM budget_allocations")->fetch_assoc();
        echo "<div class='info'>📊 Hiện có {$count['total']} bản ghi trong bảng.</div>";
        
    } else {
        echo "<div class='info'>📝 Đang tạo bảng budget_allocations...</div>";
        
        // Tạo bảng
        $sql = "CREATE TABLE budget_allocations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            category_id INT NOT NULL,
            name VARCHAR(100) NOT NULL,
            percentage DECIMAL(5,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
            INDEX idx_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        if ($conn->query($sql) === TRUE) {
            echo "<div class='success'>✅ Tạo bảng budget_allocations thành công!</div>";
            
            // Thêm dữ liệu mẫu
            echo "<div class='info'>📝 Đang thêm dữ liệu mẫu...</div>";
            
            $sampleData = [
                [2, 1, 'Tiền ăn uống', 30.0],
                [2, 8, 'Tiền nhà', 40.0],
                [2, 2, 'Tiền đi lại', 15.0],
                [2, 5, 'Giải trí', 10.0]
            ];
            
            $stmt = $conn->prepare("INSERT INTO budget_allocations (user_id, category_id, name, percentage) VALUES (?, ?, ?, ?)");
            
            foreach ($sampleData as $data) {
                $stmt->bind_param("iisd", $data[0], $data[1], $data[2], $data[3]);
                $stmt->execute();
            }
            
            echo "<div class='success'>✅ Đã thêm " . count($sampleData) . " bản ghi mẫu!</div>";
            
        } else {
            throw new Exception("Lỗi tạo bảng: " . $conn->error);
        }
    }
    
    echo "<div class='success'>
        <h2>🎉 Cài đặt hoàn tất!</h2>
        <p>Bạn có thể sử dụng tính năng Phân bổ Thu nhập ngay bây giờ.</p>
        <a href='finora/budget-allocation.php' class='btn'>🚀 Mở trang Phân bổ Thu nhập</a>
    </div>";
    
} catch (Exception $e) {
    echo "<div class='error'>
        <h2>❌ Có lỗi xảy ra!</h2>
        <p>" . $e->getMessage() . "</p>
    </div>";
}

echo "</body></html>";

$conn->close();
?>
