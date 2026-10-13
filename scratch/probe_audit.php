<?php
$ch = curl_init('https://sidaktejo.site/master-assets/reconciliation-baseline');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$res = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: " . $httpCode . "\n";
echo "Response Length: " . strlen($res) . "\n";
echo "Body:\n" . substr($res, 0, 2000) . "\n";
