<?php

namespace App\Services;

use Config\Database;
use CodeIgniter\Database\BaseConnection;
use App\Services\AssetHealthScoringEngine;

/**
 * B8.4 GIS / SLD Decision Cockpit Read-Model Service
 *
 * System Domain: TRACK_C_DECISION_INTELLIGENCE
 * Hard Guards: B84-G01 through B84-G10
 * Master Invariant: Delta_topology = 0 (Strictly Read-Only Projection)
 */
class GisSldCockpitService
{
    public const SNAPSHOT_ID = 'TOPOLOGY-20260928-245-81c43a7f';
    public const SPEC_VERSION = 'v0.1';

    protected BaseConnection $db;
    protected AssetHealthScoringEngine $ahiEngine;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect('default');
        $this->ahiEngine = new AssetHealthScoringEngine();
    }

    /**
     * B84-G05: Spatial GeoJSON Read API Endpoint Data
     * Generates RFC 7946 compliant FeatureCollection containing 6 integrated operational layers.
     * Strictly Read-Only (B84-G01).
     */
    public function getSpatialGeoJson(array $filters = []): array
    {
        // 1. Fetch Active Physical Assets (Layer 1 Base Topology)
        $assetBuilder = $this->db->table('assets')
            ->where('deleted_at IS NULL');

        if (!empty($filters['ulp'])) {
            $assetBuilder->where('unit_up', $filters['ulp']);
        }
        if (!empty($filters['penyulang'])) {
            $assetBuilder->where('penyulang', $filters['penyulang']);
        }

        $assets = $assetBuilder->get()->getResultArray();

        // 2. Fetch Active Translines (Layer 1 Base Edge Topology)
        $translineBuilder = $this->db->table('gis_translines')
            ->where('deleted_at IS NULL');

        if (!empty($filters['is_active_only'])) {
            $translineBuilder->where('is_active', 1);
        }

        $translines = $translineBuilder->get()->getResultArray();

        // 3. Batch Fetch Operational Overlays (Layers 2-6)
        $ahiScores          = $this->fetchAhiScoresMap();
        $fliHypotheses      = $this->fetchFliHypothesesMap();
        $findingsMap        = $this->fetchActiveFindingsMap();
        $remediationWpMap   = $this->fetchRemediationWpMap();
        $materialReqMap     = $this->fetchMaterialReqMap();

        $features = [];

        // Build Asset Point Features
        foreach ($assets as $asset) {
            $assetId = (int)$asset['id'];

            // Layer 2: AHI Score & Category
            $ahiData = $ahiScores[$assetId] ?? [
                'ahi_score'       => 100.0,
                'health_category' => 'EXCELLENT',
            ];

            if (!empty($filters['health_category']) && $ahiData['health_category'] !== strtoupper($filters['health_category'])) {
                continue;
            }

            // Extract Coordinates
            $lat = isset($asset['latitude']) ? (float)$asset['latitude'] : (isset($asset['lat']) ? (float)$asset['lat'] : 0.0);
            $lng = isset($asset['longitude']) ? (float)$asset['longitude'] : (isset($asset['lng']) ? (float)$asset['lng'] : 0.0);

            // Default mock coordinates if missing for visualization
            if ($lat == 0.0 && $lng == 0.0) {
                $lat = -7.4500 + ($assetId % 100) * 0.001;
                $lng = 112.7200 + ($assetId % 80) * 0.001;
            }

            $feature = [
                'type' => 'Feature',
                'geometry' => [
                    'type'        => 'Point',
                    'coordinates' => [$lng, $lat]
                ],
                'properties' => [
                    'feature_type'         => 'ASSET',
                    'asset_id'             => $assetId,
                    'kode_asset'           => $asset['kode_asset'] ?? $asset['asset_code'] ?? "AST-{$assetId}",
                    'nama_asset'           => $asset['nama_asset'] ?? $asset['asset_name'] ?? "Asset #{$assetId}",
                    'jenis_asset'          => $asset['jenis_asset'] ?? $asset['asset_type'] ?? 'EQUIPMENT',
                    'penyulang'            => $asset['penyulang'] ?? null,
                    'unit_up'              => $asset['unit_up'] ?? null,

                    // Layer 2: AHI Overlay
                    'ahi_score'            => (float)$ahiData['ahi_score'],
                    'health_category'      => $ahiData['health_category'],

                    // Layer 3: FLI-1.0.0 Fault Overlay
                    'fli_hypothesis'       => $fliHypotheses[$assetId] ?? null,

                    // Layer 4: Field Finding & Defect Overlay
                    'active_findings'      => $findingsMap[$assetId] ?? [],
                    'finding_count'        => count($findingsMap[$assetId] ?? []),

                    // Layer 5: Remediation Work Package Overlay
                    'remediation_package'  => $remediationWpMap[$assetId] ?? null,

                    // Layer 6: Material Requisition Overlay
                    'material_requisition' => $materialReqMap[$assetId] ?? null,
                ]
            ];

            $features[] = $feature;
        }

        // Build Transline LineString Features
        foreach ($translines as $tl) {
            $tlId = (int)$tl['id'];

            // Parse geometry coordinates if stored as JSON/GeoJSON
            $rawGeom = $tl['geometry'] ?? $tl['geojson'] ?? null;
            $coordinates = [];

            if ($rawGeom) {
                $decoded = is_string($rawGeom) ? json_decode($rawGeom, true) : $rawGeom;
                if (isset($decoded['coordinates'])) {
                    $coordinates = $decoded['coordinates'];
                } elseif (is_array($decoded)) {
                    $coordinates = $decoded;
                }
            }

            if (empty($coordinates)) {
                // Synthesize baseline path for visual completeness if geometry column unparsed
                $startLat = -7.4500 + ($tlId % 50) * 0.002;
                $startLng = 112.7200 + ($tlId % 40) * 0.002;
                $coordinates = [
                    [$startLng, $startLat],
                    [$startLng + 0.005, $startLat + 0.003]
                ];
            }

            $features[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type'        => 'LineString',
                    'coordinates' => $coordinates
                ],
                'properties' => [
                    'feature_type'       => 'TRANSLINE',
                    'transline_id'       => $tlId,
                    'kode_transline'     => $tl['kode_transline'] ?? $tl['segment_code'] ?? "TL-{$tlId}",
                    'nama_penyulang'     => $tl['nama_penyulang'] ?? $tl['feeder_name'] ?? null,
                    'panjang_m'          => (float)($tl['panjang_m'] ?? $tl['length_m'] ?? 0.0),
                    'is_active'          => (int)($tl['is_active'] ?? 1),
                ]
            ];
        }

        // Deterministic Spatial Fingerprint (B84-G08)
        $fingerprint = $this->generateSpatialFingerprint($assets, $translines);

        return [
            'type'                => 'FeatureCollection',
            'snapshot_id'         => self::SNAPSHOT_ID,
            'spec_version'        => self::SPEC_VERSION,
            'spatial_fingerprint' => $fingerprint,
            'total_assets'        => count($assets),
            'total_translines'    => count($translines),
            'features'            => $features
        ];
    }

    /**
     * B84-G06: SLD Topology Overlay Read API Data
     * Generates Single-Line Diagram graph topology annotated with 6 operational layers.
     */
    public function getSldTopologyOverlay(array $filters = []): array
    {
        $spatialData = $this->getSpatialGeoJson($filters);

        $nodes = [];
        $edges = [];

        foreach ($spatialData['features'] as $feature) {
            $props = $feature['properties'];
            if ($props['feature_type'] === 'ASSET') {
                $nodes[] = [
                    'id'                   => "node-asset-" . $props['asset_id'],
                    'asset_id'             => $props['asset_id'],
                    'label'                => $props['nama_asset'],
                    'code'                 => $props['kode_asset'],
                    'type'                 => $props['jenis_asset'],
                    'ahi_score'            => $props['ahi_score'],
                    'health_category'      => $props['health_category'],
                    'finding_count'        => $props['finding_count'],
                    'remediation_status'   => $props['remediation_package']['status'] ?? null,
                    'requisition_status'   => $props['material_requisition']['status'] ?? null,
                    'fli_candidate'        => !empty($props['fli_hypothesis']),
                ];
            } elseif ($props['feature_type'] === 'TRANSLINE') {
                $edges[] = [
                    'id'             => "edge-tl-" . $props['transline_id'],
                    'transline_id'   => $props['transline_id'],
                    'code'           => $props['kode_transline'],
                    'penyulang'      => $props['nama_penyulang'],
                    'length_m'       => $props['panjang_m'],
                    'is_active'      => $props['is_active'],
                ];
            }
        }

        $topologyFingerprint = hash('sha256', json_encode([
            'nodes_count' => count($nodes),
            'edges_count' => count($edges),
            'snapshot'    => self::SNAPSHOT_ID,
        ]));

        return [
            'snapshot_id'          => self::SNAPSHOT_ID,
            'topology_fingerprint' => $topologyFingerprint,
            'summary'              => [
                'total_nodes' => count($nodes),
                'total_edges' => count($edges),
            ],
            'nodes'                => $nodes,
            'edges'                => $edges,
        ];
    }

    /**
     * B8.4 360-Degree Asset Context Drawer Data Engine
     */
    public function getAsset360Context(int $assetId): array
    {
        $asset = $this->db->table('assets')
            ->where('id', $assetId)
            ->get()
            ->getRowArray();

        if (!$asset) {
            return [
                'success' => false,
                'message' => "Asset ID {$assetId} not found."
            ];
        }

        // Layer 2: Synthesize AHI Score
        $ahiResult = $this->ahiEngine->calculateAssetHealth($assetId);

        // Layer 3: FLI Hypotheses
        $fli = $this->fetchFliHypothesesForAsset($assetId);

        // Layer 4: Findings
        $findings = $this->db->table('temuan')
            ->where('asset_id', $assetId)
            ->get()
            ->getResultArray();

        // Layer 5: Remediation Work Packages
        $remediationWps = $this->fetchRemediationWpsForAsset($assetId);

        // Layer 6: Material Requisitions
        $materialReqs = $this->fetchMaterialReqsForAsset($assetId);

        $contextFingerprint = hash('sha256', json_encode([
            'asset_id'    => $assetId,
            'ahi_score'   => $ahiResult['health_score'] ?? 100,
            'findings_cnt'=> count($findings),
            'remed_cnt'   => count($remediationWps),
            'snapshot'    => self::SNAPSHOT_ID,
        ]));

        return [
            'success'               => true,
            'snapshot_id'           => self::SNAPSHOT_ID,
            'context_fingerprint'  => $contextFingerprint,
            'asset'                 => [
                'id'           => (int)$asset['id'],
                'kode_asset'   => $asset['kode_asset'] ?? "AST-{$assetId}",
                'nama_asset'   => $asset['nama_asset'] ?? "Asset #{$assetId}",
                'jenis_asset'  => $asset['jenis_asset'] ?? 'EQUIPMENT',
                'unit_up'      => $asset['unit_up'] ?? 'N/A',
                'penyulang'    => $asset['penyulang'] ?? 'N/A',
                'latitude'     => (float)($asset['latitude'] ?? 0.0),
                'longitude'    => (float)($asset['longitude'] ?? 0.0),
            ],
            'ahi_intelligence'     => $ahiResult,
            'fli_fault_projection' => $fli,
            'findings'             => $findings,
            'remediation_packages' => $remediationWps,
            'material_requisitions'=> $materialReqs,
        ];
    }

    // =========================================================================
    // HELPER & OVERLAY FETCHING METHODS (Strictly Read-Only Queries)
    // =========================================================================

    protected function fetchAhiScoresMap(): array
    {
        $map = [];
        if ($this->db->tableExists('asset_health_indices')) {
            $rows = $this->db->table('asset_health_indices')->get()->getResultArray();
            foreach ($rows as $r) {
                $score = $r['health_score'] ?? $r['ahi_score'] ?? $r['score'] ?? 100.0;
                $map[(int)($r['asset_id'] ?? $r['id'] ?? 0)] = [
                    'ahi_score'       => (float)$score,
                    'health_category' => $r['health_category'] ?? $r['classification'] ?? $r['category'] ?? 'GOOD',
                ];
            }
        }
        return $map;
    }

    protected function fetchFliHypothesesMap(): array
    {
        $map = [];
        if ($this->db->tableExists('fault_candidate_assets')) {
            $rows = $this->db->table('fault_candidate_assets')->get()->getResultArray();
            foreach ($rows as $r) {
                $map[(int)$r['asset_id']] = [
                    'fault_case_id' => (int)($r['fault_case_id'] ?? $r['case_id'] ?? 0),
                    'distance_m'    => (float)($r['distance_m'] ?? 0.0),
                    'probability'   => (float)($r['probability'] ?? 0.85),
                ];
            }
        }
        return $map;
    }

    protected function fetchActiveFindingsMap(): array
    {
        $map = [];
        if ($this->db->tableExists('temuan')) {
            $rows = $this->db->table('temuan')
                ->whereNotIn('status', ['SELESAI', 'CLOSED', 'REJECTED'])
                ->get()
                ->getResultArray();

            foreach ($rows as $r) {
                $assetId = (int)($r['asset_id'] ?? 0);
                if ($assetId > 0) {
                    if (!isset($map[$assetId])) {
                        $map[$assetId] = [];
                    }
                    $map[$assetId][] = [
                        'id'         => (int)$r['id'],
                        'deskripsi'  => $r['deskripsi_finding'] ?? $r['deskripsi'] ?? '',
                        'prioritas'  => $r['prioritas'] ?? $r['severity'] ?? 'NORMAL',
                        'status'     => $r['status'] ?? 'OPEN',
                    ];
                }
            }
        }
        return $map;
    }

    protected function fetchRemediationWpMap(): array
    {
        $map = [];
        if ($this->db->tableExists('remediation_work_packages')) {
            $wps = $this->db->table('remediation_work_packages')->get()->getResultArray();

            // Link WPs to assets via remediation_package_findings or direct asset_id column
            if ($this->db->tableExists('remediation_package_findings')) {
                $links = $this->db->table('remediation_package_findings')
                    ->select('work_package_id, asset_id')
                    ->get()
                    ->getResultArray();

                $wpById = [];
                foreach ($wps as $w) {
                    $wpById[(int)$w['id']] = $w;
                }

                foreach ($links as $l) {
                    $assetId = (int)($l['asset_id'] ?? 0);
                    $pkgId   = (int)($l['work_package_id'] ?? 0);
                    if ($assetId > 0 && isset($wpById[$pkgId])) {
                        $map[$assetId] = [
                            'id'             => $pkgId,
                            'package_number' => $wpById[$pkgId]['package_code'] ?? "WP-{$pkgId}",
                            'title'          => $wpById[$pkgId]['title'] ?? '',
                            'status'         => $wpById[$pkgId]['status'] ?? 'DRAFT',
                        ];
                    }
                }
            }
        }
        return $map;
    }

    protected function fetchMaterialReqMap(): array
    {
        $map = [];
        if ($this->db->tableExists('material_requisition_packages')) {
            $reqs = $this->db->table('material_requisition_packages')->get()->getResultArray();
            foreach ($reqs as $r) {
                $assetId = (int)($r['asset_id'] ?? 0);
                if ($assetId > 0) {
                    $map[$assetId] = [
                        'id'                 => (int)$r['id'],
                        'requisition_number' => $r['requisition_code'] ?? "REQ-{$r['id']}",
                        'status'             => $r['status'] ?? 'DRAFT',
                    ];
                }
            }
        }
        return $map;
    }

    protected function fetchFliHypothesesForAsset(int $assetId): ?array
    {
        if ($this->db->tableExists('fault_candidate_assets')) {
            $row = $this->db->table('fault_candidate_assets')
                ->where('asset_id', $assetId)
                ->get()
                ->getRowArray();
            if ($row) {
                return [
                    'fault_case_id' => (int)($row['fault_case_id'] ?? 0),
                    'distance_m'    => (float)($row['distance_m'] ?? 0.0),
                    'probability'   => (float)($row['probability'] ?? 0.85),
                ];
            }
        }
        return null;
    }

    protected function fetchRemediationWpsForAsset(int $assetId): array
    {
        if ($this->db->tableExists('remediation_package_findings') && $this->db->tableExists('remediation_work_packages')) {
            return $this->db->table('remediation_work_packages rwp')
                ->select('rwp.*')
                ->join('remediation_package_findings rpf', 'rpf.work_package_id = rwp.id')
                ->where('rpf.asset_id', $assetId)
                ->get()
                ->getResultArray();
        }
        return [];
    }


    protected function fetchMaterialReqsForAsset(int $assetId): array
    {
        if ($this->db->tableExists('material_requisition_packages')) {
            $fields = $this->db->getFieldNames('material_requisition_packages');
            if (in_array('asset_id', $fields)) {
                return $this->db->table('material_requisition_packages')
                    ->where('asset_id', $assetId)
                    ->get()
                    ->getResultArray();
            } elseif (in_array('work_package_id', $fields) && $this->db->tableExists('remediation_package_findings')) {
                return $this->db->table('material_requisition_packages mrp')
                    ->select('mrp.*')
                    ->join('remediation_package_findings rpf', 'rpf.work_package_id = mrp.work_package_id')
                    ->where('rpf.asset_id', $assetId)
                    ->get()
                    ->getResultArray();
            }
        }
        return [];
    }


    /**
     * Generate SHA-256 Spatial Fingerprint for verification (B84-G08)
     */
    protected function generateSpatialFingerprint(array $assets, array $translines): string
    {
        $assetIds = array_column($assets, 'id');
        sort($assetIds);
        $tlIds = array_column($translines, 'id');
        sort($tlIds);

        $payload = json_encode([
            'snapshot'      => self::SNAPSHOT_ID,
            'asset_count'   => count($assets),
            'asset_min'     => reset($assetIds) ?: 0,
            'asset_max'     => end($assetIds) ?: 0,
            'transline_cnt' => count($translines),
            'tl_min'        => reset($tlIds) ?: 0,
            'tl_max'        => end($tlIds) ?: 0,
        ]);

        return hash('sha256', $payload);
    }
}
