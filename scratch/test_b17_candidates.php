<?php

require_once 'e:/XAMPP/htdocs/SIDAK TEJO/vendor/autoload.php';

use App\Services\OneShotNetworkIngestionService;

$auditFile     = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/resp_global_topology_audit.json';
$assetsCsvFile = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/prod_assets.csv';
$globalTlFile  = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/resp_analytics_global.json';
$secAuditFile  = 'e:/XAMPP/htdocs/SIDAK TEJO/writable/audits/SECTION_RESOLUTION_AUDIT.json';

// 1. Load Section Resolution Audit (from B.1.6)
$secAuditRaw = json_decode(file_get_contents($secAuditFile), true);
$secMapByAssetId = [];
foreach ($secAuditRaw as $row) {
    $secMapByAssetId[$row['asset_id']] = $row;
}

echo "Loaded section resolution audit for " . count($secMapByAssetId) . " assets.\n";

// 2. Load 134 Feeders from Production Audit
$auditData = json_decode(file_get_contents($auditFile), true);
$feederMatrix = $auditData['feeder_matrix'] ?? [];

$feeders = [];
$feederByName = [];
foreach ($feederMatrix as $f) {
    $fId = (int)$f['penyulang_id'];
    $fName = strtoupper(trim($f['nama_penyulang']));
    $feeders[$fId] = [
        'id'             => $fId,
        'kode_penyulang' => $f['kode_penyulang'],
        'nama_penyulang' => $f['nama_penyulang'],
        'ulp_id'         => (int)$f['ulp_id'],
        'nama_ulp'       => $f['nama_ulp'] ?? ''
    ];
    $feederByName[$fName] = $feeders[$fId];
}

// 3. Load 217 Authoritative Translines
$tlData = json_decode(file_get_contents($globalTlFile), true);
$existingTranslines = $tlData['items'] ?? [];
$existingNaturalKeys = [];
foreach ($existingTranslines as $tl) {
    $fId = (int)$tl['penyulang_id'];
    $src = (int)$tl['source_asset_id'];
    $tgt = (int)$tl['target_asset_id'];
    $natKey = OneShotNetworkIngestionService::buildNaturalKey($fId, $src, $tgt);
    $existingNaturalKeys[$natKey] = (int)$tl['transline_id'];
}

// 4. Load 5,236 Active Assets and merge with resolved section
$h = fopen($assetsCsvFile, 'r');
$header = fgetcsv($h);

$assetsByFeeder = [];
$totalAssets = 0;

while (($row = fgetcsv($h)) !== false) {
    $totalAssets++;
    $kodeAsset   = trim($row[1] ?? '');
    $namaAsset   = trim($row[2] ?? '');
    $jenisAsset  = trim($row[3] ?? '');
    $ulpName     = trim($row[4] ?? '');
    $feederName  = trim($row[5] ?? '');
    $sectionName = trim($row[6] ?? '');
    $lat         = (float)trim($row[13] ?? '0');
    $lng         = (float)trim($row[14] ?? '0');
    $status      = trim($row[15] ?? 'NORMAL');

    $feederKey = strtoupper($feederName);
    $feederInfo = $feederByName[$feederKey] ?? null;
    $fId = $feederInfo['id'] ?? 0;
    $uId = $feederInfo['ulp_id'] ?? 0;

    $seq = 0;
    if (preg_match('/_(\d+)$/', $namaAsset, $m)) {
        $seq = (int)$m[1];
    } elseif (preg_match('/-(\d+)$/', $kodeAsset, $m)) {
        $seq = (int)$m[1];
    }

    $secAudit = $secMapByAssetId[$totalAssets] ?? null;
    $resolvedSecId = (int)($secAudit['resolved_section_id'] ?? 0);
    $resolvedSecName = $secAudit['resolved_section_name'] ?? '';
    $secResStatus = $secAudit['status'] ?? 'UNRESOLVED';
    $secResConfidence = (float)($secAudit['confidence'] ?? 0.0);

    $assetItem = [
        'id'                  => $totalAssets,
        'kode_asset'          => $kodeAsset,
        'nama_asset'          => $namaAsset,
        'jenis_asset'         => $jenisAsset,
        'penyulang_id'        => $fId,
        'nama_penyulang'      => $feederInfo['nama_penyulang'] ?? $feederName,
        'ulp_id'              => $uId,
        'nama_ulp'            => $ulpName,
        'section_id'          => $resolvedSecId,
        'section_name'        => $resolvedSecName,
        'section_res_status'  => $secResStatus,
        'section_res_conf'    => $secResConfidence,
        'latitude'            => $lat,
        'longitude'           => $lng,
        'status'              => $status,
        'sequence_no'         => $seq
    ];

    $assetsByFeeder[$fId][] = $assetItem;
}
fclose($h);

echo "Total assets loaded: {$totalAssets} across " . count($assetsByFeeder) . " active feeders.\n";

