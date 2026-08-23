<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateVendorSlaPoliciesTable extends Migration
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
            'vendor_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'comment'    => 'NULL = applies to all vendors (enterprise default)',
            ],
            'contract_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'comment'    => 'NULL = no specific contract binding',
            ],
            'work_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'comment'    => 'NULL = applies to all work types',
            ],
            'priority' => [
                'type'       => 'ENUM',
                'constraint' => ['P1', 'P2', 'P3', 'P4', 'P5'],
                'null'       => true,
                'comment'    => 'NULL = applies to all priorities',
            ],
            'sla_response_minutes' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => false,
                'comment'    => 'Target response time in minutes',
            ],
            'sla_resolution_minutes' => [
                'type'       => 'INT',
                'constraint' => 11,
                'null'       => false,
                'comment'    => 'Target resolution time in minutes',
            ],
            'effective_from' => [
                'type'    => 'DATE',
                'null'    => false,
                'comment' => 'Policy effective start date',
            ],
            'effective_until' => [
                'type'    => 'DATE',
                'null'    => true,
                'comment' => 'NULL = indefinite / open-ended policy',
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['ACTIVE', 'INACTIVE', 'DRAFT', 'CONFIGURATION_REQUIRED'],
                'default'    => 'CONFIGURATION_REQUIRED',
                'comment'    => 'ACTIVE only when officially approved by contract authority',
            ],
            'version' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 1,
                'comment'    => 'Immutable version pin — increment only, never overwrite',
            ],
            'policy_origin' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'default'    => 'SEED_SAMPLE_OR_TEMPLATE',
                'comment'    => 'SEED_SAMPLE_OR_TEMPLATE | CONTRACT_DEFINED | INTERNALLY_APPROVED',
            ],
            'approved_by' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'comment'    => 'Authority that formally approved this policy version',
            ],
            'source_reference' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
                'comment'    => 'Contract number, SOP reference, or regulatory citation',
            ],
            'notes' => [
                'type'    => 'TEXT',
                'null'    => true,
                'comment' => 'Additional context or governance notes',
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
        $this->forge->addKey(['vendor_id', 'priority', 'status', 'effective_from']);
        $this->forge->addKey(['status', 'effective_from', 'effective_until']);

        $this->forge->createTable('vendor_sla_policies', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('vendor_sla_policies', true);
    }
}
