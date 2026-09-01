<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration — GIS Transline Persistence & Authoritative Segment Storage
 */
class CreateGisTranslinesTable extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('gis_translines')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'transline_code' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                ],
                'penyulang_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
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
                'geometry' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'geometry_type' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 32,
                    'default'    => 'LineString',
                ],
                'conductor_type' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                    'default'    => 'AAAC',
                ],
                'conductor_size' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                    'default'    => '150 mm²',
                ],
                'conductor_material' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                    'default'    => 'ALUMINUM_ALLOY',
                ],
                'installation_type' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                    'default'    => 'OVERHEAD',
                ],
                'circuit_config' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                    'default'    => '3_PHASE',
                ],
                'distance_meters' => [
                    'type'       => 'DECIMAL',
                    'constraint' => '10,2',
                    'default'    => 0.00,
                ],
                'status' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 32,
                    'default'    => 'ACTIVE',
                ],
                'is_active' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 1,
                ],
                'created_by' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'default'    => 'SYSTEM',
                ],
                'updated_by' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
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
                'deleted_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addKey('penyulang_id');
            $this->forge->addKey('source_asset_id');
            $this->forge->addKey('target_asset_id');
            $this->forge->addKey('is_active');
            $this->forge->createTable('gis_translines', true);
        }
    }

    public function down()
    {
        $this->forge->dropTable('gis_translines', true);
    }
}
