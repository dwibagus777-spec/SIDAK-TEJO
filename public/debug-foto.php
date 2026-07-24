<?php
// Quick diagnostic - add to routes temporarily as: $routes->get('debug-foto/(:num)', 'DebugFoto::index/$1');
// Or just access via: /debug-foto.php directly in public folder

$m = new mysqli('127.0.0.1', 'root', '', 'sidaktejo', 3306);

// Get Railway env vars if available
$host = getenv('MYSQLHOST') ?: getenv('DB_HOST') ?: '127.0.0.1';
$port = (int)(getenv('MYSQLPORT') ?: getenv('DB_PORT') ?: 3306);
$user = getenv('MYSQLUSER') ?: getenv('DB_USER') ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: getenv('DB_PASS') ?: '';
$db   = getenv('MYSQLDATABASE') ?: getenv('DB_NAME') ?: 'sidaktejo';

if ($m->connect_error) {
    $m2 = new mysqli($host, $user, $pass, $db, $port);
    if (!$m2->connect_error) $m = $m2;
}

$res = $m->query("SELECT id, nomor_temuan, foto, foto_path FROM temuan WHERE deleted_at IS NULL ORDER BY updated_at DESC LIMIT 10");
echo "CLOUDINARY_CLOUD_NAME=" . getenv('CLOUDINARY_CLOUD_NAME') . "\n";
echo "CLOUDINARY_API_KEY=" . getenv('CLOUDINARY_API_KEY') . "\n";
echo "\n=== LATEST 10 TEMUAN FOTO ===\n";
while ($r = $res->fetch_assoc()) {
    echo "\nID:{$r['id']} | {$r['nomor_temuan']}\n";
    echo "  foto_path: '{$r['foto_path']}'\n";
    echo "  foto: '{$r['foto']}'\n";
    $photos = json_decode($r['foto'], true) ?: [];
    foreach ($photos as $p) {
        echo "  -> " . (str_starts_with($p,'http') ? '[CLOUDINARY URL]' : '[LOCAL FILE]') . ": $p\n";
    }
}
