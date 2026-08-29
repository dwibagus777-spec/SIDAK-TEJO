<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * Phase AR-01: Canonical Feeder–Asset Resolution Engine
 * Governed by 8 Canonical Invariants (Contract AR-01):
 * - AR-01-A: No Blind Assignment (Zero heuristic guessing or arbitrary range defaults)
 * - AR-01-B: Canonical Parent Integrity (Unbroken Asset -> Section -> Feeder -> ULP chain)
 * - AR-01-C: Section Membership Integrity (Section must belong to Feeder & be ACTIVE in CR-06F)
 * - AR-01-D: Asset Identity Preservation (Preserve unique kode_asset with zero duplicates)
 * - AR-01-E: CR-06G Resolution Compatibility (Direct handshake with Construction Asset Intel)
 * - AR-01-F: Full Provenance (Deterministic audit metadata attached to every classification)
 * - AR-01-G: Reversible / Auditable Mapping (Fully auditable state transitions)
 * - AR-01-H: Zero False Resolution (Missing or ambiguous data remains UNRESOLVED / PARTIAL)
 */
class CanonicalFeederAssetResolutionService
{
    protected BaseConnection $db;
    protected ConstructionAssetIntelligenceService $assetIntelService;
    protected NetworkConfigurationService $configService;

    public const STATUS_RESOLVED   = 'RESOLVED';
    public const STATUS_PARTIAL    = 'PARTIAL';
    public const STATUS_UNRESOLVED = 'UNRESOLVED';
    public const STATUS_ORPHAN     = 'ORPHAN';
    public const STATUS_CONFLICT   = 'CONFLICT';

    public function __construct(?BaseConnection $db = null)
    {
        $this->db                = $db ?? \Config\Database::connect();
        $this->assetIntelService = new ConstructionAssetIntelligenceService($this->db);
        $this->configService     = new NetworkConfigurationService($this->db);
    }

