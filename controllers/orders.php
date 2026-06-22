<?php
require_once __DIR__ . '/../helpers/helpers.php';
require_once __DIR__ . '/../middleware/auth.php';

$db     = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$auth   = authMiddleware();
$id     = $_GET['id'] ?? '';

$customer = $db->fetchOne("SELECT id FROM customers WHERE user_id=?", $auth['user_id']);
if (!$customer) Response::error('Customer not found', 404);

// GET /orders — list (fix N+1: fetch all items in one query)
if ($method === 'GET' && !$id) {
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = 10;
    $offset = ($page - 1) * $limit;
    $status = strtolower($_GET['status'] ?? '');

    $where = ['o.customer_id=?']; $params = [$customer['id']];
    if ($status) { $where[] = 'o.order_status=?'; $params[] = $status; }
    $whereStr = implode(' AND ', $where);

    $orders = $db->fetchAll(
        "SELECT o.id, o.order_number, o.total_amount, o.discount_amount, o.shipping_charge,
                o.final_amount, o.payment_status, o.order_status, o.created_at,
                COUNT(oi.id) AS item_count
         FROM orders o LEFT JOIN order_items oi ON o.id=oi.order_id
         WHERE $whereStr GROUP BY o.id ORDER BY o.created_at DESC LIMIT $limit OFFSET $offset",
        ...$params
    );

    if (!empty($orders)) {
        $orderIds   = array_column($orders, 'id');
        $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
        $allItems   = $db->fetchAll(
            "SELECT oi.order_id, oi.id, oi.product_id, oi.quantity, oi.price, oi.total, p.name AS product_name
             FROM order_items oi JOIN products p ON oi.product_id=p.id
             WHERE oi.order_id IN ($placeholders)",
            ...$orderIds
        );
        $itemsByOrder = [];
        foreach ($allItems as $item) $itemsByOrder[$item['order_id']][] = $item;
        foreach ($orders as &$order) $order['items'] = $itemsByOrder[$order['id']] ?? [];
    }
    Response::success('Orders fetched', $orders);
}

// GET /orders?id=X — single order
if ($method === 'GET' && $id) {
    if (!Validator::uuid($id)) Response::error('Invalid order ID', 400);
    $order = $db->fetchOne("SELECT * FROM orders WHERE id=? AND customer_id=?", $id, $customer['id']);
    if (!$order) Response::error('Order not found', 404);
    $order['items']   = $db->fetchAll(
        "SELECT oi.*, p.name AS product_name FROM order_items oi
         JOIN products p ON oi.product_id=p.id WHERE oi.order_id=?", $id
    );
    $order['payment'] = $db->fetchOne("SELECT * FROM payments WHERE order_id=?", $id);
    $order['address'] = $order['address_id'] ? $db->fetchOne("SELECT * FROM customer_addresses WHERE id=?", $order['address_id']) : null;
    Response::success('Order fetched', $order);
}

