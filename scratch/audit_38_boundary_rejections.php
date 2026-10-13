<?php

require_once 'e:/XAMPP/htdocs/SIDAK TEJO/vendor/autoload.php';

$outputJson = 'e:/XAMPP/htdocs/SIDAK TEJO/writable/audits/TOPOLOGY_CANDIDATE_DRY_RUN.json';
$secAuditFile = 'e:/XAMPP/htdocs/SIDAK TEJO/writable/audits/SECTION_RESOLUTION_AUDIT.json';
$assetsCsvFile= 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/prod_assets.csv';

$secAuditRaw = json_decode(file_get_contents($secAuditFile), true);
$secMap = [];
foreach ($secAuditRaw as $r) $secMap[$r['asset_id']] = $r;

$h = fopen($assetsCsvFile, 'r');
$header = fgetcsv($h);
$assetsById = [];
$totalAssets = 0;
while (($row = fgetcsv($h)) !== false) {
    $totalAssets++;
    $assetsById[$totalAssets] = [
        'id'          => $totalAssets,
        'kode_asset'  => $row[1],
        'nama_asset'  => $row[2],
        'jenis_asset' => $row[3],
        'tipe'        => $row[8] ?? '',
        'latitude'    => (float)$row[13],
        'longitude'   => (float)$row[14],
        'penyulang'   => $row[5],
        'sec_audit'   => $secMap[$totalAssets] ?? []
    ];
}
fclose($h);

// Re-generate the 38 violations with full forensic context
require_once 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/run_topology_candidate_engine_b17.php';

echo "======================================================================\n";
echo "🔍 FORENSIC AUDIT: 38 SECTION_BOUNDARY_VIOLATIONS\n";
echo "======================================================================\n";

$rejections = $run1['rejections'] ?? [];
echo "Total rejections recorded: " . count($rejections) . "\n\n";

$byFeeder = [];
foreach ($rejections as $idx => $r) {
    $uId = $r['source_id'];
    $vId = $r['target_id'];
    $u = $assetsById[$uId] ?? [];
    $v = $assetsById[$vId] ?? [];

    $fName = $u['penyulang'] ?? 'UNKNOWN';
    $byFeeder[$fName][] = [
        'idx'         => $idx + 1,
        'source_id'   => $uId,
        'source_name' => $u['nama_asset'] ?? '',
        'source_code' => $u['kode_asset'] ?? '',
        'source_type' => $u['jenis_asset'] ?? '',
        'source_sec'  => $r['source_section_id'] . ' (' . $r['source_section_name'] . ')',
        'target_id'   => $vId,
        'target_name' => $v['nama_asset'] ?? '',
        'target_code' => $v['kode_asset'] ?? '',
        'target_type' => $v['jenis_asset'] ?? '',
        'target_sec'  => $r['target_section_id'] . ' (' . $r['target_section_name'] . ')',
        'dist_m'      => $r['distance_meters'],
        'detail'      => $r['detail']
    ];
}

foreach ($byFeeder as $fName => $list) {
    echo "Penyulang: {$fName} (" . count($list) . " violations):\n";
    foreach ($list as $item) {
        echo sprintf(
            "  [%2d] %-18s (%s) -> %-18s (%s) | %5.1fm\n       SecA: %s\n       SecB: %s\n",
            $item['idx'],
            $item['source_name'], $item['source_type'],
            $item['target_name'], $item['target_type'],
            $item['dist_m'],
            $item['source_sec'],
            $item['target_sec']
        );
    }
    echo "\n";
}

file_put_contents('e:/XAMPP/htdocs/SIDAK TEJO/scratch/forensic_38_boundary_details.json', json_encode($byFeeder, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