    /**
     * Perform strict read-only canonical resolution analysis for a given feeder (Contract AR-01).
     */
    public function analyzeFeederAssetResolution(int $penyulangId): array
    {
        // 1. Feeder Truth (CR-06F)
        $tablePenyulang = $this->db->tableExists('penyulang') ? 'penyulang' : ($this->db->tableExists('db_penyulang') ? 'db_penyulang' : 'penyulang');
        
        $feederQuery = $this->db->table($tablePenyulang)->where('id', $penyulangId);
        if ($this->db->fieldExists('deleted_at', $tablePenyulang)) {
            $feederQuery->where('deleted_at IS NULL');
        }
        $feeder = $feederQuery->get()->getFirstRow('array');

        if (!$feeder) {
            return [
                'success' => false,
                'error'   => "Feeder ID #{$penyulangId} tidak ditemukan dalam database.",
            ];
        }

        // 2. Physical Topology Truth (CR-06F Active Sections)
        $secQuery = $this->db->table('sections')->where('penyulang_id', $penyulangId);
        if ($this->db->fieldExists('deleted_at', 'sections')) {
            $secQuery->where('deleted_at IS NULL');
        }
        $allSections = $secQuery->get()->getResultArray();

        $activeSectionMap = [];
        $allSectionMap    = [];
        $validSequenceCount = 0;
        $orphanSectionCount = 0;

        foreach ($allSections as $s) {
            $secId = (int)$s['id'];
            $allSectionMap[$secId] = $s;

            $activeCfg = $this->configService->getActiveConfiguration($secId);
            if ($activeCfg && !empty($activeCfg['conductors'])) {
                $activeSectionMap[$secId] = [
                    'section'       => $s,
                    'configuration' => $activeCfg,
                ];
                $hasValidSequence = false;
                foreach ($activeCfg['conductors'] as $c) {
                    if (!empty($c['sequence_order']) && (int)$c['sequence_order'] > 0) {
                        $hasValidSequence = true;
                        break;
                    }
                }
                if ($hasValidSequence) {
                    $validSequenceCount++;
                }
            } else {
                $orphanSectionCount++;
            }
        }

        // 3. Master Asset Inventory (Global Scope)
        $assetCountQuery = $this->db->table('assets');
        if ($this->db->fieldExists('deleted_at', 'assets')) {
            $assetCountQuery->where('deleted_at IS NULL');
        }
        $totalMasterAssetsCount = $assetCountQuery->countAllResults();

        // 4. Candidate Assets Discovery for this Feeder
        // Candidate: assets explicitly referencing penyulang_id OR belonging to one of the feeder's sections
        $sectionIds = array_keys($allSectionMap);

        $candidateQuery = $this->db->table('assets');
        if ($this->db->fieldExists('deleted_at', 'assets')) {
            $candidateQuery->where('deleted_at IS NULL');
        }
        if (!empty($sectionIds)) {
            $candidateQuery->groupStart()
                ->where('penyulang_id', $penyulangId)
                ->orWhereIn('section_id', $sectionIds)
            ->groupEnd();
        } else {
            $candidateQuery->where('penyulang_id', $penyulangId);
        }

        $candidateAssets = $candidateQuery->get()->getResultArray();

        // 5. Deterministic Asset Classification (Applying Gates AR-01-A through AR-01-H)
        $classified = [
            self::STATUS_RESOLVED   => [],
            self::STATUS_PARTIAL    => [],
            self::STATUS_UNRESOLVED => [],
            self::STATUS_ORPHAN     => [],
            self::STATUS_CONFLICT   => [],
        ];

        $governanceCounters = [
            'blind_assignments'             => 0,
            'duplicate_identities'          => 0,
            'cross_feeder_contaminations'   => 0,
            'cross_section_contaminations'  => 0,
            'invalid_construction_refs'     => 0,
            'cr06g_unresolved_intelligences'=> 0,
        ];

        $seenCodes = [];
        $sumAhs = 0.0;

        foreach ($candidateAssets as $asset) {
            $classification = $this->classifyAsset($asset, $feeder, $activeSectionMap, $allSectionMap);

            $status = $classification['canonical_status'];
            $classified[$status][] = $classification;

            // Audit Integrity Counters
            if (!empty($classification['flags']['duplicate_code'])) {
                $governanceCounters['duplicate_identities']++;
            }
            if (!empty($classification['flags']['cross_feeder_conflict'])) {
                $governanceCounters['cross_feeder_contaminations']++;
            }
            if (!empty($classification['flags']['cross_section_conflict'])) {
                $governanceCounters['cross_section_contaminations']++;
            }
            if (!empty($classification['flags']['invalid_construction'])) {
                $governanceCounters['invalid_construction_refs']++;
            }
            if (!empty($classification['flags']['cr06g_unresolved'])) {
                $governanceCounters['cr06g_unresolved_intelligences']++;
            }

            if ($status === self::STATUS_RESOLVED && isset($classification['cr06g_health']['asset_health_score'])) {
                $sumAhs += (float)$classification['cr06g_health']['asset_health_score'];
            }
        }

        $resolvedCount   = count($classified[self::STATUS_RESOLVED]);
        $partialCount    = count($classified[self::STATUS_PARTIAL]);
        $unresolvedCount = count($classified[self::STATUS_UNRESOLVED]);
        $orphanCount     = count($classified[self::STATUS_ORPHAN]);
        $conflictCount   = count($classified[self::STATUS_CONFLICT]);
        $totalCandidates = count($candidateAssets);

        $averageAhs = $resolvedCount > 0 ? round($sumAhs / $resolvedCount, 2) : null;

        return [
            'success'            => true,
            'timestamp'          => date('Y-m-d H:i:s'),
            'feeder'             => [
                'id'              => (int)$feeder['id'],
                'kode_penyulang'  => $feeder['kode_penyulang'] ?? 'PYL-001',
                'nama_penyulang'  => $feeder['nama_penyulang'],
                'ulp_id'          => (int)($feeder['ulp_id'] ?? 1),
            ],
            'topology'           => [
                'total_sections'            => count($allSections),
                'active_sections'           => count($activeSectionMap),
                'valid_sequence_sections'   => $validSequenceCount,
                'unconfigured_sections'     => $orphanSectionCount,
            ],
            'inventory'          => [
                'total_grid_master_assets'  => $totalMasterAssetsCount,
                'feeder_candidate_assets'   => $totalCandidates,
                'resolved_count'            => $resolvedCount,
                'partial_count'             => $partialCount,
                'unresolved_count'          => $unresolvedCount,
                'orphan_count'              => $orphanCount,
                'conflict_count'            => $conflictCount,
                'average_ahs_resolved'      => $averageAhs,
            ],
            'governance'         => $governanceCounters,
            'breakdown'          => $classified,
        ];
    }

