<?php
require_once __DIR__ . '/../helpers/helpers.php';
require_once __DIR__ . '/../middleware/auth.php';

$db     = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

// POST /auth — send_otp
if ($method === 'POST' && ($body['action'] ?? '') === 'send_otp') {
    $mobile  = trim($body['mobile'] ?? '');
    $purpose = $body['purpose'] ?? 'LOGIN';
    if (!Validator::mobile($mobile)) Response::error('Invalid mobile number');
    $otp = OtpHelper::generate();
    OtpHelper::save($mobile, $otp, $purpose);
    // Production: send via Twilio. Dev: return OTP directly.
    Response::success('OTP sent', ['otp' => $otp, 'expires_in' => OTP_EXPIRY_MINUTES . ' minutes']);
}

// POST /auth — verify_otp (Login)
if ($method === 'POST' && ($body['action'] ?? '') === 'verify_otp') {
    $mobile = trim($body['mobile'] ?? '');
    $otp    = trim($body['otp'] ?? '');
    if (!$mobile || !$otp) Response::error('Mobile and OTP are required');
    if (!OtpHelper::verify($mobile, $otp)) Response::error('Invalid or expired OTP', 401);

    $user = $db->fetchOne("SELECT * FROM users WHERE mobile=?", 's', $mobile);
    if (!$user) {
        $db->query("INSERT INTO users (mobile, role) VALUES (?, 'CUSTOMER')", 's', $mobile);
        $user = $db->fetchOne("SELECT * FROM users WHERE id=?", 'i', $db->lastInsertId());
    }
    if (!$user['is_active']) Response::error('Account is suspended', 403);

    $token = JWT::generate(['user_id' => $user['id'], 'mobile' => $user['mobile'], 'role' => $user['role']]);
    Response::success('Login successful', [
        'token' => $token,
        'user'  => ['id' => $user['id'], 'mobile' => $user['mobile'], 'role' => $user['role']]
    ]);
}

// POST /auth — register
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
        "INSERT INTO customers (user_id, full_name, email, mobile, occupation) VALUES (?,?,?,?,?)",
        'issss', $userId, $body['full_name'], $body['email'] ?? '', $body['mobile'], $body['occupation'] ?? ''
    );
    $token = JWT::generate(['user_id' => $userId, 'mobile' => $body['mobile'], 'role' => 'CUSTOMER']);
    Response::success('Account created successfully', ['token' => $token], 201);
}

Response::error('Invalid request', 404);
