<?php

require_once 'e:/XAMPP/htdocs/SIDAK TEJO/vendor/autoload.php';
use App\Services\OneShotNetworkIngestionService;

$secAuditFile = 'e:/XAMPP/htdocs/SIDAK TEJO/writable/audits/SECTION_RESOLUTION_AUDIT.json';
$assetsCsvFile= 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/prod_assets.csv';

$secAuditRaw = json_decode(file_get_contents($secAuditFile), true);
$secMap = [];
foreach ($secAuditRaw as $r) $secMap[$r['asset_id']] = $r;

$h = fopen($assetsCsvFile, 'r');
$header = fgetcsv($h);
$f118Assets = [];
$totalAssets = 0;
while (($row = fgetcsv($h)) !== false) {
    $totalAssets++;
    if (trim($row[5] ?? '') === 'BAHAGIA STEEL 1') {
        $seq = 0;
        if (preg_match('/_(\d+)$/', $row[2], $m)) $seq = (int)$m[1];
        elseif (preg_match('/-(\d+)$/', $row[1], $m)) $seq = (int)$m[1];

        $secAudit = $secMap[$totalAssets] ?? null;
        $f118Assets[] = [
            'id'          => $totalAssets,
            'kode_asset'  => $row[1],
            'nama_asset'  => $row[2],
            'latitude'    => (float)$row[13],
            'longitude'   => (float)$row[14],
            'status'      => $row[15] ?? 'NORMAL',
            'sequence_no' => $seq,
            'penyulang_id'=> 118,
            'ulp_id'      => 2,
            'section_id'  => (int)($secAudit['resolved_section_id'] ?? 0),
            'section_name'=> $secAudit['resolved_section_name'] ?? '',
            'sec_status'  => $secAudit['status'] ?? 'UNRESOLVED'
        ];
    }
}
fclose($h);

usort($f118Assets, fn($a, $b) => $a['sequence_no'] <=> $b['sequence_no']);

echo "Feeder 118 Assets (45 assets):\n";
echo sprintf("%-4s | %-25s | %-12s | %-8s | %-20s\n", "No", "Kode Asset", "Nama", "Sec ID", "Sec Status");
echo str_repeat("-", 75) . "\n";
foreach ($f118Assets as $idx => $a) {
    echo sprintf("%-4d | %-25s | %-12s | %-8d | %-20s\n", $idx + 1, $a['kode_asset'], $a['nama_asset'], $a['section_id'], $a['sec_status']);
}

echo "\nFeeder 118 Consecutive Spans Evaluation:\n";
$degrees = array_fill_keys(array_column($f118Assets, 'id'), 0);
for ($i = 0; $i < count($f118Assets) - 1; $i++) {
    $u = $f118Assets[$i];
    $v = $f118Assets[$i+1];
    $dist = OneShotNetworkIngestionService::haversineDistance($u['latitude'], $u['longitude'], $v['latitude'], $v['longitude']);

    $verdict = '';
    if ($dist < 1.0 || $dist > 120.0) {
        $verdict = "REJECTED (IMPOSSIBLE_DISTANCE: " . round($dist, 1) . "m)";
    } elseif ($u['section_id'] > 0 && $v['section_id'] > 0 && $u['section_id'] !== $v['section_id']) {
        $verdict = "REJECTED (SECTION_BOUNDARY_VIOLATION: sec {$u['section_id']} != sec {$v['section_id']}, dist: " . round($dist, 1) . "m)";
    } else {
        $ev = OneShotNetworkIngestionService::computeEvidenceAndConfidence($u, $v, $dist, $degrees[$u['id']], $degrees[$v['id']]);
        $verdict = "ACCEPTED CANDIDATE (conf: " . $ev['confidence'] . ", dist: " . round($dist, 1) . "m)";
        $degrees[$u['id']]++;
        $degrees[$v['id']]++;
    }
    echo sprintf("  Span %2d -> %2d (%s -> %s): %s\n", $i+1, $i+2, $u['nama_asset'], $v['nama_asset'], $verdict);
}
