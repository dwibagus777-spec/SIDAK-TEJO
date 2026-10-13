<?php
/**
 * SIDAK TEJO — Phase 1-A Production Probe (curl.exe delegated)
 * B8.1 Schema Migration + Post-Migration Verification
 *
 * Uses curl.exe (not PHP curl) to bypass PHP DNS resolver issue.
 * curl.exe is verified working: HTTP 200 from sidaktejo.site.
 */

$BASE_URL    = 'https://sidaktejo.site';
$AUDIT_TOKEN = 'sidak_transline_audit_2026';
$DEPLOY_KEY  = 'sidak_tejo_deploy_master_2026';

// Canonical baseline (Phase 0 locked)
$EXPECTED = [
    'active_tl'        => 245,
    'physical_tl'      => 254,
    'active_assets'    => 5236,
    'physical_assets'  => 5549,
    'span'             => 9418.37,
    'snapshot'         => 'TOPOLOGY-20260928-245-81c43a7f',
    'h_active_tl'      => '3305355a6dddf191a5176934f4de05f4368a21eb0f295660b3790804ac91af3a',
    'h_physical_tl'    => '90a92779ab1da6d911c7826a6056f168e27014778270764b4e8c42b5535d7462',
    'h_active_assets'  => '83d8260ef2dfd1c8517e28879857fbd9816a3742a353217eef03d6a46a431711',
    'h_physical_assets'=> '2e0477b238d5e352be5e9e70d2f57d42b808fd7d8d1d409dc40614c59193152a',
];

$results    = [];
$passCount  = 0;
$failCount  = 0;

// ─────────────────────────────────────────────────────────────────────────────
// Helper: invoke curl.exe, return ['http_code'=>int, 'json'=>array|null, 'raw'=>string]
// ─────────────────────────────────────────────────────────────────────────────
function curlExec(string $method, string $url, array $extraHeaders = [], bool $isPost = false): array
{
    // Build header args
    $hArgs = '';
    foreach ($extraHeaders as $h) {
        $hArgs .= ' -H ' . escapeshellarg($h);
    }

    $postArg = $isPost ? ' -X POST -d {}' : '';

    // Write response to a temp file so we can separate headers from body
    $tmpBody   = tempnam(sys_get_temp_dir(), 'p1a_body_');
    $tmpStatus = tempnam(sys_get_temp_dir(), 'p1a_stat_');

    $cmd = "curl.exe -s -k --max-time 60{$postArg}{$hArgs}"
         . " -H " . escapeshellarg('Content-Type: application/json')
         . " -o " . escapeshellarg($tmpBody)
         . " -w \"%{http_code}\""
         . " " . escapeshellarg($url)
         . " 2>&1";

    $httpCode = (int) trim(shell_exec($cmd));
    $raw      = file_exists($tmpBody) ? file_get_contents($tmpBody) : '';
    @unlink($tmpBody);
    @unlink($tmpStatus);

    $json = $raw ? @json_decode($raw, true) : null;

    return ['http_code' => $httpCode, 'raw' => $raw, 'json' => $json];
}

// ─────────────────────────────────────────────────────────────────────────────
// Helper: record check
// ─────────────────────────────────────────────────────────────────────────────
function check(string $id, string $label, bool $pass, string $actual = '', string $expected = ''): void
{
    global $results, $passCount, $failCount;
    $icon = $pass ? '✅' : '❌';
    $results[] = compact('id', 'label', 'pass', 'actual', 'expected', 'icon');
    if ($pass) { $passCount++; } else { $failCount++; }
    $suffix = $pass ? '' : "\n         expected=[{$expected}]\n         got     =[{$actual}]";
    echo "  {$icon} {$id}: {$label}{$suffix}\n";
}

function floatEq(float $a, float $b): bool { return abs($a - $b) < 0.005; }

