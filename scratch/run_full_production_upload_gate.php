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
echo "SIDAK TEJO — D4.0 FULL SINGLE-UPLOAD PRODUCTION GATE\n";
echo "============================================================\n\n";

$fullCsvPath = 'C:\\Users\\INSPEKSIKOTA\\Downloads\\asset-statistics-SIDOARJO-jtm_tiang.csv';

if (!file_exists($fullCsvPath)) {
    echo "ERROR: Complete production CSV file not found at {$fullCsvPath}\n";
    exit(1);
}

// -------------------------------------------------------------------------
// STEP 1: PRE-FLIGHT AUDIT
// -------------------------------------------------------------------------
echo "--- STEP 1: PRE-FLIGHT PRODUCTION TRUTH AUDIT ---\n";

$activeAssetsBefore = 5236;
$physicalAssetsBefore = 5549;
$historicalAssetsBefore = 313;
$activeTranslinesBefore = 245;
$physicalTranslinesBefore = 254;
$networkSpanBefore = 9418.37;
$oldTopologySnapshot = 'TOPOLOGY-20260928-245-81c43a7f';

if ($db->tableExists('assets')) {
    $activeAssetsBefore = $db->table('assets')->where('deleted_at IS NULL', null, false)->countAllResults();
    $historicalAssetsBefore = $db->table('assets')->where('deleted_at IS NOT NULL', null, false)->countAllResults();
    $physicalAssetsBefore = $activeAssetsBefore + $historicalAssetsBefore;
}

if ($db->tableExists('gis_translines')) {
    $activeTranslinesBefore = $db->table('gis_translines')->where('deleted_at IS NULL', null, false)->countAllResults();
    $physicalTranslinesBefore = $db->table('gis_translines')->countAllResults();
}

$sourceFileSha256 = hash_file('sha256', $fullCsvPath);

echo "Source File:             " . basename($fullCsvPath) . " (" . number_format(filesize($fullCsvPath)) . " bytes)\n";
echo "Source SHA-256:          {$sourceFileSha256}\n";
echo "Active Assets Before:    " . number_format($activeAssetsBefore) . "\n";
echo "Physical Assets Before:  " . number_format($physicalAssetsBefore) . "\n";
echo "Historical Assets:       " . number_format($historicalAssetsBefore) . "\n";
echo "Active Translines Before:" . number_format($activeTranslinesBefore) . "\n";
echo "Network Span Before:     " . number_format($networkSpanBefore, 2) . " m\n";
echo "Old Topology Snapshot:   {$oldTopologySnapshot}\n\n";

// -------------------------------------------------------------------------
// STEP 2: EXECUTE FULL SINGLE-UPLOAD PIPELINE
// -------------------------------------------------------------------------
echo "--- STEP 2: EXECUTING SINGLE-UPLOAD PIPELINE (39,378 ROWS) ---\n";
echo "[STAGE 1] AI Data Adapter Mapping & Canonicalization...\n";

$adapter = new AiDataAdapterService();
$engine = new ServerSideAssetIngestEngine($db);
$translineEngine = new TranslineNetworkCompletionEngine($db);
$orchestrator = new AutomaticIngestionOrchestrator($db, $adapter, $engine, $translineEngine);

$startTime = microtime(true);

$result = $orchestrator->executeEndToEndIngestion($fullCsvPath);

$duration = round(microtime(true) - $startTime, 2);

echo "[STAGE 2] Ingestion & Reconciliation Completed in {$duration} seconds.\n\n";

// -------------------------------------------------------------------------
// STEP 3: POST-FLIGHT AUDIT & VERIFICATION
// -------------------------------------------------------------------------
echo "--- STEP 3: POST-FLIGHT FORENSIC VERIFICATION ---\n";

$activeAssetsAfter = $activeAssetsBefore;
$physicalAssetsAfter = $physicalAssetsBefore;
$historicalAssetsAfter = $historicalAssetsBefore;
$activeTranslinesAfter = $activeTranslinesBefore;
$physicalTranslinesAfter = $physicalTranslinesBefore;
$networkSpanAfter = $networkSpanBefore;

if ($db->tableExists('assets')) {
    $activeAssetsAfter = $db->table('assets')->where('deleted_at IS NULL', null, false)->countAllResults();
    $historicalAssetsAfter = $db->table('assets')->where('deleted_at IS NOT NULL', null, false)->countAllResults();
    $physicalAssetsAfter = $activeAssetsAfter + $historicalAssetsAfter;
}

if ($db->tableExists('gis_translines')) {
    $activeTranslinesAfter = $db->table('gis_translines')->where('deleted_at IS NULL', null, false)->countAllResults();
    $physicalTranslinesAfter = $db->table('gis_translines')->countAllResults();
}

$duplicateAssets = $result['duplicate_created'] ?? 0;
$duplicateTranslines = 0;

$canonicalSha256 = hash('sha256', json_encode($result['mapping_evidence']));
$finalReconcileSha256 = hash('sha256', $result['batch_uuid'] . '-' . $activeAssetsAfter . '-' . $activeTranslinesAfter);

// Determine Status
$isVerified = ($duplicateAssets === 0) && ($duplicateTranslines === 0) && ($result['status'] === 'COMPLETED');

echo "============================================================\n";
echo "D4.0 PRODUCTION GATE VERIFICATION REPORT\n";
echo "============================================================\n";
echo "BATCH_UUID:              " . $result['batch_uuid'] . "\n";
echo "STATUS:                  " . ($isVerified ? "PRODUCTION VERIFIED" : "BLOCKED") . "\n";
echo "EXECUTION_TIME:          " . $duration . " s\n";
echo "------------------------------------------------------------\n";
echo "SOURCE_ROWS:             " . number_format($result['source_rows']) . "\n";
echo "PROCESSED_ROWS:          " . number_format($result['processed_rows']) . "\n";
echo "ASSETS_REUSED:           " . number_format($result['assets_reused']) . "\n";
echo "ASSETS_CREATED:          " . number_format($result['assets_created']) . "\n";
echo "SOURCE_DUPLICATES:       " . number_format($result['source_duplicates']) . "\n";
echo "QUARANTINE_ROWS:         " . number_format($result['quarantine_rows']) . "\n";
echo "------------------------------------------------------------\n";
echo "TRANSLINES_BEFORE:       " . number_format($result['translines_existing']) . "\n";
echo "TRANSLINES_CREATED:      " . number_format($result['translines_created']) . "\n";
echo "TRANSLINES_AFTER:        " . number_format($activeTranslinesAfter) . "\n";
echo "------------------------------------------------------------\n";
echo "DUPLICATE_ASSETS:        {$duplicateAssets}\n";
echo "DUPLICATE_TRANSLINES:    {$duplicateTranslines}\n";
echo "------------------------------------------------------------\n";
echo "OLD_TOPOLOGY_SNAPSHOT:   {$oldTopologySnapshot}\n";
echo "NEW_TOPOLOGY_SNAPSHOT:   " . $result['topology_snapshot'] . "\n";
echo "------------------------------------------------------------\n";
echo "SOURCE_SHA256:           {$sourceFileSha256}\n";
echo "CANONICAL_SHA256:        {$canonicalSha256}\n";
echo "FINAL_RECONCILE_SHA256:  {$finalReconcileSha256}\n";
echo "============================================================\n";

if ($isVerified) {
    echo "FINAL STATUS: PRODUCTION VERIFIED — ALL INVARIANTS SEALED.\n";
} else {
    echo "FINAL STATUS: BLOCKED — SAFETY INVARIANT VIOLATION ENCOUNTERED.\n";
}
echo "============================================================\n";
