<?php

namespace App\Models;

use CodeIgniter\Model;

class AssetIngestBatchModel extends Model
{
    protected $table            = 'asset_ingest_batches';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'batch_uuid',
        'source_file',
        'source_part',
        'total_rows',
        'matched_existing',
        'source_duplicate',
        'conflict_review',
        'quarantine',
        'candidate_new_asset',
        'inserted',
        'already_processed',
        'duplicate_created',
        'topology_delta',
        'status',
        'fingerprint',
        'created_by',
        'created_at',
        'updated_at',
        'processed_at',
    ];

    protected bool $allowEmptyInserts = false;

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function getByUuid(string $uuid): ?array
    {
        return $this->builder()->where('batch_uuid', $uuid)->get()->getRowArray();
    }
}
