<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTemuanExtras extends Migration
{
    public function up()
    {
        $fields = [
            'foto_path' => [
                'type' => 'VARCHAR',
                'constraint' => 255,
                'null' => true,
            ],
            'tindak_lanjut' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'catatan_tindak_lanjut' => [
                'type' => 'TEXT',
                'null' => true,
            ],
        ];

        $this->forge->addColumn('temuan', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('temuan', ['foto_path', 'tindak_lanjut', 'catatan_tindak_lanjut']);
    }
}
