<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Services\NetworkConfigurationIngestionService;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * CLI Command for CR-06F Network Configuration Ingestion & Pre-Flight Validation.
 * Usage:
 *   php spark network-config:ingest <filepath> [--dry-run]
 *   php spark network-config:sample-pilot [--output=<path>]
 */
class NetworkConfigIngestCommand extends BaseCommand
{
    protected $group       = 'CR-06F';
    protected $name        = 'network-config:ingest';
    protected $description = 'Ingests or pre-flights physical network configuration from Excel (Contract v1.1.1).';
    protected $usage       = 'network-config:ingest <filepath> [options]';
    protected $arguments   = [
        'filepath' => 'Path to the Excel file containing network configurations.',
    ];
    protected $options     = [
        '--dry-run' => 'Run pre-flight validation only without database mutation.',
        '--pilot'   => 'Generate and ingest pilot sample for Penyulang CANDRAMAS.',
    ];

    public function run(array $params)
    {
        CLI::write("==================================================================", "yellow");
        CLI::write("    CR-06F PHYSICAL NETWORK CONFIGURATION INGESTION ENGINE        ", "yellow");
        CLI::write("==================================================================", "yellow");
        CLI::newLine();

        $db = \Config\Database::connect();
        $ingestService = new NetworkConfigurationIngestionService($db);

        $isPilot  = array_key_exists('pilot', $params) || CLI::getOption('pilot');
        $isDryRun = array_key_exists('dry-run', $params) || CLI::getOption('dry-run');
        $filePath = $params[0] ?? CLI::getOption('filepath');

        if ($isPilot && empty($filePath)) {
            $filePath = WRITEPATH . 'pilot_candramas_v1.1.xlsx';
            $this->generatePilotExcelFile($filePath);
            CLI::write("📄 Generated Pilot Excel File: {$filePath}", "cyan");
            CLI::newLine();
        }

        if (empty($filePath)) {
            CLI::write("❌ Error: File path parameter is required.", "red");
            CLI::write("Usage: php spark network-config:ingest <path-to-excel> [--dry-run]", "white");
            CLI::write("   or: php spark network-config:ingest --pilot [--dry-run]", "white");
            return 1;
        }

        if (!is_file($filePath)) {
            CLI::write("❌ File not found: {$filePath}", "red");
            return 1;
        }

        CLI::write("Target File : " . realpath($filePath), "white");
        CLI::write("Execution   : " . ($isDryRun ? "DRY-RUN (Pre-Flight Preview)" : "ATOMIC COMMIT"), $isDryRun ? "yellow" : "green");
        CLI::newLine();

        if ($isDryRun) {
            CLI::write("🔍 Running Pre-Flight Validation Preview...", "cyan");
            $preview = $ingestService->previewFromExcel($filePath);

            if (!$preview['success']) {
                CLI::write("❌ PRE-FLIGHT VALIDATION FAILED (Fail-Closed)", "red");
                CLI::newLine();
                CLI::write("Errors:", "red");
                foreach ($preview['errors'] as $err) {
                    CLI::write("  • {$err}", "red");
                }
                return 1;
            }

            $s = $preview['summary'];
            CLI::write("✅ PRE-FLIGHT VALIDATION PASSED (100% Valid)", "green");
            CLI::newLine();
            CLI::write("------------------------------------------------------------------", "white");
            CLI::write("                      BATCH PREVIEW SUMMARY                       ", "yellow");
            CLI::write("------------------------------------------------------------------", "white");
            CLI::write(sprintf("  Total Sections in File    : %d", $s['total_sections_found']), "white");
            CLI::write(sprintf("  Valid Sections Resolved   : %d", $s['valid_sections_count']), "green");
            CLI::write(sprintf("  Rejected Sections         : %d", $s['rejected_sections_count']), $s['rejected_sections_count'] === 0 ? "green" : "red");
            CLI::write(sprintf("  Total Conductor Segments  : %d", $s['total_conductor_segments']), "white");
            CLI::write(sprintf("  Total Conductor Length    : %.2f kms (%.1f m)", $s['total_conductor_length_m'] / 1000, $s['total_conductor_length_m']), "cyan");
            
            CLI::write("  Accessories Breakdown     :", "white");
            foreach ($s['accessories_breakdown'] as $type => $qty) {
                CLI::write(sprintf("    - %-20s : %d units", $type, $qty), "cyan");
            }
            if (empty($s['accessories_breakdown'])) {
                CLI::write("    - None", "white");
            }

            CLI::newLine();
            CLI::write("  Hardening Gates Checks    :", "white");
            CLI::write(sprintf("    - Gate F3A (Sequence 1..N)       : %s", $s['sequence_violations'] === 0 ? "PASS (0 Violations)" : "FAIL ({$s['sequence_violations']} Violations)"), $s['sequence_violations'] === 0 ? "green" : "red");
            CLI::write(sprintf("    - Gate F3 (Topology Continuity)  : %s", $s['topology_discontinuity'] === 0 ? "PASS (0 Discontinuity)" : "FAIL ({$s['topology_discontinuity']} Discontinuities)"), $s['topology_discontinuity'] === 0 ? "green" : "red");
            CLI::write(sprintf("    - Gate F4 (Material Validity)    : %s", $s['invalid_materials'] === 0 ? "PASS (0 Invalid Materials)" : "FAIL ({$s['invalid_materials']} Invalid)"), $s['invalid_materials'] === 0 ? "green" : "red");
            CLI::write(sprintf("    - Domain Invariant IX (Transline): %s", $s['domain_invariant_ix']), $s['domain_invariant_ix'] === 'PASS' ? "green" : "red");
            CLI::write("------------------------------------------------------------------", "white");
            CLI::newLine();
            CLI::write("ℹ️  Dry-run complete. Zero database modifications made.", "yellow");
            CLI::write("To commit this batch, run without --dry-run flag.", "green");
            return 0;
        }

        // ATOMIC COMMIT EXECUTION
        CLI::write("⚡ Executing Atomic Database Ingestion...", "cyan");
        try {
            $result = $ingestService->ingestFromExcel($filePath, 1);

            if (!$result['success']) {
                CLI::write("❌ INGESTION REJECTED & ROLLED BACK", "red");
                CLI::newLine();
                CLI::write("Errors:", "red");
                foreach ($result['errors'] as $err) {
                    CLI::write("  • {$err}", "red");
                }
                return 1;
            }

            CLI::write("==================================================================", "green");
            CLI::write("             INGESTION COMMITTED SUCCESSFULLY                     ", "green");
            CLI::write("==================================================================", "green");
            CLI::write(sprintf("  Batch ID            : %d", $result['batch_id']), "white");
            CLI::write(sprintf("  Batch UUID          : %s", $result['batch_uuid']), "cyan");
            CLI::write(sprintf("  Committed Sections  : %d", $result['committed_sections']), "green");
            CLI::write(sprintf("  Status              : %s", $result['status']), "green");
            CLI::newLine();
            CLI::write("Next Step: Run 'php spark audit:cr06f' to verify operational coverage.", "yellow");

            return 0;
        } catch (\Throwable $e) {
            CLI::write("❌ Ingestion exception: " . $e->getMessage(), "red");
            return 1;
        }
    }

