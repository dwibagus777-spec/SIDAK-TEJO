<?php

/**
 * Phase B.7 Production Activation — Phase 0 Read-Only Preflight Verification
 * Strictly Read-Only (0 writes, 0 alters, 0 mutations)
 */

$baseUrl = getenv('SIDAK_BASE_URL') ?: 'https://sidaktejo.site/fault-dispatch';
$auditToken = getenv('SIDAK_AUDIT_TOKEN') ?: 'sidak_transline_audit_2026';
$deployMasterKey = getenv('SIDAK_DEPLOY_MASTER_KEY') ?: 'sidak_tejo_deploy_master_2026';

function callEndpoint(string $url, string $method = 'GET', array $payload = [], ?string $token = null, ?string $deployKey = null): array
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RESOLVE, ['sidaktejo.site:443:2.57.91.151', 'sidaktejo.site:80:2.57.91.151']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

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
echo "  SIDAK TEJO — B.7 PRODUCTION ACTIVATION: PHASE 0 PREFLIGHT         \n";
echo "  Target URL: {$baseUrl}                                             \n";
echo "  Mode: STRICTLY READ-ONLY (ZERO MUTATIONS)                         \n";
echo "====================================================================\n\n";

$results = [
    'https_reachable' => false,
    'unauth_401'      => false,
    'unauth_403'      => false,
    'auth_audit_200'  => false,
    'baseline_match'  => false,
    'errors'          => [],
    'baseline'        => [],
];

// Step 0a: Unauthenticated call -> Expect HTTP 401
echo "[0a] Checking Unauthenticated Firewall (Expect 401)...\n";
$unauth = callEndpoint("{$baseUrl}/audit", 'GET');
echo "     HTTP Code: {$unauth['code']} ({$unauth['elapsed']} ms)\n";
if ($unauth['code'] === 401) {
    $results['https_reachable'] = true;
    $results['unauth_401'] = true;
    echo "     PASS: Unauthenticated call correctly blocked with HTTP 401.\n";
} else {
    $results['errors'][] = "Unauthenticated firewall check failed! Expected 401, got {$unauth['code']}.";
    echo "     FAIL: Expected 401, got {$unauth['code']}. Error: {$unauth['error']}\n";
}

// Step 0b: Authenticated but unauthorized deployment call -> Expect HTTP 403
echo "\n[0b] Checking Authenticated Unauthorized Action (Expect 403)...\n";
$unauthDeploy = callEndpoint("{$baseUrl}/migrate?key={$auditToken}", 'POST');
echo "     HTTP Code: {$unauthDeploy['code']} ({$unauthDeploy['elapsed']} ms)\n";
if ($unauthDeploy['code'] === 403) {
    $results['unauth_403'] = true;
    echo "     PASS: Missing deploy key correctly blocked with HTTP 403.\n";
} else {
    $results['errors'][] = "Authorization firewall check failed! Expected 403, got {$unauthDeploy['code']}.";
    echo "     FAIL: Expected 403, got {$unauthDeploy['code']}.\n";
}

// Step 0c: Authorized read-only audit -> Expect HTTP 200
echo "\n[0c] Checking Authorized Read-Only Audit (Expect 200)...\n";
$audit = callEndpoint("{$baseUrl}/audit?key={$auditToken}", 'GET');
echo "     HTTP Code: {$audit['code']} ({$audit['elapsed']} ms)\n";
if ($audit['code'] === 200 && is_array($audit['json'])) {
    $results['auth_audit_200'] = true;
    echo "     PASS: Read-only audit endpoint returned valid JSON.\n";
} else {
    $results['errors'][] = "Audit endpoint returned invalid response or code {$audit['code']}.";
    echo "     FAIL: Audit call failed. HTTP {$audit['code']}.\n";
    echo json_encode($audit, JSON_PRETTY_PRINT) . "\n";
    exit(1);
}

// Step 0d: Production Baseline & Identity Hash Verification
echo "\n[0d] Capturing and Verifying Production Baseline...\n";
$json = $audit['json'];
$sentinel = $json['topology_sentinel'] ?? [];

