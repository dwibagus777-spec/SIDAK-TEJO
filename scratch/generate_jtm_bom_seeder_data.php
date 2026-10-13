<?php

$lines = file('writable/audits/MR01_CONSTRUCTION_MATERIAL_MAP.csv', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$header = str_getcsv(array_shift($lines));

$canonLines = file('writable/audits/MR01_PHASE2B_CANONICAL_MASTER_PREVIEW.csv', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$canonHeader = str_getcsv(array_shift($canonLines));

$canonMap = [];
foreach ($canonLines as $cl) {
    $cr = str_getcsv($cl);
    // canonical_code,canonical_name,unit,field_alias,category,...
    $canonMap[strtoupper(trim($cr[1]))] = [
        'code'     => $cr[0],
        'name'     => $cr[1],
        'unit'     => $cr[2],
        'alias'    => $cr[3],
        'category' => $cr[4],
    ];
}

$uniqueMaterials = [];
$jtmConstructions = [];

foreach ($lines as $l) {
    $r = str_getcsv($l);
    if (strpos($r[5], 'JTM') === false) {
        continue;
    }

    $cCode = $r[0];
    $cName = $r[1];
    $mRawName = trim($r[2]);
    $fieldAlias = trim($r[3]);
    $qty = (float)$r[4];

    $matched = $canonMap[strtoupper($mRawName)] ?? null;
    $matCode = $matched ? $matched['code'] : ('MAT-JTM-' . substr(md5($mRawName), 0, 8));
    $matUnit = ($matched && !empty($matched['unit']) && $matched['unit'] !== 'REVIEW_REQUIRED') ? $matched['unit'] : 'buah';
    
    // Canonical rule: individual hardware, insulators, protection items MUST be 'buah'
    if (in_array(strtoupper($matUnit), ['SET', 'BH', 'PCS', 'BTG']) || empty($matUnit)) {
        $matUnit = 'buah';
    }

    if (!isset($uniqueMaterials[$mRawName])) {
        $uniqueMaterials[$mRawName] = [
            'material_code'     => $matCode,
            'nama_material'     => $mRawName,
            'nama_lapangan'     => $fieldAlias ?: ($matched['alias'] ?? ''),
            'satuan'            => $matUnit,
            'material_domain'   => 'JTM',
            'material_category' => $matched['category'] ?? 'HARDWARE',
            'status'            => 'AKTIF',
        ];
    }

    if (!isset($jtmConstructions[$cCode])) {
        $jtmConstructions[$cCode] = [
            'code' => $cCode,
            'name' => $cName,
            'items' => [],
        ];
    }

    $jtmConstructions[$cCode]['items'][] = [
        'raw_material_name' => $mRawName,
        'material_alias'    => $fieldAlias,
        'quantity'          => $qty > 0 ? $qty : 1.0,
        'unit'              => $matUnit,
    ];
}

echo "Total Unique JTM Materials: " . count($uniqueMaterials) . "\n";
echo "Total JTM Constructions: " . count($jtmConstructions) . "\n";

// Save serialized data to scratch/jtm_bom_seed.json
file_put_contents('scratch/jtm_bom_seed.json', json_encode([
    'materials' => array_values($uniqueMaterials),
    'constructions' => $jtmConstructions,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "Saved to scratch/jtm_bom_seed.json\n";
