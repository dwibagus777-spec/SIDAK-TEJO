<?php
define('ROOTPATH', realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR);
define('APPPATH', ROOTPATH . 'app' . DIRECTORY_SEPARATOR);
define('SYSTEMPATH', ROOTPATH . 'vendor/codeigniter4/framework/system' . DIRECTORY_SEPARATOR);
define('FCPATH', ROOTPATH . 'public' . DIRECTORY_SEPARATOR);
define('COMPOSER_PATH', ROOTPATH . 'vendor/autoload.php');
define('WRITEPATH', ROOTPATH . 'writable' . DIRECTORY_SEPARATOR);
define('ENVIRONMENT', 'development');

require COMPOSER_PATH;
require SYSTEMPATH . 'Common.php';

$dotenv = new \CodeIgniter\Config\DotEnv(ROOTPATH);
$dotenv->load();

$db = \CodeIgniter\Database\Config::connect('default', false);

$sql = "SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, DELETE_RULE, UPDATE_RULE, REFERENCED_TABLE_NAME 
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
        JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS rc 
          ON kcu.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA 
         AND kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
        WHERE kcu.TABLE_SCHEMA = DATABASE() 
          AND kcu.TABLE_NAME LIKE 'asset_health%'";

$rows = $db->query($sql)->getResultArray();
echo json_encode($rows, JSON_PRETTY_PRINT);
