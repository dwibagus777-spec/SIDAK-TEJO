<?php

namespace App\Services;

use App\Models\AssetModel;
use App\Models\AssetIntelligenceSnapshotModel;
use App\Models\SectionModel;
use App\Models\PenyulangModel;
use App\Models\TemuanModel;
use CodeIgniter\Database\BaseConnection;

/**
 * Service for Construction-to-Asset Intelligence (CR-06G Contract v1.0)
 * Governed by 8 Hardening Gates (G1 - G8):
 * - Gate G0: Non-Destructive As-Built Invariant (Never mutates physical config)
 * - Gate G1: Deterministic Asset-to-Construction Resolution (No synthetic assets)
 * - Gate G2: Non-Destructive As-Built Invariant
 * - Gate G3: Canonical BOM Linkage
 * - Gate G4: Dual-Scope Finding Attribution (Asset-Bound vs Section-Bound)
 * - Gate G5: Explainable Degradation Scoring (ADI / AHS)
 * - Gate G6: Recurrence & Severity Amplification
 * - Gate G7: Fail-Closed Operational Audit
 * - Gate G8: Explainability & Audit Trail Preservation (No Data != Healthy)
 */
class ConstructionAssetIntelligenceService
{
    protected BaseConnection $db;
    protected AssetModel $assetModel;
    protected AssetIntelligenceSnapshotModel $snapshotModel;
    protected SectionModel $sectionModel;
    protected PenyulangModel $penyulangModel;
    protected TemuanModel $temuanModel;
    protected ConstructionIntelligenceService $ciService;

    // Component Weights for Asset Degradation Index (ADI)
    const COMPONENT_WEIGHTS = [
        'POLE'        => 0.35,
        'CROSS_ARM'   => 0.25,
        'INSULATOR'   => 0.25,
        'CONDUCTOR'   => 0.30,
        'PROTECTION'  => 0.20,
        'GROUNDING'   => 0.15,
        'ROW'         => 0.15,
        'OTHER'       => 0.10,
    ];

    // Priority / Severity Factors
    const SEVERITY_FACTORS = [
        'EMERGENCY' => 1.0,
        'KRITIS'    => 0.8,
        'SERIUS'    => 0.5,
        'SEDANG'    => 0.4,
        'RINGAN'    => 0.2,
        'LOW'       => 0.2,
        'MEDIUM'    => 0.4,
        'HIGH'      => 0.8,
        'CRITICAL'  => 1.0,
    ];

    public function __construct(?BaseConnection $db = null)
    {
        $this->db            = $db ?? \Config\Database::connect();
        $this->assetModel    = new AssetModel();
        $this->snapshotModel = new AssetIntelligenceSnapshotModel();
        $this->sectionModel  = new SectionModel();
        $this->penyulangModel= new PenyulangModel();
        $this->temuanModel   = new TemuanModel();
        $this->ciService     = new ConstructionIntelligenceService($this->db);
    }

    /**
     * Resolve Asset to Construction Type deterministically (Gate G1).
     * Returns resolution array without creating synthetic assets.
     */
    public function resolveAssetConstructionType(array $asset): array
    {
        $constructionTypeId = !empty($asset['construction_type_id']) ? (int)$asset['construction_type_id'] : null;

        if ($constructionTypeId && $this->db->tableExists('construction_types')) {
            $ctype = $this->db->table('construction_types')
                ->where('id', $constructionTypeId)
                ->where('approval_status', 'ACTIVE')
                ->get()
                ->getFirstRow('array');

            if ($ctype) {
                return [
                    'status'            => 'RESOLVED',
                    'construction_type' => $ctype,
                    'method'            => 'DIRECT_FOREIGN_KEY',
                ];
            }
        }

        // Fallback: Deterministic inference based on existing asset tags
        $tag = trim((string)($asset['jenis_asset'] ?? ''));
        if (!empty($tag) && $this->db->tableExists('construction_types')) {
            $cleanTag = str_replace(['-', ' ', '_'], '', strtoupper($tag));

            $ctype = $this->db->table('construction_types')
                ->groupStart()
                    ->where('construction_code', $tag)
                    ->orWhere('construction_code', $cleanTag)
                    ->orLike('construction_name', $tag)
                    ->orLike('construction_name', $cleanTag)
                ->groupEnd()
                ->where('approval_status', 'ACTIVE')
                ->get()
                ->getFirstRow('array');

            if ($ctype) {
                return [
                    'status'            => 'RESOLVED',
                    'construction_type' => $ctype,
                    'method'            => 'TAG_INFERENCE',
                ];
            }
        }

        return [
            'status'            => 'UNRESOLVED',
            'construction_type' => null,
            'method'            => 'NONE',
        ];
    }

