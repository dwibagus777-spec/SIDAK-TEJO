<?php

namespace App\Services\NotificationDrivers;

interface WhatsAppDriverInterface
{
    /**
     * Send WhatsApp Message via Provider API
     *
     * @param string $target Number or Group ID
     * @param string $message Text content
     * @return bool Success status
     */
    public function sendMessage(string $target, string $message): bool;
}
