<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddNamaPelangganToInspeksiKubikel extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('inspeksi_kubikel')) {
            $fields = $this->db->getFieldNames('inspeksi_kubikel');
            if (!in_array('nama_pelanggan', $fields, true)) {
                $this->forge->addColumn('inspeksi_kubikel', [
                    'nama_pelanggan' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 255,
                        'null'       => true,
                        'after'      => 'id_pel',
                    ],
                ]);
            }
        }
    }

    public function down()
    {
        if ($this->db->tableExists('inspeksi_kubikel')) {
            $fields = $this->db->getFieldNames('inspeksi_kubikel');
            if (in_array('nama_pelanggan', $fields, true)) {
                $this->forge->dropColumn('inspeksi_kubikel', 'nama_pelanggan');
            }
        }
    }
}
