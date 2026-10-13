<?php

$_SERVER['CI_ENVIRONMENT'] = 'development';
putenv('CI_ENVIRONMENT=development');
require 'vendor/codeigniter4/framework/system/Test/bootstrap.php';

$db = \Config\Database::connect();
$fields = $db->getFieldNames('operational_field_execution_records');
print_r($fields);
