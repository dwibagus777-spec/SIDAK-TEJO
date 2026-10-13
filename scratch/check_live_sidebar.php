<?php

$ch = curl_init('https://sidaktejo.site/asset-corpus/workspace');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RESOLVE, ['sidaktejo.site:443:2.57.91.151']);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

$html = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "========================================\n";
echo "LIVE PRODUCTION SIDEBAR PROOF\n";
echo "========================================\n";
echo "Target URL: https://sidaktejo.site/asset-corpus/workspace\n";
echo "HTTP Response Status: " . $httpCode . "\n\n";

$pos = strpos($html, 'group-data');
if ($pos !== false) {
    echo "--- SIDEBAR DATA & MASTER REFERENSI HTML ---\n";
    echo substr($html, $pos - 50, 800) . "\n";
} else {
    echo "group-data not found in HTML\n";
}
echo "========================================\n";
