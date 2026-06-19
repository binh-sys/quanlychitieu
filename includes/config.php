<?php
/**
 * Configuration & Database Connection
 */
declare(strict_types=1);

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'quanly_chitieu');

// Application configuration
define('APP_NAME', 'Expense Manager Pro');
define('APP_VERSION', '1.0.0');
define('TIMEZONE', 'Asia/Ho_Chi_Minh');

// Set timezone
date_default_timezone_set(TIMEZONE);

// Database connection class
class Database {
    private static $instance = null;
    private $conn;

    private function __construct() {
        try {
            $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if ($this->conn->connect_error) {
                throw new Exception("Connection failed: " . $this->conn->connect_error);
            }
            
            $this->conn->set_charset("utf8mb4");
        } catch (Exception $e) {
            die(json_encode(['success' => false, 'message' => 'Database connection error']));
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->conn;
    }

    public function query($sql) {
        return $this->conn->query($sql);
    }

    public function prepare($sql) {
        return $this->conn->prepare($sql);
    }

    public function escape($str) {
        return $this->conn->real_escape_string($str);
    }

    public function lastInsertId() {
        return $this->conn->insert_id;
    }
}

// Authentication helper class
class Auth {
    public static function user() {
        if (isset($_SESSION['user_id'])) {
            $db = Database::getInstance();
            $stmt = $db->prepare("SELECT id, email, name, currency, monthly_budget FROM users WHERE id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->fetch_assoc();
        }
        return null;
    }

    public static function check() {
        return isset($_SESSION['user_id']);
    }

    public static function login($userId) {
        $_SESSION['user_id'] = $userId;
    }

    public static function logout() {
        session_destroy();
    }

    public static function userId() {
        return $_SESSION['user_id'] ?? null;
    }
}

// Response helper
class Response {
    public static function json($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    public static function success($data = [], $message = 'Success') {
        self::json(['success' => true, 'message' => $message, 'data' => $data]);
    }

    public static function error($message = 'Error', $statusCode = 400) {
        self::json(['success' => false, 'message' => $message], $statusCode);
    }
}
