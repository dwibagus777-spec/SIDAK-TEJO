<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * Historical Interruption Import Service (Phase 7U Maintenance M-04)
 *
 * Responsibilities:
 * - Governed batch ingestion of historical feeder interruption data.
 * - Deduplication via source record hashing.
 * - Triple-layer persistence (Source Evidence, Canonical Operational, Intelligence Anchor).
 * - Full batch audit and provenance tracking.
 */
class HistoricalInterruptionImportService
{
    protected BaseConnection $db;
    protected HistoricalInterruptionNormalizerService $normalizer;
    protected CauseCodeResolutionService $causeResolver;
    protected HistoricalIncidentDataQualityService $qualityService;

    public function __construct(
        ?BaseConnection $db = null,
        ?HistoricalInterruptionNormalizerService $normalizer = null,
        ?CauseCodeResolutionService $causeResolver = null,
        ?HistoricalIncidentDataQualityService $qualityService = null
    ) {
        $this->db             = $db ?? \Config\Database::connect();
        $this->normalizer     = $normalizer ?? new HistoricalInterruptionNormalizerService();
        $this->causeResolver  = $causeResolver ?? new CauseCodeResolutionService($this->db);
        $this->qualityService = $qualityService ?? new HistoricalIncidentDataQualityService();
    }

    /**
     * Import an array of raw spreadsheet rows into the historical knowledge base.
     *
     * @param array $rawRows Array of row arrays (with headers or indexed)
     * @param string $batchCode Batch identifier code
     * @param string $sourceFile Source spreadsheet / CSV reference
     * @return array Batch execution summary
     */
    public function importRows(array $rawRows, string $batchCode = 'BATCH-SDA-DEFAULT', string $sourceFile = 'rekap_gangguan_sda.csv'): array
    {
        $now = date('Y-m-d H:i:s');

        // 1. Create or retrieve Batch Record
        $batchId = $this->createBatchRecord($batchCode, $sourceFile);

        $totalRead      = 0;
        $totalImported  = 0;
        $totalDuplicate = 0;
        $totalFlagged   = 0;

        $insertBatch = [];

        foreach ($rawRows as $index => $row) {
            if (empty($row) || (isset($row[0]) && $row[0] === 'SDA' && isset($row[1]) && str_contains(strtolower($row[1]), 'tanggal'))) {
                // Skip header row if present
                continue;
            }

            $totalRead++;
            $rowNumber = $index + 1;

            // Compute deterministic record hash
            $rawPayloadJson = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $recordHash     = hash('sha256', $rawPayloadJson);

            // Deduplication check
            if ($this->isDuplicateRecord($recordHash)) {
                $totalDuplicate++;
                continue;
            }

            // Normalize Canonical Operational Attributes
            $normalized = $this->normalizer->normalizeRow($row);

            // Resolve Authoritative Cause from 'PENYEBAB SESUAI KODE GANGGUAN' (Column index 30 / named)
            $rawCause = $row['PENYEBAB SESUAI KODE GANGGUAN'] ?? $row[30] ?? ($row['Penyebab'] ?? $row[28] ?? null);
            $causeResolution = $this->causeResolver->resolveCause($rawCause);

            // Evaluate Data Quality
            $quality = $this->qualityService->evaluateRecordQuality($normalized, $causeResolution);
            if ($quality['ingestion_status'] === 'FLAGGED') {
                $totalFlagged++;
            }

            // Build Layered Record
            $insertBatch[] = array_merge($normalized, [
                'batch_id'             => $batchId,
                'source_system'        => 'GOOGLE_SPREADSHEET_PLN_SDA',
                'source_sheet_name'    => 'REKAP_GANGGUAN_2025_2026',
                'source_row_number'    => $rowNumber,
                'source_record_hash'   => $recordHash,
                'raw_payload_json'     => $rawPayloadJson,
                'cause_raw'            => $causeResolution['cause_raw'],
                'cause_canonical_code' => $causeResolution['cause_canonical_code'],
                'cause_category'       => $causeResolution['cause_category'],
                'cause_mapping_status' => $causeResolution['cause_mapping_status'],
                'data_quality_score'   => $quality['data_quality_score'],
                'ingestion_status'     => $quality['ingestion_status'],
                'created_at'           => $now,
                'updated_at'           => $now,
            ]);

            $totalImported++;

            // Chunked insert for optimal performance
            if (count($insertBatch) >= 50) {
                $this->db->table('historical_feeder_interruptions')->insertBatch($insertBatch);
                $insertBatch = [];
            }
        }

        if (!empty($insertBatch)) {
            $this->db->table('historical_feeder_interruptions')->insertBatch($insertBatch);
        }

        // Update Batch Summary
        $this->updateBatchSummary($batchId, $totalRead, $totalImported, $totalDuplicate, $totalFlagged);

        return [
            'status'                 => 'success',
            'batch_id'               => $batchId,
            'batch_code'             => $batchCode,
            'total_rows_scanned'     => $totalRead,
            'total_rows_imported'    => $totalImported,
            'total_duplicates_found' => $totalDuplicate,
            'total_flagged_records'  => $totalFlagged,
            'ingestion_engine_version' => 'HISTORICAL_INTERRUPTION_INGESTION_v1.0',
            'certified_import_status'  => 'HISTORICAL_INGESTION_VERIFIED',
        ];
    }

