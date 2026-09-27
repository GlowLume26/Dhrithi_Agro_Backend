<?php
$host = 'localhost';
$port = '5432';
$db   = 'drithi_agro';
$user = 'postgres';
$pass = 'Bhavesh123';

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$db", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage() . "\n");
}

$out = '';

// Export vendors
$vendors = $pdo->query("SELECT * FROM vendors")->fetchAll(PDO::FETCH_ASSOC);
$out .= "-- VENDORS\n";
foreach ($vendors as $v) {
    $cols = implode(', ', array_keys($v));
    $vals = implode(', ', array_map(fn($x) => $x === null ? 'NULL' : "'" . addslashes($x) . "'", array_values($v)));
    $out .= "INSERT INTO vendors ($cols) VALUES ($vals) ON CONFLICT (id) DO NOTHING;\n";
}

// Export products
$products = $pdo->query("SELECT * FROM products")->fetchAll(PDO::FETCH_ASSOC);
$out .= "\n-- PRODUCTS\n";
foreach ($products as $p) {
    $cols = implode(', ', array_keys($p));
    $vals = implode(', ', array_map(fn($x) => $x === null ? 'NULL' : "'" . addslashes($x) . "'", array_values($p)));
    $out .= "INSERT INTO products ($cols) VALUES ($vals) ON CONFLICT (id) DO NOTHING;\n";
}

// Export product_images
$images = $pdo->query("SELECT * FROM product_images")->fetchAll(PDO::FETCH_ASSOC);
$out .= "\n-- PRODUCT IMAGES\n";
foreach ($images as $img) {
    $cols = implode(', ', array_keys($img));
    $vals = implode(', ', array_map(fn($x) => $x === null ? 'NULL' : "'" . addslashes($x) . "'", array_values($img)));
    $out .= "INSERT INTO product_images ($cols) VALUES ($vals) ON CONFLICT (id) DO NOTHING;\n";
}

file_put_contents(__DIR__ . '/database/products_export.sql', $out);
echo "Exported " . count($vendors) . " vendors, " . count($products) . " products, " . count($images) . " images.\n";
