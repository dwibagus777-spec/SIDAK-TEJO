<?php

$cookieFile = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/prod_cookie.txt';
$baseUrl = 'https://sidaktejo.site';
$resolveOpt = ['sidaktejo.site:443:91.108.119.5', 'sidaktejo.site:80:91.108.119.5'];

// 1. Get list of all 134 feeders from GLOBAL_NETWORK_TOPOLOGY_COVERAGE.json
$covJson = json_decode(file_get_contents('e:/XAMPP/htdocs/SIDAK TEJO/GLOBAL_NETWORK_TOPOLOGY_COVERAGE.json'), true);
$feederMatrix = $covJson['feeder_matrix'] ?? [];

echo "Starting Read-Only Global Scan of all 134 Feeders via Engine Preview...\n";

$fullInventory = [];
$t0 = microtime(true);

foreach ($feederMatrix as $idx => $f) {
    $fId = (int)$f['penyulang_id'];
    $fName = $f['nama_penyulang'];
    $ulpName = $f['nama_ulp'];
    $totalAssets = (int)$f['total_jtm_assets'];
    $validGps = (int)$f['valid_gps_assets'];
    $existingTl = (int)$f['existing_translines'];
    $connected = (int)$f['connected_assets'];
    $isolated = (int)$f['isolated_assets'];

    if ($totalAssets === 0) {
        $fullInventory[] = [
            'feeder_id'               => $fId,
            'feeder_name'             => $fName,
            'ulp'                     => $ulpName,
            'total_assets'            => 0,
            'assets_with_coordinates' => 0,
            'connected_assets'        => 0,
            'isolated_assets'         => 0,
            'active_translines'       => 0,
            'candidate_count'         => 0,
            'defensible_count'        => 0,
            'high_confidence_count'   => 0,
            'review_count'            => 0,
            'ambiguous_count'         => 0,
            'completion_percentage'   => 0.0,
            'status'                  => 'NO_ASSET',
            'priority_rank'           => 999
        ];
        continue;
    }

    // Call preview endpoint for this feeder
    $ch = curl_init($baseUrl . "/gis/api-transline-ai/preview?penyulang_id={$fId}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RESOLVE, $resolveOpt);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: Mozilla/5.0', 'X-Requested-With: XMLHttpRequest']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200) {
        echo "ERR Feeder #{$fId} ({$fName}): HTTP {$code}\n";
        continue;
    }

    $pData = json_decode($res, true);
    $inv = $pData['inventory'] ?? [];
    $sum = $pData['summary'] ?? [];

    $mAssets = (int)($inv['total_master_assets'] ?? $totalAssets);
    $mConnected = (int)($inv['connected_assets_count'] ?? $connected);
    $mIsolated = (int)($inv['isolated_assets_count'] ?? $isolated);
    $mActiveTl = (int)($inv['authoritative_translines'] ?? $existingTl);
    $cTotal = (int)($sum['total_candidates_analyzed'] ?? 0);
    $cDefensible = (int)($sum['defensible_count'] ?? 0);
    $cHighConf = (int)($sum['high_confidence_review'] ?? 0);
    $cReview = (int)($sum['review_required'] ?? 0);
    $cBlocked = (int)($sum['ambiguous_blocked'] ?? 0);

    $completionPct = $mAssets > 0 ? round(($mConnected / $mAssets) * 100, 1) : 0.0;

    $status = 'READY_FOR_AI';
    if ($mIsolated === 0) {
        $status = 'COMPLETE';
    } elseif ($cDefensible === 0 && $mIsolated <= 5 && $mConnected > 0) {
        $status = 'NATURALLY_STABILIZED';
    } elseif ($cDefensible > 0) {
        $status = 'READY_FOR_AI';
    } elseif ($cHighConf > 0 || $cReview > 0) {
        $status = 'NEEDS_REVIEW';
    } else {
        $status = 'NO_CANDIDATE';
    }

    $fullInventory[] = [
        'feeder_id'               => $fId,
        'feeder_name'             => $fName,
        'ulp'                     => $ulpName,
        'total_assets'            => $mAssets,
        'assets_with_coordinates' => $validGps,
        'connected_assets'        => $mConnected,
        'isolated_assets'         => $mIsolated,
        'active_translines'       => $mActiveTl,
        'candidate_count'         => $cTotal,
        'defensible_count'        => $cDefensible,
        'high_confidence_count'   => $cHighConf,
        'review_count'            => $cReview,
        'ambiguous_count'         => $cBlocked,
        'completion_percentage'   => $completionPct,
        'status'                  => $status,
    ];

    printf("[%02d/33] Feeder #%-3d %-22s: Assets=%-3d | Conn=%-3d | Isol=%-3d | ActiveTL=%-3d | Defens=%-3d | Status=%s\n",
        count(array_filter($fullInventory, fn($i) => $i['total_assets'] > 0)),
        $fId,
        substr($fName, 0, 22),
        $mAssets,
        $mConnected,
        $mIsolated,
        $mActiveTl,
        $cDefensible,
        $status
    );
}

$elapsed = round(microtime(true) - $t0, 2);
echo "\nCompleted scanning all 134 feeders in {$elapsed} seconds!\n";

// Sort by Priority Queue:
// 1. Defensible count desc
// 2. High confidence count desc
// 3. Asset count desc
usort($fullInventory, function ($a, $b) {
    if ($a['defensible_count'] !== $b['defensible_count']) {
        return $b['defensible_count'] <=> $a['defensible_count'];
    }
    if ($a['high_confidence_count'] !== $b['high_confidence_count']) {
        return $b['high_confidence_count'] <=> $a['high_confidence_count'];
    }
    return $b['total_assets'] <=> $a['total_assets'];
});

// Assign priority rank
foreach ($fullInventory as $rank => &$row) {
    $row['priority_rank'] = $rank + 1;
}
unset($row);

file_put_contents('scratch/full_134_feeder_inventory.json', json_encode($fullInventory, JSON_PRETTY_PRINT));
echo "Saved complete 134-feeder inventory to scratch/full_134_feeder_inventory.json\n";
