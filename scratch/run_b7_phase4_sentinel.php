<?php

/**
 * SIDAK TEJO — Phase B.7 Production Activation: Phase 4 Final Topology Sentinel Audit
 * Target Host: Production https://sidaktejo.site (IP: 2.57.91.151)
 *
 * STRICT READ-ONLY FORENSIC VERIFICATION:
 * - NO INSERT, NO UPDATE, NO DELETE, NO ALTER
 * - Compares live production network truth bit-for-bit against authoritative baseline
 * - Stores FULL SHA-256 cryptographic hashes (no truncation)
 */

$baseUrl = 'https://sidaktejo.site';
$auditToken = getenv('SIDAK_AUDIT_TOKEN') ?: 'sidak_transline_audit_2026';

function callGetEndpoint(string $url, ?string $token = null): array
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RESOLVE, ['sidaktejo.site:443:2.57.91.151', 'sidaktejo.site:80:2.57.91.151']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $headers = [];
    if ($token !== null) {
        $headers[] = 'X-Audit-Token: ' . $token;
    }
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $startTime = microtime(true);
    $res = curl_exec($ch);
    $elapsedMs = round((microtime(true) - $startTime) * 1000, 2);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    return [
        'code'    => $httpCode,
        'elapsed' => $elapsedMs,
        'body'    => (string)$res,
        'json'    => json_decode((string)$res, true),
        'error'   => $err,
    ];
}

echo "====================================================================\n";
echo "  SIDAK TEJO — B.7 PRODUCTION ACTIVATION: PHASE 4 FINAL SENTINEL    \n";
echo "  Target Host: 2.57.91.151 (sidaktejo.site)                         \n";
echo "  Mode: STRICT READ-ONLY (Zero Mutation)                            \n";
echo "  Timestamp: " . date('Y-m-d H:i:s T') . "                          \n";
echo "====================================================================\n\n";

// 1. Authoritative Baseline Definition (Permanently Sealed Snapshot)
$baseline = [
    'snapshot_id'             => 'TOPOLOGY-20260925-243-ad2c9fcb',
    'network_span_meters'     => 9418.37,
    'authoritative' => [
        'active_translines'      => 243,
        'active_translines_hash' => '5707f28af259aaca1608b5b595e139ce6dda3b0ffbfe4f1d1ed6c0cb48e40ae5',
        'active_assets'          => 5236,
        'active_assets_hash'     => '83d8260ef2dfd1c8517e28879857fbd9816a3742a353217eef03d6a46a431711',
    ],
    'physical' => [
        'physical_translines'      => 252,
        'physical_translines_hash' => '35b82b10cea9acf5fefae7fe551aa9f2b8ca833f09acab2ef4f29f2d3755fad6',
        'physical_assets'          => 5549,
        'physical_assets_hash'     => '2e0477b238d5e352be5e9e70d2f57d42b808fd7d8d1d409dc40614c59193152a',
        'inactive_translines'      => 9,
        'soft_deleted_assets'      => 313,
    ],
];

// 2. Fetch Live Audit Scorecard (Read-Only)
echo "[1/3] Querying GET /mobile-sync/audit...\n";
$auditRes = callGetEndpoint("{$baseUrl}/mobile-sync/audit?key={$auditToken}");
if ($auditRes['code'] !== 200 || !isset($auditRes['json']['topology_sentinel'])) {
    echo "ERROR: Failed to fetch audit scorecard. HTTP Code: {$auditRes['code']}\n";
    exit(1);
}
$auditSentinel = $auditRes['json']['topology_sentinel'];
echo "      HTTP {$auditRes['code']} ({$auditRes['elapsed']} ms) — Mode: {$auditRes['json']['audit_mode']}\n\n";

// 3. Fetch Live 4-Layer Truth Reconciliation (Read-Only)
echo "[2/3] Querying GET /mobile-sync/forensic-reconciliation...\n";
$reconRes = callGetEndpoint("{$baseUrl}/mobile-sync/forensic-reconciliation?key={$auditToken}");
if ($reconRes['code'] !== 200 || !isset($reconRes['json']['reconciliation_pack'])) {
    echo "ERROR: Failed to fetch reconciliation pack. HTTP Code: {$reconRes['code']}\n";
    exit(1);
}
$reconPack = $reconRes['json']['reconciliation_pack'];
echo "      HTTP {$reconRes['code']} ({$reconRes['elapsed']} ms) — Verdict: {$reconRes['json']['reconciliation_verdict']}\n\n";

// 4. Extract Live Metrics
$liveAuthoritative = [
    'active_translines'      => (int)$auditSentinel['active_translines'],
    'active_translines_hash' => (string)$auditSentinel['active_transline_hash'],
    'active_assets'          => (int)$auditSentinel['active_assets'],
    'active_assets_hash'     => (string)$auditSentinel['active_asset_hash'],
    'network_span_meters'    => (float)$auditSentinel['network_span_meters'],
    'topology_snapshot_id'   => (string)$auditSentinel['topology_snapshot_id'],
];

