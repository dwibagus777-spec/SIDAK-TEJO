<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCauseCodeDictionaryTable extends Migration
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
            'source_raw_value' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
                'comment'    => 'Raw exact string from PENYEBAB SESUAI KODE GANGGUAN',
            ],
            'canonical_cause_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => false,
                'comment'    => 'Standardized uppercase token (e.g. CAUSE_ANIMAL_CONTACT)',
            ],
            'cause_category' => [
                'type'       => 'ENUM',
                'constraint' => [
                    'VEGETATION_ROW',
                    'ANIMAL_CONTACT',
                    'LIGHTNING_WEATHER',
                    'THIRD_PARTY_OBJECT',
                    'EQUIPMENT_FAILURE',
                    'CABLE_TERMINATION_FAULT',
                    'CONDUCTOR_GSW_SNAP',
                    'CUSTOMER_IML_FAULT',
                    'OVERLOAD_SYSTEM',
                    'UNKNOWN_INVESTIGATION'
                ],
                'default'    => 'UNKNOWN_INVESTIGATION',
                'comment'    => 'High-level clustering category',
            ],
            'cause_label' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
                'comment'    => 'Human-readable descriptive label in Indonesian',
            ],
            'mapping_confidence' => [
                'type'       => 'DECIMAL',
                'constraint' => '4,2',
                'default'    => 1.00,
                'comment'    => 'Mapping confidence factor (0.00 to 1.00)',
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'governance_notes' => [
                'type'    => 'TEXT',
                'null'    => true,
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
        $this->forge->addKey('source_raw_value', false, false, 'idx_cause_raw_val');
        $this->forge->addKey('canonical_cause_code', false, false, 'idx_cause_canonical');
        $this->forge->addKey('cause_category', false, false, 'idx_cause_category');

        $this->forge->createTable('cause_code_dictionary', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('cause_code_dictionary', true);
    }
}
