<?php
/**
 * Forensic Step 1: Diagnose exact DB error in investigateTopologyDelta
 * — Inspect gis_translines schema first, then run targeted queries
 */

$baseUrl    = 'https://sidaktejo.site';
$auditToken = 'sidak_transline_audit_2026';
$resolve    = ['sidaktejo.site:443:2.57.91.151', 'sidaktejo.site:80:2.57.91.151'];

function curlGet(string $url, array $headers = [], array $resolve = []): array {
    $c = curl_init($url);
    curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($c, CURLOPT_TIMEOUT, 30);
    curl_setopt($c, CURLOPT_HTTPHEADER, $headers);
    if ($resolve) curl_setopt($c, CURLOPT_RESOLVE, $resolve);
    $body = curl_exec($c);
    $code = curl_getinfo($c, CURLINFO_HTTP_CODE);
    $err  = curl_error($c);
    curl_close($c);
    return ['code' => $code, 'body' => $body, 'error' => $err];
}

$headers = [
    'X-Audit-Token: sidak_transline_audit_2026',
    'Accept: application/json',
];

// Use the existing forensic-reconciliation endpoint to diagnose the error
echo "=== CHECKING forensic-reconciliation (known working) ===\n";
$r = curlGet("{$baseUrl}/remediation/forensic-reconciliation", $headers, $resolve);
echo "HTTP: {$r['code']}\n";
$d = json_decode($r['body'], true);

// Check what columns the active TL query uses
$layer2 = $d['reconciliation_pack']['layer_2_physical_database'] ?? [];
$layer3 = $d['reconciliation_pack']['layer_3_authoritative_view'] ?? [];
echo "Physical TL count : " . ($layer2['physical_translines_count'] ?? 'n/a') . "\n";
echo "Active TL count   : " . ($layer3['active_translines_count']   ?? 'n/a') . "\n";
echo "\nReconciliation verdict: " . ($d['reconciliation_verdict'] ?? 'n/a') . "\n";

// Now check the /remediation/audit endpoint to see the DB error trace
echo "\n=== CHECKING /remediation/audit ===\n";
$r2 = curlGet("{$baseUrl}/remediation/audit", $headers, $resolve);
echo "HTTP: {$r2['code']}\n";
$d2 = json_decode($r2['body'], true);
if ($d2) {
    echo "gate   : " . ($d2['gate']   ?? 'n/a') . "\n";
    echo "status : " . ($d2['status'] ?? 'n/a') . "\n";
    $sentinel = $d2['topology_sentinel'] ?? [];
    echo "active_translines  : " . ($sentinel['active_translines']   ?? 'n/a') . "\n";
    echo "physical_translines: " . ($sentinel['physical_translines']  ?? 'n/a') . "\n";
} else {
    echo substr($r2['body'], 0, 500) . "\n";
}

echo "\n=== DONE ===\n";