    private function generatePilotExcelFile(string $targetPath): void
    {
        $spreadsheet = new Spreadsheet();

        // Sheet 1: SECTION_CONFIGURATIONS
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('SECTION_CONFIGURATIONS');
        $headers1 = [
            'SECTION_REF', 'KODE_ULP', 'KODE_PENYULANG', 'NAMA_SECTION', 
            'IMPORT_ACTION', 'CONFIGURATION_SOURCE', 'CHANGE_REASON', 'EFFECTIVE_FROM'
        ];
        $sheet1->fromArray([$headers1], null, 'A1');
        $data1 = [
            ['SEC-CDR-001', '51301', 'CDR', 'Section A CANDRAMAS', 'ACTIVATE_NEW_VERSION', 'INITIAL_AUDIT', 'Pilot Operational Activation 2026', date('Y-m-d H:i:s')],
            ['SEC-CDR-002', '51301', 'CDR', 'Section B CANDRAMAS', 'ACTIVATE_NEW_VERSION', 'INITIAL_AUDIT', 'Pilot Operational Activation 2026', date('Y-m-d H:i:s')],
            ['SEC-CDR-003', '51301', 'CDR', 'Section C CANDRAMAS', 'ACTIVATE_NEW_VERSION', 'INITIAL_AUDIT', 'Pilot Operational Activation 2026', date('Y-m-d H:i:s')],
        ];
        $sheet1->fromArray($data1, null, 'A2');

        // Sheet 2: CONDUCTOR_SEGMENTS
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('CONDUCTOR_SEGMENTS');
        $headers2 = [
            'SECTION_REF', 'SEQUENCE_ORDER', 'KODE_MATERIAL_KONDUKTOR', 'PANJANG_METER', 
            'START_NODE', 'END_NODE', 'SEGMENT_LABEL'
        ];
        $sheet2->fromArray([$headers2], null, 'A1');
        $data2 = [
            ['SEC-CDR-001', 1, 'AAACS 240', 350.0, 'GI_CANDRAMAS', 'PB01', 'Saluran Keluar GI Trunk 1'],
            ['SEC-CDR-001', 2, 'AAAC 150', 650.0, 'PB01', 'TM1_CANDRAMAS', 'Overhead Main Feeder Segment 2'],
            ['SEC-CDR-002', 1, 'AAAC 150', 850.0, 'TM1_CANDRAMAS', 'TM8_CANDRAMAS', 'Overhead Section B Trunk'],
            ['SEC-CDR-003', 1, 'AAAC 70', 450.0, 'TM8_CANDRAMAS', 'TM15_CANDRAMAS', 'Overhead Section C Lateral'],
        ];
        $sheet2->fromArray($data2, null, 'A2');

        // Sheet 3: NETWORK_ACCESSORIES
        $sheet3 = $spreadsheet->createSheet();
        $sheet3->setTitle('NETWORK_ACCESSORIES');
        $headers3 = [
            'SECTION_REF', 'JENIS_AKSESORIS', 'KODE_MATERIAL', 'JUMLAH', 
            'LOKASI_REFERENSI', 'INITIAL_OBSERVED_CONDITION'
        ];
        $sheet3->fromArray([$headers3], null, 'A1');
        $data3 = [
            ['SEC-CDR-001', 'GSW', 'MAT-ACC-GSW', 1, 'Span Tiang 1 s/d Tiang 12', 'GOOD'],
            ['SEC-CDR-001', 'LA', 'LA', 3, 'Portal Tiang PB01', 'GOOD'],
            ['SEC-CDR-002', 'CLD', 'CLD', 2, 'Tiang TM8 Percabangan', 'GOOD'],
            ['SEC-CDR-003', 'ANIMAL_GUARD', 'PENGHALANG BINATANG', 4, 'Tiang TM10 s/d TM13', 'GOOD'],
        ];
        $sheet3->fromArray($data3, null, 'A2');

        $spreadsheet->setActiveSheetIndex(0);

        if (!is_dir(dirname($targetPath))) {
            @mkdir(dirname($targetPath), 0777, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($targetPath);
    }
}
