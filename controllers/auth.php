<?php
require_once __DIR__ . '/../helpers/helpers.php';
require_once __DIR__ . '/../middleware/auth.php';

$db     = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

// ============================================================
// POST /auth — send_otp
// Supports: mobile number OR email address
// ============================================================
if ($method === 'POST' && ($body['action'] ?? '') === 'send_otp') {
    $input   = trim($body['mobile'] ?? $body['email'] ?? '');
    $purpose = $body['purpose'] ?? 'LOGIN';

    if (!$input) Response::error('Mobile number or email is required');

    $isEmail  = Validator::email($input);
    $isMobile = Validator::mobile($input);

    if (!$isEmail && !$isMobile) {
        Response::error('Enter a valid 10-digit mobile number or email address');
    }

    $otp = OtpHelper::generate();
    // Store using input (mobile or email) as identifier
    OtpHelper::save($input, $otp, $purpose);

    $sent    = false;
    $channel = '';

    if ($isMobile) {
        // Try SMS via Fast2SMS
        $sent    = OtpHelper::sendSMS($input, $otp);
        $channel = 'SMS';
    } elseif ($isEmail) {
        // Try Email via Gmail SMTP
        $sent    = OtpHelper::sendEmail($input, $otp);
        $channel = 'email';
    }

    // Always return success — in dev mode also return OTP so you can test
    $devOtp = (FAST2SMS_API_KEY === '' && SMTP_PASS === '') ? $otp : null;

    Response::success(
        $sent ? "OTP sent via $channel" : "OTP generated (configure SMS/Email credentials to receive it)",
        array_filter([
            'channel'    => $channel ?: ($isEmail ? 'email' : 'sms'),
            'expires_in' => OTP_EXPIRY_MINUTES . ' minutes',
            'otp'        => $devOtp,   // null in production when credentials are set
        ])
    );
}

// ============================================================
// POST /auth — verify_otp  (Login)
// ============================================================
if ($method === 'POST' && ($body['action'] ?? '') === 'verify_otp') {
    $input = trim($body['mobile'] ?? $body['email'] ?? '');
    $otp   = trim($body['otp']   ?? '');

    if (!$input || !$otp) Response::error('Mobile/Email and OTP are required');
    if (!OtpHelper::verify($input, $otp)) Response::error('Invalid or expired OTP', 401);

    $isEmail = Validator::email($input);

    // Find user by mobile or email
    if ($isEmail) {
        $user = $db->fetchOne("SELECT * FROM users WHERE email=?", 's', $input);
        if (!$user) {
            // Auto-create account for email login
            $db->query("INSERT INTO users (mobile, email, role) VALUES (?,?,'CUSTOMER')", 'ss', '', $input);
            $userId = $db->lastInsertId();
            $db->query("INSERT INTO customers (user_id, full_name, email, mobile) VALUES (?,?,?,?)",
                'isss', $userId, explode('@', $input)[0], $input, '');
            $user = $db->fetchOne("SELECT * FROM users WHERE id=?", 'i', $userId);
        }
    } else {
        $user = $db->fetchOne("SELECT * FROM users WHERE mobile=?", 's', $input);
        if (!$user) {
            $db->query("INSERT INTO users (mobile, role) VALUES (?, 'CUSTOMER')", 's', $input);
            $userId = $db->lastInsertId();
            $user   = $db->fetchOne("SELECT * FROM users WHERE id=?", 'i', $userId);
        }
    }

    if (!$user['is_active']) Response::error('Account is suspended', 403);

    $token = JWT::generate(['user_id' => $user['id'], 'mobile' => $user['mobile'], 'role' => $user['role']]);
    Response::success('Login successful', [
        'token' => $token,
        'user'  => ['id' => $user['id'], 'mobile' => $user['mobile'], 'email' => $user['email'], 'role' => $user['role']]
    ]);
}

// ============================================================
// POST /auth — register
// ============================================================
if ($method === 'POST' && ($body['action'] ?? '') === 'register') {
    $err = Validator::required($body, ['full_name', 'mobile', 'otp']);
    if ($err) Response::error($err);
    if (!Validator::mobile($body['mobile'])) Response::error('Invalid mobile number');
    if (!OtpHelper::verify($body['mobile'], $body['otp'])) Response::error('Invalid or expired OTP', 401);
    if ($db->fetchOne("SELECT id FROM users WHERE mobile=?", 's', $body['mobile'])) {
        Response::error('Mobile number already registered');
    }
    $db->query("INSERT INTO users (mobile, email, role) VALUES (?,?,'CUSTOMER')", 'ss', $body['mobile'], $body['email'] ?? '');
    $userId = $db->lastInsertId();
    $db->query(
        "INSERT INTO customers (user_id, full_name, email, mobile) VALUES (?,?,?,?)",
        'isss', $userId, $body['full_name'], $body['email'] ?? '', $body['mobile']
    );
    // Send welcome SMS/email
    if (!empty($body['email'])) {
        OtpHelper::sendEmail($body['email'], 'Welcome to Drithi Agro! Your account has been created.');
    }
    $token = JWT::generate(['user_id' => $userId, 'mobile' => $body['mobile'], 'role' => 'CUSTOMER']);
    Response::success('Account created successfully', ['token' => $token], 201);
}

Response::error('Invalid request', 404);
