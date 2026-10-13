<?php

$pdo = new PDO('mysql:host=127.0.0.1;dbname=sidaktejo;charset=utf8mb4', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$ulpCount = $pdo->query('SELECT COUNT(*) FROM ulp')->fetchColumn();
$ulps = $pdo->query('SELECT id, nama_ulp, kode_ulp FROM ulp')->fetchAll(PDO::FETCH_ASSOC);

$giCount = $pdo->query('SELECT COUNT(*) FROM gardu_induk')->fetchColumn();
$gis = $pdo->query('SELECT id, nama_gi, kode_gi FROM gardu_induk')->fetchAll(PDO::FETCH_ASSOC);

$feederCount = $pdo->query('SELECT COUNT(*) FROM penyulang')->fetchColumn();
$feeders = $pdo->query('SELECT id, nama_penyulang, kode_penyulang, gardu_induk_id FROM penyulang')->fetchAll(PDO::FETCH_ASSOC);

$assetCount = $pdo->query('SELECT COUNT(*) FROM assets')->fetchColumn();
$activeAssets = $pdo->query('SELECT COUNT(*) FROM assets WHERE deleted_at IS NULL')->fetchColumn();

$transCount = $pdo->query('SELECT COUNT(*) FROM gis_translines')->fetchColumn();
$activeTrans = $pdo->query('SELECT COUNT(*) FROM gis_translines WHERE is_active = 1')->fetchColumn();

echo "=== LOCAL MYSQL DATABASE INVENTORY ===\n";
echo "ULP Count: $ulpCount\n";
foreach ($ulps as $u) {
    echo " - [{$u['id']}] {$u['kode_ulp']} - {$u['nama_ulp']}\n";
}
echo "\nGI Count: $giCount\n";
foreach ($gis as $g) {
    echo " - [{$g['id']}] {$g['kode_gi']} - {$g['nama_gi']}\n";
}
echo "\nFeeder Count: $feederCount\n";
foreach ($feeders as $f) {
    $stmtA = $pdo->prepare('SELECT COUNT(*) FROM assets WHERE penyulang_id = ? AND deleted_at IS NULL');
    $stmtA->execute([$f['id']]);
    $cA = $stmtA->fetchColumn();

    $stmtT = $pdo->prepare('SELECT COUNT(*) FROM gis_translines WHERE penyulang_id = ? AND is_active = 1');
    $stmtT->execute([$f['id']]);
    $cT = $stmtT->fetchColumn();

    echo " - [{$f['id']}] {$f['kode_penyulang']} - {$f['nama_penyulang']} (GI #{$f['gardu_induk_id']}): $cA active assets, $cT active translines\n";
}
echo "\nTotal Assets in DB: $assetCount (Active: $activeAssets)\n";
echo "Total Translines in DB: $transCount (Active: $activeTrans)\n";

$assetPenyulang = $pdo->query('SELECT penyulang_id, count(*) as cnt FROM assets GROUP BY penyulang_id')->fetchAll(PDO::FETCH_ASSOC);
echo "\nAsset breakdown by penyulang_id:\n";
foreach ($assetPenyulang as $ap) {
    $pId = $ap['penyulang_id'] !== null ? $ap['penyulang_id'] : 'NULL';
    echo " - penyulang_id: $pId -> {$ap['cnt']} assets\n";
}
