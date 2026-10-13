<?php
$json = json_decode(file_get_contents('e:/XAMPP/htdocs/SIDAK TEJO/GLOBAL_NETWORK_TOPOLOGY_COVERAGE.json'), true);

$hasAssets = array_filter($json['feeder_matrix'], fn($f) => $f['total_jtm_assets'] > 0);

usort($hasAssets, fn($a, $b) => $b['total_jtm_assets'] <=> $a['total_jtm_assets']);

echo "All 33 Feeders with Assets:\n";
echo sprintf("%-4s | %-10s | %-24s | %-18s | %-6s | %-6s | %-6s | %-6s | %-8s | %-8s\n", 
    "ID", "KODE", "NAMA PENYULANG", "ULP", "ASSET", "TL", "CONN", "ISO", "HI_CONF", "REVIEW"
);
echo str_repeat("-", 108) . "\n";
foreach ($hasAssets as $f) {
    echo sprintf("%-4d | %-10s | %-24s | %-18s | %-6d | %-6d | %-6d | %-6d | %-8d | %-8d\n",
        $f['penyulang_id'],
        $f['kode_penyulang'],
        substr($f['nama_penyulang'], 0, 24),
        substr($f['nama_ulp'], 0, 18),
        $f['total_jtm_assets'],
        $f['existing_translines'],
        $f['connected_assets'],
        $f['isolated_assets'],
        $f['high_confidence'],
        $f['review_required']
    );
}
