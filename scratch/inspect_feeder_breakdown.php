<?php

$data = json_decode(file_get_contents('e:/XAMPP/htdocs/SIDAK TEJO/GLOBAL_NETWORK_TOPOLOGY_COVERAGE.json'), true);
$matrix = $data['feeder_matrix'] ?? [];

$withAssets = [];
$noAssets = [];

foreach ($matrix as $f) {
    if (($f['total_jtm_assets'] ?? 0) > 0) {
        $withAssets[] = $f;
    } else {
        $noAssets[] = $f;
    }
}

echo "Total Feeders: " . count($matrix) . "\n";
echo "Feeders with JTM assets: " . count($withAssets) . "\n";
echo "Feeders with 0 JTM assets: " . count($noAssets) . "\n\n";

// Sort withAssets by total_jtm_assets DESC
usort($withAssets, fn($a, $b) => $b['total_jtm_assets'] <=> $a['total_jtm_assets']);

printf("%-4s | %-6s | %-24s | %-16s | %-6s | %-6s | %-6s | %-6s | %-6s | %s\n",
    "ID", "Kode", "Nama Penyulang", "ULP", "Assets", "TLs", "Conn", "Isol", "Auto", "Class");
echo str_repeat('-', 110) . "\n";

foreach ($withAssets as $f) {
    printf("%-4d | %-6s | %-24s | %-16s | %-6d | %-6d | %-6d | %-6d | %-6d | %s\n",
        $f['penyulang_id'],
        $f['kode_penyulang'],
        substr($f['nama_penyulang'], 0, 24),
        substr($f['nama_ulp'], 0, 16),
        $f['total_jtm_assets'],
        $f['existing_translines'],
        $f['connected_assets'],
        $f['isolated_assets'],
        $f['auto_complete'] ?? 0,
        substr($f['classification'], 0, 20)
    );
}