// POST /orders — place order
if ($method === 'POST') {
    $err = Validator::required($body, ['address_id', 'payment_method']);
    if ($err) Response::error($err);

    $validMethods = ['cod','upi','card','net_banking','wallet'];
    if (!in_array(strtolower($body['payment_method']), $validMethods)) Response::error('Invalid payment method');

    $cartItems = $db->fetchAll(
        "SELECT c.quantity, p.id AS product_id, p.name, p.selling_price, p.stock_qty
         FROM cart c JOIN products p ON c.product_id=p.id
         WHERE c.customer_id=? AND p.is_active=TRUE", $customer['id']
    );
    if (empty($cartItems)) Response::error('Cart is empty');
    foreach ($cartItems as $item) {
        if ($item['quantity'] > $item['stock_qty']) Response::error("Insufficient stock for: {$item['name']}");
    }

    $subtotal = array_sum(array_map(fn($i) => $i['selling_price'] * $i['quantity'], $cartItems));
    $delivery = $subtotal >= 499 ? 0.0 : 49.0;
    $discount = 0.0;

    if (!empty($body['coupon_code'])) {
        $coupon = $db->fetchOne(
            "SELECT * FROM coupons WHERE code=? AND is_active=TRUE AND valid_from<=NOW() AND valid_to>=NOW()",
            strtoupper(trim($body['coupon_code']))
        );
        if ($coupon && $subtotal >= $coupon['minimum_order_amount']) {
            $discount = $coupon['discount_type'] === 'percentage'
                ? min($subtotal * $coupon['discount_value'] / 100, $coupon['max_discount'] ?? PHP_INT_MAX)
                : (float)$coupon['discount_value'];
        }
    }

    $total   = round($subtotal - $discount + $delivery, 2);
    $orderNum = 'DA-' . date('Y') . '-' . str_pad((string)random_int(1, 99999), 5, '0', STR_PAD_LEFT);

    $db->begin();
    try {
        $orderId = $db->fetchOne("SELECT gen_random_uuid() AS id")['id'];
        $db->query(
            "INSERT INTO orders (id,order_number,customer_id,address_id,total_amount,discount_amount,shipping_charge,final_amount,payment_status,order_status)
             VALUES (?,?,?,?,?,?,?,?,'pending','placed')",
            $orderId, $orderNum, $customer['id'], $body['address_id'], (float)$subtotal, $discount, $delivery, $total
        );

        foreach ($cartItems as $item) {
            $db->query(
                "INSERT INTO order_items (id,order_id,product_id,quantity,price,total) VALUES (gen_random_uuid(),?,?,?,?,?)",
                $orderId, $item['product_id'], $item['quantity'],
                (float)$item['selling_price'], round($item['selling_price'] * $item['quantity'], 2)
            );
            $db->query("UPDATE products SET stock_qty=stock_qty-?, sold_count=sold_count+? WHERE id=?",
                $item['quantity'], $item['quantity'], $item['product_id']);
            $db->query("UPDATE inventory SET current_stock=current_stock-? WHERE product_id=?",
                $item['quantity'], $item['product_id']);
        }

        if (!empty($body['coupon_code']) && isset($coupon)) {
            $db->query("UPDATE coupons SET used_count=used_count+1 WHERE id=?", $coupon['id']);
            $db->query("INSERT INTO coupon_usage (id,coupon_id,customer_id,order_id) VALUES (gen_random_uuid(),?,?,?)",
                $coupon['id'], $customer['id'], $orderId);
        }

        $db->query("DELETE FROM cart WHERE customer_id=?", $customer['id']);
        $db->query(
            "INSERT INTO payments (id,order_id,payment_method,amount,payment_status) VALUES (gen_random_uuid(),?,?,?,'pending')",
            $orderId, strtolower($body['payment_method']), $total
        );
        $db->query(
            "INSERT INTO order_status_history (id,order_id,status,remarks) VALUES (gen_random_uuid(),?,?,'Order placed')",
            $orderId, 'placed'
        );
        $db->commit();
    } catch (Exception $e) {
        $db->rollback();
        Response::error('Order placement failed. Please try again.', 500);
    }

    Response::success('Order placed successfully', ['order_id' => $orderId, 'order_number' => $orderNum, 'total' => $total], 201);
}

// PUT /orders?id=X — cancel order
if ($method === 'PUT' && $id) {
    if (!Validator::uuid($id)) Response::error('Invalid order ID', 400);
    $order = $db->fetchOne("SELECT * FROM orders WHERE id=? AND customer_id=?", $id, $customer['id']);
    if (!$order) Response::error('Order not found', 404);
    if (!in_array($order['order_status'], ['placed', 'confirmed'])) Response::error('Order cannot be cancelled at this stage');

    $db->begin();
    $db->query("UPDATE orders SET order_status='cancelled' WHERE id=?", $id);
    $items = $db->fetchAll("SELECT product_id, quantity FROM order_items WHERE order_id=?", $id);
    foreach ($items as $item) {
        $db->query("UPDATE products SET stock_qty=stock_qty+? WHERE id=?", $item['quantity'], $item['product_id']);
        $db->query("UPDATE inventory SET current_stock=current_stock+? WHERE product_id=?", $item['quantity'], $item['product_id']);
    }
    $db->query("INSERT INTO order_status_history (id,order_id,status,remarks) VALUES (gen_random_uuid(),?,?,'Cancelled by customer')", $id, 'cancelled');
    $db->commit();
    Response::success('Order cancelled');
}

Response::error('Invalid request', 404);
