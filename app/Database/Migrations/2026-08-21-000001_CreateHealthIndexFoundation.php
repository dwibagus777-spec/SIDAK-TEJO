<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateHealthIndexFoundation extends Migration
{
    public function up()
    {
        // 1. CREATE TABLE hi_components
        if (!$this->db->tableExists('hi_components')) {
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
                'description' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'display_order' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'default'    => 0,
                ],
                'icon' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'null'       => true,
                ],
                'color' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 30,
                    'null'       => true,
                ],
                'is_active' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 1,
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
            $this->forge->createTable('hi_components');
        }

        // 2. CREATE TABLE hi_rules
        if (!$this->db->tableExists('hi_rules')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'component_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'construction_type_id' => [
                    'type'       => 'INT', // Matched with construction_types.id (INT 11 UNSIGNED)
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'weight' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,2',
                    'default'    => '1.00',
                ],
                'min_deduction' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,2',
                    'default'    => '0.00',
                ],
                'max_deduction' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,2',
                    'default'    => '25.00',
                ],
                'config_json' => [
                    'type' => 'JSON',
                    'null' => true,
                ],
                'priority' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'default'    => 100,
                ],
                'rule_version' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 20,
                    'default'    => '1.0',
                ],
                'effective_from' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'effective_until' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'is_active' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 1,
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
            $this->forge->addForeignKey('component_id', 'hi_components', 'id', 'CASCADE', 'CASCADE');
            if ($this->db->tableExists('construction_types')) {
                $this->forge->addForeignKey('construction_type_id', 'construction_types', 'id', 'RESTRICT', 'CASCADE');
            }
            $this->forge->addKey(['component_id', 'construction_type_id', 'is_active', 'priority'], false, false, 'idx_hirules_lookup');
            $this->forge->createTable('hi_rules');
        }

        // 3. CREATE TABLE asset_health_history
        if (!$this->db->tableExists('asset_health_history')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'BIGINT',
                    'constraint'     => 20,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'asset_id' => [
                    'type'       => 'INT', // Matched with assets.id (INT 11 UNSIGNED)
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'base_score' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,2',
                    'default'    => '100.00',
                ],
                'total_deduction' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,2',
                    'default'    => '0.00',
                ],
                'health_score' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,2',
                ],
                'health_category' => [
                    'type'       => 'ENUM',
                    'constraint' => ['VERY_GOOD', 'GOOD', 'FAIR', 'POOR', 'CRITICAL'],
                ],
                'explanation_json' => [
                    'type' => 'JSON',
                ],
                'rules_snapshot_json' => [
                    'type' => 'JSON',
                    'null' => true,
                ],
                'trigger_event' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                ],
                'calculation_source' => [
                    'type'       => 'ENUM',
                    'constraint' => ['MANUAL', 'EVENT', 'SCHEDULED', 'BATCH', 'SYSTEM'],
                    'default'    => 'EVENT',
                ],
                'engine_version' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 20,
                    'default'    => '1.0',
                ],
                'calculation_hash' => [
                    'type'       => 'CHAR',
                    'constraint' => 64,
                    'null'       => true,
                ],
                'calculated_by' => [
                    'type'       => 'INT', // Matched with users.id (INT 11 UNSIGNED)
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'calculated_at' => [
                    'type' => 'DATETIME',
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['asset_id', 'calculated_at'], false, false, 'idx_hihistory_asset_date');
            $this->forge->createTable('asset_health_history');

            // Explicit Foreign Key Addition
            if ($this->db->tableExists('assets')) {
                $fkCheck = $this->db->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'asset_health_history' AND CONSTRAINT_NAME = 'fk_hihistory_asset'")->getRow();
                if (!$fkCheck) {
                    $this->db->query("ALTER TABLE `asset_health_history` ADD CONSTRAINT `fk_hihistory_asset` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;");
                }
            }

            if ($this->db->tableExists('users')) {
                $fkCheck = $this->db->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'asset_health_history' AND CONSTRAINT_NAME = 'fk_hihistory_user'")->getRow();
                if (!$fkCheck) {
                    $this->db->query("ALTER TABLE `asset_health_history` ADD CONSTRAINT `fk_hihistory_user` FOREIGN KEY (`calculated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;");
                }
            }
        }

        // 4. Additive HI Snapshot Cache Columns to assets table
        if ($this->db->tableExists('assets')) {
            $assetFields = [];
            if (!$this->db->fieldExists('health_score', 'assets')) {
                $assetFields['health_score'] = [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,2',
                    'null'       => true,
                ];
            }
            if (!$this->db->fieldExists('health_category', 'assets')) {
                $assetFields['health_category'] = [
                    'type'       => 'ENUM',
                    'constraint' => ['VERY_GOOD', 'GOOD', 'FAIR', 'POOR', 'CRITICAL'],
                    'null'       => true,
                ];
            }
            if (!$this->db->fieldExists('health_index_last_calculated_at', 'assets')) {
                $assetFields['health_index_last_calculated_at'] = [
                    'type' => 'DATETIME',
                    'null' => true,
                ];
            }
            if (!empty($assetFields)) {
                $this->forge->addColumn('assets', $assetFields);
            }
        }
    }

    public function down()
    {
        $this->forge->dropTable('asset_health_history', true);
        $this->forge->dropTable('hi_rules', true);
        $this->forge->dropTable('hi_components', true);
    }
}