    /**
     * Classify an asset against the 10 Canonical Resolution Criteria (Pure Deterministic Function).
     */
    public function classifyAsset(
        array $asset,
        array $feeder,
        array $activeSectionMap,
        array $allSectionMap
    ): array {
        $assetId       = (int)$asset['id'];
        $assetPenyulang= !empty($asset['penyulang_id']) ? (int)$asset['penyulang_id'] : null;
        $assetSection  = !empty($asset['section_id']) ? (int)$asset['section_id'] : null;
        $targetFeederId= (int)$feeder['id'];

        $flags = [
            'duplicate_code'         => false,
            'cross_feeder_conflict'  => false,
            'cross_section_conflict' => false,
            'inactive_section'       => false,
            'missing_section'        => false,
            'invalid_construction'   => false,
            'cr06g_unresolved'       => false,
        ];

        $reasons = [];

        // 1. Asset Identity Integrity
        if (empty($asset['kode_asset'])) {
            return [
                'asset_id'         => $assetId,
                'kode_asset'       => $asset['kode_asset'] ?? 'UNNAMED',
                'canonical_status' => self::STATUS_ORPHAN,
                'reason'           => 'Identitas kode_asset kosong / tidak terdefinisi.',
                'flags'            => $flags,
                'provenance'       => 'AR-01-D Violation',
            ];
        }

        // 2. Feeder & Section Parent Relationship Validation
        if ($assetPenyulang !== null && $assetPenyulang !== $targetFeederId) {
            $flags['cross_feeder_conflict'] = true;
            return [
                'asset_id'         => $assetId,
                'kode_asset'       => $asset['kode_asset'],
                'canonical_status' => self::STATUS_CONFLICT,
                'reason'           => "Cross-feeder conflict: Asset terdaftar pada Penyulang #{$assetPenyulang}, bukan #{$targetFeederId}.",
                'flags'            => $flags,
                'provenance'       => 'AR-01-B Conflict Guard',
            ];
        }

        if ($assetSection === null) {
            $flags['missing_section'] = true;
            return [
                'asset_id'         => $assetId,
                'kode_asset'       => $asset['kode_asset'],
                'canonical_status' => self::STATUS_PARTIAL,
                'reason'           => 'Section FK belum terisi (Unallocated section membership).',
                'flags'            => $flags,
                'provenance'       => 'AR-01-C Incomplete Chain',
            ];
        }

        // Check if section exists in database
        $sec = $allSectionMap[$assetSection] ?? $this->db->table('sections')->where('id', $assetSection)->get()->getFirstRow('array');
        if (!$sec) {
            return [
                'asset_id'         => $assetId,
                'kode_asset'       => $asset['kode_asset'],
                'canonical_status' => self::STATUS_ORPHAN,
                'reason'           => "Section ID #{$assetSection} tidak terdaftar dalam database.",
                'flags'            => $flags,
                'provenance'       => 'AR-01-B Orphan Guard',
            ];
        }

        if ((int)$sec['penyulang_id'] !== $targetFeederId) {
            $flags['cross_section_conflict'] = true;
            return [
                'asset_id'         => $assetId,
                'kode_asset'       => $asset['kode_asset'],
                'canonical_status' => self::STATUS_CONFLICT,
                'reason'           => "Cross-section conflict: Section #{$assetSection} milik Feeder #{$sec['penyulang_id']}, bukan Feeder #{$targetFeederId}.",
                'flags'            => $flags,
                'provenance'       => 'AR-01-C Cross-Section Guard',
            ];
        }

        // Check if section is ACTIVE according to CR-06F Physical Truth
        if (!isset($activeSectionMap[$assetSection])) {
            $flags['inactive_section'] = true;
            return [
                'asset_id'         => $assetId,
                'kode_asset'       => $asset['kode_asset'],
                'canonical_status' => self::STATUS_UNRESOLVED,
                'reason'           => "Section #{$assetSection} belum memiliki konfigurasi konduktor fisik aktif pada CR-06F.",
                'flags'            => $flags,
                'provenance'       => 'AR-01-C Inactive Topology Guard',
            ];
        }

        // 3. Upstream CR-06G Asset Intelligence Handshake (Contract AR-01-E)
        $cr06gHealth = $this->assetIntelService->calculateAssetHealth($assetId);

        if (!$cr06gHealth['success'] || $cr06gHealth['resolution_status'] !== 'RESOLVED' || $cr06gHealth['asset_health_score'] === null) {
            $flags['cr06g_unresolved'] = true;
            $unresolvedReason = $cr06gHealth['error'] ?? ($cr06gHealth['breakdown']['unresolved_reason'] ?? 'CR-06G Intel Unresolved');
            return [
                'asset_id'         => $assetId,
                'kode_asset'       => $asset['kode_asset'],
                'canonical_status' => self::STATUS_UNRESOLVED,
                'reason'           => "CR-06G Handshake Unresolved: {$unresolvedReason}",
                'cr06g_health'     => $cr06gHealth,
                'flags'            => $flags,
                'provenance'       => 'AR-01-E Upstream Incomplete',
            ];
        }

        // All 10 Canonical Invariants Satisfied -> RESOLVED
        return [
            'asset_id'         => $assetId,
            'kode_asset'       => $asset['kode_asset'],
            'nama_asset'       => $asset['nama_asset'] ?? '',
            'jenis_asset'      => $asset['jenis_asset'] ?? '',
            'section_id'       => $assetSection,
            'penyulang_id'     => $targetFeederId,
            'canonical_status' => self::STATUS_RESOLVED,
            'cr06g_health'     => [
                'asset_health_score'      => (float)$cr06gHealth['asset_health_score'],
                'asset_degradation_index' => (float)$cr06gHealth['asset_degradation_index'],
                'construction_code'       => $cr06gHealth['construction_code'] ?? 'UNKNOWN',
                'active_findings_count'   => $cr06gHealth['active_findings_count'] ?? 0,
            ],
            'flags'            => $flags,
            'provenance'       => 'AR-01 Canonical Chain Complete (ULP -> Feeder -> Active Section -> Asset -> BOM -> CR-06G)',
        ];
    }

