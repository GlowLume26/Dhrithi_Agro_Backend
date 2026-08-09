<?php
require_once __DIR__ . '/../helpers/helpers.php';
require_once __DIR__ . '/../middleware/auth.php';

$db      = Database::getInstance();
$method  = $_SERVER['REQUEST_METHOD'];
$body    = json_decode(file_get_contents('php://input'), true) ?? [];
$auth    = adminMiddleware();
$section = $_GET['section'] ?? '';
$action  = $_GET['action'] ?? '';

$settingsRows = $db->fetchAll("SELECT key, value FROM app_settings WHERE key IN ('page_limit','inventory_limit')");
$cfg = [];
foreach ($settingsRows as $r) $cfg[$r['key']] = $r['value'];
$PAGE_LIMIT      = (int)($cfg['page_limit']      ?? 20);
$INVENTORY_LIMIT = (int)($cfg['inventory_limit'] ?? 100);

// GET dashboard
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
        "SELECT TO_CHAR(DATE_TRUNC('month', created_at),'Mon YYYY') AS month, SUM(final_amount) AS revenue, COUNT(*) AS orders
         FROM orders WHERE payment_status='paid' AND created_at >= NOW() - INTERVAL '6 months'
         GROUP BY DATE_TRUNC('month', created_at) ORDER BY DATE_TRUNC('month', created_at)"
    );
    Response::success('Dashboard fetched', compact('stats', 'recentOrders', 'monthlyRevenue'));
}

