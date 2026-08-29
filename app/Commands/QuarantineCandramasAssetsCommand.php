<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Services\CanonicalFeederAssetResolutionService;

/**
 * Phase AR-01 Phase 4A: Controlled Reversible Dataset Quarantine Command
 * Usage: php spark ar01:quarantine-candramas [--dry-run] [--execute]
 */
class QuarantineCandramasAssetsCommand extends BaseCommand
{
    protected $group       = 'AR-01';
    protected $name        = 'ar01:quarantine-candramas';
    protected $description = 'Phase AR-01 Phase 4A: Reversible Soft-Delete Quarantine of 312 Unassigned CANDRAMAS Pilot Assets';

    protected $options = [
        '--dry-run' => 'Run in dry-run mode (default, zero writes)',
        '--execute' => 'Execute the reversible atomic soft-delete update',
    ];

    public function run(array $params)
    {
        $isExecute = in_array('--execute', $params) || CLI::getOption('execute') !== null;
        $dryRun    = !$isExecute;

        $db = \Config\Database::connect();
        $resolver = new CanonicalFeederAssetResolutionService($db);

        $modeLabel = $dryRun ? 'DRY-RUN RECONNAISSANCE' : 'CONTROLLED EXECUTION';
        $modeColor = $dryRun ? 'yellow' : 'red';

        CLI::write("\n==================================================================", $modeColor);
        CLI::write("   AR-01 PHASE 4A: REVERSIBLE DATASET QUARANTINE PIPELINE         ", $modeColor);
        CLI::write("   MODE: {$modeLabel}                                             ", $modeColor);
        CLI::write("==================================================================\n", $modeColor);

        $result = $resolver->quarantineUnassignedPilotAssets($dryRun);

        if (!$result['success']) {
            CLI::error("🔴 OPERASI DIBATALKAN: " . ($result['error'] ?? 'Terjadi kesalahan validasi karantina.'));
            return 1;
        }

        if ($dryRun) {
            CLI::write("Target Dataset          : CANDRAMAS PILOT");
            CLI::write("Expected Candidates     : {$result['expected_candidates']}");
            CLI::write("Actual Candidates       : {$result['actual_candidates']}");
            CLI::write("Mismatch                : " . ($result['mismatch'] === 0 ? "0 (MATCH)" : "{$result['mismatch']}"));
            CLI::write("");
            CLI::write("PYL-015 Protected       : {$result['pyl015_protected']} assets");
            CLI::write("PYL-042 Protected       : {$result['pyl042_protected']} assets");
            CLI::write("PYL-001 Affected        : {$result['pyl001_affected']} assets");
            CLI::write("");
            CLI::write("Active Findings         : {$result['active_findings']} conflicts");
            CLI::write("Hard Delete             : {$result['hard_delete_count']} (Zero Hard Delete)");
            CLI::write("Database Writes         : {$result['database_writes']} (Zero Mutation)");
            CLI::write("");
            CLI::write("------------------------------------------------------------------");
            CLI::write("DRY-RUN RESULT          : " . CLI::color("PASS", 'green'));
            CLI::write("EXECUTION GATE          : " . CLI::color("{$result['gate_status']}", 'green'));
            CLI::write("------------------------------------------------------------------");
            CLI::write("\nTo apply reversible soft-delete quarantine, run with: '--execute'");
            CLI::write("Command: php spark ar01:quarantine-candramas --execute\n", 'yellow');

            return 0;
        }

        // Execution Mode Output
        CLI::write("Target Dataset          : CANDRAMAS PILOT");
        CLI::write("Quarantined Count       : " . CLI::color("{$result['quarantined_count']} assets", 'green'));
        CLI::write("Total Raw Retained      : {$result['total_raw_assets']} records (Zero Hard Delete)");
        CLI::write("");
        CLI::write("PYL-015 Protected       : {$result['pyl015_protected']} assets (Preserved)");
        CLI::write("PYL-042 Protected       : {$result['pyl042_protected']} assets (Preserved)");
        CLI::write("PYL-001 Affected        : {$result['pyl001_affected']} assets");
        CLI::write("");
        CLI::write("Active Grid Scope Before: {$result['active_grid_scope_before']} assets");
        CLI::write("Active Grid Scope After : " . CLI::color("{$result['active_grid_scope_after']} assets", 'green') . " ({$result['pyl015_protected']} PYL-015 + {$result['pyl042_protected']} PYL-042)");
        CLI::write("Quarantined Timestamp   : {$result['quarantined_timestamp']}");
        CLI::write("Reversible Guarantee    : " . CLI::color("{$result['reversible_guarantee']}", 'green'));

        CLI::write("\n==================================================================", 'green');
        CLI::write("🟢 QUARANTINE SUCCESSFUL: 312 unassigned assets soft-deleted safely.", 'green');
        CLI::write("   Next Step: Run Post-Quarantine Verification Suite:", 'white');
        CLI::write("     • php spark audit:ar01-reconcile 1", 'light_cyan');
        CLI::write("     • php spark audit:ar01-evidence 1", 'light_cyan');
        CLI::write("     • php spark audit:cc04", 'light_cyan');
        CLI::write("==================================================================\n", 'green');

        return 0;
    }
}
