<?php
require_once __DIR__ . '/../helpers/helpers.php';
require_once __DIR__ . '/../middleware/auth.php';

$db     = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$auth   = adminMiddleware();

// Only owner can manage permissions
if ($auth['role'] !== 'owner') Response::error('Access denied — owner only', 403);

$section = $_GET['section'] ?? '';

// GET all users with their permissions
if ($method === 'GET' && $section === 'users') {
    $users = $db->fetchAll(
        "SELECT u.id, u.first_name||' '||u.last_name AS name, u.email, u.role, u.is_active
         FROM users WHERE role IN ('admin','owner') ORDER BY u.created_at DESC"
    );
    foreach ($users as &$u) {
        $perms = $db->fetchAll("SELECT module, granted FROM user_permissions WHERE user_id=?", $u['id']);
        $u['permissions'] = array_column($perms, 'granted', 'module');
    }
    Response::success('Users fetched', $users);
}

// GET permissions for a specific user
if ($method === 'GET' && $section === 'user') {
    $userId = $_GET['id'] ?? '';
    if (!$userId) Response::error('User ID required');
    $perms = $db->fetchAll("SELECT module, granted FROM user_permissions WHERE user_id=?", $userId);
    Response::success('Permissions fetched', array_column($perms, 'granted', 'module'));
}

// POST — set permissions for a user
if ($method === 'POST' && $section === 'set') {
    $userId = $body['user_id'] ?? '';
    $perms  = $body['permissions'] ?? []; // { module: bool }
    if (!$userId) Response::error('User ID required');

    $db->begin();
    foreach ($perms as $module => $granted) {
        $module  = preg_replace('/[^a-z0-9_]/', '', strtolower($module));
        $granted = (bool)$granted;

        // Get old value for audit
        $old = $db->fetchOne("SELECT granted FROM user_permissions WHERE user_id=? AND module=?", $userId, $module);

        $db->query(
            "INSERT INTO user_permissions (user_id, module, granted, granted_by)
             VALUES (?, ?, ?, ?)
             ON CONFLICT (user_id, module) DO UPDATE SET granted=EXCLUDED.granted, granted_by=EXCLUDED.granted_by, updated_at=NOW()",
            $userId, $module, $granted ? 'TRUE' : 'FALSE', $auth['user_id']
        );

        // Audit log
        $db->query(
            "INSERT INTO permission_audit (changed_by, target_user, module, old_value, new_value)
             VALUES (?, ?, ?, ?, ?)",
            $auth['user_id'], $userId, $module,
            $old ? ($old['granted'] ? 'TRUE' : 'FALSE') : null,
            $granted ? 'TRUE' : 'FALSE'
        );
    }
    $db->commit();
    Response::success('Permissions updated');
}

// GET audit log
if ($method === 'GET' && $section === 'audit') {
    $logs = $db->fetchAll(
        "SELECT pa.*, 
                cb.first_name||' '||cb.last_name AS changed_by_name,
                tu.first_name||' '||tu.last_name AS target_user_name
         FROM permission_audit pa
         JOIN users cb ON pa.changed_by = cb.id
         JOIN users tu ON pa.target_user = tu.id
         ORDER BY pa.created_at DESC LIMIT 100"
    );
    Response::success('Audit log fetched', $logs);
}

Response::error('Invalid request', 404);
