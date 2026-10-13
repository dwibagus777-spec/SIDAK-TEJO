<?php

$file = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/resp_global_topology_audit.json';
$data = json_decode(file_get_contents($file), true);

$matrix = $data['feeder_matrix'] ?? [];
echo "Total Feeders in Matrix: " . count($matrix) . "\n";

if (!empty($matrix)) {
    echo "Sample Feeder 0:\n";
    print_r($matrix[0]);
}

// Check Feeder 118, 119, 23
foreach ($matrix as $f) {
    if (in_array($f['penyulang_id'], [118, 119, 23])) {
        echo sprintf("\nFeeder #%-3d | %-20s | ULP: %d | JTM: %d | Valid GPS: %d | Existing TL: %d | Connected: %d | Status: %s\n",
            $f['penyulang_id'],
            $f['nama_penyulang'],
            $f['ulp_id'],
            $f['total_jtm_assets'],
            $f['valid_gps_assets'],
            $f['existing_translines'],
            $f['connected_assets'],
            $f['classification'] ?? $f['status'] ?? 'N/A'
        );
    }
}
