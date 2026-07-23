<?php

// Enable error reporting for Vercel debugging
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Prepare writable directories in /tmp for Vercel serverless environment
$tmpDirs = ['/tmp/cache', '/tmp/logs', '/tmp/session', '/tmp/uploads', '/tmp/debugbar'];
foreach ($tmpDirs as $d) {
    if (!is_dir($d)) {
        @mkdir($d, 0777, true);
    }
}

// Forward to CodeIgniter entrypoint
require __DIR__ . '/../public/index.php';
