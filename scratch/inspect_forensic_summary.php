<?php
$data = json_decode(file_get_contents(__DIR__ . '/post_commit_forensic_results.json'), true);
$checks = $data['checks'];

echo "=== GLOBAL TRANSLINE AUDIT ===\n";
echo "Active edges count: " . $checks['transline_audit_global']['data']['active_edges_count'] . "\n";
echo "Total transline rows: " . $checks['transline_audit_global']['data']['total_transline_rows'] . "\n";
echo "Is healthy: " . ($checks['transline_audit_global']['data']['is_healthy'] ? 'YES' : 'NO') . "\n";
echo "Anomalies count: " . $checks['transline_audit_global']['data']['anomalies_count'] . "\n";

echo "\n=== FEEDER 118 TRANSLINE AUDIT ===\n";
$f118 = $checks['transline_audit_feeder118']['data'] ?? [];
echo "Feeder 118 active edges count: " . ($f118['active_edges_count'] ?? '0') . "\n";
echo "Feeder 118 is healthy: " . (($f118['is_healthy'] ?? false) ? 'YES' : 'NO') . "\n";
if (!empty($f118['active_edges_summary'])) {
    foreach ($f118['active_edges_summary'] as $edge) {
        echo " - Edge ID: {$edge['transline_id']} | Code: {$edge['transline_code']} | Distance: {$edge['distance_meters']}m | Conductor: {$edge['conductor_type']} {$edge['conductor_size']}\n";
    }
}

echo "\n=== CONDUCTOR ANALYTICS 118 ===\n";
print_r($checks['conductor_analytics_118']);

echo "\n=== CONDUCTOR ANALYTICS GLOBAL ===\n";
print_r($checks['conductor_analytics_global']);

echo "\n=== EMPTY FEEDERS ===\n";
print_r($checks['empty_feeders_verification']);
