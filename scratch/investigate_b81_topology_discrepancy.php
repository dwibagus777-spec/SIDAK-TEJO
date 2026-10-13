<?php
/**
 * B8.1 Topology Discrepancy Investigation
 * 
 * Phase 0 baseline: active_translines=243, physical_translines=252
 * Last audit probe:  active_translines=245, physical_translines=254
 * 
 * This script cross-checks BOTH /remediation/audit AND /mobile-sync/forensic-reconciliation
 * to determine if the discrepancy is real or a query difference.
 */

$baseUrl = 'https://sidaktejo.site';
$auditToken = getenv('SIDAK_AUDIT_TOKEN') ?: 'sidak_transline_audit_2026';
$resolve = ['sidaktejo.site:443:2.57.91.151', 'sidaktejo.site:80:2.57.91.151'];

function curlGet(string $url, array $headers, array $resolve): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_RESOLVE, $resolve);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $body = curl_exec($ch);
    $http  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err   = curl_error($ch);
    curl_close($ch);
    return ['http' => $http, 'body' => $body, 'error' => $err];
}

$headers = [
    'X-Audit-Token: ' . $auditToken,
    'Accept: application/json',
];

echo "============================================================\n";
echo "  B8.1 TOPOLOGY DISCREPANCY INVESTIGATION\n";
echo "  " . date('Y-m-d H:i:s T') . "\n";
echo "============================================================\n\n";

// ── ENDPOINT 1: /remediation/audit ──────────────────────────────
echo "── [1] GET /remediation/audit ──────────────────────────────\n";
$r1 = curlGet($baseUrl . '/remediation/audit', $headers, $resolve);
echo "HTTP: {$r1['http']}\n";
if ($r1['error']) echo "cURL error: {$r1['error']}\n";
$d1 = json_decode($r1['body'], true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "PARSE ERROR — raw:\n" . substr($r1['body'], 0, 500) . "\n";
    $d1 = null;
} else {
    $s1 = $d1['data']['sentinel'] ?? $d1['sentinel'] ?? null;
    if ($s1) {
        echo "active_translines   : " . ($s1['active_translines']   ?? 'N/A') . "\n";
        echo "physical_translines : " . ($s1['physical_translines'] ?? 'N/A') . "\n";
        echo "active_assets       : " . ($s1['active_assets']       ?? 'N/A') . "\n";
        echo "physical_assets     : " . ($s1['physical_assets']     ?? 'N/A') . "\n";
        echo "network_span        : " . ($s1['network_span_meters'] ?? $s1['span'] ?? 'N/A') . "\n";
        echo "snapshot_id         : " . ($s1['snapshot_id']         ?? 'N/A') . "\n";
    } else {
        echo "sentinel key not found — keys: " . implode(', ', array_keys($d1)) . "\n";
        echo substr($r1['body'], 0, 600) . "\n";
    }
}

echo "\n";

// ── ENDPOINT 2: /mobile-sync/forensic-reconciliation ────────────
echo "── [2] GET /mobile-sync/forensic-reconciliation ────────────\n";
$r2 = curlGet($baseUrl . '/mobile-sync/forensic-reconciliation', $headers, $resolve);
echo "HTTP: {$r2['http']}\n";
if ($r2['error']) echo "cURL error: {$r2['error']}\n";
$d2 = json_decode($r2['body'], true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "PARSE ERROR — raw:\n" . substr($r2['body'], 0, 500) . "\n";
    $d2 = null;
} else {
    $s2 = $d2['data']['sentinel'] ?? $d2['sentinel'] ?? null;
    if ($s2) {
        echo "active_translines   : " . ($s2['active_translines']   ?? 'N/A') . "\n";
        echo "physical_translines : " . ($s2['physical_translines'] ?? 'N/A') . "\n";
        echo "active_assets       : " . ($s2['active_assets']       ?? 'N/A') . "\n";
        echo "physical_assets     : " . ($s2['physical_assets']     ?? 'N/A') . "\n";
        echo "network_span        : " . ($s2['network_span_meters'] ?? $s2['span'] ?? 'N/A') . "\n";
        echo "snapshot_id         : " . ($s2['snapshot_id']         ?? 'N/A') . "\n";
    } else {
        echo "sentinel key not found — keys: " . implode(', ', array_keys($d2)) . "\n";
        echo substr($r2['body'], 0, 600) . "\n";
    }
}

echo "\n";

