<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Services\FieldSectionResolutionService;

/**
 * Phase AR-01 Phase 5G: Field Section Verification Command
 * Usage (List Assets): php spark ar01:verify-section --list --feeder=15 [--limit=20]
 * Usage (Verify Single): php spark ar01:verify-section --asset=3001 --section=2 --user=198501012010011001 --reason="Survey tiang" [--sequence=1]
 * Usage (Verify Code):   php spark ar01:verify-section --code=ECCO_01 --section=2 --user=198501012010011001 --reason="Survey tiang"
 */
class Ar01VerifySectionCommand extends BaseCommand
{
    protected $group       = 'Audit';
    protected $name        = 'ar01:verify-section';
    protected $description = 'AR-01 Phase 5G: Record Field Engineering Section Verification with Audit Trail';

    protected $options = [
        'list'     => 'Flag to list actual asset IDs and names for a feeder',
        'feeder'   => 'Feeder ID (required for --list)',
        'asset'    => 'Single Asset ID or Code to verify',
        'code'     => 'Asset Code to verify (e.g. ECCO_01)',
        'assets'   => 'Comma-separated Asset IDs or Codes for bulk verification',
        'section'  => 'Target Section ID (must belong to asset parent feeder)',
        'sequence' => 'Field sequence order (optional integer)',
        'user'     => 'NIP / Identity of the field verification engineer',
        'reason'   => 'Field survey reason / justification',
        'limit'    => 'Limit number of assets to list (default: 50)',
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
                if ($arg === "--{$key}" || $arg === "--{$altKey}") {
                    if (isset($argv[$i + 1]) && !str_starts_with($argv[$i + 1], '--')) {
                        return trim($argv[$i + 1], " \"'");
                    }
                }
            }
        }

        return $default;
    }

    protected function hasFlag(array $params, string $flag): bool
    {
        if (array_key_exists($flag, $params)) return true;
        if (CLI::getOption($flag) !== null) return true;
        $allOpts = CLI::getOptions();
        if (array_key_exists($flag, $allOpts)) return true;
        if (in_array("--{$flag}", $params, true)) return true;
        if (isset($_SERVER['argv']) && in_array("--{$flag}", $_SERVER['argv'], true)) return true;
        return false;
    }

    public function run(array $params)
    {
        $db = \Config\Database::connect();
        $sectionService = new FieldSectionResolutionService($db);

        $isList     = $this->hasFlag($params, 'list');
        $feederId   = $this->extractOption($params, 'feeder');
        $assetId    = $this->extractOption($params, 'asset') ?? $this->extractOption($params, 'code');
        $assetsList = $this->extractOption($params, 'assets');
        $sectionId  = $this->extractOption($params, 'section');
        $sequence   = $this->extractOption($params, 'sequence');
        $user       = $this->extractOption($params, 'user');
        $reason     = $this->extractOption($params, 'reason');
        $limit      = (int)($this->extractOption($params, 'limit', '50'));

        // Mode 1: List Assets in Feeder
        if ($isList) {
            if (!$feederId) {
                CLI::error("ERROR: Parameter --feeder=<ID> wajib disertakan untuk melihat daftar aset.");
                CLI::write("Contoh: php spark ar01:verify-section --list --feeder=15 --limit=25", 'yellow');
                return 1;
            }

            $assets = $sectionService->getFeederAssetsList((int)$feederId, $limit);
            if (empty($assets)) {
                CLI::write("Tidak ada aset aktif yang terdaftar pada Penyulang ID #{$feederId}.", 'yellow');
                return 0;
            }

            CLI::write("\n==================================================================", 'green');
            CLI::write("    DAFTAR ASET PRODUKSI — PENYULANG ID #{$feederId}              ", 'green');
            CLI::write("==================================================================\n", 'green');
            CLI::write(sprintf("%-8s | %-25s | %-25s | %-25s", "PK ID", "Kode Asset", "Nama Asset", "Section Terpasang"));
            CLI::write(str_repeat("-", 90));

            foreach ($assets as $a) {
                $secText = $a['section_id'] ? "[#{$a['section_id']}] {$a['section_name']}" : "UNRESOLVED";
                CLI::write(sprintf("#%-7d | %-25s | %-25s | %s", $a['id'], $a['kode_asset'], $a['nama_asset'], CLI::color($secText, $a['section_id'] ? 'green' : 'yellow')));
            }
            CLI::write(str_repeat("-", 90));
            CLI::write("Total ditampilkan: " . count($assets) . " aset.");
            CLI::write("Gunakan Primary Key ID di atas untuk menjalankan verifikasi seksi.\n");
            return 0;
        }

        // Mode 2: Verify Single / Bulk
        if (!$sectionId || !$user || !$reason || (!$assetId && !$assetsList)) {
            CLI::error("ERROR: Parameter tidak lengkap.");
            CLI::write("Penggunaan:");
            CLI::write('  1. Lihat ID Aset  : php spark ar01:verify-section --list --feeder=<FEEDER_ID>', 'yellow');
            CLI::write('  2. Verifikasi ID   : php spark ar01:verify-section --asset=3001 --section=1 --user=198501012010011001 --reason="Survey tiang" [--sequence=1]', 'yellow');
            CLI::write('  3. Verifikasi Kode : php spark ar01:verify-section --asset=ECCO_01 --section=1 --user=198501012010011001 --reason="Survey tiang"', 'yellow');
            CLI::write('  4. Verifikasi Bulk : php spark ar01:verify-section --assets=3001,3002,3003 --section=1 --user=198501012010011001 --reason="Survey seksi"', 'yellow');
            return 1;
        }

        // Single Asset Mode
        if ($assetId) {
            $res = $sectionService->verifyAssetSection(
                $assetId,
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
            CLI::write("Asset ID (PK)       : #" . CLI::color((string)$res['asset_id'], 'yellow') . " ({$res['asset_name']})");
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
            $identifiers = array_filter(array_map('trim', explode(',', $assetsList)));
            $res = $sectionService->bulkVerifyAssetSection($identifiers, (int)$sectionId, $user, $reason);

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
