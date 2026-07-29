<?php

/**
 * FORENSIC AUDIT SCRIPT — COMPOSER & VENDOR INTEGRITY FOR HOSTINGER
 */

define('ROOT_DIR', dirname(__DIR__));

echo "=== 1. AUDIT PATH & FILE CHECKS ===\n";
$targetFile = ROOT_DIR . '/vendor/symfony/deprecation-contracts/function.php';
$targetDir  = ROOT_DIR . '/vendor/symfony/deprecation-contracts';
$vendorDir  = ROOT_DIR . '/vendor';

echo "Target File: " . $targetFile . "\n";
echo "file_exists(): " . (file_exists($targetFile) ? "YES" : "NO") . "\n";
echo "is_file():     " . (is_file($targetFile) ? "YES" : "NO") . "\n";
echo "is_readable(): " . (is_readable($targetFile) ? "YES" : "NO") . "\n";
echo "realpath():    " . (realpath($targetFile) ?: "FALSE") . "\n";
echo "filesize():    " . (file_exists($targetFile) ? filesize($targetFile) . " bytes" : "N/A") . "\n";
echo "md5_file():    " . (file_exists($targetFile) ? md5_file($targetFile) : "N/A") . "\n\n";

echo "=== 2. AUDIT PERMISSIONS ===\n";
echo "vendor dir perm: " . substr(sprintf('%o', fileperms($vendorDir)), -4) . "\n";
echo "target dir perm: " . substr(sprintf('%o', fileperms($targetDir)), -4) . "\n";
echo "target file perm: " . substr(sprintf('%o', fileperms($targetFile)), -4) . "\n\n";

echo "=== 3. AUDIT AUTOLOAD MAPS ===\n";
$autoloadFiles = ROOT_DIR . '/vendor/composer/autoload_files.php';
if (file_exists($autoloadFiles)) {
    $filesMap = include $autoloadFiles;
    echo "autoload_files.php contents:\n";
    foreach ($filesMap as $hash => $path) {
        $exists = file_exists($path) ? "EXISTS" : "MISSING!";
        echo "  [$hash] => $path ($exists)\n";
    }
} else {
    echo "autoload_files.php NOT FOUND!\n";
}
echo "\n";

echo "=== 4. AUDIT INSTALLED PACKAGES ===\n";
$installedPhp = ROOT_DIR . '/vendor/composer/installed.php';
if (file_exists($installedPhp)) {
    $instData = include $installedPhp;
    $versions = $instData['versions'] ?? [];
    if (isset($versions['symfony/deprecation-contracts'])) {
        echo "symfony/deprecation-contracts version in installed.php: " . json_encode($versions['symfony/deprecation-contracts']) . "\n";
    } else {
        echo "symfony/deprecation-contracts NOT FOUND in installed.php!\n";
    }
} else {
    echo "installed.php NOT FOUND!\n";
}
echo "\n";

echo "=== 5. SELF TEST COMPOSER LOAD ===\n";
try {
    clearstatcache(true);
    if (function_exists('opcache_reset')) {
        @opcache_reset();
    }

    require_once ROOT_DIR . '/vendor/autoload.php';
    echo "Self Test Status: SUCCESS — vendor/autoload.php loaded without error!\n";
    if (function_exists('trigger_deprecation')) {
        echo "Function trigger_deprecation() from function.php is LOADED and AVAILABLE!\n";
    }
} catch (\Throwable $e) {
    echo "Self Test Status: FAILED — " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "\n";
}

echo "=== FORENSIC AUDIT COMPLETE ===\n";
