<?php
require_once "vendor/autoload.php";
require_once "app/Config/Paths.php";

$paths = new Config\Paths();
require_once $paths->systemDirectory . '/Boot.php';

\CodeIgniter\Boot::bootWeb($paths);

$session = \Config\Services::session();
$session->set([
    "user_id" => 1,
    "user_name" => "Administrator",
    "user_role" => "administrator",
    "user_ulp_id" => null,
    "user_ulp_nama" => "UP3 Sidoarjo"
]);
echo "Session set\n";

// Test Dashboard controller
$controller = new App\Controllers\Dashboard();
echo "Controller loaded OK\n";

// Test sidebar preference
$prefs = $session->get('sidebar_collapsed');
echo "sidebar_collapsed preference: " . ($prefs ? 'true' : 'false') . " (null = default true)\n";