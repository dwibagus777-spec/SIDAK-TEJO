<?php
define('ROOTPATH', realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR);
define('APPPATH', ROOTPATH . 'app' . DIRECTORY_SEPARATOR);
define('SYSTEMPATH', ROOTPATH . 'vendor/codeigniter4/framework/system' . DIRECTORY_SEPARATOR);
define('COMPOSER_PATH', ROOTPATH . 'vendor/autoload.php');
define('ENVIRONMENT', 'development');

require COMPOSER_PATH;
require SYSTEMPATH . 'Common.php';

$dotenv = new \CodeIgniter\Config\DotEnv(ROOTPATH);
$dotenv->load();

$db = \CodeIgniter\Database\Config::connect('default', false);
$fields = $db->getFieldData('assets');
foreach ($fields as $f) {
    if ($f->name === 'id') {
        echo "assets.id type: " . $f->type . ", max_length: " . $f->max_length . "\n";
    }
}
