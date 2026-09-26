<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;
use RuntimeException;
use InvalidArgumentException;

/**
 * SIDAK TEJO — Phase B.7.3: Chunked Evidence Upload & SHA-256 Checksum Engine
 *
 * ARCHITECTURAL CONTRACT & HARD GUARDS:
 * - B7-G08:  Evidence Pre-Hashing & Integrity (SHA-256 verified on client, chunks, & assembled file)
 * - B7-G10:  Comprehensive Journaling & Audit
 * - B7-G11:  Registered Device Governance (Revoked device cannot upload or complete seal)
 * - B7-G14:  Topology Read-Only (gis_translines = 0, assets = 0, Delta = 0)
 * - B7-G18:  Evidence Chunk State Machine (CHUNK_INIT -> UPLOADING -> ALL_CHUNKS_RECEIVED -> ASSEMBLING -> HASH_VERIFYING -> SEALED | HASH_MISMATCH)
 * - B7-G19:  Device Revocation Non-Cascade
 * - Invariant: mobile_evidence_chunks is strictly transport/staging; field_evidence is authoritative domain truth.
 */
class MobileEvidenceUploadService
{
    public const SERVICE_VERSION = 'B7-EVIDENCE-1.0';

    public const STATUS_INIT           = 'CHUNK_INIT';
    public const STATUS_UPLOADING      = 'UPLOADING';
    public const STATUS_ALL_RECEIVED   = 'ALL_CHUNKS_RECEIVED';
    public const STATUS_ASSEMBLING     = 'ASSEMBLING';
    public const STATUS_HASH_VERIFYING = 'HASH_VERIFYING';
    public const STATUS_SEALED         = 'SEALED';
    public const STATUS_HASH_MISMATCH  = 'HASH_MISMATCH';
    public const STATUS_ABORTED        = 'ABORTED';

    protected BaseConnection $db;
    protected FieldFindingsService $findingsService;
    protected FaultCaseService $caseService;
    protected string $stagingBaseDir;
    protected string $permanentBaseDir;

    public function __construct(
        ?BaseConnection $db = null,
        ?FieldFindingsService $findingsService = null,
        ?FaultCaseService $caseService = null,
        ?string $stagingBaseDir = null,
        ?string $permanentBaseDir = null
    ) {
        $this->db = $db ?? Database::connect();
        $this->caseService = $caseService ?? new FaultCaseService($this->db);
        $this->findingsService = $findingsService ?? new FieldFindingsService($this->db, $this->caseService);

        $writePath = defined('WRITEPATH') ? WRITEPATH : sys_get_temp_dir() . DIRECTORY_SEPARATOR;
        $this->stagingBaseDir = $stagingBaseDir ?? ($writePath . 'uploads' . DIRECTORY_SEPARATOR . 'evidence_staging' . DIRECTORY_SEPARATOR);
        $this->permanentBaseDir = $permanentBaseDir ?? ($writePath . 'uploads' . DIRECTORY_SEPARATOR . 'field_evidence' . DIRECTORY_SEPARATOR);

        if (!is_dir($this->stagingBaseDir)) {
            @mkdir($this->stagingBaseDir, 0755, true);
        }
        if (!is_dir($this->permanentBaseDir)) {
            @mkdir($this->permanentBaseDir, 0755, true);
        }
    }

    public function getFindingsService(): FieldFindingsService
    {
        return $this->findingsService;
    }

    public function getCaseService(): FaultCaseService
    {
        return $this->caseService;
    }

    // =========================================================================
    // 1. INITIATE UPLOAD (B7-G18: CHUNK_INIT)
    // =========================================================================

