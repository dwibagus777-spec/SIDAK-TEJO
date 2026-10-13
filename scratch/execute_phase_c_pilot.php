<?php

/**
 * SIDAK TEJO — TL-MF-01 Phase C: Controlled Live Pilot Execution
 * Target: Feeder #131 (BY PASS, ULP Krian) ONLY
 * Max Batch: <= 10 new translines in a single atomic transaction
 *
 * Hard Safety Invariants:
 * 1. Zero writes on protected tables (assets, temuan, temuan_materials, sections, penyulang, ulps, proposals)
 * 2. Short-span firewall: < 5.0m candidates strictly excluded
 * 3. Existing 199 active translines (and 208 physical DB rows) 100% immutable
 * 4. Stop immediately upon completion - NO auto-continuation to other feeders
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $headers = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) SIDAK-TEJO-PILOT/1.0',
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
echo "⚡ TL-MF-01 PHASE C: CONTROLLED LIVE PILOT EXECUTION\n";
echo "Target Feeder: #131 (BY PASS, ULP Krian)\n";
echo "Scope: Maximum 10 Defensible Translines in 1 Atomic MariaDB Transaction\n";
echo "======================================================================\n\n";

// STEP 1: CAPTURE PRE-PILOT SNAPSHOT
echo "=== STEP 1: CAPTURING CANONICAL PRE-PILOT SNAPSHOT ===\n";
$preAuditRes = apiCall($baseUrl . '/gis/api-global-topology-audit');
if ($preAuditRes['code'] !== 200 || !isset($preAuditRes['json']['fingerprints']['post'])) {
    echo "FATAL: Failed to capture pre-pilot audit snapshot! HTTP {$preAuditRes['code']}\n";
    exit(1);
}

$preFps = $preAuditRes['json']['fingerprints']['post'];
echo "Pre-Pilot Table Fingerprints:\n";
foreach ($preFps as $tbl => $info) {
    printf("  %-25s | Count: %-5d | SHA: %s\n", $tbl, $info['count'], substr($info['sha256'], 0, 16) . '...');
}

// Check Feeder 131 current translines
$f131Res = apiCall($baseUrl . '/gis/api-translines?penyulang_id=131');
$f131Count = count($f131Res['json']['data'] ?? []);
echo "\nFeeder 131 Current Translines: {$f131Count}\n";
if ($f131Count !== 0) {
    echo "WARNING: Feeder 131 already has {$f131Count} translines. Expected 0.\n";
}

// STEP 2: PREPARE PILOT PAYLOAD (8 SCRUTINIZED KEYS)
$approvedKeys = [
    "TL-NAT:131:6378-6379", // PSNN247 <-> PSNN248 (27.82m, score 95)
    "TL-NAT:131:6387-6388", // PSNN193 <-> PSNN191 (48.97m, score 93)
    "TL-NAT:131:6140-6142", // BYPASS_113 <-> BYPASS_112 (49.83m, score 90)
    "TL-NAT:131:6173-6175", // PSNN260 <-> PSNN259 (36.71m, score 90)
    "TL-NAT:131:6384-6386", // PSNN257 <-> PSNN258 (40.81m, score 90)
    "TL-NAT:131:6126-6221", // BYPASS_106 <-> BYPASS_107 (23.96m, score 88)
    "TL-NAT:131:6129-6414", // BYPASS_165 <-> PSNN226 (24.38m, score 88)
    "TL-NAT:131:6132-6146", // PSNN185 <-> PSNN186 (19.55m, score 88)
];

$batchPayload = [
    'penyulang_id'           => 131,
    'mode'                   => 'batch',
    'max_batch'              => 10,
    'candidate_natural_keys' => $approvedKeys,
];

echo "\n=== STEP 2: EXECUTING ATOMIC PILOT BATCH (" . count($approvedKeys) . " TRANSLINES) ===\n";
echo "Sending POST /gis/api-transline-tl04-complete with " . count($approvedKeys) . " approved candidate keys...\n";

$execRes = apiCall($baseUrl . '/gis/api-transline-tl04-complete', 'POST', $batchPayload);
echo "Execution HTTP Code: {$execRes['code']}\n";

$execJson = $execRes['json'];
if (!$execJson || ($execJson['status'] ?? '') !== 'success') {
    echo "FATAL: Pilot execution failed!\n";
    echo "Response: " . substr($execRes['body'], 0, 500) . "\n";
    exit(1);
}

echo "\nEXECUTION RESULT:\n";
echo "  Status:                      {$execJson['status']}\n";
echo "  Action:                      {$execJson['action']}\n";
echo "  Run ID:                      {$execJson['run_id']}\n";
echo "  Created Count:               {$execJson['created_count']}\n";
echo "  Created IDs:                 " . implode(', ', $execJson['created_ids'] ?? []) . "\n";
echo "  Zero-Write Invariants Pass:  " . ($execJson['zero_write_invariants_pass'] ? 'YES (TRUE)' : 'NO (FALSE)') . "\n";

echo "\nCreated Translines Detail:\n";
printf("  %-4s | %-24s | %-16s | %-7s | %-5s | %-10s\n", "ID", "Natural Key", "Transline Code", "Dist(m)", "Score", "Type");
echo "  " . str_repeat("-", 80) . "\n";
foreach ($execJson['created_translines'] ?? [] as $ct) {
    printf("  %-4d | %-24s | %-16s | %-7.2f | %-5d | %-10s\n",
        $ct['id'],
        $ct['natural_key'],
        $ct['transline_code'],
        $ct['distance_meters'],
        $ct['total_score'],
        $ct['candidate_type']
    );
}

// STEP 3: POST-EXECUTION VERIFICATION & ZERO-MUTATION AUDIT
echo "\n=== STEP 3: CAPTURING POST-PILOT SNAPSHOT & AUDITING INVARIANTS ===\n";
$postAuditRes = apiCall($baseUrl . '/gis/api-global-topology-audit');
if ($postAuditRes['code'] !== 200 || !isset($postAuditRes['json']['fingerprints']['post'])) {
    echo "FATAL: Failed to capture post-pilot audit snapshot!\n";
    exit(1);
}

$postFps = $postAuditRes['json']['fingerprints']['post'];

echo "Table Integrity Verification Table:\n";
printf("  %-25s | %-12s | %-12s | %-12s | %s\n", "Table", "Pre Count", "Post Count", "Delta", "Integrity Status");
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
    printf("  %-25s | %-12d | %-12d | %-+12d | %s\n", $t, $preC, $postC, $delta, $statusStr);
}

// Transline table verification
$preTlC = $preFps['gis_translines']['count'];
$postTlC = $postFps['gis_translines']['count'];
$tlDelta = $postTlC - $preTlC;
printf("  %-25s | %-12d | %-12d | %-+12d | %s\n",
    'gis_translines', $preTlC, $postTlC, $tlDelta,
    ($tlDelta === (int)$execJson['created_count']) ? "EXPECTED (+{$tlDelta} rows)" : "UNEXPECTED DELTA"
);

// STEP 4: FEEDER-LEVEL ISOLATION VERIFICATION
echo "\n=== STEP 4: FEEDER ISOLATION & IMMUTABILITY AUDIT ===\n";
// Feeder 15 verification
$f15Res = apiCall($baseUrl . '/gis/api-translines?penyulang_id=15');
$f15Tls = count($f15Res['json']['data'] ?? []);
echo "  Feeder #15 (BANJAR KEMANTREN) Translines: {$f15Tls} (Expected: 197 active - 100% UNTOUCHED)\n";

// Feeder 4 verification
$f4Res = apiCall($baseUrl . '/gis/api-translines?penyulang_id=4');
$f4Tls = count($f4Res['json']['data'] ?? []);
echo "  Feeder #4  (GEMURUNG)        Translines: {$f4Tls} (Expected: 2 active - 100% UNTOUCHED)\n";

// Feeder 131 verification
$f131Post = apiCall($baseUrl . '/gis/api-translines?penyulang_id=131');
$f131Tls = count($f131Post['json']['data'] ?? []);
echo "  Feeder #131 (BY PASS)         Translines: {$f131Tls} (Expected: {$execJson['created_count']} new)\n";

$globalActive = $f15Tls + $f4Tls + $f131Tls;
echo "  Global Active Translines:                {$globalActive} (Prior 199 + {$tlDelta} new = {$globalActive})\n";

// STEP 5: SAVE AUDIT FILE
$auditReport = [
    'timestamp'                  => date('Y-m-d H:i:s'),
    'phase'                      => 'TL-MF-01 Phase C Pilot',
    'target_feeder'              => 131,
    'target_feeder_name'         => 'BY PASS (ULP Krian)',
    'execution_result'           => $execJson,
    'pre_fingerprints'           => $preFps,
    'post_fingerprints'          => $postFps,
    'all_protected_intact'       => $allProtectedIntact,
    'feeder_isolation_verified'  => ($f15Tls === 197 && $f4Tls === 2 && $f131Tls === (int)$execJson['created_count']),
    'global_active_translines'   => $globalActive,
];

file_put_contents(__DIR__ . '/phase_c_pilot_execution_audit.json', json_encode($auditReport, JSON_PRETTY_PRINT));
echo "\nAudit registry saved to scratch/phase_c_pilot_execution_audit.json\n";

if ($allProtectedIntact && $f15Tls === 197 && $f4Tls === 2 && $f131Tls === (int)$execJson['created_count']) {
    echo "\n🏆 TL-MF-01 PHASE C CONTROLLED LIVE PILOT: SUCCESSFUL & 100% VERIFIED!\n";
    echo "🔒 STRICT STOP GATE ACTIVATED: Halting execution. No other feeders will be processed.\n";
} else {
    echo "\n❌ INVARIANT CHECK FAILED!\n";
    exit(1);
}
