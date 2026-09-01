<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Services\SpatialSectionCandidateService;

/**
 * AR-01 Phase 5G.4R.2: Root-Cause Forensic Diagnostic CLI Command
 * Usage: php spark ar01:candidates:diagnose [FEEDER_ID] [--json]
 */
class Ar01CandidatesDiagnoseCommand extends BaseCommand
{
    protected $group       = 'Audit';
    protected $name        = 'ar01:candidates:diagnose';
    protected $description = 'AR-01 Phase 5G.4R.2: Root-Cause Landmark & Topology Diagnostic for Feeder Assets';

    protected $arguments = [
        'feeder' => 'Feeder ID or Code (e.g. 4, 19, 15, PYL-004)',
    ];

    protected $options = [
        'feeder' => 'Feeder ID or Code (alternative option)',
        'json'   => 'Output raw machine-readable Diagnostic JSON',
    ];

    public function run(array $params)
    {
        $db = \Config\Database::connect();
        $candidateService = new SpatialSectionCandidateService($db);

        $feederArg = null;
        foreach ($params as $p) {
            if (!str_starts_with($p, '-')) {
                $feederArg = $p;
                break;
            }
        }
        if ($feederArg === null) {
            $feederArg = CLI::getOption('feeder');
        }

        $isJson = (bool)(CLI::getOption('json') ?? false);

        if ($feederArg === null) {
            CLI::error("Harap tentukan Feeder ID atau Kode Penyulang (misal: php spark ar01:candidates:diagnose 4).");
            return 1;
        }

        // Resolve Feeder ID
        $feederId = null;
        $feederBuilder = $db->table('penyulang');
        if (is_numeric($feederArg)) {
            $feederBuilder->where('id', (int)$feederArg);
        } else {
            $feederBuilder->where('kode_penyulang', (string)$feederArg);
        }
        $getFeeder = $feederBuilder->get();
        $feeder = $getFeeder ? $getFeeder->getRowArray() : null;

        if ($feeder) {
            $feederId = (int)$feeder['id'];
        }

        if ($feederId === null) {
            CLI::error("Penyulang '{$feederArg}' tidak ditemukan di database.");
            return 1;
        }

        $diag = $candidateService->diagnoseFeeder($feederId);

        if ($isJson) {
            CLI::write(json_encode($diag, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return 0;
        }

        if (!$diag['success']) {
            CLI::error("DIAGNOSTIC ERROR: " . ($diag['error'] ?? 'Gagal menjalankan diagnostic.'));
            return 1;
        }

        CLI::write("\n==============================================================", 'cyan');
        CLI::write("AR-01 PHASE 5G.4R.2: ROOT-CAUSE EVIDENCE DIAGNOSTIC", 'cyan');
        CLI::write("==============================================================", 'cyan');
        CLI::write("FEEDER        : [{$diag['feeder']['kode_penyulang']}] {$diag['feeder']['nama_penyulang']} (ID: #{$feederId})", 'yellow');
        CLI::write("TOTAL ASSETS  : {$diag['feeder']['total_assets']} assets in feeder", 'yellow');
        CLI::write("TOTAL SECTIONS: {$diag['feeder']['total_sections']} sections configured", 'yellow');
        CLI::write("MUTATION      : ZERO (Strictly Read-Only Diagnostic)\n", 'green');

        CLI::write("1. REZUME STATUS RESOLVER LANDMARK:", 'cyan');
        CLI::write(sprintf("  • Total Landmarks Analyzed : %d", $diag['statistics']['total_landmarks']));
        CLI::write(sprintf("  • Landmarks Resolved (GPS) : %s", CLI::color((string)$diag['statistics']['resolved_landmarks'], $diag['statistics']['resolved_landmarks'] > 0 ? 'green' : 'red')));
        CLI::write(sprintf("  • Landmarks Unresolved     : %s", CLI::color((string)$diag['statistics']['unresolved_landmarks'], $diag['statistics']['unresolved_landmarks'] > 0 ? 'yellow' : 'green')));
        CLI::write(sprintf("  • Potential Switch Devices : %d found in assets table\n", $diag['statistics']['potential_devices']));

        CLI::write("2. POTENTIAL SWITCHING DEVICES FOUND IN FEEDER:", 'cyan');
        if (empty($diag['potential_devices_found'])) {
            CLI::write("  [NONE] Tidak ada aset switching/recloser/LBS ber-kode/ber-tipe switching pada feeder ini.", 'red');
        } else {
            CLI::write(str_repeat("-", 90));
            CLI::write(sprintf("%-8s | %-20s | %-12s | %-25s | %-12s", "PK ID", "Kode Asset", "Jenis Asset", "Nama Asset", "GPS Status"));
            CLI::write(str_repeat("-", 90));
            foreach ($diag['potential_devices_found'] as $pd) {
                $gpsStatus = ($pd['latitude'] !== null && $pd['longitude'] !== null && ($pd['latitude'] != 0 || $pd['longitude'] != 0)) ? CLI::color("GPS VALID", "green") : CLI::color("NO GPS", "red");
                CLI::write(sprintf(
                    "#%-7d | %-20s | %-12s | %-25s | %s",
                    $pd['id'],
                    mb_strimwidth($pd['kode_asset'], 0, 20, '...'),
                    mb_strimwidth($pd['jenis_asset'], 0, 12, '...'),
                    mb_strimwidth($pd['nama_asset'], 0, 25, '...'),
                    $gpsStatus
                ));
            }
            CLI::write(str_repeat("-", 90));
        }

        CLI::write("\n3. SECTION-BY-SECTION LANDMARK MATCHING MATRIX:", 'cyan');
        foreach ($diag['sections_diagnostic'] as $sd) {
            CLI::write("\n" . str_repeat("=", 80), 'yellow');
            CLI::write(sprintf("SECTION #%d: %s (Seq: %d, Landmarks: %d)", $sd['section_id'], $sd['section_name'], $sd['sequence_order'], $sd['landmarks_count']), 'yellow');
            CLI::write(str_repeat("-", 80));

            foreach ($sd['parsed_landmarks'] as $lm) {
                $statusColor = ($lm['match_status'] === 'MATCH_FOUND_GPS_VALID') ? 'green' : (($lm['match_status'] === 'MATCH_FOUND_MISSING_GPS') ? 'yellow' : 'red');
                CLI::write(sprintf("  [%-12s] %s", $lm['role'], $lm['raw_label']), 'cyan');
                CLI::write(sprintf("    • Device Family : %s (%s)", $lm['device_type_family'], $lm['device_type']));
                CLI::write(sprintf("    • Tokens        : [%s]", implode(', ', $lm['tokens'])));
                CLI::write(sprintf("    • Match Status  : %s", CLI::color($lm['match_status'], $statusColor)));
                CLI::write(sprintf("    • Match Mode    : %s", $lm['match_mode']));
                CLI::write(sprintf("    • Reason / Info : %s", $lm['diagnostic_reason']));
                if ($lm['matched_asset']) {
                    $gpsStr = "({$lm['matched_asset']['latitude']}, {$lm['matched_asset']['longitude']})";
                    CLI::write(sprintf("    • Matched Asset : #%d [%s] - %s @ GPS: %s", $lm['matched_asset']['asset_id'], $lm['matched_asset']['kode_asset'], $lm['matched_asset']['nama_asset'], $gpsStr), 'green');
                }
            }
        }

        CLI::write("\n==============================================================", 'cyan');
        CLI::write("DIAGNOSTIC COMPLETE: Data evidence telah diaudit secara transparan.", 'green');
        CLI::write("==============================================================\n", 'cyan');
        return 0;
    }
}