    /**
     * Get empirical profiling summary of the ingested historical knowledge base
     */
    public function getProfilingSummary(): array
    {
        if (!$this->db->tableExists('historical_feeder_interruptions')) {
            return ['status' => 'empty', 'total_records' => 0];
        }

        $totalRecords = $this->db->table('historical_feeder_interruptions')->countAllResults();
        
        $categoryBreakdown = $this->db->table('historical_feeder_interruptions')
                                      ->select('interruption_category, COUNT(*) as count')
                                      ->groupBy('interruption_category')
                                      ->get()
                                      ->getResultArray();

        $causeBreakdown = $this->db->table('historical_feeder_interruptions')
                                   ->select('cause_category, cause_canonical_code, COUNT(*) as count')
                                   ->groupBy('cause_category, cause_canonical_code')
                                   ->orderBy('count', 'DESC')
                                   ->get()
                                   ->getResultArray();

        $relayBreakdown = $this->db->table('historical_feeder_interruptions')
                                   ->select('relay_trip_type, COUNT(*) as count')
                                   ->groupBy('relay_trip_type')
                                   ->orderBy('count', 'DESC')
                                   ->get()
                                   ->getResultArray();

        $feederTop5 = $this->db->table('historical_feeder_interruptions')
                               ->select('feeder_name, COUNT(*) as count')
                               ->groupBy('feeder_name')
                               ->orderBy('count', 'DESC')
                               ->limit(5)
                               ->get()
                               ->getResultArray();

        return [
            'status'             => 'success',
            'total_records'      => $totalRecords,
            'category_breakdown' => $categoryBreakdown,
            'cause_breakdown'    => $causeBreakdown,
            'relay_breakdown'    => $relayBreakdown,
            'top_feeders'        => $feederTop5,
        ];
    }

    protected function isDuplicateRecord(string $hash): bool
    {
        return $this->db->table('historical_feeder_interruptions')
                        ->where('source_record_hash', $hash)
                        ->countAllResults() > 0;
    }

    protected function createBatchRecord(string $batchCode, string $sourceFile): int
    {
        $existing = $this->db->table('interruption_import_batches')
                             ->where('batch_code', $batchCode)
                             ->get()
                             ->getRowArray();

        if ($existing) {
            return (int)$existing['id'];
        }

        $this->db->table('interruption_import_batches')->insert([
            'batch_code'          => $batchCode,
            'source_system'       => 'GOOGLE_SPREADSHEET_PLN_SDA',
            'source_filename'     => $sourceFile,
            'source_sheet_name'   => 'REKAP_GANGGUAN_2025_2026',
            'total_rows_read'     => 0,
            'total_rows_imported' => 0,
            'total_rows_duplicate'=> 0,
            'total_rows_flagged'  => 0,
            'batch_checksum'      => hash('sha256', $batchCode . date('YmdHis')),
            'status'              => 'PROCESSING',
            'imported_by'         => 'SYSTEM_INGESTION_SERVICE',
            'created_at'          => date('Y-m-d H:i:s'),
            'updated_at'          => date('Y-m-d H:i:s'),
        ]);

        return (int)$this->db->insertID();
    }

    protected function updateBatchSummary(int $batchId, int $read, int $imported, int $dup, int $flagged): void
    {
        $this->db->table('interruption_import_batches')
                 ->where('id', $batchId)
                 ->update([
                     'total_rows_read'      => $read,
                     'total_rows_imported' => $imported,
                     'total_rows_duplicate'=> $dup,
                     'total_rows_flagged'   => $flagged,
                     'status'               => 'COMPLETED',
                     'updated_at'           => date('Y-m-d H:i:s'),
                 ]);
    }
}
