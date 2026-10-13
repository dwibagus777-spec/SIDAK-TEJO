<?php
$data = json_decode(file_get_contents(__DIR__ . '/post_commit_forensic_results.json'), true);
$cond118 = $data['checks']['conductor_analytics_118']['data'];
$condGlobal = $data['checks']['conductor_analytics_global']['data'];

echo "=== COND 118 SUMMARY ===\n";
print_r($cond118['summary']);

echo "=== COND 118 BREAKDOWN ===\n";
print_r($cond118['breakdown']);

echo "\n=== COND GLOBAL SUMMARY ===\n";
print_r($condGlobal['summary']);

echo "=== COND GLOBAL BREAKDOWN (Conductor types) ===\n";
foreach ($condGlobal['breakdown'] as $b) {
    echo " - {$b['conductor_type']} ({$b['conductor_size']}): count={$b['count']}, length_km={$b['length_km']} km ({$b['length_meter']} m)\n";
}
