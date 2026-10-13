<?php

$_SERVER['CI_ENVIRONMENT'] = 'development';
putenv('CI_ENVIRONMENT=development');
require __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';

use Config\Database;

$db = Database::connect('default');

if ($db->tableExists('material_requisition_packages')) {
    $fields = $db->getFieldNames('material_requisition_packages');
    echo "material_requisition_packages fields: " . implode(', ', $fields) . "\n";
}
