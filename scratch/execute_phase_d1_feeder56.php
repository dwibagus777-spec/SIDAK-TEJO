<?php

/**
 * SIDAK TEJO — TL-MF-02 Phase D1: Controlled Live Pilot Execution
 * Target: Feeder #56 (BANJARAN, ULP Krian) ONLY
 * Scope: Exactly ONE atomic batch of defensible translines (<= 10)
 *
 * Hard Safety Invariants:
 * 1. gis_translines: INSERT ONLY (Zero UPDATE, DELETE, TRUNCATE, ALTER, DROP)
 * 2. 7 Protected Tables: 100% READ ONLY (assets, temuan, temuan_materials, sections, penyulang, ulps, proposals)
 * 3. Short-Span Firewall: < 5.0m blocked; 5.0-10.0m review-required/held (candidate 5514-5517 held)
 * 4. Feeder Firewall: source & target strictly within penyulang_id == 56
 * 5. Existing active translines (F15=197, F4=2, F131=8) 100% immutable
 * 6. Hard Stop Gate: STOP immediately after single batch. NO auto-continuation.
 */

$cookieFile = __DIR__ . '/prod_cookie.txt';
$baseUrl = 'https://sidaktejo.site';
$resolveOpt = ['sidaktejo.site:443:91.108.119.5', 'sidaktejo.site:80:91.108.119.5'];

function apiCall($url, $method = 'GET', $data = null) {
    global $cookieFile, $resolveOpt;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RESOLVE, $resolveOpt);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_TIMEOUT, 90);

    $headers = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) SIDAK-TEJO-PHASE-D1/1.0',
        'X-Requested-With: XMLHttpRequest',
    ];

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data !== null) {
            $payload = is_array($data) ? json_encode($data) : $data;
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    return [
        'code' => $httpCode,
        'body' => $response,
        'json' => json_decode($response, true),
        'error' => $err,
    ];
}

echo "======================================================================\n";
echo "⚡ SIDAK TEJO — TL-MF-02 PHASE D1: CONTROLLED LIVE PILOT EXECUTION\n";
echo "Target Feeder: #56 (BANJARAN, ULP Krian)\n";
echo "Scope: Strictly ONE Live Atomic Batch (<= 10 Defensible Translines)\n";
echo "Stop Gate: IMMEDIATE HALT after completion. NO auto-continuation.\n";
echo "======================================================================\n\n";

// STEP 1: CAPTURE PRE-EXECUTION CANONICAL CRYPTOGRAPHIC FINGERPRINT
echo "=== STEP 1: CAPTURING PRE-EXECUTION CRYPTOGRAPHIC FINGERPRINT ===\n";
$preAuditRes = apiCall($baseUrl . '/gis/api-global-topology-audit');
if ($preAuditRes['code'] !== 200 || !isset($preAuditRes['json']['fingerprints']['post'])) {
    echo "FATAL: Failed to capture pre-execution audit snapshot! HTTP {$preAuditRes['code']}\n";
    exit(1);
}

$preFps = $preAuditRes['json']['fingerprints']['post'];
echo "Pre-Execution Table Fingerprints (8 Tables):\n";
foreach ($preFps as $tbl => $info) {
    printf("  %-25s | Count: %-5d | SHA: %s\n", $tbl, $info['count'], $info['sha256']);
}

// Check Feeder 56 current translines
$f56Res = apiCall($baseUrl . '/gis/api-translines?penyulang_id=56');
$f56CurrentCount = count($f56Res['json']['data'] ?? []);
echo "\nFeeder #56 Current Translines: {$f56CurrentCount} (Expected: 0)\n";
if ($f56CurrentCount !== 0) {
    echo "FATAL: Feeder 56 already has {$f56CurrentCount} translines! Aborting.\n";
    exit(1);
}

// STEP 2: FORMULATE DEFENSIVE BATCH (9 APPROVED CANDIDATES)
// Note: Candidate 5514-5517 (6.13m, delta 3) is HELD FOR REVIEW per span firewall rule
$approvedKeys = [
    "TL-NAT:56:5512-5513", // AST-KRN-BANJARAN-JTM-005 -> 004 (52.59m, score 93)
    "TL-NAT:56:5513-5514", // AST-KRN-BANJARAN-JTM-006 -> 005 (37.07m, score 93)
    "TL-NAT:56:5903-5904", // AST-KRN-BANJARAN-JTM-396 -> 395 (52.98m, score 93)
    "TL-NAT:56:5611-5613", // AST-KRN-BANJARAN-JTM-105 -> 103 (13.41m, score 92)
    "TL-NAT:56:5541-5543", // AST-KRN-BANJARAN-JTM-035 -> 033 (54.32m, score 90)
    "TL-NAT:56:5509-5744", // AST-KRN-BANJARAN-JTM-236 -> 001 (16.09m, score 88)
    "TL-NAT:56:5511-5516", // AST-KRN-BANJARAN-JTM-008 -> 003 (27.96m, score 88)
    "TL-NAT:56:5528-5717", // AST-KRN-BANJARAN-JTM-209 -> 020 (24.27m, score 88)
    "TL-NAT:56:5529-5610", // AST-KRN-BANJARAN-JTM-102 -> 021 (21.29m, score 88)
];

