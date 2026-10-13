<?php

/**
 * SIDAK TEJO — Phase B.7 Production Activation: Phase 2 Synthetic Production E2E
 * Target Host: Production https://sidaktejo.site (IP: 2.57.91.151)
 *
 * Verifies all 6 Hard-Stops on Live Production:
 * 1. Synthetic record NOT deleted (DELETE = 0, terminal state: CLOSED, synthetic = true, test_mode = true)
 * 2. Replay is idempotent (HTTP 200, canonical receipt, domain execution = 0, duplicate business write = 0)
 * 3. Evidence verified (chunk hash -> assembled bytes -> full SHA-256 -> field_evidence)
 * 4. Delta pull proves cursor (cursor_in, journal_seq, next_cursor, 0 duplicates, 0 skips, no regression)
 * 5. Prediction != Actual preserved (rank 2 candidate finding recorded, prediction & actual both stored, FLI unmodified)
 * 6. Topology sentinel post-E2E identical (243 TL, 252 physical, 5236 assets, 5549 physical, 9418.37m, snapshot TOPOLOGY-20260925-243-ad2c9fcb)
 */

$baseUrl = 'https://sidaktejo.site';
$auditToken = getenv('SIDAK_AUDIT_TOKEN') ?: 'sidak_transline_audit_2026';
$deployMasterKey = getenv('SIDAK_DEPLOY_MASTER_KEY') ?: 'sidak_tejo_deploy_master_2026';

function callEndpoint(string $url, string $method = 'GET', array $payload = [], ?string $token = null, ?string $deployKey = null): array
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RESOLVE, ['sidaktejo.site:443:2.57.91.151', 'sidaktejo.site:80:2.57.91.151']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 90);

    $headers = [];
    if ($token !== null) {
        $headers[] = 'X-Audit-Token: ' . $token;
    }
    if ($deployKey !== null) {
        $headers[] = 'X-Deploy-Master-Key: ' . $deployKey;
    }

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        $jsonPayload = json_encode($payload);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Content-Length: ' . strlen($jsonPayload);
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
echo "  SIDAK TEJO — B.7 PRODUCTION ACTIVATION: PHASE 2 SYNTHETIC E2E     \n";
echo "  Target Host: 2.57.91.151 (sidaktejo.site)                         \n";
echo "  Environment: PRODUCTION AUTHORITATIVE                             \n";
echo "  Timestamp: " . date('Y-m-d H:i:s T') . "                          \n";
echo "====================================================================\n\n";

$report = [
    'gate'        => 'B7_PRODUCTION_ACTIVATION_PHASE_2',
    'timestamp'   => date('Y-m-d H:i:s T'),
    'environment' => 'production',
    'target_host' => '2.57.91.151 (https://sidaktejo.site)',
    'sub_gates'   => [],
];

// =========================================================================
// SUB-GATE 2A: AUTHENTICATION & DEPLOY KEY FIREWALL
// =========================================================================
echo "[2A] Checking Security Firewalls...\n";

// 1. Unauthenticated (no token, no deploy key) -> expect 401
$unauth = callEndpoint("{$baseUrl}/mobile-sync/test/synthetic-e2e", 'POST');
echo "     1. Unauthenticated request: HTTP {$unauth['code']} ({$unauth['elapsed']} ms)\n";
$pass401 = ($unauth['code'] === 401);

// 2. Token present, but missing Deploy Key -> expect 403
$missingDeploy = callEndpoint("{$baseUrl}/mobile-sync/test/synthetic-e2e?key={$auditToken}", 'POST');
echo "     2. Missing Deploy Key: HTTP {$missingDeploy['code']} ({$missingDeploy['elapsed']} ms)\n";
$pass403 = ($missingDeploy['code'] === 403);

$pass2A = $pass401 && $pass403;
if ($pass2A) {
    echo "     --> SUB-GATE 2A: PASS (401 & 403 Firewalls Enforced)\n\n";
} else {
    echo "     --> SUB-GATE 2A: FAIL (Firewall breach: 401={$pass401}, 403={$pass403})\n\n";
    exit(1);
}

