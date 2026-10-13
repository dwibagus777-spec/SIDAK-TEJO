<?php
$baseUrl = 'https://sidaktejo.site';

$ch = curl_init($baseUrl . '/full_audit.php');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$res = curl_exec($ch);
curl_close($ch);

$lines = explode("\n", (string)$res);
for ($i = 0; $i < min(15, count($lines)); $i++) {
    echo $lines[$i] . "\n";
}
