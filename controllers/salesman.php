<?php
require_once __DIR__ . '/../helpers/helpers.php';
require_once __DIR__ . '/../middleware/auth.php';

$db     = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$auth   = adminMiddleware();

$section = $_GET['section'] ?? '';
$PAGE    = 20;

function checkSalesmanPerm($auth, $db, $module) {
    if ($auth['role'] === 'owner') return;
    $perm = $db->fetchOne("SELECT granted FROM user_permissions WHERE user_id=? AND module=?", $auth['user_id'], $module);
    if (!$perm || !$perm['granted']) Response::error('Access denied', 403);
}

// ── SALESMEN LIST ──────────────────────────────────────────
if ($method === 'GET' && $section === 'salesmen') {
    checkSalesmanPerm($auth, $db, 'salesman_reports');
    $distId = $_GET['distributor_id'] ?? '';
    $where=[]; $params=[];
    if ($distId) { $where[]='s.distributor_id=?'; $params[]=$distId; }
    $w = $where ? 'WHERE '.implode(' AND ',$where) : '';
    $salesmen = $db->fetchAll(
        "SELECT s.*, v.business_name AS distributor_name
         FROM salesmen s LEFT JOIN vendors v ON s.distributor_id=v.id
         $w ORDER BY s.name",
        ...$params
    );
    Response::success('Salesmen fetched', $salesmen);
}

// ── SALESMAN CRUD ──────────────────────────────────────────
if ($method === 'POST' && $section === 'salesmen') {
    if ($auth['role'] !== 'owner') Response::error('Access denied', 403);
    $err = Validator::required($body, ['name']);
    if ($err) Response::error($err);
    $id = $db->fetchOne("SELECT gen_random_uuid() AS id")['id'];
    $db->query(
        "INSERT INTO salesmen (id,name,mobile,email,distributor_id) VALUES (?,?,?,?,?)",
        $id, $body['name'], $body['mobile']??null, $body['email']??null, $body['distributor_id']??null
    );
    Response::success('Salesman created', ['id'=>$id], 201);
}

if ($method === 'PUT' && $section === 'salesmen') {
    if ($auth['role'] !== 'owner') Response::error('Access denied', 403);
    $id = $_GET['id'] ?? '';
    $sets=[]; $params=[];
    foreach (['name','mobile','email','distributor_id','is_active'] as $f) {
        if (isset($body[$f])) { $sets[]="$f=?"; $params[]=$body[$f]; }
    }
    if (!$sets) Response::error('Nothing to update');
    $params[]=$id;
    $db->query("UPDATE salesmen SET ".implode(',',$sets)." WHERE id=?", ...$params);
    Response::success('Salesman updated');
}

if ($method === 'DELETE' && $section === 'salesmen') {
    if ($auth['role'] !== 'owner') Response::error('Access denied', 403);
    $id = $_GET['id'] ?? '';
    $db->query("DELETE FROM salesmen WHERE id=?", $id);
    Response::success('Salesman deleted');
}

