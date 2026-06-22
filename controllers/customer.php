<?php
require_once __DIR__ . '/../helpers/helpers.php';
require_once __DIR__ . '/../middleware/auth.php';

$db     = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$auth   = authMiddleware();

$customer = $db->fetchOne(
    "SELECT c.*, u.first_name, u.last_name, u.email, u.mobile
     FROM customers c JOIN users u ON c.user_id=u.id WHERE c.user_id=?", $auth['user_id']
);
if (!$customer) Response::error('Customer not found', 404);

$section = $_GET['section'] ?? '';

if ($method === 'GET' && $section === 'profile') {
    $stats = $db->fetchOne(
        "SELECT
            COUNT(DISTINCT o.id)                                                  AS total_orders,
            COUNT(DISTINCT w.id)                                                  AS wishlist_count,
            COUNT(DISTINCT r.id)                                                  AS review_count,
            COALESCE(SUM(CASE WHEN o.payment_status='paid' THEN o.final_amount ELSE 0 END),0) AS total_spent
         FROM customers c
         LEFT JOIN orders o   ON o.customer_id=c.id
         LEFT JOIN wishlist w ON w.customer_id=c.id
         LEFT JOIN reviews r  ON r.customer_id=c.id
         WHERE c.id=?", $customer['id']
    );
    Response::success('Profile fetched', array_merge($customer, ['stats' => $stats]));
}

if ($method === 'PUT' && $section === 'profile') {
    $allowed = ['first_name','last_name','email','mobile'];
    $sets = []; $params = [];
    foreach ($allowed as $f) {
        if (!array_key_exists($f, $body)) continue;
        if ($f === 'email'  && !Validator::email($body[$f]))  Response::error('Invalid email address');
        if ($f === 'mobile' && !Validator::mobile($body[$f])) Response::error('Invalid mobile number');
        $sets[] = "$f=?"; $params[] = $body[$f];
    }
    if (!$sets) Response::error('Nothing to update');
    $params[] = $auth['user_id'];
    $db->query("UPDATE users SET " . implode(',', $sets) . ", updated_at=NOW() WHERE id=?", ...$params);
    Response::success('Profile updated');
}

if ($method === 'GET' && $section === 'addresses') {
    Response::success('Addresses fetched',
        $db->fetchAll("SELECT * FROM customer_addresses WHERE customer_id=? ORDER BY is_default DESC, created_at DESC", $customer['id']));
}

if ($method === 'POST' && $section === 'addresses') {
    $err = Validator::required($body, ['full_name','mobile','address_line1','city','state','pincode']);
    if ($err) Response::error($err);
    if (!empty($body['is_default'])) {
        $db->query("UPDATE customer_addresses SET is_default=FALSE WHERE customer_id=?", $customer['id']);
    }
    $type = strtolower($body['address_type'] ?? 'home');
    if (!in_array($type, ['home','work','other'])) $type = 'home';
    $db->query(
        "INSERT INTO customer_addresses (id,customer_id,full_name,mobile,address_line1,address_line2,city,state,pincode,country,address_type,is_default)
         VALUES (gen_random_uuid(),?,?,?,?,?,?,?,?,?,?,?)",
        $customer['id'], $body['full_name'], $body['mobile'], $body['address_line1'],
        $body['address_line2'] ?? '', $body['city'], $body['state'], $body['pincode'],
        $body['country'] ?? 'India', $type, !empty($body['is_default'])
    );
    $addr = $db->fetchOne("SELECT id FROM customer_addresses WHERE customer_id=? ORDER BY created_at DESC LIMIT 1", $customer['id']);
    Response::success('Address added', ['id' => $addr['id']], 201);
}

if ($method === 'DELETE' && $section === 'addresses') {
    $addrId = $_GET['id'] ?? '';
    if (!$addrId) Response::error('Address ID required');
    $db->query("DELETE FROM customer_addresses WHERE id=? AND customer_id=?", $addrId, $customer['id']);
    Response::success('Address deleted');
}

Response::error('Invalid request', 404);
