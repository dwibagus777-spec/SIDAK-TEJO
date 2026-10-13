<?php
require 'vendor/autoload.php';

$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
foreach (glob('writable/*.xlsx') as $file) {
    echo "File: $file\n";
    $sheets = $reader->listWorksheetNames($file);
    echo "Sheets: " . implode(', ', $sheets) . "\n\n";
}
