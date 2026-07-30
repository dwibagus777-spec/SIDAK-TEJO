<?php
if (function_exists('opcache_reset')) {
    @opcache_reset();
}

use CodeIgniter\Boot;
use Config\Paths;

/*
 *---------------------------------------------------------------
 * CHECK PHP VERSION
 *---------------------------------------------------------------
 */

$minPhpVersion = '8.2'; // If you update this, don't forget to update `spark`.
if (version_compare(PHP_VERSION, $minPhpVersion, '<')) {
    $message = sprintf(
        'Your PHP version must be %s or higher to run CodeIgniter. Current version: %s',
        $minPhpVersion,
        PHP_VERSION,
    );

    header('HTTP/1.1 503 Service Unavailable.', true, 503);
    echo $message;

    exit(1);
}



/*
 *---------------------------------------------------------------
 * SET THE CURRENT DIRECTORY
 *---------------------------------------------------------------
 */

// Path to the front controller (this file)
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);

// Raw trace log for delete requests
if (isset($_SERVER['REQUEST_URI']) && str_contains(strtolower($_SERVER['REQUEST_URI']), 'delete')) {
    $rawLog = date('Y-m-d H:i:s') . " | RAW_INDEX_PHP | URI: " . $_SERVER['REQUEST_URI'] . " | Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN') . " | RemoteIP: " . ($_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN') . " | POST: " . json_encode($_POST) . "\n";
    file_put_contents(__DIR__ . '/debug_trace.txt', $rawLog, FILE_APPEND);
}

// Ensure the current directory is pointing to the front controller's directory
if (getcwd() . DIRECTORY_SEPARATOR !== FCPATH) {
    chdir(FCPATH);
}

/*
 *---------------------------------------------------------------
 * BOOTSTRAP THE APPLICATION
 *---------------------------------------------------------------
 * This process sets up the path constants, loads and registers
 * our autoloader, along with Composer's, loads our constants
 * and fires up an environment-specific bootstrapping.
 */

// LOAD OUR PATHS CONFIG FILE
// This is the line that might need to be changed, depending on your folder structure.
require FCPATH . '../app/Config/Paths.php';
// ^^^ Change this line if you move your application folder

$paths = new Paths();

// Ensure all writable subdirectories exist and have 0755/0775 write permissions (Hostinger Shared Hosting Compatible)
(function($writablePath) {
    $dirs = ['cache', 'logs', 'session', 'uploads', 'debugbar', 'queue'];
    foreach ($dirs as $dir) {
        $fullPath = rtrim($writablePath, '/\\') . DIRECTORY_SEPARATOR . $dir;
        if (!is_dir($fullPath)) {
            @mkdir($fullPath, 0755, true);
        }
        if (is_dir($fullPath) && !is_writable($fullPath)) {
            @chmod($fullPath, 0755);
            if (!is_writable($fullPath)) {
                @chmod($fullPath, 0775);
            }
        }
    }
})($paths->writableDirectory);

// LOAD THE FRAMEWORK BOOTSTRAP FILE
require $paths->systemDirectory . '/Boot.php';

exit(Boot::bootWeb($paths));
