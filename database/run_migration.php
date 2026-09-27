<?php
$host = 'dpg-dasi1sgu01pc73c558sg-a.singapore-postgres.render.com';
$port = '5432';
$db   = 'drithi_agro_uv0v_l5h2';
$user = 'drithi_agro_uv0v_user';
$pass = 'h4T0ntouUW9hoayO7xDdilO1KxbXH4Qh';

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$db;sslmode=require", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    echo "Connected successfully.\n";
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage() . "\n");
}

$sql = file_get_contents(__DIR__ . '/render_migration.sql');
echo "SQL file size: " . strlen($sql) . " bytes\n";
echo "First 200 chars: " . substr($sql, 0, 200) . "\n\n";

// Check if CREATE TABLE users exists in file
if (strpos($sql, 'CREATE TABLE IF NOT EXISTS users') !== false) {
    echo "✓ CREATE TABLE users found in SQL file\n";
} else {
    echo "✗ CREATE TABLE users NOT found in SQL file\n";
}

// Check encoding
echo "BOM check: " . bin2hex(substr($sql, 0, 3)) . "\n";
