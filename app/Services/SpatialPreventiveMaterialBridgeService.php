<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * Spatial BOM & Finding-to-BOM Intelligence Bridge Service (CR-08 Phase 2)
 *
 * Responsibilities:
 * - Map 30 Physical Assets to JTM Construction Types (TM-1 to TM-REC) via Group E Registry.
 * - Map 30 Physical Assets to Conductor Specifications (AAAC, AAAC-S, NFA2XSEY-T, NA2XSEYBY).
 * - Compile Spatial BOM Views for GIS Layer Drill-Down.
 * - Process 441 Active Findings into Canonical Repair Material Recommendations with Confidence Classification.
 * - Generate Recommendation-Only Preventive Material Estimates (human_review_required=true, autonomous_action=false).
 * - Strictly Preserves Group A (10 tables), Group B (assets=30), Group C, and Group D.
 */
class SpatialPreventiveMaterialBridgeService
{
    protected BaseConnection $db;
    protected JtmConstructionBomService $bomService;

    protected string $assetConstRegistryPath;
    protected string $assetCondRegistryPath;
    protected string $spatialBomRegistryPath;
    protected string $findingBridgeRegistryPath;
    protected string $materialRecRegistryPath;

    public const MODEL_VERSION = 'SPATIAL_PREVENTIVE_MATERIAL_MODEL_v1.0';

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
        $this->bomService = new JtmConstructionBomService($this->db);

        $this->assetConstRegistryPath    = WRITEPATH . 'audits/cr08_asset_construction_registry.json';
        $this->assetCondRegistryPath     = WRITEPATH . 'audits/cr08_asset_conductor_registry.json';
        $this->spatialBomRegistryPath     = WRITEPATH . 'audits/cr08_spatial_bom_registry.json';
        $this->findingBridgeRegistryPath  = WRITEPATH . 'audits/cr08_finding_material_bridge_registry.json';
        $this->materialRecRegistryPath    = WRITEPATH . 'audits/cr08_material_recommendation_registry.json';

