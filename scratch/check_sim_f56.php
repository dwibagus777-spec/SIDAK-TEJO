<?php
$data = json_decode(file_get_contents(__DIR__ . '/tl_mf02_dry_run_simulation.json'), true);
foreach ($data['feeders_report'] as $f) {
    if ($f['penyulang_id'] == 56) {
        echo "Feeder 56 in simulation:\n";
        echo "Status: " . ($f['status'] ?? 'N/A') . "\n";
        echo "Projected count: " . ($f['projected_count'] ?? 0) . "\n";
        echo "Held semantic spans: " . ($f['held_semantic_spans'] ?? 0) . "\n";
        echo "Blocked short spans: " . ($f['blocked_short_spans'] ?? 0) . "\n";
        echo "Projected candidates:\n";
        foreach ($f['projected_batch'] as $idx => $p) {
            echo sprintf("[%02d] %s | %s -> %s | %.2fm | Score: %d\n", 
                $idx + 1, 
                $p['natural_key'], 
                $p['source_asset_code'], 
                $p['target_asset_code'], 
                $p['distance_meters'], 
                $p['total_score']
            );
        }
        break;
    }
}

