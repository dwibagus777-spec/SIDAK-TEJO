<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateInspectionPhotosTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('inspection_photos')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'inspection_point_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'photo_type' => [
                'type'       => 'VARCHAR',
                'constraint' => '50',
                'default'    => 'CONDITION', // CONDITION, THERMAL, NAMEPLATE, DEFECT
            ],
            'file_path' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
            ],
            'caption' => [
                'type'       => 'VARCHAR',
                'constraint' => '150',
                'null'       => true,
            ],
            'client_uuid' => [
                'type'       => 'VARCHAR',
                'constraint' => '100',
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('inspection_point_id');
        $this->forge->addUniqueKey('client_uuid', 'uk_photo_client_uuid');
        $this->forge->createTable('inspection_photos', true);
    }

    public function down()
    {
        $this->forge->dropTable('inspection_photos', true);
    }
}
