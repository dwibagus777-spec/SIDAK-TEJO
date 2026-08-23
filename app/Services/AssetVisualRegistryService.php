<?php

namespace App\Services;

/**
 * Centralized Asset Visual Identity & Network Symbol System (Wave 3 Phase PH-VIS-01)
 *
 * Responsibilities:
 * - Single source of truth for asset visual identities, SVG symbols, map markers, and condition overlays.
 * - Deterministic normalization and alias resolution for 10 PLN distribution network asset types + 1 default.
 * - Zero external CDN dependencies, 100% locally hosted SVG assets.
 */
class AssetVisualRegistryService
{
    private static array $svgCache = [];

    /**
     * Master Definitions for Network Asset Visual Symbols
     */
    public const SYMBOLS = [
        'LBS' => [
            'symbol_key'    => 'LBS',
            'label'         => 'Load Break Switch',
            'category'      => 'switching',
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
            'svg_file'      => 'co-branch.svg',
            'svg_path'      => '/assets/icons/network/co-branch.svg',
            'color'         => '#111827',
            'shape'         => 'vertical-branch-slash',
            'map_priority'  => 70,
            'marker_anchor' => [22, 22],
            'popup_anchor'  => [0, -22],
            'description'   => 'Pengaman percabangan / Fuse Cut Out cabang',
        ],
        'TIANG' => [
            'symbol_key'    => 'TIANG',
            'label'         => 'Tiang Distribusi',
            'category'      => 'structural',
            'svg_file'      => 'tiang.svg',
            'svg_path'      => '/assets/icons/network/tiang.svg',
            'color'         => '#111827',
            'shape'         => 'circle-donut',
            'map_priority'  => 40,
            'marker_anchor' => [22, 22],
            'popup_anchor'  => [0, -22],
            'description'   => 'Tiang beton / besi penumpu jaringan SUTM',
        ],
        'PMCB_REC' => [
            'symbol_key'    => 'PMCB_REC',
            'label'         => 'PMCB / Recloser',
            'category'      => 'recloser_protection',
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
            'svg_file'      => 'distribusi.svg',
            'svg_path'      => '/assets/icons/network/distribusi.svg',
            'color'         => '#111827',
            'shape'         => 'triangle-black',
            'map_priority'  => 80,
            'marker_anchor' => [22, 22],
            'popup_anchor'  => [0, -22],
            'description'   => 'Gardu trafo distribusi penurun tegangan 20kV ke 380V/220V',
        ],
        'DEFAULT' => [
            'symbol_key'    => 'DEFAULT',
            'label'         => 'Aset Jaringan',
            'category'      => 'general',
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
     * Resolve Visual Identity from Asset Attributes (Data-Driven with Aliases)
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

        // 1. Exact & Alias Mapping on Jenis Asset
        $matchedKey = match(true) {
            // LBS
            in_array($j, ['LBS', 'LOAD_BREAK_SWITCH', 'LBS_MOTOR', 'LBS_MOTORIZED', 'LBS_OTOMATIS'], true) => 'LBS',
            
            // GI
            in_array($j, ['GI', 'GARDU_INDUK', 'SUBSTATION', 'BAY_TRAFO'], true) => 'GI',
            
            // LBSM
            in_array($j, ['LBSM', 'LBS_MANUAL', 'LOAD_BREAK_SWITCH_MANUAL', 'PMS', 'PEMISAH', 'SEKSI'], true) => 'LBSM',
            
            // CO_BRANCH
            in_array($j, ['CO_BRANCH', 'CUT_OUT_BRANCH', 'FCO', 'FUSE_CUT_OUT', 'CO', 'PERCABANGAN_CO'], true) => 'CO_BRANCH',
            
            // TIANG
            in_array($j, ['TIANG', 'POLE', 'TIANG_BETON', 'TIANG_BESI', 'TIANG_SUTM'], true) => 'TIANG',
            
            // PMCB / RECLOSER
            in_array($j, ['PMCB_REC', 'PMCB', 'RECLOSER', 'REC', 'ACR', 'AUTO_RECLOSER', 'PMT', 'PEMUTUS'], true) => 'PMCB_REC',
            
            // I3
            in_array($j, ['I3', 'INDIKATOR_3', 'FAULT_INDICATOR_3', 'FPI_3', 'FPI_3PHASE'], true) => 'I3',
            
            // GH
            in_array($j, ['GH', 'GARDU_HUBUNG', 'SWITCHING_STATION', 'KUBIKEL_GH'], true) => 'GH',
            
            // I2
            in_array($j, ['I2', 'INDIKATOR_2', 'FAULT_INDICATOR_2', 'FPI_2', 'FPI_2PHASE'], true) => 'I2',
            
            // DISTRIBUSI / TRAFO
            in_array($j, ['DISTRIBUSI', 'TRAFO', 'TRAFO_DISTRIBUSI', 'GARDU', 'GARDU_DISTRIBUSI', 'GTT', 'GTM', 'TRANSFORMER'], true) => 'DISTRIBUSI',
            
            default => null,
        };

        // 2. Fallback to Construction Type inspection if not resolved
        if ($matchedKey === null && !empty($c)) {
            $matchedKey = match(true) {
                str_contains($c, 'RECLOSER') || str_contains($c, 'PMCB') || str_contains($c, 'PMT') => 'PMCB_REC',
                str_contains($c, 'LBSM') || str_contains($c, 'PMS') || str_contains($c, 'MANUAL') => 'LBSM',
                str_contains($c, 'LBS') => 'LBS',
                str_contains($c, 'CO') || str_contains($c, 'FUSE') || str_contains($c, 'BRANCH') => 'CO_BRANCH',
                str_contains($c, 'GARDU_INDUK') || str_contains($c, 'GI') => 'GI',
                str_contains($c, 'GARDU_HUBUNG') || str_contains($c, 'GH') => 'GH',
                str_contains($c, 'TRAFO') || str_contains($c, 'GTT') || str_contains($c, 'DISTRIBUSI') => 'DISTRIBUSI',
                str_contains($c, 'TIANG') || str_contains($c, 'POLE') => 'TIANG',
                str_contains($c, 'FPI_3') || str_contains($c, 'I3') => 'I3',
                str_contains($c, 'FPI_2') || str_contains($c, 'I2') => 'I2',
                default => null,
            };
        }

        // 3. Fallback to Asset Code Prefix inspection
        if ($matchedKey === null && !empty($k)) {
            $matchedKey = match(true) {
                str_starts_with($k, 'LBS-') || str_starts_with($k, 'LBSM-') => str_starts_with($k, 'LBSM-') ? 'LBSM' : 'LBS',
                str_starts_with($k, 'GI-') => 'GI',
                str_starts_with($k, 'GH-') => 'GH',
                str_starts_with($k, 'REC-') || str_starts_with($k, 'PMCB-') => 'PMCB_REC',
                str_starts_with($k, 'CO-') => 'CO_BRANCH',
                str_starts_with($k, 'TG-') || str_starts_with($k, 'T-') => 'TIANG',
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

        // Determine effective status level
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
        $keys = ['LBS', 'GI', 'LBSM', 'CO_BRANCH', 'TIANG', 'PMCB_REC', 'I3', 'GH', 'I2', 'DISTRIBUSI', 'DEFAULT'];
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
