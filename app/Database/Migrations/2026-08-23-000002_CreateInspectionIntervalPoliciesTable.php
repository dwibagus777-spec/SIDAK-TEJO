<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateInspectionIntervalPoliciesTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'policy_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
                'comment'    => 'Unique policy identifier code',
            ],
            'policy_version' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 1,
                'comment'    => 'Immutable version pin — increment only',
            ],
            'scope_type' => [
                'type'       => 'ENUM',
                'constraint' => ['FEEDER', 'SUBSTATION', 'ULP', 'UP3', 'ENTERPRISE_DEFAULT'],
                'default'    => 'ENTERPRISE_DEFAULT',
                'comment'    => 'Policy scope level',
            ],
            'scope_reference' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'comment'    => 'Feeder code, Substation ID, or Unit code. NULL = Enterprise Default',
            ],
            'priority_tier' => [
                'type'       => 'ENUM',
                'constraint' => ['P1', 'P2', 'P3', 'P4', 'P5', 'ALL'],
                'default'    => 'ALL',
                'comment'    => 'Priority tier match (P1..P5 or ALL for explicit wildcard)',
            ],
            'health_tier' => [
                'type'       => 'ENUM',
                'constraint' => ['CRITICAL', 'POOR', 'MODERATE', 'GOOD', 'EXCELLENT', 'ALL'],
                'default'    => 'ALL',
                'comment'    => 'Health index tier match (or ALL for explicit wildcard)',
            ],
            'recommended_interval_days' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => false,
                'comment'    => 'Recommended interval in days between inspections',
            ],
            'recommended_inspection_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => false,
                'comment'    => 'Recommended technical inspection type (Visual, Thermovision, DGA, etc.)',
            ],
            'recommended_window_label' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
                'comment'    => 'Human-readable advisory window (e.g. WITHIN_14_DAYS, WITHIN_30_DAYS)',
            ],
            'effective_from' => [
                'type'    => 'DATETIME',
                'null'    => false,
                'comment' => 'Policy effective start timestamp',
            ],
            'effective_until' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'comment' => 'NULL = indefinite / currently open policy',
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['DRAFT', 'CONFIGURATION_REQUIRED', 'PENDING_APPROVAL', 'APPROVED', 'ACTIVE', 'SUPERSEDED', 'EXPIRED', 'REVOKED'],
                'default'    => 'CONFIGURATION_REQUIRED',
                'comment'    => 'ACTIVE / APPROVED are applicable for resolver',
            ],
            'source_of_record' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
                'comment'    => 'SOP number, Directors Regulation, or approved maintenance manual',
            ],
            'origin_class' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'default'    => 'SEED_SAMPLE_OR_TEMPLATE',
                'comment'    => 'SEED_SAMPLE_OR_TEMPLATE | CORPORATE_SOP | UNIT_POLICY',
            ],
            'approved_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'comment' => 'Timestamp when policy was formally approved',
            ],
            'approved_by_reference' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'comment'    => 'Authority / Supervisor reference who approved this policy',
            ],
            'supersedes_policy_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'comment'    => 'ID of prior policy version superseded by this version',
            ],
            'notes' => [
                'type'    => 'TEXT',
                'null'    => true,
                'comment' => 'Context and governance audit notes',
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
        $this->forge->addKey(['scope_type', 'scope_reference', 'priority_tier', 'health_tier', 'status', 'effective_from'], false, false, 'idx_insp_policy_match');
        $this->forge->addKey(['status', 'effective_from', 'effective_until'], false, false, 'idx_insp_policy_validity');
        $this->forge->addKey('policy_code', false, false, 'idx_insp_policy_code');

        $this->forge->createTable('inspection_interval_policies', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('inspection_interval_policies', true);
    }
}
