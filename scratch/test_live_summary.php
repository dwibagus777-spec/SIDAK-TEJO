<?php

$ch = curl_init('https://sidaktejo.site/auto-deploy');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RESOLVE, ['sidaktejo.site:443:2.57.91.151']);
$deploy = curl_exec($ch);
curl_close($ch);
echo "Deploy response: " . $deploy . "\n\n";

$ch = curl_init('https://sidaktejo.site/api/planning/summary');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RESOLVE, ['sidaktejo.site:443:2.57.91.151']);
$summary = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Summary HTTP Code: " . $code . "\n";
echo "Summary Body: " . substr((string)$summary, 0, 500) . "\n";
