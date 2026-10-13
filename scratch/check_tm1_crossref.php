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

// Now let's check TM1 materials specifically
$csvFile = 'C:/Users/INSPEKSIKOTA/.gemini/antigravity/brain/e5e83a3d-4509-4271-8129-9355c979dd3c/.system_generated/steps/25942/content.md';
$lines = file($csvFile, FILE_IGNORE_NEW_LINES);

$tm1Items = [];
$capture = false;
foreach ($lines as $line) {
    $line = trim($line);
    if (str_starts_with($line, 'KONSTRUKSI TM1,')) {
        $capture = true;
        continue;
    }
    if ($capture && str_starts_with($line, 'KONSTRUKSI TM1 A,')) {
        break;
    }
    if ($capture) {
        $r = str_getcsv($line);
        $m = trim($r[0] ?? '');
        $f = trim($r[1] ?? '');
        if ($m && $m !== 'NAMA MATERIAL') {
            $tm1Items[] = ['material' => $m, 'field' => $f];
        }
    }
}

echo "=== TM1 COMPONENTS IN SHEET (" . count($tm1Items) . " items) ===\n";
foreach ($tm1Items as $idx => $it) {
    $m = $it['material'];
    $f = $it['field'];
    $match = $canonMap[strtoupper($m)] ?? null;
    $status = $match ? "MATCH: [{$match['code']}] unit={$match['unit']} cat={$match['category']}" : "NOT IN CANONICAL PREVIEW";
    echo sprintf("%2d. %-60s | Field: %-15s | %s\n", $idx + 1, $m, $f, $status);
}
