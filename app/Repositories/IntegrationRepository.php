<?php

namespace App\Repositories;

use App\Models\IntegrationModel;
use CodeIgniter\Database\BaseResult;

class IntegrationRepository
{
    private IntegrationModel $model;

    public function __construct()
    {
        $this->model = new IntegrationModel();
    }

    public function logApiRequest(array $data): bool
    {
        $db = \Config\Database::connect();
        return $db->table('api_logs')->insert(array_merge($data, ['created_at' => date('Y-m-d H:i:s')]));
    }

    public function getApiLogs(int $limit = 50): array
    {
        try {
            $db = \Config\Database::connect();
            $query = $db->table('api_logs')->orderBy('id', 'DESC')->get($limit);
            if ($query === false || !($query instanceof BaseResult)) {
                log_message('error', '[IntegrationRepository::getApiLogs] Query gagal');
                return [];
            }
            return $query->getResultArray();
        } catch (\Throwable $e) {
            log_message('error', '[IntegrationRepository::getApiLogs] Exception: ' . $e->getMessage());
            return [];
        }
    }

    public function getApiDashboardStats(): array
    {
        $db = \Config\Database::connect();
        $total   = $db->table('api_logs')->countAllResults();
        $success = $db->table('api_logs')->where('status_code >=', 200)->where('status_code <', 400)->countAllResults();
        $failed  = $db->table('api_logs')->where('status_code >=', 400)->countAllResults();

        $avgLatency = 0;
        try {
            $query = $db->table('api_logs')->selectAvg('duration_ms', 'avg_ms')->get();
            if ($query && ($query instanceof BaseResult)) {
                $row = $query->getRowArray();
                $avgLatency = round((float)($row['avg_ms'] ?? 0), 2);
            }
        } catch (\Throwable $e) {
            log_message('error', '[IntegrationRepository::getApiDashboardStats] ' . $e->getMessage());
        }

        return [
            'total_requests' => $total,
            'success'        => $success,
            'failed'         => $failed,
            'avg_latency_ms' => $avgLatency,
        ];
    }

    public function getApiKeys(): array
    {
        try {
            $db = \Config\Database::connect();
            $query = $db->table('api_keys')->orderBy('id', 'DESC')->get();
            if ($query === false || !($query instanceof BaseResult)) return [];
            return $query->getResultArray();
        } catch (\Throwable $e) {
            log_message('error', '[IntegrationRepository::getApiKeys] Exception: ' . $e->getMessage());
            return [];
        }
    }

    public function validateApiKey(string $key): ?array
    {
        try {
            $db = \Config\Database::connect();
            $query = $db->table('api_keys')->where('api_key', $key)->where('is_active', 1)->get();
            if ($query === false || !($query instanceof BaseResult)) return null;
            $row = $query->getRowArray();
            if ($row) {
                $db->table('api_keys')->where('id', $row['id'])->update(['last_used_at' => date('Y-m-d H:i:s')]);
            }
            return $row ?: null;
        } catch (\Throwable $e) {
            log_message('error', '[IntegrationRepository::validateApiKey] Exception: ' . $e->getMessage());
            return null;
        }
    }

    public function getWebhooks(): array
    {
        try {
            $db = \Config\Database::connect();
            $query = $db->table('webhooks')->orderBy('id', 'DESC')->get();
            if ($query === false || !($query instanceof BaseResult)) return [];
            return $query->getResultArray();
        } catch (\Throwable $e) {
            log_message('error', '[IntegrationRepository::getWebhooks] Exception: ' . $e->getMessage());
            return [];
        }
    }

    public function getWebhooksByEvent(string $event): array
    {
        try {
            $db = \Config\Database::connect();
            $query = $db->table('webhooks')->where('event', $event)->where('is_active', 1)->get();
            if ($query === false || !($query instanceof BaseResult)) return [];
            return $query->getResultArray();
        } catch (\Throwable $e) {
            log_message('error', '[IntegrationRepository::getWebhooksByEvent] Exception: ' . $e->getMessage());
            return [];
        }
    }

    public function logWebhookAttempt(array $data): bool
    {
        $db = \Config\Database::connect();
        return $db->table('webhook_logs')->insert(array_merge($data, ['created_at' => date('Y-m-d H:i:s')]));
    }
}
