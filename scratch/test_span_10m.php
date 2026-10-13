<?php

$data = json_decode(file_get_contents(__DIR__ . '/feeder_131_prod_preview.json'), true);
$candidates = $data['candidates'] ?? [];
$defensible = array_filter($candidates, fn($c) => !empty($c['is_auto_complete']) && !empty($c['gate_valid']));

echo "Total Defensible Candidates: " . count($defensible) . "\n";

$approved10m = [];
$poleDegrees = [];
$rejectedUnder10m = [];

foreach ($defensible as $c) {
    $dist = (float)$c['distance_meters'];
    $src = $c['source_asset_id'];
    $tgt = $c['target_asset_id'];
    $key = $c['natural_key'];
    $pairName = "{$c['source_asset_name']} <-> {$c['target_asset_name']}";
    
    if ($dist < 10.0) {
        $rejectedUnder10m[] = [
            'key' => $key,
            'pair' => $pairName,
            'dist' => $dist,
            'score' => $c['total_score'],
        ];
        continue;
    }
    
    $srcDeg = $poleDegrees[$src] ?? 0;
    $tgtDeg = $poleDegrees[$tgt] ?? 0;
    
    if ($srcDeg >= 1 || $tgtDeg >= 1) {
        continue; // Keep completely independent
    }
    
    $approved10m[] = $c;
    $poleDegrees[$src] = $srcDeg + 1;
    $poleDegrees[$tgt] = $tgtDeg + 1;
    
    if (count($approved10m) === 10) {
        break;
    }
}

echo "=== CANDIDATES UNDER 10.0m (" . count($rejectedUnder10m) . " found) ===\n";
foreach ($rejectedUnder10m as $r) {
    printf("%-24s | %-25s | Dist: %5.2fm | Score: %2d\n", $r['key'], $r['pair'], $r['dist'], $r['score']);
}

echo "\n=== 10 CANONICAL PILOT CANDIDATES (ALL SPANS >= 10.0m, TOTALLY DISJOINT) ===\n";
printf("%-3s | %-24s | %-25s | %-8s | %-5s | %-12s | %-25s\n",
    "No", "Natural Key", "Asset Pair (Names)", "Dist(m)", "Score", "Type", "Conductor");
echo str_repeat("-", 115) . "\n";

foreach ($approved10m as $i => $b) {
    $n = $i + 1;
    printf("%-3d | %-24s | %-25s | %-8.2f | %-5d | %-12s | %s %s\n",
        $n,
        $b['natural_key'],
        "{$b['source_asset_name']} <-> {$b['target_asset_name']}",
        $b['distance_meters'],
        $b['total_score'],
        $b['candidate_type'],
        $b['conductor_type'],
        $b['conductor_size']
    );
}
