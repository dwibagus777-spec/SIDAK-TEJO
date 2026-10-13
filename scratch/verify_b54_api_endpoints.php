<?php

$baseUrl = 'http://127.0.0.1:8085';
$auditKey = 'sidak_transline_audit_2026';

function httpReq(string $url, string $method = 'GET', ?array $data = null, array $headers = []): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $defaultHeaders = ['Accept: application/json'];
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data !== null) {
            $jsonPayload = json_encode($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
            $defaultHeaders[] = 'Content-Type: application/json';
        }
    } elseif ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    }

    $allHeaders = array_merge($defaultHeaders, $headers);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);

    $startTime = microtime(true);
    $response = curl_exec($ch);
    $elapsedMs = round((microtime(true) - $startTime) * 1000, 2);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    return [
        'code'    => $httpCode,
        'elapsed' => $elapsedMs,
        'body'    => (string)$response,
        'json'    => json_decode((string)$response, true),
        'error'   => $err,
    ];
}

require __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';
$db = \Config\Database::connect('default');

echo "====================================================================\n";
echo "  SIDAK TEJO — PHASE B.5.4 EXTERNAL HTTP API BOUNDARY VERIFICATION   \n";
echo "====================================================================\n\n";

// Pre-test Authoritative Baseline Counts
$tlBefore = $db->tableExists('gis_translines') ? $db->table('gis_translines')->countAllResults() : 0;
$assetBefore = $db->tableExists('assets') ? $db->table('assets')->countAllResults() : 0;
echo "Authoritative Physical Baseline:\n";
echo " - gis_translines: {$tlBefore}\n";
echo " - assets:         {$assetBefore}\n\n";

$authHeader = ["X-Audit-Token: {$auditKey}"];
$checks = [];

// =============================================================================
// Test 1: Authentication Boundary (Unauthorized 401)
// =============================================================================
echo "[1] Testing Authentication Boundary (Expect HTTP 401)...\n";
$resAuth = httpReq("{$baseUrl}/fault-ingestion/audit");
$t1_pass = ($resAuth['code'] === 401 && (($resAuth['json']['reason'] ?? '') === 'UNAUTHORIZED'));
echo " - HTTP Status: {$resAuth['code']} | Reason: " . ($resAuth['json']['reason'] ?? 'NONE') . "\n";
echo " - Verdict: " . ($t1_pass ? "PASS ✅" : "FAIL ❌") . "\n\n";
$checks['AUTH_401'] = $t1_pass;

// =============================================================================
// Test 2: Authorized Audit Scorecard (HTTP 200)
// =============================================================================
echo "[2] Testing Authorized System Audit (Expect HTTP 200)...\n";
$resAudit = httpReq("{$baseUrl}/fault-ingestion/audit?key={$auditKey}", 'GET', null, $authHeader);
$t2_pass = ($resAudit['code'] === 200 &&
            ($resAudit['json']['status'] ?? '') === 'PHASE_B5_FAULT_INGESTION_VERIFIED' &&
            !empty($resAudit['json']['all_guards_passed']));
echo " - HTTP Status: {$resAudit['code']} ({$resAudit['elapsed']} ms)\n";
echo " - Status: " . ($resAudit['json']['status'] ?? 'NONE') . "\n";
echo " - Ingestion Version: " . ($resAudit['json']['ingestion_version'] ?? 'NONE') . "\n";
echo " - Snapshot ID: " . ($resAudit['json']['topology_snapshot_id'] ?? 'NONE') . "\n";
echo " - All Guards Passed: " . (!empty($resAudit['json']['all_guards_passed']) ? "TRUE" : "FALSE") . "\n";
echo " - Verdict: " . ($t2_pass ? "PASS ✅" : "FAIL ❌") . "\n\n";
$checks['AUDIT_200'] = $t2_pass;

// =============================================================================
// Test 3: Validation Boundary on Missing Provenance (Expect HTTP 400)
// =============================================================================
echo "[3] Testing Validation Boundary on Missing Provenance (Expect HTTP 400)...\n";
$invalidPayload = [
    'source_type' => 'SCADA',
    'penyulang_id'=> 118,
    'event_time'  => '2026-09-25 15:00:00',
    // missing source_reference!
];
$resVal = httpReq("{$baseUrl}/fault-ingestion/events?key={$auditKey}", 'POST', $invalidPayload, $authHeader);
$t3_pass = ($resVal['code'] === 400 &&
            ($resVal['json']['status'] ?? '') === 'REJECTED_INVALID_PROVENANCE' &&
            ($resVal['json']['field'] ?? '') === 'source_reference');
