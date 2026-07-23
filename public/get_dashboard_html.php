<?php
if (php_sapi_name() !== 'cli') {
    die('CLI only');
}

define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);
require __DIR__ . '/../vendor/autoload.php';

// Bootstrap CI4
$paths = new Config\Paths();
require $paths->systemDirectory . '/bootstrap.php';

// Set session mock
$session = \Config\Services::session();
$session->set([
    'user_id' => 1,
    'user_name' => 'Administrator',
    'user_role' => 'administrator',
    'logged_in' => true
]);

// Call controller
$controller = new \App\Controllers\Dashboard();
$controller->initController(
    \Config\Services::request(),
    \Config\Services::response(),
    \Config\Services::logger()
);
$response = $controller->index();
if ($response instanceof \CodeIgniter\HTTP\ResponseInterface) {
    $html = $response->getBody();
} else {
    $html = (string)$response;
}
file_put_contents(__DIR__ . '/test_dashboard.html', $html);
echo "Done!\n";
