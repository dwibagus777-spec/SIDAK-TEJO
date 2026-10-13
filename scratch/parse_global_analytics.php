<?php

$file = 'e:/XAMPP/htdocs/SIDAK TEJO/scratch/resp_analytics_global.json';
if (!file_exists($file)) {
    die("File not found\n");
}

$data = json_decode(file_get_contents($file), true);

echo "=== GLOBAL ACTIVE GIS_TRANSLINES TRUTH (from Production Database) ===\n";
echo "Status: " . ($data['status'] ?? 'N/A') . "\n";
echo "Total Active Translines: " . ($data['summary']['transline_resmi_count'] ?? 'N/A') . "\n";
echo "Total Panjang Meter: " . ($data['summary']['total_panjang_meter'] ?? 'N/A') . "\n";
echo "Total Panjang KM: " . ($data['summary']['total_panjang_km'] ?? 'N/A') . "\n";
echo "Penyulang Count: " . ($data['summary']['penyulang_count'] ?? 'N/A') . "\n";
echo "Distinct Assets Count: " . ($data['summary']['distinct_assets_count'] ?? 'N/A') . "\n";

echo "\n--- Breakdown Per Penyulang ---\n";
$feeders = $data['breakdown']['feeders'] ?? [];
$sumCount = 0;
foreach ($feeders as $f) {
    echo sprintf("Feeder #%-3d | %-25s | Lines: %3d | Length: %7.2f km | ULP: %s\n", 
        $f['id'], 
        $f['name'], 
        $f['count'], 
        $f['length_km'], 
        $f['ulp_name'] ?? '-'
    );
    $sumCount += $f['count'];
}
echo "------------------------------------------------------------\n";
echo "Sum of feeder translines: " . $sumCount . "\n";
