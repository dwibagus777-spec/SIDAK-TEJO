<?php
/**
 * Test Suite: Authoritative Asset Security, Scope Isolation & Snag Resolution
 * 
 * Verifies:
 * 1. Temuan::index() rendering with asset_id parameter (Case 1: snag/syntax error fix).
 * 2. Temuan::create() preselection, hydration, and ULP scope isolation (Case 2: "No results found" fix).
 * 3. Server-side authoritative_asset_id validation and attack vector rejection:
 *    - Attack 1: Direct cross-ULP submission by restricted role.
 *    - Attack 2: Smuggled cross-ULP asset ID.
 *    - Attack 3: Feeder-to-ULP topological mismatch.
 *    - Attack 4: Non-existent / deleted asset ID.
 * 4. Legitimate create & detail access with atomic rollback (zero test residuals).
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
helper(['url', 'form', 'app']);

$passed = 0;
$failed = 0;

function assertCheck(bool $condition, string $message) {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] " . $message . "\n";
        $passed++;
    } else {
        echo "  [FAIL] " . $message . "\n";
        $failed++;
    }
}

echo "====================================================================\n";
echo "   SIDAK TEJO: AUTHORITATIVE ASSET & SCOPE ISOLATION TEST SUITE\n";
echo "====================================================================\n\n";

$db = \Config\Database::connect();
echo "Environment Check:\n";
echo "  DB Host: {$db->hostname} | DB Name: {$db->database} | User: {$db->username}\n\n";

if ($db->hostname !== '127.0.0.1' && $db->hostname !== 'localhost') {
    die("ABORT: Not running against local database! Host is {$db->hostname}\n");
}

class TestCliUploadedFile extends \CodeIgniter\HTTP\Files\UploadedFile {
    public function isValid(): bool {
        return true;
    }
    public function getExtension(): string {
        return 'jpg';
    }
    public function move(string $targetPath, ?string $name = null, bool $overwrite = false): bool {
        $dest = rtrim($targetPath, '\\/') . '/' . ($name ?? $this->getName());
        return copy($this->path, $dest);
    }
}

// --------------------------------------------------------------------
// SECTION 1: /temuan RENDER VERIFICATION (CASE 1)
// --------------------------------------------------------------------
echo "[SECTION 1] Case 1: Checking /temuan?asset_id=3261 render across 4 roles\n";
$rolesToTest = [
    'Administrator'    => ['user_id' => 1, 'user_role' => 'administrator', 'user_ulp_id' => null],
    'Inspeksi (ULP 1)' => ['user_id' => 2, 'user_role' => 'inspeksi', 'user_ulp_id' => 1],
    'Inspeksi (ULP 2)' => ['user_id' => 3, 'user_role' => 'inspeksi', 'user_ulp_id' => 2],
    'Inspeksi (No ULP)'=> ['user_id' => 4, 'user_role' => 'inspeksi', 'user_ulp_id' => null],
];

foreach ($rolesToTest as $label => $sessData) {
    $sessData['logged_in'] = true;
    $_SESSION = $sessData;
    $session = \Config\Services::session();
    foreach ($sessData as $k => $v) { $session->set($k, $v); }

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET = ['asset_id' => '3261'];
    $uri = new \CodeIgniter\HTTP\SiteURI(new \Config\App(), 'temuan?asset_id=3261');
    $request = new \CodeIgniter\HTTP\IncomingRequest(new \Config\App(), $uri, null, new \CodeIgniter\HTTP\UserAgent());
    $request->setGlobal('get', $_GET);
    \Config\Services::injectMock('request', $request);

    try {
        $controller = new \App\Controllers\Temuan();
        $controller->initController($request, \Config\Services::response(), \Config\Services::logger());
        $html = (string)$controller->index();
        $hasError = (strpos($html, 'Whoops!') !== false || strpos($html, 'syntax error') !== false);
        assertCheck(!$hasError, "$label: Successfully rendered (" . strlen($html) . " bytes)");
    } catch (\Throwable $e) {
        assertCheck(false, "$label: Exception: " . $e->getMessage());
    }
}

// --------------------------------------------------------------------
// SECTION 2: /temuan/create PRESELECTION & SCOPE ISOLATION (CASE 2)
// --------------------------------------------------------------------
echo "\n[SECTION 2] Case 2: Preselection & ULP Scope Isolation on /temuan/create?asset_id=3261\n";
// Test 2A: Administrator (unrestricted)
{
    $sessData = ['user_id' => 1, 'user_role' => 'administrator', 'user_ulp_id' => null, 'logged_in' => true];
    $_SESSION = $sessData;
    $session = \Config\Services::session();
    foreach ($sessData as $k => $v) { $session->set($k, $v); }

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET = ['asset_id' => '3261'];
    $uri = new \CodeIgniter\HTTP\SiteURI(new \Config\App(), 'temuan/create?asset_id=3261');
    $request = new \CodeIgniter\HTTP\IncomingRequest(new \Config\App(), $uri, null, new \CodeIgniter\HTTP\UserAgent());
    $request->setGlobal('get', $_GET);
    \Config\Services::injectMock('request', $request);

    $controller = new \App\Controllers\Temuan();
    $controller->initController($request, \Config\Services::response(), \Config\Services::logger());
    $html = (string)$controller->create();

    $hasAsset = (strpos($html, 'value="3261" selected') !== false);
    $hasUlp = (strpos($html, 'value="2" selected') !== false);
    $hasFeeder = (strpos($html, 'value="52"') !== false);
    assertCheck($hasAsset && $hasUlp && $hasFeeder, "Administrator: Asset 3261, ULP 2, and Feeder 52 hydrated with preselection");
}

// Test 2B: Inspeksi ULP 1 (Cross-ULP preselection attempt for asset 3261 in ULP 2)
{
    $sessData = ['user_id' => 2, 'user_role' => 'inspeksi', 'user_ulp_id' => 1, 'logged_in' => true];
    $_SESSION = $sessData;
    $session = \Config\Services::session();
    foreach ($sessData as $k => $v) { $session->set($k, $v); }

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET = ['asset_id' => '3261', 'ulp_id' => '2'];
    $uri = new \CodeIgniter\HTTP\SiteURI(new \Config\App(), 'temuan/create?asset_id=3261&ulp_id=2');
    $request = new \CodeIgniter\HTTP\IncomingRequest(new \Config\App(), $uri, null, new \CodeIgniter\HTTP\UserAgent());
    $request->setGlobal('get', $_GET);
    \Config\Services::injectMock('request', $request);

    $controller = new \App\Controllers\Temuan();
    $controller->initController($request, \Config\Services::response(), \Config\Services::logger());
    $html = (string)$controller->create();

    $hasAssetPreselected = (strpos($html, 'value="3261" selected') !== false);
    $hasUlp2Selected = (strpos($html, 'value="2" selected') !== false);
    $hasUlp1Selected = (strpos($html, 'value="1" selected') !== false);

    assertCheck(!$hasAssetPreselected && !$hasUlp2Selected && $hasUlp1Selected, "Inspeksi ULP 1: Cross-ULP asset blocked from preselection; locked to ULP 1");
}

// --------------------------------------------------------------------
// SECTION 3: CROSS-SCOPE & PARAMETER MANIPULATION AUDIT (STORE)
// --------------------------------------------------------------------
echo "\n[SECTION 3] Security Audit: Cross-Scope & Asset Manipulation on /temuan/store\n";

// ATTACK 1: Inspeksi ULP 1 submits directly for ULP 2
{
    $sessData = ['user_id' => 2, 'user_role' => 'inspeksi', 'user_ulp_id' => 1, 'logged_in' => true];
    $_SESSION = $sessData;
    $session = \Config\Services::session();
    foreach ($sessData as $k => $v) { $session->set($k, $v); }

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [
        'ulp_id' => 2,
        'penyulang_id' => 52,
        'section_id' => 196,
        'authoritative_asset_id' => 3261,
        'jenis_temuan' => 'ROW',
        'pelaksana' => 'HAR ROW',
        'prioritas' => 'MEDIUM',
        'potensi_gangguan' => 'OCR',
        'konduktor' => 'AAAC 150 mm²',
        'alamat' => 'Jl. Attack 1',
        'tanggal_temuan' => date('Y-m-d'),
        'detail_temuan' => 'Attack 1 attempt'
    ];
    $_REQUEST = $_POST;
    \Config\Services::reset(true);
    $request = \Config\Services::request();

    $controller = new \App\Controllers\Temuan();
    $controller->initController($request, \Config\Services::response(), \Config\Services::logger());
    $res = $controller->store();

    $flashErr = $session->getFlashdata('error') ?: '';
    $isRejected = ($res instanceof \CodeIgniter\HTTP\RedirectResponse && strpos($flashErr, 'hanya diizinkan menginput temuan untuk ULP Anda') !== false);
    assertCheck($isRejected, "Attack 1 (Cross-ULP direct submit): Rejected properly ('$flashErr')");
}

// ATTACK 2: Inspeksi ULP 1 submits for ULP 1, but smuggles authoritative_asset_id = 3261 (asset in ULP 2)
{
    $sessData = ['user_id' => 2, 'user_role' => 'inspeksi', 'user_ulp_id' => 1, 'logged_in' => true];
    $_SESSION = $sessData;
    $session = \Config\Services::session();
    foreach ($sessData as $k => $v) { $session->set($k, $v); }

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [
        'ulp_id' => 1,
        'penyulang_id' => 15, // Feeder in ULP 1
        'section_id' => 48,   // Valid section in Feeder 15 (ULP 1)
        'authoritative_asset_id' => 3261, // Asset in ULP 2 / Feeder 52
        'jenis_temuan' => 'ROW',
        'pelaksana' => 'HAR ROW',
        'prioritas' => 'MEDIUM',
        'potensi_gangguan' => 'OCR',
        'konduktor' => 'AAAC 150 mm²',
        'alamat' => 'Jl. Attack 2',
        'tanggal_temuan' => date('Y-m-d'),
        'detail_temuan' => 'Attack 2 attempt'
    ];
    $_REQUEST = $_POST;
    \Config\Services::reset(true);
    $request = \Config\Services::request();

    $controller = new \App\Controllers\Temuan();
    $controller->initController($request, \Config\Services::response(), \Config\Services::logger());
    $res = $controller->store();

    $flashErr = $session->getFlashdata('error') ?: '';
    $isRejected = ($res instanceof \CodeIgniter\HTTP\RedirectResponse && (strpos($flashErr, 'tidak memiliki hak akses') !== false || strpos($flashErr, 'tidak berada dalam ULP') !== false));
    assertCheck($isRejected, "Attack 2 (Smuggled cross-ULP asset ID): Rejected properly ('$flashErr')");
}

// ATTACK 3: Administrator submits with mismatched feeder-to-ULP (feeder in ULP 2, but ulp_id set to 1)
{
    $sessData = ['user_id' => 1, 'user_role' => 'administrator', 'user_ulp_id' => null, 'logged_in' => true];
    $_SESSION = $sessData;
    $session = \Config\Services::session();
    foreach ($sessData as $k => $v) { $session->set($k, $v); }

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [
        'ulp_id' => 1,
        'penyulang_id' => 52, // Feeder 52 is in ULP 2!
        'section_id' => 196,
        'jenis_temuan' => 'ROW',
        'pelaksana' => 'HAR ROW',
        'prioritas' => 'MEDIUM',
        'potensi_gangguan' => 'OCR',
        'konduktor' => 'AAAC 150 mm²',
        'alamat' => 'Jl. Attack 3',
        'tanggal_temuan' => date('Y-m-d'),
        'detail_temuan' => 'Attack 3 attempt'
    ];
    $_REQUEST = $_POST;
    \Config\Services::reset(true);
    $request = \Config\Services::request();

    $controller = new \App\Controllers\Temuan();
    $controller->initController($request, \Config\Services::response(), \Config\Services::logger());
    $res = $controller->store();

    $flashErr = $session->getFlashdata('error') ?: '';
    $isRejected = ($res instanceof \CodeIgniter\HTTP\RedirectResponse && strpos($flashErr, 'tidak berada dalam ULP yang dipilih') !== false);
    assertCheck($isRejected, "Attack 3 (Mismatched Feeder-to-ULP): Rejected properly ('$flashErr')");
}

// ATTACK 4: Submitting a non-existent asset ID
{
    $sessData = ['user_id' => 1, 'user_role' => 'administrator', 'user_ulp_id' => null, 'logged_in' => true];
    $_SESSION = $sessData;
    $session = \Config\Services::session();
    foreach ($sessData as $k => $v) { $session->set($k, $v); }

    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [
        'ulp_id' => 2,
        'penyulang_id' => 52,
        'section_id' => 196,
        'authoritative_asset_id' => 99999999, // Non-existent
        'jenis_temuan' => 'ROW',
        'pelaksana' => 'HAR ROW',
        'prioritas' => 'MEDIUM',
        'potensi_gangguan' => 'OCR',
        'konduktor' => 'AAAC 150 mm²',
        'alamat' => 'Jl. Attack 4',
        'tanggal_temuan' => date('Y-m-d'),
        'detail_temuan' => 'Attack 4 attempt'
    ];
    $_REQUEST = $_POST;
    \Config\Services::reset(true);
    $request = \Config\Services::request();

    $controller = new \App\Controllers\Temuan();
    $controller->initController($request, \Config\Services::response(), \Config\Services::logger());
    $res = $controller->store();

    $flashErr = $session->getFlashdata('error') ?: '';
    $isRejected = ($res instanceof \CodeIgniter\HTTP\RedirectResponse && strpos($flashErr, 'tidak ditemukan atau sudah tidak aktif') !== false);
    assertCheck($isRejected, "Attack 4 (Non-existent asset ID): Rejected properly ('$flashErr')");
}

// --------------------------------------------------------------------
// SECTION 4: LEGITIMATE FLOW WITH TRANSACTION ROLLBACK (ZERO RESIDUAL)
// --------------------------------------------------------------------
echo "\n[SECTION 4] Legitimate Create & Detail Access with Transaction Rollback\n";

$initialCount = (int)$db->query("SELECT COUNT(*) as cnt FROM temuan")->getRow()->cnt;
$db->transBegin();

try {
    // 4A: Legitimate Administrator input for Asset 3261 (ULP 2 / Feeder 52 / Section 196)
    $tmpImg = tempnam(sys_get_temp_dir(), 'test_valid_') . '.jpg';
    $im = imagecreatetruecolor(50, 50);
    imagejpeg($im, $tmpImg);
    imagedestroy($im);

    $testFile = new TestCliUploadedFile($tmpImg, 'legit.jpg', 'image/jpeg', filesize($tmpImg), UPLOAD_ERR_OK, true);

    $sessData = ['user_id' => 1, 'user_role' => 'administrator', 'user_ulp_id' => null, 'logged_in' => true];
    $_SESSION = $sessData;
    $session = \Config\Services::session();
    foreach ($sessData as $k => $v) { $session->set($k, $v); }

    $data = [
        'ulp_id' => 2,
        'penyulang_id' => 52,
        'section_id' => 196,
        'asset_id' => 3261,
        'authoritative_asset_id' => 3261,
        'jenis_temuan' => 'ROW',
        'pelaksana' => 'HAR ROW',
        'prioritas' => 'MEDIUM',
        'potensi_gangguan' => 'OCR',
        'konduktor' => 'AAAC 150 mm²',
        'tanggal_temuan' => date('Y-m-d'),
        'detail_temuan' => 'Legitimate test finding',
        'alamat' => 'Jl. Legitimate',
        'material' => 'Tidak ada spesifikasi material'
    ];

    $service = new \App\Services\TemuanService();
    $resAdmin = $service->createTemuan($data, [$testFile]);

    if (!empty($resAdmin['success']) && !empty($resAdmin['id'])) {
        $adminFindingId = (int)$resAdmin['id'];
        assertCheck(true, "Legitimate Admin Store: Created finding ID $adminFindingId");

        // Access detail
        $detailController = new \App\Controllers\Temuan();
        $detailRes = $detailController->detail($adminFindingId);
        $canAccess = (!($detailRes instanceof \CodeIgniter\HTTP\RedirectResponse) && strpos((string)$detailRes, 'tidak ditemukan atau Anda tidak memiliki akses') === false);
        assertCheck($canAccess, "Legitimate Admin Detail: Detail page accessed successfully (" . strlen((string)$detailRes) . " bytes)");
    } else {
        assertCheck(false, "Legitimate Admin Store: Failed! " . ($resAdmin['message'] ?? ''));
    }

    // 4B: Legitimate Inspeksi ULP 2 input for Asset 3261 (ULP 2 / Feeder 52 / Section 196)
    $sessData = ['user_id' => 3, 'user_role' => 'inspeksi', 'user_ulp_id' => 2, 'logged_in' => true];
    $_SESSION = $sessData;
    $session = \Config\Services::session();
    foreach ($sessData as $k => $v) { $session->set($k, $v); }

    $resInsp = $service->createTemuan($data, [$testFile]);
    if (!empty($resInsp['success']) && !empty($resInsp['id'])) {
        $inspFindingId = (int)$resInsp['id'];
        assertCheck(true, "Legitimate Inspeksi ULP 2 Store: Created finding ID $inspFindingId");

        // Access detail as Inspeksi ULP 2
        $detailController = new \App\Controllers\Temuan();
        $detailResInsp = $detailController->detail($inspFindingId);
        $canAccessInsp = (!($detailResInsp instanceof \CodeIgniter\HTTP\RedirectResponse) && strpos((string)$detailResInsp, 'tidak ditemukan atau Anda tidak memiliki akses') === false);
        assertCheck($canAccessInsp, "Legitimate Inspeksi ULP 2 Detail: Detail page accessed successfully (" . strlen((string)$detailResInsp) . " bytes)");
    } else {
        assertCheck(false, "Legitimate Inspeksi ULP 2 Store: Failed! " . ($resInsp['message'] ?? ''));
    }

    @unlink($tmpImg);
} catch (\Throwable $e) {
    assertCheck(false, "Exception in Section 4: " . $e->getMessage());
} finally {
    // ALWAYS ROLLBACK to preserve pristine state
    $db->transRollback();
    echo "\n  [TRANSACTION ROLLBACK EXECUTED] All rows from Section 4 rolled back.\n";
}

// Clean any activity / audit logs created during section 4 test
$db->query("DELETE FROM audit_logs WHERE aktivitas = 'CREATE_TEMUAN' AND created_at >= NOW() - INTERVAL 1 MINUTE");

// Verify that count of temuan is unaffected
$finalMax = $db->query("SELECT MAX(id) as max_id, COUNT(*) as cnt FROM temuan")->getRowArray();
$countUnchanged = ((int)$finalMax['cnt'] === $initialCount);
assertCheck($countUnchanged, "Pristine State Verified: Temuan table total count: {$finalMax['cnt']} | Max ID: {$finalMax['max_id']}");

echo "\n====================================================================\n";
echo "   TEST SUMMARY: $passed PASSED, $failed FAILED\n";
echo "====================================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
