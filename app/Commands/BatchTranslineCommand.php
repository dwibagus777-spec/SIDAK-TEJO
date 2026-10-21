<?php

namespace App\Commands;

use App\Services\BatchTranslineValidationService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class BatchTranslineCommand extends BaseCommand
{
    protected $group       = 'GIS';
    protected $name        = 'gis:batch-transline';
    protected $description = 'Validate and batch persist qualifying translines linked strictly to JTM poles';

    public function run(array $params)
    {
        $isExecute = in_array('--execute', $params, true) || in_array('execute', $params, true) || CLI::getOption('execute') !== null || !empty($params['execute']);
        $mode = $isExecute ? 'EXECUTE' : 'DRY-RUN';

        CLI::write("=== GIS BATCH TRANSLINE VALIDATION & PERSISTENCE [{$mode}] ===", 'yellow');

        $service = new BatchTranslineValidationService();

        if (!$isExecute) {
            $dryRun = $service->dryRun();
            CLI::write("\n--- DRY-RUN SUMMARY ---", 'cyan');
            CLI::write("Total Candidates Evaluated : {$dryRun['total_evaluated']}");
            CLI::write("Valid Candidates           : {$dryRun['valid_count']}", 'green');
            CLI::write("Duplicates (Already Active): {$dryRun['duplicate_count']}", 'dark');
            CLI::write("Rejected (Violations)      : {$dryRun['rejected_count']}", 'red');

            if (!empty($dryRun['rejected_segments'])) {
                CLI::write("\nSample Rejection Reasons:", 'yellow');
                foreach (array_slice($dryRun['rejected_segments'], 0, 10) as $rej) {
                    CLI::write("  - [{$rej['feeder']}] {$rej['pair']}: {$rej['reason']} ({$rej['detail']})");
                }
            }

            if (!empty($dryRun['valid_segments'])) {
                CLI::write("\nSample Valid Segments Ready to Persist:", 'green');
                foreach (array_slice($dryRun['valid_segments'], 0, 10) as $val) {
                    $defText = $val['conductor_is_default'] ? '[DEFAULT AAACS]' : '[SOURCE CONDUCTOR]';
                    CLI::write("  + [{$val['nama_penyulang']}] {$val['source_code']} -> {$val['target_code']} ({$val['distance_meters']}m, {$val['conductor_type']} {$defText})");
                }
            }

            if (!empty($dryRun['feeders_needing_input'])) {
                CLI::write("\nFeeders Requiring Additional Field Pole Input:", 'yellow');
                foreach ($dryRun['feeders_needing_input'] as $fni) {
                    CLI::write("  ! [{$fni['nama_penyulang']}] {$fni['issue']}");
                }
            }
        } else {
            CLI::write("Executing batch transline transaction with automated backup...", 'yellow');
            $res = $service->executeBatch(null, 'ADMIN_CLI');
            CLI::write("Status: {$res['status']}", 'green');
            CLI::write("Saved Translines: {$res['saved_count']}");
            CLI::write("Backup File Created: {$res['backup_file']}");
            if (!empty($res['feeders_saved'])) {
                foreach ($res['feeders_saved'] as $fs) {
                    CLI::write("  + {$fs['nama_penyulang']}: {$fs['count']} translines");
                }
            }
        }
    }
}
