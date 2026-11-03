<?php
/**
 * Test Suite: Pelaksana Ecosystem Consistency & Drilldown Filter Verification
 * Validates:
 * 1. Authoritative Pelaksana options in all dropdowns & validation rules
 * 2. URL parameter parsing (?penyulang_id=31&pelaksana=HAR%20GARDU&status=BELUM_SELESAI)
 * 3. Status mapping ('BELUM_SELESAI' -> status != 'SELESAI')
 * 4. Pelaksana query normalization ('HAR GARDU', 'HAR_GARDU')
 * 5. Isolation: Filtering for HAR GARDU never leaks or returns ROW-only findings
 * 6. Top 10 Feeders pelaksana badges drilldown URLs
 */

define('FCPATH', __DIR__ . '/../public/');
chdir(FCPATH);
require FCPATH . '../app/Config/Paths.php';
$paths = new \Config\Paths();
require $paths->systemDirectory . '/Boot.php';
\CodeIgniter\Boot::preload($paths);
require APPPATH . 'Common.php';
require SYSTEMPATH . 'Common.php';
\Config\Services::autoloader()->initialize(new \Config\Autoload(), new \Config\Modules());
require_once APPPATH . 'Helpers/app_helper.php';

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $message) {
    global $passed, $failed;
    if ($condition) {
        echo " [PASS] " . $message . "\n";
        $passed++;
    } else {
        echo " [FAIL] " . $message . "\n";
        $failed++;
    }
}

echo "====================================================================\n";
echo "  SIDAK TEJO — TEST SUITE: PELAKSANA CONSISTENCY & DRILLDOWN FILTER\n";
echo "====================================================================\n\n";

$db = \Config\Database::connect();

// ---------------------------------------------------------
// 1. Audit Dropdowns for Authoritative Pelaksana Whitelist
// ---------------------------------------------------------
echo "--- 1. AUDITING PELAKSANA DROPDOWNS & VALIDATION RULES ---\n";

$expectedPelaksanas = ['PDKB', 'HAR GARDU', 'HAR GTT', 'HAR KONSTRUKSI', 'HAR ROW', 'HAR CRANE', 'YANTEK'];

// A. app/Views/temuan/index.php
$indexView = file_get_contents(APPPATH . 'Views/temuan/index.php');
foreach ($expectedPelaksanas as $pel) {
    assertTest(
        strpos($indexView, 'value="' . $pel . '"') !== false,
        "temuan/index.php filter_pelaksana contains option '$pel'"
    );
}
assertTest(
    strpos($indexView, 'selectedPelaksana') !== false,
    "temuan/index.php checks \$selectedPelaksana for pre-selection"
);
assertTest(
    strpos($indexView, 'BELUM_SELESAI') !== false,
    "temuan/index.php filter_status checks for 'BELUM_SELESAI' pre-selection"
);

// B. app/Views/temuan/update_pekerjaan.php
$updateView = file_get_contents(APPPATH . 'Views/temuan/update_pekerjaan.php');
foreach ($expectedPelaksanas as $pel) {
    assertTest(
        strpos($updateView, 'value="' . $pel . '"') !== false,
        "temuan/update_pekerjaan.php filter_pelaksana contains option '$pel'"
    );
}

// C. app/Views/temuan/create.php
$createView = file_get_contents(APPPATH . 'Views/temuan/create.php');
foreach ($expectedPelaksanas as $pel) {
    assertTest(
        strpos($createView, 'value="' . $pel . '"') !== false,
        "temuan/create.php pelaksana select contains option '$pel'"
    );
}
assertTest(
    strpos($createView, 'HAR GTT || old(\'pelaksana\') === \'HAR GARDU\'') === false,
    "temuan/create.php does NOT conflate HAR GARDU into HAR GTT"
);

// D. app/Views/temuan/edit.php
$editView = file_get_contents(APPPATH . 'Views/temuan/edit.php');
foreach ($expectedPelaksanas as $pel) {
    assertTest(
        strpos($editView, 'value="' . $pel . '"') !== false,
        "temuan/edit.php pelaksana select contains option '$pel'"
    );
}
assertTest(
    strpos($editView, 'HAR GTT || $pl === \'HAR GARDU\'') === false,
    "temuan/edit.php does NOT conflate HAR GARDU into HAR GTT"
);

