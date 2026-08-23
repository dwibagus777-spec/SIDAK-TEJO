<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOperationalSchedulingScenariosTable extends Migration
{
    public function up(): void
    {
        // 1. Table: operational_scheduling_scenarios
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'scenario_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
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
            'scenario_title' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'scenario_strategy' => [
                'type'       => 'ENUM',
                'constraint' => [
                    'BALANCED_PDKB_PREFERRED',
                    'AGGRESSIVE_OUTAGE_WINDOW',
                    'CONSERVATIVE_CAPACITY'
                ],
                'default'    => 'BALANCED_PDKB_PREFERRED',
            ],
            'scenario_status' => [
                'type'       => 'ENUM',
                'constraint' => [
                    'SCENARIO_DRAFT',
                    'UNDER_CAPACITY_REVIEW',
                    'SCENARIO_APPROVED',
                    'REVISION_REQUIRED',
                    'SCENARIO_SUPERSEDED'
                ],
                'default'    => 'SCENARIO_DRAFT',
            ],
            'total_scheduled_plans_count' => [
                'type'       => 'INT',
                'constraint' => 6,
                'default'    => 0,
            ],
            'total_estimated_man_days' => [
                'type'       => 'DECIMAL',
                'constraint' => '6,1',
                'default'    => 0.0,
            ],
            'peak_daily_outage_count' => [
                'type'       => 'INT',
                'constraint' => 6,
                'default'    => 0,
            ],
            'capacity_assessment_json' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'dispatch_status' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'default'    => 'NO_DISPATCH_AUTHORITY',
            ],
            'personnel_assignment_status' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'default'    => 'CAPACITY_ESTIMATE_ONLY',
            ],
            'network_operation_status' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'default'    => 'NO_SWITCHING_AUTHORITY',
            ],
            'work_order_status' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'default'    => 'NOT_A_WORK_ORDER',
            ],
            'created_by_actor_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'default'    => 'HUMAN_SCHEDULING_PLANNER',
            ],
            'approver_actor_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],
            'approval_rationale' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'revision_reason' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'approved_at' => [
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
        $this->forge->addUniqueKey('scenario_code', 'uk_sched_code');
        $this->forge->addKey(['portfolio_id', 'scenario_status'], false, false, 'idx_sched_port_status');
        $this->forge->createTable('operational_scheduling_scenarios', true);

        // 2. Table: operational_scheduled_slots
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'scenario_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
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
            'priority_tier' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'default'    => 'TIER_1_IMMEDIATE_SCHEDULING',
            ],
            'scheduled_date' => [
                'type' => 'DATE',
                'null' => false,
            ],
            'scheduled_start_time' => [
                'type' => 'TIME',
                'null' => false,
            ],
            'scheduled_end_time' => [
                'type' => 'TIME',
                'null' => false,
            ],
            'estimated_duration_hours' => [
                'type'       => 'DECIMAL',
                'constraint' => '4,1',
                'default'    => 4.0,
            ],
            'estimated_crew_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'default'    => 'REGU_PDKB_BERTEGANGAN',
            ],
            'outage_required' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ],
            'capacity_override_applied' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ],
            'scheduling_notes' => [
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

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['scenario_id', 'portfolio_item_id'], false, false, 'idx_slot_scn_item');
        $this->forge->addKey(['scheduled_date', 'outage_required'], false, false, 'idx_slot_date_outage');
        $this->forge->createTable('operational_scheduled_slots', true);

        // 3. Table: operational_scheduling_slot_events (Append-only audit trail)
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
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
            'plan_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
            ],
            'event_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => false,
            ],
            'previous_payload_json' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'new_payload_json' => [
                'type' => 'LONGTEXT',
                'null' => true,
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
        $this->forge->addKey(['scenario_id', 'slot_id'], false, false, 'idx_slot_event_scn');
        $this->forge->createTable('operational_scheduling_slot_events', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('operational_scheduling_slot_events', true);
        $this->forge->dropTable('operational_scheduled_slots', true);
        $this->forge->dropTable('operational_scheduling_scenarios', true);
    }
}
