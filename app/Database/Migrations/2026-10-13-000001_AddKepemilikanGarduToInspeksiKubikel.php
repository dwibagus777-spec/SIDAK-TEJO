<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * CR-GIS-FIX-07: Add kepemilikan_gardu and jenis_gardu columns to inspeksi_kubikel table.
 * These fields drive Beyond KWH routing:
 * - Only PELANGGAN + KUBIKEL findings route to Beyond KWH.
 * - PLN gardu findings follow normal PLN inspection flows.
 */
class AddKepemilikanGarduToInspeksiKubikel extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('inspeksi_kubikel')) {
            return;
        }

        $fields = [];

        if (!$this->db->fieldExists('kepemilikan_gardu', 'inspeksi_kubikel')) {
            $fields['kepemilikan_gardu'] = [
                'type'       => 'ENUM',
                'constraint' => ['PLN', 'PELANGGAN'],
                'default'    => 'PLN',
                'null'       => false,
                'after'      => 'id_pel',
            ];
        }

        if (!$this->db->fieldExists('jenis_gardu', 'inspeksi_kubikel')) {
            $fields['jenis_gardu'] = [
                'type'       => 'ENUM',
                'constraint' => ['KUBIKEL', 'GARDU_KONVENSIONAL'],
                'default'    => 'GARDU_KONVENSIONAL',
                'null'       => false,
                'after'      => 'kepemilikan_gardu',
            ];
        }

        if (!empty($fields)) {
            $this->forge->addColumn('inspeksi_kubikel', $fields);
        }
    }

    public function down()
    {
        if (!$this->db->tableExists('inspeksi_kubikel')) {
            return;
        }

        if ($this->db->fieldExists('kepemilikan_gardu', 'inspeksi_kubikel')) {
            $this->forge->dropColumn('inspeksi_kubikel', 'kepemilikan_gardu');
        }
        if ($this->db->fieldExists('jenis_gardu', 'inspeksi_kubikel')) {
            $this->forge->dropColumn('inspeksi_kubikel', 'jenis_gardu');
        }
    }
}