// ─────────────────────────────────────────────────────────────────────────────
// STEP 1 — POST /remediation/migrate (first call — INSTALL)
// ─────────────────────────────────────────────────────────────────────────────
echo "\n══════════════════════════════════════════════════════════\n";
echo "  SIDAK TEJO — Phase 1-A: B8.1 Migration + Verification\n";
echo "══════════════════════════════════════════════════════════\n\n";

echo "── STEP 1: Migration Trigger (POST /remediation/migrate) ──\n";

$migrateHeaders = [
    'X-Audit-Token: ' . $AUDIT_TOKEN,
    'X-Deploy-Master-Key: ' . $DEPLOY_KEY,
];

$res1 = curlExec('POST', $BASE_URL . '/remediation/migrate', $migrateHeaders, true);
$j1   = $res1['json'];

if ($res1['http_code'] === 0) {
    echo "  ❌ FATAL: curl.exe returned HTTP 0 — network failure\n";
    echo "  Raw output: " . $res1['raw'] . "\n";
    exit(1);
}

// P1-01: HTTP 200
check('P1-01', 'HTTP status = 200', $res1['http_code'] === 200, (string)$res1['http_code'], '200');

// P1-02: migration_status
$ms1 = $j1['migration_status'] ?? 'null';
$validStatuses = ['MIGRATION_INSTALLED_AND_SEALED', 'MIGRATION_ALREADY_SEALED'];
if ($ms1 === 'MIGRATION_INSTALLED_AND_SEALED') {
    check('P1-02', 'migration_status = MIGRATION_INSTALLED_AND_SEALED (fresh install)', true, $ms1, 'MIGRATION_INSTALLED_AND_SEALED');
} elseif ($ms1 === 'MIGRATION_ALREADY_SEALED') {
    check('P1-02', 'migration_status = MIGRATION_ALREADY_SEALED (idempotent — already installed)', true, $ms1, 'MIGRATION_INSTALLED_AND_SEALED|MIGRATION_ALREADY_SEALED');
} else {
    check('P1-02', 'migration_status = MIGRATION_INSTALLED_AND_SEALED', false, $ms1, 'MIGRATION_INSTALLED_AND_SEALED');
}

// P1-03: status = PASS
check('P1-03', 'status = PASS', ($j1['status'] ?? '') === 'PASS', $j1['status'] ?? 'null', 'PASS');

// P1-04: topology_delta.verdict
$verdict = $j1['topology_delta']['verdict'] ?? '';
check('P1-04', 'topology_delta.verdict = ZERO_TOPOLOGY_MUTATION_VERIFIED',
    $verdict === 'ZERO_TOPOLOGY_MUTATION_VERIFIED', $verdict, 'ZERO_TOPOLOGY_MUTATION_VERIFIED');

// P1-05: delta_active_translines = 0
$dTL = $j1['topology_delta']['delta_active_translines'] ?? 'null';
check('P1-05', 'delta_active_translines = 0', $dTL === 0, (string)$dTL, '0');

// P1-06: delta_active_assets = 0
$dA = $j1['topology_delta']['delta_active_assets'] ?? 'null';
check('P1-06', 'delta_active_assets = 0', $dA === 0, (string)$dA, '0');

// ─────────────────────────────────────────────────────────────────────────────
// STEP 2 — Sentinel After
// ─────────────────────────────────────────────────────────────────────────────
echo "\n── STEP 2: Sentinel After ──\n";
$sa = $j1['sentinel_after'] ?? [];

check('P1-07', 'sentinel_after.active_translines = 245',
    (int)($sa['active_translines'] ?? -1) === 245, (string)($sa['active_translines'] ?? 'null'), '245');

check('P1-08', 'sentinel_after.physical_translines = 254',
    (int)($sa['physical_translines'] ?? -1) === 254, (string)($sa['physical_translines'] ?? 'null'), '254');

check('P1-09', 'sentinel_after.active_assets = 5236',
    (int)($sa['active_assets'] ?? -1) === 5236, (string)($sa['active_assets'] ?? 'null'), '5236');