// E. app/Views/laporan/index.php
$laporanView = file_get_contents(APPPATH . 'Views/laporan/index.php');
foreach ($expectedPelaksanas as $pel) {
    assertTest(
        strpos($laporanView, 'value="' . $pel . '"') !== false,
        "laporan/index.php pelaksana select contains option '$pel'"
    );
}

// F. Validation rules in Temuan.php
$temuanController = file_get_contents(APPPATH . 'Controllers/Temuan.php');
assertTest(
    strpos($temuanController, 'PDKB,HAR GARDU,HAR GTT,HAR KONSTRUKSI,HAR ROW,HAR CRANE,YANTEK') !== false,
    "Temuan.php getTemuanFormRules validates all 7 authoritative pelaksana categories"
);
assertTest(
    strpos($temuanController, "'har_gtt'        => 'HAR GTT'") !== false,
    "Temuan.php rolePelaksanaMap includes 'har_gtt' => 'HAR GTT'"
);
assertTest(
    strpos($temuanController, "'selectedPelaksana'") !== false,
    "Temuan.php index() passes 'selectedPelaksana' to view"
);

// ---------------------------------------------------------
// 2. Audit Helper Role Scoping & Labels
// ---------------------------------------------------------
echo "\n--- 2. AUDITING ROLE SCOPING & LABELS ---\n";

assertTest(
    get_role_label('har_gardu') === 'HAR Gardu',
    "get_role_label('har_gardu') returns 'HAR Gardu'"
);
assertTest(
    get_role_label('har_gtt') === 'HAR GTT',
    "get_role_label('har_gtt') returns 'HAR GTT'"
);

// Test apply_role_scoping for har_gtt
$builderGtt = $db->table('temuan');
$scopedGtt = apply_role_scoping($builderGtt, 'har_gtt', 1);
$sqlGtt = $scopedGtt->getCompiledSelect(false);
assertTest(
    strpos($sqlGtt, "`temuan`.`pelaksana` = 'HAR GTT'") !== false,
    "apply_role_scoping for role 'har_gtt' filters by pelaksana = 'HAR GTT'"
);

// Test apply_role_scoping for har_gardu
$builderGardu = $db->table('temuan');
$scopedGardu = apply_role_scoping($builderGardu, 'har_gardu', 1);
$sqlGardu = $scopedGardu->getCompiledSelect(false);
assertTest(
    strpos($sqlGardu, "`temuan`.`pelaksana` = 'HAR GARDU'") !== false,
    "apply_role_scoping for role 'har_gardu' filters by pelaksana = 'HAR GARDU'"
);

// ---------------------------------------------------------
// 3. Audit TemuanRepository Filter Logic (Backend Query)
// ---------------------------------------------------------
echo "\n--- 3. AUDITING TEMUAN REPOSITORY QUERY EXECUTION ---\n";

$repo = new \App\Repositories\TemuanRepository();

// A. Test Status 'BELUM_SELESAI' mapping
$dtResultBelumSelesai = $repo->getDataTables([
    'status' => 'BELUM_SELESAI',
    'start'  => 0,
    'length' => 5
]);
assertTest(
    isset($dtResultBelumSelesai['recordsTotal']) && $dtResultBelumSelesai['recordsFiltered'] > 0,
    "getDataTables with status='BELUM_SELESAI' successfully matches active findings (>0 records)"
);

// Verify that none of the returned findings have status 'SELESAI'
$hasSelesai = false;
foreach ($dtResultBelumSelesai['data'] as $row) {
    if (strtoupper($row['status'] ?? '') === 'SELESAI') {
        $hasSelesai = true;
        break;
    }
}
assertTest(!$hasSelesai, "getDataTables with status='BELUM_SELESAI' strictly excludes status 'SELESAI'");

