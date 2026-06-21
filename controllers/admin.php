<?php
require_once __DIR__ . '/../helpers/helpers.php';
require_once __DIR__ . '/../middleware/auth.php';

$db      = Database::getInstance();
$method  = $_SERVER['REQUEST_METHOD'];
$body    = json_decode(file_get_contents('php://input'), true) ?? [];
$auth    = adminMiddleware();
$section = $_GET['section'] ?? '';
$action  = $_GET['action'] ?? '';

// GET dashboard stats
if ($method === 'GET' && $section === 'dashboard') {
    $stats = [
        'total_customers' => $db->fetchOne("SELECT COUNT(*) AS v FROM customers")['v'],
        'total_vendors'   => $db->fetchOne("SELECT COUNT(*) AS v FROM vendors WHERE status='approved'")['v'],
        'pending_vendors' => $db->fetchOne("SELECT COUNT(*) AS v FROM vendors WHERE status='pending'")['v'],
        'total_products'  => $db->fetchOne("SELECT COUNT(*) AS v FROM products WHERE is_active=TRUE")['v'],
        'total_orders'    => $db->fetchOne("SELECT COUNT(*) AS v FROM orders")['v'],
        'total_revenue'   => $db->fetchOne("SELECT COALESCE(SUM(final_amount),0) AS v FROM orders WHERE payment_status='PAID'")['v'],
    ];
    $recentOrders = $db->fetchAll(
        "SELECT o.*, u.first_name||' '||u.last_name AS customer_name
         FROM orders o JOIN customers c ON o.customer_id=c.id JOIN users u ON c.user_id=u.id
         ORDER BY o.created_at DESC LIMIT 10"
    );
    $monthlyRevenue = $db->fetchAll(
        "SELECT TO_CHAR(created_at,'Mon') AS month, SUM(final_amount) AS revenue, COUNT(*) AS orders
         FROM orders WHERE payment_status='PAID' AND created_at >= NOW() - INTERVAL '6 months'
         GROUP BY DATE_TRUNC('month', created_at) ORDER BY DATE_TRUNC('month', created_at)"
    );
    Response::success('Dashboard fetched', compact('stats', 'recentOrders', 'monthlyRevenue'));
}

// GET vendors list
if ($method === 'GET' && $section === 'vendors') {
    $status = strtolower($_GET['status'] ?? 'pending');
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * 20;
    $vendors = $db->fetchAll(
        "SELECT v.*, COUNT(vd.id) AS doc_count FROM vendors v
         LEFT JOIN vendor_documents vd ON v.id=vd.vendor_id
         WHERE v.status=? GROUP BY v.id ORDER BY v.created_at DESC LIMIT 20 OFFSET $offset",
        's', $status
    );
    foreach ($vendors as &$v) {
        $v['documents'] = $db->fetchAll("SELECT document_type, document_url FROM vendor_documents WHERE vendor_id=?", 's', $v['id']);
    }
    Response::success('Vendors fetched', $vendors);
}

// PUT approve vendor
if ($method === 'PUT' && $action === 'approve') {
    $vendorId = $_GET['id'] ?? '';
    if (!$vendorId) Response::error('Vendor ID required');
    $vendor = $db->fetchOne("SELECT * FROM vendors WHERE id=? AND status='pending'", 's', $vendorId);
    if (!$vendor) Response::error('Vendor not found', 404);
    $vendorCode = 'DA-VND-' . strtoupper(substr($vendorId, 0, 8));
    $db->query("UPDATE vendors SET status='approved', vendor_code=?, is_verified=TRUE WHERE id=?", 'ss', $vendorCode, $vendorId);
    $db->query("UPDATE users SET is_active=TRUE WHERE id=?", 's', $vendor['user_id']);
    $db->query("INSERT INTO notifications (id, user_id, title, message) VALUES (?,?,?,?)",
        'ssss', OtpHelper::uuid(), $vendor['user_id'], 'Vendor Account Approved!',
        "Your vendor account is approved. Vendor Code: $vendorCode");
    Response::success('Vendor approved', ['vendor_code' => $vendorCode]);
}

// PUT reject vendor
if ($method === 'PUT' && $action === 'reject') {
    $vendorId = $_GET['id'] ?? '';
    $reason   = trim($body['reason'] ?? '');
    if (!$vendorId || !$reason) Response::error('Vendor ID and reason required');
    $vendor = $db->fetchOne("SELECT * FROM vendors WHERE id=?", 's', $vendorId);
    if (!$vendor) Response::error('Vendor not found', 404);
    $db->query("UPDATE vendors SET status='rejected' WHERE id=?", 's', $vendorId);
    $db->query("INSERT INTO notifications (id, user_id, title, message) VALUES (?,?,?,?)",
        'ssss', OtpHelper::uuid(), $vendor['user_id'], 'Application Rejected', "Reason: $reason");
    Response::success('Vendor rejected');
}

// GET orders
if ($method === 'GET' && $section === 'orders') {
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * 20;
    $status = $_GET['status'] ?? '';
    $where  = $status ? "WHERE o.order_status=?" : '';
    $params = $status ? [$status] : [];
    $orders = $db->fetchAll(
        "SELECT o.*, u.first_name||' '||u.last_name AS customer_name
         FROM orders o JOIN customers c ON o.customer_id=c.id JOIN users u ON c.user_id=u.id
         $where ORDER BY o.created_at DESC LIMIT 20 OFFSET $offset",
        '', ...$params
    );
    Response::success('Orders fetched', $orders);
}

// PUT update order status
if ($method === 'PUT' && $section === 'orders') {
    $orderId = $_GET['id'] ?? '';
    $status  = strtoupper($body['status'] ?? '');
    $allowed = ['CONFIRMED', 'PACKED', 'SHIPPED', 'OUT_FOR_DELIVERY', 'DELIVERED', 'CANCELLED'];
    if (!in_array($status, $allowed)) Response::error('Invalid status');
    $db->query("UPDATE orders SET order_status=? WHERE id=?", 'ss', $status, $orderId);
    if ($status === 'DELIVERED') {
        $db->query("UPDATE orders SET payment_status='PAID' WHERE id=?", 's', $orderId);
    }
    Response::success('Order updated');
}

// GET customers
if ($method === 'GET' && $section === 'customers') {
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * 20;
    $customers = $db->fetchAll(
        "SELECT c.*, u.first_name, u.last_name, u.email, u.mobile, u.is_active
         FROM customers c JOIN users u ON c.user_id=u.id
         ORDER BY c.created_at DESC LIMIT 20 OFFSET $offset"
    );
    Response::success('Customers fetched', $customers);
}

Response::error('Invalid request', 404);