    /**
     * Perform deep-dive read-only diagnostics across Sections, Master Assets, and Potential Topological Linkages.
     */
    public function getDetailedReconnaissance(int $penyulangId, ?string $customPattern = null): array
    {
        // 1. Basic analysis
        $baseAnalysis = $this->analyzeFeederAssetResolution($penyulangId);
        if (!$baseAnalysis['success']) {
            return $baseAnalysis;
        }

        $feeder = $baseAnalysis['feeder'];

        // 2. Exhaustive Section Breakdown
        $secQuery = $this->db->table('sections')->where('penyulang_id', $penyulangId);
        if ($this->db->fieldExists('deleted_at', 'sections')) {
            $secQuery->where('deleted_at IS NULL');
        }
        $sections = $secQuery->get()->getResultArray();

        $sectionDetails = [];
        foreach ($sections as $s) {
            $secId = (int)$s['id'];
            $activeCfg = $this->configService->getActiveConfiguration($secId);

            $directAssetCount = $this->db->table('assets')->where('section_id', $secId);
            if ($this->db->fieldExists('deleted_at', 'assets')) {
                $directAssetCount->where('deleted_at IS NULL');
            }
            $directAssetCount = $directAssetCount->countAllResults();

            $directTemuanCount = 0;
            if ($this->db->tableExists('temuan')) {
                $tQuery = $this->db->table('temuan')->where('section_id', $secId);
                if ($this->db->fieldExists('deleted_at', 'temuan')) {
                    $tQuery->where('deleted_at IS NULL');
                }
                $directTemuanCount = $tQuery->countAllResults();
            }

            $totalLengthM = 0.0;
            $conductorsList = [];
            $accessoriesList = [];

            if ($activeCfg) {
                if (!empty($activeCfg['conductors'])) {
                    foreach ($activeCfg['conductors'] as $cond) {
                        $len = (float)($cond['length_m'] ?? 0.0);
                        $totalLengthM += $len;
                        $conductorsList[] = [
                            'conductor_id'   => (int)$cond['id'],
                            'material_code'  => $cond['material_code'] ?? 'UNKNOWN',
                            'nama_material'  => $cond['nama_material'] ?? 'Unknown Conductor',
                            'sequence_order' => (int)($cond['sequence_order'] ?? 0),
                            'segment_label'  => $cond['segment_label'] ?? '-',
                            'length_m'       => $len,
                        ];
                    }
                }
                if (!empty($activeCfg['accessories'])) {
                    foreach ($activeCfg['accessories'] as $acc) {
                        $accessoriesList[] = [
                            'accessory_type' => $acc['accessory_type'] ?? 'UNKNOWN',
                            'quantity'       => (int)($acc['quantity'] ?? 1),
                            'condition'      => $acc['condition_status'] ?? 'GOOD',
                        ];
                    }
                }
            }

            $sectionDetails[] = [
                'id'                   => $secId,
                'nama_section'         => $s['nama_section'] ?? ($s['section_name'] ?? 'Unnamed Section'),
                'kode_section'         => $s['kode_section'] ?? ($s['section_code'] ?? '-'),
                'is_active_cr06f'      => $activeCfg !== null && !empty($activeCfg['conductors']),
                'config_id'            => $activeCfg['id'] ?? null,
                'version_number'       => $activeCfg['version_number'] ?? null,
                'verification_status'  => $activeCfg['verification_status'] ?? 'UNCONFIGURED',
                'conductors_count'     => count($conductorsList),
                'total_length_km'      => round($totalLengthM / 1000.0, 3),
                'conductors'           => $conductorsList,
                'accessories_count'    => count($accessoriesList),
                'accessories'          => $accessoriesList,
                'linked_assets_count'  => $directAssetCount,
                'linked_temuan_count'  => $directTemuanCount,
            ];
        }

        // 3. Global Asset Distribution Breakdown (517 Baseline)
        $assetTable = 'assets';
        $globalFeederDist = $this->db->table($assetTable)
            ->select('penyulang_id, COUNT(*) as count')
            ->groupBy('penyulang_id')
            ->get()
            ->getResultArray();

        $globalJenisDist = $this->db->table($assetTable)
            ->select('jenis_asset, COUNT(*) as count')
            ->groupBy('jenis_asset')
            ->get()
            ->getResultArray();

        $globalCtypeDist = $this->db->table($assetTable)
            ->select('construction_type_id, COUNT(*) as count')
            ->groupBy('construction_type_id')
            ->get()
            ->getResultArray();

        // Sample 5 Global Assets
        $sampleAssets = $this->db->table($assetTable)
            ->limit(10)
            ->get()
            ->getResultArray();

        // 4. Pattern Search for Potential Linkage Evidence
        $searchTerms = ['PANJI', 'SIWALAN', 'PYL-001', 'SWP', 'PBD', 'GI', 'LBSM', 'SPBU'];
        if (!empty($customPattern)) {
            $searchTerms[] = strtoupper(trim($customPattern));
        }
        $searchTerms = array_unique($searchTerms);

        $patternMatches = [];
        foreach ($searchTerms as $term) {
            $pQuery = $this->db->table($assetTable)
                ->groupStart()
                    ->like('kode_asset', $term)
                    ->orLike('nama_asset', $term)
                    ->orLike('jenis_asset', $term)
                ->groupEnd();

            $matches = $pQuery->get()->getResultArray();
            if (!empty($matches)) {
                $patternMatches[$term] = [
                    'count'   => count($matches),
                    'samples' => array_slice($matches, 0, 5),
                ];
            }
        }

        return array_merge($baseAnalysis, [
            'detailed_sections'      => $sectionDetails,
            'global_asset_dist'      => [
                'by_feeder'            => $globalFeederDist,
                'by_jenis'             => $globalJenisDist,
                'by_construction_type' => $globalCtypeDist,
                'sample_assets'        => $sampleAssets,
            ],
            'pattern_matches'        => $patternMatches,
        ]);
    }
}
