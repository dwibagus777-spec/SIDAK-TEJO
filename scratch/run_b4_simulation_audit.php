<?php

$baseUrl = 'https://sidaktejo.site/fault-intelligence';
$cookieFile = __DIR__ . '/prod_cookie.txt';
$key = 'sidak_transline_audit_2026';

function callEndpoint(string $url, string $cookieFile, string $method = 'GET', array $postData = []): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
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
echo "  SIDAK TEJO — PHASE B.4 FAULT LOCATION INTELLIGENCE LIVE AUDIT      \n";
echo "====================================================================\n\n";

// 1. Verify Migration Sealed Governance Lock on Production
echo "[1] Testing Migration Endpoint Seal Lock (/migrate)...\n";
$migrateUrl = "{$baseUrl}/migrate?key=" . urlencode($key);
$migrateRes = callEndpoint($migrateUrl, $cookieFile);
echo "HTTP Code: {$migrateRes['code']} ({$migrateRes['elapsed']} ms)\n";

if ($migrateRes['code'] === 403 && ($migrateRes['json']['status'] ?? '') === 'MIGRATION_ALREADY_SEALED') {
    echo "Governance Status: SEALED & LOCKED (OK)\n";
    echo "Message: " . ($migrateRes['json']['message'] ?? '') . "\n";
} elseif ($migrateRes['code'] === 200) {
    echo "Message: " . ($migrateRes['json']['message'] ?? '') . "\n";
    echo "Causes Seeded: " . ($migrateRes['json']['causes_seeded'] ?? 0) . "\n";
} else {
    echo "Raw response:\n" . substr($migrateRes['body'], 0, 500) . "\n";
}

// 2. Run Comprehensive Live Audit Scorecard
echo "\n[2] Executing Phase B.4 Live System Audit Scorecard (/audit)...\n";
$auditUrl = "{$baseUrl}/audit?key=" . urlencode($key);
$auditRes = callEndpoint($auditUrl, $cookieFile);
echo "HTTP Code: {$auditRes['code']} ({$auditRes['elapsed']} ms)\n";

