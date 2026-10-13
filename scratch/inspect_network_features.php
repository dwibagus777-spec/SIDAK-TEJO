<?php
$data = json_decode(file_get_contents('https://sidaktejo.site/gis/api-network?penyulang_id=56&zoom=18&layers=JTM'), true);
// wait, use curl with cookie!
$cookieFile = __DIR__ . '/prod_cookie.txt';
$baseUrl = 'https://sidaktejo.site';
$resolveOpt = ['sidaktejo.site:443:91.108.119.5', 'sidaktejo.site:80:91.108.119.5'];

$ch = curl_init($baseUrl . '/gis/api-network?penyulang_id=56&zoom=18&layers=JTM');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RESOLVE, $resolveOpt);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) SIDAK-TEJO-D1-1/1.0',
    'X-Requested-With: XMLHttpRequest',
]);
$res = curl_exec($ch);
curl_close($ch);
$json = json_decode($res, true);
$feats = $json['data']['features'] ?? [];
echo "Total features: " . count($feats) . "\n";
if (!empty($feats)) {
    echo "First 2 features:\n";
    print_r(array_slice($feats, 0, 2));
}