    /**
     * Initiate a multi-chunk evidence upload session.
     * Enforces device authentication and syntactic bounds.
     */
    public function initiateUpload(
        string $evidenceId,
        string $uuid,
        int $caseId,
        int $totalChunks,
        int $totalBytes,
        string $fullFileSha256,
        string $deviceId,
        int $userId,
        array $options = []
    ): array {
        // 1. Path Traversal & Evidence ID Format Defense
        if (!$this->isValidEvidenceId($evidenceId)) {
            return [
                'success'   => false,
                'status'    => 'INVALID_EVIDENCE_ID',
                'http_code' => 400,
                'message'   => 'evidence_id contains illegal characters or path traversal sequences.',
            ];
        }

        // 2. Validate Device Governance (B7-G11, B7-G19)
        $devCheck = $this->verifyActiveDevice($deviceId);
        if (!$devCheck['valid']) {
            return [
                'success'   => false,
                'status'    => $devCheck['status'],
                'http_code' => 403,
                'message'   => $devCheck['message'],
            ];
        }

        // 3. Validate Case Exists and Not Terminal
        $case = $this->caseService->getCase($caseId);
        if (!$case) {
            return [
                'success'   => false,
                'status'    => 'CASE_NOT_FOUND',
                'http_code' => 404,
                'message'   => "Fault case #{$caseId} does not exist.",
            ];
        }

        if (in_array(strtoupper($case['status']), FaultCaseService::TERMINAL_STATUSES, true)) {
            return [
                'success'   => false,
                'status'    => 'CASE_TERMINAL_STATE',
                'http_code' => 422,
                'message'   => "Cannot upload evidence for case #{$caseId} in terminal state '{$case['status']}'.",
            ];
        }

        // 4. Validate Dimensions and Checksums
        if ($totalChunks <= 0 || $totalBytes <= 0) {
            return [
                'success'   => false,
                'status'    => 'INVALID_UPLOAD_DIMENSIONS',
                'http_code' => 422,
                'message'   => 'total_chunks and total_bytes must be strictly positive integers.',
            ];
        }

        $fullFileSha256 = strtolower(trim($fullFileSha256));
        if (!preg_match('/^[a-f0-9]{64}$/', $fullFileSha256)) {
            return [
                'success'   => false,
                'status'    => 'INVALID_SHA256_FORMAT',
                'http_code' => 422,
                'message'   => 'full_file_sha256 must be a valid 64-character lowercase hex string.',
            ];
        }

        // 5. Check if Evidence is Already Sealed in Authoritative Domain (Idempotency)
        $existingEvidence = $this->db->table('field_evidence')
            ->where('fault_case_id', $caseId)
            ->where('sha256', $fullFileSha256)
            ->get()
            ->getRowArray();

        if ($existingEvidence) {
            return [
                'success'        => true,
                'status'         => self::STATUS_SEALED,
                'http_code'      => 200,
                'is_existing'    => true,
                'evidence_id'    => $evidenceId,
                'field_evidence' => $existingEvidence,
                'message'        => 'Evidence is already sealed and permanently attached in domain.',
            ];
        }

        // 6. Ensure Staging Directory Exists
        $stagingDir = $this->getStagingDir($evidenceId);
        if (!is_dir($stagingDir)) {
            @mkdir($stagingDir, 0755, true);
        }

        return [
            'success'          => true,
            'status'           => self::STATUS_INIT,
            'http_code'        => 200,
            'evidence_id'      => $evidenceId,
            'case_id'          => $caseId,
            'total_chunks'     => $totalChunks,
            'total_bytes'      => $totalBytes,
            'full_file_sha256' => $fullFileSha256,
            'message'          => 'Upload session initialized. Ready for chunk streaming.',
        ];
    }

    // =========================================================================
    // 2. CHUNK UPLOAD & DEDUPLICATION (B7-G18: UPLOADING)
    // =========================================================================

