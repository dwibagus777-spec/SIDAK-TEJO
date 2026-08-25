<?php

namespace App\Services;

/**
 * Spreadsheet Gangguan Import Plan Service (CR-01 Phase 2)
 *
 * Responsibilities:
 * - Transforms In-Memory Dry-Run results into a structured Import Plan (ZERO Database Writes).
 * - Feeder resolution via FeederIdentityResolutionService.
 * - Row disposition assignment: READY_FOR_IMPORT, SKIP_EXACT_DUPLICATE, HOLD_COMPOSITE_DUPLICATE,
 *   HOLD_AMBIGUOUS_FEEDER, HOLD_UNRESOLVED_FEEDER, INVALID.
 * - Generates secure deterministic import_plan_id & confirmation_token.
 */
class SpreadsheetGangguanImportPlanService
{
    protected SpreadsheetGangguanDryRunService $dryRunService;
    protected FeederIdentityResolutionService $feederResolver;

    public function __construct(
        ?SpreadsheetGangguanDryRunService $dryRunService = null,
        ?FeederIdentityResolutionService $feederResolver = null
    ) {
        $this->dryRunService  = $dryRunService ?? new SpreadsheetGangguanDryRunService();
        $this->feederResolver = $feederResolver ?? new FeederIdentityResolutionService();
    }

    /**
     * Generate a structured Import Plan from a spreadsheet file.
     *
     * @param string $filePath Absolute path to spreadsheet
     * @param string|null $sheetName Optional worksheet name
     * @param bool $autoAcceptHighConfidenceFeeders If true, accept high confidence feeder matches
     * @return array Structured Import Plan payload
     */
    public function generateImportPlan(
        string $filePath,
        ?string $sheetName = null,
        bool $autoAcceptHighConfidenceFeeders = false
    ): array {
        // 1. Execute Dry-Run (0 = process all rows)
        $dryRun = $this->dryRunService->executeDryRun($filePath, $sheetName, 0);
        if (!$dryRun['success']) {
            return [
                'success' => false,
                'error'   => $dryRun['error'] ?? 'Dry-run simulation failed.',
            ];
        }

        $sourceFile = $dryRun['source']['filename'];
        $format     = $dryRun['source']['format'];
        $sheetTitle = $dryRun['source']['sheet_name'];

        // 2. Build Deterministic Plan ID & Tokens
        $planTimestamp = date('YmdHis');
        $planSeed      = "{$sourceFile}|{$sheetTitle}|{$dryRun['summary']['total_rows']}|{$planTimestamp}";
        $planHash      = substr(hash('sha256', $planSeed), 0, 12);
        $importPlanId  = "PLAN-STJ-GDR-{$planTimestamp}-{$planHash}";
        $confirmToken  = hash('sha256', "{$importPlanId}|CONFIRM_KEY_STJ_ENTERPRISE");

        // 3. Process and Classify Each Row
        $exactDupRowNums = array_column($dryRun['duplicates']['exact_hash'], 'row_number');
        $compDupRowNums  = array_column($dryRun['duplicates']['composite_business_key'], 'row_number');

        $plannedRows = [];
        $readyCount   = 0;
        $skipCount    = 0;
        $holdCount    = 0;
        $invalidCount = 0;

        $feederResolutionStats = [
            'EXACT_MATCH'              => 0,
            'NORMALIZED_MATCH'         => 0,
            'HIGH_CONFIDENCE_CANDIDATE'=> 0,
            'AMBIGUOUS_CANDIDATE'      => 0,
            'UNRESOLVED'               => 0,
        ];

        foreach ($dryRun['preview'] as $row) {
            $rowNum     = $row['_source_row_number'];
            $valStatus  = $row['_validation_status'];
            $rawFeeder  = $row['feeder_name'] ?? '';

            // Feeder Resolution
            $feederRes = $this->feederResolver->resolveFeeder($rawFeeder);
            $feederStatus = $feederRes['status'];
            $feederResolutionStats[$feederStatus] = ($feederResolutionStats[$feederStatus] ?? 0) + 1;

            $disposition = 'READY_FOR_IMPORT';
            $holdReason  = null;

            // Check Validity
            if ($valStatus === 'INVALID') {
                $disposition = 'INVALID';
                $invalidCount++;
                $holdReason = 'Format baris gagal validasi schema kanonikal M-04.';
            }
            // Check Exact Duplicates
            elseif (in_array($rowNum, $exactDupRowNums, true)) {
                $disposition = 'SKIP_EXACT_DUPLICATE';
                $skipCount++;
                $holdReason = 'Baris memiliki payload persis sama dengan baris sebelumnya (Tier 1 Hash Match).';
            }
            // Check Composite Duplicates
            elseif (in_array($rowNum, $compDupRowNums, true)) {
                $disposition = 'HOLD_COMPOSITE_DUPLICATE';
                $holdCount++;
                $holdReason = 'Potensi duplikat bisnis (Tanggal + Penyulang + Jam Mulai sama).';
            }
            // Check Feeder Matching
            elseif ($feederStatus === 'UNRESOLVED') {
                $disposition = 'HOLD_UNRESOLVED_FEEDER';
                $holdCount++;
                $holdReason = "Penyulang '{$rawFeeder}' tidak ditemukan dalam master database.";
            }
            elseif ($feederStatus === 'AMBIGUOUS_CANDIDATE') {
                $disposition = 'HOLD_AMBIGUOUS_FEEDER';
                $holdCount++;
                $holdReason = "Penyulang '{$rawFeeder}' memiliki beberapa kandidat kemiripan nama ambigu.";
            }
            elseif ($feederStatus === 'HIGH_CONFIDENCE_CANDIDATE' && !$autoAcceptHighConfidenceFeeders) {
                $disposition = 'HOLD_AMBIGUOUS_FEEDER';
                $holdCount++;
                $holdReason = "Penyulang '{$rawFeeder}' cocok dengan '{$feederRes['resolved_penyulang_name']}' (Confidence: {$feederRes['confidence']}), memerlukan konfirmasi manual.";
            } else {
                $readyCount++;
            }

            $plannedRows[] = [
                'row_number'        => $rowNum,
                'disposition'       => $disposition,
                'hold_reason'       => $holdReason,
                'source_data'       => $row,
                'feeder_resolution' => $feederRes,
            ];
        }

        return [
            'success'            => true,
            'import_plan_id'     => $importPlanId,
            'confirmation_token' => $confirmToken,
            'source'             => [
                'filename'   => $sourceFile,
                'format'     => $format,
                'sheet_name' => $sheetTitle,
                'file_path'  => $filePath,
            ],
            'summary' => [
                'total_source_rows' => $dryRun['summary']['total_rows'],
                'ready_for_import'  => $readyCount,
                'skip_duplicates'   => $skipCount,
                'hold_for_review'   => $holdCount,
                'invalid_rows'      => $invalidCount,
                'feeder_resolution' => $feederResolutionStats,
            ],
            'planned_rows' => $plannedRows,
            'metadata'     => [
                'mode'             => 'IMPORT_PLAN_GENERATED',
                'database_writes'  => 0,
                'schema_mutations' => 0,
                'created_at'       => date('Y-m-d H:i:s'),
            ],
        ];
    }
}
