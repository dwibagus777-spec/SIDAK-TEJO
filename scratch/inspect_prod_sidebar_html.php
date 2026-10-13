<?php

$cookieFile = __DIR__ . '/prod_cookie.txt';

// GET /dashboard
$ch = curl_init('https://sidaktejo.site/dashboard');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RESOLVE, ['sidaktejo.site:443:2.57.91.151']);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

$html = curl_exec($ch);
curl_close($ch);

echo "========================================\n";
echo "PROD DASHBOARD SIDEBAR FORENSIC DUMP\n";
echo "========================================\n";

$pos = strpos($html, 'DATA &amp; MASTER REFERENSI');
if ($pos === false) {
    $pos = strpos($html, 'DATA & MASTER REFERENSI');
}

if ($pos !== false) {
    echo "Found DATA & MASTER REFERENSI at pos {$pos}!\n\n";
    $snippet = substr($html, $pos - 100, 1500);
    echo $snippet . "\n";
} else {
    echo "DATA & MASTER REFERENSI NOT FOUND IN HTML!\n";
    echo "First 1000 chars of response:\n";
    echo substr($html, 0, 1000) . "\n";
}
echo "========================================\n";
