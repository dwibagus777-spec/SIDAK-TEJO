<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Services\BackgroundJobQueue;

/**
 * Queue Worker Controller
 * Hit via a scheduled Hostinger cron: GET /queue/run?token=SECRET
 * Or called automatically after-response via Events.
 */
class QueueWorker extends BaseController
{
    public function run(): void
    {
        // Simple HMAC security token check
        $expected = md5(env('app.secretKey', 'sidak-tejo-2025'));
        $token = $this->request->getGet('token') ?? '';

        if (!hash_equals($expected, $token)) {
            http_response_code(403);
            echo json_encode(['status' => 'forbidden']);
            return;
        }

        $queue = new BackgroundJobQueue();
        $processed = $queue->processPending(10);

        echo json_encode([
            'status'    => 'ok',
            'processed' => $processed,
            'pending'   => $queue->pendingCount(),
            'time'      => date('Y-m-d H:i:s'),
        ]);
    }

    public function status(): void
    {
        if (!session()->has('user_id')) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            return;
        }

        $queue = new BackgroundJobQueue();
        echo json_encode([
            'pending' => $queue->pendingCount(),
            'time'    => date('Y-m-d H:i:s'),
        ]);
    }
}
