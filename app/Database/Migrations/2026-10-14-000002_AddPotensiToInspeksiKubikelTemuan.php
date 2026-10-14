<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration: Add jenis_potensi dan nilai_potensi ke inspeksi_kubikel_temuan
 * - Treatment Trafo (Rp5.000.000)
 * - Cleaning Kubikel (Rp4.999.000)
 */
class AddPotensiToInspeksiKubikelTemuan extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('inspeksi_kubikel_temuan')) {
            return;
        }

        $fieldsToAdd = [];

        if (!$this->db->fieldExists('jenis_potensi', 'inspeksi_kubikel_temuan')) {
            $fieldsToAdd['jenis_potensi'] = [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
                'after'      => 'deskripsi_temuan',
            ];
        }

        if (!$this->db->fieldExists('nilai_potensi', 'inspeksi_kubikel_temuan')) {
            $fieldsToAdd['nilai_potensi'] = [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
                'null'       => true,
                'default'    => 0,
                'after'      => 'jenis_potensi',
            ];
        }

        if (!empty($fieldsToAdd)) {
            $this->forge->addColumn('inspeksi_kubikel_temuan', $fieldsToAdd);
        }
    }

    public function down()
    {
        if (!$this->db->tableExists('inspeksi_kubikel_temuan')) {
            return;
        }

        $fieldsToDrop = [];
        if ($this->db->fieldExists('nilai_potensi', 'inspeksi_kubikel_temuan')) {
            $fieldsToDrop[] = 'nilai_potensi';
        }
        if ($this->db->fieldExists('jenis_potensi', 'inspeksi_kubikel_temuan')) {
            $fieldsToDrop[] = 'jenis_potensi';
        }

        foreach ($fieldsToDrop as $field) {
            $this->forge->dropColumn('inspeksi_kubikel_temuan', $field);
        }
    }
}
