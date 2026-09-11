<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * GIS Icon Configuration for SIDAK TEJO Enterprise
 * Official PNG Assets from UP3 Sidoarjo Google Drive Collections
 */
class GisIconConfig extends BaseConfig
{
    public string $iconBasePath = 'assets/gis/icons/';

    /**
     * Master Asset Icon Definitions
     */
    public array $icons = [
        // JTM Distribution Poles
        'JTM_DEFAULT' => [
            'file'        => 'tm1.png',
            'label'       => 'Tiang TM-1 (Lurus)',
            'size'        => [30, 30],
            'anchor'      => [15, 30],
            'popupAnchor' => [0, -30],
        ],
        'JTM_TM1' => [
            'file'        => 'tm1.png',
            'label'       => 'Tiang TM-1 (Tumpu Tunggal)',
            'size'        => [30, 30],
            'anchor'      => [15, 30],
            'popupAnchor' => [0, -30],
        ],
        'JTM_TM2' => [
            'file'        => 'tm2.png',
            'label'       => 'Tiang TM-2 (Ganda / Sudut Ringan)',
            'size'        => [30, 30],
            'anchor'      => [15, 30],
            'popupAnchor' => [0, -30],
        ],
        'JTM_TM4' => [
            'file'        => 'tm4.png',
            'label'       => 'Tiang TM-4 (Akhir / Terminal)',
            'size'        => [30, 30],
            'anchor'      => [15, 30],
            'popupAnchor' => [0, -30],
        ],
        'JTM_TM5' => [
            'file'        => 'tm5.png',
            'label'       => 'Tiang TM-5 (T-Off Percabangan)',
            'size'        => [30, 30],
            'anchor'      => [15, 30],
            'popupAnchor' => [0, -30],
        ],
        'JTM_TM8' => [
            'file'        => 'tm8.png',
            'label'       => 'Tiang TM-8 (Sudut Sedang / Tension)',
            'size'        => [30, 30],
            'anchor'      => [15, 30],
            'popupAnchor' => [0, -30],
        ],
        'JTM_TM10' => [
            'file'        => 'tm10.png',
            'label'       => 'Tiang TM-10 (Double Circuit)',
            'size'        => [30, 30],
            'anchor'      => [15, 30],
            'popupAnchor' => [0, -30],
        ],
        'JTM_TM11' => [
            'file'        => 'tm11.png',
            'label'       => 'Tiang TM-11 (Spesial)',
            'size'        => [30, 30],
            'anchor'      => [15, 30],
            'popupAnchor' => [0, -30],
        ],
        'JTM_TM11_I3' => [
            'file'        => 'tm11-i3.png',
            'label'       => 'Tiang TM-11 I3 (Isolated 3-Phase)',
            'size'        => [30, 30],
            'anchor'      => [15, 30],
            'popupAnchor' => [0, -30],
        ],

        // Gardu & Substations
        'GARDU_DEFAULT' => [
            'file'        => 'gtt1-dist.png',
            'label'       => 'Gardu Trafo Tiang (GTT)',
            'size'        => [32, 34],
            'anchor'      => [16, 34],
            'popupAnchor' => [0, -34],
        ],
        'GARDU_GTT1_DIST' => [
            'file'        => 'gtt1-dist.png',
            'label'       => 'Gardu Trafo 1-Tiang Distribusi',
            'size'        => [32, 34],
            'anchor'      => [16, 34],
            'popupAnchor' => [0, -34],
        ],
        'GARDU_GTT1_I2' => [
            'file'        => 'gtt1-i2.png',
            'label'       => 'Gardu Trafo 1-Tiang Isolasi (I2)',
            'size'        => [32, 34],
            'anchor'      => [16, 34],
            'popupAnchor' => [0, -34],
        ],
        'GARDU_GTT2_DIST' => [
            'file'        => 'gtt2-dist.png',
            'label'       => 'Gardu Trafo Portal 2-Tiang Distribusi',
            'size'        => [34, 34],
            'anchor'      => [17, 34],
            'popupAnchor' => [0, -34],
        ],
        'GARDU_GTT2_I2' => [
            'file'        => 'gtt2-i2.png',
            'label'       => 'Gardu Trafo Portal 2-Tiang Tipe I2',
            'size'        => [34, 34],
            'anchor'      => [17, 34],
            'popupAnchor' => [0, -34],
        ],
        'GARDU_INDUK' => [
            'file'        => 'gi.png',
            'label'       => 'Gardu Induk (150 kV / 20 kV)',
            'size'        => [36, 32],
            'anchor'      => [18, 32],
            'popupAnchor' => [0, -32],
        ],

        // Switching & Protection Equipment
        'SWITCH_LBS' => [
            'file'        => 'lbs.png',
            'label'       => 'Load Break Switch (Manual)',
            'size'        => [32, 32],
            'anchor'      => [16, 32],
            'popupAnchor' => [0, -32],
        ],
        'SWITCH_LBSM' => [
            'file'        => 'lbsm.png',
            'label'       => 'LBS Motorized (SCADA)',
            'size'        => [32, 32],
            'anchor'      => [16, 32],
            'popupAnchor' => [0, -32],
        ],
        'SWITCH_RECLOSER' => [
            'file'        => 'pmcb-rec.png',
            'label'       => 'Pole Mounted Circuit Breaker / Recloser',
            'size'        => [32, 32],
            'anchor'      => [16, 32],
            'popupAnchor' => [0, -32],
        ],
        'SWITCH_CUTOUT' => [
            'file'        => 'co-branch.png',
            'label'       => 'Fuse Cut-Out (FCO / Branch)',
            'size'        => [36, 20],
            'anchor'      => [18, 20],
            'popupAnchor' => [0, -20],
        ],

        // Conductor Legend Graphics
        'COND_A3C_70' => [
            'file'        => 'a3c-70.png',
            'label'       => 'AAAC 70 mm²',
            'size'        => [100, 16],
        ],
        'COND_A3C_150' => [
            'file'        => 'a3c-150.png',
            'label'       => 'AAAC 150 mm²',
            'size'        => [100, 16],
        ],
        'COND_A3C_240' => [
            'file'        => 'a3c-240.png',
            'label'       => 'AAAC 240 mm²',
            'size'        => [100, 16],
        ],
        'COND_A3CS_150' => [
            'file'        => 'a3cs-150.png',
            'label'       => 'AAAC-S 150 mm²',
            'size'        => [100, 16],
        ],
        'COND_A3CS_240' => [
            'file'        => 'a3cs-240.png',
            'label'       => 'AAAC-S 240 mm²',
            'size'        => [100, 16],
        ],
        'COND_MVTIC_150' => [
            'file'        => 'mvtic-150.png',
            'label'       => 'MVTIC 150 mm²',
            'size'        => [100, 20],
        ],
        'COND_XLPE' => [
            'file'        => 'xlpe.png',
            'label'       => 'XLPE 150 mm²',
            'size'        => [100, 20],
        ],
    ];

