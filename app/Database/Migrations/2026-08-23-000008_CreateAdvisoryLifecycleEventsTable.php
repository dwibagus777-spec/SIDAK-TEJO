<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAdvisoryLifecycleEventsTable extends Migration
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
            'from_status' => [
                'type'       => 'ENUM',
                'constraint' => [
                    'ADVISORY_PROPOSED',
                    'SUPERVISOR_REVIEWED',
                    'MITIGATION_PLANNED',
                    'ARCHIVED'
                ],
                'default'    => 'ADVISORY_PROPOSED',
            ],
            'to_status' => [
                'type'       => 'ENUM',
                'constraint' => [
                    'ADVISORY_PROPOSED',
                    'SUPERVISOR_REVIEWED',
                    'MITIGATION_PLANNED',
                    'ARCHIVED'
                ],
                'null'       => false,
            ],
            'actor_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'actor_name_snapshot' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'default'    => 'HUMAN_SUPERVISOR',
            ],
            'actor_role_snapshot' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'default'    => 'SUPERVISOR_DISTRIBUSI',
            ],
            'decision_rationale' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
                'comment'    => 'Mandatory explanation for state transition',
            ],
            'decision_notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'event_timestamp' => [
                'type' => 'DATETIME',
                'null' => false,
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
        $this->forge->addKey('snapshot_id', false, false, 'idx_evt_snapshot');
        $this->forge->addKey('snapshot_code', false, false, 'idx_evt_code');
        $this->forge->addKey(['to_status', 'event_timestamp'], false, false, 'idx_evt_status_time');

        $this->forge->createTable('advisory_lifecycle_events', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('advisory_lifecycle_events', true);
    }
}
