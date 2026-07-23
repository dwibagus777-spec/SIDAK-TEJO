<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateUlpsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'kode_ulp' => ['type' => 'VARCHAR', 'constraint' => 50, 'unique' => true],
            'nama_ulp' => ['type' => 'VARCHAR', 'constraint' => 150],
            'status' => ['type' => 'ENUM', 'constraint' => ['AKTIF', 'NONAKTIF'], 'default' => 'AKTIF'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('ulps', true);
    }

    public function down()
    {
        $this->forge->dropTable('ulps', true);
    }
}
