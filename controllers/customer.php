<?php
require_once __DIR__ . '/../helpers/helpers.php';
require_once __DIR__ . '/../middleware/auth.php';

$db     = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$auth   = authMiddleware();

$customer = $db->fetchOne("SELECT * FROM customers WHERE user_id=?", 'i', $auth['user_id']);
if (!$customer) Response::error('Customer not found', 404);

$section = $_GET['section'] ?? '';

// GET profile
if ($method === 'GET' && $section === 'profile') {
    $stats = [
        'total_orders'   => $db->fetchOne("SELECT COUNT(*) AS v FROM orders WHERE customer_id=?", 'i', $customer['id'])['v'],
        'wishlist_count' => $db->fetchOne("SELECT COUNT(*) AS v FROM wishlist WHERE customer_id=?", 'i', $customer['id'])['v'],
        'review_count'   => $db->fetchOne("SELECT COUNT(*) AS v FROM reviews WHERE customer_id=?", 'i', $customer['id'])['v'],
        'total_spent'    => $db->fetchOne("SELECT COALESCE(SUM(total_amount),0) AS v FROM orders WHERE customer_id=? AND payment_status='PAID'", 'i', $customer['id'])['v'],
    ];
    Response::success('Profile fetched', array_merge($customer, ['stats' => $stats]));
}

// PUT profile
if ($method === 'PUT' && $section === 'profile') {
    $fields = ['full_name', 'email', 'dob', 'gender', 'occupation', 'farm_size', 'primary_crop'];
    $sets = []; $params = []; $types = '';
    foreach ($fields as $f) {
        if (isset($body[$f])) {
            $sets[]   = "$f=?";
            $params[] = $body[$f];
            $types   .= $f === 'farm_size' ? 'd' : 's';
        }
    }
    if (!$sets) Response::error('Nothing to update');
    $params[] = $customer['id']; $types .= 'i';
    $db->query("UPDATE customers SET " . implode(',', $sets) . " WHERE id=?", $types, ...$params);
    Response::success('Profile updated');
}

// GET addresses
if ($method === 'GET' && $section === 'addresses') {
    Response::success('Addresses fetched',
        $db->fetchAll("SELECT * FROM addresses WHERE customer_id=? ORDER BY is_default DESC", 'i', $customer['id']));
}

// POST address
if ($method === 'POST' && $section === 'addresses') {
    $err = Validator::required($body, ['full_name', 'mobile', 'address_line1', 'city', 'state', 'pincode']);
    if ($err) Response::error($err);
    if (!empty($body['is_default'])) {
        $db->query("UPDATE addresses SET is_default=0 WHERE customer_id=?", 'i', $customer['id']);
    }
    $db->query(
        "INSERT INTO addresses (customer_id, full_name, mobile, address_line1, address_line2, city, state, pincode, address_type, is_default)
         VALUES (?,?,?,?,?,?,?,?,?,?)",
        'issssssssi',
        $customer['id'], $body['full_name'], $body['mobile'], $body['address_line1'],
        $body['address_line2'] ?? '', $body['city'], $body['state'], $body['pincode'],
        $body['address_type'] ?? 'HOME', (int)($body['is_default'] ?? 0)
    );
    Response::success('Address added', ['id' => $db->lastInsertId()], 201);
}

// DELETE address
if ($method === 'DELETE' && $section === 'addresses') {
    $addrId = (int)($_GET['id'] ?? 0);
    $db->query("DELETE FROM addresses WHERE id=? AND customer_id=?", 'ii', $addrId, $customer['id']);
    Response::success('Address deleted');
}

Response::error('Invalid request', 404);
