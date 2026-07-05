<?php
require_once __DIR__ . '/../helpers/helpers.php';
require_once __DIR__ . '/../middleware/auth.php';

$db     = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$id     = $_GET['id'] ?? '';

// GET /categories — list (no auth required for read)
if ($method === 'GET') {
    $sql = isset($_GET['parent_only'])
        ? "SELECT id, name, slug, icon, image_url, sort_order FROM categories WHERE is_active=TRUE AND parent_id IS NULL ORDER BY sort_order"
        : "SELECT c.id, c.name, c.slug, c.icon, c.image_url, c.sort_order, c.parent_id, c.is_featured,
                  p.name AS parent_name
           FROM categories c LEFT JOIN categories p ON c.parent_id=p.id
           WHERE c.is_active=TRUE ORDER BY c.sort_order";
    Response::success('Categories fetched', $db->fetchAll($sql));
}

// POST /categories — create (admin required)
if ($method === 'POST') {
    $auth = adminMiddleware();
    $err = Validator::required($body, ['name']);
    if ($err) Response::error($err);
    
    $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $body['name'])) . '-' . time();
    $categoryId = $db->fetchOne("SELECT gen_random_uuid() AS id")['id'];
    
    $db->query(
        "INSERT INTO categories (id, name, slug, parent_id, icon, image_url, sort_order, is_featured, is_active)
         VALUES (?,?,?,?,?,?,?,?,?)",
        $categoryId,
        $body['name'],
        $slug,
        !empty($body['parent_id']) ? $body['parent_id'] : null,
        $body['icon'] ?? '📦',
        $body['image_url'] ?? '',
        (int)($body['sort_order'] ?? 0),
        !empty($body['is_featured']) ? 'TRUE' : 'FALSE',
        !empty($body['is_active']) ? 'TRUE' : 'FALSE'
    );
    
    Response::success('Category created', ['id' => $categoryId], 201);
}

// PUT /categories?id=X — update (admin required)
if ($method === 'PUT' && $id) {
    $auth = adminMiddleware();
    if (!Validator::uuid($id)) Response::error('Invalid category ID', 400);
    
    $allowed = ['name', 'parent_id', 'icon', 'image_url', 'sort_order', 'is_featured', 'is_active'];
    $sets = []; $params = [];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $body)) {
            if ($f === 'parent_id') {
                $sets[] = "$f=?";
                $params[] = !empty($body[$f]) ? $body[$f] : null;
            } else {
                $sets[] = "$f=?";
                $params[] = $body[$f];
            }
        }
    }
    if (!$sets) Response::error('Nothing to update');
    $params[] = $id;
    
    $db->query("UPDATE categories SET " . implode(',', $sets) . " WHERE id=?", ...$params);
    Response::success('Category updated');
}

// DELETE /categories?id=X — soft delete (admin required)
if ($method === 'DELETE' && $id) {
    $auth = adminMiddleware();
    if (!Validator::uuid($id)) Response::error('Invalid category ID', 400);
    $db->query("UPDATE categories SET is_active=FALSE WHERE id=?", $id);
    Response::success('Category deleted');
}

Response::error('Invalid request', 404);
