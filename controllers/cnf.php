<?php
require_once __DIR__ . '/../helpers/helpers.php';
require_once __DIR__ . '/../middleware/auth.php';

$db     = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$auth   = adminMiddleware();

$section = $_GET['section'] ?? '';
$PAGE    = 20;

// Permission helper
function checkCnfPerm($auth, $db, $module) {
    if ($auth['role'] === 'owner') return;
    $perm = $db->fetchOne("SELECT granted FROM user_permissions WHERE user_id=? AND module=?", $auth['user_id'], $module);
    if (!$perm || !$perm['granted']) Response::error('Access denied', 403);
}

// ── COMPANIES ──────────────────────────────────────────────
if ($section === 'companies') {
    checkCnfPerm($auth, $db, 'cnf');

    if ($method === 'GET') {
        $state  = $_GET['state'] ?? '';
        $search = trim($_GET['search'] ?? '');
        $where = []; $params = [];
        if ($state)  { $where[] = 'state ILIKE ?'; $params[] = "%$state%"; }
        if ($search) { $where[] = "(name ILIKE ? OR contact_person ILIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
        $w = $where ? 'WHERE '.implode(' AND ', $where) : '';
        $companies = $db->fetchAll("SELECT * FROM cnf_companies $w ORDER BY name", ...$params);
        // Attach warehouse count
        foreach ($companies as &$c) {
            $c['warehouse_count'] = $db->fetchOne("SELECT COUNT(*) AS n FROM warehouses WHERE cnf_company_id=?", $c['id'])['n'];
        }
        Response::success('Companies fetched', $companies);
    }

    if ($method === 'POST') {
        $err = Validator::required($body, ['name']);
        if ($err) Response::error($err);
        $id = $db->fetchOne("SELECT gen_random_uuid() AS id")['id'];
        $db->query(
            "INSERT INTO cnf_companies (id,name,contact_person,contact_number,email,address,city,state,pincode,gst_number,status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)",
            $id, $body['name'], $body['contact_person']??null, $body['contact_number']??null,
            $body['email']??null, $body['address']??null, $body['city']??null,
            $body['state']??null, $body['pincode']??null, $body['gst_number']??null, 'active'
        );
        Response::success('Company created', ['id'=>$id], 201);
    }

    if ($method === 'PUT') {
        $id = $_GET['id'] ?? '';
        if (!$id) Response::error('ID required');
        $fields = ['name','contact_person','contact_number','email','address','city','state','pincode','gst_number','status'];
        $sets=[]; $params=[];
        foreach ($fields as $f) { if (isset($body[$f])) { $sets[]="$f=?"; $params[]=$body[$f]; } }
        if (!$sets) Response::error('Nothing to update');
        $params[] = $id;
        $db->query("UPDATE cnf_companies SET ".implode(',',$sets).",updated_at=NOW() WHERE id=?", ...$params);
        Response::success('Company updated');
    }

    if ($method === 'DELETE') {
        $id = $_GET['id'] ?? '';
        $db->query("DELETE FROM cnf_companies WHERE id=?", $id);
        Response::success('Company deleted');
    }
}

// ── WAREHOUSES ─────────────────────────────────────────────
if ($section === 'warehouses') {
    checkCnfPerm($auth, $db, 'cnf');

    if ($method === 'GET') {
        $cnfId = $_GET['cnf_company_id'] ?? '';
        $state = $_GET['state'] ?? '';
        $where=[]; $params=[];
        if ($cnfId) { $where[]='w.cnf_company_id=?'; $params[]=$cnfId; }
        if ($state) { $where[]='w.state ILIKE ?'; $params[]="%$state%"; }
        $w = $where ? 'WHERE '.implode(' AND ',$where) : '';
        $warehouses = $db->fetchAll(
            "SELECT w.*, c.name AS company_name FROM warehouses w
             JOIN cnf_companies c ON w.cnf_company_id=c.id $w ORDER BY w.name",
            ...$params
        );
        Response::success('Warehouses fetched', $warehouses);
    }

    if ($method === 'POST') {
        $err = Validator::required($body, ['cnf_company_id','name']);
        if ($err) Response::error($err);
        $id = $db->fetchOne("SELECT gen_random_uuid() AS id")['id'];
        $db->query(
            "INSERT INTO warehouses (id,cnf_company_id,name,address,city,state,pincode,manager_name,contact_number,email,status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?)",
            $id, $body['cnf_company_id'], $body['name'], $body['address']??null,
            $body['city']??null, $body['state']??null, $body['pincode']??null,
            $body['manager_name']??null, $body['contact_number']??null, $body['email']??null, 'active'
        );
        Response::success('Warehouse created', ['id'=>$id], 201);
    }

    if ($method === 'PUT') {
        $id = $_GET['id'] ?? '';
        $fields = ['name','address','city','state','pincode','manager_name','contact_number','email','status'];
        $sets=[]; $params=[];
        foreach ($fields as $f) { if (isset($body[$f])) { $sets[]="$f=?"; $params[]=$body[$f]; } }
        if (!$sets) Response::error('Nothing to update');
        $params[] = $id;
        $db->query("UPDATE warehouses SET ".implode(',',$sets).",updated_at=NOW() WHERE id=?", ...$params);
        Response::success('Warehouse updated');
    }

    if ($method === 'DELETE') {
        $id = $_GET['id'] ?? '';
        $db->query("DELETE FROM warehouses WHERE id=?", $id);
        Response::success('Warehouse deleted');
    }
}

// ── STOCK ──────────────────────────────────────────────────
if ($section === 'stock') {
    checkCnfPerm($auth, $db, 'cnf_stock');

    if ($method === 'GET') {
        $page        = max(1,(int)($_GET['page']??1));
        $limit       = max(1,min(100,(int)($_GET['limit']??$PAGE)));
        $offset      = ($page-1)*$limit;
        $warehouseId = $_GET['warehouse_id'] ?? '';
        $cnfId       = $_GET['cnf_company_id'] ?? '';
        $categoryId  = $_GET['category_id'] ?? '';
        $expiryStatus= $_GET['expiry_status'] ?? '';
        $search      = trim($_GET['search'] ?? '');
        $sort        = $_GET['sort'] ?? 'name';

        $where=[]; $params=[];
        if ($warehouseId) { $where[]='wi.warehouse_id=?'; $params[]=$warehouseId; }
        if ($cnfId)       { $where[]='w.cnf_company_id=?'; $params[]=$cnfId; }
        if ($categoryId)  { $where[]='p.category_id=?'; $params[]=$categoryId; }
        if ($search)      { $where[]='p.name ILIKE ?'; $params[]="%$search%"; }
        if ($expiryStatus === 'expired')       { $where[]='wi.expiry_date < NOW()'; }
        elseif ($expiryStatus === 'expiring_soon') { $where[]='wi.expiry_date BETWEEN NOW() AND NOW()+INTERVAL \'30 days\''; }
        elseif ($expiryStatus === 'good')      { $where[]='(wi.expiry_date IS NULL OR wi.expiry_date > NOW()+INTERVAL \'30 days\')'; }

        $w = $where ? 'WHERE '.implode(' AND ',$where) : '';
        $orderBy = match($sort) {
            'stock_asc'  => 'wi.current_stock ASC',
            'stock_desc' => 'wi.current_stock DESC',
            'expiry'     => 'wi.expiry_date ASC NULLS LAST',
            default      => 'p.name ASC'
        };

        $total = $db->fetchOne(
            "SELECT COUNT(*) AS c FROM warehouse_inventory wi
             JOIN warehouses w ON wi.warehouse_id=w.id
             JOIN products p ON wi.product_id=p.id
             LEFT JOIN categories cat ON p.category_id=cat.id
             $w", ...$params
        )['c'];

        $items = $db->fetchAll(
            "SELECT wi.*, p.name AS product_name, p.sku, cat.name AS category_name,
                    w.name AS warehouse_name, c.name AS company_name,
                    CASE
                        WHEN wi.expiry_date IS NULL THEN 'no_expiry'
                        WHEN wi.expiry_date < NOW() THEN 'expired'
                        WHEN wi.expiry_date <= NOW()+INTERVAL '30 days' THEN 'expiring_soon'
                        ELSE 'good'
                    END AS expiry_status,
                    EXTRACT(DAY FROM wi.expiry_date - NOW())::INT AS days_to_expiry
             FROM warehouse_inventory wi
             JOIN warehouses w ON wi.warehouse_id=w.id
             JOIN cnf_companies c ON w.cnf_company_id=c.id
             JOIN products p ON wi.product_id=p.id
             LEFT JOIN categories cat ON p.category_id=cat.id
             $w ORDER BY $orderBy LIMIT $limit OFFSET $offset",
            ...$params
        );

        Response::json(['success'=>true,'data'=>$items,'meta'=>['total'=>(int)$total,'page'=>$page,'limit'=>$limit,'pages'=>(int)ceil($total/$limit)]]);
    }

    if ($method === 'POST') {
        $err = Validator::required($body, ['warehouse_id','product_id','current_stock']);
        if ($err) Response::error($err);
        $id = $db->fetchOne("SELECT gen_random_uuid() AS id")['id'];
        $db->query(
            "INSERT INTO warehouse_inventory (id,warehouse_id,product_id,batch_number,expiry_date,current_stock,reserved_stock,incoming_stock,outgoing_stock)
             VALUES (?,?,?,?,?,?,?,?,?)
             ON CONFLICT (warehouse_id,product_id,batch_number) DO UPDATE
             SET current_stock=EXCLUDED.current_stock, updated_at=NOW()",
            $id, $body['warehouse_id'], $body['product_id'],
            $body['batch_number']??'DEFAULT', $body['expiry_date']??null,
            $body['current_stock'], $body['reserved_stock']??0,
            $body['incoming_stock']??0, $body['outgoing_stock']??0
        );
        Response::success('Stock updated', ['id'=>$id], 201);
    }
}

// ── CNF ORDERS / INVOICES ──────────────────────────────────
if ($section === 'orders') {
    checkCnfPerm($auth, $db, 'cnf_invoices');

    if ($method === 'GET') {
        $page   = max(1,(int)($_GET['page']??1));
        $limit  = max(1,min(100,(int)($_GET['limit']??$PAGE)));
        $offset = ($page-1)*$limit;
        $cnfId  = $_GET['cnf_company_id'] ?? '';
        $whId   = $_GET['warehouse_id'] ?? '';
        $status = $_GET['status'] ?? '';
        $search = trim($_GET['search'] ?? '');

        $where=[]; $params=[];
        if ($cnfId)  { $where[]='co.cnf_company_id=?'; $params[]=$cnfId; }
        if ($whId)   { $where[]='co.warehouse_id=?'; $params[]=$whId; }
        if ($status) { $where[]='co.order_status=?'; $params[]=$status; }
        if ($search) { $where[]="(co.invoice_number ILIKE ? OR c.name ILIKE ?)"; $params[]="%$search%"; $params[]="%$search%"; }
        $w = $where ? 'WHERE '.implode(' AND ',$where) : '';

        $total = $db->fetchOne("SELECT COUNT(*) AS c FROM cnf_orders co JOIN cnf_companies c ON co.cnf_company_id=c.id $w", ...$params)['c'];
        $orders = $db->fetchAll(
            "SELECT co.*, c.name AS company_name, wh.name AS warehouse_name
             FROM cnf_orders co
             JOIN cnf_companies c ON co.cnf_company_id=c.id
             LEFT JOIN warehouses wh ON co.warehouse_id=wh.id
             $w ORDER BY co.order_date DESC LIMIT $limit OFFSET $offset",
            ...$params
        );

        if ($orders) {
            $ids = array_column($orders, 'id');
            $ph  = implode(',', array_fill(0, count($ids), '?'));
            $items = $db->fetchAll("SELECT * FROM cnf_order_items WHERE cnf_order_id IN ($ph)", ...$ids);
            $byOrder = [];
            foreach ($items as $it) $byOrder[$it['cnf_order_id']][] = $it;
            foreach ($orders as &$o) $o['items'] = $byOrder[$o['id']] ?? [];
        }

        Response::json(['success'=>true,'data'=>$orders,'meta'=>['total'=>(int)$total,'page'=>$page,'limit'=>$limit,'pages'=>(int)ceil($total/$limit)]]);
    }

    if ($method === 'POST') {
        $err = Validator::required($body, ['cnf_company_id','items']);
        if ($err) Response::error($err);
        $total = 0;
        foreach ($body['items'] as $it) $total += ($it['quantity']??1)*($it['unit_price']??0);
        $id  = $db->fetchOne("SELECT gen_random_uuid() AS id")['id'];
        $inv = 'CNF-INV-'.strtoupper(substr($id,0,8));
        $db->begin();
        $db->query(
            "INSERT INTO cnf_orders (id,invoice_number,cnf_company_id,warehouse_id,total_amount,payment_status,order_status,notes)
             VALUES (?,?,?,?,?,?,?,?)",
            $id, $inv, $body['cnf_company_id'], $body['warehouse_id']??null,
            $total, $body['payment_status']??'pending', $body['order_status']??'pending', $body['notes']??null
        );
        foreach ($body['items'] as $it) {
            $itTotal = ($it['quantity']??1)*($it['unit_price']??0);
            $db->query(
                "INSERT INTO cnf_order_items (cnf_order_id,product_id,product_name,quantity,unit_price,total)
                 VALUES (?,?,?,?,?,?)",
                $id, $it['product_id']??null, $it['product_name']??'', $it['quantity']??1, $it['unit_price']??0, $itTotal
            );
        }
        $db->commit();
        Response::success('Order created', ['id'=>$id,'invoice_number'=>$inv], 201);
    }

    if ($method === 'PUT') {
        $id = $_GET['id'] ?? '';
        $sets=[]; $params=[];
        foreach (['order_status','payment_status','notes'] as $f) {
            if (isset($body[$f])) { $sets[]="$f=?"; $params[]=$body[$f]; }
        }
        if (!$sets) Response::error('Nothing to update');
        $params[]=$id;
        $db->query("UPDATE cnf_orders SET ".implode(',',$sets).",updated_at=NOW() WHERE id=?", ...$params);
        Response::success('Order updated');
    }
}

// ── FAST MOVING PRODUCTS ───────────────────────────────────
if ($section === 'fast_moving') {
    checkCnfPerm($auth, $db, 'cnf_stock');

    $from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
    $to   = $_GET['to']   ?? date('Y-m-d');
    $cnfId= $_GET['cnf_company_id'] ?? '';
    $whId = $_GET['warehouse_id'] ?? '';

    $where = ["o.payment_status='paid'", "o.created_at BETWEEN ? AND ?::date+1"];
    $params = [$from, $to];
    if ($cnfId) { /* filter by cnf if needed */ }

    $products = $db->fetchAll(
        "SELECT p.id, p.name, cat.name AS category_name,
                SUM(oi.quantity) AS units_sold,
                COUNT(DISTINCT oi.order_id) AS order_count,
                SUM(oi.total) AS sales_amount,
                p.stock_qty AS current_stock
         FROM order_items oi
         JOIN products p ON oi.product_id=p.id
         LEFT JOIN categories cat ON p.category_id=cat.id
         JOIN orders o ON oi.order_id=o.id
         WHERE o.payment_status='paid' AND o.created_at BETWEEN ? AND ?::date+1
         GROUP BY p.id, p.name, cat.name, p.stock_qty
         ORDER BY units_sold DESC LIMIT 50",
        $from, $to
    );
    Response::success('Fast moving products', $products);
}

// ── EXPIRY REPORT ──────────────────────────────────────────
if ($section === 'expiry') {
    checkCnfPerm($auth, $db, 'cnf_stock');

    $cnfId  = $_GET['cnf_company_id'] ?? '';
    $whId   = $_GET['warehouse_id'] ?? '';
    $status = $_GET['expiry_status'] ?? '';

    $where=[]; $params=[];
    if ($cnfId) { $where[]='w.cnf_company_id=?'; $params[]=$cnfId; }
    if ($whId)  { $where[]='wi.warehouse_id=?'; $params[]=$whId; }
    if ($status === 'expired')       { $where[]='wi.expiry_date < NOW()'; }
    elseif ($status === 'expiring_7') { $where[]='wi.expiry_date BETWEEN NOW() AND NOW()+INTERVAL \'7 days\''; }
    elseif ($status === 'expiring_30'){ $where[]='wi.expiry_date BETWEEN NOW() AND NOW()+INTERVAL \'30 days\''; }
    $where[] = 'wi.expiry_date IS NOT NULL';
    $w = 'WHERE '.implode(' AND ',$where);

    $items = $db->fetchAll(
        "SELECT wi.*, p.name AS product_name, cat.name AS category_name,
                w.name AS warehouse_name, c.name AS company_name,
                EXTRACT(DAY FROM wi.expiry_date - NOW())::INT AS days_remaining,
                CASE
                    WHEN wi.expiry_date < NOW() THEN 'expired'
                    WHEN wi.expiry_date <= NOW()+INTERVAL '7 days' THEN 'expiring_7'
                    WHEN wi.expiry_date <= NOW()+INTERVAL '30 days' THEN 'expiring_30'
                    ELSE 'good'
                END AS expiry_status
         FROM warehouse_inventory wi
         JOIN warehouses w ON wi.warehouse_id=w.id
         JOIN cnf_companies c ON w.cnf_company_id=c.id
         JOIN products p ON wi.product_id=p.id
         LEFT JOIN categories cat ON p.category_id=cat.id
         $w ORDER BY wi.expiry_date ASC",
        ...$params
    );
    Response::success('Expiry report', $items);
}

Response::error('Invalid request', 404);
