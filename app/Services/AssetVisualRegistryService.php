<?php

namespace App\Services;

/**
 * Centralized Asset Visual Identity & Network Symbol System (Wave 3 Phase PH-VIS-01 Hotfix)
 *
 * Responsibilities:
 * - Single source of truth for asset visual identities, SVG symbols, map markers, and condition overlays.
 * - Family-based visual resolution: Preserves canonical master silhouette (TM-1 ring) across all TM construction variants (TM-5, TM-8, TM-10, TM-11).
 * - Zero external CDN dependencies, 100% locally hosted SVG assets.
 */
class AssetVisualRegistryService
{
    private static array $svgCache = [];

    /**
     * Master Definitions for Network Asset Visual Symbols
     */
    public const SYMBOLS = [
        // ==========================================================
        // 1. TM CONSTRUCTION FAMILY (Canonical Donut Ring Silhouette)
        // ==========================================================
        'TM_1' => [
            'symbol_key'    => 'TM_1',
            'label'         => 'Konstruksi TM-1 (Tiang Tumpu)',
            'category'      => 'structural',
            'family'        => 'TM',
            'svg_file'      => 'tm-1.svg',
            'svg_path'      => '/assets/icons/network/tm-1.svg',
            'color'         => '#111827',
            'shape'         => 'circle-donut',
            'map_priority'  => 40,
            'marker_anchor' => [22, 22],
            'popup_anchor'  => [0, -22],
            'description'   => 'Tiang tumpu garis lurus penumpu konduktor SUTM 20kV (Master Shape)',
        ],
        'TM_5' => [
            'symbol_key'    => 'TM_5',
            'label'         => 'Konstruksi TM-5 (Tiang Sudut)',
            'category'      => 'structural',
            'family'        => 'TM',
            'svg_file'      => 'tm-5.svg',
            'svg_path'      => '/assets/icons/network/tm-5.svg',
            'color'         => '#111827',
            'shape'         => 'circle-donut-angle',
            'map_priority'  => 45,
            'marker_anchor' => [22, 22],
            'popup_anchor'  => [0, -22],
            'description'   => 'Tiang sudut kecil / belokan rute SUTM 20kV',
        ],
        'TM_8' => [
            'symbol_key'    => 'TM_8',
            'label'         => 'Konstruksi TM-8 (Gardu Tiang Portal)',
            'category'      => 'structural',
            'family'        => 'TM',
            'svg_file'      => 'tm-8.svg',
            'svg_path'      => '/assets/icons/network/tm-8.svg',
            'color'         => '#111827',
            'shape'         => 'circle-donut-portal',
            'map_priority'  => 82,
            'marker_anchor' => [22, 22],
            'popup_anchor'  => [0, -22],
            'description'   => 'Tiang ganda portal penumpu trafo gardu distribusi 20kV',
        ],
        'TM_10' => [
            'symbol_key'    => 'TM_10',
            'label'         => 'Konstruksi TM-10 (Tiang Akhir)',
            'category'      => 'structural',
            'family'        => 'TM',
            'svg_file'      => 'tm-10.svg',
            'svg_path'      => '/assets/icons/network/tm-10.svg',
            'color'         => '#111827',
            'shape'         => 'circle-donut-deadend',
            'map_priority'  => 48,
            'marker_anchor' => [22, 22],
            'popup_anchor'  => [0, -22],
            'description'   => 'Tiang penegang akhir / terminasi penyulang SUTM 20kV',
        ],
        'TM_11' => [
            'symbol_key'    => 'TM_11',
            'label'         => 'Konstruksi TM-11 (Tiang Percabangan)',
            'category'      => 'structural',
            'family'        => 'TM',
            'svg_file'      => 'tm-11.svg',
            'svg_path'      => '/assets/icons/network/tm-11.svg',
            'color'         => '#111827',
            'shape'         => 'circle-donut-branch',
            'map_priority'  => 55,
            'marker_anchor' => [22, 22],
            'popup_anchor'  => [0, -22],
            'description'   => 'Tiang percabangan 3 arah (T-Off) penyulang SUTM 20kV',
        ],
        'TIANG' => [
            'symbol_key'    => 'TIANG',
            'label'         => 'Tiang Distribusi (TM)',
            'category'      => 'structural',
            'family'        => 'TM',
            'svg_file'      => 'tm-1.svg',
            'svg_path'      => '/assets/icons/network/tm-1.svg',
            'color'         => '#111827',
            'shape'         => 'circle-donut',
            'map_priority'  => 40,
            'marker_anchor' => [22, 22],
            'popup_anchor'  => [0, -22],
            'description'   => 'Tiang beton / besi penumpu jaringan SUTM 20kV',
        ],

        // ==========================================================
        // 2. NETWORK EQUIPMENT & SUBSTATIONS (Operator Reference)
        // ==========================================================
        'LBS' => [
            'symbol_key'    => 'LBS',
            'label'         => 'Load Break Switch',
            'category'      => 'switching',
            'family'        => 'SWITCH',
            'svg_file'      => 'lbs.svg',
            'svg_path'      => '/assets/icons/network/lbs.svg',
            'color'         => '#111827',
            'shape'         => 'circle-quadrants',
            'map_priority'  => 85,
            'marker_anchor' => [22, 22],
            'popup_anchor'  => [0, -22],
            'description'   => 'Saklar pemutus beban bertegangan (Motorized / Telecontrolled)',
        ],
        'GI' => [
            'symbol_key'    => 'GI',
            'label'         => 'Gardu Induk',
            'category'      => 'substation',
            'family'        => 'SUBSTATION',
            'svg_file'      => 'gardu-induk.svg',
            'svg_path'      => '/assets/icons/network/gardu-induk.svg',
            'color'         => '#dc2626',
            'shape'         => 'triangle-lightning',
            'map_priority'  => 100,
            'marker_anchor' => [22, 22],
            'popup_anchor'  => [0, -22],
            'description'   => 'Titik pasok hulu transmisi ke distribusi 20kV',
        ],
        'LBSM' => [
            'symbol_key'    => 'LBSM',
            'label'         => 'LBS Manual / PMS',
            'category'      => 'switching_manual',
            'family'        => 'SWITCH',
            'svg_file'      => 'lbsm.svg',
            'svg_path'      => '/assets/icons/network/lbsm.svg',
            'color'         => '#111827',
            'shape'         => 'square-bowtie',
            'map_priority'  => 75,
            'marker_anchor' => [22, 22],
            'popup_anchor'  => [0, -22],
            'description'   => 'Saklar pemutus beban manual / pemisah seksi',
        ],
        'CO_BRANCH' => [
            'symbol_key'    => 'CO_BRANCH',
            'label'         => 'Cut Out Branch',
            'category'      => 'protection_branch',
            'family'        => 'PROTECTION',
            'svg_file'      => 'co-branch.svg',
            'svg_path'      => '/assets/icons/network/co-branch.svg',
            'color'         => '#111827',
            'shape'         => 'vertical-branch-slash',
            'map_priority'  => 70,
            'marker_anchor' => [22, 22],
            'popup_anchor'  => [0, -22],
            'description'   => 'Pengaman percabangan / Fuse Cut Out cabang',
        ],
        'PMCB_REC' => [
            'symbol_key'    => 'PMCB_REC',
            'label'         => 'PMCB / Recloser',
            'category'      => 'recloser_protection',
            'family'        => 'PROTECTION',
            'svg_file'      => 'pmcb-recloser.svg',
            'svg_path'      => '/assets/icons/network/pmcb-recloser.svg',
            'color'         => '#dc2626',
            'shape'         => 'square-bowtie-arrows',
            'map_priority'  => 95,
            'marker_anchor' => [22, 22],
            'popup_anchor'  => [0, -22],
            'description'   => 'Pemutus balik otomatis / Recloser proteksi penyulang',
        ],
        'I3' => [
            'symbol_key'    => 'I3',
            'label'         => 'Indikator 3 (FPI 3-Phase)',
            'category'      => 'indicator',
            'family'        => 'INDICATOR',
            'svg_file'      => 'indicator-3.svg',
            'svg_path'      => '/assets/icons/network/indicator-3.svg',
            'color'         => '#2563eb',
            'shape'         => 'square-blue-red-circle',
            'map_priority'  => 65,
            'marker_anchor' => [22, 22],
            'popup_anchor'  => [0, -22],
            'description'   => 'Fault Passage Indicator 3-Phasa',
        ],
        'GH' => [
            'symbol_key'    => 'GH',
            'label'         => 'Gardu Hubung',
            'category'      => 'switching_station',
            'family'        => 'SUBSTATION',
            'svg_file'      => 'gardu-hubung.svg',
            'svg_path'      => '/assets/icons/network/gardu-hubung.svg',
            'color'         => '#ea580c',
            'shape'         => 'box-orange-chain',
            'map_priority'  => 90,
            'marker_anchor' => [22, 22],
            'popup_anchor'  => [0, -22],
            'description'   => 'Pusat manuver dan hub distribusi antar penyulang',
        ],
        'I2' => [
            'symbol_key'    => 'I2',
            'label'         => 'Indikator 2 (FPI 2-Phase)',
            'category'      => 'indicator',
            'family'        => 'INDICATOR',
            'svg_file'      => 'indicator-2.svg',
            'svg_path'      => '/assets/icons/network/indicator-2.svg',
            'color'         => '#3b82f6',
            'shape'         => 'triangle-blue',
            'map_priority'  => 60,
            'marker_anchor' => [22, 22],
            'popup_anchor'  => [0, -22],
            'description'   => 'Fault Passage Indicator 2-Phasa',
        ],
        'DISTRIBUSI' => [
            'symbol_key'    => 'DISTRIBUSI',
            'label'         => 'Trafo Distribusi',
            'category'      => 'transformer',
            'family'        => 'TRANSFORMER',
            'svg_file'      => 'distribusi.svg',
            'svg_path'      => '/assets/icons/network/distribusi.svg',
            'color'         => '#111827',
            'shape'         => 'triangle-black',
            'map_priority'  => 80,
            'marker_anchor' => [22, 22],
            'popup_anchor'  => [0, -22],
            'description'   => 'Trafo distribusi penurun tegangan 20kV ke 380V/220V',
        ],
        'DEFAULT' => [
            'symbol_key'    => 'DEFAULT',
            'label'         => 'Aset Jaringan',
            'category'      => 'general',
            'family'        => 'GENERAL',
            'svg_file'      => 'generic-network-asset.svg',
            'svg_path'      => '/assets/icons/network/generic-network-asset.svg',
            'color'         => '#475569',
            'shape'         => 'hexagon-node',
            'map_priority'  => 50,
            'marker_anchor' => [22, 22],
            'popup_anchor'  => [0, -22],
            'description'   => 'Peralatan / konstruksi jaringan distribusi 20kV',
        ],
    ];

