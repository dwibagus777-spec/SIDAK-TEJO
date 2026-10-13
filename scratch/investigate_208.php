<?php

$cookieFile = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/prod_cookie.txt';
$baseUrl = 'https://sidaktejo.site';
$resolveOpt = ['sidaktejo.site:443:91.108.119.5', 'sidaktejo.site:80:91.108.119.5'];

// Query api-translines on feeder 15 or query all translines to see IDs 207, 208
$ch = curl_init($baseUrl . '/gis/api-translines?penyulang_id=15');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RESOLVE, $resolveOpt);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: Mozilla/5.0', 'X-Requested-With: XMLHttpRequest']);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
$res = curl_exec($ch);
curl_close($ch);

$data = json_decode($res, true);
$tls = $data['data'] ?? [];

echo "Total translines in Feeder 15: " . count($tls) . "\n";
foreach ($tls as $t) {
    if ((int)$t['id'] >= 205) {
        echo "ID " . $t['id'] . ": " . ($t['transline_code'] ?? '') . " | created_by: " . ($t['created_by'] ?? '') . " | created_at: " . ($t['created_at'] ?? '') . " | status: " . ($t['status'] ?? '') . "\n";
    }
}
