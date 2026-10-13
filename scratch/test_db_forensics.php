<?php

require __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';
$db = \Config\Database::connect('default');

echo "=== LOCAL DATABASE FORENSICS ===\n";

// 1. gis_translines total vs filtered
$tlTotal = $db->table('gis_translines')->countAllResults();
echo "Total gis_translines in table: {$tlTotal}\n";
echo "gis_translines columns:\n";
print_r(array_column($db->query('SHOW COLUMNS FROM gis_translines')->getResultArray(), 'Field'));

$tlBreakdown = $db->query("
    SELECT 
        is_active, 
        CASE WHEN deleted_at IS NULL THEN 'NULL' ELSE 'NOT_NULL' END AS del_status,
        COUNT(*) AS cnt
    FROM gis_translines
    GROUP BY is_active, del_status
")->getResultArray();
echo "gis_translines breakdown:\n";
print_r($tlBreakdown);

// 2. assets total vs filtered
$assetTotal = $db->table('assets')->countAllResults();
echo "\nTotal assets in table: {$assetTotal}\n";

$assetCols = array_column($db->query("SHOW COLUMNS FROM assets")->getResultArray(), 'Field');
echo "Asset columns of interest:\n";
$filterCols = array_intersect(['is_active', 'deleted_at', 'status', 'kondisi'], $assetCols);
print_r($filterCols);

if (in_array('is_active', $assetCols, true)) {
    $assetBreakdown = $db->query("
        SELECT 
            is_active,
            CASE WHEN deleted_at IS NULL THEN 'NULL' ELSE 'NOT_NULL' END AS del_status,
            COUNT(*) AS cnt
        FROM assets
        GROUP BY is_active, del_status
    ")->getResultArray();
    print_r($assetBreakdown);
}

// 3. fault_events
$feTotal = $db->table('fault_events')->countAllResults();
echo "\nTotal fault_events in local table: {$feTotal}\n";
$feRows = $db->table('fault_events')->select('id, event_number, source_type, event_fingerprint, fingerprint_status, created_at')->get()->getResultArray();
print_r($feRows);
