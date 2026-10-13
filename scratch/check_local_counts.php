<?php

require __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';

$db = \Config\Database::connect('default');

echo "Local gis_translines count: " . $db->table('gis_translines')->where('is_active', 1)->countAllResults() . "\n";
echo "Local assets count: " . $db->table('assets')->where('is_active', 1)->countAllResults() . "\n";
echo "Migrations applied:\n";
$migs = $db->table('migrations')->get()->getResultArray();
foreach ($migs as $m) {
    echo " - " . $m['version'] . " (" . $m['class'] . ")\n";
}
