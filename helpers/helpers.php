<?php
require_once __DIR__ . '/../config/database.php';

class JWT {
    public static function generate(array $payload): string {
        $header = base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload['iat'] = time();
        $payload['exp'] = time() + JWT_EXPIRY;
        $p   = base64url_encode(json_encode($payload));
        $sig = base64url_encode(hash_hmac('sha256', "$header.$p", JWT_SECRET, true));
        return "$header.$p.$sig";
    }

    public static function verify(string $token): ?array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;
        [$header, $p, $sig] = $parts;
        $expected = base64url_encode(hash_hmac('sha256', "$header.$p", JWT_SECRET, true));
        if (!hash_equals($expected, $sig)) return null;
        $data = json_decode(base64url_decode($p), true);
        if (!$data || $data['exp'] < time()) return null;
        return $data;
    }
}

function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
function base64url_decode(string $data): string {
    return base64_decode(strtr($data, '-_', '+/'));
}

class Response {
    public static function json(mixed $data, int $code = 200): void {
        http_response_code($code);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
    public static function success(string $message, mixed $data = null, int $code = 200): void {
        self::json(['success' => true, 'message' => $message, 'data' => $data], $code);
    }
    public static function error(string $message, int $code = 400): void {
        self::json(['success' => false, 'message' => $message], $code);
    }
}

class OtpHelper {
    // OTPs stored in PostgreSQL otp_cache table (auto-created if missing)
    private static bool $tableEnsured = false;

    private static function ensureTable(): void {
        if (self::$tableEnsured) return;
        $db = Database::getInstance();
        $db->query("CREATE TABLE IF NOT EXISTS otp_cache (
            identifier  VARCHAR(100) PRIMARY KEY,
            otp_code    VARCHAR(6)   NOT NULL,
            purpose     VARCHAR(20)  NOT NULL DEFAULT 'LOGIN',
            expires_at  BIGINT       NOT NULL,
            is_used     BOOLEAN      NOT NULL DEFAULT FALSE
        )");
        self::$tableEnsured = true;
    }

    public static function generate(): string {
        return str_pad((string)random_int(0, 999999), OTP_LENGTH, '0', STR_PAD_LEFT);
    }

    public static function save(string $identifier, string $otp, string $purpose = 'LOGIN'): void {
        self::ensureTable();
        $db = Database::getInstance();
        $expires = time() + OTP_EXPIRY_MINUTES * 60;
        $db->query(
            "INSERT INTO otp_cache (identifier, otp_code, purpose, expires_at, is_used)
             VALUES (?,?,?,?,FALSE)
             ON CONFLICT (identifier) DO UPDATE SET otp_code=EXCLUDED.otp_code, purpose=EXCLUDED.purpose, expires_at=EXCLUDED.expires_at, is_used=FALSE",
            $identifier, $otp, $purpose, $expires
        );
    }

    public static function verify(string $identifier, string $otp): bool {
        self::ensureTable();
        $db  = Database::getInstance();
        $row = $db->fetchOne(
            "SELECT otp_code, expires_at, is_used FROM otp_cache WHERE identifier=?",
            $identifier
        );
        if (!$row || $row['is_used'] || $row['expires_at'] < time() || $row['otp_code'] !== $otp) return false;
        $db->query("UPDATE otp_cache SET is_used=TRUE WHERE identifier=?", $identifier);
        return true;
    }

    public static function sendSMS(string $mobile, string $otp): bool {
        if (!FAST2SMS_API_KEY) return false;
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => 'https://www.fast2sms.com/dev/bulkV2',
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query(['authorization' => FAST2SMS_API_KEY, 'variables_values' => $otp, 'route' => 'otp', 'numbers' => $mobile]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => ['cache-control: no-cache'],
        ]);
        $res = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err) return false;
        $json = json_decode($res, true);
        return isset($json['return']) && $json['return'] === true;
    }

    public static function sendEmail(string $toEmail, string $otp): bool {
        if (!SMTP_USER || !SMTP_PASS) return false;
        $subject = 'Your Drithi Agro OTP: ' . $otp;
        $message = "Your OTP is: <b>$otp</b><br>Valid for " . OTP_EXPIRY_MINUTES . " minutes. Do not share.";
        $headers = implode("\r\n", [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=utf-8',
            'From: ' . SMTP_FROM_NAME . ' <' . SMTP_USER . '>',
        ]);
        $fp = @fsockopen('ssl://' . SMTP_HOST, SMTP_PORT, $errno, $errstr, 10);
        if (!$fp) return mail($toEmail, $subject, $message, $headers);
        fgets($fp, 515);
        fwrite($fp, "EHLO localhost\r\n");      fgets($fp, 515);
        fwrite($fp, "AUTH LOGIN\r\n");           fgets($fp, 515);
        fwrite($fp, base64_encode(SMTP_USER) . "\r\n"); fgets($fp, 515);
        fwrite($fp, base64_encode(SMTP_PASS) . "\r\n"); $res = fgets($fp, 515);
        if (!str_contains($res, '235')) { fclose($fp); return false; }
        fwrite($fp, "MAIL FROM:<" . SMTP_USER . ">\r\n");  fgets($fp, 515);
        fwrite($fp, "RCPT TO:<$toEmail>\r\n");              fgets($fp, 515);
        fwrite($fp, "DATA\r\n");                            fgets($fp, 515);
        fwrite($fp, "Subject: $subject\r\nTo: $toEmail\r\n$headers\r\n\r\n$message\r\n.\r\n");
        $res = fgets($fp, 515);
        fwrite($fp, "QUIT\r\n");
        fclose($fp);
        return str_contains($res, '250');
    }
}

class Validator {
    public static function mobile(string $m): bool { return (bool)preg_match('/^[6-9]\d{9}$/', $m); }
    public static function email(string $e): bool  { return (bool)filter_var($e, FILTER_VALIDATE_EMAIL); }
    public static function required(array $data, array $fields): ?string {
        foreach ($fields as $f) {
            if (!isset($data[$f]) || $data[$f] === '') return "$f is required";
        }
        return null;
    }
    public static function uuid(string $v): bool {
        return (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $v);
    }
}

class FileUpload {
    private static array $allowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

    public static function upload(array $file, string $folder = 'general'): string {
        if ($file['error'] !== UPLOAD_ERR_OK) throw new Exception('Upload error code: ' . $file['error']);
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::$allowed)) throw new Exception('Invalid file type. Allowed: ' . implode(', ', self::$allowed));
        if ($file['size'] > MAX_FILE_SIZE) throw new Exception('File too large (max 5MB)');
        // Verify actual mime type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        $allowedMime = ['image/jpeg','image/png','image/webp','application/pdf'];
        if (!in_array($mime, $allowedMime)) throw new Exception('Invalid file content');
        $dir  = UPLOAD_PATH . $folder . '/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $dir . $name)) throw new Exception('Upload failed');
        return UPLOAD_URL . $folder . '/' . $name;
    }
}
