<?php

namespace App\Services\NotificationDrivers;

class TelegramDriver
{
    private string $botToken;

    public function __construct(?string $botToken = null)
    {
        $this->botToken = $botToken ?: (getenv('TELEGRAM_BOT_TOKEN') ?: 'demo_bot_token');
    }

    public function sendMessage(string $chatId, string $message): bool
    {
        $url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";
        $data = [
            'chat_id'    => $chatId,
            'text'       => $message,
            'parse_mode' => 'HTML',
        ];

        $options = [
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data),
                'timeout' => 5,
            ],
        ];

        $context = stream_context_create($options);
        $result = @file_get_contents($url, false, $context);

        if ($result === false) {
            log_message('error', '[TelegramDriver] Gagal mengirim pesan ke Telegram: ' . $chatId);
            return false;
        }

        return true;
    }
}
