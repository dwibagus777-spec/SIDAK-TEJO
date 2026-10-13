<?php

$cookieFile = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/prod_cookie.txt';
$resolveOpt = ['sidaktejo.site:443:91.108.119.5', 'sidaktejo.site:80:91.108.119.5'];

$urls = [
    'page' => 'https://sidaktejo.site/sld/view/15',
    'layout' => 'https://sidaktejo.site/api/sld/feeder/15/layout',
    'sheets' => 'https://sidaktejo.site/api/sld/feeder/15/sheets',
    'findings' => 'https://sidaktejo.site/api/sld/feeder/15/findings',
    'fingerprint' => 'https://sidaktejo.site/api/sld/feeder/15/fingerprint'
];

foreach ($urls as $k => $u) {
    $c = curl_init($u);
    curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($c, CURLOPT_RESOLVE, $resolveOpt);
    curl_setopt($c, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($c, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($c, CURLOPT_TIMEOUT, 30);
    $body = curl_exec($c);
    $code = curl_getinfo($c, CURLINFO_HTTP_CODE);
    curl_close($c);
    echo "[$k] HTTP $code (len: " . strlen($body) . ")\n";
    if ($k !== 'page') {
        $json = json_decode($body, true);
        if ($k === 'layout') {
            echo "   nodes: " . count($json['nodes'] ?? []) . ", edges: " . count($json['edges'] ?? []) . "\n";
        } elseif ($k === 'sheets') {
            echo "   sheets: " . count($json['sheets'] ?? []) . "\n";
            foreach ($json['sheets'] ?? [] as $sh) {
                echo "      Sheet {$sh['sheet_number']}: {$sh['pole_count']} poles, match_lines=" . count($sh['match_lines'] ?? []) . "\n";
            }
        } elseif ($k === 'findings') {
            echo "   status: " . ($json['status'] ?? '') . ", findings: " . count($json['findings'] ?? []) . "\n";
            foreach ($json['findings'] ?? [] as $f) {
                echo "      Finding {$f['finding_id']} [{$f['priority']}]: {$f['link_model']} -> Sheet {$f['annotation']['sheet_id']}\n";
            }
        } elseif ($k === 'fingerprint') {
            echo "   fp: " . ($json['data_fingerprint'] ?? '') . ", assets: " . ($json['active_assets_count'] ?? '') . ", translines: " . ($json['active_translines_count'] ?? '') . "\n";
        }
    }
}
