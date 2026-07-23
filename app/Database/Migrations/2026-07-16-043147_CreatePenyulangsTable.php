<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePenyulangsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'id_unik_penyulang' => ['type' => 'VARCHAR', 'constraint' => 100, 'unique' => true],
            'kode_penyulang' => ['type' => 'VARCHAR', 'constraint' => 100],
            'nama_penyulang' => ['type' => 'VARCHAR', 'constraint' => 150],
            'ulp_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'status' => ['type' => 'ENUM', 'constraint' => ['AKTIF', 'NONAKTIF'], 'default' => 'AKTIF'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('ulp_id', 'ulps', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('penyulang', true);
    }

    public function down()
    {
        $this->forge->dropTable('penyulang', true);
    }
}
