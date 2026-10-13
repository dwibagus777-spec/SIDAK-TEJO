<?php

$cookieFile = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/prod_cookie.txt';
$baseUrl = 'https://sidaktejo.site';
$resolveOpt = ['sidaktejo.site:443:91.108.119.5', 'sidaktejo.site:80:91.108.119.5'];

function callApi($endpoint) {
    global $baseUrl, $resolveOpt, $cookieFile;
    $ch = curl_init($baseUrl . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RESOLVE, $resolveOpt);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: Mozilla/5.0', 'X-Requested-With: XMLHttpRequest']);
    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$httpCode, json_decode($res, true), $res];
}

$allPenyulangs = [];
foreach ([1, 2, 3] as $ulpId) {
    list($code, $json) = callApi("/gis/api-penyulangs?ulp_id={$ulpId}");
    if ($code === 200 && !empty($json['penyulangs'])) {
        foreach ($json['penyulangs'] as $p) {
            $p['ulp_id'] = $ulpId;
            $allPenyulangs[] = $p;
        }
    }
}

echo "Total Penyulangs across all 3 ULPs: " . count($allPenyulangs) . "\n";
foreach ($allPenyulangs as $p) {
    echo sprintf("ID: %-3d | ULP: %-20s | Nama: %s\n", $p['id'], $p['nama_ulp'] ?? ('ULP '.$p['ulp_id']), $p['nama_penyulang']);
}
