<?php
/**
 * Probe: GET /remediation/investigate/topology-delta
 * Option B Forensic Investigation — Read-Only
 */

$baseUrl  = 'https://sidaktejo.site';
$auditToken = 'sidak_transline_audit_2026';
$resolve  = ['sidaktejo.site:443:2.57.91.151', 'sidaktejo.site:80:2.57.91.151'];

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

echo "=== PROBE: /remediation/investigate/topology-delta ===\n";
$r = curlGet("{$baseUrl}/remediation/investigate/topology-delta", $headers, $resolve);
echo "HTTP: {$r['code']}\n";

if ($r['error']) {
    echo "cURL Error: {$r['error']}\n";
    exit(1);
}

if ($r['code'] !== 200) {
    echo "Non-200 response:\n";
    echo substr($r['body'], 0, 2000) . "\n";
    exit(1);
}

$d = json_decode($r['body'], true);
if (!$d) {
    echo "Invalid JSON:\n";
    echo substr($r['body'], 0, 2000) . "\n";
    exit(1);
}

// ─── Top-level summary ───────────────────────────────────────────────
echo "\n=== TOP-LEVEL SUMMARY ===\n";
echo "endpoint          : " . ($d['endpoint']          ?? 'n/a') . "\n";
echo "mode              : " . ($d['mode']              ?? 'n/a') . "\n";
echo "timestamp         : " . ($d['timestamp']         ?? 'n/a') . "\n";
echo "baseline_snapshot : " . ($d['baseline_snapshot'] ?? 'n/a') . "\n";

// ─── Current counts ──────────────────────────────────────────────────
$counts = $d['current_counts'] ?? [];
echo "\n=== CURRENT COUNTS ===\n";
echo "physical_translines : " . ($counts['physical_translines'] ?? 'n/a') . "\n";
echo "active_translines   : " . ($counts['active_translines']   ?? 'n/a') . "\n";
echo "physical_assets     : " . ($counts['physical_assets']     ?? 'n/a') . "\n";
echo "active_assets       : " . ($counts['active_assets']       ?? 'n/a') . "\n";
echo "network_span        : " . ($counts['network_span_meters']  ?? 'n/a') . " m\n";

// ─── Hash comparison ─────────────────────────────────────────────────
$hashes = $d['hash_comparison'] ?? [];
echo "\n=== HASH COMPARISON vs BASELINE ===\n";
foreach ($hashes as $key => $val) {
    $match = is_array($val) ? ($val['match'] ?? '?') : '?';
    $current = is_array($val) ? ($val['current'] ?? 'n/a') : $val;
    $flag = ($match === true || $match === 'true' || $match === 'MATCH') ? '✅' : '❌';
    echo "{$flag} {$key}: {$current}\n";
}

// ─── Delta candidates (the 2 new TLs) ───────────────────────────────
$candidates = $d['delta_candidates'] ?? [];
echo "\n=== DELTA CANDIDATES (Δ+2 Translines) ===\n";
echo "Count: " . count($candidates) . "\n";

foreach ($candidates as $i => $tl) {
    echo "\n--- TL #" . ($i + 1) . " ---\n";
    echo "id              : " . ($tl['id']              ?? 'n/a') . "\n";
    echo "transline_code  : " . ($tl['transline_code']  ?? $tl['code'] ?? 'n/a') . "\n";
    echo "feeder_id       : " . ($tl['feeder_id']       ?? 'n/a') . "\n";
    echo "feeder_name     : " . ($tl['feeder_name']     ?? $tl['feeder']['name'] ?? 'n/a') . "\n";
    echo "feeder_code     : " . ($tl['feeder_code']     ?? $tl['feeder']['code'] ?? 'n/a') . "\n";
    echo "from_asset_id   : " . ($tl['from_asset_id']   ?? 'n/a') . "\n";
    echo "from_asset_code : " . ($tl['from_asset_code'] ?? $tl['from_asset']['code'] ?? 'n/a') . "\n";
    echo "from_asset_name : " . ($tl['from_asset_name'] ?? $tl['from_asset']['name'] ?? 'n/a') . "\n";
    echo "to_asset_id     : " . ($tl['to_asset_id']     ?? 'n/a') . "\n";
    echo "to_asset_code   : " . ($tl['to_asset_code']   ?? $tl['to_asset']['code'] ?? 'n/a') . "\n";
    echo "to_asset_name   : " . ($tl['to_asset_name']   ?? $tl['to_asset']['name'] ?? 'n/a') . "\n";
    echo "is_active       : " . ($tl['is_active']       ?? 'n/a') . "\n";
    echo "deleted_at      : " . ($tl['deleted_at']      ?? 'NULL') . "\n";
    echo "length_meters   : " . ($tl['length_meters']   ?? $tl['length'] ?? 'n/a') . "\n";
    echo "geometry_wkt    : " . substr($tl['geometry_wkt'] ?? $tl['geometry'] ?? 'n/a', 0, 80) . "\n";
    echo "created_at      : " . ($tl['created_at']      ?? 'n/a') . "\n";
    echo "updated_at      : " . ($tl['updated_at']      ?? 'n/a') . "\n";
    echo "created_by      : " . ($tl['created_by']      ?? 'n/a') . "\n";
    echo "updated_by      : " . ($tl['updated_by']      ?? 'n/a') . "\n";

    // Audit trail
    $audit = $tl['audit_trail'] ?? [];
    if ($audit) {
        echo "audit_trail:\n";
        foreach ($audit as $a) {
            echo "  - " . json_encode($a) . "\n";
        }
    } else {
        echo "audit_trail     : (none)\n";
    }
}

// ─── Recent insertions ───────────────────────────────────────────────
$recent = $d['recent_insertions_since_2026_09_20'] ?? $d['recent_insertions'] ?? [];
echo "\n=== RECENT INSERTIONS (since 2026-09-20) ===\n";
echo "Count: " . count($recent) . "\n";
foreach ($recent as $i => $row) {
    echo "  [{$i}] id={$row['id']} code=" . ($row['transline_code'] ?? $row['code'] ?? '?')
        . " created_at=" . ($row['created_at'] ?? '?')
        . " created_by=" . ($row['created_by'] ?? '?') . "\n";
}

// ─── Inactive physical translines ────────────────────────────────────
$inactive = $d['inactive_physical_translines'] ?? [];
echo "\n=== INACTIVE PHYSICAL TRANSLINES ===\n";
echo "Count: " . count($inactive) . "\n";
foreach (array_slice($inactive, 0, 10) as $i => $row) {
    echo "  [{$i}] id={$row['id']} is_active={$row['is_active']} deleted_at=" . ($row['deleted_at'] ?? 'NULL') . "\n";
}

// ─── Active ID list (just first/last few) ────────────────────────────
$activeIds = $d['active_id_list'] ?? [];
echo "\n=== ACTIVE ID LIST (count=" . count($activeIds) . ") ===\n";
if (count($activeIds) > 0) {
    $first5 = array_slice($activeIds, 0, 5);
    $last5  = array_slice($activeIds, -5);
    echo "First 5: " . implode(', ', $first5) . "\n";
    echo "Last 5 : " . implode(', ', $last5)  . "\n";
}

// ─── Raw dump for anything unexpected ────────────────────────────────
echo "\n=== RAW KEYS IN RESPONSE ===\n";
echo implode(', ', array_keys($d)) . "\n";

echo "\n=== DONE ===\n";
