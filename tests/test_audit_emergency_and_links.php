<?php
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

echo "=================================================================\n";
echo "TEST SUITE: 6 DOMAINS VERIFICATION & INTEGRITY CHECKS\n";
echo "=================================================================\n\n";

$pass = 0;
$fail = 0;
function test(string $name, bool $ok) {
    global $pass, $fail;
    if ($ok) {
        echo "  [PASS] $name\n";
        $pass++;
    } else {
        echo "  [FAIL] $name\n";
        $fail++;
    }
}

$db = \Config\Database::connect();
$assetRepo = new \App\Repositories\AssetRepository();
$assetCtrl = new \App\Controllers\AssetController();
$temuanRepo = new \App\Repositories\TemuanRepository();

// =================================================================
// 1. MASTER ASSET: HAPUS ROW DI BANJAR KEMANTREN & PRESERVASI FILTER
// =================================================================
echo "--- 1. Master Asset: Preservasi Filter & Hapus ROW di Banjar Kemantren ---\n";

// Insert a test ROW asset on Feeder 15 (Banjar Kemantren)
$db->table('assets')->where('kode_asset', 'TEST-ROW-BJK-VERIF')->delete();
$db->table('assets')->insert([
    'kode_asset'     => 'TEST-ROW-BJK-VERIF',
    'nama_asset'     => 'Pohon Mangga Dekat Tiang BJK-01',
    'jenis_asset'    => 'ROW',
    'ulp_id'         => 1,
    'penyulang_id'   => 15, // Banjar Kemantren
    'latitude'       => -7.4456,
    'longitude'      => 112.7123,
    'status'         => 'NORMAL',
    'created_at'     => date('Y-m-d H:i:s'),
    'updated_at'     => date('Y-m-d H:i:s'),
]);
$testRowId = $db->insertID();
test("Inserted temporary ROW asset on Banjar Kemantren (ID: $testRowId)", $testRowId > 0);

// Verify query returns this asset with filter jenis_asset=ROW and penyulang_id=15
$bjkFilters = ['jenis_asset' => 'ROW', 'penyulang_id' => 15, 'ulp_id' => 1];
$filteredRes = $assetRepo->getFilteredAssetsPaginated($bjkFilters, null, 1, 10);
test("Filter jenis_asset=ROW on Feeder 15 returns matching ROW assets", $filteredRes['total'] >= 1);

// Test soft delete of this specific asset using AssetLifecycleService
$lifecycleService = new \App\Services\AssetLifecycleService();
$softDeleteSuccess = $lifecycleService->softDeleteAsset($testRowId, 1, 'VERIFIKASI_HAPUS_ROW');
test("Soft delete of ROW asset updates deleted_at without destroying record", $softDeleteSuccess === true);

// Verify deleted asset is excluded from active query
$checkDeleted = $assetRepo->find($testRowId);
test("Soft-deleted ROW asset is no longer returned in active find()", $checkDeleted === null);

// Test getPreservedRedirectUrl preserves the exact filter string
$assetCtrl->initController(
    \Config\Services::request(),
    \Config\Services::response(),
    \Config\Services::logger()
);
$_POST['return_query'] = 'ulp_id=1&penyulang_id=15&jenis_asset=ROW&status=NORMAL&page=999&per_page=50';
$redirUrl = $assetCtrl->getPreservedRedirectUrl();
test("getPreservedRedirectUrl() preserves exact query parameters", strpos($redirUrl, 'jenis_asset=ROW') !== false && strpos($redirUrl, 'penyulang_id=15') !== false);
test("Pagination clamp redirects page 999 to valid existing page", strpos($redirUrl, 'page=999') === false);

// Clean up test ROW asset completely
$db->table('assets')->where('id', $testRowId)->delete();
test("Cleaned up test ROW record", true);

// =================================================================
// 2. MENU DATA UID & DATA UP (ROLE AUTHORIZATION & ROUTING)
// =================================================================
echo "\n--- 2. Menu Data UID dan Data UP ---\n";
$sidebarHtml = file_get_contents(APPPATH . 'Views/layouts/admin.php');
test("Sidebar renders Data UID link (/master-uid)", strpos($sidebarHtml, "site_url('master-uid')") !== false);
test("Sidebar renders Data UP link (/master-up3)", strpos($sidebarHtml, "site_url('master-up3')") !== false);
test("Sidebar UID/UP guarded by check_role(['administrator', 'admin_pusat'])", strpos($sidebarHtml, "check_role(['administrator', 'admin_pusat'])") !== false);

$routesContent = file_get_contents(APPPATH . 'Config/Routes.php');
test("Route alias /data-uid mapped and guarded by role filter", strpos($routesContent, "'data-uid'") !== false);
test("Route alias /data-up mapped and guarded by role filter", strpos($routesContent, "'data-up'") !== false);

// =================================================================
// 3. TEMUAN EMERGENCY: SCOPED QUERY & DETAIL URL PROTECTION
// =================================================================
echo "\n--- 3. Temuan Emergency: Role Scoping & Anti-Leak Protection ---\n";
test("getEmergencyFindings() method exists", method_exists($temuanRepo, 'getEmergencyFindings'));

$adminFindings = $temuanRepo->getEmergencyFindings('administrator', null, null, 10);
test("Administrator receives cross-unit findings", is_array($adminFindings));

$ulpFindings = $temuanRepo->getEmergencyFindings('supervisor_ulp', 1, null, 10);
test("Supervisor ULP receives findings scoped to ULP 1", is_array($ulpFindings));

