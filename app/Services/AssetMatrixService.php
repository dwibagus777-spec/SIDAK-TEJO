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
                'name'                 => 'Jaringan Tegangan Menengah 20kV',
                'allowed_constructions'=> ['TM1', 'TM2', 'TM3', 'TM4', 'TM5', 'TM6', 'TM7', 'TM8', 'TM9', 'TM10', 'TM11', 'TM12', 'TMMVTIC', 'SKTM', 'TMTP', 'TM16', 'TM16A', 'GTT', 'GTT1', 'GTT2', 'PMS', 'PMT'],
                'default_construction' => 'TM1',
            ],
            'GARDU' => [
                'name'                 => 'Gardu Distribusi 20kV',
                'allowed_constructions'=> ['GD_PORTAL', 'GD_CANTOL', 'GD_BETON', 'GD_KIOS', 'GTT', 'GTT1', 'GTT2'],
                'default_construction' => 'GD_PORTAL',
            ],
            'TRAFO' => [
                'name'                 => 'Trafo Distribusi 20kV',
                'allowed_constructions'=> ['TRAFO_DISTRIBUSI', 'TM8', 'TM11', 'GD_PORTAL', 'GD_CANTOL'],
                'default_construction' => 'TRAFO_DISTRIBUSI',
            ],
            'KUBIKEL' => [
                'name'                 => 'Kubikel 20kV (Switchgear)',
                'allowed_constructions'=> ['KUBIKEL_INCOMING', 'KUBIKEL_OUTGOING', 'KUBIKEL_METERING', 'KUBIKEL_PROTECTION', 'KUBIKEL'],
                'default_construction' => 'KUBIKEL',
            ],
            'LBS' => [
                'name'                 => 'Load Break Switch (LBS)',
                'allowed_constructions'=> ['TM10', 'LBS_MANUAL', 'LBS'],
                'default_construction' => 'LBS',
            ],
            'LBSM' => [
                'name'                 => 'LBS Motorized (SCADA)',
                'allowed_constructions'=> ['LBS_MOTOR', 'LBSM'],
                'default_construction' => 'LBSM',
            ],
            'RECLOSER' => [
                'name'                 => 'Automatic Circuit Recloser (ACR)',
                'allowed_constructions'=> ['TM12', 'RECLOSER'],
                'default_construction' => 'RECLOSER',
            ],
            'SECTIONALIZER' => [
                'name'                 => 'Automatic Sectionalizer',
                'allowed_constructions'=> ['SECTIONALIZER'],
                'default_construction' => 'SECTIONALIZER',
            ],
            'JTR' => [
                'name'                 => 'Jaringan Tegangan Rendah 380/220V',
                'allowed_constructions'=> ['TR1', 'TR2', 'TR3', 'TR4', 'TR5', 'TR6', 'TR7', 'TR8', 'TR9', 'SKTR'],
                'default_construction' => 'TR1',
            ],
        ];
    }

    /**
     * Check if a construction code is compatible with a primary asset family
     */
    public static function isCompatible(string $jenisAsset, string $constructionCode): bool
    {
        $matrix = self::getMatrix();
        $jenis  = strtoupper(trim($jenisAsset));
        $code   = strtoupper(trim($constructionCode));

        if (!isset($matrix[$jenis])) {
            return true; // Allow custom asset categories gracefully
        }

        return in_array($code, $matrix[$jenis]['allowed_constructions'], true);
    }
}
