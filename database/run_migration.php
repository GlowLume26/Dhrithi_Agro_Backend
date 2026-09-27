<?php
$host = 'dpg-dasi1sgu01pc73c558sg-a.singapore-postgres.render.com';
$port = '5432';
$db   = 'drithi_agro_uv0v_l5h2';
$user = 'drithi_agro_uv0v_user';
$pass = 'h4T0ntouUW9hoayO7xDdilO1KxbXH4Qh';

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$db;sslmode=require";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    echo "Connected successfully.\n";
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage() . "\n");
}

$files = ['render_migration.sql', 'admin_seed.sql'];
$sql = '';
foreach ($files as $f) {
    $path = __DIR__ . '/' . $f;
    if (file_exists($path)) $sql .= file_get_contents($path) . "\n";
}

// Split SQL respecting $$ dollar-quoted blocks
$statements = [];
$buffer     = '';
$inDollar   = false;
$i          = 0;
$chars      = $sql;
$len        = strlen($chars);

while ($i < $len) {
    // detect $$ toggle
    if ($i + 1 < $len && $chars[$i] === '$' && $chars[$i+1] === '$') {
        $inDollar = !$inDollar;
        $buffer  .= '$$';
        $i       += 2;
        continue;
    }
    $ch      = $chars[$i];
    $buffer .= $ch;
    $i++;
    if (!$inDollar && $ch === ';') {
        $stmt = trim($buffer);
        if ($stmt !== '' && !preg_match('/^--/', $stmt)) {
            $statements[] = $stmt;
        }
        $buffer = '';
    }
}

$ok = 0; $fail = 0;
foreach ($statements as $stmt) {
    $clean = trim($stmt);
    if (!$clean || preg_match('/^--/', $clean)) continue;
    try {
        $pdo->exec($clean);
        echo "OK: " . substr(preg_replace('/\s+/', ' ', $clean), 0, 80) . "\n";
        $ok++;
    } catch (PDOException $e) {
        echo "SKIP/ERR: " . $e->getMessage() . "\n";
        $fail++;
    }
}

echo "\nDone. OK=$ok  SKIPPED/ERR=$fail\n";
