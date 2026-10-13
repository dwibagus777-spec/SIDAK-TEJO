<?php

$jsonFile = 'e:/XAMPP/htdocs/SIDAK TEJO/writable/audits/ONE_SHOT_CONSOLIDATED_DRY_RUN_REPORT.json';
$data = json_decode(file_get_contents($jsonFile), true);

$cands = $data['auto_accept_candidates_list'] ?? [];
echo "Total Accepted Candidates in Dry-Run Report: " . count($cands) . "\n";
printf("%-4s | %-20s | %-4s | %-22s -> %-22s | %-7s | %-6s\n", "No", "Natural Key", "FId", "Source", "Target", "Dist(m)", "Conf");
echo str_repeat("-", 95) . "\n";

$totalDist = 0.0;
foreach ($cands as $i => $c) {
    $num = $i + 1;
    $totalDist += (float)$c['distance_meters'];
    printf("%-4d | %-20s | %-4d | %-22s -> %-22s | %-7.2f | %-6.4f\n",
        $num,
        $c['natural_key'],
        $c['penyulang_id'],
        $c['source_asset_name'],
        $c['target_asset_name'],
        $c['distance_meters'],
        $c['confidence']
    );
}

echo str_repeat("-", 95) . "\n";
echo "Total Distance of 26 Candidates: {$totalDist} m (" . round($totalDist / 1000.0, 3) . " km)\n";