$heldKeys = [
    [
        'natural_key' => "TL-NAT:56:5514-5517",
        'source'      => "AST-KRN-BANJARAN-JTM-009",
        'target'      => "AST-KRN-BANJARAN-JTM-006",
        'distance_m'  => 6.13,
        'seq_delta'   => 3,
        'reason'      => "SPAN_5_TO_10M_DELTA_EXCEEDS_2 (requires seq_delta <= 2, actual delta = 3)",
    ]
];

echo "\nBatch Formulation:\n";
echo "  Total Analyzed Preview Candidates: 10\n";
echo "  Approved Defensible Keys:          " . count($approvedKeys) . "\n";
echo "  Held for Operator Review:          " . count($heldKeys) . " (" . $heldKeys[0]['natural_key'] . " - " . $heldKeys[0]['reason'] . ")\n";

$batchPayload = [
    'penyulang_id'           => 56,
    'mode'                   => 'batch',
    'max_batch'              => 10,
    'candidate_natural_keys' => $approvedKeys,
];

// STEP 3: EXECUTE SINGLE ATOMIC LIVE BATCH
echo "\n=== STEP 3: EXECUTING ATOMIC LIVE BATCH ON FEEDER #56 ===\n";
echo "Sending POST /gis/api-transline-tl04-complete with " . count($approvedKeys) . " approved keys...\n";

$execRes = apiCall($baseUrl . '/gis/api-transline-tl04-complete', 'POST', $batchPayload);
echo "Execution HTTP Code: {$execRes['code']}\n";

$execJson = $execRes['json'];
if (!$execJson || ($execJson['status'] ?? '') !== 'success') {
    echo "FATAL: Live batch execution failed!\n";
    echo "Response: " . substr($execRes['body'], 0, 1000) . "\n";
    exit(1);
}

echo "\nEXECUTION RESULT:\n";
echo "  Status:                     {$execJson['status']}\n";
echo "  Action:                     {$execJson['action']}\n";
echo "  Run ID:                     {$execJson['run_id']}\n";
echo "  Created Count:              {$execJson['created_count']}\n";
echo "  Created IDs:                " . implode(', ', $execJson['created_ids'] ?? []) . "\n";
echo "  Zero-Write Invariants Pass: " . (!empty($execJson['zero_write_invariants_pass']) ? 'YES (TRUE)' : 'NO (FALSE)') . "\n";

echo "\nCreated Translines Detail:\n";
printf("  %-6s | %-22s | %-24s | %-7s | %-5s | %-12s\n", "PK ID", "Natural Key", "Transline Code", "Dist(m)", "Score", "Type");
echo "  " . str_repeat("-", 88) . "\n";
foreach ($execJson['created_translines'] ?? [] as $ct) {
    printf("  %-6d | %-22s | %-24s | %-7.2f | %-5d | %-12s\n",
        $ct['id'],
        $ct['natural_key'],
        $ct['transline_code'],
        $ct['distance_meters'],
        $ct['total_score'],
        $ct['candidate_type']
    );
}

// STEP 4: CAPTURE POST-EXECUTION FINGERPRINT & AUDIT INVARIANTS
echo "\n=== STEP 4: CAPTURING POST-EXECUTION FINGERPRINT & AUDITING INVARIANTS ===\n";
$postAuditRes = apiCall($baseUrl . '/gis/api-global-topology-audit');
if ($postAuditRes['code'] !== 200 || !isset($postAuditRes['json']['fingerprints']['post'])) {
    echo "FATAL: Failed to capture post-execution audit snapshot!\n";
    exit(1);
}

$postFps = $postAuditRes['json']['fingerprints']['post'];

echo "\nTable Integrity Verification Table (8 Tables):\n";
printf("  %-25s | %-10s | %-10s | %-8s | %s\n", "Table", "Pre Count", "Post Count", "Delta", "Integrity Status");
echo "  " . str_repeat("-", 85) . "\n";

