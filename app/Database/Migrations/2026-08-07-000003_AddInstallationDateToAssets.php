<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddInstallationDateToAssets extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();
        if ($db->tableExists('assets')) {
            if (!$db->fieldExists('installation_date', 'assets')) {
                $fields = [
                    'installation_date' => [
                        'type' => 'DATE',
                        'null' => true,
                        'after' => 'tahun_instalasi',
                    ],
                ];
                $this->forge->addColumn('assets', $fields);
            }
        }
    }

    public function down()
    {
        // Keep migration additive for production safety
    }
}
