<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Services\FeederAssetReviewService;

/**
 * Phase AR-01 Phase 5E: Bulk Approval Command for Deterministic PASS Rows
 * Usage: php spark ar01:approve-batch --batch=BATCH-ID --scope=PASS --approver-nip=XXXX --reason="Verified"
 */
class Ar01ApproveBatchCommand extends BaseCommand
{
    protected $group       = 'Audit';
    protected $name        = 'ar01:approve-batch';
    protected $description = 'AR-01 Phase 5E: Bulk Human Engineering Sign-Off for Deterministic PASS Scope (Excludes WARNING/REVIEW)';

    protected $options = [
        'batch'        => 'The Batch ID to approve',
        'scope'        => 'Scope of approval (strictly PASS)',
        'approver-nip' => 'NIP of the Human Engineering Reviewer',
        'approver-name'=> 'Full name of the Approver (optional)',
        'reason'       => 'Explicit engineering justification for the bulk decision',
    ];

    public function run(array $params)
    {
        $batchId      = CLI::getOption('batch');
        $scope        = CLI::getOption('scope') ?? 'PASS';
        $approverNip  = CLI::getOption('approver-nip');
        $approverName = CLI::getOption('approver-name');
        $reason       = CLI::getOption('reason');

        // Fallback parameter parsing
        foreach ($params as $p) {
            if (str_starts_with($p, '--batch=')) $batchId = substr($p, 8);
            if (str_starts_with($p, '--scope=')) $scope = substr($p, 8);
            if (str_starts_with($p, '--approver-nip=')) $approverNip = substr($p, 15);
            if (str_starts_with($p, '--approver-name=')) $approverName = substr($p, 16);
            if (str_starts_with($p, '--reason=')) $reason = substr($p, 9);
        }

        if (!$batchId || !$approverNip || !$reason) {
            CLI::error("ERROR: Parameter tidak lengkap. Wajib menyertakan --batch, --scope=PASS, --approver-nip, dan --reason.");
            CLI::write("Contoh: php spark ar01:approve-batch --batch=BATCH-MULTI-... --scope=PASS --approver-nip=198501012010011001 --reason=\"Engineering review verified all deterministic PASS candidates\"");
            return 1;
        }

        $db = \Config\Database::connect();
        $reviewService = new FeederAssetReviewService($db);

        $result = $reviewService->approveBatchScope($batchId, $scope, $approverNip, $reason, $approverName);

        if (!$result['success']) {
            CLI::error("BULK SIGN-OFF FAILED: " . $result['error']);
            return 1;
        }

        CLI::write("\n==================================================================", 'green');
        CLI::write("🟢 AR-01 PHASE 5E: BULK HUMAN ENGINEERING SIGN-OFF RECORDED       ", 'green');
        CLI::write("==================================================================", 'green');
        CLI::write("Batch ID            : {$result['batch_id']}");
        CLI::write("Approved Scope      : " . CLI::color($result['scope'], 'yellow'));
        CLI::write("Approved Count      : " . CLI::color("{$result['approved_count']} deterministic PASS assets", 'green'));
        CLI::write("Approver NIP        : {$approverNip}");
        CLI::write("Signed SHA-256      : " . CLI::color($result['signed_sha256'], 'green'));
        CLI::write("Security Note       : WARNING / REVIEW anomalies (e.g. GTT2T) remained unapproved per Invariant 5E-E.");
        CLI::write("Database Writes     : 0 writes to 'assets' table (Strictly Read-Only)");
        CLI::write("==================================================================\n", 'green');

        return 0;
    }
}
