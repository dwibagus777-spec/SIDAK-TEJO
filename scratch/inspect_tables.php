<?php
require __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';
$db = Config\Database::connect('default');
echo "Database: " . $db->getDatabase() . " Platform: " . $db->getPlatform() . "\n";
$tables = $db->listTables();
echo "Tables count: " . count($tables) . "\n";
print_r(array_filter($tables, fn($t) => str_contains($t, 'fault') || str_contains($t, 'field') || str_contains($t, 'remediation')));
if ($db->tableExists('field_evidence')) {
    echo "field_evidence columns:\n";
    print_r($db->getFieldData('field_evidence'));
}

