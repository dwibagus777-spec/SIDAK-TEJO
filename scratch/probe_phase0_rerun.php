<?php
/**
 * FULL PHASE 0 RE-RUN v2 — B8.1 RE-BASELINE VALIDATION
 * Fixed: correct JSON path (topology_sentinel sub-key from /remediation/audit)
 * Snapshot: TOPOLOGY-20260928-245-81c43a7f
 */

const BASE_URL         = 'https://sidaktejo.site';
const AUDIT_TOKEN      = 'sidak_transline_audit_2026';

const EXPECTED_SNAP    = 'TOPOLOGY-20260928-245-81c43a7f';
const EXPECTED_ACT_TL  = 245;
const EXPECTED_PHY_TL  = 254;
const EXPECTED_ACT_ASS = 5236;
const EXPECTED_PHY_ASS = 5549;
const EXPECTED_SPAN    = 9418.37;

const EXPECTED_H_ACTIVE_TL    = '3305355a6dddf191a5176934f4de05f4368a21eb0f295660b3790804ac91af3a';
const EXPECTED_H_PHYSICAL_TL  = '90a92779ab1da6d911c7826a6056f168e27014778270764b4e8c42b5535d7462';
const EXPECTED_H_ACTIVE_ASS   = '83d8260ef2dfd1c8517e28879857fbd9816a3742a353217eef03d6a46a431711';
// Canonical h_physical_assets — PRODUCTION VERIFIED by 2 independent probes (1d variant):
const EXPECTED_H_PHYSICAL_ASS = '2e0477b238d5e352be5e9e70d2f57d42b808fd7d8d1d409dc40614c59193152a';

$results = [];
$pass = 0; $fail = 0;

function check(string $id, bool $ok, string $detail, &$results, &$pass, &$fail): void {
    $results[] = ['id' => $id, 'ok' => $ok, 'detail' => $detail];
    $ok ? $pass++ : $fail++;
    echo ($ok ? "[PASS]" : "[FAIL]") . " {$id}: {$detail}\n";
}

function curlGet(string $url, array $headers = []): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $errno  = curl_errno($ch);
    $err    = curl_error($ch);
    curl_close($ch);
    return ['status' => $status, 'body' => $body, 'errno' => $errno, 'err' => $err];
}

echo "\n=== FULL PHASE 0 RE-RUN v2 — TOPOLOGY-20260928-245-81c43a7f ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . " WIB\n\n";

// P0-01: HTTPS connectivity
$r = curlGet(BASE_URL . '/remediation/audit', ['X-Audit-Token: ' . AUDIT_TOKEN]);
check('P0-01', $r['errno'] === 0 && $r['status'] === 200,
    $r['errno'] === 0 ? "HTTPS OK (HTTP {$r['status']})" : "CURL error #{$r['errno']}: {$r['err']}",
    $results, $pass, $fail);

// P0-02: 401 perimeter
$r401 = curlGet(BASE_URL . '/remediation/audit');
check('P0-02', $r401['status'] === 401,
    "Unauthenticated → HTTP {$r401['status']} (expect 401)",
    $results, $pass, $fail);

// P0-03: 403 deploy firewall — POST to /remediation/migrate with audit-token only (NO deploy key)
// Security contract: authenticate() passes (audit token present), authorizeDeploy() fails (no deploy key) → 403
$chDep = curl_init(BASE_URL . '/remediation/migrate');
curl_setopt_array($chDep, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => '{}',
    CURLOPT_HTTPHEADER     => ['X-Audit-Token: ' . AUDIT_TOKEN, 'Content-Type: application/json'],
    // Deliberately NO X-Deploy-Master-Key header → must trigger 403 from authorizeDeploy()
]);
$depBody   = curl_exec($chDep);
$depStatus = curl_getinfo($chDep, CURLINFO_HTTP_CODE);
$depErrno  = curl_errno($chDep);
curl_close($chDep);
check('P0-03', $depStatus === 403,
    "Deploy firewall (audit-only POST to /migrate, no deploy key) → HTTP {$depStatus}" . ($depErrno ? " errno={$depErrno}" : "") . " (security contract: must be 403)",
    $results, $pass, $fail);

