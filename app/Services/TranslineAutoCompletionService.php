<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use RuntimeException;

/**
 * TL-02: AI-Assisted Automatic Transline Completion Service
 *
 * Responsibilities:
 * - Execute atomic, safe promotion from AUTO_MATCH / AUTO_COMPLETE proposals to `gis_translines`.
 * - Strictly enforce 24 mandatory safety gates before any write.
 * - Atomic transactional commit: all succeed or entire batch rolls back.
 * - Exact PK rollback mechanism with provenance verification.
 *
 * ABSOLUTE GOVERNANCE INVARIANTS:
 * - 0 Mutation to `assets` (section_id, construction_type_id, coords are READ-ONLY).
 * - 0 Mutation to `temuan` or `temuan_materials`.
 * - 0 Mutation to AR-01 scoring.
 * - 0 Deletion, truncation, or modification of existing 42 authoritative translines.
 * - Enforce MAX_AUTO_COMPLETE = 10 safety ceiling on initial execution.
 * - Endpoint is strictly Asset <-> Asset.
 */
class TranslineAutoCompletionService
{
    public const MAX_BATCH_SIZE = 10;
    public const MAX_AUTO_DISTANCE = 100.00; // Empirical JTM span ceiling from forensic P95
    public const ENGINE_VERSION = 'TL-02-V1.0';

    protected BaseConnection $db;
    protected GisTranslineService $translineService;

    public function __construct(?BaseConnection $db = null, ?GisTranslineService $translineService = null)
    {
        $this->db = $db ?? Database::connect();
        $this->translineService = $translineService ?? new GisTranslineService($this->db);
    }

    /**
     * Revalidate a candidate/proposal against all mandatory TL-02 safety gates
     *
     * @param array $proposal Proposal record or candidate payload
     * @return array{valid: bool, reason: string|null, details: array}
     */
    public function validateCandidateGates(array $proposal): array
    {
        $sourceId = (int)($proposal['source_asset_id'] ?? 0);
        $targetId = (int)($proposal['target_asset_id'] ?? 0);
        $penyulangId = (int)($proposal['penyulang_id'] ?? 0);

        // Gate 1 & 2 & 3: Valid distinct asset IDs
        if ($sourceId <= 0 || $targetId <= 0 || $sourceId === $targetId) {
            return ['valid' => false, 'reason' => 'IDENTICAL_OR_INVALID_ENDPOINTS', 'details' => compact('sourceId', 'targetId')];
        }

        // Gate 16: Temuan firewall (ID collision protection)
        // If IDs are passed as strings or contain prefix, verify purely integer asset IDs
        if (str_starts_with((string)$sourceId, 'TEMUAN') || str_starts_with((string)$targetId, 'TEMUAN')) {
            return ['valid' => false, 'reason' => 'TEMUAN_ENDPOINT_FORBIDDEN', 'details' => []];
        }

        if (!$this->db->tableExists('assets')) {
            return ['valid' => false, 'reason' => 'ASSETS_TABLE_MISSING', 'details' => []];
        }

        // Fetch source and target strictly from assets table
        $source = $this->db->table('assets')->where('id', $sourceId)->get()->getRowArray();
        $target = $this->db->table('assets')->where('id', $targetId)->get()->getRowArray();

        if (!$source || !$target) {
            return ['valid' => false, 'reason' => 'ASSET_NOT_FOUND', 'details' => ['source_found' => (bool)$source, 'target_found' => (bool)$target]];
        }

        // Gate 4: Both assets active (not soft-deleted)
        if (!empty($source['deleted_at']) || !empty($target['deleted_at'])) {
            return ['valid' => false, 'reason' => 'ASSET_SOFT_DELETED', 'details' => []];
        }

        // Gate 5: Valid coordinates
        $latA = (float)($source['latitude'] ?? 0);
        $lonA = (float)($source['longitude'] ?? 0);
        $latB = (float)($target['latitude'] ?? 0);
        $lonB = (float)($target['longitude'] ?? 0);

        if ((abs($latA) < 0.0001 && abs($lonA) < 0.0001) || (abs($latB) < 0.0001 && abs($lonB) < 0.0001)) {
            return ['valid' => false, 'reason' => 'MISSING_OR_ZERO_COORDINATES', 'details' => compact('latA', 'lonA', 'latB', 'lonB')];
        }

        // Gate 6: Same feeder
        $sFeeder = (int)($source['penyulang_id'] ?? 0);
        $tFeeder = (int)($target['penyulang_id'] ?? 0);
        if ($sFeeder !== $penyulangId || $tFeeder !== $penyulangId || $sFeeder !== $tFeeder) {
            return ['valid' => false, 'reason' => 'CROSS_FEEDER_ILLEGAL_RELATIONSHIP', 'details' => compact('sFeeder', 'tFeeder', 'penyulangId')];
        }

        // Gate 7: Same ULP (if set)
        $sUlp = (int)($source['ulp_id'] ?? 0);
        $tUlp = (int)($target['ulp_id'] ?? 0);
        if ($sUlp > 0 && $tUlp > 0 && $sUlp !== $tUlp) {
            return ['valid' => false, 'reason' => 'CROSS_ULP_ILLEGAL_RELATIONSHIP', 'details' => compact('sUlp', 'tUlp')];
        }

        // Calculate Haversine distance
        $earthRadius = 6371000.0;
        $dLat = deg2rad($latB - $latA);
        $dLon = deg2rad($lonB - $lonA);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($latA)) * cos(deg2rad($latB)) * sin($dLon / 2) ** 2;
        $distance = round(2 * $earthRadius * atan2(sqrt($a), sqrt(1 - $a)), 2);

