<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAssetTypesTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('asset_types')) {
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
                'default'    => 'GD',
            ],
            'icon' => [
                'type'       => 'VARCHAR',
                'constraint' => '50',
                'default'    => 'bolt',
            ],
            'marker_shape' => [
                'type'       => 'VARCHAR',
                'constraint' => '30',
                'default'    => 'circle',
            ],
            'marker_size' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 24,
            ],
            'default_color' => [
                'type'       => 'VARCHAR',
                'constraint' => '20',
                'default'    => '#0284c7',
            ],
            'parent_type' => [
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
        $this->forge->createTable('asset_types', true);
    }

    public function down()
    {
        $this->forge->dropTable('asset_types', true);
    }
}
