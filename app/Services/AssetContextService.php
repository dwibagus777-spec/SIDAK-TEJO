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
        if (function_exists('helper')) {
            helper('app');
        }
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

        if (!empty($asset['section_resolution_method']) && $asset['section_resolution_method'] === 'OPERATOR_CORRECTION') {
            $sectionSource      = 'OPERATOR';
            $sectionStatusBadge = 'DIKOREKSI_OPERATOR';
        }

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
        $activeFindings = 0;
        $totalFindings  = 0;
        if ($this->db->tableExists('temuan')) {
            $activeFindings = (int)$this->db->table('temuan')
                ->where('asset_id', (int)$asset['id'])
                ->where('status !=', 'SELESAI')
                ->where('deleted_at IS NULL')
                ->countAllResults();
            $totalFindings = (int)$this->db->table('temuan')
                ->where('asset_id', (int)$asset['id'])
                ->where('deleted_at IS NULL')
                ->countAllResults();
        }

        $assetBlock = [
            'id'                    => (int)$asset['id'],
            'kode_asset'            => (string)($asset['kode_asset'] ?? ''),
            'nama_asset'            => (string)($asset['nama_asset'] ?? ''),
            'jenis_asset'           => (string)($asset['jenis_asset'] ?? ''),
            'latitude'              => isset($asset['latitude']) && $asset['latitude'] !== null ? (float)$asset['latitude'] : null,
            'longitude'             => isset($asset['longitude']) && $asset['longitude'] !== null ? (float)$asset['longitude'] : null,
            'lokasi'                => (string)($asset['lokasi'] ?? ''),
            'status'                => (string)($asset['status'] ?? 'NORMAL'),
            'active_findings_count' => $activeFindings,
            'total_findings_count'  => $totalFindings,
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
            'asset_id'        => (int)$asset['id'],
            'focus_asset_id'  => (int)$asset['id'],
            'from'            => 'gis_inspection',
            'inspection_date' => date('Y-m-d'),
        ];
        if (!empty($asset['latitude']) && !empty($asset['longitude'])) {
            $navQueryParams['lat']  = $asset['latitude'];
            $navQueryParams['lng']  = $asset['longitude'];
            $navQueryParams['zoom'] = 19;
        }
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
        $viewTemuanUrl   = site_url('temuan') . '?asset_id=' . (int)$asset['id'];

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
                'default_qty'   => (float)($m['default_qty'] ?? 1.0),
                'category'      => (string)($m['category'] ?? ($m['material_category'] ?? '')),
            ];
        }

        $accService  = new \App\Services\JtmAccessoryService($this->db);
        $accessories = $accService->getAccessoriesForAsset($assetId);

        return [
            'status'         => $finalStatus,
            'message'        => $finalMessage,
            'asset'          => $assetBlock,
            'network'        => $networkBlock,
            'construction'   => $pickerResult['construction'] ?? null,
            'bom'            => $normalizedBom,
            'accessories'    => $accessories,
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
                'view_temuan_url'   => $viewTemuanUrl,
                'params'            => $navQueryParams,
            ],
        ];
    }

    /**
     * FIX-01: Persistent Operator Section Correction
     * Atomically updates assets.section_id with strict boundary, field isolation, and audit validation.
     */
    public function correctSection(
        int $assetId,
        int $newSectionId,
        int $userId,
        string $userRole = '',
        ?int $userUlpId = null,
        string $reason = ''
    ): array {
        if ($assetId <= 0) {
            return ['status' => 'error', 'code' => 'INVALID_ASSET', 'message' => 'Asset ID tidak valid.'];
        }
        if ($newSectionId <= 0) {
            return ['status' => 'error', 'code' => 'INVALID_SECTION', 'message' => 'Section ID tidak valid.'];
        }

        $builder = $this->db->table('assets')->where('id', $assetId);
        if ($this->db->fieldExists('deleted_at', 'assets')) {
            $builder->where('deleted_at IS NULL');
        }
        $asset = $builder->get()->getRowArray();
        if (!$asset) {
            return ['status' => 'error', 'code' => 'ASSET_NOT_FOUND', 'message' => 'Aset tidak ditemukan atau telah dihapus.'];
        }

        if (!$this->db->tableExists('sections')) {
            return ['status' => 'error', 'code' => 'TABLE_NOT_FOUND', 'message' => 'Tabel section tidak tersedia.'];
        }

        $section = $this->db->table('sections')->where('id', $newSectionId)->get()->getRowArray();
        if (!$section) {
            return ['status' => 'error', 'code' => 'SECTION_NOT_FOUND', 'message' => 'Section target tidak ditemukan di database.'];
        }

        // 1. Feeder Boundary Firewall
        $assetPenyulangId = (int)($asset['penyulang_id'] ?? 0);
        $sectionPenyulangId = (int)($section['penyulang_id'] ?? 0);
        if ($assetPenyulangId > 0 && $sectionPenyulangId > 0 && $assetPenyulangId !== $sectionPenyulangId) {
            return [
                'status'  => 'error',
                'code'    => 'CROSS_FEEDER_REJECTED',
                'message' => "Pelanggaran batas penyulang: Section target (#{$newSectionId}) berada pada penyulang berbeda dari aset (#{$assetId})."
            ];
        }

        // 2. ULP Boundary Firewall (for restricted roles)
        $roleNorm = strtoupper(trim((string)$userRole));
        if ($roleNorm === 'ADMIN_ULP' && $userUlpId !== null && $userUlpId > 0) {
            $assetUlpId = (int)($asset['ulp_id'] ?? 0);
            $secUlpId = (int)($section['ulp_id'] ?? 0);
            if (($assetUlpId > 0 && $assetUlpId !== $userUlpId) || ($secUlpId > 0 && $secUlpId !== $userUlpId)) {
                return [
                    'status'  => 'error',
                    'code'    => 'CROSS_ULP_REJECTED',
                    'message' => 'Akses ditolak: Aset atau section berada di luar wilayah wewenang ULP Anda.'
                ];
            }
        }

        // 3. Capture Pre-Update Fingerprint (Strict Invariant Protection)
        $before = [
            'id'                   => (int)$asset['id'],
            'section_id'           => $asset['section_id'] !== null ? (int)$asset['section_id'] : null,
            'construction_type_id' => $asset['construction_type_id'] !== null ? (int)$asset['construction_type_id'] : null,
            'latitude'             => (string)$asset['latitude'],
            'longitude'            => (string)$asset['longitude'],
            'kode_asset'           => (string)$asset['kode_asset'],
            'nama_asset'           => (string)($asset['nama_asset'] ?? ''),
            'penyulang_id'         => (int)($asset['penyulang_id'] ?? 0),
            'ulp_id'               => (int)($asset['ulp_id'] ?? 0),
        ];

        // 4. Atomic Database Update
        $this->db->transBegin();
        try {
            $updatePayload = [
                'section_id' => $newSectionId,
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            if ($this->db->fieldExists('section_resolution_method', 'assets')) {
                $updatePayload['section_resolution_method'] = 'OPERATOR_CORRECTION';
            }
            if ($this->db->fieldExists('section_verified_by', 'assets')) {
                $updatePayload['section_verified_by'] = $userId > 0 ? $userId : null;
            }
            if ($this->db->fieldExists('section_verified_at', 'assets')) {
                $updatePayload['section_verified_at'] = date('Y-m-d H:i:s');
            }

            $this->db->table('assets')->where('id', $assetId)->update($updatePayload);

            // 5. Post-Update Server-Authoritative Verification
            $after = $this->db->table('assets')->where('id', $assetId)->get()->getRowArray();
            if (!$after) {
                throw new \RuntimeException("Gagal memuat ulang aset pasca-update.");
            }

            if ((int)$after['section_id'] !== $newSectionId) {
                throw new \RuntimeException("Integritas gagal: section_id di database tidak sesuai target.");
            }

            // Invariant assertions: Unrequested fields MUST NOT change
            if ((string)$after['latitude'] !== $before['latitude'] ||
                (string)$after['longitude'] !== $before['longitude'] ||
                (string)$after['kode_asset'] !== $before['kode_asset'] ||
                (string)($after['nama_asset'] ?? '') !== $before['nama_asset'] ||
                (int)($after['penyulang_id'] ?? 0) !== $before['penyulang_id'] ||
                (int)($after['ulp_id'] ?? 0) !== $before['ulp_id'] ||
                (int)($after['construction_type_id'] ?? 0) !== (int)($before['construction_type_id'] ?? 0)
            ) {
                throw new \RuntimeException("Integritas gagal: Terdeteksi mutasi pada kolom terproteksi!");
            }

            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            return [
                'status'  => 'error',
                'code'    => 'TRANSACTION_ROLLBACK',
                'message' => 'Gagal menyimpan koreksi section: ' . $e->getMessage()
            ];
        }

        // 6. Audit Trail Logging (Existing infrastructure)
        $auditReason = $reason ?: 'Koreksi Section Operator via GIS';
        if (function_exists('log_activity')) {
            log_activity(
                'OPERATOR_CORRECT_SECTION',
                "Asset #{$assetId} ({$before['kode_asset']}) Section changed: {$before['section_id']} -> {$newSectionId}. User: #{$userId}. Reason: {$auditReason}"
            );
        }

        if ($this->db->tableExists('audit_logs')) {
            try {
                $this->db->table('audit_logs')->insert([
                    'user_id'    => $userId > 0 ? $userId : null,
                    'username'   => 'OPERATOR',
                    'role'       => $userRole ?: 'operator',
                    'aktivitas'  => 'OPERATOR_CORRECT_SECTION',
                    'detail'     => "Asset #{$assetId} ({$before['kode_asset']}) Section: {$before['section_id']} -> {$newSectionId}. Reason: {$auditReason}",
                    'ip_address' => '127.0.0.1',
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            } catch (\Throwable $ae) {
                // Ignore optional audit errors
            }
        }

        try {
            if (class_exists('\App\Services\AssetHistoryService') && class_exists('\Config\AssetEvent')) {
                (new \App\Services\AssetHistoryService())->logEvent(
                    $assetId,
                    \Config\AssetEvent::UPDATED ?? 'UPDATED',
                    (string)$before['section_id'],
                    (string)$newSectionId,
                    $before['kode_asset'],
                    "Koreksi Section oleh Operator: #{$before['section_id']} -> #{$newSectionId}. {$auditReason}",
                    $userId
                );
            }
        } catch (\Throwable $ae) {
            // Ignore optional history logging errors
        }

        return [
            'status'           => 'success',
            'code'             => 'SECTION_CORRECTED',
            'message'          => "Koreksi Section berhasil disimpan ke database master (Section ID: {$newSectionId}).",
            'asset_id'         => $assetId,
            'field'            => 'section_id',
            'old_value'        => $before['section_id'],
            'new_value'        => $newSectionId,
            'persisted'        => true,
            'updated_context'  => $this->getAssetContext($assetId, $userUlpId, $userRole),
        ];
    }

    /**
     * FIX-01: Persistent Operator Construction Type Correction
     * Atomically updates assets.construction_type_id with strict boundary, field isolation, and audit validation.
     */
    public function correctConstruction(
        int $assetId,
        int $newConstructionTypeId,
        int $userId,
        string $userRole = '',
        ?int $userUlpId = null,
        string $reason = ''
    ): array {
        if ($assetId <= 0) {
            return ['status' => 'error', 'code' => 'INVALID_ASSET', 'message' => 'Asset ID tidak valid.'];
        }
        if ($newConstructionTypeId <= 0) {
            return ['status' => 'error', 'code' => 'INVALID_CONSTRUCTION', 'message' => 'Construction Type ID tidak valid.'];
        }

        $builder = $this->db->table('assets')->where('id', $assetId);
        if ($this->db->fieldExists('deleted_at', 'assets')) {
            $builder->where('deleted_at IS NULL');
        }
        $asset = $builder->get()->getRowArray();
        if (!$asset) {
            return ['status' => 'error', 'code' => 'ASSET_NOT_FOUND', 'message' => 'Aset tidak ditemukan atau telah dihapus.'];
        }

        // Validate construction type exists
        $constructionExists = false;
        if ($this->db->tableExists('construction_types')) {
            $ct = $this->db->table('construction_types')->where('id', $newConstructionTypeId)->get()->getRowArray();
            if ($ct) $constructionExists = true;
        }
        if (!$constructionExists && $this->db->tableExists('canonical_construction_types')) {
            $cct = $this->db->table('canonical_construction_types')->where('id', $newConstructionTypeId)->get()->getRowArray();
            if ($cct) $constructionExists = true;
        }

        if (!$constructionExists) {
            return [
                'status'  => 'error',
                'code'    => 'CONSTRUCTION_NOT_FOUND',
                'message' => "Standar konstruksi target (#{$newConstructionTypeId}) tidak ditemukan di database."
            ];
        }

        // ULP Boundary Firewall
        $roleNorm = strtoupper(trim((string)$userRole));
        if ($roleNorm === 'ADMIN_ULP' && $userUlpId !== null && $userUlpId > 0) {
            $assetUlpId = (int)($asset['ulp_id'] ?? 0);
            if ($assetUlpId > 0 && $assetUlpId !== $userUlpId) {
                return [
                    'status'  => 'error',
                    'code'    => 'CROSS_ULP_REJECTED',
                    'message' => 'Akses ditolak: Aset berada di luar wilayah wewenang ULP Anda.'
                ];
            }
        }

        // Capture Pre-Update Fingerprint (Strict Invariant Protection)
        $before = [
            'id'                   => (int)$asset['id'],
            'section_id'           => $asset['section_id'] !== null ? (int)$asset['section_id'] : null,
            'construction_type_id' => $asset['construction_type_id'] !== null ? (int)$asset['construction_type_id'] : null,
            'latitude'             => (string)$asset['latitude'],
            'longitude'            => (string)$asset['longitude'],
            'kode_asset'           => (string)$asset['kode_asset'],
            'nama_asset'           => (string)($asset['nama_asset'] ?? ''),
            'penyulang_id'         => (int)($asset['penyulang_id'] ?? 0),
            'ulp_id'               => (int)($asset['ulp_id'] ?? 0),
        ];

        // Atomic Database Update
        $this->db->transBegin();
        try {
            $updatePayload = [
                'construction_type_id' => $newConstructionTypeId,
                'updated_at'           => date('Y-m-d H:i:s'),
            ];

            $this->db->table('assets')->where('id', $assetId)->update($updatePayload);

            // Post-Update Server-Authoritative Verification
            $after = $this->db->table('assets')->where('id', $assetId)->get()->getRowArray();
            if (!$after) {
                throw new \RuntimeException("Gagal memuat ulang aset pasca-update.");
            }

            if ((int)$after['construction_type_id'] !== $newConstructionTypeId) {
                throw new \RuntimeException("Integritas gagal: construction_type_id di database tidak sesuai target.");
            }

            // Invariant assertions: Unrequested fields MUST NOT change
            if ((string)$after['latitude'] !== $before['latitude'] ||
                (string)$after['longitude'] !== $before['longitude'] ||
                (string)$after['kode_asset'] !== $before['kode_asset'] ||
                (string)($after['nama_asset'] ?? '') !== $before['nama_asset'] ||
                (int)($after['penyulang_id'] ?? 0) !== $before['penyulang_id'] ||
                (int)($after['ulp_id'] ?? 0) !== $before['ulp_id'] ||
                (int)($after['section_id'] ?? 0) !== (int)($before['section_id'] ?? 0)
            ) {
                throw new \RuntimeException("Integritas gagal: Terdeteksi mutasi pada kolom terproteksi!");
            }

            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            return [
                'status'  => 'error',
                'code'    => 'TRANSACTION_ROLLBACK',
                'message' => 'Gagal menyimpan koreksi konstruksi: ' . $e->getMessage()
            ];
        }

        // Audit Trail Logging
        $auditReason = $reason ?: 'Koreksi Konstruksi Operator via GIS';
        if (function_exists('log_activity')) {
            log_activity(
                'OPERATOR_CORRECT_CONSTRUCTION',
                "Asset #{$assetId} ({$before['kode_asset']}) Construction Type changed: {$before['construction_type_id']} -> {$newConstructionTypeId}. User: #{$userId}. Reason: {$auditReason}"
            );
        }

        if ($this->db->tableExists('audit_logs')) {
            try {
                $this->db->table('audit_logs')->insert([
                    'user_id'    => $userId > 0 ? $userId : null,
                    'username'   => 'OPERATOR',
                    'role'       => $userRole ?: 'operator',
                    'aktivitas'  => 'OPERATOR_CORRECT_CONSTRUCTION',
                    'detail'     => "Asset #{$assetId} ({$before['kode_asset']}) Construction: {$before['construction_type_id']} -> {$newConstructionTypeId}. Reason: {$auditReason}",
                    'ip_address' => '127.0.0.1',
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            } catch (\Throwable $ae) {
                // Ignore optional audit errors
            }
        }

        try {
            if (class_exists('\App\Services\AssetHistoryService') && class_exists('\Config\AssetEvent')) {
                (new \App\Services\AssetHistoryService())->logEvent(
                    $assetId,
                    \Config\AssetEvent::UPDATED ?? 'UPDATED',
                    (string)$before['construction_type_id'],
                    (string)$newConstructionTypeId,
                    $before['kode_asset'],
                    "Koreksi Konstruksi oleh Operator: #{$before['construction_type_id']} -> #{$newConstructionTypeId}. {$auditReason}",
                    $userId
                );
            }
        } catch (\Throwable $ae) {
            // Ignore optional history logging errors
        }

        return [
            'status'           => 'success',
            'code'             => 'CONSTRUCTION_CORRECTED',
            'message'          => "Koreksi Standar Konstruksi berhasil disimpan ke database master (Construction ID: {$newConstructionTypeId}).",
            'asset_id'         => $assetId,
            'field'            => 'construction_type_id',
            'old_value'        => $before['construction_type_id'],
            'new_value'        => $newConstructionTypeId,
            'persisted'        => true,
            'updated_context'  => $this->getAssetContext($assetId, $userUlpId, $userRole),
        ];
    }
}
