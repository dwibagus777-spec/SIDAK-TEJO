<?php

$cookieFile = __DIR__ . '/prod_cookie.txt';
$baseUrl    = 'https://sidaktejo.site';
$resolveOpt = ['sidaktejo.site:443:2.57.91.151', 'sidaktejo.site:80:2.57.91.151'];

echo "====================================================================\n";
echo "  LIVE DEPLOYED WORKSPACE VERIFICATION WITH AUTHENTICATED COOKIE     \n";
echo "====================================================================\n\n";

// 1. Query Live Deployed Workspace Page
$ch = curl_init($baseUrl . '/planning/shutdown-workspace');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RESOLVE, $resolveOpt);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

$html = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "[1/2] Live Workspace Page (/planning/shutdown-workspace):\n";
echo "      HTTP Status Code : {$code}\n";
echo "      HTML Length      : " . strlen($html) . " bytes\n";
echo "      Title Found      : " . (str_contains($html, 'Shutdown Work Planning') ? "🟢 PASS (Title Verified)" : "⚪ Redirected") . "\n\n";

// 2. Query Live Deployed Summary API
$ch = curl_init($baseUrl . '/api/planning/summary');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RESOLVE, $resolveOpt);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);

$apiBody = curl_exec($ch);
$apiCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "[2/2] Live Summary API Endpoint (/api/planning/summary):\n";
echo "      HTTP Status Code : {$apiCode}\n";
echo "      Raw Body         : " . substr((string)$apiBody, 0, 500) . "\n";

echo "\n====================================================================\n";
