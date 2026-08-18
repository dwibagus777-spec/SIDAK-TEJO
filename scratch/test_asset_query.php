<?php
define('FCPATH', __DIR__ . '/../public/');
define('ROOTPATH', realpath(__DIR__ . '/../') . DIRECTORY_SEPARATOR);
define('APPPATH', ROOTPATH . 'app' . DIRECTORY_SEPARATOR);
define('SYSTEMPATH', ROOTPATH . 'vendor/codeigniter4/framework/system' . DIRECTORY_SEPARATOR);
define('WRITEPATH', ROOTPATH . 'writable' . DIRECTORY_SEPARATOR);
define('ENVIRONMENT', 'development');

chdir(ROOTPATH);
require 'vendor/autoload.php';
require SYSTEMPATH . 'Common.php';
require 'app/Config/Constants.php';

$dbConfig = new \Config\Database();
$db = \Config\Database::connect();

echo "=== DB ASSET DIAGNOSTIC ===\n";

$totalCount = $db->table('assets')->countAllResults();
echo "Total rows in assets table: {$totalCount}\n";

$activeCount = $db->table('assets')->where('deleted_at IS NULL')->countAllResults();
echo "Active rows in assets table (deleted_at IS NULL): {$activeCount}\n";

$ulps = $db->table('ulps')->get()->getResultArray();
echo "\nULPs in database:\n";
foreach ($ulps as $u) {
    $c = $db->table('assets')->where('ulp_id', $u['id'])->where('deleted_at IS NULL')->countAllResults();
    echo "- ID: {$u['id']} | Nama: {$u['nama_ulp']} | Active Assets Count: {$c}\n";
}

$sampleAssets = $db->table('assets')->select('id, kode_asset, nama_asset, jenis_asset, ulp_id, penyulang_id, deleted_at')->limit(5)->get()->getResultArray();
echo "\nSample Assets:\n";
print_r($sampleAssets);
