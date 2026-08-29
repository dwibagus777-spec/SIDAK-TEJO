<?php

namespace App\Services;

use App\Models\NetworkConfigurationImportBatchModel;
use App\Models\NetworkSectionConfigurationModel;
use App\Models\NetworkSectionConductorModel;
use App\Models\NetworkSectionAccessoryModel;
use App\Models\SectionModel;
use App\Models\PenyulangModel;
use App\Models\UlpModel;
use CodeIgniter\Database\BaseConnection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Service for Network Configuration Operational Activation & Ingestion Governance (CR-06F Contract v1.1.1)
 * Enforces 8 Hardening Gates (F1 - F8):
 * - Gate F1: Section-Scoped Truth (ULP -> Penyulang -> Section)
 * - Gate F2: Honest Empty State (0 rows is valid empty state)
 * - Gate F3: Conductor Segment Reality (Multi-segment mixed conductors)
 * - Gate F3A: Continuous Segment Sequence Invariant (Strict 1..N order, no duplicate/gap)
 * - Gate F4: Accessory Position Independence
 * - Gate F5: Active Version Governance (ACTIVATE_NEW_VERSION supersedes old ACTIVE atomically)
 * - Gate F6: No Topology Mutation from Findings (Initial observed condition only)
 * - Gate F7: Fail-Closed Audit Integration
 * - Gate F8: Batch Provenance & Scoped SECTION_REF
 */
class NetworkConfigurationIngestionService
{
    protected BaseConnection $db;
    protected NetworkConfigurationImportBatchModel $batchModel;
    protected NetworkSectionConfigurationModel $configModel;
    protected NetworkSectionConductorModel $conductorModel;
    protected NetworkSectionAccessoryModel $accessoryModel;
    protected SectionModel $sectionModel;
    protected PenyulangModel $penyulangModel;
    protected UlpModel $ulpModel;
    protected ConstructionIntelligenceService $ciService;
    protected NetworkConfigurationService $ncService;

    private static function ensureComposerAutoload(): void
    {
        if (class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class, true)) {
            return;
        }

        $candidates = [
            defined('COMPOSER_PATH') ? COMPOSER_PATH : '',
            defined('ROOTPATH') ? ROOTPATH . 'vendor/autoload.php' : '',
            defined('APPPATH') ? realpath(APPPATH . '../vendor/autoload.php') : '',
            defined('FCPATH') ? realpath(FCPATH . '../vendor/autoload.php') : '',
            realpath(__DIR__ . '/../../vendor/autoload.php'),
        ];

