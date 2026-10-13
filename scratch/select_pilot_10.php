<?php

$data = json_decode(file_get_contents(__DIR__ . '/feeder_131_prod_preview.json'), true);
$candidates = $data['candidates'] ?? [];

echo "Total Candidates in preview: " . count($candidates) . "\n";

// Filter only auto_complete + gate_valid
$defensible = array_filter($candidates, fn($c) => !empty($c['is_auto_complete']) && !empty($c['gate_valid']));
echo "Total Defensible Candidates: " . count($defensible) . "\n\n";

// Apply user's hard firewall: distance >= 5.0m
$approvedBatch = [];
$poleDegrees = [];
$rejectedShort = [];
$rejectedDegreeConflict = [];

foreach ($defensible as $c) {
    $dist = (float)$c['distance_meters'];
    $src = $c['source_asset_id'];
    $tgt = $c['target_asset_id'];
    $key = $c['natural_key'];
    $pairName = "{$c['source_asset_name']} <-> {$c['target_asset_name']}";
    
    if ($dist < 5.0) {
        $rejectedShort[] = [
            'key' => $key,
            'pair' => $pairName,
            'dist' => $dist,
            'score' => $c['total_score'],
            'reason' => 'FIREWALL: Distance < 5.0m (ambiguous tag/equipment)'
        ];
        continue;
    }
    
    // Check if adding this edge causes degree > 2 or conflict in pilot batch
    $srcDeg = $poleDegrees[$src] ?? 0;
    $tgtDeg = $poleDegrees[$tgt] ?? 0;
    
    // For maximum safety in this pilot batch, we want completely independent edges (degree <= 1 per node in this batch, or max 2 for true continuous chain)
    if ($srcDeg >= 1 || $tgtDeg >= 1) {
        $rejectedDegreeConflict[] = [
            'key' => $key,
            'pair' => $pairName,
            'dist' => $dist,
            'score' => $c['total_score'],
            'reason' => "PILOT_SAFETY: Pole already has edge in this batch (srcDeg={$srcDeg}, tgtDeg={$tgtDeg})"
        ];
        continue;
    }
    
    $approvedBatch[] = $c;
    $poleDegrees[$src] = $srcDeg + 1;
    $poleDegrees[$tgt] = $tgtDeg + 1;
    
    if (count($approvedBatch) === 10) {
        break; // Stop at exactly 10
    }
}

echo "=== 1. REJECTED SHORT SPANS (< 5.0m) ===\n";
foreach ($rejectedShort as $r) {
    printf("%-24s | %-25s | Dist: %5.2fm | Score: %2d | %s\n", $r['key'], $r['pair'], $r['dist'], $r['score'], $r['reason']);
}

echo "\n=== 2. HELD FOR INDEPENDENT BATCHING (degree > 0 in this batch) ===\n";
foreach (array_slice($rejectedDegreeConflict, 0, 10) as $r) {
    printf("%-24s | %-25s | Dist: %5.2fm | Score: %2d | %s\n", $r['key'], $r['pair'], $r['dist'], $r['score'], $r['reason']);
}

echo "\n=== 3. 10 CANONICAL CANDIDATES SELECTED FOR PHASE C PILOT ===\n";
printf("%-3s | %-24s | %-25s | %-8s | %-5s | %-12s | %-25s\n",
    "No", "Natural Key", "Asset Pair (Names)", "Dist(m)", "Score", "Type", "Conductor");
echo str_repeat("-", 115) . "\n";

foreach ($approvedBatch as $i => $b) {
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

file_put_contents(__DIR__ . '/pilot_10_approved_candidates.json', json_encode($approvedBatch, JSON_PRETTY_PRINT));
