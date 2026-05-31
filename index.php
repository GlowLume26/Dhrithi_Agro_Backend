<?php
// ============================================================
// DRITHI AGRO — PHP API ROUTER
// URL: /backend/index.php?route=<controller>
// ============================================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$route = trim($_GET['route'] ?? '', '/');

$routes = [
    'auth'       => __DIR__ . '/controllers/auth.php',
    'products'   => __DIR__ . '/controllers/products.php',
    'categories' => __DIR__ . '/controllers/categories.php',
    'cart'       => __DIR__ . '/controllers/cart.php',
    'orders'     => __DIR__ . '/controllers/orders.php',
    'wishlist'   => __DIR__ . '/controllers/wishlist.php',
    'vendors'    => __DIR__ . '/controllers/vendors.php',
    'admin'      => __DIR__ . '/controllers/admin.php',
    'customer'   => __DIR__ . '/controllers/customer.php',
];

if (isset($routes[$route])) {
    require_once $routes[$route];
} else {
    http_response_code(404);
    echo json_encode([
        'success'          => false,
        'message'          => 'Route not found',
        'available_routes' => array_keys($routes)
    ]);
}
