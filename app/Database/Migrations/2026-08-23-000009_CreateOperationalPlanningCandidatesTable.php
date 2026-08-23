<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOperationalPlanningCandidatesTable extends Migration
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
            'promoted_from_lifecycle_state' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'default'    => 'MITIGATION_PLANNED',
            ],
            'finding_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
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
            'asset_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'asset_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'candidate_status' => [
                'type'       => 'ENUM',
                'constraint' => [
                    'CANDIDATE_CREATED',
                    'UNDER_PLANNING_REVIEW',
                    'ACCEPTED_AS_PLANNING_INTENT',
                    'DISCARDED'
                ],
                'default'    => 'CANDIDATE_CREATED',
            ],
            'proposed_work_title' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'proposed_work_scope' => [
                'type' => 'TEXT',
                'null' => false,
            ],
            'target_completion_days' => [
                'type'       => 'INT',
                'constraint' => 5,
                'default'    => 7,
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
            'promotion_rationale' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'decision_rationale' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'decision_notes' => [
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

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('candidate_code', 'uk_cnd_code');
        $this->forge->addKey('snapshot_id', false, false, 'idx_cnd_snapshot');
        $this->forge->addKey('candidate_status', false, false, 'idx_cnd_status');
        $this->forge->addKey(['penyulang_id', 'candidate_status'], false, false, 'idx_cnd_feeder_status');

        $this->forge->createTable('operational_planning_candidates', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('operational_planning_candidates', true);
    }
}
