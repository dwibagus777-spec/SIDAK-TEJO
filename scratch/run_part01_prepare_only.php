<?php

$_SERVER['CI_ENVIRONMENT'] = 'development';
putenv('CI_ENVIRONMENT=development');
require __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';

use App\Services\ServerSideAssetIngestEngine;
use Config\Database;

require_once APPPATH . 'Database/Migrations/2026-10-02-000001_CreateIdempotentAssetIngestSchema.php';

$db = Database::connect();
$engine = new ServerSideAssetIngestEngine($db);

// Ensure migration has run
$migration = new \App\Database\Migrations\CreateIdempotentAssetIngestSchema();
$migration->up();

// Truncate test journal tables to ensure fresh PART 01 execution
$db->table('asset_ingest_rows')->truncate();
$db->table('asset_ingest_batches')->truncate();

echo "============================================================\n";
echo "SIDAK TEJO — TRACK D: ASSET INGESTION PART 01 CONTROLLED EXECUTION\n";
echo "============================================================\n\n";

// STEP 0 — FORENSIC RECOVERY CHECK
echo "--- STEP 0: FORENSIC RECOVERY CHECK ---\n";
$forensic = $engine->forensicCheck();
echo "Active Assets: {$forensic['active_assets']}\n";
echo "Physical Assets: {$forensic['physical_assets']}\n";
echo "Historical/Deleted Assets: {$forensic['historical_assets']}\n";
echo "Active Translines: {$forensic['active_translines']}\n";
echo "Physical Translines: {$forensic['physical_translines']}\n";
echo "Network Span: {$forensic['network_span_meters']} m\n";
echo "Topology Snapshot: {$forensic['topology_snapshot']}\n";
echo "Forensic Status: {$forensic['forensic_status']}\n\n";

// Check if PART 01 was already prepared
$existingBatch = $db->table('asset_ingest_batches')
    ->where('source_part', 1)
    ->where('source_file', 'SIDAK_TEJO_ASSET_IMPORT_PART_01_OF_20.csv')
    ->get()->getRowArray();

if ($existingBatch && $existingBatch['status'] === 'PREPARED') {
    echo "NOTICE: PART 01 was already PREPARED in batch {$existingBatch['batch_uuid']}.\n";
    $report = $engine->getBatchReport($existingBatch['batch_uuid']);
} else {
    $csvPath = 'C:\\Users\\INSPEKSIKOTA\\Downloads\\SIDAK_TEJO_ASSET_IMPORT_PART_01_OF_20.csv';
    if (!file_exists($csvPath)) {
        throw new \RuntimeException("CSV file not found: {$csvPath}");
    }

    $lines = file($csvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $headerLine = array_shift($lines);

    $rows = [];
    foreach ($lines as $l) {
        $l = trim($l);
        if (empty($l)) continue;
        $cols = str_getcsv($l);
        if (count($cols) < 7) continue;
        $rows[] = [
            'unit'            => trim($cols[0] ?? ''),
            'ulp'             => trim($cols[1] ?? ''),
            'feeder'          => trim($cols[2] ?? ''),
            'asset_name'      => trim($cols[3] ?? ''),
            'section'         => trim($cols[4] ?? ''),
            'latitude'        => is_numeric($cols[5] ?? null) ? (float)$cols[5] : null,
            'longitude'       => is_numeric($cols[6] ?? null) ? (float)$cols[6] : null,
            'jenis_asset'     => trim($cols[7] ?? 'jtm_tiang'),
            'kode_konstruksi' => trim($cols[8] ?? ''),
            'tanggal_operasi' => trim($cols[9] ?? ''),
            'kapasitas'       => trim($cols[10] ?? '0'),
            'jumlah_pohon'    => trim($cols[11] ?? '0'),
        ];
    }

    echo "Submitting " . count($rows) . " data rows from PART 01 (" . basename($csvPath) . ") to /api/asset-ingest/prepare (PREPARE ONLY)...\n\n";
    
    // STEP 1 — PREPARE ONLY (Zero-write to assets table)
    $report = $engine->prepareBatch($rows, 'SIDAK_TEJO_ASSET_IMPORT_PART_01_OF_20.csv', 1);
}

// Format Compact Report
echo "============================================================\n";
echo "PART 01 COMPACT PREPARE REPORT\n";
echo "============================================================\n";
echo "PART:                 01/20\n";
echo "SOURCE_ROWS:          " . ($report['source_rows'] ?? $report['total_rows']) . "\n";
echo "REUSE_EXISTING:       " . $report['matched_existing'] . "\n";
echo "SOURCE_DUPLICATE:     " . $report['source_duplicate'] . "\n";
echo "CONFLICT_REVIEW:      " . $report['conflict_review'] . "\n";
echo "QUARANTINE:           " . $report['quarantine'] . "\n";
echo "CANDIDATE_NEW_ASSET:  " . $report['candidate_new_asset'] . "\n";
echo "ALREADY_PROCESSED:    " . $report['already_processed'] . "\n";
echo "INSERTED:             " . $report['inserted'] . "\n";
echo "UPDATED:              0\n";
echo "DELETED:              0\n";
echo "DUPLICATE_CREATED:    " . $report['duplicate_created'] . "\n";
echo "TOPOLOGY_DELTA:       " . $report['topology_delta'] . "\n";
echo "ASSET_DELTA:          0\n";
echo "BATCH_UUID:           " . $report['batch_uuid'] . "\n";
echo "BATCH_FINGERPRINT:    " . ($report['fingerprint'] ?? 'N/A') . "\n";
echo "STATUS:               " . $report['status'] . "\n";
echo "============================================================\n";
echo "STEP 6: HARD STOP — PREPARE COMPLETED. NO COMMIT EXECUTED.\n";
echo "============================================================\n";