    /**
     * Resolve Visual Identity from Asset Attributes (Family-Based Visual Resolution)
     *
     * @param string|null $jenisAsset
     * @param string|null $constructionType
     * @param string|null $kode
     * @return array<string, mixed>
     */
    public function resolveVisual(?string $jenisAsset, ?string $constructionType = null, ?string $kode = null): array
    {
        $j = strtoupper(trim(str_replace(['-', ' '], '_', (string)$jenisAsset)));
        $c = strtoupper(trim(str_replace(['-', ' '], '_', (string)$constructionType)));
        $k = strtoupper(trim((string)$kode));

        // -------------------------------------------------------------
        // STEP 1: TM CONSTRUCTION FAMILY RESOLUTION (Highest Priority)
        // Preserves Canonical Donut Ring Shape with Internal Accents
        // -------------------------------------------------------------
        $tmMatched = match(true) {
            // TM-8 / Portal Double Pole
            str_contains($c, 'TM_8') || str_contains($c, 'TM8') || str_contains($c, 'PORTAL') || str_contains($j, 'TM_8') || str_contains($j, 'TM8') || str_contains($k, 'TM8') || str_contains($k, 'TM-8') => 'TM_8',

            // TM-5 / Pole Angle
            str_contains($c, 'TM_5') || str_contains($c, 'TM5') || str_contains($c, 'SUDUT') || str_contains($j, 'TM_5') || str_contains($j, 'TM5') => 'TM_5',

            // TM-10 / Dead-End Pole
            str_contains($c, 'TM_10') || str_contains($c, 'TM10') || str_contains($c, 'AKHIR') || str_contains($c, 'DEAD_END') || str_contains($j, 'TM_10') || str_contains($j, 'TM10') => 'TM_10',

            // TM-11 / Branch Pole (T-Off)
            str_contains($c, 'TM_11') || str_contains($c, 'TM11') || str_contains($c, 'PERCABANGAN') || str_contains($j, 'TM_11') || str_contains($j, 'TM11') => 'TM_11',

            // TM-1 / Tangent Pole Standard
            str_contains($c, 'TM_1') || str_contains($c, 'TM1') || str_contains($c, 'TUMPU') || str_contains($j, 'TM_1') || str_contains($j, 'TM1') => 'TM_1',

            default => null,
        };

        if ($tmMatched !== null) {
            $spec = self::SYMBOLS[$tmMatched];
            return array_merge($spec, [
                'fallback'        => false,
                'family'          => 'TM',
                'base_silhouette' => 'TM_1',
            ]);
        }

        // -------------------------------------------------------------
        // STEP 2: PRIMARY ASSET CATEGORY RESOLUTION (Switching/Substation/Protection/Indicators)
        // -------------------------------------------------------------
        $matchedKey = match(true) {
            // LBS
            in_array($j, ['LBS', 'LOAD_BREAK_SWITCH', 'LBS_MOTOR', 'LBS_MOTORIZED', 'LBS_OTOMATIS'], true) || str_contains($c, 'LBS_MOTOR') => 'LBS',
            
            // GI
            in_array($j, ['GI', 'GARDU_INDUK', 'SUBSTATION', 'BAY_TRAFO'], true) || str_contains($c, 'GARDU_INDUK') || str_contains($c, 'GI') => 'GI',
            
            // LBSM
            in_array($j, ['LBSM', 'LBS_MANUAL', 'LOAD_BREAK_SWITCH_MANUAL', 'PMS', 'PEMISAH', 'SEKSI'], true) || str_contains($c, 'PMS') || str_contains($c, 'LBSM') => 'LBSM',
            
            // CO_BRANCH
            in_array($j, ['CO_BRANCH', 'CUT_OUT_BRANCH', 'FCO', 'FUSE_CUT_OUT', 'CO', 'PERCABANGAN_CO'], true) || str_contains($c, 'FCO') || str_contains($c, 'CUT_OUT') => 'CO_BRANCH',
            
            // PMCB / RECLOSER
            in_array($j, ['PMCB_REC', 'PMCB', 'RECLOSER', 'REC', 'ACR', 'AUTO_RECLOSER', 'PMT', 'PEMUTUS'], true) || str_contains($c, 'RECLOSER') || str_contains($c, 'PMCB') || str_contains($c, 'PMT') => 'PMCB_REC',
            
            // I3
            in_array($j, ['I3', 'INDIKATOR_3', 'FAULT_INDICATOR_3', 'FPI_3', 'FPI_3PHASE'], true) || str_contains($c, 'FPI_3') => 'I3',
            
            // GH
            in_array($j, ['GH', 'GARDU_HUBUNG', 'SWITCHING_STATION', 'KUBIKEL_GH'], true) || str_contains($c, 'GARDU_HUBUNG') || str_contains($c, 'GH') => 'GH',
            
            // I2
            in_array($j, ['I2', 'INDIKATOR_2', 'FAULT_INDICATOR_2', 'FPI_2', 'FPI_2PHASE'], true) || str_contains($c, 'FPI_2') => 'I2',

            // TIANG / STRUCTURAL POLES
            in_array($j, ['TIANG', 'POLE', 'TIANG_BETON', 'TIANG_BESI', 'TIANG_SUTM', 'JTM'], true) || str_contains($c, 'TIANG') || str_contains($c, 'POLE') => 'TM_1',
            
            // DISTRIBUSI / TRAFO
            in_array($j, ['DISTRIBUSI', 'TRAFO', 'TRAFO_DISTRIBUSI', 'GARDU', 'GARDU_DISTRIBUSI', 'GTT', 'GTM', 'TRANSFORMER'], true) || str_contains($c, 'TRAFO') || str_contains($c, 'GTT') => 'DISTRIBUSI',
            
            default => null,
        };

        // -------------------------------------------------------------
        // STEP 3: ASSET CODE PREFIX INSPECTION (Fallback)
        // -------------------------------------------------------------
        if ($matchedKey === null && !empty($k)) {
            $matchedKey = match(true) {
                str_starts_with($k, 'LBS-') || str_starts_with($k, 'LBSM-') => str_starts_with($k, 'LBSM-') ? 'LBSM' : 'LBS',
                str_starts_with($k, 'GI-') => 'GI',
                str_starts_with($k, 'GH-') => 'GH',
                str_starts_with($k, 'REC-') || str_starts_with($k, 'PMCB-') => 'PMCB_REC',
                str_starts_with($k, 'CO-') => 'CO_BRANCH',
                str_starts_with($k, 'TG-') || str_starts_with($k, 'T-') => 'TM_1',
                str_starts_with($k, 'SDJ-') || str_starts_with($k, 'GD-') || str_starts_with($k, 'TR-') => 'DISTRIBUSI',
                default => null,
            };
        }

        $symbolKey = $matchedKey ?? 'DEFAULT';
        $spec = self::SYMBOLS[$symbolKey] ?? self::SYMBOLS['DEFAULT'];

        return array_merge($spec, [
            'fallback' => ($symbolKey === 'DEFAULT'),
        ]);
    }

