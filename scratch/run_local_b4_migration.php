<?php

require __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';

$migrationFile = __DIR__ . '/../app/Database/Migrations/2026-09-25-000001_CreateFaultIntelligenceTables.php';
require_once $migrationFile;

$mig = new \App\Database\Migrations\CreateFaultIntelligenceTables();
$mig->up();

$db = \Config\Database::connect('default');
$tables = ['fault_cause_categories', 'fault_events', 'fault_cases', 'fault_candidate_assets', 'fault_actual_findings'];
foreach ($tables as $t) {
    echo "Table {$t}: " . ($db->tableExists($t) ? "EXISTS (rows: " . $db->table($t)->countAllResults() . ")" : "MISSING") . "\n";
}
