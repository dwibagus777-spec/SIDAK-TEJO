<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOperationalWorkAuthorizationsTable extends Migration
{
    public function up(): void
    {
        // 1. Table: operational_work_authorizations
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
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
            'scenario_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
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
            'portfolio_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
            ],
            'portfolio_item_id' => [
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
            'scheduled_date' => [
                'type' => 'DATE',
                'null' => false,
            ],
            'scheduled_window' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'default'    => '08:30 - 12:00',
            ],
            'authorization_status' => [
                'type'       => 'ENUM',
                'constraint' => [
                    'READINESS_CHECK_PENDING',
                    'READINESS_VERIFIED',
                    'EXECUTION_AUTHORIZED',
                    'REVISION_REQUIRED',
                    'AUTHORIZATION_REVOKED',
                    'AUTHORIZATION_SUPERSEDED'
                ],
                'default'    => 'READINESS_CHECK_PENDING',
            ],
            'safety_readiness_json' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'material_readiness_json' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'permit_readiness_json' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'team_readiness_json' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'readiness_score' => [
                'type'       => 'DECIMAL',
                'constraint' => '5,2',
                'default'    => 0.00,
            ],
            'execution_mode_status' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'default'    => 'HUMAN_DIRECTED_EXECUTION_ONLY',
            ],
            'crew_dispatch_status' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'default'    => 'NO_AUTOMATIC_DISPATCH',
            ],
            'personnel_assignment_status' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'default'    => 'AUTHORIZATION_SCOPE_ONLY',
            ],
            'network_operation_status' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'default'    => 'NO_SWITCHING_AUTHORITY',
            ],
            'work_execution_status' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'default'    => 'AUTHORIZED_INTENT_ONLY',
            ],
            'authorizing_official_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],
            'authorizing_official_role' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'authorization_rationale' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'authorization_sha256' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => true,
            ],
            'authorized_at' => [
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
        $this->forge->addUniqueKey('authorization_code', 'uk_auth_code');
        $this->forge->addKey(['slot_id', 'authorization_status'], false, false, 'idx_auth_slot_status');
        $this->forge->addKey(['scenario_id', 'plan_id'], false, false, 'idx_auth_scn_plan');
        $this->forge->createTable('operational_work_authorizations', true);

        // 2. Table: operational_authorization_events (Append-only audit trail)
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
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
            'event_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => false,
            ],
            'previous_status' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'default'    => 'READINESS_CHECK_PENDING',
            ],
            'new_status' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => false,
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
        $this->forge->addKey(['authorization_id', 'event_type'], false, false, 'idx_auth_ev_id');
        $this->forge->createTable('operational_authorization_events', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('operational_authorization_events', true);
        $this->forge->dropTable('operational_work_authorizations', true);
    }
}
