<?php

require __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';

$db = \Config\Database::connect('default');
$tables = $db->listTables();
echo "Tables in local sidaktejo database:\n";
print_r($tables);
