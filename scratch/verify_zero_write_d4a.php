<?php

require __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';

use Config\Database;
use App\Services\TranslineProposalReviewService;

$db = Database::connect('tests');
$forge = Database::forge('tests');

echo "=== ZERO-WRITE VERIFICATION SUITE: SUB-GATE D4A ===" . PHP_EOL;

// 1. Setup test schema
$schema = [
    'penyulang' => [
        'id'             => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
        'ulp_id'         => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
        'kode_penyulang' => ['type' => 'VARCHAR', 'constraint' => 50],
        'nama_penyulang' => ['type' => 'VARCHAR', 'constraint' => 100],
        'status'         => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'AKTIF'],
    ],
    'sections' => [
        'id'           => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
        'penyulang_id' => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
        'nama_section' => ['type' => 'VARCHAR', 'constraint' => 100],
    ],
    'assets' => [
        'id'                   => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
        'kode_asset'           => ['type' => 'VARCHAR', 'constraint' => 100],
        'nama_asset'           => ['type' => 'VARCHAR', 'constraint' => 255, 'default' => 'Tiang Aset'],
        'jenis_asset'          => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'TIANG_BETON'],
        'type'                 => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
        'ulp_id'               => ['type' => 'INT', 'constraint' => 11, 'null' => true],
        'penyulang_id'         => ['type' => 'INT', 'constraint' => 11, 'null' => true],
        'section_id'           => ['type' => 'INT', 'constraint' => 11, 'null' => true],
        'sequence_no'          => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
        'construction_type_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
        'latitude'             => ['type' => 'DECIMAL', 'constraint' => '10,8', 'null' => true],
        'longitude'            => ['type' => 'DECIMAL', 'constraint' => '11,8', 'null' => true],
        'lokasi'               => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        'status'               => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'NORMAL'],
        'deleted_at'           => ['type' => 'DATETIME', 'null' => true],
    ],
    'gis_translines' => [
        'id'                 => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
        'transline_code'     => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
        'penyulang_id'       => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
        'section_id'         => ['type' => 'INT', 'constraint' => 11, 'null' => true],
        'source_asset_id'    => ['type' => 'INT', 'constraint' => 11, 'null' => true],
        'target_asset_id'    => ['type' => 'INT', 'constraint' => 11, 'null' => true],
        'from_asset_id'      => ['type' => 'INT', 'constraint' => 11, 'null' => true],
        'to_asset_id'        => ['type' => 'INT', 'constraint' => 11, 'null' => true],
        'geometry'           => ['type' => 'TEXT', 'null' => true],
        'geometry_type'      => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'LineString'],
        'conductor_type'     => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'AAAC'],
        'conductor_size'     => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => '150 mm²'],
        'conductor_material' => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'ALUMINUM_ALLOY'],
        'installation_type'  => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'OVERHEAD'],
        'circuit_config'     => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => '3_PHASE'],
        'distance_meters'    => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 50.00],
        'length_m'           => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true],
        'source'             => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'MANUAL'],
        'status'             => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'ACTIVE'],
        'is_active'          => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
        'created_by'         => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
        'created_at'         => ['type' => 'DATETIME', 'null' => true],
    ],
    'gis_transline_proposals' => [
        'id'                      => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
        'penyulang_id'            => ['type' => 'INT', 'constraint' => 11],
        'section_id'              => ['type' => 'INT', 'constraint' => 11, 'null' => true],
        'source_asset_id'         => ['type' => 'INT', 'constraint' => 11, 'null' => true],
        'target_asset_id'         => ['type' => 'INT', 'constraint' => 11, 'null' => true],
        'from_asset_id'           => ['type' => 'INT', 'constraint' => 11, 'null' => true],
        'to_asset_id'             => ['type' => 'INT', 'constraint' => 11, 'null' => true],
        'natural_key'             => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
        'proposed_conductor_type' => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'AAAC'],
        'proposed_conductor_size' => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => '150 mm²'],
        'proposed_distance'       => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 50.00],
        'proposed_geometry'       => ['type' => 'TEXT', 'null' => true],
        'classification'          => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'AUTO_MATCH'],
        'confidence_score'        => ['type' => 'DECIMAL', 'constraint' => '5,4', 'default' => 1.0000],
        'evidence_json'           => ['type' => 'TEXT', 'null' => true],
        'proposal_source'         => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'DETERMINISTIC_ENGINE'],
        'engine_version'          => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'TL-01-V2.0'],
        'status'                  => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'PENDING_REVIEW'],
        'reviewed_by'             => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
        'reviewed_at'             => ['type' => 'DATETIME', 'null' => true],
        'review_note'             => ['type' => 'TEXT', 'null' => true],
        'confirmed_transline_id'  => ['type' => 'INT', 'constraint' => 11, 'null' => true],
        'created_at'              => ['type' => 'DATETIME', 'null' => true],
        'updated_at'              => ['type' => 'DATETIME', 'null' => true],
        'deleted_at'              => ['type' => 'DATETIME', 'null' => true],
    ],
    'temuan' => [
        'id'           => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
        'ulp_id'       => ['type' => 'INT', 'constraint' => 11, 'null' => true],
        'penyulang_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
        'section_id'   => ['type' => 'INT', 'constraint' => 11, 'null' => true],
        'asset_id'     => ['type' => 'INT', 'constraint' => 11, 'null' => true],
        'judul'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        'created_at'   => ['type' => 'DATETIME', 'null' => true],
    ],
    'temuan_materials' => [
        'id'          => ['type' => 'INT', 'constraint' => 11, 'auto_increment' => true],
        'temuan_id'   => ['type' => 'INT', 'constraint' => 11],
        'material_id' => ['type' => 'INT', 'constraint' => 11],
        'quantity'    => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 1.0],
    ],
];

