<?php

require_once 'e:/XAMPP/htdocs/SIDAK TEJO/vendor/autoload.php';
use App\Services\OneShotNetworkIngestionService;

$secAuditFile = 'e:/XAMPP/htdocs/SIDAK TEJO/writable/audits/SECTION_RESOLUTION_AUDIT.json';
$assetsCsvFile= 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/prod_assets.csv';

$secAuditRaw = json_decode(file_get_contents($secAuditFile), true);
$secMap = [];
foreach ($secAuditRaw as $r) {
    $secMap[$r['asset_id']] = $r;
}

$h = fopen($assetsCsvFile, 'r');
$header = fgetcsv($h);
$jp4Assets = [];
$totalAssets = 0;
while (($row = fgetcsv($h)) !== false) {
    $totalAssets++;
    if (trim($row[5] ?? '') === 'JAVA PASIFIK 4') {
        $seq = 0;
        if (preg_match('/_(\d+)$/', $row[2], $m)) $seq = (int)$m[1];
        elseif (preg_match('/-(\d+)$/', $row[1], $m)) $seq = (int)$m[1];

        $secAudit = $secMap[$totalAssets] ?? null;
        $jp4Assets[] = [
            'id'          => $totalAssets,
            'kode_asset'  => $row[1],
            'nama_asset'  => $row[2],
            'latitude'    => (float)$row[13],
            'longitude'   => (float)$row[14],
            'status'      => $row[15] ?? 'NORMAL',
            'sequence_no' => $seq,
            'penyulang_id'=> 79,
            'ulp_id'      => 2,
            'section_id'  => (int)($secAudit['resolved_section_id'] ?? 0),
            'section_name'=> $secAudit['resolved_section_name'] ?? ''
        ];
    }
}
fclose($h);

usort($jp4Assets, fn($a, $b) => $a['sequence_no'] <=> $b['sequence_no']);

$degrees = [];
foreach ($jp4Assets as $a) $degrees[$a['id']] = 0;

$warnReasons = [];
for ($i = 0; $i < count($jp4Assets) - 1; $i++) {
    $u = $jp4Assets[$i];
    $v = $jp4Assets[$i+1];
    $dist = OneShotNetworkIngestionService::haversineDistance($u['latitude'], $u['longitude'], $v['latitude'], $v['longitude']);
    if ($dist < 1.0 || $dist > 120.0) continue;

    $ev = OneShotNetworkIngestionService::computeEvidenceAndConfidence($u, $v, $dist, $degrees[$u['id']], $degrees[$v['id']]);
    $conf = $ev['confidence'];
    if ($conf < 0.95) {
        $penalties = [];
        if ($ev['evidence']['distance_score'] < 1.0) $penalties[] = "dist=" . round($dist,1) . "m(score " . $ev['evidence']['distance_score'] . ")";
        if ($ev['evidence']['sequence_score'] < 1.0) $penalties[] = "seqDelta=" . $ev['evidence']['sequence_delta'] . "(score " . $ev['evidence']['sequence_score'] . ")";
        if ($ev['evidence']['degree_score'] < 1.0) $penalties[] = "degree(score " . $ev['evidence']['degree_score'] . ")";
        $warnReasons[] = sprintf(
            "%s -> %s | conf=%0.4f | %s",
            $u['nama_asset'], $v['nama_asset'], $conf, implode(', ', $penalties)
        );
    }
    $degrees[$u['id']]++;
    $degrees[$v['id']]++;
}

echo "JP4 Non-Auto-Accept Sample (First 15):\n";
for ($i = 0; $i < min(15, count($warnReasons)); $i++) {
    echo "  " . $warnReasons[$i] . "\n";
}
