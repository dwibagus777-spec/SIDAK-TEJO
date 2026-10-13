<?php
/**
 * Trigger auto-deploy to production
 */
$ch = curl_init('https://sidaktejo.site/auto-deploy');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_RESOLVE, ['sidaktejo.site:443:2.57.91.151']);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
$body = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "HTTP: $http\n";
echo $body . "\n";
