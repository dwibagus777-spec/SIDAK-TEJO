<?php

define('FCPATH', __DIR__ . '/public/');
require __DIR__ . '/app/Config/Paths.php';
$paths = new Config\Paths();
require $paths->systemDirectory . '/Boot.php';
CodeIgniter\Boot::bootCli($paths);

$db = \Config\Database::connect();

echo "=== DISTINCT JENIS ASSET IN DB ===\n";
$distinctJenis = $db->table('assets')
    ->select('jenis_asset, count(*) as cnt')
    ->where('deleted_at IS NULL')
    ->groupBy('jenis_asset')
    ->get()->getResultArray();
print_r($distinctJenis);

echo "\n=== ASSETS FOR PENYULANG_ID = 15 ===\n";
$penyulang15 = $db->table('assets')
    ->select('id, kode_asset, nama_asset, jenis_asset, ulp_id, penyulang_id')
    ->where('penyulang_id', 15)
    ->where('deleted_at IS NULL')
    ->get()->getResultArray();
print_r($penyulang15);

echo "\n=== ASSETS FOR ULP_ID = 1 AND PENYULANG_ID = 15 ===\n";
$ulp1penyulang15 = $db->table('assets')
    ->select('id, kode_asset, nama_asset, jenis_asset, ulp_id, penyulang_id')
    ->where('ulp_id', 1)
    ->where('penyulang_id', 15)
    ->where('deleted_at IS NULL')
    ->get()->getResultArray();
print_r($ulp1penyulang15);

echo "\n=== PENYULANG TABLE ID 15 DETAILS ===\n";
$pDetails = $db->table('penyulang')
    ->where('id', 15)
    ->get()->getRowArray();
print_r($pDetails);

echo "\n=== ALL PENYULANG IN ULP_ID = 1 ===\n";
$allP = $db->table('penyulang')
    ->select('id, nama_penyulang, ulp_id, status')
    ->where('ulp_id', 1)
    ->get()->getResultArray();
print_r($allP);