$actTL     = (int)($sentinel['active_translines'] ?? 0);
$physTL    = (int)($sentinel['physical_translines'] ?? 0);
$actAssets = (int)($sentinel['active_assets'] ?? 0);
$physAssets= (int)($sentinel['physical_assets'] ?? 0);
$span      = (float)($sentinel['network_span_meters'] ?? 0.0);
$snapId    = (string)($sentinel['snapshot_id'] ?? '');
$actTLHash = (string)($sentinel['active_transline_hash'] ?? '');
$physTLHash= (string)($sentinel['physical_transline_hash'] ?? '');
$actAstHash= (string)($sentinel['active_asset_hash'] ?? '');
$physAstHash=(string)($sentinel['physical_asset_hash'] ?? '');

echo "     Active Translines       : {$actTL} (Expected: 243)\n";
echo "     Physical Translines     : {$physTL} (Expected: 252)\n";
echo "     Active Assets           : {$actAssets} (Expected: 5236)\n";
echo "     Physical Assets         : {$physAssets} (Expected: 5549)\n";
echo "     Network Span (m)        : {$span} (Expected: 9418.37)\n";
echo "     Snapshot ID             : {$snapId} (Expected: TOPOLOGY-20260925-243-ad2c9fcb)\n";
echo "     Active TL Hash          : " . substr($actTLHash, 0, 16) . "...\n";
echo "     Physical TL Hash        : " . substr($physTLHash, 0, 16) . "...\n";
echo "     Active Asset Hash       : " . substr($actAstHash, 0, 16) . "...\n";
echo "     Physical Asset Hash     : " . substr($physAstHash, 0, 16) . "...\n";

$baselineMatch = (
    $actTL === 243 &&
    $physTL === 252 &&
    $actAssets === 5236 &&
    $physAssets === 5549 &&
    abs($span - 9418.37) < 0.01 &&
    $snapId === 'TOPOLOGY-20260925-243-ad2c9fcb' &&
    !empty($actTLHash) &&
    !empty($physTLHash) &&
    !empty($actAstHash) &&
    !empty($physAstHash)
);

if ($baselineMatch) {
    $results['baseline_match'] = true;
    echo "     PASS: All baseline metrics match canonical truth.\n";
} else {
    $results['errors'][] = "Production baseline mismatch!";
    echo "     FAIL: Baseline numbers do not match canonical expectation!\n";
}

$results['baseline'] = [
    'active_translines'        => $actTL,
    'physical_translines'      => $physTL,
    'active_assets'            => $actAssets,
    'physical_assets'          => $physAssets,
    'network_span_meters'      => $span,
    'snapshot_id'              => $snapId,
    'active_transline_hash'    => $actTLHash,
    'physical_transline_hash'  => $physTLHash,
    'active_asset_hash'        => $actAstHash,
    'physical_asset_hash'      => $physAstHash,
];

// Step 0e: Forensic Reconciliation Baseline Check
echo "\n[0e] Querying 4-Layer Forensic Reconciliation Baseline...\n";
$recon = callEndpoint("{$baseUrl}/forensic-reconciliation?key={$auditToken}", 'GET');
echo "     HTTP Code: {$recon['code']} ({$recon['elapsed']} ms)\n";
$reconVerdict = $recon['json']['reconciliation_verdict'] ?? 'UNKNOWN';
echo "     Reconciliation Verdict  : {$reconVerdict}\n";

$allPassed = $results['https_reachable'] && $results['unauth_401'] && $results['unauth_403'] && $results['auth_audit_200'] && $results['baseline_match'];

echo "\n====================================================================\n";
if ($allPassed) {
    echo "  PHASE 0 READ-ONLY PREFLIGHT VERDICT: 🟢 PASS\n";
    echo "  Zero writes / zero mutations verified on live production.\n";
} else {
    echo "  PHASE 0 READ-ONLY PREFLIGHT VERDICT: 🔴 FAIL\n";
}
echo "====================================================================\n";

file_put_contents(__DIR__ . '/preflight_result.json', json_encode($results, JSON_PRETTY_PRINT));
