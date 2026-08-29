<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Model for Asset Intelligence Snapshots (CR-06G)
 * Captures explainable degradation breakdowns, AHS, ADI, and Resolution Status.
 */
class AssetIntelligenceSnapshotModel extends Model
{
    protected $table            = 'asset_intelligence_snapshots';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields    = [
        'asset_id',
        'penyulang_id',
        'section_id',
        'construction_type_id',
        'resolution_status',
        'bom_completeness_ratio',
        'active_findings_count',
        'recurring_findings_count',
        'asset_degradation_index',
        'asset_health_score',
        'health_category',
        'degradation_breakdown_json',
        'snapshot_version',
        'calculated_at',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
