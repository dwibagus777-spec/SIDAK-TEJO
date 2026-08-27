<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * CR-06B: Physical Network Section Configurations, Mixed Conductors & Accessories
 * Gate 4: Single ACTIVE version invariant per section
 */
class CreateNetworkSectionConfigurationTables extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        // 1. Table: network_section_configurations
        if (!$db->tableExists('network_section_configurations')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'section_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'version_number' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 1,
                ],
                'effective_from' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'effective_to' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'verification_status' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 30,
                    'default'    => 'ACTIVE', // DRAFT, SUBMITTED, VERIFIED, ACTIVE, SUPERSEDED
                ],
                'configuration_source' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'default'    => 'INITIAL_AUDIT', // INSPECTION, WORK_ORDER, UPRATING_PROJECT, INITIAL_AUDIT
                ],
                'inspection_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'changed_by' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'change_reason' => [
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

            $this->forge->addKey('id', true);
            $this->forge->addKey('section_id');
            $this->forge->addKey('verification_status');
            if ($db->tableExists('sections')) {
                $this->forge->addForeignKey('section_id', 'sections', 'id', 'CASCADE', 'CASCADE');
            }
            $this->forge->createTable('network_section_configurations', true);
        }

        // 2. Table: network_section_conductors (Mixed Conductor support)
        if (!$db->tableExists('network_section_conductors')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'network_section_configuration_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'conductor_material_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'sequence_order' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 1,
                ],
                'segment_label' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                ],
                'start_node_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'end_node_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'length_m' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '10,2',
                    'null'       => true,
                ],
                'verified' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 1,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addKey('network_section_configuration_id');
            $this->forge->addForeignKey('network_section_configuration_id', 'network_section_configurations', 'id', 'CASCADE', 'CASCADE');
            if ($db->tableExists('master_materials')) {
                $this->forge->addForeignKey('conductor_material_id', 'master_materials', 'id', 'CASCADE', 'CASCADE');
            }
            $this->forge->createTable('network_section_conductors', true);
        }

        // 3. Table: network_section_accessories (GSW, LA, CLD, MCA)
        if (!$db->tableExists('network_section_accessories')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'network_section_configuration_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'accessory_material_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'accessory_type' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 30,
                    'default'    => 'GSW', // GSW, LA, CLD, MCA, GROUNDING, ANIMAL_GUARD
                ],
                'quantity' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'default'    => 1,
                ],
                'location_reference' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                ],
                'condition_status' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 30,
                    'default'    => 'GOOD', // GOOD, DEFECTIVE, MISSING
                ],
                'verified' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 1,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addKey('network_section_configuration_id');
            $this->forge->addForeignKey('network_section_configuration_id', 'network_section_configurations', 'id', 'CASCADE', 'CASCADE');
            if ($db->tableExists('master_materials')) {
                $this->forge->addForeignKey('accessory_material_id', 'master_materials', 'id', 'CASCADE', 'CASCADE');
            }
            $this->forge->createTable('network_section_accessories', true);
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();
        if ($db->tableExists('network_section_accessories')) {
            $this->forge->dropTable('network_section_accessories', true);
        }
        if ($db->tableExists('network_section_conductors')) {
            $this->forge->dropTable('network_section_conductors', true);
        }
        if ($db->tableExists('network_section_configurations')) {
            $this->forge->dropTable('network_section_configurations', true);
        }
    }
}
