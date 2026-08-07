<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAssetRelationshipsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('asset_relationships')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'source_asset_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'target_asset_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'relationship_type' => [
                'type'       => 'VARCHAR',
                'constraint' => '50',
                'default'    => 'CONNECTED_TO', // UPSTREAM, DOWNSTREAM, SUPPLIES, CONNECTED_TO, LOCATED_AT, PROTECTS
            ],
            'sequence_no' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'effective_date' => [
                'type' => 'DATE',
                'null' => true,
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
        $this->forge->addKey('source_asset_id');
        $this->forge->addKey('target_asset_id');
        $this->forge->createTable('asset_relationships', true);
    }

    public function down()
    {
        $this->forge->dropTable('asset_relationships', true);
    }
}
