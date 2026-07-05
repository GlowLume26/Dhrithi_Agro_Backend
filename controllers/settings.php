<?php
require_once __DIR__ . '/../helpers/helpers.php';
require_once __DIR__ . '/../middleware/auth.php';

$db     = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

// GET /settings — public, returns all settings as key=>value map
if ($method === 'GET') {
    $rows = $db->fetchAll("SELECT key, value FROM app_settings ORDER BY key");
    $map  = [];
    foreach ($rows as $r) $map[$r['key']] = $r['value'];
    Response::success('Settings fetched', $map);
}

// PUT /settings — admin only, bulk update
if ($method === 'PUT') {
    adminMiddleware();
    if (empty($body)) Response::error('No settings provided');
    foreach ($body as $key => $value) {
        $db->query(
            "INSERT INTO app_settings (key, value, updated_at) VALUES (?,?,NOW())
             ON CONFLICT (key) DO UPDATE SET value=EXCLUDED.value, updated_at=NOW()",
            $key, (string)$value
        );
    }
    Response::success('Settings saved');
}

Response::error('Invalid request', 404);
