<?php
$json = json_decode(file_get_contents('e:/XAMPP/htdocs/SIDAK TEJO/GLOBAL_NETWORK_TOPOLOGY_COVERAGE.json'), true);

$ulpStats = [];
foreach ($json['feeder_matrix'] as $f) {
    $u = $f['nama_ulp'];
    if (!isset($ulpStats[$u])) {
        $ulpStats[$u] = [
            'total_feeders' => 0,
            'feeders_with_assets' => 0,
            'feeders_empty' => 0,
            'total_assets' => 0,
            'existing_translines' => 0,
            'connected_assets' => 0,
            'isolated_assets' => 0,
            'high_confidence' => 0,
            'review_required' => 0,
            'blocked' => 0,
        ];
    }
    $ulpStats[$u]['total_feeders']++;
    if ($f['total_jtm_assets'] > 0) {
        $ulpStats[$u]['feeders_with_assets']++;
    } else {
        $ulpStats[$u]['feeders_empty']++;
    }
    $ulpStats[$u]['total_assets'] += $f['total_jtm_assets'];
    $ulpStats[$u]['existing_translines'] += $f['existing_translines'];
    $ulpStats[$u]['connected_assets'] += $f['connected_assets'];
    $ulpStats[$u]['isolated_assets'] += $f['isolated_assets'];
    $ulpStats[$u]['high_confidence'] += $f['high_confidence'];
    $ulpStats[$u]['review_required'] += $f['review_required'];
    $ulpStats[$u]['blocked'] += $f['blocked'];
}

echo "ULP SUMMARY:\n";
echo json_encode($ulpStats, JSON_PRETTY_PRINT);
