<?php

/**
 * D4.1.3 — FULL HISTORICAL INGEST RECONCILIATION AUDIT SCRIPT
 */

define('FCPATH', realpath(__DIR__ . '/../public') . DIRECTORY_SEPARATOR);
chdir(FCPATH);
define('ENVIRONMENT', 'development');

require FCPATH . '../app/Config/Paths.php';
$paths = new Config\Paths();

define('APPPATH', realpath($paths->appDirectory) . DIRECTORY_SEPARATOR);
define('ROOTPATH', realpath($paths->appDirectory . '/../') . DIRECTORY_SEPARATOR);
define('SYSTEMPATH', realpath($paths->systemDirectory) . DIRECTORY_SEPARATOR);
define('WRITEPATH', realpath($paths->writableDirectory) . DIRECTORY_SEPARATOR);

require APPPATH . 'Config/Constants.php';
require SYSTEMPATH . 'Common.php';

require $paths->systemDirectory . '/Boot.php';
CodeIgniter\Boot::bootTest($paths);

use Config\Database;

$db = Database::connect();

echo "========================================\n";
echo "D4.1.3 FULL HISTORICAL INGEST RECONCILIATION\n";
echo "========================================\n\n";

// 1. Audit Table Presence
$hasBatches = $db->tableExists('asset_ingest_batches');
$hasRows    = $db->tableExists('asset_ingest_rows');
$hasAssets  = $db->tableExists('assets');

echo "1. DATABASE TABLE PRESENCE:\n";
echo "   - asset_ingest_batches : " . ($hasBatches ? 'YES' : 'NO') . "\n";
echo "   - asset_ingest_rows    : " . ($hasRows ? 'YES' : 'NO') . "\n";
echo "   - assets               : " . ($hasAssets ? 'YES' : 'NO') . "\n\n";

// 2. Audit Batch Summary Grouped by Status
if ($hasBatches) {
    $totalBatches = $db->table('asset_ingest_batches')->countAllResults();
    echo "2. TOTAL BATCHES IN JOURNAL: " . number_format($totalBatches) . "\n";

    $statusGroup = $db->table('asset_ingest_batches')
        ->select('status, COUNT(*) as count, SUM(total_rows) as total_rows, SUM(inserted) as total_created, SUM(matched_existing) as total_reused')
        ->groupBy('status')
        ->get()
        ->getResultArray();

    echo "   BATCH STATUS BREAKDOWN:\n";
    foreach ($statusGroup as $sg) {
        echo sprintf("   - Status: %-25s | Count: %-6s | Rows: %-8s | Created: %-6s | Reused: %-6s\n",
            $sg['status'],
            number_format($sg['count']),
            number_format($sg['total_rows'] ?? 0),
            number_format($sg['total_created'] ?? 0),
            number_format($sg['total_reused'] ?? 0)
        );
    }
    echo "\n";
}

// 3. Audit Production Assets Count & Provenance
if ($hasAssets) {
    $activeAssets = $db->table('assets')->where('deleted_at IS NULL', null, false)->countAllResults();
    $physicalAssets = $db->table('assets')->countAllResults();

    echo "3. PRODUCTION ASSETS TRUTH:\n";
    echo "   - Active Assets   : " . number_format($activeAssets) . "\n";
    echo "   - Physical Assets : " . number_format($physicalAssets) . "\n";
    echo "   - Baseline Target : 5,236 Active | 5,549 Physical\n";
    echo "   - Real Asset Delta: +" . number_format($activeAssets - 5236) . " Active | +" . number_format($physicalAssets - 5549) . " Physical\n\n";
}

// 4. Inspect Ingest Rows Breakdown if Table Exists
if ($hasRows) {
    $totalRows = $db->table('asset_ingest_rows')->countAllResults();
    echo "4. ASSET INGEST ROWS JOURNAL SUMMARY:\n";
    echo "   - Total Journal Rows: " . number_format($totalRows) . "\n";

    $rowStatus = $db->table('asset_ingest_rows')
        ->select('processing_status, COUNT(*) as count')
        ->groupBy('processing_status')
        ->get()
        ->getResultArray();

    echo "   ROW STATUS BREAKDOWN:\n";
    foreach ($rowStatus as $rs) {
        echo sprintf("   - Status: %-25s | Count: %s\n", $rs['processing_status'], number_format($rs['count']));
    }
    echo "\n";
}

// 5. Unaccounted Asset Delta Audit
$sumCommittedCreated = 0;
if ($hasBatches) {
    $sumRes = $db->table('asset_ingest_batches')
        ->selectSum('inserted')
        ->whereIn('status', ['COMMITTED', 'COMPLETED'])
        ->get()
        ->getRowArray();
    $sumCommittedCreated = (int)($sumRes['inserted'] ?? 0);
}

$realDelta = $activeAssets - 5236;
$unaccountedDelta = $realDelta - $sumCommittedCreated;

echo "5. FINAL RECONCILIATION SUMMARY:\n";
echo "   - Baseline Active Assets  : 5,236\n";
echo "   - Current Active Assets   : " . number_format($activeAssets) . "\n";
echo "   - Real Asset Delta        : +" . number_format($realDelta) . "\n";
echo "   - Sum Committed Ingest    : +" . number_format($sumCommittedCreated) . "\n";
echo "   - Unaccounted Asset Delta : " . number_format($unaccountedDelta) . "\n\n";

echo "RECONCILIATION FORENSIC COMPLETE.\n";
