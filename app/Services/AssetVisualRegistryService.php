<?php

namespace App\Services;

/**
 * Centralized Asset Visual Identity & Flat 2D Vector Symbol System (Wave 3 Phase PH-VIS-02)
 *
 * Responsibilities:
 * - Single source of truth for flat 2D SVG vector symbols (zero background cards, zero 3D extrusion).
 * - Family-based visual resolution: Canonical master silhouette (TM-1 ring) with clean internal variations.
 * - Transline continuity behavior and condition halo mapping.
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
            'symbol_key'         => 'TM_1',
            'label'              => 'Konstruksi TM-1 (Tiang Tumpu)',
            'category'           => 'structural',
            'family'             => 'TM',
            'visual_family'      => 'NETWORK_STRUCTURE',
            'transline_behavior' => 'INLINE_CONTINUATION',
            'svg_file'           => 'tm-1.svg',
            'svg_path'           => '/assets/icons/network/tm-1.svg',
            'png_file'           => 'tm1.png',
            'png_path'           => '/assets/gis/icons/tm1.png',
            'color'              => '#111827',
            'shape'              => 'circle-donut',
            'map_priority'       => 40,
            'marker_anchor'      => [22, 22],
            'popup_anchor'       => [0, -22],
            'description'        => 'Tiang tumpu garis lurus penumpu konduktor SUTM 20kV (Master Shape)',
        ],
        'TM_2' => [
            'symbol_key'         => 'TM_2',
            'label'              => 'Konstruksi TM-2 (Tiang Penegang Tunggal)',
            'category'           => 'structural',
            'family'             => 'TM',
            'visual_family'      => 'NETWORK_STRUCTURE',
            'transline_behavior' => 'INLINE_TENSION',
            'svg_file'           => 'tm-1.svg',
            'svg_path'           => '/assets/icons/network/tm-1.svg',
            'png_file'           => 'tm2.png',
            'png_path'           => '/assets/gis/icons/tm2.png',
            'color'              => '#111827',
            'shape'              => 'circle-donut-tension',
            'map_priority'       => 42,
            'marker_anchor'      => [22, 22],
            'popup_anchor'       => [0, -22],
            'description'        => 'Tiang penegang tunggal (tension pole) SUTM 20kV',
        ],
        'TM_4' => [
            'symbol_key'         => 'TM_4',
            'label'              => 'Konstruksi TM-4 (Tiang Penegang Ganda)',
            'category'           => 'structural',
            'family'             => 'TM',
            'visual_family'      => 'NETWORK_STRUCTURE',
            'transline_behavior' => 'INLINE_DOUBLE_TENSION',
            'svg_file'           => 'tm-1.svg',
            'svg_path'           => '/assets/icons/network/tm-1.svg',
            'png_file'           => 'tm4.png',
            'png_path'           => '/assets/gis/icons/tm4.png',
            'color'              => '#111827',
            'shape'              => 'circle-donut-double-tension',
            'map_priority'       => 44,
            'marker_anchor'      => [22, 22],
            'popup_anchor'       => [0, -22],
            'description'        => 'Tiang penegang ganda (double tension pole) SUTM 20kV',
        ],
        'TM_5' => [
            'symbol_key'         => 'TM_5',
            'label'              => 'Konstruksi TM-5 (Tiang Sudut)',
            'category'           => 'structural',
            'family'             => 'TM',
            'visual_family'      => 'NETWORK_STRUCTURE',
            'transline_behavior' => 'INLINE_ANGLE',
            'svg_file'           => 'tm-5.svg',
            'svg_path'           => '/assets/icons/network/tm-5.svg',
            'png_file'           => 'tm5.png',
            'png_path'           => '/assets/gis/icons/tm5.png',
            'color'              => '#111827',
            'shape'              => 'circle-donut-angle',
            'map_priority'       => 45,
            'marker_anchor'      => [22, 22],
            'popup_anchor'       => [0, -22],
            'description'        => 'Tiang sudut kecil / belokan rute SUTM 20kV',
        ],
        'TM_8' => [
            'symbol_key'         => 'TM_8',
            'label'              => 'Konstruksi TM-8 (Gardu Tiang Portal)',
            'category'           => 'structural',
            'family'             => 'TM',
            'visual_family'      => 'NETWORK_STRUCTURE',
            'transline_behavior' => 'INLINE_PORTAL',
            'svg_file'           => 'tm-8.svg',
            'svg_path'           => '/assets/icons/network/tm-8.svg',
            'png_file'           => 'tm8.png',
            'png_path'           => '/assets/gis/icons/tm8.png',
            'color'              => '#111827',
            'shape'              => 'circle-donut-portal',
            'map_priority'       => 82,
            'marker_anchor'      => [22, 22],
            'popup_anchor'       => [0, -22],
            'description'        => 'Tiang ganda portal penumpu trafo gardu distribusi 20kV',
        ],
        'TM_10' => [
            'symbol_key'         => 'TM_10',
            'label'              => 'Konstruksi TM-10 (Tiang Akhir)',
            'category'           => 'structural',
            'family'             => 'TM',
            'visual_family'      => 'NETWORK_STRUCTURE',
            'transline_behavior' => 'TERMINAL_DEAD_END',
            'svg_file'           => 'tm-10.svg',
            'svg_path'           => '/assets/icons/network/tm-10.svg',
            'png_file'           => 'tm10.png',
            'png_path'           => '/assets/gis/icons/tm10.png',
            'color'              => '#111827',
            'shape'              => 'circle-donut-deadend',
            'map_priority'       => 48,
            'marker_anchor'      => [22, 22],
            'popup_anchor'       => [0, -22],
            'description'        => 'Tiang penegang akhir / terminasi penyulang SUTM 20kV',
        ],
        'TM_11' => [
            'symbol_key'         => 'TM_11',
            'label'              => 'Konstruksi TM-11 (Tiang Percabangan)',
            'category'           => 'structural',
            'family'             => 'TM',
            'visual_family'      => 'NETWORK_STRUCTURE',
            'transline_behavior' => 'BRANCH_T_OFF',
            'svg_file'           => 'tm-11.svg',
            'svg_path'           => '/assets/icons/network/tm-11.svg',
            'png_file'           => 'tm11.png',
            'png_path'           => '/assets/gis/icons/tm11.png',
            'color'              => '#111827',
            'shape'              => 'circle-donut-branch',
            'map_priority'       => 55,
            'marker_anchor'      => [22, 22],
            'popup_anchor'       => [0, -22],
            'description'        => 'Tiang percabangan 3 arah (T-Off) penyulang SUTM 20kV',
        ],
        'TIANG' => [
            'symbol_key'         => 'TIANG',
            'label'              => 'Tiang Distribusi (TM)',
            'category'           => 'structural',
            'family'             => 'TM',
            'visual_family'      => 'NETWORK_STRUCTURE',
            'transline_behavior' => 'INLINE_CONTINUATION',
            'svg_file'           => 'tm-1.svg',
            'svg_path'           => '/assets/icons/network/tm-1.svg',
            'png_file'           => 'tm1.png',
            'png_path'           => '/assets/gis/icons/tm1.png',
            'color'              => '#111827',
            'shape'              => 'circle-donut',
            'map_priority'       => 40,
            'marker_anchor'      => [22, 22],
            'popup_anchor'       => [0, -22],
            'description'        => 'Tiang beton / besi penumpu jaringan SUTM 20kV',
        ],

        // ==========================================================
        // 2. NETWORK EQUIPMENT & SUBSTATIONS (Dedicated Symbol Families)
        // ==========================================================
        'LBS' => [
            'symbol_key'         => 'LBS',
            'label'              => 'Load Break Switch',
            'category'           => 'switching',
            'family'             => 'SWITCH',
            'visual_family'      => 'SWITCHING_PROTECTION',
            'transline_behavior' => 'INLINE_SWITCH',
            'svg_file'           => 'lbs.svg',
            'svg_path'           => '/assets/icons/network/lbs.svg',
            'png_file'           => 'lbs.png',
            'png_path'           => '/assets/gis/icons/lbs.png',
            'color'              => '#111827',
            'shape'              => 'circle-quadrants',
            'map_priority'       => 85,
            'marker_anchor'      => [22, 22],
            'popup_anchor'       => [0, -22],
            'description'        => 'Saklar pemutus beban bertegangan (Motorized / Telecontrolled)',
        ],
        'GI' => [
            'symbol_key'         => 'GI',
            'label'              => 'Gardu Induk',
            'category'           => 'substation',
            'family'             => 'SUBSTATION',
            'visual_family'      => 'SUBSTATION_HUB',
            'transline_behavior' => 'SOURCE_SUBSTATION',
            'svg_file'           => 'gardu-induk.svg',
            'svg_path'           => '/assets/icons/network/gardu-induk.svg',
            'png_file'           => 'gi.png',
            'png_path'           => '/assets/gis/icons/gi.png',
            'color'              => '#dc2626',
            'shape'              => 'triangle-lightning',
            'map_priority'       => 100,
            'marker_anchor'      => [22, 22],
            'popup_anchor'       => [0, -22],
            'description'        => 'Titik pasok hulu transmisi ke distribusi 20kV',
        ],
        'LBSM' => [
            'symbol_key'         => 'LBSM',
            'label'              => 'LBS Manual / PMS',
            'category'           => 'switching_manual',
            'family'             => 'SWITCH',
            'visual_family'      => 'SWITCHING_PROTECTION',
            'transline_behavior' => 'INLINE_SWITCH',
            'svg_file'           => 'lbsm.svg',
            'svg_path'           => '/assets/icons/network/lbsm.svg',
            'png_file'           => 'lbsm.png',
            'png_path'           => '/assets/gis/icons/lbsm.png',
            'color'              => '#111827',
            'shape'              => 'square-bowtie',
            'map_priority'       => 75,
            'marker_anchor'      => [22, 22],
            'popup_anchor'       => [0, -22],
            'description'        => 'Saklar pemutus beban manual / pemisah seksi',
        ],
        'CO_BRANCH' => [
            'symbol_key'         => 'CO_BRANCH',
            'label'              => 'Cut Out Branch',
            'category'           => 'protection_branch',
            'family'             => 'PROTECTION',
            'visual_family'      => 'SWITCHING_PROTECTION',
            'transline_behavior' => 'BRANCH_PROTECTION',
            'svg_file'           => 'co-branch.svg',
            'svg_path'           => '/assets/icons/network/co-branch.svg',
            'png_file'           => 'co-branch.png',
            'png_path'           => '/assets/gis/icons/co-branch.png',
            'color'              => '#111827',
            'shape'              => 'vertical-branch-slash',
            'map_priority'       => 70,
            'marker_anchor'      => [22, 22],
            'popup_anchor'       => [0, -22],
            'description'        => 'Pengaman percabangan / Fuse Cut Out cabang',
        ],
        'PMCB_REC' => [
            'symbol_key'         => 'PMCB_REC',
            'label'              => 'PMCB / Recloser',
            'category'           => 'recloser_protection',
            'family'             => 'PROTECTION',
            'visual_family'      => 'SWITCHING_PROTECTION',
            'transline_behavior' => 'INLINE_PROTECTION',
            'svg_file'           => 'pmcb-recloser.svg',
            'svg_path'           => '/assets/icons/network/pmcb-recloser.svg',
            'png_file'           => 'pmcb-rec.png',
            'png_path'           => '/assets/gis/icons/pmcb-rec.png',
            'color'              => '#dc2626',
            'shape'              => 'square-bowtie-arrows',
            'map_priority'       => 95,
            'marker_anchor'      => [22, 22],
            'popup_anchor'       => [0, -22],
            'description'        => 'Pemutus balik otomatis / Recloser proteksi penyulang',
        ],
        'I3' => [
            'symbol_key'         => 'I3',
            'label'              => 'Indikator 3 (FPI 3-Phase)',
            'category'           => 'indicator',
            'family'             => 'INDICATOR',
            'visual_family'      => 'FAULT_INDICATOR',
            'transline_behavior' => 'INLINE_INDICATOR',
            'svg_file'           => 'indicator-3.svg',
            'svg_path'           => '/assets/icons/network/indicator-3.svg',
            'png_file'           => 'tm11-i3.png',
            'png_path'           => '/assets/gis/icons/tm11-i3.png',
            'color'              => '#2563eb',
            'shape'              => 'square-dark-center',
            'map_priority'       => 65,
            'marker_anchor'      => [22, 22],
            'popup_anchor'       => [0, -22],
            'description'        => 'Fault Passage Indicator 3-Phasa',
        ],
        'GH' => [
            'symbol_key'         => 'GH',
            'label'              => 'Gardu Hubung',
            'category'           => 'switching_station',
            'family'             => 'SUBSTATION',
            'visual_family'      => 'SUBSTATION_HUB',
            'transline_behavior' => 'HUB_STATION',
            'svg_file'           => 'gardu-hubung.svg',
            'svg_path'           => '/assets/icons/network/gardu-hubung.svg',
            'png_file'           => 'gi.png',
            'png_path'           => '/assets/gis/icons/gi.png',
            'color'              => '#ea580c',
            'shape'              => 'box-orange-chain',
            'map_priority'       => 90,
            'marker_anchor'      => [22, 22],
            'popup_anchor'       => [0, -22],
            'description'        => 'Pusat manuver dan hub distribusi antar penyulang',
        ],
        'I2' => [
            'symbol_key'         => 'I2',
            'label'              => 'Indikator 2 (FPI 2-Phase)',
            'category'           => 'indicator',
            'family'             => 'INDICATOR',
            'visual_family'      => 'FAULT_INDICATOR',
            'transline_behavior' => 'INLINE_INDICATOR',
            'svg_file'           => 'indicator-2.svg',
            'svg_path'           => '/assets/icons/network/indicator-2.svg',
            'png_file'           => 'gtt1-i2.png',
            'png_path'           => '/assets/gis/icons/gtt1-i2.png',
            'color'              => '#3b82f6',
            'shape'              => 'triangle-blue',
            'map_priority'       => 60,
            'marker_anchor'      => [22, 22],
            'popup_anchor'       => [0, -22],
            'description'        => 'Fault Passage Indicator 2-Phasa',
        ],
        'DISTRIBUSI' => [
            'symbol_key'         => 'DISTRIBUSI',
            'label'              => 'Trafo Distribusi',
            'category'           => 'transformer',
            'family'             => 'TRANSFORMER',
            'visual_family'      => 'TRANSFORMER',
            'transline_behavior' => 'INLINE_EQUIPMENT',
            'svg_file'           => 'distribusi.svg',
            'svg_path'           => '/assets/icons/network/distribusi.svg',
            'png_file'           => 'gtt1-dist.png',
            'png_path'           => '/assets/gis/icons/gtt1-dist.png',
            'color'              => '#111827',
            'shape'              => 'triangle-solid-black',
            'map_priority'       => 80,
            'marker_anchor'      => [22, 22],
            'popup_anchor'       => [0, -22],
            'description'        => 'Trafo distribusi penurun tegangan 20kV ke 380V/220V',
        ],
        'GARDU_GTT2_DIST' => [
            'symbol_key'         => 'GARDU_GTT2_DIST',
            'label'              => 'Gardu Trafo Portal 2-Tiang Distribusi (GTT-2)',
            'category'           => 'transformer',
            'family'             => 'TRANSFORMER',
            'visual_family'      => 'TRANSFORMER',
            'transline_behavior' => 'INLINE_PORTAL',
            'svg_file'           => 'distribusi.svg',
            'svg_path'           => '/assets/icons/network/distribusi.svg',
            'png_file'           => 'gtt2-dist.png',
            'png_path'           => '/assets/gis/icons/gtt2-dist.png',
            'color'              => '#059669',
            'shape'              => 'portal-transformer',
            'map_priority'       => 83,
            'marker_anchor'      => [22, 22],
            'popup_anchor'       => [0, -22],
            'description'        => 'Gardu trafo distribusi konstruksi portal 2 tiang (GTT-2)',
        ],
        'GARDU_GTT1_DIST' => [
            'symbol_key'         => 'GARDU_GTT1_DIST',
            'label'              => 'Gardu Trafo 1-Tiang Cantilever (GTT-1)',
            'category'           => 'transformer',
            'family'             => 'TRANSFORMER',
            'visual_family'      => 'TRANSFORMER',
            'transline_behavior' => 'INLINE_CANTILEVER',
            'svg_file'           => 'distribusi.svg',
            'svg_path'           => '/assets/icons/network/distribusi.svg',
            'png_file'           => 'gtt1-dist.png',
            'png_path'           => '/assets/gis/icons/gtt1-dist.png',
            'color'              => '#059669',
            'shape'              => 'cantilever-transformer',
            'map_priority'       => 81,
            'marker_anchor'      => [22, 22],
            'popup_anchor'       => [0, -22],
            'description'        => 'Gardu trafo distribusi konstruksi cantol 1 tiang (GTT-1)',
        ],
        'DEFAULT' => [
            'symbol_key'         => 'DEFAULT',
            'label'              => 'Aset Jaringan',
            'category'           => 'general',
            'family'             => 'GENERAL',
            'visual_family'      => 'GENERAL_NETWORK',
            'transline_behavior' => 'INLINE_NODE',
            'svg_file'           => 'generic-network-asset.svg',
            'svg_path'           => '/assets/icons/network/generic-network-asset.svg',
            'png_file'           => 'tm1.png',
            'png_path'           => '/assets/gis/icons/tm1.png',
            'color'              => '#475569',
            'shape'              => 'hexagon-node',
            'map_priority'       => 50,
            'marker_anchor'      => [22, 22],
            'popup_anchor'       => [0, -22],
            'description'        => 'Peralatan / konstruksi jaringan distribusi 20kV',
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

        $matchedKey = null;
        $isFallback = false;
        $fallbackReason = null;

        // -------------------------------------------------------------
        // STEP 1: ENGINEERING SUBTYPE RESOLUTION (Highest Priority)
        // Resolves authentic equipment identities from construction_type
        // -------------------------------------------------------------
        $matchedKey = match(true) {
            // Gardu Tiang Trafo 2-Tiang Portal (GTT-2)
            $c === 'GTT2' || str_contains($c, 'GTT2') || str_contains($c, 'GTT_2') || str_contains($c, '2_TIANG') || str_contains($k, 'GTT2') || str_contains($k, 'GTT-2') => (str_contains($c, 'I2') || str_contains($k, 'I2') ? 'I2' : 'GARDU_GTT2_DIST'),

            // Gardu Tiang Trafo 1-Tiang Cantilever (GTT-1 / GTT)
            $c === 'GTT1' || $c === 'GTT' || str_contains($c, 'GTT1') || str_contains($c, 'GTT_1') || str_contains($c, 'CANTOL') || str_contains($c, 'CANTILEVER') || str_contains($k, 'GTT1') || str_contains($k, 'GTT-1') => (str_contains($c, 'I2') || str_contains($k, 'I2') ? 'I2' : 'GARDU_GTT1_DIST'),

            // Gardu Induk (GI)
            in_array($j, ['GI', 'GARDU_INDUK', 'SUBSTATION', 'BAY_TRAFO'], true) || str_contains($c, 'GARDU_INDUK') || str_contains($c, 'GI') || str_starts_with($k, 'GI-') => 'GI',

            // Gardu Hubung (GH)
            in_array($j, ['GH', 'GARDU_HUBUNG', 'SWITCHING_STATION', 'KUBIKEL_GH'], true) || str_contains($c, 'GARDU_HUBUNG') || str_contains($c, 'GH') || str_starts_with($k, 'GH-') => 'GH',

            // Switching: PMS / LBS Manual
            $c === 'PMS' || str_contains($c, 'PMS') || str_contains($c, 'LBSM') || in_array($j, ['LBSM', 'LBS_MANUAL', 'LOAD_BREAK_SWITCH_MANUAL', 'PMS', 'PEMISAH', 'SEKSI'], true) || str_starts_with($k, 'PMS-') || str_starts_with($k, 'LBSM-') => 'LBSM',

            // Switching: LBS Motorized
            in_array($j, ['LBS', 'LOAD_BREAK_SWITCH', 'LBS_MOTOR', 'LBS_MOTORIZED', 'LBS_OTOMATIS'], true) || str_contains($c, 'LBS_MOTOR') || (str_contains($c, 'LBS') && !str_contains($c, 'LBSM')) || str_starts_with($k, 'LBS-') => 'LBS',

            // Protection: PMCB / Recloser
            in_array($j, ['PMCB_REC', 'PMCB', 'RECLOSER', 'REC', 'ACR', 'AUTO_RECLOSER', 'PMT', 'PEMUTUS'], true) || str_contains($c, 'RECLOSER') || str_contains($c, 'PMCB') || str_contains($c, 'PMT') || str_starts_with($k, 'REC-') || str_starts_with($k, 'PMCB-') => 'PMCB_REC',

            // Protection: Fuse Cut Out (FCO / Cutout Branch)
            in_array($j, ['CO_BRANCH', 'CUT_OUT_BRANCH', 'FCO', 'FUSE_CUT_OUT', 'CO', 'PERCABANGAN_CO'], true) || str_contains($c, 'FCO') || str_contains($c, 'CUT_OUT') || str_starts_with($k, 'CO-') => 'CO_BRANCH',

            // TM-8 / TMTP / Portal Double Pole
            $c === 'TM8' || str_contains($c, 'TM_8') || str_contains($c, 'TM8') || $c === 'TMTP' || str_contains($c, 'TMTP') || str_contains($c, 'PORTAL') || str_contains($j, 'TM_8') || str_contains($j, 'TM8') || str_contains($k, 'TM8') || str_contains($k, 'TM-8') => 'TM_8',

            // TM-11 / Percabangan T-Off
            $c === 'TM11' || str_contains($c, 'TM_11') || str_contains($c, 'TM11') || str_contains($c, 'PERCABANGAN') || str_contains($j, 'TM_11') || str_contains($j, 'TM11') || str_contains($k, 'TM11') || str_contains($k, 'TM-11') => (str_contains($c, 'I3') || str_contains($k, 'I3') ? 'I3' : 'TM_11'),

            // TM-10 / Dead-End Pole (Tiang Akhir)
            $c === 'TM10' || str_contains($c, 'TM_10') || str_contains($c, 'TM10') || str_contains($c, 'AKHIR') || str_contains($c, 'DEAD_END') || str_contains($j, 'TM_10') || str_contains($j, 'TM10') || str_contains($k, 'TM10') || str_contains($k, 'TM-10') => 'TM_10',

            // TM-5 / Pole Angle (Tiang Sudut)
            $c === 'TM5' || str_contains($c, 'TM_5') || str_contains($c, 'TM5') || str_contains($c, 'SUDUT') || str_contains($j, 'TM_5') || str_contains($j, 'TM5') || str_contains($k, 'TM5') || str_contains($k, 'TM-5') => 'TM_5',

            // TM-4 / Double Tension Pole (Tiang Penegang Ganda)
            $c === 'TM4' || str_contains($c, 'TM_4') || str_contains($c, 'TM4') || str_contains($j, 'TM_4') || str_contains($j, 'TM4') || str_contains($k, 'TM4') || str_contains($k, 'TM-4') => 'TM_4',

            // TM-2 / Single Tension Pole (Tiang Penegang Tunggal)
            $c === 'TM2' || str_contains($c, 'TM_2') || str_contains($c, 'TM2') || str_contains($j, 'TM_2') || str_contains($j, 'TM2') || str_contains($k, 'TM2') || str_contains($k, 'TM-2') => 'TM_2',

            // TM-1 / Tangent Pole Standard (Tiang Tumpu Garis Lurus)
            $c === 'TM1' || str_contains($c, 'TM_1') || str_contains($c, 'TM1') || str_contains($c, 'TUMPU') || str_contains($j, 'TM_1') || str_contains($j, 'TM1') || str_contains($k, 'TM1') || str_contains($k, 'TM-1') => 'TM_1',

            // Fault Passage Indicators
            str_contains($c, 'FPI_3') || str_contains($c, 'I3') || in_array($j, ['I3', 'INDIKATOR_3', 'FAULT_INDICATOR_3', 'FPI_3'], true) => 'I3',
            str_contains($c, 'FPI_2') || str_contains($c, 'I2') || in_array($j, ['I2', 'INDIKATOR_2', 'FAULT_INDICATOR_2', 'FPI_2'], true) => 'I2',

            // Explicit Category Fallbacks
            in_array($j, ['DISTRIBUSI', 'TRAFO', 'TRAFO_DISTRIBUSI', 'GARDU', 'GARDU_DISTRIBUSI', 'TRANSFORMER'], true) || str_contains($c, 'TRAFO') || str_starts_with($k, 'SDJ-') || str_starts_with($k, 'GD-') || str_starts_with($k, 'TR-') => 'GARDU_GTT1_DIST',

            in_array($j, ['TIANG', 'POLE', 'TIANG_BETON', 'TIANG_BESI', 'TIANG_SUTM'], true) || str_contains($c, 'TIANG') || str_contains($c, 'POLE') || str_starts_with($k, 'TG-') || str_starts_with($k, 'T-') => 'TM_1',

            default => null,
        };

        // -------------------------------------------------------------
        // STEP 2: SAFE CONTROLLED FALLBACK (Hard Amendment 2)
        // If unknown construction type, fallback to TM-1 with diagnostic metadata
        // -------------------------------------------------------------
        if ($matchedKey !== null) {
            $symbolKey = $matchedKey;
            $isFallback = false;
            $fallbackReason = null;
        } elseif ($j === 'JTM' || empty($c)) {
            $symbolKey = 'TM_1';
            $isFallback = true;
            $fallbackReason = 'UNKNOWN_CONSTRUCTION_TYPE';
        } else {
            $symbolKey = 'DEFAULT';
            $isFallback = true;
            $fallbackReason = 'UNRECOGNIZED_EQUIPMENT_SPECIFICATION';
        }

        $spec = self::SYMBOLS[$symbolKey] ?? self::SYMBOLS['TM_1'];

        return array_merge($spec, [
            'fallback'        => $isFallback,
            'isFallback'      => $isFallback,
            'fallbackReason'  => $fallbackReason,
            'family'          => $spec['family'] ?? 'TM',
            'base_silhouette' => $spec['symbol_key'] ?? 'TM_1',
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

    /**
     * Get Web Asset PNG Path for an Asset Symbol (TL-03 Authentic Icons)
     *
     * @param string $symbolKey
     * @return string
     */
    public function getPublicAssetPngPath(string $symbolKey): string
    {
        $spec = self::SYMBOLS[$symbolKey] ?? self::SYMBOLS['DEFAULT'];
        $path = $spec['png_path'] ?? '/assets/gis/icons/tm1.png';
        return base_url($path);
    }
}
