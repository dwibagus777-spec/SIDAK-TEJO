<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * CR-06A: Canonical Master Materials Identity & Field Aliases
 * Gate 1: Identity only (no warehouse/stock/price)
 */
class CreateMasterMaterialsAndAliasesTable extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        // 1. Table: master_materials
        if (!$db->tableExists('master_materials')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'material_code' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 60,
                    'unique'     => true,
                ],
                'nama_material' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 150,
                ],
                'nama_lapangan' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                ],
                'satuan' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 20,
                    'default'    => 'SET',
                ],
                'material_domain' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'default'    => 'JTM',
                ],
                'material_category' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'default'    => 'HARDWARE',
                ],
                'specification' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'source_workbook' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'default'    => 'KONSTRUKSI.xlsx',
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
                'status' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 20,
                    'default'    => 'AKTIF',
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'deleted_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addKey('material_domain');
            $this->forge->addKey('material_category');
            $this->forge->createTable('master_materials', true);
        }

        // 2. Table: material_aliases
        if (!$db->tableExists('material_aliases')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'material_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'alias_name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                ],
                'normalized_alias' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                ],
                'alias_type' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'default'    => 'FIELD_TERM',
                ],
                'source' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'default'    => 'KONSTRUKSI.xlsx',
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addKey('material_id');
            $this->forge->addKey('normalized_alias');
            $this->forge->addForeignKey('material_id', 'master_materials', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('material_aliases', true);
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();
        if ($db->tableExists('material_aliases')) {
            $this->forge->dropTable('material_aliases', true);
        }
        if ($db->tableExists('master_materials')) {
            $this->forge->dropTable('master_materials', true);
        }
    }
}
