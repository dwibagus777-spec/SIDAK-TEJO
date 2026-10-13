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
    return [$httpCode, json_decode($res, true)];
}

$sampleFeeders = [
    1  => 'SIWALAN PANJI (ULP Kota)',
    2  => 'PERMATA HIJAU (ULP Kota)',
    15 => 'BANJAR KEMANTREN (ULP Kota)',
    42 => 'CANDRA MAS (ULP Kota)',
    46 => 'ADI PRIMA 1 (ULP Krian)',
    94 => 'TLOCOR (ULP Porong)',
];

foreach ($sampleFeeders as $fid => $name) {
    $t0 = microtime(true);
    list($code, $json) = callApi("/gis/api-network?penyulang_id={$fid}&layers=JTM,GARDU,TRAFO,SWITCH,TEMUAN");
    $dt = round((microtime(true) - $t0) * 1000);
    
    $features = $json['data']['features'] ?? [];
    $translines = $json['data']['translines'] ?? [];
    $assetCount = 0;
    $temuanCount = 0;
    foreach ($features as $f) {
        $type = $f['properties']['entity_type'] ?? '';
        if ($type === 'ASSET') $assetCount++;
        if ($type === 'TEMUAN') $temuanCount++;
    }
    
    echo sprintf(
        "Feeder %-3d [%-26s] HTTP %d (%3dms) | Assets: %4d | Translines: %4d | Temuan: %3d\n",
        $fid, $name, $code, $dt, $assetCount, count($translines), $temuanCount
    );
}
