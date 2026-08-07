<?php

namespace App\Models;

use CodeIgniter\Model;

class InspectionTemplateItemModel extends Model
{
    protected $table            = 'inspection_template_items';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'template_id',
        'item_name',
        'item_type',
        'unit',
        'min_value',
        'max_value',
        'is_photo_required',
        'photo_label',
        'sort_order',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
