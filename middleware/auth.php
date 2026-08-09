<?php
require_once __DIR__ . '/../helpers/helpers.php';

function authMiddleware(): array {
    $auth = $_SERVER['HTTP_AUTHORIZATION']
         ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
         ?? '';
    if (!$auth && function_exists('getallheaders')) {
        $h    = getallheaders();
        $auth = $h['Authorization'] ?? $h['authorization'] ?? '';
    }
    if (!$auth || !str_starts_with($auth, 'Bearer ')) {
        Response::error('Unauthorized — token missing', 401);
    }
    $payload = JWT::verify(substr($auth, 7));
    if (!$payload) Response::error('Unauthorized — invalid or expired token', 401);
    return $payload;
}

function adminMiddleware(): array {
    $payload = authMiddleware();
    if (!in_array($payload['role'], ['admin', 'owner', 'superadmin'])) Response::error('Forbidden — admin access required', 403);
    return $payload;
}

function vendorMiddleware(): array {
    $payload = authMiddleware();
    if (!in_array($payload['role'], ['vendor', 'admin'])) Response::error('Forbidden — vendor access required', 403);
    return $payload;
}