echo " - HTTP Status: {$resVal['code']} | Status: " . ($resVal['json']['status'] ?? 'NONE') . "\n";
echo " - Rejected Field: " . ($resVal['json']['field'] ?? 'NONE') . "\n";
echo " - Message: " . ($resVal['json']['message'] ?? 'NONE') . "\n";
echo " - Verdict: " . ($t3_pass ? "PASS ✅" : "FAIL ❌") . "\n\n";
$checks['PROVENANCE_400'] = $t3_pass;

// =============================================================================
// Test 4: Single Event Ingestion (Expect HTTP 201 Created)
// =============================================================================
echo "[4] Testing Single Event Ingestion (Expect HTTP 201 Created)...\n";
$testRef = 'HTTP-API-TEST-' . bin2hex(random_bytes(4));
$validPayload = [
    'source_type'      => 'PMCB_RELAY',
    'source_reference' => $testRef,
    'penyulang_id'     => 118,
    'event_time'       => '2026-09-25 15:30:00',
    'fault_phase'      => 'RN',
    'fault_current_a'  => 650.50,
    'relay_distance_m' => 45.0,
];
$resIngest = httpReq("{$baseUrl}/fault-ingestion/events?key={$auditKey}", 'POST', $validPayload, $authHeader);
$eventId = (int)($resIngest['json']['event_id'] ?? 0);
$fingerprint = $resIngest['json']['event_fingerprint'] ?? '';
$t4_pass = ($resIngest['code'] === 201 &&
            ($resIngest['json']['success'] ?? false) === true &&
            ($resIngest['json']['is_duplicate'] ?? true) === false &&
            ($resIngest['json']['lifecycle_status'] ?? '') === 'INGESTED' &&
            $eventId > 0 &&
            strlen($fingerprint) === 64);
echo " - HTTP Status: {$resIngest['code']} | Event ID: #{$eventId} | Number: " . ($resIngest['json']['event_number'] ?? 'NONE') . "\n";
echo " - Fingerprint: {$fingerprint}\n";
echo " - Lifecycle Status: " . ($resIngest['json']['lifecycle_status'] ?? 'NONE') . "\n";
echo " - Verdict: " . ($t4_pass ? "PASS ✅" : "FAIL ❌") . "\n\n";
$checks['INGEST_201'] = $t4_pass;

// =============================================================================
// Test 5: Idempotent Replay Boundary (Expect HTTP 200 OK + Duplicate Flag)
// =============================================================================
echo "[5] Testing Idempotent Replay Boundary (Expect HTTP 200 OK + is_duplicate: true)...\n";
$resReplay = httpReq("{$baseUrl}/fault-ingestion/events?key={$auditKey}", 'POST', $validPayload, $authHeader);
$t5_pass = ($resReplay['code'] === 200 &&
            ($resReplay['json']['success'] ?? false) === true &&
            ($resReplay['json']['is_duplicate'] ?? false) === true &&
            ($resReplay['json']['status'] ?? '') === 'IDEMPOTENT_DUPLICATE' &&
            (int)($resReplay['json']['event_id'] ?? 0) === $eventId);
echo " - HTTP Status: {$resReplay['code']} | is_duplicate: " . (!empty($resReplay['json']['is_duplicate']) ? "TRUE" : "FALSE") . "\n";
echo " - Returned Existing Event ID: #" . ($resReplay['json']['event_id'] ?? 'NONE') . "\n";
echo " - Message: " . ($resReplay['json']['message'] ?? 'NONE') . "\n";
echo " - Verdict: " . ($t5_pass ? "PASS ✅" : "FAIL ❌") . "\n\n";
$checks['REPLAY_200_IDEMPOTENT'] = $t5_pass;

