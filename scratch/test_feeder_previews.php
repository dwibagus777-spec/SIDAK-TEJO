<?php

$cookieFile = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/prod_cookie.txt';
$baseUrl = 'https://sidaktejo.site';
$resolveOpt = ['sidaktejo.site:443:91.108.119.5', 'sidaktejo.site:80:91.108.119.5'];

$feedersToTest = [
    4   => 'GEMURUNG',
    23  => 'GEDANGAN',
    19  => 'GADING KIRANA',
    56  => 'BANJARAN',
    131 => 'BY PASS'
];

echo "=== TESTING TRANSLINE COMPLETION ENGINE PREVIEW (READ-ONLY) ===\n";

foreach ($feedersToTest as $fId => $fName) {
    $ch = curl_init($baseUrl . "/gis/api-transline-ai/preview?penyulang_id={$fId}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RESOLVE, $resolveOpt);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: Mozilla/5.0', 'X-Requested-With: XMLHttpRequest']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $res = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200) {
        echo "Feeder #{$fId} ({$fName}): HTTP {$code} - " . substr($res, 0, 100) . "\n";
        continue;
    }

    $data = json_decode($res, true);
    $inv = $data['inventory'] ?? [];
    $sum = $data['summary'] ?? [];

    printf("Feeder #%-3d | %-16s | Assets: %-4d | Translines: %-4d | Conn: %-4d | Isol: %-4d | Total Cand: %-4d | Defensible: %-4d | HighConf: %-4d | Review: %-4d | Blocked: %-4d\n",
        $fId,
        $fName,
        $inv['total_master_assets'] ?? 0,
        $inv['authoritative_translines'] ?? 0,
        $inv['connected_assets_count'] ?? 0,
        $inv['isolated_assets_count'] ?? 0,
        $sum['total_candidates_analyzed'] ?? 0,
        $sum['defensible_count'] ?? 0,
        $sum['high_confidence_review'] ?? 0,
        $sum['review_required'] ?? 0,
        $sum['ambiguous_blocked'] ?? 0
    );
}
