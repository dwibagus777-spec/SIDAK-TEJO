<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Services\FieldSectionResolutionService;

/**
 * Phase AR-01 Phase 5G: Field Section Verification Command
 * Usage: php spark ar01:verify-section --asset=151 --section=2 --user=198501012010011001 --reason="Survey lapangan tiang KM 2" [--sequence=12]
 */
class Ar01VerifySectionCommand extends BaseCommand
{
    protected $group       = 'Audit';
    protected $name        = 'ar01:verify-section';
    protected $description = 'AR-01 Phase 5G: Record Field Engineering Section Verification with Audit Trail';

    protected $options = [
        'asset'    => 'Single Asset ID to verify',
        'assets'   => 'Comma-separated Asset IDs for bulk verification',
        'section'  => 'Target Section ID (must belong to asset parent feeder)',
        'sequence' => 'Field sequence order (optional integer)',
        'user'     => 'NIP / Identity of the field verification engineer',
        'reason'   => 'Field survey reason / justification',
    ];

    /**
     * Multi-tier robust option extractor for CodeIgniter 4.7.4 CLI.
     */
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
        $assetId    = $this->extractOption($params, 'asset');
        $assetsList = $this->extractOption($params, 'assets');
        $sectionId  = $this->extractOption($params, 'section');
        $sequence   = $this->extractOption($params, 'sequence');
        $user       = $this->extractOption($params, 'user');
        $reason     = $this->extractOption($params, 'reason');

        if (!$sectionId || !$user || !$reason || (!$assetId && !$assetsList)) {
            CLI::error("ERROR: Parameter tidak lengkap.");
            CLI::write("Penggunaan:");
            CLI::write('  php spark ar01:verify-section --asset=151 --section=2 --user=198501012010011001 --reason="Survey lapangan tiang KM 2" [--sequence=12]', 'yellow');
            CLI::write('  php spark ar01:verify-section --assets=151,152,153 --section=2 --user=198501012010011001 --reason="Survey lapangan"', 'yellow');
            return 1;
        }

        $db = \Config\Database::connect();
        $sectionService = new FieldSectionResolutionService($db);

        // Single Asset Mode
        if ($assetId) {
            $res = $sectionService->verifyAssetSection(
                (int)$assetId,
                (int)$sectionId,
                $user,
                $reason,
                $sequence !== null ? (int)$sequence : null
            );

            if (!$res['success']) {
                CLI::error("VERIFIKASI GAGAL: " . $res['error']);
                return 1;
            }

            CLI::write("\n==================================================================", 'green');
            CLI::write("🟢 AR-01 PHASE 5G: FIELD SECTION VERIFICATION RECORDED            ", 'green');
            CLI::write("==================================================================\n", 'green');
            CLI::write("Asset ID            : #" . CLI::color((string)$res['asset_id'], 'yellow') . " ({$res['asset_name']})");
            CLI::write("Penyulang ID        : #{$res['penyulang_id']}");
            CLI::write("Section Baru        : #" . CLI::color((string)$res['new_section_id'], 'green') . " [{$res['section_name']}]");
            if ($res['old_section_id']) {
                CLI::write("Section Sebelumnya  : #{$res['old_section_id']}");
            }
            if ($res['field_sequence'] !== null) {
                CLI::write("Field Sequence      : Posisi urutan ke-" . CLI::color((string)$res['field_sequence'], 'yellow'));
            }
            CLI::write("Verified By         : {$res['verified_by']}");
            CLI::write("Verified Timestamp  : {$res['verified_at']}");
            CLI::write("Resolution Method   : " . CLI::color($res['resolution_method'], 'cyan'));
            CLI::write("Audit Trail         : " . CLI::color("Immutably logged in 'asset_section_history'", 'green'));
            CLI::write("==================================================================\n", 'green');

            return 0;
        }

        // Bulk Assets Mode
        if ($assetsList) {
            $ids = array_map('intval', explode(',', $assetsList));
            $res = $sectionService->bulkVerifyAssetSection($ids, (int)$sectionId, $user, $reason);

            if (!$res['success']) {
                CLI::error("BULK VERIFIKASI GAGAL: " . $res['error']);
                if (!empty($res['error_details'])) {
                    foreach ($res['error_details'] as $ed) {
                        CLI::write("  • {$ed}", 'red');
                    }
                }
                return 1;
            }

            CLI::write("\n==================================================================", 'green');
            CLI::write("🟢 AR-01 PHASE 5G: BULK FIELD SECTION VERIFICATION RECORDED       ", 'green');
            CLI::write("==================================================================\n", 'green');
            CLI::write("Verified Assets Count : " . CLI::color("{$res['verified_count']} assets", 'green'));
            CLI::write("Target Section ID     : #{$res['new_section_id']}");
            CLI::write("Verified By           : {$res['verified_by']}");
            CLI::write("==================================================================\n", 'green');

            return 0;
        }

        return 0;
    }
}
