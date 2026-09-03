<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * MR-01 Phase 3B: Finding Material Transaction Workflow
 * Creates the additive transaction table `temuan_materials`.
 *
 * Guaranteed Invariants:
 * - Zero Financial / Price / Rupiah columns
 * - Zero Procurement / Vendor / PO columns
 * - Zero Stock / Warehouse / Deduction columns
 * - Strict Canonical Snapshot Columns for Historical Immutability
 * - Uniqueness on (temuan_id, asset_id, material_id)
 */
class CreateTemuanMaterialsTable extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        if (!$db->tableExists('temuan_materials')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'temuan_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'asset_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'construction_type_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'material_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                // SNAPSHOT COLUMNS: Preserves Historical Truth of the Moment
                'canonical_code_snapshot' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 60,
                ],
                'canonical_name_snapshot' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 150,
                ],
                'unit_snapshot' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 20,
                ],
                'quantity' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '10,2',
                ],
                'justification_note' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'source_mode' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 30,
                    'default'    => 'BOM_PICKER',
                ],
                'created_by' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addKey('temuan_id');
            $this->forge->addKey('asset_id');
            $this->forge->addKey('material_id');
            $this->forge->addKey('construction_type_id');

            // Enforce Unique Combination: exactly 1 line per material per asset in a finding
            $this->forge->addUniqueKey(['temuan_id', 'asset_id', 'material_id'], 'uq_temuan_asset_material');

            if ($db->tableExists('temuan')) {
                $this->forge->addForeignKey('temuan_id', 'temuan', 'id', 'CASCADE', 'CASCADE');
            }
            if ($db->tableExists('assets')) {
                $this->forge->addForeignKey('asset_id', 'assets', 'id', 'RESTRICT', 'CASCADE');
            }
            if ($db->tableExists('construction_types')) {
                $this->forge->addForeignKey('construction_type_id', 'construction_types', 'id', 'RESTRICT', 'CASCADE');
            }
            if ($db->tableExists('master_materials')) {
                $this->forge->addForeignKey('material_id', 'master_materials', 'id', 'RESTRICT', 'CASCADE');
            }

            $this->forge->createTable('temuan_materials', true);
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();
        if ($db->tableExists('temuan_materials')) {
            $this->forge->dropTable('temuan_materials', true);
        }
    }
}
