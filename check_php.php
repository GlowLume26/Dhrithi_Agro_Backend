<?php
header('Content-Type: text/plain');

$envPath = __DIR__ . '/.env';
echo "Looking for .env at: $envPath\n";
echo "File exists: " . (file_exists($envPath) ? 'YES' : 'NO') . "\n\n";

if (file_exists($envPath)) {
    echo "=== Raw .env contents ===\n";
    echo file_get_contents($envPath) . "\n\n";
    
    echo "=== Parsed values ===\n";
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        echo trim($k) . " = " . trim($v) . "\n";
    }
}

echo "\n=== What database.php will use ===\n";
foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
    [$k, $v] = explode('=', $line, 2);
    $_ENV[trim($k)] = trim($v);
}
echo "DB_USER = " . ($_ENV['DB_USER'] ?? 'NOT SET') . "\n";
echo "DB_PASS = " . ($_ENV['DB_PASS'] ?? 'NOT SET') . "\n";
echo "DB_NAME = " . ($_ENV['DB_NAME'] ?? 'NOT SET') . "\n";