    /**
     * Resolve BOM for a construction type (Gate G3).
     */
    public function resolveBom(int $constructionTypeId): array
    {
        if (!$this->db->tableExists('construction_bom_items')) {
            return [
                'status'             => 'UNRESOLVED',
                'completeness_ratio' => 0.0,
                'items'              => [],
            ];
        }

        $items = $this->db->table('construction_bom_items')
            ->where('construction_type_id', $constructionTypeId)
            ->get()
            ->getResultArray();

        if (empty($items)) {
            return [
                'status'             => 'PARTIAL',
                'completeness_ratio' => 0.0,
                'items'              => [],
            ];
        }

        $resolvedCount = 0;
        $totalItems    = count($items);
        $resolvedItems = [];

        foreach ($items as $item) {
            $matId = !empty($item['material_id']) ? (int)$item['material_id'] : null;
            $mat   = null;

            if ($matId && $this->db->tableExists('master_materials')) {
                $mat = $this->db->table('master_materials')->where('id', $matId)->get()->getFirstRow('array');
            }

            if (!$mat && !empty($item['material_alias'])) {
                $mat = $this->ciService->resolveMaterialAlias($item['material_alias']);
            }

            if ($mat) {
                $resolvedCount++;
                $resolvedItems[] = [
                    'bom_id'             => (int)$item['id'],
                    'material_id'        => (int)$mat['id'],
                    'material_code'      => $mat['material_code'],
                    'nama_material'      => $mat['nama_material'],
                    'quantity'           => (float)($item['quantity'] ?? 1.0),
                    'quantity_status'    => $item['quantity_status'] ?? 'KNOWN',
                    'component_category' => $item['component_category'] ?? 'HARDWARE',
                    'resolved'           => true,
                ];
            } else {
                $resolvedItems[] = [
                    'bom_id'             => (int)$item['id'],
                    'material_id'        => null,
                    'material_code'      => null,
                    'raw_name'           => $item['raw_material_name'] ?? 'Unknown Item',
                    'quantity'           => (float)($item['quantity'] ?? 1.0),
                    'quantity_status'    => $item['quantity_status'] ?? 'UNKNOWN',
                    'component_category' => $item['component_category'] ?? 'HARDWARE',
                    'resolved'           => false,
                ];
            }
        }

        $ratio = $totalItems > 0 ? ($resolvedCount / $totalItems) : 0.0;
        $status = ($ratio >= 1.0) ? 'RESOLVED' : ($ratio > 0 ? 'PARTIAL' : 'UNRESOLVED');

        return [
            'status'             => $status,
            'completeness_ratio' => round($ratio, 4),
            'items'              => $resolvedItems,
        ];
    }

    /**
     * Map active findings to asset and classify into canonical component categories (Gate G4).
     */
    public function getAttributedFindings(int $assetId): array
    {
        if (!$this->db->tableExists('temuan')) {
            return [];
        }

        $builder = $this->db->table('temuan')
            ->where('asset_id', $assetId)
            ->where('deleted_at IS NULL');

        $findings = $builder->get()->getResultArray();
        $attributed = [];

        foreach ($findings as $f) {
            $component = $this->classifyFindingComponent($f);
            $priority  = strtoupper(trim((string)($f['prioritas'] ?? 'RINGAN')));
            $severity  = self::SEVERITY_FACTORS[$priority] ?? 0.2;
            $recurr    = isset($f['recurrence_count']) ? (int)$f['recurrence_count'] : 0;

            $attributed[] = [
                'finding_id'       => (int)$f['id'],
                'nomor_temuan'     => $f['nomor_temuan'],
                'jenis_temuan'     => $f['jenis_temuan'],
                'detail_temuan'    => $f['detail_temuan'] ?? '',
                'component'        => $component,
                'priority'         => $priority,
                'severity_factor'  => $severity,
                'recurrence_count' => $recurr,
            ];
        }

        return $attributed;
    }

