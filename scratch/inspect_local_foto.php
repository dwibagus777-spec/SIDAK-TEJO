<?php
$mysqli = new mysqli("127.0.0.1", "root", "", "sidaktejo", 3306);
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "=== RECENT TEMUAN IN LOCAL DATABASE ===\n";
$res = $mysqli->query("SELECT id, nomor_temuan, foto, foto_path, created_at FROM temuan ORDER BY id DESC LIMIT 10");
while ($row = $res->fetch_assoc()) {
    echo "ID: {$row['id']} | Nomor: {$row['nomor_temuan']} | foto_path: '{$row['foto_path']}' | foto: '{$row['foto']}' | Created: {$row['created_at']}\n";
}
