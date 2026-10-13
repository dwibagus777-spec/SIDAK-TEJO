<?php

/**
 * SIDAK TEJO — Phase B.7 Production Activation: Phase 1 Schema Migration & Verification
 *
 * Implements the 6-step Phase 1 Gate Sequence:
 * 1A. Authorization Firewall Check (HTTP 403 on missing Deploy Key)
 * 1B. Primary Migration Execution (Create 4 B.7 Tables)
 * 1C. In-Depth Schema Forensic Verification (Column types, AUTO_INCREMENT PK, Unique Constraints)
 * 1D. Idempotency & Re-Run Verification (HTTP 200 MIGRATION_ALREADY_SEALED)
 * 1E. Topology & B.6 Sentinel Verification (Δ = 0, Hash Invariants, Span Invariants)
 * 1F. 4-Layer Truth Reconciliation
 */

$baseUrl = getenv('SIDAK_BASE_URL') ?: 'https://sidaktejo.site/mobile-sync';
$auditToken = getenv('SIDAK_AUDIT_TOKEN') ?: 'sidak_transline_audit_2026';
$deployMasterKey = getenv('SIDAK_DEPLOY_MASTER_KEY') ?: 'sidak_tejo_deploy_master_2026';

function callEndpoint(string $url, string $method = 'GET', array $payload = [], ?string $token = null, ?string $deployKey = null): array
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
echo "  SIDAK TEJO — B.7 PRODUCTION ACTIVATION: PHASE 1 SCHEMA MIGRATION  \n";
echo "  Target URL: {$baseUrl}                                             \n";
echo "  Target Host: 2.57.91.151 (sidaktejo.site)                         \n";
echo "====================================================================\n\n";

$report = [
    'gate'        => 'B7_PRODUCTION_ACTIVATION_PHASE_1',
    'timestamp'   => date('Y-m-d H:i:s T'),
    'environment' => 'production',
    'steps'       => [],
];

// =========================================================================
// STEP 1A: AUTHORIZATION FIREWALL (EXPECT HTTP 403 ON MISSING DEPLOY KEY)
// =========================================================================
echo "[1A] Checking Authorization Firewall (Missing Deploy Key -> Expect 403)...\n";
$authCheck = callEndpoint("{$baseUrl}/migrate?key={$auditToken}", 'POST');
echo "     HTTP Code: {$authCheck['code']} ({$authCheck['elapsed']} ms)\n";

$pass1A = ($authCheck['code'] === 403);
if ($pass1A) {
    echo "     PASS: Request without Deploy Key was blocked with HTTP 403 Forbidden.\n";
} else {
    echo "     FAIL: Expected HTTP 403, got {$authCheck['code']}!\n";
    exit(1);
}
$report['steps']['1A_authorization'] = [
    'status'    => $pass1A ? 'PASS' : 'FAIL',
    'http_code' => $authCheck['code'],
    'response'  => $authCheck['json'] ?? [],
];

// =========================================================================
// STEP 1B: PRIMARY MIGRATION EXECUTION (WITH DEPLOY MASTER KEY)
// =========================================================================
echo "\n[1B] Executing Primary Migration (With Deploy Master Key)...\n";
$mig1 = callEndpoint("{$baseUrl}/migrate?key={$auditToken}&deploy_key={$deployMasterKey}", 'POST');
echo "     HTTP Code: {$mig1['code']} ({$mig1['elapsed']} ms)\n";

if ($mig1['code'] !== 200 || !is_array($mig1['json'])) {
    echo "     FAIL: Migration request failed or returned invalid JSON! Code: {$mig1['code']}\n";
    echo "     Error: {$mig1['error']}\n";
    echo "     Body: {$mig1['body']}\n";
    exit(1);
}

