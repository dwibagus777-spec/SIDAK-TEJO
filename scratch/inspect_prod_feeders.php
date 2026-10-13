<?php

$cookieFile = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/prod_cookie.txt';
$resolveOpt = ['sidaktejo.site:443:91.108.119.5', 'sidaktejo.site:80:91.108.119.5'];

// Check available feeders from /dashboard or /sld or /master-assets
$c = curl_init('https://sidaktejo.site/api/sld/feeder/15/fingerprint');
curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($c, CURLOPT_RESOLVE, $resolveOpt);
curl_setopt($c, CURLOPT_COOKIEFILE, $cookieFile);
$res = curl_exec($c);
curl_close($c);

echo "Feeder 15 FP: " . $res . "\n";

// Let's check other feeders: 1, 2, 3, 4, 5...
for ($i = 1; $i <= 10; $i++) {
    $c = curl_init("https://sidaktejo.site/api/sld/feeder/{$i}/fingerprint");
    curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($c, CURLOPT_RESOLVE, $resolveOpt);
    curl_setopt($c, CURLOPT_COOKIEFILE, $cookieFile);
    $res = curl_exec($c);
    $code = curl_getinfo($c, CURLINFO_HTTP_CODE);
    curl_close($c);
    if ($code === 200) {
        $json = json_decode($res, true);
        echo "Feeder {$i}: assets=" . ($json['active_assets_count'] ?? 0) . ", translines=" . ($json['active_translines_count'] ?? 0) . "\n";
    }
}
