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
        '--dry-run'  => 'Run pre-flight validation only without database mutation.',
        '--pilot'    => 'Generate and ingest pilot sample using REAL master sections from database.',
        '--feeder'   => 'Specify feeder name or ID for pilot generation (default: first active feeder or CANDRAMAS).',
        '--inspect'  => 'Inspect production ULP -> Penyulang -> Sections master hierarchy.',
    ];

    public function run(array $params)
    {
        CLI::write("==================================================================", "yellow");
        CLI::write("    CR-06F PHYSICAL NETWORK CONFIGURATION INGESTION ENGINE        ", "yellow");
        CLI::write("==================================================================", "yellow");
        CLI::newLine();

        $db = \Config\Database::connect();
        $ingestService = new NetworkConfigurationIngestionService($db);

        $isInspect = array_key_exists('inspect', $params) || CLI::getOption('inspect');
        $isPilot   = array_key_exists('pilot', $params) || CLI::getOption('pilot');
        $isDryRun  = array_key_exists('dry-run', $params) || CLI::getOption('dry-run');
        $feederOpt = $params['feeder'] ?? CLI::getOption('feeder');
        $filePath  = $params[0] ?? CLI::getOption('filepath');

        // Mode 1: Hierarchy Inspection
        if ($isInspect) {
            $this->inspectMasterHierarchy($db, $feederOpt);
            return 0;
        }

        // Mode 2: Dynamic Master-Aware Pilot Generation
        if ($isPilot && empty($filePath)) {
            $genResult = $this->generatePilotExcelFromMaster($db, WRITEPATH, $feederOpt);
            if (!$genResult['success']) {
                CLI::write("❌ Failed to generate pilot from master: " . $genResult['error'], "red");
                return 1;
            }
            $filePath = $genResult['file_path'];
            CLI::write("📄 Generated Pilot Excel File: {$filePath}", "cyan");
            CLI::write("   Feeder Selected: [{$genResult['kode_penyulang']}] {$genResult['nama_penyulang']}", "white");
            CLI::write("   Sections Mapped: " . implode(', ', $genResult['sections']), "white");
            CLI::newLine();
        }

        if (empty($filePath)) {
            CLI::write("❌ Error: File path parameter is required.", "red");
            CLI::write("Usage: php spark network-config:ingest <path-to-excel> [--dry-run]", "white");
            CLI::write("   or: php spark network-config:ingest --pilot [--feeder=<name>] [--dry-run]", "white");
            CLI::write("   or: php spark network-config:ingest --inspect [keyword]", "white");
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

    private function inspectMasterHierarchy(\CodeIgniter\Database\BaseConnection $db, ?string $keyword = null): void
    {
        CLI::write("==================================================================", "white");
        CLI::write("           MASTER PRODUCTION TOPOLOGY HIERARCHY INSPECTOR         ", "yellow");
        CLI::write("==================================================================", "white");
        CLI::newLine();

        $totalUlps     = $db->table('ulps')->countAllResults();
        $totalFeeders  = $db->table('penyulang')->countAllResults();
        $totalSections = $db->table('sections')->countAllResults();

        CLI::write(sprintf("Inventory: %d ULPs, %d Feeders (Penyulang), %d Sections", $totalUlps, $totalFeeders, $totalSections), "cyan");
        CLI::newLine();

        $builder = $db->table('penyulang')
            ->select('penyulang.*, ulps.kode_ulp, ulps.nama_ulp')
            ->join('ulps', 'ulps.id = penyulang.ulp_id', 'left');

        if (!empty($keyword)) {
            $builder->groupStart()
                ->like('penyulang.nama_penyulang', $keyword)
                ->orLike('penyulang.kode_penyulang', $keyword)
                ->orLike('ulps.nama_ulp', $keyword)
            ->groupEnd();
        }

        $feeders = $builder->limit(20)->get()->getResultArray();

        if (empty($feeders)) {
            CLI::write("⚠️  No feeders matched keyword '{$keyword}'.", "yellow");
            return;
        }

        foreach ($feeders as $f) {
            CLI::write(sprintf("⚡ Feeder ID #%d: [%s] %s (ULP: %s - %s)", 
                $f['id'], 
                $f['kode_penyulang'] ?? 'N/A', 
                $f['nama_penyulang'], 
                $f['kode_ulp'] ?? 'N/A', 
                $f['nama_ulp'] ?? 'N/A'
            ), "green");

            $sections = $db->table('sections')
                ->where('penyulang_id', $f['id'])
                ->limit(10)
                ->get()
                ->getResultArray();

            if (empty($sections)) {
                CLI::write("   └── (0 Sections linked to this feeder)", "white");
            } else {
                foreach ($sections as $s) {
                    CLI::write(sprintf("   └── Section ID #%d: %s", $s['id'], $s['nama_section']), "white");
                }
                $secCount = $db->table('sections')->where('penyulang_id', $f['id'])->countAllResults();
                if ($secCount > 10) {
                    CLI::write(sprintf("   └── ... and %d more sections", $secCount - 10), "yellow");
                }
            }
            CLI::newLine();
        }
    }

    private function generatePilotExcelFromMaster(\CodeIgniter\Database\BaseConnection $db, string $targetPath, ?string $feederKeyword = null): array
    {
        // 1. Resolve Feeder from Database
        $feederBuilder = $db->table('penyulang');
        if (!empty($feederKeyword)) {
            $feederBuilder->groupStart()
                ->like('nama_penyulang', $feederKeyword)
                ->orLike('kode_penyulang', $feederKeyword)
                ->orWhere('id', is_numeric($feederKeyword) ? (int)$feederKeyword : 0)
            ->groupEnd();
        } else {
            $feederBuilder->groupStart()
                ->like('nama_penyulang', 'CANDRAMAS')
                ->orLike('kode_penyulang', 'CDR')
            ->groupEnd();
        }

        $feeder = $feederBuilder->get()->getFirstRow('array');
        if (!$feeder) {
            $feeder = $db->table('penyulang')->get()->getFirstRow('array');
        }

        if (!$feeder) {
            return ['success' => false, 'error' => 'No feeders found in master database.'];
        }

        // 2. Resolve ULP
        $ulp = null;
        if (!empty($feeder['ulp_id'])) {
            $ulp = $db->table('ulps')->where('id', $feeder['ulp_id'])->get()->getFirstRow('array');
        }
        if (!$ulp) {
            $ulp = $db->table('ulps')->get()->getFirstRow('array');
        }

        $kodeUlp       = $ulp['kode_ulp'] ?? '51301';
        $kodePenyulang = !empty($feeder['kode_penyulang']) ? $feeder['kode_penyulang'] : $feeder['nama_penyulang'];

        // 3. Resolve Real Sections
        $sections = $db->table('sections')->where('penyulang_id', $feeder['id'])->limit(3)->get()->getResultArray();
        if (empty($sections)) {
            $sections = $db->table('sections')->limit(3)->get()->getResultArray();
        }

        if (empty($sections)) {
            return ['success' => false, 'error' => 'No sections found in master database.'];
        }

        $spreadsheet = new Spreadsheet();

        // -------------------------------------------------------------
        // Sheet 1: SECTION_CONFIGURATIONS
        // -------------------------------------------------------------
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('SECTION_CONFIGURATIONS');
        $headers1 = [
            'SECTION_REF', 'KODE_ULP', 'KODE_PENYULANG', 'NAMA_SECTION', 
            'IMPORT_ACTION', 'CONFIGURATION_SOURCE', 'CHANGE_REASON', 'EFFECTIVE_FROM'
        ];
        $sheet1->fromArray([$headers1], null, 'A1');

        $data1 = [];
        $data2 = [];
        $data3 = [];
        $sectionNames = [];

        foreach ($sections as $idx => $sec) {
            $refNum = sprintf('%03d', $idx + 1);
            $sRef   = "SEC-PILOT-{$refNum}";
            $sName  = $sec['nama_section'];
            $sectionNames[] = $sName;

            // Sheet 1 row
            $data1[] = [
                $sRef, 
                $kodeUlp, 
                $kodePenyulang, 
                $sName, 
                'ACTIVATE_NEW_VERSION', 
                'INITIAL_AUDIT', 
                'Pilot Operational Activation 2026', 
                date('Y-m-d H:i:s')
            ];

            // Sheet 2 conductors (2 segments for first section, 1 for others)
            if ($idx === 0) {
                $data2[] = [$sRef, 1, 'AAACS 240', 350.0, 'GI_START', 'PB01', "{$sName} Trunk 1"];
                $data2[] = [$sRef, 2, 'AAAC 150', 650.0, 'PB01', 'TM1_NODE', "{$sName} Overhead Main"];
            } elseif ($idx === 1) {
                $data2[] = [$sRef, 1, 'AAAC 150', 850.0, 'TM1_NODE', 'TM8_NODE', "{$sName} Trunk"];
            } else {
                $data2[] = [$sRef, 1, 'AAAC 70', 450.0, 'TM8_NODE', 'TM15_NODE', "{$sName} Lateral"];
            }

            // Sheet 3 accessories
            if ($idx === 0) {
                $data3[] = [$sRef, 'GSW', 'MAT-ACC-GSW', 1, 'Span Tiang 1 s/d Tiang 12', 'GOOD'];
                $data3[] = [$sRef, 'LA', 'LA', 3, 'Portal Tiang PB01', 'GOOD'];
            } elseif ($idx === 1) {
                $data3[] = [$sRef, 'CLD', 'CLD', 2, 'Tiang TM8 Percabangan', 'GOOD'];
            } else {
                $data3[] = [$sRef, 'ANIMAL_GUARD', 'PENGHALANG BINATANG', 4, 'Tiang TM10 s/d TM13', 'GOOD'];
            }
        }

        $sheet1->fromArray($data1, null, 'A2');

        // Sheet 2: CONDUCTOR_SEGMENTS
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('CONDUCTOR_SEGMENTS');
        $headers2 = [
            'SECTION_REF', 'SEQUENCE_ORDER', 'KODE_MATERIAL_KONDUKTOR', 'PANJANG_METER', 
            'START_NODE', 'END_NODE', 'SEGMENT_LABEL'
        ];
        $sheet2->fromArray([$headers2], null, 'A1');
        $sheet2->fromArray($data2, null, 'A2');

        // Sheet 3: NETWORK_ACCESSORIES
        $sheet3 = $spreadsheet->createSheet();
        $sheet3->setTitle('NETWORK_ACCESSORIES');
        $headers3 = [
            'SECTION_REF', 'JENIS_AKSESORIS', 'KODE_MATERIAL', 'JUMLAH', 
            'LOKASI_REFERENSI', 'INITIAL_OBSERVED_CONDITION'
        ];
        $sheet3->fromArray([$headers3], null, 'A1');
        $sheet3->fromArray($data3, null, 'A2');

        $spreadsheet->setActiveSheetIndex(0);

        // Construct canonical provenance filename: pilot_<FEEDER_CODE>_<FEEDER_SLUG>_v1.1.xlsx
        $feederSlug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $feeder['nama_penyulang']), '-'));
        $feederCode = !empty($feeder['kode_penyulang']) ? preg_replace('/[^A-Za-z0-9-]+/', '-', $feeder['kode_penyulang']) : 'FEEDER';
        $finalFilename = "pilot_{$feederCode}_{$feederSlug}_v1.1.xlsx";
        
        $finalPath = is_dir($targetPath) ? rtrim($targetPath, '/\\') . DIRECTORY_SEPARATOR . $finalFilename : $targetPath;

        if (!is_dir(dirname($finalPath))) {
            @mkdir(dirname($finalPath), 0777, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($finalPath);

        return [
            'success'        => true,
            'file_path'      => $finalPath,
            'kode_penyulang' => $kodePenyulang,
            'nama_penyulang' => $feeder['nama_penyulang'],
            'sections'       => $sectionNames,
        ];
    }
}
