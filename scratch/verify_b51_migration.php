<?php

// Force development environment
$_SERVER['CI_ENVIRONMENT'] = 'development';
putenv('CI_ENVIRONMENT=development');

require __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';

$db = \Config\Database::connect('default');
$forge = \Config\Database::forge('default');

echo "=== PHASE B.5.1 MIGRATION & BACKWARD COMPATIBILITY TEST ===\n";
echo "Database: " . $db->getDatabase() . "\n\n";

// Pre-test baseline counts
$tlBaseline = $db->tableExists('gis_translines') ? $db->table('gis_translines')->countAllResults() : 0;
$assetBaseline = $db->tableExists('assets') ? $db->table('assets')->countAllResults() : 0;
echo "Authoritative Physical Baseline:\n";
echo " - gis_translines: {$tlBaseline}\n";
echo " - assets:         {$assetBaseline}\n\n";

// 1. Ensure B.4 tables exist locally
if (!$db->tableExists('fault_events')) {
    echo "[1] Applying B.4 migration (CreateFaultIntelligenceTables)...\n";
    $b4File = __DIR__ . '/../app/Database/Migrations/2026-09-25-000001_CreateFaultIntelligenceTables.php';
    require_once $b4File;
    $b4Mig = new \App\Database\Migrations\CreateFaultIntelligenceTables();
    // inject forge/db
    $b4MigRef = new ReflectionClass($b4Mig);
    $forgeProp = $b4MigRef->getProperty('forge');
    $forgeProp->setAccessible(true);
    $forgeProp->setValue($b4Mig, $forge);
    $dbProp = $b4MigRef->getProperty('db');
    $dbProp->setAccessible(true);
    $dbProp->setValue($b4Mig, $db);
    $b4Mig->up();
    echo " -> B.4 tables installed.\n";
} else {
    echo "[1] B.4 fault_events table already exists.\n";
}

// 2. Seed simulated legacy B.4 rows if table is empty
$legacyCountBefore = $db->table('fault_events')->countAllResults();
if ($legacyCountBefore === 0) {
    echo "[2] Seeding simulated legacy B.4 fault_events...\n";
    $sampleAsset = $db->table('assets')->select('id')->limit(1)->get()->getRowArray();
    $validAssetId = !empty($sampleAsset['id']) ? (int)$sampleAsset['id'] : null;

    $legacyEvents = [
        [
            'event_number'            => 'EVT-LEGACY-001',
            'penyulang_id'            => 118,
            'source_device_asset_id'  => $validAssetId,
            'event_time'              => '2026-09-24 10:15:00',
            'topology_snapshot_id'    => 'TOPOLOGY-20260925-243-ad2c9fcb',
            'source_type'             => 'MANUAL_ENTRY',
            'source_reference'        => 'TICKET-2026-001',
            'raw_telemetry_json'      => json_encode(['legacy_note' => 'Initial manual report']),
            'status'                  => 'OPEN',
            'created_at'              => '2026-09-24 10:15:00',
        ],
        [
            'event_number'            => 'EVT-LEGACY-002',
            'penyulang_id'            => 118,
            'source_device_asset_id'  => $validAssetId,
            'event_time'              => '2026-09-24 11:30:00',
            'topology_snapshot_id'    => 'TOPOLOGY-20260925-243-ad2c9fcb',
            'source_type'             => 'PMCB_RELAY',
            'source_reference'        => 'RELAY-REC-991',
            'raw_telemetry_json'      => json_encode(['current' => 450.0]),
            'status'                  => 'ANALYZED',
            'created_at'              => '2026-09-24 11:30:00',
        ],
        [
            'event_number'            => 'EVT-LEGACY-003',
            'penyulang_id'            => 15,
            'source_device_asset_id'  => null,
            'event_time'              => '2026-09-24 14:00:00',
            'topology_snapshot_id'    => 'TOPOLOGY-20260925-243-ad2c9fcb',
            'source_type'             => 'SCADA',
            'source_reference'        => 'SCADA-SEQ-4042',
            'raw_telemetry_json'      => json_encode(['alarm' => 'OVERCURRENT']),
            'status'                  => 'RESOLVED',
            'created_at'              => '2026-09-24 14:00:00',
        ],
    ];
    $db->table('fault_events')->insertBatch($legacyEvents);
    $legacyCountBefore = count($legacyEvents);
    echo " -> Seeded {$legacyCountBefore} legacy rows.\n";
} else {
    echo "[2] Found {$legacyCountBefore} existing rows in fault_events.\n";
}

