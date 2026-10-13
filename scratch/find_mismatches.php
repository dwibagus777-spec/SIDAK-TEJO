<?php
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

require_once SYSTEMPATH . 'Helpers/array_helper.php';
require_once SYSTEMPATH . 'Helpers/url_helper.php';
require_once SYSTEMPATH . 'Helpers/html_helper.php';
require_once SYSTEMPATH . 'Helpers/form_helper.php';
require_once APPPATH . 'Helpers/app_helper.php';
helper(['array', 'url', 'html', 'form', 'app']);

$_SESSION = ['user_role' => 'administrator', 'isLoggedIn' => true];
service('session')->set($_SESSION);

$controller = new \App\Controllers\MaterialRequisitionController();
$controller->initController(\Config\Services::request(), \Config\Services::response(), \Config\Services::logger());
$html = $controller->create();

preg_match_all('/<label[^>]+for=["\']([^"\']+)["\']/i', $html, $m);
$mismatches = [];
foreach ($m[1] as $id) {
    if (strpos($html, 'id="' . $id . '"') === false && strpos($html, "id='{$id}'") === false) {
        $mismatches[] = $id;
    }
}
echo "Mismatched IDs: " . implode(', ', array_unique($mismatches)) . "\n";
