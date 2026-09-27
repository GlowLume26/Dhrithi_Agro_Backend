<?php
$envFile = __DIR__ . '/.env';
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
    [$k, $v] = explode('=', $line, 2);
    putenv(trim($k) . '=' . trim($v));
}
$host = getenv('DB_HOST');
$port = getenv('DB_PORT') ?: '5432';
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$name = getenv('DB_NAME');

echo "Connecting to: $host:$port/$name as $user\n";

foreach (['sslmode=require','sslmode=prefer','sslmode=disable'] as $ssl) {
    $dsn = "pgsql:host=$host;port=$port;dbname=$name;$ssl";
    try {
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        echo "SUCCESS with $ssl\n";
        break;
    } catch (Exception $e) {
        echo "FAILED with $ssl: " . $e->getMessage() . "\n";
    }
}
