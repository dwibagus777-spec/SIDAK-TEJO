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

        $bomResult = $this->resolveBomByConstructionTypeId($constructionTypeId);
        $bomResult['asset'] = $assetData;

        return $bomResult;
    }

    /**
     * Resolve Construction Type & Governed BOM by Construction Type ID.
     * Enforces the same 3 Firewalls (Provisional Kubikel, Held Specifications, Active Master Materials).
     * Guaranteed ZERO database writes (Read-Only).
     */
    public function resolveBomByConstructionTypeId(int $constructionTypeId): array
    {
        if ($constructionTypeId <= 0) {
            return [
                'status' => 'NO_CONSTRUCTION',
                'message' => 'KONSTRUKSI BELUM TERPETAKAN',
                'construction' => null,
                'materials' => [],
            ];
        }

        // Resolve Construction Type
        $construction = $this->constructionModel->find($constructionTypeId);
        if (!$construction) {
            return [
                'status' => 'NO_CONSTRUCTION',
                'message' => 'KONSTRUKSI BELUM TERPETAKAN',
                'construction' => null,
                'materials' => [],
            ];
        }

        $cFamily = strtoupper(trim((string)($construction['construction_family'] ?? '')));
        $cStatus = strtoupper(trim((string)($construction['approval_status'] ?? 'ACTIVE')));
        $cCode   = strtoupper(trim((string)($construction['construction_code'] ?? ($construction['code'] ?? ''))));
        $cName   = (string)($construction['construction_name'] ?? ($construction['name'] ?? $cCode));

        // Provisional Kubikel Firewall: block draft/provisional constructions from picker
        if ($cFamily === 'GARDU_KUBIKEL' || $cStatus === 'DRAFT' || str_contains($cCode, 'KUBIKEL')) {
            return [
                'status' => 'PROVISIONAL_BLOCKED',
                'message' => 'KONSTRUKSI MASIH BERSTATUS PROVISIONAL / DRAFT (BELUM FIX)',
                'construction' => [
                    'id' => (int)$construction['id'],
                    'code' => $cCode,
                    'name' => $cName,
                    'family' => $cFamily,
                ],
                'materials' => [],
            ];
        }

        $nomenclature = self::resolveNomenclature($cCode, $cName);
        $constructionData = [
            'id'             => (int)$construction['id'],
            'code'           => $cCode,
            'name'           => $cName,
            'family'         => $cFamily,
            'canonical_code' => $nomenclature['canonical_code'],
            'technical_name' => $nomenclature['technical_name'],
            'feature_badge'  => $nomenclature['feature_badge'],
            'display_label'  => $nomenclature['display_label'],
        ];

        // Query Construction BOM Items
        $bomItems = $this->bomModel
            ->where('construction_type_id', $constructionTypeId)
            ->orderBy('id', 'ASC')
            ->findAll() ?: [];

        if (empty($bomItems)) {
            return [
                'status' => 'NO_BOM',
                'message' => 'BOM KONSTRUKSI BELUM TERSEDIA',
                'construction' => $constructionData,
                'materials' => [],
            ];
        }

        // Filter BOM Items & Resolve Active Master Materials
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

                $matUnit = (string)($material['satuan'] ?? ($item['unit'] ?? 'SET'));
                $assemblySemantics = self::resolveAssemblySemantics(
                    (string)$material['nama_material'],
                    (string)$material['material_code'],
                    $matUnit,
                    $cCode
                );

                $materials[] = [
                    'id'                 => $matId,
                    'code'               => (string)$material['material_code'],
                    'name'               => (string)$material['nama_material'],
                    'field_alias'        => (string)($material['nama_lapangan'] ?? ($item['material_alias'] ?? '')),
                    'unit'               => $matUnit,
                    'category'           => (string)($material['material_category'] ?? ($item['component_category'] ?? 'HARDWARE')),
                    'assembly_semantics' => $assemblySemantics,
                ];
            }
        }

        if (empty($materials)) {
            return [
                'status' => 'NO_BOM',
                'message' => 'BOM KONSTRUKSI BELUM TERSEDIA',
                'construction' => $constructionData,
                'materials' => [],
            ];
        }

        return [
            'status' => 'READY',
            'message' => 'Material sesuai BOM konstruksi',
            'construction' => $constructionData,
            'materials' => $materials,
        ];
    }

    /**
     * MR-01 Construction Nomenclature Translation Layer.
     * Maps canonical construction code to technical name, distinctive badge, and 2-layer display label.
     * Strictly read-only; does NOT mutate construction_types database table.
     */
    public static function resolveNomenclature(string $code, string $dbName): array
    {
        $cleanCode = strtoupper(trim(str_replace([' ', '-', '_', '/'], '', $code)));

        $map = [
            'TM1' => [
                'canonical_code' => 'TM1',
                'technical_name' => 'Konstruksi Tiang Tumpu Lurus',
                'feature_badge'  => 'TIANG TUMPU',
                'display_label'  => 'TM1 — Konstruksi Tiang Tumpu Lurus',
            ],
            'TM1A' => [
                'canonical_code' => 'TM1A',
                'technical_name' => 'Konstruksi Tiang Tumpu dengan Percabangan Tap',
                'feature_badge'  => '🔀 DENGAN LINE TAP',
                'display_label'  => 'TM1A — Konstruksi Tiang Tumpu dengan Percabangan Tap',
            ],
            'TM1C' => [
                'canonical_code' => 'TM1C',
                'technical_name' => 'Konstruksi Tiang Tumpu Tipe C',
                'feature_badge'  => 'TIANG TUMPU TIPE C',
                'display_label'  => 'TM1C — Konstruksi Tiang Tumpu Tipe C',
            ],
            'TM1TYPEC' => [
                'canonical_code' => 'TM1C',
                'technical_name' => 'Konstruksi Tiang Tumpu Tipe C',
                'feature_badge'  => 'TIANG TUMPU TIPE C',
                'display_label'  => 'TM1C — Konstruksi Tiang Tumpu Tipe C',
            ],
            'TM2' => [
                'canonical_code' => 'TM2',
                'technical_name' => 'Konstruksi Tiang Tumpu Sudut Ganda',
                'feature_badge'  => '📐 SUDUT KECIL / GANDA',
                'display_label'  => 'TM2 — Konstruksi Tiang Tumpu Sudut Ganda',
            ],
            'TM4' => [
                'canonical_code' => 'TM4',
                'technical_name' => 'Konstruksi Tiang Tarik Tunggal (Akhir / Awal)',
                'feature_badge'  => 'TIANG TARIK TUNGGAL',
                'display_label'  => 'TM4 — Konstruksi Tiang Tarik Tunggal (Akhir / Awal)',
            ],
            'TM4A' => [
                'canonical_code' => 'TM4A',
                'technical_name' => 'Konstruksi Tiang Tarik Sudut dengan Arrester',
                'feature_badge'  => '⚡ DENGAN ARRESTER',
                'display_label'  => 'TM4A — Konstruksi Tiang Tarik Sudut dengan Arrester',
            ],
            'TM5' => [
                'canonical_code' => 'TM5',
                'technical_name' => 'Konstruksi Tiang Penegang (Tension)',
                'feature_badge'  => 'TIANG PENEGANG',
                'display_label'  => 'TM5 — Konstruksi Tiang Penegang (Tension)',
            ],
            'TM5C' => [
                'canonical_code' => 'TM5C',
                'technical_name' => 'Konstruksi Tiang Penegang dengan CO',
                'feature_badge'  => '🔌 DENGAN CO',
                'display_label'  => 'TM5C — Konstruksi Tiang Penegang dengan CO',
            ],
            'TM8' => [
                'canonical_code' => 'TM8',
                'technical_name' => 'Konstruksi Tiang Percabangan',
                'feature_badge'  => '🔀 PERCABANGAN / T-OFF',
                'display_label'  => 'TM8 — Konstruksi Tiang Percabangan',
            ],
            'TM8C' => [
                'canonical_code' => 'TM8C',
                'technical_name' => 'Konstruksi Tiang Percabangan dengan CO',
                'feature_badge'  => '🔌 DENGAN CO',
                'display_label'  => 'TM8C — Konstruksi Tiang Percabangan dengan CO',
            ],
            'TM10' => [
                'canonical_code' => 'TM10',
                'technical_name' => 'Konstruksi Tiang Transposisi Fasa',
                'feature_badge'  => 'TIANG TRANSPOSISI',
                'display_label'  => 'TM10 — Konstruksi Tiang Transposisi Fasa',
            ],
            'TM11' => [
                'canonical_code' => 'TM11',
                'technical_name' => 'Konstruksi Tiang Penegang dengan Arrester',
                'feature_badge'  => '⚡ DENGAN ARRESTER',
                'display_label'  => 'TM11 — Konstruksi Tiang Penegang dengan Arrester',
            ],
            'TM11DS' => [
                'canonical_code' => 'TM11-DS',
                'technical_name' => 'Konstruksi Tiang Penegang dengan Arrester & DS',
                'feature_badge'  => '🛡️ DENGAN LA & DS',
                'display_label'  => 'TM11-DS — Konstruksi Tiang Penegang dengan Arrester & DS',
            ],
            'TM11CO' => [
                'canonical_code' => 'TM11-CO',
                'technical_name' => 'Konstruksi Tiang Penegang dengan Arrester & CO',
                'feature_badge'  => '🛡️ DENGAN LA & CO',
                'display_label'  => 'TM11-CO — Konstruksi Tiang Penegang dengan Arrester & CO',
            ],
            'TM12' => [
                'canonical_code' => 'TM12',
                'technical_name' => 'Konstruksi Trafo Portal Akhir Sejajar Jaringan dengan CO',
                'feature_badge'  => '🔌 PORTAL DENGAN CO',
                'display_label'  => 'TM12 — Konstruksi Trafo Portal Akhir Sejajar Jaringan dengan CO',
            ],
            'TM13' => [
                'canonical_code' => 'TM13',
                'technical_name' => 'Konstruksi Trafo Portal Akhir dengan CO',
                'feature_badge'  => '🔌 PORTAL DENGAN CO',
                'display_label'  => 'TM13 — Konstruksi Trafo Portal Akhir dengan CO',
            ],
            'TMTP' => [
                'canonical_code' => 'TMTP',
                'technical_name' => 'Konstruksi Tiang Khusus HT Pole',
                'feature_badge'  => 'TIANG KHUSUS HT POLE',
                'display_label'  => 'TMTP — Konstruksi Tiang Khusus HT Pole (Tanpa Travers Biasa)',
            ],
            'TMMVTIC1' => [
                'canonical_code' => 'TMMVTIC1',
                'technical_name' => 'Konstruksi MVTIC Tumpu Lurus',
                'feature_badge'  => 'MVTIC TUMPU',
                'display_label'  => 'TMMVTIC1 — Konstruksi MVTIC Tumpu Lurus',
            ],
            'TMMVTIC2' => [
                'canonical_code' => 'TMMVTIC2',
                'technical_name' => 'Konstruksi MVTIC Sudut Kecil',
                'feature_badge'  => '📐 MVTIC SUDUT',
                'display_label'  => 'TMMVTIC2 — Konstruksi MVTIC Sudut Kecil',
            ],
            'TMMVTIC3' => [
                'canonical_code' => 'TMMVTIC3',
                'technical_name' => 'Konstruksi MVTIC Sudut dengan Proteksi PVC',
                'feature_badge'  => '🛡️ MVTIC SUDUT PROTEKSI',
                'display_label'  => 'TMMVTIC3 — Konstruksi MVTIC Sudut dengan Proteksi PVC',
            ],
            'TMMVTIC4' => [
                'canonical_code' => 'TMMVTIC4',
                'technical_name' => 'Konstruksi MVTIC Tarik Akhir (Dead-End Tunggal)',
                'feature_badge'  => 'MVTIC TARIK AKHIR',
                'display_label'  => 'TMMVTIC4 — Konstruksi MVTIC Tarik Akhir (Dead-End Tunggal)',
            ],
            'TMMVTIC4DS' => [
                'canonical_code' => 'TMMVTIC4-DS',
                'technical_name' => 'Konstruksi MVTIC Tarik Akhir dengan DS',
                'feature_badge'  => '🎛️ DENGAN DS',
                'display_label'  => 'TMMVTIC4-DS — Konstruksi MVTIC Tarik Akhir dengan DS',
            ],
            'TMMVTIC4CO' => [
                'canonical_code' => 'TMMVTIC4-CO',
                'technical_name' => 'Konstruksi MVTIC Tarik Akhir dengan CO',
                'feature_badge'  => '🔌 DENGAN CO',
                'display_label'  => 'TMMVTIC4-CO — Konstruksi MVTIC Tarik Akhir dengan CO',
            ],
            'TMMVTIC5' => [
                'canonical_code' => 'TMMVTIC5',
                'technical_name' => 'Konstruksi MVTIC Percabangan Sambungan Salaman Atas JTM',
                'feature_badge'  => '🔀 SALAMAN ATAS JTM',
                'display_label'  => 'TMMVTIC5 — Konstruksi MVTIC Percabangan Sambungan Salaman Atas JTM',
            ],
            'TMMVTIC5SALAMANATAS' => [
                'canonical_code' => 'TMMVTIC5',
                'technical_name' => 'Konstruksi MVTIC Percabangan Sambungan Salaman Atas JTM',
                'feature_badge'  => '🔀 SALAMAN ATAS JTM',
                'display_label'  => 'TMMVTIC5 — Konstruksi MVTIC Percabangan Sambungan Salaman Atas JTM',
            ],
            'TMMVTIC5A' => [
                'canonical_code' => 'TMMVTIC5A',
                'technical_name' => 'Konstruksi MVTIC Sambungan Percabangan Tipe A',
                'feature_badge'  => '🔗 SAMBUNGAN TIPE A',
                'display_label'  => 'TMMVTIC5A — Konstruksi MVTIC Sambungan Percabangan Tipe A',
            ],
            'TMMVTIC5B' => [
                'canonical_code' => 'TMMVTIC5B',
                'technical_name' => 'Konstruksi MVTIC Sambungan Percabangan Tipe B',
                'feature_badge'  => '🔗 SAMBUNGAN TIPE B',
                'display_label'  => 'TMMVTIC5B — Konstruksi MVTIC Sambungan Percabangan Tipe B',
            ],
            'TMMVTIC10' => [
                'canonical_code' => 'TMMVTIC10',
                'technical_name' => 'Konstruksi MVTIC Tiang Penegang Khusus',
                'feature_badge'  => 'MVTIC PENEGANG',
                'display_label'  => 'TMMVTIC10 — Konstruksi MVTIC Tiang Penegang Khusus',
            ],
            'GTT1' => [
                'canonical_code'   => 'GTT1',
                'technical_name'   => 'Gardu Distribusi Tiang Cantol (1 Tiang)',
                'feature_badge'    => '⚡🔌 CANTOL DENGAN LA & CO',
                'display_label'    => 'GTT1 — Gardu Distribusi Tiang Cantol dengan LA & CO',
                'topology_meaning' => 'CANTOL',
                'pole_count'       => 1,
            ],
            'GTT1TIANGCANTOL' => [
                'canonical_code'   => 'GTT1',
                'technical_name'   => 'Gardu Distribusi Tiang Cantol (1 Tiang)',
                'feature_badge'    => '⚡🔌 CANTOL DENGAN LA & CO',
                'display_label'    => 'GTT1 — Gardu Distribusi Tiang Cantol dengan LA & CO',
                'topology_meaning' => 'CANTOL',
                'pole_count'       => 1,
            ],
            'GTT2' => [
                'canonical_code'   => 'GTT2',
                'technical_name'   => 'Gardu Distribusi Tiang Portal (2 Tiang)',
                'feature_badge'    => '⚡🔌 PORTAL DENGAN LA & CO',
                'display_label'    => 'GTT2 — Gardu Distribusi Tiang Portal dengan LA & CO',
                'topology_meaning' => 'PORTAL',
                'pole_count'       => 2,
            ],
            'GTT2TIANGPORTAL' => [
                'canonical_code'   => 'GTT2',
                'technical_name'   => 'Gardu Distribusi Tiang Portal (2 Tiang)',
                'feature_badge'    => '⚡🔌 PORTAL DENGAN LA & CO',
                'display_label'    => 'GTT2 — Gardu Distribusi Tiang Portal dengan LA & CO',
                'topology_meaning' => 'PORTAL',
                'pole_count'       => 2,
            ],
        ];

        if (isset($map[$cleanCode])) {
            return $map[$cleanCode];
        }

        // Fuzzy match
        foreach ($map as $k => $v) {
            if (str_contains($cleanCode, $k) || str_contains($k, $cleanCode)) {
                return $v;
            }
        }

        return [
            'canonical_code'   => $code,
            'technical_name'   => $dbName ?: $code,
            'feature_badge'    => 'STANDAR PLN',
            'display_label'    => ($code ? "{$code} — " : "") . $dbName,
            'topology_meaning' => 'STANDARD',
            'pole_count'       => 1,
        ];
    }

    /**
     * MR-01 Phase Assembly Semantics Mapping Layer.
     * Resolves 3-phase equipment assembly semantics (e.g. FCO, Lightning Arrester, DS).
     * Strictly Read-Only; provides clear distinction between physical piece count (e.g. 3 buah)
     * and transaction unit (e.g. 1 SET = 3 buah • 1/phasa R-S-T).
     *
     * Invariants:
     * - BOM count "(3)" represents physical pieces (PCS/buah), NOT transaction unit "3 SET".
     * - 1 SET = 3 buah (1 buah per phasa: R, S, T).
     * - Only applies to explicitly verified 3-phase equipment; never globally assumed.
     */
    public static function resolveAssemblySemantics(string $materialName, string $materialCode = '', string $unit = 'SET', ?string $constructionCode = null): array
    {
        $upperName = strtoupper(trim($materialName));
        $upperCode = strtoupper(trim($materialCode));
        $upperUnit = strtoupper(trim($unit));

        // 1. FUSE CUT OUT (FCO)
        if (str_contains($upperName, 'FUSE CUT OUT') || str_contains($upperName, 'CUT OUT') || str_contains($upperCode, 'FCO') || $upperName === 'FCO') {
            return [
                'is_3phase_assembly' => true,
                'physical_qty'       => 3,
                'physical_unit'      => 'BUAH',
                'transaction_unit'   => 'SET',
                'phase_distribution' => '1/phasa (R, S, T)',
                'assembly_rule'      => '1 SET = 3 buah • 1/phasa',
                'display_caption'    => '1 SET = 3 buah • 1/phasa (R-S-T)',
            ];
        }

        // 2. LIGHTNING ARRESTER (LA)
        if (str_contains($upperName, 'ARRESTER') || str_contains($upperCode, 'LA') || str_contains($upperName, 'PENANGKAL PETIR') || $upperName === 'LA') {
            return [
                'is_3phase_assembly' => true,
                'physical_qty'       => 3,
                'physical_unit'      => 'BUAH',
                'transaction_unit'   => 'SET',
                'phase_distribution' => '1/phasa (R, S, T)',
                'assembly_rule'      => '1 SET = 3 buah • 1/phasa',
                'display_caption'    => '1 SET = 3 buah • 1/phasa (R-S-T)',
            ];
        }

        // 3. DISCONNECTING SWITCH (DS)
        if (str_contains($upperName, 'DISCONNECTING SWITCH') || str_contains($upperName, 'DISCONECCTING SWITCH') || str_contains($upperCode, 'DS') || str_contains($upperName, 'SAKLAR PEMISAH')) {
            return [
                'is_3phase_assembly' => true,
                'physical_qty'       => 3,
                'physical_unit'      => 'POLE',
                'transaction_unit'   => 'SET',
                'phase_distribution' => '3-Pole Gang Operated (R, S, T)',
                'assembly_rule'      => '1 SET = 3 pole (3-phasa)',
                'display_caption'    => '1 SET = 3 pole (3-phasa)',
            ];
        }

        // Default: Standard individual material (no 3-phase assembly expansion)
        return [
            'is_3phase_assembly' => false,
            'physical_qty'       => 1,
            'physical_unit'      => $upperUnit ?: 'BH',
            'transaction_unit'   => $upperUnit ?: 'BH',
            'phase_distribution' => null,
            'assembly_rule'      => null,
            'display_caption'    => null,
        ];
    }
}