foreach ($schema as $tableName => $fields) {
    if (!$db->tableExists($tableName)) {
        $forge->addField($fields);
        $forge->addKey('id', true);
        $forge->createTable($tableName, true);
    } else {
        $db->table($tableName)->emptyTable();
    }
}

// 2. Seed Baseline Canonical Counts (Identical to Production State)
// 28 Penyulang
$penyulangBatch = [];
for ($i = 1; $i <= 28; $i++) {
    $penyulangBatch[] = [
        'id'             => $i,
        'ulp_id'         => 1,
        'kode_penyulang' => 'FDR_' . sprintf('%02d', $i),
        'nama_penyulang' => 'FEEDER ' . $i,
        'status'         => 'AKTIF',
    ];
}
$db->table('penyulang')->insertBatch($penyulangBatch);

// 134 Sections
$sectionsBatch = [];
for ($i = 1; $i <= 134; $i++) {
    $sectionsBatch[] = [
        'id'           => $i,
        'penyulang_id' => (($i - 1) % 28) + 1,
        'nama_section' => 'SECTION ' . $i,
    ];
}
$db->table('sections')->insertBatch($sectionsBatch);

// 2660 Assets
$assetsBatch = [];
for ($i = 1; $i <= 2660; $i++) {
    $secId = (($i - 1) % 134) + 1;
    $penyulangId = (($secId - 1) % 28) + 1;
    $assetsBatch[] = [
        'id'                   => $i,
        'kode_asset'           => 'AST_' . sprintf('%05d', $i),
        'nama_asset'           => 'Asset Pole ' . $i,
        'jenis_asset'          => 'TIANG_BETON',
        'type'                 => 'POLE',
        'ulp_id'               => 1,
        'penyulang_id'         => $penyulangId,
        'section_id'           => $secId,
        'sequence_no'          => $i,
        'construction_type_id' => 101,
        'latitude'             => -7.45000000 + ($i * 0.00001),
        'longitude'            => 112.71000000 + ($i * 0.00001),
        'lokasi'               => 'Sidoarjo KM ' . ($i * 0.1),
        'status'               => 'NORMAL',
    ];
    if (count($assetsBatch) === 500) {
        $db->table('assets')->insertBatch($assetsBatch);
        $assetsBatch = [];
    }
}
if (!empty($assetsBatch)) {
    $db->table('assets')->insertBatch($assetsBatch);
}

