<?php

namespace App\Controllers;

use App\Services\DatabaseOptimizationService;

class StatusController extends BaseController
{
    public function health()
    {
        $db = \Config\Database::connect();
        $dbStatus = 'OK';
        try {
            $db->query('SELECT 1');
        } catch (\Throwable $e) {
            $dbStatus = 'FAIL: ' . $e->getMessage();
        }

        $health = [
            'status'      => ($dbStatus === 'OK') ? 'HEALTHY' : 'DEGRADED',
            'version'     => '1.0.0',
            'environment' => ENVIRONMENT,
            'timestamp'   => date('Y-m-d H:i:s'),
            'checks'      => [
                'database' => $dbStatus,
                'php'      => PHP_VERSION,
                'memory'   => round(memory_get_usage(true) / 1048576, 2) . ' MB',
                'disk'     => round(disk_free_space(WRITEPATH) / 1048576, 2) . ' MB',
            ]
        ];

        $code = ($health['status'] === 'HEALTHY') ? 200 : 503;
        return $this->response->setStatusCode($code)->setJSON($health);
    }

    public function status()
    {
        $db = \Config\Database::connect();
        
        $totalTemuan = $db->tableExists('temuan') ? $db->table('temuan')->where('deleted_at IS NULL')->countAllResults() : 0;
        $totalWO     = $db->tableExists('work_orders') ? $db->table('work_orders')->where('deleted_at IS NULL')->countAllResults() : 0;
        $totalAssets = $db->tableExists('assets') ? $db->table('assets')->where('deleted_at IS NULL')->countAllResults() : 0;
        $totalDocs   = $db->tableExists('documents') ? $db->table('documents')->where('deleted_at IS NULL')->countAllResults() : 0;
        $queueJobs   = $db->tableExists('background_jobs') ? $db->table('background_jobs')->where('status', 'PENDING')->countAllResults() : 0;

        return $this->response->setJSON([
            'system' => 'SIDAK TEJO Enterprise System',
            'release' => 'v1.0.0 Enterprise Production',
            'metrics' => [
                'total_temuan'      => $totalTemuan,
                'total_work_orders' => $totalWO,
                'total_assets'      => $totalAssets,
                'total_documents'   => $totalDocs,
                'pending_queue_jobs'=> $queueJobs,
            ],
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    public function optimizeDatabase()
    {
        $logs = DatabaseOptimizationService::optimizeIndexes();
        return $this->response->setJSON([
            'status' => 'SUCCESS',
            'message' => 'Database indexes optimized successfully',
            'logs' => $logs
        ]);
    }
}
