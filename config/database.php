<?php
// ============================================================
// DRITHI AGRO — Database & App Configuration (PostgreSQL)
// ============================================================

define('DB_HOST', 'localhost');
define('DB_PORT', '5432');
define('DB_USER', 'postgres');
define('DB_PASS', 'Bhavesh123');
define('DB_NAME', 'drithi_agro');

define('JWT_SECRET', 'drithi-agro-jwt-secret-2025-change-in-production');
define('JWT_EXPIRY', 86400); // 24 hours

define('APP_URL',     'http://localhost:8000');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('UPLOAD_URL',  APP_URL . '/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB

define('OTP_EXPIRY_MINUTES', 10);
define('OTP_LENGTH', 6);

// SMTP — Gmail
define('SMTP_HOST',      'smtp.gmail.com');
define('SMTP_PORT',      587);
define('SMTP_USER',      '');
define('SMTP_PASS',      '');
define('SMTP_FROM_NAME', 'Drithi Agro');

// Fast2SMS
define('FAST2SMS_API_KEY', '');

// Razorpay
define('RAZORPAY_KEY_ID',     'rzp_test_xxxx');
define('RAZORPAY_KEY_SECRET', 'your_razorpay_secret');

class Database {
    private static ?Database $instance = null;
    private PDO $conn;

    private function __construct() {
        $dsn = 'pgsql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME;
        try {
            $this->conn = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['success' => false, 'message' => $e->getMessage()]));
        }
    }

    public static function getInstance(): Database {
        if (!self::$instance) self::$instance = new Database();
        return self::$instance;
    }

    public function getConn(): PDO { return $this->conn; }

    public function query(string $sql, string $types = '', mixed ...$params): PDOStatement|bool {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params ?: []);
            return $stmt;
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['success' => false, 'message' => 'Query failed: ' . $e->getMessage()]));
        }
    }

    public function fetchAll(string $sql, string $types = '', mixed ...$params): array {
        $stmt = $this->query($sql, $types, ...$params);
        return $stmt instanceof PDOStatement ? $stmt->fetchAll() : [];
    }

    public function fetchOne(string $sql, string $types = '', mixed ...$params): ?array {
        $stmt = $this->query($sql, $types, ...$params);
        $row  = $stmt instanceof PDOStatement ? $stmt->fetch() : false;
        return $row ?: null;
    }

    public function lastInsertId(string $sequence = ''): int {
        return (int)$this->conn->lastInsertId($sequence ?: null);
    }

    public function escape(string $val): string {
        return str_replace("'", "''", $val);
    }
}
