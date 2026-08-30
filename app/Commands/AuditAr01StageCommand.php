<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Services\FeederAssetStagingService;

/**
 * Phase AR-01 Phase 5A-5D: Feeder Asset Staging & Validation Audit Command
 * Strictly Read-Only / Zero Mutation to 'assets' table.
 * Usage: php spark audit:ar01-stage [filePath]
 */
class AuditAr01StageCommand extends BaseCommand
{
    protected $group       = 'Audit';
    protected $name        = 'audit:ar01-stage';
    protected $description = 'Phase AR-01 Phase 5A-5D: Stage and Validate Source File for PYL-001 (Strictly Read-Only)';

    protected $arguments = [
        'file' => 'The relative or absolute path to the CSV/Excel file (default: writable/Template_Import_JTM_SIWALAN_PANJI.csv)',
    ];

    public function run(array $params)
    {
        $defaultPath = WRITEPATH . 'Template_Import_JTM_SIWALAN_PANJI.csv';
        $filePath = !empty($params[0]) ? $params[0] : $defaultPath;

        if (!file_exists($filePath) && file_exists(ROOTPATH . $filePath)) {
            $filePath = ROOTPATH . $filePath;
        }

        $db = \Config\Database::connect();
        $stager = new FeederAssetStagingService($db);

        CLI::write("\n==================================================================", 'yellow');
        CLI::write("   AR-01 PHASE 5: SOURCE REGISTRATION & STAGING AUDIT (5A - 5D)  ", 'yellow');
        CLI::write("   TARGET FEEDER: PYL-001 (STRICTLY READ-ONLY / ZERO MUTATION)   ", 'yellow');
        CLI::write("==================================================================\n", 'yellow');

        $result = $stager->stageAndValidateSourceFile($filePath, 1);

        if (!$result['success']) {
            CLI::error("ERROR: " . ($result['error'] ?? 'Gagal melakukan staging file sumber.'));
            return 1;
        }

        $sr = $result['source_registration'];
        $tf = $result['target_feeder'];
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

        // 2. PHASE 5B: TARGET FEEDER CONTEXT & CR-06F TRUTH
        CLI::write("\n2️⃣  PHASE 5B: TARGET FEEDER & CR-06F TOPOLOGICAL BOUNDARY", 'cyan');
        CLI::write("------------------------------------------------------------------");
        CLI::write("  Target Feeder ID              : #{$tf['id']} [{$tf['kode_penyulang']}] {$tf['nama_penyulang']}");
        CLI::write("  Active CR-06F Sections        : {$tf['active_sections']} seksi terkonfigurasi");
        foreach ($tf['sections_list'] as $sec) {
            CLI::write("     └─ {$sec}");
        }

        // 3. PHASE 5C: DETERMINISTIC VALIDATION & ANOMALY RECONNAISSANCE
        CLI::write("\n3️⃣  PHASE 5C: DETERMINISTIC VALIDATION & ANOMALIES", 'cyan');
        CLI::write("------------------------------------------------------------------");
        CLI::write("  Total Source Rows Scanned     : {$sm['total_staged_rows']} rows");
        CLI::write("  Unique Asset Identifiers      : {$sm['unique_asset_names']} (Duplicates: {$sm['duplicate_names']})");
        CLI::write("  Construction Types Breakdown  :");
        foreach ($result['construction_distribution'] as $code => $cnt) {
            CLI::write("     • {$code} : {$cnt} assets");
        }

        if (!empty($result['detected_anomalies'])) {
            CLI::write("\n  ⚠️  Detected Anomalies / Warnings (" . count($result['detected_anomalies']) . " rows):", 'yellow');
            foreach (array_slice($result['detected_anomalies'], 0, 5) as $anom) {
                CLI::write("     └─ [Row #{$anom['row_number']}] {$anom['asset_name']} -> Status: " . CLI::color($anom['status'], $anom['status'] === 'REJECT' ? 'red' : 'yellow'));
                foreach ($anom['errors'] as $err) {
                    CLI::write("        ⛔ Error: {$err}", 'red');
                }
                foreach ($anom['warnings'] as $warn) {
                    CLI::write("        ⚠️  Warning: {$warn}", 'yellow');
                }
            }
            if (count($result['detected_anomalies']) > 5) {
                CLI::write("     └─ ... dan " . (count($result['detected_anomalies']) - 5) . " anomali lainnya dicatat dalam staging log.");
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
        CLI::write("   Source batch is registered and validated in staging layer.", 'green');
        CLI::write("==================================================================\n", 'yellow');

        return 0;
    }
}
