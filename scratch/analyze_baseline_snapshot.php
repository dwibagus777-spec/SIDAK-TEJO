<?php

$file = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/resp_global_topology_audit.json';
$data = json_decode(file_get_contents($file), true);
$matrix = $data['feeder_matrix'] ?? [];

$feedersWithAssets = 0;
$feedersWithoutAssets = 0;
$totalJtmAssets = 0;
$totalExistingTl = 0;
$ulpDist = [];
$activeTlFeeders = [];

foreach ($matrix as $f) {
    $assets = (int)$f['total_jtm_assets'];
    $tl = (int)$f['existing_translines'];
    $uId = (int)$f['ulp_id'];
    $uName = $f['nama_ulp'] ?? "ULP #{$uId}";

    if ($assets > 0) {
        $feedersWithAssets++;
        $totalJtmAssets += $assets;
        $ulpDist[$uName] = ($ulpDist[$uName] ?? 0) + $assets;
    } else {
        $feedersWithoutAssets++;
    }

    if ($tl > 0) {
        $totalExistingTl += $tl;
        $activeTlFeeders[] = [
            'id'    => $f['penyulang_id'],
            'name'  => $f['nama_penyulang'],
            'lines' => $tl
        ];
    }
}

echo "=== GLOBAL FEEDER & ASSET BASELINE SNAPSHOT ===\n";
echo "Total Feeders: " . count($matrix) . "\n";
echo "Feeders with assets: {$feedersWithAssets}\n";
echo "Feeders without assets: {$feedersWithoutAssets}\n";
echo "Total JTM Assets: {$totalJtmAssets}\n";
echo "Total Existing Translines: {$totalExistingTl}\n";

echo "\n--- ULP Asset Distribution ---\n";
foreach ($ulpDist as $u => $cnt) {
    echo sprintf("  %-25s: %d assets\n", $u, $cnt);
}

echo "\n--- Feeders with Existing Translines ---\n";
foreach ($activeTlFeeders as $atf) {
    echo sprintf("  Feeder #%-3d | %-20s: %d lines\n", $atf['id'], $atf['name'], $atf['lines']);
}