    /**
     * Classify finding text into canonical component category (Gate G4).
     */
    public function classifyFindingComponent(array $finding): string
    {
        $text = strtoupper(
            ($finding['jenis_temuan'] ?? '') . ' ' . 
            ($finding['detail_temuan'] ?? '') . ' ' . 
            ($finding['component_code'] ?? '') . ' ' .
            ($finding['material'] ?? '')
        );

        if (str_contains($text, 'ROW') || str_contains($text, 'POHON') || str_contains($text, 'RANTING') || str_contains($text, 'JARAK BEBAS') || str_contains($text, 'BAMBU') || str_contains($text, 'TANAMAN')) {
            return 'ROW';
        }
        if (str_contains($text, 'ISOLATOR') || str_contains($text, 'INSULATOR') || str_contains($text, 'SIR') || str_contains($text, 'PIN')) {
            return 'INSULATOR';
        }
        if (str_contains($text, 'TIANG') || str_contains($text, 'POLE') || str_contains($text, 'FONDASI')) {
            return 'POLE';
        }
        if (str_contains($text, 'TRAVERS') || str_contains($text, 'CROSS ARM') || str_contains($text, 'ARM') || str_contains($text, 'UNP')) {
            return 'CROSS_ARM';
        }
        if (str_contains($text, 'ARRESTER') || str_contains($text, 'LA') || str_contains($text, 'FCO') || str_contains($text, 'CUT OUT') || str_contains($text, 'PROTEKSI') || str_contains($text, 'CLD')) {
            return 'PROTECTION';
        }
        if (str_contains($text, 'GROUND') || str_contains($text, 'PENTANAHAN') || str_contains($text, 'GSW') || str_contains($text, 'ARDE')) {
            return 'GROUNDING';
        }
        if (str_contains($text, 'KONDUKTOR') || str_contains($text, 'KABEL') || str_contains($text, 'AAAC') || str_contains($text, 'RANTAS') || str_contains($text, 'ANDONGAN')) {
            return 'CONDUCTOR';
        }

        return 'OTHER';
    }

