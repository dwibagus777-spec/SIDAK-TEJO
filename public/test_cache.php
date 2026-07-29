<?php
header('Content-Type: text/plain; charset=utf-8');

$root = dirname(__DIR__);
$writablePath = $root . '/writable';
$realWritable = realpath($writablePath);

echo "=== 1. WRITEPATH AUDIT ===\n";
echo "Root Path: " . $root . "\n";
echo "Paths::writableDirectory: " . $writablePath . "\n";
echo "realpath(writableDirectory): " . ($realWritable ?: 'FALSE') . "\n";
echo "WRITEPATH Target: " . $writablePath . "/cache/\n\n";

$subDirs = ['cache', 'session', 'logs', 'uploads', 'debugbar', 'temp'];

echo "=== 2. SUBDIRECTORY EXISTENCE & PERMISSION CHECK ===\n";
foreach ($subDirs as $sub) {
    $dirPath = $writablePath . '/' . $sub;
    $exists    = is_dir($dirPath) ? "YES" : "NO";
    $writable  = is_writable($dirPath) ? "YES" : "NO";
    $perms     = is_dir($dirPath) ? substr(sprintf('%o', fileperms($dirPath)), -4) : "N/A";
    $realSub   = realpath($dirPath) ?: "FALSE";

    echo sprintf("Folder %-10s | Exists: %-3s | Writable: %-3s | Perms: %-4s | Path: %s\n", 
        $sub, $exists, $writable, $perms, $realSub);
}
echo "\n";

echo "=== 3. WRITE TEST ON WRITABLE/CACHE/TEST.TXT ===\n";
$cacheDir = $writablePath . '/cache';
$testFile = $cacheDir . '/test.txt';

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
echo "\n=== CACHE AUDIT COMPLETE ===\n";