    /**
     * Upload an individual chunk binary.
     * Supports out-of-order transmission, verifies chunk size & hash,
     * and guarantees chunk idempotency.
     */
    public function uploadChunk(
        string $evidenceId,
        int $chunkIndex,
        int $totalChunks,
        string $chunkDataBinary,
        string $expectedChunkSha256,
        int $expectedChunkSize,
        string $fullFileSha256,
        string $uuid,
        string $deviceId,
        int $userId
    ): array {
        // Path Traversal & Evidence ID Check
        if (!$this->isValidEvidenceId($evidenceId)) {
            return [
                'success'   => false,
                'status'    => 'INVALID_EVIDENCE_ID',
                'http_code' => 400,
                'message'   => 'evidence_id contains illegal characters.',
            ];
        }

        // Validate Device Governance
        $devCheck = $this->verifyActiveDevice($deviceId);
        if (!$devCheck['valid']) {
            return [
                'success'   => false,
                'status'    => $devCheck['status'],
                'http_code' => 403,
                'message'   => $devCheck['message'],
            ];
        }

        // Index Bounds Check
        if ($chunkIndex < 0 || $chunkIndex >= $totalChunks || $totalChunks <= 0) {
            return [
                'success'   => false,
                'status'    => 'INVALID_CHUNK_INDEX',
                'http_code' => 422,
                'message'   => "chunk_index {$chunkIndex} is out of bounds for total_chunks {$totalChunks}.",
            ];
        }

        // Chunk Size Verification (Never trust Content-Length alone)
        $actualBytes = strlen($chunkDataBinary);
        if ($actualBytes !== $expectedChunkSize) {
            return [
                'success'   => false,
                'status'    => 'CHUNK_SIZE_MISMATCH',
                'http_code' => 422,
                'message'   => "Declared chunk size ({$expectedChunkSize} bytes) does not match received bytes ({$actualBytes} bytes).",
            ];
        }

        // Chunk Hash Verification
        $actualChunkSha256 = hash('sha256', $chunkDataBinary);
        $expectedChunkSha256 = strtolower(trim($expectedChunkSha256));
        if ($actualChunkSha256 !== $expectedChunkSha256) {
            return [
                'success'   => false,
                'status'    => 'CHUNK_HASH_MISMATCH',
                'http_code' => 422,
                'message'   => "Calculated chunk hash ({$actualChunkSha256}) does not match declared hash ({$expectedChunkSha256}).",
            ];
        }

        $fullFileSha256 = strtolower(trim($fullFileSha256));

        // ---------------------------------------------------------------------
        // Idempotency & Conflict Check on Composite Key (evidence_id, chunk_index)
        // ---------------------------------------------------------------------
        $existingChunk = $this->db->table('mobile_evidence_chunks')
            ->where('evidence_id', $evidenceId)
            ->where('chunk_index', $chunkIndex)
            ->get()
            ->getRowArray();

        $now = date('Y-m-d H:i:s');
        $stagingDir = $this->getStagingDir($evidenceId);
        if (!is_dir($stagingDir)) {
            @mkdir($stagingDir, 0755, true);
        }
        $chunkFilePath = $stagingDir . DIRECTORY_SEPARATOR . sprintf('part_%05d.chunk', $chunkIndex);

        if ($existingChunk) {
            // Check if identical hash
            if ($existingChunk['chunk_sha256'] === $actualChunkSha256) {
                // Ensure file exists on disk if previously stored
                if (!file_exists($chunkFilePath)) {
                    file_put_contents($chunkFilePath, $chunkDataBinary);
                }
                return [
                    'success'         => true,
                    'status'          => 'EXISTING',
                    'http_code'       => 200,
                    'evidence_id'     => $evidenceId,
                    'chunk_index'     => $chunkIndex,
                    'received_chunks' => $this->countReceivedChunks($evidenceId),
                    'total_chunks'    => $totalChunks,
                    'message'         => "Chunk #{$chunkIndex} already uploaded with identical hash. Deduplicated (B7-G18).",
                ];
            }

            // Conflicting hash for same chunk index
            return [
                'success'   => false,
                'status'    => 'CHUNK_CONFLICT',
                'http_code' => 409,
                'message'   => "Chunk #{$chunkIndex} already exists with a different hash. Chunk conflict rejected (Guard B7-G18).",
            ];
        }

        // Store chunk file to staging
        file_put_contents($chunkFilePath, $chunkDataBinary);

        // Record in mobile_evidence_chunks
        $this->db->table('mobile_evidence_chunks')->insert([
            'evidence_id'            => $evidenceId,
            'client_submission_uuid' => $uuid,
            'chunk_index'            => $chunkIndex,
            'total_chunks'           => $totalChunks,
            'expected_chunk_size'    => $expectedChunkSize,
            'actual_chunk_size'      => $actualBytes,
            'chunk_sha256'           => $actualChunkSha256,
            'full_file_sha256'       => $fullFileSha256,
            'temp_file_path'         => $chunkFilePath,
            'status'                 => 'CHUNK_RECEIVED',
            'received_at'            => $now,
            'created_at'             => $now,
        ]);

        $receivedCount = $this->countReceivedChunks($evidenceId);
        $lifecycleStatus = ($receivedCount === $totalChunks) ? self::STATUS_ALL_RECEIVED : self::STATUS_UPLOADING;

        return [
            'success'         => true,
            'status'          => $lifecycleStatus,
            'http_code'       => 200,
            'evidence_id'     => $evidenceId,
            'chunk_index'     => $chunkIndex,
            'received_chunks' => $receivedCount,
            'total_chunks'    => $totalChunks,
            'message'         => ($lifecycleStatus === self::STATUS_ALL_RECEIVED)
                ? 'All chunks received. Ready for assembly and SHA-256 seal.'
                : "Chunk #{$chunkIndex} accepted.",
        ];
    }

