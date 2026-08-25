<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * Shutdown Scope, Inspection Taxonomy, SLD Work Planning & Evidence-Based Material Traceability Service (CC-06 Phase 2)
 *
 * 6 Integrated Workspaces:
 * 1. Executive Reliability Dashboard (GIRI, Radar, Readiness, OPEX/CAPEX)
 * 2. Work Scope Planner (Feeder, Multi-Section, 17 Inspection Tasks, 4 Work Modes)
 * 3. Dynamic SLD Scope Viewer (GI -> Feeder -> Section Nodes -> Assets/Gardu/Temuan)
 * 4. Work Finding Console (Filterable Work Items, Severity, Scope Categories)
 * 5. Preventive Material Requirement Console (Level 1: Aggregate, Level 2: Evidence Drill-Down)
 * 6. Material Allocation Evidence Console (Hierarchical Request Tree: Material -> Plan -> Feeder -> Section -> Node -> Asset -> Finding)
 */
class ShutdownWorkPlanningService
{
    protected BaseConnection $db;
    protected JtmConstructionBomService $bomService;
    protected SpatialPreventiveMaterialBridgeService $spatialBridgeService;

    protected string $catalogRegistryPath;
    protected string $workScopeRegistryPath;
    protected string $sldPlanningRegistryPath;
    protected string $workFindingRegistryPath;
    protected string $materialEvidenceRegistryPath;

    public const MODEL_VERSION = 'EVIDENCE_BASED_WORK_PLANNING_MODEL_v2.0';

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
        $this->bomService = new JtmConstructionBomService($this->db);
        $this->spatialBridgeService = new SpatialPreventiveMaterialBridgeService($this->db);

        $this->catalogRegistryPath          = WRITEPATH . 'audits/cc06_inspection_work_catalog_registry.json';
        $this->workScopeRegistryPath        = WRITEPATH . 'audits/cc06_work_scope_registry.json';
        $this->sldPlanningRegistryPath      = WRITEPATH . 'audits/cc06_sld_planning_registry.json';
        $this->workFindingRegistryPath      = WRITEPATH . 'audits/cc06_work_finding_registry.json';
        $this->materialEvidenceRegistryPath = WRITEPATH . 'audits/cc06_material_allocation_evidence_registry.json';

