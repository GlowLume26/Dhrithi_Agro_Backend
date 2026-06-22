<?php
// ── Load .env ────────────────────────────────────────────────
foreach (file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
    [$k, $v] = explode('=', $line, 2);
    $_ENV[trim($k)] = trim($v);
}

define('DB_HOST',   $_ENV['DB_HOST']   ?? 'localhost');
define('DB_PORT',   $_ENV['DB_PORT']   ?? '5432');
define('DB_USER',   $_ENV['DB_USER']   ?? 'postgres');
define('DB_PASS',   $_ENV['DB_PASS']   ?? '');
define('DB_NAME',   $_ENV['DB_NAME']   ?? 'drithi_agro');

define('JWT_SECRET',  $_ENV['JWT_SECRET']  ?? 'change-me');
define('JWT_EXPIRY',  (int)($_ENV['JWT_EXPIRY'] ?? 86400));

define('APP_URL',      $_ENV['APP_URL']      ?? 'http://localhost/drithi-agro/backend');
define('UPLOAD_PATH',  __DIR__ . '/../uploads/');
define('UPLOAD_URL',   APP_URL . '/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024);

define('OTP_EXPIRY_MINUTES', 10);
define('OTP_LENGTH', 6);

define('SMTP_HOST',      $_ENV['SMTP_HOST']      ?? 'smtp.gmail.com');
define('SMTP_PORT',      (int)($_ENV['SMTP_PORT'] ?? 587));
define('SMTP_USER',      $_ENV['SMTP_USER']      ?? '');
define('SMTP_PASS',      $_ENV['SMTP_PASS']      ?? '');
define('SMTP_FROM_NAME', $_ENV['SMTP_FROM_NAME'] ?? 'Drithi Agro');

define('FAST2SMS_API_KEY',   $_ENV['FAST2SMS_API_KEY']   ?? '');
define('RAZORPAY_KEY_ID',    $_ENV['RAZORPAY_KEY_ID']    ?? '');
define('RAZORPAY_KEY_SECRET',$_ENV['RAZORPAY_KEY_SECRET']?? '');

class Database {
    private static ?Database $instance = null;
    private PDO $conn;

    private function __construct() {
        $dsn = 'pgsql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME;
        try {
            $this->conn = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_PERSISTENT         => true,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['success' => false, 'message' => 'Database connection failed']));
        }
    }

    public static function getInstance(): Database {
        if (!self::$instance) self::$instance = new Database();
        return self::$instance;
    }

    public function getConn(): PDO { return $this->conn; }

    public function query(string $sql, mixed ...$params): PDOStatement {
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params ?: []);
            return $stmt;
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['success' => false, 'message' => 'Query failed: ' . $e->getMessage()]));
        }
    }

    public function fetchAll(string $sql, mixed ...$params): array {
        return $this->query($sql, ...$params)->fetchAll();
    }

    public function fetchOne(string $sql, mixed ...$params): ?array {
        $row = $this->query($sql, ...$params)->fetch();
        return $row ?: null;
    }

    public function begin(): void   { $this->conn->beginTransaction(); }
    public function commit(): void  { $this->conn->commit(); }
    public function rollback(): void{ $this->conn->rollBack(); }
}
