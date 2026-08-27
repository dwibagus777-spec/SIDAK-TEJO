<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * CR-06A: Enhance Construction Types and Create Construction BOM Items
 * Gate 2: material_id nullable with ON DELETE SET NULL
 * Gate 3: quantity_status ENUM('KNOWN', 'UNKNOWN', 'NOT_APPLICABLE')
 * Kubikel Draft Governance: approval_status column
 */
class EnhanceConstructionTypesAndCreateBomTable extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        // 1. Ensure construction_types exists and has CR-06 columns
        if ($db->tableExists('construction_types')) {
            $fieldsToAdd = [];

            if (!$db->fieldExists('construction_code', 'construction_types')) {
                $fieldsToAdd['construction_code'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'null'       => true,
                ];
            }

            if (!$db->fieldExists('construction_name', 'construction_types')) {
                $fieldsToAdd['construction_name'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 150,
                    'null'       => true,
                ];
            }

            if (!$db->fieldExists('construction_family', 'construction_types')) {
                $fieldsToAdd['construction_family'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'default'    => 'JTM',
                ];
            }

            if (!$db->fieldExists('asset_domain', 'construction_types')) {
                $fieldsToAdd['asset_domain'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'default'    => 'TIANG',
                ];
            }

            if (!$db->fieldExists('approval_status', 'construction_types')) {
                $fieldsToAdd['approval_status'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 30,
                    'default'    => 'ACTIVE',
                ];
            }

            if (!$db->fieldExists('source_sheet', 'construction_types')) {
                $fieldsToAdd['source_sheet'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'null'       => true,
                ];
            }

            if (!$db->fieldExists('source_row', 'construction_types')) {
                $fieldsToAdd['source_row'] = [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'null'       => true,
                ];
            }

            if (!empty($fieldsToAdd)) {
                $this->forge->addColumn('construction_types', $fieldsToAdd);
            }
        } else {
            // Create fresh construction_types if not exists
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'construction_code' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'unique'     => true,
                ],
                'construction_name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 150,
                ],
                'construction_family' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'default'    => 'JTM',
                ],
                'asset_domain' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'default'    => 'TIANG',
                ],
                'approval_status' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 30,
                    'default'    => 'ACTIVE',
                ],
                'source_sheet' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'null'       => true,
                ],
                'source_row' => [
                    'type'       => 'INT',
                    'constraint' => 11,
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
            $this->forge->addKey('construction_family');
            $this->forge->addKey('approval_status');
            $this->forge->createTable('construction_types', true);
        }

        // 2. Table: construction_bom_items
        if (!$db->tableExists('construction_bom_items')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'construction_type_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'material_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'raw_material_name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 150,
                ],
                'material_alias' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                ],
                'quantity' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '10,2',
                    'null'       => true,
                ],
                'quantity_status' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 30,
                    'default'    => 'KNOWN', // KNOWN, UNKNOWN, NOT_APPLICABLE
                ],
                'unit' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 20,
                    'null'       => true,
                ],
                'mandatory' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 1,
                ],
                'component_category' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'null'       => true,
                ],
                'source_sheet' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'null'       => true,
                ],
                'source_row' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'null'       => true,
                ],
                'mapping_status' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 30,
                    'default'    => 'RESOLVED', // RESOLVED, UNRESOLVED, MANUAL_REVIEW_REQUIRED
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
            $this->forge->addKey('construction_type_id');
            $this->forge->addKey('material_id');
            $this->forge->addKey('mapping_status');
            $this->forge->addForeignKey('construction_type_id', 'construction_types', 'id', 'CASCADE', 'CASCADE');
            if ($db->tableExists('master_materials')) {
                $this->forge->addForeignKey('material_id', 'master_materials', 'id', 'SET NULL', 'SET NULL');
            }
            $this->forge->createTable('construction_bom_items', true);
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();
        if ($db->tableExists('construction_bom_items')) {
            $this->forge->dropTable('construction_bom_items', true);
        }
    }
}
