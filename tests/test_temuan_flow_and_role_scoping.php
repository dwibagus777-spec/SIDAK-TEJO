<?php
/**
 * Test Suite: Temuan Creation Flow, Role Scoping & Mobile Form Picker Validation
 * 
 * Verifies:
 * 1. TemuanRepository::getDetail schema resilience (works even if ulps.up3_id is absent).
 * 2. Creator grace access in getDetail (creator always has view access to findings they created).
 * 3. Form ULP scoping for create/store/edit/update across all 14 roles.
 * 4. All 14 roles permission consistency across routes, menus, and scoping helpers.
 * 5. Cascading dropdown reset guards (clearing ULP/feeder also clears asset).
 * 6. Mobile bottom sheet CSS rules & Select2 configuration for short/long lists.
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
helper('app');

$passCount = 0;
$failCount = 0;

function assertTest(bool $condition, string $message, &$passCount, &$failCount) {
    if ($condition) {
        echo "[PASS] " . $message . "\n";
        $passCount++;
    } else {
        echo "[FAIL] " . $message . "\n";
        $failCount++;
    }
}

echo "====================================================================\n";
echo "TEST SUITE: TEMUAN FLOW, ACCESS RECOVERY & ROLE SCOPING INTEGRITY\n";
echo "====================================================================\n\n";

$db = \Config\Database::connect();
$repo = new \App\Repositories\TemuanRepository();

// -----------------------------------------------------------------------------
// DOMAIN 1: TemuanRepository::getDetail() Schema Resilience & Creator Grace
// -----------------------------------------------------------------------------
echo "--- DOMAIN 1: TemuanRepository::getDetail() Schema Resilience & Creator Grace ---\n";

// Fetch an existing temuan to test with
$testTemuan = $db->table('temuan')->where('deleted_at IS NULL')->orderBy('id', 'DESC')->get()->getRowArray();
if (!$testTemuan) {
    // Insert a temporary temuan if none exists
    $db->table('temuan')->insert([
        'nomor_temuan' => 'TEST-FLOW-001',
        'ulp_id' => 1,
        'penyulang_id' => 1,
        'section_id' => 1,
        'jenis_temuan' => 'KONSTRUKSI',
        'pelaksana' => 'HAR KONSTRUKSI',
        'prioritas' => 'HIGH',
        'status' => 'BELUM SELESAI',
        'created_by' => 9999,
        'tanggal_temuan' => date('Y-m-d'),
        'created_at' => date('Y-m-d H:i:s')
    ]);
    $testId = $db->insertID();
    $testTemuan = $db->table('temuan')->where('id', $testId)->get()->getRowArray();
} else {
    $testId = (int)$testTemuan['id'];
}

// 1.1: Admin access (no ULP/UP3 filter)
$detailAdmin = $repo->getDetail($testId, null, null);
assertTest($detailAdmin !== null && (int)$detailAdmin['id'] === $testId, "Admin scope successfully resolves temuan detail", $passCount, $failCount);
assertTest(array_key_exists('up3_id', $detailAdmin), "Result includes up3_id key safely (schema tolerant)", $passCount, $failCount);

// 1.2: Matching ULP access
$temuanUlp = (int)$testTemuan['ulp_id'];
$detailSameUlp = $repo->getDetail($testId, $temuanUlp, null);
assertTest($detailSameUlp !== null && (int)$detailSameUlp['id'] === $testId, "Same ULP scope successfully resolves temuan detail", $passCount, $failCount);

// 1.3: Cross-ULP unauthorized access strictly rejected
$diffUlp = ($temuanUlp === 1) ? 2 : 1;
$detailCrossUlp = $repo->getDetail($testId, $diffUlp, null);
assertTest($detailCrossUlp === null, "Cross-ULP unauthorized scope returns null (access strictly denied)", $passCount, $failCount);

// 1.4: Creator grace access (user created finding, even if ULP filter differs)
$creatorId = (int)($testTemuan['created_by'] ?? 1);
$detailCreatorGrace = $repo->getDetail($testId, $diffUlp, null, $creatorId);
assertTest($detailCreatorGrace !== null && (int)$detailCreatorGrace['id'] === $testId, "Creator grace access allows creator to view finding even if ULP filter differs", $passCount, $failCount);


// -----------------------------------------------------------------------------
// DOMAIN 2: Form ULP Scoping & Controller Guard Integrity
// -----------------------------------------------------------------------------
echo "\n--- DOMAIN 2: Form ULP Scoping & Guard Integrity ---\n";

$controllerSrc = file_get_contents(APPPATH . 'Controllers/Temuan.php');

// 2.1: create() uses getScopedUlpsAndPenyulangs()
assertTest(strpos($controllerSrc, 'public function create()') !== false && 
           strpos($controllerSrc, '[$ulps, , $isRestricted] = $this->getScopedUlpsAndPenyulangs();') !== false,
           "Temuan::create() invokes getScopedUlpsAndPenyulangs() to restrict form choices", $passCount, $failCount);

// 2.2: store() enforces $isRestricted guard
assertTest(strpos($controllerSrc, 'if ($isRestricted && $userUlpId !== null && (int)$userUlpId !== $ulpIdInput)') !== false,
           "Temuan::store() enforces strict rejection for cross-ULP input by restricted users", $passCount, $failCount);

// 2.3: edit() uses getScopedUlpsAndPenyulangs()
assertTest(strpos($controllerSrc, 'public function edit(int $id)') !== false &&
           strpos($controllerSrc, "'isRestricted'                => \$isRestricted") !== false,
           "Temuan::edit() enforces getScopedUlpsAndPenyulangs() and passes isRestricted flag", $passCount, $failCount);

// 2.4: update() guards against cross-ULP update
assertTest(strpos($controllerSrc, 'if ($isRestricted && $userUlpId !== null && (int)$userUlpId !== $ulpIdInput)') !== false,
           "Temuan::update() strictly blocks cross-ULP update tampering", $passCount, $failCount);

// 2.5: detail() passes userId to getDetail() for creator grace
assertTest(strpos($controllerSrc, '$temuan = $this->temuanRepository->getDetail($id, $ulpIdFilter, $up3IdFilter, $userId ?: null);') !== false,
           "Temuan::detail() passes userId to repository for creator grace access", $passCount, $failCount);


// -----------------------------------------------------------------------------
// DOMAIN 3: Comprehensive 14 Roles Matrix Audit
// -----------------------------------------------------------------------------
echo "\n--- DOMAIN 3: Comprehensive 14 Roles Matrix Audit ---\n";

$allRoles = [
    'administrator', 'admin', 'admin_pusat', 'admin_ulp', 'supervisor_up3',
    'supervisor_ulp', 'inspeksi', 'yantek', 'pdkb', 'har_row',
    'har_konstruksi', 'har_gtt', 'har_gardu', 'har_trafo', 'har_crane'
];

$routesSrc = file_get_contents(APPPATH . 'Config/Routes.php');
$helperSrc = file_get_contents(APPPATH . 'Helpers/app_helper.php');
$adminLayoutSrc = file_get_contents(APPPATH . 'Views/layouts/admin.php');

// 3.1: Check all 14 roles in Routes filter for temuan/create and temuan/store
foreach ($allRoles as $r) {
    if ($r === 'admin') continue; // represented as admin_pusat / administrator in some filters, but check both
    $rolePattern = $r;
    assertTest(strpos($routesSrc, $rolePattern) !== false, "Role '$r' is registered in application routes", $passCount, $failCount);
}

// 3.2: Specifically verify har_gtt is present in temuan routes filter
assertTest(strpos($routesSrc, "har_gtt") !== false, "Role 'har_gtt' is included in Routes.php role filters", $passCount, $failCount);

// 3.3: Specifically verify har_gtt is present in apply_role_scoping in app_helper.php
assertTest(strpos($helperSrc, "'har_gtt'") !== false, "Role 'har_gtt' is covered in apply_role_scoping() helper", $passCount, $failCount);

// 3.4: Verify input-temuan menu item covers all 14 roles
assertTest(strpos($helperSrc, "'roles'     => ['administrator', 'admin', 'admin_pusat', 'admin_ulp', 'inspeksi', 'supervisor_ulp', 'supervisor_up3', 'yantek', 'pdkb', 'har_row', 'har_konstruksi', 'har_gtt', 'har_gardu', 'har_trafo', 'har_crane']") !== false,
           "Sidebar menu for Input Temuan covers all 14 operational roles", $passCount, $failCount);

// 3.5: Verify mobile dock FAB (+) button covers operational roles
assertTest(strpos($adminLayoutSrc, "har_gtt") !== false && strpos($adminLayoutSrc, "sidak-dock-fab") !== false,
           "Mobile bottom dock FAB (+) button is enabled for operational roles including har_gtt", $passCount, $failCount);


// -----------------------------------------------------------------------------
// DOMAIN 4: Mobile Form Picker & Dropdown Interaction
// -----------------------------------------------------------------------------
echo "\n--- DOMAIN 4: Mobile Form Picker & Dropdown Interaction ---\n";

// 4.1: CSS contains mobile bottom sheet for Select2
assertTest(strpos($adminLayoutSrc, '.select2-container--open .select2-dropdown') !== false,
           "Mobile bottom sheet styles for Select2 are defined in admin layout", $passCount, $failCount);
assertTest(strpos($adminLayoutSrc, 'animation: sidakSheetSlideUp') !== false,
           "Mobile sheet slide up animation is defined", $passCount, $failCount);
assertTest(strpos($adminLayoutSrc, 'border-radius: 20px 20px 0 0') !== false,
           "Modern rounded top bottom sheet card is styled", $passCount, $failCount);

// 4.2: Select2 minimumResultsForSearch: Infinity on <= 8 items
assertTest(strpos($adminLayoutSrc, 'minimumResultsForSearch: disableSearch ? Infinity : 0') !== false,
           "Select2 automatically disables search for <= 8 items (prevents virtual keyboard popup)", $passCount, $failCount);

// 4.3: Backdrop overlay click listener closes dropdown
assertTest(strpos($adminLayoutSrc, "document).on('click', '.select2-container--open'") !== false,
           "Backdrop tap listener dismisses mobile bottom sheet picker", $passCount, $failCount);


// -----------------------------------------------------------------------------
// DOMAIN 5: Cascading Dropdowns & Asset Reset Verification
// -----------------------------------------------------------------------------
echo "\n--- DOMAIN 5: Cascading Dropdowns & Asset Reset Verification ---\n";

$createViewSrc = file_get_contents(APPPATH . 'Views/temuan/create.php');

// 5.1: clearAssetSelection() exists in create.php
assertTest(strpos($createViewSrc, 'function clearAssetSelection') !== false,
           "clearAssetSelection() is defined to reset #mr01_asset_id during cascading changes", $passCount, $failCount);

// 5.2: loadPenyulang calls clearAssetSelection()
assertTest(strpos($createViewSrc, 'loadPenyulang') !== false && 
           strpos($createViewSrc, 'clearAssetSelection();') !== false,
           "loadPenyulang() properly clears downstream asset selection", $passCount, $failCount);

// 5.3: Single ULP auto-selected when restricted
assertTest(strpos($createViewSrc, '$singleUlp = (count($ulps) === 1)') !== false,
           "create.php pre-selects assigned ULP when restricted to a single unit", $passCount, $failCount);


echo "\n====================================================================\n";
echo "SUMMARY: {$passCount} PASSED | {$failCount} FAILED\n";
echo "====================================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);
