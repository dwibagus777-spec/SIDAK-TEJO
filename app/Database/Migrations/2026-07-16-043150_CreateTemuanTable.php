<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTemuanTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'nomor_temuan' => ['type' => 'VARCHAR', 'constraint' => 50, 'unique' => true],
            'ulp_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'penyulang_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'section_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'jenis_temuan' => ['type' => 'VARCHAR', 'constraint' => 50],
            'pelaksana' => ['type' => 'VARCHAR', 'constraint' => 50],
            'prioritas' => ['type' => 'VARCHAR', 'constraint' => 50],
            'potensi_gangguan' => ['type' => 'VARCHAR', 'constraint' => 50],
            'konduktor' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'noga' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'material' => ['type' => 'TEXT', 'null' => true],
            'detail_temuan' => ['type' => 'TEXT', 'null' => true],
            'alamat' => ['type' => 'TEXT', 'null' => true],
            'latitude' => ['type' => 'DECIMAL', 'constraint' => '10,8', 'null' => true],
            'longitude' => ['type' => 'DECIMAL', 'constraint' => '11,8', 'null' => true],
            'tanggal_temuan' => ['type' => 'DATE'],
            'tanggal_selesai' => ['type' => 'DATE', 'null' => true],
            'foto' => ['type' => 'TEXT', 'null' => true],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'BELUM'],
            'created_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'updated_by' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('ulp_id', 'ulps', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('penyulang_id', 'penyulang', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('section_id', 'sections', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('temuan', true);
    }

    public function down()
    {
        $this->forge->dropTable('temuan', true);
    }
}
