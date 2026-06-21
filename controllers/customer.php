<?php
require_once __DIR__ . '/../helpers/helpers.php';
require_once __DIR__ . '/../middleware/auth.php';

$db     = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$auth   = authMiddleware();

$customer = $db->fetchOne(
    "SELECT c.*, u.first_name, u.last_name, u.email, u.mobile
     FROM customers c JOIN users u ON c.user_id=u.id WHERE c.user_id=?",
    's', $auth['user_id']
);
if (!$customer) Response::error('Customer not found', 404);

$section = $_GET['section'] ?? '';

// GET profile
if ($method === 'GET' && $section === 'profile') {
    $stats = [
        'total_orders'   => $db->fetchOne("SELECT COUNT(*) AS v FROM orders WHERE customer_id=?", 's', $customer['id'])['v'],
        'wishlist_count' => $db->fetchOne("SELECT COUNT(*) AS v FROM wishlist WHERE customer_id=?", 's', $customer['id'])['v'],
        'review_count'   => $db->fetchOne("SELECT COUNT(*) AS v FROM reviews WHERE customer_id=?", 's', $customer['id'])['v'],
        'total_spent'    => $db->fetchOne("SELECT COALESCE(SUM(final_amount),0) AS v FROM orders WHERE customer_id=? AND payment_status='PAID'", 's', $customer['id'])['v'],
    ];
    Response::success('Profile fetched', array_merge($customer, ['stats' => $stats]));
}

// PUT profile
if ($method === 'PUT' && $section === 'profile') {
    $fields = ['first_name', 'last_name', 'email', 'mobile'];
    $sets = []; $params = [];
    foreach ($fields as $f) {
        if (isset($body[$f])) { $sets[] = "$f=?"; $params[] = $body[$f]; }
    }
    if (!$sets) Response::error('Nothing to update');
    $params[] = $auth['user_id'];
    $db->query("UPDATE users SET " . implode(',', $sets) . " WHERE id=?", '', ...$params);
    Response::success('Profile updated');
}

// GET addresses
if ($method === 'GET' && $section === 'addresses') {
    Response::success('Addresses fetched',
        $db->fetchAll("SELECT * FROM customer_addresses WHERE customer_id=? ORDER BY is_default DESC", 's', $customer['id']));
}

// POST address
if ($method === 'POST' && $section === 'addresses') {
    $err = Validator::required($body, ['full_name', 'mobile', 'address_line1', 'city', 'state', 'pincode']);
    if ($err) Response::error($err);
    if (!empty($body['is_default'])) {
        $db->query("UPDATE customer_addresses SET is_default=FALSE WHERE customer_id=?", 's', $customer['id']);
    }
    $addrId = OtpHelper::uuid();
    $db->query(
        "INSERT INTO customer_addresses (id, customer_id, full_name, mobile, address_line1, address_line2, city, state, pincode, address_type, is_default)
         VALUES (?,?,?,?,?,?,?,?,?,?,?)",
        'sssssssssss',
        $addrId, $customer['id'], $body['full_name'], $body['mobile'], $body['address_line1'],
        $body['address_line2'] ?? '', $body['city'], $body['state'], $body['pincode'],
        $body['address_type'] ?? 'HOME', $body['is_default'] ?? false
    );
    Response::success('Address added', ['id' => $addrId], 201);
}

// DELETE address
if ($method === 'DELETE' && $section === 'addresses') {
    $addrId = $_GET['id'] ?? '';
    $db->query("DELETE FROM customer_addresses WHERE id=? AND customer_id=?", 'ss', $addrId, $customer['id']);
    Response::success('Address deleted');
}

Response::error('Invalid request', 404);
