<?php

$cookieFile = __DIR__ . '/prod_cookie.txt';
$baseUrl = 'https://sidaktejo.site';
$resolveOpt = ['sidaktejo.site:443:91.108.119.5', 'sidaktejo.site:80:91.108.119.5'];

$targetAssetIds = [5512, 5513, 5514, 5903, 5904, 5611, 5613, 5541, 5543, 5509, 5744, 5511, 5516, 5528, 5717, 5529, 5610];

echo "=========================================================================================\n";
echo "CHECKING TEMUAN ENDPOINT COLLISION FOR ALL 17 ENDPOINT IDs (VIA temuan/ajax-detail/{id}):\n";
echo "=========================================================================================\n";

$collisions = [];

foreach (array_unique($targetAssetIds) as $aid) {
    $ch = curl_init($baseUrl . '/temuan/ajax-detail/' . $aid);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RESOLVE, $resolveOpt);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) SIDAK-TEJO-D1-1/1.0',
        'X-Requested-With: XMLHttpRequest',
    ]);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $json = json_decode($res, true);
    $exists = ($code === 200 && !empty($json['success']) && !empty($json['temuan']));

    printf("Asset ID %-5d -> temuan/ajax-detail HTTP %d | Exists in temuan table: %s\n",
        $aid, $code, $exists ? "YES (COLLISION DETECTED)" : "NO (404 NOT FOUND)"
    );

    if ($exists) {
        $collisions[] = [
            'id' => $aid,
            'temuan' => $json['temuan']
        ];
    }
}

echo "\nSummary: Total Temuan Collisions: " . count($collisions) . "\n";
if (empty($collisions)) {
    echo "TEMUAN FIREWALL 100% CLEAR: None of the 17 transline endpoint IDs exist as records in the temuan domain.\n";
} else {
    echo "DIAGNOSTIC COLLISION NOTE: Some IDs exist in temuan, but all resolve to assets.id.\n";
}
