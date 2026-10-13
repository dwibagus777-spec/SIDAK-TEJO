<?php

require_once 'e:/XAMPP/htdocs/SIDAK TEJO/vendor/autoload.php';

use App\Services\OneShotNetworkIngestionService;

$auditFile     = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/resp_global_topology_audit.json';
$assetsCsvFile = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/prod_assets.csv';
$globalTlFile  = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/resp_analytics_global.json';

// Load
$auditData = json_decode(file_get_contents($auditFile), true);
$feederMatrix = $auditData['feeder_matrix'] ?? [];

$feeders = [];
$feederByName = [];
foreach ($feederMatrix as $f) {
    $fId = (int)$f['penyulang_id'];
    $fName = strtoupper(trim($f['nama_penyulang']));
    $feeders[] = [
        'id'             => $fId,
        'kode_penyulang' => $f['kode_penyulang'],
        'nama_penyulang' => $f['nama_penyulang'],
        'ulp_id'         => (int)$f['ulp_id'],
        'nama_ulp'       => $f['nama_ulp'] ?? ''
    ];
    $feederByName[$fName] = [
        'id'     => $fId,
        'ulp_id' => (int)$f['ulp_id'],
        'name'   => $f['nama_penyulang']
    ];
}

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
    if (!$feederInfo) continue;

    $fId = $feederInfo['id'];
    $uId = $feederInfo['ulp_id'];

    // Extract natural pole sequence number if present (e.g. from JP4_98 or BHGSTL1_015)
    $seq = 0;
    if (preg_match('/_(\d+)$/', $namaAsset, $m)) {
        $seq = (int)$m[1];
    } elseif (preg_match('/-(\d+)$/', $kodeAsset, $m)) {
        $seq = (int)$m[1];
    }

    $assetsByFeeder[$fId][] = [
        'id'             => $totalAssets,
        'kode_asset'     => $kodeAsset,
        'nama_asset'     => $namaAsset,
        'jenis_asset'    => $jenisAsset,
        'penyulang_id'   => $fId,
        'nama_penyulang' => $feederInfo['name'],
        'ulp_id'         => $uId,
        'nama_ulp'       => $ulpName,
        'section_name'   => $sectionName,
        'section_id'     => 0,
        'latitude'       => $lat,
        'longitude'      => $lng,
        'status'         => $status,
        'sequence_no'    => $seq
    ];
}
fclose($h);

// Load 217 existing authoritative translines
$tlData = json_decode(file_get_contents($globalTlFile), true);
$existingTranslines = $tlData['items'] ?? [];

$existingNaturalKeys = [];
$existingByFeeder = [];
foreach ($existingTranslines as $tl) {
    $fId = (int)$tl['penyulang_id'];
    $src = (int)$tl['source_asset_id'];
    $tgt = (int)$tl['target_asset_id'];
    $natKey = OneShotNetworkIngestionService::buildNaturalKey($fId, $src, $tgt);
    $existingNaturalKeys[$natKey] = (int)$tl['transline_id'];
    $existingByFeeder[$fId][] = $tl;
}

$sourceData = [
    'total_feeders'            => count($feeders),
    'feeders_with_assets'      => count($assetsByFeeder),
    'feeders_without_assets'   => count($feeders) - count($assetsByFeeder),
    'total_active_assets'      => $totalAssets,
    'total_existing_translines'=> count($existingTranslines),
    'feeders'                  => $feeders,
    'assets_by_feeder'         => $assetsByFeeder,
    'assets_by_ulp'            => [],
    'assets_by_section'        => [],
    'existing_natural_keys'    => $existingNaturalKeys,
    'existing_translines_by_f' => $existingByFeeder
];

$service = new OneShotNetworkIngestionService();
$dryRun = $service->runDryRun($sourceData);

echo "=== RESULTS WITH NATURAL POLE SEQUENCE SORTING ===\n";
echo "Total Candidates Generated : " . $dryRun['summary']['total_candidates_generated'] . "\n";
echo "  • AUTO_ACCEPT (>= 0.95)   : " . $dryRun['summary']['accepted_candidates'] . "\n";
echo "  • WARNING_REVIEW          : " . $dryRun['summary']['warning_review_candidates'] . "\n";
echo "  • REVIEW_REQUIRED         : " . $dryRun['summary']['review_required_candidates'] . "\n";
echo "Rejected Candidates         : " . $dryRun['summary']['rejected_candidates'] . "\n";
echo "Estimated New Length        : " . $dryRun['summary']['estimated_total_new_length_km'] . " km\n";

echo "\nRejections Breakdown:\n";
foreach ($dryRun['rejection_breakdown'] as $k => $v) {
    echo "  {$k}: {$v}\n";
}

echo "\nFeeder 118 Breakdown:\n";
foreach ($dryRun['feeder_breakdown'] as $fb) {
    if ($fb['penyulang_id'] == 118) {
        print_r($fb);
    }
}
