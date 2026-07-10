<?php
require_once __DIR__ . '/../helpers/helpers.php';
require_once __DIR__ . '/../middleware/auth.php';

$db     = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

// ============================================================
// POST /auth — send_otp
// ============================================================
if ($method === 'POST' && ($body['action'] ?? '') === 'send_otp') {
    $purpose = $body['purpose'] ?? 'LOGIN';
    $input   = trim($body['mobile'] ?? $body['email'] ?? '');

    if (!$input) Response::error('Mobile number or email is required');

    $isEmail  = Validator::email($input);
    $isMobile = Validator::mobile($input);
    if (!$isEmail && !$isMobile) Response::error('Enter a valid 10-digit mobile number or email address');

    // ── LOGIN: Validate user exists BEFORE wasting OTP/SMS ──
    if ($purpose === 'LOGIN') {
        $field = $isEmail ? 'email' : 'mobile';
        $user  = $db->fetchOne("SELECT * FROM users WHERE $field=?", $input);

        if (!$user) {
            Response::error('Account not found or signed up without email. Please register.');
        }
        if (!$user['is_active']) {
            Response::error('Account is suspended. Contact support.');
        }
    }

    // ── REGISTER: Check duplicate BEFORE wasting OTP/SMS ──
    if ($purpose === 'REGISTER') {
        $err = Validator::required($body, ['first_name', 'mobile']);
        if ($err) Response::error($err);
        if (!Validator::mobile($body['mobile'])) Response::error('Invalid mobile number');
        if (!empty($body['email']) && !Validator::email($body['email'])) Response::error('Invalid email address');

        if ($db->fetchOne("SELECT id FROM users WHERE mobile=?", $body['mobile'])) {
            Response::error('Already registered. Please login.');
        }
        if (!empty($body['email']) && $db->fetchOne("SELECT id FROM users WHERE email=?", $body['email'])) {
            Response::error('Already registered. Please login.');
        }

        $email = !empty($body['email']) ? trim($body['email']) : null;
        $db->begin();
        $userId = $db->fetchOne("SELECT gen_random_uuid() AS id")['id'];
        $db->query(
            "INSERT INTO users (id, first_name, last_name, email, mobile, password_hash, role, is_active)
             VALUES (?, ?, ?, ?, ?, ?, 'customer', TRUE)",
            $userId,
            $body['first_name'],
            $body['last_name'] ?? '',
            $email,
            $body['mobile'],
            password_hash(uniqid('', true), PASSWORD_DEFAULT)
        );
        $db->query(
            "INSERT INTO customers (id, user_id, customer_code) VALUES (gen_random_uuid(), ?, ?)",
            $userId, 'CUS-' . strtoupper(substr($userId, 0, 8))
        );
        $db->commit();
    }

    // Generate & save OTP
    $otp = OtpHelper::generate();
    OtpHelper::save($input, $otp, $purpose);

    // Attempt delivery
    $sent = false; $channel = '';
    if ($isMobile)    { $sent = OtpHelper::sendSMS($input, $otp);   $channel = 'SMS'; }
    elseif ($isEmail) { $sent = OtpHelper::sendEmail($input, $otp); $channel = 'email'; }

    // $devOtp = (!FAST2SMS_API_KEY && !SMTP_PASS) ? $otp : null;
    $devOtp = (!$sent) ? $otp : null;
    Response::success(
        $sent ? "OTP sent via $channel" : "OTP generated (configure SMS/Email to receive it)",
        array_filter(['channel' => $channel, 'expires_in' => OTP_EXPIRY_MINUTES . ' minutes', 'otp' => $devOtp])
    );
}

// ============================================================
// POST /auth — verify_otp (Login for customer, vendor, admin)
// ============================================================
if ($method === 'POST' && ($body['action'] ?? '') === 'verify_otp') {
    $input = trim($body['mobile'] ?? $body['email'] ?? '');
    $otp   = trim($body['otp'] ?? '');
    if (!$input || !$otp) Response::error('Mobile/Email and OTP are required');
    if (!OtpHelper::verify($input, $otp)) Response::error('Invalid or expired OTP', 401);

    $isEmail = Validator::email($input);
    $field   = $isEmail ? 'email' : 'mobile';
    $user    = $db->fetchOne("SELECT * FROM users WHERE $field=?", $input);

    if (!$user) Response::error('Account not found. Please register first.', 404);
    if (!$user['is_active']) Response::error('Account is suspended', 403);

    $token = JWT::generate(['user_id' => $user['id'], 'mobile' => $user['mobile'], 'role' => $user['role']]);
    Response::success('Login successful', [
        'token' => $token,
        'user'  => [
            'id'     => $user['id'],
            'mobile' => $user['mobile'],
            'email'  => $user['email'],
            'role'   => $user['role'],
            'name'   => trim($user['first_name'] . ' ' . $user['last_name'])
        ]
    ]);
}

// ============================================================
// POST /auth — register (Finalize customer registration)
// ============================================================
if ($method === 'POST' && ($body['action'] ?? '') === 'register') {
    $mobile = trim($body['mobile'] ?? '');
    $otp    = trim($body['otp'] ?? '');
    if (!$mobile || !$otp) Response::error('Mobile and OTP are required');
    if (!Validator::mobile($mobile)) Response::error('Invalid mobile number');
    if (!OtpHelper::verify($mobile, $otp)) Response::error('Invalid or expired OTP', 401);

    $user = $db->fetchOne("SELECT * FROM users WHERE mobile=?", $mobile);
    if (!$user) Response::error('Registration data not found', 404);
    if ($user['role'] !== 'customer') Response::error('Invalid registration', 400);

    $token = JWT::generate(['user_id' => $user['id'], 'mobile' => $user['mobile'], 'role' => $user['role']]);
    Response::success('Registration successful', [
        'token' => $token,
        'user'  => [
            'id'     => $user['id'],
            'mobile' => $user['mobile'],
            'email'  => $user['email'],
            'role'   => $user['role'],
            'name'   => trim($user['first_name'] . ' ' . $user['last_name'])
        ]
    ], 201);
}

if ($method === 'POST' && ($body['action'] ?? '') === 'admin_login') {
    $input    = trim($body['email'] ?? $body['mobile'] ?? '');
    $password = trim($body['password'] ?? '');

    if (!$input || !$password) Response::error('Email/Mobile and password are required');

    $isEmail = Validator::email($input);
    $field   = $isEmail ? 'email' : 'mobile';

    $user = $db->fetchOne("SELECT * FROM users WHERE $field=?", $input);
    if (!$user) Response::error('Invalid credentials', 401);
    if (!in_array($user['role'], ['admin', 'owner'])) Response::error('Access denied — admin only', 403);
    if (!$user['is_active']) Response::error('Account is suspended', 403);
    if (!password_verify($password, $user['password_hash'])) Response::error('Invalid credentials', 401);

    $token = JWT::generate(['user_id' => $user['id'], 'mobile' => $user['mobile'], 'role' => $user['role']]);
    Response::success('Login successful', [
        'token' => $token,
        'user'  => [
            'id'     => $user['id'],
            'mobile' => $user['mobile'],
            'email'  => $user['email'],
            'role'   => $user['role'],
            'name'   => trim($user['first_name'] . ' ' . $user['last_name'])
        ]
    ]);
}

Response::error('Invalid request', 404);