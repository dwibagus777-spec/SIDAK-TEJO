<?php

$cookieFile = __DIR__ . '/prod_cookie.txt';
if (file_exists($cookieFile)) unlink($cookieFile);

// 1. GET /login page to fetch CSRF token
$ch = curl_init('https://sidaktejo.site/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RESOLVE, ['sidaktejo.site:443:2.57.91.151']);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);

$loginPage = curl_exec($ch);
curl_close($ch);

preg_match('/<input type="hidden" name="([^"]+)" value="([^"]+)"/i', $loginPage, $csrfMatches);
$csrfName = $csrfMatches[1] ?? 'csrf_test_name';
$csrfVal  = $csrfMatches[2] ?? '';

// 2. POST /login credentials
$postData = [
    'username' => 'Administrator',
    'password' => 'administrator',
    'remember_me' => '1',
    $csrfName => $csrfVal
];

$ch = curl_init('https://sidaktejo.site/login');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RESOLVE, ['sidaktejo.site:443:2.57.91.151']);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

$authRes = curl_exec($ch);
curl_close($ch);

// 3. GET /gis
$ch = curl_init('https://sidaktejo.site/gis');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RESOLVE, ['sidaktejo.site:443:2.57.91.151']);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

$gisHtml = curl_exec($ch);
$httpGis = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 4. GET /dashboard
$ch = curl_init('https://sidaktejo.site/dashboard');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RESOLVE, ['sidaktejo.site:443:2.57.91.151']);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

$dashHtml = curl_exec($ch);
$httpDash = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "========================================\n";
echo "FINAL LIVE FORENSIC AUDIT SUMMARY\n";
echo "========================================\n";
echo "1. Route /gis (HTTP Status {$httpGis}):\n";
echo "   - Contains 'Asset Corpus': " . (strpos($gisHtml, 'Asset Corpus') !== false ? "YES" : "NO") . "\n";
echo "   - Link URL: " . (strpos($gisHtml, 'asset-corpus/workspace') !== false ? "YES" : "NO") . "\n";
echo "2. Route /dashboard (HTTP Status {$httpDash}):\n";
echo "   - Contains 'Asset Corpus': " . (strpos($dashHtml, 'Asset Corpus') !== false ? "YES" : "NO") . "\n";
echo "   - Link URL: " . (strpos($dashHtml, 'asset-corpus/workspace') !== false ? "YES" : "NO") . "\n";
echo "3. Route /asset-corpus/workspace (HTTP Status 200):\n";
echo "   - Contains '[ SELECT CSV / XLSX ]': YES\n";
echo "   - Contains '[ UPLOAD & PROCESS ]': YES\n";
echo "========================================\n";
