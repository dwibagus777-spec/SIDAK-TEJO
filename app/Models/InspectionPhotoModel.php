<?php

namespace App\Models;

use CodeIgniter\Model;

class InspectionPhotoModel extends Model
{
    protected $table            = 'inspection_photos';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'inspection_point_id',
        'photo_type',
        'file_path',
        'caption',
        'client_uuid',
        'created_at',
    ];

    protected $useTimestamps = false;
}
