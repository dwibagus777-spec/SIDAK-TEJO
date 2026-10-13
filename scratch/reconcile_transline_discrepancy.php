<?php

$cookieFile = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/prod_cookie.txt';
$baseUrl = 'https://sidaktejo.site';
$resolveOpt = ['sidaktejo.site:443:91.108.119.5', 'sidaktejo.site:80:91.108.119.5'];

echo "=== RECONCILING 197 vs 198 TRANSLINES IN FEEDER 15 ===\n";

// 1. Fetch from /gis/api-translines?penyulang_id=15
$ch = curl_init($baseUrl . '/gis/api-translines?penyulang_id=15');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RESOLVE, $resolveOpt);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: Mozilla/5.0', 'X-Requested-With: XMLHttpRequest']);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
$res1 = curl_exec($ch);
curl_close($ch);

$data1 = json_decode($res1, true);
$tls1 = $data1['data'] ?? [];
echo "1. api-translines count: " . count($tls1) . "\n";

$byStatus = [];
$byActive = [];
$byPenyulang = [];
$inactiveList = [];

foreach ($tls1 as $t) {
    $st = $t['status'] ?? 'NULL';
    $act = $t['is_active'] ?? 'NULL';
    $pid = $t['penyulang_id'] ?? 'NULL';
    $byStatus[$st] = ($byStatus[$st] ?? 0) + 1;
    $byActive[$act] = ($byActive[$act] ?? 0) + 1;
    $byPenyulang[$pid] = ($byPenyulang[$pid] ?? 0) + 1;
    if ($act != 1 || $st !== 'ACTIVE') {
        $inactiveList[] = $t;
    }
}

echo "Status breakdown: " . json_encode($byStatus) . "\n";
echo "Active breakdown: " . json_encode($byActive) . "\n";
echo "Penyulang breakdown: " . json_encode($byPenyulang) . "\n";

if (!empty($inactiveList)) {
    echo "Non-standard rows:\n" . json_encode($inactiveList, JSON_PRETTY_PRINT) . "\n";
}

// 2. Fetch from /gis/api-transline-ai/preview?penyulang_id=15
$ch = curl_init($baseUrl . '/gis/api-transline-ai/preview?penyulang_id=15');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RESOLVE, $resolveOpt);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: Mozilla/5.0', 'X-Requested-With: XMLHttpRequest']);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
$res2 = curl_exec($ch);
curl_close($ch);

$data2 = json_decode($res2, true);
$tls2 = $data2['inventory']['authoritative_translines'] ?? 0;
echo "2. api-transline-ai/preview authoritative_translines count: " . $tls2 . "\n";

