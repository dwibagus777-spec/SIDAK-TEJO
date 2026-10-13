<?php

$_SERVER['CI_ENVIRONMENT'] = 'development';
putenv('CI_ENVIRONMENT=development');
require __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';

use Config\Database;

$db = Database::connect('default');
$row = $db->table('assets')->select('id, kode_asset')->where('deleted_at IS NULL')->orderBy('id', 'ASC')->get()->getRowArray();
echo "First Active Asset: ID=" . ($row['id'] ?? 'NONE') . ", Code=" . ($row['kode_asset'] ?? 'NONE') . "\n";
