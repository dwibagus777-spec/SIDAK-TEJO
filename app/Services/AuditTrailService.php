<?php

namespace App\Services;

use App\Models\AuditLogModel;
use Config\Database;

class AuditTrailService
{
    private AuditLogModel $auditModel;

    public function __construct()
    {
        $this->auditModel = new AuditLogModel();
    }

    /**
     * Compute array diff between old data and new data
     */
    public function computeDiff(array $oldData, array $newData): array
    {
        $diff = [];
        foreach ($newData as $key => $newVal) {
            $oldVal = $oldData[$key] ?? null;
            if ($oldVal != $newVal) {
                $diff[$key] = [
                    'old' => $oldVal,
                    'new' => $newVal
                ];
            }
        }
        return $diff;
    }

    /**
     * Log Audit Trail with Time Machine versioning
     */
    public function logAudit(string $activity, ?string $detail = null, ?array $oldData = null, ?array $newData = null, ?int $temuanId = null, ?int $woId = null): bool
    {
        $session = session();
        $request = \Config\Services::request();

        $diff = ($oldData && $newData) ? $this->computeDiff($oldData, $newData) : null;

        // Get latest version number for temuan
        $versionNumber = 1;
        if ($temuanId) {
            $db = Database::connect();
            $maxVer = $db->table('audit_logs')->where('temuan_id', $temuanId)->selectMax('version_number')->get()->getRowArray();
            $versionNumber = ((int)($maxVer['version_number'] ?? 0)) + 1;
        }

        $logData = [
            'user_id'        => $session->get('user_id'),
            'username'       => $session->get('user_name') ?: 'Guest',
            'role'           => $session->get('user_role') ?: 'guest',
            'nip'            => $session->get('nip') ?: '',
            'nama_lengkap'   => $session->get('nama_pegawai') ?: ($session->get('user_name') ?: 'User'),
            'ulp_id'         => $session->get('user_ulp_id'),
            'temuan_id'      => $temuanId,
            'wo_id'          => $woId,
            'aktivitas'      => $activity,
            'detail'         => $detail,
            'data_lama_json' => $oldData ? json_encode($oldData) : null,
            'data_baru_json' => $newData ? json_encode($newData) : null,
            'diff_json'      => $diff ? json_encode($diff) : null,
            'ip_address'     => $request->getIPAddress(),
            'user_agent'     => (string)$request->getUserAgent(),
            'app_type'       => (str_contains(strtolower((string)$request->getUserAgent()), 'android')) ? 'APK' : 'WEB',
            'version_number' => $versionNumber,
            'created_at'     => date('Y-m-d H:i:s')
        ];

        try {
            return (bool)$this->auditModel->insert($logData);
        } catch (\Throwable $e) {
            log_message('error', '[AuditTrailService] Insert error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get Filtered Audit Logs with Pagination
     */
    public function getFilteredLogs(array $filters = [], int $limit = 50): array
    {
        $db = Database::connect();
        $builder = $db->table('audit_logs');
        $builder->orderBy('created_at', 'DESC');

        if (!empty($filters['username'])) {
            $builder->like('username', $filters['username']);
        }
        if (!empty($filters['aktivitas'])) {
            $builder->where('aktivitas', $filters['aktivitas']);
        }
        if (!empty($filters['temuan_id'])) {
            $builder->where('temuan_id', (int)$filters['temuan_id']);
        }

        $query = $builder->get($limit);
        return $query ? $query->getResultArray() : [];
    }

    /**
     * Get Time Machine versions for a specific Temuan ID
     */
    public function getTimeMachineVersions(int $temuanId): array
    {
        $db = Database::connect();
        $query = $db->table('audit_logs')
            ->where('temuan_id', $temuanId)
            ->orderBy('version_number', 'ASC')
            ->get();

        return $query ? $query->getResultArray() : [];
    }
}