        $this->initializeGroupGRegistries();
    }

    /**
     * Initialize Group G Registries.
     */
    public function initializeGroupGRegistries(): void
    {
        $now = date('Y-m-d H:i:s T');
        $utc = gmdate('Y-m-d\TH:i:s\Z');

        // 1. Inspection Work Catalog
        if (!file_exists($this->catalogRegistryPath)) {
            $inspectionCatalog = [
                ['domain' => 'JTM', 'code' => 'INSP-JTM-VL1',     'name' => 'Inspeksi Visual Level 1 JTM',    'method' => 'VISUAL_LEVEL_1',    'target' => 'Material & ROW',                 'outage_mode' => 'NON_OUTAGE'],
                ['domain' => 'JTM', 'code' => 'INSP-JTM-THERMO',  'name' => 'Inspeksi Thermovision JTM',       'method' => 'THERMOVISION',      'target' => 'Titik Sambung SUTM',             'outage_mode' => 'NON_OUTAGE_ONLINE'],
                ['domain' => 'JTM', 'code' => 'INSP-JTM-GROUND',  'name' => 'Inspeksi Pentanahan JTM',        'method' => 'GROUNDING_TEST',    'target' => 'GSW, LA, CLD',                   'outage_mode' => 'NON_OUTAGE'],
                ['domain' => 'JTM', 'code' => 'INSP-JTM-LEAKAGE', 'name' => 'Inspeksi Arus Bocor JTM',       'method' => 'LEAKAGE_CURRENT',   'target' => 'Arrester & CLD',                 'outage_mode' => 'NON_OUTAGE'],
                ['domain' => 'JTM', 'code' => 'INSP-JTM-PD',      'name' => 'Inspeksi Partial Discharge JTM', 'method' => 'PARTIAL_DISCHARGE', 'target' => 'Material SUTM',                  'outage_mode' => 'NON_OUTAGE_ONLINE'],

                ['domain' => 'Gardu', 'code' => 'INSP-GRD-VL1',   'name' => 'Inspeksi Visual Level 1 Gardu',  'method' => 'VISUAL_LEVEL_1',    'target' => 'Relay, kebersihan, sumber 220V', 'outage_mode' => 'NON_OUTAGE'],
                ['domain' => 'Gardu', 'code' => 'INSP-GRD-PD',    'name' => 'Inspeksi Partial Discharge Gardu','method' => 'PARTIAL_DISCHARGE','target' => 'Seluruh aset kubikel',          'outage_mode' => 'NON_OUTAGE_ONLINE'],

                ['domain' => 'Trafo', 'code' => 'INSP-TRF-VL1',   'name' => 'Inspeksi Visual Level 1 Trafo',  'method' => 'VISUAL_LEVEL_1',    'target' => 'Seluruh material aset',          'outage_mode' => 'NON_OUTAGE'],
                ['domain' => 'Trafo', 'code' => 'INSP-TRF-THERMO','name' => 'Inspeksi Thermovision Trafo',   'method' => 'THERMOVISION',      'target' => 'Titik sambung',                  'outage_mode' => 'NON_OUTAGE_ONLINE'],
                ['domain' => 'Trafo', 'code' => 'INSP-TRF-GROUND','name' => 'Inspeksi Pentanahan Trafo',    'method' => 'GROUNDING_TEST',    'target' => 'Trafo, LA, Netral',              'outage_mode' => 'NON_OUTAGE'],
                ['domain' => 'Trafo', 'code' => 'INSP-TRF-LEAKAGE','name'=> 'Inspeksi Arus Bocor Trafo',     'method' => 'LEAKAGE_CURRENT',   'target' => 'Trafo & LA',                     'outage_mode' => 'NON_OUTAGE'],
                ['domain' => 'Trafo', 'code' => 'INSP-TRF-PD',    'name' => 'Inspeksi Partial Discharge Trafo','method' => 'PARTIAL_DISCHARGE','target' => 'Material Trafo',                 'outage_mode' => 'NON_OUTAGE_ONLINE'],

                ['domain' => 'JTR',   'code' => 'INSP-JTR-VL1',   'name' => 'Inspeksi Visual Level 1 JTR',    'method' => 'VISUAL_LEVEL_1',    'target' => 'Seluruh aset',                   'outage_mode' => 'NON_OUTAGE'],
                ['domain' => 'JTR',   'code' => 'INSP-JTR-THERMO', 'name' => 'Inspeksi Thermovision JTR',      'method' => 'THERMOVISION',      'target' => 'Titik sambung',                  'outage_mode' => 'NON_OUTAGE_ONLINE'],
                ['domain' => 'JTR',   'code' => 'INSP-JTR-GROUND', 'name' => 'Inspeksi Pentanahan JTR',       'method' => 'GROUNDING_TEST',    'target' => 'Ground TR',                      'outage_mode' => 'NON_OUTAGE'],
                ['domain' => 'JTR',   'code' => 'INSP-JTR-LEAKAGE','name' => 'Inspeksi Arus Bocor JTR',       'method' => 'LEAKAGE_CURRENT',   'target' => 'Ground TR',                      'outage_mode' => 'NON_OUTAGE'],
                ['domain' => 'JTR',   'code' => 'INSP-JTR-PD',     'name' => 'Inspeksi Partial Discharge JTR', 'method' => 'PARTIAL_DISCHARGE', 'target' => 'Material JTR',                  'outage_mode' => 'NON_OUTAGE_ONLINE'],
            ];

            $doc = [
                'registry_id'      => 'CC06_INSPECTION_WORK_CATALOG_REGISTRY_v1.0',
                'created_at'       => $now,
                'created_at_utc'   => $utc,
                'total_items'      => count($inspectionCatalog),
                'domains'          => ['JTM', 'Gardu', 'Trafo', 'JTR'],
                'inspection_tasks' => $inspectionCatalog,
            ];
            file_put_contents($this->catalogRegistryPath, json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        // 2. Work Scope Registry
        if (!file_exists($this->workScopeRegistryPath)) {
            $doc = [
                'registry_id'    => 'CC06_WORK_SCOPE_REGISTRY_v1.0',
                'created_at'     => $now,
                'created_at_utc' => $utc,
                'plans'          => [],
            ];
            file_put_contents($this->workScopeRegistryPath, json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        // 3. SLD Planning Registry
        if (!file_exists($this->sldPlanningRegistryPath)) {
            $doc = [
                'registry_id'    => 'CC06_SLD_PLANNING_REGISTRY_v1.0',
                'created_at'     => $now,
                'created_at_utc' => $utc,
                'sld_topologies' => [],
            ];
            file_put_contents($this->sldPlanningRegistryPath, json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        // 4. Work Finding Registry
        if (!file_exists($this->workFindingRegistryPath)) {
            $doc = [
                'registry_id'    => 'CC06_WORK_FINDING_REGISTRY_v1.0',
                'created_at'     => $now,
                'created_at_utc' => $utc,
                'work_items'     => [],
            ];
            file_put_contents($this->workFindingRegistryPath, json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }

        // 5. Material Allocation Evidence Registry
        if (!file_exists($this->materialEvidenceRegistryPath)) {
            $doc = [
                'registry_id'    => 'CC06_MATERIAL_ALLOCATION_EVIDENCE_REGISTRY_v1.0',
                'created_at'     => $now,
                'created_at_utc' => $utc,
                'evidence_chains'=> [],
            ];
            file_put_contents($this->materialEvidenceRegistryPath, json_encode($doc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        }
    }

    /**
     * Get Inspection Work Catalog.
     */
    public function getInspectionCatalog(): array
    {
        if (file_exists($this->catalogRegistryPath)) {
            return json_decode(file_get_contents($this->catalogRegistryPath), true);
        }
        return ['inspection_tasks' => []];
    }

    /**
     * Get Sections and Asset/Finding Statistics for a Feeder.
     */
    public function getFeederSections(int $feederId): array
    {
        $feeder = $this->db->table('penyulang')->where('id', $feederId)->get()->getRowArray();
        if (!$feeder) {
            return ['success' => false, 'error' => "Feeder ID {$feederId} not found."];
        }

        $sections = $this->db->table('sections')->where('penyulang_id', $feederId)->orderBy('id', 'ASC')->get()->getResultArray();
        $enrichedSections = [];

        foreach ($sections as $s) {
            $sId = (int)$s['id'];
            $secName = $s['nama_seksi'] ?? $s['section_name'] ?? "Section #{$sId}";
            $secCode = $s['kode_seksi'] ?? $s['section_code'] ?? "SEC-{$sId}";

            $assetsCount = $this->db->table('assets')->where('section_id', $sId)->countAllResults();
            $findingsCount = $this->db->table('temuan')->where('section_id', $sId)->countAllResults();

            $enrichedSections[] = [
                'section_id'     => $sId,
                'section_name'   => $secName,
                'section_code'   => $secCode,
                'penyulang_id'   => $feederId,
                'total_assets'   => $assetsCount,
                'total_findings' => $findingsCount,
            ];
        }

        return [
            'success'        => true,
            'feeder'         => $feeder,
            'total_sections' => count($enrichedSections),
            'sections'       => $enrichedSections,
        ];
    }

    /**
     * Compose a Governed Shutdown & Inspection Scope Plan with Hierarchical Material Evidence.
     */
    public function composeWorkPlanScope(int $feederId, array $sectionIds, array $scopes, array $inspectionCodes, string $workMode, array $actor): array
    {
        $feeder = $this->db->table('penyulang')->where('id', $feederId)->get()->getRowArray();
        if (!$feeder) {
            return ['success' => false, 'error' => "Feeder ID {$feederId} not found."];
        }

        if (empty($sectionIds)) {
            return ['success' => false, 'error' => 'At least one network section must be selected for the shutdown scope.'];
        }

        if (empty($inspectionCodes)) {
            $inspectionCodes = ['INSP-JTM-VL1'];
        }

        $planId = 'WP-CC06-' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(2)), 0, 4);
        $requestNo = 'REQ-MAT-' . date('Ymd') . '-' . substr(bin2hex(random_bytes(2)), 0, 4);
        $now = date('Y-m-d H:i:s');
        $utc = gmdate('Y-m-d\TH:i:s\Z');

        // Fetch Assets in selected sections
        $assets = $this->db->table('assets')->whereIn('section_id', $sectionIds)->orderBy('id', 'ASC')->get()->getResultArray();
        // Fetch Findings in selected sections
        $findings = $this->db->table('temuan')->whereIn('section_id', $sectionIds)->orderBy('id', 'ASC')->get()->getResultArray();

        // Load Finding-to-Material Bridge (CR-08) & Master Materials (CR-07)
        $findingBridgePath = WRITEPATH . 'audits/cr08_finding_material_bridge_registry.json';
        $bridgeData = file_exists($findingBridgePath) ? json_decode(file_get_contents($findingBridgePath), true)['findings_bridges'] ?? [] : [];
        $bomSummary = $this->bomService->getWorkspaceSummary();

        // 1. Build Work Finding Table & Material Evidence Lines
        $workItems = [];
        $materialRequirements = [];
        $evidenceChains = [];
        $hierarchicalMaterialTree = [];

        $itemNo = 1;
        foreach ($findings as $f) {
            $fId = (int)$f['id'];
            $secId = (int)($f['section_id'] ?? 0);
            $bridge = $bridgeData[$fId] ?? null;

            // Associate with asset in that section if available
            $associatedAsset = null;
            foreach ($assets as $a) {
                if ((int)$a['section_id'] === $secId) {
                    $associatedAsset = $a;
                    break;
                }
            }

            $assetCode = $associatedAsset['kode_asset'] ?? 'AST-JTM-GENERIC';
            $assetType = $associatedAsset['jenis_asset'] ?? 'TIANG_BETON';
            $cCode     = ($assetType === 'GARDU_DISTRIBUSI_PORTAL') ? 'TM-8' : (($assetType === 'GTT_GARDU_TRAFO_TIANG') ? 'TM-9' : 'TM-1');

            $matCode = $bridge['matched_canonical_code'] ?? 'MAT-ISO-PIN-20KV-12.5KN';
            $matQty  = $bridge['recommended_qty'] ?? 1;
            $matUnit = $bridge['unit'] ?? 'PCS';
            $matName = $bomSummary['materials'][$matCode]['official_name'] ?? $matCode;

            $priority = (str_contains(strtoupper($f['uraian_temuan'] ?? ''), 'KRITIS') || str_contains(strtoupper($f['uraian_temuan'] ?? ''), 'RETAK')) ? 'KRITIS' : 'TINGGI';
            $primaryInspCode = $inspectionCodes[0] ?? 'INSP-JTM-VL1';
            $sldPosition = "Node-SEC-{$secId}-{$assetCode}";

            // Category assignment
            $scopeCategory = 'TO_TEMUAN';
            if ($assetType === 'GTT_GARDU_TRAFO_TIANG') $scopeCategory = 'TO_GTT';
            elseif ($assetType === 'GARDU_DISTRIBUSI_PORTAL') $scopeCategory = 'TO_GARDU';
            elseif (in_array('TO_PDKB', $scopes, true)) $scopeCategory = 'TO_PDKB';

            $workItems[] = [
                'item_no'          => $itemNo++,
                'section_id'       => $secId,
                'sld_node'         => $sldPosition,
                'asset_code'       => $assetCode,
                'asset_type'       => $assetType,
                'construction'     => $cCode,
                'scope_category'   => $scopeCategory,
                'inspection_code'  => $primaryInspCode,
                'finding_id'       => $fId,
                'nomor_temuan'     => $f['nomor_temuan'] ?? "TEMUAN-{$fId}",
                'deskripsi'        => $f['uraian_temuan'] ?? $f['judul'] ?? 'Perbaikan Konstruksi & Sambungan JTM',
                'priority'         => $priority,
                'canonical_mat'    => $matCode,
                'material_name'    => $matName,
                'quantity'         => $matQty,
                'unit'             => $matUnit,
                'status'           => 'SCHEDULED_FOR_OUTAGE',
            ];

            // Aggregate Material Needs (Level 1)
            if (!isset($materialRequirements[$matCode])) {
                $materialRequirements[$matCode] = [
                    'canonical_material_code' => $matCode,
                    'official_name'           => $matName,
                    'total_quantity'          => 0,
                    'unit'                    => $matUnit,
                    'allocated_assets_count'  => 0,
                    'drill_down_evidence'     => [],
                ];
            }
            $materialRequirements[$matCode]['total_quantity'] += $matQty;
            $materialRequirements[$matCode]['allocated_assets_count']++;

            // Create Granular Evidence Line (Level 2)
            $evidenceId = 'EVID-' . $planId . '-' . sprintf('%03d', $itemNo - 1);
            $evidenceHash = hash('sha256', "{$evidenceId}|{$primaryInspCode}|{$matCode}|{$matQty}|{$fId}|{$assetCode}|{$secId}|{$sldPosition}");

            $evidenceEntry = [
                'evidence_id'            => $evidenceId,
                'request_no'             => $requestNo,
                'plan_id'                => $planId,
                'inspection_work_code'   => $primaryInspCode,
                'canonical_material_code'=> $matCode,
                'official_name'          => $matName,
                'allocated_quantity'     => $matQty,
                'unit'                   => $matUnit,
                'target_asset_code'      => $assetCode,
                'target_asset_type'      => $assetType,
                'target_construction'    => $cCode,
                'feeder_id'              => $feederId,
                'feeder_name'            => $feeder['nama_penyulang'],
                'section_id'             => $secId,
                'sld_position'           => $sldPosition,
                'source_finding_id'      => $fId,
                'source_finding_number'  => $f['nomor_temuan'] ?? "TEMUAN-{$fId}",
                'reason_justification'   => $f['uraian_temuan'] ?? 'Penggantian material preventif hasil temuan inspeksi',
                'decision_boundary'      => 'RECOMMENDATION_ONLY',
                'human_review_required'  => true,
                'evidence_hash'          => $evidenceHash,
            ];

            $evidenceChains[] = $evidenceEntry;
            $materialRequirements[$matCode]['drill_down_evidence'][] = $evidenceEntry;

            // Build Hierarchical Request Tree: Material -> Feeder -> Section -> Asset -> Finding
            if (!isset($hierarchicalMaterialTree[$matCode])) {
                $hierarchicalMaterialTree[$matCode] = [
                    'material_code'  => $matCode,
                    'material_name'  => $matName,
                    'total_quantity' => 0,
                    'unit'           => $matUnit,
                    'allocations'    => [],
                ];
            }
            $hierarchicalMaterialTree[$matCode]['total_quantity'] += $matQty;
            $hierarchicalMaterialTree[$matCode]['allocations'][] = [
                'qty'          => $matQty,
                'unit'         => $matUnit,
                'finding_no'   => $f['nomor_temuan'] ?? "TEMUAN-{$fId}",
                'finding_id'   => $fId,
                'asset_code'   => $assetCode,
                'section_id'   => $secId,
                'sld_node'     => $sldPosition,
                'evidence_id'  => $evidenceId,
                'evidence_hash'=> $evidenceHash,
                'description'  => "{$matQty} {$matUnit} dialokasikan untuk temuan {$fId} pada aset {$assetCode} (Seksi {$secId})",
            ];
        }

        // 2. Generate Dynamic SLD Model
        $sldModel = $this->generateDynamicSld($feederId, $sectionIds, $workItems);

        // Action Hash for entire plan
        $planActionHash = hash('sha256', "{$planId}|{$requestNo}|{$feederId}|" . implode(',', $sectionIds) . "|" . implode(',', $inspectionCodes) . "|{$workMode}|{$actor['actor_nip']}|{$now}");

        $planRecord = [
            'plan_id'                  => $planId,
            'request_no'               => $requestNo,
            'feeder_id'                => $feederId,
            'feeder_name'              => $feeder['nama_penyulang'],
            'feeder_code'              => $feeder['kode_penyulang'],
            'ulp_id'                   => (int)$feeder['ulp_id'],
            'work_mode'                => $workMode,
            'inspection_work_codes'    => $inspectionCodes,
            'affected_section_ids'     => $sectionIds,
            'scopes_selected'          => $scopes,
            'total_affected_sections'  => count($sectionIds),
            'total_affected_assets'    => count($assets),
            'total_work_findings'      => count($workItems),
            'total_material_types'     => count($materialRequirements),
            'aggregated_materials'     => array_values($materialRequirements),
            'hierarchical_request_tree'=> array_values($hierarchicalMaterialTree),
            'created_by'               => $actor,
            'created_at'               => $now,
            'created_at_utc'           => $utc,
            'decision_boundary'        => 'RECOMMENDATION_ONLY (Human Technical & Management Review Required)',
            'autonomous_action'        => false,
            'autonomous_switching'     => false,
            'autonomous_procurement'   => false,
            'action_hash'              => $planActionHash,
        ];

        // Save into Group G Registries
        // 1. Work Scope Registry
        $scopeDoc = json_decode(file_get_contents($this->workScopeRegistryPath), true) ?? ['plans' => []];
        $scopeDoc['plans'][$planId] = $planRecord;
        file_put_contents($this->workScopeRegistryPath, json_encode($scopeDoc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // 2. SLD Planning Registry
        $sldDoc = json_decode(file_get_contents($this->sldPlanningRegistryPath), true) ?? ['sld_topologies' => []];
        $sldDoc['sld_topologies'][$planId] = $sldModel;
        file_put_contents($this->sldPlanningRegistryPath, json_encode($sldDoc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // 3. Work Finding Registry
        $wfDoc = json_decode(file_get_contents($this->workFindingRegistryPath), true) ?? ['work_items' => []];
        $wfDoc['work_items'][$planId] = $workItems;
        file_put_contents($this->workFindingRegistryPath, json_encode($wfDoc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // 4. Material Allocation Evidence Registry
        $matDoc = json_decode(file_get_contents($this->materialEvidenceRegistryPath), true) ?? ['evidence_chains' => []];
        $matDoc['evidence_chains'][$planId] = $evidenceChains;
        file_put_contents($this->materialEvidenceRegistryPath, json_encode($matDoc, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return [
            'success'                   => true,
            'plan_id'                   => $planId,
            'request_no'                => $requestNo,
            'feeder_name'               => $feeder['nama_penyulang'],
            'work_mode'                 => $workMode,
            'inspection_codes'          => $inspectionCodes,
            'affected_sections'         => count($sectionIds),
            'work_items_count'          => count($workItems),
            'material_types_count'      => count($materialRequirements),
            'evidence_chains_count'     => count($evidenceChains),
            'action_hash'               => $planActionHash,
            'plan_summary'              => $planRecord,
            'work_items'                => $workItems,
            'material_requirements'     => array_values($materialRequirements),
            'hierarchical_request_tree' => array_values($hierarchicalMaterialTree),
            'evidence_chains'           => $evidenceChains,
            'sld_topology'              => $sldModel,
        ];
    }

    /**
     * Generate Dynamic Single Line Diagram (SLD) Model.
     */
    public function generateDynamicSld(int $feederId, array $shutdownSectionIds, array $workItems = []): array
    {
        $feeder = $this->db->table('penyulang')->where('id', $feederId)->get()->getRowArray();
        $sections = $this->db->table('sections')->where('penyulang_id', $feederId)->orderBy('id', 'ASC')->get()->getResultArray();

        $nodes = [];
        $nodes[] = [
            'id'       => 'GI_OUTGOING',
            'label'    => 'GI OUTGOING CB (' . ($feeder['nama_penyulang'] ?? 'FEEDER') . ')',
            'type'     => 'CIRCUIT_BREAKER',
            'status'   => 'ENERGIZED',
            'level'    => 0,
            'assets'   => [],
            'gardus'   => [],
            'findings' => 0,
        ];

        $secCount = 0;
        foreach ($sections as $s) {
            $sId = (int)$s['id'];
            $secCount++;
            $isShutdown = in_array($sId, $shutdownSectionIds, true);
            $secName = $s['nama_seksi'] ?? $s['section_name'] ?? "Section #{$sId}";

            $secWorkCount = 0;
            $secAssets = [];
            $secGardus = [];

            foreach ($workItems as $w) {
                if ((int)$w['section_id'] === $sId) {
                    $secWorkCount++;
                    if (!in_array($w['asset_code'], $secAssets, true)) {
                        $secAssets[] = $w['asset_code'];
                    }
                    if (str_contains($w['asset_type'], 'GARDU') || str_contains($w['asset_type'], 'GTT')) {
                        if (!in_array($w['asset_code'], $secGardus, true)) {
                            $secGardus[] = $w['asset_code'];
                        }
                    }
                }
            }

            $nodes[] = [
                'id'            => "SEC_{$sId}",
                'section_id'    => $sId,
                'label'         => $secName,
                'type'          => 'NETWORK_SECTION',
                'status'        => $isShutdown ? 'SHUTDOWN_SCOPE_ISOLATED' : 'NORMAL_ENERGIZED',
                'is_shutdown'   => $isShutdown,
                'level'         => $secCount,
                'work_items_cnt'=> $secWorkCount,
                'assets'        => $secAssets,
                'gardus'        => $secGardus,
                'findings'      => $secWorkCount,
            ];
        }

        return [
            'feeder_id'            => $feederId,
            'feeder_name'          => $feeder['nama_penyulang'] ?? 'FEEDER',
            'total_nodes'          => count($nodes),
            'shutdown_nodes_count' => count($shutdownSectionIds),
            'sld_nodes'            => $nodes,
            'topology_structure'   => 'RADIAL_OVERHEAD_UNDERGROUND_HYBRID',
            'planning_aid_only'    => true,
            'switching_controller' => false,
            'lineage'              => 'CC-06 Dynamic SLD Topology Generator',
        ];
    }

    /**
     * Get Complete Details of a Specific Work Plan.
     */
    public function getWorkPlanDetail(string $planId): array
    {
        $scopeDoc = json_decode(file_get_contents($this->workScopeRegistryPath), true)['plans'] ?? [];
        $sldDoc   = json_decode(file_get_contents($this->sldPlanningRegistryPath), true)['sld_topologies'] ?? [];
        $wfDoc    = json_decode(file_get_contents($this->workFindingRegistryPath), true)['work_items'] ?? [];
        $matDoc   = json_decode(file_get_contents($this->materialEvidenceRegistryPath), true)['evidence_chains'] ?? [];

        if (!isset($scopeDoc[$planId])) {
            return ['success' => false, 'error' => "Plan ID {$planId} not found."];
        }

        return [
            'success'         => true,
            'plan_summary'    => $scopeDoc[$planId],
            'sld_topology'    => $sldDoc[$planId] ?? null,
            'work_items'      => $wfDoc[$planId] ?? [],
            'evidence_chains' => $matDoc[$planId] ?? [],
        ];
    }

    /**
     * Get Planning Workspace Summary.
     */
    public function getPlanningSummary(): array
    {
        $catDoc   = $this->getInspectionCatalog();
        $scopeDoc = json_decode(file_get_contents($this->workScopeRegistryPath), true)['plans'] ?? [];
        $sldDoc   = json_decode(file_get_contents($this->sldPlanningRegistryPath), true)['sld_topologies'] ?? [];
        $wfDoc    = json_decode(file_get_contents($this->workFindingRegistryPath), true)['work_items'] ?? [];
        $matDoc   = json_decode(file_get_contents($this->materialEvidenceRegistryPath), true)['evidence_chains'] ?? [];

        $totalEvidenceChains = 0;
        foreach ($matDoc as $chains) {
            $totalEvidenceChains += count($chains);
        }

        return [
            'success'                => true,
            'model_version'          => self::MODEL_VERSION,
            'total_inspection_tasks' => count($catDoc['inspection_tasks'] ?? []),
            'total_plans_composed'   => count($scopeDoc),
            'total_sld_topologies'   => count($sldDoc),
            'total_work_item_groups' => count($wfDoc),
            'total_evidence_chains'  => $totalEvidenceChains,
            'governance_role'        => 'GROUP_G_WORK_PLANNING_AND_EVIDENCE_ACTIVE',
            'decision_boundary'      => 'RECOMMENDATION_ONLY (Human Management Review Required)',
        ];
    }
}
