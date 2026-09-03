<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Model for Additive GIS Transline Proposals (TL-01 Phase 2B)
 *
 * Guaranteed Invariants:
 * - Represents proposed edges only (AUTO_MATCH, NEEDS_REVIEW, INVALID, MISSING).
 * - NOT authoritative topology.
 * - Confirmed proposals are linked to `gis_translines` via `confirmed_transline_id`.
 */
class GisTranslineProposalModel extends Model
{
    protected $table            = 'gis_transline_proposals';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'penyulang_id',
        'section_id',
        'source_asset_id',
        'target_asset_id',
        'natural_key',
        'proposed_conductor_type',
        'proposed_conductor_size',
        'proposed_distance',
        'proposed_geometry',
        'classification',
        'confidence_score',
        'evidence_json',
        'proposal_source',
        'engine_version',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_note',
        'confirmed_transline_id',
        'deleted_at',
    ];

    protected bool $allowEmptyInserts = false;

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Status Constants
    public const STATUS_PENDING_REVIEW = 'PENDING_REVIEW';
    public const STATUS_CONFIRMED      = 'CONFIRMED';
    public const STATUS_REJECTED       = 'REJECTED';
    public const STATUS_STALE          = 'STALE';

    // Classification Constants
    public const CLASSIFICATION_AUTO_MATCH   = 'AUTO_MATCH';
    public const CLASSIFICATION_NEEDS_REVIEW = 'NEEDS_REVIEW';
    public const CLASSIFICATION_INVALID      = 'INVALID';
    public const CLASSIFICATION_MISSING      = 'MISSING';

    /**
     * Find existing active proposal by natural key
     */
    public function findByNaturalKey(string $naturalKey): ?array
    {
        return $this->where('natural_key', $naturalKey)
            ->where('deleted_at IS NULL')
            ->first();
    }

    /**
     * Get pending proposals by section
     */
    public function getPendingBySection(int $sectionId): array
    {
        return $this->where('section_id', $sectionId)
            ->where('status', self::STATUS_PENDING_REVIEW)
            ->where('deleted_at IS NULL')
            ->orderBy('confidence_score', 'DESC')
            ->findAll();
    }
}
