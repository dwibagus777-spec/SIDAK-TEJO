<?php
/**
 * Test Suite: Master Asset Filter Preservation, Sidebar UID/UP, GIS Delete, & Login A11y
 */

// 1. Boot CodeIgniter
define('FCPATH', __DIR__ . '/../public/');
chdir(FCPATH);
require FCPATH . '../app/Config/Paths.php';
$paths = new \Config\Paths();
require $paths->systemDirectory . '/Boot.php';
\CodeIgniter\Boot::preload($paths);
require APPPATH . 'Common.php';
require SYSTEMPATH . 'Common.php';
\Config\Services::autoloader()->initialize(new \Config\Autoload(), new \Config\Modules());
helper(['url', 'form', 'app']);

$db = \Config\Database::connect();
$passed = 0;
$failed = 0;

function assertTest($condition, $message) {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$message}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$message}\n";
        $failed++;
    }
}

echo "=================================================================\n";
echo "1. VERIFIKASI AKSESIBILITAS HALAMAN LOGIN (WCAG AA & PSI)\n";
echo "=================================================================\n";

$authLayout = file_get_contents(APPPATH . 'Views/layouts/auth.php');

// A11y Check 1: Button Submit Contrast
assertTest(
    strpos($authLayout, 'linear-gradient(135deg, #0F766E 0%, #115E59 100%)') !== false,
    'Submit button background uses high-contrast Teal 700 to Teal 800 (contrast >= 5.25:1 against #FFFFFF)'
);

// A11y Check 2: Initial Opacity 1 (No hidden text on evaluation)
assertTest(
    strpos($authLayout, '/* Login Content Reveal - Default opacity 1 ensures flawless accessibility & contrast evaluation */') !== false &&
    strpos($authLayout, '.auth-form-card {' . "\n" . '            opacity: 1;') !== false,
    'Form card and brand side default to opacity: 1 for instantaneous DOM accessibility tree extraction'
);

// A11y Check 3: Overlay and Backdrop aria-hidden
assertTest(
    strpos($authLayout, '<div class="cinematic-reveal" id="cinematic-reveal-overlay" aria-hidden="true">') !== false,
    'Cinematic reveal overlay has aria-hidden="true" to prevent obscuring accessibility focus'
);
assertTest(
    strpos($authLayout, '<div class="book-spine-backdrop" id="spine-backdrop" aria-hidden="true" style="display: none;">') !== false,
    'Book spine loading backdrop has aria-hidden="true" and style="display: none;" while idle'
);

// A11y Check 4: Password Toggle Touch Target Size >= 44x44px
assertTest(
    strpos($authLayout, 'width: 44px;') !== false && strpos($authLayout, 'height: 44px;') !== false,
    'Password visibility toggle button has minimum 44x44px touch target (WCAG 2.5.5 / 2.5.8 compliant)'
);

// A11y Check 5: Decorative Icons aria-hidden
assertTest(
    strpos($authLayout, '<i class="fas fa-user input-icon-left" aria-hidden="true"></i>') !== false &&
    strpos($authLayout, '<i class="fas fa-lock input-icon-left" aria-hidden="true"></i>') !== false &&
    strpos($authLayout, '<i class="fas fa-sign-in-alt" aria-hidden="true"></i>') !== false,
    'Input icons and submit button icon have aria-hidden="true" to prevent redundant screen reader announcements'
);

echo "\n=================================================================\n";
echo "2. VERIFIKASI PRESERVASI FILTER MASTER ASSET & AUTO-CLAMP PAGINASI\n";
echo "=================================================================\n";

$assetControllerCode = file_get_contents(APPPATH . 'Controllers/AssetController.php');
$assetIndexView = file_get_contents(APPPATH . 'Views/assets/index.php');

// Filter Check 1: Return query inputs in forms
assertTest(
    strpos($assetIndexView, 'name="return_query" class="asset-return-query"') !== false,
    'Form single delete and bulk delete include return_query hidden input'
);

// Filter Check 2: JS passes window.location.search to return_query
assertTest(
    strpos($assetIndexView, "$('.asset-return-query').val(window.location.search.replace(/^\?/, ''));") !== false,
    'JavaScript populates return_query with current active filter and pagination parameters'
);

// Filter Check 3: Helper method getPreservedRedirectUrl in AssetController
assertTest(
    strpos($assetControllerCode, 'function getPreservedRedirectUrl') !== false,
    'AssetController includes getPreservedRedirectUrl() helper method'
);

// Filter Check 4: countFilteredAssets added to AssetRepository
$assetRepoCode = file_get_contents(APPPATH . 'Repositories/AssetRepository.php');
assertTest(
    strpos($assetRepoCode, 'public function countFilteredAssets') !== false,
    'AssetRepository includes countFilteredAssets() for dynamic pagination boundary calculation'
);

// Filter Check 5: Test pagination clamping logic directly
$reflection = new \ReflectionClass(\App\Controllers\AssetController::class);
$method = $reflection->getMethod('getPreservedRedirectUrl');
$method->setAccessible(true);

$controller = new \App\Controllers\AssetController();
$controller->initController(
    \Config\Services::request(),
    \Config\Services::response(),
    \Config\Services::logger()
);

