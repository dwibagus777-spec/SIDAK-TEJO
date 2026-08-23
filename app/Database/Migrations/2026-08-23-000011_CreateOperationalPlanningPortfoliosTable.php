<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOperationalPlanningPortfoliosTable extends Migration
{
    public function up(): void
    {
        // 1. Table: operational_planning_portfolios
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'portfolio_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
            ],
            'portfolio_title' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'period_year' => [
                'type'       => 'INT',
                'constraint' => 4,
                'null'       => false,
            ],
            'period_week' => [
                'type'       => 'INT',
                'constraint' => 2,
                'null'       => false,
            ],
            'portfolio_status' => [
                'type'       => 'ENUM',
                'constraint' => [
                    'PORTFOLIO_DRAFT',
                    'UNDER_PORTFOLIO_REVIEW',
                    'PORTFOLIO_RATIFIED',
                    'PORTFOLIO_ARCHIVED'
                ],
                'default'    => 'PORTFOLIO_DRAFT',
            ],
            'total_plans_count' => [
                'type'       => 'INT',
                'constraint' => 6,
                'default'    => 0,
            ],
            'total_outage_plans_count' => [
                'type'       => 'INT',
                'constraint' => 6,
                'default'    => 0,
            ],
            'tier_1_plans_count' => [
                'type'       => 'INT',
                'constraint' => 6,
                'default'    => 0,
            ],
            'tier_2_plans_count' => [
                'type'       => 'INT',
                'constraint' => 6,
                'default'    => 0,
            ],
            'tier_3_plans_count' => [
                'type'       => 'INT',
                'constraint' => 6,
                'default'    => 0,
            ],
            'portfolio_risk_summary_json' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'material_demand_summary_json' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'material_aggregation_status' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'default'    => 'INDICATIVE_PORTFOLIO_ESTIMATE_ONLY',
            ],
            'created_by_actor_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'default'    => 'HUMAN_PORTFOLIO_MANAGER',
            ],
            'governing_manager_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],
            'governing_manager_role' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'ratification_rationale' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'ratified_at' => [
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
        $this->forge->addUniqueKey('portfolio_code', 'uk_port_code');
        $this->forge->addKey('portfolio_status', false, false, 'idx_port_status');
        $this->forge->addKey(['period_year', 'period_week'], false, false, 'idx_port_period');
        $this->forge->createTable('operational_planning_portfolios', true);

        // 2. Table: operational_portfolio_items
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
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
            'candidate_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
            ],
            'snapshot_id' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
                'null'       => false,
            ],
            'snapshot_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
            ],
            'penyulang_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'feeder_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'default'    => 'BALUNG',
            ],
            'section_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'section_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'default'    => 'BALUNG-03',
            ],
            'work_category' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'default'    => 'ROW_CLEARANCE',
            ],
            'outage_required' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ],
            'priority_tier' => [
                'type'       => 'ENUM',
                'constraint' => [
                    'UNASSIGNED',
                    'TIER_1_IMMEDIATE_SCHEDULING',
                    'TIER_2_PLANNED_WINDOW',
                    'TIER_3_DEFERRED_MAINTENANCE'
                ],
                'default'    => 'UNASSIGNED',
            ],
            'priority_assigned_by' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],
            'priority_assigned_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'priority_rationale' => [
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
        $this->forge->addKey(['portfolio_id', 'plan_id'], false, false, 'idx_port_plan');
        $this->forge->addKey('plan_id', false, false, 'idx_plan_item');
        $this->forge->addKey('priority_tier', false, false, 'idx_item_tier');
        $this->forge->createTable('operational_portfolio_items', true);

        // 3. Table: operational_portfolio_tier_events (Append-only forensic audit trail)
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'portfolio_id' => [
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
            'plan_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
            ],
            'previous_tier' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'default'    => 'UNASSIGNED',
            ],
            'new_tier' => [
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
        $this->forge->addKey(['portfolio_id', 'portfolio_item_id'], false, false, 'idx_tier_event_port');
        $this->forge->createTable('operational_portfolio_tier_events', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('operational_portfolio_tier_events', true);
        $this->forge->dropTable('operational_portfolio_items', true);
        $this->forge->dropTable('operational_planning_portfolios', true);
    }
}