    /**
     * Get Condition and Severity Overlay Meta
     *
     * @param string|null $condition (GOOD, FAIR, POOR, CRITICAL, EMERGENCY, OUT_OF_SERVICE)
     * @param string|null $severity (NORMAL, LOW, MEDIUM, HIGH, EMERGENCY)
     * @return array<string, mixed>
     */
    public function getConditionOverlay(?string $condition, ?string $severity = null): array
    {
        $cond = strtoupper(trim((string)$condition));
        $sev  = strtoupper(trim((string)$severity));

        if ($sev === 'EMERGENCY' || $cond === 'EMERGENCY') {
            return [
                'condition'    => 'EMERGENCY',
                'severity'     => 'EMERGENCY',
                'ring_class'   => 'asset-ring-emergency',
                'badge_class'  => 'bg-danger text-white pulse-badge',
                'border_color' => '#dc2626',
                'pulse'        => true,
                'label'        => 'EMERGENCY',
            ];
        }

        if ($cond === 'CRITICAL' || $sev === 'HIGH') {
            return [
                'condition'    => 'CRITICAL',
                'severity'     => 'HIGH',
                'ring_class'   => 'asset-ring-critical',
                'badge_class'  => 'bg-danger text-white',
                'border_color' => '#ef4444',
                'pulse'        => true,
                'label'        => 'CRITICAL',
            ];
        }

        if ($cond === 'POOR' || $sev === 'MEDIUM') {
            return [
                'condition'    => 'POOR',
                'severity'     => 'MEDIUM',
                'ring_class'   => 'asset-ring-poor',
                'badge_class'  => 'bg-warning text-dark',
                'border_color' => '#f59e0b',
                'pulse'        => false,
                'label'        => 'POOR',
            ];
        }

        if ($cond === 'FAIR' || $sev === 'LOW') {
            return [
                'condition'    => 'FAIR',
                'severity'     => 'LOW',
                'ring_class'   => 'asset-ring-fair',
                'badge_class'  => 'bg-info text-dark',
                'border_color' => '#0ea5e9',
                'pulse'        => false,
                'label'        => 'FAIR',
            ];
        }

        if ($cond === 'OUT_OF_SERVICE') {
            return [
                'condition'    => 'OUT_OF_SERVICE',
                'severity'     => 'INACTIVE',
                'ring_class'   => 'asset-ring-inactive',
                'badge_class'  => 'bg-secondary text-white',
                'border_color' => '#64748b',
                'pulse'        => false,
                'label'        => 'OUT OF SERVICE',
            ];
        }

        // Default: GOOD
        return [
            'condition'    => 'GOOD',
            'severity'     => 'NORMAL',
            'ring_class'   => 'asset-ring-good',
            'badge_class'  => 'bg-success text-white',
            'border_color' => '#10b981',
            'pulse'        => false,
            'label'        => 'GOOD',
        ];
    }

