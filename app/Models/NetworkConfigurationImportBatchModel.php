<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Model for Network Configuration Import Batches (CR-06F Gate F8 Provenance)
 */
class NetworkConfigurationImportBatchModel extends Model
{
    protected $table            = 'network_configuration_import_batches';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'batch_uuid',
        'source_filename',
        'source_type',
        'import_status', // VALIDATING, REJECTED, COMMITTING, COMMITTED, ROLLED_BACK, FAILED
        'total_sections',
        'committed_sections',
        'rejected_sections',
        'validation_summary',
        'imported_by',
        'started_at',
        'completed_at',
    ];

    protected bool $allowEmptyInserts = false;

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
