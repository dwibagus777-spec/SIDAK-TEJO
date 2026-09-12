<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Services\GlobalNetworkTopologyAuditService;

/**
 * GlobalTopologyAuditCommand
 *
 * CLI runner for STRICT READ-ONLY Global Network Topology Coverage Audit across ALL Feeders.
 */
class GlobalTopologyAuditCommand extends BaseCommand
{
    protected $group       = 'GIS';
    protected $name        = 'gis:audit-global-topology';
    protected $description = 'Performs STRICT READ-ONLY Global Network Topology Coverage Audit across ALL Feeders.';
    protected $usage       = 'gis:audit-global-topology [--json] [--output=PATH]';
    protected $options     = [
        '--json'   => 'Output raw audit result as JSON',
        '--output' => 'Optional file path to save audit JSON',
    ];

    public function run(array $params)
    {
        CLI::write("============================================================", 'yellow');
        CLI::write("   SIDAK TEJO — GLOBAL NETWORK TOPOLOGY COVERAGE AUDIT      ", 'yellow');
        CLI::write("   STRICTLY READ-ONLY FORENSIC PHASE                        ", 'yellow');
        CLI::write("============================================================", 'yellow');

        $auditService = new GlobalNetworkTopologyAuditService();
        $result = $auditService->runAudit();

        $summary = $result['global_summary'];
        $gov = $result['governance'];

        CLI::write("\n[GOVERNANCE FIREWALL STATUS]");
        CLI::write("  - Zero-Write Firewall : " . ($gov['zero_write_firewall'] === 'PASS' ? "PASS (0 MUTASI)" : "FAIL"), $gov['zero_write_firewall'] === 'PASS' ? 'green' : 'red');
        CLI::write("  - Operational Inserts : " . $gov['operational_inserts']);
        CLI::write("  - Operational Updates : " . $gov['operational_updates']);
        CLI::write("  - Operational Deletes : " . $gov['operational_deletes']);
        CLI::write("  - Operational DDL     : " . $gov['operational_ddl']);

        CLI::write("\n[GLOBAL NETWORK TOTALS]");
        CLI::write("  - Total Feeders               : " . $summary['total_feeders']);
        CLI::write("  - Total ULPs                  : " . $summary['total_ulps']);
        CLI::write("  - Total JTM Assets            : " . $summary['total_jtm_assets']);
        CLI::write("  - Total Valid GPS Assets      : " . $summary['total_valid_gps_assets']);
        CLI::write("  - Total Existing Translines   : " . $summary['total_existing_translines']);
        CLI::write("  - Total Connected Assets      : " . $summary['total_connected_assets']);
        CLI::write("  - Total Isolated Assets       : " . $summary['total_isolated_assets']);
        CLI::write("  - Total Auto-Complete Cand.   : " . $summary['total_auto_complete']);
        CLI::write("  - Total High Confidence Cand. : " . $summary['total_high_confidence']);
        CLI::write("  - Total Review Required Cand. : " . $summary['total_review_required']);
        CLI::write("  - Total Blocked Candidates    : " . $summary['total_blocked']);

        CLI::write("\n[FEEDER CLASSIFICATION SUMMARY]");
        foreach ($result['classification_summary'] as $cls => $cnt) {
            CLI::write(sprintf("  - %-32s : %d", $cls, $cnt));
        }

        CLI::write("\n[AUTOMATION OPPORTUNITY]");
        foreach ($result['automation_opportunity'] as $opp => $cnt) {
            CLI::write(sprintf("  - %-32s : %d", $opp, $cnt));
        }

        $isJson = CLI::getOption('json') !== null;
        $outPath = CLI::getOption('output');

        if ($outPath) {
            file_put_contents($outPath, json_encode($result, JSON_PRETTY_PRINT));
            CLI::write("\nAudit report successfully saved to: {$outPath}", 'green');
        }

        if ($isJson) {
            CLI::write(json_encode($result, JSON_PRETTY_PRINT));
        }

        return 0;
    }
}