if ($auditRes['json']) {
    $scorecard = $auditRes['json'];
    echo "Status: " . ($scorecard['status'] ?? '') . "\n";
    echo "Engine Version: " . ($scorecard['engine_version'] ?? '') . "\n";
    echo "Snapshot ID: " . ($scorecard['topology_snapshot_id'] ?? '') . "\n";
    echo "All Guards Passed: " . (!empty($scorecard['all_guards_passed']) ? 'TRUE' : 'FALSE') . "\n";

    echo "\n--- MUTATION GOVERNANCE SCOPES ---\n";
    $scopes = $scorecard['governance_mutation_scopes'] ?? [];
    $topoScope = $scopes['authoritative_topology_scope'] ?? [];
    $faultScope = $scopes['fault_intelligence_scope'] ?? [];
    echo " - Authoritative Topology: " . ($topoScope['rule'] ?? 'IMMUTABLE') . " (Mutations: TL = {$topoScope['gis_translines_mutations']}, Assets = {$topoScope['master_assets_mutations']})\n";
    echo " - Fault Intelligence: " . ($faultScope['schema_installation'] ?? 'SEALED') . " (Ingestion: {$faultScope['fault_data_ingestion']})\n";

    echo "\n--- CHECKS SUMMARY ---\n";
    $checks = $scorecard['checks'] ?? [];
    echo " - Engine Guards: " . (!empty($checks['engine_guards']['all_passed']) ? 'PASS' : 'FAIL') . "\n";
    echo " - Tables Installed: " . (!empty($checks['tables_installed']['passed']) ? 'PASS' : 'FAIL') . "\n";
    echo " - Guard 12 (No Circular FK): " . (!empty($checks['guard_12_no_circular']['passed']) ? 'PASS' : 'FAIL') . "\n";
    echo " - Authoritative Topology Zero Mutation: " . (!empty($checks['authoritative_topology_zero_mutation']['passed']) ? 'PASS' : 'FAIL') . "\n";
    echo " - Feeder 118 Candidate Match: " . (!empty($checks['feeder_118_locator']['passed']) ? 'PASS' : 'FAIL') . "\n";

    // Save report to writable/audits
    @file_put_contents(__DIR__ . '/../writable/audits/B4_FAULT_INTELLIGENCE_REPORT.json', json_encode($scorecard, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo "\nSaved live audit report to writable/audits/B4_FAULT_INTELLIGENCE_REPORT.json\n";
} else {
    echo "Raw response:\n" . substr($auditRes['body'], 0, 500) . "\n";
}

// 3. Test 360° Context Engine on Feeder 118 Anchor Asset (Tiang 33 = 5245)
echo "\n[3] Testing 360° Context Engine (/context/5245)...\n";
$ctxUrl = "{$baseUrl}/context/5245?key=" . urlencode($key);
$ctxRes = callEndpoint($ctxUrl, $cookieFile);
echo "HTTP Code: {$ctxRes['code']} ({$ctxRes['elapsed']} ms)\n";
if ($ctxRes['json']) {
    $p = $ctxRes['json']['payload'] ?? [];
    $a = $p['asset'] ?? [];
    $up = $p['upstream_lineage'] ?? [];
    $fh = $up['feeder_head'] ?? [];
    $tm = $p['topology_metrics'] ?? [];
    echo "Asset: #{$a['id']} - {$a['kode_asset']} ({$a['nama_asset']})\n";
    echo "Feeder ID: " . ($a['feeder_id'] ?? 'NULL') . "\n";
    echo "Reachable: " . (!empty($up['reachable']) ? 'TRUE' : 'FALSE') . "\n";
    echo "Feeder Head: Asset #" . ($fh['asset_id'] ?? '') . " (" . ($fh['kode_asset'] ?? '') . ")\n";
    echo "Distance to Feeder Head: " . ($up['distance_to_feeder_head_m'] ?? 'NULL') . " m\n";
    echo "Hops to Feeder Head: " . ($up['hops_to_feeder_head'] ?? 'NULL') . "\n";
    echo "Conductor Impedance Supported: " . (!empty($tm['impedance_supported']) ? 'TRUE' : 'FALSE') . "\n";
    echo "Impedance Reason: " . ($tm['impedance_status_reason'] ?? '') . "\n";
}

// 4. Test Canonical Taxonomy (/causes)
echo "\n[4] Testing Canonical Cause Taxonomy Catalog (/causes)...\n";
$causesUrl = "{$baseUrl}/causes?key=" . urlencode($key);
$causesRes = callEndpoint($causesUrl, $cookieFile);
echo "HTTP Code: {$causesRes['code']} ({$causesRes['elapsed']} ms)\n";
if ($causesRes['json']) {
    echo "Total Active Cause Categories: " . ($causesRes['json']['count'] ?? 0) . "\n";
    $cats = array_slice($causesRes['json']['categories'] ?? [], 0, 5);
    foreach ($cats as $c) {
        echo " - [{$c['code']}] {$c['name']}\n";
    }
}

// 5. Test Read-Only Fault Simulation (/simulate)
echo "\n[5] Testing Read-Only Fault Simulation on Feeder 118 (/simulate)...\n";
$simUrl = "{$baseUrl}/simulate?penyulang_id=118&device_id=5245&target_distance=25.0&tolerance=20.0&key=" . urlencode($key);
$simRes = callEndpoint($simUrl, $cookieFile);
echo "HTTP Code: {$simRes['code']} ({$simRes['elapsed']} ms)\n";
if ($simRes['json']) {
    $res = $simRes['json']['result']['payload'] ?? [];
    $meta = $res['case_meta'] ?? [];
    $candidates = $res['candidates'] ?? [];
    echo "Simulation Mode: " . ($simRes['json']['mode'] ?? '') . "\n";
    echo "Mutation: " . (!empty($simRes['json']['mutation']) ? 'TRUE' : 'FALSE') . "\n";
    echo "Input Hash: " . ($meta['analysis_input_hash'] ?? '') . "\n";
    echo "Candidate Count: " . ($meta['candidate_count'] ?? 0) . "\n";
    echo "Warning Label: " . ($meta['candidate_label_warning'] ?? '') . "\n";

    if (!empty($candidates)) {
        $top = $candidates[0];
        echo "Rank 1 Candidate: Asset #{$top['asset_id']} ({$top['kode_asset']})\n";
        echo "Status: {$top['candidate_status']}\n";
        echo "Graph Distance: {$top['graph_distance_from_device_m']} m\n";
        echo "Distance Delta: {$top['distance_delta_m']} m\n";
        echo "Normalized Confidence: {$top['confidence_score']} (Scale: {$top['score_scale']})\n";
        echo "Confidence Percent: {$top['confidence_percent']}%\n";
        echo "Evidence Score (UI Ranking): {$top['evidence_score']}\n";
    }
}

echo "\n====================================================================\n";
echo "  AUDIT COMPLETE\n";
echo "====================================================================\n";
