<?php

declare(strict_types=1);

define('ROOTPATH', realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR);
define('APPPATH', ROOTPATH . 'app' . DIRECTORY_SEPARATOR);
define('SYSTEMPATH', ROOTPATH . 'vendor/codeigniter4/framework/system' . DIRECTORY_SEPARATOR);
define('FCPATH', ROOTPATH . 'public' . DIRECTORY_SEPARATOR);
define('COMPOSER_PATH', ROOTPATH . 'vendor/autoload.php');
define('WRITEPATH', ROOTPATH . 'writable' . DIRECTORY_SEPARATOR);
define('ENVIRONMENT', 'development');
defined('CI_DEBUG') || define('CI_DEBUG', true);

require COMPOSER_PATH;
require SYSTEMPATH . 'Common.php';

$dotenv = new \CodeIgniter\Config\DotEnv(ROOTPATH);
$dotenv->load();

$db = \CodeIgniter\Database\Config::connect('default', false);

echo "Assets total: " . $db->table('assets')->countAllResults() . "\n";
echo "Assets active: " . $db->table('assets')->where('deleted_at IS NULL')->where('status !=', 'INACTIVE')->countAllResults() . "\n";
echo "Translines total: " . $db->table('gis_translines')->countAllResults() . "\n";
echo "Translines active: " . $db->table('gis_translines')->where('deleted_at IS NULL')->where('is_active', 1)->countAllResults() . "\n";
