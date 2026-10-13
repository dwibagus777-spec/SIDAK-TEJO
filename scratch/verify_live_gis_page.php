<?php

$cookieFile = __DIR__ . '/prod_cookie.txt';
$baseUrl = 'https://sidaktejo.site';
$resolveOpt = ['sidaktejo.site:443:91.108.119.5', 'sidaktejo.site:80:91.108.119.5'];

$ch = curl_init($baseUrl . '/gis');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RESOLVE, $resolveOpt);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$html = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status Code: {$code}\n";
echo "HTML Length: " . strlen($html) . " bytes\n";
echo "Checks on Live Production /gis Page:\n";
echo "  1. gisCanvasRenderer: " . (strpos($html, 'gisCanvasRenderer') !== false ? "PASS (Present)" : "FAIL") . "\n";
echo "  2. SIDAK_GIS_NETWORK_CACHE_BY_FEEDER: " . (strpos($html, 'SIDAK_GIS_NETWORK_CACHE_BY_FEEDER') !== false ? "PASS (Present)" : "FAIL") . "\n";
echo "  3. buildNetworkIndexes: " . (strpos($html, 'buildNetworkIndexes') !== false ? "PASS (Present)" : "FAIL") . "\n";
echo "  4. GIS_ICON_CACHE: " . (strpos($html, 'GIS_ICON_CACHE') !== false ? "PASS (Present)" : "FAIL") . "\n";
echo "  5. markerByAssetId: " . (strpos($html, 'markerByAssetId') !== false ? "PASS (Present)" : "FAIL") . "\n";
echo "  6. Active Network Deduplication: " . (strpos($html, 'activeNetworkRequestPromise') !== false ? "PASS (Present)" : "FAIL") . "\n";
echo "  7. Stale Request Abort: " . (strpos($html, 'activeNetworkAbortController') !== false ? "PASS (Present)" : "FAIL") . "\n";
echo "  8. In-Memory Filter Render: " . (strpos($html, 'renderFilteredLayers(false)') !== false ? "PASS (Present)" : "FAIL") . "\n";
