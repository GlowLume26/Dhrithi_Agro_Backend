<?php
require_once __DIR__ . '/../helpers/helpers.php';
require_once __DIR__ . '/../middleware/auth.php';

$db     = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$auth   = adminMiddleware();

// Permission check
if ($auth['role'] !== 'owner') {
    $perm = $db->fetchOne("SELECT granted FROM user_permissions WHERE user_id=? AND module='manufacturer_orders'", $auth['user_id']);
    if (!$perm || !$perm['granted']) Response::error('Access denied', 403);
}

$section = $_GET['section'] ?? '';
$PAGE    = 20;

// GET manufacturers list
if ($method === 'GET' && $section === 'manufacturers') {
    $mfrs = $db->fetchAll("SELECT * FROM manufacturers WHERE is_active=TRUE ORDER BY name");
    Response::success('Manufacturers fetched', $mfrs);
}

// GET manufacturer orders
if ($method === 'GET' && ($section === '' || $section === 'orders')) {
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = max(1, min(100, (int)($_GET['limit'] ?? $PAGE)));
    $offset = ($page - 1) * $limit;
    $mfrId  = $_GET['manufacturer_id'] ?? '';
    $status = $_GET['status'] ?? '';
    $search = trim($_GET['search'] ?? '');
    $date   = $_GET['date'] ?? '';

    $where = []; $params = [];
    if ($mfrId)  { $where[] = 'mo.manufacturer_id=?'; $params[] = $mfrId; }
    if ($status) { $where[] = 'mo.status=?';           $params[] = $status; }
    if ($date)   { $where[] = 'DATE(mo.order_date)=?'; $params[] = $date; }
    if ($search) { $where[] = "(mo.order_number ILIKE ? OR m.name ILIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
    $w = $where ? 'WHERE '.implode(' AND ', $where) : '';

    $total = $db->fetchOne("SELECT COUNT(*) AS c FROM manufacturer_orders mo JOIN manufacturers m ON mo.manufacturer_id=m.id $w", ...$params)['c'];
    $orders = $db->fetchAll(
        "SELECT mo.*, m.name AS manufacturer_name, m.mobile AS manufacturer_mobile
         FROM manufacturer_orders mo
         JOIN manufacturers m ON mo.manufacturer_id=m.id
         $w ORDER BY mo.order_date DESC LIMIT $limit OFFSET $offset",
        ...$params
    );

    // Attach items
    if ($orders) {
        $ids = array_column($orders, 'id');
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $items = $db->fetchAll("SELECT * FROM manufacturer_order_items WHERE manufacturer_order_id IN ($ph)", ...$ids);
        $byOrder = [];
        foreach ($items as $it) $byOrder[$it['manufacturer_order_id']][] = $it;
        foreach ($orders as &$o) $o['items'] = $byOrder[$o['id']] ?? [];
    }

    Response::json(['success'=>true,'data'=>$orders,'meta'=>['total'=>(int)$total,'page'=>$page,'limit'=>$limit,'pages'=>(int)ceil($total/$limit)]]);
}

// POST create manufacturer order
if ($method === 'POST') {
    $err = Validator::required($body, ['manufacturer_id','items']);
    if ($err) Response::error($err);
    if (!is_array($body['items']) || empty($body['items'])) Response::error('Items required');

    $total = 0;
    foreach ($body['items'] as $it) $total += ($it['quantity'] ?? 1) * ($it['unit_price'] ?? 0);

    $id  = $db->fetchOne("SELECT gen_random_uuid() AS id")['id'];
    $num = 'MFR-'.strtoupper(substr($id, 0, 8));

    $db->begin();
    $db->query(
        "INSERT INTO manufacturer_orders (id, manufacturer_id, order_number, total_amount, status, invoice_number, notes)
         VALUES (?,?,?,?,?,?,?)",
        $id, $body['manufacturer_id'], $num, $total,
        $body['status'] ?? 'pending', $body['invoice_number'] ?? null, $body['notes'] ?? null
    );
    foreach ($body['items'] as $it) {
        $itTotal = ($it['quantity'] ?? 1) * ($it['unit_price'] ?? 0);
        $db->query(
            "INSERT INTO manufacturer_order_items (manufacturer_order_id, product_id, product_name, quantity, unit_price, tax_rate, discount, total)
             VALUES (?,?,?,?,?,?,?,?)",
            $id, $it['product_id'] ?? null, $it['product_name'] ?? '', $it['quantity'] ?? 1,
            $it['unit_price'] ?? 0, $it['tax_rate'] ?? 0, $it['discount'] ?? 0, $itTotal
        );
    }
    $db->commit();
    Response::success('Order created', ['id' => $id, 'order_number' => $num], 201);
}

// PUT update status
if ($method === 'PUT') {
    $id = $_GET['id'] ?? '';
    if (!$id) Response::error('Order ID required');
    $status = $body['status'] ?? '';
    $allowed = ['pending','confirmed','dispatched','delivered','cancelled'];
    if (!in_array($status, $allowed)) Response::error('Invalid status');
    $db->query("UPDATE manufacturer_orders SET status=?, updated_at=NOW() WHERE id=?", $status, $id);
    Response::success('Order updated');
}

// DELETE
if ($method === 'DELETE') {
    $id = $_GET['id'] ?? '';
    if (!$id) Response::error('Order ID required');
    $db->query("DELETE FROM manufacturer_orders WHERE id=?", $id);
    Response::success('Order deleted');
}

Response::error('Invalid request', 404);
