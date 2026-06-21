<?php
require_once __DIR__ . '/../helpers/helpers.php';
require_once __DIR__ . '/../middleware/auth.php';

$db     = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$auth   = authMiddleware();
$id     = $_GET['id'] ?? '';

$customer = $db->fetchOne("SELECT id FROM customers WHERE user_id=?", 's', $auth['user_id']);
if (!$customer) Response::error('Customer not found', 404);

// GET /orders — list
if ($method === 'GET' && !$id) {
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = 10;
    $offset = ($page - 1) * $limit;
    $status = $_GET['status'] ?? '';

    $where = 'o.customer_id=?'; $params = [$customer['id']];
    if ($status) { $where .= ' AND o.order_status=?'; $params[] = strtoupper($status); }

    $orders = $db->fetchAll(
        "SELECT o.*, COUNT(oi.id) AS item_count FROM orders o
         LEFT JOIN order_items oi ON o.id=oi.order_id
         WHERE $where GROUP BY o.id ORDER BY o.created_at DESC LIMIT $limit OFFSET $offset",
        '', ...$params
    );
    foreach ($orders as &$order) {
        $order['items'] = $db->fetchAll(
            "SELECT oi.*, p.name AS product_name FROM order_items oi
             JOIN products p ON oi.product_id=p.id WHERE oi.order_id=?", 's', $order['id']
        );
    }
    Response::success('Orders fetched', $orders);
}

// GET /orders?id=X — single order
if ($method === 'GET' && $id) {
    $order = $db->fetchOne("SELECT * FROM orders WHERE id=? AND customer_id=?", 'ss', $id, $customer['id']);
    if (!$order) Response::error('Order not found', 404);
    $order['items']   = $db->fetchAll(
        "SELECT oi.*, p.name AS product_name FROM order_items oi
         JOIN products p ON oi.product_id=p.id WHERE oi.order_id=?", 's', $id
    );
    $order['payment'] = $db->fetchOne("SELECT * FROM payments WHERE order_id=?", 's', $id);
    $order['address'] = $db->fetchOne("SELECT * FROM customer_addresses WHERE id=?", 's', $order['address_id'] ?? '');
    Response::success('Order fetched', $order);
}

// POST /orders — place order
if ($method === 'POST') {
    $err = Validator::required($body, ['address_id', 'payment_method']);
    if ($err) Response::error($err);

    $cartItems = $db->fetchAll(
        "SELECT c.quantity, p.id AS product_id, p.name, p.selling_price, p.stock_qty
         FROM cart c JOIN products p ON c.product_id=p.id
         WHERE c.customer_id=? AND p.is_active=TRUE", 's', $customer['id']
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
            "SELECT * FROM coupons WHERE code=? AND is_active=TRUE AND valid_from<=NOW() AND valid_to>=NOW()",
            's', strtoupper($body['coupon_code'])
        );
        if ($coupon && $subtotal >= $coupon['minimum_order_amount']) {
            $discount = $coupon['discount_type'] === 'percentage'
                ? min($subtotal * $coupon['discount_value'] / 100, $coupon['max_discount'] ?? PHP_INT_MAX)
                : $coupon['discount_value'];
            $db->query("UPDATE coupons SET used_count=used_count+1 WHERE id=?", 's', $coupon['id']);
        }
    }

    $total       = $subtotal - $discount + $delivery;
    $orderNumber = 'DA-' . date('Y') . '-' . str_pad((string)random_int(1, 99999), 5, '0', STR_PAD_LEFT);
    $orderId     = OtpHelper::uuid();

    $db->query(
        "INSERT INTO orders (id,order_number,customer_id,total_amount,discount_amount,shipping_charge,final_amount,payment_status,order_status)
         VALUES (?,?,?,?,?,?,?,'PENDING','PLACED')",
        'sssdddd', $orderId, $orderNumber, $customer['id'], $subtotal, $discount, $delivery, $total
    );

    foreach ($cartItems as $item) {
        $db->query(
            "INSERT INTO order_items (id,order_id,product_id,quantity,price,total) VALUES (?,?,?,?,?,?)",
            'sssid d', OtpHelper::uuid(), $orderId, $item['product_id'],
            $item['quantity'], $item['selling_price'], $item['selling_price'] * $item['quantity']
        );
        $db->query("UPDATE products SET stock_qty=stock_qty-?, sold_count=sold_count+? WHERE id=?",
            'iis', $item['quantity'], $item['quantity'], $item['product_id']);
    }

    $db->query("DELETE FROM cart WHERE customer_id=?", 's', $customer['id']);
    $db->query(
        "INSERT INTO payments (id,order_id,payment_method,amount,payment_status) VALUES (?,?,?,?,'PENDING')",
        'sssd', OtpHelper::uuid(), $orderId, strtoupper($body['payment_method']), $total
    );

    Response::success('Order placed successfully', [
        'order_id'     => $orderId,
        'order_number' => $orderNumber,
        'total'        => $total
    ], 201);
}

// PUT /orders?id=X — cancel order
if ($method === 'PUT' && $id) {
    $order = $db->fetchOne("SELECT * FROM orders WHERE id=? AND customer_id=?", 'ss', $id, $customer['id']);
    if (!$order) Response::error('Order not found', 404);
    if (!in_array($order['order_status'], ['PLACED', 'CONFIRMED'])) {
        Response::error('Order cannot be cancelled at this stage');
    }
    $db->query("UPDATE orders SET order_status='CANCELLED' WHERE id=?", 's', $id);
    $items = $db->fetchAll("SELECT * FROM order_items WHERE order_id=?", 's', $id);
    foreach ($items as $item) {
        $db->query("UPDATE products SET stock_qty=stock_qty+? WHERE id=?", 'is', $item['quantity'], $item['product_id']);
    }
    Response::success('Order cancelled');
}

Response::error('Invalid request', 404);
