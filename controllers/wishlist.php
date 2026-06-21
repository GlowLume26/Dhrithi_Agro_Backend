<?php
require_once __DIR__ . '/../helpers/helpers.php';
require_once __DIR__ . '/../middleware/auth.php';

$db     = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$auth   = authMiddleware();

$customer = $db->fetchOne("SELECT id FROM customers WHERE user_id=?", 's', $auth['user_id']);
if (!$customer) Response::error('Customer not found', 404);

// GET /wishlist
if ($method === 'GET') {
    $items = $db->fetchAll(
        "SELECT w.id, p.id AS product_id, p.name, p.selling_price, p.mrp, p.stock_qty,
                (SELECT image_url FROM product_images WHERE product_id=p.id AND is_primary=TRUE LIMIT 1) AS image
         FROM wishlist w
         JOIN products p ON w.product_id=p.id
         WHERE w.customer_id=? AND p.is_active=TRUE ORDER BY w.created_at DESC",
        's', $customer['id']
    );
    Response::success('Wishlist fetched', $items);
}

// POST /wishlist
if ($method === 'POST') {
    $productId = $body['product_id'] ?? '';
    if (!$productId) Response::error('Product ID required');
    $db->query(
        "INSERT INTO wishlist (id, customer_id, product_id) VALUES (?,?,?) ON CONFLICT DO NOTHING",
        'sss', OtpHelper::uuid(), $customer['id'], $productId
    );
    Response::success('Added to wishlist');
}

// DELETE /wishlist?product_id=X
if ($method === 'DELETE') {
    $productId = $_GET['product_id'] ?? '';
    $db->query("DELETE FROM wishlist WHERE customer_id=? AND product_id=?", 'ss', $customer['id'], $productId);
    Response::success('Removed from wishlist');
}

Response::error('Invalid request', 404);