    /**
     * Get Ordered Legend Items for GIS Floating Panel
     *
     * @return array<int, array<string, mixed>>
     */
    public function getLegendItems(): array
    {
        $keys = [
            'TM_1', 'TM_5', 'TM_8', 'TM_10', 'TM_11',
            'LBS', 'GI', 'LBSM', 'CO_BRANCH', 'PMCB_REC',
            'I3', 'GH', 'I2', 'DISTRIBUSI'
        ];
        $items = [];

        foreach ($keys as $key) {
            if (isset(self::SYMBOLS[$key])) {
                $items[] = self::SYMBOLS[$key];
            }
        }

        return $items;
    }

    /**
     * Get Local SVG File Content (Cached)
     *
     * @param string $symbolKey
     * @return string
     */
    public function getSvgContent(string $symbolKey): string
    {
        $spec = self::SYMBOLS[$symbolKey] ?? self::SYMBOLS['DEFAULT'];
        $filename = $spec['svg_file'];

        if (isset(self::$svgCache[$filename])) {
            return self::$svgCache[$filename];
        }

        $filePath = FCPATH . 'assets/icons/network/' . $filename;
        if (is_file($filePath)) {
            $content = (string)file_get_contents($filePath);
            self::$svgCache[$filename] = $content;
            return $content;
        }

        return '';
    }

    /**
     * Get Web Asset Path for an Asset Symbol
     *
     * @param string $symbolKey
     * @return string
     */
    public function getPublicAssetPath(string $symbolKey): string
    {
        $spec = self::SYMBOLS[$symbolKey] ?? self::SYMBOLS['DEFAULT'];
        return base_url($spec['svg_path']);
    }
}
