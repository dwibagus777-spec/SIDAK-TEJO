<?php
$baseUrl = 'https://sidaktejo.site/master-assets/b22-production-lock';
$cookieFile = __DIR__ . '/prod_cookie.txt';
$key = 'sidak_transline_audit_2026';

$url = $baseUrl . '?key=' . urlencode($key);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);

$startTime = microtime(true);
$res = curl_exec($ch);
$elapsedMs = round((microtime(true) - $startTime) * 1000, 2);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "Elapsed: {$elapsedMs} ms\n";
if ($err) {
    echo "CURL Error: $err\n";
}

$json = json_decode((string)$res, true);
if ($json) {
    echo "Report Title: " . ($json['audit_metadata']['report_title'] ?? 'unknown') . "\n";
    echo "Phase: " . ($json['audit_metadata']['phase'] ?? 'unknown') . "\n";
    echo "Overall Verdict: " . ($json['audit_metadata']['overall_verdict'] ?? 'unknown') . "\n";
    echo "Read-Only Verified: " . ($json['audit_metadata']['read_only_verified'] ? 'TRUE' : 'FALSE') . "\n";
    echo "Database Mutations: " . ($json['audit_metadata']['database_mutations'] ?? 'n/a') . "\n";
    
    echo "\n=== 7 FORENSIC SCORECARD ===\n";
    foreach ($json['forensic_scorecard'] as $k => $c) {
        echo " - [{$c['status']}] {$c['name']}\n";
    }

    echo "\n=== DETAIL METRICS ===\n";
    $c1 = $json['forensic_scorecard']['check_1_authoritative_invariant'];
    echo "1. Invariant: Total={$c1['total_active_translines']}, Old={$c1['old_preserved_count']}, New={$c1['new_active_count']}, Formula='{$c1['invariant_formula']}'\n";

    $c2 = $json['forensic_scorecard']['check_2_fingerprint_uniqueness'];
    echo "2. Fingerprint: Rows={$c2['total_rows_evaluated']}, Distinct={$c2['distinct_fingerprints']}, Collisions={$c2['collision_count']}\n";

    $c3 = $json['forensic_scorecard']['check_3_spatial_integrity'];
    echo "3. Spatial: ZeroDist={$c3['zero_distance_count']}, SelfLoops={$c3['self_loop_count']}, InvalidCoords={$c3['invalid_coords_count']}, TotalDist={$c3['total_distance_m']}m ({$c3['total_distance_km']}km)\n";

    $c4 = $json['forensic_scorecard']['check_4_graph_boundary_integrity'];
    $newViol = $c4['new_ingested_boundary_violations'] ?? 0;
    $legSwitch = $c4['legacy_boundary_switch_edges'] ?? 0;
    echo "4. Boundary: CrossFeeder={$c4['cross_feeder_violations']}, CrossULP={$c4['cross_ulp_violations']}, NewIngestedViolations={$newViol}, LegacySwitchEdges={$legSwitch}\n";

    $c5 = $json['forensic_scorecard']['check_5_asset_immutability'];
    echo "5. Assets: ActiveCount={$c5['total_active_assets']}, Touched=" . ($c5['parent_asset_id_touched'] ? 'YES' : 'NO') . ", Delta={$c5['delta_assets_table']}\n";

    $c6 = $json['forensic_scorecard']['check_6_gis_network_truth_feeder_118'];
    echo "6. Feeder 118: OfficialCount={$c6['authoritative_transline_count']}, TL={$c6['transline_id']} ({$c6['transline_code']}), Dist={$c6['span_length_meters']}m, Tiang20-21={$c6['tiang_20_to_21_in_db']}\n";

    $c7 = $json['forensic_scorecard']['check_7_conductor_analytics_canonical'];
    echo "7. Conductor: GlobalOfficial={$c7['global_official_count']}, GlobalPanjangM={$c7['global_total_panjang_m']}m, F118Official={$c7['feeder_118_official_count']}, F118PanjangM={$c7['feeder_118_total_panjang_m']}m\n";

    echo "\n=== GOVERNANCE QUEUES RETAINED ===\n";
    print_r($json['governance_queues_retained']);

    echo "\n=== NEXT PHASE GATE ===\n";
    print_r($json['next_phase_gate']);

    // Save report to audits
    file_put_contents(__DIR__ . '/../writable/audits/B2_2_PRODUCTION_LOCK_VERIFICATION_REPORT.json', json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    file_put_contents(__DIR__ . '/b2_2_live_audit_response.json', json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
} else {
    echo "Raw Response:\n$res\n";
}