// GET vendors
if ($method === 'GET' && $section === 'vendors') {
    $status = strtolower($_GET['status'] ?? 'pending');
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $offset = ($page - 1) * $PAGE_LIMIT;
    $vendors = $db->fetchAll(
        "SELECT v.*, u.email AS user_email, COUNT(vd.id) AS doc_count
         FROM vendors v
         JOIN users u ON v.user_id=u.id
         LEFT JOIN vendor_documents vd ON v.id=vd.vendor_id
         WHERE v.status=? GROUP BY v.id, u.email ORDER BY v.created_at DESC LIMIT $PAGE_LIMIT OFFSET $offset",
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
    try {
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($page - 1) * $PAGE_LIMIT;
        $status         = strtolower($_GET['status'] ?? '');
        $category_id    = $_GET['category_id'] ?? '';
        $subcategory_id = $_GET['subcategory_id'] ?? '';
        $date           = $_GET['date'] ?? '';

        $where = []; $params = [];
        if ($status)         { $where[] = 'o.order_status=?';   $params[] = $status; }
        if ($category_id)    { $where[] = 'p.category_id=?';    $params[] = $category_id; }
        if ($subcategory_id) { $where[] = 'p.category_id=?';    $params[] = $subcategory_id; }
        if ($date)           { $where[] = 'DATE(o.created_at)=?'; $params[] = $date; }
        $whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        $total = ($db->fetchOne(
            "SELECT COUNT(DISTINCT o.id) AS total
             FROM orders o
             JOIN customers c ON o.customer_id=c.id
             JOIN users u ON c.user_id=u.id
             JOIN order_items oi ON oi.order_id=o.id
             JOIN products p ON oi.product_id=p.id
             $whereStr", ...$params
        ))['total'] ?? 0;

        $orders = $db->fetchAll(
            "SELECT DISTINCT o.id, o.order_number, o.final_amount, o.order_status, o.payment_status, o.created_at,
                    u.first_name||' '||u.last_name AS customer_name,
                    ca.address_line1, ca.address_line2, ca.city, ca.state, ca.pincode, ca.mobile,
                    p.category_id, cat.name AS category_name
             FROM orders o
             JOIN customers c ON o.customer_id=c.id
             JOIN users u ON c.user_id=u.id
             LEFT JOIN customer_addresses ca ON o.address_id=ca.id
             JOIN order_items oi ON oi.order_id=o.id
             JOIN products p ON oi.product_id=p.id
             LEFT JOIN categories cat ON p.category_id=cat.id
             $whereStr
             ORDER BY o.created_at DESC
             LIMIT $PAGE_LIMIT OFFSET $offset",
            ...$params
        );

        if (!empty($orders)) {
            $orderIds     = array_column($orders, 'id');
            $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
            $allItems     = $db->fetchAll(
                "SELECT oi.order_id, oi.product_id, oi.quantity, oi.price, oi.total, p.name AS product_name
                 FROM order_items oi
                 JOIN products p ON oi.product_id=p.id
                 WHERE oi.order_id IN ($placeholders)",
                ...$orderIds
            );
            $itemsByOrder = [];
            foreach ($allItems as $item) $itemsByOrder[$item['order_id']][] = $item;
            foreach ($orders as &$order) {
                $order['items']   = $itemsByOrder[$order['id']] ?? [];
                $order['address'] = [
                    'address_line1' => $order['address_line1'],
                    'address_line2' => $order['address_line2'],
                    'city'          => $order['city'],
                    'district'      => $order['city'],
                    'state'         => $order['state'],
                    'pincode'       => $order['pincode'],
                    'mobile'        => $order['mobile'],
                ];
            }
        }
        Response::json(['success' => true, 'data' => $orders, 'meta' => ['total' => (int)$total, 'page' => $page, 'limit' => $PAGE_LIMIT, 'pages' => (int)ceil($total / $PAGE_LIMIT)]]);
    } catch (Exception $e) {
        error_log('Orders API Error: ' . $e->getMessage());
        Response::error('Failed to fetch orders: ' . $e->getMessage(), 500);
    }
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
    $offset = ($page - 1) * $PAGE_LIMIT;
    $search = trim($_GET['search'] ?? '');
    $city   = trim($_GET['city'] ?? '');
    $where = []; $params = [];
    if ($search) {
        $where[]     = "(u.first_name ILIKE ? OR u.last_name ILIKE ? OR u.email ILIKE ? OR u.mobile ILIKE ? OR ca.city ILIKE ? OR ca.state ILIKE ? OR ca.district ILIKE ? OR c.customer_code ILIKE ?)";
        $s           = "%$search%";
        $params      = array_merge($params, [$s,$s,$s,$s,$s,$s,$s,$s]);
    }
    if ($city) {
        $where[] = 'ca.city ILIKE ?';
        $params[] = "%$city%";
    }
    $whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $total = ($db->fetchOne(
        "SELECT COUNT(DISTINCT c.id) AS total
         FROM customers c
         JOIN users u ON c.user_id=u.id
         LEFT JOIN customer_addresses ca ON ca.customer_id=c.id AND ca.is_default=TRUE
         $whereStr", ...$params
    ))['total'] ?? 0;
    $customers = $db->fetchAll(
        "SELECT c.id, c.customer_code, c.total_orders, c.total_spent, c.loyalty_points, c.created_at,
                u.first_name, u.last_name, u.email, u.mobile, u.is_active,
                ca.city, ca.state, ca.district
         FROM customers c
         JOIN users u ON c.user_id=u.id
         LEFT JOIN customer_addresses ca ON ca.customer_id=c.id AND ca.is_default=TRUE
         $whereStr
         ORDER BY c.created_at DESC LIMIT $PAGE_LIMIT OFFSET $offset",
        ...$params
    );
    Response::json(['success' => true, 'data' => $customers, 'meta' => ['total' => (int)$total, 'page' => $page, 'limit' => $PAGE_LIMIT, 'pages' => (int)ceil($total / $PAGE_LIMIT)]]);
}

// GET inventory
if ($method === 'GET' && $section === 'inventory') {
    $search = trim($_GET['search'] ?? '');
    $params = [];
    $extraWhere = '';
    if ($search) { $extraWhere = 'AND (p.name ILIKE ? OR p.sku ILIKE ?)'; $params = ["%$search%", "%$search%"]; }
    $items = $db->fetchAll(
        "SELECT p.id, p.name, p.sku, p.stock_qty AS available, p.sold_count AS sold,
                COALESCE(i.low_stock_threshold, 10) AS threshold,
                COALESCE(i.reserved_stock, 0) AS reserved
         FROM products p LEFT JOIN inventory i ON i.product_id=p.id
         WHERE p.is_active=TRUE $extraWhere
         ORDER BY p.stock_qty ASC LIMIT $INVENTORY_LIMIT",
        ...$params
    );
    Response::success('Inventory fetched', $items);
}

// PUT restock
if ($method === 'PUT' && $section === 'inventory') {
    $productId = $_GET['id'] ?? '';
    if (!$productId || !Validator::uuid($productId)) Response::error('Valid Product ID required');
    $qty = (int)($body['qty'] ?? 0);
    if ($qty <= 0) Response::error('Quantity must be positive');
    $db->query("UPDATE products SET stock_qty=stock_qty+?, updated_at=NOW() WHERE id=?", $qty, $productId);
    $db->query("UPDATE inventory SET current_stock=current_stock+? WHERE product_id=?", $qty, $productId);
    Response::success('Stock updated');
}

// GET offers
if ($method === 'GET' && $section === 'offers') {
    $offers = $db->fetchAll("SELECT * FROM banners ORDER BY created_at DESC");
    Response::success('Offers fetched', $offers);
}

// POST create offer
if ($method === 'POST' && $section === 'offers') {
    $err = Validator::required($body, ['name']);
    if ($err) Response::error($err);
    $id = $db->fetchOne("SELECT gen_random_uuid() AS id")['id'];
    $db->query(
        "INSERT INTO banners (id, title, image_url, link_url, start_date, end_date, is_active)
         VALUES (?,?,?,?,?,?,TRUE)",
        $id, $body['name'], $body['image'] ?? '',
        $body['redirect_product'] ?? $body['redirect_category'] ?? '',
        $body['start'] ?? null, $body['end'] ?? null
    );
    Response::success('Offer created', ['id' => $id], 201);
}

// PUT update offer
if ($method === 'PUT' && $section === 'offers') {
    $offerId = $_GET['id'] ?? '';
    if (!$offerId || !Validator::uuid($offerId)) Response::error('Valid Offer ID required');
    $map = ['name'=>'title','image'=>'image_url','start'=>'start_date','end'=>'end_date','is_active'=>'is_active'];
    $sets = []; $params = [];
    foreach ($map as $from => $to) {
        if (array_key_exists($from, $body)) { $sets[] = "$to=?"; $params[] = $body[$from]; }
    }
    if (!$sets) Response::error('Nothing to update');
    $params[] = $offerId;
    $db->query("UPDATE banners SET " . implode(',', $sets) . " WHERE id=?", ...$params);
    Response::success('Offer updated');
}

// DELETE offer
if ($method === 'DELETE' && $section === 'offers') {
    $offerId = $_GET['id'] ?? '';
    if (!$offerId || !Validator::uuid($offerId)) Response::error('Valid Offer ID required');
    $db->query("DELETE FROM banners WHERE id=?", $offerId);
    Response::success('Offer deleted');
}

// GET admin users
if ($method === 'GET' && $section === 'admins') {
    $admins = $db->fetchAll(
        "SELECT id, first_name||' '||last_name AS name, email, role, is_active, permissions, created_at
         FROM users WHERE role IN ('admin','owner','superadmin') ORDER BY created_at DESC"
    );
    foreach ($admins as &$a) {
        $a['permissions'] = !empty($a['permissions']) ? json_decode($a['permissions'], true) : null;
    }
    Response::success('Admins fetched', $admins);
}

// POST create admin
if ($method === 'POST' && $section === 'admins') {
    $err = Validator::required($body, ['name','email','password','role']);
    if ($err) Response::error($err);
    if (!in_array($body['role'], ['admin','owner'])) Response::error('Invalid role');
    if ($db->fetchOne("SELECT id FROM users WHERE email=?", $body['email'])) Response::error('Email already exists');
    $parts = explode(' ', trim($body['name']), 2);
    $id    = $db->fetchOne("SELECT gen_random_uuid() AS id")['id'];
    $perms = isset($body['permissions']) ? json_encode($body['permissions']) : null;
    $db->query(
        "INSERT INTO users (id, first_name, last_name, email, password_hash, role, is_active, permissions)
         VALUES (?,?,?,?,?,?,TRUE,?)",
        $id, $parts[0], $parts[1] ?? '', $body['email'],
        password_hash($body['password'], PASSWORD_DEFAULT), $body['role'], $perms
    );
    Response::success('Admin created', ['id' => $id], 201);
}

// PUT update admin
if ($method === 'PUT' && $section === 'admins') {
    $adminId = $_GET['id'] ?? '';
    if (!$adminId || !Validator::uuid($adminId)) Response::error('Valid Admin ID required');
    $sets = []; $params = [];
    if (!empty($body['name'])) {
        $parts = explode(' ', trim($body['name']), 2);
        $sets[] = 'first_name=?'; $params[] = $parts[0];
        $sets[] = 'last_name=?';  $params[] = $parts[1] ?? '';
    }
    if (!empty($body['email']))       { $sets[] = 'email=?';         $params[] = $body['email']; }
    if (!empty($body['password']))    { $sets[] = 'password_hash=?'; $params[] = password_hash($body['password'], PASSWORD_DEFAULT); }
    if (!empty($body['role']))        { $sets[] = 'role=?';          $params[] = $body['role']; }
    if (isset($body['is_active']))    { $sets[] = 'is_active=?';     $params[] = $body['is_active'] ? 'TRUE' : 'FALSE'; }
    if (isset($body['permissions']))  { $sets[] = 'permissions=?';   $params[] = json_encode($body['permissions']); }
    if (!$sets) Response::error('Nothing to update');
    $params[] = $adminId;
    $db->query("UPDATE users SET " . implode(',', $sets) . " WHERE id=?", ...$params);
    Response::success('Admin updated');
}

// DELETE admin
if ($method === 'DELETE' && $section === 'admins') {
    $adminId = $_GET['id'] ?? '';
    if (!$adminId || !Validator::uuid($adminId)) Response::error('Valid Admin ID required');
    $db->query("DELETE FROM users WHERE id=? AND role IN ('admin','owner')", $adminId);
    Response::success('Admin deleted');
}

// GET reports
if ($method === 'GET' && $section === 'reports') {
    $monthly = $db->fetchAll(
        "SELECT TO_CHAR(created_at,'Mon') AS m, TO_CHAR(created_at,'YYYY-MM') AS ym,
                SUM(final_amount) AS rev, COUNT(*) AS orders
         FROM orders WHERE payment_status='paid' AND created_at >= NOW() - INTERVAL '6 months'
         GROUP BY DATE_TRUNC('month',created_at), TO_CHAR(created_at,'Mon'), TO_CHAR(created_at,'YYYY-MM')
         ORDER BY ym"
    );
    $daily = $db->fetchAll(
        "SELECT TO_CHAR(created_at,'Dy') AS d, SUM(final_amount) AS rev, COUNT(*) AS orders
         FROM orders WHERE payment_status='paid' AND created_at >= NOW() - INTERVAL '7 days'
         GROUP BY DATE_TRUNC('day',created_at), TO_CHAR(created_at,'Dy')
         ORDER BY DATE_TRUNC('day',created_at)"
    );
    $topProducts = $db->fetchAll(
        "SELECT p.name, SUM(oi.quantity) AS units, SUM(oi.total) AS revenue
         FROM order_items oi JOIN products p ON oi.product_id=p.id
         JOIN orders o ON oi.order_id=o.id WHERE o.payment_status='paid'
         GROUP BY p.id, p.name ORDER BY revenue DESC LIMIT 5"
    );
    $summary = $db->fetchOne(
        "SELECT COALESCE(SUM(final_amount),0) AS total_revenue, COUNT(*) AS total_orders
         FROM orders WHERE payment_status='paid'"
    );
    Response::success('Reports fetched', compact('monthly','daily','topProducts','summary'));
}

// PUT toggle customer active status
if ($method === 'PUT' && $section === 'customers') {
    $customerId = $_GET['id'] ?? '';
    if (!$customerId || !Validator::uuid($customerId)) Response::error('Valid Customer ID required');
    $is_active = !empty($body['is_active']) ? 'TRUE' : 'FALSE';
    $customer  = $db->fetchOne("SELECT user_id FROM customers WHERE id=?", $customerId);
    if (!$customer) Response::error('Customer not found', 404);
    $db->query("UPDATE users SET is_active=? WHERE id=?", $is_active, $customer['user_id']);
    Response::success($body['is_active'] ? 'Customer activated' : 'Customer blocked');
}

// DELETE customer
if ($method === 'DELETE' && $section === 'customers') {
    $customerId = $_GET['id'] ?? '';
    if (!$customerId || !Validator::uuid($customerId)) Response::error('Valid Customer ID required');
    $customer = $db->fetchOne("SELECT user_id FROM customers WHERE id=?", $customerId);
    if (!$customer) Response::error('Customer not found', 404);
    $db->begin();
    $db->query("DELETE FROM cart WHERE customer_id=?", $customerId);
    $db->query("DELETE FROM wishlist WHERE customer_id=?", $customerId);
    $db->query("DELETE FROM customer_addresses WHERE customer_id=?", $customerId);
    $db->query("DELETE FROM customers WHERE id=?", $customerId);
    $db->query("DELETE FROM users WHERE id=?", $customer['user_id']);
    $db->commit();
    Response::success('Customer deleted');
}

Response::error('Invalid request', 404);
