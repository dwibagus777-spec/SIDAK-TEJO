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

echo "=== JTM CONSTRUCTIONS IN 'KONSTRUKSI JTM' SHEET ===\n";
echo "Total JTM construction codes: " . count($map) . "\n\n";

foreach ($map as $code => $items) {
    echo "============================================================\n";
    echo "CONSTRUCTION CODE: [{$code}] (" . count($items) . " materials)\n";
    
    // Categorize components
    $pinCount = 0;
    $strainCount = 0;
    $travers = [];
    $hasLA = false;
    $hasCO = false;
    $hasDS = false;
    $hasLineTap = false;
    $special = [];

    foreach ($items as $it) {
        $m = strtoupper($it['material']);
        $f = strtoupper($it['field_name']);

        if (strpos($m, 'PIN') !== false || strpos($m, 'POST') !== false) {
            $pinCount++;
        }
        if (strpos($m, 'STRAIN') !== false || strpos($m, 'HANG ISOLATOR') !== false) {
            $strainCount++;
        }
        if (strpos($m, 'CROSS ARM') !== false || strpos($m, 'KANAL') !== false) {
            $travers[] = $it['material'] . " (" . $it['field_name'] . ")";
        }
        if (strpos($m, 'ARRESTER') !== false || strpos($m, 'LIGHTNING') !== false) {
            $hasLA = true;
        }
        if (strpos($m, 'CUT OUT') !== false || strpos($m, 'FCO') !== false || strpos($m, 'FUSE') !== false) {
            $hasCO = true;
        }
        if (strpos($m, 'DISCONECCTING') !== false || strpos($m, 'DISCONNECTING') !== false || strpos($m, 'DS') !== false) {
            $hasDS = true;
        }
        if (strpos($m, 'LINE TAP') !== false || strpos($m, 'TAP') !== false) {
            $hasLineTap = true;
        }
    }

    echo "  - Isolator Pin/Post: " . ($pinCount > 0 ? "YES ({$pinCount} items)" : "NO") . "\n";
    echo "  - Isolator Strain/Tarik: " . ($strainCount > 0 ? "YES ({$strainCount} items)" : "NO") . "\n";
    echo "  - Travers: " . (count($travers) > 0 ? implode('; ', $travers) : "Tanpa Travers") . "\n";
    echo "  - Arrester (LA): " . ($hasLA ? "⚡ YES (DENGAN ARRESTER)" : "NO") . "\n";
    echo "  - Cut Out (FCO): " . ($hasCO ? "🔌 YES (DENGAN CO)" : "NO") . "\n";
    echo "  - Disconnecting Switch (DS): " . ($hasDS ? "🎛️ YES (DENGAN DS)" : "NO") . "\n";
    echo "  - Line Tap (Percabangan): " . ($hasLineTap ? "🔀 YES (LINE TAP CONNECTOR)" : "NO") . "\n";

    echo "  - Raw materials preview:\n";
    foreach (array_slice($items, 0, 8) as $it) {
        echo "      * {$it['material']} [{$it['field_name']}]\n";
    }
    if (count($items) > 8) {
        echo "      * ... and " . (count($items) - 8) . " more items\n";
    }
}
