<?php

namespace App\Models;

use CodeIgniter\Model;

class ManagementTrafoModel extends Model
{
    protected $table            = 'tb_management_trafo';
    protected $primaryKey       = 'id_management';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $allowedFields    = ['id_penyulang', 'id_section', 'nama_gardu', 'tgl_input', 'foto_nameplate_lama', 'foto_nameplate_baru', 'keterangan'];
}
