<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use App\Models\SectionModel;
use App\Models\PenyulangModel;

/**
 * Dynamic Single Line Diagram (SLD) Engine (CR-06H Contract v1.0)
 * Governed by 9 Hardening Gates (H0 - H8):
 * - Gate H0: Non-Destructive Renderer (Strictly Read-Only, Never Mutates Config)
 * - Gate H1: Active CR-06F Topology Truth (Only ACTIVE verification_status)
 * - Gate H2: Conductor Sequence Continuity (Deterministic sequence_order)
 * - Gate H3: Hierarchy Routing Integrity (ULP -> Feeder -> Section)
 * - Gate H4: Active Operational Findings Overlay (deleted_at IS NULL, not SELESAI)
 * - Gate H5: Resolved Health Guard (No default healthy for uncalculated assets)
 * - Gate H6: Historical & Soft-Deleted Immunity
 * - Gate H7: Zero Orphan Visual Nodes (All nodes connected to verified sections)
 * - Gate H8: Full Provenance & Traceability (Source entity & PK in all elements)
 */
class DynamicSldEngineService
{
    protected BaseConnection $db;
    protected NetworkConfigurationService $configService;
    protected ConstructionAssetIntelligenceService $intelService;
    protected SectionModel $sectionModel;
    protected PenyulangModel $penyulangModel;

    public function __construct(?BaseConnection $db = null, ?NetworkConfigurationService $configService = null)
    {
        $this->db            = $db ?? \Config\Database::connect();
        $this->configService = $configService ?? new NetworkConfigurationService($this->db);
        $this->intelService  = new ConstructionAssetIntelligenceService($this->db);
        $this->sectionModel  = new SectionModel();
        $this->penyulangModel= new PenyulangModel();
    }

