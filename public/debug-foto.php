<?php
// Quick Cloudinary + DB diagnostic for Railway
// Access: /debug-foto.php

// Read Railway env vars
$cloudName = getenv('CLOUDINARY_CLOUD_NAME') ?: 'NOT SET';
$apiKey    = getenv('CLOUDINARY_API_KEY') ?: 'NOT SET';
$apiSecret = getenv('CLOUDINARY_API_SECRET') ?: 'NOT SET';

// DB env vars on Railway
$host = getenv('MYSQLHOST') ?: getenv('DB_HOST') ?: '127.0.0.1';
$port = (int)(getenv('MYSQLPORT') ?: getenv('DB_PORT') ?: 3306);
$user = getenv('MYSQLUSER') ?: getenv('DB_USER') ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: getenv('DB_PASS') ?: '';
$db   = getenv('MYSQLDATABASE') ?: getenv('DB_NAME') ?: 'railway';

header('Content-Type: text/plain; charset=utf-8');

echo "=== CLOUDINARY ENV VARS ===\n";
echo "CLOUDINARY_CLOUD_NAME: " . ($cloudName !== 'NOT SET' ? substr($cloudName, 0, 4) . '****' : 'NOT SET ❌') . "\n";
echo "CLOUDINARY_API_KEY:    " . ($apiKey !== 'NOT SET' ? substr($apiKey, 0, 6) . '****' : 'NOT SET ❌') . "\n";
echo "CLOUDINARY_API_SECRET: " . ($apiSecret !== 'NOT SET' ? 'SET ✅' : 'NOT SET ❌') . "\n";

echo "\n=== DB CONNECTION ===\n";
echo "Host: $host:$port | DB: $db | User: $user\n";

$m = @new mysqli($host, $user, $pass, $db, $port);
if ($m->connect_error) {
    echo "DB Connect ERROR: " . $m->connect_error . "\n";
    exit;
}

echo "DB Connected ✅\n";

echo "\n=== LATEST 10 TEMUAN FOTO ===\n";
$res = $m->query("SELECT id, nomor_temuan, foto, foto_path, updated_at FROM temuan WHERE deleted_at IS NULL ORDER BY updated_at DESC LIMIT 10");
if (!$res) { echo "Query error: " . $m->error; exit; }

while ($r = $res->fetch_assoc()) {
    echo "\nID:{$r['id']} | {$r['nomor_temuan']} | updated:{$r['updated_at']}\n";
    echo "  foto_path: '{$r['foto_path']}'\n";
    $photos = json_decode($r['foto'], true) ?: [];
    if (empty($photos) && !empty($r['foto'])) $photos = [$r['foto']];
    foreach ($photos as $p) {
        $type = str_starts_with($p, 'http') ? '☁️ CLOUDINARY' : '💾 LOCAL FILE';
        echo "  $type: " . substr($p, 0, 80) . "\n";
    }
    if (empty($photos)) echo "  (no photos)\n";
}
