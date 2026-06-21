<?php
require_once __DIR__ . '/../helpers/helpers.php';
require_once __DIR__ . '/../middleware/auth.php';

$db     = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$auth   = authMiddleware();

$customer = $db->fetchOne("SELECT id FROM customers WHERE user_id=?", 's', $auth['user_id']);
if (!$customer) Response::error('Customer not found', 404);

// GET /cart
if ($method === 'GET') {
    $items = $db->fetchAll(
        "SELECT c.id, c.quantity, p.id AS product_id, p.name, p.selling_price, p.mrp, p.stock_qty,
                (SELECT image_url FROM product_images WHERE product_id=p.id AND is_primary=TRUE LIMIT 1) AS image,
                v.business_name AS vendor_name
         FROM cart c
         JOIN products p ON c.product_id=p.id
         JOIN vendors v ON p.vendor_id=v.id
         WHERE c.customer_id=? AND p.is_active=TRUE", 's', $customer['id']
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
    $product = $db->fetchOne("SELECT id, stock_qty FROM products WHERE id=? AND is_active=TRUE", 's', $body['product_id']);
    if (!$product) Response::error('Product not found', 404);
    $qty = max(1, (int)($body['quantity'] ?? 1));
    if ($qty > $product['stock_qty']) Response::error('Insufficient stock');
    $db->query(
        "INSERT INTO cart (id, customer_id, product_id, quantity) VALUES (?,?,?,?)
         ON CONFLICT (customer_id, product_id) DO UPDATE SET quantity=EXCLUDED.quantity",
        'sssi', OtpHelper::uuid(), $customer['id'], $body['product_id'], $qty
    );
    Response::success('Added to cart');
}

// PUT /cart?id=X — update quantity
if ($method === 'PUT') {
    $cartId = $_GET['id'] ?? '';
    $qty    = max(1, (int)($body['quantity'] ?? 1));
    $db->query("UPDATE cart SET quantity=? WHERE id=? AND customer_id=?", 'iss', $qty, $cartId, $customer['id']);
    Response::success('Cart updated');
}

// DELETE /cart?id=X
if ($method === 'DELETE') {
    $cartId = $_GET['id'] ?? '';
    if ($cartId) {
        $db->query("DELETE FROM cart WHERE id=? AND customer_id=?", 'ss', $cartId, $customer['id']);
    } else {
        $db->query("DELETE FROM cart WHERE customer_id=?", 's', $customer['id']);
    }
    Response::success('Removed from cart');
}

Response::error('Invalid request', 404);
