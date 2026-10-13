<?php

$_SERVER['CI_ENVIRONMENT'] = 'development';
putenv('CI_ENVIRONMENT=development');
require __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';

$db = \Config\Database::connect('default');

echo "=== TABLES IN DATABASE ===\n";
$tables = $db->listTables();
foreach ($tables as $t) {
    if (strpos($t, 'fault_') !== false || strpos($t, 'dispatch') !== false || strpos($t, 'field') !== false) {
        echo " - {$t}\n";
    }
}

if ($db->tableExists('fault_cases')) {
    echo "\n=== COLUMNS IN fault_cases ===\n";
    $fields = $db->getFieldData('fault_cases');
    foreach ($fields as $f) {
        echo " - {$f->name} ({$f->type})\n";
    }
}

if ($db->tableExists('fault_actual_findings')) {
    echo "\n=== COLUMNS IN fault_actual_findings ===\n";
    $fields = $db->getFieldData('fault_actual_findings');
    foreach ($fields as $f) {
        echo " - {$f->name} ({$f->type})\n";
    }
}

echo "\n=== ROW COUNTS ===\n";
foreach (['fault_events', 'fault_cases', 'fault_candidate_assets', 'fault_actual_findings', 'gis_translines', 'assets'] as $tbl) {
    if ($db->tableExists($tbl)) {
        echo " - {$tbl}: " . $db->table($tbl)->countAllResults() . "\n";
    }
}

echo "\n=== FAULT CASES DATA ===\n";
print_r($db->table('fault_cases')->get()->getResultArray());


