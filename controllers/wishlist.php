<?php
require_once __DIR__ . '/../helpers/helpers.php';
require_once __DIR__ . '/../middleware/auth.php';

$db     = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$auth   = authMiddleware();

$customer = $db->fetchOne("SELECT id FROM customers WHERE user_id=?", 'i', $auth['user_id']);
if (!$customer) Response::error('Customer not found', 404);

// GET /wishlist
if ($method === 'GET') {
    $items = $db->fetchAll(
        "SELECT w.id, p.id AS product_id, p.name, p.selling_price, p.mrp, p.stock_qty,
                (SELECT image_url FROM product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) AS image,
                b.name AS brand_name
         FROM wishlist w
         JOIN products p ON w.product_id=p.id
         LEFT JOIN brands b ON p.brand_id=b.id
         WHERE w.customer_id=? AND p.is_active=1 ORDER BY w.added_at DESC",
        'i', $customer['id']
    );
    Response::success('Wishlist fetched', $items);
}

// POST /wishlist
if ($method === 'POST') {
    $productId = (int)($body['product_id'] ?? 0);
    if (!$productId) Response::error('Product ID required');
    $db->query("INSERT IGNORE INTO wishlist (customer_id, product_id) VALUES (?,?)", 'ii', $customer['id'], $productId);
    Response::success('Added to wishlist');
}

// DELETE /wishlist?product_id=X
if ($method === 'DELETE') {
    $productId = (int)($_GET['product_id'] ?? 0);
    $db->query("DELETE FROM wishlist WHERE customer_id=? AND product_id=?", 'ii', $customer['id'], $productId);
    Response::success('Removed from wishlist');
}

Response::error('Invalid request', 404);
