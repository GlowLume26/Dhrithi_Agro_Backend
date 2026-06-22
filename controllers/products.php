<?php
require_once __DIR__ . '/../helpers/helpers.php';
require_once __DIR__ . '/../middleware/auth.php';

$db     = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$id     = $_GET['id'] ?? '';

// GET /products — list
if ($method === 'GET' && !$id) {
    $where = ['p.is_active = TRUE']; $params = [];

    if (!empty($_GET['category_id'])) { $where[] = 'p.category_id=?'; $params[] = $_GET['category_id']; }
    if (!empty($_GET['vendor_id']))   { $where[] = 'p.vendor_id=?';   $params[] = $_GET['vendor_id']; }
    if (!empty($_GET['search'])) {
        $where[] = 'p.search_vector @@ plainto_tsquery(?)';
        $params[] = $_GET['search'];
    }
    if (!empty($_GET['min_price'])) { $where[] = 'p.selling_price>=?'; $params[] = (float)$_GET['min_price']; }
    if (!empty($_GET['max_price'])) { $where[] = 'p.selling_price<=?'; $params[] = (float)$_GET['max_price']; }
    if (!empty($_GET['on_offer']))  { $where[] = 'p.selling_price < p.mrp'; }

    $allowed = ['selling_price','avg_rating','sold_count','created_at'];
    $sort    = in_array($_GET['sort'] ?? '', $allowed) ? $_GET['sort'] : 'sold_count';
    $order   = ($_GET['order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
    $page    = max(1, (int)($_GET['page'] ?? 1));
    $limit   = min(50, max(1, (int)($_GET['limit'] ?? 20)));
    $offset  = ($page - 1) * $limit;

    $whereStr = implode(' AND ', $where);
    $sql = "SELECT p.id, p.name, p.slug, p.mrp, p.selling_price, p.stock_qty, p.unit,
                   p.avg_rating, p.review_count, p.sold_count, p.is_featured,
                   c.name AS category_name, pc.name AS parent_category_name,
                   v.business_name AS vendor_name, pi.image_url AS primary_image
            FROM products p
            LEFT JOIN categories c  ON p.category_id=c.id
            LEFT JOIN categories pc ON c.parent_id=pc.id
            LEFT JOIN vendors v ON p.vendor_id=v.id
            LEFT JOIN LATERAL (
                SELECT image_url FROM product_images
                WHERE product_id=p.id AND is_primary=TRUE LIMIT 1
            ) pi ON TRUE
            WHERE $whereStr ORDER BY p.$sort $order LIMIT $limit OFFSET $offset";

    $products = $db->fetchAll($sql, ...$params);
    $total    = ($db->fetchOne("SELECT COUNT(*) AS total FROM products p WHERE $whereStr", ...$params))['total'] ?? 0;

    Response::json(['success' => true, 'data' => $products,
        'meta' => ['total' => (int)$total, 'page' => $page, 'limit' => $limit, 'pages' => (int)ceil($total / $limit)]]);
}

// GET /products?id=X — single product
if ($method === 'GET' && $id) {
    if (!Validator::uuid($id)) Response::error('Invalid product ID', 400);
    $product = $db->fetchOne(
        "SELECT p.*, c.name AS category_name, pc.name AS parent_category_name,
                v.business_name AS vendor_name, v.is_verified AS vendor_verified
         FROM products p
         LEFT JOIN categories c  ON p.category_id=c.id
         LEFT JOIN categories pc ON c.parent_id=pc.id
         LEFT JOIN vendors v ON p.vendor_id=v.id
         WHERE p.id=? AND p.is_active=TRUE", $id
    );
    if (!$product) Response::error('Product not found', 404);

    // Fetch images and reviews in 2 queries (not N+1)
    $product['images']  = $db->fetchAll("SELECT id,image_url,alt_text,sort_order,is_primary FROM product_images WHERE product_id=? ORDER BY sort_order", $id);
    $product['reviews'] = $db->fetchAll(
        "SELECT r.rating, r.review_text, r.created_at,
                u.first_name||' '||u.last_name AS customer_name
         FROM reviews r
         JOIN customers c ON r.customer_id=c.id
         JOIN users u ON c.user_id=u.id
         WHERE r.product_id=? AND r.is_approved=TRUE ORDER BY r.created_at DESC LIMIT 10", $id
    );
    Response::success('Product fetched', $product);
}

// POST /products — create (VENDOR only)
if ($method === 'POST') {
    $auth   = vendorMiddleware();
    $vendor = $db->fetchOne("SELECT id FROM vendors WHERE user_id=? AND status='approved'", $auth['user_id']);
    if (!$vendor) Response::error('Vendor not approved', 403);

    $err = Validator::required($body, ['name', 'category_id', 'mrp', 'selling_price', 'stock_qty']);
    if ($err) Response::error($err);

    $db->begin();
    $slug      = strtolower(preg_replace('/[^a-z0-9]+/', '-', $body['name'])) . '-' . time();
    $productId = $db->fetchOne("SELECT gen_random_uuid() AS id")['id'];
    $db->query(
        "INSERT INTO products (id,vendor_id,category_id,name,slug,description,sku,mrp,selling_price,stock_qty,unit,hsn_code,gst_rate)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
        $productId, $vendor['id'], $body['category_id'], $body['name'], $slug,
        $body['description'] ?? '', $body['sku'] ?? '',
        (float)$body['mrp'], (float)$body['selling_price'], (int)$body['stock_qty'],
        $body['unit'] ?? 'Piece', $body['hsn_code'] ?? '', (float)($body['gst_rate'] ?? 5.00)
    );
    $db->query("INSERT INTO inventory (id,product_id,current_stock,low_stock_threshold) VALUES (gen_random_uuid(),?,?,10)",
        $productId, (int)$body['stock_qty']);

    if (!empty($_FILES['images'])) {
        $files = $_FILES['images'];
        $count = is_array($files['name']) ? count($files['name']) : 1;
        for ($i = 0; $i < min($count, 5); $i++) {
            $file = is_array($files['name'])
                ? ['name'=>$files['name'][$i],'tmp_name'=>$files['tmp_name'][$i],'size'=>$files['size'][$i],'error'=>$files['error'][$i]]
                : $files;
            try {
                $url = FileUpload::upload($file, 'products');
                $db->query("INSERT INTO product_images (id,product_id,image_url,is_primary,sort_order) VALUES (gen_random_uuid(),?,?,?,?)",
                    $productId, $url, ($i === 0) ? 'TRUE' : 'FALSE', $i);
            } catch (Exception $e) {}
        }
    }
    $db->commit();
    Response::success('Product created', ['id' => $productId], 201);
}

// PUT /products?id=X — update (VENDOR only)
if ($method === 'PUT' && $id) {
    if (!Validator::uuid($id)) Response::error('Invalid product ID', 400);
    $auth   = vendorMiddleware();
    $vendor = $db->fetchOne("SELECT id FROM vendors WHERE user_id=? AND status='approved'", $auth['user_id']);
    if (!$vendor) Response::error('Vendor not approved', 403);
    if (!$db->fetchOne("SELECT id FROM products WHERE id=? AND vendor_id=?", $id, $vendor['id']))
        Response::error('Product not found or not yours', 404);

    $allowed = ['name','description','mrp','selling_price','stock_qty','unit','is_active','is_featured','hsn_code','gst_rate'];
    $sets = []; $params = [];
    foreach ($allowed as $f) {
        if (array_key_exists($f, $body)) { $sets[] = "$f=?"; $params[] = $body[$f]; }
    }
    if (!$sets) Response::error('Nothing to update');
    $params[] = $id; $params[] = $vendor['id'];
    $db->query("UPDATE products SET " . implode(',', $sets) . ", updated_at=NOW() WHERE id=? AND vendor_id=?", ...$params);
    Response::success('Product updated');
}

// DELETE /products?id=X — soft delete (VENDOR only)
if ($method === 'DELETE' && $id) {
    if (!Validator::uuid($id)) Response::error('Invalid product ID', 400);
    $auth   = vendorMiddleware();
    $vendor = $db->fetchOne("SELECT id FROM vendors WHERE user_id=?", $auth['user_id']);
    if (!$vendor) Response::error('Vendor not found', 403);
    $db->query("UPDATE products SET is_active=FALSE, updated_at=NOW() WHERE id=? AND vendor_id=?", $id, $vendor['id']);
    Response::success('Product deleted');
}

Response::error('Invalid request', 404);
