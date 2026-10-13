<?php

require_once 'e:/XAMPP/htdocs/SIDAK TEJO/vendor/autoload.php';
use App\Services\OneShotNetworkIngestionService;

$secAuditFile = 'e:/XAMPP/htdocs/SIDAK TEJO/writable/audits/SECTION_RESOLUTION_AUDIT.json';
$auditFile    = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/resp_global_topology_audit.json';
$assetsCsvFile= 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/prod_assets.csv';

$secAuditRaw = json_decode(file_get_contents($secAuditFile), true);
$secMap = [];
foreach ($secAuditRaw as $r) {
    $secMap[$r['asset_id']] = $r;
}

$auditData = json_decode(file_get_contents($auditFile), true);
$feeders = [];
$feederByName = [];
foreach ($auditData['feeder_matrix'] as $f) {
    $fId = (int)$f['penyulang_id'];
    $fName = strtoupper(trim($f['nama_penyulang']));
    $feeders[$fId] = $f;
    $feederByName[$fName] = $f;
}

$h = fopen($assetsCsvFile, 'r');
$header = fgetcsv($h);
$assetsByFeeder = [];
$totalAssets = 0;
while (($row = fgetcsv($h)) !== false) {
    $totalAssets++;
    $fName = strtoupper(trim($row[5] ?? ''));
    $fId = $feederByName[$fName]['penyulang_id'] ?? 0;
    $seq = 0;
    if (preg_match('/_(\d+)$/', $row[2], $m)) $seq = (int)$m[1];
    elseif (preg_match('/-(\d+)$/', $row[1], $m)) $seq = (int)$m[1];

    $secAudit = $secMap[$totalAssets] ?? null;
    $assetsByFeeder[$fId][] = [
        'id'          => $totalAssets,
        'kode_asset'  => $row[1],
        'nama_asset'  => $row[2],
        'latitude'    => (float)$row[13],
        'longitude'   => (float)$row[14],
        'status'      => $row[15] ?? 'NORMAL',
        'sequence_no' => $seq,
        'penyulang_id'=> $fId,
        'ulp_id'      => (int)($feederByName[$fName]['ulp_id'] ?? 0),
        'section_id'  => (int)($secAudit['resolved_section_id'] ?? 0),
        'section_name'=> $secAudit['resolved_section_name'] ?? ''
    ];
}
fclose($h);

$autoAcceptByFeeder = [];
$warningByFeeder = [];
$reviewByFeeder = [];
$secViolByFeeder = [];

foreach ($assetsByFeeder as $fId => $assets) {
    $fName = $feeders[$fId]['nama_penyulang'] ?? "F{$fId}";
    usort($assets, function ($a, $b) {
        if ($a['sequence_no'] !== $b['sequence_no']) return $a['sequence_no'] <=> $b['sequence_no'];
        return strnatcasecmp($a['kode_asset'], $b['kode_asset']);
    });
    $degrees = [];
    foreach ($assets as $a) $degrees[$a['id']] = 0;

    for ($i = 0; $i < count($assets) - 1; $i++) {
        $u = $assets[$i];
        $v = $assets[$i+1];
        $dist = OneShotNetworkIngestionService::haversineDistance($u['latitude'], $u['longitude'], $v['latitude'], $v['longitude']);
        if ($dist < 1.0 || $dist > 120.0) continue;
        if ($degrees[$u['id']] >= 4 || $degrees[$v['id']] >= 4) continue;

        if ($u['section_id'] > 0 && $v['section_id'] > 0 && $u['section_id'] !== $v['section_id']) {
            $secViolByFeeder[$fName][] = [
                'u' => $u['nama_asset'] . " (sec {$u['section_id']})",
                'v' => $v['nama_asset'] . " (sec {$v['section_id']})",
                'dist' => $dist
            ];
            continue;
        }

        $ev = OneShotNetworkIngestionService::computeEvidenceAndConfidence($u, $v, $dist, $degrees[$u['id']], $degrees[$v['id']]);
        $c = $ev['confidence'];
        if ($c >= 0.95) {
            $autoAcceptByFeeder[$fName][] = [
                'edge' => "{$u['nama_asset']} -> {$v['nama_asset']}",
                'dist' => $dist,
                'conf' => $c,
                'ev'   => $ev['evidence']
            ];
        } elseif ($c >= 0.80) {
            $warningByFeeder[$fName] = ($warningByFeeder[$fName] ?? 0) + 1;
        } else {
            $reviewByFeeder[$fName] = ($reviewByFeeder[$fName] ?? 0) + 1;
        }
        $degrees[$u['id']]++;
        $degrees[$v['id']]++;
    }
}

echo "Feeders with AUTO_ACCEPT candidates:\n";
foreach ($autoAcceptByFeeder as $fn => $cands) {
    echo "  • {$fn}: " . count($cands) . " candidates\n";
    foreach ($cands as $cand) {
        echo sprintf("      - %-30s | dist: %5.1fm | conf: %0.4f\n", $cand['edge'], $cand['dist'], $cand['conf']);
    }
}

echo "\nFeeders with SECTION_BOUNDARY_VIOLATION:\n";
foreach ($secViolByFeeder as $fn => $viols) {
    echo "  • {$fn}: " . count($viols) . " violations\n";
    foreach ($viols as $v) {
        echo "      - {$v['u']} -> {$v['v']} ({$v['dist']}m)\n";
    }
}
