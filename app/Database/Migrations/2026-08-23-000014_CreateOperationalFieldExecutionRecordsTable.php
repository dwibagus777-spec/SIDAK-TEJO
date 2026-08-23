<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOperationalFieldExecutionRecordsTable extends Migration
{
    public function up(): void
    {
        // 1. Table: operational_field_execution_records
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'execution_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
            ],
            'authorization_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
                'null'       => false,
            ],
            'authorization_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
            ],
            'scenario_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
                'null'       => false,
            ],
            'slot_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
                'null'       => false,
            ],
            'portfolio_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
                'null'       => false,
            ],
            'plan_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
                'null'       => false,
            ],
            'plan_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
            ],
            'candidate_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
                'null'       => false,
            ],
            'snapshot_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
                'null'       => false,
            ],
            'feeder_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'default'    => 'BALUNG',
            ],
            'section_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'default'    => 'BALUNG-03',
            ],
            'execution_status' => [
                'type'       => 'ENUM',
                'constraint' => [
                    'WORK_PENDING_FIELD_START',
                    'WORK_IN_PROGRESS',
                    'WORK_PAUSED_SAFETY_HOLD',
                    'WORK_COMPLETED_PENDING_ACCEPTANCE',
                    'WORK_ABORTED_FIELD_CONSTRAINTS'
                ],
                'default'    => 'WORK_PENDING_FIELD_START',
            ],
            'progress_percentage' => [
                'type'       => 'DECIMAL',
                'constraint' => '5,2',
                'default'    => 0.00,
            ],
            'field_start_initiated_by' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],
            'field_start_initiated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'field_start_actor_role' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'field_start_rationale' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'work_started_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'work_completed_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'field_supervisor_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],
            'field_crew_count' => [
                'type'       => 'INT',
                'constraint' => 4,
                'default'    => 4,
            ],
            'before_evidence_json' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'after_evidence_json' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'actual_materials_json' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'safety_hold_reason' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'safety_hold_declared_by' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],
            'safety_hold_declared_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'field_incident_notes' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'completion_declaration_rationale' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'field_completion_declared_by' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],
            'field_completion_declared_at' => [
                'type' => 'DATETIME',
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

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('execution_code', 'uk_exec_code');
        $this->forge->addKey(['authorization_id', 'execution_status'], false, false, 'idx_exec_auth_status');
        $this->forge->addKey(['plan_id', 'execution_status'], false, false, 'idx_exec_plan_status');
        $this->forge->createTable('operational_field_execution_records', true);

        // 2. Table: operational_execution_progress_events (Append-only audit ledger)
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'execution_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
                'null'       => false,
            ],
            'execution_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
            ],
            'event_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => false,
            ],
            'progress_percentage' => [
                'type'       => 'DECIMAL',
                'constraint' => '5,2',
                'default'    => 0.00,
            ],
            'event_description' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'evidence_metadata_json' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'decision_rationale' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'recorded_by' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => false,
            ],
            'recorded_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['execution_id', 'event_type'], false, false, 'idx_exec_ev_type');
        $this->forge->createTable('operational_execution_progress_events', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('operational_execution_progress_events', true);
        $this->forge->dropTable('operational_field_execution_records', true);
    }
}
