<?php
require_once __DIR__ . '/../helpers/helpers.php';
require_once __DIR__ . '/../middleware/auth.php';

$db     = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? '';

// GET /vendors
if ($method === 'GET' && !$action) {
    $vendors = $db->fetchAll(
        "SELECT id, vendor_code, business_name, owner_name, city, state, is_verified
         FROM vendors WHERE status='approved' ORDER BY created_at DESC LIMIT 20"
    );
    Response::success('Vendors fetched', $vendors);
}

// POST /vendors?action=register
if ($method === 'POST' && $action === 'register') {
    $err = Validator::required($body, ['business_name','owner_name','mobile','email','gst_number','pan_number','address','city','state','pincode']);
    if ($err) Response::error($err);
    if (!Validator::mobile($body['mobile'])) Response::error('Invalid mobile number');
    if (!Validator::email($body['email']))   Response::error('Invalid email address');
    if ($db->fetchOne("SELECT id FROM users WHERE email=?", $body['email'])) Response::error('Email already registered');
    if ($db->fetchOne("SELECT id FROM vendors WHERE gst_number=?", $body['gst_number'])) Response::error('GST number already registered');

    $db->begin();
    try {
        $userId   = $db->fetchOne("SELECT gen_random_uuid() AS id")['id'];
        $vendorId = $db->fetchOne("SELECT gen_random_uuid() AS id")['id'];
        $db->query(
            "INSERT INTO users (id,first_name,last_name,email,mobile,password_hash,role,is_active) VALUES (?,?,?,?,?,?,'vendor',FALSE)",
            $userId, $body['owner_name'], '', $body['email'], $body['mobile'], password_hash(uniqid('', true), PASSWORD_DEFAULT)
        );
        $db->query(
            "INSERT INTO vendors (id,user_id,business_name,owner_name,email,mobile,gst_number,pan_number,address,city,state,pincode,status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'pending')",
            $vendorId, $userId, $body['business_name'], $body['owner_name'],
            $body['email'], $body['mobile'], $body['gst_number'], $body['pan_number'],
            $body['address'], $body['city'], $body['state'], $body['pincode']
        );
        foreach (['aadhaar'=>'AADHAAR','pan'=>'PAN','gst'=>'GST_CERTIFICATE','logo'=>'BUSINESS_LOGO'] as $key=>$type) {
            if (!empty($_FILES[$key]) && $_FILES[$key]['error'] === 0) {
                try {
                    $url = FileUpload::upload($_FILES[$key], 'vendor-docs');
                    $db->query("INSERT INTO vendor_documents (id,vendor_id,document_type,document_url) VALUES (gen_random_uuid(),?,?,?)", $vendorId, $type, $url);
                } catch (Exception $e) {}
            }
        }
        $db->commit();
    } catch (Exception $e) {
        $db->rollback();
        Response::error('Registration failed. Please try again.', 500);
    }
    Response::success('Vendor application submitted. Pending admin approval.', [
        'application_ref' => 'VR-' . date('Y') . '-' . strtoupper(substr($vendorId, 0, 8))
    ], 201);
}

// GET /vendors?action=dashboard
if ($method === 'GET' && $action === 'dashboard') {
    $auth   = vendorMiddleware();
    $vendor = $db->fetchOne("SELECT * FROM vendors WHERE user_id=? AND status='approved'", $auth['user_id']);
    if (!$vendor) Response::error('Vendor not found or not approved', 403);

    $stats = $db->fetchOne(
        "SELECT
            COALESCE(SUM(CASE WHEN o.payment_status='paid' THEN oi.total ELSE 0 END), 0) AS total_revenue,
            COUNT(DISTINCT oi.order_id)                                                   AS total_orders,
            (SELECT COUNT(*) FROM products WHERE vendor_id=? AND is_active=TRUE)          AS total_products,
            COALESCE(AVG(r.rating), 0)                                                    AS avg_rating
         FROM order_items oi
         JOIN orders o ON oi.order_id=o.id
         JOIN products p ON oi.product_id=p.id
         LEFT JOIN reviews r ON r.product_id=p.id
         WHERE p.vendor_id=?",
        $vendor['id'], $vendor['id']
    );

    $recentOrders = $db->fetchAll(
        "SELECT oi.id, oi.quantity, oi.price, oi.total, p.name AS product_name,
                o.order_number, o.created_at, o.payment_status
         FROM order_items oi
         JOIN orders o ON oi.order_id=o.id
         JOIN products p ON oi.product_id=p.id
         WHERE p.vendor_id=? ORDER BY o.created_at DESC LIMIT 10", $vendor['id']
    );
    $topProducts = $db->fetchAll(
        "SELECT p.id, p.name, p.selling_price, p.stock_qty, p.sold_count, pi.image_url AS image
         FROM products p
         LEFT JOIN LATERAL (SELECT image_url FROM product_images WHERE product_id=p.id AND is_primary=TRUE LIMIT 1) pi ON TRUE
         WHERE p.vendor_id=? AND p.is_active=TRUE ORDER BY p.sold_count DESC LIMIT 5", $vendor['id']
    );
    $monthlyRevenue = $db->fetchAll(
        "SELECT TO_CHAR(o.created_at,'Mon YYYY') AS month, COALESCE(SUM(oi.total),0) AS revenue
         FROM order_items oi JOIN orders o ON oi.order_id=o.id JOIN products p ON oi.product_id=p.id
         WHERE p.vendor_id=? AND o.created_at >= NOW() - INTERVAL '6 months' AND o.payment_status='paid'
         GROUP BY DATE_TRUNC('month', o.created_at) ORDER BY DATE_TRUNC('month', o.created_at)", $vendor['id']
    );
    Response::success('Dashboard data fetched', compact('stats', 'recentOrders', 'topProducts', 'monthlyRevenue'));
}

Response::error('Invalid request', 404);
