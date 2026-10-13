<?php
$baseUrl = 'https://sidaktejo.site/master-assets/b2-atomic-commit';
$cookieFile = __DIR__ . '/prod_cookie.txt';

$params = [
    'key'         => 'sidak_transline_audit_2026',
    'fingerprint' => 'ad2c9fcb833ca2680d00bb45adfa27a4e87730745aa65bf1c3419c89cb713397',
    'commit'      => 0,
];

$url = $baseUrl . '?' . http_build_query($params);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$res = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
if ($err) {
    echo "CURL Error: $err\n";
}
echo "Response Body:\n$res\n";
