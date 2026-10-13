<?php
$lines = file('writable/audits/MR01_CONSTRUCTION_MATERIAL_MAP.csv', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$header = str_getcsv(array_shift($lines));

$jtm = [];
foreach ($lines as $l) {
    $r = str_getcsv($l);
    // Columns: construction_code, construction_name, raw_material_name, field_name, quantity, source_sheet, source_row
    if (strpos($r[5], 'JTM') !== false) {
        $code = $r[0];
        if (!isset($jtm[$code])) {
            $jtm[$code] = [
                'name' => $r[1],
                'items' => [],
            ];
        }
        $jtm[$code]['items'][] = [
            'material_name' => $r[2],
            'field_alias'   => $r[3],
            'default_qty'   => (float)$r[4],
        ];
    }
}

echo "JTM Constructions found: " . count($jtm) . "\n";
foreach ($jtm as $code => $data) {
    echo sprintf("  %-10s | %-45s | %2d items\n", $code, substr($data['name'], 0, 45), count($data['items']));
}

echo "\nTM1 Detail:\n";
foreach ($jtm['TM1']['items'] as $idx => $it) {
    echo ($idx + 1) . ". {$it['material_name']} (Alias: {$it['field_alias']}) - Qty: {$it['default_qty']}\n";
}
