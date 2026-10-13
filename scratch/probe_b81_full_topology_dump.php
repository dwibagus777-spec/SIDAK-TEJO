<?php
/**
 * B8.1 Full Forensic Topology Dump — get ALL hashes and reconciliation layer data
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 90);
    $body = curl_exec($ch);
    $http  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err   = curl_error($ch);
    curl_close($ch);
    return ['http' => $http, 'body' => $body, 'error' => $err];
}

$headers = ['X-Audit-Token: ' . $auditToken, 'Accept: application/json'];

// Canonical Phase 0 baseline fingerprints
$baseline = [
    'active_translines'   => 243,
    'physical_translines' => 252,
    'active_assets'       => 5236,
    'physical_assets'     => 5549,
    'network_span'        => 9418.37,
    'h_active_tl'   => '5707f28af259aaca1608b5b595e139ce6dda3b0ffbfe4f1d1ed6c0cb48e40ae5',
    'h_physical_tl' => '35b82b10cea9acf5fefae7fe551aa9f2b8ca833f09acab2ef4f29f2d3755fad6',
    'h_active_aa'   => '83d8260ef2dfd1c8517e28879857fbd9816a3742a353217eef03d6a46a431711',
    'h_physical_pa' => '2e0477b238d5e352be5e9e70d2f57d42b808fd7d8d1d409dc40614c59193152a',
];

echo "=== B8.1 FULL TOPOLOGY FORENSIC DUMP (" . date('Y-m-d H:i:s T') . ") ===\n\n";

// ── /remediation/forensic-reconciliation ─────────────────────────
echo "── /remediation/forensic-reconciliation\n";
$r = curlGet($baseUrl . '/remediation/forensic-reconciliation', $headers, $resolve);
echo "HTTP: {$r['http']}\n";
if ($r['error']) echo "ERROR: {$r['error']}\n";
$d = json_decode($r['body'], true);
if (!$d) { echo "JSON error: " . json_last_error_msg() . "\n"; echo substr($r['body'], 0, 600); exit; }

$pack = $d['reconciliation_pack'] ?? [];
$l2   = $pack['layer_2_physical_database'] ?? [];
$l3   = $pack['layer_3_authoritative_view'] ?? [];
$l4   = $pack['layer_4_topology_snapshot'] ?? [];
$verdict = $d['reconciliation_verdict'] ?? 'N/A';

echo "\n[Layer 2 — Physical Database]\n";
$pt  = $l2['physical_translines_count'] ?? 'N/A';
$pa  = $l2['physical_assets_count']     ?? 'N/A';
$h_pt = $l2['physical_translines_hash'] ?? 'N/A';
$h_pa = $l2['physical_assets_hash']     ?? 'N/A';
$inact= $l2['inactive_translines_count'] ?? 'N/A';
$sdel = $l2['soft_deleted_assets_count'] ?? 'N/A';
$tb   = $l2['total_phase_b_tables']     ?? 'N/A';
echo "  physical_translines  : $pt  (baseline=252, Δ=" . ($pt !== 'N/A' ? ($pt - 252) : 'N/A') . ")\n";
echo "  physical_assets      : $pa  (baseline=5549, Δ=" . ($pa !== 'N/A' ? ($pa - 5549) : 'N/A') . ")\n";
echo "  inactive_translines  : $inact\n";
echo "  soft_deleted_assets  : $sdel\n";
echo "  total_phase_b_tables : $tb\n";
echo "  h_physical_tl : $h_pt\n";
echo "  h_physical_pa : $h_pa\n";
echo "  h_physical_tl match : " . ($h_pt === $baseline['h_physical_tl'] ? '✅ MATCH' : '❌ MISMATCH (baseline=' . $baseline['h_physical_tl'] . ')') . "\n";
echo "  h_physical_pa match : " . ($h_pa === $baseline['h_physical_pa'] ? '✅ MATCH' : '❌ MISMATCH (baseline=' . $baseline['h_physical_pa'] . ')') . "\n";

echo "\n[Layer 3 — Authoritative View]\n";
$at  = $l3['active_translines_count'] ?? 'N/A';
$aa  = $l3['active_assets_count']     ?? 'N/A';
$sp  = $l3['network_span_meters']     ?? 'N/A';
$sn  = $l3['snapshot_id']            ?? 'N/A';
$h_at = $l3['active_translines_hash'] ?? 'N/A';
$h_aa = $l3['active_assets_hash']     ?? 'N/A';
echo "  active_translines    : $at  (baseline=243, Δ=" . ($at !== 'N/A' ? ($at - 243) : 'N/A') . ")\n";
echo "  active_assets        : $aa  (baseline=5236, Δ=" . ($aa !== 'N/A' ? ($aa - 5236) : 'N/A') . ")\n";
echo "  network_span_meters  : $sp  (baseline=9418.37, Δ=" . ($sp !== 'N/A' ? round($sp - 9418.37, 2) : 'N/A') . ")\n";
echo "  snapshot_id          : $sn\n";
echo "  h_active_tl  : $h_at\n";
echo "  h_active_aa  : $h_aa\n";
echo "  h_active_tl match : " . ($h_at === $baseline['h_active_tl'] ? '✅ MATCH' : '❌ MISMATCH (baseline=' . $baseline['h_active_tl'] . ')') . "\n";
echo "  h_active_aa match : " . ($h_aa === $baseline['h_active_aa'] ? '✅ MATCH' : '❌ MISMATCH (baseline=' . $baseline['h_active_aa'] . ')') . "\n";

echo "\n[Layer 4 — Topology Snapshot]\n";
if ($l4) {
    foreach ($l4 as $k => $v) {
        if (!is_array($v)) echo "  $k: $v\n";
    }
    $snap_h = $l4['snapshot_hash'] ?? $l4['hash'] ?? 'N/A';
    echo "  snapshot_hash: $snap_h\n";
} else {
    echo "  (layer 4 not present or empty)\n";
}

echo "\n[Reconciliation Verdict] : $verdict\n";

echo "\n=== SUMMARY ===\n";
$issues = [];
if ($at !== 'N/A' && $at != 243) $issues[] = "active_translines CHANGED: 243→$at (Δ=" . ($at-243) . ")";
if ($pt !== 'N/A' && $pt != 252) $issues[] = "physical_translines CHANGED: 252→$pt (Δ=" . ($pt-252) . ")";
if ($aa !== 'N/A' && $aa != 5236) $issues[] = "active_assets CHANGED: 5236→$aa";
if ($pa !== 'N/A' && $pa != 5549) $issues[] = "physical_assets CHANGED: 5549→$pa";
if ($sp !== 'N/A' && abs($sp - 9418.37) > 0.01) $issues[] = "network_span CHANGED: 9418.37→$sp";
if ($h_at !== 'N/A' && $h_at !== $baseline['h_active_tl']) $issues[] = "active_TL HASH CHANGED";
if ($h_pt !== 'N/A' && $h_pt !== $baseline['h_physical_tl']) $issues[] = "physical_TL HASH CHANGED";
if ($h_aa !== 'N/A' && $h_aa !== $baseline['h_active_aa']) $issues[] = "active_AA HASH CHANGED";
if ($h_pa !== 'N/A' && $h_pa !== $baseline['h_physical_pa']) $issues[] = "physical_PA HASH CHANGED";

if (empty($issues)) {
    echo "✅ TOPOLOGY IMMUTABLE — All counts & hashes match Phase 0 baseline\n";
    echo "✅ Phase 1 GO CLEARED on topology sentinel\n";
} else {
    echo "⛔ TOPOLOGY DELTA DETECTED — HARD STOP:\n";
    foreach ($issues as $i => $issue) {
        echo "  [" . ($i+1) . "] $issue\n";
    }
    echo "\nPhase 1 migration BLOCKED pending governance review.\n";
}
