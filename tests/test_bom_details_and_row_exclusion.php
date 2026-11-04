<?php
define('FCPATH', __DIR__ . '/../public/');
require FCPATH . '../app/Config/Paths.php';
$paths = new \Config\Paths();
require $paths->systemDirectory . '/Boot.php';
\CodeIgniter\Boot::preload($paths);
require APPPATH . 'Common.php';
require SYSTEMPATH . 'Common.php';
\Config\Services::autoloader()->initialize(new \Config\Autoload(), new \Config\Modules());

$db = \Config\Database::connect();
$session = \Config\Services::session();

echo "====================================================================\n";
echo "SIDAK TEJO: BOM DETAILS & ROW EXCLUSION REGRESSION TEST SUITE\n";
echo "====================================================================\n";

$passCount = 0;
$failCount = 0;

function assertTest(string $desc, bool $condition, string $detail = '') {
    global $passCount, $failCount;
    if ($condition) {
        echo "[PASS] $desc\n";
        $passCount++;
    } else {
        echo "[FAIL] $desc " . ($detail ? "($detail)" : "") . "\n";
        $failCount++;
    }
}

// Ensure at least one test ROW temuan exists for regression testing
$existingRow = $db->table('temuan')->where('jenis_temuan', 'ROW')->get()->getRowArray();
$insertedRowId = null;
if (!$existingRow) {
    $row455 = $db->table('temuan')->where('id', 455)->get()->getRowArray();
    if ($row455) {
        $testRow = $row455;
        unset($testRow['id']);
        $testRow['nomor_temuan'] = 'TEST-ROW-REG-999';
        $testRow['jenis_temuan'] = 'ROW';
        $testRow['pelaksana'] = 'HAR ROW';
        $testRow['material'] = '-';
        $testRow['deleted_at'] = null;
        $db->table('temuan')->insert($testRow);
        $insertedRowId = (int)$db->insertID();
    }
}

