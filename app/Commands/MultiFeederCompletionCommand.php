<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Services\MultiFeederCompletionOrchestrator;

/**
 * MultiFeederCompletionCommand
 *
 * CLI Runner for TL-MF-02 Controlled Multi-Feeder Completion Orchestrator.
 */
class MultiFeederCompletionCommand extends BaseCommand
{
    protected $group       = 'GIS';
    protected $name        = 'gis:multi-feeder-completion';
    protected $description = 'Runs the TL-MF-02 Controlled Multi-Feeder Network Completion Orchestrator.';
    protected $usage       = 'gis:multi-feeder-completion [--mode=dry-run|live] [--feeder=ID] [--max-feeders=N] [--max-batches=N] [--resume] [--reset-state] [--json] [--report]';
    protected $options     = [
        '--mode'        => 'Execution mode: dry-run (default, zero writes) or live',
        '--feeder'      => 'Focus on a single feeder ID',
        '--max-feeders' => 'Limit maximum number of feeders in queue to process',
        '--max-batches' => 'Maximum batches per feeder before pausing (PAUSED, not stabilized)',
        '--resume'      => 'Resume processing from previous checkpoint state',
        '--reset-state' => 'Safely archive active checkpoint and start a fresh run_id',
        '--json'        => 'Output result as JSON',
        '--report'      => 'Save summary markdown report to file',
    ];

