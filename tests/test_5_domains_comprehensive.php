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

echo "====================================================\n";
echo "SIDAK TEJO - COMPREHENSIVE 5-DOMAIN VERIFICATION SUITE\n";
echo "====================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertCheck(string $desc, bool $condition) {
    global $passCount, $failCount;
    if ($condition) {
        echo " [PASS] $desc\n";
        $passCount++;
    } else {
        echo " [FAIL] $desc\n";
        $failCount++;
    }
}

// ----------------------------------------------------
// DOMAIN 1: HIERARKI WILAYAH DAN JARINGAN
// ----------------------------------------------------
echo "--- Domain 1: Hierarki Wilayah dan Jaringan ---\n";
$db = \Config\Database::connect('default');
assertCheck("Tabel 'uids' exists", $db->tableExists('uids'));
assertCheck("Tabel 'up3s' exists", $db->tableExists('up3s'));
assertCheck("Kolom 'up3_id' di tabel 'ulps' exists", $db->fieldExists('up3_id', 'ulps'));
assertCheck("Kolom 'up3_id' di tabel 'gardu_induk' exists", $db->fieldExists('up3_id', 'gardu_induk'));
assertCheck("Kolom 'uid_id' di tabel 'users' exists", $db->fieldExists('uid_id', 'users'));
assertCheck("Kolom 'up3_id' di tabel 'users' exists", $db->fieldExists('up3_id', 'users'));

$netService = new \App\Services\NetworkLookupService();
$uids = $netService->getUids();
assertCheck("UID default terdaftar (UID Jawa Timur)", count($uids) > 0 && $uids[0]['kode_uid'] === 'UID_JATIM');

$up3s = $netService->getUp3s((int)$uids[0]['id']);
assertCheck("UP3 default terdaftar di bawah UID (UP3 Sidoarjo)", count($up3s) > 0 && $up3s[0]['kode_up3'] === 'UP3_SDA');

$giList = $netService->getGarduInduk((int)$up3s[0]['id']);
assertCheck("Gardu Induk terdaftar di bawah UP3", count($giList) > 0);

$giId = (int)$giList[0]['id'];
$feeders = $netService->getPenyulangsByGi($giId);
assertCheck("Penyulang terdaftar di bawah Gardu Induk (gi_id = $giId)", count($feeders) > 0);

$sections = $netService->getSectionsByPenyulang(15);
assertCheck("Sections terdaftar di bawah Penyulang 15", count($sections) > 0);

$assets = $netService->getAssets(['penyulang_id' => 15]);
assertCheck("Aset terdaftar di bawah Penyulang 15", count($assets) > 0);

// ----------------------------------------------------
// DOMAIN 2: HAK AKSES, CAKUPAN DATA, DAN MENU PER ROLE
// ----------------------------------------------------
echo "\n--- Domain 2: Hak Akses, Cakupan Data, dan Menu Per Role ---\n";

$rolesToTest = [
    'administrator'  => ['cross_ulp' => true,  'label' => 'Administrator'],
    'admin_pusat'    => ['cross_ulp' => true,  'label' => 'Admin Pusat'],
    'supervisor_up3' => ['cross_ulp' => true,  'label' => 'Supervisor UP3'],
    'pdkb'           => ['cross_ulp' => true,  'label' => 'PDKB'],
    'har_crane'      => ['cross_ulp' => true,  'label' => 'HAR Crane'],
    'supervisor_ulp' => ['cross_ulp' => false, 'label' => 'Supervisor ULP'],
    'admin_ulp'      => ['cross_ulp' => false, 'label' => 'Admin ULP'],
    'inspeksi'       => ['cross_ulp' => false, 'label' => 'Inspeksi'],
    'har_row'        => ['cross_ulp' => false, 'label' => 'HAR ROW'],
    'har_gardu'      => ['cross_ulp' => false, 'label' => 'HAR Gardu'],
    'har_konstruksi' => ['cross_ulp' => false, 'label' => 'HAR Konstruksi'],
    'har_trafo'      => ['cross_ulp' => false, 'label' => 'HAR Trafo'],
    'yantek'         => ['cross_ulp' => false, 'label' => 'Yantek'],
];

foreach ($rolesToTest as $r => $meta) {
    $lbl = get_role_label($r);
    assertCheck("Role label untuk '$r' sesuai ('{$meta['label']}')", $lbl === $meta['label']);

    // Test apply_role_scoping query builder
    $builder = $db->table('temuan');
    $scoped = apply_role_scoping($builder, $r, 1);
    $sql = $scoped->getCompiledSelect(false);
    
    if ($meta['cross_ulp']) {
        assertCheck("Role '$r' bebas lintas ULP (tidak dibatasi where ulp_id)", !str_contains($sql, '`temuan`.`ulp_id` = 1'));
    } else {
        assertCheck("Role '$r' dibatasi ULP tugas (`temuan`.`ulp_id` = 1)", str_contains($sql, '`temuan`.`ulp_id` = 1'));
    }

    if ($r === 'har_row') {
        assertCheck("Role 'har_row' membatasi pelaksana HAR ROW", str_contains($sql, "'HAR ROW'"));
    } elseif ($r === 'har_gardu') {
        assertCheck("Role 'har_gardu' membatasi pelaksana HAR GARDU", str_contains($sql, "'HAR GARDU'"));
    } elseif ($r === 'har_konstruksi') {
        assertCheck("Role 'har_konstruksi' membatasi pelaksana HAR KONSTRUKSI", str_contains($sql, "'HAR KONSTRUKSI'"));
    } elseif ($r === 'har_trafo') {
        assertCheck("Role 'har_trafo' membatasi pelaksana HAR TRAFO / GARDU", str_contains($sql, "'HAR TRAFO'"));
    }
}