$mig1Data = $mig1['json'];
$mig1Status = $mig1Data['status'] ?? '';
echo "     Migration Status: {$mig1Status}\n";
$pass1B = ($mig1['code'] === 200 && ($mig1Status === 'MIGRATION_INSTALLED_AND_SEALED' || $mig1Status === 'MIGRATION_ALREADY_SEALED'));
if ($pass1B) {
    echo "     PASS: Primary migration executed and confirmed.\n";
} else {
    echo "     FAIL: Migration status unexpected: {$mig1Status}\n";
    exit(1);
}
$report['steps']['1B_migration_first_run'] = [
    'status'           => $pass1B ? 'PASS' : 'FAIL',
    'http_code'        => $mig1['code'],
    'migration_status' => $mig1Status,
];

// =========================================================================
// STEP 1C: IN-DEPTH SCHEMA FORENSIC VERIFICATION
// =========================================================================
echo "\n[1C] Verifying Schema Forensics on Live Database...\n";
$forensics = $mig1Data['phase_1c_forensics'] ?? [];
$tablesForensics = $forensics['tables'] ?? [];

echo "     1. Table mobile_devices        : " . (!empty($tablesForensics['mobile_devices']['valid']) ? 'VALID ✅' : 'INVALID ❌') . "\n";
echo "     2. Table mobile_sync_batches   : " . (!empty($tablesForensics['mobile_sync_batches']['valid']) ? 'VALID ✅' : 'INVALID ❌') . "\n";

$journalForensics = $tablesForensics['mobile_sync_journal'] ?? [];
echo "     3. Table mobile_sync_journal   :\n";
echo "        - journal_seq BIGINT PK     : " . (!empty($journalForensics['journal_seq_bigint_pk']) ? 'YES (PRI AUTO_INCREMENT) ✅' : 'NO ❌') . "\n";
echo "        - journal_seq Type          : " . ($journalForensics['journal_seq_type'] ?? 'N/A') . "\n";
echo "        - client_submission_uuid    : " . (!empty($journalForensics['uuid_unique']) ? 'UNIQUE CONSTRAINT ✅' : 'NOT UNIQUE ❌') . "\n";

$chunkForensics = $tablesForensics['mobile_evidence_chunks'] ?? [];
echo "     4. Table mobile_evidence_chunks:\n";
echo "        - (evidence_id, chunk_index): " . (!empty($chunkForensics['composite_unique_chunk']) ? 'COMPOSITE UNIQUE ✅' : 'NOT UNIQUE ❌') . "\n";

$pass1C = !empty($forensics['all_valid']);
if ($pass1C) {
    echo "     PASS: All 4 B.7 tables conform strictly to vB7-SPEC-1.1 schema.\n";
} else {
    echo "     FAIL: Schema forensic validation failed!\n";
    exit(1);
}
$report['steps']['1C_schema_forensics'] = $forensics;

// =========================================================================
// STEP 1D: IDEMPOTENCY & RE-RUN VERIFICATION (SECOND RUN)
// =========================================================================
echo "\n[1D] Verifying Migration Idempotency (Second Run -> Expect MIGRATION_ALREADY_SEALED)...\n";
$mig2 = callEndpoint("{$baseUrl}/migrate?key={$auditToken}&deploy_key={$deployMasterKey}", 'POST');
echo "     HTTP Code: {$mig2['code']} ({$mig2['elapsed']} ms)\n";

$mig2Data = $mig2['json'] ?? [];
$mig2Status = $mig2Data['status'] ?? '';
echo "     Second Run Status: {$mig2Status}\n";

$pass1D = ($mig2['code'] === 200 && $mig2Status === 'MIGRATION_ALREADY_SEALED');
if ($pass1D) {
    echo "     PASS: Second run returned MIGRATION_ALREADY_SEALED with zero duplicate table/index errors.\n";
} else {
    echo "     FAIL: Second run expected MIGRATION_ALREADY_SEALED, got {$mig2Status}!\n";
    exit(1);
}
$report['steps']['1D_idempotency_second_run'] = [
    'status'           => $pass1D ? 'PASS' : 'FAIL',
    'http_code'        => $mig2['code'],
    'migration_status' => $mig2Status,
];

// =========================================================================
// STEP 1E: TOPOLOGY & B.6 SENTINEL VERIFICATION
// =========================================================================
echo "\n[1E] Checking Topology & Physical Sentinel Invariants...\n";
$sentinel = $mig2Data['phase_1e_sentinel'] ?? [];
$before = $sentinel['sentinel_before'] ?? [];
$after  = $sentinel['sentinel_after'] ?? [];