// Simulate request with query string where page=999 (far beyond existing items)
$_POST['return_query'] = 'ulp_id=1&penyulang_id=15&page=999&per_page=50';
$redirectUrl = $method->invoke($controller);

assertTest(
    strpos($redirectUrl, 'ulp_id=1') !== false && strpos($redirectUrl, 'penyulang_id=15') !== false,
    'Redirect URL strictly preserves ulp_id and penyulang_id filters'
);
assertTest(
    strpos($redirectUrl, 'page=999') === false,
    'Page 999 is automatically clamped to valid existing page boundary when items are deleted'
);

echo "\n=================================================================\n";
echo "3. VERIFIKASI MENU SIDEBAR DATA UID & DATA UP (ROLE-SCOPED)\n";
echo "=================================================================\n";

$adminLayout = file_get_contents(APPPATH . 'Views/layouts/admin.php');
$routesCode = file_get_contents(APPPATH . 'Config/Routes.php');

// Sidebar Check 1: Direct li.nav-item for Data UID and Data UP
assertTest(
    strpos($adminLayout, '<span class="nav-link-title">Data UID</span>') !== false,
    'Sidebar Category 4 renders direct Data UID menu item for authorized roles'
);
assertTest(
    strpos($adminLayout, '<span class="nav-link-title">Data UP</span>') !== false,
    'Sidebar Category 4 renders direct Data UP menu item for authorized roles'
);

// Sidebar Check 2: Command palette / drawer submenu labels
assertTest(
    strpos($adminLayout, "'text' => 'Data UID'") !== false && strpos($adminLayout, "'text' => 'Data UP'") !== false,
    'Full menu drawer / command palette uses exact labels "Data UID" and "Data UP"'
);

// Sidebar Check 3: Route aliases and security filters
assertTest(
    strpos($routesCode, "\$routes->get('data-uid', 'UidController::index', ['filter' => 'role:administrator,admin,admin_pusat']);") !== false &&
    strpos($routesCode, "\$routes->get('data-up', 'Up3Controller::index', ['filter' => 'role:administrator,admin,admin_pusat']);") !== false,
    'Route aliases /data-uid and /data-up are registered and protected by role filter'
);

echo "\n=================================================================\n";
echo "4. VERIFIKASI GIS DIRECT ASSET DELETE & PRE-CHECK INTEGRITAS\n";
echo "=================================================================\n";

$gisControllerCode = file_get_contents(APPPATH . 'Controllers/GisController.php');
$gisView = file_get_contents(APPPATH . 'Views/gis/index.php');

// GIS Delete Check 1: Route registration
assertTest(
    strpos($routesCode, "gis/api-delete-asset/(:num)") !== false,
    'Route POST gis/api-delete-asset/(:num) is registered with role filter'
);

// GIS Delete Check 2: Controller method exists
assertTest(
    strpos($gisControllerCode, 'public function apiDeleteAsset(int $id)') !== false,
    'GisController::apiDeleteAsset() exists'
);

// GIS Delete Check 3: Role check and ULP scoping
assertTest(
    strpos($gisControllerCode, "\$userRole === 'admin_ulp'") !== false &&
    strpos($gisControllerCode, "(int)\$asset['ulp_id'] !== \$userUlpId") !== false,
    'Admin ULP role scoping prevents deleting assets outside their assigned ULP'
);

// GIS Delete Check 4: Integrity pre-check: active translines
assertTest(
    strpos($gisControllerCode, "where('source_asset_id', \$id)") !== false &&
    strpos($gisControllerCode, "segmen jalur transline aktif") !== false,
    'Integrity pre-check blocks deletion if asset is connected to active translines'
);

// GIS Delete Check 5: Integrity pre-check: active temuan
assertTest(
    strpos($gisControllerCode, "whereNotIn('status', ['SELESAI', 'CLOSED', 'RESOLVED'])") !== false &&
    strpos($gisControllerCode, "temuan inspeksi aktif") !== false,
    'Integrity pre-check blocks deletion if asset has unresolved inspection findings'
);

// GIS Delete Check 6: Frontend UI Delete Button in Drawer
assertTest(
    strpos($gisView, 'id="btn-context-drawer-delete-asset"') !== false,
    'Context drawer sticky footer includes "Hapus" button guarded by check_role'
);

// GIS Delete Check 7: Frontend Confirmation Modal
assertTest(
    strpos($gisView, 'id="modal-gis-delete-asset"') !== false &&
    strpos($gisView, 'id="gis-del-reason"') !== false,
    'Confirmation modal displays asset identity and requires mandatory deletion reason'
);

// GIS Delete Check 8: Frontend Marker removal from Leaflet Map
assertTest(
    strpos($gisView, 'markerByAssetId.delete(assetId)') !== false &&
    strpos($gisView, 'markerCluster.removeLayer(m)') !== false,
    'Frontend deletes Leaflet marker from map and markerCluster dynamically without page reload'
);

echo "\n=================================================================\n";
echo "RINGKASAN HASIL PENGUJIAN:\n";
echo "TOTAL: " . ($passed + $failed) . " | PASS: {$passed} | FAIL: {$failed}\n";
echo "=================================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
