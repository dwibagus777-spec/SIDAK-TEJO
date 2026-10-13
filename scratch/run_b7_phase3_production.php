<?php

/**
 * SIDAK TEJO — Phase B.7 Production Activation: Phase 3 Adversarial Attack & Resilience Audit
 * Target Host: Production https://sidaktejo.site (IP: 2.57.91.151)
 *
 * Verifies all 14 Canonical Adversarial Attack Scenarios on Live Production:
 * 1.  ADV-01: Invalid/malformed sync envelope -> 4xx, no domain mutation
 * 2.  ADV-02: UUID collision dengan payload berbeda -> reject/conflict
 * 3.  ADV-03: Batch replay -> idempotent
 * 4.  ADV-04: Mock GPS -> reject
 * 5.  ADV-05: Invalid coordinates -> reject
 * 6.  ADV-06: Revoked device PUSH -> 403
 * 7.  ADV-07: Revoked device EVIDENCE -> 403
 * 8.  ADV-08: Revoked device PULL -> 403
 * 9.  ADV-09: FSM shortcut -> reject
 * 10. ADV-10: Premature evidence seal -> reject
 * 11. ADV-11: Tampered SHA-256 -> reject + staging cleanup
 * 12. ADV-12: Unauthorized user scope -> reject/isolate
 * 13. ADV-13: Rejected operation -> journal retained, tidak masuk delta
 * 14. ADV-14: Topology mutation attempt -> reject, Δ topology = 0
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
echo "  SIDAK TEJO — B.7 PRODUCTION ACTIVATION: PHASE 3 ADVERSARIAL AUDIT \n";
echo "  Target Host: 2.57.91.151 (sidaktejo.site)                         \n";
echo "  Environment: PRODUCTION AUTHORITATIVE                             \n";
echo "  Timestamp: " . date('Y-m-d H:i:s T') . "                          \n";
echo "====================================================================\n\n";

$report = [
    'gate'        => 'B7_PRODUCTION_ACTIVATION_PHASE_3',
    'timestamp'   => date('Y-m-d H:i:s T'),
    'environment' => 'production',
    'target_host' => '2.57.91.151 (https://sidaktejo.site)',
    'sub_gates'   => [],
];

// =========================================================================
// SUB-GATE 3A: SECURITY & AUTHORIZATION FIREWALL
// =========================================================================
echo "[3A] Checking Security Firewalls (401 & 403 Enforced)...\n";

$unauth = callEndpoint("{$baseUrl}/mobile-sync/test/adversarial", 'POST');
echo "     1. Unauthenticated request: HTTP {$unauth['code']} ({$unauth['elapsed']} ms)\n";
$pass401 = ($unauth['code'] === 401);

$missingDeploy = callEndpoint("{$baseUrl}/mobile-sync/test/adversarial?key={$auditToken}", 'POST');
echo "     2. Missing Deploy Key: HTTP {$missingDeploy['code']} ({$missingDeploy['elapsed']} ms)\n";
$pass403 = ($missingDeploy['code'] === 403);

$pass3A = $pass401 && $pass403;
if ($pass3A) {
    echo "     --> SUB-GATE 3A: PASS (401 & 403 Firewalls Enforced)\n\n";
} else {
    echo "     --> SUB-GATE 3A: FAIL (Firewall breach: 401={$pass401}, 403={$pass403})\n\n";
    exit(1);
}

$report['sub_gates']['3A_security_firewall'] = [
    'status'   => 'PASS',
    'code_401' => $unauth['code'],
    'code_403' => $missingDeploy['code'],
];

// =========================================================================
// SUB-GATE 3B: EXECUTE 14 CANONICAL ADVERSARIAL ATTACK SCENARIOS
// =========================================================================
echo "[3B] Executing 14 Canonical Adversarial Attack Scenarios on Production...\n";
$advUrl = "{$baseUrl}/mobile-sync/test/adversarial?key={$auditToken}&deploy_key={$deployMasterKey}";
$advRes = callEndpoint($advUrl, 'POST');

echo "     HTTP Code: {$advRes['code']} ({$advRes['elapsed']} ms)\n";

if ($advRes['code'] !== 200 || !is_array($advRes['json'])) {
    echo "     --> SUB-GATE 3B: FAIL (Expected HTTP 200, got {$advRes['code']})\n";
    echo "     Body: " . substr($advRes['body'], 0, 500) . "\n";
    exit(1);
}

$advData = $advRes['json'];
$verdict = $advData['verdict'] ?? 'UNKNOWN';
$scenarios = $advData['scenarios'] ?? [];

echo "     Verdict: {$verdict}\n";
echo "     Scenarios Tested: " . ($advData['scenarios_total'] ?? 0) . " | Passed: " . ($advData['scenarios_passed'] ?? 0) . "\n\n";

echo "     Adversarial Scenario Detailed Breakdown:\n";
$expectedScenarios = [
    'ADV-01_invalid_envelope'                  => 'Invalid/Malformed sync envelope -> 400 REJECTED',
    'ADV-02_uuid_collision_conflicting_payload'=> 'UUID collision with conflicting payload -> DUPLICATE, 0 domain writes',
    'ADV-03_batch_replay_idempotency'          => 'Batch replay -> idempotent HTTP 200, 0 new rows',
    'ADV-04_mock_gps_rejection'                => 'Mock GPS -> rejected, journal logged REJECTED',
    'ADV-05_invalid_gps_coordinates'           => 'Invalid GPS coordinates -> rejected, journal logged REJECTED',
    'ADV-06_revoked_device_push'               => 'Revoked device PUSH -> HTTP 403 DEVICE_REVOKED',
    'ADV-07_revoked_device_evidence'           => 'Revoked device EVIDENCE -> HTTP 403 DEVICE_REVOKED',
    'ADV-08_revoked_device_pull'               => 'Revoked device PULL -> HTTP 403 DEVICE_REVOKED',
    'ADV-09_fsm_shortcut'                      => 'FSM shortcut (illegal direct jump) -> rejected',
    'ADV-10_premature_evidence_seal'           => 'Premature evidence seal -> HTTP 422 CHUNKS_INCOMPLETE',
    'ADV-11_tampered_sha256'                   => 'Tampered SHA-256 -> HTTP 422 HASH_MISMATCH, staging purged',
    'ADV-12_user_scoping_isolation'            => 'Unauthorized user scoping -> User A isolated from User B',
    'ADV-13_rejected_operation_delta_exclusion'=> 'Rejected operations in journal excluded from mobile delta',
    'ADV-14_topology_sentinel_invariants'      => 'Topology mutation invariant -> Delta = 0, hashes 100% match',
];

$allScenariosPass = true;
foreach ($expectedScenarios as $key => $desc) {
    $sc = $scenarios[$key] ?? ['status' => 'FAIL', 'reason' => 'Missing scenario'];
    $scPass = ($sc['status'] === 'PASS');
    if (!$scPass) {
        $allScenariosPass = false;
    }
    $statusIcon = $scPass ? "🟢 PASS" : "🔴 FAIL";
    echo "     • {$key}: {$statusIcon} ({$desc})\n";
}
echo "\n";

$pass3B = ($advRes['code'] === 200 && $advData['status'] === 'PASS' && $allScenariosPass);
$report['sub_gates']['3B_adversarial_matrix'] = [
    'status'           => $pass3B ? 'PASS' : 'FAIL',
    'scenarios_total'  => count($expectedScenarios),
    'scenarios_passed' => count(array_filter($scenarios, fn($s) => $s['status'] === 'PASS')),
    'scenarios'        => $scenarios,
];

// =========================================================================
// SUB-GATE 3C: POST-ADVERSARIAL 4-LAYER TRUTH RECONCILIATION
// =========================================================================
echo "[3C] Running 4-Layer Truth Reconciliation Post-Adversarial...\n";
$recon = callEndpoint("{$baseUrl}/mobile-sync/forensic-reconciliation?key={$auditToken}");
$reconVerdict = $recon['json']['reconciliation_verdict'] ?? 'UNKNOWN';
echo "     HTTP Code: {$recon['code']} ({$recon['elapsed']} ms)\n";
echo "     Reconciliation Verdict: {$reconVerdict}\n";

$pass3C = ($recon['code'] === 200 && $reconVerdict === 'PASS_AUTHORITATIVE_PRODUCTION');
echo "     --> SUB-GATE 3C: " . ($pass3C ? "PASS" : "FAIL") . "\n\n";

$report['sub_gates']['3C_reconciliation_post_adversarial'] = [
    'status'  => $pass3C ? 'PASS' : 'FAIL',
    'verdict' => $reconVerdict,
    'pack'    => $recon['json']['reconciliation_pack'] ?? [],
];

// =========================================================================
// OVERALL PHASE 3 VERDICT
// =========================================================================
$overallPass = ($pass3A && $pass3B && $pass3C);
$report['overall_verdict'] = $overallPass ? 'PASS' : 'FAIL';

echo "====================================================================\n";
echo "  PHASE 3 OVERALL VERDICT: " . ($overallPass ? "🟢 PASS / READY FOR LOCK REVIEW" : "🔴 FAIL") . "\n";
echo "  Phase 4 Status: 🔒 LOCKED / NOT STARTED (Awaiting explicit user GO)\n";
echo "====================================================================\n";

file_put_contents(__DIR__ . '/phase3_result.json', json_encode($report, JSON_PRETTY_PRINT));
echo "\nSaved audit result to scratch/phase3_result.json\n";
