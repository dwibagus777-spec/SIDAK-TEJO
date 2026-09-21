<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * CR-HOTFIX-02: Additive Domain — JTM & Conductor Accessories
 *
 * Implements:
 * 1. master_jtm_accessories: Catalog of JTM / Conductor accessories (GSW, EGLA, CLD, MCA, GROUND GSW, PENGHALANG BINATANG)
 * 2. temuan_accessories: Finding-to-Asset Accessory observations with snapshot integrity and duplicate protection
 *
 * Guaranteed Invariants:
 * - Completely additive, zero alteration to existing assets, translines, topology, temuan_materials, or fault_records.
 * - Snapshot column `accessory_name_snapshot` preserves historical truth.
 * - Unique constraint on (temuan_id, accessory_type_id) prevents duplicate entries per finding.
 */
class CreateJtmAccessoriesTables extends Migration
{
    public function up()
    {
        $db = $this->db ?? \Config\Database::connect();

        // 1. Table: master_jtm_accessories
        if (!$db->tableExists('master_jtm_accessories')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'code' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                ],
                'name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                ],
                'category' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'default'    => 'JTM',
                ],
                'is_active' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 1,
                ],
                'sort_order' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 0,
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
            $this->forge->addUniqueKey('code');
            $this->forge->createTable('master_jtm_accessories', true);
        }

        if ($db->tableExists('master_jtm_accessories') && $db->table('master_jtm_accessories')->countAllResults() === 0) {
            $now = date('Y-m-d H:i:s');
            $initialMaster = [
                ['code' => 'GSW',                'name' => 'GSW',                 'category' => 'JTM', 'sort_order' => 1, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['code' => 'EGLA',               'name' => 'EGLA',                'category' => 'JTM', 'sort_order' => 2, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['code' => 'CLD',                'name' => 'CLD',                 'category' => 'JTM', 'sort_order' => 3, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['code' => 'MCA',                'name' => 'MCA',                 'category' => 'JTM', 'sort_order' => 4, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['code' => 'GROUND_GSW',         'name' => 'GROUND GSW',          'category' => 'JTM', 'sort_order' => 5, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['code' => 'PENGHALANG_BINATANG', 'name' => 'PENGHALANG BINATANG', 'category' => 'JTM', 'sort_order' => 6, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now],
            ];
            $db->table('master_jtm_accessories')->insertBatch($initialMaster);
        }

        // 2. Table: temuan_accessories
        if (!$db->tableExists('temuan_accessories')) {
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
                'accessory_type_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'accessory_name_snapshot' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                ],
                'status' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 20,
                    'default'    => 'ADA',
                ],
                'condition' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 30,
                    'default'    => 'BAIK',
                ],
                'note' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'photo_url' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
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
            $this->forge->addUniqueKey(['temuan_id', 'accessory_type_id'], 'idx_temuan_acc_unique');
            $this->forge->addKey('asset_id', false, false, 'idx_temuan_acc_asset');
            $this->forge->addKey('temuan_id', false, false, 'idx_temuan_acc_temuan');
            $this->forge->createTable('temuan_accessories', true);
        }
    }

    public function down()
    {
        $this->forge->dropTable('temuan_accessories', true);
        $this->forge->dropTable('master_jtm_accessories', true);
    }
}