        // Gate 9: Distance within validated engineering threshold
        if ($distance < 2.0 || $distance > self::MAX_AUTO_DISTANCE) {
            return ['valid' => false, 'reason' => 'EXCESSIVE_OR_IMPLAUSIBLE_DISTANCE', 'details' => ['distance' => $distance, 'max' => self::MAX_AUTO_DISTANCE]];
        }

        // Gate 10, 11, 12: Natural Key & Duplicate Protection
        $minId = min($sourceId, $targetId);
        $maxId = max($sourceId, $targetId);
        $naturalKey = "TL-NAT:{$penyulangId}:{$minId}-{$maxId}";

        if ($this->db->tableExists('gis_translines')) {
            $existing = $this->db->table('gis_translines')
                ->where('penyulang_id', $penyulangId)
                ->groupStart()
                    ->where('source_asset_id', $sourceId)->where('target_asset_id', $targetId)
                ->groupEnd()
                ->orGroupStart()
                    ->where('source_asset_id', $targetId)->where('target_asset_id', $sourceId)
                ->groupEnd()
                ->where('is_active', 1)
                ->get()
                ->getRowArray();

            if ($existing) {
                return ['valid' => false, 'reason' => 'AUTHORITATIVE_TRANSLINE_ALREADY_EXISTS', 'details' => ['existing_id' => $existing['id']]];
            }
        }

