<?php

/**
 * SIDAK TEJO - Universal Root Index.php Forwarder for Hostinger
 */

$publicPath = __DIR__ . '/public';

if (file_exists($publicPath . '/index.php')) {
    chdir($publicPath);
    require_once $publicPath . '/index.php';
} else {
    echo "Error: CodeIgniter public/index.php not found.";
}
