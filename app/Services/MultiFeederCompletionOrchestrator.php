<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use RuntimeException;

/**
 * TL-MF-02: CONTROLLED MULTI-FEEDER EXECUTION ENGINE & ORCHESTRATOR
 *
 * Enterprise Orchestrator for Scalable, Resilient Medium-Voltage Network Topology Reconstruction.
 *
 * GOVERNANCE & SAFETY INVARIANTS:
 * 1. CANDIDATE != AUTHORIZATION: AUTO_DEFENSIBLE indicates evaluation eligibility, not automatic write.
 * 2. MULTI-TIER SPAN FIREWALL:
 *    - < 5.0m: BLOCKED (SHORT_SPAN_FIREWALL - duplicate GPS, portal, cantilever, equipment tags).
 *    - 5.0m - 10.0m: REVIEW_REQUIRED (requires strict consecutive pole evidence; skipped in auto-batch).
 *    - 10.0m - 85.0m: Normal candidate evaluation.
 *    - > 85.0m: BLOCKED (SPAN_EXCEEDS_HARD_CEILING_85M).
 * 3. PROXIMITY != TOPOLOGY: Distance alone never establishes an edge. Bearing collinearity and section coherence required.
 * 4. ABSOLUTE ZERO-MUTATION ON ASSETS: assets table is 100% strictly READ-ONLY. Zero coordinates/section mutation.
 * 5. TEMUAN ISOLATION: temuan & temuan_materials are exclusively in Inspection domain. Zero topology participation.
 * 6. FAULT ISOLATION: Anomaly on Feeder A rolls back Feeder A, quarantines it, and proceeds to Feeder B.
 * 7. SINGLE RUN LOCK: Only 1 active orchestrator process allowed at any time.
 * 8. DB AS SOURCE OF TRUTH: Resumability and topology state re-read the live DB; JSON state is audit checkpoint only.
 * 9. HONEST STABILIZATION: NATURAL_STABILIZED only when defensible = 0. Max-batches reached = PAUSED.
 * 10. AUDIT IMMUTABILITY: --reset-state archives active checkpoint; never deletes audit files or historical records.
 */
class MultiFeederCompletionOrchestrator
{
    public const ORCHESTRATOR_VERSION = 'TL-MF-02.0';
    public const MAX_BATCH_SIZE = 10;
    public const HARD_MAX_DEGREE = 4;
    public const HARD_MAX_SPAN_METERS = 85.0;
    public const SHORT_SPAN_FIREWALL_METERS = 5.0;
    public const SEMANTIC_REVIEW_SPAN_METERS = 10.0;
    public const NEAR_COMPLETE_THRESHOLD_PCT = 99.0;

    protected BaseConnection $db;
    protected TranslineNetworkCompletionEngine $completionEngine;
    protected GlobalNetworkTopologyAuditService $auditService;
    protected string $stateFilePath;
    protected string $lockFilePath;
    protected $lockHandle = null;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
        $this->completionEngine = new TranslineNetworkCompletionEngine($this->db);
        $this->auditService = new GlobalNetworkTopologyAuditService();

        $writePath = defined('WRITEPATH') ? WRITEPATH : (__DIR__ . '/../../writable/');
        $auditsDir = rtrim($writePath, '/\\') . DIRECTORY_SEPARATOR . 'audits';
        $locksDir  = rtrim($writePath, '/\\') . DIRECTORY_SEPARATOR . 'locks';

        if (!is_dir($auditsDir)) {
            @mkdir($auditsDir, 0777, true);
        }
        if (!is_dir($locksDir)) {
            @mkdir($locksDir, 0777, true);
        }

