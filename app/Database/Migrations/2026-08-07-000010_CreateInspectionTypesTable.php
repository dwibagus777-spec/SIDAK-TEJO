<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateInspectionTypesTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('inspection_types')) {
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
            'category' => [
                'type'       => 'VARCHAR',
                'constraint' => '50',
                'default'    => 'JTM', // JTM, JTR, GD, THERMOVISION
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'default_interval_months' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 3,
            ],
            'icon' => [
                'type'       => 'VARCHAR',
                'constraint' => '50',
                'default'    => 'clipboard-check',
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
        $this->forge->createTable('inspection_types', true);
    }

    public function down()
    {
        $this->forge->dropTable('inspection_types', true);
    }
}
