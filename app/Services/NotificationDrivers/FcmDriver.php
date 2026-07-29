<?php

namespace App\Services\NotificationDrivers;

class FcmDriver
{
    private string $serverKey;

    public function __construct(?string $serverKey = null)
    {
        $this->serverKey = $serverKey ?: (getenv('FCM_SERVER_KEY') ?: 'demo_fcm_server_key');
    }

    public function sendPushNotification(string $tokenOrTopic, string $title, string $body, array $data = []): bool
    {
        $url = 'https://fcm.googleapis.com/fcm/send';

        $payload = [
            'to'           => $tokenOrTopic,
            'notification' => [
                'title' => $title,
                'body'  => $body,
                'sound' => 'default',
            ],
            'data' => $data,
        ];

        $headers = [
            'Authorization: key=' . $this->serverKey,
            'Content-Type: application/json',
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        $result = curl_exec($ch);
        curl_close($ch);

        return $result !== false;
    }
}
