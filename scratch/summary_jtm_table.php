<?php

$lines = file('e:/XAMPP/htdocs/SIDAK TEJO/writable/audits/MR01_CONSTRUCTION_MATERIAL_MAP.csv', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$header = str_getcsv(array_shift($lines));

$map = [];
foreach ($lines as $line) {
    $row = str_getcsv($line);
    $code = $row[0];
    $sheet = $row[5];
    if ($sheet === 'KONSTRUKSI JTM') {
        $map[$code][] = [
            'material' => $row[2],
            'field_name' => $row[3],
        ];
    }
}

printf("%-15s | %-6s | %-6s | %-12s | %-12s | %-10s | %-12s\n",
    "Code", "Pin", "Strain", "Arrester", "Cut Out", "DS", "Line Tap");
echo str_repeat('-', 85) . "\n";

foreach ($map as $code => $items) {
    $hasPin = false; $hasStrain = false; $hasLA = false; $hasCO = false; $hasDS = false; $hasLineTap = false;
    foreach ($items as $it) {
        $m = strtoupper($it['material']);
        $f = strtoupper($it['field_name']);
        if (strpos($m, 'PIN') !== false || strpos($m, 'POST') !== false) $hasPin = true;
        if (strpos($m, 'STRAIN') !== false || strpos($m, 'HANG') !== false) $hasStrain = true;
        if (strpos($m, 'ARRESTER') !== false || strpos($m, 'LIGHTNING') !== false) $hasLA = true;
        if (strpos($m, 'CUT OUT') !== false || strpos($m, 'FCO') !== false || strpos($f, 'FCO') !== false) $hasCO = true;
        if (strpos($m, 'DISCONECCTING') !== false || strpos($m, 'DISCONNECTING') !== false || $f === 'DS') $hasDS = true;
        if (strpos($m, 'LINE TAP') !== false || strpos($m, 'TAP') !== false) $hasLineTap = true;
    }

    printf("%-15s | %-6s | %-6s | %-12s | %-12s | %-10s | %-12s\n",
        substr($code, 0, 15),
        $hasPin ? "YES" : "NO",
        $hasStrain ? "YES" : "NO",
        $hasLA ? "YES (LA)" : "NO",
        $hasCO ? "YES (CO)" : "NO",
        $hasDS ? "YES (DS)" : "NO",
        $hasLineTap ? "YES (TAP)" : "NO"
    );
}
