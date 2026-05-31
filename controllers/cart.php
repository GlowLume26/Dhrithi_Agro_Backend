<?php
require_once __DIR__ . '/../helpers/helpers.php';
require_once __DIR__ . '/../middleware/auth.php';

$db     = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$auth   = authMiddleware();

$customer = $db->fetchOne("SELECT id FROM customers WHERE user_id=?", 'i', $auth['user_id']);
if (!$customer) Response::error('Customer not found', 404);

// GET /cart
if ($method === 'GET') {
    $items = $db->fetchAll(
        "SELECT c.id, c.quantity, p.id AS product_id, p.name, p.selling_price, p.mrp, p.stock_qty,
                (SELECT image_url FROM product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) AS image,
                v.store_name AS vendor_name
         FROM cart c
         JOIN products p ON c.product_id=p.id
         JOIN vendors v ON p.vendor_id=v.id
         WHERE c.customer_id=? AND p.is_active=1", 'i', $customer['id']
    );
    $subtotal = array_sum(array_map(fn($i) => $i['selling_price'] * $i['quantity'], $items));
    $mrpTotal = array_sum(array_map(fn($i) => $i['mrp'] * $i['quantity'], $items));
    $delivery = $subtotal >= 499 ? 0 : 49;
    Response::success('Cart fetched', [
        'items'     => $items,
        'subtotal'  => round($subtotal, 2),
        'mrp_total' => round($mrpTotal, 2),
        'savings'   => round($mrpTotal - $subtotal, 2),
        'delivery'  => $delivery,
        'total'     => round($subtotal + $delivery, 2)
    ]);
}

// POST /cart — add item
if ($method === 'POST') {
    $err = Validator::required($body, ['product_id']);
    if ($err) Response::error($err);
    $product = $db->fetchOne("SELECT id, stock_qty FROM products WHERE id=? AND is_active=1", 'i', (int)$body['product_id']);
    if (!$product) Response::error('Product not found', 404);
    $qty = max(1, (int)($body['quantity'] ?? 1));
    if ($qty > $product['stock_qty']) Response::error('Insufficient stock');
    $db->query(
        "INSERT INTO cart (customer_id, product_id, quantity) VALUES (?,?,?) ON DUPLICATE KEY UPDATE quantity=?",
        'iiii', $customer['id'], (int)$body['product_id'], $qty, $qty
    );
    Response::success('Added to cart');
}

// PUT /cart?id=X — update quantity
if ($method === 'PUT') {
    $cartId = (int)($_GET['id'] ?? 0);
    $qty    = max(1, (int)($body['quantity'] ?? 1));
    $db->query("UPDATE cart SET quantity=? WHERE id=? AND customer_id=?", 'iii', $qty, $cartId, $customer['id']);
    Response::success('Cart updated');
}

// DELETE /cart?id=X — remove item (or clear all if no id)
if ($method === 'DELETE') {
    $cartId = (int)($_GET['id'] ?? 0);
    if ($cartId) {
        $db->query("DELETE FROM cart WHERE id=? AND customer_id=?", 'ii', $cartId, $customer['id']);
    } else {
        $db->query("DELETE FROM cart WHERE customer_id=?", 'i', $customer['id']);
    }
    Response::success('Removed from cart');
}

Response::error('Invalid request', 404);
