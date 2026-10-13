<?php

$_SERVER['CI_ENVIRONMENT'] = 'development';
putenv('CI_ENVIRONMENT=development');
require __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';

use Config\Database;

$db = Database::connect('default');
$tables = $db->listTables();
sort($tables);
foreach ($tables as $t) {
    if (str_contains($t, 'material') || str_contains($t, 'health') || str_contains($t, 'fault') || str_contains($t, 'remed') || str_contains($t, 'asset')) {
        echo $t . "\n";
    }
}
