<?php

namespace App\Services;

use Config\Database;
use CodeIgniter\Database\BaseConnection;

/**
 * CR-ASSET-01: Mass Asset Commit Service
 *
 * Implements two-phase execution:
 * Phase 1: Preflight Plan Generation (Dry-Run with cryptographic SHA-256 fingerprint).
 * Phase 2: Atomic InnoDB Transaction Execution with full audit receipt persistence.
 */
class MassAssetCommitService
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /**
     * Generate preflight execution plan and save to writable/audits/plans/.
     */
    public function generatePlan(array $ingestionBatches, array $topologyResult, array $options = []): array
    {
        $timestamp = date('Ymd_His');
        $planId = "CR-ASSET01-PLAN-{$timestamp}";

        $totalScanned = 0;
        $allAutoAccept = [];
        $allAutoReview = [];
        $allQuarantine = [];
        $filesIncluded = [];

        foreach ($ingestionBatches as $batch) {
            $totalScanned += (int)($batch['total_rows_scanned'] ?? 0);
            $filesIncluded[] = $batch['file_name'] ?? basename($batch['file_path'] ?? 'unknown');

            foreach ($batch['auto_accept'] ?? [] as $item) {
                $allAutoAccept[] = $item;
            }
            foreach ($batch['auto_review'] ?? [] as $item) {
                $allAutoReview[] = $item;
            }
            foreach ($batch['quarantine'] ?? [] as $item) {
                $allQuarantine[] = $item;
            }
        }

        $candidateTranslines = $topologyResult['candidate_translines'] ?? [];
        $isolatedNodes = $topologyResult['isolated_nodes'] ?? [];
        $reviewCandidates = $topologyResult['review_candidates'] ?? [];

        $planPayload = [
            'plan_id'            => $planId,
            'created_at'         => date('Y-m-d H:i:s'),
            'generator'          => 'MassAssetCommitService (CR-ASSET-01)',
            'files_included'     => $filesIncluded,
            'summary'            => [
                'total_rows_scanned'       => $totalScanned,
                'auto_accept_assets'       => count($allAutoAccept),
                'auto_review_assets'       => count($allAutoReview),
                'quarantine_assets'        => count($allQuarantine),
                'candidate_translines'     => count($candidateTranslines),
                'isolated_nodes'           => count($isolatedNodes),
                'topology_review_held'     => count($reviewCandidates),
            ],
            'auto_accept_assets'   => $allAutoAccept,
            'auto_review_assets'   => $allAutoReview,
            'quarantine_assets'    => $allQuarantine,
            'candidate_translines' => $candidateTranslines,
            'isolated_nodes'       => $isolatedNodes,
            'topology_review'      => $reviewCandidates,
        ];

        // Compute cryptographic SHA-256 fingerprint of the plan payload
        $fingerprint = hash('sha256', json_encode($planPayload, JSON_UNESCAPED_SLASHES));
        $planPayload['fingerprint'] = $fingerprint;

        $dir = WRITEPATH . 'audits' . DIRECTORY_SEPARATOR . 'plans';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $shortHash = substr($fingerprint, 0, 8);
        $planFileName = "{$planId}_{$shortHash}.json";
        $planFilePath = $dir . DIRECTORY_SEPARATOR . $planFileName;

        file_put_contents($planFilePath, json_encode($planPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return [
            'plan_id'     => $planId,
            'plan_file'   => $planFilePath,
            'fingerprint' => $fingerprint,
            'created_at'  => $planPayload['created_at'],
            'summary'     => $planPayload['summary'],
        ];
    }

    /**
     * Atomically execute plan from file within an InnoDB database transaction.
     */
    public function executePlan(string $planFilePath, array $options = []): array
    {
        if (!file_exists($planFilePath) || !is_readable($planFilePath)) {
            throw new \InvalidArgumentException("Plan file does not exist or is not readable: {$planFilePath}");
        }

        $rawJson = file_get_contents($planFilePath);
        $planData = json_decode($rawJson, true);
        if (!$planData || !isset($planData['plan_id'])) {
            throw new \RuntimeException("Malformed or invalid plan JSON: {$planFilePath}");
        }

        $planId = $planData['plan_id'];
        $storedHash = $planData['fingerprint'] ?? '';

        // Integrity verification
        $verifyPayload = $planData;
        unset($verifyPayload['fingerprint']);
        $computedHash = hash('sha256', json_encode($verifyPayload, JSON_UNESCAPED_SLASHES));

        if ($storedHash !== '' && $computedHash !== $storedHash) {
            throw new \RuntimeException("PLAN INTEGRITY COMPROMISED: Fingerprint mismatch! Stored: {$storedHash}, Computed: {$computedHash}");
        }

        // Begin transaction
        $this->db->transBegin();

        $assetsInserted = 0;
        $assetsEnriched = 0;
        $assetsSkipped = 0;
        $translinesInserted = 0;
        $now = date('Y-m-d H:i:s');

        try {
            // Step 1: Ingest AUTO_ACCEPT assets
            $assetCodeToIdMap = [];

            // Pre-seed map from existing DB
            if ($this->db->tableExists('assets')) {
                $existing = $this->db->table('assets')
                    ->select('id, kode_asset')
                    ->where('deleted_at IS NULL', null, false)
                    ->get()
                    ->getResultArray();
                foreach ($existing as $e) {
                    $assetCodeToIdMap[strtoupper(trim((string)$e['kode_asset']))] = (int)$e['id'];
                }
            }

            foreach ($planData['auto_accept_assets'] ?? [] as $item) {
                $action = $item['action'] ?? '';
                $payload = $item['payload'] ?? [];
                $code = strtoupper(trim((string)($payload['kode_asset'] ?? '')));

                if ($action === 'INSERT') {
                    $insertData = [
                        'kode_asset'           => $payload['kode_asset'],
                        'nama_asset'           => $payload['nama_asset'],
                        'jenis_asset'          => $payload['jenis_asset'] ?? 'JTM',
                        'ulp_id'               => $payload['ulp_id'] ?? null,
                        'penyulang_id'         => $payload['penyulang_id'] ?? null,
                        'section_id'           => $payload['section_id'] ?? null,
                        'construction_type_id' => $payload['construction_type_id'] ?? null,
                        'lokasi'               => $payload['lokasi'] ?? null,
                        'latitude'             => (string)$payload['latitude'],
                        'longitude'            => (string)$payload['longitude'],
                        'status'               => 'NORMAL',
                        'created_at'           => $now,
                        'updated_at'           => $now,
                    ];

                    $this->db->table('assets')->insert($insertData);
                    $newId = (int)$this->db->insertID();
                    if ($newId > 0) {
                        $assetCodeToIdMap[$code] = $newId;
                        $assetsInserted++;
                    }
                } elseif ($action === 'ENRICH') {
                    $existingId = (int)($payload['id'] ?? 0);
                    $enrichFields = $payload['enrich_fields'] ?? [];
                    if ($existingId > 0 && !empty($enrichFields)) {
                        $enrichFields['updated_at'] = $now;
                        $this->db->table('assets')->where('id', $existingId)->update($enrichFields);
                        $assetsEnriched++;
                        $assetCodeToIdMap[$code] = $existingId;
                    }
                } elseif ($action === 'SKIP') {
                    $assetsSkipped++;
                    if (!isset($assetCodeToIdMap[$code]) && isset($payload['id'])) {
                        $assetCodeToIdMap[$code] = (int)$payload['id'];
                    }
                }
            }

            // Step 2: Ingest candidate translines
            if ($this->db->tableExists('gis_translines')) {
                foreach ($planData['candidate_translines'] ?? [] as $edge) {
                    $sourceCode = strtoupper(trim((string)($edge['source_asset_code'] ?? '')));
                    $targetCode = strtoupper(trim((string)($edge['target_asset_code'] ?? '')));

                    $sourceId = $assetCodeToIdMap[$sourceCode] ?? null;
                    $targetId = $assetCodeToIdMap[$targetCode] ?? null;

                    // Strictly verify both endpoints exist
                    if (!$sourceId || !$targetId) {
                        continue;
                    }

                    // Check anti-duplicate edge in DB
                    $exists = $this->db->table('gis_translines')
                        ->groupStart()
                            ->where('source_asset_id', $sourceId)->where('target_asset_id', $targetId)
                        ->groupEnd()
                        ->orGroupStart()
                            ->where('source_asset_id', $targetId)->where('target_asset_id', $sourceId)
                        ->groupEnd()
                        ->where('deleted_at IS NULL', null, false)
                        ->countAllResults();

                    if ($exists > 0) {
                        continue;
                    }

                    $translinePayload = [
                        'transline_code'     => $edge['transline_code'] ?? "TL-{$sourceCode}-{$targetCode}",
                        'penyulang_id'       => (int)$edge['penyulang_id'],
                        'source_asset_id'    => $sourceId,
                        'target_asset_id'    => $targetId,
                        'geometry'           => $edge['geometry'] ?? null,
                        'geometry_type'      => $edge['geometry_type'] ?? 'LineString',
                        'conductor_type'     => $edge['conductor_type'] ?? 'AAAC',
                        'conductor_size'     => $edge['conductor_size'] ?? '150 mm²',
                        'conductor_material' => $edge['conductor_material'] ?? 'ALUMINUM_ALLOY',
                        'installation_type'  => $edge['installation_type'] ?? 'OVERHEAD',
                        'circuit_config'     => $edge['circuit_config'] ?? '3_PHASE',
                        'distance_meters'    => (float)($edge['distance_meters'] ?? 0.0),
                        'status'             => 'ACTIVE',
                        'is_active'          => 1,
                        'created_by'         => 'CR_ASSET_01_MASS_INGESTION',
                        'created_at'         => $now,
                        'updated_at'         => $now,
                    ];

                    $this->db->table('gis_translines')->insert($translinePayload);
                    $translinesInserted++;
                }
            }

            if ($this->db->transStatus() === false) {
                $this->db->transRollback();
                throw new \RuntimeException("Database transaction failed during mass asset commit.");
            }

            $this->db->transCommit();

            // Step 3: Write execution receipt
            $receiptId = "CR-ASSET01-RECEIPT-" . date('Ymd_His');
            $receiptPayload = [
                'receipt_id'   => $receiptId,
                'plan_id'      => $planId,
                'plan_file'    => $planFilePath,
                'executed_at'  => $now,
                'status'       => 'COMMITTED',
                'mutations'    => [
                    'assets_inserted'      => $assetsInserted,
                    'assets_enriched'      => $assetsEnriched,
                    'assets_skipped'       => $assetsSkipped,
                    'translines_inserted'  => $translinesInserted,
                    'quarantine_count'     => count($planData['quarantine_assets'] ?? []),
                    'auto_review_count'    => count($planData['auto_review_assets'] ?? []),
                    'isolated_nodes_count' => count($planData['isolated_nodes'] ?? []),
                ],
            ];

            $receiptDir = WRITEPATH . 'audits' . DIRECTORY_SEPARATOR . 'receipts';
            if (!is_dir($receiptDir)) {
                mkdir($receiptDir, 0755, true);
            }
            $receiptFile = $receiptDir . DIRECTORY_SEPARATOR . "{$receiptId}.json";
            file_put_contents($receiptFile, json_encode($receiptPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $receiptPayload['receipt_file'] = $receiptFile;
            return $receiptPayload;

        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }
}