    /**
     * Calculate Explainable Asset Health Score (AHS) & Asset Degradation Index (ADI) (Gate G5, G6, G8).
     * Enforces Invariant: "No Data != Healthy".
     */
    public function calculateAssetHealth(int $assetId): array
    {
        $asset = $this->db->table('assets')->where('id', $assetId)->where('deleted_at IS NULL')->get()->getFirstRow('array')
            ?? $this->db->table('assets')->where('id', $assetId)->get()->getFirstRow('array');

        if (!$asset) {
            return [
                'success'           => false,
                'error'             => "Asset ID #{$assetId} tidak ditemukan.",
                'resolution_status' => 'INVALID',
            ];
        }

        // 1. Resolve Construction Type
        $ctypeResolution = $this->resolveAssetConstructionType($asset);
        if ($ctypeResolution['status'] !== 'RESOLVED') {
            return $this->buildUnresolvedHealthResult($asset, 'UNRESOLVED_CONSTRUCTION_TYPE', 0.0);
        }

        $ctype = $ctypeResolution['construction_type'];

        // 2. Resolve BOM
        $bomResolution = $this->resolveBom((int)$ctype['id']);
        if ($bomResolution['status'] === 'UNRESOLVED') {
            return $this->buildUnresolvedHealthResult($asset, 'UNRESOLVED_BOM', 0.0);
        }

        // 3. Resolve Attributed Findings
        $findings = $this->getAttributedFindings($assetId);

        // 4. Calculate Explainable ADI & AHS
        $componentDegradations = [];
        $totalAdi = 0.0;

        foreach ($findings as $f) {
            $comp   = $f['component'];
            $weight = self::COMPONENT_WEIGHTS[$comp] ?? 0.10;
            $sev    = $f['severity_factor'];
            $rec    = min($f['recurrence_count'], 3); // Max multiplier 3

            // Formula: wi * Si * (1 + 0.5 * Ri)
            $factor = $weight * $sev * (1.0 + 0.5 * $rec);

            $componentDegradations[$comp] = ($componentDegradations[$comp] ?? 0.0) + $factor;
            $totalAdi += $factor;
        }

        // Cap ADI at 1.0
        $finalAdi = min(1.0, round($totalAdi, 4));
        $ahs      = round(100.0 * (1.0 - $finalAdi), 2);

        // Determine Health Category
        if ($ahs >= 85.0) {
            $category = 'GOOD';
        } elseif ($ahs >= 70.0) {
            $category = 'WARNING';
        } elseif ($ahs >= 50.0) {
            $category = 'POOR';
        } else {
            $category = 'CRITICAL';
        }

        $breakdown = [
            'construction_code'      => $ctype['construction_code'],
            'construction_name'      => $ctype['construction_name'],
            'bom_completeness_ratio' => $bomResolution['completeness_ratio'],
            'total_active_findings'  => count($findings),
            'component_degradation'  => $componentDegradations,
            'raw_adi'                => round($totalAdi, 4),
            'final_adi'              => $finalAdi,
            'ahs'                    => $ahs,
        ];

        // 5. Save Snapshot for Audit Trail & Explainability (Gate G8)
        $snapshotId = $this->snapshotModel->insert([
            'asset_id'                   => $assetId,
            'penyulang_id'               => $asset['penyulang_id'] ?? null,
            'section_id'                 => $asset['section_id'] ?? null,
            'construction_type_id'       => (int)$ctype['id'],
            'resolution_status'          => 'RESOLVED',
            'bom_completeness_ratio'     => $bomResolution['completeness_ratio'],
            'active_findings_count'      => count($findings),
            'recurring_findings_count'   => count(array_filter($findings, fn($f) => $f['recurrence_count'] > 0)),
            'asset_degradation_index'    => $finalAdi,
            'asset_health_score'         => $ahs,
            'health_category'            => $category,
            'degradation_breakdown_json' => json_encode($breakdown, JSON_PRETTY_PRINT),
            'snapshot_version'           => 'CR-06G-v1.0',
            'calculated_at'              => date('Y-m-d H:i:s'),
        ], true);

        // Update assets table cache
        $this->db->table('assets')->where('id', $assetId)->update([
            'construction_type_id'            => (int)$ctype['id'],
            'health_score'                    => $ahs,
            'health_category'                 => $category,
            'degradation_index'               => $finalAdi,
            'intelligence_resolution_status'  => 'RESOLVED',
            'updated_at'                      => date('Y-m-d H:i:s'),
        ]);

        return [
            'success'                 => true,
            'asset_id'                => $assetId,
            'resolution_status'       => 'RESOLVED',
            'construction_type_id'    => (int)$ctype['id'],
            'construction_code'       => $ctype['construction_code'],
            'bom_completeness_ratio'  => $bomResolution['completeness_ratio'],
            'asset_degradation_index' => $finalAdi,
            'asset_health_score'      => $ahs,
            'health_category'         => $category,
            'active_findings_count'   => count($findings),
            'breakdown'               => $breakdown,
            'snapshot_id'             => $snapshotId,
        ];
    }

    /**
     * Build honest unresolved result (Gate G0 & G8: No Data != Healthy).
     */
    private function buildUnresolvedHealthResult(array $asset, string $reason, float $bomRatio): array
    {
        $status = ($reason === 'UNRESOLVED_CONSTRUCTION_TYPE') ? 'UNRESOLVED' : 'PARTIAL';

        $this->db->table('assets')->where('id', $asset['id'])->update([
            'health_score'                   => null,
            'health_category'                => 'UNRESOLVED',
            'degradation_index'              => null,
            'intelligence_resolution_status' => $status,
            'updated_at'                     => date('Y-m-d H:i:s'),
        ]);

        return [
            'success'                 => true,
            'asset_id'                => (int)$asset['id'],
            'resolution_status'       => $status,
            'unresolved_reason'       => $reason,
            'bom_completeness_ratio'  => $bomRatio,
            'asset_degradation_index' => null,
            'asset_health_score'      => null,
            'health_category'         => 'UNRESOLVED',
            'active_findings_count'   => 0,
            'breakdown'               => [
                'note' => "Health calculation suspended: {$reason}. Prevented false-healthy rating (Gate G8).",
            ],
        ];
    }

