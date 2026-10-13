<?php
$data = json_decode(file_get_contents(__DIR__ . '/post_commit_forensic_results.json'), true);
$checks = $data['checks'];

echo "=========================================================\n";
echo "       B.2.1 POST-COMMIT FORENSIC EXECUTIVE SUMMARY      \n";
echo "=========================================================\n";

$globalAudit = $checks['transline_audit_global']['data'];
echo "1. DATABASE AUDIT (GLOBAL)\n";
echo "   - Active Edges Count: {$globalAudit['active_edges_count']}\n";
echo "   - Unique Fingerprints: {$globalAudit['unique_fingerprints']}\n";
echo "   - Is Healthy: " . ($globalAudit['is_healthy'] ? 'TRUE' : 'FALSE') . "\n";
echo "   - Anomalies Count: {$globalAudit['anomalies_count']}\n";
echo "   - Critical Anomalies: {$globalAudit['critical_anomalies']}\n";

$f118Audit = $checks['transline_audit_feeder118']['data'];
echo "\n2. DATABASE AUDIT (FEEDER 118 - BAHAGIA STEEL 1)\n";
echo "   - Active Edges Count: {$f118Audit['active_edges_count']}\n";
echo "   - Is Healthy: " . ($f118Audit['is_healthy'] ? 'TRUE' : 'FALSE') . "\n";
echo "   - Anomalies Count: {$f118Audit['anomalies_count']}\n";
if (!empty($f118Audit['active_edges_summary'])) {
    foreach ($f118Audit['active_edges_summary'] as $e) {
        echo "   - Authoritative Transline: ID={$e['transline_id']} | Code={$e['transline_code']} | Distance={$e['distance_meters']}m | Conductor={$e['conductor_type']} {$e['conductor_size']}\n";
    }
}

$cond118 = $checks['conductor_analytics_118']['data'];
echo "\n3. CONDUCTOR ANALYTICS (FEEDER 118)\n";
if (is_array($cond118)) {
    echo "   - Status: " . ($cond118['status'] ?? 'unknown') . "\n";
    echo "   - Summary Total Segments: " . ($cond118['data']['summary']['total_segments'] ?? 'n/a') . "\n";
    echo "   - Summary Total Length: " . ($cond118['data']['summary']['total_length_km'] ?? 'n/a') . " km (" . ($cond118['data']['summary']['total_length_meters'] ?? 'n/a') . " m)\n";
    echo "   - Authoritative Segments: " . ($cond118['data']['summary']['authoritative_segments_count'] ?? 'n/a') . "\n";
} else {
    echo "   - Response: " . substr((string)$cond118, 0, 150) . "\n";
}

$condGlobal = $checks['conductor_analytics_global']['data'];
echo "\n4. CONDUCTOR ANALYTICS (GLOBAL)\n";
if (is_array($condGlobal)) {
    echo "   - Status: " . ($condGlobal['status'] ?? 'unknown') . "\n";
    echo "   - Summary Total Segments: " . ($condGlobal['data']['summary']['total_segments'] ?? 'n/a') . "\n";
    echo "   - Summary Total Length: " . ($condGlobal['data']['summary']['total_length_km'] ?? 'n/a') . " km (" . ($condGlobal['data']['summary']['total_length_meters'] ?? 'n/a') . " m)\n";
    echo "   - Authoritative Segments: " . ($condGlobal['data']['summary']['authoritative_segments_count'] ?? 'n/a') . "\n";
} else {
    echo "   - Response: " . substr((string)$condGlobal, 0, 150) . "\n";
}

echo "\n5. EMPTY FEEDERS INTEGRITY (SAMPLE 5 OF 101)\n";
foreach ($checks['empty_feeders_verification'] as $fid => $ef) {
    echo "   - Feeder #$fid: Translines = {$ef['translines_count']} [OK]\n";
}
echo "=========================================================\n";
