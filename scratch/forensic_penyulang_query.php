<?php

$data = json_decode(file_get_contents('e:/XAMPP/htdocs/SIDAK TEJO/GLOBAL_NETWORK_TOPOLOGY_COVERAGE.json'), true);
$matrix = $data['feeder_matrix'] ?? [];

echo "=== SEARCH RESULTS IN LIVE PRODUCTION SNAPSHOT ===\n";

echo "\n1. Check by feeder code 'PYL-016':\n";
foreach ($matrix as $f) {
    if ($f['kode_penyulang'] === 'PYL-016') {
        echo json_encode($f, JSON_PRETTY_PRINT) . "\n";
    }
}

echo "\n2. Check by name containing 'MASPION':\n";
foreach ($matrix as $f) {
    if (stripos($f['nama_penyulang'], 'maspion') !== false) {
        echo json_encode($f, JSON_PRETTY_PRINT) . "\n";
    }
}

echo "\n3. Check by id 16 or 28:\n";
foreach ($matrix as $f) {
    if ($f['penyulang_id'] === 16 || $f['penyulang_id'] === 28) {
        echo "ID: {$f['penyulang_id']} | Kode: {$f['kode_penyulang']} | Nama: {$f['nama_penyulang']} | ULP: {$f['nama_ulp']}\n";
    }
}

echo "\n4. Search for any feeder with '1' or '2' relating to Maspion:\n";
foreach ($matrix as $f) {
    if (stripos($f['nama_penyulang'], 'masp') !== false) {
        echo "Found: ID={$f['penyulang_id']}, Kode={$f['kode_penyulang']}, Nama='{$f['nama_penyulang']}', ULP={$f['nama_ulp']}\n";
    }
}
