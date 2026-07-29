<?php

namespace App\Services;

use App\Services\NotificationDrivers\FonnteDriver;
use App\Services\NotificationDrivers\TelegramDriver;
use App\Services\NotificationDrivers\FcmDriver;
use App\Repositories\NotificationRepository;

class NotificationService
{
    private FonnteDriver $waDriver;
    private TelegramDriver $telegramDriver;
    private FcmDriver $fcmDriver;
    private NotificationRepository $repository;

    public function __construct()
    {
        $this->waDriver       = new FonnteDriver();
        $this->telegramDriver = new TelegramDriver();
        $this->fcmDriver      = new FcmDriver();
        $this->repository     = new NotificationRepository();
    }

    /**
     * Dispatch notification across multiple channels (Async Non-Blocking)
     */
    public function dispatchNotification(string $type, string $title, string $message, ?int $targetUserId = null, ?string $targetPhone = null): bool
    {
        // 1. In-App Notification (Always stored)
        $this->repository->logNotification([
            'user_id' => $targetUserId,
            'type'    => $type,
            'title'   => $title,
            'message' => $message,
            'channel' => 'IN_APP',
            'status'  => 'DELIVERED',
            'target'  => $targetUserId ? (string)$targetUserId : 'ALL',
        ]);

        // 2. WhatsApp Notification
        if ($targetPhone) {
            $this->waDriver->sendMessage($targetPhone, "🚨 *{$title}*\n\n{$message}\n\n_SIDAK TEJO Enterprise System_");
        }

        // 3. Telegram Notification
        $telegramGroup = getenv('TELEGRAM_GROUP_ID') ?: '-100123456789';
        $this->telegramDriver->sendMessage($telegramGroup, "<b>{$title}</b>\n\n{$message}");

        // 4. Push Notification FCM
        $this->fcmDriver->sendPushNotification('/topics/all_officers', $title, $message);

        return true;
    }
}