$report['sub_gates']['2A_security_firewall'] = [
    'status'   => 'PASS',
    'code_401' => $unauth['code'],
    'code_403' => $missingDeploy['code'],
];

// =========================================================================
// SUB-GATE 2B: EXECUTE SYNTHETIC E2E ON PRODUCTION
// =========================================================================
echo "[2B] Executing Synthetic Production E2E Chain...\n";
$e2eUrl = "{$baseUrl}/mobile-sync/test/synthetic-e2e?key={$auditToken}&deploy_key={$deployMasterKey}";
$e2eRes = callEndpoint($e2eUrl, 'POST');

echo "     HTTP Code: {$e2eRes['code']} ({$e2eRes['elapsed']} ms)\n";

if ($e2eRes['code'] !== 200 || !is_array($e2eRes['json'])) {
    echo "     --> SUB-GATE 2B: FAIL (Expected HTTP 200, got {$e2eRes['code']})\n";
    echo "     Body: " . substr($e2eRes['body'], 0, 500) . "\n";
    exit(1);
}

$e2eData = $e2eRes['json'];
$verdict = $e2eData['verdict'] ?? 'UNKNOWN';
$correlationId = $e2eData['correlation_id'] ?? 'UNKNOWN';
$chain = $e2eData['correlation_chain'] ?? [];
$hardStops = $e2eData['hard_stops'] ?? [];

echo "     Verdict: {$verdict}\n";
echo "     Correlation ID: {$correlationId}\n";
echo "     Sync ID: " . ($chain['sync_id'] ?? 'N/A') . "\n";
echo "     Case ID: " . ($chain['case_id'] ?? 'N/A') . " (Final Status: " . ($chain['case_final_status'] ?? 'N/A') . ")\n";
echo "     Finding ID: " . ($chain['finding_id'] ?? 'N/A') . "\n";
echo "     Evidence ID: " . ($chain['evidence_id'] ?? 'N/A') . " (Field Evidence ID: " . ($chain['field_evidence_id'] ?? 'N/A') . ")\n";
echo "     Journal Sequence: " . ($chain['journal_seq_start'] ?? 'N/A') . " -> " . ($chain['journal_seq_end'] ?? 'N/A') . "\n";
echo "     Next Pull Cursor: " . ($chain['pull_cursor_final'] ?? 'N/A') . "\n\n";

$pass2B = ($e2eRes['code'] === 200 && $e2eData['status'] === 'PASS');
$report['sub_gates']['2B_synthetic_e2e_execution'] = [
    'status'            => $pass2B ? 'PASS' : 'FAIL',
    'http_code'         => $e2eRes['code'],
    'execution_time_ms' => $e2eRes['elapsed'],
    'correlation_id'    => $correlationId,
    'correlation_chain' => $chain,
];

// =========================================================================
// SUB-GATE 2C: VERIFY THE 6 HARD-STOPS
// =========================================================================
echo "[2C] Auditing the 6 Non-Negotiable Hard-Stops:\n";

// Hard-stop 1: Retention & No DELETE
$hs1 = $hardStops['hard_stop_1_retention'] ?? [];
$passHs1 = ($hs1['status'] === 'PASS' && $hs1['delete_count'] === 0 && $hs1['case_final_status'] === 'CLOSED');
echo "     1. Permanent Retention (DELETE = 0, Status = CLOSED): " . ($passHs1 ? "PASS" : "FAIL") . "\n";

// Hard-stop 2: Idempotent Replay
$hs2 = $hardStops['hard_stop_2_idempotent_replay'] ?? [];
$passHs2 = ($hs2['status'] === 'PASS' && $hs2['http_code'] === 200 && $hs2['duplicate_count'] === 5 && $hs2['domain_execution_count'] === 0 && $hs2['duplicate_writes'] === 0);
echo "     2. Replay Idempotency (HTTP 200, Duplicates = 5, Domain Writes = 0): " . ($passHs2 ? "PASS" : "FAIL") . "\n";

