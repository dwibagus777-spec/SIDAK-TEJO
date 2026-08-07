<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateInspectionTemplatesTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('inspection_templates')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'inspection_type_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'title' => [
                'type'       => 'VARCHAR',
                'constraint' => '150',
            ],
            'asset_category' => [
                'type'       => 'VARCHAR',
                'constraint' => '50',
                'null'       => true, // TIANG, GARDU, TRAFO
            ],
            'construction_type_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'version' => [
                'type'       => 'VARCHAR',
                'constraint' => '20',
                'default'    => 'v1.0',
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
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
        $this->forge->addKey('inspection_type_id');
        $this->forge->createTable('inspection_templates', true);
    }

    public function down()
    {
        $this->forge->dropTable('inspection_templates', true);
    }
}
