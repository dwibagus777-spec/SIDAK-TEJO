<?php

$auditKey = 'sidak_transline_audit_2026';
$url = "https://sidaktejo.site/fault-ingestion/forensic-reconciliation?key={$auditKey}";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RESOLVE, ['sidaktejo.site:443:2.57.91.151', 'sidaktejo.site:80:2.57.91.151']);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$res = curl_exec($ch);
curl_close($ch);

$data = json_decode($res, true);
echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
