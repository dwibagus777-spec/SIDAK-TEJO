<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * CR-ASSET-01 Configuration: Mass Asset Ingestion & Topology Reconciliation Policy
 */
class AssetIngestion extends BaseConfig
{
    /**
     * Geodetic bounding box policies (no hardcoded coordinates in service logic).
     */
    public array $geodeticPolicies = [
        'EAST_JAVA' => [
            'min_lat' => -8.9000,
            'max_lat' => -6.5000,
            'min_lng' => 110.5000,
            'max_lng' => 115.0000,
        ],
        'SIDOARJO_REGENCY' => [
            'min_lat' => -7.6000,
            'max_lat' => -7.3000,
            'min_lng' => 112.5000,
            'max_lng' => 112.9000,
        ],
    ];

    public string $activeGeodeticPolicy = 'EAST_JAVA';

    /**
     * Identity reconciliation tolerances (in meters).
     */
    public float $coordinateMatchToleranceMeters = 1.0;
    public float $spatialDuplicateThresholdMeters = 1.0;

    /**
     * Topology candidate edge distance thresholds (in meters).
     */
    public float $minSpanDistanceMeters = 5.0;
    public float $maxSpanDistanceMeters = 120.0;
    public float $abnormalSpanDistanceMeters = 150.0;

    /**
     * Construction nomenclature canonical mapping.
     */
    public array $constructionAliases = [
        'TM1'       => 'TM1',
        'TM2'       => 'TM2',
        'TM3'       => 'TM3',
        'TM4'       => 'TM4',
        'TM5'       => 'TM5',
        'TM8'       => 'TM8',
        'TM10'      => 'TM10',
        'TM11'      => 'TM11',
        'TMMVTIC'   => 'TMMVTIC',
        'TMTP'      => 'TMTP',
        'GTT'       => 'GTT',
        'GTT1'      => 'GTT1',
        'GTT2'      => 'GTT2',
        'GTT2T'     => 'GTT2',
        'PMS'       => 'PMS',
        'LBS'       => 'LBS',
        'LBSM'      => 'LBSM',
        'PMCB'      => 'PMCB',
        'RECLOSER'  => 'RECLOSER',
        'AVS'       => 'AVS',
        'PGS'       => 'PGS',
    ];

    /**
     * Standard conductor defaults for candidate edges.
     */
    public array $defaultConductor = [
        'type'              => 'AAAC',
        'size'              => '150 mm²',
        'material'          => 'ALUMINUM_ALLOY',
        'installation_type' => 'OVERHEAD',
        'circuit_config'    => '3_PHASE',
    ];
}
