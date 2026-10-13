<?php

$data = json_decode(file_get_contents(__DIR__ . '/feeder_131_prod_preview.json'), true);
foreach ($data['candidates'] ?? [] as $c) {
    if ($c['natural_key'] === 'TL-NAT:131:6129-6261') {
        echo json_encode($c, JSON_PRETTY_PRINT);
        break;
    }
}
