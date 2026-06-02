<?php
// ============================================================
// DRITHI AGRO — Database & App Configuration
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'drithi_agro');

define('JWT_SECRET', 'drithi-agro-jwt-secret-2025-change-in-production');
define('JWT_EXPIRY', 86400); // 24 hours

define('APP_URL',     'http://localhost/drithi-agro/backend');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('UPLOAD_URL',  APP_URL . '/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

define('OTP_EXPIRY_MINUTES', 10);
define('OTP_LENGTH', 6);

// SMTP — Gmail
define('SMTP_HOST',      'smtp.gmail.com');
define('SMTP_PORT',      587);
define('SMTP_USER',      '');  // 👈 PUT YOUR GMAIL: example@gmail.com
define('SMTP_PASS',      '');  // 👈 PUT GMAIL APP PASSWORD (not your login password)
define('SMTP_FROM_NAME', 'Drithi Agro');

// Fast2SMS — Free Indian SMS API (https://www.fast2sms.com)
define('FAST2SMS_API_KEY', ''); // 👈 PUT YOUR fast2sms API key here

// Razorpay
define('RAZORPAY_KEY_ID',     'rzp_test_xxxx');
define('RAZORPAY_KEY_SECRET', 'your_razorpay_secret');

class Database {
    private static ?Database $instance = null;
    private mysqli $conn;

    private function __construct() {
        $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($this->conn->connect_error) {
            http_response_code(500);
            die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . $this->conn->connect_error]));
        }
        $this->conn->set_charset('utf8mb4');
    }

    public static function getInstance(): Database {
        if (!self::$instance) self::$instance = new Database();
        return self::$instance;
    }

    public function getConn(): mysqli { return $this->conn; }

    public function query(string $sql, string $types = '', mixed ...$params): mysqli_result|bool {
        $stmt = $this->conn->prepare($sql);
        if (!$stmt) {
            http_response_code(500);
            die(json_encode(['success' => false, 'message' => 'Query prepare failed: ' . $this->conn->error]));
        }
        if ($types && $params) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        return $stmt->get_result() ?: ($stmt->affected_rows >= 0);
    }

    public function fetchAll(string $sql, string $types = '', mixed ...$params): array {
        $result = $this->query($sql, $types, ...$params);
        return $result instanceof mysqli_result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    public function fetchOne(string $sql, string $types = '', mixed ...$params): ?array {
        $result = $this->query($sql, $types, ...$params);
        return $result instanceof mysqli_result ? ($result->fetch_assoc() ?: null) : null;
    }

    public function lastInsertId(): int { return $this->conn->insert_id; }
    public function escape(string $val): string { return $this->conn->real_escape_string($val); }
}
