<?php

namespace App\Repositories;

use App\Models\NotificationModel;
use CodeIgniter\Database\BaseResult;

class NotificationRepository
{
    private NotificationModel $model;

    public function __construct()
    {
        $this->model = new NotificationModel();
    }

    public function getUserNotifications(?int $userId, int $limit = 30): array
    {
        try {
            $db = \Config\Database::connect();
            $builder = $db->table('notifications');
            if ($userId) {
                $builder->groupStart()->where('user_id', $userId)->orWhere('user_id IS NULL')->groupEnd();
            }
            $query = $builder->orderBy('id', 'DESC')->get($limit);
            if ($query === false || !($query instanceof BaseResult)) {
                log_message('error', '[NotificationRepository::getUserNotifications] Query gagal | ' . json_encode($db->error()));
                return [];
            }
            return $query->getResultArray();
        } catch (\Throwable $e) {
            log_message('error', '[NotificationRepository::getUserNotifications] Exception: ' . $e->getMessage());
            return [];
        }
    }

    public function getUnreadCount(?int $userId): int
    {
        try {
            $db = \Config\Database::connect();
            $builder = $db->table('notifications');
            if ($db->fieldExists('read_at', 'notifications')) {
                $builder->where('read_at IS NULL');
            } else {
                $builder->where('is_read', 0);
            }
            if ($userId) {
                $builder->groupStart()->where('user_id', $userId)->orWhere('user_id IS NULL')->groupEnd();
            }
            return $builder->countAllResults();
        } catch (\Throwable $e) {
            log_message('error', '[NotificationRepository::getUnreadCount] Exception: ' . $e->getMessage());
            return 0;
        }
    }

    public function markAllAsRead(?int $userId): bool
    {
        try {
            $db = \Config\Database::connect();
            $builder = $db->table('notifications');
            $updateData = ['is_read' => 1];
            if ($db->fieldExists('read_at', 'notifications')) {
                $updateData['read_at'] = date('Y-m-d H:i:s');
            }
            if ($userId) {
                $builder->where('user_id', $userId);
            }
            return $builder->update($updateData);
        } catch (\Throwable $e) {
            log_message('error', '[NotificationRepository::markAllAsRead] Exception: ' . $e->getMessage());
            return false;
        }
    }

    public function logNotification(array $data): bool
    {
        $db = \Config\Database::connect();
        return $db->table('notifications')->insert(array_merge($data, [
            'created_at' => date('Y-m-d H:i:s')
        ]));
    }

    public function getTemplates(): array
    {
        try {
            $db = \Config\Database::connect();
            $query = $db->table('notification_templates')->get();
            if ($query === false || !($query instanceof BaseResult)) {
                log_message('error', '[NotificationRepository::getTemplates] Query gagal | ' . json_encode($db->error()));
                return [];
            }
            return $query->getResultArray();
        } catch (\Throwable $e) {
            log_message('error', '[NotificationRepository::getTemplates] Exception: ' . $e->getMessage());
            return [];
        }
    }

    public function getRules(): array
    {
        try {
            $db = \Config\Database::connect();
            $query = $db->table('notification_rules')->get();
            if ($query === false || !($query instanceof BaseResult)) {
                log_message('error', '[NotificationRepository::getRules] Query gagal | ' . json_encode($db->error()));
                return [];
            }
            return $query->getResultArray();
        } catch (\Throwable $e) {
            log_message('error', '[NotificationRepository::getRules] Exception: ' . $e->getMessage());
            return [];
        }
    }

    public function getUserPreferences(int $userId): array
    {
        try {
            $db = \Config\Database::connect();
            $query = $db->table('user_notification_preferences')->where('user_id', $userId)->get();
            if ($query === false || !($query instanceof BaseResult)) {
                log_message('error', '[NotificationRepository::getUserPreferences] Query gagal | ' . json_encode($db->error()));
                return $this->getDefaultPreferences($userId);
            }
            $row = $query->getRowArray();
            return $row ?: $this->getDefaultPreferences($userId);
        } catch (\Throwable $e) {
            log_message('error', '[NotificationRepository::getUserPreferences] Exception: ' . $e->getMessage());
            return $this->getDefaultPreferences($userId);
        }
    }

    private function getDefaultPreferences(int $userId): array
    {
        return [
            'user_id'          => $userId,
            'push_enabled'     => 1,
            'wa_enabled'       => 1,
            'email_enabled'    => 1,
            'telegram_enabled' => 1,
            'voice_enabled'    => 1,
            'dnd_enabled'      => 0,
        ];
    }
}
