<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOperationalPlansTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'constraint'     => 20,
                'unsigned'       => true,
                'auto_increment' => true,
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
                'comment'    => 'FK to operational_planning_candidates.id',
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
                'comment'    => 'FK to preventive_risk_advisory_snapshots.id',
            ],
            'snapshot_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
            ],
            'source_planning_intent_status' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'default'    => 'ACCEPTED_AS_PLANNING_INTENT',
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
            'plan_status' => [
                'type'       => 'ENUM',
                'constraint' => [
                    'PLAN_DRAFT',
                    'UNDER_PLANNING_REVIEW',
                    'APPROVED_FOR_PORTFOLIO',
                    'REVISION_REQUIRED'
                ],
                'default'    => 'PLAN_DRAFT',
            ],
            'work_category' => [
                'type'       => 'ENUM',
                'constraint' => [
                    'ROW_CLEARANCE',
                    'EQUIPMENT_REPAIR',
                    'THERMO_CORRECTION',
                    'GROUNDING_IMPROVEMENT',
                    'INSULATOR_REPLACEMENT'
                ],
                'default'    => 'ROW_CLEARANCE',
            ],
            'work_scope_narrative' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            'safety_precautions' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            'outage_required' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'comment'    => '0: Bertegangan (PDKB), 1: Pemadaman SUTM',
            ],
            'proposed_execution_window_start' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'proposed_execution_window_end' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'indicative_materials_json' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
            'material_status' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'default'    => 'INDICATIVE_ESTIMATE_ONLY',
            ],
            'schedule_status' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'default'    => 'PROPOSED_WINDOW_ONLY',
            ],
            'planner_actor_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'planner_actor_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'default'    => 'HUMAN_PLANNER',
            ],
            'planner_actor_role' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'default'    => 'PERENCANA_PEMELIHARAAN',
            ],
            'reviewer_actor_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],
            'review_rationale' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'revision_reason' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'revision_requested_by' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],
            'revision_requested_at' => [
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
        $this->forge->addUniqueKey('plan_code', 'uk_plan_code');
        $this->forge->addKey('candidate_id', false, false, 'idx_plan_candidate');
        $this->forge->addKey('snapshot_id', false, false, 'idx_plan_snapshot');
        $this->forge->addKey('plan_status', false, false, 'idx_plan_status');
        $this->forge->addKey(['penyulang_id', 'plan_status'], false, false, 'idx_plan_feeder_status');

        $this->forge->createTable('operational_plans', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('operational_plans', true);
    }
}
