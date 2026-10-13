<?php

$cookieFile = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/prod_cookie.txt';
$baseUrl = 'https://sidaktejo.site';
$resolveOpt = ['sidaktejo.site:443:91.108.119.5', 'sidaktejo.site:80:91.108.119.5'];

$ch = curl_init($baseUrl . '/gis/api-translines?penyulang_id=15');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RESOLVE, $resolveOpt);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: Mozilla/5.0', 'X-Requested-With: XMLHttpRequest']);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
$res = curl_exec($ch);
curl_close($ch);

$data = json_decode($res, true);
$tls = $data['data'] ?? [];

echo "Total translines returned by api-translines?penyulang_id=15: " . count($tls) . "\n";

$byPenyulang = [];
$bySource = [];
$maxId = 0;
$ids = [];
foreach ($tls as $t) {
    $pId = (int)($t['penyulang_id'] ?? 0);
    $byPenyulang[$pId] = ($byPenyulang[$pId] ?? 0) + 1;
    $ids[] = (int)$t['id'];
    if ((int)$t['id'] > $maxId) $maxId = (int)$t['id'];
}

echo "Breakdown by penyulang_id in payload:\n";
print_r($byPenyulang);
echo "Min ID: " . min($ids) . ", Max ID: " . max($ids) . "\n";

// Check if any IDs are above 169 or from proposals
echo "IDs count: " . count($ids) . "\n";
echo "IDs list sample: " . implode(', ', array_slice($ids, 0, 20)) . "... and last 20: " . implode(', ', array_slice($ids, -20)) . "\n";
