<?php

$file = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/resp_global_topology_audit.json';
$raw = file_get_contents($file);
$data = json_decode($raw, true);

echo "Status: " . ($data['status'] ?? 'N/A') . "\n";
echo "Phase: " . ($data['phase'] ?? 'N/A') . "\n";
echo "Timestamp: " . ($data['timestamp'] ?? 'N/A') . "\n";
echo "Duration ms: " . ($data['duration_ms'] ?? 'N/A') . "\n";

$fps = $data['fingerprints']['post'] ?? $data['fingerprints']['pre'] ?? [];
echo "\n=== PRODUCTION TABLE ROW COUNTS ===\n";
foreach ($fps as $tbl => $info) {
    echo sprintf("%-25s: %d rows (SHA256: %s)\n", $tbl, $info['count'], substr($info['sha256'], 0, 16) . '...');
}

$summary = $data['global_summary'] ?? [];
echo "\n=== GLOBAL SUMMARY ===\n";
foreach ($summary as $k => $v) {
    echo sprintf("  %-35s: %s\n", $k, is_bool($v) ? ($v ? 'true' : 'false') : $v);
}
