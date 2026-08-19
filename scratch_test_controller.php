<?php

define('FCPATH', __DIR__ . '/public/');
chdir(__DIR__);

require 'app/Config/Paths.php';
$paths = new Config\Paths();

define('APPPATH', realpath($paths->appDirectory) . DIRECTORY_SEPARATOR);
define('ROOTPATH', realpath($paths->appDirectory . '/../') . DIRECTORY_SEPARATOR);
define('SYSTEMPATH', realpath($paths->systemDirectory) . DIRECTORY_SEPARATOR);
define('WRITEPATH', realpath($paths->writableDirectory) . DIRECTORY_SEPARATOR);

require SYSTEMPATH . 'bootstrap.php';

$db = \Config\Database::connect();
$repo = new \App\Repositories\AssetRepository();

$filters = [
    'ulp_id'      => '1',
    'penyulang_id'=> '15',
    'jenis_asset' => 'JTM',
    'status'      => '',
    'search'      => '',
];

echo "========================================\n";
echo "SIMULATING ASSET CONTROLLER INDEX QUERY:\n";
echo "========================================\n\n";

$resNull = $repo->getFilteredAssetsPaginated($filters, null, 1, 50);
echo "Result with null userUlpId:\n";
echo "Total : " . $resNull['total'] . "\n";
echo "Count : " . count($resNull['data']) . "\n";
if (!empty($resNull['data'])) {
    echo "Sample Asset 0: " . print_r($resNull['data'][0], true) . "\n";
} else {
    echo "DATA ARRAY IS EMPTY!\n";
}

echo "\nResult with userUlpId = 1:\n";
$res1 = $repo->getFilteredAssetsPaginated($filters, 1, 1, 50);
echo "Total : " . $res1['total'] . "\n";
echo "Count : " . count($res1['data']) . "\n";

echo "\nResult with userUlpId = 3:\n";
$res3 = $repo->getFilteredAssetsPaginated($filters, 3, 1, 50);
echo "Total : " . $res3['total'] . "\n";
echo "Count : " . count($res3['data']) . "\n";
