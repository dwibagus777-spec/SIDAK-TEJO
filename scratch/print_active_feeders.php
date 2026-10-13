<?php
$data = json_decode(file_get_contents('writable/audits/GLOBAL_PRODUCTION_DRY_RUN_REPORT.json'), true);
echo sprintf("%-4s | %-5s | %-24s | %-6s | %-7s | %-6s | %-6s | %-6s | %-6s | %-6s | %-20s\n",
    "No", "ID", "Nama Penyulang", "Assets", "Auth TL", "Cand.", "Accept", "Warn", "Review", "Reject", "Status"
);
echo str_repeat("-", 115) . "\n";

$no = 1;
$activeCount = 0;
$emptyCount = 0;
foreach ($data['all_134_feeders'] as $f) {
    if ($f['total_assets'] > 0) {
        $activeCount++;
        echo sprintf("%-4d | %-5d | %-24s | %-6d | %-7d | %-6d | %-6d | %-6d | %-6d | %-6d | %-20s\n",
            $no++,
            $f['penyulang_id'],
            substr($f['nama_penyulang'], 0, 24),
            $f['total_assets'],
            $f['existing_translines'],
            $f['candidates_generated'],
            $f['accepted'],
            $f['warning'],
            $f['review'],
            $f['rejected'],
            $f['status']
        );
    } else {
        $emptyCount++;
    }
}
echo str_repeat("-", 115) . "\n";
echo "Active Feeders with Assets: {$activeCount} | Empty Feeders: {$emptyCount} | Total: " . ($activeCount + $emptyCount) . "\n";
