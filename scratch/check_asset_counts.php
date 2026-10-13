<?php

$_SERVER['CI_ENVIRONMENT'] = 'development';
putenv('CI_ENVIRONMENT=development');
require __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';

use Config\Database;

echo "========================================\n";
echo "SIDAK TEJO DATABASE INGESTION AUDIT\n";
echo "========================================\n\n";

try {
    $db = Database::connect('default');
    echo "Connected to database group: default (" . $db->getPlatform() . ")\n\n";

    if ($db->tableExists('asset_ingest_batches')) {
        $batches = $db->table('asset_ingest_batches')->get()->getResultArray();
        echo "1. BATCHES INGESTED (asset_ingest_batches):\n";
        echo "   Total Batches: " . count($batches) . "\n";
        foreach ($batches as $row) {
            echo "   - UUID: " . $row['batch_uuid'] . "\n";
            echo "     File: " . $row['source_file'] . "\n";
            echo "     Source Rows: " . number_format($row['source_rows']) . "\n";
            echo "     Processed Rows: " . number_format($row['processed_rows']) . "\n";
            echo "     Source Duplicates: " . number_format($row['source_duplicates']) . "\n";
            echo "     Quarantine Rows: " . number_format($row['quarantine_rows']) . "\n";
            echo "     Assets Created: " . number_format($row['assets_created']) . "\n";
            echo "     Assets Reused: " . number_format($row['assets_reused']) . "\n";
            echo "     Translines Created: " . number_format($row['translines_created']) . "\n";
            echo "     Status: " . $row['status'] . "\n";
            echo "     Created At: " . $row['created_at'] . "\n\n";
        }
    } else {
        echo "1. asset_ingest_batches table does not exist on default database.\n\n";
    }

    if ($db->tableExists('asset_ingest_rows')) {
        $cntRows = $db->table('asset_ingest_rows')->countAllResults();
        echo "2. JOURNAL INGESTED ROWS (asset_ingest_rows):\n";
        echo "   Total Rows Recorded in Journal: " . number_format($cntRows) . " baris CSV\n\n";
    }

    $assetTable = $db->tableExists('assets') ? 'assets' : ($db->tableExists('db_assets') ? 'db_assets' : null);
    if ($assetTable) {
        $cntActive = $db->table($assetTable)->where('status', 'ACTIVE')->countAllResults();
        $cntTotal = $db->table($assetTable)->countAllResults();
        $cntDeleted = $db->table($assetTable)->where('status', 'DELETED')->countAllResults();

        echo "3. MASTER ASSETS TABLE ({$assetTable}):\n";
        echo "   Active Assets (100% Coverage) : " . number_format($cntActive) . " aset\n";
        echo "   Physical Total Rows           : " . number_format($cntTotal) . " aset\n";
        echo "   Historical Deleted Assets     : " . number_format($cntDeleted) . " aset\n\n";
    }

    $translineTable = $db->tableExists('gis_translines') ? 'gis_translines' : ($db->tableExists('db_gis_translines') ? 'db_gis_translines' : null);
    if ($translineTable) {
        $cntActiveTl = $db->table($translineTable)->where('status', 'ACTIVE')->countAllResults();
        $cntTotalTl = $db->table($translineTable)->countAllResults();

        echo "4. MASTER GIS TRANSLINES ({$translineTable}):\n";
        echo "   Active Authoritative Translines : " . number_format($cntActiveTl) . " jalur\n";
        echo "   Total Physical Transline Rows   : " . number_format($cntTotalTl) . " jalur\n\n";
    }

} catch (\Throwable $e) {
    echo "Database connection error on 'default': " . $e->getMessage() . "\n";
}

echo "========================================\n";
