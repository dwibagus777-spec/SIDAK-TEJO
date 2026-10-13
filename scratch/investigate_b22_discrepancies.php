<?php
$data = json_decode(file_get_contents(__DIR__ . '/post_commit_forensic_results.json'), true);
$global = $data['checks']['transline_audit_global']['data']['active_edges_summary'];

echo "Total edges in summary: " . count($global) . "\n";

// 1. Inspect IDs
$ids = array_column($global, 'transline_id');
sort($ids);
echo "Min ID: " . min($ids) . "\n";
echo "Max ID: " . max($ids) . "\n";
$newIds = array_filter($ids, fn($id) => $id >= 315 && $id <= 340);
$oldIds = array_filter($ids, fn($id) => !($id >= 315 && $id <= 340));
echo "Total IDs < 315: " . count($oldIds) . "\n";
echo "Total IDs in 315..340: " . count($newIds) . "\n";
echo "Sum: " . (count($oldIds) + count($newIds)) . "\n";

// Show any gap or IDs between 226 and 315
$between = array_filter($ids, fn($id) => $id > 226 && $id < 315);
echo "IDs between 226 and 315: " . implode(', ', $between) . "\n";

// 2. Fetch the full audit JSON from b22 endpoint to inspect the 2 section violations
$b22ResponseFile = __DIR__ . '/b2_2_live_audit_response.json';
if (file_exists($b22ResponseFile)) {
    $b22 = json_decode(file_get_contents($b22ResponseFile), true);
    print_r($b22['forensic_scorecard']['check_1_authoritative_invariant']);
}
