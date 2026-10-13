<?php
$cookieFile = __DIR__ . '/prod_cookie.txt';
$baseUrl = 'https://sidaktejo.site';
$resolveOpt = ['sidaktejo.site:443:91.108.119.5', 'sidaktejo.site:80:91.108.119.5'];

$ch = curl_init($baseUrl . '/gis/api-network?penyulang_id=56&zoom=16&layers=JTM');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RESOLVE, $resolveOpt);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) SIDAK-TEJO-D1-1/1.0',
    'X-Requested-With: XMLHttpRequest',
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
$data = json_decode($response, true);
if ($data) {
    echo "Keys: " . implode(', ', array_keys($data['data'] ?? [])) . "\n";
    $nodes = $data['data']['nodes'] ?? $data['data']['assets'] ?? [];
    echo "Nodes count: " . count($nodes) . "\n";
    if (!empty($nodes)) {
        echo "Sample node:\n";
        print_r($nodes[0]);
    }
} else {
    echo substr($response, 0, 500) . "\n";
}
