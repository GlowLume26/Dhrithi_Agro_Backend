<?php
require_once __DIR__ . '/../helpers/helpers.php';

function authMiddleware(): array {
    $headers = getallheaders();
    $auth    = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (!$auth || !str_starts_with($auth, 'Bearer ')) {
        Response::error('Unauthorized — token missing', 401);
    }
    $payload = JWT::verify(substr($auth, 7));
    if (!$payload) Response::error('Unauthorized — invalid or expired token', 401);
    return $payload;
}

function adminMiddleware(): array {
    $payload = authMiddleware();
    if ($payload['role'] !== 'ADMIN') Response::error('Forbidden — admin access required', 403);
    return $payload;
}

function vendorMiddleware(): array {
    $payload = authMiddleware();
    if ($payload['role'] !== 'VENDOR') Response::error('Forbidden — vendor access required', 403);
    return $payload;
}
