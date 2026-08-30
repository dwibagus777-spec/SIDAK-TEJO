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
        'batch'         => 'The Batch ID to approve',
        'row'           => 'The source row number to review/approve',
        'decision'      => 'Decision: APPROVED, REJECTED, or NEEDS_REVIEW',
        'approver-nip'  => 'NIP of the Human Engineering Reviewer',
        'approver-name' => 'Full name of the Approver (optional)',
        'reason'        => 'Explicit engineering justification for the decision',
    ];

    /**
     * Multi-tier robust option extractor for CodeIgniter 4.7.4 CLI.
     */
    protected function extractOption(array $params, string $key, ?string $default = null): ?string
    {
        $altKey = str_replace('-', '_', $key);

        // 1. Check in $params associative array (both hyphen and underscore keys)
        if (isset($params[$key]) && is_string($params[$key]) && trim($params[$key]) !== '') {
            return trim($params[$key]);
        }
        if (isset($params[$altKey]) && is_string($params[$altKey]) && trim($params[$altKey]) !== '') {
            return trim($params[$altKey]);
        }

        // 2. Check in CLI::getOption (both hyphen and underscore)
        $opt = CLI::getOption($key) ?? CLI::getOption($altKey);
        if ($opt !== null && is_string($opt) && trim($opt) !== '') {
            return trim($opt);
        }

        // 3. Check in CLI::getOptions() global map
        $allOpts = CLI::getOptions();
        if (isset($allOpts[$key]) && is_string($allOpts[$key]) && trim($allOpts[$key]) !== '') {
            return trim($allOpts[$key]);
        }
        if (isset($allOpts[$altKey]) && is_string($allOpts[$altKey]) && trim($allOpts[$altKey]) !== '') {
            return trim($allOpts[$altKey]);
        }

        // 4. Check sequential/raw $params list
        foreach ($params as $v) {
            if (is_string($v)) {
                if (str_starts_with($v, "--{$key}=")) {
                    return trim(substr($v, strlen("--{$key}=")), " \"'");
                }
                if (str_starts_with($v, "--{$altKey}=")) {
                    return trim(substr($v, strlen("--{$altKey}=")), " \"'");
                }
            }
        }

        // 5. Fallback inspection of $_SERVER['argv']
        if (isset($_SERVER['argv']) && is_array($_SERVER['argv'])) {
            $argv = $_SERVER['argv'];
            for ($i = 0; $i < count($argv); $i++) {
                $arg = $argv[$i];
                if (str_starts_with($arg, "--{$key}=")) {
                    return trim(substr($arg, strlen("--{$key}=")), " \"'");
                }
                if (str_starts_with($arg, "--{$altKey}=")) {
                    return trim(substr($arg, strlen("--{$altKey}=")), " \"'");
                }
                if ($arg === "--{$key}" || $arg === "--{$altKey}") {
                    if (isset($argv[$i + 1]) && !str_starts_with($argv[$i + 1], '--')) {
                        return trim($argv[$i + 1], " \"'");
                    }
                }
            }
        }

        return $default;
    }

    public function run(array $params)
    {
        $batchId      = $this->extractOption($params, 'batch');
        $rowNumber    = $this->extractOption($params, 'row');
        $decision     = $this->extractOption($params, 'decision', 'APPROVED');
        $approverNip  = $this->extractOption($params, 'approver-nip');
        $approverName = $this->extractOption($params, 'approver-name');
        $reason       = $this->extractOption($params, 'reason');

        // Validation Contract
        $missing = [];
        if (!$batchId)      $missing[] = '--batch=<BATCH_ID>';
        if (!$rowNumber)    $missing[] = '--row=<ROW_NUMBER>';
        if (!$approverNip)  $missing[] = '--approver-nip=<NIP>';
        if (!$reason)       $missing[] = '--reason="<justification>"';

        if (!empty($missing)) {
            CLI::error("ERROR: Parameter tidak lengkap: " . implode(', ', $missing));
            CLI::write("Contoh penggunaan yang benar:");
            CLI::write('php spark ar01:approve --batch=BATCH-MULTI-... --row=151 --decision=APPROVED --approver-nip=198501012010011001 --reason="Gardu Trafo Tiang 2 Portal (GTT-2T) telah diverifikasi gambar teknis lapangan"', 'yellow');
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
        CLI::write("==================================================================\n", 'green');

        CLI::write("Batch ID            : " . CLI::color($result['batch_id'], 'yellow'));
        CLI::write("Source Row Number   : #" . CLI::color((string)$result['row_number'], 'yellow'));
        CLI::write("Asset Name          : {$result['asset_name']}");
        CLI::write("Engineering Decision: " . CLI::color($result['decision'], 'cyan'));
        CLI::write("Approver NIP        : {$approverNip}");
        if ($approverName) {
            CLI::write("Approver Name       : {$approverName}");
        }
        CLI::write("Justification       : \"{$reason}\"");
        CLI::write("Signed SHA-256      : " . CLI::color($result['signed_sha256'], 'green'));
        CLI::write("Approved Timestamp  : {$result['approved_at']}");
        CLI::write("Assets Table Writes : " . CLI::color("0 writes (Strictly Read-Only during Phase 5E)", 'green'));
        CLI::write("==================================================================\n", 'green');

        return 0;
    }
}
