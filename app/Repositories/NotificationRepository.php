<?php

namespace App\Repositories;

use App\Models\NotificationModel;

class NotificationRepository
{
    private NotificationModel $model;

    public function __construct()
    {
        $this->model = new NotificationModel();
    }

    public function getUserNotifications(?int $userId, int $limit = 30): array
    {
        $db = \Config\Database::connect();
        $builder = $db->table('notifications');
        if ($userId) {
            $builder->where('user_id', $userId)->orWhere('user_id IS NULL');
        }
        return $builder->orderBy('id', 'DESC')->get($limit)->getResultArray();
    }

    public function getUnreadCount(?int $userId): int
    {
        $db = \Config\Database::connect();
        $builder = $db->table('notifications')->where('read_at IS NULL');
        if ($userId) {
            $builder->where('user_id', $userId)->orWhere('user_id IS NULL');
        }
        return $builder->countAllResults();
    }

    public function markAllAsRead(?int $userId): bool
    {
        $db = \Config\Database::connect();
        $builder = $db->table('notifications')->where('read_at IS NULL');
        if ($userId) {
            $builder->where('user_id', $userId);
        }
        return $builder->update(['read_at' => date('Y-m-d H:i:s')]);
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
        $db = \Config\Database::connect();
        return $db->table('notification_templates')->get()->getResultArray();
    }

    public function getRules(): array
    {
        $db = \Config\Database::connect();
        return $db->table('notification_rules')->get()->getResultArray();
    }

    public function getUserPreferences(int $userId): array
    {
        $db = \Config\Database::connect();
        $row = $db->table('user_notification_preferences')->where('user_id', $userId)->get()->getRowArray();
        if (!$row) {
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
        return $row;
    }
}
