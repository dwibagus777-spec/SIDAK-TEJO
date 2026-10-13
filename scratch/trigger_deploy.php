<?php

$c = curl_init('https://sidaktejo.site/auto-deploy');
curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($c, CURLOPT_RESOLVE, ['sidaktejo.site:443:2.57.91.151', 'sidaktejo.site:80:2.57.91.151']);
curl_setopt($c, CURLOPT_TIMEOUT, 60);
$res = curl_exec($c);
$code = curl_getinfo($c, CURLINFO_HTTP_CODE);
$err = curl_error($c);
curl_close($c);

echo "HTTP Code: {$code}\n";
if ($err) {
    echo "cURL Error: {$err}\n";
}
echo "Response: {$res}\n";
