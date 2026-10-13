<?php

$targetFile = 'e:/XAMPP/htdocs/SIDAK TEJO/app/Controllers/MigrateController.php';
$content = file_get_contents($targetFile);

// Check if already injected
if (strpos($content, 'atomicCommitB2') !== false) {
    echo "atomicCommitB2 already present in MigrateController.php\n";
    exit(0);
}

$addition = file_get_contents('e:/XAMPP/htdocs/SIDAK TEJO/scratch/migrate_controller_addition.php');
// Strip the <?php\nclass DummyController {\n and the trailing \n}\n
$addition = preg_replace('/^<\?php\s*class DummyController \{\s*/s', '', $addition);
$addition = preg_replace('/\s*\}\s*$/s', '', $addition);

// Find last closing bracket of MigrateController
$lastBracePos = strrpos($content, '}');
if ($lastBracePos === false) {
    echo "ERROR: Could not find closing brace in MigrateController.php\n";
    exit(1);
}

$newContent = substr($content, 0, $lastBracePos) . "\n" . $addition . "\n}\n";

file_put_contents($targetFile, $newContent);
echo "Successfully injected atomicCommitB2 and getB2AcceptedCandidates into MigrateController.php\n";

// Verify syntax
system('php -l "' . $targetFile . '"');
