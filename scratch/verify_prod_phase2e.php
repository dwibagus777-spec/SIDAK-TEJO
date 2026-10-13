<?php

$cookieFile = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/prod_cookie.txt';
$baseUrl = 'https://sidaktejo.site';
$resolveOpt = ['sidaktejo.site:443:91.108.119.5', 'sidaktejo.site:80:91.108.119.5'];

$c = curl_init($baseUrl . '/dashboard');
curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($c, CURLOPT_RESOLVE, $resolveOpt);
curl_setopt($c, CURLOPT_COOKIEFILE, $cookieFile);
$res = curl_exec($c);
$code = curl_getinfo($c, CURLINFO_HTTP_CODE);
curl_close($c);

echo "HTTP {$code} (Length: " . strlen($res) . " bytes)\n";

$checks = [
    'node-hub-dashboard'          => strpos($res, 'id="node-hub-dashboard"') !== false,
    'node-planning'               => strpos($res, 'id="node-planning"') !== false,
    'node-temuan'                 => strpos($res, 'id="node-temuan"') !== false,
    'node-wo'                     => strpos($res, 'id="node-wo"') !== false,
    'node-emergency'              => strpos($res, 'id="node-emergency"') !== false,
    'node-tugas'                  => strpos($res, 'id="node-tugas"') !== false,
    'node-ai'                     => strpos($res, 'id="node-ai"') !== false,
    'node-gis'                    => strpos($res, 'id="node-gis"') !== false,
    'node-analytics'              => strpos($res, 'id="node-analytics"') !== false,
    'constellation-svg-network'   => strpos($res, 'constellation-svg-network') !== false,
    'contextual-dashboard-card'   => strpos($res, 'contextual-dashboard-card') !== false,
    'contextual-kpi-mint'         => strpos($res, 'contextual-kpi-mint') !== false,
    'kpi-total-temuan'            => strpos($res, 'id="kpi-total-temuan"') !== false,
    'kpi-emergency'               => strpos($res, 'id="kpi-emergency"') !== false,
    'kpi-belum'                   => strpos($res, 'id="kpi-belum"') !== false,
    'kpi-gis-pins'                => strpos($res, 'id="kpi-gis-pins"') !== false,
    'sidak-floating-dock'         => strpos($res, 'sidak-floating-dock') !== false,
    'emc-mini-map'                => strpos($res, 'id="emc-mini-map"') !== false,
    'sla-val-emergency'           => strpos($res, 'id="sla-val-emergency"') !== false,
];

$pass = 0;
foreach ($checks as $name => $ok) {
    echo ($ok ? "✅ PASS: " : "❌ FAIL: ") . $name . "\n";
    if ($ok) $pass++;
}

echo "\nSummary: {$pass} / " . count($checks) . " live verification checks passed.\n";
