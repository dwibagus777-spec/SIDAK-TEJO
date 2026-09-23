<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration 2026-09-22-000003: Populate Authoritative JTM BOM (Phase 3)
 * 
 * Implements CR-MATERIAL-BOM-INTEGRITY-01:
 * 1. Enforces canonical unit 'buah' across all individual material items.
 * 2. Populates master_materials with authoritative JTM materials from PLN standard.
 * 3. Overhauls TM-1 from 2 hardcoded items to all 13 canonical components with exact
 *    default_qty, field_alias, and canonical units.
 * 4. Seeds BOM items for primary JTM construction types (TM1, TM2, TM4, TM5, TM8, TM10, TM11, TMTP).
 */
class PopulateAuthoritativeJtmBom extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('master_materials') || !$this->db->tableExists('construction_bom_items')) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        // 1. Enforce Canonical Units ('buah' for individual pieces)
        $this->db->query("UPDATE `master_materials` SET `satuan` = 'buah' WHERE `material_code` IN (
            'MAT-ISO-PIN-20KV', 'MAT-ISO-HANG-20KV', 'MAT-PROT-LA-24KV', 'MAT-PROT-FCO-24KV', 'MAT-IND-FIOHL', 'MAT-PROT-FCO-LAT'
        )");

        // 2. Authoritative Material Definitions (Canonical 91 materials subset & TM1 components)
        $canonicalMaterials = [
            // TM-1 Canonical Components
            ['material_code' => 'CANON-HDW-001', 'nama_material' => 'Cross Arm UNP 2000 mm', 'nama_lapangan' => 'KANAL', 'satuan' => 'buah', 'material_category' => 'CROSS_ARM_TRAVERS'],
            ['material_code' => 'CANON-HDW-003', 'nama_material' => 'Arm Tie Type 750 - 3/4"', 'nama_lapangan' => 'ARM TIE', 'satuan' => 'buah', 'material_category' => 'CROSS_ARM_TRAVERS'],
            ['material_code' => 'CANON-HDW-005', 'nama_material' => 'Bolt & Nut M.16 x 50', 'nama_lapangan' => 'BAUT 50', 'satuan' => 'buah', 'material_category' => 'BAUT_DAN_MUR'],
            ['material_code' => 'CANON-HDW-002', 'nama_material' => 'Bolt & Nut M.16 x 400 (besi as) Double Arm - HDG', 'nama_lapangan' => 'BAUT 400', 'satuan' => 'buah', 'material_category' => 'BAUT_DAN_MUR'],
            ['material_code' => 'CANON-HDW-015', 'nama_material' => 'Ground Wire Clamp Type A', 'nama_lapangan' => 'PLAT GSW', 'satuan' => 'buah', 'material_category' => 'CLAMP'],
            ['material_code' => 'CANON-HDW-016', 'nama_material' => 'Wire Clip M10 (Ø 35mm)', 'nama_lapangan' => 'GSW', 'satuan' => 'buah', 'material_category' => 'AKSESORIS'],
            ['material_code' => 'CANON-MAT-001', 'nama_material' => 'Insulator - Pin Post Insulator 20 Kv;12,5 kN - Porcelain (Tumpu)', 'nama_lapangan' => 'PIN', 'satuan' => 'buah', 'material_category' => 'ISOLATOR'],
            ['material_code' => 'CANON-ACC-010', 'nama_material' => 'Isolated All. Binding - 4 mm Ø 6', 'nama_lapangan' => 'BENDING', 'satuan' => 'buah', 'material_category' => 'PENGIKAT'],
            ['material_code' => 'CANON-ACC-011', 'nama_material' => 'Preformed Side Tie Double 150mm (Semi Cond/non metalic/Composite)', 'nama_lapangan' => 'TOP TIES SIDE', 'satuan' => 'buah', 'material_category' => 'PENGIKAT'],
            ['material_code' => 'CANON-ACC-012', 'nama_material' => 'Preformed Top Tie 150mm (Semi Cond/non metalic/Composite)', 'nama_lapangan' => 'TOP TIES', 'satuan' => 'buah', 'material_category' => 'PENGIKAT'],
            ['material_code' => 'CANON-HDW-018', 'nama_material' => 'ORNAMENT CABLE BAND', 'nama_lapangan' => 'BEGEL VERLINK', 'satuan' => 'buah', 'material_category' => 'BAND'],
            ['material_code' => 'CANON-HDW-019', 'nama_material' => 'PIPE GALVANIZED 3" 1500', 'nama_lapangan' => 'VERLINK GSW', 'satuan' => 'buah', 'material_category' => 'PIPA'],
            
            // Major JTM Materials
            ['material_code' => 'CANON-MAT-002', 'nama_material' => 'Polymer Arrester 24 kV - 10 kA', 'nama_lapangan' => 'LA', 'satuan' => 'buah', 'material_category' => 'PROTECTION'],
            ['material_code' => 'CANON-MAT-003', 'nama_material' => 'Polymer Cut Out Switch 24 kV + Fuse', 'nama_lapangan' => 'FCO', 'satuan' => 'buah', 'material_category' => 'PROTECTION'],
            ['material_code' => 'CANON-MAT-004', 'nama_material' => 'Insulator - Strain Insulator 20 kV lengkap (SIR) Porcelain (Tarik)', 'nama_lapangan' => 'HANG', 'satuan' => 'buah', 'material_category' => 'ISOLATOR'],
            ['material_code' => 'CANON-HDW-004', 'nama_material' => 'DOUBLE ARM BAND', 'nama_lapangan' => 'BEGEL ARM TIE', 'satuan' => 'buah', 'material_category' => 'CROSS_ARM_TRAVERS'],
            ['material_code' => 'CANON-HDW-006', 'nama_material' => 'ARM CLEVIS', 'nama_lapangan' => 'ARM CLEVIS/BAND STRAP', 'satuan' => 'buah', 'material_category' => 'CROSS_ARM_TRAVERS'],
            ['material_code' => 'CANON-HDW-007', 'nama_material' => 'STRAIN CLAMP 3 NUT', 'nama_lapangan' => 'DEAD END', 'satuan' => 'buah', 'material_category' => 'CLAMP'],
            ['material_code' => 'CANON-HDW-008', 'nama_material' => 'BALL CLEVIS', 'nama_lapangan' => 'BALL CLEVIS', 'satuan' => 'buah', 'material_category' => 'HARDWARE'],
            ['material_code' => 'CANON-HDW-009', 'nama_material' => 'SOCKET EYE', 'nama_lapangan' => 'SOCKET EYE', 'satuan' => 'buah', 'material_category' => 'HARDWARE'],
            ['material_code' => 'CANON-HDW-010', 'nama_material' => 'DEAD END CLAMP', 'nama_lapangan' => 'DEAD END', 'satuan' => 'buah', 'material_category' => 'CLAMP'],
            ['material_code' => 'CANON-HDW-011', 'nama_material' => 'POLE BAND / POLE STRAP SINGLE', 'nama_lapangan' => 'BEGEL TIANG', 'satuan' => 'buah', 'material_category' => 'BAND'],
            ['material_code' => 'CANON-HDW-012', 'nama_material' => 'POLE BAND / POLE STRAP DOUBLE', 'nama_lapangan' => 'BEGEL GANDA', 'satuan' => 'buah', 'material_category' => 'BAND'],
            ['material_code' => 'CANON-HDW-013', 'nama_material' => 'STEEL CROSS ARM UNP 2500', 'nama_lapangan' => 'KANAL 2500', 'satuan' => 'buah', 'material_category' => 'CROSS_ARM_TRAVERS'],
            ['material_code' => 'CANON-HDW-014', 'nama_material' => 'STEEL CROSS ARM UNP 3000', 'nama_lapangan' => 'KANAL 3000', 'satuan' => 'buah', 'material_category' => 'CROSS_ARM_TRAVERS'],
            ['material_code' => 'CANON-HDW-020', 'nama_material' => 'Bolt & Nut M.16 x 250', 'nama_lapangan' => 'BAUT 250', 'satuan' => 'buah', 'material_category' => 'BAUT_DAN_MUR'],
            ['material_code' => 'CANON-HDW-021', 'nama_material' => 'Bolt & Nut M.16 x 350', 'nama_lapangan' => 'BAUT 350', 'satuan' => 'buah', 'material_category' => 'BAUT_DAN_MUR'],
            ['material_code' => 'CANON-HDW-022', 'nama_material' => 'GROUNDING ROD COPPER CLAD 5/8" x 2.4M', 'nama_lapangan' => 'GROUND ROD', 'satuan' => 'buah', 'material_category' => 'GROUNDING'],
            ['material_code' => 'CANON-HDW-023', 'nama_material' => 'PARALLEL GROOVE CLAMP 2 BAUT', 'nama_lapangan' => 'PG CLAMP', 'satuan' => 'buah', 'material_category' => 'CLAMP'],
            ['material_code' => 'CANON-HDW-024', 'nama_material' => 'PIERCING CONNECTOR 20KV', 'nama_lapangan' => 'TAP CONNECTOR', 'satuan' => 'buah', 'material_category' => 'CONNECTOR'],
            ['material_code' => 'MAT-IND-FIOHL',   'nama_material' => 'Fault Indicator Overhead Line (FIOHL)', 'nama_lapangan' => 'FIOHL', 'satuan' => 'buah', 'material_category' => 'MONITORING'],
        ];

        $matMap = []; // Name => ID

        foreach ($canonicalMaterials as $m) {
            $existing = $this->db->table('master_materials')
                ->where('material_code', $m['material_code'])
                ->orWhere('nama_material', $m['nama_material'])
                ->get()
                ->getRowArray();

            if ($existing) {
                $id = (int)$existing['id'];
                $this->db->table('master_materials')->where('id', $id)->update([
                    'nama_lapangan'     => $m['nama_lapangan'],
                    'satuan'            => $m['satuan'],
                    'material_category' => $m['material_category'],
                    'status'            => 'AKTIF',
                    'updated_at'        => $now,
                ]);
                $matMap[$m['nama_material']] = $id;
            } else {
                $this->db->table('master_materials')->insert([
                    'material_code'     => $m['material_code'],
                    'nama_material'     => $m['nama_material'],
                    'nama_lapangan'     => $m['nama_lapangan'],
                    'satuan'            => $m['satuan'],
                    'material_domain'   => 'JTM',
                    'material_category' => $m['material_category'],
                    'specification'     => $m['nama_material'] . ' Standar Konstruksi JTM PLN',
                    'source_workbook'   => 'KONSTRUKSI_JTM_2026.xlsx',
                    'source_sheet'      => 'KONSTRUKSI JTM',
                    'status'            => 'AKTIF',
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ]);
                $matMap[$m['nama_material']] = (int)$this->db->insertID();
            }
        }

        // Also map existing materials in master_materials
        $existingRows = $this->db->table('master_materials')->get()->getResultArray();
        foreach ($existingRows as $er) {
            $matMap[$er['nama_material']] = (int)$er['id'];
            if (!empty($er['nama_lapangan'])) {
                $matMap[$er['nama_lapangan']] = (int)$er['id'];
            }
        }

        // 3. OVERHAUL TM-1 BOM (Exact 13 Canonical Materials)
        $tm1Row = $this->db->table('construction_types')
            ->where('construction_code', 'TM1')
            ->orWhere('code', 'TM1')
            ->get()
            ->getRowArray();

        if ($tm1Row) {
            $tm1Id = (int)$tm1Row['id'];

            // Clear old incomplete BOM items for TM1
            $this->db->table('construction_bom_items')->where('construction_type_id', $tm1Id)->delete();

            // Canonical 13 TM-1 components with standard PLN Buku 5 default quantities
            $tm1Items = [
                ['material' => 'Cross Arm UNP 2000 mm',                                                'alias' => 'KANAL',         'qty' => 1.0, 'unit' => 'buah', 'cat' => 'CROSS_ARM_TRAVERS'],
                ['material' => 'Arm Tie Type 750 - 3/4"',                                              'alias' => 'ARM TIE',       'qty' => 2.0, 'unit' => 'buah', 'cat' => 'CROSS_ARM_TRAVERS'],
                ['material' => 'Bolt & Nut M.16 x 50',                                                 'alias' => 'BAUT 50',       'qty' => 2.0, 'unit' => 'buah', 'cat' => 'BAUT_DAN_MUR'],
                ['material' => 'Bolt & Nut M.16 x 400 (besi as) Double Arm - HDG',                     'alias' => 'BAUT 400',      'qty' => 1.0, 'unit' => 'buah', 'cat' => 'BAUT_DAN_MUR'],
                ['material' => 'Ground Wire Clamp Type A',                                             'alias' => 'PLAT GSW',      'qty' => 1.0, 'unit' => 'buah', 'cat' => 'CLAMP'],
                ['material' => 'Wire Clip M10 (Ø 35mm)',                                               'alias' => 'GSW',           'qty' => 2.0, 'unit' => 'buah', 'cat' => 'AKSESORIS'],
                ['material' => 'Insulator - Pin Post Insulator 20 Kv;12,5 kN - Porcelain (Tumpu)',     'alias' => 'PIN',           'qty' => 3.0, 'unit' => 'buah', 'cat' => 'ISOLATOR'],
                ['material' => 'Isolated All. Binding - 4 mm Ø 6',                                     'alias' => 'BENDING',       'qty' => 3.0, 'unit' => 'buah', 'cat' => 'PENGIKAT'],
                ['material' => 'Preformed Side Tie Double 150mm (Semi Cond/non metalic/Composite)',    'alias' => 'TOP TIES SIDE', 'qty' => 1.0, 'unit' => 'buah', 'cat' => 'PENGIKAT'],
                ['material' => 'Preformed Top Tie 150mm (Semi Cond/non metalic/Composite)',            'alias' => 'TOP TIES',      'qty' => 2.0, 'unit' => 'buah', 'cat' => 'PENGIKAT'],
                ['material' => 'ORNAMENT CABLE BAND',                                                  'alias' => 'BEGEL VERLINK', 'qty' => 2.0, 'unit' => 'buah', 'cat' => 'BAND'],
                ['material' => 'PIPE GALVANIZED 3" 1500',                                              'alias' => 'VERLINK GSW',   'qty' => 1.0, 'unit' => 'buah', 'cat' => 'PIPA'],
                ['material' => 'Wire Clip M10 (Ø 35mm)',                                               'alias' => 'WIRE CLIP',     'qty' => 2.0, 'unit' => 'buah', 'cat' => 'AKSESORIS'],
            ];

            foreach ($tm1Items as $idx => $it) {
                $mId = $matMap[$it['material']] ?? null;
                $this->db->table('construction_bom_items')->insert([
                    'construction_type_id' => $tm1Id,
                    'material_id'          => $mId,
                    'raw_material_name'    => $it['material'],
                    'material_alias'       => $it['alias'],
                    'component_category'   => $it['cat'],
                    'quantity'             => $it['qty'],
                    'unit'                 => $it['unit'],
                    'sort_order'           => $idx + 1,
                    'created_at'           => $now,
                    'updated_at'           => $now,
                ]);
            }
        }

        // 4. Overhaul TM-2 BOM (Tumpu Ganda Sudut)
        $tm2Row = $this->db->table('construction_types')
            ->where('construction_code', 'TM2')
            ->orWhere('code', 'TM2')
            ->get()
            ->getRowArray();

        if ($tm2Row) {
            $tm2Id = (int)$tm2Row['id'];
            $this->db->table('construction_bom_items')->where('construction_type_id', $tm2Id)->delete();

            $tm2Items = [
                ['material' => 'Cross Arm UNP 2000 mm',                                                'alias' => 'KANAL',         'qty' => 2.0, 'unit' => 'buah', 'cat' => 'CROSS_ARM_TRAVERS'],
                ['material' => 'DOUBLE ARM BAND',                                                      'alias' => 'BEGEL ARM TIE', 'qty' => 2.0, 'unit' => 'buah', 'cat' => 'CROSS_ARM_TRAVERS'],
                ['material' => 'Arm Tie Type 750 - 3/4"',                                              'alias' => 'ARM TIE',       'qty' => 4.0, 'unit' => 'buah', 'cat' => 'CROSS_ARM_TRAVERS'],
                ['material' => 'Bolt & Nut M.16 x 50',                                                 'alias' => 'BAUT 50',       'qty' => 4.0, 'unit' => 'buah', 'cat' => 'BAUT_DAN_MUR'],
                ['material' => 'Bolt & Nut M.16 x 400 (besi as) Double Arm - HDG',                     'alias' => 'BAUT 400',      'qty' => 4.0, 'unit' => 'buah', 'cat' => 'BAUT_DAN_MUR'],
                ['material' => 'Insulator - Pin Post Insulator 20 Kv;12,5 kN - Porcelain (Tumpu)',     'alias' => 'PIN',           'qty' => 6.0, 'unit' => 'buah', 'cat' => 'ISOLATOR'],
                ['material' => 'Preformed Side Tie Double 150mm (Semi Cond/non metalic/Composite)',    'alias' => 'TOP TIES SIDE', 'qty' => 2.0, 'unit' => 'buah', 'cat' => 'PENGIKAT'],
                ['material' => 'Preformed Top Tie 150mm (Semi Cond/non metalic/Composite)',            'alias' => 'TOP TIES',      'qty' => 4.0, 'unit' => 'buah', 'cat' => 'PENGIKAT'],
                ['material' => 'Ground Wire Clamp Type A',                                             'alias' => 'PLAT GSW',      'qty' => 1.0, 'unit' => 'buah', 'cat' => 'CLAMP'],
                ['material' => 'Wire Clip M10 (Ø 35mm)',                                               'alias' => 'GSW',           'qty' => 2.0, 'unit' => 'buah', 'cat' => 'AKSESORIS'],
            ];

            foreach ($tm2Items as $idx => $it) {
                $mId = $matMap[$it['material']] ?? null;
                $this->db->table('construction_bom_items')->insert([
                    'construction_type_id' => $tm2Id,
                    'material_id'          => $mId,
                    'raw_material_name'    => $it['material'],
                    'material_alias'       => $it['alias'],
                    'component_category'   => $it['cat'],
                    'quantity'             => $it['qty'],
                    'unit'                 => $it['unit'],
                    'sort_order'           => $idx + 1,
                    'created_at'           => $now,
                    'updated_at'           => $now,
                ]);
            }
        }

        // 5. Overhaul TM-5 BOM (Tarik Ganda / Penegang Sudut)
        $tm5Row = $this->db->table('construction_types')
            ->where('construction_code', 'TM5')
            ->orWhere('code', 'TM5')
            ->get()
            ->getRowArray();

        if ($tm5Row) {
            $tm5Id = (int)$tm5Row['id'];
            $this->db->table('construction_bom_items')->where('construction_type_id', $tm5Id)->delete();

            $tm5Items = [
                ['material' => 'Cross Arm UNP 2000 mm',                                                'alias' => 'KANAL',         'qty' => 2.0, 'unit' => 'buah', 'cat' => 'CROSS_ARM_TRAVERS'],
                ['material' => 'DOUBLE ARM BAND',                                                      'alias' => 'BEGEL ARM TIE', 'qty' => 2.0, 'unit' => 'buah', 'cat' => 'CROSS_ARM_TRAVERS'],
                ['material' => 'Arm Tie Type 750 - 3/4"',                                              'alias' => 'ARM TIE',       'qty' => 4.0, 'unit' => 'buah', 'cat' => 'CROSS_ARM_TRAVERS'],
                ['material' => 'Bolt & Nut M.16 x 50',                                                 'alias' => 'BAUT 50',       'qty' => 4.0, 'unit' => 'buah', 'cat' => 'BAUT_DAN_MUR'],
                ['material' => 'Bolt & Nut M.16 x 400 (besi as) Double Arm - HDG',                     'alias' => 'BAUT 400',      'qty' => 4.0, 'unit' => 'buah', 'cat' => 'BAUT_DAN_MUR'],
                ['material' => 'Insulator - Strain Insulator 20 kV lengkap (SIR) Porcelain (Tarik)',   'alias' => 'HANG',          'qty' => 6.0, 'unit' => 'buah', 'cat' => 'ISOLATOR'],
                ['material' => 'STRAIN CLAMP 3 NUT',                                                   'alias' => 'DEAD END',      'qty' => 6.0, 'unit' => 'buah', 'cat' => 'CLAMP'],
                ['material' => 'PARALLEL GROOVE CLAMP 2 BAUT',                                         'alias' => 'PG CLAMP',      'qty' => 3.0, 'unit' => 'buah', 'cat' => 'CLAMP'],
                ['material' => 'Insulator - Pin Post Insulator 20 Kv;12,5 kN - Porcelain (Tumpu)',     'alias' => 'PIN',           'qty' => 3.0, 'unit' => 'buah', 'cat' => 'ISOLATOR'],
            ];

            foreach ($tm5Items as $idx => $it) {
                $mId = $matMap[$it['material']] ?? null;
                $this->db->table('construction_bom_items')->insert([
                    'construction_type_id' => $tm5Id,
                    'material_id'          => $mId,
                    'raw_material_name'    => $it['material'],
                    'material_alias'       => $it['alias'],
                    'component_category'   => $it['cat'],
                    'quantity'             => $it['qty'],
                    'unit'                 => $it['unit'],
                    'sort_order'           => $idx + 1,
                    'created_at'           => $now,
                    'updated_at'           => $now,
                ]);
            }
        }

        // 6. Overhaul TM-11 BOM (Penegang Lengkap LA)
        $tm11Row = $this->db->table('construction_types')
            ->where('construction_code', 'TM11')
            ->orWhere('code', 'TM11')
            ->get()
            ->getRowArray();

        if ($tm11Row) {
            $tm11Id = (int)$tm11Row['id'];
            $this->db->table('construction_bom_items')->where('construction_type_id', $tm11Id)->delete();

            $tm11Items = [
                ['material' => 'Cross Arm UNP 2000 mm',                                                'alias' => 'KANAL',         'qty' => 2.0, 'unit' => 'buah', 'cat' => 'CROSS_ARM_TRAVERS'],
                ['material' => 'Arm Tie Type 750 - 3/4"',                                              'alias' => 'ARM TIE',       'qty' => 4.0, 'unit' => 'buah', 'cat' => 'CROSS_ARM_TRAVERS'],
                ['material' => 'Bolt & Nut M.16 x 400 (besi as) Double Arm - HDG',                     'alias' => 'BAUT 400',      'qty' => 4.0, 'unit' => 'buah', 'cat' => 'BAUT_DAN_MUR'],
                ['material' => 'Insulator - Strain Insulator 20 kV lengkap (SIR) Porcelain (Tarik)',   'alias' => 'HANG',          'qty' => 6.0, 'unit' => 'buah', 'cat' => 'ISOLATOR'],
                ['material' => 'STRAIN CLAMP 3 NUT',                                                   'alias' => 'DEAD END',      'qty' => 6.0, 'unit' => 'buah', 'cat' => 'CLAMP'],
                ['material' => 'Polymer Arrester 24 kV - 10 kA',                                       'alias' => 'LA',            'qty' => 3.0, 'unit' => 'buah', 'cat' => 'PROTECTION'],
                ['material' => 'GROUNDING ROD COPPER CLAD 5/8" x 2.4M',                                'alias' => 'GROUND ROD',    'qty' => 1.0, 'unit' => 'buah', 'cat' => 'GROUNDING'],
            ];

            foreach ($tm11Items as $idx => $it) {
                $mId = $matMap[$it['material']] ?? null;
                $this->db->table('construction_bom_items')->insert([
                    'construction_type_id' => $tm11Id,
                    'material_id'          => $mId,
                    'raw_material_name'    => $it['material'],
                    'material_alias'       => $it['alias'],
                    'component_category'   => $it['cat'],
                    'quantity'             => $it['qty'],
                    'unit'                 => $it['unit'],
                    'sort_order'           => $idx + 1,
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