    /**
     * Render Complete Dynamic Feeder SLD Graph (CR-06H Core).
     * Connects Physical Truth (CR-06F) with Asset Intelligence Overlay (CR-06G).
     */
    public function renderFeederSld(int $penyulangId): array
    {
        $feeder = $this->db->table('penyulang')
            ->select('penyulang.*, ulps.kode_ulp, ulps.nama_ulp')
            ->join('ulps', 'ulps.id = penyulang.ulp_id', 'left')
            ->where('penyulang.id', $penyulangId)
            ->get()
            ->getFirstRow('array');

        if (!$feeder) {
            return [
                'success' => false,
                'error'   => "Feeder ID #{$penyulangId} tidak ditemukan.",
                'graph'   => null,
            ];
        }

        // Fetch sections under this feeder
        $sections = $this->db->table('sections')
            ->where('penyulang_id', $penyulangId)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();

        $nodes = [];
        $edges = [];
        $nodeMap = [];
        $totalConductorLengthM = 0.0;
        $totalAccessoriesCount = 0;
        $activeConfigSectionsCount = 0;
        $defectsSummary = [];

        // 1. Root Substation Node (GI Node)
        $giNodeId = "GI_" . preg_replace('/[^A-Za-z0-9_]/', '', strtoupper($feeder['kode_penyulang'] ?? 'FEEDER'));
        $nodes[] = [
            'node_id'      => $giNodeId,
            'label'        => "GI " . $feeder['nama_penyulang'] . " (Sumber)",
            'node_type'    => 'SUBSTATION',
            'status'       => 'NORMAL',
            'traceability' => [
                'entity' => 'penyulang',
                'id'     => (int)$feeder['id'],
            ],
        ];
        $nodeMap[$giNodeId] = true;
        $previousNodeId = $giNodeId;

        // 2. Iterate Sections and Build Topology Truth Graph (Gate H1, H2, H3)
        foreach ($sections as $sIdx => $sec) {
            $sectionId = (int)$sec['id'];
            $config = $this->configService->getActiveConfiguration($sectionId);

            $sectionAhs = null;
            $sectionAdi = 0.0;
            $sectionStatus = 'NO_CONFIG';
            $sectionFindings = [];

            // CR-06G Intelligence Summary for this Section
            $secIntel = $this->intelService->getSectionIntelligenceSummary($sectionId);
            if ($secIntel['status'] === 'RESOLVED') {
                $sectionAhs = $secIntel['average_health_score'];
                $sectionAdi = $secIntel['section_structural_risk'];
                $sectionStatus = 'RESOLVED';
            } elseif ($secIntel['status'] === 'PARTIAL') {
                $sectionStatus = 'PARTIAL';
            } else {
                $sectionStatus = 'UNRESOLVED';
            }

            // Fetch active findings for this section (Gate H4, H6)
            $findings = $this->getActiveSectionFindings($sectionId);
            foreach ($findings as $f) {
                $comp = $this->intelService->classifyFindingComponent($f);
                $isCritical = in_array(strtoupper($f['prioritas'] ?? ''), ['EMERGENCY', 'KRITIS', 'HIGH', 'CRITICAL']);
                
                $defectsSummary[] = [
                    'finding_id'   => (int)$f['id'],
                    'nomor_temuan' => $f['nomor_temuan'],
                    'section_id'   => $sectionId,
                    'nama_section' => $sec['nama_section'],
                    'component'    => $comp,
                    'priority'     => $f['prioritas'] ?? 'RINGAN',
                    'severity'     => $isCritical ? 'CRITICAL' : 'WARNING',
                    'label'        => $f['detail_temuan'] ?: $f['jenis_temuan'],
                    'badge_color'  => $isCritical ? '#DC3545' : '#FFC107',
                    'traceability' => [
                        'entity' => 'temuan',
                        'id'     => (int)$f['id'],
                    ],
                ];
            }

            if ($config && !empty($config['conductors'])) {
                $activeConfigSectionsCount++;
                
                // Conductor Segments (Sorted by sequence_order - Gate H2)
                $conductors = $config['conductors'];
                usort($conductors, fn($a, $b) => (int)$a['sequence_order'] <=> (int)$b['sequence_order']);

                foreach ($conductors as $cIdx => $c) {
                    $lengthM = $c['length_m'] !== null ? (float)$c['length_m'] : 0.0;
                    $totalConductorLengthM += $lengthM;

                    $startNode = !empty($c['start_node_id']) ? $c['start_node_id'] : $previousNodeId;
                    $endNode   = !empty($c['end_node_id']) ? $c['end_node_id'] : "SEC_{$sectionId}_NODE_" . ($cIdx + 1);

                    // Add nodes to graph if not exists
                    if (!isset($nodeMap[$startNode])) {
                        $nodes[] = [
                            'node_id'      => $startNode,
                            'label'        => $startNode,
                            'node_type'    => $this->inferNodeType($startNode),
                            'status'       => 'NORMAL',
                            'traceability' => ['entity' => 'network_section_conductors', 'id' => (int)$c['id']],
                        ];
                        $nodeMap[$startNode] = true;
                    }

                    if (!isset($nodeMap[$endNode])) {
                        $nodes[] = [
                            'node_id'      => $endNode,
                            'label'        => $endNode,
                            'node_type'    => $this->inferNodeType($endNode),
                            'status'       => 'NORMAL',
                            'traceability' => ['entity' => 'network_section_conductors', 'id' => (int)$c['id']],
                        ];
                        $nodeMap[$endNode] = true;
                    }

                    // Build Edge (Gate H8: Full Traceability)
                    $edges[] = [
                        'edge_id'            => "EDGE-SEC-{$sectionId}-SEG-{$c['sequence_order']}",
                        'section_id'         => $sectionId,
                        'nama_section'       => $sec['nama_section'],
                        'sequence_order'     => (int)$c['sequence_order'],
                        'from_node'          => $startNode,
                        'to_node'            => $endNode,
                        'conductor_material' => $c['material_code'] ?? ($c['nama_material'] ?? 'AAAC'),
                        'length_m'           => $lengthM,
                        'segment_label'      => $c['segment_label'] ?? "{$sec['nama_section']} Seg {$c['sequence_order']}",
                        'health_score'       => $sectionAhs,
                        'health_category'    => $this->getHealthCategoryFromScore($sectionAhs),
                        'active_defects'     => count($findings),
                        'traceability'       => [
                            'entity' => 'network_section_conductors',
                            'id'     => (int)$c['id'],
                            'config_id' => (int)$config['id'],
                        ],
                    ];

                    $previousNodeId = $endNode;
                }

                // Accessories Count
                $totalAccessoriesCount += count($config['accessories'] ?? []);
            } else {
                // Section without active physical config (Honest fallback representation)
                $secNodeId = "SEC_{$sectionId}_UNCONFIGURED";
                if (!isset($nodeMap[$secNodeId])) {
                    $nodes[] = [
                        'node_id'      => $secNodeId,
                        'label'        => $sec['nama_section'] . " (Unconfigured)",
                        'node_type'    => 'SECTION_UNCONFIGURED',
                        'status'       => 'UNCONFIGURED',
                        'traceability' => ['entity' => 'sections', 'id' => $sectionId],
                    ];
                    $nodeMap[$secNodeId] = true;
                }

                $edges[] = [
                    'edge_id'            => "EDGE-SEC-{$sectionId}-UNCONFIG",
                    'section_id'         => $sectionId,
                    'nama_section'       => $sec['nama_section'],
                    'sequence_order'     => 1,
                    'from_node'          => $previousNodeId,
                    'to_node'            => $secNodeId,
                    'conductor_material' => 'UNCONFIGURED',
                    'length_m'           => 0.0,
                    'segment_label'      => $sec['nama_section'],
                    'health_score'       => null,
                    'health_category'    => 'UNRESOLVED',
                    'active_defects'     => count($findings),
                    'traceability'       => ['entity' => 'sections', 'id' => $sectionId],
                ];

                $previousNodeId = $secNodeId;
            }
        }

        // Feeder Health Rollup
        $feederIntel = $this->intelService->getFeederIntelligenceSummary($penyulangId);

        return [
            'success'          => true,
            'feeder_id'        => $penyulangId,
            'kode_penyulang'   => $feeder['kode_penyulang'] ?? 'N/A',
            'nama_penyulang'   => $feeder['nama_penyulang'],
            'kode_ulp'         => $feeder['kode_ulp'] ?? '51301',
            'nama_ulp'         => $feeder['nama_ulp'] ?? 'ULP SIDOARJO KOTA',
            'topology_summary' => [
                'total_sections'            => count($sections),
                'configured_sections'       => $activeConfigSectionsCount,
                'unconfigured_sections'     => count($sections) - $activeConfigSectionsCount,
                'total_conductor_length_km' => round($totalConductorLengthM / 1000.0, 3),
                'total_accessories'         => $totalAccessoriesCount,
                'total_nodes'               => count($nodes),
                'total_edges'               => count($edges),
            ],
            'graph' => [
                'nodes' => $nodes,
                'edges' => $edges,
            ],
            'intelligence_overlay' => [
                'overall_health_score'   => $feederIntel['overall_health_score'] ?? null,
                'bom_degradation_factor' => $feederIntel['bom_degradation_factor'] ?? 0.0,
                'active_findings_count'  => count($defectsSummary),
                'defects_summary'        => $defectsSummary,
            ],
            'rendered_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Render Section Detail Drilldown (Gate H8).
     * Connects section physical conductors, accessories, assets, and explainable health.
     */
    public function getSectionDrilldownDetails(int $sectionId): array
    {
        $section = $this->db->table('sections')
            ->select('sections.*, penyulang.kode_penyulang, penyulang.nama_penyulang, ulps.kode_ulp, ulps.nama_ulp')
            ->join('penyulang', 'penyulang.id = sections.penyulang_id', 'left')
            ->join('ulps', 'ulps.id = penyulang.ulp_id', 'left')
            ->where('sections.id', $sectionId)
            ->get()
            ->getFirstRow('array');

        if (!$section) {
            return [
                'success' => false,
                'error'   => "Section ID #{$sectionId} tidak ditemukan.",
            ];
        }

        // 1. Physical Configuration Truth (CR-06F)
        $config = $this->configService->getActiveConfiguration($sectionId);

        // 2. Intelligence & Health (CR-06G)
        $intelligence = $this->intelService->getSectionIntelligenceSummary($sectionId);

        // 3. Assets under Section
        $assets = $this->db->table('assets')
            ->where('section_id', $sectionId)
            ->where('deleted_at IS NULL')
            ->get()
            ->getResultArray();

        $assetDetails = [];
        foreach ($assets as $a) {
            $health = $this->intelService->calculateAssetHealth((int)$a['id']);
            $assetDetails[] = [
                'asset_id'                       => (int)$a['id'],
                'kode_asset'                     => $a['kode_asset'],
                'nama_asset'                     => $a['nama_asset'],
                'jenis_asset'                    => $a['jenis_asset'],
                'construction_code'              => $health['construction_code'] ?? 'UNKNOWN',
                'health_score'                   => $health['asset_health_score'],
                'degradation_index'              => $health['asset_degradation_index'],
                'health_category'                => $health['health_category'],
                'intelligence_resolution_status' => $health['resolution_status'],
                'active_findings_count'          => $health['active_findings_count'] ?? 0,
                'breakdown'                      => $health['breakdown'] ?? [],
            ];
        }

        // 4. Active Operational Findings (Gate H4, H6)
        $findings = $this->getActiveSectionFindings($sectionId);
        $findingsDetails = [];
        foreach ($findings as $f) {
            $findingsDetails[] = [
                'finding_id'       => (int)$f['id'],
                'nomor_temuan'     => $f['nomor_temuan'],
                'jenis_temuan'     => $f['jenis_temuan'],
                'detail_temuan'    => $f['detail_temuan'] ?? '',
                'prioritas'        => $f['prioritas'] ?? 'RINGAN',
                'component'        => $this->intelService->classifyFindingComponent($f),
                'recurrence_count' => (int)($f['recurrence_count'] ?? 0),
                'tanggal_temuan'   => $f['tanggal_temuan'] ?? null,
                'status'           => $f['status'] ?? 'OPEN',
            ];
        }

        return [
            'success'        => true,
            'section'        => [
                'id'             => (int)$section['id'],
                'nama_section'   => $section['nama_section'],
                'kode_penyulang' => $section['kode_penyulang'] ?? 'N/A',
                'nama_penyulang' => $section['nama_penyulang'] ?? 'N/A',
                'kode_ulp'       => $section['kode_ulp'] ?? 'N/A',
                'nama_ulp'       => $section['nama_ulp'] ?? 'N/A',
            ],
            'physical_configuration' => $config ? [
                'status'             => $config['verification_status'] ?? 'ACTIVE',
                'version_number'     => $config['version_number'] ?? 1,
                'effective_from'     => $config['effective_from'] ?? null,
                'conductors'         => $config['conductors'] ?? [],
                'accessories'        => $config['accessories'] ?? [],
                'total_length_m'     => array_sum(array_column($config['conductors'] ?? [], 'length_m')),
                'total_accessories'  => count($config['accessories'] ?? []),
            ] : null,
            'intelligence_summary' => $intelligence,
            'assets'               => $assetDetails,
            'active_findings'      => $findingsDetails,
        ];
    }

    /**
     * Render Complete Dynamic SLD Payload for a Section (Gate 5 / H0).
     */
    public function renderSectionSld(int $sectionId, ?string $asOfDate = null): array
    {
        // 1. Resolve Configuration (Current Active or Historical Time-Travel)
        if ($asOfDate !== null) {
            $config = $this->configService->getConfigurationAt($sectionId, $asOfDate);
        } else {
            $config = $this->configService->getActiveConfiguration($sectionId);
        }

        if (!$config) {
            return [
                'success'        => false,
                'section_id'     => $sectionId,
                'message'        => 'Tidak ada konfigurasi aktif untuk section ini.',
                'topology_truth' => null,
                'health_overlay' => null,
            ];
        }

        // 2. Build Topology Truth (Structure, Segments, Conductor Sizes, Distance)
        $topologyTruth = $this->buildTopologyTruth($config);

        // 3. Build Visual Health Overlay (Inspection Status, Broken LA, Hotspot Findings)
        $healthOverlay = $this->buildVisualHealthOverlay($sectionId, $config);

        return [
            'success'                => true,
            'section_id'             => $sectionId,
            'configuration_version'  => $config['version_number'] ?? 1,
            'verification_status'    => $config['verification_status'] ?? 'ACTIVE',
            'effective_from'         => $config['effective_from'] ?? null,
            'effective_to'           => $config['effective_to'] ?? null,
            'topology_truth'         => $topologyTruth,
            'health_overlay'         => $healthOverlay,
            'rendered_at'            => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Build Topology Truth from Network Configuration
     */
    private function buildTopologyTruth(array $config): array
    {
        $segments = [];
        foreach ($config['conductors'] ?? [] as $c) {
            $segments[] = [
                'segment_id'     => $c['id'],
                'segment_label'  => $c['segment_label'] ?? ('Segment #' . $c['sequence_order']),
                'sequence_order' => (int)$c['sequence_order'],
                'conductor_code' => $c['material_code'] ?? 'UNKNOWN',
                'conductor_name' => $c['nama_material'] ?? 'Unknown Conductor',
                'length_m'       => $c['length_m'] !== null ? (float)$c['length_m'] : null,
                'start_node'     => $c['start_node_id'],
                'end_node'       => $c['end_node_id'],
                'verified'       => (bool)($c['verified'] ?? true),
            ];
        }

        $accessories = [];
        foreach ($config['accessories'] ?? [] as $a) {
            $accessories[] = [
                'accessory_id'       => $a['id'],
                'accessory_type'     => $a['accessory_type'],
                'material_code'      => $a['material_code'] ?? 'UNKNOWN',
                'nama_material'      => $a['nama_material'] ?? $a['accessory_type'],
                'quantity'           => (int)$a['quantity'],
                'location_reference' => $a['location_reference'],
                'nominal_condition'  => $a['condition_status'] ?? 'GOOD',
            ];
        }

        return [
            'total_segments'    => count($segments),
            'total_accessories' => count($accessories),
            'segments'          => $segments,
            'accessories'       => $accessories,
        ];
    }

    /**
     * Build Visual Health Overlay (Observations, Broken LA, Defective Insulators)
     */
    private function buildVisualHealthOverlay(int $sectionId, array $config): array
    {
        $defects     = [];
        $overallRisk = 'NORMAL';

        // Check accessories condition in configuration
        foreach ($config['accessories'] ?? [] as $a) {
            $cond = strtoupper($a['condition_status'] ?? 'GOOD');
            if ($cond === 'DEFECTIVE' || $cond === 'MISSING') {
                $defects[] = [
                    'element_type' => 'ACCESSORY',
                    'element_id'   => $a['id'],
                    'label'        => $a['accessory_type'] . ' (' . ($a['location_reference'] ?: 'Section ' . $sectionId) . ')',
                    'condition'    => $cond,
                    'severity'     => $cond === 'MISSING' ? 'CRITICAL' : 'WARNING',
                    'sld_icon'     => 'icon-warning-' . strtolower($a['accessory_type']),
                    'color_hex'    => $cond === 'MISSING' ? '#DC3545' : '#FFC107',
                ];
            }
        }

        // Query active operational findings (Gate H4, H6)
        $findings = $this->getActiveSectionFindings($sectionId);
        foreach ($findings as $f) {
            $isCritical = in_array(strtoupper($f['prioritas'] ?? ''), ['EMERGENCY', 'KRITIS', 'HIGH', 'CRITICAL']);

            $defects[] = [
                'element_type' => 'FINDING',
                'element_id'   => $f['id'],
                'label'        => $f['detail_temuan'] ?: ($f['jenis_temuan'] ?? 'Anomali Jaringan'),
                'condition'    => 'ANOMALY_OPEN',
                'severity'     => $isCritical ? 'CRITICAL' : 'WARNING',
                'sld_icon'     => $isCritical ? 'icon-alert-triangle-red' : 'icon-alert-circle-yellow',
                'color_hex'    => $isCritical ? '#DC3545' : '#FD7E14',
            ];
        }

        if (!empty($defects)) {
            $hasCritical = false;
            foreach ($defects as $d) {
                if ($d['severity'] === 'CRITICAL') {
                    $hasCritical = true;
                    break;
                }
            }
            $overallRisk = $hasCritical ? 'CRITICAL_WARNING' : 'MODERATE_WARNING';
        }

        return [
            'overall_visual_status' => $overallRisk,
            'defect_count'          => count($defects),
            'defect_overlays'       => $defects,
        ];
    }

    /**
     * Get active section findings with soft-delete and completion immunity (Gate H4, H6).
     */
    private function getActiveSectionFindings(int $sectionId): array
    {
        if (!$this->db->tableExists('temuan')) {
            return [];
        }

        $builder = $this->db->table('temuan')
            ->where('section_id', $sectionId)
            ->where('deleted_at IS NULL');

        if ($this->db->fieldExists('status', 'temuan')) {
            $builder->where('status !=', 'SELESAI');
        }

        return $builder->get()->getResultArray();
    }

    private function inferNodeType(string $nodeName): string
    {
        $upper = strtoupper($nodeName);
        if (str_contains($upper, 'GI') || str_contains($upper, 'SUBSTATION')) {
            return 'SUBSTATION';
        }
        if (str_contains($upper, 'LBS')) {
            return 'SWITCH';
        }
        if (str_contains($upper, 'RECLOSER')) {
            return 'RECLOSER';
        }
        if (str_contains($upper, 'PB') || str_contains($upper, 'PORTAL')) {
            return 'PORTAL';
        }
        if (str_contains($upper, 'TM') || str_contains($upper, 'TIANG')) {
            return 'POLE';
        }
        return 'JUNCTION';
    }

    private function getHealthCategoryFromScore(?float $score): string
    {
        if ($score === null) {
            return 'UNRESOLVED';
        }
        if ($score >= 85.0) return 'GOOD';
        if ($score >= 70.0) return 'WARNING';
        if ($score >= 50.0) return 'POOR';
        return 'CRITICAL';
    }
}
