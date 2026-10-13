<?php

declare(strict_types=1);

define('ROOTPATH', realpath(__DIR__ . '/..') . DIRECTORY_SEPARATOR);
define('APPPATH', ROOTPATH . 'app' . DIRECTORY_SEPARATOR);
define('SYSTEMPATH', ROOTPATH . 'vendor/codeigniter4/framework/system' . DIRECTORY_SEPARATOR);
define('FCPATH', ROOTPATH . 'public' . DIRECTORY_SEPARATOR);
define('COMPOSER_PATH', ROOTPATH . 'vendor/autoload.php');
define('WRITEPATH', ROOTPATH . 'writable' . DIRECTORY_SEPARATOR);
define('ENVIRONMENT', 'development');
defined('CI_DEBUG') || define('CI_DEBUG', true);

require COMPOSER_PATH;
require SYSTEMPATH . 'Common.php';

$dotenv = new \CodeIgniter\Config\DotEnv(ROOTPATH);
$dotenv->load();

use App\Services\MaterialRequisitionService;
use App\Exceptions\UnprocessableEntityException;
use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Exceptions\MethodNotAllowedException;

$db = \CodeIgniter\Database\Config::connect('default', false);
$service = new MaterialRequisitionService($db);

$results = [];

// Helper to seed a valid synthetic Work Package & Master Material if needed
$seedData = function() use ($db) {
    // 1. Ensure at least 1 master_materials row
    $mat = $db->table('master_materials')->limit(1)->get()->getRowArray();
    if ($mat === null) {
        $db->table('master_materials')->insert([
            'material_code' => 'MAT-TEST-001',
            'nama_material' => 'Kabel Skun AL 70mm',
            'satuan'        => 'PCS',
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
        $masterId = (int)$db->insertID();
    } else {
        $masterId = (int)$mat['id'];
    }

    // 2. Ensure at least 1 material_warehouses row
    $wh = $db->table('material_warehouses')->limit(1)->get()->getRowArray();
    if ($wh === null) {
        $db->table('material_warehouses')->insert([
            'warehouse_code' => 'WH-ULP-KDL',
            'warehouse_name' => 'Gudang ULP Kendal',
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);
        $warehouseId = (int)$db->insertID();
    } else {
        $warehouseId = (int)$wh['id'];
    }

    // 3. Ensure at least 1 remediation_work_packages row
    $wp = $db->table('remediation_work_packages')->limit(1)->get()->getRowArray();
    if ($wp === null) {
        $db->table('remediation_work_packages')->insert([
            'work_package_code'     => 'WP-B82-TEST-001',
            'title'                 => 'Synthetic Work Package for B8.2 Test',
            'client_operation_uuid' => 'B82-SYNTH-WP-001',
            'status'                => 'DRAFT',
            'created_by'            => 1,
            'test_mode'             => 1,
            'created_at'            => date('Y-m-d H:i:s'),
            'updated_at'            => date('Y-m-d H:i:s'),
        ]);
        $wpId = (int)$db->insertID();
    } else {
        $wpId = (int)$wp['id'];
    }

    return [$masterId, $warehouseId, $wpId];
};

list($masterId, $warehouseId, $wpId) = $seedData();

// -----------------------------------------------------------------------------
// B82-ADV-01: Valid Requisition Creation (DRAFT)
// -----------------------------------------------------------------------------
try {
    $uuid01 = 'B82-PROD-TEST-UUID-' . mt_rand(1000, 9999);
    $payload01 = [
        'work_package_id'       => $wpId,
        'warehouse_id'          => $warehouseId,
        'client_operation_uuid' => $uuid01,
        'test_mode'             => 1,
        'items'                 => [
            [
                'material_master_id' => $masterId,
                'requested_quantity' => 10.0,
                'provenance_level'   => 'RECOMMENDED',
            ]
        ]
    ];
    $pkg01 = $service->createRequisitionPackage($payload01, 1, 'FIELD_ENGINEER');
    $passed01 = ($pkg01['status'] === 'DRAFT' && count($pkg01['items']) === 1 && $pkg01['is_duplicate'] === false);
    $results['B82-ADV-01'] = [
        'description' => 'Valid Requisition Creation in DRAFT state',
        'passed'      => $passed01,
        'details'     => "Pkg ID: {$pkg01['id']}, Status: {$pkg01['status']}, Items: " . count($pkg01['items']),
    ];
} catch (\Throwable $e) {
    $results['B82-ADV-01'] = [
        'description' => 'Valid Requisition Creation in DRAFT state',
        'passed'      => false,
        'details'     => 'Exception: ' . $e->getMessage(),
    ];
}

// -----------------------------------------------------------------------------
// B82-ADV-02: Replay Protection / Idempotency (B82-G08)
// -----------------------------------------------------------------------------
try {
    $pkg02 = $service->createRequisitionPackage($payload01, 1, 'FIELD_ENGINEER');
    $passed02 = ($pkg02['id'] === $pkg01['id'] && $pkg02['is_duplicate'] === true);
    $results['B82-ADV-02'] = [
        'description' => 'Idempotent replay protection (B82-G08)',
        'passed'      => $passed02,
        'details'     => "Canonical ID matched: {$pkg02['id']}, Duplicate flag: " . ($pkg02['is_duplicate'] ? 'TRUE' : 'FALSE'),
    ];
} catch (\Throwable $e) {
    $results['B82-ADV-02'] = [
        'description' => 'Idempotent replay protection (B82-G08)',
        'passed'      => false,
        'details'     => 'Exception: ' . $e->getMessage(),
    ];
}

// -----------------------------------------------------------------------------
// B82-ADV-03: Unmapped Material Rejection (B82-G01)
// -----------------------------------------------------------------------------
try {
    $payload03 = [
        'work_package_id'       => $wpId,
        'client_operation_uuid' => 'B82-PROD-TEST-UUID-INVALID-MAT',
        'test_mode'             => 1,
        'items'                 => [
            [
                'material_master_id' => 999999, // non-existent
                'requested_quantity' => 5.0,
            ]
        ]
    ];
    $service->createRequisitionPackage($payload03, 1, 'FIELD_ENGINEER');
    $results['B82-ADV-03'] = [
        'description' => 'Unmapped material master ID rejection (B82-G01)',
        'passed'      => false,
        'details'     => 'Failed to throw 422 exception for unmapped material',
    ];
} catch (UnprocessableEntityException $e) {
    $results['B82-ADV-03'] = [
        'description' => 'Unmapped material master ID rejection (B82-G01)',
        'passed'      => true,
        'details'     => 'Correctly caught 422: ' . $e->getMessage(),
    ];
} catch (\Throwable $e) {
    $results['B82-ADV-03'] = [
        'description' => 'Unmapped material master ID rejection (B82-G01)',
        'passed'      => false,
        'details'     => 'Unexpected exception: ' . $e->getMessage(),
    ];
}

// -----------------------------------------------------------------------------
// B82-ADV-04: AI Autonomy Provenance Ceiling (B82-G03)
// -----------------------------------------------------------------------------
try {
    $payload04 = [
        'work_package_id'       => $wpId,
        'client_operation_uuid' => 'B82-PROD-TEST-UUID-AI-CEIL',
        'test_mode'             => 1,
        'items'                 => [
            [
                'material_master_id' => $masterId,
                'requested_quantity' => 2.0,
                'provenance_level'   => 'APPROVED', // AI trying to auto-approve
            ]
        ]
    ];
    $pkg04 = $service->createRequisitionPackage($payload04, 1, 'AI_AGENT');
    $itemProv = $pkg04['items'][0]['provenance_level'] ?? '';
    $passed04 = ($itemProv === 'RECOMMENDED'); // Capped at L4 RECOMMENDED
    $results['B82-ADV-04'] = [
        'description' => 'AI Autonomy Ceiling enforcement (B82-G03)',
        'passed'      => $passed04,
        'details'     => "Requested APPROVED by AI_AGENT, capped to: {$itemProv}",
    ];
} catch (\Throwable $e) {
    $results['B82-ADV-04'] = [
        'description' => 'AI Autonomy Ceiling enforcement (B82-G03)',
        'passed'      => false,
        'details'     => 'Exception: ' . $e->getMessage(),
    ];
}

// -----------------------------------------------------------------------------
// B82-ADV-05: Non-existent Parent Work Package Rejection (B82-G04)
// -----------------------------------------------------------------------------
try {
    $payload05 = [
        'work_package_id'       => 999999, // non-existent
        'client_operation_uuid' => 'B82-PROD-TEST-UUID-INVALID-WP',
        'test_mode'             => 1,
        'items'                 => [
            ['material_master_id' => $masterId, 'requested_quantity' => 1.0]
        ]
    ];
    $service->createRequisitionPackage($payload05, 1, 'FIELD_ENGINEER');
    $results['B82-ADV-05'] = [
        'description' => 'Non-existent parent Work Package rejection (B82-G04)',
        'passed'      => false,
        'details'     => 'Failed to throw 404 exception',
    ];
} catch (NotFoundException $e) {
    $results['B82-ADV-05'] = [
        'description' => 'Non-existent parent Work Package rejection (B82-G04)',
        'passed'      => true,
        'details'     => 'Correctly caught 404: ' . $e->getMessage(),
    ];
} catch (\Throwable $e) {
    $results['B82-ADV-05'] = [
        'description' => 'Non-existent parent Work Package rejection (B82-G04)',
        'passed'      => false,
        'details'     => 'Unexpected exception: ' . $e->getMessage(),
    ];
}

// -----------------------------------------------------------------------------
// B82-ADV-06: Illegal FSM Shortcut Rejection
// -----------------------------------------------------------------------------
try {
    // Attempting to skip DRAFT -> APPROVED directly
    $service->transitionStatus((int)$pkg01['id'], 'approve', [], 1, 'SUPERVISOR');
    $results['B82-ADV-06'] = [
        'description' => 'Illegal FSM shortcut rejection (409 Conflict)',
        'passed'      => false,
        'details'     => 'Failed to reject illegal shortcut DRAFT -> approve',
    ];
} catch (ConflictException $e) {
    $results['B82-ADV-06'] = [
        'description' => 'Illegal FSM shortcut rejection (409 Conflict)',
        'passed'      => true,
        'details'     => 'Correctly caught 409 Conflict: ' . $e->getMessage(),
    ];
} catch (\Throwable $e) {
    $results['B82-ADV-06'] = [
        'description' => 'Illegal FSM shortcut rejection (409 Conflict)',
        'passed'      => false,
        'details'     => 'Unexpected exception: ' . $e->getMessage(),
    ];
}

// -----------------------------------------------------------------------------
// B82-ADV-07: Rejection without Notes (B82-G07)
// -----------------------------------------------------------------------------
try {
    // Create empty draft package for rejection test
    $uuid07 = 'B82-PROD-TEST-UUID-REJ-' . mt_rand(1000, 9999);
    $pkg07 = $service->createRequisitionPackage([
        'work_package_id'       => $wpId,
        'client_operation_uuid' => $uuid07,
        'items'                 => [['material_master_id' => $masterId, 'requested_quantity' => 1.0]]
    ], 1, 'FIELD_ENGINEER');

    $service->transitionStatus((int)$pkg07['id'], 'submit', [], 1, 'FIELD_ENGINEER');
    $service->transitionStatus((int)$pkg07['id'], 'request_approval', [], 1, 'FIELD_ENGINEER');
    
    // Attempt reject without notes
    $service->transitionStatus((int)$pkg07['id'], 'reject', ['notes' => ''], 1, 'SUPERVISOR');

    $results['B82-ADV-07'] = [
        'description' => 'Rejection without notes rejection (B82-G07)',
        'passed'      => false,
        'details'     => 'Failed to throw 422 for empty rejection notes',
    ];
} catch (UnprocessableEntityException $e) {
    $results['B82-ADV-07'] = [
        'description' => 'Rejection without notes rejection (B82-G07)',
        'passed'      => true,
        'details'     => 'Correctly caught 422: ' . $e->getMessage(),
    ];
} catch (\Throwable $e) {
    $results['B82-ADV-07'] = [
        'description' => 'Rejection without notes rejection (B82-G07)',
        'passed'      => false,
        'details'     => 'Unexpected exception: ' . $e->getMessage(),
    ];
}

// -----------------------------------------------------------------------------
// B82-ADV-08: Complete 9-State Lifecycle Mutation Sequence
// -----------------------------------------------------------------------------
try {
    $uuid08 = 'B82-PROD-TEST-UUID-LIFECYCLE-' . mt_rand(1000, 9999);
    $pkg08 = $service->createRequisitionPackage([
        'work_package_id'       => $wpId,
        'warehouse_id'          => $warehouseId,
        'client_operation_uuid' => $uuid08,
        'test_mode'             => 1,
        'items'                 => [
            ['material_master_id' => $masterId, 'requested_quantity' => 5.0]
        ]
    ], 1, 'FIELD_ENGINEER');

    $t1 = $service->transitionStatus((int)$pkg08['id'], 'submit', [], 1, 'FIELD_ENGINEER');
    $t2 = $service->transitionStatus((int)$pkg08['id'], 'request_approval', [], 1, 'FIELD_ENGINEER');
    $t3 = $service->transitionStatus((int)$pkg08['id'], 'approve', [], 2, 'SUPERVISOR');
    $t4 = $service->transitionStatus((int)$pkg08['id'], 'reserve', [], 2, 'WAREHOUSE_OFFICER');
    $t5 = $service->transitionStatus((int)$pkg08['id'], 'issue', [], 2, 'WAREHOUSE_OFFICER');
    $t6 = $service->transitionStatus((int)$pkg08['id'], 'consume', [], 1, 'FIELD_ENGINEER');
    
    $evidenceSha256 = hash('sha256', 'B82-SYNTHETIC-EVIDENCE-CONTENT-TEST');
    $t7 = $service->transitionStatus((int)$pkg08['id'], 'reconcile', [
        'evidence_sha256' => $evidenceSha256,
        'notes'           => 'Verification complete'
    ], 3, 'QA_ENGINEER');

    $finalPkg = $service->getRequisitionPackage((int)$pkg08['id']);
    $passed08 = ($finalPkg['status'] === 'RECONCILED' && count($finalPkg['history']) === 8 && !empty($finalPkg['reconciliation']));

    $results['B82-ADV-08'] = [
        'description' => 'Complete 9-State FSM lifecycle sequence (DRAFT -> RECONCILED)',
        'passed'      => $passed08,
        'details'     => "Final status: {$finalPkg['status']}, Audit history entries: " . count($finalPkg['history']),
    ];
} catch (\Throwable $e) {
    $results['B82-ADV-08'] = [
        'description' => 'Complete 9-State FSM lifecycle sequence (DRAFT -> RECONCILED)',
        'passed'      => false,
        'details'     => 'Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine(),
    ];
}

// -----------------------------------------------------------------------------
// B82-ADV-09: Invalid Reconciliation Evidence Hash (B82-G06)
// -----------------------------------------------------------------------------
try {
    $uuid09 = 'B82-PROD-TEST-UUID-INVALID-HASH-' . mt_rand(1000, 9999);
    $pkg09 = $service->createRequisitionPackage([
        'work_package_id'       => $wpId,
        'warehouse_id'          => $warehouseId,
        'client_operation_uuid' => $uuid09,
        'items'                 => [['material_master_id' => $masterId, 'requested_quantity' => 1.0]]
    ], 1, 'FIELD_ENGINEER');

    $service->transitionStatus((int)$pkg09['id'], 'submit', [], 1, 'FIELD_ENGINEER');
    $service->transitionStatus((int)$pkg09['id'], 'request_approval', [], 1, 'FIELD_ENGINEER');
    $service->transitionStatus((int)$pkg09['id'], 'approve', [], 2, 'SUPERVISOR');
    $service->transitionStatus((int)$pkg09['id'], 'reserve', [], 2, 'WAREHOUSE_OFFICER');
    $service->transitionStatus((int)$pkg09['id'], 'issue', [], 2, 'WAREHOUSE_OFFICER');
    $service->transitionStatus((int)$pkg09['id'], 'consume', [], 1, 'FIELD_ENGINEER');

    // Attempt reconcile with bad hash
    $service->transitionStatus((int)$pkg09['id'], 'reconcile', ['evidence_sha256' => 'invalid-short-hash'], 3, 'QA_ENGINEER');

    $results['B82-ADV-09'] = [
        'description' => 'Invalid reconciliation evidence SHA-256 hash rejection (B82-G06)',
        'passed'      => false,
        'details'     => 'Failed to throw 422 for invalid SHA-256 hash',
    ];
} catch (UnprocessableEntityException $e) {
    $results['B82-ADV-09'] = [
        'description' => 'Invalid reconciliation evidence SHA-256 hash rejection (B82-G06)',
        'passed'      => true,
        'details'     => 'Correctly caught 422: ' . $e->getMessage(),
    ];
} catch (\Throwable $e) {
    $results['B82-ADV-09'] = [
        'description' => 'Invalid reconciliation evidence SHA-256 hash rejection (B82-G06)',
        'passed'      => false,
        'details'     => 'Unexpected exception: ' . $e->getMessage(),
    ];
}

// -----------------------------------------------------------------------------
// B82-ADV-10: Physical DELETE Rejection (B82-G05)
// -----------------------------------------------------------------------------
try {
    $service->deletePackage((int)$pkg01['id']);
    $results['B82-ADV-10'] = [
        'description' => 'Physical DELETE rejection by Service (B82-G05)',
        'passed'      => false,
        'details'     => 'Failed to throw 405 MethodNotAllowedException',
    ];
} catch (MethodNotAllowedException $e) {
    $results['B82-ADV-10'] = [
        'description' => 'Physical DELETE rejection by Service (B82-G05)',
        'passed'      => true,
        'details'     => 'Correctly caught 405: ' . $e->getMessage(),
    ];
} catch (\Throwable $e) {
    $results['B82-ADV-10'] = [
        'description' => 'Physical DELETE rejection by Service (B82-G05)',
        'passed'      => false,
        'details'     => 'Unexpected exception: ' . $e->getMessage(),
    ];
}

// Output final report JSON
$totalTests = count($results);
$passedTests = count(array_filter($results, fn($r) => $r['passed']));
$verdict = ($totalTests === $passedTests) ? 'PASS' : 'FAIL';

$output = [
    'gate'         => 'B8_2_2_SERVICE_ADVERSARIAL_SUITE',
    'timestamp'    => date('Y-m-d H:i:s T'),
    'total_tests'  => $totalTests,
    'passed_tests' => $passedTests,
    'verdict'      => $verdict,
    'test_results' => $results,
];

echo json_encode($output, JSON_PRETTY_PRINT);
exit($verdict === 'PASS' ? 0 : 1);