// Hard-stop 3: Evidence Hash Chain & Seal
$hs3 = $hardStops['hard_stop_3_evidence_seal'] ?? [];
$passHs3 = ($hs3['status'] === 'PASS' && $hs3['assembled_sha256_match'] === true && $hs3['field_evidence_sealed'] === true);
echo "     3. Evidence Hash Integrity (Chunk -> Assembly -> Sealed SHA-256): " . ($passHs3 ? "PASS" : "FAIL") . "\n";
echo "        - Full File SHA-256: " . ($hs3['full_file_sha256'] ?? 'N/A') . "\n";

// Hard-stop 4: Delta Pull Cursor Monotonicity
$hs4 = $hardStops['hard_stop_4_cursor_monotonicity'] ?? [];
$passHs4 = ($hs4['status'] === 'PASS' && $hs4['zero_duplicates'] === true && $hs4['zero_skips'] === true && $hs4['no_regression'] === true);
echo "     4. Delta Pull Cursor Monotonicity (Cursor 0 -> Next Cursor, Zero Skips/Duplicates): " . ($passHs4 ? "PASS" : "FAIL") . "\n";
echo "        - Cursor In: " . ($hs4['cursor_in'] ?? 'N/A') . ", Next Cursor: " . ($hs4['next_cursor'] ?? 'N/A') . ", Delivered: " . ($hs4['records_delivered'] ?? 'N/A') . "\n";

// Hard-stop 5: Prediction != Actual Preserved
$hs5 = $hardStops['hard_stop_5_prediction_vs_actual'] ?? [];
$passHs5 = ($hs5['status'] === 'PASS' && $hs5['predicted_asset_id'] !== $hs5['actual_asset_id'] && $hs5['prediction_rank'] === 2 && $hs5['fli_weights_modified'] === false);
echo "     5. Prediction != Actual Preserved (Predicted Asset #{$hs5['predicted_asset_id']} != Actual Asset #{$hs5['actual_asset_id']}, Rank 2, FLI unmodified): " . ($passHs5 ? "PASS" : "FAIL") . "\n";

// Hard-stop 6: Topology Sentinel Post-E2E
$hs6 = $hardStops['hard_stop_6_topology_sentinel'] ?? [];
$passHs6 = ($hs6['status'] === 'PASS' && $hs6['zero_mutation'] === true
    && $hs6['delta_active_tl'] === 0 && $hs6['delta_physical_tl'] === 0
    && $hs6['delta_active_assets'] === 0 && $hs6['delta_physical_assets'] === 0
    && $hs6['hash_active_tl_match'] === true && $hs6['hash_phys_tl_match'] === true
    && $hs6['hash_active_asset_match'] === true && $hs6['hash_phys_asset_match'] === true);
echo "     6. Topology Sentinel Post-E2E (Delta TL = 0, Delta Assets = 0, Hashes 100% Match): " . ($passHs6 ? "PASS" : "FAIL") . "\n";
echo "        - Active TL: {$hs6['sentinel_before']['active_translines']} -> {$hs6['sentinel_after']['active_translines']} (Delta = {$hs6['delta_active_tl']})\n";
echo "        - Physical TL: {$hs6['sentinel_before']['physical_translines']} -> {$hs6['sentinel_after']['physical_translines']} (Delta = {$hs6['delta_physical_tl']})\n";
echo "        - Active Assets: {$hs6['sentinel_before']['active_assets']} -> {$hs6['sentinel_after']['active_assets']} (Delta = {$hs6['delta_active_assets']})\n";
echo "        - Physical Assets: {$hs6['sentinel_before']['physical_assets']} -> {$hs6['sentinel_after']['physical_assets']} (Delta = {$hs6['delta_physical_assets']})\n";
echo "        - Total Span: {$hs6['sentinel_after']['network_span_meters']} m\n";
echo "        - Snapshot: {$hs6['sentinel_after']['topology_snapshot_id']}\n\n";

$allHardStopsPass = ($passHs1 && $passHs2 && $passHs3 && $passHs4 && $passHs5 && $passHs6);
$report['sub_gates']['2C_hard_stops'] = [
    'all_pass'    => $allHardStopsPass,
    'hard_stop_1' => $hs1,
    'hard_stop_2' => $hs2,
    'hard_stop_3' => $hs3,
    'hard_stop_4' => $hs4,
    'hard_stop_5' => $hs5,
    'hard_stop_6' => $hs6,
];

