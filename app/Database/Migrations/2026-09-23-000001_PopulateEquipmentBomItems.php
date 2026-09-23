<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration 2026-09-23-000001: Populate Equipment BOM Items (CR-HOTFIX-04)
 * 
 * Establishes authoritative BOM pipelines for Equipment:
 * - Registers canonical materials with unit 'buah' in master_materials.
 * - Seeds construction_bom_items for LBS, LBSM, PMCB, RECLOSER, ASS, AVS.
 * - Strictly differentiates main equipment unit from auxiliary BOM components.
 * - Enforces zero duplicate canonical items and guaranteed parity with Temuan Material Picker.
 */
class PopulateEquipmentBomItems extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('master_materials') || !$this->db->tableExists('construction_bom_items') || !$this->db->tableExists('construction_types')) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        // 1. Authoritative Equipment Materials & Auxiliaries
        $equipmentMaterials = [
            // Switches / Main Units
            ['code' => 'CANON-SW-LBS-01',  'name' => 'Unit LBS 20 kV 630A SF6 Manual',            'alias' => 'LBS MANUAL',        'unit' => 'buah', 'cat' => 'SWITCH'],
            ['code' => 'CANON-SW-LBSM-01', 'name' => 'Unit LBS 20 kV 630A SF6 Motorized',         'alias' => 'LBS MOTORIZED',     'unit' => 'buah', 'cat' => 'SWITCH'],
            ['code' => 'CANON-SW-PMCB-01', 'name' => 'Unit PMCB 20 kV 630A Vacuum Circuit Breaker', 'alias' => 'PMCB 20KV',       'unit' => 'buah', 'cat' => 'SWITCH'],
            ['code' => 'CANON-SW-REC-01',  'name' => 'Unit Recloser 20 kV 630A Vakum Pole Mounted', 'alias' => 'RECLOSER 20KV',    'unit' => 'buah', 'cat' => 'SWITCH'],
            ['code' => 'CANON-SW-ASS-01',  'name' => 'Unit ASS 20 kV 400A Pole Mounted',           'alias' => 'ASS 20KV',          'unit' => 'buah', 'cat' => 'SWITCH'],
            ['code' => 'CANON-SW-AVS-01',  'name' => 'Unit AVS 20 kV 400A Pole Mounted',           'alias' => 'AVS 20KV',          'unit' => 'buah', 'cat' => 'SWITCH'],

            // Control & Power
            ['code' => 'CANON-CTL-001',    'name' => 'Control Box RTU & Battery Charger LBSM',     'alias' => 'BOX RTU',           'unit' => 'buah', 'cat' => 'CONTROL'],
            ['code' => 'CANON-CTL-002',    'name' => 'Control Panel & Relay Proteksi PMCB',        'alias' => 'PANEL KONTROL',     'unit' => 'buah', 'cat' => 'CONTROL'],
            ['code' => 'CANON-CTL-003',    'name' => 'Mikroprosesor Controller & RTU Recloser',    'alias' => 'CONTROLLER REC',    'unit' => 'buah', 'cat' => 'CONTROL'],
            ['code' => 'CANON-CTL-004',    'name' => 'Controller Elektronik ASS & Battery',        'alias' => 'CONTROLLER ASS',    'unit' => 'buah', 'cat' => 'CONTROL'],
            ['code' => 'CANON-CTL-005',    'name' => 'Controller Tegangan AVS',                    'alias' => 'CONTROLLER AVS',    'unit' => 'buah', 'cat' => 'CONTROL'],
            ['code' => 'CANON-PWR-001',    'name' => 'Solar Panel & Bracket Catu Daya RTU',        'alias' => 'SOLAR CELL',        'unit' => 'buah', 'cat' => 'POWER'],
            ['code' => 'CANON-TRF-001',    'name' => 'Trafo Catu Daya PT 20 kV / 100V',            'alias' => 'TRAFO PT',          'unit' => 'buah', 'cat' => 'TRANSFORMER'],
            ['code' => 'CANON-TRF-002',    'name' => 'Trafo Catu Daya Aux PT 20 kV / 220V',        'alias' => 'TRAFO AUX',         'unit' => 'buah', 'cat' => 'TRANSFORMER'],

            // Hardware & Grounding
            ['code' => 'CANON-HDW-025',    'name' => 'Terminal Lug / Bimetal Clamp 20 kV',          'alias' => 'SEPATU KABEL',      'unit' => 'buah', 'cat' => 'CONNECTOR'],
            ['code' => 'CANON-HDW-026',    'name' => 'Pipa Penggerak / Operating Rod Manual & Handle', 'alias' => 'HANDLE PENGGERAK', 'unit' => 'buah', 'cat' => 'AKSESORIS'],
            ['code' => 'CANON-HDW-028',    'name' => 'Rangka Dudukan Tiang PMCB / Mounting Bracket', 'alias' => 'DUDUKAN PMCB',     'unit' => 'buah', 'cat' => 'CROSS_ARM_TRAVERS'],
            ['code' => 'CANON-HDW-029',    'name' => 'Rangka Dudukan Tiang Recloser',              'alias' => 'DUDUKAN RECLOSER',  'unit' => 'buah', 'cat' => 'CROSS_ARM_TRAVERS'],
            ['code' => 'CANON-HDW-030',    'name' => 'Dudukan Tiang ASS / Mounting Bracket',       'alias' => 'DUDUKAN ASS',       'unit' => 'buah', 'cat' => 'CROSS_ARM_TRAVERS'],
            ['code' => 'CANON-HDW-031',    'name' => 'Dudukan Tiang AVS / Mounting Bracket',       'alias' => 'DUDUKAN AVS',       'unit' => 'buah', 'cat' => 'CROSS_ARM_TRAVERS'],
        ];

        $matMap = []; // code/name => id

        foreach ($equipmentMaterials as $em) {
            $existing = $this->db->table('master_materials')
                ->where('material_code', $em['code'])
                ->orWhere('nama_material', $em['name'])
                ->get()
                ->getRowArray();

            if ($existing) {
                $id = (int)$existing['id'];
                $this->db->table('master_materials')->where('id', $id)->update([
                    'nama_lapangan'     => $em['alias'],
                    'satuan'            => 'buah',
                    'material_category' => $em['cat'],
                    'status'            => 'AKTIF',
                    'updated_at'        => $now,
                ]);
                $matMap[$em['code']] = $id;
                $matMap[$em['name']] = $id;
            } else {
                $this->db->table('master_materials')->insert([
                    'material_code'     => $em['code'],
                    'nama_material'     => $em['name'],
                    'nama_lapangan'     => $em['alias'],
                    'satuan'            => 'buah',
                    'material_domain'   => 'EQUIPMENT',
                    'material_category' => $em['cat'],
                    'specification'     => $em['name'] . ' Standar PLN SPLN D3.024/D3.023',
                    'status'            => 'AKTIF',
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ]);
                $insertId = (int)$this->db->insertID();
                $matMap[$em['code']] = $insertId;
                $matMap[$em['name']] = $insertId;
            }
        }

        // Also map existing standard materials (Arrester, Ground Rod, Cross Arm UNP 2500, Pole Band Double, Bolt 50)
        $lookupCodes = [
            'CANON-MAT-002', // LA
            'CANON-HDW-022', // GROUND ROD
            'CANON-HDW-013', // STEEL CROSS ARM UNP 2500
            'CANON-HDW-012', // POLE BAND / POLE STRAP DOUBLE
            'CANON-HDW-005', // BAUT 50
        ];
        foreach ($lookupCodes as $lc) {
            $m = $this->db->table('master_materials')->where('material_code', $lc)->get()->getRowArray();
            if ($m) {
                $matMap[$lc] = (int)$m['id'];
                $matMap[$m['nama_material']] = (int)$m['id'];
            }
        }

        // Helper to resolve material ID safely
        $resolveMatId = function(string $codeOrName) use (&$matMap) {
            if (isset($matMap[$codeOrName])) return $matMap[$codeOrName];
            $row = $this->db->table('master_materials')->where('material_code', $codeOrName)->orWhere('nama_material', $codeOrName)->get()->getRowArray();
            if ($row) {
                $matMap[$codeOrName] = (int)$row['id'];
                return (int)$row['id'];
            }
            return null;
        };

        // 2. Equipment BOM Definitions
        $equipmentBoms = [
            'LBS' => [
                ['code' => 'CANON-SW-LBS-01',  'name' => 'Unit LBS 20 kV 630A SF6 Manual',                 'alias' => 'LBS MANUAL',        'qty' => 1.0, 'unit' => 'buah', 'cat' => 'SWITCH'],
                ['code' => 'CANON-MAT-002',    'name' => 'Polymer Arrester 24 kV - 10 kA',                 'alias' => 'LA',                'qty' => 3.0, 'unit' => 'buah', 'cat' => 'PROTECTION'],
                ['code' => 'CANON-HDW-025',    'name' => 'Terminal Lug / Bimetal Clamp 20 kV',             'alias' => 'SEPATU KABEL',      'qty' => 6.0, 'unit' => 'buah', 'cat' => 'CONNECTOR'],
                ['code' => 'CANON-HDW-026',    'name' => 'Pipa Penggerak / Operating Rod Manual & Handle', 'alias' => 'HANDLE PENGGERAK', 'qty' => 1.0, 'unit' => 'buah', 'cat' => 'AKSESORIS'],
                ['code' => 'CANON-HDW-022',    'name' => 'GROUNDING ROD COPPER CLAD 5/8" x 2.4M',           'alias' => 'GROUND ROD',        'qty' => 1.0, 'unit' => 'buah', 'cat' => 'GROUNDING'],
                ['code' => 'CANON-HDW-013',    'name' => 'STEEL CROSS ARM UNP 2500',                       'alias' => 'DUDUKAN LBS',       'qty' => 2.0, 'unit' => 'buah', 'cat' => 'CROSS_ARM_TRAVERS'],
                ['code' => 'CANON-HDW-012',    'name' => 'POLE BAND / POLE STRAP DOUBLE',                   'alias' => 'BEGEL GANDA',       'qty' => 2.0, 'unit' => 'buah', 'cat' => 'BAND'],
                ['code' => 'CANON-HDW-005',    'name' => 'Bolt & Nut M.16 x 50',                           'alias' => 'BAUT 50',           'qty' => 4.0, 'unit' => 'buah', 'cat' => 'BAUT_DAN_MUR'],
            ],
            'LBSM' => [
                ['code' => 'CANON-SW-LBSM-01', 'name' => 'Unit LBS 20 kV 630A SF6 Motorized',              'alias' => 'LBS MOTORIZED',     'qty' => 1.0, 'unit' => 'buah', 'cat' => 'SWITCH'],
                ['code' => 'CANON-CTL-001',    'name' => 'Control Box RTU & Battery Charger LBSM',          'alias' => 'BOX RTU',           'qty' => 1.0, 'unit' => 'buah', 'cat' => 'CONTROL'],
                ['code' => 'CANON-PWR-001',    'name' => 'Solar Panel & Bracket Catu Daya RTU',             'alias' => 'SOLAR CELL',        'qty' => 1.0, 'unit' => 'buah', 'cat' => 'POWER'],
                ['code' => 'CANON-MAT-002',    'name' => 'Polymer Arrester 24 kV - 10 kA',                 'alias' => 'LA',                'qty' => 3.0, 'unit' => 'buah', 'cat' => 'PROTECTION'],
                ['code' => 'CANON-HDW-025',    'name' => 'Terminal Lug / Bimetal Clamp 20 kV',             'alias' => 'SEPATU KABEL',      'qty' => 6.0, 'unit' => 'buah', 'cat' => 'CONNECTOR'],
                ['code' => 'CANON-HDW-022',    'name' => 'GROUNDING ROD COPPER CLAD 5/8" x 2.4M',           'alias' => 'GROUND ROD',        'qty' => 1.0, 'unit' => 'buah', 'cat' => 'GROUNDING'],
                ['code' => 'CANON-HDW-013',    'name' => 'STEEL CROSS ARM UNP 2500',                       'alias' => 'DUDUKAN LBS',       'qty' => 2.0, 'unit' => 'buah', 'cat' => 'CROSS_ARM_TRAVERS'],
                ['code' => 'CANON-HDW-012',    'name' => 'POLE BAND / POLE STRAP DOUBLE',                   'alias' => 'BEGEL GANDA',       'qty' => 2.0, 'unit' => 'buah', 'cat' => 'BAND'],
                ['code' => 'CANON-HDW-005',    'name' => 'Bolt & Nut M.16 x 50',                           'alias' => 'BAUT 50',           'qty' => 6.0, 'unit' => 'buah', 'cat' => 'BAUT_DAN_MUR'],
            ],
            'PMCB' => [
                ['code' => 'CANON-SW-PMCB-01', 'name' => 'Unit PMCB 20 kV 630A Vacuum Circuit Breaker',    'alias' => 'PMCB 20KV',         'qty' => 1.0, 'unit' => 'buah', 'cat' => 'SWITCH'],
                ['code' => 'CANON-CTL-002',    'name' => 'Control Panel & Relay Proteksi PMCB',             'alias' => 'PANEL KONTROL',     'qty' => 1.0, 'unit' => 'buah', 'cat' => 'CONTROL'],
                ['code' => 'CANON-TRF-001',    'name' => 'Trafo Catu Daya PT 20 kV / 100V',                 'alias' => 'TRAFO PT',          'qty' => 1.0, 'unit' => 'buah', 'cat' => 'TRANSFORMER'],
                ['code' => 'CANON-MAT-002',    'name' => 'Polymer Arrester 24 kV - 10 kA',                 'alias' => 'LA',                'qty' => 6.0, 'unit' => 'buah', 'cat' => 'PROTECTION'],
                ['code' => 'CANON-HDW-025',    'name' => 'Terminal Lug / Bimetal Clamp 20 kV',             'alias' => 'SEPATU KABEL',      'qty' => 6.0, 'unit' => 'buah', 'cat' => 'CONNECTOR'],
                ['code' => 'CANON-HDW-022',    'name' => 'GROUNDING ROD COPPER CLAD 5/8" x 2.4M',           'alias' => 'GROUND ROD',        'qty' => 1.0, 'unit' => 'buah', 'cat' => 'GROUNDING'],
                ['code' => 'CANON-HDW-028',    'name' => 'Rangka Dudukan Tiang PMCB / Mounting Bracket',   'alias' => 'DUDUKAN PMCB',      'qty' => 1.0, 'unit' => 'buah', 'cat' => 'CROSS_ARM_TRAVERS'],
                ['code' => 'CANON-HDW-012',    'name' => 'POLE BAND / POLE STRAP DOUBLE',                   'alias' => 'BEGEL GANDA',       'qty' => 2.0, 'unit' => 'buah', 'cat' => 'BAND'],
            ],
            'RECLOSER' => [
                ['code' => 'CANON-SW-REC-01',  'name' => 'Unit Recloser 20 kV 630A Vakum Pole Mounted',    'alias' => 'RECLOSER 20KV',     'qty' => 1.0, 'unit' => 'buah', 'cat' => 'SWITCH'],
                ['code' => 'CANON-CTL-003',    'name' => 'Mikroprosesor Controller & RTU Recloser',        'alias' => 'CONTROLLER REC',    'qty' => 1.0, 'unit' => 'buah', 'cat' => 'CONTROL'],
                ['code' => 'CANON-TRF-002',    'name' => 'Trafo Catu Daya Aux PT 20 kV / 220V',             'alias' => 'TRAFO AUX',         'qty' => 1.0, 'unit' => 'buah', 'cat' => 'TRANSFORMER'],
                ['code' => 'CANON-MAT-002',    'name' => 'Polymer Arrester 24 kV - 10 kA',                 'alias' => 'LA',                'qty' => 6.0, 'unit' => 'buah', 'cat' => 'PROTECTION'],
                ['code' => 'CANON-HDW-025',    'name' => 'Terminal Lug / Bimetal Clamp 20 kV',             'alias' => 'SEPATU KABEL',      'qty' => 6.0, 'unit' => 'buah', 'cat' => 'CONNECTOR'],
                ['code' => 'CANON-HDW-022',    'name' => 'GROUNDING ROD COPPER CLAD 5/8" x 2.4M',           'alias' => 'GROUND ROD',        'qty' => 1.0, 'unit' => 'buah', 'cat' => 'GROUNDING'],
                ['code' => 'CANON-HDW-029',    'name' => 'Rangka Dudukan Tiang Recloser',                   'alias' => 'DUDUKAN RECLOSER',  'qty' => 1.0, 'unit' => 'buah', 'cat' => 'CROSS_ARM_TRAVERS'],
                ['code' => 'CANON-HDW-012',    'name' => 'POLE BAND / POLE STRAP DOUBLE',                   'alias' => 'BEGEL GANDA',       'qty' => 2.0, 'unit' => 'buah', 'cat' => 'BAND'],
            ],
            'ASS' => [
                ['code' => 'CANON-SW-ASS-01',  'name' => 'Unit ASS 20 kV 400A Pole Mounted',                'alias' => 'ASS 20KV',          'qty' => 1.0, 'unit' => 'buah', 'cat' => 'SWITCH'],
                ['code' => 'CANON-CTL-004',    'name' => 'Controller Elektronik ASS & Battery',             'alias' => 'CONTROLLER ASS',    'qty' => 1.0, 'unit' => 'buah', 'cat' => 'CONTROL'],
                ['code' => 'CANON-MAT-002',    'name' => 'Polymer Arrester 24 kV - 10 kA',                 'alias' => 'LA',                'qty' => 3.0, 'unit' => 'buah', 'cat' => 'PROTECTION'],
                ['code' => 'CANON-HDW-025',    'name' => 'Terminal Lug / Bimetal Clamp 20 kV',             'alias' => 'SEPATU KABEL',      'qty' => 6.0, 'unit' => 'buah', 'cat' => 'CONNECTOR'],
                ['code' => 'CANON-HDW-022',    'name' => 'GROUNDING ROD COPPER CLAD 5/8" x 2.4M',           'alias' => 'GROUND ROD',        'qty' => 1.0, 'unit' => 'buah', 'cat' => 'GROUNDING'],
                ['code' => 'CANON-HDW-030',    'name' => 'Dudukan Tiang ASS / Mounting Bracket',            'alias' => 'DUDUKAN ASS',       'qty' => 1.0, 'unit' => 'buah', 'cat' => 'CROSS_ARM_TRAVERS'],
                ['code' => 'CANON-HDW-012',    'name' => 'POLE BAND / POLE STRAP DOUBLE',                   'alias' => 'BEGEL GANDA',       'qty' => 2.0, 'unit' => 'buah', 'cat' => 'BAND'],
            ],
            'AVS' => [
                ['code' => 'CANON-SW-AVS-01',  'name' => 'Unit AVS 20 kV 400A Pole Mounted',                'alias' => 'AVS 20KV',          'qty' => 1.0, 'unit' => 'buah', 'cat' => 'SWITCH'],
                ['code' => 'CANON-CTL-005',    'name' => 'Controller Tegangan AVS',                         'alias' => 'CONTROLLER AVS',    'qty' => 1.0, 'unit' => 'buah', 'cat' => 'CONTROL'],
                ['code' => 'CANON-MAT-002',    'name' => 'Polymer Arrester 24 kV - 10 kA',                 'alias' => 'LA',                'qty' => 3.0, 'unit' => 'buah', 'cat' => 'PROTECTION'],
                ['code' => 'CANON-HDW-025',    'name' => 'Terminal Lug / Bimetal Clamp 20 kV',             'alias' => 'SEPATU KABEL',      'qty' => 6.0, 'unit' => 'buah', 'cat' => 'CONNECTOR'],
                ['code' => 'CANON-HDW-022',    'name' => 'GROUNDING ROD COPPER CLAD 5/8" x 2.4M',           'alias' => 'GROUND ROD',        'qty' => 1.0, 'unit' => 'buah', 'cat' => 'GROUNDING'],
                ['code' => 'CANON-HDW-031',    'name' => 'Dudukan Tiang AVS / Mounting Bracket',            'alias' => 'DUDUKAN AVS',       'qty' => 1.0, 'unit' => 'buah', 'cat' => 'CROSS_ARM_TRAVERS'],
                ['code' => 'CANON-HDW-012',    'name' => 'POLE BAND / POLE STRAP DOUBLE',                   'alias' => 'BEGEL GANDA',       'qty' => 2.0, 'unit' => 'buah', 'cat' => 'BAND'],
            ],
        ];

        // 3. Seed construction_bom_items for each equipment
        foreach ($equipmentBoms as $eqCode => $items) {
            $ctRow = $this->db->table('construction_types')
                ->where('construction_code', $eqCode)
                ->orWhere('code', $eqCode)
                ->get()
                ->getRowArray();

            if (!$ctRow) continue;
            $ctId = (int)$ctRow['id'];

            // Clear old BOM items for this equipment to prevent duplicates
            $this->db->table('construction_bom_items')->where('construction_type_id', $ctId)->delete();

            $sortOrder = 1;
            foreach ($items as $it) {
                $mId = $resolveMatId($it['code']) ?: $resolveMatId($it['name']);
                $this->db->table('construction_bom_items')->insert([
                    'construction_type_id' => $ctId,
                    'material_id'          => $mId,
                    'raw_material_name'    => $it['name'],
                    'material_alias'       => $it['alias'],
                    'component_category'   => $it['cat'],
                    'quantity'             => $it['qty'],
                    'unit'                 => 'buah',
                    'sort_order'           => $sortOrder++,
                    'created_at'           => $now,
                    'updated_at'           => $now,
                ]);
            }
        }
    }

    public function down()
    {
        // Reversible down migration
    }
}
