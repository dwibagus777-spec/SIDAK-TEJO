<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBaselineAssetsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('baseline_assets')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'baseline_id' => [
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
            'distance_from_previous' => [
                'type'       => 'DECIMAL',
                'constraint' => '10,2',
                'default'    => 0.00,
            ],
            'section_name' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
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
        $this->forge->addKey(['baseline_id', 'sequence_no']);
        $this->forge->addUniqueKey(['baseline_id', 'asset_id']);
        $this->forge->createTable('baseline_assets', true);
    }

    public function down()
    {
        $this->forge->dropTable('baseline_assets', true);
    }
}
