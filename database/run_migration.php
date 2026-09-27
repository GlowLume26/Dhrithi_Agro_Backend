<?php
$host = 'dpg-d9s1if2jnfac738kstt0-a.singapore-postgres.render.com';
$port = '5432';
$db   = 'drithi_agro_uv0v';
$user = 'drithi_agro_uv0v_user';
$pass = 'oYPKcuzlzSF5mvCIFQrr4I9quYJC0Z5p';

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$db;sslmode=require";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    echo "Connected successfully.\n";
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage() . "\n");
}

$sql = file_get_contents(__DIR__ . '/render_migration.sql');

// Split on semicolons but keep $$ blocks intact
$statements = [];
$buffer = '';
$inDollar = false;

foreach (explode("\n", $sql) as $line) {
    $trimmed = trim($line);
    if (str_contains($trimmed, '$$')) {
        $count = substr_count($trimmed, '$$');
        if ($count % 2 !== 0) $inDollar = !$inDollar;
    }
    $buffer .= $line . "\n";
    if (!$inDollar && str_ends_with(rtrim($line), ';')) {
        $stmt = trim($buffer);
        if ($stmt && !str_starts_with($stmt, '--')) {
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
