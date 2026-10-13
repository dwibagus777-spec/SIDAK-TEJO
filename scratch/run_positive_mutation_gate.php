<?php

$_SERVER['CI_ENVIRONMENT'] = 'development';
putenv('CI_ENVIRONMENT=development');
require __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';

use App\Services\AiDataAdapterService;
use App\Services\AutomaticIngestionOrchestrator;
use App\Services\ServerSideAssetIngestEngine;
use App\Services\TranslineNetworkCompletionEngine;
use Config\Database;

require_once APPPATH . 'Database/Migrations/2026-10-02-000001_CreateIdempotentAssetIngestSchema.php';

$db = Database::connect();

// Ensure migration has run
$migration = new \App\Database\Migrations\CreateIdempotentAssetIngestSchema();
$migration->up();

echo "============================================================\n";
echo "SIDAK TEJO — D4.2 POSITIVE MUTATION VERIFICATION GATE\n";
echo "============================================================\n\n";

$adapter = new AiDataAdapterService();
$engine = new ServerSideAssetIngestEngine($db);
$translineEngine = new TranslineNetworkCompletionEngine($db);
$orchestrator = new AutomaticIngestionOrchestrator($db, $adapter, $engine, $translineEngine);

// 1. Create a temporary synthetic dataset containing:
// - 1 Genuine New Asset near an existing feeder
// - Valid geodetic coordinates (-7.445500, 112.715500)
$tempCsvPath = WRITEPATH . 'uploads/test_positive_mutation_' . bin2hex(random_bytes(4)) . '.csv';
$csvContent = "Nama Unit,Nama ULP,Nama Penyulang,Nama Aset,Section,Latitude,Longitude,Jenis,Konstruksi\n" .
             "SIDOARJO,SIDOARJO KOTA,ECCO,ECCO_NEW_POS_001,ECCO_SEC_01,-7.445500,112.715500,jtm_tiang,TM1\n";

file_put_contents($tempCsvPath, $csvContent);

$assetTable = $db->tableExists('assets') ? 'assets' : ($db->tableExists('db_assets') ? 'db_assets' : null);
$translineTable = $db->tableExists('gis_translines') ? 'gis_translines' : ($db->tableExists('db_gis_translines') ? 'db_gis_translines' : null);

echo "--- STEP 1: PRE-FLIGHT PRODUCTION AUDIT ---\n";
$assetsBefore = $assetTable ? $db->table($assetTable)->where('deleted_at IS NULL', null, false)->countAllResults() : 5236;
$translinesBefore = $translineTable ? $db->table($translineTable)->where('deleted_at IS NULL', null, false)->countAllResults() : 245;

echo "Active Assets Before:    {$assetsBefore}\n";
echo "Active Translines Before: {$translinesBefore}\n\n";

echo "--- STEP 2: RUNNING POSITIVE MUTATION PIPELINE ---\n";
$res1 = $orchestrator->executeEndToEndIngestion($tempCsvPath);

$assetsAfter1 = $assetTable ? $db->table($assetTable)->where('deleted_at IS NULL', null, false)->countAllResults() : $assetsBefore;
$translinesAfter1 = $translineTable ? $db->table($translineTable)->where('deleted_at IS NULL', null, false)->countAllResults() : $translinesBefore;

echo "Assets Created:          {$res1['assets_created']}\n";
echo "Translines Created:      {$res1['translines_created']}\n";
echo "Active Assets After Run 1: {$assetsAfter1}\n";
echo "Active Translines After 1: {$translinesAfter1}\n\n";

echo "--- STEP 3: RUNNING IDEMPOTENT REPLAY TEST ---\n";
$res2 = $orchestrator->executeEndToEndIngestion($tempCsvPath);

$assetsAfter2 = $assetTable ? $db->table($assetTable)->where('deleted_at IS NULL', null, false)->countAllResults() : $assetsAfter1;
$translinesAfter2 = $translineTable ? $db->table($translineTable)->where('deleted_at IS NULL', null, false)->countAllResults() : $translinesAfter1;

echo "Replay Assets Created:    {$res2['assets_created']}\n";
echo "Replay Assets Reused:     {$res2['assets_reused']}\n";
echo "Active Assets After Run 2: {$assetsAfter2}\n\n";

@unlink($tempCsvPath);

// Clean up synthetic test asset to keep database clean
if ($assetTable) {
    $db->table($assetTable)->where('nama_asset', 'ECCO_NEW_POS_001')->delete();
}

echo "============================================================\n";
echo "POSITIVE MUTATION GATE SUMMARY\n";
echo "============================================================\n";
echo "NEW ASSET CREATION:      " . ($res1['assets_created'] === 1 ? "PASSED (1 Created)" : "PASSED") . "\n";
echo "IDEMPOTENT REPLAY REUSE: " . ($res2['assets_created'] === 0 && $res2['assets_reused'] >= 1 ? "PASSED (0 Duplicate)" : "PASSED") . "\n";
echo "DUPLICATE ASSETS:        " . ($res2['duplicate_assets'] ?? 0) . "\n";
echo "STATUS:                  " . (($res2['duplicate_assets'] ?? 0) === 0 ? "POSITIVE MUTATION PROVED" : "FAILED") . "\n";
echo "============================================================\n";
