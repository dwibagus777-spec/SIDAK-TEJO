<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateConstructionTypesTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('construction_types')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'code' => [
                'type'       => 'VARCHAR',
                'constraint' => '50',
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
            ],
            'network_type' => [
                'type'       => 'VARCHAR',
                'constraint' => '20',
                'default'    => 'JTM',
            ],
            'asset_category' => [
                'type'       => 'VARCHAR',
                'constraint' => '50',
                'default'    => 'TIANG',
            ],
            'construction_group' => [
                'type'       => 'VARCHAR',
                'constraint' => '50',
                'null'       => true,
            ],
            'voltage_level' => [
                'type'       => 'VARCHAR',
                'constraint' => '20',
                'null'       => true,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'standard_reference' => [
                'type'       => 'VARCHAR',
                'constraint' => '150',
                'null'       => true,
            ],
            'icon' => [
                'type'       => 'VARCHAR',
                'constraint' => '50',
                'null'       => true,
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'sort_order' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
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
        $this->forge->addUniqueKey('code');
        $this->forge->createTable('construction_types', true);
    }

    public function down()
    {
        $this->forge->dropTable('construction_types', true);
    }
}