// B. Test Pelaksana 'HAR GARDU' exact filter & underscore tolerance
$dtResultGardu = $repo->getDataTables([
    'pelaksana' => 'HAR GARDU',
    'start'     => 0,
    'length'    => 10
]);
assertTest(
    isset($dtResultGardu['data']),
    "getDataTables with pelaksana='HAR GARDU' executes cleanly"
);

$dtResultGarduUnderscore = $repo->getDataTables([
    'pelaksana' => 'HAR_GARDU',
    'start'     => 0,
    'length'    => 10
]);
assertTest(
    $dtResultGarduUnderscore['recordsFiltered'] === $dtResultGardu['recordsFiltered'],
    "getDataTables treats 'HAR_GARDU' and 'HAR GARDU' identically"
);

// C. Test combined query matching URL from report:
// ?penyulang_id=31&pelaksana=HAR%20GARDU&status=BELUM_SELESAI
$dtDrilldown = $repo->getDataTables([
    'penyulang_id' => 31,
    'pelaksana'    => 'HAR GARDU',
    'status'       => 'BELUM_SELESAI',
    'start'        => 0,
    'length'       => 10
]);
assertTest(
    isset($dtDrilldown['data']),
    "getDataTables with penyulang_id=31, pelaksana='HAR GARDU', status='BELUM_SELESAI' executes cleanly"
);

// Verify that if no HAR GARDU exists on local Feeder 31, it returns 0 rows without defaulting/leaking to ROW findings
$nonGarduFound = false;
foreach ($dtDrilldown['data'] as $row) {
    if (strtoupper(trim($row['pelaksana'] ?? '')) !== 'HAR GARDU') {
        $nonGarduFound = true;
    }
}
assertTest(
    !$nonGarduFound,
    "getDataTables with pelaksana='HAR GARDU' NEVER leaks or returns non-HAR GARDU (e.g. ROW) records"
);

// D. Test Pelaksana 'PDKB' on Feeder 31
$dtPdkb = $repo->getDataTables([
    'penyulang_id' => 31,
    'pelaksana'    => 'PDKB',
    'status'       => 'BELUM_SELESAI',
    'start'        => 0,
    'length'       => 10
]);
assertTest(
    $dtPdkb['recordsFiltered'] > 0,
    "getDataTables with penyulang_id=31, pelaksana='PDKB', status='BELUM_SELESAI' returns actual PDKB findings ({$dtPdkb['recordsFiltered']} found)"
);
$allPdkb = true;
foreach ($dtPdkb['data'] as $row) {
    if (strtoupper(trim($row['pelaksana'] ?? '')) !== 'PDKB') {
        $allPdkb = false;
    }
}
assertTest($allPdkb, "All records returned for pelaksana='PDKB' are strictly PDKB");

// ---------------------------------------------------------
// 4. Audit Dashboard Drilldown Links
// ---------------------------------------------------------
echo "\n--- 4. AUDITING DASHBOARD TOP 10 DRILLDOWN LINKS ---\n";

$dashView = file_get_contents(APPPATH . 'Views/dashboard/index.php');
assertTest(
    strpos($dashView, 'ulp_id=\' + (f.ulp_id ? \'&ulp_id=\' + f.ulp_id : \'\')') !== false ||
    strpos($dashView, '(f.ulp_id ? \'&ulp_id=\' + f.ulp_id : \'\')') !== false,
    "Dashboard modal drillUrl passes ulp_id along with penyulang_id to avoid cascade race conditions"
);

assertTest(
    strpos($dashView, 'href="<?= site_url(\'temuan\') ?>?penyulang_id=<?= $r[\'id\'] ?>&ulp_id=<?= $r[\'ulp_id\'] ?? \'\' ?>&pelaksana=<?= urlencode($pb[\'pelaksana\']) ?>&status=BELUM_SELESAI"') !== false,
    "Dashboard Top 10 pelaksana badges render direct anchor links with penyulang_id, ulp_id, pelaksana, status"
);

// ---------------------------------------------------------
// Summary
// ---------------------------------------------------------
echo "\n====================================================================\n";
echo "  TEST SUMMARY: $passed PASSED | $failed FAILED\n";
echo "====================================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
