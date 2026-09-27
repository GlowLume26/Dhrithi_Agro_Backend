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

// Replace bcrypt hashes ($2y$...) with placeholders to avoid $$ confusion
$hashes = [];
$sql = preg_replace_callback(
    '/\'\$2y\$[^\']+\'/',
    function($m) use (&$hashes) {
        $key = '__HASH_' . count($hashes) . '__';
        $hashes[$key] = $m[0];
        return "'$key'";
    },
    $sql
);

// Now split on $$ blocks safely
$statements = [];
$buffer     = '';
$inDollar   = false;
$i          = 0;
$len        = strlen($sql);

while ($i < $len) {
    if ($i + 1 < $len && $sql[$i] === '$' && $sql[$i+1] === '$') {
        $inDollar = !$inDollar;
        $buffer  .= '$$';
        $i       += 2;
        continue;
    }
    $ch      = $sql[$i];
    $buffer .= $ch;
    $i++;
    if (!$inDollar && $ch === ';') {
        $stmt = trim($buffer);
        if ($stmt !== '' && !preg_match('/^--/', $stmt)) {
            // Restore bcrypt hashes
            foreach ($hashes as $k => $v) {
                $stmt = str_replace("'$k'", $v, $stmt);
            }
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
