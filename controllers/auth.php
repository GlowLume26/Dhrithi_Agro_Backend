<?php
require_once __DIR__ . '/../helpers/helpers.php';
require_once __DIR__ . '/../middleware/auth.php';

$db     = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

// POST /auth — send_otp
if ($method === 'POST' && ($body['action'] ?? '') === 'send_otp') {
    $input   = trim($body['mobile'] ?? $body['email'] ?? '');
    $purpose = $body['purpose'] ?? 'LOGIN';
    if (!$input) Response::error('Mobile number or email is required');

    $isEmail  = Validator::email($input);
    $isMobile = Validator::mobile($input);
    if (!$isEmail && !$isMobile) Response::error('Enter a valid 10-digit mobile number or email address');

    $otp = OtpHelper::generate();
    OtpHelper::save($input, $otp, $purpose);

    $sent = false; $channel = '';
    if ($isMobile)    { $sent = OtpHelper::sendSMS($input, $otp);   $channel = 'SMS'; }
    elseif ($isEmail) { $sent = OtpHelper::sendEmail($input, $otp); $channel = 'email'; }

    $devOtp = (!FAST2SMS_API_KEY && !SMTP_PASS) ? $otp : null;
    Response::success(
        $sent ? "OTP sent via $channel" : "OTP generated (configure SMS/Email to receive it)",
        array_filter(['channel' => $channel, 'expires_in' => OTP_EXPIRY_MINUTES . ' minutes', 'otp' => $devOtp])
    );
}

// POST /auth — verify_otp
if ($method === 'POST' && ($body['action'] ?? '') === 'verify_otp') {
    $input = trim($body['mobile'] ?? $body['email'] ?? '');
    $otp   = trim($body['otp'] ?? '');
    if (!$input || !$otp) Response::error('Mobile/Email and OTP are required');
    if (!OtpHelper::verify($input, $otp)) Response::error('Invalid or expired OTP', 401);

    $isEmail = Validator::email($input);
    $field   = $isEmail ? 'email' : 'mobile';
    $user    = $db->fetchOne("SELECT * FROM users WHERE $field=?", $input);

    if (!$user) {
        $db->begin();
        $userId = $db->fetchOne("SELECT gen_random_uuid() AS id")['id'];
        $name   = $isEmail ? explode('@', $input)[0] : '';
        $db->query(
            "INSERT INTO users (id,first_name,last_name,email,mobile,password_hash,role) VALUES (?,?,?,?,?,?,'customer')",
            $userId, $name, '', $isEmail ? $input : '', $isEmail ? '' : $input, password_hash(uniqid('', true), PASSWORD_DEFAULT)
        );
        $db->query(
            "INSERT INTO customers (id,user_id,customer_code) VALUES (gen_random_uuid(),?,?)",
            $userId, 'CUS-' . strtoupper(substr($userId, 0, 8))
        );
        $db->commit();
        $user = $db->fetchOne("SELECT * FROM users WHERE id=?", $userId);
    }

    if (!$user['is_active']) Response::error('Account is suspended', 403);
    $token = JWT::generate(['user_id' => $user['id'], 'mobile' => $user['mobile'], 'role' => $user['role']]);
    Response::success('Login successful', [
        'token' => $token,
        'user'  => ['id' => $user['id'], 'mobile' => $user['mobile'], 'email' => $user['email'],
                    'role' => $user['role'], 'name' => trim($user['first_name'] . ' ' . $user['last_name'])]
    ]);
}

// POST /auth — register
if ($method === 'POST' && ($body['action'] ?? '') === 'register') {
    $err = Validator::required($body, ['first_name', 'mobile', 'otp']);
    if ($err) Response::error($err);
    if (!Validator::mobile($body['mobile'])) Response::error('Invalid mobile number');
    if (!empty($body['email']) && !Validator::email($body['email'])) Response::error('Invalid email address');
    if (!OtpHelper::verify($body['mobile'], $body['otp'])) Response::error('Invalid or expired OTP', 401);
    if ($db->fetchOne("SELECT id FROM users WHERE mobile=?", $body['mobile'])) Response::error('Mobile already registered');

    $db->begin();
    $userId = $db->fetchOne("SELECT gen_random_uuid() AS id")['id'];
    $db->query(
        "INSERT INTO users (id,first_name,last_name,email,mobile,password_hash,role,is_active) VALUES (?,?,?,?,?,?,'customer',TRUE)",
        $userId, $body['first_name'], $body['last_name'] ?? '', $body['email'] ?? '', $body['mobile'],
        password_hash(uniqid('', true), PASSWORD_DEFAULT)
    );
    $db->query("INSERT INTO customers (id,user_id,customer_code) VALUES (gen_random_uuid(),?,?)",
        $userId, 'CUS-' . strtoupper(substr($userId, 0, 8)));
    $db->commit();

    $token = JWT::generate(['user_id' => $userId, 'mobile' => $body['mobile'], 'role' => 'customer']);
    Response::success('Account created successfully', ['token' => $token], 201);
}

Response::error('Invalid request', 404);