    // =========================================================================
    // 3. ASSEMBLE, VERIFY SHA-256 & SEAL (B7-G08, B7-G18: SEALED | HASH_MISMATCH)
    // =========================================================================

    /**
     * Assemble all staged chunks, recalculate end-to-end full-file SHA-256 checksum,
     * and seal the evidence into authoritative B.6 domain (field_evidence).
     */
    public function assembleAndSealEvidence(
        string $evidenceId,
        int $caseId,
        int $userId,
        string $deviceId,
        array $options = []
    ): array {
        // Path Traversal Defense
        if (!$this->isValidEvidenceId($evidenceId)) {
            return [
                'success'   => false,
                'status'    => 'INVALID_EVIDENCE_ID',
                'http_code' => 400,
                'message'   => 'evidence_id contains illegal characters.',
            ];
        }

        // Validate Device Governance (B7-G19)
        $devCheck = $this->verifyActiveDevice($deviceId);
        if (!$devCheck['valid']) {
            return [
                'success'   => false,
                'status'    => $devCheck['status'],
                'http_code' => 403,
                'message'   => $devCheck['message'],
            ];
        }

        // Validate Case Exists
        $case = $this->caseService->getCase($caseId);
        if (!$case) {
            return [
                'success'   => false,
                'status'    => 'CASE_NOT_FOUND',
                'http_code' => 404,
                'message'   => "Fault case #{$caseId} does not exist.",
            ];
        }

        // Validate Field Finding if provided
        $findingId = isset($options['field_finding_id']) ? (int)$options['field_finding_id'] : null;
        if ($findingId !== null && $findingId > 0) {
            $finding = $this->db->table('field_findings')->where('id', $findingId)->get()->getRowArray();
            if (!$finding) {
                return [
                    'success'   => false,
                    'status'    => 'FINDING_NOT_FOUND',
                    'http_code' => 404,
                    'message'   => "Field finding #{$findingId} does not exist.",
                ];
            }
            if ((int)$finding['fault_case_id'] !== $caseId) {
                return [
                    'success'   => false,
                    'status'    => 'FINDING_CASE_MISMATCH',
                    'http_code' => 422,
                    'message'   => "Finding #{$findingId} belongs to Case #{$finding['fault_case_id']}, not Case #{$caseId}.",
                ];
            }
        }

        // Retrieve Chunks ordered by chunk_index
        $chunks = $this->db->table('mobile_evidence_chunks')
            ->where('evidence_id', $evidenceId)
            ->orderBy('chunk_index', 'ASC')
            ->get()
            ->getResultArray();

        if (empty($chunks)) {
            return [
                'success'   => false,
                'status'    => 'NO_CHUNKS_FOUND',
                'http_code' => 422,
                'message'   => "No chunks found for evidence '{$evidenceId}'.",
            ];
        }

        $totalChunks = (int)$chunks[0]['total_chunks'];
        $targetSha256 = strtolower($chunks[0]['full_file_sha256']);

        // Check if already sealed in authoritative domain (Idempotency)
        $existingDomainEvidence = $this->db->table('field_evidence')
            ->where('fault_case_id', $caseId)
            ->where('sha256', $targetSha256)
            ->get()
            ->getRowArray();

        if ($existingDomainEvidence) {
            return [
                'success'           => true,
                'status'            => self::STATUS_SEALED,
                'http_code'         => 200,
                'is_existing'       => true,
                'evidence_id'       => $evidenceId,
                'field_evidence_id' => (int)$existingDomainEvidence['id'],
                'sha256'            => $targetSha256,
                'message'           => 'Evidence was already sealed and registered in authoritative domain.',
            ];
        }

        // Check Completeness: all chunk_index from 0 to (totalChunks - 1) must exist
        if (count($chunks) < $totalChunks) {
            $receivedIndices = array_column($chunks, 'chunk_index');
            $missing = [];
            for ($i = 0; $i < $totalChunks; $i++) {
                if (!in_array($i, $receivedIndices)) {
                    $missing[] = $i;
                }
            }
            return [
                'success'        => false,
                'status'         => 'CHUNKS_INCOMPLETE',
                'http_code'      => 422,
                'received_count' => count($chunks),
                'total_chunks'   => $totalChunks,
                'missing_chunks' => $missing,
                'message'        => 'Cannot seal evidence: not all chunks have been received (Guard B7-G18).',
            ];
        }

        // ---------------------------------------------------------------------
        // Assemble Chunks into Single Binary (ASSEMBLING)
        // ---------------------------------------------------------------------
        $stagingDir = $this->getStagingDir($evidenceId);
        $assembledPath = $stagingDir . DIRECTORY_SEPARATOR . 'assembled_' . $evidenceId . '.tmp';

        $outHandle = fopen($assembledPath, 'wb');
        if ($outHandle === false) {
            return [
                'success'   => false,
                'status'    => 'ASSEMBLY_IO_ERROR',
                'http_code' => 500,
                'message'   => 'Failed to initialize assembly staging file.',
            ];
        }

        for ($i = 0; $i < $totalChunks; $i++) {
            $partFile = $stagingDir . DIRECTORY_SEPARATOR . sprintf('part_%05d.chunk', $i);
            if (!file_exists($partFile)) {
                fclose($outHandle);
                @unlink($assembledPath);
                return [
                    'success'   => false,
                    'status'    => 'CHUNK_FILE_MISSING_ON_DISK',
                    'http_code' => 500,
                    'message'   => "Chunk part #{$i} file is missing on storage disk.",
                ];
            }
            $partData = file_get_contents($partFile);
            fwrite($outHandle, $partData);
        }
        fclose($outHandle);

        // ---------------------------------------------------------------------
        // Checksum Verification (HASH_VERIFYING)
        // ---------------------------------------------------------------------
        $actualAssembledSha256 = hash_file('sha256', $assembledPath);

        if ($actualAssembledSha256 !== $targetSha256) {
            // Purge corrupted assembly
            @unlink($assembledPath);
            $this->purgeStagingDir($evidenceId);

            return [
                'success'        => false,
                'status'         => self::STATUS_HASH_MISMATCH,
                'http_code'      => 422,
                'expected_sha256' => $targetSha256,
                'actual_sha256'  => $actualAssembledSha256,
                'message'        => "Full-file checksum mismatch! Target ({$targetSha256}) !== Assembled ({$actualAssembledSha256}). Assembly aborted and purged (Guard B7-G08, B7-G18).",
            ];
        }

        // ---------------------------------------------------------------------
        // Checksum Exact Match: Move to Permanent Evidence Storage & Seal in B.6
        // ---------------------------------------------------------------------
        $subfolder = date('Ym');
        $permanentDir = $this->permanentBaseDir . $subfolder . DIRECTORY_SEPARATOR;
        if (!is_dir($permanentDir)) {
            @mkdir($permanentDir, 0755, true);
        }

        $permanentFileName = 'EV-' . $evidenceId . '-' . substr($targetSha256, 0, 12) . '.jpg';
        $permanentPath = $permanentDir . $permanentFileName;

        rename($assembledPath, $permanentPath);
        $this->purgeStagingDir($evidenceId);

        // Forward E2E Correlation ID into metadata for forensic traceability
        $optionsMetadata = $options['metadata'] ?? [];
        if (!empty($options['correlation_id'])) {
            $optionsMetadata['correlation_id'] = $options['correlation_id'];
        }
        if (!empty($options['client_submission_uuid'])) {
            $optionsMetadata['client_submission_uuid'] = $options['client_submission_uuid'];
        }
        $options['metadata'] = $optionsMetadata;

        // Call B.6 Authoritative Domain Service: attachEvidence (Guard B6-G10)
        $evidenceType = $options['evidence_type'] ?? 'PHOTO';
        $domainRes = $this->findingsService->attachEvidence(
            $caseId,
            $userId,
            $permanentPath,
            $targetSha256,
            $evidenceType,
            $options
        );

        if (!$domainRes['success']) {
            @unlink($permanentPath);
            return [
                'success'   => false,
                'status'    => $domainRes['status'] ?? 'DOMAIN_ATTACH_FAILED',
                'http_code' => 422,
                'message'   => $domainRes['message'] ?? 'Failed to attach evidence in domain.',
            ];
        }

        $fieldEvidenceId = (int)($domainRes['evidence']['id'] ?? $domainRes['id'] ?? 0);
        $correlationId = $options['correlation_id'] ?? $options['client_submission_uuid'] ?? ('EVID-' . $evidenceId);
        $syncId = $options['sync_id'] ?? ('SYNC-EVID-' . substr($evidenceId, 0, 8));
        $now = date('Y-m-d H:i:s');
        $journalSeq = null;

        // Record in mobile_sync_journal to bind full E2E Correlation ID chain
        if ($this->db->tableExists('mobile_sync_journal')) {
            $existingJournal = $this->db->table('mobile_sync_journal')
                ->where('client_submission_uuid', $correlationId)
                ->get()
                ->getRowArray();

            if (!$existingJournal) {
                $this->db->table('mobile_sync_journal')->insert([
                    'client_submission_uuid' => $correlationId,
                    'sync_id'                => $syncId,
                    'device_id'              => $deviceId,
                    'user_id'                => $userId,
                    'operation_type'         => 'EVIDENCE',
                    'entity_type'            => 'field_evidence',
                    'entity_id'              => $fieldEvidenceId,
                    'payload_sha256'         => $targetSha256,
                    'payload_json'           => json_encode([
                        'evidence_id'  => $evidenceId,
                        'sha256'       => $targetSha256,
                        'file_path'    => $permanentPath,
                        'options'      => $options,
                    ]),
                    'client_created_at'      => $options['captured_at'] ?? $now,
                    'client_timezone_offset' => '+07:00',
                    'server_received_at'     => $now,
                    'server_processed_at'    => $now,
                    'attempt_no'             => 1,
                    'sync_status'            => 'ACCEPTED',
                    'response_http_code'     => 200,
                    'domain_status'          => 'SEALED',
                    'created_at'             => $now,
                ]);
                $journalSeq = (int)$this->db->insertID();
            } else {
                $journalSeq = (int)$existingJournal['journal_seq'];
            }
        }

        return [
            'success'           => true,
            'status'            => self::STATUS_SEALED,
            'http_code'         => 200,
            'evidence_id'       => $evidenceId,
            'case_id'           => $caseId,
            'field_evidence_id' => $fieldEvidenceId,
            'correlation_id'    => $correlationId,
            'journal_seq'       => $journalSeq,
            'sha256'            => $targetSha256,
            'stored_path'       => $permanentPath,
            'message'           => 'Evidence assembled, verified against client SHA-256, and sealed in domain (B7-G08, B7-G18 PASS).',
        ];
    }