check('P1-10', 'sentinel_after.physical_assets = 5549',
    (int)($sa['physical_assets'] ?? -1) === 5549, (string)($sa['physical_assets'] ?? 'null'), '5549');

check('P1-11', 'sentinel_after.network_span_meters = 9418.37',
    floatEq((float)($sa['network_span_meters'] ?? 0), 9418.37),
    number_format((float)($sa['network_span_meters'] ?? 0), 2), '9418.37');

check('P1-12', 'sentinel_after.topology_snapshot_id = TOPOLOGY-20260928-245-81c43a7f',
    ($sa['topology_snapshot_id'] ?? '') === 'TOPOLOGY-20260928-245-81c43a7f',
    $sa['topology_snapshot_id'] ?? 'null', 'TOPOLOGY-20260928-245-81c43a7f');

// ─────────────────────────────────────────────────────────────────────────────
// STEP 3 — Schema Forensics
// ─────────────────────────────────────────────────────────────────────────────
echo "\n── STEP 3: Schema Forensics ──\n";
$foren = $j1['forensics'] ?? [];

check('P1-13', 'forensics.all_valid = true',
    ($foren['all_valid'] ?? false) === true, var_export($foren['all_valid'] ?? null, true), 'true');

$rwp = $foren['tables']['remediation_work_packages'] ?? [];
check('P1-14', 'rwp.op_uuid_unique_b81_g03 = true',
    ($rwp['op_uuid_unique_b81_g03'] ?? false) === true, var_export($rwp['op_uuid_unique_b81_g03'] ?? null, true), 'true');
check('P1-15', 'rwp.code_unique = true',
    ($rwp['code_unique'] ?? false) === true, var_export($rwp['code_unique'] ?? null, true), 'true');
check('P1-16', 'rwp.all_9_fsm_states = true',
    ($rwp['all_9_fsm_states'] ?? false) === true, var_export($rwp['all_9_fsm_states'] ?? null, true), 'true');

$rpf = $foren['tables']['remediation_package_findings'] ?? [];
check('P1-17', 'rpf.composite_unique = true',
    ($rpf['composite_unique'] ?? false) === true, var_export($rpf['composite_unique'] ?? null, true), 'true');

$rph = $foren['tables']['remediation_package_history'] ?? [];
check('P1-18', 'rph.b81_g02_restrict_enforced = true',
    ($rph['b81_g02_restrict_enforced'] ?? false) === true, var_export($rph['b81_g02_restrict_enforced'] ?? null, true), 'true');

// ─────────────────────────────────────────────────────────────────────────────
// STEP 4 — IDEMPOTENCY: second POST /remediation/migrate
// ─────────────────────────────────────────────────────────────────────────────
echo "\n── STEP 4: Idempotency (2nd POST /remediation/migrate) ──\n";

$res2 = curlExec('POST', $BASE_URL . '/remediation/migrate', $migrateHeaders, true);
$j2   = $res2['json'];

check('P1-19a', 'Idempotency HTTP = 200', $res2['http_code'] === 200, (string)$res2['http_code'], '200');
$ms2 = $j2['migration_status'] ?? 'null';
check('P1-19b', 'Idempotency migration_status = MIGRATION_ALREADY_SEALED',
    $ms2 === 'MIGRATION_ALREADY_SEALED', $ms2, 'MIGRATION_ALREADY_SEALED');
check('P1-19c', 'Idempotency status = PASS',
    ($j2['status'] ?? '') === 'PASS', $j2['status'] ?? 'null', 'PASS');

// ─────────────────────────────────────────────────────────────────────────────
// STEP 5 — GET /remediation/audit
// ─────────────────────────────────────────────────────────────────────────────
echo "\n── STEP 5: Audit Layer (GET /remediation/audit) ──\n";

$auditHeaders = ['X-Audit-Token: ' . $AUDIT_TOKEN];
$resAudit = curlExec('GET', $BASE_URL . '/remediation/audit', $auditHeaders, false);
$jA = $resAudit['json'];

