<?php
$m = new mysqli('127.0.0.1','root','','sidaktejo',3306);
if($m->connect_error) { echo 'ERR: '.$m->connect_error; exit; }

echo "=== LOCAL DB TEMUAN (last 15) ===\n";
$res = $m->query("SELECT id, nomor_temuan, foto, foto_path, updated_at FROM temuan ORDER BY id DESC LIMIT 15");
while($r = $res->fetch_assoc()) {
    echo "ID:{$r['id']} | {$r['nomor_temuan']} | path:'{$r['foto_path']}' | foto:'{$r['foto']}'\n";
}

echo "\n=== SEARCHING STJ-2026-000013 specifically ===\n";
$res2 = $m->query("SELECT * FROM temuan WHERE nomor_temuan LIKE '%000013%' OR nomor_temuan LIKE '%STJ-2026%' LIMIT 5");
while($r = $res2->fetch_assoc()) {
    echo "\nID: {$r['id']}\n";
    echo "Nomor: {$r['nomor_temuan']}\n";
    echo "foto_path: '{$r['foto_path']}'\n";
    echo "foto JSON: '{$r['foto']}'\n";
    $photos = json_decode($r['foto'], true) ?: [];
    echo "Photos decoded: " . print_r($photos, true) . "\n";
}
