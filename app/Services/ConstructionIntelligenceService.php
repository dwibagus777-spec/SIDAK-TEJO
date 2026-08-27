<?php

namespace App\Services;

use App\Models\MasterMaterialModel;
use App\Models\MaterialAliasModel;
use App\Models\ConstructionTypeModel;
use App\Models\ConstructionBomItemModel;
use CodeIgniter\Database\BaseConnection;

/**
 * Service for Construction & Material Intelligence (CR-06A)
 * Governed by 7 Hardening Gates:
 * Gate 1: Pure Material Identity (No stock inventory/warehouse)
 * Gate 2: SET NULL FK policy for unresolved BOM items
 * Gate 3: quantity_status ENUM('KNOWN', 'UNKNOWN', 'NOT_APPLICABLE')
 * Kubikel Draft Governance: approval_status = 'DRAFT'
 */
class ConstructionIntelligenceService
{
    protected BaseConnection $db;
    protected MasterMaterialModel $materialModel;
    protected MaterialAliasModel $aliasModel;
    protected ConstructionTypeModel $constructionModel;
    protected ConstructionBomItemModel $bomModel;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db                = $db ?? \Config\Database::connect();
        $this->materialModel     = new MasterMaterialModel();
        $this->aliasModel        = new MaterialAliasModel();
        $this->constructionModel = new ConstructionTypeModel();
        $this->bomModel          = new ConstructionBomItemModel();
    }

    /**
     * Resolve raw string / field term into canonical Material ID and Object.
     * Fully in-memory matching: Cross-database safe & zero SQL escaping errors.
     */
    public function resolveMaterialAlias(?string $rawAlias): ?array
    {
        if (empty($rawAlias)) {
            return null;
        }

        $clean = trim($rawAlias);
        $norm  = strtoupper(preg_replace('/[^A-Z0-9]/', '', $clean));

        if (empty($norm)) {
            return null;
        }

        $materials = $this->materialModel->findAll() ?: [];

        // 1. Direct match on material_code
        foreach ($materials as $mat) {
            $mCode = strtoupper(trim((string)$mat['material_code']));
            $mNorm = preg_replace('/[^A-Z0-9]/', '', $mCode);
            if ($mCode === strtoupper($clean) || (!empty($norm) && $mNorm === $norm)) {
                return $mat;
            }
        }

        // 2. Lookup alias table
        $aliases = $this->aliasModel->findAll() ?: [];
        foreach ($aliases as $al) {
            $aName = strtoupper(trim((string)$al['alias_name']));
            $aNorm = strtoupper(trim((string)$al['normalized_alias']));
            if ($aName === strtoupper($clean) || (!empty($norm) && $aNorm === $norm)) {
                return $this->materialModel->find($al['material_id']);
            }
        }

        // 3. Match on nama_lapangan or nama_material
        foreach ($materials as $mat) {
            $nLap = !empty($mat['nama_lapangan']) ? strtoupper(trim((string)$mat['nama_lapangan'])) : '';
            $nMat = strtoupper(trim((string)$mat['nama_material']));
            if ($nLap === strtoupper($clean) || $nMat === strtoupper($clean)) {
                return $mat;
            }
        }

        return null;
    }

    /**
     * Register or Update a Master Material
     * Gate 1: Pure identity only.
     */
    public function registerMaterial(array $data): array
    {
        $code = trim((string)($data['material_code'] ?? ''));
        if (empty($code)) {
            throw new \InvalidArgumentException('Material code wajib diisi.');
        }

        $existing = $this->materialModel->where('material_code', $code)->first();

        $payload = [
            'material_code'     => $code,
            'nama_material'     => trim((string)($data['nama_material'] ?? $code)),
            'nama_lapangan'     => !empty($data['nama_lapangan']) ? trim((string)$data['nama_lapangan']) : null,
            'satuan'            => !empty($data['satuan']) ? strtoupper(trim((string)$data['satuan'])) : 'SET',
            'material_domain'   => !empty($data['material_domain']) ? strtoupper(trim((string)$data['material_domain'])) : 'JTM',
            'material_category' => !empty($data['material_category']) ? strtoupper(trim((string)$data['material_category'])) : 'HARDWARE',
            'specification'     => !empty($data['specification']) ? trim((string)$data['specification']) : null,
            'source_workbook'   => $data['source_workbook'] ?? 'KONSTRUKSI.xlsx',
            'source_sheet'      => $data['source_sheet'] ?? null,
            'source_row'        => isset($data['source_row']) ? (int)$data['source_row'] : null,
            'status'            => $data['status'] ?? 'AKTIF',
        ];

        if ($existing) {
            $this->materialModel->update($existing['id'], $payload);
            $materialId = (int)$existing['id'];
        } else {
            $materialId = (int)$this->materialModel->insert($payload, true);
        }

        // Register aliases if provided
        if (!empty($data['aliases']) && is_array($data['aliases'])) {
            foreach ($data['aliases'] as $alias) {
                $this->addMaterialAlias($materialId, (string)$alias, $data['alias_type'] ?? 'FIELD_TERM');
            }
        }

        return $this->materialModel->find($materialId);
    }

    /**
     * Add an alias for a material.
     */
    public function addMaterialAlias(int $materialId, string $aliasName, string $aliasType = 'FIELD_TERM'): void
    {
        $clean = trim($aliasName);
        $norm  = strtoupper(preg_replace('/[^A-Z0-9]/', '', $clean));

        if (empty($norm)) {
            return;
        }

        $exists = $this->aliasModel
            ->where('material_id', $materialId)
            ->where('normalized_alias', $norm)
            ->first();

        if (!$exists) {
            $this->aliasModel->insert([
                'material_id'      => $materialId,
                'alias_name'       => $clean,
                'normalized_alias' => $norm,
                'alias_type'       => $aliasType,
                'source'           => 'KONSTRUKSI.xlsx',
                'created_at'       => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * Register or Update a Construction Type
     * Kubikel Draft Governance: Enforce 'DRAFT' for GARDU_KUBIKEL.
     * Defensive against schema variations between dev and production.
     */
    public function registerConstructionType(array $data): array
    {
        $code = strtoupper(trim((string)($data['construction_code'] ?? ($data['code'] ?? ''))));
        if (empty($code)) {
            throw new \InvalidArgumentException('Construction code wajib diisi.');
        }

        $family = strtoupper(trim((string)($data['construction_family'] ?? 'JTM')));

        // Kubikel Draft Governance Rule
        $approvalStatus = $data['approval_status'] ?? 'ACTIVE';
        if ($family === 'GARDU_KUBIKEL' || str_contains($code, 'KUBIKEL')) {
            $approvalStatus = 'DRAFT';
        }

        // Safe in-memory lookup to avoid column name mismatch exceptions
        $existing = null;
        $allConstructions = $this->constructionModel->findAll() ?: [];
        foreach ($allConstructions as $c) {
            $cCode1 = strtoupper(trim((string)($c['construction_code'] ?? '')));
            $cCode2 = strtoupper(trim((string)($c['code'] ?? '')));
            if ($cCode1 === $code || $cCode2 === $code) {
                $existing = $c;
                break;
            }
        }

        $payload = [
            'construction_family' => $family,
            'approval_status'     => $approvalStatus,
            'is_active'           => $approvalStatus === 'ACTIVE' ? 1 : 0,
        ];

        if ($this->db->fieldExists('code', 'construction_types')) {
            $payload['code'] = $code;
        }
        if ($this->db->fieldExists('construction_code', 'construction_types')) {
            $payload['construction_code'] = $code;
        }
        if ($this->db->fieldExists('name', 'construction_types')) {
            $payload['name'] = trim((string)($data['construction_name'] ?? ($data['name'] ?? $code)));
        }
        if ($this->db->fieldExists('construction_name', 'construction_types')) {
            $payload['construction_name'] = trim((string)($data['construction_name'] ?? ($data['name'] ?? $code)));
        }
        if ($this->db->fieldExists('network_type', 'construction_types')) {
            $payload['network_type'] = $data['network_type'] ?? ($family === 'MVTIC' ? 'MVTIC' : 'JTM');
        }
        if ($this->db->fieldExists('asset_domain', 'construction_types')) {
            $payload['asset_domain'] = $data['asset_domain'] ?? ($family === 'GTT' ? 'GARDU' : ($family === 'GARDU_KUBIKEL' ? 'KUBIKEL' : 'TIANG'));
        }
        if ($this->db->fieldExists('source_sheet', 'construction_types')) {
            $payload['source_sheet'] = $data['source_sheet'] ?? null;
        }
        if ($this->db->fieldExists('source_row', 'construction_types')) {
            $payload['source_row'] = isset($data['source_row']) ? (int)$data['source_row'] : null;
        }

        if ($existing) {
            $this->constructionModel->update($existing['id'], $payload);
            $constructionId = (int)$existing['id'];
        } else {
            $constructionId = (int)$this->constructionModel->insert($payload, true);
        }

        return $this->constructionModel->find($constructionId);
    }

    /**
     * Add a BOM item to a construction type
     * Gate 2: material_id is NULLABLE with ON DELETE SET NULL
     * Gate 3: quantity_status ENUM('KNOWN', 'UNKNOWN', 'NOT_APPLICABLE')
     */
    public function addBomItem(int $constructionTypeId, array $itemData): array
    {
        $rawName = trim((string)($itemData['raw_material_name'] ?? ''));
        if (empty($rawName)) {
            throw new \InvalidArgumentException('Raw material name wajib diisi.');
        }

        // Attempt resolution
        $resolvedMaterial = $this->resolveMaterialAlias($rawName);
        $materialId       = $resolvedMaterial['id'] ?? null;
        $mappingStatus    = $materialId ? 'RESOLVED' : 'UNRESOLVED';

        // Quantity logic (Gate 3)
        $quantity       = isset($itemData['quantity']) && is_numeric($itemData['quantity']) ? (float)$itemData['quantity'] : null;
        $quantityStatus = $itemData['quantity_status'] ?? ($quantity !== null ? 'KNOWN' : 'UNKNOWN');

        $payload = [
            'construction_type_id' => $constructionTypeId,
            'material_id'          => $materialId,
            'raw_material_name'    => $rawName,
            'material_alias'       => $itemData['material_alias'] ?? null,
            'quantity'             => $quantity,
            'quantity_status'      => $quantityStatus,
            'unit'                 => $itemData['unit'] ?? ($resolvedMaterial['satuan'] ?? null),
            'mandatory'            => isset($itemData['mandatory']) ? (int)$itemData['mandatory'] : 1,
            'component_category'   => $itemData['component_category'] ?? ($resolvedMaterial['material_category'] ?? null),
            'source_sheet'         => $itemData['source_sheet'] ?? null,
            'source_row'           => isset($itemData['source_row']) ? (int)$itemData['source_row'] : null,
            'mapping_status'       => $mappingStatus,
        ];

        // Safe query matching existing BOM item
        $existing = $this->bomModel
            ->where('construction_type_id', $constructionTypeId)
            ->where('raw_material_name', $rawName)
            ->first();

        if ($existing) {
            $this->bomModel->update($existing['id'], $payload);
            $bomId = (int)$existing['id'];
        } else {
            $bomId = (int)$this->bomModel->insert($payload, true);
        }

        return $this->bomModel->find($bomId);
    }

    /**
     * Generate Data Quality & Unresolved Materials Report
     */
    public function getDataQualityReport(): array
    {
        $totalMaterials     = $this->materialModel->countAllResults();
        $totalAliases       = $this->aliasModel->countAllResults();
        $totalConstructions = $this->constructionModel->countAllResults();
        $totalBomItems      = $this->bomModel->countAllResults();

        $resolvedBomCount   = $this->bomModel->where('mapping_status', 'RESOLVED')->countAllResults();
        $unresolvedBomCount = $this->bomModel->where('mapping_status', 'UNRESOLVED')->countAllResults();
        $unknownQtyCount    = $this->bomModel->where('quantity_status', 'UNKNOWN')->countAllResults();

        $draftConstructions = $this->constructionModel->where('approval_status', 'DRAFT')->findAll();
        $unresolvedItems    = $this->bomModel->where('mapping_status', 'UNRESOLVED')->findAll();

        return [
            'metrics' => [
                'total_materials'       => $totalMaterials,
                'total_aliases'         => $totalAliases,
                'total_constructions'   => $totalConstructions,
                'total_bom_items'       => $totalBomItems,
                'resolved_bom_items'    => $resolvedBomCount,
                'unresolved_bom_items'  => $unresolvedBomCount,
                'resolution_rate_pct'   => $totalBomItems > 0 ? round(($resolvedBomCount / $totalBomItems) * 100, 2) : 100.0,
                'unknown_qty_items'     => $unknownQtyCount,
                'draft_constructions'   => count($draftConstructions),
            ],
            'draft_constructions' => $draftConstructions,
            'unresolved_items'    => $unresolvedItems,
        ];
    }
}
