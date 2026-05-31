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
        "SELECT v.id, v.vendor_id_code, v.store_name, v.business_name, v.store_banner_url,
                v.business_logo_url, v.rating, v.total_products, v.total_orders, v.city, v.state, v.is_verified
         FROM vendors v WHERE v.status='APPROVED' ORDER BY v.rating DESC LIMIT 20"
    );
    Response::success('Vendors fetched', $vendors);
}

// POST /vendors?action=register — vendor registration
if ($method === 'POST' && $action === 'register') {
    $err = Validator::required($body, ['business_name', 'owner_name', 'mobile', 'email', 'gst_number', 'pan_number', 'address', 'city', 'state', 'pincode']);
    if ($err) Response::error($err);
    if (!Validator::mobile($body['mobile'])) Response::error('Invalid mobile number');
    if (!Validator::email($body['email']))   Response::error('Invalid email address');
    if ($db->fetchOne("SELECT id FROM vendors WHERE gst_number=?", 's', $body['gst_number'])) {
        Response::error('GST number already registered');
    }

    $db->query("INSERT INTO users (mobile, email, role, is_active) VALUES (?,?,'VENDOR',0)", 'ss', $body['mobile'], $body['email']);
    $userId = $db->lastInsertId();

    $db->query(
        "INSERT INTO vendors (user_id, business_name, owner_name, mobile, email, gst_number, pan_number,
         business_type, address, city, state, pincode, store_name, store_description)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
        'isssssssssssss',
        $userId, $body['business_name'], $body['owner_name'], $body['mobile'], $body['email'],
        $body['gst_number'], $body['pan_number'], $body['business_type'] ?? 'SOLE_PROPRIETORSHIP',
        $body['address'], $body['city'], $body['state'], $body['pincode'],
        $body['store_name'] ?? $body['business_name'], $body['store_description'] ?? ''
    );
    $vendorId = $db->lastInsertId();

    $docTypes = ['aadhaar' => 'AADHAAR', 'pan' => 'PAN', 'gst' => 'GST_CERTIFICATE',
                 'business_reg' => 'BUSINESS_REGISTRATION', 'bank' => 'BANK_PASSBOOK', 'logo' => 'BUSINESS_LOGO'];
    foreach ($docTypes as $key => $type) {
        if (!empty($_FILES[$key]) && $_FILES[$key]['error'] === 0) {
            try {
                $url = FileUpload::upload($_FILES[$key], 'vendor-docs');
                $db->query("INSERT INTO vendor_documents (vendor_id, doc_type, file_url, file_name, file_size) VALUES (?,?,?,?,?)",
                    'isssi', $vendorId, $type, $url, $_FILES[$key]['name'], $_FILES[$key]['size']);
                if ($type === 'BUSINESS_LOGO') {
                    $db->query("UPDATE vendors SET business_logo_url=? WHERE id=?", 'si', $url, $vendorId);
                }
            } catch (Exception $e) { /* skip */ }
        }
    }

    Response::success('Vendor application submitted. Pending admin approval.', [
        'application_ref' => 'VR-' . date('Y') . '-DA-' . str_pad((string)$vendorId, 4, '0', STR_PAD_LEFT)
    ], 201);
}

// GET /vendors?action=dashboard — vendor dashboard
if ($method === 'GET' && $action === 'dashboard') {
    $auth   = vendorMiddleware();
    $vendor = $db->fetchOne("SELECT * FROM vendors WHERE user_id=? AND status='APPROVED'", 'i', $auth['user_id']);
    if (!$vendor) Response::error('Vendor not found or not approved', 403);

    $stats = [
        'total_revenue'  => $db->fetchOne("SELECT COALESCE(SUM(oi.total_price),0) AS val FROM order_items oi JOIN orders o ON oi.order_id=o.id WHERE oi.vendor_id=? AND o.payment_status='PAID'", 'i', $vendor['id'])['val'],
        'total_orders'   => $db->fetchOne("SELECT COUNT(DISTINCT order_id) AS val FROM order_items WHERE vendor_id=?", 'i', $vendor['id'])['val'],
        'total_products' => $db->fetchOne("SELECT COUNT(*) AS val FROM products WHERE vendor_id=? AND is_active=1", 'i', $vendor['id'])['val'],
        'avg_rating'     => $db->fetchOne("SELECT COALESCE(AVG(r.rating),0) AS val FROM reviews r JOIN products p ON r.product_id=p.id WHERE p.vendor_id=?", 'i', $vendor['id'])['val'],
        'pending_orders' => $db->fetchOne("SELECT COUNT(*) AS val FROM order_items WHERE vendor_id=? AND item_status='PROCESSING'", 'i', $vendor['id'])['val'],
        'low_stock'      => $db->fetchOne("SELECT COUNT(*) AS val FROM products WHERE vendor_id=? AND stock_qty<10 AND is_active=1", 'i', $vendor['id'])['val'],
    ];

    $recentOrders = $db->fetchAll(
        "SELECT oi.*, o.order_number, o.placed_at, o.payment_status FROM order_items oi
         JOIN orders o ON oi.order_id=o.id WHERE oi.vendor_id=? ORDER BY o.placed_at DESC LIMIT 10", 'i', $vendor['id']
    );
    $topProducts = $db->fetchAll(
        "SELECT p.id, p.name, p.selling_price, p.stock_qty, p.sold_count,
                (SELECT image_url FROM product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) AS image
         FROM products p WHERE p.vendor_id=? AND p.is_active=1 ORDER BY p.sold_count DESC LIMIT 5", 'i', $vendor['id']
    );
    $monthlyRevenue = $db->fetchAll(
        "SELECT DATE_FORMAT(o.placed_at,'%b') AS month, COALESCE(SUM(oi.total_price),0) AS revenue
         FROM order_items oi JOIN orders o ON oi.order_id=o.id
         WHERE oi.vendor_id=? AND o.placed_at>=DATE_SUB(NOW(), INTERVAL 6 MONTH) AND o.payment_status='PAID'
         GROUP BY MONTH(o.placed_at), DATE_FORMAT(o.placed_at,'%b') ORDER BY MONTH(o.placed_at)", 'i', $vendor['id']
    );

    Response::success('Dashboard data fetched', compact('stats', 'recentOrders', 'topProducts', 'monthlyRevenue'));
}

Response::error('Invalid request', 404);
