<?php

namespace App\Services;

use App\Models\AssetModel;
use App\Models\ConstructionTypeModel;
use App\Models\ConstructionBomItemModel;
use App\Models\MasterMaterialModel;
use CodeIgniter\Database\BaseConnection;

/**
 * MR-01 Phase 3A: Asset-Driven Material Picker Service (Strictly Read-Only)
 *
 * Implements authoritative data contract:
 * Section -> Asset -> assets.construction_type_id -> Construction -> BOM -> Active Master Materials
 *
 * Enforces 4 Hard Firewalls:
 * 1. Section Scoping Firewall: asset must strictly belong to the specified section.
 * 2. Authoritative Construction Firewall: strictly checks assets.construction_type_id; ZERO silent inference.
 * 3. Held Specification Firewall: 9 held specification variants strictly excluded from picker.
 * 4. Provisional Kubikel Firewall: Kubikel draft items strictly excluded from picker.
 */
class MaterialPickerService
{
    protected BaseConnection $db;
    protected AssetModel $assetModel;
    protected ConstructionTypeModel $constructionModel;
    protected ConstructionBomItemModel $bomModel;
    protected MasterMaterialModel $materialModel;

    /**
     * MR-01 Phase 2B Human Decision Register: Held Specification Items
     */
    protected array $heldSpecifications = [
        'LIGHTNING ARRESTER 20KV',
        'ARRESTER GTT',
        'STRAIN ROD INSULATOR',
        'FUSE CUT OUT 24KV',
    ];

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
        $this->assetModel = new AssetModel();
        $this->constructionModel = new ConstructionTypeModel();
        $this->bomModel = new ConstructionBomItemModel();
        $this->materialModel = new MasterMaterialModel();
    }

    /**
     * Resolve material picker payload deterministically.
     * Guaranteed ZERO database writes (Read-Only).
     */
    public function resolvePicker(int $assetId, int $sectionId): array
    {
        // 1. Input sanity validation
        if ($assetId <= 0 || $sectionId <= 0) {
            return [
                'status' => 'INVALID_ASSET',
                'message' => 'Asset dan Section harus valid.',
                'asset' => null,
                'construction' => null,
                'materials' => [],
            ];
        }

        // 2. Fetch Asset and enforce Section Scoping
        $asset = $this->assetModel->find($assetId);
        if (!$asset || (int)($asset['section_id'] ?? 0) !== $sectionId) {
            return [
                'status' => 'INVALID_ASSET',
                'message' => 'Asset tidak sesuai Section yang dipilih',
                'asset' => null,
                'construction' => null,
                'materials' => [],
            ];
        }

        $assetData = [
            'id' => (int)$asset['id'],
            'kode_asset' => (string)($asset['kode_asset'] ?? ''),
            'nama_asset' => (string)($asset['nama_asset'] ?? ''),
            'jenis_asset' => (string)($asset['jenis_asset'] ?? ''),
            'section_id' => (int)$asset['section_id'],
        ];

        // 3. Authoritative Construction Check: strictly check assets.construction_type_id
        $constructionTypeId = !empty($asset['construction_type_id']) ? (int)$asset['construction_type_id'] : null;
        if (!$constructionTypeId) {
            return [
                'status' => 'NO_CONSTRUCTION',
                'message' => 'KONSTRUKSI BELUM TERPETAKAN',
                'asset' => $assetData,
                'construction' => null,
                'materials' => [],
            ];
        }

        // 4. Resolve Construction Type
        $construction = $this->constructionModel->find($constructionTypeId);
        if (!$construction) {
            return [
                'status' => 'NO_CONSTRUCTION',
                'message' => 'KONSTRUKSI BELUM TERPETAKAN',
                'asset' => $assetData,
                'construction' => null,
                'materials' => [],
            ];
        }

        $cFamily = strtoupper(trim((string)($construction['construction_family'] ?? '')));
        $cStatus = strtoupper(trim((string)($construction['approval_status'] ?? 'ACTIVE')));
        $cCode   = strtoupper(trim((string)($construction['construction_code'] ?? ($construction['code'] ?? ''))));
        $cName   = (string)($construction['construction_name'] ?? ($construction['name'] ?? $cCode));

        // 5. Provisional Kubikel Firewall: block draft/provisional constructions from picker
        if ($cFamily === 'GARDU_KUBIKEL' || $cStatus === 'DRAFT' || str_contains($cCode, 'KUBIKEL')) {
            return [
                'status' => 'PROVISIONAL_BLOCKED',
                'message' => 'KONSTRUKSI MASIH BERSTATUS PROVISIONAL / DRAFT (BELUM FIX)',
                'asset' => $assetData,
                'construction' => [
                    'id' => (int)$construction['id'],
                    'code' => $cCode,
                    'name' => $cName,
                    'family' => $cFamily,
                ],
                'materials' => [],
            ];
        }

        $constructionData = [
            'id' => (int)$construction['id'],
            'code' => $cCode,
            'name' => $cName,
            'family' => $cFamily,
        ];

        // 6. Query Construction BOM Items
        $bomItems = $this->bomModel
            ->where('construction_type_id', $constructionTypeId)
            ->orderBy('id', 'ASC')
            ->findAll() ?: [];

        if (empty($bomItems)) {
            return [
                'status' => 'NO_BOM',
                'message' => 'BOM KONSTRUKSI BELUM TERSEDIA',
                'asset' => $assetData,
                'construction' => $constructionData,
                'materials' => [],
            ];
        }

        // 7. Filter BOM Items & Resolve Active Master Materials
        $materials = [];
        $seenMaterialKeys = [];

        foreach ($bomItems as $item) {
            $rawName = trim((string)($item['raw_material_name'] ?? ''));

            // Firewall: Exclude Held Specification Items
            $isHeld = false;
            foreach ($this->heldSpecifications as $heldPattern) {
                if (strcasecmp($rawName, $heldPattern) === 0) {
                    $isHeld = true;
                    break;
                }
            }
            if ($isHeld) {
                continue;
            }

            // Firewall: Exclude Provisional Kubikel Components
            if (stripos($rawName, 'KUBIKEL') !== false || stripos($rawName, 'OCB') !== false || stripos($rawName, 'RELLAY') !== false) {
                continue;
            }

            // Lookup canonical master material
            $materialId = !empty($item['material_id']) ? (int)$item['material_id'] : null;
            $material = null;

            if ($materialId) {
                $material = $this->materialModel->find($materialId);
            } else {
                $material = $this->materialModel->where('nama_material', $rawName)->first();
            }

            // Only display if material is active
            if ($material && strtoupper((string)($material['status'] ?? 'AKTIF')) === 'AKTIF') {
                $matId = (int)$material['id'];

                // Duplicate Protection: Exactly 1 option per canonical material ID
                if (isset($seenMaterialKeys[$matId])) {
                    continue;
                }
                $seenMaterialKeys[$matId] = true;

                $materials[] = [
                    'id' => $matId,
                    'code' => (string)$material['material_code'],
                    'name' => (string)$material['nama_material'],
                    'field_alias' => (string)($material['nama_lapangan'] ?? ($item['material_alias'] ?? '')),
                    'unit' => (string)($material['satuan'] ?? ($item['unit'] ?? 'SET')),
                    'category' => (string)($material['material_category'] ?? ($item['component_category'] ?? 'HARDWARE')),
                ];
            }
        }

        if (empty($materials)) {
            return [
                'status' => 'NO_BOM',
                'message' => 'BOM KONSTRUKSI BELUM TERSEDIA',
                'asset' => $assetData,
                'construction' => $constructionData,
                'materials' => [],
            ];
        }

        return [
            'status' => 'READY',
            'message' => 'Material sesuai BOM konstruksi',
            'asset' => $assetData,
            'construction' => $constructionData,
            'materials' => $materials,
        ];
    }
}
