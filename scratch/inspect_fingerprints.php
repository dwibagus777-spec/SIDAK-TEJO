<?php
$json = json_decode(file_get_contents('e:/XAMPP/htdocs/SIDAK TEJO/GLOBAL_NETWORK_TOPOLOGY_COVERAGE.json'), true);
echo json_encode($json['fingerprints'], JSON_PRETTY_PRINT);
