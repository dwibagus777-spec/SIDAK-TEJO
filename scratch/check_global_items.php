<?php

$file = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/resp_analytics_global.json';
$data = json_decode(file_get_contents($file), true);

echo "Total items in resp_analytics_global.json: " . count($data['items'] ?? []) . "\n";
if (!empty($data['items'])) {
    echo "Item 0: " . $data['items'][0]['transline_code'] . " (Feeder: " . $data['items'][0]['nama_penyulang'] . ")\n";
    echo "Item 216: " . $data['items'][216]['transline_code'] . " (Feeder: " . $data['items'][216]['nama_penyulang'] . ")\n";
}
