<?php

namespace App\Services;

class AssetMatrixService
{
    /**
     * Master Asset Family Matrix & Construction Compatibility Map
     */
    public static function getMatrix(): array
    {
        return [
            'JTM' => [
                'name'                         => 'Jaringan Tegangan Menengah 20kV',
                'asset_types'                  => ['TM1', 'TM2', 'TM3', 'TM4', 'TM5', 'TM6', 'TM7', 'TM8', 'TM9', 'TM10', 'TM11', 'TM12', 'TMMVTIC', 'SKTM', 'TMTP', 'TM16', 'TM16A', 'GTT', 'GTT1', 'GTT2', 'PMS', 'PMT'],
                'allowed_support_constructions'=> ['TM1', 'TM2', 'TM3', 'TM4', 'TM5', 'TM6', 'TM7', 'TM8', 'TM9', 'TM10', 'TM11', 'TM12', 'TMTP', 'TM16', 'TM16A'],
                'default_construction'         => 'TM1',
            ],
            'GARDU' => [
                'name'                         => 'Gardu Distribusi 20kV',
                'asset_types'                  => ['GD_PORTAL', 'GD_CANTOL', 'GD_BETON', 'GD_KIOS', 'GTT', 'GTT1', 'GTT2'],
                'allowed_support_constructions'=> ['GD_PORTAL', 'GD_CANTOL', 'GD_BETON', 'GD_KIOS'],
                'default_construction'         => 'GD_PORTAL',
            ],
            'TRAFO' => [
                'name'                         => 'Trafo Distribusi 20kV',
                'asset_types'                  => ['TRAFO_DISTRIBUSI', 'TRAFO_1PHASE', 'TRAFO_3PHASE'],
                'allowed_support_constructions'=> ['TM8', 'TM11', 'GD_PORTAL', 'GD_CANTOL', 'GTT', 'GTT1', 'GTT2'],
                'default_construction'         => 'TRAFO_DISTRIBUSI',
            ],
            'KUBIKEL' => [
                'name'                         => 'Kubikel 20kV (Switchgear)',
                'asset_types'                  => ['KUBIKEL_INCOMING', 'KUBIKEL_OUTGOING', 'KUBIKEL_METERING', 'KUBIKEL_PROTECTION', 'KUBIKEL'],
                'allowed_support_constructions'=> ['GD_BETON', 'GD_KIOS'],
                'default_construction'         => 'KUBIKEL',
            ],
            'LBS' => [
                'name'                         => 'Load Break Switch (LBS)',
                'asset_types'                  => ['LBS_MANUAL', 'LBS'],
                'allowed_support_constructions'=> ['TM10', 'TM1', 'TM2'],
                'default_construction'         => 'LBS',
            ],
            'LBSM' => [
                'name'                         => 'LBS Motorized (SCADA)',
                'asset_types'                  => ['LBS_MOTOR', 'LBSM'],
                'allowed_support_constructions'=> ['TM10', 'TM16A'],
                'default_construction'         => 'LBSM',
            ],
            'RECLOSER' => [
                'name'                         => 'Automatic Circuit Recloser (ACR)',
                'asset_types'                  => ['RECLOSER'],
                'allowed_support_constructions'=> ['TM12'],
                'default_construction'         => 'RECLOSER',
            ],
            'SECTIONALIZER' => [
                'name'                         => 'Automatic Sectionalizer',
                'asset_types'                  => ['SECTIONALIZER'],
                'allowed_support_constructions'=> ['TM9', 'TM10'],
                'default_construction'         => 'SECTIONALIZER',
            ],
            'JTR' => [
                'name'                         => 'Jaringan Tegangan Rendah 380/220V',
                'asset_types'                  => ['TR1', 'TR2', 'TR3', 'TR4', 'TR5', 'TR6', 'TR7', 'TR8', 'TR9', 'SKTR'],
                'allowed_support_constructions'=> ['TR1', 'TR2', 'TR3', 'TR4', 'TR5'],
                'default_construction'         => 'TR1',
            ],
        ];
    }

    /**
     * Check if a construction code is compatible with a primary asset family (as equipment type OR support construction)
     */
    public static function isCompatible(string $jenisAsset, string $constructionCode): bool
    {
        $matrix = self::getMatrix();
        $jenis  = strtoupper(trim($jenisAsset));
        $code   = strtoupper(trim($constructionCode));

        if (!isset($matrix[$jenis])) {
            return true; // Allow custom asset categories gracefully
        }

        $allowedTypes   = $matrix[$jenis]['asset_types'] ?? [];
        $allowedSupport = $matrix[$jenis]['allowed_support_constructions'] ?? [];

        return in_array($code, $allowedTypes, true) || in_array($code, $allowedSupport, true);
    }
}
