<?php
$dryRunFile = __DIR__ . '/../writable/audits/ONE_SHOT_CONSOLIDATED_DRY_RUN_REPORT.json';
$dryRun = json_decode(file_get_contents($dryRunFile), true);

$mappedFile = __DIR__ . '/mapped_26_kodes.json';
$mapped = json_decode(file_get_contents($mappedFile), true);

echo "Total mapped: " . count($mapped) . "\n";
foreach ($mapped as $c) {
    echo "Cand {$c['idx']}: {$c['natural_key']} | P={$c['penyulang_id']} | {$c['source_asset_name']} ({$c['source_kode_asset']}) -> {$c['target_asset_name']} ({$c['target_kode_asset']})\n";
}
