<?php
/**
 * Probe: Parse nested delta_candidates from /remediation/investigate/topology-delta
 * Also directly query IDs 341 and 342 via feeder endpoint for full provenance
 */

$baseUrl    = 'https://sidaktejo.site';
$resolve    = ['sidaktejo.site:443:2.57.91.151', 'sidaktejo.site:80:2.57.91.151'];
$headers    = ['X-Audit-Token: sidak_transline_audit_2026', 'Accept: application/json'];

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

echo "=== FULL FORENSIC PROBE: delta_candidates nested structure ===\n";
$r = curlGet("{$baseUrl}/remediation/investigate/topology-delta", $headers, $resolve);
echo "HTTP: {$r['code']}\n";

$d = json_decode($r['body'], true);
if (!$d || $r['code'] !== 200) {
    echo "FAILED:\n" . substr($r['body'], 0, 2000) . "\n";
    exit(1);
}

// ─── Schema discovery ────────────────────────────────────────────────
$schema = $d['schema_discovery'] ?? [];
echo "\n=== SCHEMA DISCOVERY ===\n";
echo "gis_translines columns : " . implode(', ', $schema['gis_translines_columns'] ?? []) . "\n";
echo "has_geometry_col       : " . ($schema['has_geometry_col'] ? 'YES' : 'NO') . "\n";
echo "selected_columns       : " . implode(', ', $schema['selected_columns'] ?? []) . "\n";
echo "feeder_columns         : " . implode(', ', $schema['feeder_columns'] ?? []) . "\n";
echo "asset_columns          : " . implode(', ', $schema['asset_columns'] ?? []) . "\n";
echo "audit_tables_found     : " . implode(', ', $schema['audit_tables_found'] ?? ['(none)']) . "\n";

// ─── Delta summary ───────────────────────────────────────────────────
$delta = $d['delta'] ?? [];
echo "\n=== DELTA SUMMARY ===\n";
echo "Δ active_translines   : " . ($delta['delta_active_translines']   ?? 'n/a') . "\n";
echo "Δ physical_translines : " . ($delta['delta_physical_translines'] ?? 'n/a') . "\n";
echo "hard_stop_triggered   : " . ($delta['hard_stop_triggered'] ? 'YES ❌' : 'NO ✅') . "\n";

// ─── Hash comparison ─────────────────────────────────────────────────
$curr = $d['current'] ?? [];
$base = $d['baseline'] ?? [];
echo "\n=== HASH vs BASELINE ===\n";
echo "h_active_tl   match: " . ($curr['h_active_tl_match']   ? '✅' : '❌') . "\n";
echo "  baseline : " . ($base['h_active_tl'] ?? 'n/a') . "\n";
echo "  current  : " . ($curr['h_active_tl'] ?? 'n/a') . "\n";
echo "h_physical_tl match: " . ($curr['h_physical_tl_match'] ? '✅' : '❌') . "\n";
echo "  baseline : " . ($base['h_physical_tl'] ?? 'n/a') . "\n";
echo "  current  : " . ($curr['h_physical_tl'] ?? 'n/a') . "\n";

// ─── Delta candidates (NESTED STRUCTURE) ─────────────────────────────
$candidates = $d['delta_candidates'] ?? [];
echo "\n=== DELTA CANDIDATES (Δ+2 Translines) — FULL PROVENANCE ===\n";
echo "Count: " . count($candidates) . "\n";

