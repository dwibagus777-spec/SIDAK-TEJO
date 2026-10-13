<?php
$cookieFile = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/prod_cookie.txt';
$baseUrl = 'https://sidaktejo.site';
$resolveOpt = ['sidaktejo.site:443:91.108.119.5', 'sidaktejo.site:80:91.108.119.5'];

$ch = curl_init($baseUrl . '/gis/api-global-topology-audit');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RESOLVE, $resolveOpt);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: Mozilla/5.0', 'X-Requested-With: XMLHttpRequest']);
curl_setopt($ch, CURLOPT_TIMEOUT, 120);

$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code !== 200) {
    echo "HTTP {$code}: {$res}\n";
    exit(1);
}

$data = json_decode($res, true);
$fps = $data['fingerprints']['post'] ?? [];

echo json_encode($fps, JSON_PRETTY_PRINT);
file_put_contents('scratch/pre_highzoom_fingerprint.json', json_encode($fps, JSON_PRETTY_PRINT));
echo "\nSaved pre-fingerprint to scratch/pre_highzoom_fingerprint.json\n";
