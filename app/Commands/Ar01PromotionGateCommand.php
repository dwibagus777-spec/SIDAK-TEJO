<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Services\FeederAssetReviewService;

/**
 * Phase AR-01 Phase 5E -> 5F: Promotion Eligibility Certificate Gate Command
 * Usage: php spark ar01:promotion-gate --batch=BATCH-ID
 */
class Ar01PromotionGateCommand extends BaseCommand
{
    protected $group       = 'Audit';
    protected $name        = 'ar01:promotion-gate';
    protected $description = 'AR-01 Phase 5E -> 5F: Evaluate Promotion Eligibility Gate and Issue Cryptographic Certificate';

    protected function extractOption(array $params, string $key, ?string $default = null): ?string
    {
        $altKey = str_replace('-', '_', $key);
        if (isset($params[$key]) && is_string($params[$key]) && trim($params[$key]) !== '') {
            return trim($params[$key]);
        }
        if (isset($params[$altKey]) && is_string($params[$altKey]) && trim($params[$altKey]) !== '') {
            return trim($params[$altKey]);
        }
        $opt = CLI::getOption($key) ?? CLI::getOption($altKey);
        if ($opt !== null && is_string($opt) && trim($opt) !== '') {
            return trim($opt);
        }
        $allOpts = CLI::getOptions();
        if (isset($allOpts[$key]) && is_string($allOpts[$key]) && trim($allOpts[$key]) !== '') {
            return trim($allOpts[$key]);
        }
        if (isset($allOpts[$altKey]) && is_string($allOpts[$altKey]) && trim($allOpts[$altKey]) !== '') {
            return trim($allOpts[$altKey]);
        }
        foreach ($params as $v) {
            if (is_string($v)) {
                if (str_starts_with($v, "--{$key}=")) {
                    return trim(substr($v, strlen("--{$key}=")), " \"'");
                }
                if (str_starts_with($v, "--{$altKey}=")) {
                    return trim(substr($v, strlen("--{$altKey}=")), " \"'");
                }
                if (!str_starts_with($v, '--')) {
                    return trim($v);
                }
            }
        }
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
            }
        }
        return $default;
    }

    public function run(array $params)
    {
        $batchId = $this->extractOption($params, 'batch');

        $defaultPath = WRITEPATH . 'Template_Import_MULTI_PENYULANG_PART1.csv';
        if (!file_exists($defaultPath)) {
            $defaultPath = WRITEPATH . 'Template_Import_JTM_SIWALAN_PANJI.csv';
        }

        $db = \Config\Database::connect();
        $reviewService = new FeederAssetReviewService($db);

        if (!$batchId) {
            $init = $reviewService->getOrCreateStagedBatch($defaultPath);
            if (!$init['success']) {
                CLI::error("ERROR: " . $init['error']);
                return 1;
            }
            $batchId = $init['batch_id'];
        }

        $gate = $reviewService->evaluatePromotionGate($batchId);
        if (!$gate['success']) {
            CLI::error("ERROR: " . $gate['error']);
            return 1;
        }

        $c = $gate['counts'];

        CLI::write("\n==================================================================", 'yellow');
        CLI::write("       AR-01 PHASE 5E → 5F PROMOTION GATE EVALUATION             ", 'yellow');
        CLI::write("==================================================================\n", 'yellow');

        CLI::write("Batch ID                     : " . CLI::color($batchId, 'yellow'));
        CLI::write("Source Integrity             : " . CLI::color($gate['source_integrity'], str_starts_with($gate['source_integrity'], 'PASS') ? 'green' : 'red'));
        CLI::write("Staging Integrity            : " . CLI::color($gate['staging_integrity'], 'green'));
        CLI::write("Schema Validation            : " . CLI::color($gate['schema_validation'], 'green'));
        CLI::write("Feeder FK Resolution         : " . CLI::color($gate['feeder_fk_resolution'], 'green'));
        CLI::write("Construction FK Resolution   : " . CLI::color($gate['construction_fk_resolution'], 'green'));

        CLI::write("\nStaged Breakdown:");
        CLI::write("  • PASS Rows                : {$c['pass_rows']}");
        CLI::write("  • WARNING Rows             : {$c['warning_rows']}");
        CLI::write("  • REJECT Rows              : {$c['reject_rows']}");

        CLI::write("\nHuman Decision Breakdown:");
        CLI::write("  • Approved Rows            : " . CLI::color((string)$c['approved_rows'], 'green'));
        CLI::write("  • Pending / Needs Review   : " . CLI::color((string)$c['pending_rows'], $c['pending_rows'] > 0 ? 'yellow' : 'green'));
        CLI::write("  • Rejected Rows            : " . CLI::color((string)$c['rejected_rows'], $c['rejected_rows'] > 0 ? 'red' : 'green'));

        CLI::write("\nApproval Signature Integrity : " . CLI::color($gate['signature_integrity'], $gate['signature_integrity'] === 'PASS' ? 'green' : 'yellow'));
        CLI::write("Assets Table Mutation        : " . CLI::color("{$gate['assets_table_writes']} writes (Strictly Read-Only)", 'green'));

        CLI::write("\n------------------------------------------------------------------");
        if ($gate['promotion_eligibility'] === 'UNLOCKED') {
            CLI::write("PROMOTION ELIGIBILITY : " . CLI::color("UNLOCKED", 'green'));
            CLI::write("Certificate Token     : " . CLI::color($gate['certificate_token'], 'green'));
            CLI::write("Stage 5F Readiness    : READY FOR CONTROLLED PROMOTION", 'green');
        } else {
            CLI::write("PROMOTION ELIGIBILITY : " . CLI::color("LOCKED", 'red'));
            CLI::write("Reason(s):", 'yellow');
            foreach ($gate['lock_reasons'] as $lr) {
                CLI::write("  🔒 {$lr}", 'yellow');
            }
        }
        CLI::write("==================================================================\n", 'yellow');

        return $gate['promotion_eligibility'] === 'UNLOCKED' ? 0 : 2;
    }
}
