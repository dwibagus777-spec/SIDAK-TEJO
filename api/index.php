<?php

// Prepare writable directories in /tmp for Vercel serverless environment
$tmpDirs = ['/tmp/cache', '/tmp/logs', '/tmp/session', '/tmp/uploads', '/tmp/debugbar'];
foreach ($tmpDirs as $d) {
    if (!is_dir($d)) {
        @mkdir($d, 0777, true);
    }
}

// Forward to CodeIgniter entrypoint
require __DIR__ . '/../public/index.php';
