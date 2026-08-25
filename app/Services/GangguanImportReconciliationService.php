<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * Gangguan Import Reconciliation Service (CR-01 Phase 2)
 *
 * Responsibilities:
 * - Post-import audit & reconciliation between planned vs actual database state.
 * - Invariant safety guard: Verifies no unexpected mutation occurred in unrelated tables.
 * - Produces formal reconciliation report.
 */
class GangguanImportReconciliationService
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * Reconcile an executed import result against database state.
     *
     * @param array $importPlan The original import plan
     * @param array $commitResult The result returned from SpreadsheetGangguanImportService::commitImportPlan
     * @param array $preTableCounts Table row counts before the commit
     * @return array Structured reconciliation report
     */
    public function reconcileImport(array $importPlan, array $commitResult, array $preTableCounts = []): array
    {
        $plannedReady = $importPlan['summary']['ready_for_import'] ?? 0;
        $inserted     = $commitResult['inserted_count'] ?? 0;
        $skipped      = $commitResult['skipped_duplicates'] ?? 0;
        $held         = $commitResult['held_count'] ?? 0;
        $isCommitted  = ($commitResult['status'] ?? '') === 'COMMITTED';

        $currentDbCount = $this->db->table('historical_feeder_interruptions')->countAllResults();
        $rawPre = $preTableCounts['historical_feeder_interruptions'] ?? 0;
        $preDbCount = is_array($rawPre) ? ($rawPre['row_count'] ?? $rawPre['total_rows'] ?? 0) : (int)$rawPre;

        $actualDbDelta = $currentDbCount - $preDbCount;
        $isDeltaMatched = ($actualDbDelta === $inserted);

        // Verify other protected tables
        $unrelatedTableAudits = [];
        $allProtectedPreserved = true;

        $protectedTables = ['temuan', 'penyulang', 'sections', 'assets', 'ulps', 'migrations'];
        foreach ($protectedTables as $tbl) {
            if ($this->db->tableExists($tbl)) {
                $curr = $this->db->table($tbl)->countAllResults();
                $rawTblPre = $preTableCounts[$tbl] ?? $curr;
                $pre = is_array($rawTblPre) ? ($rawTblPre['row_count'] ?? $rawTblPre['total_rows'] ?? $curr) : (int)$rawTblPre;
                $intact = ($curr === $pre);
                if (!$intact) $allProtectedPreserved = false;

                $unrelatedTableAudits[$tbl] = [
                    'pre_count'  => $pre,
                    'post_count' => $curr,
                    'intact'     => $intact,
                ];
            }
        }

        $isReconciled = $isCommitted && $isDeltaMatched && $allProtectedPreserved;

        return [
            'status'               => $isReconciled ? 'RECONCILIATION_PASSED' : 'RECONCILIATION_FAILED',
            'reconciled'           => $isReconciled,
            'import_plan_id'       => $importPlan['import_plan_id'] ?? null,
            'batch_id'             => $commitResult['batch_id'] ?? null,
            'reconciliation_audit' => [
                'planned_ready_rows'   => $plannedReady,
                'actual_inserted_rows' => $inserted,
                'skipped_duplicates'   => $skipped,
                'held_rows'            => $held,
                'db_delta_matched'     => $isDeltaMatched,
                'historical_db_count'  => $currentDbCount,
            ],
            'protected_tables_audit'   => $unrelatedTableAudits,
            'reconciled_at'            => date('Y-m-d H:i:s'),
        ];
    }
}
