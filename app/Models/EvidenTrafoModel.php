<?php

namespace App\Models;

use CodeIgniter\Model;

class EvidenTrafoModel extends Model
{
    protected $table            = 'tb_eviden_trafo';
    protected $primaryKey       = 'id_trafo';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $allowedFields    = ['id_penyulang', 'id_section', 'nama_gardu', 'tgl_input', 'keterangan'];
}
