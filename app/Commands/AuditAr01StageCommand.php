<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Services\FeederAssetStagingService;

/**
 * Phase AR-01 Phase 5A-5D: Feeder Asset Staging & Multi-Source Validation Audit Command
 * Strictly Read-Only / Zero Mutation to 'assets' table.
 * Usage: php spark audit:ar01-stage [filePath] [--feeder=1]
 */
class AuditAr01StageCommand extends BaseCommand
{
    protected $group       = 'Audit';
    protected $name        = 'audit:ar01-stage';
    protected $description = 'Phase AR-01 Phase 5A-5D: Stage and Validate Source File (Strictly Read-Only)';

    protected $arguments = [
        'file' => 'The relative or absolute path to the CSV/Excel file (default: writable/Template_Import_JTM_SIWALAN_PANJI.csv)',
    ];

    protected $options = [
        'feeder' => 'Filter and validate strictly for a specific Feeder ID (e.g. --feeder=1 for PYL-001)',
    ];

    public function run(array $params)
    {
        $defaultPath = WRITEPATH . 'Template_Import_JTM_SIWALAN_PANJI.csv';
        $multiPath   = WRITEPATH . 'Template_Import_MULTI_PENYULANG_PART1.csv';
        
        $filePath = !empty($params[0]) ? $params[0] : (file_exists($multiPath) ? $multiPath : $defaultPath);

        if (!file_exists($filePath) && file_exists(ROOTPATH . $filePath)) {
            $filePath = ROOTPATH . $filePath;
        }

        $targetFeederId = $this->getOption('feeder');
        $targetFeederId = ($targetFeederId !== null && is_numeric($targetFeederId)) ? (int)$targetFeederId : null;

        $db = \Config\Database::connect();
        $stager = new FeederAssetStagingService($db);

        CLI::write("\n==================================================================", 'yellow');
        CLI::write("   AR-01 PHASE 5: SOURCE REGISTRATION & STAGING AUDIT (5A - 5D)  ", 'yellow');
        CLI::write("   STRICTLY READ-ONLY / ZERO MUTATION TO 'assets' TABLE           ", 'yellow');
        CLI::write("==================================================================\n", 'yellow');

        // Check for duplicate files in writable folder to demonstrate identical file protection
        $sampleDuplicateCheck = [
            $filePath,
            WRITEPATH . 'Template_Import_SEMUA_PENYULANG.csv',
            WRITEPATH . 'Template_Import_SEPARO_PENYULANG.csv',
        ];
        $existingFilesToCheck = array_values(array_filter($sampleDuplicateCheck, 'file_exists'));

        if (count($existingFilesToCheck) > 1) {
            $recon = $stager->reconcileSourceFiles($existingFilesToCheck);
            if ($recon['has_identical_files']) {
                CLI::write("🛡️  SOURCE FILE DUPLICATION & IDENTICAL FILE PROTECTION (AR-01-P5-D)", 'red');
                CLI::write("------------------------------------------------------------------");
                foreach ($recon['duplicate_files'] as $df) {
                    CLI::write("  • Duplicate Detected : " . CLI::color($df['duplicate_file']['file_name'], 'yellow'));
                    CLI::write("    └─ SHA-256 Checksum : {$df['duplicate_file']['sha256']}");
                    CLI::write("    └─ Data Rows        : {$df['duplicate_file']['data_rows']} rows");
                    CLI::write("    └─ Matches Original : " . CLI::color($df['original_file']['file_name'], 'cyan'));
                    CLI::write("    └─ Resolution Action: " . CLI::color("SKIPPED_DUPLICATE_SOURCE (0 WRITES)", 'green'));
                }
                CLI::write("------------------------------------------------------------------\n");
            }
        }

        $result = $stager->stageAndValidateSourceFile($filePath, $targetFeederId);

        if (!$result['success']) {
            CLI::error("ERROR: " . ($result['error'] ?? 'Gagal melakukan staging file sumber.'));
            return 1;
        }

        $sr = $result['source_registration'];
        $tf = $result['target_feeder'] ?? null;
        $sm = $result['staging_summary'];
        $dmg = $result['database_mutation_guard'];

        // 1. PHASE 5A: SOURCE REGISTRATION & FINGERPRINTING
        CLI::write("1️⃣  PHASE 5A: SOURCE REGISTRATION & FINGERPRINTING (AR-01-P5-I)", 'cyan');
        CLI::write("------------------------------------------------------------------");
        CLI::write("  Source File Path              : " . CLI::color($sr['source_file'], 'white'));
        CLI::write("  File Size                     : " . number_format($sr['file_size']) . " bytes");
        CLI::write("  Source SHA-256 Fingerprint    : " . CLI::color($sr['file_sha256'], 'green'));
        CLI::write("  Ingestion Batch ID            : " . CLI::color($sr['ingestion_batch_id'], 'yellow'));
        CLI::write("  Source Immutability Invariant : " . CLI::color($sr['source_immutability'], 'green'));

        // 2. PHASE 5B: FEEDER SCOPE & TOPOLOGICAL BOUNDARY
        CLI::write("\n2️⃣  PHASE 5B: FEEDER SCOPE & REGISTRY BREAKDOWN", 'cyan');
        CLI::write("------------------------------------------------------------------");
        if ($tf) {
            CLI::write("  Target Feeder ID              : #{$tf['id']} [{$tf['kode_penyulang']}] {$tf['nama_penyulang']}");
            CLI::write("  Active CR-06F Sections        : {$tf['active_sections']} seksi terkonfigurasi");
            foreach ($tf['sections_list'] as $sec) {
                CLI::write("     └─ {$sec}");
            }
        } else {
            CLI::write("  Scope Mode                    : " . CLI::color("MULTI-FEEDER DATASET", 'yellow') . " ({$result['total_feeders_found']} Feeders Detected)");
            CLI::write("  Top Feeders in Dataset        :");
            $topFeeders = array_slice($result['feeder_distribution'], 0, 10);
            foreach ($topFeeders as $fname => $fcnt) {
                CLI::write("     • {$fname} : {$fcnt} assets");
            }
            if (count($result['feeder_distribution']) > 10) {
                CLI::write("     • ... dan " . (count($result['feeder_distribution']) - 10) . " penyulang lainnya.");
            }
        }

        // 3. PHASE 5C: DETERMINISTIC VALIDATION & ANOMALY RECONNAISSANCE
        CLI::write("\n3️⃣  PHASE 5C: DETERMINISTIC VALIDATION & ANOMALIES", 'cyan');
        CLI::write("------------------------------------------------------------------");
        CLI::write("  Total Source Rows Scanned     : {$sm['total_staged_rows']} rows");
        CLI::write("  Unique Asset Identifiers      : {$sm['unique_asset_names']} (Duplicates: {$sm['duplicate_names']})");
        CLI::write("  Top Construction Types        :");
        $topConst = array_slice($result['construction_distribution'], 0, 8);
        foreach ($topConst as $code => $cnt) {
            CLI::write("     • {$code} : {$cnt} assets");
        }

        if (!empty($result['detected_anomalies'])) {
            CLI::write("\n  ⚠️  Sample Anomalies / Warnings (" . count($result['detected_anomalies']) . " rows):", 'yellow');
            foreach (array_slice($result['detected_anomalies'], 0, 5) as $anom) {
                $feederLabel = !empty($anom['feeder_name']) ? " [{$anom['feeder_name']}]" : "";
                CLI::write("     └─ [Row #{$anom['row_number']}]{$feederLabel} {$anom['asset_name']} -> Status: " . CLI::color($anom['status'], $anom['status'] === 'REJECT' ? 'red' : 'yellow'));
                foreach ($anom['errors'] as $err) {
                    CLI::write("        ⛔ Error: {$err}", 'red');
                }
                foreach ($anom['warnings'] as $warn) {
                    CLI::write("        ⚠️  Warning: {$warn}", 'yellow');
                }
            }
            if (count($result['detected_anomalies']) > 5) {
                CLI::write("     └─ ... dan " . (count($result['detected_anomalies']) - 5) . " anomali lainnya tercatat dalam staging audit log.");
            }
        } else {
            CLI::write("  Anomalies Detected            : " . CLI::color("0 anomalies (Clean)", 'green'));
        }

        // 4. PHASE 5D: STAGING REVIEW QUEUE SUMMARY
        CLI::write("\n4️⃣  PHASE 5D: STAGING REVIEW QUEUE SUMMARY", 'cyan');
        CLI::write("------------------------------------------------------------------");
        CLI::write("  • PASS (Ready for Review)     : " . CLI::color("{$sm['pass_candidates']} assets", 'green'));
        CLI::write("  • WARNING (Needs Attention)   : " . CLI::color("{$sm['warning_candidates']} assets", 'yellow'));
        CLI::write("  • REJECT (Schema/FK Violation): " . CLI::color("{$sm['reject_candidates']} assets", $sm['reject_candidates'] > 0 ? 'red' : 'green'));

        // 5. WRITE GATE & IMMUTABILITY VERIFICATION
        CLI::write("\n5️⃣  WRITE GATE & DATABASE MUTATION GUARD (Stage 5E/5F Invariants)", 'cyan');
        CLI::write("------------------------------------------------------------------");
        CLI::write("  Master Assets Table Mutation  : " . CLI::color("{$dmg['assets_table_writes']} writes ({$dmg['assets_table_mutations']})", 'green'));
        CLI::write("  Promotion Gate Status         : " . CLI::color($dmg['promotion_gate'], 'yellow'));

        CLI::write("\n==================================================================", 'yellow');
        CLI::write("🟢 STAGING AUDIT COMPLETE: ZERO WRITES TO 'assets' TABLE", 'green');
        CLI::write("   Source dataset is registered and staged safely.", 'green');
        CLI::write("==================================================================\n", 'yellow');

        return 0;
    }
}
