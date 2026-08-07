<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateInspectionPointsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('inspection_points')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'inspection_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'asset_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'sequence_no' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 1,
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => '20',
                'default'    => 'PENDING', // PENDING, PASSED, FAILED, SKIPPED
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'inspected_at' => [
                'type' => 'DATETIME',
                'null' => true,
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
        $this->forge->addKey('inspection_id');
        $this->forge->addKey('asset_id');
        $this->forge->addUniqueKey(['inspection_id', 'asset_id'], 'uk_insp_asset');
        $this->forge->createTable('inspection_points', true);
    }

    public function down()
    {
        $this->forge->dropTable('inspection_points', true);
    }
}
