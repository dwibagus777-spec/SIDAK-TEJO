<?php

require __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';

$db = \Config\Database::connect('default');

$tables = [
    'gis_translines',
    'gis_transline_proposals',
    'assets',
    'sections',
    'penyulang',
    'temuan',
    'temuan_materials',
];

echo "=== BASELINE DATABASE COUNTS ===" . PHP_EOL;
foreach ($tables as $t) {
    $cnt = $db->tableExists($t) ? $db->table($t)->countAllResults() : 'TABLE_NOT_FOUND';
    echo "{$t}: {$cnt}" . PHP_EOL;
}

if ($db->tableExists('assets')) {
    $secCount = $db->table('assets')->where('section_id IS NOT NULL')->countAllResults();
    $conCount = $db->table('assets')->where('construction_type_id IS NOT NULL')->countAllResults();
    echo "assets with section_id: {$secCount}" . PHP_EOL;
    echo "assets with construction_type_id: {$conCount}" . PHP_EOL;
}

if ($db->tableExists('gis_transline_proposals')) {
    $props = $db->table('gis_transline_proposals')->get()->getResultArray();
    echo "--- PROPOSALS DETAIL ---" . PHP_EOL;
    foreach ($props as $p) {
        echo "Proposal #{$p['id']} - Status: {$p['status']}, Classification: {$p['classification']}, Feeder: {$p['penyulang_id']}, Source: {$p['source_asset_id']}, Target: {$p['target_asset_id']}, ConfirmedTL: " . ($p['confirmed_transline_id'] ?? 'NULL') . PHP_EOL;
    }
}

if ($db->tableExists('gis_translines')) {
    $tls = $db->table('gis_translines')->get()->getResultArray();
    echo "--- TRANSLINES COUNT: " . count($tls) . " ---" . PHP_EOL;
    if (!empty($tls)) {
        echo json_encode($tls[0], JSON_PRETTY_PRINT) . PHP_EOL;
    }
}
