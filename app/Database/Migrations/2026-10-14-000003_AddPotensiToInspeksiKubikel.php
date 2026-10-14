<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration: Add jenis_potensi dan nilai_potensi ke inspeksi_kubikel
 */
class AddPotensiToInspeksiKubikel extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('inspeksi_kubikel')) {
            return;
        }

        $fieldsToAdd = [];

        if (!$this->db->fieldExists('jenis_potensi', 'inspeksi_kubikel')) {
            $fieldsToAdd['jenis_potensi'] = [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
                'after'      => 'catatan_umum',
            ];
        }

        if (!$this->db->fieldExists('nilai_potensi', 'inspeksi_kubikel')) {
            $fieldsToAdd['nilai_potensi'] = [
                'type'       => 'DECIMAL',
                'constraint' => '15,2',
                'null'       => true,
                'default'    => 0,
                'after'      => 'jenis_potensi',
            ];
        }

        if (!empty($fieldsToAdd)) {
            $this->forge->addColumn('inspeksi_kubikel', $fieldsToAdd);
        }
    }

    public function down()
    {
        if (!$this->db->tableExists('inspeksi_kubikel')) {
            return;
        }

        $fieldsToDrop = [];
        if ($this->db->fieldExists('nilai_potensi', 'inspeksi_kubikel')) {
            $fieldsToDrop[] = 'nilai_potensi';
        }
        if ($this->db->fieldExists('jenis_potensi', 'inspeksi_kubikel')) {
            $fieldsToDrop[] = 'jenis_potensi';
        }

        foreach ($fieldsToDrop as $field) {
            $this->forge->dropColumn('inspeksi_kubikel', $field);
        }
    }
}
