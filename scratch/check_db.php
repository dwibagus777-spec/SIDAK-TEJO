<?php

define('COMPOSER_PATH', __DIR__ . '/../vendor/autoload.php');
require __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';

$db = Config\Database::connect();

if ($db->tableExists('master_materials')) {
    echo "master_materials fields:\n";
    print_r($db->getFieldData('master_materials'));
}