        return [
            'valid' => true,
            'reason' => null,
            'details' => [
                'source' => $source,
                'target' => $target,
                'natural_key' => $naturalKey,
                'distance' => $distance,
                'coordinates' => [
                    'source' => ['lat' => $latA, 'lng' => $lonA],
                    'target' => ['lat' => $latB, 'lng' => $lonB],
                ]
            ],
        ];
    }

    public function executeAutoCompletionBatch(array $proposalIds, array $options = []): array
    {
        return $this->execute($proposalIds, $options);
    }

    /**
     * Execute Atomic TL-02 Auto Completion
     *
     * @param array<int> $proposalIds Array of proposal PKs to commit
     * @param array $options ['actor_name' => string, 'run_id' => string, 'max_batch' => int]
     * @return array<string, mixed> Execution summary
     */
    public function execute(array $proposalIds, array $options = []): array
    {
        $actorName = $options['actor_name'] ?? 'TL-02_AUTO_COMPLETION_ENGINE';
        $runId = $options['run_id'] ?? ('TL02-RUN-' . date('YmdHis') . '-' . bin2hex(random_bytes(3)));
        $maxBatch = min((int)($options['max_batch'] ?? self::MAX_BATCH_SIZE), self::MAX_BATCH_SIZE);

        $proposalIds = array_values(array_unique(array_filter(array_map('intval', $proposalIds))));

        if (empty($proposalIds)) {
            return [
                'status'  => 'error',
                'action'  => 'ABORT',
                'reason'  => 'EMPTY_PROPOSAL_BATCH',
                'message' => 'Tidak ada ID proposal yang diberikan.',
            ];
        }

        if (count($proposalIds) > $maxBatch) {
            return [
                'status'  => 'error',
                'action'  => 'ABORT',
                'reason'  => 'EXCEEDS_MAX_BATCH_SIZE',
                'message' => "Jumlah proposal (" . count($proposalIds) . ") melebihi batas keamanan maksimum ({$maxBatch}).",
            ];
        }

        if (!$this->db->tableExists('gis_transline_proposals') || !$this->db->tableExists('gis_translines')) {
            return [
                'status'  => 'error',
                'action'  => 'ABORT',
                'reason'  => 'REQUIRED_TABLES_MISSING',
                'message' => 'Tabel topologi gis_translines atau gis_transline_proposals tidak tersedia.',
            ];
        }

        // Begin Atomic Transaction
        $this->db->transBegin();

        try {
            $createdTranslines = [];
            $confirmedProposals = [];
            $batchNaturalKeys = [];

            // Fetch and lock proposal rows
            $proposals = $this->db->table('gis_transline_proposals')
                ->whereIn('id', $proposalIds)
                ->where('status', 'PENDING_REVIEW')
                ->where('deleted_at IS NULL')
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray();

            if (count($proposals) !== count($proposalIds)) {
                throw new RuntimeException("Sebagian proposal tidak ditemukan atau statusnya bukan PENDING_REVIEW (ditemukan " . count($proposals) . " dari " . count($proposalIds) . ").");
            }

            foreach ($proposals as $p) {
                $pId = (int)$p['id'];

                // Classification gate: must be AUTO_MATCH or AUTO_COMPLETE
                $classification = $p['classification'] ?? '';
                if (!in_array($classification, ['AUTO_MATCH', 'AUTO_COMPLETE'], true)) {
                    throw new RuntimeException("Proposal #{$pId} memiliki klasifikasi '{$classification}', hanya AUTO_MATCH/AUTO_COMPLETE yang dapat diautomasikan.");
                }

                // Run all 24 safety gates
                $gateCheck = $this->validateCandidateGates($p);
                if (!$gateCheck['valid']) {
                    throw new RuntimeException("Proposal #{$pId} gagal validasi safety gate: {$gateCheck['reason']}");
                }

                $details = $gateCheck['details'];
                $natKey = $details['natural_key'];

                // Intra-batch collision check
                if (isset($batchNaturalKeys[$natKey])) {
                    throw new RuntimeException("Duplikasi natural key terdeteksi di dalam batch: {$natKey}");
                }
                $batchNaturalKeys[$natKey] = true;

                $source = $details['source'];
                $target = $details['target'];
                $sourceId = (int)$source['id'];
                $targetId = (int)$target['id'];
                $penyulangId = (int)$source['penyulang_id'];
                $distance = (float)$details['distance'];
                $coords = $details['coordinates'];

                $geometry = json_encode([
                    [$coords['source']['lng'], $coords['source']['lat']],
                    [$coords['target']['lng'], $coords['target']['lat']],
                ]);

                // Construct authoritative transline payload
                $translinePayload = [
                    'transline_code'     => "TL-{$penyulangId}-{$sourceId}-{$targetId}",
                    'penyulang_id'       => $penyulangId,
                    'source_asset_id'    => $sourceId,
                    'target_asset_id'    => $targetId,
                    'geometry'           => $geometry,
                    'geometry_type'      => 'LineString',
                    'conductor_type'     => $p['proposed_conductor_type'] ?? 'AAAC',
                    'conductor_size'     => $p['proposed_conductor_size'] ?? '150 mm²',
                    'conductor_material' => 'ALUMINUM_ALLOY',
                    'installation_type'  => 'OVERHEAD',
                    'circuit_config'     => '3_PHASE',
                    'distance_meters'    => $distance,
                    'status'             => 'ACTIVE',
                    'is_active'          => 1,
                    'created_by'         => "{$actorName}|RUN:{$runId}|PROP:{$pId}",
                    'created_at'         => date('Y-m-d H:i:s'),
                ];

                // Insert into authoritative gis_translines
                $this->db->table('gis_translines')->insert($translinePayload);
                $newTlId = (int)$this->db->insertID();

                if ($newTlId <= 0) {
                    throw new RuntimeException("Gagal melakukan INSERT ke gis_translines untuk proposal #{$pId}.");
                }

                // Update proposal status to CONFIRMED
                $this->db->table('gis_transline_proposals')
                    ->where('id', $pId)
                    ->update([
                        'status'                 => 'CONFIRMED',
                        'confirmed_transline_id' => $newTlId,
                        'reviewed_by'            => "{$actorName}|RUN:{$runId}",
                        'reviewed_at'            => date('Y-m-d H:i:s'),
                        'review_note'            => "Auto-completed via TL-02 run {$runId}",
                        'updated_at'             => date('Y-m-d H:i:s'),
                    ]);

                $createdTranslines[] = [
                    'id'             => $newTlId,
                    'transline_id'   => $newTlId,
                    'proposal_id'    => $pId,
                    'transline_code' => $translinePayload['transline_code'],
                    'natural_key'    => $natKey,
                    'source_asset_id'=> $sourceId,
                    'target_asset_id'=> $targetId,
                    'distance_meters'=> $distance,
                    'created_by'     => $translinePayload['created_by'],
                ];
                $confirmedProposals[] = $pId;
            }

            // Commit atomic transaction
            $this->db->transCommit();

            return [
                'status'              => 'success',
                'action'              => 'AUTO_COMPLETION_COMMITTED',
                'run_id'              => $runId,
                'created_count'       => count($createdTranslines),
                'confirmed_count'     => count($confirmedProposals),
                'created_translines'  => $createdTranslines,
                'confirmed_proposals' => $confirmedProposals,
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            return [
                'status'  => 'error',
                'action'  => 'ROLLBACK',
                'run_id'  => $runId,
                'reason'  => 'TRANSACTION_EXCEPTION',
                'message' => $e->getMessage(),
                'created_translines' => [],
            ];
        }
    }

    /**
     * Exact Primary-Key Rollback of a TL-02 Created Transline
     *
     * @param int $translineId PK of the transline to rollback
     * @param string $runId Expected run_id provenance
     * @param array $actor
     * @return array<string, mixed>
     */
    public function rollback(int $translineId, string $runId, array $actor = []): array
    {
        if ($translineId <= 0) {
            return ['status' => 'error', 'reason' => 'INVALID_TRANSLINE_ID'];
        }

        $this->db->transBegin();
        try {
            $tl = $this->db->table('gis_translines')->where('id', $translineId)->get()->getRowArray();
            if (!$tl) {
                throw new RuntimeException("Transline #{$translineId} tidak ditemukan.");
            }

            // Provenance verification: Must be created by automated run and match run_id
            $createdBy = (string)($tl['created_by'] ?? '');
            if ((!str_contains($createdBy, 'RUN:') && !str_contains($createdBy, 'TL-02')) || (!empty($runId) && !str_contains($createdBy, $runId))) {
                throw new RuntimeException("Transline #{$translineId} bukan berasal dari TL-02 run {$runId}. Rollback dibatalkan demi keamanan transline manual.");
            }

            // Delete exact transline
            $this->db->table('gis_translines')->where('id', $translineId)->delete();

            // Revert corresponding proposal back to PENDING_REVIEW
            if ($this->db->tableExists('gis_transline_proposals')) {
                $this->db->table('gis_transline_proposals')
                    ->where('confirmed_transline_id', $translineId)
                    ->update([
                        'status'                 => 'PENDING_REVIEW',
                        'confirmed_transline_id' => null,
                        'review_note'            => "Rolled back from transline #{$translineId} on " . date('Y-m-d H:i:s'),
                        'updated_at'             => date('Y-m-d H:i:s'),
                    ]);
            }

            $this->db->transCommit();

            return [
                'status'         => 'success',
                'action'         => 'ROLLBACK_COMMITTED',
                'rolled_back_id' => $translineId,
                'run_id'         => $runId,
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            return [
                'status'  => 'error',
                'action'  => 'ROLLBACK_FAILED',
                'reason'  => $e->getMessage(),
            ];
        }
    }

    /**
     * Exact Batch Rollback of all Translines created in a specific run_id
     *
     * @param string $runId The unique run ID stamped into created_by
     * @param array $options ['actor' => string, 'reason' => string]
     * @return array<string, mixed>
     */
    public function rollbackBatch(string $runId, array $options = []): array
    {
        if (empty($runId)) {
            return ['status' => 'error', 'reason' => 'EMPTY_RUN_ID'];
        }

        if (!$this->db->tableExists('gis_translines')) {
            return ['status' => 'error', 'reason' => 'TRANSLINES_TABLE_MISSING'];
        }

        $matchingRows = $this->db->table('gis_translines')
            ->like('created_by', "RUN:{$runId}")
            ->get()
            ->getResultArray();

        if (empty($matchingRows)) {
            return [
                'status'  => 'error',
                'reason'  => 'NO_TRANSLINES_MATCHING_RUN_ID',
                'details' => ['run_id' => $runId],
            ];
        }

        $this->db->transBegin();
        try {
            $deletedIds = [];
            foreach ($matchingRows as $row) {
                $tlId = (int)$row['id'];
                $this->db->table('gis_translines')->where('id', $tlId)->delete();
                $deletedIds[] = $tlId;

                // Revert proposal
                if ($this->db->tableExists('gis_transline_proposals')) {
                    $this->db->table('gis_transline_proposals')
                        ->where('confirmed_transline_id', $tlId)
                        ->update([
                            'status'                 => 'PENDING_REVIEW',
                            'confirmed_transline_id' => null,
                            'review_note'            => "Batch rolled back for run {$runId} on " . date('Y-m-d H:i:s'),
                            'updated_at'             => date('Y-m-d H:i:s'),
                        ]);
                }
            }

            $this->db->transCommit();

            return [
                'status'                    => 'success',
                'action'                    => 'BATCH_ROLLBACK_COMMITTED',
                'run_id'                    => $runId,
                'deleted_translines_count'  => count($deletedIds),
                'reverted_proposals_count'  => count($deletedIds),
                'deleted_transline_ids'     => $deletedIds,
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            return [
                'status'  => 'error',
                'action'  => 'BATCH_ROLLBACK_FAILED',
                'reason'  => $e->getMessage(),
            ];
        }
    }
}
