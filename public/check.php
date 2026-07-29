<?php
header('Content-Type: text/plain; charset=utf-8');

$root = dirname(__DIR__);
$target = $root . '/vendor/symfony/deprecation-contracts/function.php';
$vendor = $root . '/vendor';

echo "=== LANGKAH 1: DIAGNOSTIC CHECK ===\n";
echo "PHP_VERSION: " . PHP_VERSION . "\n";
echo "getcwd(): " . getcwd() . "\n";
echo "__DIR__: " . __DIR__ . "\n";
echo "realpath(vendor): " . (realpath($vendor) ?: 'FALSE') . "\n";
echo "realpath(function.php): " . (realpath($target) ?: 'FALSE') . "\n";
echo "file_exists(function.php): " . (file_exists($target) ? 'YES' : 'NO') . "\n";
echo "is_file(function.php): " . (is_file($target) ? 'YES' : 'NO') . "\n";
echo "is_readable(function.php): " . (is_readable($target) ? 'YES' : 'NO') . "\n";
echo "filesize(function.php): " . (file_exists($target) ? filesize($target) : 'N/A') . "\n";
echo "md5_file(function.php): " . (file_exists($target) ? md5_file($target) : 'N/A') . "\n\n";

echo "=== LANGKAH 5: REALPATH OF MAIN COMPOSER FILES ===\n";
echo "realpath(public/index.php): " . (realpath(__DIR__ . '/index.php') ?: 'FALSE') . "\n";
echo "realpath(vendor/autoload.php): " . (realpath($vendor . '/autoload.php') ?: 'FALSE') . "\n";
echo "realpath(vendor/composer/autoload_real.php): " . (realpath($vendor . '/composer/autoload_real.php') ?: 'FALSE') . "\n\n";

echo "=== LANGKAH 3: FILE MD5 CHECKSUMS ===\n";
$files = [
    'vendor/composer/autoload_real.php' => $vendor . '/composer/autoload_real.php',
    'vendor/composer/autoload_static.php' => $vendor . '/composer/autoload_static.php',
    'vendor/composer/autoload_files.php' => $vendor . '/composer/autoload_files.php',
    'vendor/symfony/deprecation-contracts/function.php' => $target,
    'composer.lock' => $root . '/composer.lock',
];
foreach ($files as $name => $path) {
    $exists = file_exists($path);
    $md5 = $exists ? md5_file($path) : 'MISSING';
    echo sprintf("%-50s: %s\n", $name, $md5);
}