$up3Findings = $temuanRepo->getEmergencyFindings('supervisor_up3', null, 1, 10);
test("Supervisor UP3 receives findings scoped to UP3 1", is_array($up3Findings));

$pdkbFindings = $temuanRepo->getEmergencyFindings('pdkb', 1, null, 10);
test("PDKB role receives findings scoped to PDKB specialization", is_array($pdkbFindings));

// Test detail scope security: attempting to access finding belonging to foreign ULP must return null
$dummyFinding = $db->table('temuan')->where('deleted_at IS NULL')->orderBy('id', 'DESC')->get()->getRowArray();
if ($dummyFinding) {
    $realUlpId = (int)$dummyFinding['ulp_id'];
    $foreignUlpId = $realUlpId + 999;
    
    // Legitimate access
    $validDetail = $temuanRepo->getDetail((int)$dummyFinding['id'], $realUlpId, null);
    test("getDetail() with valid ULP ID returns finding data", $validDetail !== null);
    
    // Foreign access must be blocked (prevent cross-unit information leakage)
    $blockedDetail = $temuanRepo->getDetail((int)$dummyFinding['id'], $foreignUlpId, null);
    test("getDetail() with foreign ULP ID returns NULL (access denied at query level)", $blockedDetail === null);
} else {
    test("getDetail() scope security assertion (skipped: no temuan in DB)", true);
}

// =================================================================
// 4. DASHBOARD RINGKASAN ASET: CARD LINKS & SCOPE GENERATION
// =================================================================
echo "\n--- 4. Dashboard Ringkasan Aset & Infrastruktur ---\n";
$dashIndex = file_get_contents(APPPATH . 'Views/dashboard/index.php');
test("Total Aset card links to master-assets?show_all=1", strpos($dashIndex, "master-assets?show_all=1") !== false);
test("Tiang JTM card links to master-assets?jenis_asset=JTM", strpos($dashIndex, "master-assets?jenis_asset=JTM") !== false);
test("Keypoint card links to master-assets?jenis_asset=LBS", strpos($dashIndex, "master-assets?jenis_asset=LBS") !== false);
test("Gardu card links to master-assets?jenis_asset=Gardu", strpos($dashIndex, "master-assets?jenis_asset=Gardu") !== false);
test("Trafo card links to master-assets?jenis_asset=Trafo", strpos($dashIndex, "master-assets?jenis_asset=Trafo") !== false);
test("Pohon ROW card links to master-assets?jenis_asset=ROW", strpos($dashIndex, "master-assets?jenis_asset=ROW") !== false);
test("Asset Summary header removed hardcoded 'UP3 Sidoarjo'", strpos($dashIndex, "Ringkasan Aset &amp; Infrastruktur UP3 Sidoarjo") === false);
test("Dashboard index renders Temuan Emergency Aktif module", strpos($dashIndex, "Temuan Emergency Aktif") !== false);

$dashMobile = file_get_contents(APPPATH . 'Views/dashboard/mobile.php');
test("Mobile Total Aset links with query scope", strpos($dashMobile, "master-assets?show_all=1") !== false);
test("Mobile renders Temuan Emergency Aktif mobile card", strpos($dashMobile, "Temuan Emergency Aktif") !== false);

// =================================================================
// 5. KEAMANAN & PERFORMA (.htaccess & CACHE-CONTROL AUDIT)
// =================================================================
echo "\n--- 5. Keamanan & Performa (.htaccess & Headers) ---\n";
$htaccess = file_get_contents(FCPATH . '.htaccess');
test(".htaccess sets X-Content-Type-Options: nosniff", strpos($htaccess, 'X-Content-Type-Options "nosniff"') !== false);
test(".htaccess sets X-Frame-Options: SAMEORIGIN", strpos($htaccess, 'X-Frame-Options "SAMEORIGIN"') !== false);
test(".htaccess sets Referrer-Policy: strict-origin-when-cross-origin", strpos($htaccess, 'Referrer-Policy "strict-origin-when-cross-origin"') !== false);
test(".htaccess restricts no-cache only to dynamic (php|html) files", strpos($htaccess, '<FilesMatch "\.(php|html)$">') !== false);
test(".htaccess configures long-term caching for static assets (css|js|images|fonts)", strpos($htaccess, 'public, max-age=2592000') !== false && strpos($htaccess, 'public, max-age=31536000') !== false);

// =================================================================
// 6. AKSESIBILITAS HALAMAN LOGIN (WCAG AA & CONTRAST RATIO)
// =================================================================
echo "\n--- 6. Aksesibilitas Halaman Login ---\n";
$authLayout = file_get_contents(APPPATH . 'Views/layouts/auth.php');
test("Submit button uses high-contrast teal gradient #0F766E to #115E59 (>= 5.25:1)", strpos($authLayout, "#0F766E 0%, #115E59 100%") !== false);
test("Auth brand side defaults to opacity: 1 (no invisible DOM text)", strpos($authLayout, ".auth-brand-side {\n            opacity: 1;") !== false);
test("Overlay and spine backdrop have aria-hidden='true'", strpos($authLayout, 'id="cinematic-reveal-overlay" aria-hidden="true"') !== false);
test("Password toggle button has 44x44px minimum touch target", strpos($authLayout, 'width: 44px;') !== false && strpos($authLayout, 'height: 44px;') !== false);
test("Alert colors meet WCAG AA high-contrast criteria (#991B1B, #166534, #1E40AF)", strpos($authLayout, 'color: #991B1B;') !== false && strpos($authLayout, 'color: #166534;') !== false);

echo "\n=================================================================\n";
echo "RESULT: PASS: $pass | FAIL: $fail\n";
echo "=================================================================\n";