$allProtectedIntact = true;
$protectedTables = ['assets', 'gis_transline_proposals', 'temuan', 'temuan_materials', 'sections', 'penyulang', 'ulps'];

foreach ($protectedTables as $t) {
    $preC = $preFps[$t]['count'];
    $postC = $postFps[$t]['count'];
    $preSha = $preFps[$t]['sha256'];
    $postSha = $postFps[$t]['sha256'];
    $delta = $postC - $preC;
    $intact = ($delta === 0 && $preSha === $postSha);
    
    if (!$intact) $allProtectedIntact = false;
    
    $statusStr = $intact ? "IMMUTABLE (0 writes, SHA identical)" : "VIOLATION (MUTATED)";
    printf("  %-25s | %-10d | %-10d | %-+8d | %s\n", $t, $preC, $postC, $delta, $statusStr);
}

// Transline table verification
$preTlC = $preFps['gis_translines']['count'];
$postTlC = $postFps['gis_translines']['count'];
$tlDelta = $postTlC - $preTlC;
$expectedDelta = (int)$execJson['created_count'];
$tlOk = ($tlDelta === $expectedDelta);
printf("  %-25s | %-10d | %-10d | %-+8d | %s\n",
    'gis_translines', $preTlC, $postTlC, $tlDelta,
    $tlOk ? "EXPECTED (+{$tlDelta} rows INSERT-only)" : "VIOLATION (UNEXPECTED DELTA)"
);

// STEP 5: FEEDER-LEVEL ISOLATION VERIFICATION
echo "\n=== STEP 5: MULTI-FEEDER ISOLATION AUDIT ===\n";
$f15Active = 0; $f4Active = 0; $f131Active = 0; $f56Active = 0;
foreach ($postAuditRes['json']['feeder_matrix'] ?? [] as $fm) {
    if ($fm['penyulang_id'] == 15) $f15Active = $fm['existing_translines'];
    if ($fm['penyulang_id'] == 4)  $f4Active = $fm['existing_translines'];
    if ($fm['penyulang_id'] == 131) $f131Active = $fm['existing_translines'];
    if ($fm['penyulang_id'] == 56)  $f56Active = $fm['existing_translines'];
}

printf("  Feeder #15  (SUMPUT)   : %d active translines (Target: 197) -> %s\n", $f15Active, $f15Active === 197 ? 'PRESERVED' : 'MUTATED');
printf("  Feeder #4   (SEPANJANG): %d active translines (Target: 2)   -> %s\n", $f4Active, $f4Active === 2 ? 'PRESERVED' : 'MUTATED');
printf("  Feeder #131 (BY PASS)  : %d active translines (Target: 8)   -> %s\n", $f131Active, $f131Active === 8 ? 'PRESERVED' : 'MUTATED');
printf("  Feeder #56  (BANJARAN) : %d active translines (Target: %d)   -> %s\n", $f56Active, $expectedDelta, $f56Active === $expectedDelta ? 'VERIFIED' : 'MISMATCH');

// STEP 6: SAVE AUDIT ARTIFACT
$auditReport = [
    'timestamp'                 => date('c'),
    'phase'                     => 'TL-MF-02 PHASE D1 CONTROLLED LIVE PILOT',
    'feeder'                    => [
        'penyulang_id'   => 56,
        'kode_penyulang' => '5130-021',
        'nama_penyulang' => 'BANJARAN',
        'ulp_id'         => 2,
        'nama_ulp'       => 'ULP Krian',
    ],
    'run_id'                    => $execJson['run_id'],
    'execution_result'          => $execJson,
    'held_keys'                 => $heldKeys,
    'pre_fingerprints'          => $preFps,
    'post_fingerprints'         => $postFps,
    'table_integrity_pass'      => $allProtectedIntact && $tlOk,
    'feeder_isolation_pass'     => ($f15Active === 197 && $f4Active === 2 && $f131Active === 8 && $f56Active === $expectedDelta),
    'stop_gate_enforced'        => true,
];

file_put_contents(__DIR__ . '/phase_d1_feeder56_execution_audit.json', json_encode($auditReport, JSON_PRETTY_PRINT));
echo "\nSaved full pilot execution audit to scratch/phase_d1_feeder56_execution_audit.json\n";

echo "\n======================================================================\n";
echo "🛑 HARD STOP GATE ENFORCED:\n";
echo "TL-MF-02 Phase D1 pilot execution complete for Feeder #56.\n";
echo "Exactly {$expectedDelta} defensible translines created.\n";
echo "Engine has HALTED. No continuation to Batch 2 or subsequent feeders.\n";
echo "======================================================================\n";
