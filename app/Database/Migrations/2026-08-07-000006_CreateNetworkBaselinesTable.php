<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateNetworkBaselinesTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('network_baselines')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => '150',
            ],
            'network_type' => [
                'type'       => 'VARCHAR',
                'constraint' => '20',
                'default'    => 'JTM',
            ],
            'ulp_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'penyulang_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'gardu_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'trafo_id' => [
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
            'effective_date' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'status' => [
                'type'       => 'VARCHAR',
                'constraint' => '20',
                'default'    => 'ACTIVE',
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
        $this->forge->createTable('network_baselines', true);
    }

    public function down()
    {
        $this->forge->dropTable('network_baselines', true);
    }
}
