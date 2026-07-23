<?php

// Set working directory to project root
chdir(dirname(__DIR__));

// Fix Vercel SCRIPT_NAME and SCRIPT_FILENAME so CodeIgniter 4 routes correctly
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../public/index.php';

// Normalize REQUEST_URI if Vercel serverless prefix /api/index is prepended
if (isset($_SERVER['REQUEST_URI'])) {
    if (strpos($_SERVER['REQUEST_URI'], '/api/index.php') === 0) {
        $_SERVER['REQUEST_URI'] = substr($_SERVER['REQUEST_URI'], 14) ?: '/';
    } elseif (strpos($_SERVER['REQUEST_URI'], '/api/index') === 0) {
        $_SERVER['REQUEST_URI'] = substr($_SERVER['REQUEST_URI'], 10) ?: '/';
    }
}

// Enable full error display for debugging on Vercel
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Shutdown handler for unhandled fatal errors
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        if (!headers_sent()) {
            header('Content-Type: text/html', true, 200);
        }
        echo "<div style='font-family:sans-serif; padding:20px; background:#fff0f0; border:2px solid red; margin:20px;'>";
        echo "<h2 style='color:red;'>Fatal PHP Error on Vercel:</h2>";
        echo "<p><b>Message:</b> " . htmlspecialchars($error['message']) . "</p>";
        echo "<p><b>File:</b> " . htmlspecialchars($error['file']) . " (Line " . $error['line'] . ")</p>";
        echo "</div>";
    }
});

// Ensure /tmp writable directories exist
$tmpDirs = ['/tmp/cache', '/tmp/logs', '/tmp/session', '/tmp/uploads', '/tmp/debugbar'];
foreach ($tmpDirs as $d) {
    if (!is_dir($d)) {
        @mkdir($d, 0777, true);
    }
}

try {
    require __DIR__ . '/../public/index.php';
} catch (\Throwable $e) {
    if (!headers_sent()) {
        header('Content-Type: text/html', true, 200);
    }
    echo "<div style='font-family:sans-serif; padding:20px; background:#fff0f0; border:2px solid red; margin:20px;'>";
    echo "<h2 style='color:red;'>Application Exception on Vercel:</h2>";
    echo "<p><b>Message:</b> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><b>File:</b> " . htmlspecialchars($e->getFile()) . " (Line " . $e->getLine() . ")</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    echo "</div>";
}
