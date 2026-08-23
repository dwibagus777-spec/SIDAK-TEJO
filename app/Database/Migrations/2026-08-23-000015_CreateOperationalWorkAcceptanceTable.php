<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOperationalWorkAcceptanceTable extends Migration
{
    public function up(): void
    {
        // 1. Table: operational_work_acceptances
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'acceptance_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
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
            'acceptance_status' => [
                'type'       => 'ENUM',
                'constraint' => [
                    'ACCEPTANCE_REVIEW_PENDING',
                    'REWORK_REQUIRED',
                    'ACCEPTANCE_REJECTED',
                    'WORK_ACCEPTED',
                    'WORK_CLOSED'
                ],
                'default'    => 'ACCEPTANCE_REVIEW_PENDING',
            ],
            'evidence_verification_json' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'technical_quality_json' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'material_audit_json' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'asbuilt_verification_json' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'quality_score' => [
                'type'       => 'DECIMAL',
                'constraint' => '5,2',
                'default'    => 0.00,
            ],
            'rework_instructions' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'accepting_inspector_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],
            'accepting_inspector_role' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'closing_manager_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],
            'closing_manager_role' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'acceptance_rationale' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'closure_rationale' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'acceptance_certificate_sha256' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => true,
            ],
            'accepted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'closed_at' => [
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
        $this->forge->addUniqueKey('acceptance_code', 'uk_acc_code');
        $this->forge->addKey(['execution_id', 'acceptance_status'], false, false, 'idx_acc_exec_status');
        $this->forge->addKey(['plan_id', 'acceptance_status'], false, false, 'idx_acc_plan_status');
        $this->forge->createTable('operational_work_acceptances', true);

        // 2. Table: operational_acceptance_events (Append-only audit trail)
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'acceptance_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
                'null'       => false,
            ],
            'acceptance_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
            ],
            'event_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => false,
            ],
            'previous_status' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'default'    => 'ACCEPTANCE_REVIEW_PENDING',
            ],
            'new_status' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => false,
            ],
            'quality_score' => [
                'type'       => 'DECIMAL',
                'constraint' => '5,2',
                'default'    => 0.00,
            ],
            'decision_rationale' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'decided_by' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => false,
            ],
            'decided_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['acceptance_id', 'event_type'], false, false, 'idx_acc_ev_type');
        $this->forge->createTable('operational_acceptance_events', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('operational_acceptance_events', true);
        $this->forge->dropTable('operational_work_acceptances', true);
    }
}
