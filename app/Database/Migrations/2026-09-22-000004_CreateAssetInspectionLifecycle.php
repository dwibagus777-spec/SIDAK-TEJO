<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration 2026-09-22-000004: Create Asset Inspection Lifecycle (Phase 4)
 * 
 * Implements GIS Inspection Lifecycle States:
 * - PLANNED_PENDING 🟠: Asset scheduled in planning, awaiting inspection
 * - IN_PROGRESS 🔵: Asset currently undergoing inspection
 * - INSPECTED 🟢: Inspection completed (Crucial Invariant: INSPECTED != HAS_FINDING; 0 findings is valid inspected!)
 * - NOT_PLANNED ⚪: Asset outside current operational planning
 */
class CreateAssetInspectionLifecycle extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('asset_inspection_states')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'planning_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                    'default'    => 0,
                ],
                'asset_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'status' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 30,
                    'default'    => 'PLANNED_PENDING',
                ],
                'finding_count' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'default'    => 0,
                ],
                'inspected_by' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'inspected_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'notes' => [
                    'type' => 'TEXT',
                    'null' => true,
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
            $this->forge->addKey(['asset_id', 'status']);
            $this->forge->addKey(['planning_id', 'status']);
            $this->forge->addUniqueKey(['asset_id', 'planning_id']);
            $this->forge->createTable('asset_inspection_states', true);
        }
    }

    public function down()
    {
        $this->forge->dropTable('asset_inspection_states', true);
    }
}
