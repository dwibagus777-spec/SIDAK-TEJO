<?php
$baseUrl = 'https://sidaktejo.site';
$cookieFile = __DIR__ . '/prod_cookie.txt';

function getUrl($url, $cookieFile = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'body' => $res, 'json' => json_decode((string)$res, true)];
}

echo "=== Testing /gis/api-translines?penyulang_id=118 ===\n";
$r1 = getUrl($baseUrl . '/gis/api-translines?penyulang_id=118', $cookieFile);
echo "HTTP Code: " . $r1['code'] . "\n";
if (is_array($r1['json'])) {
    echo "Keys: " . implode(', ', array_keys($r1['json'])) . "\n";
    if (isset($r1['json']['data'])) {
        echo "Data count: " . count($r1['json']['data']) . "\n";
        print_r($r1['json']['data']);
    } elseif (isset($r1['json']['translines'])) {
        echo "Translines count: " . count($r1['json']['translines']) . "\n";
        print_r($r1['json']['translines']);
    } else {
        print_r($r1['json']);
    }
} else {
    echo substr((string)$r1['body'], 0, 200) . "\n";
}

echo "\n=== Testing /assets/network/118 ===\n";
$r2 = getUrl($baseUrl . '/assets/network/118', $cookieFile);
echo "HTTP Code: " . $r2['code'] . "\n";
if (is_array($r2['json'])) {
    echo "Keys: " . implode(', ', array_keys($r2['json'])) . "\n";
    if (isset($r2['json']['translines'])) {
        echo "Translines in network: " . count($r2['json']['translines']) . "\n";
    }
}
