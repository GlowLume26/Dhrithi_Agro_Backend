<?php
$host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?? 'NOT SET';
$port = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?? '5432';
$user = $_ENV['DB_USER'] ?? getenv('DB_USER') ?? 'NOT SET';
$pass = $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?? 'NOT SET';
$name = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?? 'NOT SET';

echo "DB_HOST: $host\n";
echo "DB_PORT: $port\n";
echo "DB_NAME: $name\n";
echo "DB_USER: $user\n";
echo "DB_PASS: " . (strlen($pass) > 4 ? substr($pass,0,4).'****' : $pass) . "\n\n";

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
