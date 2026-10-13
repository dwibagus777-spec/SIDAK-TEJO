<?php

$_SERVER['CI_ENVIRONMENT'] = 'development';
putenv('CI_ENVIRONMENT=development');
require __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';
$db = \Config\Database::connect('default');
$db->query("DELETE FROM assets WHERE id = 1891 OR kode_asset LIKE 'AST-AUDIT-DELETED-%'");
echo "Cleaned up. Total assets physical: " . $db->table('assets')->countAllResults() . "\n";