// ── ENDPOINT 3: /remediation/forensic-reconciliation (new B8.1 endpoint) ─
echo "── [3] GET /remediation/forensic-reconciliation ────────────\n";
$r3 = curlGet($baseUrl . '/remediation/forensic-reconciliation', $headers, $resolve);
echo "HTTP: {$r3['http']}\n";
if ($r3['error']) echo "cURL error: {$r3['error']}\n";
$d3 = json_decode($r3['body'], true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo "PARSE ERROR — raw:\n" . substr($r3['body'], 0, 500) . "\n";
    $d3 = null;
} else {
    $s3 = $d3['data']['sentinel'] ?? $d3['sentinel'] ?? null;
    if ($s3) {
        echo "active_translines   : " . ($s3['active_translines']   ?? 'N/A') . "\n";
        echo "physical_translines : " . ($s3['physical_translines'] ?? 'N/A') . "\n";
        echo "active_assets       : " . ($s3['active_assets']       ?? 'N/A') . "\n";
        echo "physical_assets     : " . ($s3['physical_assets']     ?? 'N/A') . "\n";
        echo "network_span        : " . ($s3['network_span_meters'] ?? $s3['span'] ?? 'N/A') . "\n";
        echo "snapshot_id         : " . ($s3['snapshot_id']         ?? 'N/A') . "\n";
        $recon = $d3['data']['reconciliation'] ?? $d3['reconciliation'] ?? null;
        if ($recon) {
            echo "reconciliation_status: " . ($recon['status'] ?? $recon['reconciliation_status'] ?? 'N/A') . "\n";
        }
    } else {
        echo "keys found: " . implode(', ', array_keys($d3)) . "\n";
        echo substr($r3['body'], 0, 800) . "\n";
    }
}

echo "\n";

// ── COMPARISON ───────────────────────────────────────────────────
echo "── [COMPARISON] vs Phase 0 Baseline ───────────────────────\n";
$baseline = [
    'active_translines'   => 243,
    'physical_translines' => 252,
    'active_assets'       => 5236,
    'physical_assets'     => 5549,
    'network_span'        => 9418.37,
];

$sources = [];
if ($d1) $sources['remediation/audit'] = $d1['data']['sentinel'] ?? $d1['sentinel'] ?? [];
if ($d2) $sources['mobile-sync/forensic'] = $d2['data']['sentinel'] ?? $d2['sentinel'] ?? [];
if ($d3) $sources['remediation/forensic'] = $d3['data']['sentinel'] ?? $d3['sentinel'] ?? [];

$allMatch = true;
foreach ($sources as $srcName => $src) {
    if (!$src) continue;
    $at  = $src['active_translines']   ?? 'N/A';
    $pt  = $src['physical_translines'] ?? 'N/A';
    $aa  = $src['active_assets']       ?? 'N/A';
    $pa  = $src['physical_assets']     ?? 'N/A';
    $span= $src['network_span_meters'] ?? $src['span'] ?? 'N/A';

    $dAt  = ($at  !== 'N/A') ? ($at  - $baseline['active_translines'])   : 'N/A';
    $dPt  = ($pt  !== 'N/A') ? ($pt  - $baseline['physical_translines']) : 'N/A';
    $dAa  = ($aa  !== 'N/A') ? ($aa  - $baseline['active_assets'])       : 'N/A';
    $dPa  = ($pa  !== 'N/A') ? ($pa  - $baseline['physical_assets'])     : 'N/A';
    $dSp  = ($span !== 'N/A') ? round($span - $baseline['network_span'], 2) : 'N/A';

    $status = ($dAt === 0 && $dPt === 0 && $dAa === 0 && $dPa === 0 && $dSp == 0.0) ? '✅ MATCH' : '❌ DELTA';
    if ($status !== '✅ MATCH') $allMatch = false;

    echo "\n[$srcName] → $status\n";
    echo "  Δ active_TL   : $dAt  (now=$at, base=243)\n";
    echo "  Δ physical_TL : $dPt  (now=$pt, base=252)\n";
    echo "  Δ active_AA   : $dAa  (now=$aa, base=5236)\n";
    echo "  Δ physical_PA : $dPa  (now=$pa, base=5549)\n";
    echo "  Δ span        : $dSp  (now=$span, base=9418.37)\n";
}

echo "\n============================================================\n";
if ($allMatch) {
    echo "VERDICT: ✅ ALL SOURCES MATCH BASELINE → Phase 1 GO cleared on topology\n";
} else {
    echo "VERDICT: ⛔ TOPOLOGY DELTA DETECTED → DO NOT execute Phase 1 migration\n";
    echo "         Investigate root cause before proceeding.\n";
}
echo "============================================================\n";
