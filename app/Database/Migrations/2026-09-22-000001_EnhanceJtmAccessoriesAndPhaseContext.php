<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * CR-ACCESSORY-PHASE-01: JTM Accessories Phase Context, Canonical Units, and Immutable Snapshots
 *
 * Implements:
 * 1. 3-Domain Data Model: Material (Identity), Configuration (Qty, Phase), Observation (Condition, Notes)
 * 2. Idempotent alterations to master_jtm_accessories and temuan_accessories
 * 3. Canonical material units ('buah') for individual unit items
 * 4. Distinct canonical categories: CONDUCTOR_GROUNDING, PROTECTION, MONITORING
 * 5. Strict snapshot columns in temuan_accessories
 */
class EnhanceJtmAccessoriesAndPhaseContext extends Migration
{
    public function up()
    {
        $db = $this->db ?? \Config\Database::connect();

        // 1. Alter Table: master_jtm_accessories (Additive & Idempotent)
        if ($db->tableExists('master_jtm_accessories')) {
            $fieldsToAdd = [];
            if (!$db->fieldExists('sub_category', 'master_jtm_accessories')) {
                $fieldsToAdd['sub_category'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'default'    => 'CONDUCTOR_GROUNDING',
                    'after'      => 'category',
                ];
            }
            if (!$db->fieldExists('phase_applicable', 'master_jtm_accessories')) {
                $fieldsToAdd['phase_applicable'] = [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 1,
                    'after'      => 'description',
                ];
            }
            if (!$db->fieldExists('phase_mode', 'master_jtm_accessories')) {
                $fieldsToAdd['phase_mode'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 30,
                    'default'    => 'MULTI_PHASE',
                    'after'      => 'phase_applicable',
                ];
            }
            if (!$db->fieldExists('default_qty', 'master_jtm_accessories')) {
                $fieldsToAdd['default_qty'] = [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 3,
                    'after'      => 'phase_mode',
                ];
            }
            if (!$db->fieldExists('unit', 'master_jtm_accessories')) {
                $fieldsToAdd['unit'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 20,
                    'default'    => 'buah',
                    'after'      => 'default_qty',
                ];
            }
            if (!$db->fieldExists('canonical_material_code', 'master_jtm_accessories')) {
                $fieldsToAdd['canonical_material_code'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 60,
                    'null'       => true,
                    'after'      => 'unit',
                ];
            }

            if (!empty($fieldsToAdd)) {
                $this->forge->addColumn('master_jtm_accessories', $fieldsToAdd);
            }

            // Seed/Update 10 Canonical Accessories with Correct Semantic Categories
            $now = date('Y-m-d H:i:s');
            $canonicalAccessories = [
                // 1. CONDUCTOR & GROUNDING
                [
                    'code'                    => 'GSW',
                    'name'                    => 'GSW',
                    'category'                => 'CONDUCTOR_GROUNDING',
                    'sub_category'            => 'CONDUCTOR_GROUNDING',
                    'description'             => 'Ground Steel Wire / Kawat Petir JTM',
                    'phase_applicable'        => 1,
                    'phase_mode'              => 'MULTI_PHASE',
                    'default_qty'             => 3,
                    'unit'                    => 'buah',
                    'canonical_material_code' => null,
                    'sort_order'              => 1,
                    'is_active'               => 1,
                ],
                [
                    'code'                    => 'GROUND_GSW',
                    'name'                    => 'GROUND GSW',
                    'category'                => 'CONDUCTOR_GROUNDING',
                    'sub_category'            => 'CONDUCTOR_GROUNDING',
                    'description'             => 'Pembumian Kawat GSW / Grounding Down Lead',
                    'phase_applicable'        => 0,
                    'phase_mode'              => 'NONE',
                    'default_qty'             => 1,
                    'unit'                    => 'buah',
                    'canonical_material_code' => null,
                    'sort_order'              => 2,
                    'is_active'               => 1,
                ],
                [
                    'code'                    => 'PENGHALANG_BINATANG',
                    'name'                    => 'PENGHALANG BINATANG',
                    'category'                => 'CONDUCTOR_GROUNDING',
                    'sub_category'            => 'CONDUCTOR_GROUNDING',
                    'description'             => 'Animal Guard / Penghalang Panjat Binatang',
                    'phase_applicable'        => 0,
                    'phase_mode'              => 'NONE',
                    'default_qty'             => 1,
                    'unit'                    => 'buah',
                    'canonical_material_code' => null,
                    'sort_order'              => 3,
                    'is_active'               => 1,
                ],
                // 2. PROTECTION
                [
                    'code'                    => 'EGLA',
                    'name'                    => 'EGLA',
                    'category'                => 'PROTECTION',
                    'sub_category'            => 'PROTECTION',
                    'description'             => 'Externally Gapped Line Arrester',
                    'phase_applicable'        => 1,
                    'phase_mode'              => 'MULTI_PHASE',
                    'default_qty'             => 3,
                    'unit'                    => 'buah',
                    'canonical_material_code' => null,
                    'sort_order'              => 4,
                    'is_active'               => 1,
                ],
                [
                    'code'                    => 'CLD',
                    'name'                    => 'CLD',
                    'category'                => 'PROTECTION',
                    'sub_category'            => 'PROTECTION',
                    'description'             => 'Current Limiting Device',
                    'phase_applicable'        => 1,
                    'phase_mode'              => 'MULTI_PHASE',
                    'default_qty'             => 3,
                    'unit'                    => 'buah',
                    'canonical_material_code' => null,
                    'sort_order'              => 5,
                    'is_active'               => 1,
                ],
                [
                    'code'                    => 'MCA',
                    'name'                    => 'MCA',
                    'category'                => 'PROTECTION',
                    'sub_category'            => 'PROTECTION',
                    'description'             => 'Multi-Chamber Arrester',
                    'phase_applicable'        => 1,
                    'phase_mode'              => 'MULTI_PHASE',
                    'default_qty'             => 3,
                    'unit'                    => 'buah',
                    'canonical_material_code' => null,
                    'sort_order'              => 6,
                    'is_active'               => 1,
                ],
                [
                    'code'                    => 'ARRESTER',
                    'name'                    => 'Arrester Jaringan',
                    'category'                => 'PROTECTION',
                    'sub_category'            => 'PROTECTION',
                    'description'             => 'Polymer Lightning Arrester 24 kV 10 kA Jaringan',
                    'phase_applicable'        => 1,
                    'phase_mode'              => 'MULTI_PHASE',
                    'default_qty'             => 3,
                    'unit'                    => 'buah',
                    'canonical_material_code' => 'MAT-PROT-LA-24KV',
                    'sort_order'              => 7,
                    'is_active'               => 1,
                ],
                [
                    'code'                    => 'FCO',
                    'name'                    => 'FCO',
                    'category'                => 'PROTECTION',
                    'sub_category'            => 'PROTECTION',
                    'description'             => 'Fuse Cut Out Switch 24 kV 100A',
                    'phase_applicable'        => 1,
                    'phase_mode'              => 'MULTI_PHASE',
                    'default_qty'             => 3,
                    'unit'                    => 'buah',
                    'canonical_material_code' => 'MAT-PROT-FCO-24KV',
                    'sort_order'              => 8,
                    'is_active'               => 1,
                ],
                [
                    'code'                    => 'FCO_BRANCH',
                    'name'                    => 'FCO Branch / Lateral',
                    'category'                => 'PROTECTION',
                    'sub_category'            => 'PROTECTION',
                    'description'             => 'Fuse Cut Out Percabangan / Lateral Tap 24 kV',
                    'phase_applicable'        => 1,
                    'phase_mode'              => 'MULTI_PHASE',
                    'default_qty'             => 3,
                    'unit'                    => 'buah',
                    'canonical_material_code' => 'MAT-PROT-FCO-LAT',
                    'sort_order'              => 9,
                    'is_active'               => 1,
                ],
                // 3. MONITORING
                [
                    'code'                    => 'FIOHL',
                    'name'                    => 'FIOHL',
                    'category'                => 'MONITORING',
                    'sub_category'            => 'MONITORING',
                    'description'             => 'Fault Indicator Overhead Line 20 kV',
                    'phase_applicable'        => 1,
                    'phase_mode'              => 'MULTI_PHASE',
                    'default_qty'             => 3,
                    'unit'                    => 'buah',
                    'canonical_material_code' => 'MAT-IND-FIOHL',
                    'sort_order'              => 10,
                    'is_active'               => 1,
                ],
            ];

            foreach ($canonicalAccessories as $acc) {
                $existing = $db->table('master_jtm_accessories')->where('code', $acc['code'])->get()->getRowArray();
                if ($existing) {
                    $db->table('master_jtm_accessories')->where('id', $existing['id'])->update([
                        'name'                    => $acc['name'],
                        'category'                => $acc['category'],
                        'sub_category'            => $acc['sub_category'],
                        'description'             => $acc['description'],
                        'phase_applicable'        => $acc['phase_applicable'],
                        'phase_mode'              => $acc['phase_mode'],
                        'default_qty'             => $acc['default_qty'],
                        'unit'                    => $acc['unit'],
                        'canonical_material_code' => $acc['canonical_material_code'],
                        'sort_order'              => $acc['sort_order'],
                        'is_active'               => 1,
                        'updated_at'              => $now,
                    ]);
                } else {
                    $acc['created_at'] = $now;
                    $acc['updated_at'] = $now;
                    $db->table('master_jtm_accessories')->insert($acc);
                }
            }
        }

        // 2. Alter Table: temuan_accessories (Configuration & Snapshot Invariants)
        if ($db->tableExists('temuan_accessories')) {
            $accColsToAdd = [];
            if (!$db->fieldExists('accessory_code', 'temuan_accessories')) {
                $accColsToAdd['accessory_code'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'null'       => true,
                    'after'      => 'accessory_type_id',
                ];
            }
            if (!$db->fieldExists('category_snapshot', 'temuan_accessories')) {
                $accColsToAdd['category_snapshot'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'null'       => true,
                    'after'      => 'accessory_name_snapshot',
                ];
            }
            if (!$db->fieldExists('qty', 'temuan_accessories')) {
                $accColsToAdd['qty'] = [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'default'    => 1,
                    'after'      => 'status',
                ];
            }
            if (!$db->fieldExists('unit', 'temuan_accessories')) {
                $accColsToAdd['unit'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 20,
                    'default'    => 'buah',
                    'after'      => 'qty',
                ];
            }
            if (!$db->fieldExists('phase_applicable', 'temuan_accessories')) {
                $accColsToAdd['phase_applicable'] = [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 0,
                    'after'      => 'unit',
                ];
            }
            if (!$db->fieldExists('phase_configuration', 'temuan_accessories')) {
                $accColsToAdd['phase_configuration'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 30,
                    'null'       => true,
                    'after'      => 'phase_applicable',
                ];
            }
            if (!$db->fieldExists('phase_positions', 'temuan_accessories')) {
                $accColsToAdd['phase_positions'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'null'       => true,
                    'after'      => 'phase_configuration',
                ];
            }

            if (!empty($accColsToAdd)) {
                $this->forge->addColumn('temuan_accessories', $accColsToAdd);
            }
        }

        // 3. Update master_materials Canonical Units to 'buah' & Seed FIOHL / FCO Lateral
        if ($db->tableExists('master_materials')) {
            // Update individual unit materials to 'buah'
            $db->table('master_materials')
                ->whereIn('material_code', [
                    'MAT-ISO-PIN-20KV',
                    'MAT-ISO-HANG-20KV',
                    'MAT-PROT-LA-24KV',
                    'MAT-PROT-FCO-24KV',
                ])
                ->update(['satuan' => 'buah']);

            // Insert MAT-IND-FIOHL if not exists
            $existingFiohl = $db->table('master_materials')->where('material_code', 'MAT-IND-FIOHL')->get()->getRowArray();
            if (!$existingFiohl) {
                $db->table('master_materials')->insert([
                    'material_code'     => 'MAT-IND-FIOHL',
                    'nama_material'     => 'Fault Indicator Overhead Line (FIOHL)',
                    'nama_lapangan'     => 'FIOHL',
                    'satuan'            => 'buah',
                    'material_domain'   => 'JTM',
                    'material_category' => 'MONITORING',
                    'specification'     => 'Fault Indicator 20 kV Overhead Lines with Visual Flag / LED',
                    'source_workbook'   => 'CANONICAL_2026.xlsx',
                    'source_sheet'      => 'MONITORING',
                    'status'            => 'AKTIF',
                    'created_at'        => date('Y-m-d H:i:s'),
                    'updated_at'        => date('Y-m-d H:i:s'),
                ]);
            } else {
                $db->table('master_materials')->where('id', $existingFiohl['id'])->update(['satuan' => 'buah']);
            }

            // Insert MAT-PROT-FCO-LAT if not exists
            $existingFcoLat = $db->table('master_materials')->where('material_code', 'MAT-PROT-FCO-LAT')->get()->getRowArray();
            if (!$existingFcoLat) {
                $db->table('master_materials')->insert([
                    'material_code'     => 'MAT-PROT-FCO-LAT',
                    'nama_material'     => 'Fuse Cut Out Branch / Lateral 24 kV',
                    'nama_lapangan'     => 'FCO BRANCH',
                    'satuan'            => 'buah',
                    'material_domain'   => 'JTM',
                    'material_category' => 'PROTECTION',
                    'specification'     => '24 kV 100A Branch/Lateral Tap Protection Cut Out Switch',
                    'source_workbook'   => 'CANONICAL_2026.xlsx',
                    'source_sheet'      => 'PROTECTION',
                    'status'            => 'AKTIF',
                    'created_at'        => date('Y-m-d H:i:s'),
                    'updated_at'        => date('Y-m-d H:i:s'),
                ]);
            } else {
                $db->table('master_materials')->where('id', $existingFcoLat['id'])->update(['satuan' => 'buah']);
            }
        }
    }

    public function down()
    {
        // Safe additive migration: zero destructive down drops
    }
}
