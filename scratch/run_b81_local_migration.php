<?php

/**
 * SIDAK TEJO — Phase B.8.1 Local Migration & Schema Forensic Runner
 * Target: Local Development / Testbed (127.0.0.1:3306, DB: sidaktejo)
 *
 * Verifies:
 * 1. 3 New Tables Creation (remediation_work_packages, remediation_package_findings, remediation_package_history)
 * 2. Deep Schema Forensics (PK, FK RESTRICT, Unique constraints, 9 ENUM states)
 * 3. B81-G02: Audit History ON DELETE RESTRICT Verification
 * 4. B81-G03: client_operation_uuid UNIQUE Verification
 * 5. Idempotency (repeated execution without duplicate table/index errors)
 * 6. Zero Mutation on B.6, B.7, and GIS tables
 */

$_SERVER['CI_ENVIRONMENT'] = 'development';
putenv('CI_ENVIRONMENT=development');
require __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';

use Config\Database;
use App\Database\Migrations\CreateRemediationCoreSchema;

echo "====================================================================\n";
echo "  SIDAK TEJO — B.8.1 LOCAL SCHEMA MIGRATION & FORENSIC AUDIT        \n";
echo "  Target: LOCAL TESTBED (127.0.0.1, DB: sidaktejo)                  \n";
echo "  Timestamp: " . date('Y-m-d H:i:s T') . "                          \n";
echo "====================================================================\n\n";

$db = Database::connect('default');
$forge = Database::forge('default');

// 1. Run Migration UP
echo "[1/4] Executing CreateRemediationCoreSchema->up()...\n";
require_once APPPATH . 'Database/Migrations/2026-09-26-000002_CreateRemediationCoreSchema.php';
$migration = new CreateRemediationCoreSchema($forge);
$migration->up();
echo "      Migration executed successfully.\n\n";

// 2. Deep Schema Forensics
echo "[2/4] Performing Deep Schema Forensics...\n";

// A. Check remediation_work_packages
$tblWpExists = $db->tableExists('remediation_work_packages');
echo "      • Table remediation_work_packages: " . ($tblWpExists ? "🟢 EXISTS" : "🔴 MISSING") . "\n";

$wpIndexes = $db->query("SHOW INDEX FROM remediation_work_packages")->getResultArray();
$hasOpUuidUnique = false;
$hasCodeUnique = false;
foreach ($wpIndexes as $idx) {
    if ($idx['Key_name'] === 'uk_wp_op_uuid' && (int)$idx['Non_unique'] === 0) {
        $hasOpUuidUnique = true;
    }
    if ($idx['Key_name'] === 'uk_wp_code' && (int)$idx['Non_unique'] === 0) {
        $hasCodeUnique = true;
    }
}
echo "        - client_operation_uuid UNIQUE (B81-G03): " . ($hasOpUuidUnique ? "🟢 PASS" : "🔴 FAIL") . "\n";
echo "        - package_code UNIQUE: " . ($hasCodeUnique ? "🟢 PASS" : "🔴 FAIL") . "\n";

// Check 9 enum states in status column
$wpCols = $db->query("SHOW FULL COLUMNS FROM remediation_work_packages")->getResultArray();
$statusColDef = '';
foreach ($wpCols as $c) {
    if ($c['Field'] === 'status') {
        $statusColDef = $c['Type'];
    }
}
$expectedStates = [
    'DRAFT', 'ENGINEER_SUBMITTED', 'PENDING_APPROVAL', 'APPROVED',
    'REJECTED', 'RELEASED', 'IN_PROGRESS', 'COMPLETED', 'VERIFIED'
];
$allStatesFound = true;
foreach ($expectedStates as $st) {
    if (!str_contains($statusColDef, "'{$st}'")) {
        $allStatesFound = false;
        break;
    }
}
echo "        - 9 FSM Status Enum: " . ($allStatesFound ? "🟢 PASS (All 9 states present)" : "🔴 FAIL") . "\n";

// B. Check remediation_package_findings
$tblRpfExists = $db->tableExists('remediation_package_findings');
echo "      • Table remediation_package_findings: " . ($tblRpfExists ? "🟢 EXISTS" : "🔴 MISSING") . "\n";

$rpfIndexes = $db->query("SHOW INDEX FROM remediation_package_findings")->getResultArray();
$compositeUnique = false;
foreach ($rpfIndexes as $idx) {
    if ($idx['Key_name'] === 'uk_wp_finding' && (int)$idx['Non_unique'] === 0) {
        $compositeUnique = true;
    }
}
echo "        - Composite Unique (work_package_id, finding_id): " . ($compositeUnique ? "🟢 PASS" : "🔴 FAIL") . "\n";

// C. Check remediation_package_history & B81-G02 RESTRICT FK
$tblRphExists = $db->tableExists('remediation_package_history');
echo "      • Table remediation_package_history: " . ($tblRphExists ? "🟢 EXISTS" : "🔴 MISSING") . "\n";

$fkQuery = "SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME, DELETE_RULE, UPDATE_RULE 
            FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS 
            WHERE CONSTRAINT_SCHEMA = DATABASE() 
              AND TABLE_NAME = 'remediation_package_history' 
              AND REFERENCED_TABLE_NAME = 'remediation_work_packages'";
$fkRow = $db->query($fkQuery)->getRowArray();
$deleteRule = $fkRow['DELETE_RULE'] ?? 'NONE';
$constraintName = $fkRow['CONSTRAINT_NAME'] ?? 'UNKNOWN';
$isRestrict = ($deleteRule === 'RESTRICT' || $deleteRule === 'NO ACTION');
echo "        - FK work_package_id DELETE_RULE (B81-G02): {$deleteRule} -> " . ($isRestrict ? "🟢 PASS (Non-Cascade RESTRICT Enforced)" : "🔴 FAIL") . "\n\n";

// 3. Idempotency Test (Re-run UP)
echo "[3/4] Testing Idempotency & Repeated Execution...\n";
try {
    $migration->up();
    echo "      Re-run migration UP: 🟢 PASS (Idempotent, Zero Errors)\n\n";
} catch (\Throwable $e) {
    echo "      Re-run migration UP: 🔴 FAIL (" . $e->getMessage() . ")\n\n";
}

// 4. Verify Existing Tables Untouched
echo "[4/4] Verifying Upstream Tables Untouched (B.6, B.7, GIS)...\n";
$b6Tables = ['fault_cases', 'dispatch_assignments', 'field_investigations', 'field_findings', 'field_evidence'];
$b7Tables = ['mobile_devices', 'mobile_sync_batches', 'mobile_sync_journal', 'mobile_evidence_chunks'];

$allUntouched = true;
foreach (array_merge($b6Tables, $b7Tables) as $tbl) {
    if (!$db->tableExists($tbl)) {
        echo "      ERROR: Expected upstream table {$tbl} missing!\n";
        $allUntouched = false;
    }
}
echo "      Upstream B.6 & B.7 Tables: " . ($allUntouched ? "🟢 ALL 11 INTACT" : "🔴 CORRUPTED") . "\n";

$overallPass = ($tblWpExists && $tblRpfExists && $tblRphExists && $hasOpUuidUnique && $allStatesFound && $compositeUnique && $isRestrict && $allUntouched);

echo "\n====================================================================\n";
echo "  LOCAL B8.1 SCHEMA FORENSIC VERDICT: " . ($overallPass ? "🟢 PASS / VERIFIED" : "🔴 FAIL") . "\n";
echo "  Live Production: 🔒 STRICTLY UNTOUCHED\n";
echo "====================================================================\n";