foreach ($candidates as $i => $cand) {
    $tl      = $cand['transline']     ?? [];
    $feeder  = $cand['feeder']        ?? [];
    $fromA   = $cand['from_asset']    ?? [];
    $toA     = $cand['to_asset']      ?? [];
    $geoInfo = $cand['geometry_info'] ?? [];
    $audit   = $cand['audit_trail']   ?? [];

    echo "\n━━━ TL #" . ($i + 1) . " ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

    // Transline core fields
    echo "[TRANSLINE]\n";
    echo "  id             : " . ($tl['id']             ?? 'n/a') . "\n";
    echo "  transline_code : " . ($tl['transline_code'] ?? 'n/a') . "\n";
    echo "  feeder_id      : " . ($tl['feeder_id']      ?? 'n/a') . "\n";
    echo "  from_asset_id  : " . ($tl['from_asset_id']  ?? 'n/a') . "\n";
    echo "  to_asset_id    : " . ($tl['to_asset_id']    ?? 'n/a') . "\n";
    echo "  is_active      : " . ($tl['is_active']      ?? 'n/a') . "\n";
    echo "  deleted_at     : " . ($tl['deleted_at']     ?? 'NULL') . "\n";
    echo "  created_at     : " . ($tl['created_at']     ?? 'n/a') . "\n";
    echo "  updated_at     : " . ($tl['updated_at']     ?? 'n/a') . "\n";
    echo "  created_by     : " . ($tl['created_by']     ?? 'n/a') . "\n";
    echo "  updated_by     : " . ($tl['updated_by']     ?? 'n/a') . "\n";
    // Print any extra TL fields
    $knownTLKeys = ['id','transline_code','feeder_id','from_asset_id','to_asset_id','is_active','deleted_at','created_at','updated_at','created_by','updated_by'];
    foreach ($tl as $k => $v) {
        if (!in_array($k, $knownTLKeys)) {
            echo "  {$k} : {$v}\n";
        }
    }

    // Geometry
    echo "[GEOMETRY]\n";
    echo "  has_col        : " . ($geoInfo['has_geometry_column'] ? 'YES' : 'NO') . "\n";
    echo "  st_available   : " . (isset($geoInfo['st_available']) ? ($geoInfo['st_available'] ? 'YES' : 'NO') : 'n/a') . "\n";
    echo "  length_meters  : " . ($geoInfo['length_meters']  ?? 'n/a') . "\n";
    echo "  geometry_wkt   : " . substr($geoInfo['geometry_wkt'] ?? 'n/a', 0, 80) . "\n";

    // Feeder
    echo "[FEEDER]\n";
    if ($feeder) {
        foreach ($feeder as $k => $v) echo "  {$k}: {$v}\n";
    } else {
        echo "  (null — feeder_id may be empty or feeder not found)\n";
    }

    // From asset
    echo "[FROM_ASSET]\n";
    if ($fromA) {
        foreach ($fromA as $k => $v) echo "  {$k}: {$v}\n";
    } else {
        echo "  (null)\n";
    }

    // To asset
    echo "[TO_ASSET]\n";
    if ($toA) {
        foreach ($toA as $k => $v) echo "  {$k}: {$v}\n";
    } else {
        echo "  (null)\n";
    }

    // Audit trail
    echo "[AUDIT_TRAIL]\n";
    if ($audit) {
        foreach ($audit as $a) echo "  " . json_encode($a) . "\n";
    } else {
        echo "  (none found in audit tables)\n";
    }
}

// ─── Active ID list — identify the delta IDs ─────────────────────────
$activeIds = $d['active_id_list'] ?? [];
echo "\n=== ACTIVE ID LIST ===\n";
echo "Total count: " . count($activeIds) . "\n";
echo "Last 10 IDs: " . implode(', ', array_slice($activeIds, -10)) . "\n";

// ─── Recent insertions ───────────────────────────────────────────────
$recent = $d['recent_insertions_since_2026_09_20'] ?? [];
echo "\n=== RECENT INSERTIONS (since 2026-09-20) ===\n";
echo "Count: " . count($recent) . "\n";
foreach ($recent as $row) {
    $isNewDelta = in_array($row['id'] ?? 0, [341, 342]) ? ' ◀ DELTA CANDIDATE' : '';
    echo "  id={$row['id']} code=" . ($row['transline_code'] ?? '?')
        . " feeder=" . ($row['feeder_id'] ?? '?')
        . " active=" . ($row['is_active'] ?? '?')
        . " created=" . ($row['created_at'] ?? '?')
        . " by=" . ($row['created_by'] ?? '?')
        . $isNewDelta . "\n";
}

echo "\n=== DONE ===\n";
