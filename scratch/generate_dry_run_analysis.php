<?php

$cookieFile = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/prod_cookie.txt';
$baseUrl = 'https://sidaktejo.site';
$resolveOpt = ['sidaktejo.site:443:91.108.119.5', 'sidaktejo.site:80:91.108.119.5'];

$inv = json_decode(file_get_contents('scratch/full_134_feeder_inventory.json'), true);

// Update Feeder 15 to canonical 197 active translines in inventory
foreach ($inv as &$r) {
    if ($r['feeder_id'] === 15) {
        $r['active_translines'] = 197;
    }
}
unset($r);

echo "=== TOP 10 FEEDERS BY DEFENSIBLE CANDIDATES ===\n";
usort($inv, fn($a, $b) => $b['defensible_count'] <=> $a['defensible_count']);
printf("%-4s | %-25s | %-18s | %-6s | %-6s | %-6s | %-6s | %-8s | %-8s\n",
    "ID", "Feeder Name", "ULP", "Assets", "Conn", "Isol", "ActiveTL", "Defens", "Batches");
echo str_repeat("-", 105) . "\n";
$top10Defensible = array_slice($inv, 0, 10);
foreach ($top10Defensible as $row) {
    $estBatches = ceil($row['defensible_count'] / 10);
    printf("%-4d | %-25s | %-18s | %-6d | %-6d | %-6d | %-6d | %-8d | %-8d\n",
        $row['feeder_id'],
        substr($row['feeder_name'], 0, 25),
        substr($row['ulp'], 0, 18),
        $row['total_assets'],
        $row['connected_assets'],
        $row['isolated_assets'],
        $row['active_translines'],
        $row['defensible_count'],
        $estBatches
    );
}

echo "\n=== TOP 10 FEEDERS BY ISOLATED ASSETS ===\n";
usort($inv, fn($a, $b) => $b['isolated_assets'] <=> $a['isolated_assets']);
printf("%-4s | %-25s | %-18s | %-6s | %-6s | %-6s | %-6s | %-8s\n",
    "ID", "Feeder Name", "ULP", "Assets", "Conn", "Isol", "ActiveTL", "Defens");
echo str_repeat("-", 95) . "\n";
$top10Isolated = array_slice($inv, 0, 10);
foreach ($top10Isolated as $row) {
    printf("%-4d | %-25s | %-18s | %-6d | %-6d | %-6d | %-6d | %-8d\n",
        $row['feeder_id'],
        substr($row['feeder_name'], 0, 25),
        substr($row['ulp'], 0, 18),
        $row['total_assets'],
        $row['connected_assets'],
        $row['isolated_assets'],
        $row['active_translines'],
        $row['defensible_count']
    );
}

// Top 1 Priority Feeder is Feeder #131 (BY PASS) with 381 defensible candidates
$topFeeder = $top10Defensible[0];
$topId = $topFeeder['feeder_id'];
$topName = $topFeeder['feeder_name'];

echo "\n============================================================\n";
echo ">>> FETCHING DETAILED FIRST 20 CANDIDATES FOR TOP FEEDER #{$topId} ({$topName})\n";
echo "============================================================\n";

$ch = curl_init($baseUrl . "/gis/api-transline-ai/preview?penyulang_id={$topId}");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RESOLVE, $resolveOpt);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: Mozilla/5.0', 'X-Requested-With: XMLHttpRequest']);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
$res = curl_exec($ch);
curl_close($ch);

$data = json_decode($res, true);
$candidates = $data['candidates'] ?? [];
$defensibleCandidates = array_values(array_filter($candidates, fn($c) => $c['is_auto_complete'] && $c['gate_valid']));

echo "Total Defensible Candidates retrieved: " . count($defensibleCandidates) . "\n\n";

$first20 = array_slice($defensibleCandidates, 0, 20);
file_put_contents('scratch/top_feeder_first_20_candidates.json', json_encode($first20, JSON_PRETTY_PRINT));

foreach ($first20 as $idx => $c) {
    $num = $idx + 1;
    $sId = $c['source_asset_id'];
    $sCode = $c['source_asset_code'];
    $sName = $c['source_asset_name'];
    $tId = $c['target_asset_id'];
    $tCode = $c['target_asset_code'];
    $tName = $c['target_asset_name'];
    $dist = $c['distance_meters'];
    $bDelta = $c['score_breakdown']['bearing_delta_degrees'] ?? 0;
    $score = $c['total_score'];
    $class = $c['classification'];
    $cType = $c['candidate_type'];
    $evidence = $c['network_evidence']['coherence_summary'] ?? 'N/A';
    
    echo sprintf("[%02d] %s (%s) ──[%.1fm | Δ%s°]──> %s (%s)\n",
        $num, $sCode, $sName, $dist, $bDelta, $tCode, $tName
    );
    echo sprintf("     Score: %d/100 | Type: %s | Class: %s\n", $score, $cType, $class);
    echo sprintf("     Evidence: %s\n", $evidence);
    echo sprintf("     Gate Valid: %s | Reasons: %s\n\n", 
        $c['gate_valid'] ? 'YES' : 'NO', 
        empty($c['gate_reasons']) ? 'ALL_GATES_PASSED' : implode(', ', $c['gate_reasons'])
    );
}
