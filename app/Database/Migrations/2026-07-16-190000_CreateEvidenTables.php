<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateEvidenTables extends Migration
{
    public function up()
    {
        // 1. Tabel tb_eviden_kubikel
        $this->forge->addField([
            'id_kubikel' => [
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
            'id_pel' => [
                'type'           => 'VARCHAR',
                'constraint'     => 100,
                'null'           => true,
            ],
            'tgl_input' => [
                'type'           => 'DATE',
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
        $this->forge->addKey('id_kubikel', true);
        $this->forge->createTable('tb_eviden_kubikel', true);

        // 2. Tabel tb_eviden_trafo
        $this->forge->addField([
            'id_trafo' => [
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
        $this->forge->addKey('id_trafo', true);
        $this->forge->createTable('tb_eviden_trafo', true);

        // 3. Tabel tb_foto_eviden
        $this->forge->addField([
            'id_foto' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => false,
                'auto_increment' => true,
            ],
            'id_parent' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'null'           => false,
            ],
            'kategori' => [
                'type'           => "ENUM('KUBIKEL','TRAFO')",
                'null'           => false,
            ],
            'jenis_foto' => [
                'type'           => 'VARCHAR',
                'constraint'     => 100,
                'null'           => false,
            ],
            'nama_file' => [
                'type'           => 'VARCHAR',
                'constraint'     => 255,
                'null'           => false,
            ],
        ]);
        $this->forge->addKey('id_foto', true);
        $this->forge->addKey(['id_parent', 'kategori']);
        $this->forge->createTable('tb_foto_eviden', true);
    }

    public function down()
    {
        $this->forge->dropTable('tb_foto_eviden', true);
        $this->forge->dropTable('tb_eviden_trafo', true);
        $this->forge->dropTable('tb_eviden_kubikel', true);
    }
}