// 3. Apply B.5.1 Migration
echo "\n[3] Applying B.5.1 Migration (EnhanceFaultEventsIngestion)...\n";
$b5File = __DIR__ . '/../app/Database/Migrations/2026-09-25-000002_EnhanceFaultEventsIngestion.php';
require_once $b5File;
$b5Mig = new \App\Database\Migrations\EnhanceFaultEventsIngestion();
$b5MigRef = new ReflectionClass($b5Mig);
$forgeProp = $b5MigRef->getProperty('forge');
$forgeProp->setAccessible(true);
$forgeProp->setValue($b5Mig, $forge);
$dbProp = $b5MigRef->getProperty('db');
$dbProp->setAccessible(true);
$dbProp->setValue($b5Mig, $db);
$b5Mig->up();
echo " -> B.5.1 migration completed successfully.\n\n";

// 4. Verification of Acceptance Criteria
echo "[4] Verifying Acceptance Criteria:\n";

// Check 4.1: Historical row count preserved
$legacyCountAfter = $db->table('fault_events')->countAllResults();
$rowCountPreserved = ($legacyCountBefore === $legacyCountAfter);
echo " - [Criterion 1] Row Preservation: Before = {$legacyCountBefore}, After = {$legacyCountAfter} -> " .
    ($rowCountPreserved ? "PASS ✅" : "FAIL ❌") . "\n";

// Check 4.2: Schema columns in fault_events (fetch fresh from DB)
$rawCols = $db->query('SHOW COLUMNS FROM `fault_events`')->getResultArray();
$fields = array_column($rawCols, 'Field');
$requiredCols = [
    'event_fingerprint',
    'fingerprint_status',
    'fault_phase',
    'fault_current_a',
    'phase_currents_json',
    'protection_elements',
    'trip_sequence',
    'relay_distance_m',
    'lifecycle_status',
];
$allColsPresent = true;
foreach ($requiredCols as $rc) {
    $hasCol = in_array($rc, $fields, true);
    if (!$hasCol) $allColsPresent = false;
    echo "   * Column '{$rc}': " . ($hasCol ? "PRESENT ✅" : "MISSING ❌") . "\n";
}

// Check 4.3: Legacy row fingerprint & lifecycle handling
$legacyRows = $db->table('fault_events')->limit(5)->get()->getResultArray();
$legacyFingerprintCheck = true;
$legacyLifecycleCheck = true;
foreach ($legacyRows as $lr) {
    if ($lr['fingerprint_status'] !== 'LEGACY_UNFINGERPRINTED') {
        $legacyFingerprintCheck = false;
    }
    if ($lr['event_fingerprint'] !== null) {
        $legacyFingerprintCheck = false; // Must be NULL, no fake hash
    }
    if (empty($lr['lifecycle_status'])) {
        $legacyLifecycleCheck = false;
    }
    echo "   * Row #{$lr['id']} ({$lr['event_number']}): fp_status={$lr['fingerprint_status']}, fingerprint=" .
        ($lr['event_fingerprint'] ?? 'NULL') . ", status={$lr['status']} -> lifecycle={$lr['lifecycle_status']}\n";
}
echo " - [Criterion 2] Legacy Fingerprint Handling (NULL + LEGACY_UNFINGERPRINTED): " .
    ($legacyFingerprintCheck ? "PASS ✅" : "FAIL ❌") . "\n";
