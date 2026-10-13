<?php

$csvFile = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/prod_assets.csv';
$auditFile = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/resp_global_topology_audit.json';

$auditData = json_decode(file_get_contents($auditFile), true);
$feederMatrix = $auditData['feeder_matrix'] ?? [];

// Map feeder name -> feeder details
$feederByName = [];
$feederById = [];
foreach ($feederMatrix as $f) {
    $fNameNorm = strtoupper(trim($f['nama_penyulang']));
    $feederByName[$fNameNorm] = $f;
    $feederById[(int)$f['penyulang_id']] = $f;
}

// Read CSV
$h = fopen($csvFile, 'r');
$header = fgetcsv($h);

$assetsByFeeder = [];
$unmatchedAssets = [];
$invalidCoords = [];
$rowNum = 0;

while (($row = fgetcsv($h)) !== false) {
    $rowNum++;
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
    if (!isset($feederByName[$feederKey])) {
        $unmatchedAssets[] = "Row {$rowNum}: Unknown feeder '{$feederName}' for asset {$kodeAsset}";
        continue;
    }

    $feederInfo = $feederByName[$feederKey];
    $feederId   = (int)$feederInfo['penyulang_id'];
    $ulpId      = (int)$feederInfo['ulp_id'];

    if ($lat == 0.0 || $lng == 0.0 || abs($lat) > 90 || abs($lng) > 180) {
        $invalidCoords[] = "Row {$rowNum}: {$kodeAsset} (Lat: {$lat}, Lng: {$lng})";
    }

    $assetsByFeeder[$feederId][] = [
        'id'           => $rowNum, // deterministic sequential index
        'row_no'       => $rowNum,
        'kode_asset'   => $kodeAsset,
        'nama_asset'   => $namaAsset,
        'jenis_asset'  => $jenisAsset,
        'penyulang_id' => $feederId,
        'nama_penyulang' => $feederName,
        'ulp_id'       => $ulpId,
        'nama_ulp'     => $ulpName,
        'section_name' => $sectionName,
        'section_id'   => 0, // default if not numeric
        'latitude'     => $lat,
        'longitude'    => $lng,
        'status'       => $status,
        'sequence_no'  => $rowNum
    ];
}
fclose($h);

echo "Total CSV Assets Processed: {$rowNum}\n";
echo "Matched to Feeders: " . (count($assetsByFeeder)) . " feeders\n";
echo "Unmatched Assets: " . count($unmatchedAssets) . "\n";
echo "Invalid GPS Assets: " . count($invalidCoords) . "\n";

$sumAssets = 0;
foreach ($assetsByFeeder as $fid => $flist) {
    $sumAssets += count($flist);
}
echo "Sum of Assets in Feeders: {$sumAssets}\n";
