<?php

namespace CodeIgniter\Session {
    if (!function_exists('CodeIgniter\Session\dot_array_search')) {
        function dot_array_search(string $index, array $array) {
            return \dot_array_search($index, $array);
        }
    }
}

namespace {
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
    require_once SYSTEMPATH . 'Helpers/url_helper.php';
    require_once SYSTEMPATH . 'Helpers/array_helper.php';
    require_once SYSTEMPATH . 'Helpers/form_helper.php';
    require_once SYSTEMPATH . 'Helpers/html_helper.php';
    require_once APPPATH . 'Helpers/app_helper.php';

    $dotenv = new \CodeIgniter\Config\DotEnv(ROOTPATH);
    $dotenv->load();

    // Mock session
    $session = \Config\Services::session();
    $_SESSION['user_id'] = 1;
    $_SESSION['user_name'] = 'Administrator Test';
    $_SESSION['user_role'] = 'administrator';
    $_SESSION['logged_in'] = true;

    $results = [];

    // B83-UI-01: /asset-health Overview Page
    try {
        $controller = new \App\Controllers\AssetHealthController();
        $controller->initController(
            \Config\Services::request(),
            \Config\Services::response(),
            \Config\Services::logger()
        );
        $output = $controller->index();
        $results['B83-UI-01'] = [
            'description' => 'UI Surface /asset-health (Overview Hub)',
            'passed'      => !empty($output),
            'details'     => 'Rendered overview HTML cleanly',
        ];
    } catch (\Throwable $e) {
        $results['B83-UI-01'] = [
            'description' => 'UI Surface /asset-health (Overview Hub)',
            'passed'      => false,
            'details'     => 'Error: ' . $e->getMessage(),
        ];
    }

    // B83-UI-02: /asset-health/394 Detail Page
    try {
        $output = $controller->detail(394);
        $results['B83-UI-02'] = [
            'description' => 'UI Surface /asset-health/394 (10-Factor Breakdown Detail)',
            'passed'      => !empty($output),
            'details'     => 'Rendered detail HTML cleanly',
        ];
    } catch (\Throwable $e) {
        $results['B83-UI-02'] = [
            'description' => 'UI Surface /asset-health/394 (10-Factor Breakdown Detail)',
            'passed'      => false,
            'details'     => 'Error: ' . $e->getMessage(),
        ];
    }

    // B83-UI-03: /asset-health/recommendations Queue Page
    try {
        $output = $controller->recommendations();
        $results['B83-UI-03'] = [
            'description' => 'UI Surface /asset-health/recommendations (L4 Queue)',
            'passed'      => !empty($output),
            'details'     => 'Rendered queue HTML cleanly',
        ];
    } catch (\Throwable $e) {
        $results['B83-UI-03'] = [
            'description' => 'UI Surface /asset-health/recommendations (L4 Queue)',
            'passed'      => false,
            'details'     => 'Error: ' . $e->getMessage(),
        ];
    }

    // B83-UI-04: /asset-health/coverage Report Endpoint
    try {
        $response = $controller->coverageReport();
        $body = $response->getBody();
        $json = json_decode($body, true);
        $results['B83-UI-04'] = [
            'description' => 'API Endpoint /asset-health/coverage (Coverage Report JSON)',
            'passed'      => ($json && isset($json['verdict']) && $json['verdict'] === 'PASS'),
            'details'     => 'JSON Response Verdict: ' . ($json['verdict'] ?? 'FAIL'),
        ];
    } catch (\Throwable $e) {
        $results['B83-UI-04'] = [
            'description' => 'API Endpoint /asset-health/coverage (Coverage Report JSON)',
            'passed'      => false,
            'details'     => 'Error: ' . $e->getMessage(),
        ];
    }

    $totalTests = count($results);
    $passedTests = count(array_filter($results, fn($r) => $r['passed']));
    $verdict = ($totalTests === $passedTests) ? 'PASS' : 'FAIL';

    echo "\n" . json_encode([
        'gate'         => 'B8_3_3_UI_SURFACE_VERIFICATION',
        'timestamp'    => date('Y-m-d H:i:s T'),
        'total_tests'  => $totalTests,
        'passed_tests' => $passedTests,
        'verdict'      => $verdict,
        'test_results' => $results,
    ], JSON_PRETTY_PRINT);

    exit($verdict === 'PASS' ? 0 : 1);
}
