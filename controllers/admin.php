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
        'total_vendors'   => $db->fetchOne("SELECT COUNT(*) AS v FROM vendors WHERE status='APPROVED'")['v'],
        'pending_vendors' => $db->fetchOne("SELECT COUNT(*) AS v FROM vendors WHERE status='PENDING'")['v'],
        'total_products'  => $db->fetchOne("SELECT COUNT(*) AS v FROM products WHERE is_active=1")['v'],
        'total_orders'    => $db->fetchOne("SELECT COUNT(*) AS v FROM orders")['v'],
        'total_revenue'   => $db->fetchOne("SELECT COALESCE(SUM(total_amount),0) AS v FROM orders WHERE payment_status='PAID'")['v'],
    ];
    $recentOrders = $db->fetchAll(
        "SELECT o.*, c.full_name AS customer_name FROM orders o
         JOIN customers c ON o.customer_id=c.id ORDER BY o.placed_at DESC LIMIT 10"
    );
    $monthlyRevenue = $db->fetchAll(
        "SELECT DATE_FORMAT(placed_at,'%b') AS month, SUM(total_amount) AS revenue, COUNT(*) AS orders
         FROM orders WHERE payment_status='PAID' AND placed_at>=DATE_SUB(NOW(), INTERVAL 6 MONTH)
         GROUP BY YEAR(placed_at), MONTH(placed_at) ORDER BY placed_at"
    );
    Response::success('Dashboard fetched', compact('stats', 'recentOrders', 'monthlyRevenue'));
}

// GET vendors list
if ($method === 'GET' && $section === 'vendors') {
    $status = strtoupper($_GET['status'] ?? 'PENDING');
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * 20;
    $vendors = $db->fetchAll(
        "SELECT v.*, COUNT(vd.id) AS doc_count FROM vendors v
         LEFT JOIN vendor_documents vd ON v.id=vd.vendor_id
         WHERE v.status=? GROUP BY v.id ORDER BY v.created_at DESC LIMIT 20 OFFSET $offset",
        's', $status
    );
    foreach ($vendors as &$v) {
        $v['documents'] = $db->fetchAll("SELECT doc_type, file_url FROM vendor_documents WHERE vendor_id=?", 'i', $v['id']);
    }
    Response::success('Vendors fetched', $vendors);
}

// PUT approve vendor
if ($method === 'PUT' && $action === 'approve') {
    $vendorId = (int)($_GET['id'] ?? 0);
    if (!$vendorId) Response::error('Vendor ID required');
    $vendor = $db->fetchOne("SELECT * FROM vendors WHERE id=? AND status='PENDING'", 'i', $vendorId);
    if (!$vendor) Response::error('Vendor not found', 404);
    $vendorCode = 'DA-VND-' . str_pad((string)$vendorId, 5, '0', STR_PAD_LEFT);
    $tempPass   = 'DA@' . strtoupper(substr(md5(uniqid()), 0, 8));
    $db->query(
        "UPDATE vendors SET status='APPROVED', vendor_id_code=?, temp_password=?, approved_at=NOW(), approved_by=?, is_verified=1 WHERE id=?",
        'ssii', $vendorCode, $tempPass, $auth['user_id'], $vendorId
    );
    $db->query("UPDATE users SET is_active=1 WHERE id=?", 'i', $vendor['user_id']);
    $db->query("INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,'VENDOR')",
        'iss', $vendor['user_id'], 'Vendor Account Approved!',
        "Your vendor account is approved. Vendor ID: $vendorCode. Temp Password: $tempPass"
    );
    Response::success('Vendor approved', ['vendor_id_code' => $vendorCode, 'temp_password' => $tempPass]);
}

// PUT reject vendor
if ($method === 'PUT' && $action === 'reject') {
    $vendorId = (int)($_GET['id'] ?? 0);
    $reason   = trim($body['reason'] ?? '');
    if (!$vendorId || !$reason) Response::error('Vendor ID and reason required');
    $vendor = $db->fetchOne("SELECT * FROM vendors WHERE id=?", 'i', $vendorId);
    if (!$vendor) Response::error('Vendor not found', 404);
    $db->query("UPDATE vendors SET status='REJECTED', rejection_reason=? WHERE id=?", 'si', $reason, $vendorId);
    $db->query("INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,'VENDOR')",
        'iss', $vendor['user_id'], 'Application Rejected', "Reason: $reason"
    );
    Response::success('Vendor rejected');
}

// GET orders
if ($method === 'GET' && $section === 'orders') {
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * 20;
    $status = $_GET['status'] ?? '';
    $where  = $status ? "WHERE o.order_status='" . $db->escape(strtoupper($status)) . "'" : '';
    $orders = $db->fetchAll(
        "SELECT o.*, c.full_name AS customer_name FROM orders o
         JOIN customers c ON o.customer_id=c.id $where ORDER BY o.placed_at DESC LIMIT 20 OFFSET $offset"
    );
    Response::success('Orders fetched', $orders);
}

// PUT update order status
if ($method === 'PUT' && $section === 'orders') {
    $orderId = (int)($_GET['id'] ?? 0);
    $status  = strtoupper($body['status'] ?? '');
    $allowed = ['CONFIRMED', 'PACKED', 'SHIPPED', 'OUT_FOR_DELIVERY', 'DELIVERED', 'CANCELLED'];
    if (!in_array($status, $allowed)) Response::error('Invalid status');
    $db->query("UPDATE orders SET order_status=? WHERE id=?", 'si', $status, $orderId);
    if ($status === 'DELIVERED') {
        $db->query("UPDATE orders SET delivered_at=NOW(), payment_status='PAID' WHERE id=?", 'i', $orderId);
    }
    Response::success('Order updated');
}

// GET customers
if ($method === 'GET' && $section === 'customers') {
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * 20;
    $customers = $db->fetchAll(
        "SELECT c.*, u.is_active, COUNT(o.id) AS total_orders, COALESCE(SUM(o.total_amount),0) AS total_spent
         FROM customers c JOIN users u ON c.user_id=u.id
         LEFT JOIN orders o ON c.id=o.customer_id AND o.payment_status='PAID'
         GROUP BY c.id ORDER BY c.created_at DESC LIMIT 20 OFFSET $offset"
    );
    Response::success('Customers fetched', $customers);
}

Response::error('Invalid request', 404);
