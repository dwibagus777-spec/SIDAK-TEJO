<?php

$cookieFile = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/prod_cookie.txt';
$baseUrl = 'https://sidaktejo.site';
$resolveOpt = ['sidaktejo.site:443:91.108.119.5', 'sidaktejo.site:80:91.108.119.5'];

// Check which assets have recent updated_at
$ch = curl_init($baseUrl . '/gis/api-network?penyulang_id=15');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RESOLVE, $resolveOpt);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: Mozilla/5.0', 'X-Requested-With: XMLHttpRequest']);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
$res = curl_exec($ch);
curl_close($ch);

$data = json_decode($res, true);
$features = $data['features'] ?? [];

echo "Total features in Feeder 15: " . count($features) . "\n";
foreach ($features as $f) {
    $props = $f['properties'] ?? [];
    if (isset($props['updated_at']) && !empty($props['updated_at'])) {
        if (str_contains($props['updated_at'], date('Y-m-d'))) {
            echo "Asset ID {$props['id']} ({$props['kode_asset']}) updated at: {$props['updated_at']}\n";
        }
    }
}