// =============================================================================
// Test 6: Lifecycle Transition - Invalid Shortcut (Expect HTTP 422)
// =============================================================================
echo "[6] Testing Lifecycle Transition - Invalid Shortcut (Expect HTTP 422)...\n";
$invalidTransPayload = ['target_status' => 'CLOSED']; // INGESTED -> CLOSED not allowed
$resInvTrans = httpReq("{$baseUrl}/fault-ingestion/{$eventId}/transition?key={$auditKey}", 'POST', $invalidTransPayload, $authHeader);
$t6_pass = ($resInvTrans['code'] === 422 &&
            ($resInvTrans['json']['status'] ?? '') === 'REJECTED_INVALID_TRANSITION');
echo " - HTTP Status: {$resInvTrans['code']} | Status: " . ($resInvTrans['json']['status'] ?? 'NONE') . "\n";
echo " - Message: " . ($resInvTrans['json']['message'] ?? 'NONE') . "\n";
echo " - Verdict: " . ($t6_pass ? "PASS ✅ (Properly Rejected)" : "FAIL ❌") . "\n\n";
$checks['LIFECYCLE_422_REJECT'] = $t6_pass;

// =============================================================================
// Test 7: Lifecycle Transition - Valid Progression (Expect HTTP 200)
// =============================================================================
echo "[7] Testing Lifecycle Transition - Valid Progression (Expect HTTP 200)...\n";
$validTransPayload = ['target_status' => 'ANALYZING'];
$resValTrans = httpReq("{$baseUrl}/fault-ingestion/{$eventId}/transition?key={$auditKey}", 'POST', $validTransPayload, $authHeader);
$t7_pass = ($resValTrans['code'] === 200 &&
            ($resValTrans['json']['success'] ?? false) === true &&
            ($resValTrans['json']['new_status'] ?? '') === 'ANALYZING');
echo " - HTTP Status: {$resValTrans['code']} | Previous: " . ($resValTrans['json']['previous_status'] ?? 'NONE') . " -> New: " . ($resValTrans['json']['new_status'] ?? 'NONE') . "\n";
echo " - Verdict: " . ($t7_pass ? "PASS ✅" : "FAIL ❌") . "\n\n";
$checks['LIFECYCLE_200_TRANSITION'] = $t7_pass;

// =============================================================================
// Test 8: Append-Only Telemetry Revision (Expect HTTP 200)
// =============================================================================
echo "[8] Testing Append-Only Revision (POST /fault-ingestion/{$eventId}/amend)...\n";
$amendPayload = [
    'changes'          => ['fault_current_a' => 675.25, 'trip_sequence' => 'TRIP_1'],
    'amendment_reason' => 'Koreksi arus gangguan dari event recorder relay',
    'amended_by'       => 'DISPATCHER_HERU',
];
$resAmend = httpReq("{$baseUrl}/fault-ingestion/{$eventId}/amend?key={$auditKey}", 'POST', $amendPayload, $authHeader);
$t8_pass = ($resAmend['code'] === 200 &&
            ($resAmend['json']['success'] ?? false) === true &&
            ($resAmend['json']['revision_no'] ?? 0) === 1);
echo " - HTTP Status: {$resAmend['code']} | Revision No: #" . ($resAmend['json']['revision_no'] ?? 'NONE') . "\n";
echo " - Message: " . ($resAmend['json']['message'] ?? 'NONE') . "\n";
echo " - Verdict: " . ($t8_pass ? "PASS ✅" : "FAIL ❌") . "\n\n";
$checks['AMEND_200_REVISION'] = $t8_pass;

// =============================================================================
// Test 9: Query Revisions History (GET /fault-ingestion/{$eventId}/revisions)
// =============================================================================
echo "[9] Querying Revisions History (GET /fault-ingestion/{$eventId}/revisions)...\n";
$resRevList = httpReq("{$baseUrl}/fault-ingestion/{$eventId}/revisions?key={$auditKey}", 'GET', null, $authHeader);
$t9_pass = ($resRevList['code'] === 200 &&
            (($resRevList['json']['count'] ?? 0) >= 1) &&
            !empty($resRevList['json']['revisions']));
echo " - HTTP Status: {$resRevList['code']} | Count: " . ($resRevList['json']['count'] ?? 0) . "\n";
echo " - Verdict: " . ($t9_pass ? "PASS ✅" : "FAIL ❌") . "\n\n";
$checks['REVISIONS_GET_200'] = $t9_pass;

