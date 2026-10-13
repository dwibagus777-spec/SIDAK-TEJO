<?php
$json = json_decode(file_get_contents('GLOBAL_NETWORK_TOPOLOGY_COVERAGE.json'), true);
echo "=== FEEDERS WITH ACTIVE TRANSLINES ===\n";
$total = 0;
foreach ($json['feeder_matrix'] as $f) {
    if ($f['existing_translines'] > 0) {
        printf("Feeder #%-3d | %-25s | %-20s | %-5d translines | %-5d connected assets\n", 
            $f['penyulang_id'], 
            $f['nama_penyulang'], 
            $f['nama_ulp'], 
            $f['existing_translines'],
            $f['connected_assets']
        );
        $total += $f['existing_translines'];
    }
}
echo "--------------------------------------------------------------------------------\n";
echo "Total Active Translines Across All Feeders: {$total}\n";
echo "Total Rows in DB table 'gis_translines'   : 206\n";
echo "Difference (Soft-deleted / inactive)     : " . (206 - $total) . "\n";
