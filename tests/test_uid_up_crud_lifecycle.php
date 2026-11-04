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
echo "SIDAK TEJO: DYNAMIC UID & UP CRUD & HIERARCHY LIFECYCLE TEST\n";
echo "====================================================================\n";

$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'administrator';

$uidModel = new \App\Models\UidModel();
$up3Model = new \App\Models\Up3Model();

$pass = 0;
$fail = 0;
function t(string $name, bool $ok, string $extra = '') {
    global $pass, $fail;
    if ($ok) { echo "[PASS] $name\n"; $pass++; }
    else { echo "[FAIL] $name ($extra)\n"; $fail++; }
}

// 1. Create UID
$uidCode = 'UID_TEST_' . time();
$uidName = 'UID Uji Coba Otomatis';
$uidId = $uidModel->insert([
    'kode_uid' => $uidCode,
    'nama_uid' => $uidName,
    'status'   => 'AKTIF'
]);
t("Insert new UID into database", $uidId > 0, "ID: $uidId");

$fetchedUid = $uidModel->find($uidId);
t("Read inserted UID from database", $fetchedUid && $fetchedUid['kode_uid'] === $uidCode);

// 2. Create UP attached to UID
$upCode = 'UP3_TEST_' . time();
$upName = 'UP3 Uji Coba Wilayah';
$upId = $up3Model->insert([
    'uid_id'   => $uidId,
    'kode_up3' => $upCode,
    'nama_up3' => $upName,
    'status'   => 'AKTIF'
]);
t("Insert new UP linked to UID into database", $upId > 0, "UP ID: $upId");

$fetchedUp = $up3Model->find($upId);
t("Read inserted UP from database", $fetchedUp && (int)$fetchedUp['uid_id'] === $uidId);

// 3. Hierarchy relation integrity: UID cannot be deleted while it has UP attached
$uidController = new \App\Controllers\UidController();
$uidController->initController(\Config\Services::request(), \Config\Services::response(), \Config\Services::logger());
$uidController->delete($uidId);
$flashErr = session()->getFlashdata('error') ?? '';
t("Deleting UID with attached UP is rejected", str_contains(strtolower($flashErr), 'tidak dapat menghapus uid'), "Flash: $flashErr");
t("UID still exists in database", $uidModel->find($uidId) !== null);

// 4. Update UP
$up3Model->update($upId, ['nama_up3' => $upName . ' (Updated)']);
$updatedUp = $up3Model->find($upId);
t("Update UP name in database", str_contains($updatedUp['nama_up3'], '(Updated)'));

// 5. Delete UP
$up3Controller = new \App\Controllers\Up3Controller();
$up3Controller->initController(\Config\Services::request(), \Config\Services::response(), \Config\Services::logger());
$up3Controller->delete($upId);
$flashSuccUp = session()->getFlashdata('success') ?? '';
t("Delete UP without children succeeds", str_contains(strtolower($flashSuccUp), 'berhasil'), "Flash: $flashSuccUp");
t("UP removed from database", $up3Model->find($upId) === null);

// 6. Delete UID after UP removed
$uidController->delete($uidId);
$flashSuccUid = session()->getFlashdata('success') ?? '';
t("Delete UID without children succeeds", str_contains(strtolower($flashSuccUid), 'berhasil'), "Flash: $flashSuccUid");
t("UID removed from database", $uidModel->find($uidId) === null);

echo "====================================================================\n";
echo "SUMMARY: Tests: " . ($pass + $fail) . " | Passed: $pass | Failed: $fail\n";
echo "====================================================================\n";
exit($fail > 0 ? 1 : 0);
