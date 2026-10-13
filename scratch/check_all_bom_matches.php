<?php
$canonFile = 'writable/audits/MR01_PHASE2B_CANONICAL_MASTER_PREVIEW.csv';
$canonLines = file($canonFile, FILE_IGNORE_NEW_LINES);
$canonHeader = str_getcsv(array_shift($canonLines));

$canonMap = [];
foreach ($canonLines as $line) {
    $r = str_getcsv($line);
    $code = $r[0];
    $name = $r[1];
    $unit = $r[2];
    $alias = $r[3];
    $cat = $r[4];
    $canonMap[strtoupper(trim($name))] = [
        'code' => $code,
        'name' => $name,
        'unit' => $unit,
        'alias' => $alias,
        'category' => $cat,
    ];
}

$mapFile = 'writable/audits/MR01_CONSTRUCTION_MATERIAL_MAP.csv';
$mapLines = file($mapFile, FILE_IGNORE_NEW_LINES);
$mapHeader = str_getcsv(array_shift($mapLines));

$unmatched = [];
$totalRows = 0;
$matchedRows = 0;

$constructionStats = [];

foreach ($mapLines as $line) {
    $r = str_getcsv($line);
    $cCode = $r[0];
    $sheet = $r[5];
    $mName = $r[2];
    $fName = $r[3];
    $totalRows++;

    if (!isset($constructionStats[$cCode])) {
        $constructionStats[$cCode] = ['total' => 0, 'matched' => 0];
    }
    $constructionStats[$cCode]['total']++;

    $match = $canonMap[strtoupper(trim($mName))] ?? null;
    if ($match) {
        $matchedRows++;
        $constructionStats[$cCode]['matched']++;
    } else {
        $unmatched[strtoupper(trim($mName))] = [
            'raw' => $mName,
            'field' => $fName,
            'sheet' => $sheet,
            'cCode' => $cCode,
        ];
    }
}

echo "Total BOM rows in MR01_CONSTRUCTION_MATERIAL_MAP.csv: $totalRows\n";
echo "Matched to Canonical Master: $matchedRows (" . round($matchedRows / $totalRows * 100, 1) . "%)\n";
echo "Unmatched unique materials: " . count($unmatched) . "\n\n";

if (count($unmatched) > 0) {
    echo "--- UNMATCHED MATERIALS ---\n";
    foreach ($unmatched as $u) {
        echo sprintf("  - %-40s | Field: %-15s | Sheet: %s | C: %s\n", $u['raw'], $u['field'], $u['sheet'], $u['cCode']);
    }
}

echo "\n--- BY CONSTRUCTION TYPE ---\n";
foreach ($constructionStats as $c => $st) {
    echo sprintf("  %-20s : %2d / %2d matched\n", $c, $st['matched'], $st['total']);
}
