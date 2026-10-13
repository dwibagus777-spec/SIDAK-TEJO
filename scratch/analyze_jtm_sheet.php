<?php
$csvFile = 'C:/Users/INSPEKSIKOTA/.gemini/antigravity/brain/e5e83a3d-4509-4271-8129-9355c979dd3c/.system_generated/steps/25942/content.md';
$lines = file($csvFile, FILE_IGNORE_NEW_LINES);

// Parse the fetched Google Sheet KONSTRUKSI JTM
$currentConstruction = null;
$constructions = [];

foreach ($lines as $line) {
    $line = trim($line);
    if (empty($line) || str_starts_with($line, 'Title:') || str_starts_with($line, 'Description:') || str_starts_with($line, 'Source:') || str_starts_with($line, '---')) {
        continue;
    }
    $row = str_getcsv($line);
    $col0 = trim($row[0] ?? '');
    $col1 = trim($row[1] ?? '');

    if (str_starts_with($col0, 'KONSTRUKSI ')) {
        $currentConstruction = trim(substr($col0, strlen('KONSTRUKSI ')));
        $constructions[$currentConstruction] = [];
        continue;
    }

    if ($col0 === 'NAMA MATERIAL' || $col0 === '') {
        continue;
    }

    if ($currentConstruction) {
        $constructions[$currentConstruction][] = [
            'material' => $col0,
            'field_name' => $col1,
        ];
    }
}

echo "Total constructions parsed: " . count($constructions) . "\n\n";

$allMaterials = [];
foreach ($constructions as $cCode => $items) {
    echo "Construction: [{$cCode}] -> " . count($items) . " items\n";
    foreach ($items as $it) {
        $mat = $it['material'];
        $field = $it['field_name'];
        if (!isset($allMaterials[$mat])) {
            $allMaterials[$mat] = [
                'field_names' => [],
                'constructions' => [],
            ];
        }
        if ($field && !in_array($field, $allMaterials[$mat]['field_names'])) {
            $allMaterials[$mat]['field_names'][] = $field;
        }
        $allMaterials[$mat]['constructions'][] = $cCode;
    }
}

echo "\nTotal unique materials in KONSTRUKSI JTM: " . count($allMaterials) . "\n";
