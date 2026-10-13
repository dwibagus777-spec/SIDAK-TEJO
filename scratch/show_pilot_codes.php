<?php

$candidates = json_decode(file_get_contents(__DIR__ . '/top_feeder_first_20_candidates.json'), true);

echo "Total Candidates in file: " . count($candidates) . "\n\n";

printf("%-4s | %-24s | %-12s -> %-12s | %-7s | %-5s | %-20s | %-15s\n",
    "No", "Natural Key", "Source Name", "Target Name", "Dist(m)", "Score", "Type", "Status");
echo str_repeat("-", 105) . "\n";

foreach ($candidates as $i => $c) {
    $num = $i + 1;
    $dist = (float)$c['distance_meters'];
    $shortFlag = ($dist < 5.0) ? "SHORT (<5m)" : "OK";
    
    printf("%-4d | %-24s | %-12s -> %-12s | %-7.2f | %-5d | %-20s | %-15s\n",
        $num,
        $c['natural_key'],
        $c['source_asset_name'],
        $c['target_asset_name'],
        $dist,
        $c['total_score'],
        $c['candidate_type'],
        $shortFlag
    );
}
