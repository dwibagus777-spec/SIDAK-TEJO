<?php

$_SERVER['CI_ENVIRONMENT'] = 'development';
putenv('CI_ENVIRONMENT=development');
require __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';

use Config\Database;

$db = Database::connect('default');

if ($db->tableExists('remediation_package_findings')) {
    $fields = $db->getFieldNames('remediation_package_findings');
    echo "remediation_package_findings fields: " . implode(', ', $fields) . "\n";
}

if ($db->tableExists('remediation_work_packages')) {
    $fields = $db->getFieldNames('remediation_work_packages');
    echo "remediation_work_packages fields: " . implode(', ', $fields) . "\n";
}
