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
     * @param int|null $workingSectionId Optional operator-corrected working section ID (non-persisted)
     * @param int|null $workingConstructionTypeId Optional operator-corrected working construction type ID (non-persisted)
     * @return array Canonical context structure
     */
    public function getAssetContext(
        int $assetId,
        ?int $userUlpId = null,
        ?string $userRole = null,
        ?int $workingSectionId = null,
        ?int $workingConstructionTypeId = null
    ): array {
        // 1. Sanity Check
        if ($assetId <= 0) {
            return [
                'status'         => 'INVALID_ASSET',
                'message'        => 'Asset ID tidak valid.',
                'asset'          => null,
                'network'        => null,
                'construction'   => null,
                'bom'            => [],
                'navigation'     => null,
                'context_source' => null,
                'status_badges'  => null,
            ];
        }

        // 2. Query Asset record
        if (!$this->db->tableExists('assets')) {
            return [
                'status'         => 'INVALID_ASSET',
                'message'        => 'Tabel aset tidak tersedia.',
                'asset'          => null,
                'network'        => null,
                'construction'   => null,
                'bom'            => [],
                'navigation'     => null,
                'context_source' => null,
                'status_badges'  => null,
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
                'status'         => 'INVALID_ASSET',
                'message'        => 'Aset jaringan tidak ditemukan.',
                'asset'          => null,
                'network'        => null,
                'construction'   => null,
                'bom'            => [],
                'navigation'     => null,
                'context_source' => null,
                'status_badges'  => null,
            ];
        }

        // 3. Resolve Authoritative Network Hierarchy: Section -> Penyulang -> ULP
        $authoritativeSectionId   = !empty($asset['section_id']) ? (int)$asset['section_id'] : null;
        $authoritativePenyulangId = !empty($asset['penyulang_id']) ? (int)$asset['penyulang_id'] : null;
        $authoritativeUlpId       = !empty($asset['ulp_id']) ? (int)$asset['ulp_id'] : null;

        $authoritativeSection = null;
        if ($authoritativeSectionId && $this->db->tableExists('sections')) {
            $authoritativeSection = $this->db->table('sections')->where('id', $authoritativeSectionId)->get()->getRowArray();
            if ($authoritativeSection && empty($authoritativePenyulangId) && !empty($authoritativeSection['penyulang_id'])) {
                $authoritativePenyulangId = (int)$authoritativeSection['penyulang_id'];
            }
        }

        $penyulang = null;
        if ($authoritativePenyulangId && $this->db->tableExists('penyulang')) {
            $penyulang = $this->db->table('penyulang')->where('id', $authoritativePenyulangId)->get()->getRowArray();
            if ($penyulang && empty($authoritativeUlpId) && !empty($penyulang['ulp_id'])) {
                $authoritativeUlpId = (int)$penyulang['ulp_id'];
            }
        }

        $ulp = null;
        if ($authoritativeUlpId && $this->db->tableExists('ulps')) {
            $ulp = $this->db->table('ulps')->where('id', $authoritativeUlpId)->get()->getRowArray();
        }

        // 4. Authorization / Scoping check on Authoritative ULP
        $roleNorm = strtoupper(trim((string)$userRole));
        if ($roleNorm === 'ADMIN_ULP' && $userUlpId !== null && $userUlpId > 0) {
            if ($authoritativeUlpId !== null && (int)$authoritativeUlpId !== (int)$userUlpId) {
                return [
                    'status'         => 'FORBIDDEN',
                    'message'        => 'Akses ditolak: Aset berada di luar wilayah wewenang ULP Anda.',
                    'asset'          => null,
                    'network'        => null,
                    'construction'   => null,
                    'bom'            => [],
                    'navigation'     => null,
                    'context_source' => null,
                    'status_badges'  => null,
                ];
            }
        }

        // 5. Section Resolution: Authoritative vs Operator Working Context
        $effectiveSection     = $authoritativeSection;
        $effectiveSectionId   = $authoritativeSectionId;
        $sectionSource        = 'SYSTEM';
        $sectionStatusBadge   = 'TERVERIFIKASI_SISTEM';

        if ($workingSectionId !== null && $workingSectionId > 0) {
            if (!$this->db->tableExists('sections')) {
                return [
                    'status'  => 'INVALID_WORKING_SECTION',
                    'message' => 'Tabel section tidak tersedia.',
                ];
            }

            $candidateSection = $this->db->table('sections')->where('id', $workingSectionId)->get()->getRowArray();
            if (!$candidateSection) {
                return [
                    'status'         => 'INVALID_WORKING_SECTION',
                    'message'        => 'Section koreksi operator tidak ditemukan di database.',
                    'asset'          => null,
                    'network'        => null,
                    'construction'   => null,
                    'bom'            => [],
                    'navigation'     => null,
                    'context_source' => null,
                    'status_badges'  => null,
                ];
            }

            // Cross-Feeder Firewall: Selected section must belong to authoritative feeder
            if ($authoritativePenyulangId !== null && (int)($candidateSection['penyulang_id'] ?? 0) !== $authoritativePenyulangId) {
                return [
                    'status'         => 'INVALID_WORKING_SECTION',
                    'message'        => 'Section koreksi operator tidak berada di bawah penyulang aset ini (Cross-feeder violation).',
                    'asset'          => null,
                    'network'        => null,
                    'construction'   => null,
                    'bom'            => [],
                    'navigation'     => null,
                    'context_source' => null,
                    'status_badges'  => null,
                ];
            }

            // Cross-ULP Firewall for admin_ulp role
            if ($roleNorm === 'ADMIN_ULP' && $userUlpId !== null && $userUlpId > 0) {
                $sectionPenyulang = $this->db->table('penyulang')->where('id', (int)$candidateSection['penyulang_id'])->get()->getRowArray();
                if ($sectionPenyulang && !empty($sectionPenyulang['ulp_id']) && (int)$sectionPenyulang['ulp_id'] !== (int)$userUlpId) {
                    return [
                        'status'         => 'FORBIDDEN',
                        'message'        => 'Akses ditolak: Section koreksi berada di luar wilayah wewenang ULP Anda.',
                        'asset'          => null,
                        'network'        => null,
                        'construction'   => null,
                        'bom'            => [],
                        'navigation'     => null,
                        'context_source' => null,
                        'status_badges'  => null,
                    ];
                }
            }

            $effectiveSection   = $candidateSection;
            $effectiveSectionId = (int)$candidateSection['id'];
            $sectionSource      = 'OPERATOR';
            $sectionStatusBadge = 'DIKOREKSI_OPERATOR';
        } elseif (!$effectiveSection) {
            $sectionSource      = 'UNMAPPED';
            $sectionStatusBadge = 'BELUM_TERPETAKAN';
        }

        // 6. Construction & BOM Resolution: Authoritative vs Operator Working Context
        $authoritativeConstructionId = !empty($asset['construction_type_id']) ? (int)$asset['construction_type_id'] : null;
        $constructionSource          = 'SYSTEM';
        $constructionStatusBadge     = 'TERVERIFIKASI_SISTEM';

        if ($workingConstructionTypeId !== null && $workingConstructionTypeId > 0) {
            $bomResult = $this->pickerService->resolveBomByConstructionTypeId($workingConstructionTypeId);
            if ($bomResult['status'] === 'NO_CONSTRUCTION') {
                return [
                    'status'         => 'INVALID_WORKING_CONSTRUCTION',
                    'message'        => 'Standar konstruksi yang dipilih tidak valid atau tidak ditemukan.',
                    'asset'          => null,
                    'network'        => null,
                    'construction'   => null,
                    'bom'            => [],
                    'navigation'     => null,
                    'context_source' => null,
                    'status_badges'  => null,
                ];
            }

            $pickerResult            = $bomResult;
            $constructionSource      = 'OPERATOR';
            $constructionStatusBadge = 'DIKOREKSI_OPERATOR';
            $finalStatus             = $pickerResult['status'];
            $finalMessage            = $pickerResult['message'];
        } else {
            $pickerResult = $this->pickerService->resolvePicker($assetId, $effectiveSectionId ?? 0);
            $finalStatus  = $pickerResult['status'] ?? 'INVALID_ASSET';
            $finalMessage = $pickerResult['message'] ?? '';

            if ($pickerResult['construction']) {
                $constructionSource      = 'SYSTEM';
                $constructionStatusBadge = 'TERVERIFIKASI_SISTEM';
            } else {
                $constructionSource      = 'UNMAPPED';
                $constructionStatusBadge = 'BELUM_TERPETAKAN';
            }

            // Normalize status if section is unmapped
            if ($finalStatus === 'INVALID_ASSET' && empty($effectiveSectionId)) {
                $finalStatus  = 'NO_SECTION';
                $finalMessage = 'Aset belum terhubung ke section jaringan.';
            }
        }

        // 7. Assemble Asset Identity Block
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

        // 8. Assemble Network Context Block
        $networkBlock = [
            'ulp'       => [
                'id'       => $ulp ? (int)$ulp['id'] : null,
                'kode_ulp' => $ulp ? (string)($ulp['kode_ulp'] ?? '') : '',
                'nama_ulp' => $ulp ? (string)($ulp['nama_ulp'] ?? 'ULP Tidak Terpetakan') : 'ULP Tidak Terpetakan',
                'source'   => 'SYSTEM',
                'locked'   => true,
            ],
            'penyulang' => [
                'id'             => $penyulang ? (int)$penyulang['id'] : null,
                'kode_penyulang' => $penyulang ? (string)($penyulang['kode_penyulang'] ?? '') : '',
                'nama_penyulang' => $penyulang ? (string)($penyulang['nama_penyulang'] ?? 'Penyulang Tidak Terpetakan') : 'Penyulang Tidak Terpetakan',
                'source'         => 'SYSTEM',
                'locked'         => true,
            ],
            'section'   => [
                'id'           => $effectiveSection ? (int)$effectiveSection['id'] : null,
                'nama_section' => $effectiveSection ? (string)($effectiveSection['nama_section'] ?? 'Section Tidak Terpetakan') : 'Section Tidak Terpetakan',
                'source'       => $sectionSource,
                'status_badge' => $sectionStatusBadge,
            ],
        ];

        // 9. Assemble Navigation Context Handoff (Pure Navigation, No Mutation)
        $navQueryParams = [
            'asset_id' => (int)$asset['id'],
        ];
        if ($effectiveSection && !empty($effectiveSection['id'])) {
            $navQueryParams['section_id'] = (int)$effectiveSection['id'];
        }
        if ($penyulang && !empty($penyulang['id'])) {
            $navQueryParams['penyulang_id'] = (int)$penyulang['id'];
        }
        if ($ulp && !empty($ulp['id'])) {
            $navQueryParams['ulp_id'] = (int)$ulp['id'];
        }
        if ($workingSectionId !== null && $workingSectionId > 0) {
            $navQueryParams['working_section_id'] = $workingSectionId;
            $navQueryParams['context_mode']        = 'WORKING_CONTEXT';
        }
        if ($workingConstructionTypeId !== null && $workingConstructionTypeId > 0) {
            $navQueryParams['working_construction_id'] = $workingConstructionTypeId;
            $navQueryParams['context_mode']             = 'WORKING_CONTEXT';
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
            'status'         => $finalStatus,
            'message'        => $finalMessage,
            'asset'          => $assetBlock,
            'network'        => $networkBlock,
            'construction'   => $pickerResult['construction'] ?? null,
            'bom'            => $normalizedBom,
            'context_source' => [
                'ulp'          => 'SYSTEM',
                'penyulang'    => 'SYSTEM',
                'section'      => $sectionSource,
                'construction' => $constructionSource,
            ],
            'status_badges'  => [
                'ulp'          => 'TERVERIFIKASI_SISTEM',
                'penyulang'    => 'TERVERIFIKASI_SISTEM',
                'section'      => $sectionStatusBadge,
                'construction' => $constructionStatusBadge,
            ],
            'navigation'     => [
                'create_temuan_url' => $createTemuanUrl,
                'params'            => $navQueryParams,
            ],
        ];
    }
}
