<?php

namespace App\Models;

use CodeIgniter\Model;

class AssetIngestRowModel extends Model
{
    protected $table            = 'asset_ingest_rows';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'batch_uuid',
        'source_file',
        'source_part',
        'source_row_number',
        'source_fingerprint',
        'canonical_identity',
        'unit_name',
        'ulp_name',
        'feeder_name',
        'asset_name',
        'section_name',
        'latitude',
        'longitude',
        'raw_data',
        'processing_status',
        'classification',
        'matched_asset_id',
        'error_code',
        'error_message',
        'created_at',
        'processed_at',
    ];

    protected bool $allowEmptyInserts = false;

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = null;

    public function findByFingerprint(string $fingerprint): ?array
    {
        return $this->builder()->where('source_fingerprint', $fingerprint)->get()->getRowArray();
    }

    public function getRowsByBatch(string $batchUuid): array
    {
        return $this->builder()->where('batch_uuid', $batchUuid)->get()->getResultArray();
    }
}
