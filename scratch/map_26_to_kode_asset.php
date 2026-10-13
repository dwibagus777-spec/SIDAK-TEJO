<?php

$cands = json_decode(file_get_contents('e:/XAMPP/htdocs/SIDAK TEJO/scratch/embedded_26_candidates.json'), true);

$csv = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/prod_assets.csv';
$h = fopen($csv, 'r');
$header = fgetcsv($h);

$assetsByRowNo = [];
$assetsByName = [];
$assetsByKode = [];

$rowNo = 0;
while (($row = fgetcsv($h)) !== false) {
    $rowNo++;
    $item = [
        'row_no'     => $rowNo,
        'kode_asset' => trim($row[1]),
        'nama_asset' => trim($row[2]),
        'feeder'     => trim($row[5]),
    ];
    $assetsByRowNo[$rowNo] = $item;
    $assetsByName[$item['nama_asset']] = $item;
    $assetsByKode[$item['kode_asset']] = $item;
}
fclose($h);

echo "Loaded {$rowNo} assets from CSV.\n\n";

printf("%-3s | %-20s | %-4s | %-22s (%-25s) -> %-22s (%-25s)\n",
    "No", "Natural Key", "FId", "Src Name", "Src Kode", "Tgt Name", "Tgt Kode");
echo str_repeat("-", 130) . "\n";

$mapped = [];
foreach ($cands as $i => $c) {
    $srcRow = $c['source_asset_id'];
    $tgtRow = $c['target_asset_id'];

    $srcAsset = $assetsByRowNo[$srcRow] ?? null;
    $tgtAsset = $assetsByRowNo[$tgtRow] ?? null;

    $srcKode = $srcAsset['kode_asset'] ?? 'NOT_FOUND';
    $tgtKode = $tgtAsset['kode_asset'] ?? 'NOT_FOUND';

    printf("%-3d | %-20s | %-4d | %-22s (%-25s) -> %-22s (%-25s)\n",
        $i + 1,
        $c['natural_key'],
        $c['penyulang_id'],
        $c['source_asset_name'],
        $srcKode,
        $c['target_asset_name'],
        $tgtKode
    );

    $mapped[] = [
        'idx'               => $i + 1,
        'natural_key'       => $c['natural_key'],
        'penyulang_id'      => $c['penyulang_id'],
        'source_row_no'     => $srcRow,
        'target_row_no'     => $tgtRow,
        'source_asset_name' => $c['source_asset_name'],
        'target_asset_name' => $c['target_asset_name'],
        'source_kode_asset' => $srcKode,
        'target_kode_asset' => $tgtKode,
        'distance_meters'   => $c['distance_meters'],
        'confidence'        => $c['confidence'],
        'geometry'          => $c['geometry'],
        'conductor_type'    => $c['conductor_type'],
        'conductor_size'    => $c['conductor_size'],
    ];
}

file_put_contents('e:/XAMPP/htdocs/SIDAK TEJO/scratch/mapped_26_kodes.json', json_encode($mapped, JSON_PRETTY_PRINT));
echo "\nSaved mapped 26 to scratch/mapped_26_kodes.json\n";
