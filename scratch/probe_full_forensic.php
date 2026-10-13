<?php
/**
 * Full forensic probe — HTTPS hostname only (no CURLOPT_RESOLVE)
 * Gets full JSON and parses nested delta_candidates
 */

$url    = 'https://sidaktejo.site/remediation/investigate/topology-delta';
$token  = 'sidak_transline_audit_2026';

$c = curl_init($url);
curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($c, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($c, CURLOPT_TIMEOUT, 60);
curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 20);
curl_setopt($c, CURLOPT_HTTPHEADER, [
    "X-Audit-Token: {$token}",
    'Accept: application/json',
]);
$body = curl_exec($c);
$code = curl_getinfo($c, CURLINFO_HTTP_CODE);
$err  = curl_error($c);
curl_close($c);

echo "=== FORENSIC PROBE: topology-delta ===\n";
echo "HTTP: {$code} | err: {$err}\n";
echo "Body length: " . strlen((string)$body) . " bytes\n";

if ($code !== 200 || !$body) {
    echo "FAILED. Body snippet: " . substr((string)$body, 0, 500) . "\n";
    exit(1);
}

$d = json_decode($body, true);
if (!$d) {
    echo "JSON decode failed. Raw body:\n" . substr($body, 0, 1000) . "\n";
    exit(1);
}

// ─── Schema discovery ────────────────────────────────────────────────────────
$schema = $d['schema_discovery'] ?? [];
echo "\n=== SCHEMA DISCOVERY ===\n";
echo "gis_translines cols : " . implode(', ', $schema['gis_translines_columns'] ?? []) . "\n";
echo "has_geometry_col    : " . (($schema['has_geometry_col'] ?? false) ? 'YES' : 'NO') . "\n";
echo "selected_columns    : " . implode(', ', $schema['selected_columns'] ?? []) . "\n";
echo "feeder_columns      : " . implode(', ', $schema['feeder_columns'] ?? []) . "\n";
echo "asset_columns       : " . implode(', ', $schema['asset_columns'] ?? []) . "\n";
echo "audit_tables_found  : " . implode(', ', $schema['audit_tables_found'] ?? ['(none)']) . "\n";

// ─── Delta summary ───────────────────────────────────────────────────────────
$delta = $d['delta'] ?? [];
echo "\n=== DELTA SUMMARY ===\n";
echo "Δ active_tl         : " . ($delta['delta_active_translines']   ?? 'n/a') . "\n";
echo "Δ physical_tl       : " . ($delta['delta_physical_translines'] ?? 'n/a') . "\n";
echo "hard_stop_triggered : " . (($delta['hard_stop_triggered'] ?? false) ? 'YES ❌' : 'NO') . "\n";

// ─── Hash comparison ─────────────────────────────────────────────────────────
$curr = $d['current']  ?? [];
$base = $d['baseline'] ?? [];
echo "\n=== HASH vs BASELINE ===\n";
echo "h_active_tl   match: " . (($curr['h_active_tl_match']   ?? false) ? '✅ MATCH' : '❌ MISMATCH') . "\n";
echo "  baseline : " . ($base['h_active_tl'] ?? 'n/a') . "\n";
echo "  current  : " . ($curr['h_active_tl'] ?? 'n/a') . "\n";
echo "h_physical_tl match: " . (($curr['h_physical_tl_match'] ?? false) ? '✅ MATCH' : '❌ MISMATCH') . "\n";
echo "  baseline : " . ($base['h_physical_tl'] ?? 'n/a') . "\n";
echo "  current  : " . ($curr['h_physical_tl'] ?? 'n/a') . "\n";

// ─── Active ID list ───────────────────────────────────────────────────────────
$activeIds = $d['active_id_list'] ?? [];
echo "\n=== ACTIVE ID LIST ===\n";
echo "Total count : " . count($activeIds) . "\n";
echo "Last 10 IDs : " . implode(', ', array_slice($activeIds, -10)) . "\n";

// ─── Delta candidates (NESTED STRUCTURE) ─────────────────────────────────────
$candidates = $d['delta_candidates'] ?? [];
echo "\n=== DELTA CANDIDATES (" . count($candidates) . " found) ===\n";

foreach ($candidates as $i => $cand) {
    $tl      = $cand['transline']     ?? [];
    $feeder  = $cand['feeder']        ?? [];
    $fromA   = $cand['from_asset']    ?? [];
    $toA     = $cand['to_asset']      ?? [];
    $geoInfo = $cand['geometry_info'] ?? [];
    $audit   = $cand['audit_trail']   ?? [];

    echo "\n━━━ CANDIDATE #" . ($i + 1) . " ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

    echo "[TRANSLINE — RAW FIELDS]\n";
    foreach ($tl as $k => $v) {
        echo "  {$k} : " . (is_null($v) ? 'NULL' : $v) . "\n";
    }

    echo "[GEOMETRY]\n";
    echo "  has_geometry_column : " . (($geoInfo['has_geometry_column'] ?? false) ? 'YES' : 'NO') . "\n";
    echo "  st_available        : " . ($geoInfo['st_available'] ?? 'n/a') . "\n";
    echo "  length_meters       : " . ($geoInfo['length_meters'] ?? 'n/a') . "\n";
    echo "  geometry_wkt        : " . substr($geoInfo['geometry_wkt'] ?? 'n/a', 0, 100) . "\n";

    echo "[FEEDER]\n";
    if ($feeder) {
        foreach ($feeder as $k => $v) echo "  {$k}: " . (is_null($v) ? 'NULL' : $v) . "\n";
    } else {
        echo "  (null)\n";
    }

    echo "[FROM_ASSET]\n";
    if ($fromA) {
        foreach ($fromA as $k => $v) echo "  {$k}: " . (is_null($v) ? 'NULL' : $v) . "\n";
    } else {
        echo "  (null)\n";
    }

    echo "[TO_ASSET]\n";
    if ($toA) {
        foreach ($toA as $k => $v) echo "  {$k}: " . (is_null($v) ? 'NULL' : $v) . "\n";
    } else {
        echo "  (null)\n";
    }

    echo "[AUDIT_TRAIL]\n";
    if ($audit) {
        foreach ($audit as $a) echo "  " . json_encode($a) . "\n";
    } else {
        echo "  (none)\n";
    }
}

// ─── Recent insertions ────────────────────────────────────────────────────────
$recent = $d['recent_insertions_since_2026_09_20'] ?? [];
echo "\n=== RECENT INSERTIONS (since 2026-09-20) — " . count($recent) . " rows ===\n";
foreach ($recent as $row) {
    $flag = in_array($row['id'] ?? 0, [341, 342]) ? ' ◀ DELTA' : '';
    echo "  id=" . ($row['id'] ?? '?')
        . " code=" . ($row['transline_code'] ?? '?')
        . " feeder=" . ($row['feeder_id'] ?? ($row['penyulang_id'] ?? '?'))
        . " active=" . ($row['is_active'] ?? '?')
        . " created=" . ($row['created_at'] ?? '?')
        . " by=" . ($row['created_by'] ?? '?')
        . $flag . "\n";
}

echo "\n=== RAW JSON (full, for reference) ===\n";
echo json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

echo "\n=== DONE ===\n";