    // =========================================================================
    // 4. HELPER UTILITIES
    // =========================================================================

    public function isValidEvidenceId(string $evidenceId): bool
    {
        // 8 to 64 alphanumeric characters, underscores, and hyphens (NO directory separators or null bytes)
        return (bool)preg_match('/^[a-zA-Z0-9_-]{8,64}$/', $evidenceId);
    }

    public function getStagingDir(string $evidenceId): string
    {
        return $this->stagingBaseDir . $evidenceId;
    }

    public function purgeStagingDir(string $evidenceId): void
    {
        $dir = $this->getStagingDir($evidenceId);
        if (is_dir($dir)) {
            $files = glob($dir . '/*');
            if ($files) {
                foreach ($files as $f) {
                    if (is_file($f)) {
                        @unlink($f);
                    }
                }
            }
            @rmdir($dir);
        }
    }

    public function countReceivedChunks(string $evidenceId): int
    {
        return (int)$this->db->table('mobile_evidence_chunks')
            ->where('evidence_id', $evidenceId)
            ->countAllResults();
    }

    protected function verifyActiveDevice(string $deviceId): array
    {
        $device = $this->db->table('mobile_devices')->where('device_id', $deviceId)->get()->getRowArray();
        if (!$device) {
            return [
                'valid'   => false,
                'status'  => 'DEVICE_NOT_REGISTERED',
                'message' => "Device '{$deviceId}' is not registered.",
            ];
        }

        if (strtoupper($device['status']) === 'REVOKED') {
            return [
                'valid'   => false,
                'status'  => 'DEVICE_REVOKED',
                'message' => "Device '{$deviceId}' is revoked (Guard B7-G19).",
            ];
        }

        if (strtoupper($device['status']) !== 'ACTIVE') {
            return [
                'valid'   => false,
                'status'  => 'DEVICE_SUSPENDED',
                'message' => "Device '{$deviceId}' is suspended.",
            ];
        }

        return ['valid' => true];
    }
}
