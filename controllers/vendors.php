<?php
require_once __DIR__ . '/../helpers/helpers.php';
require_once __DIR__ . '/../middleware/auth.php';

$db     = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? '';

// GET /vendors — list approved vendors
if ($method === 'GET' && !$action) {
    $vendors = $db->fetchAll(
        "SELECT id, vendor_code, business_name, owner_name, city, state, is_verified, status
         FROM vendors WHERE status='approved' ORDER BY created_at DESC LIMIT 20"
    );
    Response::success('Vendors fetched', $vendors);
}

// POST /vendors?action=register
if ($method === 'POST' && $action === 'register') {
    $err = Validator::required($body, ['business_name', 'owner_name', 'mobile', 'email', 'gst_number', 'pan_number', 'address', 'city', 'state', 'pincode']);
    if ($err) Response::error($err);
    if (!Validator::mobile($body['mobile'])) Response::error('Invalid mobile number');
    if (!Validator::email($body['email']))   Response::error('Invalid email address');
    if ($db->fetchOne("SELECT id FROM vendors WHERE gst_number=?", 's', $body['gst_number'])) {
        Response::error('GST number already registered');
    }

    $userId   = OtpHelper::uuid();
    $vendorId = OtpHelper::uuid();
    $db->query("INSERT INTO users (id,first_name,last_name,email,mobile,password_hash,role,is_active) VALUES (?,?,?,?,?,?,'vendor',FALSE)",
        'ssssss', $userId, $body['owner_name'], '', $body['email'], $body['mobile'], password_hash(uniqid(), PASSWORD_DEFAULT));
    $db->query(
        "INSERT INTO vendors (id, user_id, business_name, owner_name, email, mobile, gst_number, pan_number, address, city, state, pincode, status)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'pending')",
        'ssssssssssss',
        $vendorId, $userId, $body['business_name'], $body['owner_name'],
        $body['email'], $body['mobile'], $body['gst_number'], $body['pan_number'],
        $body['address'], $body['city'], $body['state'], $body['pincode']
    );

    foreach (['aadhaar' => 'AADHAAR', 'pan' => 'PAN', 'gst' => 'GST_CERTIFICATE', 'logo' => 'BUSINESS_LOGO'] as $key => $type) {
        if (!empty($_FILES[$key]) && $_FILES[$key]['error'] === 0) {
            try {
                $url = FileUpload::upload($_FILES[$key], 'vendor-docs');
                $db->query("INSERT INTO vendor_documents (id, vendor_id, document_type, document_url) VALUES (?,?,?,?)",
                    'ssss', OtpHelper::uuid(), $vendorId, $type, $url);
            } catch (Exception $e) {}
        }
    }

    Response::success('Vendor application submitted. Pending admin approval.', [
        'application_ref' => 'VR-' . date('Y') . '-DA-' . strtoupper(substr($vendorId, 0, 8))
    ], 201);
}

// GET /vendors?action=dashboard
if ($method === 'GET' && $action === 'dashboard') {
    $auth   = vendorMiddleware();
    $vendor = $db->fetchOne("SELECT * FROM vendors WHERE user_id=? AND status='approved'", 's', $auth['user_id']);
    if (!$vendor) Response::error('Vendor not found or not approved', 403);

    $stats = [
        'total_revenue'  => $db->fetchOne("SELECT COALESCE(SUM(oi.total),0) AS val FROM order_items oi JOIN orders o ON oi.order_id=o.id JOIN products p ON oi.product_id=p.id WHERE p.vendor_id=? AND o.payment_status='PAID'", 's', $vendor['id'])['val'] ?? 0,
        'total_orders'   => $db->fetchOne("SELECT COUNT(DISTINCT oi.order_id) AS val FROM order_items oi JOIN products p ON oi.product_id=p.id WHERE p.vendor_id=?", 's', $vendor['id'])['val'],
        'total_products' => $db->fetchOne("SELECT COUNT(*) AS val FROM products WHERE vendor_id=? AND is_active=TRUE", 's', $vendor['id'])['val'],
        'avg_rating'     => $db->fetchOne("SELECT COALESCE(AVG(r.rating),0) AS val FROM reviews r JOIN products p ON r.product_id=p.id WHERE p.vendor_id=?", 's', $vendor['id'])['val'],
    ];

    $recentOrders = $db->fetchAll(
        "SELECT oi.*, o.order_number, o.created_at, o.payment_status
         FROM order_items oi JOIN orders o ON oi.order_id=o.id
         JOIN products p ON oi.product_id=p.id
         WHERE p.vendor_id=? ORDER BY o.created_at DESC LIMIT 10", 's', $vendor['id']
    );
    $topProducts = $db->fetchAll(
        "SELECT p.id, p.name, p.selling_price, p.stock_qty, p.sold_count,
                (SELECT image_url FROM product_images WHERE product_id=p.id AND is_primary=TRUE LIMIT 1) AS image
         FROM products p WHERE p.vendor_id=? AND p.is_active=TRUE ORDER BY p.sold_count DESC LIMIT 5", 's', $vendor['id']
    );
    $monthlyRevenue = $db->fetchAll(
        "SELECT TO_CHAR(o.created_at,'Mon') AS month, COALESCE(SUM(oi.total),0) AS revenue
         FROM order_items oi JOIN orders o ON oi.order_id=o.id JOIN products p ON oi.product_id=p.id
         WHERE p.vendor_id=? AND o.created_at >= NOW() - INTERVAL '6 months' AND o.payment_status='PAID'
         GROUP BY DATE_TRUNC('month', o.created_at), TO_CHAR(o.created_at,'Mon')
         ORDER BY DATE_TRUNC('month', o.created_at)", 's', $vendor['id']
    );

    Response::success('Dashboard data fetched', compact('stats', 'recentOrders', 'topProducts', 'monthlyRevenue'));
}

Response::error('Invalid request', 404);
