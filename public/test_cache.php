<?php
header('Content-Type: text/plain; charset=utf-8');

use Config\Paths;
use Config\Cache;

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/codeigniter4/framework/system/Common.php';

if (!defined('WRITEPATH')) {
    define('WRITEPATH', realpath(__DIR__ . '/../writable') . DIRECTORY_SEPARATOR);
}

$paths = new Paths();
$writablePath = $paths->writableDirectory;
$realWritable = realpath($writablePath);

echo "=== 1. WRITEPATH AUDIT ===\n";
echo "Paths::writableDirectory: " . $writablePath . "\n";
echo "realpath(writableDirectory): " . ($realWritable ?: 'FALSE') . "\n";
echo "Defined WRITEPATH constant: " . WRITEPATH . "\n\n";

$subDirs = ['cache', 'session', 'logs', 'uploads', 'debugbar', 'temp'];

echo "=== 2. SUBDIRECTORY EXISTENCE & PERMISSION CHECK ===\n";
foreach ($subDirs as $sub) {
    $dirPath = rtrim($writablePath, '/\\') . DIRECTORY_SEPARATOR . $sub;
    
    if (!is_dir($dirPath)) {
        $created = @mkdir($dirPath, 0755, true);
        echo "Creating dir '$sub': " . ($created ? "SUCCESS" : "FAILED") . "\n";
    }
    
    if (is_dir($dirPath) && !is_writable($dirPath)) {
        @chmod($dirPath, 0755);
        if (!is_writable($dirPath)) {
            @chmod($dirPath, 0775);
        }
    }
    
    $exists    = is_dir($dirPath) ? "YES" : "NO";
    $writable  = is_writable($dirPath) ? "YES" : "NO";
    $perms     = is_dir($dirPath) ? substr(sprintf('%o', fileperms($dirPath)), -4) : "N/A";
    $realSub   = realpath($dirPath) ?: "FALSE";
    $ownerInfo = function_exists('posix_getpwuid') && is_dir($dirPath) ? @posix_getpwuid(fileowner($dirPath))['name'] : fileowner($dirPath);

    echo sprintf("Folder %-10s | Exists: %-3s | Writable: %-3s | Perms: %-4s | Owner: %-8s | Path: %s\n", 
        $sub, $exists, $writable, $perms, $ownerInfo, $realSub);
}
echo "\n";

echo "=== 3. WRITE TEST ON WRITABLE/CACHE/TEST.TXT ===\n";
$cacheDir = rtrim($writablePath, '/\\') . DIRECTORY_SEPARATOR . 'cache';
$testFile = $cacheDir . DIRECTORY_SEPARATOR . 'test.txt';

try {
    $testContent = "Cache Write Test OK — " . date('Y-m-d H:i:s');
    $writeRes = @file_put_contents($testFile, $testContent);
    
    if ($writeRes !== false) {
        echo "file_put_contents(): SUCCESS (" . $writeRes . " bytes written)\n";
        echo "is_file(test.txt): " . (is_file($testFile) ? "YES" : "NO") . "\n";
        echo "file_get_contents(): " . file_get_contents($testFile) . "\n";
        @unlink($testFile);
        echo "unlink(test.txt): SUCCESS\n";
    } else {
        echo "file_put_contents(): FAILED to write to $testFile\n";
    }
} catch (\Throwable $e) {
    echo "Cache Test Exception: " . $e->getMessage() . "\n";
}
echo "\n";

echo "=== 4. CONFIG CACHE & HANDLER AUDIT ===\n";
$cacheConfig = new Cache();
echo "Primary Handler: " . $cacheConfig->handler . "\n";
echo "Backup Handler: " . $cacheConfig->backupHandler . "\n";
echo "File StorePath: " . $cacheConfig->file['storePath'] . "\n";
echo "File Mode: " . decoct($cacheConfig->file['mode']) . "\n";
echo "\n=== CACHE AUDIT COMPLETE ===\n";