// =========================================================================
// SUB-GATE 2D: PUBLIC API OVER-THE-WIRE VERIFICATION (/api/v1/sync/pull)
// =========================================================================
echo "[2D] Verifying Public Transport Boundary Over-The-Wire (GET /api/v1/sync/pull)...\n";
$wirePull0 = callEndpoint("{$baseUrl}/api/v1/sync/pull?cursor=0&device_id=SYNTH-DEV-B7-E2E-001&user_id=999");
echo "     Page 1 (cursor=0): HTTP {$wirePull0['code']}, count: " . ($wirePull0['json']['count'] ?? 'N/A') . ", next_cursor: " . ($wirePull0['json']['next_cursor'] ?? 'N/A') . " ({$wirePull0['elapsed']} ms)\n";

$wireNextCursor = (int)($wirePull0['json']['next_cursor'] ?? 0);
$wirePullNext = callEndpoint("{$baseUrl}/api/v1/sync/pull?cursor={$wireNextCursor}&device_id=SYNTH-DEV-B7-E2E-001&user_id=999");
echo "     Page 2 (cursor={$wireNextCursor}): HTTP {$wirePullNext['code']}, count: " . ($wirePullNext['json']['count'] ?? 'N/A') . ", next_cursor: " . ($wirePullNext['json']['next_cursor'] ?? 'N/A') . " ({$wirePullNext['elapsed']} ms)\n";

$pass2D = ($wirePull0['code'] === 200 && ($wirePull0['json']['count'] ?? 0) >= 5)
    && ($wirePullNext['code'] === 200 && ($wirePullNext['json']['count'] ?? 0) === 0);

echo "     --> SUB-GATE 2D: " . ($pass2D ? "PASS (Wire boundary confirmed)" : "FAIL") . "\n\n";

$report['sub_gates']['2D_wire_pull_verification'] = [
    'status'            => $pass2D ? 'PASS' : 'FAIL',
    'page_1_http_code'  => $wirePull0['code'],
    'page_1_count'      => $wirePull0['json']['count'] ?? 0,
    'page_1_next_cursor'=> $wireNextCursor,
    'page_2_http_code'  => $wirePullNext['code'],
    'page_2_count'      => $wirePullNext['json']['count'] ?? 0,
];

// =========================================================================
// SUB-GATE 2E: 4-LAYER TRUTH RECONCILIATION POST-E2E
// =========================================================================
echo "[2E] Running 4-Layer Truth Reconciliation Post-E2E...\n";
$recon = callEndpoint("{$baseUrl}/mobile-sync/forensic-reconciliation?key={$auditToken}");
$reconVerdict = $recon['json']['reconciliation_verdict'] ?? 'UNKNOWN';
echo "     HTTP Code: {$recon['code']} ({$recon['elapsed']} ms)\n";
echo "     Reconciliation Verdict: {$reconVerdict}\n";

$pass2E = ($recon['code'] === 200 && $reconVerdict === 'PASS_AUTHORITATIVE_PRODUCTION');
echo "     --> SUB-GATE 2E: " . ($pass2E ? "PASS" : "FAIL") . "\n\n";

$report['sub_gates']['2E_reconciliation_post_e2e'] = [
    'status'  => $pass2E ? 'PASS' : 'FAIL',
    'verdict' => $reconVerdict,
    'pack'    => $recon['json']['reconciliation_pack'] ?? [],
];

// =========================================================================
// OVERALL PHASE 2 VERDICT
// =========================================================================
$overallPass = ($pass2A && $pass2B && $allHardStopsPass && $pass2D && $pass2E);
$report['overall_verdict'] = $overallPass ? 'PASS' : 'FAIL';

echo "====================================================================\n";
echo "  PHASE 2 OVERALL VERDICT: " . ($overallPass ? "🟢 PASS / READY FOR LOCK REVIEW" : "🔴 FAIL") . "\n";
echo "  Phase 3 Status: 🔒 LOCKED / NOT STARTED (Awaiting explicit user GO)\n";
echo "====================================================================\n";

file_put_contents(__DIR__ . '/phase2_result.json', json_encode($report, JSON_PRETTY_PRINT));
echo "\nSaved audit result to scratch/phase2_result.json\n";
