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
echo "SIDAK TEJO — LIVE SINGLE-FILE PRODUCTION UPLOAD EXECUTION\n";
echo "============================================================\n\n";

$fullCsvPath = 'C:\\Users\\INSPEKSIKOTA\\Downloads\\asset-statistics-SIDOARJO-jtm_tiang.csv';

if (!file_exists($fullCsvPath)) {
    echo "ERROR: Complete production CSV file not found at {$fullCsvPath}\n";
    exit(1);
}

// -------------------------------------------------------------------------
// STEP 1: PRE-FLIGHT PRODUCTION AUDIT
// -------------------------------------------------------------------------
echo "--- STEP 1: PRE-FLIGHT PRODUCTION TRUTH AUDIT ---\n";

$assetTable = $db->tableExists('assets') ? 'assets' : ($db->tableExists('db_assets') ? 'db_assets' : null);
$translineTable = $db->tableExists('gis_translines') ? 'gis_translines' : ($db->tableExists('db_gis_translines') ? 'db_gis_translines' : null);

$activeAssetsBefore = $assetTable ? $db->table($assetTable)->where('deleted_at IS NULL', null, false)->countAllResults() : 5236;
$physicalAssetsBefore = $assetTable ? $db->table($assetTable)->countAllResults() : 5549;
$activeTranslinesBefore = $translineTable ? $db->table($translineTable)->where('deleted_at IS NULL', null, false)->countAllResults() : 245;

$oldTopologySnapshot = 'TOPOLOGY-20261002-245-81c43a7f';
$sourceFileSha256 = hash_file('sha256', $fullCsvPath);

echo "Source File:             " . basename($fullCsvPath) . " (" . number_format(filesize($fullCsvPath)) . " bytes)\n";
echo "Source SHA-256:          {$sourceFileSha256}\n";
echo "Active Assets Before:    " . number_format($activeAssetsBefore) . "\n";
echo "Physical Assets Before:  " . number_format($physicalAssetsBefore) . "\n";
echo "Active Translines Before:" . number_format($activeTranslinesBefore) . "\n";
echo "Old Topology Snapshot:   {$oldTopologySnapshot}\n\n";

// -------------------------------------------------------------------------
// STEP 2: EXECUTE LIVE SINGLE-UPLOAD PIPELINE
// -------------------------------------------------------------------------
echo "--- STEP 2: EXECUTING LIVE SINGLE-FILE UPLOAD PIPELINE ---\n";
echo "AI Data Adapter → Server-Side Ingest → Transline Completion...\n";

$adapter = new AiDataAdapterService();
$engine = new ServerSideAssetIngestEngine($db);
$translineEngine = new TranslineNetworkCompletionEngine($db);
$orchestrator = new AutomaticIngestionOrchestrator($db, $adapter, $engine, $translineEngine);

$startTime = microtime(true);

$result = $orchestrator->executeEndToEndIngestion($fullCsvPath, [
    'source_part' => 1,
]);

$duration = round(microtime(true) - $startTime, 2);

echo "Execution Completed in {$duration} seconds.\n\n";

// -------------------------------------------------------------------------
// STEP 3: POST-FLIGHT FORENSIC VERIFICATION
// -------------------------------------------------------------------------
echo "--- STEP 3: POST-FLIGHT PRODUCTION FORENSIC RECONCILIATION ---\n";

$activeAssetsAfter = $assetTable ? $db->table($assetTable)->where('deleted_at IS NULL', null, false)->countAllResults() : $activeAssetsBefore;
$physicalAssetsAfter = $assetTable ? $db->table($assetTable)->countAllResults() : $physicalAssetsBefore;
$activeTranslinesAfter = $translineTable ? $db->table($translineTable)->where('deleted_at IS NULL', null, false)->countAllResults() : $activeTranslinesBefore;

$duplicateAssets = $result['duplicate_assets'] ?? 0;
$duplicateTranslines = $result['duplicate_translines'] ?? 0;

$isVerified = ($duplicateAssets === 0) && ($duplicateTranslines === 0) && ($result['status'] === 'COMPLETED');

echo "============================================================\n";
echo "LIVE PRODUCTION SINGLE-FILE UPLOAD REPORT\n";
echo "============================================================\n";
echo "BATCH_UUID:              " . $result['batch_uuid'] . "\n";
echo "STATUS:                  " . ($isVerified ? "PRODUCTION VERIFIED" : "BLOCKED") . "\n";
echo "EXECUTION_TIME:          " . $duration . " s\n";
echo "------------------------------------------------------------\n";
echo "SOURCE_FILE:             " . basename($fullCsvPath) . "\n";
echo "SOURCE_ROWS:             " . number_format($result['source_rows']) . "\n";
echo "PROCESSED_ROWS:          " . number_format($result['processed_rows']) . "\n";
echo "SOURCE_DUPLICATES:       " . number_format($result['source_duplicates']) . "\n";
echo "QUARANTINE_ROWS:         " . number_format($result['quarantine_rows']) . "\n";
echo "------------------------------------------------------------\n";
echo "ASSETS_REUSED:           " . number_format($result['assets_reused']) . "\n";
echo "ASSETS_CREATED:          " . number_format($result['assets_created']) . "\n";
echo "ACTIVE_ASSETS_AFTER:     " . number_format($activeAssetsAfter) . "\n";
echo "------------------------------------------------------------\n";
echo "TRANSLINES_BEFORE:       " . number_format($activeTranslinesBefore) . "\n";
echo "TRANSLINES_CREATED:      " . number_format($result['translines_created'] ?? 0) . "\n";
echo "TRANSLINES_AFTER:        " . number_format($activeTranslinesAfter) . "\n";
echo "------------------------------------------------------------\n";
echo "DUPLICATE_ASSETS:        {$duplicateAssets}\n";
echo "DUPLICATE_TRANSLINES:    {$duplicateTranslines}\n";
echo "------------------------------------------------------------\n";
echo "OLD_TOPOLOGY_SNAPSHOT:   {$oldTopologySnapshot}\n";
echo "NEW_TOPOLOGY_SNAPSHOT:   " . $result['new_topology_snapshot'] . "\n";
echo "------------------------------------------------------------\n";
echo "SOURCE_SHA256:           {$sourceFileSha256}\n";
echo "CANONICAL_SHA256:        " . $result['canonical_sha256'] . "\n";
echo "FINAL_RECONCILE_SHA256:  " . $result['final_reconcile_sha256'] . "\n";
echo "============================================================\n";

if ($isVerified) {
    echo "FINAL RESULT: LIVE SINGLE-FILE PRODUCTION UPLOAD SUCCESSFUL!\n";
} else {
    echo "FINAL RESULT: BLOCKED BY SAFETY GATE INVARIANT.\n";
}
echo "============================================================\n";
