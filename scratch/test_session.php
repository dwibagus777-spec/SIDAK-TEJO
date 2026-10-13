<?php

$cookieFile = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/prod_cookie.txt';
$baseUrl = 'https://sidaktejo.site';
$resolveOpt = ['sidaktejo.site:443:91.108.119.5', 'sidaktejo.site:80:91.108.119.5'];

$c = curl_init($baseUrl . '/gis');
curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($c, CURLOPT_RESOLVE, $resolveOpt);
curl_setopt($c, CURLOPT_COOKIEFILE, $cookieFile);
$res = curl_exec($c);
$code = curl_getinfo($c, CURLINFO_HTTP_CODE);
curl_close($c);

echo "HTTP {$code} (len " . strlen($res) . ")\n";
if (strpos($res, 'setup-feeder-select') !== false) {
    echo "SESSION VALID: Found setup-feeder-select!\n";
} elseif (strpos($res, 'login') !== false) {
    echo "SESSION EXPIRED: Redirected to login!\n";
} else {
    echo "First 200 chars:\n" . substr($res, 0, 200) . "\n";
}
