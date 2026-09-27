<?php
// Load .env
foreach (file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
    [$k, $v] = explode('=', $line, 2);
    putenv(trim($k) . '=' . trim($v));
}

$host = getenv('DB_HOST');
$port = getenv('DB_PORT') ?: '5432';
$user = getenv('DB_USER');
$pass = getenv('DB_PASS');
$name = getenv('DB_NAME');

$password = 'Admin@1234';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "New hash: $hash\n\n";

// Try connecting
foreach (['sslmode=require', 'sslmode=prefer', 'sslmode=disable'] as $ssl) {
    try {
        $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$name;$ssl", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        echo "Connected with $ssl\n";

        // Check existing admins
        $rows = $pdo->query("SELECT id, email, role, LEFT(password_hash,20) AS hash_preview FROM users WHERE role IN ('admin','owner')")->fetchAll(PDO::FETCH_ASSOC);
        echo "Current admins:\n";
        foreach ($rows as $r) echo "  {$r['email']} ({$r['role']}) hash: {$r['hash_preview']}...\n";

        // Update passwords
        $stmt = $pdo->prepare("UPDATE users SET password_hash=? WHERE role IN ('admin','owner')");
        $stmt->execute([$hash]);
        echo "\nUpdated " . $stmt->rowCount() . " admin(s) with new hash for password: $password\n";

        // Verify
        $rows = $pdo->query("SELECT email, role FROM users WHERE role IN ('admin','owner')")->fetchAll(PDO::FETCH_ASSOC);
        echo "Done. Admins updated:\n";
        foreach ($rows as $r) echo "  {$r['email']} ({$r['role']})\n";
        break;
    } catch (Exception $e) {
        echo "Failed $ssl: " . $e->getMessage() . "\n";
    }
}
