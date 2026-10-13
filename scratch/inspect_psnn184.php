<?php

$candidates = json_decode(file_get_contents(__DIR__ . '/top_feeder_first_20_candidates.json'), true);

$map = [];
foreach ($candidates as $c) {
    $map[$c['source_asset_code']] = ['name' => $c['source_asset_name'], 'coords' => $c['coordinates'][0]];
    $map[$c['target_asset_code']] = ['name' => $c['target_asset_name'], 'coords' => $c['coordinates'][1]];
}

echo "POLE COORDINATES AROUND PSNN184/185/186:\n";
foreach (['PSNN184', 'PSNN185', 'PSNN186', 'PSNN187'] as $name) {
    foreach ($map as $code => $info) {
        if ($info['name'] === $name) {
            printf("%-10s | %-25s | Lon: %10.6f, Lat: %10.6f\n", $name, $code, $info['coords'][0], $info['coords'][1]);
            break;
        }
    }
}
