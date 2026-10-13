<?php

$cookieFile = __DIR__ . '/cookie.txt';
if (file_exists($cookieFile)) {
    unlink($cookieFile);
}

$baseUrl = "https://localhost/SIDAK%20TEJO/public";

$ch = curl_init("{$baseUrl}/login");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

$html = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

preg_match('/name="csrf_test_name"\s+value="([^"]+)"/i', $html, $m);
$csrf = $m[1] ?? '';
echo "1. Fetched Login Page via HTTPS: HTTP {$httpCode}, CSRF Token: " . ($csrf ? substr($csrf, 0, 10) . '...' : 'NONE') . PHP_EOL;

if ($csrf) {
    // 2. Perform AJAX login
    curl_setopt($ch, CURLOPT_URL, "{$baseUrl}/login");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-Requested-With: XMLHttpRequest',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'csrf_test_name' => $csrf,
        'username'       => 'admin',
        'password'       => 'admin123',
    ]));
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    $loginRes = curl_exec($ch);
    $loginCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    echo "2. AJAX Login Result: HTTP {$loginCode}, Body: " . $loginRes . PHP_EOL;

    // 3. Test GET /gis/api-proposal-exception-queue (Real API via HTTPS)
    curl_setopt($ch, CURLOPT_URL, "{$baseUrl}/gis/api-proposal-exception-queue");
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json'
    ]);
    $queueRes = curl_exec($ch);
    $queueCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    echo "3. Real GET /gis/api-proposal-exception-queue: HTTP {$queueCode}" . PHP_EOL;
    echo "   Payload Preview: " . substr($queueRes, 0, 300) . PHP_EOL;

    // 4. Test GET /gis/api-proposal-workbench/1 (Real API via HTTPS)
    curl_setopt($ch, CURLOPT_URL, "{$baseUrl}/gis/api-proposal-workbench/1");
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    $wbRes = curl_exec($ch);
    $wbCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    echo "4. Real GET /gis/api-proposal-workbench/1: HTTP {$wbCode}" . PHP_EOL;
    echo "   Payload Preview: " . substr($wbRes, 0, 300) . PHP_EOL;

    // 5. Test Nonexistent Proposal ID (Expected 404)
    curl_setopt($ch, CURLOPT_URL, "{$baseUrl}/gis/api-proposal-workbench/99999");
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    $notFoundRes = curl_exec($ch);
    $notFoundCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    echo "5. Negative Test: GET /gis/api-proposal-workbench/99999 (Not Found): HTTP {$notFoundCode}" . PHP_EOL;
    echo "   Payload: " . $notFoundRes . PHP_EOL;

    // 6. Test Invalid Proposal ID (Expected 422)
    curl_setopt($ch, CURLOPT_URL, "{$baseUrl}/gis/api-proposal-workbench/0");
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    $invalidRes = curl_exec($ch);
    $invalidCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    echo "6. Negative Test: GET /gis/api-proposal-workbench/0 (Invalid ID): HTTP {$invalidCode}" . PHP_EOL;
    echo "   Payload: " . $invalidRes . PHP_EOL;
}

curl_close($ch);