// Test Feeder candidate generation with boundary awareness
$allCandidates = [];
$allRejections = [];
$counts = [
    'AUTO_ACCEPT'     => 0,
    'WARNING_REVIEW'  => 0,
    'REVIEW_REQUIRED' => 0
];
$rejTaxonomy = [];

foreach ($assetsByFeeder as $fId => $assets) {
    // Sort assets by sequence_no or pole code
    usort($assets, function ($a, $b) {
        $seqA = (int)($a['sequence_no'] ?? 0);
        $seqB = (int)($b['sequence_no'] ?? 0);
        if ($seqA !== $seqB) {
            return $seqA <=> $seqB;
        }
        return strnatcasecmp($a['kode_asset'], $b['kode_asset']);
    });

    $count = count($assets);
    $degrees = [];
    foreach ($assets as $a) {
        $degrees[$a['id']] = 0;
    }

    for ($i = 0; $i < $count - 1; $i++) {
        $u = $assets[$i];
        $v = $assets[$i + 1];
        $uId = $u['id'];
        $vId = $v['id'];

        $natKey = OneShotNetworkIngestionService::buildNaturalKey($fId, $uId, $vId);

        // 1. Conflict existing authoritative transline
        if (isset($existingNaturalKeys[$natKey])) {
            $rejTaxonomy['CONFLICT_EXISTING'] = ($rejTaxonomy['CONFLICT_EXISTING'] ?? 0) + 1;
            continue;
        }

        // 2. Distance check
        $dist = OneShotNetworkIngestionService::haversineDistance(
            $u['latitude'], $u['longitude'],
            $v['latitude'], $v['longitude']
        );

        if ($dist < 1.0 || $dist > 120.0) {
            $rejTaxonomy['IMPOSSIBLE_DISTANCE'] = ($rejTaxonomy['IMPOSSIBLE_DISTANCE'] ?? 0) + 1;
            continue;
        }

        // 3. Degree check
        if ($degrees[$uId] >= 4 || $degrees[$vId] >= 4) {
            $rejTaxonomy['MAX_DEGREE_EXCEEDED'] = ($rejTaxonomy['MAX_DEGREE_EXCEEDED'] ?? 0) + 1;
            continue;
        }

        // 4. Section Boundary Awareness
        $secU = (int)$u['section_id'];
        $secV = (int)$v['section_id'];

        // If both have resolved sections and they differ -> BOUNDARY VIOLATION!
        if ($secU > 0 && $secV > 0 && $secU !== $secV) {
            $rejTaxonomy['SECTION_BOUNDARY_VIOLATION'] = ($rejTaxonomy['SECTION_BOUNDARY_VIOLATION'] ?? 0) + 1;
            continue;
        }

        // Compute evidence & confidence
        $ev = OneShotNetworkIngestionService::computeEvidenceAndConfidence($u, $v, $dist, $degrees[$uId], $degrees[$vId]);
        $conf = $ev['confidence'];

        if ($conf >= 0.95) {
            $counts['AUTO_ACCEPT']++;
            $class = 'AUTO_ACCEPT';
        } elseif ($conf >= 0.80) {
            $counts['WARNING_REVIEW']++;
            $class = 'WARNING_REVIEW';
        } else {
            $counts['REVIEW_REQUIRED']++;
            $class = 'REVIEW_REQUIRED';
        }

        $allCandidates[] = [
            'natural_key'    => $natKey,
            'penyulang_id'   => $fId,
            'source_id'      => $uId,
            'target_id'      => $vId,
            'distance'       => $dist,
            'confidence'     => $conf,
            'classification' => $class,
            'sec_u'          => $secU,
            'sec_v'          => $secV,
            'evidence'       => $ev['evidence']
        ];

        $degrees[$uId]++;
        $degrees[$vId]++;
    }
}

echo "\n--- RESULTS OF CANDIDATE GENERATION WITH SECTION RESOLUTION ---\n";
echo "Total Candidates Generated: " . count($allCandidates) . "\n";
echo "  • AUTO_ACCEPT (>= 0.95)  : {$counts['AUTO_ACCEPT']}\n";
echo "  • WARNING_REVIEW (0.80-0.94): {$counts['WARNING_REVIEW']}\n";
echo "  • REVIEW_REQUIRED (< 0.80) : {$counts['REVIEW_REQUIRED']}\n\n";

echo "Rejection Breakdown:\n";
print_r($rejTaxonomy);

// Show top 10 candidates
usort($allCandidates, fn($a, $b) => $b['confidence'] <=> $a['confidence']);
echo "\nTop 10 Highest Confidence Candidates:\n";
for ($i = 0; $i < min(10, count($allCandidates)); $i++) {
    $c = $allCandidates[$i];
    echo sprintf(
        "  #%d | %s | Dist: %5.1fm | Sec: %d->%d | Conf: %0.4f (%s)\n",
        $i + 1, $c['natural_key'], $c['distance'], $c['sec_u'], $c['sec_v'], $c['confidence'], $c['classification']
    );
}
