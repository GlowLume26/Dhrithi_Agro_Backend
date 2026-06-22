<?php
require_once __DIR__ . '/../helpers/helpers.php';
require_once __DIR__ . '/../middleware/auth.php';

$db      = Database::getInstance();
$method  = $_SERVER['REQUEST_METHOD'];
$body    = json_decode(file_get_contents('php://input'), true) ?? [];
$auth    = adminMiddleware();
$section = $_GET['section'] ?? '';
$action  = $_GET['action'] ?? '';

// GET dashboard — all stats in minimal queries
if ($method === 'GET' && $section === 'dashboard') {
    $stats = $db->fetchOne(
        "SELECT
            (SELECT COUNT(*) FROM customers)                                   AS total_customers,
            (SELECT COUNT(*) FROM vendors WHERE status='approved')             AS total_vendors,
            (SELECT COUNT(*) FROM vendors WHERE status='pending')              AS pending_vendors,
            (SELECT COUNT(*) FROM products WHERE is_active=TRUE)               AS total_products,
            (SELECT COUNT(*) FROM orders)                                       AS total_orders,
            (SELECT COALESCE(SUM(final_amount),0) FROM orders WHERE payment_status='paid') AS total_revenue"
    );
    $recentOrders = $db->fetchAll(
        "SELECT o.id, o.order_number, o.final_amount, o.order_status, o.payment_status, o.created_at,
                u.first_name||' '||u.last_name AS customer_name
         FROM orders o JOIN customers c ON o.customer_id=c.id JOIN users u ON c.user_id=u.id
         ORDER BY o.created_at DESC LIMIT 10"
    );
    $monthlyRevenue = $db->fetchAll(
        "SELECT TO_CHAR(created_at,'Mon YYYY') AS month, SUM(final_amount) AS revenue, COUNT(*) AS orders
         FROM orders WHERE payment_status='paid' AND created_at >= NOW() - INTERVAL '6 months'
         GROUP BY DATE_TRUNC('month', created_at) ORDER BY DATE_TRUNC('month', created_at)"
    );
    Response::success('Dashboard fetched', compact('stats', 'recentOrders', 'monthlyRevenue'));
}

// GET vendors — fix N+1: fetch all docs in one query
if ($method === 'GET' && $section === 'vendors') {
    $status = strtolower($_GET['status'] ?? 'pending');
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * 20;
    $vendors = $db->fetchAll(
        "SELECT v.*, u.email AS user_email, COUNT(vd.id) AS doc_count
         FROM vendors v
         JOIN users u ON v.user_id=u.id
         LEFT JOIN vendor_documents vd ON v.id=vd.vendor_id
         WHERE v.status=? GROUP BY v.id, u.email ORDER BY v.created_at DESC LIMIT 20 OFFSET $offset",
        $status
    );
    if (!empty($vendors)) {
        $vids = array_column($vendors, 'id');
        $placeholders = implode(',', array_fill(0, count($vids), '?'));
        $allDocs = $db->fetchAll("SELECT vendor_id, document_type, document_url FROM vendor_documents WHERE vendor_id IN ($placeholders)", ...$vids);
        $docsByVendor = [];
        foreach ($allDocs as $d) $docsByVendor[$d['vendor_id']][] = $d;
        foreach ($vendors as &$v) $v['documents'] = $docsByVendor[$v['id']] ?? [];
    }
    Response::success('Vendors fetched', $vendors);
}