// 42 Authoritative Baseline Translines
$translinesBatch = [];
for ($i = 1; $i <= 42; $i++) {
    $translinesBatch[] = [
        'id'                 => $i,
        'transline_code'     => 'TL_BASELINE_' . sprintf('%03d', $i),
        'penyulang_id'       => (($i - 1) % 28) + 1,
        'section_id'         => (($i - 1) % 134) + 1,
        'source_asset_id'    => $i,
        'target_asset_id'    => $i + 1,
        'from_asset_id'      => $i,
        'to_asset_id'        => $i + 1,
        'geometry'           => '{"type":"LineString","coordinates":[[112.71, -7.45],[112.72, -7.45]]}',
        'geometry_type'      => 'LineString',
        'conductor_type'     => 'AAAC',
        'conductor_size'     => '150 mm²',
        'conductor_material' => 'ALUMINUM_ALLOY',
        'installation_type'  => 'OVERHEAD',
        'circuit_config'     => '3_PHASE',
        'distance_meters'    => 45.50,
        'length_m'           => 45.50,
        'source'             => 'BASELINE_SEEDER',
        'status'             => 'ACTIVE',
        'is_active'          => 1,
        'created_by'         => 'SYSTEM_SEEDER',
        'created_at'         => '2026-09-01 00:00:00',
    ];
}
$db->table('gis_translines')->insertBatch($translinesBatch);

// 5 Proposals
$proposalsData = [
    [
        'id'                      => 1,
        'penyulang_id'            => 1,
        'section_id'              => 1,
        'source_asset_id'         => 10,
        'target_asset_id'         => 11,
        'from_asset_id'           => 10,
        'to_asset_id'             => 11,
        'natural_key'             => 'FDR_01:AST_00010->AST_00011',
        'proposed_conductor_type' => 'AAAC',
        'proposed_conductor_size' => '150 mm²',
        'proposed_distance'       => 50.00,
        'classification'          => 'AUTO_MATCH',
        'confidence_score'        => 1.0000,
        'status'                  => 'PENDING_REVIEW',
        'evidence_json'           => '{"model":"heuristic-v1","factors":["span_distance_ok"]}',
    ],
    [
        'id'                      => 2,
        'penyulang_id'            => 1,
        'section_id'              => 1,
        'source_asset_id'         => 12,
        'target_asset_id'         => 13,
        'from_asset_id'           => 12,
        'to_asset_id'             => 13,
        'natural_key'             => 'FDR_01:AST_00012->AST_00013',
        'proposed_conductor_type' => 'AAAC',
        'proposed_conductor_size' => '150 mm²',
        'proposed_distance'       => 52.00,
        'classification'          => 'AUTO_MATCH',
        'confidence_score'        => 0.9800,
        'status'                  => 'PENDING_REVIEW',
        'evidence_json'           => '{"model":"heuristic-v1","factors":["span_distance_ok"]}',
    ],
    [
        'id'                      => 3,
        'penyulang_id'            => 2,
        'section_id'              => 2,
        'source_asset_id'         => 14,
        'target_asset_id'         => 15,
        'from_asset_id'           => 14,
        'to_asset_id'             => 15,
        'natural_key'             => 'FDR_02:AST_00014->AST_00015',
        'proposed_conductor_type' => 'AAAC',
        'proposed_conductor_size' => '150 mm²',
        'proposed_distance'       => 48.00,
        'classification'          => 'AUTO_MATCH',
        'confidence_score'        => 0.9500,
        'status'                  => 'PENDING_REVIEW',
        'evidence_json'           => '{"model":"heuristic-v1","factors":["span_distance_ok"]}',
    ],
    [
        'id'                      => 4,
        'penyulang_id'            => 3,
        'section_id'              => 3,
        'source_asset_id'         => 16,
        'target_asset_id'         => 17,
        'from_asset_id'           => 16,
        'to_asset_id'             => 17,
        'natural_key'             => 'FDR_03:AST_00016->AST_00017',
        'proposed_conductor_type' => 'AAAC',
        'proposed_conductor_size' => '150 mm²',
        'proposed_distance'       => 75.00,
        'classification'          => 'NEEDS_REVIEW',
        'confidence_score'        => 0.6500,
        'status'                  => 'PENDING_REVIEW',
        'evidence_json'           => '{"model":"heuristic-v1","factors":["span_distance_long"]}',
    ],
    [
        'id'                      => 5,
        'penyulang_id'            => 4,
        'section_id'              => 4,
        'source_asset_id'         => 18,
        'target_asset_id'         => 19,
        'from_asset_id'           => 18,
        'to_asset_id'             => 19,
        'natural_key'             => 'FDR_04:AST_00018->AST_00019',
        'proposed_conductor_type' => 'AAAC',
        'proposed_conductor_size' => '150 mm²',
        'proposed_distance'       => 120.00,
        'classification'          => 'INVALID',
        'confidence_score'        => 0.2000,
        'status'                  => 'PENDING_REVIEW',
        'evidence_json'           => '{"model":"heuristic-v1","factors":["span_distance_exceeded"]}',
    ],
];
$db->table('gis_transline_proposals')->insertBatch($proposalsData);

