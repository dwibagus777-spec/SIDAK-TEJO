<?php

namespace App\Models;

use CodeIgniter\Model;

class EvidenKubikelModel extends Model
{
    protected $table            = 'tb_eviden_kubikel';
    protected $primaryKey       = 'id_kubikel';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $allowedFields    = ['id_penyulang', 'id_section', 'nama_gardu', 'id_pel', 'tgl_input', 'keterangan'];
}