    /**
     * Resolve icon key by asset properties
     */
    public function resolveIconKey(array $asset): string
    {
        $type = strtoupper(trim((string)($asset['jenis_asset'] ?? $asset['type'] ?? 'JTM')));
        $name = strtoupper(trim((string)($asset['nama_asset'] ?? $asset['name'] ?? '')));
        $code = strtoupper(trim((string)($asset['kode_asset'] ?? $asset['code'] ?? '')));
        $constr = strtoupper(trim((string)($asset['construction_type'] ?? $asset['construction_code'] ?? $asset['konstruksi'] ?? '')));

        // 1. Gardu Tiang Trafo (Portal 2-Tiang & Cantilever)
        if ($constr === 'GTT2' || str_contains($constr, 'GTT2') || str_contains($constr, 'GTT_2') || str_contains($constr, '2-TIANG') || str_contains($name, 'GTT2') || str_contains($code, 'GTT2')) {
            return (str_contains($constr, 'I2') || str_contains($name, 'I2')) ? 'GARDU_GTT2_I2' : 'GARDU_GTT2_DIST';
        }
        if ($constr === 'GTT1' || $constr === 'GTT' || str_contains($constr, 'GTT1') || str_contains($constr, 'GTT_1') || str_contains($name, 'GTT') || str_contains($code, 'GTT')) {
            return (str_contains($constr, 'I2') || str_contains($name, 'I2')) ? 'GARDU_GTT1_I2' : 'GARDU_GTT1_DIST';
        }
        if ($type === 'GARDU') {
            if (str_contains($name, 'GI') || str_contains($code, 'GI-') || str_contains($name, 'INDUK') || str_contains($constr, 'GI')) {
                return 'GARDU_INDUK';
            }
            if (str_contains($name, 'PORTAL') || str_contains($name, 'GTT2') || str_contains($constr, '2-TIANG')) {
                return str_contains($name, 'I2') ? 'GARDU_GTT2_I2' : 'GARDU_GTT2_DIST';
            }
            if (str_contains($name, 'I2')) {
                return 'GARDU_GTT1_I2';
            }
            return 'GARDU_GTT1_DIST';
        }

        // 2. Switching & Protection Equipment
        if ($constr === 'PMS' || str_contains($constr, 'PMS') || str_contains($constr, 'LBSM') || str_contains($name, 'PMS') || str_contains($name, 'LBSM') || str_contains($name, 'MOTOR') || $type === 'PMS') {
            return 'SWITCH_LBSM';
        }
        if ($type === 'SWITCH' || str_contains($name, 'LBS') || str_contains($name, 'REC') || str_contains($name, 'PMCB') || str_contains($constr, 'LBS')) {
            if (str_contains($name, 'LBSM') || str_contains($name, 'MOTOR')) {
                return 'SWITCH_LBSM';
            }
            if (str_contains($name, 'LBS') || str_contains($constr, 'LBS')) {
                return 'SWITCH_LBS';
            }
            if (str_contains($name, 'REC') || str_contains($name, 'PMCB') || str_contains($name, 'RECLOSER') || str_contains($constr, 'REC')) {
                return 'SWITCH_RECLOSER';
            }
            if (str_contains($name, 'FCO') || str_contains($name, 'CUTOUT') || str_contains($name, 'BRANCH') || str_contains($constr, 'FCO')) {
                return 'SWITCH_CUTOUT';
            }
            return 'SWITCH_LBS';
        }

        // 3. JTM Poles
        if ($constr === 'TM11' || str_contains($constr, 'TM-11') || str_contains($constr, 'TM11') || str_contains($name, 'TM11') || str_contains($code, 'TM11')) {
            return str_contains($constr, 'I3') || str_contains($name, 'I3') ? 'JTM_TM11_I3' : 'JTM_TM11';
        }
        if ($constr === 'TM10' || str_contains($constr, 'TM-10') || str_contains($constr, 'TM10') || str_contains($name, 'TM10') || str_contains($code, 'TM10')) {
            return 'JTM_TM10';
        }
        if ($constr === 'TM8' || $constr === 'TMTP' || str_contains($constr, 'TM-8') || str_contains($constr, 'TM8') || str_contains($constr, 'TMTP') || str_contains($name, 'TM8') || str_contains($code, 'TM8')) {
            return 'JTM_TM8';
        }
        if ($constr === 'TM5' || str_contains($constr, 'TM-5') || str_contains($constr, 'TM5') || str_contains($name, 'TM5') || str_contains($code, 'TM5')) {
            return 'JTM_TM5';
        }
        if ($constr === 'TM4' || str_contains($constr, 'TM-4') || str_contains($constr, 'TM4') || str_contains($name, 'TM4') || str_contains($code, 'TM4')) {
            return 'JTM_TM4';
        }
        if ($constr === 'TM2' || str_contains($constr, 'TM-2') || str_contains($constr, 'TM2') || str_contains($name, 'TM2') || str_contains($code, 'TM2')) {
            return 'JTM_TM2';
        }
        if ($constr === 'TM1' || str_contains($constr, 'TM-1') || str_contains($constr, 'TM1') || str_contains($name, 'TM1') || str_contains($code, 'TM1') || str_contains($constr, 'TUMPU')) {
            return 'JTM_TM1';
        }

        return 'JTM_DEFAULT';
    }
}