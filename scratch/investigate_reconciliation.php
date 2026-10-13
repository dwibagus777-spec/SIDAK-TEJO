<?php

$_SERVER['CI_ENVIRONMENT'] = 'development';
putenv('CI_ENVIRONMENT=development');
require __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';

$db = \Config\Database::connect('default');

echo "=== LOCAL ENVIRONMENT (127.0.0.1) ===\n";
echo "Database Name: " . $db->getDatabase() . "\n";
echo "Hostname: " . $db->hostname . "\n";

// Local counts
$tlPhysical = $db->table('gis_translines')->countAllResults();
$tlActive = $db->table('gis_translines')->where('is_active', 1)->where('deleted_at IS NULL')->countAllResults();
$tlInactive = $db->table('gis_translines')->where('is_active', 0)->orWhere('deleted_at IS NOT NULL')->countAllResults();

$assetPhysical = $db->table('assets')->countAllResults();
$assetActive = $db->table('assets')->where('deleted_at IS NULL')->countAllResults();
$assetDeleted = $db->table('assets')->where('deleted_at IS NOT NULL')->countAllResults();

echo "Local gis_translines:\n";
echo " - Physical: {$tlPhysical}\n";
echo " - Active: {$tlActive}\n";
echo " - Inactive: {$tlInactive}\n";

echo "Local assets:\n";
echo " - Physical: {$assetPhysical}\n";
echo " - Active: {$assetActive}\n";
echo " - Deleted: {$assetDeleted}\n";

// Check feeders breakdown in local gis_translines
$localFeeders = $db->query("SELECT penyulang_id, COUNT(*) as cnt FROM gis_translines GROUP BY penyulang_id ORDER BY cnt DESC LIMIT 10")->getResultArray();
echo "\nLocal Feeders in gis_translines (Top 10):\n";
foreach ($localFeeders as $lf) {
    echo " - Feeder ID {$lf['penyulang_id']}: {$lf['cnt']} translines\n";
}

// Check feeder 15 specifically in local
$f15Translines = $db->table('gis_translines')->where('penyulang_id', 15)->countAllResults();
$f15Assets = $db->table('assets')->where('penyulang_id', 15)->countAllResults();
echo "\nLocal Feeder 15 counts:\n";
echo " - gis_translines (feeder 15): {$f15Translines}\n";
echo " - assets (feeder 15): {$f15Assets}\n";

// Now query remote production
echo "\n=== REMOTE PRODUCTION (https://sidaktejo.site) ===\n";
$auditKey = 'sidak_transline_audit_2026';
$ch = curl_init("https://sidaktejo.site/fault-ingestion/forensic-reconciliation?key={$auditKey}");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_RESOLVE, ['sidaktejo.site:443:2.57.91.151', 'sidaktejo.site:80:2.57.91.151']);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Production HTTP Status: {$code}\n";
if ($code === 200) {
    $prodData = json_decode($res, true);
    $recon = $prodData['reconciliation'] ?? [];
    echo "Production Translines:\n";
    echo " - Authoritative Active: " . ($recon['translines']['authoritative_active'] ?? 'N/A') . "\n";
    echo " - Physical Total: " . ($recon['translines']['physical_total_table'] ?? 'N/A') . "\n";
    echo "Production Assets:\n";
    echo " - Authoritative Active: " . ($recon['assets']['authoritative_active'] ?? 'N/A') . "\n";
    echo " - Physical Total: " . ($recon['assets']['physical_total_table'] ?? 'N/A') . "\n";
    echo "Production Topology Snapshot: " . ($recon['topology_snapshot']['snapshot_id'] ?? 'N/A') . "\n";
} else {
    echo "Failed to query production: " . substr($res, 0, 300) . "\n";
}
