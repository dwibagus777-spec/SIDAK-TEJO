<?php

namespace App\Services;

/**
 * Lightweight Background Job Queue — file-based, Hostinger Shared Hosting compatible.
 * No Redis, Beanstalkd, or PCntl needed.
 */
class BackgroundJobQueue
{
    private string $queueDir;

    public function __construct()
    {
        $this->queueDir = WRITEPATH . 'queue/';
        if (!is_dir($this->queueDir)) {
            @mkdir($this->queueDir, 0755, true);
        }
    }

    /**
     * Push a job to the queue file-store
     */
    public function push(string $jobType, array $payload = []): string
    {
        $jobId = uniqid('job_', true);
        $job = [
            'id'        => $jobId,
            'type'      => $jobType,
            'payload'   => $payload,
            'status'    => 'pending',
            'created_at'=> date('Y-m-d H:i:s'),
            'retries'   => 0,
        ];

        $file = $this->queueDir . $jobId . '.json';
        file_put_contents($file, json_encode($job), LOCK_EX);
        return $jobId;
    }

    /**
     * Process all pending jobs (called by a cron endpoint or after-response hook)
     */
    public function processPending(int $maxJobs = 5): int
    {
        $processed = 0;
        $files = glob($this->queueDir . '*.json');
        if (!$files) return 0;

        foreach ($files as $file) {
            if ($processed >= $maxJobs) break;

            $job = json_decode(file_get_contents($file), true);
            if (!$job || ($job['status'] ?? '') !== 'pending') continue;

            // Mark as processing
            $job['status'] = 'processing';
            file_put_contents($file, json_encode($job), LOCK_EX);

            try {
                $this->executeJob($job);
                @unlink($file); // Remove on success
                $processed++;
            } catch (\Throwable $e) {
                $job['status'] = 'failed';
                $job['error'] = $e->getMessage();
                $job['retries'] = ($job['retries'] ?? 0) + 1;
                if ($job['retries'] >= 3) {
                    $job['status'] = 'dead';
                }
                file_put_contents($file, json_encode($job), LOCK_EX);
                log_message('error', '[BGQueue] Job ' . $job['id'] . ' failed: ' . $e->getMessage());
            }
        }

        return $processed;
    }

    private function executeJob(array $job): void
    {
        $type = $job['type'] ?? '';
        $payload = $job['payload'] ?? [];

        switch ($type) {
            case 'GENERATE_THUMBNAIL':
                $svc = new ImageWatermarkService();
                $svc->generateResolutions($payload['file_path'] ?? '');
                break;

            case 'APPLY_WATERMARK':
                $svc = new ImageWatermarkService();
                $svc->applyWatermark($payload['file_path'] ?? '', $payload['metadata'] ?? []);
                break;

            case 'CACHE_CLEAN':
                if (function_exists('cache')) {
                    @cache()->clean();
                }
                break;

            default:
                log_message('info', '[BGQueue] Unknown job type: ' . $type);
        }
    }

    /**
     * Get pending job count
     */
    public function pendingCount(): int
    {
        $files = glob($this->queueDir . '*.json');
        if (!$files) return 0;

        $count = 0;
        foreach ($files as $f) {
            $job = json_decode(file_get_contents($f), true);
            if (($job['status'] ?? '') === 'pending') $count++;
        }
        return $count;
    }
}
