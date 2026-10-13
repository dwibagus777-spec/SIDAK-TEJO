<?php

$lines = file('e:/XAMPP/htdocs/SIDAK TEJO/writable/audits/MR01_CONSTRUCTION_MATERIAL_MAP.csv', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$header = str_getcsv(array_shift($lines));

$allConstructions = [];
foreach ($lines as $line) {
    $row = str_getcsv($line);
    $code = $row[0];
    $sheet = $row[5];
    $mat = $row[2];
    $field = $row[3];
    
    if (!isset($allConstructions[$sheet][$code])) {
        $allConstructions[$sheet][$code] = [];
    }
    $allConstructions[$sheet][$code][] = [
        'material' => $mat,
        'field' => $field,
    ];
}

foreach ($allConstructions as $sheet => $codes) {
    echo "============================================================\n";
    echo "SHEET: {$sheet} (" . count($codes) . " construction types)\n";
    echo "============================================================\n";
    foreach ($codes as $c => $mats) {
        echo "  - Code: [{$c}] (" . count($mats) . " materials)\n";
    }
}
