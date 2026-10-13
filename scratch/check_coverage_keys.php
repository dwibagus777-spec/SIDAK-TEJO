<?php

$data = json_decode(file_get_contents('e:/XAMPP/htdocs/SIDAK TEJO/GLOBAL_NETWORK_TOPOLOGY_COVERAGE.json'), true);
echo "Coverage keys: " . implode(', ', array_keys($data)) . "\n";
if (isset($data['feeder_matrix'][0])) {
    echo "First feeder matrix keys: " . implode(', ', array_keys($data['feeder_matrix'][0])) . "\n";
}
