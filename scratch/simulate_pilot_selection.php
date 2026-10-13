<?php

$candidates = json_decode(file_get_contents(__DIR__ . '/top_feeder_first_20_candidates.json'), true);

echo "=== CANDIDATE FILTERING & PILOT BATCH SELECTION FOR FEEDER 131 ===\n";

$selected = [];
$degrees = [];
$skippedShort = [];
$skippedDegree = [];

foreach ($candidates as $idx => $c) {
    $num = $idx + 1;
    $dist = (float)$c['distance_meters'];
    $src = $c['source_asset_id'];
    $tgt = $c['target_asset_id'];
    
    // Gate: Short distance firewall (< 5.0m)
    if ($dist < 5.0) {
        $skippedShort[] = [
            'num' => $num,
            'key' => $c['natural_key'],
            'pair' => "{$c['source_asset_name']} -> {$c['target_asset_name']}",
            'dist' => $dist,
            'reason' => 'SHORT_SPAN_FIREWALL (<5.0m): Ambiguous equipment/portal/duplicate tag'
        ];
        continue;
    }
    
    // Gate: In-batch degree saturation (max degree 4 per node, and prevent conflicts in pilot)
    $srcDeg = $degrees[$src] ?? 0;
    $tgtDeg = $degrees[$tgt] ?? 0;
    
    if ($srcDeg >= 2 || $tgtDeg >= 2) {
        $skippedDegree[] = [
            'num' => $num,
            'key' => $c['natural_key'],
            'pair' => "{$c['source_asset_name']} -> {$c['target_asset_name']}",
            'dist' => $dist,
            'reason' => "DEGREE_LIMIT in single pilot batch (srcDeg={$srcDeg}, tgtDeg={$tgtDeg})"
        ];
        continue;
    }
    
    // Accept candidate into batch
    $selected[] = $c;
    $degrees[$src] = $srcDeg + 1;
    $degrees[$tgt] = $tgtDeg + 1;
    
    if (count($selected) >= 10) {
        break; // Hard cap of 10 translines
    }
}

echo "\n--- 1. SKIPPED DUE TO SHORT SPAN (< 5.0m) FIREWALL ---\n";
foreach ($skippedShort as $s) {
    printf("[#%02d] %-24s | %-25s | Dist: %5.2fm | %s\n", $s['num'], $s['key'], $s['pair'], $s['dist'], $s['reason']);
}

echo "\n--- 2. SKIPPED DUE TO BATCH INDEPENDENCE / DEGREE OVERLAP ---\n";
foreach ($skippedDegree as $s) {
    printf("[#%02d] %-24s | %-25s | Dist: %5.2fm | %s\n", $s['num'], $s['key'], $s['pair'], $s['dist'], $s['reason']);
}

echo "\n--- 3. SELECTED FOR LIVE PILOT (Max 10 Candidates) ---\n";
printf("%-3s | %-24s | %-25s | %-8s | %-5s | %-6s | %-12s\n",
    "No", "Natural Key", "Asset Pair (Names)", "Dist(m)", "Score", "Bear°", "Type");
echo str_repeat("-", 95) . "\n";

foreach ($selected as $i => $s) {
    $n = $i + 1;
    printf("%-3d | %-24s | %-25s | %-8.2f | %-5d | %-6.1f | %-12s\n",
        $n,
        $s['natural_key'],
        "{$s['source_asset_name']} -> {$s['target_asset_name']}",
        $s['distance_meters'],
        $s['total_score'],
        $s['score_breakdown']['bearing_degrees'] ?? 0,
        $s['candidate_type']
    );
}

echo "\nTotal Selected for Batch: " . count($selected) . "\n";

file_put_contents(__DIR__ . '/pilot_selected_candidates.json', json_encode($selected, JSON_PRETTY_PRINT));