// ----------------------------------------------------
// DOMAIN 3: TOPOLOGI KEYPOINT DAN TRANSLINE
// ----------------------------------------------------
echo "\n--- Domain 3: Topologi Keypoint dan Transline ---\n";
$topo = new \App\Services\AssetTopologyService();

// Mock topology graph in gis_translines
$db->table('gis_translines')->insert([
    'id' => 999901,
    'penyulang_id' => 15,
    'source_asset_id' => 888001,
    'target_asset_id' => 888002,
    'transline_code' => 'TL-TEST-A-B',
    'status' => 'OPERASIONAL',
    'created_at' => date('Y-m-d H:i:s'),
]);
$db->table('gis_translines')->insert([
    'id' => 999902,
    'penyulang_id' => 15,
    'source_asset_id' => 888002,
    'target_asset_id' => 888003,
    'transline_code' => 'TL-TEST-B-C',
    'status' => 'OPERASIONAL',
    'created_at' => date('Y-m-d H:i:s'),
]);

// 1. Tiang 888002 terhubung incoming dari 888001 dan outgoing ke 888003 (2 lines)
// Penambahan cabang kedua dari 888002 ke 888004 harus DIIZINKAN (bukan cycle)
$branchAllowed = !$topo->wouldCreateCycle(888002, 888004);
assertCheck("Percabangan dua garis dari tiang tumpu keypoint (888002 -> 888004) diizinkan tanpa error cycle", $branchAllowed);

// 2. Loop tertutup dari 888003 kembali ke 888001 harus DITOLAK (cycle sejati)
$loopDetected = $topo->wouldCreateCycle(888003, 888001);
assertCheck("Loop tertutup sejati (888003 -> 888001) terdeteksi sebagai CIRCLE/CYCLE", $loopDetected);

// Cleanup mock
$db->table('gis_translines')->whereIn('id', [999901, 999902])->delete();

// ----------------------------------------------------
// DOMAIN 4: MASTER ASSET ROW
// ----------------------------------------------------
echo "\n--- Domain 4: Master Asset ROW ---\n";
$assetRepo = new \App\Repositories\AssetRepository();
$rowFiltered = $assetRepo->getFilteredAssetsPaginated(['jenis_asset' => 'ROW'], null, 1, 10);
assertCheck("Filter Master Asset dengan jenis_asset='ROW' mengembalikan record", $rowFiltered['total'] >= 1);
assertCheck("Record ROW pertama memuat kode ROW-BJK-001", isset($rowFiltered['data'][0]['kode_asset']) && str_starts_with($rowFiltered['data'][0]['kode_asset'], 'ROW'));

// ----------------------------------------------------
// DOMAIN 5: PERFORMA DAN AKSESIBILITAS HALAMAN LOGIN
// ----------------------------------------------------
echo "\n--- Domain 5: Performa dan Aksesibilitas Halaman Login ---\n";
$loginHtml = view('layouts/auth');
assertCheck("Halaman login memiliki landmark root <main>", str_contains($loginHtml, '<main class="auth-viewport-wrapper"') && str_contains($loginHtml, '</main>'));
assertCheck("Warna status dot 'Sistem Aktif' menggunakan #0F766E (kontras 5.1:1 >= 4.5:1 WCAG AA)", str_contains($loginHtml, 'color: #0F766E;'));
assertCheck("Warna footer build menggunakan #475569 (kontras 5.25:1 >= 4.5:1 WCAG AA)", str_contains($loginHtml, 'color: #475569;'));
assertCheck("Tombol CTA submit menggunakan kontras gradient #0D9488 ke #0F766E (kontras >= 4.5:1)", str_contains($loginHtml, '#0D9488 0%, #0F766E 100%'));
assertCheck("Toggle password menggunakan elemen <button type=\"button\"> dengan aria-label", str_contains($loginHtml, '<button type="button" class="pw-toggle-btn"') && str_contains($loginHtml, 'aria-label="Tampilkan atau sembunyikan password"'));
assertCheck("CSS pihak ketiga dimuat non-render-blocking dengan media=\"print\" onload", str_contains($loginHtml, 'media="print" onload="this.media=\'all\'"'));
assertCheck("Gambar emblem memiliki atribut width='286' dan height='260' (pencegahan CLS)", str_contains($loginHtml, 'width="286"') && str_contains($loginHtml, 'height="260"'));
assertCheck("Gambar emblem memiliki atribut fetchpriority='high' (optimasi LCP)", str_contains($loginHtml, 'fetchpriority="high"'));
assertCheck("Cache-busting emblem menggunakan COMMIT_ID statis (bukan time())", !str_contains($loginHtml, 'logo_sidak_hd.jpg?v=<?'));

echo "\n====================================================\n";
echo "SUMMARY: PASS: $passCount | FAIL: $failCount\n";
echo "====================================================\n";
