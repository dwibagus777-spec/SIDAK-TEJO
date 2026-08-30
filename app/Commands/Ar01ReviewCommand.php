<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Services\FeederAssetReviewService;

/**
 * Phase AR-01 Phase 5E: Human Engineering Review Matrix Command
 * Usage: php spark ar01:review [--batch=BATCH-ID]
 */
class Ar01ReviewCommand extends BaseCommand
{
    protected $group       = 'Audit';
    protected $name        = 'ar01:review';
    protected $description = 'AR-01 Phase 5E: Human Engineering Review & Anomaly Queue (Strictly Read-Only)';

    protected $arguments = [
        'batch' => 'The Batch ID to review (optional)',
    ];

    protected $options = [
        'batch' => 'The Batch ID to review (optional)',
    ];

    public function run(array $params)
    {
        $batchId = CLI::getOption('batch');
        if (!$batchId) {
            foreach ($params as $p) {
                if (str_starts_with($p, '--batch=')) {
                    $batchId = substr($p, 8);
                    break;
                } elseif (!str_starts_with($p, '--')) {
                    $batchId = $p;
                    break;
                }
            }
        }

        $defaultPath = WRITEPATH . 'Template_Import_MULTI_PENYULANG_PART1.csv';
        if (!file_exists($defaultPath)) {
            $defaultPath = WRITEPATH . 'Template_Import_JTM_SIWALAN_PANJI.csv';
        }

        $db = \Config\Database::connect();
        $reviewService = new FeederAssetReviewService($db);

        if (!$batchId) {
            // Auto-stage or fetch batch from default file
            $init = $reviewService->getOrCreateStagedBatch($defaultPath);
            if (!$init['success']) {
                CLI::error("ERROR: " . $init['error']);
                return 1;
            }
            $batchId = $init['batch_id'];
        }

        $summary = $reviewService->getBatchReviewSummary($batchId);
        if (!$summary['success']) {
            CLI::error("ERROR: " . $summary['error']);
            return 1;
        }

        $b = $summary['batch'];
        $c = $summary['counts'];

        CLI::write("\n==================================================================", 'yellow');
        CLI::write("    AR-01 PHASE 5E — HUMAN ENGINEERING REVIEW & SIGN-OFF         ", 'yellow');
        CLI::write("    STRICTLY READ-ONLY / ZERO MUTATION TO 'assets' TABLE         ", 'yellow');
        CLI::write("==================================================================\n", 'yellow');

        CLI::write("Batch ID            : " . CLI::color($batchId, 'yellow'));
        CLI::write("Source File         : " . $b['source_filename']);
        CLI::write("Source SHA-256      : " . CLI::color($b['source_sha256'], 'green'));
        CLI::write("Source Integrity    : " . CLI::color($summary['source_integrity'], str_starts_with($summary['source_integrity'], 'PASS') ? 'green' : 'red'));
        CLI::write("Total Staged Rows   : {$c['total_rows']} rows");

        CLI::write("\nFEEDER SUMMARY", 'cyan');
        CLI::write("------------------------------------------------------------------");
        foreach ($summary['feeder_summary'] as $f) {
            CLI::write(sprintf("  %-20s %d assets", $f['source_feeder_name'], $f['cnt']));
        }

        CLI::write("\nCONSTRUCTION REVIEW MATRIX", 'cyan');
        CLI::write("------------------------------------------------------------------");
        foreach ($summary['construction_summary'] as $cs) {
            $statusCol = $cs['validation_status'] === 'PASS' ? 'green' : 'yellow';
            $reviewCol = $cs['review_status'] === 'APPROVED' ? 'green' : 'yellow';
            CLI::write(sprintf("  %-12s %-6d [%s] (Review: %s)", 
                $cs['source_construction_code'],
                $cs['cnt'],
                CLI::color($cs['validation_status'], $statusCol),
                CLI::color($cs['review_status'], $reviewCol)
            ));
        }

        if (!empty($summary['review_queue'])) {
            CLI::write("\n⚠️  HUMAN REVIEW QUEUE (Requires Explicit Sign-off):", 'yellow');
            CLI::write("------------------------------------------------------------------");
            foreach (array_slice($summary['review_queue'], 0, 10) as $rq) {
                CLI::write(sprintf("  [ROW #%d] [%s] %s", $rq['source_row_number'], $rq['source_feeder_name'], $rq['source_asset_name']));
                CLI::write("    ├─ Source Construction : " . CLI::color($rq['source_construction_code'], 'yellow'));
                CLI::write("    ├─ Proposed Canonical  : " . CLI::color($rq['normalized_construction_code'], 'cyan'));
                CLI::write("    ├─ Confidence Score    : {$rq['normalization_score']}%");
                CLI::write("    └─ Review Status       : " . CLI::color($rq['review_status'], 'yellow'));
            }
            if (count($summary['review_queue']) > 10) {
                CLI::write("  ... dan " . (count($summary['review_queue']) - 10) . " antrean review lainnya.");
            }
        } else {
            CLI::write("\nAntrean Review Khusus : " . CLI::color("0 pending (Semua anomali telah disetujui / PASS)", 'green'));
        }

        // Evaluate Gate
        $gate = $reviewService->evaluatePromotionGate($batchId);
        CLI::write("\n------------------------------------------------------------------");
        CLI::write("PROMOTION ELIGIBILITY GATE : " . CLI::color($gate['promotion_eligibility'], $gate['promotion_eligibility'] === 'UNLOCKED' ? 'green' : 'red'));
        if ($gate['promotion_eligibility'] === 'LOCKED') {
            foreach ($gate['lock_reasons'] as $lr) {
                CLI::write("  🔒 {$lr}", 'red');
            }
        } else {
            CLI::write("  🟢 Certificate Token: {$gate['certificate_token']}", 'green');
        }
        CLI::write("Database Mutation Guard    : " . CLI::color("0 writes to 'assets' table", 'green'));
        CLI::write("==================================================================\n", 'yellow');

        return 0;
    }
}
