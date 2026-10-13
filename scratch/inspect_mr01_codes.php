<?php

$lines = file('e:/XAMPP/htdocs/SIDAK TEJO/writable/audits/MR01_CONSTRUCTION_MATERIAL_MAP.csv', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$header = str_getcsv(array_shift($lines));

$constructions = [];
foreach ($lines as $line) {
    $row = str_getcsv($line);
    $code = $row[0];
    $mat = $row[2];
    $field = $row[3];
    if (!isset($constructions[$code])) {
        $constructions[$code] = [];
    }
    $constructions[$code][] = [
        'material' => $mat,
        'field' => $field
    ];
}

echo "Total distinct construction codes in MR01_CONSTRUCTION_MATERIAL_MAP: " . count($constructions) . "\n\n";

foreach ($constructions as $code => $items) {
    echo "Code: [{$code}] (" . count($items) . " BOM items)\n";
    $mats = array_map(fn($it) => $it['material'], $items);
    
    // Check key distinguishing components:
    $hasLA = false;
    $hasCO = false;
    $hasDS = false;
    $hasLBS = false;
    $hasArrester = false;
    $hasStrain = false;
    $hasPin = false;
    $hasLineTap = false;

    foreach ($mats as $m) {
        $mUpper = strtoupper($m);
        if (strpos($mUpper, 'ARRESTER') !== false || strpos($mUpper, 'LIGHTNING') !== false) $hasLA = true;
        if (strpos($mUpper, 'CUT OUT') !== false || strpos($mUpper, 'FCO') !== false || strpos($mUpper, 'FUSE') !== false) $hasCO = true;
        if (strpos($mUpper, 'DISCONECCTING') !== false || strpos($mUpper, 'DISCONNECTING') !== false || strpos($mUpper, ' DS') !== false) $hasDS = true;
        if (strpos($mUpper, 'LBS') !== false) $hasLBS = true;
        if (strpos($mUpper, 'STRAIN') !== false || strpos($mUpper, 'HANG ISOLATOR') !== false) $hasStrain = true;
        if (strpos($mUpper, 'PIN') !== false || strpos($mUpper, 'POST') !== false) $hasPin = true;
        if (strpos($mUpper, 'LINE TAP') !== false || strpos($mUpper, 'TAP') !== false) $hasLineTap = true;
    }

    $flags = [];
    if ($hasLA) $flags[] = "ARRESTER";
    if ($hasCO) $flags[] = "FCO/CO";
    if ($hasDS) $flags[] = "DS";
    if ($hasLBS) $flags[] = "LBS";
    if ($hasStrain) $flags[] = "STRAIN/HANG";
    if ($hasPin) $flags[] = "PIN/POST";
    if ($hasLineTap) $flags[] = "LINE_TAP";

    echo "  Features: " . implode(', ', $flags) . "\n";
}
