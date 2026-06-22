<?php
require_once __DIR__ . '/../helpers/helpers.php';
require_once __DIR__ . '/../middleware/auth.php';

$db     = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$auth   = authMiddleware();

$customer = $db->fetchOne("SELECT id FROM customers WHERE user_id=?", $auth['user_id']);
if (!$customer) Response::error('Customer not found', 404);

if ($method === 'GET') {
    $items = $db->fetchAll(
        "SELECT w.id, w.created_at, p.id AS product_id, p.name, p.selling_price, p.mrp, p.stock_qty,
                pi.image_url AS image
         FROM wishlist w
         JOIN products p ON w.product_id=p.id
         LEFT JOIN LATERAL (SELECT image_url FROM product_images WHERE product_id=p.id AND is_primary=TRUE LIMIT 1) pi ON TRUE
         WHERE w.customer_id=? AND p.is_active=TRUE ORDER BY w.created_at DESC", $customer['id']
    );
    Response::success('Wishlist fetched', $items);
}

if ($method === 'POST') {
    $productId = $body['product_id'] ?? '';
    if (!$productId) Response::error('Product ID required');
    if (!$db->fetchOne("SELECT id FROM products WHERE id=? AND is_active=TRUE", $productId)) Response::error('Product not found', 404);
    $db->query(
        "INSERT INTO wishlist (id,customer_id,product_id) VALUES (gen_random_uuid(),?,?) ON CONFLICT (customer_id,product_id) DO NOTHING",
        $customer['id'], $productId
    );
    Response::success('Added to wishlist');
}

if ($method === 'DELETE') {
    $productId = $_GET['product_id'] ?? '';
    if (!$productId) Response::error('Product ID required');
    $db->query("DELETE FROM wishlist WHERE customer_id=? AND product_id=?", $customer['id'], $productId);
    Response::success('Removed from wishlist');
}

Response::error('Invalid request', 404);
