<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateReliabilityTargetPoliciesTable extends Migration
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
                'comment'    => 'Policy target scope level',
            ],
            'scope_reference' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'comment'    => 'Feeder code, Substation ID, or Unit code. NULL = Enterprise Default',
            ],
            'target_saidi_min_cust' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'null'       => true,
                'comment'    => 'Target SAIDI in minutes per customer per year',
            ],
            'target_saifi_times_cust' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,4',
                'null'       => true,
                'comment'    => 'Target SAIFI in interruptions per customer per year',
            ],
            'target_ens_mwh' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,3',
                'null'       => true,
                'comment'    => 'Target Energy Not Supplied in MWh',
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
                'comment'    => 'Decree number, corporate KPI target decree, or statutory reference',
            ],
            'origin_class' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'default'    => 'SEED_SAMPLE_OR_TEMPLATE',
                'comment'    => 'SEED_SAMPLE_OR_TEMPLATE | CORPORATE_TARGET | ESDM_TMP',
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
                'comment'    => 'Authority / Officer that approved this policy',
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

        $this->forge->addKey('id', true);
        $this->forge->addKey(['scope_type', 'scope_reference', 'status', 'effective_from']);
        $this->forge->addKey(['status', 'effective_from', 'effective_until']);
        $this->forge->addKey('policy_code');

        $this->forge->createTable('reliability_target_policies', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('reliability_target_policies', true);
    }
}
