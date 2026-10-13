<?php

$cookieFile = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/prod_cookie.txt';
$baseUrl = 'https://sidaktejo.site';
$resolveOpt = ['sidaktejo.site:443:91.108.119.5', 'sidaktejo.site:80:91.108.119.5'];

// Check table count and fingerprint
$ch = curl_init($baseUrl . '/gis/api-global-topology-audit');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RESOLVE, $resolveOpt);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: Mozilla/5.0', 'X-Requested-With: XMLHttpRequest']);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
$res = curl_exec($ch);
curl_close($ch);

$data = json_decode($res, true);
$fps = $data['fingerprints']['post'] ?? [];
echo "=== CANONICAL TABLE COUNTS (MARIADB PRODUCTION) ===\n";
foreach ($fps as $tbl => $info) {
    printf("%-25s: %d rows (SHA-256: %s)\n", $tbl, $info['count'], substr($info['sha256'], 0, 16) . '...');
}

echo "\n=== ACTIVE TRANSLINES PER FEEDER ===\n";
$fMatrix = $data['feeder_matrix'] ?? [];
$activeTlTotal = 0;
foreach ($fMatrix as $f) {
    if ($f['existing_translines'] > 0) {
        printf("Feeder #%-3d | %-25s | Active Translines: %d\n", $f['penyulang_id'], $f['nama_penyulang'], $f['existing_translines']);
        $activeTlTotal += $f['existing_translines'];
    }
}
echo "Total Active Translines from Matrix: {$activeTlTotal}\n";

// Also test Feeder 4 directly
$ch = curl_init($baseUrl . '/gis/api-translines?penyulang_id=4');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RESOLVE, $resolveOpt);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: Mozilla/5.0', 'X-Requested-With: XMLHttpRequest']);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
$res4 = curl_exec($ch);
curl_close($ch);
$d4 = json_decode($res4, true);
echo "Feeder #4 api-translines count: " . count($d4['data'] ?? []) . "\n";

// Also test Feeder 15 directly
$ch = curl_init($baseUrl . '/gis/api-translines?penyulang_id=15');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RESOLVE, $resolveOpt);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: Mozilla/5.0', 'X-Requested-With: XMLHttpRequest']);
curl_setopt($ch, CURLOPT_TIMEOUT, 60);
$res15 = curl_exec($ch);
curl_close($ch);
$d15 = json_decode($res15, true);
echo "Feeder #15 api-translines count: " . count($d15['data'] ?? []) . "\n";
