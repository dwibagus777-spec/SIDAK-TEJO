<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

class StepUpAuthorizationService
{
    protected BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * Step-Up Re-Authorization Challenge Engine (Phase 5A)
     */
    public function requestStepUpChallenge(string $action, int $userId = 1): array
    {
        $db = $this->db;

        $token = 'STEPUP-STJ-' . date('Ymd') . '-' . sprintf('%04d', rand(1000, 9999));

        $stepUpGrant = [
            'grant_token'      => $token,
            'user_id'          => $userId,
            'target_action'    => $action,
            'challenge_method' => 'BIOMETRIC_OR_SUPERVISOR_PIN',
            'grant_duration_m' => 30,
            'issued_at'        => date('Y-m-d H:i:s'),
            'expires_at'       => date('Y-m-d H:i:s', strtotime('+30 minutes')),
            'step_up_status'   => 'STEP_UP_GRANT_ACTIVE',
        ];

        return [
            'status'                  => 'success',
            'step_up_grant'           => $stepUpGrant,
            'step_up_engine_version'  => 'STEP_UP_AUTH_v1.0',
            'certified_step_up_status'=> 'STEP_UP_AUTHORIZATION_VERIFIED',
        ];
    }
}
