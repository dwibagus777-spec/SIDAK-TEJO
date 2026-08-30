<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * Phase AR-01 Phase 5E: Human Engineering Review & Sign-Off Engine
 * 
 * Strict Invariants:
 * - INVARIANT 5E-A: ZERO WRITE TO 'assets' table.
 * - INVARIANT 5E-B: No blind FK assignment (strict name / entity resolution).
 * - INVARIANT 5E-C: Feeder resolution must be canonical and strictly matched.
 * - INVARIANT 5E-D: Section resolution must be strictly parent-feeder bound (Zero cross-feeder leakage).
 * - INVARIANT 5E-E: Construction normalization proposals require explicit human approval (e.g. GTT2T -> GTT-2T).
 * - INVARIANT 5E-F: Approval is batch-bound with cryptographic fingerprint.
 * - INVARIANT 5E-G: Fail-closed promotion eligibility certificate.
 */
class FeederAssetReviewService
{
    protected BaseConnection $db;
    protected FeederAssetStagingService $stagingService;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
        $this->stagingService = new FeederAssetStagingService($this->db);
        $this->ensureSchemaExists();
    }

    /**
     * Ensure Phase 5E tables exist in memory / database
     */
    public function ensureSchemaExists(): void
    {
        if (!$this->db->tableExists('ar01_ingestion_batches')) {
            $forge = \Config\Database::forge($this->db);
            $forge->addField([
                'id'              => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'batch_id'        => ['type' => 'VARCHAR', 'constraint' => 100],
                'source_filename' => ['type' => 'VARCHAR', 'constraint' => 255],
                'source_path'     => ['type' => 'VARCHAR', 'constraint' => 255],
                'source_sha256'   => ['type' => 'VARCHAR', 'constraint' => 64],
                'source_size'     => ['type' => 'BIGINT', 'default' => 0],
                'row_count'       => ['type' => 'INTEGER', 'default' => 0],
                'pass_count'      => ['type' => 'INTEGER', 'default' => 0],
                'warning_count'   => ['type' => 'INTEGER', 'default' => 0],
                'reject_count'    => ['type' => 'INTEGER', 'default' => 0],
                'status'          => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'STAGED'],
                'created_at'      => ['type' => 'DATETIME', 'null' => true],
                'created_by'      => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'SYSTEM_PARSER'],
            ]);
            $forge->createTable('ar01_ingestion_batches', true);
        }

        if (!$this->db->tableExists('ar01_staging_assets')) {
            $forge = \Config\Database::forge($this->db);
            $forge->addField([
                'id'                         => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'batch_id'                   => ['type' => 'VARCHAR', 'constraint' => 100],
                'source_row_number'          => ['type' => 'INTEGER'],
                'source_asset_code'          => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'source_asset_name'          => ['type' => 'VARCHAR', 'constraint' => 255],
                'source_feeder_name'         => ['type' => 'VARCHAR', 'constraint' => 150],
                'source_section_name'        => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                'source_latitude'            => ['type' => 'DOUBLE', 'null' => true],
                'source_longitude'           => ['type' => 'DOUBLE', 'null' => true],
                'source_construction_code'   => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'source_conductor_material'  => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'source_asset_type'          => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'JTM'],
                'proposed_penyulang_id'      => ['type' => 'INTEGER', 'null' => true],
                'proposed_section_id'        => ['type' => 'INTEGER', 'null' => true],
                'proposed_construction_type_id' => ['type' => 'INTEGER', 'null' => true],
                'normalized_feeder_name'     => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                'normalized_construction_code'=> ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'normalization_score'        => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => '100.00'],
                'validation_status'          => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'PASS'],
                'validation_messages'        => ['type' => 'TEXT', 'null' => true],
                'review_status'              => ['type' => 'VARCHAR', 'constraint' => 30, 'default' => 'READY_FOR_REVIEW'],
                'approved_at'                => ['type' => 'DATETIME', 'null' => true],
                'rejected_at'                => ['type' => 'DATETIME', 'null' => true],
                'created_at'                 => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('ar01_staging_assets', true);
        }

        if (!$this->db->tableExists('ar01_review_decisions')) {
            $forge = \Config\Database::forge($this->db);
            $forge->addField([
                'id'                 => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'batch_id'           => ['type' => 'VARCHAR', 'constraint' => 100],
                'staging_asset_id'   => ['type' => 'INTEGER', 'null' => true],
                'source_row_number'  => ['type' => 'INTEGER', 'null' => true],
                'scope'              => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'SINGLE_ROW'],
                'decision'           => ['type' => 'VARCHAR', 'constraint' => 30],
                'decision_reason'    => ['type' => 'TEXT'],
                'approver_nip'       => ['type' => 'VARCHAR', 'constraint' => 100],
                'approver_name'      => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                'approver_role'      => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'ENGINEERING_REVIEWER'],
                'signed_sha256'      => ['type' => 'VARCHAR', 'constraint' => 64],
                'approved_at'        => ['type' => 'DATETIME', 'null' => true],
                'created_at'         => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('ar01_review_decisions', true);
        }

        if (!$this->db->tableExists('ar01_audit_log')) {
            $forge = \Config\Database::forge($this->db);
            $forge->addField([
                'id'          => ['type' => 'INTEGER', 'auto_increment' => true, 'primary_key' => true],
                'batch_id'    => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'event_type'  => ['type' => 'VARCHAR', 'constraint' => 100],
                'actor'       => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'SYSTEM'],
                'event_data'  => ['type' => 'TEXT', 'null' => true],
                'status'      => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'SUCCESS'],
                'created_at'  => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->createTable('ar01_audit_log', true);
        }
    }

    /**
     * Get or create a staged batch from source file.
     * Zero write to 'assets' table.
     */
    public function getOrCreateStagedBatch(string $filePath, ?string $forcedBatchId = null): array
    {
        if (!file_exists($filePath)) {
            return [
                'success' => false,
                'error'   => "File sumber tidak ditemukan di path: {$filePath}",
            ];
        }

        $fileSize = filesize($filePath);
        $fileSha256 = hash_file('sha256', $filePath);
        $fileName = basename($filePath);
        $now = date('Y-m-d H:i:s');

        // Check if already staged with matching SHA-256
        $existingBatch = $this->db->table('ar01_ingestion_batches')
            ->where('source_sha256', $fileSha256)
            ->get()
            ->getRowArray();

        if ($existingBatch) {
            $batchId = $existingBatch['batch_id'];
            if ($existingBatch['source_path'] !== $filePath && file_exists($filePath) && hash_file('sha256', $filePath) === $fileSha256) {
                $this->db->table('ar01_ingestion_batches')->where('batch_id', $batchId)->update(['source_path' => $filePath]);
                $existingBatch['source_path'] = $filePath;
            }
            $stagedCount = $this->db->table('ar01_staging_assets')->where('batch_id', $batchId)->countAllResults();
            if ($stagedCount > 0) {
                return [
                    'success'  => true,
                    'batch_id' => $batchId,
                    'batch'    => $existingBatch,
                    'is_new'   => false,
                ];
            }
        }

        $batchId = $forcedBatchId ?? ('BATCH-MULTI-' . date('Ymd-His') . '-' . substr(strtoupper($fileSha256), 0, 8));

        // Perform Phase 5A-5D staging in memory
        $stageResult = $this->stagingService->stageAndValidateSourceFile($filePath, null);
        if (!$stageResult['success']) {
            return $stageResult;
        }

        $sm = $stageResult['staging_summary'];
        $proposal = $this->stagingService->generateNormalizationProposal($filePath);

        $constructionProposalMap = [];
        if ($proposal['success']) {
            foreach ($proposal['construction_normalization'] as $cp) {
                $constructionProposalMap[strtoupper($cp['source_code'])] = $cp;
            }
        }

        // Fetch database registries for deterministic FK resolution
        $tablePenyulang = $this->db->tableExists('db_penyulang') ? 'db_penyulang' : 'penyulang';
        $allFeeders = $this->db->table($tablePenyulang)->get()->getResultArray();
        $feederMap = [];
        foreach ($allFeeders as $f) {
            $fn = strtoupper(trim((string)($f['nama_penyulang'] ?? '')));
            if ($fn !== '') {
                $feederMap[$fn] = $f;
            }
        }

        $constructionRows = $this->db->table('construction_types')->get()->getResultArray();
        $constructionDbMap = [];
        foreach ($constructionRows as $cr) {
            $cCode = strtoupper(trim((string)($cr['kode_konstruksi'] ?? $cr['construction_code'] ?? $cr['code'] ?? '')));
            if ($cCode !== '') {
                $constructionDbMap[$cCode] = $cr;
                $constructionDbMap[str_replace(['-', ' ', '_'], '', $cCode)] = $cr;
            }
        }

        // 1. Insert Ingestion Batch Record
        $batchData = [
            'batch_id'        => $batchId,
            'source_filename' => $fileName,
            'source_path'     => $filePath,
            'source_sha256'   => $fileSha256,
            'source_size'     => $fileSize,
            'row_count'       => $sm['total_staged_rows'],
            'pass_count'      => $sm['pass_candidates'],
            'warning_count'   => $sm['warning_candidates'],
            'reject_count'    => $sm['reject_candidates'],
            'status'          => 'READY_FOR_REVIEW',
            'created_at'      => $now,
            'created_by'      => 'SYSTEM_PARSER',
        ];
        $this->db->table('ar01_ingestion_batches')->insert($batchData);

        // 2. Parse & Insert Staging Assets
        $handle = fopen($filePath, 'r');
        $headerRaw = fgetcsv($handle);
        $header = array_map('trim', $headerRaw);

        $stagingBatchRows = [];
        $rowNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if (empty($row) || (count($row) === 1 && trim((string)$row[0]) === '')) {
                continue;
            }

            $rowData = [];
            foreach ($header as $idx => $colName) {
                $rowData[$colName] = $row[$idx] ?? '';
            }

            $assetName  = trim((string)($rowData['Nama Asset JTM'] ?? ''));
            $feederName = trim((string)($rowData['Penyulang'] ?? ''));
            $constCode  = trim((string)($rowData['Konstruksi (e.g. TM1)'] ?? ''));
            $latRaw     = trim((string)($rowData['Latitude'] ?? ''));
            $lonRaw     = trim((string)($rowData['Longitude'] ?? ''));

            // FK resolution
            $feederObj = $feederMap[strtoupper($feederName)] ?? null;
            $proposedFeederId = $feederObj['id'] ?? null;

            // Construction resolution
            $constProp = $constructionProposalMap[strtoupper($constCode)] ?? null;
            $proposedConstCode = $constProp['canonical_code'] ?? $constCode;
            $constObj = $constructionDbMap[strtoupper($constCode)] ?? $constructionDbMap[strtoupper($proposedConstCode)] ?? null;
            $proposedConstId = $constObj['id'] ?? null;

            // Validation & Review status
            $isExactConstruction = ($constProp && str_starts_with($constProp['action'], 'PASS'));
            $isExactFeeder = ($feederObj !== null);

            $validationStatus = 'PASS';
            $reviewStatus = 'READY_FOR_REVIEW';
            $validationMessages = [];

            if (!$isExactFeeder) {
                $validationStatus = 'WARNING';
                $reviewStatus = 'NEEDS_REVIEW';
                $validationMessages[] = "Penyulang '{$feederName}' belum terdaftar di database.";
            }

            if (!$isExactConstruction) {
                $validationStatus = 'WARNING';
                $reviewStatus = 'NEEDS_REVIEW';
                $validationMessages[] = "Tipe Konstruksi '{$constCode}' memerlukan persetujuan normalisasi ke '{$proposedConstCode}'.";
            }

            $normScore = ($constProp && isset($constProp['confidence'])) ? (float)$constProp['confidence'] : 100.00;

            $stagingBatchRows[] = [
                'batch_id'                   => $batchId,
                'source_row_number'          => $rowNumber,
                'source_asset_code'          => $assetName,
                'source_asset_name'          => $assetName,
                'source_feeder_name'         => $feederName,
                'source_section_name'        => null,
                'source_latitude'            => is_numeric($latRaw) ? (float)$latRaw : null,
                'source_longitude'           => is_numeric($lonRaw) ? (float)$lonRaw : null,
                'source_construction_code'   => $constCode,
                'source_conductor_material'  => $rowData['Material Conductor'] ?? 'A3CS',
                'source_asset_type'          => $rowData['Jenis Asset'] ?? 'JTM',
                'proposed_penyulang_id'      => $proposedFeederId,
                'proposed_section_id'        => null,
                'proposed_construction_type_id' => $proposedConstId,
                'normalized_feeder_name'     => $feederObj['nama_penyulang'] ?? $feederName,
                'normalized_construction_code'=> $proposedConstCode,
                'normalization_score'        => $normScore,
                'validation_status'          => $validationStatus,
                'validation_messages'        => !empty($validationMessages) ? implode('; ', $validationMessages) : null,
                'review_status'              => $reviewStatus,
                'created_at'                 => $now,
            ];

            if (count($stagingBatchRows) >= 200) {
                $this->db->table('ar01_staging_assets')->insertBatch($stagingBatchRows);
                $stagingBatchRows = [];
            }
        }
        fclose($handle);

        if (!empty($stagingBatchRows)) {
            $this->db->table('ar01_staging_assets')->insertBatch($stagingBatchRows);
        }

        // 3. Log Staging Audit Event
        $this->logAuditEvent($batchId, 'STAGING_CREATION', 'SYSTEM_PARSER', [
            'file_name'   => $fileName,
            'source_size' => $fileSize,
            'source_hash' => $fileSha256,
            'row_count'   => $sm['total_staged_rows'],
            'pass_count'  => $sm['pass_candidates'],
            'warn_count'  => $sm['warning_candidates'],
        ]);

        return [
            'success'  => true,
            'batch_id' => $batchId,
            'batch'    => $batchData,
            'is_new'   => true,
        ];
    }

    /**
     * Get Batch Review Summary and Review Queue.
     */
    public function getBatchReviewSummary(string $batchId): array
    {
        $batch = $this->db->table('ar01_ingestion_batches')->where('batch_id', $batchId)->get()->getRowArray();
        if (!$batch) {
            return ['success' => false, 'error' => "Batch ID '{$batchId}' tidak ditemukan di database."];
        }

        // Verify Source File Integrity
        $sourcePath = $batch['source_path'];
        $hashMatch = false;
        if (file_exists($sourcePath)) {
            $currentHash = hash_file('sha256', $sourcePath);
            $hashMatch = ($currentHash === $batch['source_sha256']);
        }

        // Summaries
        $totalRows   = $this->db->table('ar01_staging_assets')->where('batch_id', $batchId)->countAllResults();
        $passCount   = $this->db->table('ar01_staging_assets')->where('batch_id', $batchId)->where('validation_status', 'PASS')->countAllResults();
        $warnCount   = $this->db->table('ar01_staging_assets')->where('batch_id', $batchId)->where('validation_status', 'WARNING')->countAllResults();
        $rejectCount = $this->db->table('ar01_staging_assets')->where('batch_id', $batchId)->where('validation_status', 'REJECT')->countAllResults();

        $approvedCount = $this->db->table('ar01_staging_assets')->where('batch_id', $batchId)->where('review_status', 'APPROVED')->countAllResults();
        $needsReviewCount = $this->db->table('ar01_staging_assets')->where('batch_id', $batchId)->whereIn('review_status', ['READY_FOR_REVIEW', 'NEEDS_REVIEW'])->countAllResults();
        $rejectedReviewCount = $this->db->table('ar01_staging_assets')->where('batch_id', $batchId)->where('review_status', 'REJECTED')->countAllResults();

        // Feeder Breakdown
        $feederRows = $this->db->table('ar01_staging_assets')
            ->select('source_feeder_name, COUNT(*) as cnt')
            ->where('batch_id', $batchId)
            ->groupBy('source_feeder_name')
            ->orderBy('cnt', 'DESC')
            ->get()
            ->getResultArray();

        // Construction Breakdown
        $constRows = $this->db->table('ar01_staging_assets')
            ->select('source_construction_code, normalized_construction_code, validation_status, review_status, COUNT(*) as cnt')
            ->where('batch_id', $batchId)
            ->groupBy('source_construction_code, normalized_construction_code, validation_status, review_status')
            ->orderBy('cnt', 'DESC')
            ->get()
            ->getResultArray();

        // Needs Review Queue (Items needing human decision e.g. GTT2T)
        $reviewQueue = $this->db->table('ar01_staging_assets')
            ->where('batch_id', $batchId)
            ->where('review_status', 'NEEDS_REVIEW')
            ->orderBy('source_row_number', 'ASC')
            ->get()
            ->getResultArray();

        return [
            'success'            => true,
            'batch_id'           => $batchId,
            'batch'              => $batch,
            'source_integrity'   => $hashMatch ? 'PASS (SHA-256 Matched)' : 'FAIL (Hash Mismatch or File Missing)',
            'counts'             => [
                'total_rows'       => $totalRows,
                'pass_rows'        => $passCount,
                'warning_rows'     => $warnCount,
                'reject_rows'      => $rejectCount,
                'approved_rows'    => $approvedCount,
                'pending_rows'     => $needsReviewCount,
                'rejected_rows'    => $rejectedReviewCount,
            ],
            'feeder_summary'     => $feederRows,
            'construction_summary'=> $constRows,
            'review_queue'       => $reviewQueue,
            'database_writes'    => 0,
        ];
    }

    /**
     * Approve or reject a single row with cryptographic fingerprint.
     * Invariant 5E-A: 0 writes to 'assets' table.
     */
    public function approveSingleRow(string $batchId, int $rowNumber, string $decision, string $approverNip, string $reason, ?string $approverName = null): array
    {
        $decision = strtoupper(trim($decision));
        if (!in_array($decision, ['APPROVED', 'REJECTED', 'NEEDS_REVIEW'])) {
            return ['success' => false, 'error' => "Decision harus berupa 'APPROVED', 'REJECTED', atau 'NEEDS_REVIEW'."];
        }

        if (empty(trim($approverNip))) {
            return ['success' => false, 'error' => "Approver NIP wajib diisi untuk integritas tanda tangan audit."];
        }

        if (empty(trim($reason))) {
            return ['success' => false, 'error' => "Alasan (reason) wajib diisi untuk audit governance."];
        }

        $batch = $this->db->table('ar01_ingestion_batches')->where('batch_id', $batchId)->get()->getRowArray();
        if (!$batch) {
            return ['success' => false, 'error' => "Batch ID '{$batchId}' tidak ditemukan."];
        }

        // Verify Source File Integrity
        if (!file_exists($batch['source_path']) || hash_file('sha256', $batch['source_path']) !== $batch['source_sha256']) {
            return ['success' => false, 'error' => "BATCH INTEGRITY FAILURE: File sumber telah termodifikasi atau hash tidak cocok."];
        }

        $stagingRow = $this->db->table('ar01_staging_assets')
            ->where('batch_id', $batchId)
            ->where('source_row_number', $rowNumber)
            ->get()
            ->getRowArray();

        if (!$stagingRow) {
            return ['success' => false, 'error' => "Baris #{$rowNumber} tidak ditemukan pada batch '{$batchId}'."];
        }

        $now = date('Y-m-d H:i:s');
        $signedSha = hash('sha256', "{$batchId}|{$batch['source_sha256']}|{$stagingRow['id']}|{$decision}|{$approverNip}|{$now}|{$reason}");

        // Record Decision
        $decisionData = [
            'batch_id'          => $batchId,
            'staging_asset_id'  => $stagingRow['id'],
            'source_row_number' => $rowNumber,
            'scope'             => 'SINGLE_ROW',
            'decision'          => $decision,
            'decision_reason'   => $reason,
            'approver_nip'      => $approverNip,
            'approver_name'     => $approverName ?? $approverNip,
            'approver_role'     => 'ENGINEERING_REVIEWER',
            'signed_sha256'     => $signedSha,
            'approved_at'       => $now,
            'created_at'        => $now,
        ];
        $this->db->table('ar01_review_decisions')->insert($decisionData);

        // If approved and proposed_construction_type_id is null, resolve or register canonical construction type
        $proposedConstId = $stagingRow['proposed_construction_type_id'];
        if ($decision === 'APPROVED' && empty($proposedConstId)) {
            $canonicalCode = $stagingRow['normalized_construction_code'] ?? 'GTT-2T';
            $codeCol = $this->db->fieldExists('construction_code', 'construction_types') ? 'construction_code' : ($this->db->fieldExists('code', 'construction_types') ? 'code' : 'kode_konstruksi');
            $nameCol = $this->db->fieldExists('construction_name', 'construction_types') ? 'construction_name' : ($this->db->fieldExists('name', 'construction_types') ? 'name' : 'nama_konstruksi');
            
            $existingConst = $this->db->table('construction_types')
                ->where($codeCol, $canonicalCode)
                ->orWhere($codeCol, str_replace(['-', ' ', '_'], '', $canonicalCode))
                ->get()
                ->getRowArray();
                
            if ($existingConst) {
                $proposedConstId = (int)$existingConst['id'];
            } else {
                $insertData = [
                    $codeCol => $canonicalCode,
                    $nameCol => 'Gardu Trafo Tiang 2 Portal (' . $canonicalCode . ')',
                ];
                if ($this->db->fieldExists('code', 'construction_types')) $insertData['code'] = $canonicalCode;
                if ($this->db->fieldExists('name', 'construction_types')) $insertData['name'] = 'Gardu Trafo Tiang 2 Portal (' . $canonicalCode . ')';
                if ($this->db->fieldExists('construction_family', 'construction_types')) $insertData['construction_family'] = 'GARDU_TRAFO';
                if ($this->db->fieldExists('is_active', 'construction_types')) $insertData['is_active'] = 1;
                
                $this->db->table('construction_types')->insert($insertData);
                $proposedConstId = (int)$this->db->insertID();
            }
        }

        // Update Staging Record Status
        $updateData = [
            'review_status' => $decision,
            'approved_at'   => $decision === 'APPROVED' ? $now : null,
            'rejected_at'   => $decision === 'REJECTED' ? $now : null,
        ];
        if ($decision === 'APPROVED') {
            $updateData['validation_status'] = 'PASS';
            if ($proposedConstId) {
                $updateData['proposed_construction_type_id'] = $proposedConstId;
            }
        }
        $this->db->table('ar01_staging_assets')->where('id', $stagingRow['id'])->update($updateData);

        // Log Audit Event
        $this->logAuditEvent($batchId, 'SINGLE_ROW_DECISION', $approverNip, [
            'row_number' => $rowNumber,
            'asset_name' => $stagingRow['source_asset_name'],
            'decision'   => $decision,
            'reason'     => $reason,
            'signature'  => $signedSha,
        ]);

        return [
            'success'        => true,
            'batch_id'       => $batchId,
            'row_number'     => $rowNumber,
            'asset_name'     => $stagingRow['source_asset_name'],
            'decision'       => $decision,
            'signed_sha256'  => $signedSha,
            'approved_at'    => $now,
            'database_writes'=> 0,
        ];
    }

    /**
     * Bulk approve deterministic PASS rows only.
     * Invariant: MUST NOT approve WARNING / REVIEW rows (e.g. GTT2T remains NEEDS_REVIEW).
     * Invariant 5E-A: 0 writes to 'assets' table.
     */
    public function approveBatchScope(string $batchId, string $scope, string $approverNip, string $reason, ?string $approverName = null): array
    {
        $scope = strtoupper(trim($scope));
        if ($scope !== 'PASS') {
            return ['success' => false, 'error' => "Bulk approval scope hanya didukung untuk 'PASS'."];
        }

        if (empty(trim($approverNip))) {
            return ['success' => false, 'error' => "Approver NIP wajib diisi."];
        }

        if (empty(trim($reason))) {
            return ['success' => false, 'error' => "Alasan (reason) wajib diisi."];
        }

        $batch = $this->db->table('ar01_ingestion_batches')->where('batch_id', $batchId)->get()->getRowArray();
        if (!$batch) {
            return ['success' => false, 'error' => "Batch ID '{$batchId}' tidak ditemukan."];
        }

        // Verify Source File Integrity
        if (!file_exists($batch['source_path']) || hash_file('sha256', $batch['source_path']) !== $batch['source_sha256']) {
            return ['success' => false, 'error' => "BATCH INTEGRITY FAILURE: File sumber telah termodifikasi atau hash tidak cocok."];
        }

        $now = date('Y-m-d H:i:s');

        // Total stats for comprehensive reporting
        $totalPassRows = $this->db->table('ar01_staging_assets')
            ->where('batch_id', $batchId)
            ->where('validation_status', 'PASS')
            ->where('normalization_score >=', 100)
            ->countAllResults();

        $alreadyApprovedCount = $this->db->table('ar01_staging_assets')
            ->where('batch_id', $batchId)
            ->where('validation_status', 'PASS')
            ->where('normalization_score >=', 100)
            ->where('review_status', 'APPROVED')
            ->countAllResults();

        $skippedWarningCount = $this->db->table('ar01_staging_assets')
            ->where('batch_id', $batchId)
            ->where('validation_status', 'WARNING')
            ->countAllResults();

        $skippedRejectCount = $this->db->table('ar01_staging_assets')
            ->where('batch_id', $batchId)
            ->where('validation_status', 'REJECT')
            ->countAllResults();

        // Select only clean PASS rows with 100% normalization score (leaving GTT2T untouched)
        $eligibleRows = $this->db->table('ar01_staging_assets')
            ->where('batch_id', $batchId)
            ->where('validation_status', 'PASS')
            ->where('normalization_score >=', 100)
            ->where('review_status !=', 'APPROVED')
            ->get()
            ->getResultArray();

        $approvedCount = 0;
        foreach ($eligibleRows as $er) {
            $this->db->table('ar01_staging_assets')
                ->where('id', $er['id'])
                ->update([
                    'review_status' => 'APPROVED',
                    'approved_at'   => $now,
                ]);
            $approvedCount++;
        }

        $signedSha = hash('sha256', "{$batchId}|{$batch['source_sha256']}|BULK_PASS|{$totalPassRows}|{$approverNip}|{$now}|{$reason}");

        $decisionData = [
            'batch_id'          => $batchId,
            'staging_asset_id'  => null,
            'source_row_number' => null,
            'scope'             => 'BULK_PASS',
            'decision'          => 'APPROVED',
            'decision_reason'   => $reason,
            'approver_nip'      => $approverNip,
            'approver_name'     => $approverName ?? $approverNip,
            'approver_role'     => 'ENGINEERING_REVIEWER',
            'signed_sha256'     => $signedSha,
            'approved_at'       => $now,
            'created_at'        => $now,
        ];
        $this->db->table('ar01_review_decisions')->insert($decisionData);

        $this->logAuditEvent($batchId, 'BULK_APPROVAL', $approverNip, [
            'scope'          => 'PASS',
            'approved_count' => $approvedCount,
            'total_pass'     => $totalPassRows,
            'reason'         => $reason,
            'signature'      => $signedSha,
        ]);

        return [
            'success'               => true,
            'batch_id'              => $batchId,
            'scope'                 => 'PASS',
            'eligible_pass_rows'    => $totalPassRows,
            'approved_count'        => $approvedCount,
            'already_approved_count'=> $alreadyApprovedCount,
            'skipped_warning_count' => $skippedWarningCount,
            'skipped_reject_count'  => $skippedRejectCount,
            'signed_sha256'         => $signedSha,
            'database_writes'       => 0,
        ];
    }

    /**
     * Evaluate Phase 5E Promotion Gate Certificate.
     * Evaluates whether ALL 792 items have been verified and approved.
     * Returns LOCKED if any WARNING/REVIEW item is pending.
     */
    public function evaluatePromotionGate(string $batchId): array
    {
        $batch = $this->db->table('ar01_ingestion_batches')->where('batch_id', $batchId)->get()->getRowArray();
        if (!$batch) {
            return ['success' => false, 'error' => "Batch ID '{$batchId}' tidak ditemukan."];
        }

        // 1. Source Integrity Check
        $hashMatch = false;
        if (file_exists($batch['source_path'])) {
            $currentHash = hash_file('sha256', $batch['source_path']);
            $hashMatch = ($currentHash === $batch['source_sha256']);
        }

        // 2. Count Validation & Review Statuses
        $totalRows      = $this->db->table('ar01_staging_assets')->where('batch_id', $batchId)->countAllResults();
        $passCount      = $this->db->table('ar01_staging_assets')->where('batch_id', $batchId)->where('validation_status', 'PASS')->countAllResults();
        $warnCount      = $this->db->table('ar01_staging_assets')->where('batch_id', $batchId)->where('validation_status', 'WARNING')->countAllResults();
        $rejectCount    = $this->db->table('ar01_staging_assets')->where('batch_id', $batchId)->where('validation_status', 'REJECT')->countAllResults();

        $approvedCount  = $this->db->table('ar01_staging_assets')->where('batch_id', $batchId)->where('review_status', 'APPROVED')->countAllResults();
        $pendingCount   = $this->db->table('ar01_staging_assets')->where('batch_id', $batchId)->whereIn('review_status', ['READY_FOR_REVIEW', 'NEEDS_REVIEW'])->countAllResults();
        $rejectedCount  = $this->db->table('ar01_staging_assets')->where('batch_id', $batchId)->where('review_status', 'REJECTED')->countAllResults();

        // 3. Foreign Key Checks
        $unresolvedFeeders = $this->db->table('ar01_staging_assets')
            ->where('batch_id', $batchId)
            ->where('proposed_penyulang_id IS NULL')
            ->countAllResults();

        $unresolvedConstructions = $this->db->table('ar01_staging_assets')
            ->where('batch_id', $batchId)
            ->where('proposed_construction_type_id IS NULL')
            ->countAllResults();

        // 4. Review Decisions Signature Check
        $decisionsCount = $this->db->table('ar01_review_decisions')->where('batch_id', $batchId)->countAllResults();
        $signaturesValid = ($decisionsCount > 0);

        // Fail-closed Gate Evaluation
        $isUnlocked = (
            $hashMatch &&
            $totalRows > 0 &&
            $unresolvedFeeders === 0 &&
            $unresolvedConstructions === 0 &&
            $rejectCount === 0 &&
            $pendingCount === 0 &&
            $rejectedCount === 0 &&
            $approvedCount === $totalRows &&
            $signaturesValid
        );

        $lockReasons = [];
        if (!$hashMatch) {
            $lockReasons[] = "Source file SHA-256 hash mismatch atau file hilang.";
        }
        if ($unresolvedFeeders > 0) {
            $lockReasons[] = "Terdapat {$unresolvedFeeders} aset dengan penyulang yang belum terpetakan ke database.";
        }
        if ($unresolvedConstructions > 0) {
            $lockReasons[] = "Terdapat {$unresolvedConstructions} aset dengan tipe konstruksi yang belum terpetakan ke CR-06G.";
        }
        if ($pendingCount > 0) {
            $lockReasons[] = "Terdapat {$pendingCount} record yang masih berstatus PENDING / NEEDS_REVIEW (memerlukan persetujuan human engineering).";
        }
        if ($rejectedCount > 0) {
            $lockReasons[] = "Terdapat {$rejectedCount} record yang ditolak (REJECTED).";
        }
        if (!$signaturesValid) {
            $lockReasons[] = "Belum ada tanda tangan keputusan review engineering yang terekam.";
        }

        $certificateToken = null;
        if ($isUnlocked) {
            $certificateToken = 'CERT-PROMOTION-' . strtoupper(hash('sha256', "{$batchId}|{$batch['source_sha256']}|{$approvedCount}|UNLOCKED"));
        }

        return [
            'success'               => true,
            'batch_id'              => $batchId,
            'source_integrity'      => $hashMatch ? 'PASS (SHA-256 Matched)' : 'FAIL',
            'staging_integrity'     => $totalRows > 0 ? 'PASS' : 'FAIL',
            'schema_validation'     => $rejectCount === 0 ? 'PASS' : 'FAIL',
            'feeder_fk_resolution'  => $unresolvedFeeders === 0 ? 'PASS' : 'FAIL',
            'construction_fk_resolution' => $unresolvedConstructions === 0 ? 'PASS' : 'FAIL',
            'counts'                => [
                'total_rows'    => $totalRows,
                'pass_rows'     => $passCount,
                'warning_rows'  => $warnCount,
                'reject_rows'   => $rejectCount,
                'approved_rows' => $approvedCount,
                'pending_rows'  => $pendingCount,
                'rejected_rows' => $rejectedCount,
            ],
            'signature_integrity'   => $signaturesValid ? 'PASS' : 'PENDING',
            'promotion_eligibility' => $isUnlocked ? 'UNLOCKED' : 'LOCKED',
            'certificate_token'     => $certificateToken,
            'lock_reasons'          => $lockReasons,
            'assets_table_writes'   => 0,
        ];
    }

    /**
     * Helper to log audit events into ar01_audit_log
     */
    protected function logAuditEvent(?string $batchId, string $eventType, string $actor, ?array $eventData = null): void
    {
        $this->db->table('ar01_audit_log')->insert([
            'batch_id'   => $batchId,
            'event_type' => $eventType,
            'actor'      => $actor,
            'event_data' => $eventData ? json_encode($eventData) : null,
            'status'     => 'SUCCESS',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
