<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRiwayatTindakLanjutTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'temuan_id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
            ],
            'tanggal' => [
                'type' => 'DATETIME',
            ],
            'pelaksana' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'status_progress' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ],
            'komentar' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'foto_sebelum' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ],
            'foto_proses' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ],
            'foto_sesudah' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
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
        
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('temuan_id', 'temuan', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('riwayat_tindak_lanjut');
    }

    public function down()
    {
        $this->forge->dropTable('riwayat_tindak_lanjut');
    }
}
