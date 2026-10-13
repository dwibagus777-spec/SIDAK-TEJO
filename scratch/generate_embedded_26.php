<?php

$jsonFile = 'e:/XAMPP/htdocs/SIDAK TEJO/writable/audits/ONE_SHOT_CONSOLIDATED_DRY_RUN_REPORT.json';
$data = json_decode(file_get_contents($jsonFile), true);
$cands = $data['auto_accept_candidates_list'] ?? [];

$export = [];
foreach ($cands as $c) {
    $srcId = (int)$c['source_asset_id'];
    $tgtId = (int)$c['target_asset_id'];
    $fId   = (int)$c['penyulang_id'];
    $minId = min($srcId, $tgtId);
    $maxId = max($srcId, $tgtId);

    $export[] = [
        'natural_key'         => $c['natural_key'],
        'transline_code'      => "TL-{$fId}-{$minId}-{$maxId}",
        'penyulang_id'        => $fId,
        'source_asset_id'     => $minId,
        'target_asset_id'     => $maxId,
        'source_asset_name'   => $c['source_asset_name'],
        'target_asset_name'   => $c['target_asset_name'],
        'source_section_id'   => (int)$c['source_section_id'],
        'target_section_id'   => (int)$c['target_section_id'],
        'distance_meters'     => round((float)$c['distance_meters'], 2),
        'conductor_type'      => $c['conductor_type'] ?? 'AAAC',
        'conductor_size'      => $c['conductor_size'] ?? '150 mm²',
        'confidence'          => (float)$c['confidence'],
        'geometry'            => $c['geometry'],
    ];
}

file_put_contents('e:/XAMPP/htdocs/SIDAK TEJO/scratch/embedded_26_candidates.json', json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "Exported " . count($export) . " candidates to scratch/embedded_26_candidates.json\n";