try {
    // -------------------------------------------------------------
    // TEST 1: BOM Details Endpoint for ID 455 (Valid Non-ROW finding)
    // -------------------------------------------------------------
    $_SESSION['user_id']   = 1;
    $_SESSION['user_role'] = 'administrator';
    $_SESSION['user_ulp_id'] = null;

    $controller = new \App\Controllers\Temuan();
    $controller->initController(\Config\Services::request(), \Config\Services::response(), \Config\Services::logger());

    $res455 = $controller->apiBomDetails(455);
    $code455 = $res455->getStatusCode();
    $body455 = json_decode($res455->getBody(), true);

    assertTest("ID 455 returns HTTP 200", $code455 === 200, "Code: $code455");
    assertTest("ID 455 response JSON success is true", ($body455['success'] ?? false) === true);
    assertTest("ID 455 returns valid non-empty materials list", !empty($body455['data']['materials']));
    assertTest("ID 455 jenis_temuan is KONSTRUKSI (not ROW)", ($body455['data']['jenis_temuan'] ?? '') !== 'ROW');

    // -------------------------------------------------------------
    // TEST 2: BOM Details Endpoint for ID 456 (Valid Non-ROW finding)
    // -------------------------------------------------------------
    $res456 = $controller->apiBomDetails(456);
    $code456 = $res456->getStatusCode();
    $body456 = json_decode($res456->getBody(), true);

    assertTest("ID 456 returns HTTP 200", $code456 === 200, "Code: $code456");
    assertTest("ID 456 response JSON success is true", ($body456['success'] ?? false) === true);
    assertTest("ID 456 returns valid materials", isset($body456['data']['materials']));

    // -------------------------------------------------------------
    // TEST 3: BOM Details Endpoint for ID 693 (Non-existent finding)
    // Must return structured HTTP 404, NEVER HTTP 500!
    // -------------------------------------------------------------
    $res693 = $controller->apiBomDetails(693);
    $code693 = $res693->getStatusCode();
    $body693 = json_decode($res693->getBody(), true);

    assertTest("ID 693 (non-existent) returns HTTP 404 (Not 500)", $code693 === 404, "Code: $code693");
    assertTest("ID 693 response has success=false", ($body693['success'] ?? true) === false);
    assertTest("ID 693 returns clear not found message", str_contains(strtolower($body693['message'] ?? ''), 'tidak ditemukan'));

    // -------------------------------------------------------------
    // TEST 4: BOM Details for ROW Finding (Must return HTTP 422 with is_row=true)
    // -------------------------------------------------------------
    $rowId = $existingRow ? (int)$existingRow['id'] : $insertedRowId;
    $resRow = $controller->apiBomDetails($rowId);
    $codeRow = $resRow->getStatusCode();
    $bodyRow = json_decode($resRow->getBody(), true);

    assertTest("ROW finding ID $rowId returns HTTP 422 (Not 500)", $codeRow === 422, "Code: $codeRow");
    assertTest("ROW finding response indicates is_row=true", ($bodyRow['is_row'] ?? false) === true);
    assertTest("ROW finding message explains exclusion from BOM", str_contains(strtolower($bodyRow['message'] ?? ''), 'tidak memerlukan material'));

    // -------------------------------------------------------------
    // TEST 5: Out of Scope Denial (HTTP 403, Not 500)
    // -------------------------------------------------------------
    $_SESSION['user_role'] = 'supervisor_ulp';
    $_SESSION['user_ulp_id'] = 999; // ULP that does not match ID 455 (ULP 2)
    $resScope = $controller->apiBomDetails(455);
    $codeScope = $resScope->getStatusCode();
    $bodyScope = json_decode($resScope->getBody(), true);

    assertTest("Cross-scope access returns HTTP 403 (Not 500)", $codeScope === 403, "Code: $codeScope");
    assertTest("Cross-scope response has success=false", ($bodyScope['success'] ?? true) === false);
    assertTest("Cross-scope message explains access denial", str_contains(strtolower($bodyScope['message'] ?? ''), 'akses'));

    // Restore session
    $_SESSION['user_role'] = 'administrator';
    $_SESSION['user_ulp_id'] = null;

    // -------------------------------------------------------------
    // TEST 6: DataTables Filtering with bom_only=1 vs General View
    // -------------------------------------------------------------
    $repo = new \App\Repositories\TemuanRepository();
    // Targeted test: search for the test ROW finding specifically
    $testRowNumber = $existingRow ? $existingRow['nomor_temuan'] : 'TEST-ROW-REG-999';
    $dtResultNormalWithSearch = $repo->getDataTables(['length' => 10, 'start' => 0, 'search' => ['value' => $testRowNumber]], null, null);
    assertTest("General DataTables finding list preserves ROW findings", $dtResultNormalWithSearch['recordsFiltered'] >= 1, "Count: " . $dtResultNormalWithSearch['recordsFiltered']);

    // With bom_only=1: must be completely filtered out
    $dtResultBomWithSearch = $repo->getDataTables(['length' => 10, 'start' => 0, 'bom_only' => 1, 'search' => ['value' => $testRowNumber]], null, null);
    assertTest("bom_only=1 strictly excludes ROW finding from results (0 records)", $dtResultBomWithSearch['recordsFiltered'] === 0, "Count: " . $dtResultBomWithSearch['recordsFiltered']);

    // Check broad bom_only dataset
    $dtResultBomOnly = $repo->getDataTables(['length' => 100, 'start' => 0, 'bom_only' => 1], null, null);
    $hasRowInBomOnly = false;
    $allQualifyInBomOnly = true;
    foreach ($dtResultBomOnly['data'] as $r) {
        $j = strtoupper($r['jenis_temuan'] ?? '');
        $p = strtoupper(trim(str_replace('_', ' ', (string)($r['pelaksana'] ?? ''))));
        $a = strtoupper($r['jenis_asset'] ?? '');
        if ($j === 'ROW' || str_contains($p, 'ROW') || $a === 'ROW') {
            $hasRowInBomOnly = true;
        }
        if (!in_array($p, ['PDKB', 'HAR KONSTRUKSI', 'HAR GARDU', 'HAR GTT', 'HAR CRANE'])) {
            $allQualifyInBomOnly = false;
        }
    }

    assertTest("bom_only=1 dataset has zero ROW records across all returned pages", !$hasRowInBomOnly);
    assertTest("bom_only=1 findings strictly belong to qualifying maintenance pelaksana", $allQualifyInBomOnly);

    // -------------------------------------------------------------
    // TEST 7: Dashboard BOM Material Matrix Queries (Zero ROW in BOM Table)
    // -------------------------------------------------------------
    $tmRowsSql = "
        SELECT
            t.penyulang_id,
            p.nama_penyulang,
            t.jenis_temuan,
            t.pelaksana,
            COUNT(DISTINCT t.id) AS total_temuan
        FROM temuan t
        LEFT JOIN penyulang p ON p.id = t.penyulang_id
        WHERE t.deleted_at IS NULL
          AND t.jenis_temuan != 'ROW'
          AND t.jenis_temuan NOT LIKE '%ROW%'
          AND REPLACE(UPPER(TRIM(COALESCE(t.pelaksana, ''))), '_', ' ') IN ('PDKB', 'HAR KONSTRUKSI', 'HAR GARDU', 'HAR GTT', 'HAR CRANE')
          AND t.pelaksana NOT LIKE '%ROW%'
          AND (t.asset_id IS NULL OR t.asset_id NOT IN (SELECT id FROM assets WHERE jenis_asset = 'ROW' OR kode_asset LIKE 'AST-ROW-%'))
        GROUP BY t.penyulang_id, p.nama_penyulang, t.jenis_temuan, t.pelaksana
    ";
    $tmRows = $db->query($tmRowsSql)->getResultArray();
    $anyRowInBomMatrix = false;
    foreach ($tmRows as $r) {
        if (strtoupper($r['jenis_temuan'] ?? '') === 'ROW' || stripos($r['pelaksana'] ?? '', 'ROW') !== false) {
            $anyRowInBomMatrix = true;
        }
    }
    assertTest("Dashboard BOM matrix SQL query returns 0 ROW findings", !$anyRowInBomMatrix);

    // -------------------------------------------------------------
    // TEST 8: Menu Registry contains master-uid and master-up3
    // -------------------------------------------------------------
    $menuRegistry = get_sidak_menu_registry();
    $masterItems = array_filter($menuRegistry['items'], fn($m) => $m['category'] === 'MASTER');
    $masterIds = array_column($masterItems, 'id');

    assertTest("Menu registry includes 'master-uid'", in_array('master-uid', $masterIds), "Found: " . implode(',', $masterIds));
    assertTest("Menu registry includes 'master-up3'", in_array('master-up3', $masterIds), "Found: " . implode(',', $masterIds));
    assertTest("Menu registry includes 'master-hierarchy'", in_array('master-hierarchy', $masterIds), "Found: " . implode(',', $masterIds));

    // -------------------------------------------------------------
    // TEST 9: UID and UP3 Parent-Child Relational Delete Protection
    // -------------------------------------------------------------
    $uidController = new \App\Controllers\UidController();
    $uidController->initController(\Config\Services::request(), \Config\Services::response(), \Config\Services::logger());

    $_SESSION['user_role'] = 'administrator';
    $delUidRes = $uidController->delete(1);
    $errFlash = session()->getFlashdata('error') ?? '';
    assertTest("Deleting UID with attached UP3 is blocked", str_contains(strtolower($errFlash), 'tidak dapat menghapus uid'), "Flash: $errFlash");

    $up3Controller = new \App\Controllers\Up3Controller();
    $up3Controller->initController(\Config\Services::request(), \Config\Services::response(), \Config\Services::logger());
    $delUp3Res = $up3Controller->delete(1);
    $errFlashUp3 = session()->getFlashdata('error') ?? '';
    assertTest("Deleting UP3 with attached ULPs is blocked", str_contains(strtolower($errFlashUp3), 'tidak dapat menghapus up'), "Flash: $errFlashUp3");

} finally {
    // Clean up inserted test ROW row if created
    if ($insertedRowId) {
        $db->table('temuan')->where('id', $insertedRowId)->delete();
    }
}

echo "====================================================================\n";
echo "SUMMARY: Total Tests: " . ($passCount + $failCount) . " | Passed: $passCount | Failed: $failCount\n";
echo "====================================================================\n";
exit($failCount > 0 ? 1 : 0);
