<?php

$timestamp = time();
$routes = [
    "https://sidaktejo.site/asset-corpus/workspace?v={$timestamp}",
    "https://sidaktejo.site/asset-health?v={$timestamp}",
    "https://sidaktejo.site/master-assets?v={$timestamp}"
];

echo "========================================\n";
echo "PRODUCTION SIDEBAR CACHE-BUSTED FORENSIC TEST\n";
echo "========================================\n\n";

foreach ($routes as $url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RESOLVE, ['sidaktejo.site:443:2.57.91.151']);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $hasAssetCorpus = (strpos($html, 'Asset Corpus') !== false);
    $hasHref = (strpos($html, 'asset-corpus/workspace') !== false);

    echo "URL: {$url}\n";
    echo "  - HTTP Status: {$httpCode}\n";
    echo "  - Contains 'Asset Corpus': " . ($hasAssetCorpus ? "YES" : "NO") . "\n";
    echo "  - Contains Link 'asset-corpus/workspace': " . ($hasHref ? "YES" : "NO") . "\n\n";
}

echo "========================================\n";
