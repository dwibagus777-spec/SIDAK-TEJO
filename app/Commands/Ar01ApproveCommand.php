<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Services\FeederAssetReviewService;

/**
 * Phase AR-01 Phase 5E: Single-Row Human Approval Command
 * Usage: php spark ar01:approve --batch=BATCH-ID --row=151 --decision=APPROVED --approver-nip=XXXX --reason="Verified"
 */
class Ar01ApproveCommand extends BaseCommand
{
    protected $group       = 'Audit';
    protected $name        = 'ar01:approve';
    protected $description = 'AR-01 Phase 5E: Record Single-Row Human Engineering Sign-Off with Cryptographic Signature';

    protected $options = [
        'batch'        => 'The Batch ID to approve',
        'row'          => 'The source row number to review/approve',
        'decision'     => 'Decision: APPROVED, REJECTED, or NEEDS_REVIEW',
        'approver-nip' => 'NIP of the Human Engineering Reviewer',
        'approver-name'=> 'Full name of the Approver (optional)',
        'reason'       => 'Explicit engineering justification for the decision',
    ];

    public function run(array $params)
    {
        $batchId      = CLI::getOption('batch');
        $rowNumber    = CLI::getOption('row');
        $decision     = CLI::getOption('decision') ?? 'APPROVED';
        $approverNip  = CLI::getOption('approver-nip');
        $approverName = CLI::getOption('approver-name');
        $reason       = CLI::getOption('reason');

        // Fallback parameter parsing
        foreach ($params as $p) {
            if (str_starts_with($p, '--batch=')) $batchId = substr($p, 8);
            if (str_starts_with($p, '--row=')) $rowNumber = (int)substr($p, 6);
            if (str_starts_with($p, '--decision=')) $decision = substr($p, 11);
            if (str_starts_with($p, '--approver-nip=')) $approverNip = substr($p, 15);
            if (str_starts_with($p, '--approver-name=')) $approverName = substr($p, 16);
            if (str_starts_with($p, '--reason=')) $reason = substr($p, 9);
        }

        if (!$batchId || !$rowNumber || !$approverNip || !$reason) {
            CLI::error("ERROR: Parameter tidak lengkap. Wajib menyertakan --batch, --row, --approver-nip, dan --reason.");
            CLI::write("Contoh: php spark ar01:approve --batch=BATCH-MULTI-... --row=151 --decision=APPROVED --approver-nip=198501012010011001 --reason=\"Konstruksi GTT-2T telah diverifikasi gambar teknis lapangan\"");
            return 1;
        }

        $db = \Config\Database::connect();
        $reviewService = new FeederAssetReviewService($db);

        $result = $reviewService->approveSingleRow($batchId, (int)$rowNumber, $decision, $approverNip, $reason, $approverName);

        if (!$result['success']) {
            CLI::error("SIGN-OFF FAILED: " . $result['error']);
            return 1;
        }

        CLI::write("\n==================================================================", 'green');
        CLI::write("🟢 AR-01 PHASE 5E: HUMAN ENGINEERING SIGN-OFF RECORDED           ", 'green');
        CLI::write("==================================================================", 'green');
        CLI::write("Batch ID            : {$result['batch_id']}");
        CLI::write("Row Number          : #{$result['row_number']}");
        CLI::write("Asset Name          : {$result['asset_name']}");
        CLI::write("Decision            : " . CLI::color($result['decision'], 'yellow'));
        CLI::write("Approver NIP        : {$approverNip}");
        CLI::write("Signed SHA-256      : " . CLI::color($result['signed_sha256'], 'green'));
        CLI::write("Approved Timestamp  : {$result['approved_at']}");
        CLI::write("Database Writes     : 0 writes to 'assets' table (Strictly Read-Only)");
        CLI::write("==================================================================\n", 'green');

        return 0;
    }
}
