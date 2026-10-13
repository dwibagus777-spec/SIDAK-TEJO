<?php

/**
 * D4.1.1 — CRITICAL RECONCILIATION FORENSIC SCRIPT
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

use App\Services\AiDataAdapterService;
use App\Services\ServerSideAssetIngestEngine;
use App\Services\TranslineNetworkCompletionEngine;
use App\Services\AutomaticIngestionOrchestrator;
use Config\Database;

$db = Database::connect();

echo "========================================\n";
echo "D4.1.1 RECONCILIATION FORENSIC RUNNER\n";
echo "========================================\n\n";

// 1. Inspect Baseline State
$activeAssets = $db->table('assets')->where('deleted_at IS NULL', null, false)->countAllResults();
$physicalAssets = $db->table('assets')->countAllResults();
$activeTranslines = $db->table('gis_translines')->where('deleted_at IS NULL', null, false)->countAllResults();

echo "1. CURRENT DATABASE STATE:\n";
echo "   - Active Assets     : " . number_format($activeAssets) . "\n";
echo "   - Physical Assets   : " . number_format($physicalAssets) . "\n";
echo "   - Active Translines : " . number_format($activeTranslines) . "\n\n";

// 2. Inspect Recent Ingest Batches & Created Assets
if ($db->tableExists('asset_ingest_batches')) {
    $batches = $db->table('asset_ingest_batches')->orderBy('id', 'DESC')->get(5)->getResultArray();
    echo "2. RECENT INGEST BATCHES:\n";
    foreach ($batches as $b) {
        echo "   - Batch UUID: {$b['batch_uuid']} | File: {$b['source_file']} | Status: {$b['status']} | Created: {$b['inserted']} | Reused: {$b['matched_existing']} | Processed: {$b['processed_at']}\n";
    }
    echo "\n";
}

// 3. Inspect Sample Assets (Check Canonical Kode Asset & No Row-Index)
echo "3. AUDIT OF CREATED ASSETS (CHECK CANONICAL CODES & NO ROW INDEX):\n";
$sampleCreated = $db->table('assets')
    ->select('id, kode_asset, nama_asset, jenis_asset, latitude, longitude, created_at')
    ->orderBy('id', 'DESC')
    ->get(10)
    ->getResultArray();

$rowIndexViolations = 0;
$canonicalValidCount = 0;

foreach ($sampleCreated as $row) {
    $code = $row['kode_asset'];
    if (preg_match('/AST-[A-Z0-9]+-\d+$/i', $code) && !preg_match('/AST-[A-Z0-9]+-[A-Z]+/', $code)) {
        $rowIndexViolations++;
    } else {
        $canonicalValidCount++;
    }
    echo "   - ID: {$row['id']} | Kode: {$code} | Nama: {$row['nama_asset']} | Pos: ({$row['latitude']}, {$row['longitude']})\n";
}
echo "\n";

// 4. Test Replay of 2,000 Rows File (Idempotency Audit)
$part1File = ROOTPATH . 'writable/Template_Import_MULTI_PENYULANG_PART1.csv';
if (!file_exists($part1File)) {
    echo "⚠️ PART 1 CSV file not found at {$part1File}\n";
} else {
    echo "4. TESTING REPLAY IDEMPOTENCY ON 2,000 ROW PART FILE:\n";
    $adapter = new AiDataAdapterService();
    $engine = new ServerSideAssetIngestEngine($db);
    $orchestrator = new AutomaticIngestionOrchestrator($db, $adapter, $engine);

    $replayReport = $orchestrator->executeEndToEndIngestion($part1File, [
        'source_part' => 1,
        'correlation_id' => 'REPLAY-TEST-' . date('YmdHis'),
    ]);

    echo "   - Replay Batch UUID     : {$replayReport['batch_uuid']}\n";
    echo "   - Replay Source Rows    : {$replayReport['source_rows']}\n";
    echo "   - Replay Source Dupes   : {$replayReport['source_duplicates']}\n";
    echo "   - Replay Assets Created : {$replayReport['assets_created']}\n";
    echo "   - Replay Assets Reused  : {$replayReport['assets_reused']}\n";
    echo "   - Replay Duplicate Asset: {$replayReport['duplicate_assets']}\n";
    echo "   - Replay Translines New : {$replayReport['translines_created']}\n";
    echo "   - Replay Execution Time : {$replayReport['execution_time']} s\n\n";
}

// 5. Audit Network Completion Transline Candidates
echo "5. TRANSLINE NETWORK COMPLETION CANDIDATES AUDIT:\n";
$transEngine = new TranslineNetworkCompletionEngine($db);
$candidates = $transEngine->evaluateCandidateEdges();
echo "   - Evaluated Edge Candidates Count : " . count($candidates) . "\n";

$autoSafe = 0;
$reviewReq = 0;
foreach ($candidates as $c) {
    if (($c['status'] ?? '') === 'AUTO_SAFE') $autoSafe++;
    else $reviewReq++;
}
echo "   - AUTO_SAFE Candidates           : {$autoSafe}\n";
echo "   - NEEDS_HUMAN_REVIEW Candidates  : {$reviewReq}\n\n";

echo "FORENSIC COMPLETE.\n";
