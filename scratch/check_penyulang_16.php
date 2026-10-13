<?php
$data = json_decode(file_get_contents(__DIR__ . '/../GLOBAL_NETWORK_TOPOLOGY_COVERAGE.json'), true);
echo "Total feeders in matrix: " . count($data['feeder_matrix']) . "\n";
foreach ($data['feeder_matrix'] as $f) {
    if (stripos($f['nama_penyulang'], 'maspion') !== false || stripos($f['kode_penyulang'], '16') !== false || $f['penyulang_id'] == 16) {
        echo "Found: ID={$f['penyulang_id']}, Kode={$f['kode_penyulang']}, Nama={$f['nama_penyulang']}, ULP={$f['nama_ulp']}, Assets={$f['total_jtm_assets']}\n";
    }
}