// P1-20: b81_schema_installed = true
$b81 = $jA['b81_schema_installed'] ?? null;
check('P1-20', 'audit.b81_schema_installed = true', $b81 === true, var_export($b81, true), 'true');

// P1-21: reconciliation_status
$recSt = $jA['reconciliation_status'] ?? '';
check('P1-21', 'audit.reconciliation_status = PASS_AUTHORITATIVE_PRODUCTION',
    $recSt === 'PASS_AUTHORITATIVE_PRODUCTION', $recSt, 'PASS_AUTHORITATIVE_PRODUCTION');

// P1-22..P1-25: 4 identity hashes — located under topology_sentinel in audit response
$ts = $jA['topology_sentinel'] ?? [];

$hAtl = $ts['active_transline_hash'] ?? '';
check('P1-22', 'h_active_tl matches canonical',
    $hAtl === $EXPECTED['h_active_tl'], $hAtl, $EXPECTED['h_active_tl']);

$hPtl = $ts['physical_transline_hash'] ?? '';
check('P1-23', 'h_physical_tl matches canonical',
    $hPtl === $EXPECTED['h_physical_tl'], $hPtl, $EXPECTED['h_physical_tl']);

$hAa = $ts['active_asset_hash'] ?? '';
check('P1-24', 'h_active_assets matches canonical',
    $hAa === $EXPECTED['h_active_assets'], $hAa, $EXPECTED['h_active_assets']);

$hPa = $ts['physical_asset_hash'] ?? '';
check('P1-25', 'h_physical_assets matches canonical (with 1d)',
    $hPa === $EXPECTED['h_physical_assets'], $hPa, $EXPECTED['h_physical_assets']);

// ─────────────────────────────────────────────────────────────────────────────
// STEP 6 — GET /remediation/forensic-reconciliation
// ─────────────────────────────────────────────────────────────────────────────
echo "\n── STEP 6: Forensic Reconciliation ──\n";

$resRec = curlExec('GET', $BASE_URL . '/remediation/forensic-reconciliation', $auditHeaders, false);
$jRec   = $resRec['json'];

$recSt2 = $jRec['reconciliation_verdict'] ?? '';
check('P1-26', 'forensic-reconciliation.reconciliation_verdict = PASS_AUTHORITATIVE_PRODUCTION',
    $recSt2 === 'PASS_AUTHORITATIVE_PRODUCTION', $recSt2, 'PASS_AUTHORITATIVE_PRODUCTION');

// ─────────────────────────────────────────────────────────────────────────────
// SUMMARY
// ─────────────────────────────────────────────────────────────────────────────
$total = $passCount + $failCount;
echo "\n══════════════════════════════════════════════════════════\n";
echo "  Phase 1-A Result: {$passCount}/{$total} PASS\n";
echo "══════════════════════════════════════════════════════════\n\n";

if ($failCount === 0) {
    echo "  🔒 B8.1 PHASE 1 — SEALED\n\n";
    echo "  B8.1_SCHEMA_INSTALLED = TRUE\n";
    echo "  TOPOLOGY Δ            = 0\n";
    echo "  ASSET Δ               = 0\n";
    echo "  HASH Δ                = 0\n";
    echo "  SNAPSHOT              = TOPOLOGY-20260928-245-81c43a7f (unchanged)\n";
    echo "  SPAN                  = 9418.37 m (unchanged)\n";
} else {
    echo "  ⛔ B8.1 PHASE 1 — OPEN ({$failCount} failure(s))\n\n";
    echo "  FAILED CHECKS:\n";
    foreach ($results as $r) {
        if (!$r['pass']) {
            echo "    ❌ {$r['id']}: {$r['label']}\n";
            echo "       expected=[{$r['expected']}]\n";
            echo "       got     =[{$r['actual']}]\n";
        }
    }
}

echo "\n── Raw migrate() response (1st call, first 3000 chars) ──\n";
echo substr(json_encode($j1, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 0, 3000) . "\n";
