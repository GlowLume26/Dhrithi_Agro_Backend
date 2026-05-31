<?php
require_once __DIR__ . '/../helpers/helpers.php';
require_once __DIR__ . '/../middleware/auth.php';

$db     = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$auth   = authMiddleware();
$id     = (int)($_GET['id'] ?? 0);

$customer = $db->fetchOne("SELECT id FROM customers WHERE user_id=?", 'i', $auth['user_id']);
if (!$customer) Response::error('Customer not found', 404);

// GET /orders — list
if ($method === 'GET' && !$id) {
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = 10;
    $offset = ($page - 1) * $limit;
    $status = $_GET['status'] ?? '';

    $where  = 'o.customer_id=?';
    $params = [$customer['id']];
    $types  = 'i';
    if ($status) { $where .= ' AND o.order_status=?'; $params[] = strtoupper($status); $types .= 's'; }

    $orders = $db->fetchAll(
        "SELECT o.*, COUNT(oi.id) AS item_count FROM orders o
         LEFT JOIN order_items oi ON o.id=oi.order_id
         WHERE $where GROUP BY o.id ORDER BY o.placed_at DESC LIMIT $limit OFFSET $offset",
        $types, ...$params
    );
    foreach ($orders as &$order) {
        $order['items'] = $db->fetchAll("SELECT * FROM order_items WHERE order_id=?", 'i', $order['id']);
    }
    Response::success('Orders fetched', $orders);
}

// GET /orders?id=X — single order
if ($method === 'GET' && $id) {
    $order = $db->fetchOne("SELECT * FROM orders WHERE id=? AND customer_id=?", 'ii', $id, $customer['id']);
    if (!$order) Response::error('Order not found', 404);
    $order['items']   = $db->fetchAll("SELECT * FROM order_items WHERE order_id=?", 'i', $id);
    $order['payment'] = $db->fetchOne("SELECT * FROM payments WHERE order_id=?", 'i', $id);
    $order['address'] = $db->fetchOne("SELECT * FROM addresses WHERE id=?", 'i', $order['address_id']);
    Response::success('Order fetched', $order);
}

// POST /orders — place order
if ($method === 'POST') {
    $err = Validator::required($body, ['address_id', 'payment_method']);
    if ($err) Response::error($err);

    $cartItems = $db->fetchAll(
        "SELECT c.quantity, p.id AS product_id, p.vendor_id, p.name, p.selling_price, p.stock_qty,
                (SELECT image_url FROM product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) AS image
         FROM cart c JOIN products p ON c.product_id=p.id
         WHERE c.customer_id=? AND p.is_active=1", 'i', $customer['id']
    );
    if (empty($cartItems)) Response::error('Cart is empty');

    foreach ($cartItems as $item) {
        if ($item['quantity'] > $item['stock_qty']) Response::error("Insufficient stock for: {$item['name']}");
    }

    $subtotal = array_sum(array_map(fn($i) => $i['selling_price'] * $i['quantity'], $cartItems));
    $delivery = $subtotal >= 499 ? 0 : 49;
    $discount = 0;

    if (!empty($body['coupon_code'])) {
        $coupon = $db->fetchOne(
            "SELECT * FROM offers WHERE code=? AND is_active=1 AND valid_from<=NOW() AND valid_until>=NOW()",
            's', strtoupper($body['coupon_code'])
        );
        if ($coupon && $subtotal >= $coupon['min_order_value']) {
            $discount = $coupon['discount_type'] === 'PERCENTAGE'
                ? min($subtotal * $coupon['discount_value'] / 100, $coupon['max_discount'] ?? PHP_INT_MAX)
                : $coupon['discount_value'];
            $db->query("UPDATE offers SET used_count=used_count+1 WHERE id=?", 'i', $coupon['id']);
        }
    }

    $total       = $subtotal - $discount + $delivery;
    $orderNumber = 'DA-' . date('Y') . '-' . str_pad((string)random_int(1, 99999), 5, '0', STR_PAD_LEFT);

    $db->query(
        "INSERT INTO orders (order_number, customer_id, address_id, subtotal, discount_amount, delivery_charge, total_amount, coupon_code, payment_method)
         VALUES (?,?,?,?,?,?,?,?,?)",
        'siidddds s',
        $orderNumber, $customer['id'], (int)$body['address_id'],
        $subtotal, $discount, $delivery, $total,
        $body['coupon_code'] ?? '', strtoupper($body['payment_method'])
    );
    $orderId = $db->lastInsertId();

    foreach ($cartItems as $item) {
        $db->query(
            "INSERT INTO order_items (order_id, product_id, vendor_id, product_name, product_image, quantity, unit_price, total_price)
             VALUES (?,?,?,?,?,?,?,?)",
            'iiiissdd',
            $orderId, $item['product_id'], $item['vendor_id'],
            $item['name'], $item['image'] ?? '',
            $item['quantity'], $item['selling_price'],
            $item['selling_price'] * $item['quantity']
        );
        $db->query("UPDATE products SET stock_qty=stock_qty-?, sold_count=sold_count+? WHERE id=?",
            'iii', $item['quantity'], $item['quantity'], $item['product_id']);
    }

    $db->query("DELETE FROM cart WHERE customer_id=?", 'i', $customer['id']);
    $db->query("INSERT INTO payments (order_id, amount, status) VALUES (?,?,'CREATED')", 'id', $orderId, $total);

    Response::success('Order placed successfully', [
        'order_id'     => $orderId,
        'order_number' => $orderNumber,
        'total'        => $total
    ], 201);
}

// PUT /orders?id=X — cancel order
if ($method === 'PUT' && $id) {
    $order = $db->fetchOne("SELECT * FROM orders WHERE id=? AND customer_id=?", 'ii', $id, $customer['id']);
    if (!$order) Response::error('Order not found', 404);
    if (!in_array($order['order_status'], ['PLACED', 'CONFIRMED'])) {
        Response::error('Order cannot be cancelled at this stage');
    }
    $db->query("UPDATE orders SET order_status='CANCELLED' WHERE id=?", 'i', $id);
    $items = $db->fetchAll("SELECT * FROM order_items WHERE order_id=?", 'i', $id);
    foreach ($items as $item) {
        $db->query("UPDATE products SET stock_qty=stock_qty+? WHERE id=?", 'ii', $item['quantity'], $item['product_id']);
    }
    Response::success('Order cancelled');
}

Response::error('Invalid request', 404);
