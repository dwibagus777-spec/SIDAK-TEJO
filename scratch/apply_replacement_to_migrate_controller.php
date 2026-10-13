<?php
$origFile = __DIR__ . '/../app/Controllers/MigrateController.php';
$origContent = file_get_contents($origFile);

$replacementFile = __DIR__ . '/complete_replacement.php';
$replacement = file_get_contents($replacementFile);
// strip <?php\n// Complete replacement methods for MigrateController\n
$replacement = preg_replace('/^<\?php.*?\n/s', '', $replacement);
$replacement = preg_replace('/^\/\/.*?\n/', '', $replacement);

$needle = "/**\n     * Phase B.2.1: One-Shot Consolidated Atomic Production Commit Engine";
$pos = strpos($origContent, $needle);
if ($pos === false) {
    die("Needle not found!\n");
}

$before = substr($origContent, 0, $pos);
$newContent = $before . $replacement . "\n}\n";

file_put_contents($origFile, $newContent);
echo "Updated MigrateController.php successfully!\n";

$res = exec('php -l ' . escapeshellarg($origFile), $output, $ret);
echo implode("\n", $output) . "\n";
if ($ret === 0) {
    echo "MIGRATECONTROLLER SYNTAX OK!\n";
} else {
    echo "MIGRATECONTROLLER SYNTAX ERROR!\n";
}
