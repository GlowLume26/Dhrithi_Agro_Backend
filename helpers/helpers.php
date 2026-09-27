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

        $subject  = 'Your Drithi Agro OTP: ' . $otp;
        $htmlBody = '
<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;background:#f5f5f5;padding:30px;">
<div style="max-width:480px;margin:0 auto;background:white;border-radius:16px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.08);">
  <div style="background:linear-gradient(135deg,#1b5e20,#2e7d32);padding:28px 32px;text-align:center;">
    <h1 style="color:white;margin:0;font-size:22px;font-weight:900;">&#127807; Drithi Agro</h1>
    <p style="color:rgba(255,255,255,0.8);margin:4px 0 0;font-size:13px;">Farm to Future</p>
  </div>
  <div style="padding:32px;text-align:center;">
    <p style="font-size:15px;color:#555;margin-bottom:24px;">Use the OTP below to verify your identity.</p>
    <div style="background:#f0fdf4;border:2px dashed #2e7d32;border-radius:12px;padding:20px 32px;display:inline-block;margin-bottom:24px;">
      <div style="font-size:38px;font-weight:900;letter-spacing:10px;color:#1b5e20;">' . $otp . '</div>
    </div>
    <p style="font-size:13px;color:#888;">Valid for <b>' . OTP_EXPIRY_MINUTES . ' minutes</b>. Do not share this OTP with anyone.</p>
    <p style="font-size:12px;color:#aaa;margin-top:20px;">If you did not request this, please ignore this email.</p>
  </div>
  <div style="background:#f9fbe7;padding:16px 32px;text-align:center;border-top:1px solid #e8f5e9;">
    <p style="font-size:11px;color:#aaa;margin:0;">&copy; 2025 Drithi Agro &middot; Made with &#10084; for Indian Farmers</p>
  </div>
</div>
</body></html>';
        $altBody  = 'Your Drithi Agro OTP is: ' . $otp . '. Valid for ' . OTP_EXPIRY_MINUTES . ' minutes. Do not share.';

        // ── Try PHPMailer first (if Composer installed) ──
        $autoload = __DIR__ . '/../vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = SMTP_HOST;
                $mail->SMTPAuth   = true;
                $mail->Username   = SMTP_USER;
                $mail->Password   = SMTP_PASS;
                $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = SMTP_PORT;
                $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
                $mail->addAddress($toEmail);
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body    = $htmlBody;
                $mail->AltBody = $altBody;
                $mail->send();
                return true;
            } catch (\Exception $e) {
                error_log('PHPMailer Error: ' . $mail->ErrorInfo);
                // fall through to native SMTP below
            }
        }

        // ── Native SMTP fallback (TLS STARTTLS on port 587) ──
        $fp = @stream_socket_client('tcp://' . SMTP_HOST . ':' . SMTP_PORT, $errno, $errstr, 15);
        if (!$fp) return false;
        $read = fn() => fgets($fp, 515);
        $write = fn($s) => fwrite($fp, $s . "\r\n");
        $read(); // 220 greeting
        $write('EHLO localhost'); while (($line = $read()) && $line[3] !== ' ') {}
        $write('STARTTLS'); $read();
        // Upgrade to TLS
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { fclose($fp); return false; }
        $write('EHLO localhost'); while (($line = $read()) && $line[3] !== ' ') {}
        $write('AUTH LOGIN'); $read();
        $write(base64_encode(SMTP_USER)); $read();
        $write(base64_encode(SMTP_PASS)); $res = $read();
        if (!str_starts_with($res, '235')) { fclose($fp); return false; }
        $write('MAIL FROM:<' . SMTP_USER . '>'); $read();
        $write('RCPT TO:<' . $toEmail . '>');    $read();
        $write('DATA'); $read();
        $headers = implode("\r\n", [
            'From: ' . SMTP_FROM_NAME . ' <' . SMTP_USER . '>',
            'To: ' . $toEmail,
            'Subject: ' . $subject,
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=utf-8',
        ]);
        $write($headers . "\r\n\r\n" . $htmlBody . "\r\n.");
        $res = $read();
        $write('QUIT'); fclose($fp);
        return str_starts_with($res, '250');
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
