<?php
$json = json_decode(file_get_contents('e:/XAMPP/htdocs/SIDAK TEJO/GLOBAL_NETWORK_TOPOLOGY_COVERAGE.json'), true);
echo "Keys: " . implode(', ', array_keys($json)) . "\n";
echo "Global summary: " . json_encode($json['global_summary'], JSON_PRETTY_PRINT) . "\n";
echo "Feeder 15 Matrix: " . json_encode($json['feeder_matrix'][14] ?? $json['feeder_matrix'][15] ?? [], JSON_PRETTY_PRINT) . "\n";
