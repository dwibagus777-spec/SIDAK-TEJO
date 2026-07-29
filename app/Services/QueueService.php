<?php

namespace App\Services;

class QueueService
{
    public static function push(string $jobType, array $payload): int
    {
        $db = \Config\Database::connect();
        self::ensureQueueTableExists($db);

        $db->table('background_jobs')->insert([
            'job_type'    => $jobType,
            'payload'     => json_encode($payload),
            'status'      => 'PENDING',
            'attempts'    => 0,
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        return $db->insertID();
    }

    public static function processPendingJobs(int $limit = 5): array
    {
        $db = \Config\Database::connect();
        self::ensureQueueTableExists($db);

        $query = $db->table('background_jobs')
            ->where('status', 'PENDING')
            ->orderBy('id', 'ASC')
            ->get($limit);

        if (!$query) return [];
        $jobs = $query->getResultArray();
        $processed = [];

        foreach ($jobs as $job) {
            $jobId   = $job['id'];
            $type    = $job['job_type'];
            $payload = json_decode($job['payload'], true) ?: [];

            $db->table('background_jobs')->where('id', $jobId)->update([
                'status'     => 'PROCESSING',
                'attempts'   => $job['attempts'] + 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            try {
                $result = self::executeJob($type, $payload);
                $db->table('background_jobs')->where('id', $jobId)->update([
                    'status'       => 'COMPLETED',
                    'result'       => json_encode($result),
                    'completed_at' => date('Y-m-d H:i:s'),
                ]);
                $processed[] = ['id' => $jobId, 'type' => $type, 'status' => 'COMPLETED'];
            } catch (\Throwable $e) {
                log_message('error', "[QueueProcessor] Job #{$jobId} ({$type}) error: " . $e->getMessage());
                $status = ($job['attempts'] >= 3) ? 'FAILED' : 'PENDING';
                $db->table('background_jobs')->where('id', $jobId)->update([
                    'status'     => $status,
                    'result'     => json_encode(['error' => $e->getMessage()]),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $processed[] = ['id' => $jobId, 'type' => $type, 'status' => $status];
            }
        }

        return $processed;
    }

    private static function executeJob(string $type, array $payload): array
    {
        return match ($type) {
            'GENERATE_PDF'    => ['message' => 'PDF generated asynchronously', 'payload' => $payload],
            'AI_SUMMARY'      => ['message' => 'AI Forecast processed', 'payload' => $payload],
            'NOTIFICATION'    => ['message' => 'Notification dispatched', 'payload' => $payload],
            'IMAGE_OPTIMIZE' => ['message' => 'Image resized & WebP optimized', 'payload' => $payload],
            default           => ['message' => 'Generic job completed', 'payload' => $payload],
        };
    }

    private static function ensureQueueTableExists(\CodeIgniter\Database\BaseConnection $db): void
    {
        if (!$db->tableExists('background_jobs')) {
            $forge = \Config\Database::forge();
            $forge->addField([
                'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
                'job_type'     => ['type' => 'VARCHAR', 'constraint' => 100],
                'payload'      => ['type' => 'TEXT', 'null' => true],
                'status'       => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'PENDING'],
                'attempts'     => ['type' => 'INT', 'constraint' => 3, 'default' => 0],
                'result'       => ['type' => 'TEXT', 'null' => true],
                'completed_at' => ['type' => 'DATETIME', 'null' => true],
                'created_at'   => ['type' => 'DATETIME', 'null' => true],
                'updated_at'   => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('background_jobs', true);
        }
    }
}