// 623 Temuan
$temuanBatch = [];
for ($i = 1; $i <= 623; $i++) {
    $temuanBatch[] = [
        'id'           => $i,
        'ulp_id'       => 1,
        'penyulang_id' => (($i - 1) % 28) + 1,
        'section_id'   => (($i - 1) % 134) + 1,
        'asset_id'     => (($i - 1) % 2660) + 1,
        'judul'        => 'Temuan ' . $i,
        'created_at'   => '2026-08-01 10:00:00',
    ];
    if (count($temuanBatch) === 200) {
        $db->table('temuan')->insertBatch($temuanBatch);
        $temuanBatch = [];
    }
}
if (!empty($temuanBatch)) {
    $db->table('temuan')->insertBatch($temuanBatch);
}

// 1842 Temuan Materials
$materialsBatch = [];
for ($i = 1; $i <= 1842; $i++) {
    $materialsBatch[] = [
        'id'          => $i,
        'temuan_id'   => (($i - 1) % 623) + 1,
        'material_id' => (($i - 1) % 50) + 1,
        'quantity'    => 2.0,
    ];
    if (count($materialsBatch) === 500) {
        $db->table('temuan_materials')->insertBatch($materialsBatch);
        $materialsBatch = [];
    }
}
if (!empty($materialsBatch)) {
    $db->table('temuan_materials')->insertBatch($materialsBatch);
}

$tables = [
    'gis_translines'          => 42,
    'gis_transline_proposals' => 5,
    'assets'                  => 2660,
    'sections'                => 134,
    'penyulang'               => 28,
    'temuan'                  => 623,
    'temuan_materials'        => 1842,
];

// 3. CAPTURE BEFORE STATE
$beforeCounts = [];
$beforeHashes = [];
foreach ($tables as $table => $expectedCount) {
    $rows = $db->table($table)->orderBy('id', 'ASC')->get()->getResultArray();
    $cnt = count($rows);
    $beforeCounts[$table] = $cnt;
    $beforeHashes[$table] = hash('sha256', json_encode($rows));
    if ($cnt !== $expectedCount) {
        throw new Exception("Mismatch in seeded count for {$table}: expected {$expectedCount}, got {$cnt}");
    }
}

echo "✅ Baseline counts verified across all 7 operational tables." . PHP_EOL;

// 4. EXECUTE FULL D4A READ-ONLY WORKBENCH PIPELINE
$service = new TranslineProposalReviewService($db);

echo "Executing D4A Workbench operations..." . PHP_EOL;

// 4.1. Detail retrieval on multiple proposals
$details = [];
for ($pId = 1; $pId <= 5; $pId++) {
    $detail = $service->getProposalWorkbenchDetail($pId);
    $details[$pId] = $detail;
    if ($detail['status'] !== 'success') {
        throw new Exception("Workbench detail failed for proposal #{$pId}: " . ($detail['message'] ?? ''));
    }
    // Verify policy locks
    if ($detail['policy_locks']['can_confirm'] !== false ||
        $detail['policy_locks']['can_rollback'] !== false ||
        $detail['policy_locks']['can_reject'] !== false ||
        $detail['policy_locks']['can_mutate_asset'] !== false) {
        throw new Exception("Policy locks not enforced on proposal #{$pId}");
    }
}
echo "  -> getProposalWorkbenchDetail executed for 5 proposals: PASS" . PHP_EOL;

// 4.2. Exception queue retrieval across all filter configurations
$qAll = $service->getExceptionReviewQueue();
$qBlocked = $service->getExceptionReviewQueue(null, 'BLOCKED');
$qReview = $service->getExceptionReviewQueue(null, 'HUMAN_REVIEW');
$qReady = $service->getExceptionReviewQueue(null, 'READY');
$qFeeder = $service->getExceptionReviewQueue(1);

if ($qAll['status'] !== 'success' || $qBlocked['status'] !== 'success' || $qReview['status'] !== 'success') {
    throw new Exception("Exception review queue execution failed.");
}
echo "  -> getExceptionReviewQueue executed for all filters: PASS" . PHP_EOL;

