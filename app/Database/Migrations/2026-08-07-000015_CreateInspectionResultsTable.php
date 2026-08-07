<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateInspectionResultsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('inspection_results')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'inspection_point_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'template_item_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'result_status' => [
                'type'       => 'VARCHAR',
                'constraint' => '20',
                'default'    => 'PASS', // PASS, FAIL, N/A
            ],
            'measurement_value' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'null'       => true,
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'temuan_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
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
        $this->forge->addKey('inspection_point_id');
        $this->forge->addKey('template_item_id');
        $this->forge->addKey('temuan_id');
        $this->forge->addUniqueKey(['inspection_point_id', 'template_item_id'], 'uk_point_template_item');
        $this->forge->createTable('inspection_results', true);
    }

    public function down()
    {
        $this->forge->dropTable('inspection_results', true);
    }
}
