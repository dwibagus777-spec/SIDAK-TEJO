<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateManagementTrafoTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id_management' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => false,
                'auto_increment' => true,
            ],
            'id_penyulang' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'null'           => true,
            ],
            'id_section' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'null'           => true,
            ],
            'nama_gardu' => [
                'type'           => 'VARCHAR',
                'constraint'     => 100,
                'null'           => true,
            ],
            'tgl_input' => [
                'type'           => 'DATE',
                'null'           => true,
            ],
            'foto_nameplate_lama' => [
                'type'           => 'VARCHAR',
                'constraint'     => 255,
                'null'           => true,
            ],
            'foto_nameplate_baru' => [
                'type'           => 'VARCHAR',
                'constraint'     => 255,
                'null'           => true,
            ],
            'keterangan' => [
                'type'           => 'TEXT',
                'null'           => true,
            ],
            'created_at' => [
                'type'           => 'TIMESTAMP',
                'null'           => false,
                'default'        => new RawSql('CURRENT_TIMESTAMP'),
            ],
        ]);
        $this->forge->addKey('id_management', true);
        $this->forge->createTable('tb_management_trafo', true);
    }

    public function down()
    {
        $this->forge->dropTable('tb_management_trafo', true);
    }
}
