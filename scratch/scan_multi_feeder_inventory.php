<?php

$cookieFile = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/prod_cookie.txt';
$baseUrl = 'https://sidaktejo.site';
$resolveOpt = ['sidaktejo.site:443:91.108.119.5', 'sidaktejo.site:80:91.108.119.5'];

// Read the fresh GLOBAL_NETWORK_TOPOLOGY_COVERAGE.json
$jsonPath = 'e:/XAMPP/htdocs/SIDAK TEJO/GLOBAL_NETWORK_TOPOLOGY_COVERAGE.json';
if (!file_exists($jsonPath)) {
    echo "ERROR: GLOBAL_NETWORK_TOPOLOGY_COVERAGE.json not found!\n";
    exit(1);
}

$data = json_decode(file_get_contents($jsonPath), true);
$feederMatrix = $data['feeder_matrix'] ?? [];

echo "Total Feeders in Matrix: " . count($feederMatrix) . "\n";

$inventory = [];
$activeFeedersWithAssets = [];
$emptyFeeders = [];

foreach ($feederMatrix as $f) {
    $fId = (int)$f['penyulang_id'];
    $fName = $f['nama_penyulang'];
    $ulpName = $f['nama_ulp'];
    $totalAssets = (int)$f['total_jtm_assets'];
    $validGps = (int)$f['valid_gps_assets'];
    $connected = (int)$f['connected_assets'];
    $isolated = (int)$f['isolated_assets'];
    $activeTl = (int)$f['existing_translines'];
    
    // Candidates from matrix
    $auto = (int)($f['auto_complete'] ?? 0);
    $high = (int)($f['high_confidence'] ?? 0);
    $review = (int)($f['review_required'] ?? 0);
    $blocked = (int)($f['blocked'] ?? 0);
    $totalCandidates = $auto + $high + $review + $blocked;

    $completionPct = $totalAssets > 0 ? round(($connected / $totalAssets) * 100, 1) : 0.0;

    $status = 'UNKNOWN';
    if ($totalAssets === 0) {
        $status = 'NO_ASSET';
        $emptyFeeders[] = $fId;
    } elseif ($validGps === 0) {
        $status = 'NO_COORDINATE';
    } elseif ($isolated === 0 || ($totalAssets > 0 && $connected === $totalAssets)) {
        $status = 'COMPLETE';
    } elseif ($auto > 0) {
        $status = 'READY_FOR_AI';
        $activeFeedersWithAssets[] = $f;
    } elseif ($high > 0 || $review > 0) {
        $status = 'NEEDS_REVIEW';
        $activeFeedersWithAssets[] = $f;
    } elseif ($totalCandidates === 0 && $isolated > 0) {
        $status = 'NO_CANDIDATE';
        $activeFeedersWithAssets[] = $f;
    } else {
        $status = $f['opportunity'] ?? 'BLOCKED';
        $activeFeedersWithAssets[] = $f;
    }

    $inventory[] = [
        'feeder_id'             => $fId,
        'feeder_name'           => $fName,
        'ulp'                   => $ulpName,
        'total_assets'          => $totalAssets,
        'assets_with_coords'    => $validGps,
        'connected_assets'      => $connected,
        'isolated_assets'       => $isolated,
        'active_translines'     => $activeTl,
        'candidate_count'       => $totalCandidates,
        'defensible_count'      => $auto,
        'high_confidence_count' => $high,
        'review_count'          => $review,
        'ambiguous_count'       => $blocked,
        'completion_percentage' => $completionPct,
        'status'                => $status,
    ];
}

file_put_contents('scratch/phase_a_inventory.json', json_encode($inventory, JSON_PRETTY_PRINT));
echo "Saved Phase A inventory to scratch/phase_a_inventory.json\n";
echo "Active Feeders with Assets: " . count($activeFeedersWithAssets) . "\n";
echo "Empty Feeders (No Assets): " . count($emptyFeeders) . "\n";

// Display top 15 feeders with most assets
usort($inventory, fn($a, $b) => $b['total_assets'] <=> $a['total_assets']);
echo "\n=== TOP FEEDERS BY ASSET COUNT ===\n";
printf("%-4s | %-25s | %-18s | %-6s | %-6s | %-6s | %-6s | %-6s | %-12s\n",
    "ID", "Feeder Name", "ULP", "Assets", "Conn", "Isol", "Trans", "Defens", "Status");
echo str_repeat("-", 100) . "\n";
foreach (array_slice($inventory, 0, 20) as $row) {
    printf("%-4d | %-25s | %-18s | %-6d | %-6d | %-6d | %-6d | %-6d | %-12s\n",
        $row['feeder_id'],
        substr($row['feeder_name'], 0, 25),
        substr($row['ulp'], 0, 18),
        $row['total_assets'],
        $row['connected_assets'],
        $row['isolated_assets'],
        $row['active_translines'],
        $row['defensible_count'],
        $row['status']
    );
}
