<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . '/config/database.php';
try {
    $db = Database::getInstance();
    $tables = $db->fetchAll("SELECT table_name FROM information_schema.tables WHERE table_schema='public' ORDER BY table_name");
    $names = array_column($tables, 'table_name');
    $check = [];
    foreach (['orders','order_items','order_status_history','payments','coupon_usage','coupons','cart','customers','customer_addresses','app_settings'] as $t) {
        $check[$t] = in_array($t, $names);
    }
    echo json_encode(['check' => $check]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
