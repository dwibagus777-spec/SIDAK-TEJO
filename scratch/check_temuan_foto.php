<?php
/**
 * Diagnostic script - check foto record for specific temuan
 * Run: php scratch/check_temuan_foto.php
 */

$host = getenv('DB_HOST') ?: 'monorail.proxy.rlwy.net';
$port = (int)(getenv('DB_PORT') ?: 41249);
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: 'RjUymwIsYgVzMhWffuPqkWvHzGZlFqLq';
$db   = getenv('DB_NAME') ?: 'railway';

// Try to load from .env file if it exists
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (str_contains($line, '=')) {
            [$key, $val] = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val, " \t\n\r\0\x0B\"'");
            switch ($key) {
                case 'database.default.hostname': $host = $val; break;
                case 'database.default.port':     $port = (int)$val; break;
                case 'database.default.username': $user = $val; break;
                case 'database.default.password': $pass = $val; break;
                case 'database.default.database': $db   = $val; break;
            }
        }
    }
}

echo "Connecting to: {$host}:{$port} db={$db} user={$user}\n";

$mysqli = @new mysqli($host, $user, $pass, $db, $port);
if ($mysqli->connect_error) {
    // Fallback to local XAMPP
    $mysqli = new mysqli('127.0.0.1', 'root', '', 'sidaktejo', 3306);
    echo "Using LOCAL database: 127.0.0.1 sidaktejo\n";
}

echo "\n=== TEMUAN STJ-2026-000013 ===\n";
$stmt = $mysqli->prepare("SELECT id, nomor_temuan, foto, foto_path, created_at, updated_at FROM temuan WHERE nomor_temuan LIKE ? OR nomor_temuan LIKE ? OR id = ? ORDER BY id DESC LIMIT 5");
$like1 = '%STJ-2026-000013%';
$like2 = '%STJ%000013%';
$id = 13;
$stmt->bind_param('ssi', $like1, $like2, $id);
$stmt->execute();
$result = $stmt->get_result();

$found = false;
while ($row = $result->fetch_assoc()) {
    $found = true;
    echo "\nID: {$row['id']}\n";
    echo "Nomor: {$row['nomor_temuan']}\n";
    echo "foto_path: '{$row['foto_path']}'\n";
    echo "foto JSON: '{$row['foto']}'\n";
    echo "created_at: {$row['created_at']}\n";
    echo "updated_at: {$row['updated_at']}\n";
    
    $photos = json_decode($row['foto'], true) ?: [];
    if (empty($photos) && !empty($row['foto'])) {
        $photos = [$row['foto']]; // non-JSON single file
    }
    echo "Photo count: " . count($photos) . "\n";
    foreach ($photos as $i => $p) {
        echo "  Photo[$i]: '{$p}'\n";
    }
}

if (!$found) {
    echo "NOT FOUND with those criteria. Showing last 10 temuan:\n";
    $res2 = $mysqli->query("SELECT id, nomor_temuan, foto, foto_path FROM temuan ORDER BY id DESC LIMIT 10");
    while ($row = $res2->fetch_assoc()) {
        echo "ID: {$row['id']} | {$row['nomor_temuan']} | path: '{$row['foto_path']}' | foto: '{$row['foto']}'\n";
    }
}