// =============================================================================
// Test 10: Batch Ingestion & Idempotency (POST /fault-ingestion/batch)
// =============================================================================
echo "[10] Testing Batch Ingestion & Replay Idempotency...\n";
$tag = bin2hex(random_bytes(3));
$batchPayload = [
    'source_type' => 'SCADA',
    'records'     => [
        [
            'source_reference' => "HTTP-BATCH-1-{$tag}",
            'penyulang_id'     => 118,
            'event_time'       => '2026-09-25 16:00:00',
            'fault_phase'      => 'RN',
            'fault_current_a'  => 710.0,
        ],
        [
            'source_reference' => "HTTP-BATCH-2-{$tag}",
            'penyulang_id'     => 118,
            'event_time'       => '2026-09-25 16:05:00',
            'fault_phase'      => 'ST',
            'fault_current_a'  => 850.0,
        ],
    ],
];

// Run 1: First batch
$resBatch1 = httpReq("{$baseUrl}/fault-ingestion/batch?key={$auditKey}", 'POST', $batchPayload, $authHeader);
$b1_ok = ($resBatch1['code'] === 200 &&
          ($resBatch1['json']['total_records'] ?? 0) === 2 &&
          ($resBatch1['json']['accepted_records'] ?? 0) === 2 &&
          ($resBatch1['json']['duplicate_records'] ?? 0) === 0);
echo " - Run 1: Total = " . ($resBatch1['json']['total_records'] ?? 0) .
     " | Accepted = " . ($resBatch1['json']['accepted_records'] ?? 0) .
     " | Duplicates = " . ($resBatch1['json']['duplicate_records'] ?? 0) . " -> " .
     ($b1_ok ? "PASS ✅" : "FAIL ❌") . "\n";

// Run 2: Replay exact same batch
$resBatch2 = httpReq("{$baseUrl}/fault-ingestion/batch?key={$auditKey}", 'POST', $batchPayload, $authHeader);
$b2_ok = ($resBatch2['code'] === 200 &&
          ($resBatch2['json']['total_records'] ?? 0) === 2 &&
          ($resBatch2['json']['accepted_records'] ?? 0) === 0 &&
          ($resBatch2['json']['duplicate_records'] ?? 0) === 2);
echo " - Run 2 (Replay): Total = " . ($resBatch2['json']['total_records'] ?? 0) .
     " | Accepted = " . ($resBatch2['json']['accepted_records'] ?? 0) .
     " | Duplicates = " . ($resBatch2['json']['duplicate_records'] ?? 0) . " -> " .
     ($b2_ok ? "PASS ✅" : "FAIL ❌") . "\n";

$checks['BATCH_IDEMPOTENCY'] = $b1_ok && $b2_ok;

// =============================================================================
// Test 11: Authoritative Topology Zero Mutation Invariant
// =============================================================================
echo "\n[11] Verifying Authoritative Physical Topology Invariant...\n";
$tlAfter = $db->tableExists('gis_translines') ? $db->table('gis_translines')->countAllResults() : 0;
$assetAfter = $db->tableExists('assets') ? $db->table('assets')->countAllResults() : 0;
$zeroMutation = ($tlBefore === $tlAfter && $assetBefore === $assetAfter);

echo " - gis_translines: {$tlBefore} -> {$tlAfter} (Delta: " . ($tlAfter - $tlBefore) . ")\n";
echo " - assets:         {$assetBefore} -> {$assetAfter} (Delta: " . ($assetAfter - $assetBefore) . ")\n";
echo " - Status: " . ($zeroMutation ? "MUTATION = 0 (PASS ✅)" : "MUTATION DETECTED ❌") . "\n\n";
$checks['ZERO_MUTATION'] = $zeroMutation;

// =============================================================================
// SUMMARY SCORECARD
// =============================================================================
echo "====================================================================\n";
echo "  PHASE B.5.4 API BOUNDARY SCORECARD                                 \n";
echo "====================================================================\n";
$allPass = true;
foreach ($checks as $name => $passed) {
    if (!$passed) $allPass = false;
    echo sprintf(" %-25s : %s\n", $name, $passed ? "PASS ✅" : "FAIL ❌");
}
echo "--------------------------------------------------------------------\n";
echo "GATE B.5.4 VERDICT: " . ($allPass ? "PASS 🟢" : "FAIL 🔴") . "\n";
echo "====================================================================\n";
