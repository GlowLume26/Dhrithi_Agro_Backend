<?php
require_once __DIR__ . '/../config/database.php';

class JWT {
    public static function generate(array $payload): string {
        $header  = rtrim(base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'])), '=');
        $payload['iat'] = time();
        $payload['exp'] = time() + JWT_EXPIRY;
        $p   = rtrim(base64_encode(json_encode($payload)), '=');
        $sig = rtrim(base64_encode(hash_hmac('sha256', "$header.$p", JWT_SECRET, true)), '=');
        return "$header.$p.$sig";
    }

    public static function verify(string $token): ?array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;
        [$header, $p, $sig] = $parts;
        $expected = rtrim(base64_encode(hash_hmac('sha256', "$header.$p", JWT_SECRET, true)), '=');
        if (!hash_equals($expected, $sig)) return null;
        $data = json_decode(base64_decode($p), true);
        if (!$data || $data['exp'] < time()) return null;
        return $data;
    }
}

class Response {
    public static function json(mixed $data, int $code = 200): void {
        http_response_code($code);
        echo json_encode($data);
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
    public static function generate(): string {
        return str_pad((string)random_int(0, 999999), OTP_LENGTH, '0', STR_PAD_LEFT);
    }

    public static function save(string $mobile, string $otp, string $purpose = 'LOGIN'): void {
        $db = Database::getInstance();
        $db->query("UPDATE otp_store SET is_used=1 WHERE mobile=? AND purpose=? AND is_used=0", 'ss', $mobile, $purpose);
        $expires = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MINUTES . ' minutes'));
        $db->query("INSERT INTO otp_store (mobile, otp_code, purpose, expires_at) VALUES (?,?,?,?)", 'ssss', $mobile, $otp, $purpose, $expires);
    }

    public static function verify(string $mobile, string $otp): bool {
        $db  = Database::getInstance();
        $row = $db->fetchOne(
            "SELECT id FROM otp_store WHERE mobile=? AND otp_code=? AND is_used=0 AND expires_at > NOW() ORDER BY id DESC LIMIT 1",
            'ss', $mobile, $otp
        );
        if (!$row) return false;
        $db->query("UPDATE otp_store SET is_used=1 WHERE id=?", 'i', $row['id']);
        return true;
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
}

class FileUpload {
    public static function upload(array $file, string $folder = 'general'): string {
        $dir = UPLOAD_PATH . $folder . '/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'pdf', 'webp'];
        if (!in_array($ext, $allowed)) throw new Exception('Invalid file type. Allowed: jpg, jpeg, png, pdf, webp');
        if ($file['size'] > MAX_FILE_SIZE) throw new Exception('File too large (max 5MB)');
        $name = uniqid() . '_' . time() . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $dir . $name)) throw new Exception('Upload failed');
        return UPLOAD_URL . $folder . '/' . $name;
    }
}
