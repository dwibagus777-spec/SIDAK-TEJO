<?php

namespace App\Controllers\Api;

use App\Controllers\Api\BaseApiController;

class HealthController extends BaseApiController
{
    public function index()
    {
        $health = $this->integrationService->healthCheck();
        $code = ($health['status'] === 'HEALTHY') ? 200 : 503;

        return $this->respondStandard(
            ($health['status'] === 'HEALTHY'),
            $code,
            'System Health Check: ' . $health['status'],
            $health
        );
    }
}
