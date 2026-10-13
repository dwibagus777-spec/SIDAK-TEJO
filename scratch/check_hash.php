<?php
$h1 = "2e0477b238d5e352be5e9e70d2f57d42b808fd7d8d1d409dc40614c59193152a";
$h2 = "2e0477b238d5e352be5e9e70d2f57d42b808fd7d8d409dc40614c59193152a";

echo "h1: {$h1} (len=" . strlen($h1) . ")\n";
echo "h2: {$h2} (len=" . strlen($h2) . ")\n";

$url = 'https://sidaktejo.site/mobile-sync/forensic-reconciliation';
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RESOLVE, ['sidaktejo.site:443:2.57.91.151', 'sidaktejo.site:80:2.57.91.151']);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-Audit-Token: sidak_transline_audit_2026']);
$res = curl_exec($ch);
curl_close($ch);

$data = json_decode($res, true);
$prodHash = $data['reconciliation_pack']['layer_2_physical_database']['physical_assets_hash'] ?? null;
echo "Live Production Server physical_assets_hash: {$prodHash} (len=" . strlen($prodHash) . ")\n";
echo "Matches h1? " . ($prodHash === $h1 ? "YES" : "NO") . "\n";
echo "Matches h2? " . ($prodHash === $h2 ? "YES" : "NO") . "\n";