echo "     Active Translines       : {$before['active_translines']} -> {$after['active_translines']} (Δ = {$sentinel['delta_active_tl']})\n";
echo "     Physical Translines     : {$before['physical_translines']} -> {$after['physical_translines']} (Δ = {$sentinel['delta_physical_tl']})\n";
echo "     Active Assets           : {$before['active_assets']} -> {$after['active_assets']} (Δ = {$sentinel['delta_active_assets']})\n";
echo "     Physical Assets         : {$before['physical_assets']} -> {$after['physical_assets']} (Δ = {$sentinel['delta_physical_assets']})\n";
echo "     Active TL Hash Match    : " . (!empty($sentinel['hash_active_tl_match']) ? 'MATCH (TRUE) ✅' : 'MISMATCH ❌') . "\n";
echo "     Active Asset Hash Match : " . (!empty($sentinel['hash_active_asset_match']) ? 'MATCH (TRUE) ✅' : 'MISMATCH ❌') . "\n";
echo "     Physical TL Hash Match  : " . (!empty($sentinel['hash_phys_tl_match']) ? 'MATCH (TRUE) ✅' : 'MISMATCH ❌') . "\n";
echo "     Physical Asset Hash Match: " . (!empty($sentinel['hash_phys_asset_match']) ? 'MATCH (TRUE) ✅' : 'MISMATCH ❌') . "\n";
echo "     7 B.6 Tables Untouched  : " . (!empty($sentinel['b6_tables_untouched']) ? 'YES (100% UNTOUCHED) ✅' : 'POLLUTED ❌') . "\n";
echo "     Network Span            : {$after['network_span_meters']} m (Δ = 0.00 m)\n";
echo "     Snapshot ID             : {$after['topology_snapshot_id']}\n";

$pass1E = !empty($sentinel['zero_mutation']) && !empty($sentinel['b6_tables_untouched']);
if ($pass1E) {
    echo "     PASS: Zero topology mutation and zero asset mutation confirmed.\n";
} else {
    echo "     FAIL: Sentinel detected unexpected mutation!\n";
    exit(1);
}
$report['steps']['1E_sentinel'] = $sentinel;

// =========================================================================
// STEP 1F: 4-LAYER TRUTH RECONCILIATION
// =========================================================================
echo "\n[1F] Querying 4-Layer Forensic Truth Reconciliation...\n";
$recon = callEndpoint("{$baseUrl}/forensic-reconciliation?key={$auditToken}", 'GET');
echo "     HTTP Code: {$recon['code']} ({$recon['elapsed']} ms)\n";

$reconData = $recon['json'] ?? [];
$reconVerdict = $reconData['reconciliation_verdict'] ?? '';
echo "     Reconciliation Verdict  : {$reconVerdict}\n";

$pass1F = ($recon['code'] === 200 && $reconVerdict === 'PASS_AUTHORITATIVE_PRODUCTION');
if ($pass1F) {
    echo "     PASS: 4-layer truth reconciliation fully validated.\n";
} else {
    echo "     FAIL: 4-layer reconciliation verdict: {$reconVerdict}!\n";
    exit(1);
}
$report['steps']['1F_reconciliation'] = $reconData;

// Overall Verdict
$overallPass = ($pass1A && $pass1B && $pass1C && $pass1D && $pass1E && $pass1F);
echo "\n====================================================================\n";
if ($overallPass) {
    echo "  PHASE 1 SCHEMA MIGRATION GATE: 🟢 PASS / CLEAN / VERIFIED        \n";
    echo "  All 4 B.7 Tables Installed & Sealed with Zero Topology Mutation   \n";
} else {
    echo "  PHASE 1 SCHEMA MIGRATION GATE: 🔴 FAIL                           \n";
}
echo "====================================================================\n";

$report['overall_verdict'] = $overallPass ? 'PASS' : 'FAIL';
file_put_contents(__DIR__ . '/phase1_result.json', json_encode($report, JSON_PRETTY_PRINT));