// Parse sentinel
if ($r['errno'] !== 0 || $r['status'] !== 200) {
    echo "\n[ABORT] audit endpoint unavailable.\n";
    exit(1);
}
$body = json_decode($r['body'], true);
if (!$body) {
    echo "\n[ABORT] Invalid JSON.\n";
    exit(1);
}

// Correct path: body->topology_sentinel
$ts = $body['topology_sentinel'] ?? [];

$actTL   = $ts['active_translines']     ?? null;
$phyTL   = $ts['physical_translines']   ?? null;
$actAss  = $ts['active_assets']         ?? null;
$phyAss  = $ts['physical_assets']       ?? null;
$span    = $ts['network_span_meters']   ?? null;
$snapId  = $ts['topology_snapshot_id']  ?? null;
$hActTL  = $ts['active_transline_hash'] ?? null;
$hPhyTL  = $ts['physical_transline_hash'] ?? null;
$hActAss = $ts['active_asset_hash']     ?? null;
$hPhyAss = $ts['physical_asset_hash']   ?? null;

check('P0-04', $actTL === EXPECTED_ACT_TL,
    "active_translines = {$actTL} (expect " . EXPECTED_ACT_TL . ")",
    $results, $pass, $fail);

check('P0-05', $phyTL === EXPECTED_PHY_TL,
    "physical_translines = {$phyTL} (expect " . EXPECTED_PHY_TL . ")",
    $results, $pass, $fail);

check('P0-06', $actAss === EXPECTED_ACT_ASS,
    "active_assets = {$actAss} (expect " . EXPECTED_ACT_ASS . ")",
    $results, $pass, $fail);

check('P0-07', $phyAss === EXPECTED_PHY_ASS,
    "physical_assets = {$phyAss} (expect " . EXPECTED_PHY_ASS . ")",
    $results, $pass, $fail);

check('P0-08', $span !== null && abs((float)$span - EXPECTED_SPAN) < 0.01,
    "network_span_meters = {$span} (expect " . EXPECTED_SPAN . ")",
    $results, $pass, $fail);

check('P0-09', $snapId === EXPECTED_SNAP,
    "topology_snapshot_id = '{$snapId}' (expect '" . EXPECTED_SNAP . "')",
    $results, $pass, $fail);

check('P0-10', $hActTL === EXPECTED_H_ACTIVE_TL,
    "h_active_tl = " . ($hActTL ?: 'NULL'),
    $results, $pass, $fail);

check('P0-11', $hPhyTL === EXPECTED_H_PHYSICAL_TL,
    "h_physical_tl = " . ($hPhyTL ?: 'NULL'),
    $results, $pass, $fail);

check('P0-12', $hActAss === EXPECTED_H_ACTIVE_ASS,
    "h_active_assets = " . ($hActAss ?: 'NULL'),
    $results, $pass, $fail);

// P0-13: physical_asset_hash — single canonical (1d variant, user-confirmed production truth)
check('P0-13', $hPhyAss === EXPECTED_H_PHYSICAL_ASS,
    "h_physical_assets\n        actual   = " . ($hPhyAss ?: 'NULL') . "\n        expected = " . EXPECTED_H_PHYSICAL_ASS,
    $results, $pass, $fail);

// Summary
$total = $pass + $fail;
echo "\n=== PHASE 0 SUMMARY ===\n";
echo "PASS: {$pass}/{$total}\n";
echo "FAIL: {$fail}/{$total}\n";

$hardFails = array_filter($results, fn($r) => !$r['ok']);

if (count($hardFails) === 0) {
    echo "Verdict: ✅ FULL PASS — Phase 0 = 13/13\n";
} else {
    echo "Verdict: ❌ FAIL — Hard failures present:\n";
    foreach ($hardFails as $f) {
        echo "  ✗ {$f['id']}: {$f['detail']}\n";
    }
}

echo "\n--- Full physical_asset_hash from production ---\n";
echo $hPhyAss . "\n";

echo "\n--- reconciliation_status ---\n";
echo ($body['reconciliation_status'] ?? 'N/A') . "\n";

echo "\n--- b81_schema_installed ---\n";
echo json_encode($body['b81_schema_installed'] ?? null) . "\n";
