<?php

define('FCPATH', __DIR__ . '/public/');
chdir(__DIR__);

require 'app/Config/Paths.php';
$paths = new Config\Paths();

// Define constants
define('APPPATH', realpath($paths->appDirectory) . DIRECTORY_SEPARATOR);
define('ROOTPATH', realpath($paths->appDirectory . '/../') . DIRECTORY_SEPARATOR);
define('SYSTEMPATH', realpath($paths->systemDirectory) . DIRECTORY_SEPARATOR);
define('WRITEPATH', realpath($paths->writableDirectory) . DIRECTORY_SEPARATOR);

require SYSTEMPATH . 'bootstrap.php';

$db = \Config\Database::connect();

echo "========================================\n";
echo "SIDAK TEJO FORENSIC ASSET TRACE\n";
echo "========================================\n\n";

// 1. SELECT FORENSIC TEST ASSET FROM GIS QUERY RESULT (penyulang_id = 15)
$gisAssets = $db->table('assets a')
    ->select('a.id, a.kode_asset, a.nama_asset, a.jenis_asset, a.status, a.ulp_id, a.penyulang_id, a.latitude, a.longitude, a.deleted_at')
    ->where('a.penyulang_id', 15)
    ->where('a.deleted_at IS NULL')
    ->where('a.latitude !=', 0)
    ->where('a.longitude !=', 0)
    ->limit(1)
    ->get()
    ->getResultArray();

if (empty($gisAssets)) {
    echo "ERROR: No GIS assets found with penyulang_id = 15!\n";
    exit(1);
}

$testAsset = $gisAssets[0];
$assetId   = $testAsset['id'];

echo "----------------------------------------\n";
echo "FORENSIC TEST ASSET IDENTIFIED FROM GIS:\n";
echo "----------------------------------------\n";
echo "ID          : " . $testAsset['id'] . "\n";
echo "Kode Asset  : " . $testAsset['kode_asset'] . "\n";
echo "Nama Asset  : " . $testAsset['nama_asset'] . "\n";
echo "Jenis Asset : '" . $testAsset['jenis_asset'] . "'\n";
echo "Status      : '" . $testAsset['status'] . "'\n";
echo "ULP ID      : " . $testAsset['ulp_id'] . "\n";
echo "Penyulang ID: " . $testAsset['penyulang_id'] . "\n";
echo "Latitude    : " . $testAsset['latitude'] . "\n";
echo "Longitude   : " . $testAsset['longitude'] . "\n";
echo "Deleted At  : " . var_export($testAsset['deleted_at'], true) . "\n\n";

// 2. PROGRESSIVE ISOLATION STEPS ON MASTER ASSET QUERY

$repo = new \App\Repositories\AssetRepository();

// STEP 0: NO FILTER
$b0 = $db->table('assets a');
$b0->where('a.deleted_at IS NULL');
$sql0 = $b0->getCompiledSelect();
$res0 = $b0->get()->getResultArray();
$count0 = count($res0);
$found0 = array_filter($res0, fn($r) => $r['id'] == $assetId);

echo "STEP 0 [No Filter]:\n";
echo "Count: " . $count0 . " rows | Test Asset Found: " . (!empty($found0) ? "YES" : "NO") . "\n\n";

// STEP 1: ULP FILTER ONLY (ulp_id = 1)
$filters1 = ['ulp_id' => 1];
$res1 = $repo->getFilteredAssetsPaginated($filters1, null, 1, 1000);
$found1 = array_filter($res1['data'], fn($r) => $r['id'] == $assetId);

echo "STEP 1 [ulp_id = 1]:\n";
echo "Count: " . $res1['total'] . " rows | Test Asset Found: " . (!empty($found1) ? "YES" : "NO") . "\n\n";

// STEP 2: ULP + PENYULANG (ulp_id = 1 & penyulang_id = 15)
$filters2 = ['ulp_id' => 1, 'penyulang_id' => 15];
$res2 = $repo->getFilteredAssetsPaginated($filters2, null, 1, 1000);
$found2 = array_filter($res2['data'], fn($r) => $r['id'] == $assetId);

echo "STEP 2 [ulp_id = 1 & penyulang_id = 15]:\n";
echo "Count: " . $res2['total'] . " rows | Test Asset Found: " . (!empty($found2) ? "YES" : "NO") . "\n\n";

// STEP 3: ULP + PENYULANG + JENIS ASSET (ulp_id = 1 & penyulang_id = 15 & jenis_asset = JTM)
$filters3 = ['ulp_id' => 1, 'penyulang_id' => 15, 'jenis_asset' => 'JTM'];
$res3 = $repo->getFilteredAssetsPaginated($filters3, null, 1, 1000);
$found3 = array_filter($res3['data'], fn($r) => $r['id'] == $assetId);

echo "STEP 3 [ulp_id = 1 & penyulang_id = 15 & jenis_asset = JTM]:\n";
echo "Count: " . $res3['total'] . " rows | Test Asset Found: " . (!empty($found3) ? "YES" : "NO") . "\n\n";

// STEP 4: ULP + PENYULANG + JENIS ASSET + STATUS KOSONG (status = '')
$filters4 = ['ulp_id' => 1, 'penyulang_id' => 15, 'jenis_asset' => 'JTM', 'status' => ''];
$res4 = $repo->getFilteredAssetsPaginated($filters4, null, 1, 1000);
$found4 = array_filter($res4['data'], fn($r) => $r['id'] == $assetId);

echo "STEP 4 [ulp_id = 1 & penyulang_id = 15 & jenis_asset = JTM & status = '']:\n";
echo "Count: " . $res4['total'] . " rows | Test Asset Found: " . (!empty($found4) ? "YES" : "NO") . "\n\n";

// STEP 5: ULP + PENYULANG + JENIS ASSET + STATUS KOSONG + SEARCH KOSONG (search = '')
$filters5 = ['ulp_id' => 1, 'penyulang_id' => 15, 'jenis_asset' => 'JTM', 'status' => '', 'search' => ''];
$res5 = $repo->getFilteredAssetsPaginated($filters5, null, 1, 1000);
$found5 = array_filter($res5['data'], fn($r) => $r['id'] == $assetId);

echo "STEP 5 [FULL URL PARAMETERS: ulp_id=1&penyulang_id=15&jenis_asset=JTM&status=&search=]:\n";
echo "Count: " . $res5['total'] . " rows | Test Asset Found: " . (!empty($found5) ? "YES" : "NO") . "\n\n";
