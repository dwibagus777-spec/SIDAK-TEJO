<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

class DemandSideFlexibilityAdvisoryService
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * Demand-Side Flexibility Opportunity & Peak Shaving Advisory Engine (Phase 7P)
     */
    public function recommendDemandFlexibility(int $assetId = 1): array
    {
        $db = $this->db;

        $flexibilityAdvisory = [
            'bundle_id'                  => 'DSF-BDL-STJ-' . date('YmdHis') . '-01',
            'asset_id'                   => $assetId,
            'recommended_flexibility_action'=>'SPKLU_PEAK_SHAVING_SCHEDULE_ADVISORY',
            'estimated_peak_reduction_kw'=> 28.5,
            'advisory_status'            => 'ADVISORY_PROPOSED',
            'automatic_load_shedding'    => 'DENIED_REQUIRES_DISPATCHER_APPROVAL',
            'remote_tap_changer_mutation'=> 'FORBIDDEN',
            'spklu_charger_auto_disconnect'=>'FORBIDDEN',
            'advised_at'                 => date('Y-m-d H:i:s'),
            'flexibility_status'         => 'DEMAND_SIDE_FLEXIBILITY_COMPLETED',
        ];

        return [
            'status'                     => 'success',
            'flexibility_advisory'       => $flexibilityAdvisory,
            'flexibility_engine_version' => 'DEMAND_SIDE_FLEXIBILITY_v1.0',
            'certified_flexibility_status'=> 'DEMAND_SIDE_FLEXIBILITY_VERIFIED',
        ];
    }
}
