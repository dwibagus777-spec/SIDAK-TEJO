<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration: Add tanggal_temuan column to inspeksi_kubikel_temuan
 */
class AddTanggalTemuanToInspeksiKubikelTemuan extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('inspeksi_kubikel_temuan')) {
            return;
        }

        if (!$this->db->fieldExists('tanggal_temuan', 'inspeksi_kubikel_temuan')) {
            $this->forge->addColumn('inspeksi_kubikel_temuan', [
                'tanggal_temuan' => [
                    'type'    => 'DATE',
                    'null'    => true,
                    'after'   => 'posisi',
                ],
            ]);
        }
    }

    public function down()
    {
        if (!$this->db->tableExists('inspeksi_kubikel_temuan')) {
            return;
        }

        if ($this->db->fieldExists('tanggal_temuan', 'inspeksi_kubikel_temuan')) {
            $this->forge->dropColumn('inspeksi_kubikel_temuan', 'tanggal_temuan');
        }
    }
}