    /**
     * Calculate Section Intelligence Summary for CR-06H & CC-04.
     */
    public function getSectionIntelligenceSummary(int $sectionId): array
    {
        $assets = $this->assetModel->where('section_id', $sectionId)->findAll();
        $totalAssets = count($assets);

        if ($totalAssets === 0) {
            return [
                'section_id'              => $sectionId,
                'total_assets'            => 0,
                'resolved_assets'         => 0,
                'unresolved_assets'       => 0,
                'average_health_score'    => null,
                'section_structural_risk' => 0.0,
                'status'                  => 'NO_ASSETS',
            ];
        }

        $resolvedCount = 0;
        $ahsSum        = 0.0;
        $adiSum        = 0.0;

        foreach ($assets as $a) {
            $health = $this->calculateAssetHealth((int)$a['id']);
            if ($health['resolution_status'] === 'RESOLVED' && $health['asset_health_score'] !== null) {
                $resolvedCount++;
                $ahsSum += (float)$health['asset_health_score'];
                $adiSum += (float)$health['asset_degradation_index'];
            }
        }

        $avgAhs = $resolvedCount > 0 ? round($ahsSum / $resolvedCount, 2) : null;
        $avgAdi = $resolvedCount > 0 ? round($adiSum / $resolvedCount, 4) : 0.0;

        return [
            'section_id'              => $sectionId,
            'total_assets'            => $totalAssets,
            'resolved_assets'         => $resolvedCount,
            'unresolved_assets'       => $totalAssets - $resolvedCount,
            'average_health_score'    => $avgAhs,
            'section_structural_risk' => $avgAdi,
            'status'                  => ($resolvedCount === $totalAssets) ? 'RESOLVED' : ($resolvedCount > 0 ? 'PARTIAL' : 'UNRESOLVED'),
        ];
    }

    /**
     * Calculate Feeder Rollup for CC-04 Interface Contract.
     */
    public function getFeederIntelligenceSummary(int $penyulangId): array
    {
        $feeder = $this->penyulangModel->find($penyulangId);
        if (!$feeder) {
            return ['success' => false, 'error' => "Feeder ID #{$penyulangId} tidak ditemukan."];
        }

        $sections = $this->sectionModel->where('penyulang_id', $penyulangId)->findAll();
        $sectionsSummary = [];
        $totalAssets = 0;
        $resolvedAssets = 0;
        $totalAdi = 0.0;

        foreach ($sections as $s) {
            $secSum = $this->getSectionIntelligenceSummary((int)$s['id']);
            $sectionsSummary[] = $secSum;

            $totalAssets    += $secSum['total_assets'];
            $resolvedAssets += $secSum['resolved_assets'];
            $totalAdi       += ($secSum['section_structural_risk'] * $secSum['resolved_assets']);
        }

        $overallAdi = $resolvedAssets > 0 ? round($totalAdi / $resolvedAssets, 4) : 0.0;
        $overallAhs = $resolvedAssets > 0 ? round(100.0 * (1.0 - $overallAdi), 2) : null;

        // Findings count on feeder
        $activeFindings = $this->temuanModel
            ->where('penyulang_id', $penyulangId)
            ->where('deleted_at IS NULL')
            ->countAllResults();

        $criticalFindings = $this->temuanModel
            ->where('penyulang_id', $penyulangId)
            ->whereIn('prioritas', ['EMERGENCY', 'KRITIS', 'HIGH', 'CRITICAL'])
            ->where('deleted_at IS NULL')
            ->countAllResults();

        $recurringFindings = $this->temuanModel
            ->where('penyulang_id', $penyulangId)
            ->where('recurrence_count >', 0)
            ->where('deleted_at IS NULL')
            ->countAllResults();

        return [
            'feeder_id'               => $penyulangId,
            'kode_penyulang'          => $feeder['kode_penyulang'] ?? 'N/A',
            'nama_penyulang'          => $feeder['nama_penyulang'],
            'total_sections'          => count($sections),
            'total_assets'            => $totalAssets,
            'resolved_assets'         => $resolvedAssets,
            'unresolved_assets'       => $totalAssets - $resolvedAssets,
            'overall_health_score'    => $overallAhs,
            'bom_degradation_factor'  => $overallAdi,
            'active_findings_count'   => $activeFindings,
            'critical_findings_count' => $criticalFindings,
            'recurring_findings_count'=> $recurringFindings,
            'sections_breakdown'      => $sectionsSummary,
        ];
    }
}
