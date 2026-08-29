<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * CC-04 v1.2: Harmonize Feeder Health Policy Rules to Conserved 1.0000 Weights (Gate E2-A)
 * Canonical Weights:
 * - PHYSICAL_COVERAGE       = 0.2000
 * - ASSET_STRUCTURAL_HEALTH = 0.2500 (alias BOM_DEGRADATION)
 * - FINDING_SEVERITY        = 0.2500 (alias CRITICAL_FINDINGS)
 * - RELIABILITY_PERFORMANCE = 0.2000 (alias GANGGUAN_FREQUENCY)
 * - RECURRENCE_CHRONICITY   = 0.1000 (alias RECURRING_FINDINGS)
 * Sum = 1.0000
 */
class HarmonizeFeederHealthCanonicalWeights extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        if ($db->tableExists('feeder_health_policy_versions') && $db->tableExists('feeder_health_policy_rules')) {
            $policies = $db->table('feeder_health_policy_versions')
                ->where('policy_code', 'FHI-v1.0')
                ->get()
                ->getResultArray();

            foreach ($policies as $policy) {
                $policyId = (int)$policy['id'];

                // Canonical rules definition (Conserved Sum = 1.0000)
                $canonicalRules = [
                    'PHYSICAL_COVERAGE'       => ['weight' => 0.2000, 'min_sempurna' => 85.0, 'min_sakit' => 70.0, 'min_kronis' => 50.0, 'max_kritis' => 49.99],
                    'BOM_DEGRADATION'         => ['weight' => 0.2500, 'min_sempurna' => 85.0, 'min_sakit' => 70.0, 'min_kronis' => 50.0, 'max_kritis' => 49.99],
                    'CRITICAL_FINDINGS'       => ['weight' => 0.2500, 'min_sempurna' => 85.0, 'min_sakit' => 70.0, 'min_kronis' => 50.0, 'max_kritis' => 49.99],
                    'GANGGUAN_FREQUENCY'      => ['weight' => 0.2000, 'min_sempurna' => 85.0, 'min_sakit' => 70.0, 'min_kronis' => 50.0, 'max_kritis' => 49.99],
                    'RECURRING_FINDINGS'      => ['weight' => 0.1000, 'min_sempurna' => 85.0, 'min_sakit' => 70.0, 'min_kronis' => 50.0, 'max_kritis' => 49.99],
                ];

                // Delete obsolete / duplicate rules for FHI-v1.0
                $db->table('feeder_health_policy_rules')
                    ->where('policy_version_id', $policyId)
                    ->delete();

                // Re-insert exact 5 canonical rules
                $now = date('Y-m-d H:i:s');
                foreach ($canonicalRules as $metricKey => $cfg) {
                    $db->table('feeder_health_policy_rules')->insert([
                        'policy_version_id'      => $policyId,
                        'metric_key'             => $metricKey,
                        'weight'                 => $cfg['weight'],
                        'threshold_sempurna_min' => $cfg['min_sempurna'],
                        'threshold_sakit_min'    => $cfg['min_sakit'],
                        'threshold_kronis_min'   => $cfg['min_kronis'],
                        'threshold_kritis_max'   => $cfg['max_kritis'],
                        'created_at'             => $now,
                    ]);
                }
            }
        }
    }

    public function down()
    {
        // Non-destructive down
    }
}
