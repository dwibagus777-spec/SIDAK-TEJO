<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Model for Executive Decision Logs (Phase CC-04, Gate E9-A Closed-Loop Governance)
 */
class ExecutiveDecisionLogModel extends Model
{
    protected $table            = 'executive_decision_logs';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'penyulang_id',
        'feeder_health_classification_id',
        'recommendation_code',
        'recommended_action',
        'assigned_unit',
        'priority_level',
        'baseline_fhi',
        'approval_status', // PENDING, APPROVED, REJECTED, DISPATCHED, COMPLETED, VERIFIED
        'approved_by',
        'approved_at',
        'work_order_id',
        'outcome_verified_fhi',
        'delta_fhi',
        'outcome_notes',
        'created_at',
        'updated_at',
    ];

    protected bool $allowEmptyInserts = false;

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
