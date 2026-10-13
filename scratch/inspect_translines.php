<?php
$json = json_decode(file_get_contents('e:/XAMPP/htdocs/SIDAK TEJO/GLOBAL_NETWORK_TOPOLOGY_COVERAGE.json'), true);

$hasTranslines = [];
$hasAssets = [];
$noAssets = [];

foreach ($json['feeder_matrix'] as $f) {
    if ($f['existing_translines'] > 0) {
        $hasTranslines[] = $f;
    }
    if ($f['total_jtm_assets'] > 0) {
        $hasAssets[] = $f;
    } else {
        $noAssets[] = $f;
    }
}

echo "Feeders with translines (" . count($hasTranslines) . "):\n";
foreach ($hasTranslines as $f) {
    echo sprintf("  - ID %3d [%-10s] %-25s (%-18s): Assets=%3d, Translines=%3d, Conn=%3d, Iso=%3d\n",
        $f['penyulang_id'], $f['kode_penyulang'], $f['nama_penyulang'], $f['nama_ulp'], $f['total_jtm_assets'], $f['existing_translines'], $f['connected_assets'], $f['isolated_assets']
    );
}

echo "\nFeeders with assets: " . count($hasAssets) . "\n";
echo "Feeders with NO assets: " . count($noAssets) . "\n";

