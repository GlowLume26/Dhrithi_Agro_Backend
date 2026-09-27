<?php
$host = 'dpg-dasi1sgu01pc73c558sg-a.singapore-postgres.render.com';
$port = '5432';
$db   = 'drithi_agro_uv0v_l5h2';
$user = 'drithi_agro_uv0v_user';
$pass = 'h4T0ntouUW9hoayO7xDdilO1KxbXH4Qh';

// Test connection first
try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$db;sslmode=require", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    echo "Connected successfully.\n";
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage() . "\n");
}

// Use psql to run SQL — handles $$ blocks and complex SQL correctly
$cmd = "PGPASSWORD=" . escapeshellarg($pass)
     . " psql"
     . " -h " . escapeshellarg($host)
     . " -p " . escapeshellarg($port)
     . " -U " . escapeshellarg($user)
     . " -d " . escapeshellarg($db)
     . " --set=sslmode=require"
     . " -f " . escapeshellarg(__DIR__ . '/render_migration.sql')
     . " 2>&1";

echo "Running render_migration.sql...\n";
passthru($cmd);
echo "\nDone.\n";
