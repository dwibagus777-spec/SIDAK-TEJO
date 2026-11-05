<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Ensure fields written by TemuanService exist on installations with older schemas. */
class AddTemuanCreatorMetadata extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('temuan')) {
            return;
        }

        $fields = [
            'created_by_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
                'null'       => true,
            ],
            'created_by_nip' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
                'null'       => true,
            ],
            'updated_by_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
                'null'       => true,
            ],
            'updated_by_nip' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
                'null'       => true,
            ],
        ];

        foreach ($fields as $field => $definition) {
            if (!$this->db->fieldExists($field, 'temuan')) {
                $this->forge->addColumn('temuan', [$field => $definition]);
            }
        }
    }

    public function down()
    {
        // Keep nullable audit metadata on rollback to avoid deleting recorded data.
    }
}
