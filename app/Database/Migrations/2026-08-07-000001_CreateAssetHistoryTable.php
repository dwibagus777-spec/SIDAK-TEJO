<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAssetHistoryTable extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('asset_history')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'asset_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                ],
                'tanggal' => [
                    'type' => 'DATETIME',
                ],
                'jenis_event' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                ],
                'status_lama' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'null'       => true,
                ],
                'status_baru' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                ],
                'referensi' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                ],
                'deskripsi' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'user_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'null'       => true,
                ],
                'approved_by' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'null'       => true,
                ],
                'foto_sebelum' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                ],
                'foto_sesudah' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                ],
                'ip_address' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 45,
                    'null'       => true,
                ],
                'user_agent' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                ],
                'device' => [
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
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addKey('asset_id');
            $this->forge->addKey('jenis_event');
            $this->forge->createTable('asset_history', true);
        }
    }

    public function down()
    {
        $this->forge->dropTable('asset_history', true);
    }
}
