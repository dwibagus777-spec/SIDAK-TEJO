<?php
define('FCPATH', __DIR__ . '/../public/');
require 'vendor/autoload.php';
require 'app/Config/Paths.php';
$paths = new \Config\Paths();
require $paths->systemDirectory . '/Boot.php';
\CodeIgniter\Boot::bootSpark($paths);

$db = \Config\Database::connect('default');
try {
    $rows = $db->table('master_materials')->get()->getResultArray();
    echo "Total master_materials: " . count($rows) . "\n";
    foreach ($rows as $r) {
        echo sprintf("[%d] %-25s | %-50s | %-20s | %-8s\n", $r['id'], $r['material_code'], $r['nama_material'], $r['nama_lapangan'] ?? '', $r['satuan'] ?? '');
    }

    echo "\n--- CONSTRUCTION TYPES ---\n";
    $cTypes = $db->table('construction_types')->get()->getResultArray();
    echo "Total construction_types: " . count($cTypes) . "\n";
    foreach ($cTypes as $c) {
        $cId = $c['id'];
        $bomCount = $db->table('construction_bom_items')->where('construction_type_id', $cId)->countAllResults();
        $code = $c['construction_code'] ?? ($c['code'] ?? '');
        $name = $c['construction_name'] ?? ($c['name'] ?? '');
        echo sprintf("[%d] %-15s | %-45s | BOM items: %d\n", $cId, $code, $name, $bomCount);
    }

    echo "\n--- BOM ITEMS FOR TM1 (id=1) ---\n";
    $tm1Boms = $db->table('construction_bom_items')->where('construction_type_id', 1)->get()->getResultArray();
    foreach ($tm1Boms as $b) {
        echo sprintf("  - ID: %d, raw: %-45s, mat_id: %s, qty: %s, unit: %s\n",
            $b['id'], $b['raw_material_name'], $b['material_id'] ?? 'NULL', $b['quantity'] ?? 'NULL', $b['unit'] ?? 'NULL'
        );
    }
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
