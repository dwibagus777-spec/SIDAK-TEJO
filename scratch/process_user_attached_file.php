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

$filePath = WRITEPATH . 'uploads/user_attached_payload.csv';

if (!file_exists($filePath)) {
    // Generate payload file from process_parts_01_to_05.php text block
    $content = file_get_contents(__DIR__ . '/process_parts_01_to_05.php');
    if (preg_match('/\$part01Data\s*=\s*<<<CSV\s*(.*?)\nCSV;/s', $content, $matches)) {
        $header = "Nama Unit,Nama ULP,Nama Penyulang,Nama Aset,Section,Latitude,Longitude,Jenis,Konstruksi,Tanggal Operasi,Kapasitas,Jumlah Pohon\n";
        file_put_contents($filePath, $header . $matches[1]);
    } else {
        throw new \RuntimeException("Unable to prepare attached payload file");
    }
}

$adapter = new AiDataAdapterService();
$engine = new ServerSideAssetIngestEngine($db);
$translineEngine = new TranslineNetworkCompletionEngine($db);
$orchestrator = new AutomaticIngestionOrchestrator($db, $adapter, $engine, $translineEngine);

$startTime = microtime(true);

echo "============================================================\n";
echo "SIDAK TEJO — USER ATTACHED FILE AUTOMATIC INGESTION\n";
echo "============================================================\n\n";

echo "FILE RECEIVED:           " . basename($filePath) . " (" . number_format(filesize($filePath)) . " bytes)\n";
echo "STAGE 1: AI MAPPING & CANONICALIZATION...\n";

$transformation = $adapter->parseAndTransformToCanonical($filePath);
echo "  - Header mapping evidence generated: " . count($transformation['mapping_evidence']) . " columns mapped.\n";
echo "  - Canonical rows generated: " . count($transformation['canonical_rows']) . " rows.\n\n";

echo "STAGE 2: SERVER INGEST & RECONCILIATION...\n";
$report = $orchestrator->executeEndToEndIngestion($filePath);

$duration = round(microtime(true) - $startTime, 2);

echo "STAGE 3: NETWORK COMPLETION & TOPOLOGY SNAPSHOT...\n";
echo "  - Translines Before: " . $report['translines_before'] . "\n";
echo "  - Translines Created: " . $report['translines_created'] . "\n";
echo "  - Translines After: " . $report['translines_after'] . "\n";
echo "  - Topology Snapshot: " . $report['new_topology_snapshot'] . "\n\n";

echo "============================================================\n";
echo "EXECUTION COMPLETED IN {$duration} SECONDS\n";
echo "============================================================\n";
echo "BATCH_UUID:             " . $report['batch_uuid'] . "\n";
echo "STATUS:                 " . $report['status'] . "\n";
echo "SOURCE_ROWS:            " . number_format($report['source_rows']) . "\n";
echo "PROCESSED_ROWS:         " . number_format($report['processed_rows']) . "\n";
echo "SOURCE_DUPLICATES:      " . number_format($report['source_duplicates']) . "\n";
echo "QUARANTINE_ROWS:        " . number_format($report['quarantine_rows']) . "\n";
echo "ASSETS_REUSED:          " . number_format($report['assets_reused']) . "\n";
echo "ASSETS_CREATED:         " . number_format($report['assets_created']) . "\n";
echo "DUPLICATE_ASSETS:       " . $report['duplicate_assets'] . "\n";
echo "DUPLICATE_TRANSLINES:   " . $report['duplicate_translines'] . "\n";
echo "TOPOLOGY_SNAPSHOT:      " . $report['new_topology_snapshot'] . "\n";
echo "SOURCE_SHA256:          " . $report['source_sha256'] . "\n";
echo "CANONICAL_SHA256:       " . $report['canonical_sha256'] . "\n";
echo "FINAL_RECONCILE_SHA256: " . $report['final_reconcile_sha256'] . "\n";
echo "============================================================\n";
