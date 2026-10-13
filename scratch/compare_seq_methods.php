<?php

require_once 'e:/XAMPP/htdocs/SIDAK TEJO/vendor/autoload.php';
use App\Services\OneShotNetworkIngestionService;

$secAuditFile = 'e:/XAMPP/htdocs/SIDAK TEJO/writable/audits/SECTION_RESOLUTION_AUDIT.json';
$auditFile    = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/resp_global_topology_audit.json';
$assetsCsvFile= 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/prod_assets.csv';

$secAuditRaw = json_decode(file_get_contents($secAuditFile), true);
$secMap = [];
foreach ($secAuditRaw as $r) $secMap[$r['asset_id']] = $r;

$auditData = json_decode(file_get_contents($auditFile), true);
$feederByName = [];
foreach ($auditData['feeder_matrix'] as $f) {
    $feederByName[strtoupper(trim($f['nama_penyulang']))] = $f;
}

$h = fopen($assetsCsvFile, 'r');
$header = fgetcsv($h);
$assetsByFeeder = [];
$totalAssets = 0;
while (($row = fgetcsv($h)) !== false) {
    $totalAssets++;
    $fName = strtoupper(trim($row[5] ?? ''));
    $fInfo = $feederByName[$fName] ?? null;
    $fId = $fInfo['penyulang_id'] ?? 0;
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
        'ulp_id'      => (int)($fInfo['ulp_id'] ?? 0),
        'section_id'  => (int)($secAudit['resolved_section_id'] ?? 0),
        'section_name'=> $secAudit['resolved_section_name'] ?? ''
    ];
}
fclose($h);

// Compare:
// Method A: Exact computeEvidenceAndConfidence from OneShotNetworkIngestionService (current code)
// Method B: If sequence_no is used for seqDelta

$countA = ['AUTO' => 0, 'WARN' => 0, 'REV' => 0];
$countB = ['AUTO' => 0, 'WARN' => 0, 'REV' => 0];

foreach ($assetsByFeeder as $fId => $assets) {
    usort($assets, fn($a, $b) => ($a['sequence_no'] !== $b['sequence_no']) ? ($a['sequence_no'] <=> $b['sequence_no']) : strnatcasecmp($a['kode_asset'], $b['kode_asset']));
    $degA = array_fill_keys(array_column($assets, 'id'), 0);
    $degB = array_fill_keys(array_column($assets, 'id'), 0);

    for ($i = 0; $i < count($assets) - 1; $i++) {
        $u = $assets[$i];
        $v = $assets[$i+1];
        $dist = OneShotNetworkIngestionService::haversineDistance($u['latitude'], $u['longitude'], $v['latitude'], $v['longitude']);
        if ($dist < 1.0 || $dist > 120.0) continue;
        if ($u['section_id'] > 0 && $v['section_id'] > 0 && $u['section_id'] !== $v['section_id']) continue;

        // Method A
        $evA = OneShotNetworkIngestionService::computeEvidenceAndConfidence($u, $v, $dist, $degA[$u['id']], $degA[$v['id']]);
        if ($evA['confidence'] >= 0.95) $countA['AUTO']++;
        elseif ($evA['confidence'] >= 0.80) $countA['WARN']++;
        else $countA['REV']++;
        $degA[$u['id']]++; $degA[$v['id']]++;

        // Method B: using natural sequence_no
        $seqDeltaNatural = ($u['sequence_no'] > 0 && $v['sequence_no'] > 0) ? abs($u['sequence_no'] - $v['sequence_no']) : OneShotNetworkIngestionService::calculateSequenceDelta($u['kode_asset'], $v['kode_asset']);
        if ($seqDeltaNatural === 1) $sScore = 1.00;
        elseif ($seqDeltaNatural <= 3) $sScore = 0.85;
        elseif ($seqDeltaNatural <= 5) $sScore = 0.65;
        else $sScore = 0.35;

        // Replace seqScore in formula
        $evBCopy = $evA['evidence'];
        $evBCopy['sequence_score'] = $sScore;
        $evBCopy['sequence_delta'] = $seqDeltaNatural;
        $confB = round((
            ($evBCopy['distance_score'] * 0.25) +
            ($evBCopy['bearing_score'] * 0.15) +
            ($sScore * 0.20) +
            ($evBCopy['section_score'] * 0.15) +
            ($evBCopy['degree_score'] * 0.10) +
            ($evBCopy['existing_parent_relation_score'] * 0.10) +
            ($evBCopy['asset_status_score'] * 0.05)
        ), 4);
        if ($confB >= 0.95) $countB['AUTO']++;
        elseif ($confB >= 0.80) $countB['WARN']++;
        else $countB['REV']++;
        $degB[$u['id']]++; $degB[$v['id']]++;
    }
}

echo "Method A (as-is in OneShotNetworkIngestionService):\n";
print_r($countA);
echo "Method B (natural sequence_no delta):\n";
print_r($countB);
