<?php
require_once __DIR__ . '/../helpers/helpers.php';
require_once __DIR__ . '/../middleware/auth.php';

$db     = Database::getInstance();
$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$id     = $_GET['id'] ?? '';

// GET /products — list with filters & pagination
if ($method === 'GET' && !$id) {
    $where  = ['p.is_active = TRUE'];
    $params = [];

    if (!empty($_GET['category_id'])) { $where[] = 'p.category_id=?'; $params[] = $_GET['category_id']; }
    if (!empty($_GET['vendor_id']))   { $where[] = 'p.vendor_id=?';   $params[] = $_GET['vendor_id']; }
    if (!empty($_GET['search'])) {
        $s = '%' . $_GET['search'] . '%';
        $where[]  = '(p.name ILIKE ? OR p.description ILIKE ?)';
        $params[] = $s; $params[] = $s;
    }
    if (!empty($_GET['min_price'])) { $where[] = 'p.selling_price>=?'; $params[] = (float)$_GET['min_price']; }
    if (!empty($_GET['max_price'])) { $where[] = 'p.selling_price<=?'; $params[] = (float)$_GET['max_price']; }
    if (!empty($_GET['on_offer']))  { $where[] = 'p.selling_price < p.mrp'; }

    $allowed_sort = ['selling_price', 'avg_rating', 'sold_count', 'created_at'];
    $sort   = in_array($_GET['sort'] ?? '', $allowed_sort) ? $_GET['sort'] : 'sold_count';
    $order  = ($_GET['order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = min(50, max(1, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    $whereStr = implode(' AND ', $where);
    $sql = "SELECT p.*, c.name AS category_name, c.parent_id AS parent_category_id,
                   pc.name AS parent_category_name, v.business_name AS vendor_name,
                   (SELECT image_url FROM product_images WHERE product_id=p.id AND is_primary=TRUE LIMIT 1) AS primary_image
            FROM products p
            LEFT JOIN categories c  ON p.category_id=c.id
            LEFT JOIN categories pc ON c.parent_id=pc.id
            LEFT JOIN vendors v ON p.vendor_id=v.id
            WHERE $whereStr ORDER BY p.$sort $order LIMIT $limit OFFSET $offset";

    $products = $params ? $db->fetchAll($sql, '', ...$params) : $db->fetchAll($sql);
    $countRow = $params
        ? $db->fetchOne("SELECT COUNT(*) AS total FROM products p WHERE $whereStr", '', ...$params)
        : $db->fetchOne("SELECT COUNT(*) AS total FROM products p WHERE $whereStr");
    $total = $countRow['total'] ?? 0;

    Response::json(['success' => true, 'data' => $products,
        'meta' => ['total' => (int)$total, 'page' => $page, 'limit' => $limit, 'pages' => (int)ceil($total / $limit)]]);
}

// GET /products?id=X — single product
if ($method === 'GET' && $id) {
    $product = $db->fetchOne(
        "SELECT p.*, c.name AS category_name, pc.name AS parent_category_name,
                v.business_name AS vendor_name, v.is_verified AS vendor_verified
         FROM products p
         LEFT JOIN categories c  ON p.category_id=c.id
         LEFT JOIN categories pc ON c.parent_id=pc.id
         LEFT JOIN vendors v ON p.vendor_id=v.id
         WHERE p.id=? AND p.is_active=TRUE", 's', $id
    );
    if (!$product) Response::error('Product not found', 404);
    $product['images']  = $db->fetchAll("SELECT * FROM product_images WHERE product_id=? ORDER BY sort_order", 's', $id);
    $product['reviews'] = $db->fetchAll(
        "SELECT r.*, u.first_name||' '||u.last_name AS customer_name
         FROM reviews r
         JOIN customers c ON r.customer_id=c.id
         JOIN users u ON c.user_id=u.id
         WHERE r.product_id=? AND r.is_approved=TRUE ORDER BY r.created_at DESC LIMIT 10", 's', $id
    );
    Response::success('Product fetched', $product);
}

// POST /products — create (VENDOR only)
if ($method === 'POST') {
    $auth   = vendorMiddleware();
    $vendor = $db->fetchOne("SELECT id FROM vendors WHERE user_id=? AND status='approved'", 's', $auth['user_id']);
    if (!$vendor) Response::error('Vendor not approved', 403);

    $err = Validator::required($body, ['name', 'category_id', 'mrp', 'selling_price', 'stock_qty']);
    if ($err) Response::error($err);

    $slug      = strtolower(preg_replace('/[^a-z0-9]+/', '-', $body['name'])) . '-' . time();
    $productId = OtpHelper::uuid();
    $db->query(
        "INSERT INTO products (id, vendor_id, category_id, name, slug, description, sku, mrp, selling_price, stock_qty, unit, hsn_code, gst_rate)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
        'sssssssddiss d',
        $productId, $vendor['id'], $body['category_id'],
        $body['name'], $slug, $body['description'] ?? '', $body['sku'] ?? '',
        (float)$body['mrp'], (float)$body['selling_price'], (int)$body['stock_qty'],
        $body['unit'] ?? 'Piece', $body['hsn_code'] ?? '', (float)($body['gst_rate'] ?? 5.00)
    );

    if (!empty($_FILES['images'])) {
        $files = $_FILES['images'];
        $count = is_array($files['name']) ? count($files['name']) : 1;
        for ($i = 0; $i < min($count, 5); $i++) {
            $file = is_array($files['name'])
                ? ['name' => $files['name'][$i], 'tmp_name' => $files['tmp_name'][$i], 'size' => $files['size'][$i], 'error' => $files['error'][$i]]
                : $files;
            try {
                $url = FileUpload::upload($file, 'products');
                $db->query("INSERT INTO product_images (id, product_id, image_url, is_primary, sort_order) VALUES (?,?,?,?,?)",
                    'sssii', OtpHelper::uuid(), $productId, $url, $i === 0 ? 1 : 0, $i);
            } catch (Exception $e) {}
        }
    }
    Response::success('Product created', ['id' => $productId], 201);
}

// PUT /products?id=X — update (VENDOR only)
if ($method === 'PUT' && $id) {
    $auth   = vendorMiddleware();
    $vendor = $db->fetchOne("SELECT id FROM vendors WHERE user_id=? AND status='approved'", 's', $auth['user_id']);
    if (!$vendor) Response::error('Vendor not approved', 403);
    if (!$db->fetchOne("SELECT id FROM products WHERE id=? AND vendor_id=?", 'ss', $id, $vendor['id'])) {
        Response::error('Product not found or not yours', 404);
    }
    $fields = ['name', 'description', 'mrp', 'selling_price', 'stock_qty', 'unit', 'is_active', 'is_featured'];
    $sets = []; $params = [];
    foreach ($fields as $f) {
        if (isset($body[$f])) { $sets[] = "$f=?"; $params[] = $body[$f]; }
    }
    if (!$sets) Response::error('Nothing to update');
    $params[] = $id; $params[] = $vendor['id'];
    $db->query("UPDATE products SET " . implode(',', $sets) . " WHERE id=? AND vendor_id=?", '', ...$params);
    Response::success('Product updated');
}

// DELETE /products?id=X — soft delete (VENDOR only)
if ($method === 'DELETE' && $id) {
    $auth   = vendorMiddleware();
    $vendor = $db->fetchOne("SELECT id FROM vendors WHERE user_id=?", 's', $auth['user_id']);
    $db->query("UPDATE products SET is_active=FALSE WHERE id=? AND vendor_id=?", 'ss', $id, $vendor['id']);
    Response::success('Product deleted');
}

Response::error('Invalid request', 404);
