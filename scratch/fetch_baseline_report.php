<?php
$ch = curl_init('https://sidaktejo.site/master-assets/reconciliation-baseline');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$res = curl_exec($ch);
curl_close($ch);

$data = json_decode($res, true);
if ($data && isset($data['report'])) {
    $dir = __DIR__ . '/../writable/audits';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $targetPath = $dir . '/reconciliation_baseline_report.json';
    file_put_contents($targetPath, json_encode($data['report'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo "Saved reconciliation baseline to " . realpath($targetPath) . "\n";
    echo "Metadata: " . json_encode($data['report']['audit_metadata']) . "\n";
    echo "Asset totals: Raw=" . $data['report']['asset_reconciliation']['total_raw_assets'] 
         . ", Active=" . $data['report']['asset_reconciliation']['total_active_assets']
         . ", Valid Coords=" . $data['report']['asset_reconciliation']['valid_coordinates_count']
         . ", Valid Const=" . $data['report']['asset_reconciliation']['valid_construction_count'] . "\n";
} else {
    echo "Failed to decode report: " . substr($res, 0, 500) . "\n";
}