// 4.3. Authoritative comparison retrieval
$cmp = $service->compareWithAuthoritativeTranslines([
    'penyulang_id'            => 1,
    'section_id'              => 1,
    'source_asset_id'         => 1,
    'target_asset_id'         => 2,
    'proposed_distance'       => 45.50,
    'proposed_conductor_type' => 'AAAC',
    'proposed_conductor_size' => '150 mm²',
]);
if ($cmp['status'] !== 'success' || !$cmp['exact_endpoints_match']) {
    throw new Exception("Authoritative transline comparison failed.");
}
echo "  -> compareWithAuthoritativeTranslines executed: PASS" . PHP_EOL;

// 5. CAPTURE AFTER STATE
$afterCounts = [];
$afterHashes = [];
$violations = [];

foreach ($tables as $table => $expectedCount) {
    $rows = $db->table($table)->orderBy('id', 'ASC')->get()->getResultArray();
    $cnt = count($rows);
    $afterCounts[$table] = $cnt;
    $afterHashes[$table] = hash('sha256', json_encode($rows));

    $countDelta = $cnt - $beforeCounts[$table];
    $hashMatch  = ($beforeHashes[$table] === $afterHashes[$table]);

    if ($countDelta !== 0) {
        $violations[] = "COUNT MUTATION on {$table}: delta {$countDelta}";
    }
    if (!$hashMatch) {
        $violations[] = "HASH MISMATCH on {$table}: content was modified";
    }
}

// 6. COMPILE AUDIT REPORT
$auditReport = [
    'audit_run_at'      => date('c'),
    'gate'              => 'TL-01 Sub-Gate D4A: Exception Review Workbench',
    'verdict'           => empty($violations) ? 'PASS_SEALED' : 'FAIL',
    'production_write'  => 'LOCKED',
    'operational_writes_detected' => count($violations),
    'violations'        => $violations,
    'baseline_reconciliation' => [],
    'policy_locks_verified'   => [
        'can_confirm'      => false,
        'can_rollback'     => false,
        'can_reject'       => false,
        'can_mutate_asset' => false,
    ],
    'subsystem_metrics' => [
        'queue_total'      => $qAll['queue_count'] ?? $qAll['summary']['total'] ?? 0,
        'ready'            => $qAll['summary']['ready'],
        'human_review'     => $qAll['summary']['human_review'],
        'blocked'          => $qAll['summary']['blocked'],
        'active'           => $qAll['summary']['active'],
        'governance_anomaly' => $qAll['summary']['governance_anomaly'],
    ],
];

foreach ($tables as $table => $count) {
    $auditReport['baseline_reconciliation'][$table] = [
        'before_count' => $beforeCounts[$table],
        'after_count'  => $afterCounts[$table],
        'delta'        => $afterCounts[$table] - $beforeCounts[$table],
        'before_hash'  => $beforeHashes[$table],
        'after_hash'   => $afterHashes[$table],
        'identical'    => ($beforeHashes[$table] === $afterHashes[$table]),
    ];
}

$auditPath = __DIR__ . '/../writable/audits/tl01_phase4_d4a_final_audit.json';
if (!is_dir(dirname($auditPath))) {
    mkdir(dirname($auditPath), 0777, true);
}
file_put_contents($auditPath, json_encode($auditReport, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo PHP_EOL . "=== AUDIT REPORT SUMMARY ===" . PHP_EOL;
echo "Verdict: " . $auditReport['verdict'] . PHP_EOL;
echo "Operational Writes Detected: " . $auditReport['operational_writes_detected'] . PHP_EOL;
echo "Policy Locks Verified: can_confirm=false, can_rollback=false, can_reject=false, can_mutate_asset=false" . PHP_EOL;
echo "Reconciliation Table:" . PHP_EOL;
foreach ($auditReport['baseline_reconciliation'] as $tbl => $rec) {
    $statusStr = $rec['identical'] ? 'IDENTICAL' : 'MUTATED';
    echo sprintf("  %-25s | Count: %4d -> %4d (delta %d) | %s\n", $tbl, $rec['before_count'], $rec['after_count'], $rec['delta'], $statusStr);
}
echo PHP_EOL . "Report saved to: " . $auditPath . PHP_EOL;

if (!empty($violations)) {
    exit(1);
}
exit(0);