// ── SALESMAN REPORT ────────────────────────────────────────
if ($method === 'GET' && $section === 'report') {
    checkSalesmanPerm($auth, $db, 'salesman_reports');

    $salesmanId = $_GET['salesman_id'] ?? '';
    $distId     = $_GET['distributor_id'] ?? '';
    $from       = $_GET['from'] ?? date('Y-m-01');
    $to         = $_GET['to']   ?? date('Y-m-d');
    $page       = max(1,(int)($_GET['page']??1));
    $limit      = max(1,min(100,(int)($_GET['limit']??$PAGE)));
    $offset     = ($page-1)*$limit;

    $where = ["o.created_at BETWEEN ? AND ?::date+1"]; $params = [$from, $to];
    if ($salesmanId) { $where[]='so.salesman_id=?'; $params[]=$salesmanId; }
    if ($distId)     { $where[]='s.distributor_id=?'; $params[]=$distId; }
    $w = 'WHERE '.implode(' AND ',$where);

    // Summary
    $summary = $db->fetchOne(
        "SELECT COUNT(DISTINCT o.id) AS total_orders,
                COALESCE(SUM(o.final_amount),0) AS total_amount,
                COUNT(DISTINCT o.customer_id) AS total_customers
         FROM salesman_orders so
         JOIN salesmen s ON so.salesman_id=s.id
         JOIN orders o ON so.order_id=o.id
         $w", ...$params
    );

    $total = $db->fetchOne(
        "SELECT COUNT(DISTINCT o.id) AS c FROM salesman_orders so
         JOIN salesmen s ON so.salesman_id=s.id
         JOIN orders o ON so.order_id=o.id $w", ...$params
    )['c'];

    $orders = $db->fetchAll(
        "SELECT DISTINCT o.id, o.order_number, o.final_amount, o.order_status, o.payment_status, o.created_at,
                u.first_name||' '||u.last_name AS customer_name,
                s.name AS salesman_name, v.business_name AS distributor_name
         FROM salesman_orders so
         JOIN salesmen s ON so.salesman_id=s.id
         JOIN orders o ON so.order_id=o.id
         JOIN customers c ON o.customer_id=c.id
         JOIN users u ON c.user_id=u.id
         LEFT JOIN vendors v ON s.distributor_id=v.id
         $w ORDER BY o.created_at DESC LIMIT $limit OFFSET $offset",
        ...$params
    );

    Response::json(['success'=>true,'data'=>$orders,'summary'=>$summary,'meta'=>['total'=>(int)$total,'page'=>$page,'limit'=>$limit,'pages'=>(int)ceil($total/$limit)]]);
}

// ── SALESMAN ORDERS (simple list) ─────────────────────────
if ($method === 'GET' && $section === 'orders') {
    checkSalesmanPerm($auth, $db, 'salesman_orders');

    $salesmanId = $_GET['salesman_id'] ?? '';
    $distId     = $_GET['distributor_id'] ?? '';
    $from       = $_GET['from'] ?? '';
    $to         = $_GET['to']   ?? '';
    $search     = trim($_GET['search'] ?? '');
    $page       = max(1,(int)($_GET['page']??1));
    $limit      = max(1,min(100,(int)($_GET['limit']??$PAGE)));
    $offset     = ($page-1)*$limit;

    $where=[]; $params=[];
    if ($salesmanId) { $where[]='so.salesman_id=?'; $params[]=$salesmanId; }
    if ($distId)     { $where[]='s.distributor_id=?'; $params[]=$distId; }
    if ($from && $to){ $where[]='o.created_at BETWEEN ? AND ?::date+1'; $params[]=$from; $params[]=$to; }
    if ($search)     { $where[]="(o.order_number ILIKE ? OR u.first_name ILIKE ?)"; $params[]="%$search%"; $params[]="%$search%"; }
    $w = $where ? 'WHERE '.implode(' AND ',$where) : '';

    $total = $db->fetchOne(
        "SELECT COUNT(*) AS c FROM salesman_orders so
         JOIN salesmen s ON so.salesman_id=s.id
         JOIN orders o ON so.order_id=o.id
         JOIN customers c ON o.customer_id=c.id
         JOIN users u ON c.user_id=u.id $w", ...$params
    )['c'];

    $orders = $db->fetchAll(
        "SELECT o.id, o.order_number, o.final_amount, o.order_status, o.payment_status, o.created_at,
                u.first_name||' '||u.last_name AS customer_name,
                s.name AS salesman_name, v.business_name AS distributor_name
         FROM salesman_orders so
         JOIN salesmen s ON so.salesman_id=s.id
         JOIN orders o ON so.order_id=o.id
         JOIN customers c ON o.customer_id=c.id
         JOIN users u ON c.user_id=u.id
         LEFT JOIN vendors v ON s.distributor_id=v.id
         $w ORDER BY o.created_at DESC LIMIT $limit OFFSET $offset",
        ...$params
    );

    Response::json(['success'=>true,'data'=>$orders,'meta'=>['total'=>(int)$total,'page'=>$page,'limit'=>$limit,'pages'=>(int)ceil($total/$limit)]]);
}

Response::error('Invalid request', 404);
