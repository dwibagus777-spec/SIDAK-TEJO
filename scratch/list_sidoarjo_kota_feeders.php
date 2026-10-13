<?php

$data = json_decode(file_get_contents('e:/XAMPP/htdocs/SIDAK TEJO/GLOBAL_NETWORK_TOPOLOGY_COVERAGE.json'), true);
$matrix = $data['feeder_matrix'] ?? [];

echo "=== ALL 45 FEEDERS IN ULP SIDOARJO KOTA (PRODUCTION) ===\n";
printf("%-4s | %-8s | %-30s | %-6s | %s\n", "ID", "Kode", "Nama Penyulang", "Assets", "Status");
echo str_repeat('-', 70) . "\n";

foreach ($matrix as $f) {
    if ($f['ulp_id'] == 1 || stripos($f['nama_ulp'], 'sidoarjo') !== false) {
        printf("%-4d | %-8s | %-30s | %-6d | %s\n",
            $f['penyulang_id'],
            $f['kode_penyulang'],
            $f['nama_penyulang'],
            $f['total_jtm_assets'],
            $f['total_jtm_assets'] > 0 ? "AKTIF ({$f['total_jtm_assets']} aset)" : "EMPTY (0 aset)"
        );
    }
}
