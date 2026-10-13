<?php

require __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';

$db = \Config\Database::connect('default');

echo "=== PREFLIGHT INSPECTION: FAULT_EVENTS (MySQL default) ===\n";
echo "Database: " . $db->getDatabase() . "\n";
echo "Table exists: " . ($db->tableExists('fault_events') ? "YES" : "NO") . "\n";

if ($db->tableExists('fault_events')) {
    $fields = $db->getFieldData('fault_events');
    echo "\nFields in fault_events:\n";
    foreach ($fields as $f) {
        echo " - {$f->name} ({$f->type}, max_length: {$f->max_length}, default: " . var_export($f->default, true) . ", nullable: " . ($f->nullable ? "YES" : "NO") . ")\n";
    }

    $count = $db->table('fault_events')->countAllResults();
    echo "\nTotal existing rows in fault_events: {$count}\n";

    if ($count > 0) {
        $rows = $db->table('fault_events')->limit(5)->get()->getResultArray();
        echo "Sample existing rows:\n";
        print_r($rows);
    }
}

echo "\nAuthoritative topology invariant check:\n";
$translineCount = $db->table('gis_translines')->where('is_active', 1)->countAllResults();
$assetCount = $db->table('master_assets')->where('is_active', 1)->countAllResults();
echo "Active translines: {$translineCount}\n";
echo "Active assets: {$assetCount}\n";
