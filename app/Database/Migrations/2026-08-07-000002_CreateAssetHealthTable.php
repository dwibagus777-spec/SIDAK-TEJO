<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAssetHealthTable extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();
        if ($db->tableExists('assets')) {
            $fields = [];
            if (!$db->fieldExists('health_score', 'assets')) {
                $fields['health_score'] = [
                    'type'       => 'INT',
                    'constraint' => 3,
                    'default'    => 100,
                    'after'      => 'status',
                ];
            }
            if (!$db->fieldExists('health_category', 'assets')) {
                $fields['health_category'] = [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'default'    => 'EXCELLENT',
                    'after'      => 'health_score',
                ];
            }
            if (!$db->fieldExists('asset_version', 'assets')) {
                $fields['asset_version'] = [
                    'type'       => 'INT',
                    'constraint' => 5,
                    'default'    => 1,
                    'after'      => 'health_category',
                ];
            }
            if (!$db->fieldExists('deleted_by', 'assets')) {
                $fields['deleted_by'] = [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'null'       => true,
                ];
            }
            if (!$db->fieldExists('deleted_reason', 'assets')) {
                $fields['deleted_reason'] = [
                    'type' => 'TEXT',
                    'null' => true,
                ];
            }

            if (!empty($fields)) {
                $this->forge->addColumn('assets', $fields);
            }
        }
    }

    public function down()
    {
        // No destructive column dropping in down() for production safety
    }
}
