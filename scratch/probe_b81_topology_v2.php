<?php
/**
 * B8.1 Quick Topology Probe — corrected key paths
 * The /remediation/audit endpoint uses 'topology_sentinel' not 'sentinel'
 */

$baseUrl = 'https://sidaktejo.site';
$auditToken = getenv('SIDAK_AUDIT_TOKEN') ?: 'sidak_transline_audit_2026';
$resolve = ['sidaktejo.site:443:2.57.91.151'];

function curlGet(string $url, array $headers, array $resolve): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_RESOLVE, $resolve);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $body = curl_exec($ch);
    $http  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err   = curl_error($ch);
    curl_close($ch);
    return ['http' => $http, 'body' => $body, 'error' => $err];
}

$headers = ['X-Audit-Token: ' . $auditToken, 'Accept: application/json'];

echo "=== B8.1 TOPOLOGY PROBE (" . date('H:i:s') . ") ===\n\n";

// ── /remediation/audit ─────────────────────────────────────────
echo "── [1] /remediation/audit\n";
$r1 = curlGet($baseUrl . '/remediation/audit', $headers, $resolve);
echo "HTTP: {$r1['http']}\n";
$d1 = json_decode($r1['body'], true);
if ($d1 && isset($d1['topology_sentinel'])) {
    $s = $d1['topology_sentinel'];
    $at = $s['active_translines']   ?? 'N/A';
    $pt = $s['physical_translines'] ?? 'N/A';
    $aa = $s['active_assets']       ?? 'N/A';
    $pa = $s['physical_assets']     ?? 'N/A';
    $sp = $s['network_span_meters'] ?? $s['network_span'] ?? 'N/A';
    $sn = $s['snapshot_id'] ?? 'N/A';
    echo "active_TL=$at  physical_TL=$pt  active_AA=$aa  physical_PA=$pa  span=$sp  snap=$sn\n";
    // hashes
    $h_at = $s['active_tl_sha256']       ?? $s['active_translines_sha256'] ?? 'N/A';
    $h_pt = $s['physical_tl_sha256']     ?? $s['physical_translines_sha256'] ?? 'N/A';
    $h_aa = $s['active_assets_sha256']   ?? 'N/A';
    $h_pa = $s['physical_assets_sha256'] ?? 'N/A';
    echo "h_active_TL  : $h_at\n";
    echo "h_physical_TL: $h_pt\n";
    echo "h_active_AA  : $h_aa\n";
    echo "h_physical_PA: $h_pa\n";
    // delta vs baseline
    $baseline_at = 243; $baseline_pt = 252; $baseline_aa = 5236; $baseline_pa = 5549; $baseline_sp = 9418.37;
    echo "\nΔ vs Phase0 baseline:\n";
    echo "  Δ active_TL   = " . ($at !== 'N/A' ? ($at - $baseline_at)  : 'N/A') . "\n";
    echo "  Δ physical_TL = " . ($pt !== 'N/A' ? ($pt - $baseline_pt)  : 'N/A') . "\n";
    echo "  Δ active_AA   = " . ($aa !== 'N/A' ? ($aa - $baseline_aa)  : 'N/A') . "\n";
    echo "  Δ physical_PA = " . ($pa !== 'N/A' ? ($pa - $baseline_pa)  : 'N/A') . "\n";
    echo "  Δ span        = " . ($sp !== 'N/A' ? round($sp - $baseline_sp, 2) : 'N/A') . "\n";
} elseif ($d1) {
    echo "topology_sentinel key missing — available: " . implode(', ', array_keys($d1)) . "\n";
    // Dump top-level values (non-array)
    foreach ($d1 as $k => $v) {
        if (!is_array($v)) echo "  $k: $v\n";
    }
} else {
    echo "JSON parse error: " . json_last_error_msg() . "\n";
    echo substr($r1['body'], 0, 400) . "\n";
}

echo "\n";

// ── /mobile-sync/forensic-reconciliation ──────────────────────
echo "── [2] /mobile-sync/forensic-reconciliation\n";
$r2 = curlGet($baseUrl . '/mobile-sync/forensic-reconciliation', $headers, $resolve);
echo "HTTP: {$r2['http']}\n";
$d2 = json_decode($r2['body'], true);
if ($d2) {
    // Try multiple key paths used by MobileSyncController
    $s2 = $d2['data']['sentinel'] ?? $d2['sentinel'] ?? $d2['topology_sentinel'] ?? null;
    if (!$s2) {
        // try walking the keys
        echo "Top-level keys: " . implode(', ', array_keys($d2)) . "\n";
        foreach ($d2 as $k => $v) {
            if (is_array($v)) {
                echo "  [$k] keys: " . implode(', ', array_keys($v)) . "\n";
            } else {
                echo "  $k: $v\n";
            }
        }
    } else {
        $at = $s2['active_translines']   ?? 'N/A';
        $pt = $s2['physical_translines'] ?? 'N/A';
        $aa = $s2['active_assets']       ?? 'N/A';
        $pa = $s2['physical_assets']     ?? 'N/A';
        $sp = $s2['network_span_meters'] ?? $s2['span'] ?? 'N/A';
        $sn = $s2['snapshot_id'] ?? 'N/A';
        echo "active_TL=$at  physical_TL=$pt  active_AA=$aa  physical_PA=$pa  span=$sp  snap=$sn\n";
        echo "  Δ active_TL   = " . ($at !== 'N/A' ? ($at - 243)  : 'N/A') . "\n";
        echo "  Δ physical_TL = " . ($pt !== 'N/A' ? ($pt - 252)  : 'N/A') . "\n";
    }
} else {
    echo "JSON parse error or empty. raw: " . substr($r2['body'], 0, 400) . "\n";
}

echo "\nDone.\n";
