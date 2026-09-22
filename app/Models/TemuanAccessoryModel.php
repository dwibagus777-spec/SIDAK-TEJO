<?php

namespace App\Models;

use CodeIgniter\Model;

class TemuanAccessoryModel extends Model
{
    protected $table            = 'temuan_accessories';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'temuan_id',
        'asset_id',
        'accessory_type_id',
        'accessory_code',
        'accessory_name_snapshot',
        'category_snapshot',
        'status',
        'qty',
        'unit',
        'phase_applicable',
        'phase_configuration',
        'phase_positions',
        'condition',
        'note',
        'photo_url',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Get all accessories for a finding with strict snapshot priority
     */
    public function getByTemuanId(int $temuanId): array
    {
        $fields = $this->db->getFieldNames($this->table);
        $hasCode = in_array('accessory_code', $fields, true);
        $hasCategory = in_array('category_snapshot', $fields, true);

        $mFields = $this->db->tableExists('master_jtm_accessories') ? $this->db->getFieldNames('master_jtm_accessories') : [];
        $hasSubCat = in_array('sub_category', $mFields, true);

        $codeExpr   = $hasCode ? 'COALESCE(ta.accessory_code, m.code)' : 'm.code';
        $catExpr    = $hasCategory ? 'COALESCE(ta.category_snapshot, m.category)' : 'm.category';
        $subCatExpr = $hasSubCat ? 'm.sub_category' : 'm.category';

        $builder = $this->db->table($this->table . ' ta');
        return $builder->select("ta.*, 
                {$codeExpr} as accessory_code, 
                {$catExpr} as accessory_category,
                {$subCatExpr} as accessory_sub_category")
            ->join('master_jtm_accessories m', 'm.id = ta.accessory_type_id', 'left')
            ->where('ta.temuan_id', $temuanId)
            ->orderBy('m.sort_order', 'ASC')
            ->orderBy('ta.id', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * Get all accessories for an asset across findings
     */
    public function getByAssetId(int $assetId): array
    {
        $fields = $this->db->getFieldNames($this->table);
        $hasCode = in_array('accessory_code', $fields, true);
        $hasCategory = in_array('category_snapshot', $fields, true);

        $mFields = $this->db->tableExists('master_jtm_accessories') ? $this->db->getFieldNames('master_jtm_accessories') : [];
        $hasSubCat = in_array('sub_category', $mFields, true);

        $codeExpr   = $hasCode ? 'COALESCE(ta.accessory_code, m.code)' : 'm.code';
        $catExpr    = $hasCategory ? 'COALESCE(ta.category_snapshot, m.category)' : 'm.category';
        $subCatExpr = $hasSubCat ? 'm.sub_category' : 'm.category';

        $builder = $this->db->table($this->table . ' ta');
        return $builder->select("ta.*, 
                {$codeExpr} as accessory_code,
                {$catExpr} as accessory_category,
                {$subCatExpr} as accessory_sub_category")
            ->join('master_jtm_accessories m', 'm.id = ta.accessory_type_id', 'left')
            ->where('ta.asset_id', $assetId)
            ->orderBy('ta.created_at', 'DESC')
            ->get()
            ->getResultArray();
    }
}