// PUT approve vendor
if ($method === 'PUT' && $action === 'approve') {
    $vendorId = $_GET['id'] ?? '';
    if (!$vendorId || !Validator::uuid($vendorId)) Response::error('Valid Vendor ID required');
    $vendor = $db->fetchOne("SELECT * FROM vendors WHERE id=? AND status='pending'", $vendorId);
    if (!$vendor) Response::error('Vendor not found', 404);
    $vendorCode = 'DA-VND-' . strtoupper(substr($vendorId, 0, 8));
    $db->begin();
    $db->query("UPDATE vendors SET status='approved', vendor_code=?, is_verified=TRUE, updated_at=NOW() WHERE id=?", $vendorCode, $vendorId);
    $db->query("UPDATE users SET is_active=TRUE WHERE id=?", $vendor['user_id']);
    $db->query("INSERT INTO notifications (id,user_id,title,message) VALUES (gen_random_uuid(),?,?,?)",
        $vendor['user_id'], 'Vendor Account Approved!', "Your vendor account is approved. Vendor Code: $vendorCode");
    $db->commit();
    Response::success('Vendor approved', ['vendor_code' => $vendorCode]);
}

// PUT reject vendor
if ($method === 'PUT' && $action === 'reject') {
    $vendorId = $_GET['id'] ?? '';
    $reason   = trim($body['reason'] ?? '');
    if (!$vendorId || !$reason) Response::error('Vendor ID and reason required');
    $vendor = $db->fetchOne("SELECT user_id FROM vendors WHERE id=?", $vendorId);
    if (!$vendor) Response::error('Vendor not found', 404);
    $db->begin();
    $db->query("UPDATE vendors SET status='rejected', updated_at=NOW() WHERE id=?", $vendorId);
    $db->query("INSERT INTO notifications (id,user_id,title,message) VALUES (gen_random_uuid(),?,?,?)",
        $vendor['user_id'], 'Application Rejected', "Reason: $reason");
    $db->commit();
    Response::success('Vendor rejected');
}

// GET orders
if ($method === 'GET' && $section === 'orders') {
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * 20;
    $status = strtolower($_GET['status'] ?? '');
    $where  = $status ? 'WHERE o.order_status=?' : '';
    $params = $status ? [$status] : [];
    $orders = $db->fetchAll(
        "SELECT o.id, o.order_number, o.final_amount, o.order_status, o.payment_status, o.created_at,
                u.first_name||' '||u.last_name AS customer_name
         FROM orders o JOIN customers c ON o.customer_id=c.id JOIN users u ON c.user_id=u.id
         $where ORDER BY o.created_at DESC LIMIT 20 OFFSET $offset",
        ...$params
    );
    Response::success('Orders fetched', $orders);
}

// PUT update order status
if ($method === 'PUT' && $section === 'orders') {
    $orderId = $_GET['id'] ?? '';
    if (!$orderId || !Validator::uuid($orderId)) Response::error('Valid Order ID required');
    $status  = strtolower($body['status'] ?? '');
    $allowed = ['confirmed','packed','shipped','out_for_delivery','delivered','cancelled'];
    if (!in_array($status, $allowed)) Response::error('Invalid status');
    if (!$db->fetchOne("SELECT id FROM orders WHERE id=?", $orderId)) Response::error('Order not found', 404);
    $db->begin();
    $db->query("UPDATE orders SET order_status=?, updated_at=NOW() WHERE id=?", $status, $orderId);
    if ($status === 'delivered') $db->query("UPDATE orders SET payment_status='paid' WHERE id=?", $orderId);
    $db->query("INSERT INTO order_status_history (id,order_id,status,remarks) VALUES (gen_random_uuid(),?,?,?)",
        $orderId, $status, 'Updated by admin');
    $db->commit();
    Response::success('Order updated');
}

// GET customers
if ($method === 'GET' && $section === 'customers') {
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * 20;
    $customers = $db->fetchAll(
        "SELECT c.id, c.customer_code, c.total_orders, c.total_spent, c.loyalty_points, c.created_at,
                u.first_name, u.last_name, u.email, u.mobile, u.is_active
         FROM customers c JOIN users u ON c.user_id=u.id
         ORDER BY c.created_at DESC LIMIT 20 OFFSET $offset"
    );
    Response::success('Customers fetched', $customers);
}

Response::error('Invalid request', 404);
