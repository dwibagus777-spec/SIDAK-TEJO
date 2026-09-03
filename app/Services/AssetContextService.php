<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;

/**
 * MAP-02: Read-Only Asset Context Service
 * Deterministically resolves full network context and governed BOM for any asset.
 * Guaranteed ZERO database mutations (Strict Read-Only).
 */
class AssetContextService
{
    protected BaseConnection $db;
    protected MaterialPickerService $pickerService;

    public function __construct(?BaseConnection $db = null, ?MaterialPickerService $pickerService = null)
    {
        $this->db = $db ?? Database::connect();
        $this->pickerService = $pickerService ?? new MaterialPickerService($this->db);
    }

    /**
     * Get authoritative asset context with full network hierarchy and governed BOM preview.
     * Enforces user scoping if role is restricted (e.g. admin_ulp).
     *
     * @param int $assetId Asset Primary Key
     * @param int|null $userUlpId Optional user ULP assignment for authorization scoping
     * @param string|null $userRole Optional user role
     * @return array Canonical context structure
     */
    public function getAssetContext(int $assetId, ?int $userUlpId = null, ?string $userRole = null): array
    {
        // 1. Sanity Check
        if ($assetId <= 0) {
            return [
                'status'       => 'INVALID_ASSET',
                'message'      => 'Asset ID tidak valid.',
                'asset'        => null,
                'network'      => null,
                'construction' => null,
                'bom'          => [],
                'navigation'   => null,
            ];
        }

        // 2. Query Asset record
        if (!$this->db->tableExists('assets')) {
            return [
                'status'       => 'INVALID_ASSET',
                'message'      => 'Tabel aset tidak tersedia.',
                'asset'        => null,
                'network'      => null,
                'construction' => null,
                'bom'          => [],
                'navigation'   => null,
            ];
        }

        $builder = $this->db->table('assets')->where('id', $assetId);
        // Filter out soft-deleted records if column exists
        if ($this->db->fieldExists('deleted_at', 'assets')) {
            $builder->where('deleted_at IS NULL');
        }
        $asset = $builder->get()->getRowArray();

        if (!$asset) {
            return [
                'status'       => 'INVALID_ASSET',
                'message'      => 'Aset jaringan tidak ditemukan.',
                'asset'        => null,
                'network'      => null,
                'construction' => null,
                'bom'          => [],
                'navigation'   => null,
            ];
        }

        // 3. Resolve Network Hierarchy: Section -> Penyulang -> ULP
        $sectionId   = !empty($asset['section_id']) ? (int)$asset['section_id'] : null;
        $penyulangId = !empty($asset['penyulang_id']) ? (int)$asset['penyulang_id'] : null;
        $ulpId       = !empty($asset['ulp_id']) ? (int)$asset['ulp_id'] : null;

        $section = null;
        if ($sectionId && $this->db->tableExists('sections')) {
            $section = $this->db->table('sections')->where('id', $sectionId)->get()->getRowArray();
            if ($section && empty($penyulangId) && !empty($section['penyulang_id'])) {
                $penyulangId = (int)$section['penyulang_id'];
            }
        }

        $penyulang = null;
        if ($penyulangId && $this->db->tableExists('penyulang')) {
            $penyulang = $this->db->table('penyulang')->where('id', $penyulangId)->get()->getRowArray();
            if ($penyulang && empty($ulpId) && !empty($penyulang['ulp_id'])) {
                $ulpId = (int)$penyulang['ulp_id'];
            }
        }

        $ulp = null;
        if ($ulpId && $this->db->tableExists('ulps')) {
            $ulp = $this->db->table('ulps')->where('id', $ulpId)->get()->getRowArray();
        }

        // 4. Authorization / Scoping check
        $roleNorm = strtoupper(trim((string)$userRole));
        if ($roleNorm === 'ADMIN_ULP' && $userUlpId !== null && $userUlpId > 0) {
            if ($ulpId !== null && (int)$ulpId !== (int)$userUlpId) {
                return [
                    'status'       => 'FORBIDDEN',
                    'message'      => 'Akses ditolak: Aset berada di luar wilayah wewenang ULP Anda.',
                    'asset'        => null,
                    'network'      => null,
                    'construction' => null,
                    'bom'          => [],
                    'navigation'   => null,
                ];
            }
        }

        // 5. Reuse MaterialPickerService for deterministic Construction & BOM resolution
        // Pass $sectionId if available, otherwise 0
        $effectiveSectionId = $sectionId ?? 0;
        $pickerResult = $this->pickerService->resolvePicker($assetId, $effectiveSectionId);

        // Normalize status
        $finalStatus = $pickerResult['status'] ?? 'INVALID_ASSET';
        $finalMessage = $pickerResult['message'] ?? '';

        // If picker returned INVALID_ASSET because section_id was null or 0, check if asset actually exists
        if ($finalStatus === 'INVALID_ASSET' && empty($sectionId)) {
            $finalStatus = 'NO_SECTION';
            $finalMessage = 'Aset belum terhubung ke section jaringan.';
        }

        // 6. Assemble Asset Identity Block
        $assetBlock = [
            'id'          => (int)$asset['id'],
            'kode_asset'  => (string)($asset['kode_asset'] ?? ''),
            'nama_asset'  => (string)($asset['nama_asset'] ?? ''),
            'jenis_asset' => (string)($asset['jenis_asset'] ?? ''),
            'latitude'    => isset($asset['latitude']) && $asset['latitude'] !== null ? (float)$asset['latitude'] : null,
            'longitude'   => isset($asset['longitude']) && $asset['longitude'] !== null ? (float)$asset['longitude'] : null,
            'lokasi'      => (string)($asset['lokasi'] ?? ''),
            'status'      => (string)($asset['status'] ?? 'NORMAL'),
        ];

        // 7. Assemble Network Context Block
        $networkBlock = [
            'ulp'       => [
                'id'       => $ulp ? (int)$ulp['id'] : null,
                'kode_ulp' => $ulp ? (string)($ulp['kode_ulp'] ?? '') : '',
                'nama_ulp' => $ulp ? (string)($ulp['nama_ulp'] ?? 'ULP Tidak Terpetakan') : 'ULP Tidak Terpetakan',
            ],
            'penyulang' => [
                'id'             => $penyulang ? (int)$penyulang['id'] : null,
                'kode_penyulang' => $penyulang ? (string)($penyulang['kode_penyulang'] ?? '') : '',
                'nama_penyulang' => $penyulang ? (string)($penyulang['nama_penyulang'] ?? 'Penyulang Tidak Terpetakan') : 'Penyulang Tidak Terpetakan',
            ],
            'section'   => [
                'id'           => $section ? (int)$section['id'] : null,
                'nama_section' => $section ? (string)($section['nama_section'] ?? 'Section Tidak Terpetakan') : 'Section Tidak Terpetakan',
            ],
        ];

        // 8. Assemble Navigation Context Handoff (Pure Navigation, No Mutation)
        $navQueryParams = [
            'asset_id' => (int)$asset['id'],
        ];
        if ($section && !empty($section['id'])) {
            $navQueryParams['section_id'] = (int)$section['id'];
        }
        if ($penyulang && !empty($penyulang['id'])) {
            $navQueryParams['penyulang_id'] = (int)$penyulang['id'];
        }
        if ($ulp && !empty($ulp['id'])) {
            $navQueryParams['ulp_id'] = (int)$ulp['id'];
        }

        $createTemuanUrl = site_url('temuan/create') . '?' . http_build_query($navQueryParams);

        // Normalize BOM items to have both standard and canonical keys
        $normalizedBom = [];
        foreach ($pickerResult['materials'] ?? [] as $m) {
            $code = (string)($m['code'] ?? ($m['material_code'] ?? ''));
            $name = (string)($m['name'] ?? ($m['nama_material'] ?? ''));
            $unit = (string)($m['unit'] ?? ($m['satuan'] ?? 'SET'));
            $normalizedBom[] = [
                'material_id'   => (int)($m['id'] ?? ($m['material_id'] ?? 0)),
                'code'          => $code,
                'material_code' => $code,
                'name'          => $name,
                'nama_material' => $name,
                'field_alias'   => (string)($m['field_alias'] ?? ($m['nama_lapangan'] ?? '')),
                'unit'          => $unit,
                'satuan'        => $unit,
                'category'      => (string)($m['category'] ?? ($m['material_category'] ?? '')),
            ];
        }

        return [
            'status'       => $finalStatus,
            'message'      => $finalMessage,
            'asset'        => $assetBlock,
            'network'      => $networkBlock,
            'construction' => $pickerResult['construction'] ?? null,
            'bom'          => $normalizedBom,
            'navigation'   => [
                'create_temuan_url' => $createTemuanUrl,
                'params'            => $navQueryParams,
            ],
        ];
    }
}