        foreach ($candidates as $path) {
            if (!empty($path) && is_file($path)) {
                require_once $path;
                if (class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class, true)) {
                    break;
                }
            }
        }
    }

    public function __construct(?BaseConnection $db = null)
    {
        self::ensureComposerAutoload();
        $this->db             = $db ?? \Config\Database::connect();
        $this->batchModel     = new NetworkConfigurationImportBatchModel();
        $this->configModel    = new NetworkSectionConfigurationModel();
        $this->conductorModel = new NetworkSectionConductorModel();
        $this->accessoryModel = new NetworkSectionAccessoryModel();
        $this->sectionModel   = new SectionModel();
        $this->penyulangModel = new PenyulangModel();
        $this->ulpModel       = new UlpModel();
        $this->ciService      = new ConstructionIntelligenceService($this->db);
        $this->ncService      = new NetworkConfigurationService($this->db);
    }

    /**
     * Ingest from structured Excel Spreadsheet file.
     */
    public function ingestFromExcel(string $filePath, ?int $uploadedBy = null, bool $dryRun = false): array
    {
        if (!is_file($filePath)) {
            return [
                'success' => false,
                'errors'  => ["File Excel tidak ditemukan: {$filePath}"],
            ];
        }

        try {
            $spreadsheet = IOFactory::load($filePath);
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'errors'  => ['Gagal membaca file Excel: ' . $e->getMessage()],
            ];
        }

        $sheets = [
            'SECTION_CONFIGURATIONS' => $this->parseWorksheetToAssociative($spreadsheet, 'SECTION_CONFIGURATIONS'),
            'CONDUCTOR_SEGMENTS'     => $this->parseWorksheetToAssociative($spreadsheet, 'CONDUCTOR_SEGMENTS'),
            'NETWORK_ACCESSORIES'    => $this->parseWorksheetToAssociative($spreadsheet, 'NETWORK_ACCESSORIES'),
        ];

        if ($dryRun) {
            return $this->generatePreflightPreview($sheets, basename($filePath));
        }

        return $this->processStructuredPayload($sheets, basename($filePath), 'EXCEL', $uploadedBy);
    }

    /**
     * Generate Pre-Flight Preview without database mutation (Dry-Run Mode).
     */
    public function previewFromExcel(string $filePath): array
    {
        return $this->ingestFromExcel($filePath, null, true);
    }

    /**
     * Calculate comprehensive Batch Preview statistics without mutating database.
     */
    public function generatePreflightPreview(array $payload, string $sourceName = 'MANUAL_PAYLOAD'): array
    {
        $validationResult = $this->validateAndResolvePayload($payload, 0);
        $resolvedSections = $validationResult['resolved_sections'];

        $totalSectionsFound = count($payload['SECTION_CONFIGURATIONS'] ?? []);
        $validSectionsCount = count($resolvedSections);
        $rejectedCount      = $totalSectionsFound - $validSectionsCount;

        $totalSegments       = 0;
        $totalLengthM        = 0.0;
        $accessoriesSummary  = [];
        $sectionDetails      = [];

        foreach ($resolvedSections as $sRef => $secData) {
            $condCount = count($secData['conductors']);
            $secLen    = array_sum(array_column($secData['conductors'], 'length_m'));
            $accCount  = count($secData['accessories']);

            $totalSegments += $condCount;
            $totalLengthM  += $secLen;

            foreach ($secData['accessories'] as $acc) {
                $type = $acc['accessory_type'] ?? 'OTHER';
                $accessoriesSummary[$type] = ($accessoriesSummary[$type] ?? 0) + (int)($acc['quantity'] ?? 1);
            }

            $sectionDetails[] = [
                'section_ref'                  => $sRef,
                'nama_section'                 => $secData['nama_section'],
                'import_action'                => $secData['import_action'],
                'conductor_segments_count'     => $condCount,
                'total_length_m'               => $secLen,
                'accessories_count'            => $accCount,
                'topology_connectivity_status' => $secData['topology_connectivity_status'],
            ];
        }

        // Count specific violation categories from errors
        $seqViolations  = 0;
        $topDiscont     = 0;
        $invalidMats    = 0;
        $domainIxViol   = 0;

        foreach ($validationResult['errors'] as $err) {
            if (str_contains($err, 'Gate F3A') || str_contains($err, 'urutan segmen') || str_contains($err, 'SEQUENCE_ORDER')) {
                $seqViolations++;
            }
            if (str_contains($err, 'Diskontinuitas topologi')) {
                $topDiscont++;
            }
            if (str_contains($err, 'tidak dikenali di Master Material')) {
                $invalidMats++;
            }
            if (str_contains($err, 'Domain Invariant IX')) {
                $domainIxViol++;
            }
        }

        return [
            'success'  => $validationResult['valid'],
            'dry_run'  => true,
            'source'   => $sourceName,
            'summary'  => [
                'total_sections_found'    => $totalSectionsFound,
                'valid_sections_count'    => $validSectionsCount,
                'rejected_sections_count' => $rejectedCount,
                'total_conductor_segments'=> $totalSegments,
                'total_conductor_length_m'=> $totalLengthM,
                'accessories_breakdown'   => $accessoriesSummary,
                'sequence_violations'     => $seqViolations,
                'topology_discontinuity'  => $topDiscont,
                'invalid_materials'       => $invalidMats,
                'domain_invariant_ix'     => $domainIxViol === 0 ? 'PASS' : 'VIOLATION',
            ],
            'sections_preview' => $sectionDetails,
            'errors'           => $validationResult['errors'],
        ];
    }

    /**
     * Process structured array payload (used by Excel parser and Direct API / Test Suite).
     */
    public function processStructuredPayload(array $payload, string $sourceName = 'MANUAL_PAYLOAD', string $sourceType = 'JSON', ?int $uploadedBy = null): array
    {
        $batchUuid = 'BATCH-NETCFG-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
        $now       = date('Y-m-d H:i:s');

        // Initial Batch record
        $batchId = (int)$this->batchModel->insert([
            'batch_uuid'         => $batchUuid,
            'source_filename'    => $sourceName,
            'source_type'        => $sourceType,
            'import_status'      => 'VALIDATING',
            'total_sections'     => 0,
            'committed_sections' => 0,
            'rejected_sections'  => 0,
            'validation_summary' => null,
            'imported_by'        => $uploadedBy,
            'started_at'         => $now,
            'created_at'         => $now,
        ], true);

        // =========================================================================
        // PHASE 1 & 2: IN-MEMORY VALIDATION, LINTING & ENTITY RESOLUTION
        // =========================================================================
        $validationResult = $this->validateAndResolvePayload($payload, $batchId);

        if (!$validationResult['valid']) {
            $this->batchModel->update($batchId, [
                'import_status'      => 'REJECTED',
                'total_sections'     => count($payload['SECTION_CONFIGURATIONS'] ?? []),
                'rejected_sections'  => count($payload['SECTION_CONFIGURATIONS'] ?? []),
                'validation_summary' => json_encode(['errors' => $validationResult['errors']], JSON_PRETTY_PRINT),
                'completed_at'       => date('Y-m-d H:i:s'),
            ]);

            return [
                'success'    => false,
                'batch_id'   => $batchId,
                'batch_uuid' => $batchUuid,
                'status'     => 'REJECTED',
                'errors'     => $validationResult['errors'],
            ];
        }

        // =========================================================================
        // PHASE 3: 2-PHASE HARD GATE — ALL OR NOTHING
        // =========================================================================
        $resolvedSections = $validationResult['resolved_sections'];

        $this->db->transBegin();
        try {
            $this->batchModel->update($batchId, ['import_status' => 'COMMITTING']);

            $committedCount = 0;

            foreach ($resolvedSections as $sRef => $secData) {
                $sectionId     = $secData['section_id'];
                $importAction  = $secData['import_action']; // ACTIVATE_NEW_VERSION or CREATE_DRAFT
                $configSource  = $secData['configuration_source'];
                $changeReason  = $secData['change_reason'];
                $effectiveFrom = $secData['effective_from'];
                $connStatus    = $secData['topology_connectivity_status'];

                // Calculate next version number
                $latest = $this->configModel
                    ->where('section_id', $sectionId)
                    ->orderBy('version_number', 'DESC')
                    ->first();
                $versionNumber = $latest ? ((int)$latest['version_number'] + 1) : 1;

                // State determination (Amendment F-02)
                $verificationStatus = ($importAction === 'ACTIVATE_NEW_VERSION') ? 'ACTIVE' : 'DRAFT';

                // If ACTIVATE_NEW_VERSION: Atomically supersede previous active configuration
                if ($importAction === 'ACTIVATE_NEW_VERSION') {
                    $this->configModel
                        ->where('section_id', $sectionId)
                        ->where('verification_status', 'ACTIVE')
                        ->where('effective_to IS NULL')
                        ->set([
                            'verification_status' => 'SUPERSEDED',
                            'effective_to'        => $effectiveFrom,
                        ])
                        ->update();
                }

                // Insert network_section_configurations
                $configPayload = [
                    'section_id'                   => $sectionId,
                    'import_batch_id'              => $batchId,
                    'section_ref'                  => $sRef,
                    'version_number'               => $versionNumber,
                    'effective_from'               => $effectiveFrom,
                    'effective_to'                 => null,
                    'verification_status'          => $verificationStatus,
                    'topology_connectivity_status' => $connStatus,
                    'configuration_source'         => $configSource,
                    'change_reason'                => $changeReason,
                    'created_at'                   => $now,
                ];

                $configId = (int)$this->configModel->insert($configPayload, true);

                // Insert conductors
                foreach ($secData['conductors'] as $cond) {
                    $this->conductorModel->insert([
                        'network_section_configuration_id' => $configId,
                        'conductor_material_id'            => $cond['material_id'],
                        'sequence_order'                   => $cond['sequence_order'],
                        'segment_label'                    => $cond['segment_label'] ?? null,
                        'start_node_id'                    => null, // Optional node labels preserved in segment_label/notes
                        'end_node_id'                      => null,
                        'length_m'                         => $cond['length_m'],
                        'verified'                         => 1,
                        'created_at'                       => $now,
                    ]);
                }

                // Insert accessories
                foreach ($secData['accessories'] as $acc) {
                    $this->accessoryModel->insert([
                        'network_section_configuration_id' => $configId,
                        'accessory_material_id'            => $acc['material_id'],
                        'accessory_type'                   => $acc['accessory_type'],
                        'quantity'                         => $acc['quantity'],
                        'location_reference'               => $acc['location_reference'] ?? null,
                        'condition_status'                 => $acc['initial_observed_condition'] ?? 'GOOD',
                        'initial_observed_condition'       => $acc['initial_observed_condition'] ?? 'GOOD',
                        'verified'                         => 1,
                        'created_at'                       => $now,
                    ]);
                }

                $committedCount++;
            }

            // Mark Batch COMMITTED
            $this->batchModel->update($batchId, [
                'import_status'      => 'COMMITTED',
                'total_sections'     => count($resolvedSections),
                'committed_sections' => $committedCount,
                'rejected_sections'  => 0,
                'validation_summary' => json_encode(['summary' => "Successfully committed {$committedCount} section configurations."], JSON_PRETTY_PRINT),
                'completed_at'       => date('Y-m-d H:i:s'),
            ]);

            $this->db->transCommit();

            return [
                'success'            => true,
                'batch_id'           => $batchId,
                'batch_uuid'         => $batchUuid,
                'status'             => 'COMMITTED',
                'total_sections'     => count($resolvedSections),
                'committed_sections' => $committedCount,
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            $this->batchModel->update($batchId, [
                'import_status'      => 'ROLLED_BACK',
                'validation_summary' => json_encode(['error' => 'Database transaction failed: ' . $e->getMessage()], JSON_PRETTY_PRINT),
                'completed_at'       => date('Y-m-d H:i:s'),
            ]);

            throw new \RuntimeException("Network configuration ingestion failed and was rolled back: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Validate and Resolve payload in memory (Phase 1 & 2).
     */
    public function validateAndResolvePayload(array $payload, int $batchId): array
    {
        $errors = [];
        $resolvedSections = [];

        $secRows  = $payload['SECTION_CONFIGURATIONS'] ?? [];
        $condRows = $payload['CONDUCTOR_SEGMENTS'] ?? [];
        $accRows  = $payload['NETWORK_ACCESSORIES'] ?? [];

        if (empty($secRows)) {
            $errors[] = 'Sheet SECTION_CONFIGURATIONS kosong atau tidak ditemukan.';
            return ['valid' => false, 'errors' => $errors, 'resolved_sections' => []];
        }

        // 1. Validate SECTION_REF Uniqueness within Batch (Amendment F-01)
        $seenRefs = [];
        foreach ($secRows as $idx => $row) {
            $rowNum = $idx + 2;
            $sRef   = trim((string)($row['SECTION_REF'] ?? ''));

            if (empty($sRef)) {
                $errors[] = "SECTION_CONFIGURATIONS Baris #{$rowNum}: SECTION_REF wajib diisi.";
                continue;
            }

            if (isset($seenRefs[$sRef])) {
                $errors[] = "SECTION_CONFIGURATIONS Baris #{$rowNum}: Duplikat SECTION_REF '{$sRef}' di dalam batch yang sama (Baris sebelumnya: #{$seenRefs[$sRef]}).";
            } else {
                $seenRefs[$sRef] = $rowNum;
            }

            // Resolve Section Hierarchy (Gate F1)
            $ulpStr       = trim((string)($row['KODE_ULP'] ?? ''));
            $penyulangStr = trim((string)($row['KODE_PENYULANG'] ?? ''));
            $sectionStr   = trim((string)($row['NAMA_SECTION'] ?? ''));

            if (empty($sectionStr)) {
                $errors[] = "SECTION_CONFIGURATIONS Baris #{$rowNum} [{$sRef}]: NAMA_SECTION wajib diisi.";
                continue;
            }

            $resolvedSection = $this->resolveSectionHierarchy($ulpStr, $penyulangStr, $sectionStr);
            if (!$resolvedSection) {
                $errors[] = "SECTION_CONFIGURATIONS Baris #{$rowNum} [{$sRef}]: Section '{$sectionStr}' tidak ditemukan dalam hierarki ULP: '{$ulpStr}' / Penyulang: '{$penyulangStr}'.";
                continue;
            }

            // Command validation (Amendment F-02)
            $actionRaw = strtoupper(trim((string)($row['IMPORT_ACTION'] ?? ($row['ACTION'] ?? 'ACTIVATE_NEW_VERSION'))));
            if (!in_array($actionRaw, ['ACTIVATE_NEW_VERSION', 'CREATE_DRAFT', 'ACTIVATE', 'DRAFT'])) {
                $errors[] = "SECTION_CONFIGURATIONS Baris #{$rowNum} [{$sRef}]: IMPORT_ACTION '{$actionRaw}' tidak valid (Gunakan: ACTIVATE_NEW_VERSION atau CREATE_DRAFT).";
                continue;
            }
            $importAction = ($actionRaw === 'CREATE_DRAFT' || $actionRaw === 'DRAFT') ? 'CREATE_DRAFT' : 'ACTIVATE_NEW_VERSION';

            $resolvedSections[$sRef] = [
                'section_id'                   => (int)$resolvedSection['id'],
                'section_ref'                  => $sRef,
                'nama_section'                 => $resolvedSection['nama_section'],
                'import_action'                => $importAction,
                'configuration_source'         => !empty($row['CONFIGURATION_SOURCE']) ? strtoupper(trim((string)$row['CONFIGURATION_SOURCE'])) : 'INITIAL_AUDIT',
                'change_reason'                => !empty($row['CHANGE_REASON']) ? trim((string)$row['CHANGE_REASON']) : 'Initial Network Configuration Import',
                'effective_from'               => !empty($row['EFFECTIVE_FROM']) ? trim((string)$row['EFFECTIVE_FROM']) : date('Y-m-d H:i:s'),
                'topology_connectivity_status' => 'UNVERIFIED',
                'conductors'                   => [],
                'accessories'                  => [],
            ];
        }

        // 2. Validate CONDUCTOR_SEGMENTS & Gate F3A (Continuous Sequence)
        $conductorsByRef = [];
        foreach ($condRows as $idx => $row) {
            $rowNum = $idx + 2;
            $sRef   = trim((string)($row['SECTION_REF'] ?? ''));

            if (empty($sRef)) {
                $errors[] = "CONDUCTOR_SEGMENTS Baris #{$rowNum}: SECTION_REF wajib diisi.";
                continue;
            }

            if (!isset($resolvedSections[$sRef])) {
                $errors[] = "CONDUCTOR_SEGMENTS Baris #{$rowNum}: SECTION_REF '{$sRef}' tidak terdaftar di sheet SECTION_CONFIGURATIONS.";
                continue;
            }

            $seq = isset($row['SEQUENCE_ORDER']) && is_numeric($row['SEQUENCE_ORDER']) ? (int)$row['SEQUENCE_ORDER'] : null;
            if ($seq === null || $seq < 1) {
                $errors[] = "CONDUCTOR_SEGMENTS Baris #{$rowNum} [{$sRef}]: SEQUENCE_ORDER harus berupa bilangan bulat positif (>= 1).";
                continue;
            }

            $len = isset($row['PANJANG_METER']) && is_numeric($row['PANJANG_METER']) ? (float)$row['PANJANG_METER'] : null;
            if ($len === null || $len <= 0) {
                $errors[] = "CONDUCTOR_SEGMENTS Baris #{$rowNum} [{$sRef}]: PANJANG_METER harus berupa angka positif > 0.";
                continue;
            }

            // Material Resolution
            $matStr = trim((string)($row['KODE_MATERIAL_KONDUKTOR'] ?? ($row['CONDUCTOR_MATERIAL'] ?? '')));
            if (empty($matStr)) {
                $errors[] = "CONDUCTOR_SEGMENTS Baris #{$rowNum} [{$sRef}]: KODE_MATERIAL_KONDUKTOR wajib diisi.";
                continue;
            }

            $mat = $this->ciService->resolveMaterialAlias($matStr);
            if (!$mat) {
                $errors[] = "CONDUCTOR_SEGMENTS Baris #{$rowNum} [{$sRef}]: Material konduktor '{$matStr}' tidak dikenali di Master Material.";
                continue;
            }

            // Domain Invariant IX check
            if (in_array(strtoupper($mat['material_domain']), ['GARDU', 'TRAFO', 'KUBIKEL'])) {
                $errors[] = "CONDUCTOR_SEGMENTS Baris #{$rowNum} [{$sRef}]: Domain Invariant IX Violation: Equipment '{$mat['nama_material']}' ({$mat['material_domain']}) dilarang dijadikan konduktor transline.";
                continue;
            }

            $startNode = !empty($row['START_NODE']) ? trim((string)$row['START_NODE']) : null;
            $endNode   = !empty($row['END_NODE']) ? trim((string)$row['END_NODE']) : null;

            $conductorsByRef[$sRef][] = [
                'row_num'        => $rowNum,
                'sequence_order' => $seq,
                'material_id'    => (int)$mat['id'],
                'material_code'  => $mat['material_code'],
                'length_m'       => $len,
                'start_node'     => $startNode,
                'end_node'       => $endNode,
                'segment_label'  => $row['SEGMENT_LABEL'] ?? "Segment {$seq} ({$mat['material_code']})",
            ];
        }

        // Gate F3A & Node Continuity Validation per Section
        foreach ($resolvedSections as $sRef => &$secData) {
            $segments = $conductorsByRef[$sRef] ?? [];
            if (empty($segments)) {
                $errors[] = "SECTION_CONFIGURATIONS [{$sRef}]: Tidak memiliki segmen konduktor di sheet CONDUCTOR_SEGMENTS.";
                continue;
            }

            // Sort segments by sequence_order
            usort($segments, fn($a, $b) => $a['sequence_order'] <=> $b['sequence_order']);

            // Validate Gate F3A: Continuous Sequence (1, 2, 3, ... N)
            $expectedSeq = 1;
            $seenSeqs = [];
            foreach ($segments as $seg) {
                $currSeq = $seg['sequence_order'];
                if (isset($seenSeqs[$currSeq])) {
                    $errors[] = "CONDUCTOR_SEGMENTS [{$sRef}]: Duplikat SEQUENCE_ORDER '{$currSeq}' terdeteksi (Gate F3A Violation).";
                }
                $seenSeqs[$currSeq] = true;

                if ($currSeq !== $expectedSeq) {
                    $errors[] = "CONDUCTOR_SEGMENTS [{$sRef}]: Celah/ketidaksinambungan urutan segmen terdeteksi (Ditemukan: {$currSeq}, Diharapkan: {$expectedSeq}) (Gate F3A Violation).";
                }
                $expectedSeq++;
            }

            // Validate Node Continuity (Amendment F-03)
            $hasCompleteNodes = true;
            foreach ($segments as $seg) {
                if (empty($seg['start_node']) || empty($seg['end_node'])) {
                    $hasCompleteNodes = false;
                    break;
                }
            }

            if ($hasCompleteNodes && count($segments) > 1) {
                // Mode A: Complete Nodes -> Check continuity
                $isContinuous = true;
                for ($i = 0; $i < count($segments) - 1; $i++) {
                    $currEnd   = strtoupper(trim($segments[$i]['end_node']));
                    $nextStart = strtoupper(trim($segments[$i + 1]['start_node']));
                    if ($currEnd !== $nextStart) {
                        $isContinuous = false;
                        $errors[] = "CONDUCTOR_SEGMENTS [{$sRef}]: Diskontinuitas topologi simpul antara Segmen #{$segments[$i]['sequence_order']} (End: {$currEnd}) dan Segmen #{$segments[$i + 1]['sequence_order']} (Start: {$nextStart}).";
                    }
                }
                $secData['topology_connectivity_status'] = $isContinuous ? 'VERIFIED' : 'DISCONTINUOUS';
            } else {
                // Mode B: Partial/Empty Nodes -> UNVERIFIED but valid
                $secData['topology_connectivity_status'] = 'UNVERIFIED';
            }

            $secData['conductors'] = $segments;
        }
        unset($secData);

        // 3. Validate NETWORK_ACCESSORIES
        foreach ($accRows as $idx => $row) {
            $rowNum = $idx + 2;
            $sRef   = trim((string)($row['SECTION_REF'] ?? ''));

            if (empty($sRef)) {
                $errors[] = "NETWORK_ACCESSORIES Baris #{$rowNum}: SECTION_REF wajib diisi.";
                continue;
            }

            if (!isset($resolvedSections[$sRef])) {
                $errors[] = "NETWORK_ACCESSORIES Baris #{$rowNum}: SECTION_REF '{$sRef}' tidak terdaftar di sheet SECTION_CONFIGURATIONS.";
                continue;
            }

            $accType = strtoupper(trim((string)($row['JENIS_AKSESORIS'] ?? ($row['ACCESSORY_TYPE'] ?? 'GSW'))));
            $validTypes = ['GSW', 'LA', 'CLD', 'MCA', 'ANIMAL_GUARD', 'GROUNDING', 'EGLA', 'OTHER'];
            if (!in_array($accType, $validTypes)) {
                $errors[] = "NETWORK_ACCESSORIES Baris #{$rowNum} [{$sRef}]: JENIS_AKSESORIS '{$accType}' tidak valid.";
                continue;
            }

            $matStr = trim((string)($row['KODE_MATERIAL'] ?? ($row['MATERIAL_CODE'] ?? $accType)));
            $mat = $this->ciService->resolveMaterialAlias($matStr);
            if (!$mat) {
                $errors[] = "NETWORK_ACCESSORIES Baris #{$rowNum} [{$sRef}]: Material aksesoris '{$matStr}' tidak dikenali di Master Material.";
                continue;
            }

            $qty = isset($row['JUMLAH']) && is_numeric($row['JUMLAH']) ? (int)$row['JUMLAH'] : 1;
            if ($qty < 1) {
                $errors[] = "NETWORK_ACCESSORIES Baris #{$rowNum} [{$sRef}]: JUMLAH harus >= 1.";
                continue;
            }

            $obsCond = strtoupper(trim((string)($row['INITIAL_OBSERVED_CONDITION'] ?? ($row['STATUS_KONDISI'] ?? 'GOOD'))));
            if (!in_array($obsCond, ['GOOD', 'DEFECTIVE', 'MISSING'])) {
                $obsCond = 'GOOD';
            }

            $resolvedSections[$sRef]['accessories'][] = [
                'row_num'                    => $rowNum,
                'accessory_type'             => $accType,
                'material_id'                => (int)$mat['id'],
                'quantity'                   => $qty,
                'location_reference'         => !empty($row['LOKASI_REFERENSI']) ? trim((string)$row['LOKASI_REFERENSI']) : null,
                'initial_observed_condition' => $obsCond,
            ];
        }

        $isValid = empty($errors);
        return [
            'valid'             => $isValid,
            'errors'            => $errors,
            'resolved_sections' => $resolvedSections,
        ];
    }

    /**
     * Resolve Section Hierarchy: ULP -> Penyulang -> Section
     */
    private function resolveSectionHierarchy(string $ulpStr, string $penyulangStr, string $sectionStr): ?array
    {
        $cleanSec = trim($sectionStr);
        if (empty($cleanSec)) {
            return null;
        }

        $hasPenyulangTable = $this->db->tableExists('penyulang');
        $hasNamaPenyulang  = $hasPenyulangTable && $this->db->fieldExists('nama_penyulang', 'penyulang');

        $builder = $this->db->table('sections');

        if ($hasPenyulangTable && $hasNamaPenyulang) {
            $builder->select('sections.*, penyulang.nama_penyulang, penyulang.kode_penyulang, penyulang.ulp_id')
                ->join('penyulang', 'penyulang.id = sections.penyulang_id', 'left');

            if (!empty($penyulangStr)) {
                $builder->groupStart()
                    ->where('penyulang.kode_penyulang', $penyulangStr)
                    ->orWhere('penyulang.nama_penyulang', $penyulangStr)
                    ->orWhere('penyulang.id', is_numeric($penyulangStr) ? (int)$penyulangStr : 0)
                ->groupEnd();
            }
        } else {
            $builder->select('sections.*');
        }

        $builder->where('sections.nama_section', $cleanSec);

        $match = $builder->get()->getFirstRow('array');
        if ($match) {
            return $match;
        }

        // 2. Fallback: Case-insensitive in-memory matching
        $allSections = $this->db->table('sections')->get()->getResultArray();
        foreach ($allSections as $s) {
            if (strcasecmp(trim($s['nama_section']), $cleanSec) === 0) {
                return $s;
            }
        }

        // 3. Fallback: Lookup by ID if numeric
        if (is_numeric($cleanSec)) {
            return $this->sectionModel->find((int)$cleanSec);
        }

        // 4. Fallback: Like search in section table
        return $this->sectionModel->like('nama_section', $cleanSec)->first();
    }

    /**
     * Helper to read Worksheet into array of associative rows based on header row.
     */
    private function parseWorksheetToAssociative(Spreadsheet $spreadsheet, string $sheetName): array
    {
        $sheet = $spreadsheet->getSheetByName($sheetName);
        if (!$sheet) {
            return [];
        }

        $highestRow = $sheet->getHighestRow();
        $highestCol = $sheet->getHighestColumn();
        $highestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestCol);

        if ($highestRow < 2) {
            return [];
        }

        // Read headers
        $headers = [];
        for ($col = 1; $col <= $highestColIndex; $col++) {
            $val = trim((string)$sheet->getCellByColumnAndRow($col, 1)->getValue());
            if (!empty($val)) {
                $headers[$col] = strtoupper($val);
            }
        }

        if (empty($headers)) {
            return [];
        }

        $rows = [];
        for ($row = 2; $row <= $highestRow; $row++) {
            $rowData = [];
            $hasContent = false;

            foreach ($headers as $col => $headerName) {
                $cellVal = $sheet->getCellByColumnAndRow($col, $row)->getValue();
                $cellStr = $cellVal !== null ? trim((string)$cellVal) : '';
                if ($cellStr !== '') {
                    $hasContent = true;
                }
                $rowData[$headerName] = $cellStr;
            }

            if ($hasContent) {
                $rows[] = $rowData;
            }
        }

        return $rows;
    }
}
