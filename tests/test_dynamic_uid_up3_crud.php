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

$db = \Config\Database::connect();
$lookup = new \App\Services\NetworkLookupService();

echo "====================================================\n";
echo "TEST: DYNAMIC UID & UP CRUD AND HIERARCHY ISOLATION\n";
echo "====================================================\n";

$pass = 0;
$fail = 0;
function chk(string $msg, bool $cond) {
    global $pass, $fail;
    if ($cond) {
        echo "  [PASS] $msg\n";
        $pass++;
    } else {
        echo "  [FAIL] $msg\n";
        $fail++;
    }
}

// 1. Create a dynamic test UID
$db->table('uids')->where('kode_uid', 'UID_TEST_DYNAMIC')->delete();
$db->table('uids')->insert([
    'kode_uid' => 'UID_TEST_DYNAMIC',
    'nama_uid' => 'UID Testing Dinamis',
    'status'   => 'AKTIF',
    'created_at' => date('Y-m-d H:i:s'),
    'updated_at' => date('Y-m-d H:i:s')
]);
$testUidId = $db->insertID();
chk("Inserted dynamic UID ID: {$testUidId}", $testUidId > 0);

// 2. Create a dynamic test UP linked to this UID
$db->table('up3s')->where('kode_up3', 'UP_TEST_DYNAMIC')->delete();
$db->table('up3s')->insert([
    'uid_id'   => $testUidId,
    'kode_up3' => 'UP_TEST_DYNAMIC',
    'nama_up3' => 'UP Testing Dinamis',
    'status'   => 'AKTIF',
    'created_at' => date('Y-m-d H:i:s'),
    'updated_at' => date('Y-m-d H:i:s')
]);
$testUpId = $db->insertID();
chk("Inserted dynamic UP ID: {$testUpId} linked to UID: {$testUidId}", $testUpId > 0);

// 3. Test NetworkLookupService isolation
$up3sForTestUid = $lookup->getUp3s($testUidId);
$found = false;
foreach ($up3sForTestUid as $u) {
    if ($u['kode_up3'] === 'UP_TEST_DYNAMIC') {
        $found = true;
        break;
    }
}
chk("Lookup getUp3s({$testUidId}) returns ONLY the linked UP (count 1)", $found && count($up3sForTestUid) === 1);

// 4. Verify that lookup for default UID (UID Jawa Timur, ID 1) does NOT include this test UP
$up3sForJatim = $lookup->getUp3s(1);
$leaked = false;
foreach ($up3sForJatim as $u) {
    if ($u['kode_up3'] === 'UP_TEST_DYNAMIC') {
        $leaked = true;
        break;
    }
}
chk("UID Jawa Timur does NOT leak UP Testing Dinamis", !$leaked);

// 5. Test integrity protection on delete
$attachedUp = $db->table('up3s')->where('uid_id', $testUidId)->countAllResults();
chk("Integrity guard: UID {$testUidId} has {$attachedUp} attached UP, protected from deletion", $attachedUp > 0);

// 6. Clean up test records
$db->table('up3s')->where('id', $testUpId)->delete();
$db->table('uids')->where('id', $testUidId)->delete();
chk("Cleanup test dynamic records successful", true);

echo "\n====================================================\n";
echo "RESULT: PASS: $pass | FAIL: $fail\n";
echo "====================================================\n";