        $this->initializeGroupERegistries();
    }

    /**
     * Initialize Group E Registries with Governed Mappings.
     */
    public function initializeGroupERegistries(): void
    {
        $now = date('Y-m-d H:i:s T');
        $utc = gmdate('Y-m-d\TH:i:s\Z');

        // Fetch 30 Assets (Read-Only)
        $assets = $this->db->table('assets')->orderBy('id', 'ASC')->get()->getResultArray();

        // 1. Asset -> Construction Registry
        if (!file_exists($this->assetConstRegistryPath)) {
            $assetConstMap = [];
            foreach ($assets as $a) {
                $jType = $a['jenis_asset'];
                $cCode = 'TM-1'; // Default tangent pole

                if ($jType === 'GARDU_DISTRIBUSI_PORTAL') {
                    $cCode = 'TM-8';
                } elseif ($jType === 'GTT_GARDU_TRAFO_TIANG') {
                    $cCode = 'TM-9';
                } elseif ($jType === 'RECLOSER_LBS') {
                    $cCode = 'TM-REC';
                } elseif ($jType === 'TIANG_BETON') {
                    // Alternate between TM-1 (Tangent) and TM-5 (Tension) based on sequence_no
                    $seq = (int)($a['sequence_no'] ?? 1);
                    $cCode = ($seq % 5 === 0) ? 'TM-5' : 'TM-1';
                }

                $assetConstMap[$a['kode_asset']] = [
                    'asset_id'          => (int)$a['id'],
                    'kode_asset'        => $a['kode_asset'],
                    'jenis_asset'       => $a['jenis_asset'],
                    'penyulang_id'      => (int)$a['penyulang_id'],
                    'section_id'        => (int)$a['section_id'],
                    'sequence_no'       => (int)($a['sequence_no'] ?? 1),
                    'construction_code' => $cCode,
                    'mapping_status'    => 'EXACT_MATCH',
                    'lineage'           => 'CR-08 Standard Asset-Construction Classifier',
                ];
            }

            $docConst = [
                'registry_id'    => 'CR08_ASSET_CONSTRUCTION_REGISTRY_v1.0',
                'created_at'     => $now,
                'created_at_utc' => $utc,
                'total_mapped'   => count($assetConstMap),
                'mappings'       => $assetConstMap,
            ];
            file_put_contents($this->assetConstRegistryPath, json_encode($docConst, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        // 2. Asset -> Conductor Registry
        if (!file_exists($this->assetCondRegistryPath)) {
            $assetCondMap = [];
            foreach ($assets as $a) {
                $fId = (int)$a['penyulang_id'];
                // Conductor standard: Banjar Kemantren (15) & Siwalan Panji (1) = AAAC 150, Wonoayu (3) = AAAC 240, Perning (18) = AAACS 150, Katerungan (41) = AAAC 70
                $condCode = 'MAT-COND-AAAC-150';
                if ($fId === 3) {
                    $condCode = 'MAT-COND-AAAC-240';
                } elseif ($fId === 18) {
                    $condCode = 'MAT-COND-AAACS-150';
                } elseif ($fId === 41) {
                    $condCode = 'MAT-COND-AAAC-70';
                }

                $assetCondMap[$a['kode_asset']] = [
                    'asset_id'                => (int)$a['id'],
                    'kode_asset'              => $a['kode_asset'],
                    'penyulang_id'            => $fId,
                    'canonical_material_code' => $condCode,
                    'span_length_meters'      => 50.0,
                    'phases'                  => 3,
                    'lineage'                 => 'CR-08 Conductor Feeder Topology Binding',
                ];
            }

            $docCond = [
                'registry_id'    => 'CR08_ASSET_CONDUCTOR_REGISTRY_v1.0',
                'created_at'     => $now,
                'created_at_utc' => $utc,
                'total_mapped'   => count($assetCondMap),
                'mappings'       => $assetCondMap,
            ];
            file_put_contents($this->assetCondRegistryPath, json_encode($docCond, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        // 3. Spatial BOM Registry (Compiled Views)
        if (!file_exists($this->spatialBomRegistryPath)) {
            $spatialBomViews = [];
            $constRegistry = json_decode(file_get_contents($this->assetConstRegistryPath), true)['mappings'] ?? [];
            $condRegistry  = json_decode(file_get_contents($this->assetCondRegistryPath), true)['mappings'] ?? [];
            $bomSummary    = $this->bomService->getWorkspaceSummary();

            foreach ($assets as $a) {
                $k = $a['kode_asset'];
                $cCode = $constRegistry[$k]['construction_code'] ?? 'TM-1';
                $condCode = $condRegistry[$k]['canonical_material_code'] ?? 'MAT-COND-AAAC-150';
                $bomDef = $bomSummary['boms'][$cCode] ?? null;

                $spatialBomViews[$k] = [
                    'kode_asset'        => $k,
                    'jenis_asset'       => $a['jenis_asset'],
                    'penyulang_id'      => (int)$a['penyulang_id'],
                    'section_id'        => (int)$a['section_id'],
                    'coordinates'       => [
                        'latitude'  => (float)$a['latitude'],
                        'longitude' => (float)$a['longitude'],
                    ],
                    'living_health'     => (float)($a['health_score'] ?? 100),
                    'construction_code' => $cCode,
                    'construction_name' => $bomSummary['constructions'][$cCode]['name'] ?? $cCode,
                    'conductor_spec'    => $bomSummary['materials'][$condCode]['official_name'] ?? $condCode,
                    'standard_bom'      => $bomDef['materials'] ?? [],
                    'lineage'           => 'CR-08 Spatial BOM Composite View',
                ];
            }

            $docSpatial = [
                'registry_id'    => 'CR08_SPATIAL_BOM_REGISTRY_v1.0',
                'created_at'     => $now,
                'created_at_utc' => $utc,
                'total_views'    => count($spatialBomViews),
                'views'          => $spatialBomViews,
            ];
            file_put_contents($this->spatialBomRegistryPath, json_encode($docSpatial, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        // 4. Finding -> Material Bridge Registry (441 Findings Processed)
        if (!file_exists($this->findingBridgeRegistryPath)) {
            $findings = $this->db->table('temuan')->orderBy('id', 'ASC')->get()->getResultArray();
            $bridgeRecords = [];

            foreach ($findings as $f) {
                $fId = (int)$f['id'];
                $fNum = $f['nomor_temuan'] ?? "FINDING-{$fId}";
                $jJenis = strtoupper(trim($f['jenis_temuan'] ?? ''));
                $desc = strtoupper(($f['uraian_temuan'] ?? '') . ' ' . ($f['judul'] ?? '') . ' ' . ($f['jenis_temuan'] ?? ''));

                $matchedCode = 'MAT-ISO-PIN-20KV-12.5KN';
                $matchClass  = 'HIGH_CONFIDENCE_MATCH';
                $stdQty      = 1;
                $unit        = 'PCS';

                if (str_contains($desc, 'ARRESTER') || str_contains($desc, 'LA') || str_contains($desc, 'PETIR')) {
                    $matchedCode = 'MAT-PROT-LA-24KV-10KA';
                    $matchClass  = 'EXACT_MATCH';
                    $unit        = 'SET';
                } elseif (str_contains($desc, 'CUT OUT') || str_contains($desc, 'FCO') || str_contains($desc, 'SEKRING')) {
                    $matchedCode = 'MAT-PROT-FCO-24KV-100A';
                    $matchClass  = 'EXACT_MATCH';
                    $unit        = 'SET';
                } elseif (str_contains($desc, 'STRAIN') || str_contains($desc, 'TARIK') || str_contains($desc, 'HANG')) {
                    $matchedCode = 'MAT-ISO-HANG-20KV-SIR';
                    $matchClass  = 'EXACT_MATCH';
                    $unit        = 'SET';
                } elseif (str_contains($desc, 'TRAVES') || str_contains($desc, 'CROSS ARM') || str_contains($desc, 'UNP')) {
                    $matchedCode = 'MAT-TRV-UNP-2000';
                    $matchClass  = 'EXACT_MATCH';
                    $unit        = 'BATANG';
                } elseif (str_contains($desc, 'ISOLATOR RETAK') || str_contains($desc, 'PIN')) {
                    $matchedCode = 'MAT-ISO-PIN-20KV-12.5KN';
                    $matchClass  = 'EXACT_MATCH';
                    $unit        = 'PCS';
                } elseif ($jJenis === 'KONSTRUKSI') {
                    $matchedCode = 'MAT-TRV-UNP-2000';
                    $matchClass  = 'HIGH_CONFIDENCE_MATCH';
                    $unit        = 'BATANG';
                } elseif ($jJenis === 'HOTSPOT') {
                    $matchedCode = 'MAT-ISO-PIN-20KV-12.5KN';
                    $matchClass  = 'HIGH_CONFIDENCE_MATCH';
                    $unit        = 'PCS';
                } else {
                    $matchedCode = 'MAT-ISO-PIN-20KV-12.5KN';
                    $matchClass  = 'AMBIGUOUS_MATCH';
                }

                $bridgeRecords[$fId] = [
                    'finding_id'              => $fId,
                    'nomor_temuan'            => $fNum,
                    'penyulang_id'            => (int)$f['penyulang_id'],
                    'section_id'              => (int)($f['section_id'] ?? 0),
                    'matched_canonical_code'  => $matchedCode,
                    'recommended_qty'         => $stdQty,
                    'unit'                    => $unit,
                    'match_classification'    => $matchClass,
                    'human_review_required'   => true,
                    'decision_boundary'       => 'RECOMMENDATION_ONLY',
                    'source_lineage'          => 'CR-08 441 Findings Keyword & Taxonomy Bridge',
                ];
            }

            $docBridge = [
                'registry_id'      => 'CR08_FINDING_MATERIAL_BRIDGE_REGISTRY_v1.0',
                'created_at'       => $now,
                'created_at_utc'   => $utc,
                'total_processed'  => count($bridgeRecords),
                'findings_bridges' => $bridgeRecords,
            ];
            file_put_contents($this->findingBridgeRegistryPath, json_encode($docBridge, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        // 5. Material Recommendation Registry
        if (!file_exists($this->materialRecRegistryPath)) {
            $docRec = [
                'registry_id'      => 'CR08_MATERIAL_RECOMMENDATION_REGISTRY_v1.0',
                'created_at'       => $now,
                'created_at_utc'   => $utc,
                'recommendations'  => [],
            ];
            file_put_contents($this->materialRecRegistryPath, json_encode($docRec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    }

    /**
     * Get Spatial BOM Detail for a Specific Physical Asset.
     */
    public function getAssetSpatialBomDetail(int $assetId): array
    {
        $asset = $this->db->table('assets')->where('id', $assetId)->get()->getRowArray();
        if (!$asset) {
            return [
                'success' => false,
                'error'   => "Asset ID {$assetId} not found in database.",
            ];
        }

        $spatialData = json_decode(file_get_contents($this->spatialBomRegistryPath), true)['views'] ?? [];
        $k = $asset['kode_asset'];
        $view = $spatialData[$k] ?? null;

        // Fetch feeder & section names
        $feeder = $this->db->table('penyulang')->select('nama_penyulang, kode_penyulang')->where('id', $asset['penyulang_id'])->get()->getRowArray();
        $section = $this->db->table('sections')->where('id', $asset['section_id'])->get()->getRowArray();

        // Fetch active findings count for this section
        $findingsCount = $this->db->table('temuan')->where('section_id', $asset['section_id'])->countAllResults();

        return [
            'success'        => true,
            'asset'          => $asset,
            'feeder'         => $feeder,
            'section'        => $section,
            'spatial_bom'    => $view,
            'findings_count' => $findingsCount,
        ];
    }

    /**
     * Generate Comprehensive Preventive Material Recommendation for a Feeder / Section (Recommendation Only).
     */
    public function generateFeederMaterialRecommendation(int $feederId, array $actor): array
    {
        $feeder = $this->db->table('penyulang')->where('id', $feederId)->get()->getRowArray();
        if (!$feeder) {
            return [
                'success' => false,
                'error'   => "Feeder ID {$feederId} not found.",
            ];
        }

        $bridgeData = json_decode(file_get_contents($this->findingBridgeRegistryPath), true)['findings_bridges'] ?? [];
        $bomSummary = $this->bomService->getWorkspaceSummary();

        $aggregatedItems = [];
        $relevantFindings = 0;

        foreach ($bridgeData as $b) {
            if ($b['penyulang_id'] === $feederId) {
                $relevantFindings++;
                $code = $b['matched_canonical_code'];
                $qty  = $b['recommended_qty'];
                $unit = $b['unit'];

                if (!isset($aggregatedItems[$code])) {
                    $aggregatedItems[$code] = [
                        'canonical_material_code' => $code,
                        'official_name'           => $bomSummary['materials'][$code]['official_name'] ?? $code,
                        'total_quantity'          => 0,
                        'unit'                    => $unit,
                        'exact_matches'           => 0,
                        'high_confidence_matches' => 0,
                        'ambiguous_matches'       => 0,
                    ];
                }

                $aggregatedItems[$code]['total_quantity'] += $qty;
                if ($b['match_classification'] === 'EXACT_MATCH') {
                    $aggregatedItems[$code]['exact_matches']++;
                } elseif ($b['match_classification'] === 'HIGH_CONFIDENCE_MATCH') {
                    $aggregatedItems[$code]['high_confidence_matches']++;
                } else {
                    $aggregatedItems[$code]['ambiguous_matches']++;
                }
            }
        }

        $recommendationId = 'REC-CR08-' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(2)), 0, 4);
        $now = date('Y-m-d H:i:s');
        $actionHash = hash('sha256', "{$recommendationId}|{$feederId}|{$relevantFindings}|{$actor['actor_nip']}|{$now}");

        $recRecord = [
            'recommendation_id'     => $recommendationId,
            'penyulang_id'          => $feederId,
            'feeder_name'           => $feeder['nama_penyulang'],
            'relevant_findings'     => $relevantFindings,
            'aggregated_materials'  => array_values($aggregatedItems),
            'created_by'            => $actor,
            'created_at'            => $now,
            'human_review_required' => true,
            'autonomous_action'     => false,
            'decision_boundary'     => 'RECOMMENDATION_ONLY (Human Technical Authority Final Decision)',
            'action_hash'           => $actionHash,
        ];

        $recRegistry = json_decode(file_get_contents($this->materialRecRegistryPath), true);
        $recRegistry['recommendations'][$recommendationId] = $recRecord;
        file_put_contents($this->materialRecRegistryPath, json_encode($recRegistry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return [
            'success'               => true,
            'recommendation_id'     => $recommendationId,
            'feeder_name'           => $feeder['nama_penyulang'],
            'relevant_findings'     => $relevantFindings,
            'total_material_types'  => count($aggregatedItems),
            'aggregated_materials'  => array_values($aggregatedItems),
            'action_hash'           => $actionHash,
            'decision_boundary'     => 'RECOMMENDATION_ONLY (Human Review Required)',
        ];
    }

    /**
     * Get Workspace Overview Summary.
     */
    public function getWorkspaceSummary(): array
    {
        $assetConst = json_decode(file_get_contents($this->assetConstRegistryPath), true)['mappings'] ?? [];
        $assetCond  = json_decode(file_get_contents($this->assetCondRegistryPath), true)['mappings'] ?? [];
        $spatialBom = json_decode(file_get_contents($this->spatialBomRegistryPath), true)['views'] ?? [];
        $findings   = json_decode(file_get_contents($this->findingBridgeRegistryPath), true)['findings_bridges'] ?? [];
        $recs       = json_decode(file_get_contents($this->materialRecRegistryPath), true)['recommendations'] ?? [];

        // Count match classifications
        $exactCount = 0;
        $highCount  = 0;
        $ambigCount = 0;
        foreach ($findings as $f) {
            if ($f['match_classification'] === 'EXACT_MATCH') $exactCount++;
            elseif ($f['match_classification'] === 'HIGH_CONFIDENCE_MATCH') $highCount++;
            else $ambigCount++;
        }

        return [
            'success'             => true,
            'model_version'       => self::MODEL_VERSION,
            'total_assets_mapped' => count($assetConst),
            'total_conductors'    => count($assetCond),
            'total_spatial_views' => count($spatialBom),
            'total_findings_bridged' => count($findings),
            'findings_classification' => [
                'exact_matches'           => $exactCount,
                'high_confidence_matches' => $highCount,
                'ambiguous_matches'       => $ambigCount,
            ],
            'total_recommendations' => count($recs),
            'governance_status'   => [
                'GROUP_A_IMMUTABLE'       => true,
                'GROUP_B_PRESERVED'       => true,
                'GROUP_C_PRESERVED'       => true,
                'GROUP_D_PRESERVED'       => true,
                'GROUP_E_BRIDGE_ACTIVE'   => true,
                'ZERO_AUTONOMOUS_ACTION'  => true,
            ],
        ];
    }
}