echo " - [Criterion 3] Legacy Lifecycle Status Mapping: " .
    ($legacyLifecycleCheck ? "PASS ✅" : "FAIL ❌") . "\n";

// Check 4.4: Tables fault_event_revisions and fault_ingestion_batches
$revisionsExists = $db->tableExists('fault_event_revisions');
$batchesExists = $db->tableExists('fault_ingestion_batches');
echo " - [Criterion 4] Table 'fault_event_revisions' (Guard 1): " . ($revisionsExists ? "EXISTS ✅" : "MISSING ❌") . "\n";
echo " - [Criterion 5] Table 'fault_ingestion_batches': " . ($batchesExists ? "EXISTS ✅" : "MISSING ❌") . "\n";

// Check 4.5: Deduplication constraint test
echo "\n[5] Testing Deduplication Gate Constraint (UNIQUE index on event_fingerprint):\n";
$testFp = hash('sha256', 'TEST-FINGERPRINT-EVENT-001');
$insertedFirst = false;
$duplicateRejected = false;

try {
    $db->table('fault_events')->insert([
        'event_number'       => 'EVT-TEST-FP-001',
        'event_fingerprint'  => $testFp,
        'fingerprint_status' => 'CALCULATED',
        'penyulang_id'       => 118,
        'event_time'         => date('Y-m-d H:i:s'),
        'source_type'        => 'SCADA',
        'source_reference'   => 'TEST-REF-001',
        'lifecycle_status'   => 'INGESTED',
    ]);
    $insertedFirst = true;
    echo " - Initial insert with fingerprint {$testFp}: SUCCESS ✅\n";
} catch (\Throwable $e) {
    echo " - Initial insert failed: " . $e->getMessage() . "\n";
}

try {
    $db->table('fault_events')->insert([
        'event_number'       => 'EVT-TEST-FP-002', // duplicate fingerprint!
        'event_fingerprint'  => $testFp,
        'fingerprint_status' => 'CALCULATED',
        'penyulang_id'       => 118,
        'event_time'         => date('Y-m-d H:i:s'),
        'source_type'        => 'SCADA',
        'source_reference'   => 'TEST-REF-002',
        'lifecycle_status'   => 'INGESTED',
    ]);
    echo " - Duplicate insert unexpectedly succeeded: FAIL ❌\n";
} catch (\Throwable $e) {
    $duplicateRejected = true;
    echo " - Duplicate insert rejected by UNIQUE constraint: PASS ✅ (" . get_class($e) . ")\n";
}

// Clean up test rows
$db->table('fault_events')->where('event_fingerprint', $testFp)->delete();

// Check 4.6: Invariant verification
$tlPost = $db->tableExists('gis_translines') ? $db->table('gis_translines')->countAllResults() : 0;
$assetPost = $db->tableExists('assets') ? $db->table('assets')->countAllResults() : 0;
$zeroMutation = ($tlBaseline === $tlPost && $assetBaseline === $assetPost);

echo "\n[6] Post-Migration Authoritative Physical Topology Invariant:\n";
echo " - gis_translines: {$tlBaseline} -> {$tlPost} (Delta: " . ($tlPost - $tlBaseline) . ")\n";
echo " - assets:         {$assetBaseline} -> {$assetPost} (Delta: " . ($assetPost - $assetBaseline) . ")\n";
echo " - Invariant Status: " . ($zeroMutation ? "MUTATION = 0 (PASS ✅)" : "MUTATION DETECTED ❌") . "\n\n";

$allPassed = $rowCountPreserved && $allColsPresent && $legacyFingerprintCheck &&
             $legacyLifecycleCheck && $revisionsExists && $batchesExists &&
             $insertedFirst && $duplicateRejected && $zeroMutation;

echo "====================================================================\n";
echo "PHASE B.5.1 MIGRATION GATE VERDICT: " . ($allPassed ? "PASS 🟢" : "FAIL 🔴") . "\n";
echo "====================================================================\n";