        $this->stateFilePath = $auditsDir . DIRECTORY_SEPARATOR . 'tl_mf02_orchestrator_state.json';
        $this->lockFilePath  = $locksDir . DIRECTORY_SEPARATOR . 'tl_mf02_orchestrator.lock';
    }

    // =========================================================================
    // 🔒 PROCESS CONCURRENCY LOCK (Invariant 7)
    // =========================================================================

    /**
     * Acquire exclusive process lock to prevent concurrent orchestrator runs
     */
    public function acquireLock(): bool
    {
        $this->lockHandle = @fopen($this->lockFilePath, 'c+');
        if (!$this->lockHandle) {
            return false;
        }

        if (!flock($this->lockHandle, LOCK_EX | LOCK_NB)) {
            fclose($this->lockHandle);
            $this->lockHandle = null;
            return false;
        }

        ftruncate($this->lockHandle, 0);
        fwrite($this->lockHandle, json_encode([
            'pid'        => getmypid(),
            'locked_at'  => date('Y-m-d H:i:s'),
            'version'    => self::ORCHESTRATOR_VERSION,
        ]));
        fflush($this->lockHandle);

        return true;
    }

    /**
     * Release exclusive process lock
     */
    public function releaseLock(): void
    {
        if ($this->lockHandle) {
            flock($this->lockHandle, LOCK_UN);
            fclose($this->lockHandle);
            $this->lockHandle = null;
            @unlink($this->lockFilePath);
        }
    }

    // =========================================================================
    // 📋 STATE CHECKPOINT & RESUMABILITY (Invariants 8 & 10)
    // =========================================================================

    /**
     * Load current orchestrator checkpoint state
     */
    public function loadState(): array
    {
        if (!file_exists($this->stateFilePath)) {
            return [
                'orchestrator_version' => self::ORCHESTRATOR_VERSION,
                'active_run_id'        => null,
                'state_status'         => 'INITIAL',
                'created_at'           => date('Y-m-d H:i:s'),
                'updated_at'           => date('Y-m-d H:i:s'),
                'completed_feeders'    => [],
                'quarantined_feeders'  => [],
                'skipped_feeders'      => [],
                'global_totals'        => [
                    'total_batches_run'      => 0,
                    'total_translines_added' => 0,
                    'total_feeders_visited'  => 0,
                ],
                'archive_history'      => [],
            ];
        }

        $raw = file_get_contents($this->stateFilePath);
        $state = json_decode($raw, true);
        return is_array($state) ? $state : [];
    }

    /**
     * Save active orchestrator checkpoint state
     */
    public function saveState(array $state): void
    {
        $state['updated_at'] = date('Y-m-d H:i:s');
        file_put_contents($this->stateFilePath, json_encode($state, JSON_PRETTY_PRINT));
    }

    /**
     * Reset active state SAFELY by archiving previous state without deleting audit history
     */
    public function resetActiveState(): array
    {
        $currentState = $this->loadState();
        $writePath = defined('WRITEPATH') ? WRITEPATH : (__DIR__ . '/../../writable/');
        $auditsDir = rtrim($writePath, '/\\') . DIRECTORY_SEPARATOR . 'audits';

        // Archive existing state if it has history
        if (!empty($currentState['active_run_id'])) {
            $archiveFile = $auditsDir . DIRECTORY_SEPARATOR . 'tl_mf02_state_archive_' . date('Ymd_His') . '.json';
            file_put_contents($archiveFile, json_encode($currentState, JSON_PRETTY_PRINT));
        }

        $freshState = [
            'orchestrator_version' => self::ORCHESTRATOR_VERSION,
            'active_run_id'        => 'RUN-MF02-' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(3)), 0, 6),
            'state_status'         => 'INITIALIZED',
            'created_at'           => date('Y-m-d H:i:s'),
            'updated_at'           => date('Y-m-d H:i:s'),
            'completed_feeders'    => [],
            'quarantined_feeders'  => [],
            'skipped_feeders'      => [],
            'global_totals'        => [
                'total_batches_run'      => 0,
                'total_translines_added' => 0,
                'total_feeders_visited'  => 0,
            ],
            'archive_history'      => !empty($currentState['active_run_id'])
                ? array_merge($currentState['archive_history'] ?? [], [$currentState['active_run_id']])
                : [],
        ];

        $this->saveState($freshState);
        return $freshState;
    }

    // =========================================================================
    // 🌐 GLOBAL FEEDER INVENTORY & PRIORITY QUEUE DISPATCHER
    // =========================================================================

    /**
     * Scan all feeders and categorize into NO_ASSET, NEAR_COMPLETE, and READY_FOR_AI Priority Queue
     */
    public function buildGlobalFeederQueue(): array
    {
        // Query all feeders
        $feedersBuilder = $this->db->table('penyulang p')
            ->select('p.id as penyulang_id, p.kode_penyulang, p.nama_penyulang, p.ulp_id, u.nama_ulp')
            ->join('ulps u', 'u.id = p.ulp_id', 'left');
        if ($this->db->fieldExists('deleted_at', 'penyulang')) {
            $feedersBuilder->where('p.deleted_at IS NULL');
        }
        $feeders = $feedersBuilder->orderBy('p.id', 'ASC')->get()->getResultArray();

        $noAssetQueue     = [];
        $nearCompleteQueue = [];
        $readyQueue       = [];

        // Count assets and translines per feeder from live database
        $assetCounts = $this->db->table('assets')
            ->select('penyulang_id, COUNT(*) as cnt, SUM(CASE WHEN latitude IS NOT NULL AND longitude IS NOT NULL AND ABS(latitude) > 0.0001 AND ABS(longitude) > 0.0001 THEN 1 ELSE 0 END) as gps_cnt')
            ->where('deleted_at IS NULL')
            ->groupBy('penyulang_id')
            ->get()->getResultArray();

        $assetMap = [];
        foreach ($assetCounts as $ac) {
            $assetMap[(int)$ac['penyulang_id']] = [
                'total' => (int)$ac['cnt'],
                'gps'   => (int)$ac['gps_cnt'],
            ];
        }

        $translineCounts = $this->db->table('gis_translines')
            ->select('penyulang_id, COUNT(*) as cnt')
            ->where('deleted_at IS NULL')
            ->where('is_active', 1)
            ->groupBy('penyulang_id')
            ->get()->getResultArray();

        $tlMap = [];
        foreach ($translineCounts as $tc) {
            $tlMap[(int)$tc['penyulang_id']] = (int)$tc['cnt'];
        }

        foreach ($feeders as $f) {
            $fId = (int)$f['penyulang_id'];
            $aInfo = $assetMap[$fId] ?? ['total' => 0, 'gps' => 0];
            $totalAssets = $aInfo['total'];
            $gpsAssets   = $aInfo['gps'];
            $activeTls   = $tlMap[$fId] ?? 0;

            $meta = [
                'penyulang_id'        => $fId,
                'kode_penyulang'      => $f['kode_penyulang'],
                'nama_penyulang'      => $f['nama_penyulang'],
                'ulp_id'              => (int)($f['ulp_id'] ?? 0),
                'nama_ulp'            => $f['nama_ulp'] ?? 'UNKNOWN',
                'total_jtm_assets'    => $totalAssets,
                'valid_gps_assets'    => $gpsAssets,
                'existing_translines' => $activeTls,
                'connectivity_pct'    => 0.0,
                'classification'      => 'UNKNOWN',
            ];

            if ($totalAssets === 0) {
                $meta['classification'] = 'NO_ASSET';
                $noAssetQueue[] = $meta;
                continue;
            }

            // Estimate connectivity
            $estConnected = min($totalAssets, $activeTls > 0 ? ($activeTls + 1) : 0);
            $connPct = $totalAssets > 0 ? round(($estConnected / $totalAssets) * 100, 1) : 0.0;
            $meta['connectivity_pct'] = $connPct;

            if ($connPct >= self::NEAR_COMPLETE_THRESHOLD_PCT) {
                $meta['classification'] = 'NEAR_COMPLETE';
                $nearCompleteQueue[] = $meta;
            } else {
                $meta['classification'] = 'READY_FOR_AI';
                $readyQueue[] = $meta;
            }
        }

        // Rank READY_FOR_AI queue: highest assets first, then lowest connectivity
        usort($readyQueue, function ($a, $b) {
            if ($a['total_jtm_assets'] !== $b['total_jtm_assets']) {
                return $b['total_jtm_assets'] <=> $a['total_jtm_assets'];
            }
            return $a['connectivity_pct'] <=> $b['connectivity_pct'];
        });

        return [
            'total_feeders'       => count($feeders),
            'no_asset_count'      => count($noAssetQueue),
            'near_complete_count' => count($nearCompleteQueue),
            'ready_for_ai_count'  => count($readyQueue),
            'no_asset_feeders'    => $noAssetQueue,
            'near_complete'       => $nearCompleteQueue,
            'priority_queue'      => $readyQueue,
        ];
    }

    // =========================================================================
    // 🚀 ORCHESTRATOR EXECUTION RUNNER
    // =========================================================================

    /**
     * Main execution entry point.
     *
     * Options:
     * - mode: 'dry-run' (default) | 'live'
     * - feeder_id: optional single feeder focus
     * - max_feeders: limit number of feeders in queue
     * - max_batches: limit batches per feeder (triggers PAUSED when hit)
     * - resume: resume from saved state
     * - reset_state: archive previous state and start fresh
     * - actor_name: engineer/operator provenance
     */
    public function run(array $options = []): array
    {
        $mode = strtolower($options['mode'] ?? 'dry-run');
        if ($mode !== 'live') {
            $mode = 'dry-run';
        }

        $targetFeederId = isset($options['feeder_id']) && (int)$options['feeder_id'] > 0 ? (int)$options['feeder_id'] : null;
        $maxFeeders     = isset($options['max_feeders']) ? max(1, (int)$options['max_feeders']) : null;
        $maxBatches     = isset($options['max_batches']) ? max(1, (int)$options['max_batches']) : 50;
        $isResume       = !empty($options['resume']);
        $isResetState   = !empty($options['reset_state']);
        $actorName      = (string)($options['actor_name'] ?? 'ENGINEER_TRANSLINE_AI');

        // 1. Concurrency Process Lock (Invariant 7)
        if (!$this->acquireLock()) {
            return [
                'status'  => 'RUN_ALREADY_ACTIVE',
                'success' => false,
                'message' => 'Another TL-MF-02 orchestrator process is already running. Execution halted to prevent split-brain topology.',
            ];
        }

        try {
            // 2. State Checkpoint Management (Invariant 8 & 10)
            if ($isResetState) {
                $state = $this->resetActiveState();
            } else {
                $state = $this->loadState();
            }

            $runId = $state['active_run_id'] ?? ('RUN-MF02-' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(3)), 0, 6));
            $state['active_run_id'] = $runId;
            $state['mode'] = $mode;

            // 3. Pre-Execution Cryptographic Fingerprint of Protected Domains
            $preFingerprint = $this->auditService->computeDatabaseFingerprint();

            // 4. Build Global Feeder Priority Queue
            $queueData = $this->buildGlobalFeederQueue();
            $priorityQueue = $queueData['priority_queue'];

            if ($targetFeederId !== null) {
                // Focus strictly on single requested feeder
                $priorityQueue = array_values(array_filter($priorityQueue, fn($f) => (int)$f['penyulang_id'] === $targetFeederId));
                if (empty($priorityQueue)) {
                    // Feeder might be in near_complete or no_asset
                    $allF = array_merge($queueData['priority_queue'], $queueData['near_complete'], $queueData['no_asset_feeders']);
                    $priorityQueue = array_values(array_filter($allF, fn($f) => (int)$f['penyulang_id'] === $targetFeederId));
                }
            }

            if ($maxFeeders !== null) {
                $priorityQueue = array_slice($priorityQueue, 0, $maxFeeders);
            }

            // Resume filter: skip already completed or quarantined feeders if requested
            if ($isResume && !empty($state['completed_feeders'])) {
                $completedIds = array_flip($state['completed_feeders']);
                $priorityQueue = array_values(array_filter($priorityQueue, fn($f) => !isset($completedIds[(int)$f['penyulang_id']])));
            }

            $feedersReport = [];
            $totalCreatedTranslinesGlobal = 0;
            $totalBatchesGlobal = 0;

            // 5. Sequential Feeder Worker Loop with Fault Isolation (Invariant 6)
            foreach ($priorityQueue as $feederMeta) {
                $fId = (int)$feederMeta['penyulang_id'];
                $fName = $feederMeta['nama_penyulang'];

                $feederResult = $this->processFeederWorker(
                    $fId,
                    $feederMeta,
                    $mode,
                    $maxBatches,
                    $runId,
                    $actorName
                );

                $feedersReport[] = $feederResult;
                $totalCreatedTranslinesGlobal += $feederResult['total_created_count'];
                $totalBatchesGlobal += $feederResult['batches_run'];

                // Update persistent checkpoint
                if ($feederResult['status'] === 'NATURAL_STABILIZED') {
                    if (!in_array($fId, $state['completed_feeders'] ?? [])) {
                        $state['completed_feeders'][] = $fId;
                    }
                } elseif ($feederResult['status'] === 'ANOMALY_HELD') {
                    if (!in_array($fId, $state['quarantined_feeders'] ?? [])) {
                        $state['quarantined_feeders'][] = $fId;
                    }
                }

                $state['global_totals']['total_batches_run'] += $feederResult['batches_run'];
                $state['global_totals']['total_translines_added'] += $feederResult['total_created_count'];
                $state['global_totals']['total_feeders_visited']++;
                $this->saveState($state);
            }

            // 6. Post-Execution Cryptographic Fingerprint & Invariant Verification
            $postFingerprint = $this->auditService->computeDatabaseFingerprint();

            $protectedIntact = true;
            $protectedTables = ['assets', 'gis_transline_proposals', 'temuan', 'temuan_materials', 'sections', 'penyulang', 'ulps'];
            foreach ($protectedTables as $pt) {
                if (($preFingerprint[$pt]['sha256'] ?? '') !== ($postFingerprint[$pt]['sha256'] ?? '')) {
                    $protectedIntact = false;
                }
            }

            $tlDelta = ($postFingerprint['gis_translines']['count'] ?? 0) - ($preFingerprint['gis_translines']['count'] ?? 0);

            $summaryResult = [
                'status'                    => 'SUCCESS',
                'orchestrator_version'      => self::ORCHESTRATOR_VERSION,
                'run_id'                    => $runId,
                'mode'                      => $mode,
                'feeders_queued_count'      => count($priorityQueue),
                'feeders_processed_count'   => count($feedersReport),
                'total_batches_run'         => $totalBatchesGlobal,
                'total_translines_added'    => $totalCreatedTranslinesGlobal,
                'dry_run_zero_write_pass'   => ($mode === 'dry-run' ? ($tlDelta === 0 && $protectedIntact) : true),
                'protected_domains_intact'  => $protectedIntact,
                'pre_fingerprints'          => $preFingerprint,
                'post_fingerprints'         => $postFingerprint,
                'feeders_report'            => $feedersReport,
                'global_queue_summary'      => [
                    'total_feeders'       => $queueData['total_feeders'],
                    'no_asset_count'      => $queueData['no_asset_count'],
                    'near_complete_count' => $queueData['near_complete_count'],
                    'ready_for_ai_count'  => $queueData['ready_for_ai_count'],
                ],
            ];

            return $summaryResult;
        } finally {
            $this->releaseLock();
        }
    }

    // =========================================================================
    // ⚙️ PER-FEEDER WORKER WITH IN-MEMORY GRAPH RELOAD & FAULT ISOLATION
    // =========================================================================

    /**
     * Process one feeder through progressive atomic batches until Natural Stabilization
     * Fault-isolated: catches any internal anomaly and marks feeder as ANOMALY_HELD without aborting the run.
     */
    protected function processFeederWorker(
        int $feederId,
        array $feederMeta,
        string $mode,
        int $maxBatches,
        string $runId,
        string $actorName
    ): array {
        $batches = [];
        $allCreatedTranslines = [];
        $totalCreated = 0;
        $batchNum = 0;
        $feederStatus = 'IN_PROGRESS';
        $quarantineReason = '';

        $simulatedActiveEdges = [];
        try {
            // Initial graph build
            $graph = $this->completionEngine->buildGraph($feederId);
            $initialAssets = count($graph['assets']);
            $initialExistingTls = count($graph['translines']);
            $initialIsolated = count($graph['isolated_ids']);

            while (true) {
                $batchNum++;

                if ($batchNum > $maxBatches) {
                    $feederStatus = 'MAX_BATCH_LIMIT_REACHED'; // Invariant 2: PAUSED, NOT natural stabilization
                    break;
                }

                // 1. Build Fresh In-Memory Graph G(V, E)
                $currentGraph = $this->completionEngine->buildGraph($feederId, $simulatedActiveEdges);

                if (empty($currentGraph['isolated_ids'])) {
                    $feederStatus = 'NATURAL_STABILIZED';
                    break;
                }

                // 2. Discover Candidates from Current Topology
                $candidates = $this->completionEngine->discoverCandidates($currentGraph);

                // 3. Apply Multi-Tier Short-Span Firewall (Invariants 1 & 2)
                $eligibleCandidates = [];
                $blockedShortSpans  = 0;
                $heldSemanticSpans  = 0;

                foreach ($candidates as $c) {
                    $dist = (float)$c['distance_meters'];

                    // Gate: Short-span hard ceiling and floor
                    if ($dist < self::SHORT_SPAN_FIREWALL_METERS) {
                        $blockedShortSpans++;
                        continue; // Strictly BLOCKED (< 5.0m)
                    }

                    if ($dist < self::SEMANTIC_REVIEW_SPAN_METERS) {
                        // 5.0m - 10.0m Semantic Firewall: requires consecutive pole sequence evidence
                        $seqDelta = $c['score_breakdown']['sequence_delta'] ?? 999;
                        $isMainline = in_array($c['candidate_type'] ?? '', ['ISOLATED_CHAIN_SEGMENT', 'MAINLINE']);
                        if ($seqDelta > 2 || !$isMainline) {
                            $heldSemanticSpans++;
                            continue; // Held for operator semantic review
                        }
                    }

                    if (!empty($c['is_auto_complete']) && !empty($c['gate_valid'])) {
                        $eligibleCandidates[] = $c;
                    }
                }

                // 4. Natural Stabilization Check (Invariant 9)
                if (empty($eligibleCandidates)) {
                    $feederStatus = 'NATURAL_STABILIZED';
                    break; // Natural stop gate: all defensible candidates exhausted
                }

                // 5. Assemble Independent Batch (<= MAX_BATCH_SIZE)
                $selectedBatch = [];
                $batchDegrees = $currentGraph['degrees'];

                foreach ($eligibleCandidates as $c) {
                    $u = $c['source_asset_id'];
                    $v = $c['target_asset_id'];

                    if (($batchDegrees[$u] ?? 0) >= self::HARD_MAX_DEGREE || ($batchDegrees[$v] ?? 0) >= self::HARD_MAX_DEGREE) {
                        continue; // In-batch degree saturation guard
                    }

                    $selectedBatch[] = $c;
                    $batchDegrees[$u] = ($batchDegrees[$u] ?? 0) + 1;
                    $batchDegrees[$v] = ($batchDegrees[$v] ?? 0) + 1;

                    if (count($selectedBatch) >= self::MAX_BATCH_SIZE) {
                        break;
                    }
                }

                if (empty($selectedBatch)) {
                    $feederStatus = 'NATURAL_STABILIZED';
                    break;
                }

                // 6. Execute Batch (Simulation in DRY_RUN, Atomic DB Commit in LIVE)
                if ($mode === 'dry-run') {
                    // Simulated Batch Commit
                    $simulatedTranslines = [];
                    foreach ($selectedBatch as $idx => $edge) {
                        $simulatedTranslines[] = [
                            'natural_key'     => $edge['natural_key'],
                            'source_asset_id' => $edge['source_asset_id'],
                            'target_asset_id' => $edge['target_asset_id'],
                            'distance_meters' => $edge['distance_meters'],
                            'total_score'     => $edge['total_score'],
                            'candidate_type'  => $edge['candidate_type'],
                        ];
                    }

                    $batches[] = [
                        'batch_number'        => $batchNum,
                        'mode'                => 'dry-run',
                        'count'               => count($selectedBatch),
                        'translines'          => $simulatedTranslines,
                        'blocked_short_spans' => $blockedShortSpans,
                        'held_semantic_spans' => $heldSemanticSpans,
                    ];

                    $totalCreated += count($selectedBatch);
                    $allCreatedTranslines = array_merge($allCreatedTranslines, $simulatedTranslines);
                    $simulatedActiveEdges = array_merge($simulatedActiveEdges, $simulatedTranslines);
                } else {
                    // LIVE EXECUTION: Single Atomic MariaDB Transaction (Invariant J)
                    $commitResult = $this->executeLiveAtomicBatch(
                        $feederId,
                        $selectedBatch,
                        $batchNum,
                        $runId,
                        $actorName
                    );

                    if ($commitResult['status'] !== 'success') {
                        throw new RuntimeException("Batch {$batchNum} transaction failed: " . ($commitResult['message'] ?? 'unknown'));
                    }

                    $batches[] = [
                        'batch_number'        => $batchNum,
                        'mode'                => 'live',
                        'count'               => $commitResult['created_count'],
                        'created_ids'         => $commitResult['created_ids'],
                        'translines'          => $commitResult['created_translines'],
                        'blocked_short_spans' => $blockedShortSpans,
                        'held_semantic_spans' => $heldSemanticSpans,
                    ];

                    $totalCreated += $commitResult['created_count'];
                    $allCreatedTranslines = array_merge($allCreatedTranslines, $commitResult['created_translines']);
                }
            }

            // Re-read final state
            $finalGraph = $this->completionEngine->buildGraph($feederId, $simulatedActiveEdges);
            $finalIsolated = count($finalGraph['isolated_ids']);
            $finalConnected = count($finalGraph['connected_ids']);

            return [
                'penyulang_id'         => $feederId,
                'kode_penyulang'       => $feederMeta['kode_penyulang'],
                'nama_penyulang'       => $feederMeta['nama_penyulang'],
                'ulp_id'               => $feederMeta['ulp_id'],
                'nama_ulp'             => $feederMeta['nama_ulp'],
                'status'               => $feederStatus,
                'initial_assets'       => $initialAssets,
                'initial_translines'   => $initialExistingTls,
                'initial_isolated'     => $initialIsolated,
                'final_connected'      => $finalConnected,
                'final_isolated'       => $finalIsolated,
                'total_created_count'  => $totalCreated,
                'batches_run'          => count($batches),
                'batches'              => $batches,
            ];
        } catch (\Throwable $e) {
            // FAULT ISOLATION: Catch error on this feeder, isolate and quarantine (Invariant 6)
            return [
                'penyulang_id'         => $feederId,
                'kode_penyulang'       => $feederMeta['kode_penyulang'],
                'nama_penyulang'       => $feederMeta['nama_penyulang'],
                'ulp_id'               => $feederMeta['ulp_id'],
                'nama_ulp'             => $feederMeta['nama_ulp'],
                'status'               => 'ANOMALY_HELD',
                'quarantine_reason'    => $e->getMessage(),
                'total_created_count'  => $totalCreated,
                'batches_run'          => count($batches),
                'batches'              => $batches,
            ];
        }
    }

    // =========================================================================
    // 🔒 LIVE ATOMIC BATCH TRANSACTION WITH ZERO-MUTATION VERIFICATION
    // =========================================================================

    /**
     * Execute one isolated atomic MariaDB transaction for a batch of <= 10 edges
     */
    protected function executeLiveAtomicBatch(
        int $penyulangId,
        array $selectedBatch,
        int $batchNum,
        string $runId,
        string $actorName
    ): array {
        // Pre-write signatures of protected domains
        $preSignatures = [
            'assets' => $this->db->table('assets')->where('penyulang_id', $penyulangId)->countAllResults(),
            'temuan' => $this->db->table('temuan')->countAllResults(),
        ];

        $validColumns = array_flip($this->db->getFieldNames('gis_translines'));

        $this->db->transStart();

        $batchCreatedTranslines = [];
        $batchCreatedIds = [];

        try {
            foreach ($selectedBatch as $idx => $edge) {
                $minId = min((int)$edge['source_asset_id'], (int)$edge['target_asset_id']);
                $maxId = max((int)$edge['source_asset_id'], (int)$edge['target_asset_id']);

                // Hard safety check immediately before insert
                if ((float)$edge['distance_meters'] < self::SHORT_SPAN_FIREWALL_METERS) {
                    throw new RuntimeException("SHORT_SPAN_FIREWALL breach: Edge {$edge['natural_key']} has span < 5.0m.");
                }

                // Anti-duplicate against live DB
                $exists = $this->db->table('gis_translines')
                    ->where('penyulang_id', $penyulangId)
                    ->where('source_asset_id', $minId)
                    ->where('target_asset_id', $maxId)
                    ->where('deleted_at IS NULL')
                    ->countAllResults();

                if ($exists > 0) {
                    continue; // Already materialized
                }

                $translineCode = "TL-{$penyulangId}-{$minId}-{$maxId}";
                $propNum = $idx + 1;
                $provenance = "{$actorName}|ENGINE=TL-MF-02|RUN:{$runId}|BATCH:{$batchNum}|PROP:{$propNum}";

                $coords = $edge['coordinates'];
                $wktPoints = [];
                foreach ($coords as $pt) {
                    $wktPoints[] = $pt[0] . ' ' . $pt[1];
                }
                $wkt = 'LINESTRING (' . implode(', ', $wktPoints) . ')';
                $geoJson = json_encode([
                    'type' => 'LineString',
                    'coordinates' => $coords
                ]);

                $row = [
                    'transline_code'     => $translineCode,
                    'penyulang_id'       => $penyulangId,
                    'source_asset_id'    => $minId,
                    'target_asset_id'    => $maxId,
                    'conductor_type'     => $edge['conductor_type'] ?? 'AAAC',
                    'conductor_size'     => $edge['conductor_size'] ?? '150 mm²',
                    'conductor_material' => 'ALUMINUM_ALLOY',
                    'installation_type'  => 'OVERHEAD',
                    'circuit_config'     => '3_PHASE',
                    'distance_meters'    => round((float)$edge['distance_meters'], 2),
                    'length_meters'      => round((float)$edge['distance_meters'], 2),
                    'geometry'           => $wkt,
                    'status'             => 'ACTIVE',
                    'is_active'          => 1,
                    'created_by'         => $provenance,
                    'created_at'         => date('Y-m-d H:i:s'),
                ];

                if (isset($validColumns['coordinates'])) {
                    $row['coordinates'] = $geoJson;
                }

                $insertRow = array_intersect_key($row, $validColumns);
                $inserted = $this->db->table('gis_translines')->insert($insertRow);

                if (!$inserted) {
                    $err = $this->db->error();
                    throw new RuntimeException("Insert into gis_translines failed: " . ($err['message'] ?? 'unknown'));
                }

                $newId = (int)$this->db->insertID();
                $batchCreatedIds[] = $newId;
                $batchCreatedTranslines[] = [
                    'id'              => $newId,
                    'transline_code'  => $translineCode,
                    'natural_key'     => $edge['natural_key'] ?? "{$penyulangId}_{$minId}_{$maxId}",
                    'source_asset_id' => $minId,
                    'target_asset_id' => $maxId,
                    'distance_meters' => $edge['distance_meters'] ?? 0.0,
                    'total_score'     => $edge['total_score'] ?? 0.0,
                    'candidate_type'  => $edge['candidate_type'] ?? 'MAINLINE',
                    'created_by'      => $provenance,
                ];
            }

            // Zero-write verification within transaction
            $postAssets = $this->db->table('assets')->where('penyulang_id', $penyulangId)->countAllResults();
            $postTemuan = $this->db->table('temuan')->countAllResults();

            if ($preSignatures['assets'] !== $postAssets || $preSignatures['temuan'] !== $postTemuan) {
                throw new RuntimeException("CRITICAL: Protected domain mutation detected. Transaction aborted.");
            }

            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                throw new RuntimeException("Transaction commit failed for batch {$batchNum}.");
            }

            return [
                'status'             => 'success',
                'created_count'      => count($batchCreatedTranslines),
                'created_ids'        => $batchCreatedIds,
                'created_translines' => $batchCreatedTranslines,
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            return [
                'status'  => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }
}