    public function run(array $params)
    {
        $mode       = strtolower((string)(CLI::getOption('mode') ?? 'dry-run'));
        $feederId   = CLI::getOption('feeder') ? (int)CLI::getOption('feeder') : null;
        $maxFeeders = CLI::getOption('max-feeders') ? (int)CLI::getOption('max-feeders') : null;
        $maxBatches = CLI::getOption('max-batches') ? (int)CLI::getOption('max-batches') : 50;
        $resume     = CLI::getOption('resume') !== null;
        $resetState = CLI::getOption('reset-state') !== null;
        $isJson     = CLI::getOption('json') !== null;
        $doReport   = CLI::getOption('report') !== null;

        if ($mode !== 'live') {
            $mode = 'dry-run';
        }

        if (!$isJson) {
            CLI::write("=======================================================================", 'yellow');
            CLI::write("   ⚡ SIDAK TEJO — TL-MF-02 MULTI-FEEDER COMPLETION ORCHESTRATOR       ", 'yellow');
            CLI::write("   Version: " . MultiFeederCompletionOrchestrator::ORCHESTRATOR_VERSION . " | Mode: " . strtoupper($mode), 'yellow');
            CLI::write("=======================================================================", 'yellow');

            if ($mode === 'dry-run') {
                CLI::write("ℹ️  MODE: DRY-RUN SIMULATION (STRICT READ-ONLY: 0 DB WRITES)", 'cyan');
            } else {
                CLI::write("⚠️  MODE: LIVE EXECUTION (ATOMIC BATCH WRITES ENABLED)", 'red');
            }
        }

        $orchestrator = new MultiFeederCompletionOrchestrator();

        $result = $orchestrator->run([
            'mode'        => $mode,
            'feeder_id'   => $feederId,
            'max_feeders' => $maxFeeders,
            'max_batches' => $maxBatches,
            'resume'      => $resume,
            'reset_state' => $resetState,
            'actor_name'  => 'ENGINEER_TRANSLINE_CLI',
        ]);

        if ($isJson) {
            CLI::write(json_encode($result, JSON_PRETTY_PRINT));
            return;
        }

        if (($result['status'] ?? '') === 'RUN_ALREADY_ACTIVE') {
            CLI::error("❌ " . $result['message']);
            return;
        }

        $qSum = $result['global_queue_summary'] ?? [];
        CLI::write("\n[1. GLOBAL FEEDER QUEUE SUMMARY]");
        CLI::write("  - Total Feeders in System    : " . ($qSum['total_feeders'] ?? 0));
        CLI::write("  - Feeders with No Assets     : " . ($qSum['no_asset_count'] ?? 0) . " (SKIPPED)");
        CLI::write("  - Near-Complete Feeders      : " . ($qSum['near_complete_count'] ?? 0) . " (SKIPPED)");
        CLI::write("  - Ready for AI Queue         : " . ($qSum['ready_for_ai_count'] ?? 0));
        CLI::write("  - Feeders Processed in Run   : " . ($result['feeders_processed_count'] ?? 0));

        CLI::write("\n[2. ORCHESTRATION TOTALS]");
        CLI::write("  - Run ID                     : " . ($result['run_id'] ?? ''));
        CLI::write("  - Total Batches Executed     : " . ($result['total_batches_run'] ?? 0));
        CLI::write("  - Total Translines Added     : " . ($result['total_translines_added'] ?? 0));
        CLI::write("  - Zero-Write Firewall Pass   : " . ($result['dry_run_zero_write_pass'] ? 'PASS (0 WRITES)' : 'FAIL'), $result['dry_run_zero_write_pass'] ? 'green' : 'red');
        CLI::write("  - Protected Domains Intact   : " . ($result['protected_domains_intact'] ? 'PASS (IDENTICAL SHA)' : 'FAIL'), $result['protected_domains_intact'] ? 'green' : 'red');

        CLI::write("\n[3. PROCESSED FEEDERS DETAIL]");
        printf("  %-4s | %-24s | %-16s | %-12s | %-8s | %-8s | %-15s\n",
            "ID", "Nama Penyulang", "ULP", "Initial Isol", "Final Isol", "Added TL", "Status");
        CLI::write("  " . str_repeat("-", 100));

        foreach ($result['feeders_report'] ?? [] as $fr) {
            $fStatusColor = 'green';
            if ($fr['status'] === 'ANOMALY_HELD') {
                $fStatusColor = 'red';
            } elseif ($fr['status'] === 'MAX_BATCH_LIMIT_REACHED') {
                $fStatusColor = 'yellow';
            }

            printf("  %-4d | %-24s | %-16s | %-12d | %-8d | %-8d | %s\n",
                $fr['penyulang_id'],
                substr($fr['nama_penyulang'], 0, 24),
                substr($fr['nama_ulp'], 0, 16),
                $fr['initial_isolated'],
                $fr['final_isolated'] ?? $fr['initial_isolated'],
                $fr['total_created_count'],
                $fr['status']
            );
        }

        if ($doReport) {
            $reportPath = WRITEPATH . 'audits/TL_MF02_ORCHESTRATOR_DRY_RUN.md';
            $this->generateMarkdownReport($result, $reportPath);
            CLI::write("\n📄 Detailed report written to: {$reportPath}", 'green');
        }

        CLI::write("\n=======================================================================", 'yellow');
        CLI::write("   TL-MF-02 ORCHESTRATOR RUN COMPLETE", 'yellow');
        CLI::write("=======================================================================", 'yellow');
    }

    protected function generateMarkdownReport(array $result, string $path): void
    {
        $md = "# TL-MF-02 MULTI-FEEDER COMPLETION ORCHESTRATOR REPORT\n\n";
        $md .= "**Run ID**: `{$result['run_id']}`  \n";
        $md .= "**Mode**: `{$result['mode']}`  \n";
        $md .= "**Date**: " . date('Y-m-d H:i:s') . "  \n\n";
        $md .= "## Summary\n";
        $md .= "- **Feeders Processed**: {$result['feeders_processed_count']}\n";
        $md .= "- **Total Batches**: {$result['total_batches_run']}\n";
        $md .= "- **Total Projected Translines**: {$result['total_translines_added']}\n";
        $md .= "- **Zero-Write Firewall**: " . ($result['dry_run_zero_write_pass'] ? 'PASS (0 writes)' : 'FAIL') . "\n\n";
        file_put_contents($path, $md);
    }
}