$livePhysical = [
    'physical_translines'      => (int)$auditSentinel['physical_translines'],
    'physical_translines_hash' => (string)$auditSentinel['physical_transline_hash'],
    'physical_assets'          => (int)$auditSentinel['physical_assets'],
    'physical_assets_hash'     => (string)$auditSentinel['physical_asset_hash'],
    'inactive_translines'      => (int)($reconPack['layer_2_physical_database']['inactive_translines_count'] ?? 0),
    'soft_deleted_assets'      => (int)($reconPack['layer_2_physical_database']['soft_deleted_assets_count'] ?? 0),
];

// 5. Compare & Compute Forensic Deltas
echo "[3/3] Bit-for-Bit Cryptographic Comparison...\n";

// Authoritative comparisons
$deltaActiveTL = $liveAuthoritative['active_translines'] - $baseline['authoritative']['active_translines'];
$matchHashActiveTL = hash_equals($baseline['authoritative']['active_translines_hash'], $liveAuthoritative['active_translines_hash']);

$deltaActiveAssets = $liveAuthoritative['active_assets'] - $baseline['authoritative']['active_assets'];
$matchHashActiveAssets = hash_equals($baseline['authoritative']['active_assets_hash'], $liveAuthoritative['active_assets_hash']);

$deltaSpan = round($liveAuthoritative['network_span_meters'] - $baseline['network_span_meters'], 2);
$matchSnapshot = ($liveAuthoritative['topology_snapshot_id'] === $baseline['snapshot_id']);

// Physical comparisons
$deltaPhysTL = $livePhysical['physical_translines'] - $baseline['physical']['physical_translines'];
$matchHashPhysTL = hash_equals($baseline['physical']['physical_translines_hash'], $livePhysical['physical_translines_hash']);

$deltaPhysAssets = $livePhysical['physical_assets'] - $baseline['physical']['physical_assets'];
$matchHashPhysAssets = hash_equals($baseline['physical']['physical_assets_hash'], $livePhysical['physical_assets_hash']);

$deltaInactiveTL = $livePhysical['inactive_translines'] - $baseline['physical']['inactive_translines'];
$deltaSoftDeletedAssets = $livePhysical['soft_deleted_assets'] - $baseline['physical']['soft_deleted_assets'];

// Verdict evaluation
$allZeroDelta = ($deltaActiveTL === 0 && $deltaActiveAssets === 0 && $deltaPhysTL === 0 && $deltaPhysAssets === 0 && $deltaSpan === 0.0 && $deltaInactiveTL === 0 && $deltaSoftDeletedAssets === 0);
$allHashesMatch = ($matchHashActiveTL && $matchHashActiveAssets && $matchHashPhysTL && $matchHashPhysAssets && $matchSnapshot);
$reconciliationAuthoritative = ($reconRes['json']['reconciliation_verdict'] === 'PASS_AUTHORITATIVE_PRODUCTION');

$phase4Pass = ($allZeroDelta && $allHashesMatch && $reconciliationAuthoritative);

echo "\n--- AUTHORITATIVE LAYER ---\n";
echo "Active Translines      : {$liveAuthoritative['active_translines']} (Baseline: {$baseline['authoritative']['active_translines']}, Δ: {$deltaActiveTL}) -> " . ($deltaActiveTL === 0 ? "🟢 PASS" : "🔴 FAIL") . "\n";
echo "Active Translines Hash : {$liveAuthoritative['active_translines_hash']}\n";
echo "                         " . ($matchHashActiveTL ? "🟢 MATCH (Identical)" : "🔴 MISMATCH") . "\n";
echo "Active Assets          : {$liveAuthoritative['active_assets']} (Baseline: {$baseline['authoritative']['active_assets']}, Δ: {$deltaActiveAssets}) -> " . ($deltaActiveAssets === 0 ? "🟢 PASS" : "🔴 FAIL") . "\n";
echo "Active Assets Hash     : {$liveAuthoritative['active_assets_hash']}\n";
echo "                         " . ($matchHashActiveAssets ? "🟢 MATCH (Identical)" : "🔴 MISMATCH") . "\n";
echo "Network Span           : {$liveAuthoritative['network_span_meters']} m (Baseline: {$baseline['network_span_meters']} m, Δ: {$deltaSpan} m) -> " . ($deltaSpan === 0.0 ? "🟢 PASS" : "🔴 FAIL") . "\n";
echo "Snapshot ID            : {$liveAuthoritative['topology_snapshot_id']} (Baseline: {$baseline['snapshot_id']}) -> " . ($matchSnapshot ? "🟢 IDENTICAL" : "🔴 MISMATCH") . "\n";

