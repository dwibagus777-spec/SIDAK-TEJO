<?php

namespace App\Models;

use CodeIgniter\Model;

class FotoEvidenModel extends Model
{
    protected $table            = 'tb_foto_eviden';
    protected $primaryKey       = 'id_foto';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $allowedFields    = ['id_parent', 'kategori', 'jenis_foto', 'nama_file'];
}
