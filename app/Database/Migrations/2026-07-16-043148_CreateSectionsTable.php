<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSectionsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'penyulang_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'nama_section' => ['type' => 'VARCHAR', 'constraint' => 150],
            'status' => ['type' => 'ENUM', 'constraint' => ['AKTIF', 'NONAKTIF'], 'default' => 'AKTIF'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('penyulang_id', 'penyulang', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('sections', true);
    }

    public function down()
    {
        $this->forge->dropTable('sections', true);
    }
}