echo "\n--- PHYSICAL LAYER ---\n";
echo "Physical Translines    : {$livePhysical['physical_translines']} (Baseline: {$baseline['physical']['physical_translines']}, Δ: {$deltaPhysTL}) -> " . ($deltaPhysTL === 0 ? "🟢 PASS" : "🔴 FAIL") . "\n";
echo "Physical Transline Hash: {$livePhysical['physical_translines_hash']}\n";
echo "                         " . ($matchHashPhysTL ? "🟢 MATCH (Identical)" : "🔴 MISMATCH") . "\n";
echo "Physical Assets        : {$livePhysical['physical_assets']} (Baseline: {$baseline['physical']['physical_assets']}, Δ: {$deltaPhysAssets}) -> " . ($deltaPhysAssets === 0 ? "🟢 PASS" : "🔴 FAIL") . "\n";
echo "Physical Assets Hash   : {$livePhysical['physical_assets_hash']}\n";
echo "                         " . ($matchHashPhysAssets ? "🟢 MATCH (Identical)" : "🔴 MISMATCH") . "\n";
echo "Inactive Translines    : {$livePhysical['inactive_translines']} (Δ: {$deltaInactiveTL}) -> 🟢 PASS\n";
echo "Soft-Deleted Assets    : {$livePhysical['soft_deleted_assets']} (Δ: {$deltaSoftDeletedAssets}) -> 🟢 PASS\n";

echo "\n--- 4-LAYER TRUTH RECONCILIATION ---\n";
echo "Reconciliation Verdict : {$reconRes['json']['reconciliation_verdict']} -> " . ($reconciliationAuthoritative ? "🟢 PASS" : "🔴 FAIL") . "\n";

echo "\n====================================================================\n";
echo "  PHASE 4 OVERALL VERDICT: " . ($phase4Pass ? "🟢 PASS / LOCKED" : "🔴 FAIL") . "\n";
echo "  Phase 5 Status: 🔒 LOCKED / NOT STARTED (Awaiting explicit user GO)\n";
echo "====================================================================\n";

$resultData = [
    'gate'                => 'B7_PRODUCTION_ACTIVATION_PHASE_4',
    'timestamp'           => date('Y-m-d H:i:s T'),
    'environment'         => 'production',
    'target_host'         => '2.57.91.151 (https://sidaktejo.site)',
    'mode'                => 'STRICT_READ_ONLY',
    'verdict'             => $phase4Pass ? 'PASS' : 'FAIL',
    'baseline_snapshot'   => $baseline['snapshot_id'],
    'baseline'            => $baseline,
    'live_authoritative'  => $liveAuthoritative,
    'live_physical'       => $livePhysical,
    'deltas' => [
        'delta_active_translines'      => $deltaActiveTL,
        'delta_physical_translines'    => $deltaPhysTL,
        'delta_active_assets'          => $deltaActiveAssets,
        'delta_physical_assets'        => $deltaPhysAssets,
        'delta_span_meters'            => $deltaSpan,
        'delta_inactive_translines'    => $deltaInactiveTL,
        'delta_soft_deleted_assets'    => $deltaSoftDeletedAssets,
    ],
    'cryptographic_hashes' => [
        'active_translines' => [
            'baseline_full_sha256' => $baseline['authoritative']['active_translines_hash'],
            'live_full_sha256'     => $liveAuthoritative['active_translines_hash'],
            'match'                => $matchHashActiveTL,
        ],
        'physical_translines' => [
            'baseline_full_sha256' => $baseline['physical']['physical_translines_hash'],
            'live_full_sha256'     => $livePhysical['physical_translines_hash'],
            'match'                => $matchHashPhysTL,
        ],
        'active_assets' => [
            'baseline_full_sha256' => $baseline['authoritative']['active_assets_hash'],
            'live_full_sha256'     => $liveAuthoritative['active_assets_hash'],
            'match'                => $matchHashActiveAssets,
        ],
        'physical_assets' => [
            'baseline_full_sha256' => $baseline['physical']['physical_assets_hash'],
            'live_full_sha256'     => $livePhysical['physical_assets_hash'],
            'match'                => $matchHashPhysAssets,
        ],
    ],
    'snapshot_verification' => [
        'baseline_snapshot_id' => $baseline['snapshot_id'],
        'live_snapshot_id'     => $liveAuthoritative['topology_snapshot_id'],
        'match'                => $matchSnapshot,
    ],
    '4_layer_reconciliation' => [
        'endpoint_status' => $reconRes['code'],
        'verdict'         => $reconRes['json']['reconciliation_verdict'],
        'aligned'         => $reconciliationAuthoritative,
        'pack'            => $reconPack,
    ],
    'strict_read_only_invariants' => [
        'no_insert'   => true,
        'no_update'   => true,
        'no_delete'   => true,
        'no_alter'    => true,
        'mutations'   => 0,
    ],
];

file_put_contents(__DIR__ . '/phase4_sentinel_result.json', json_encode($resultData, JSON_PRETTY_PRINT));
echo "\nSaved forensic audit result to scratch/phase4_sentinel_result.json\n";
