<?php
header('Content-Type: text/plain; charset=utf-8');

try {
    require __DIR__ . '/../vendor/autoload.php';
    echo "AUTOLOAD OK";
} catch (\Throwable $e) {
    echo "AUTOLOAD FAILED: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine();
}
