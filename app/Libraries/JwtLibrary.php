<?php

namespace App\Libraries;

class JwtLibrary
{
    private static string $secretKey = 'SIDAK_TEJO_FLUTTER_JWT_SECRET_2026_VERY_SECURE_KEY';

    /**
     * Encode payload array ke token JWT (HS256)
     */
    public static function encode(array $payload, int $ttlSeconds = 604800): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        
        $iat = time();
        $exp = $iat + $ttlSeconds;
        
        $payload['iat'] = $iat;
        $payload['exp'] = $exp;

        $base64Header = self::base64UrlEncode(json_encode($header));
        $base64Payload = self::base64UrlEncode(json_encode($payload));

        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, self::$secretKey, true);
        $base64Signature = self::base64UrlEncode($signature);

        return $base64Header . "." . $base64Payload . "." . $base64Signature;
    }

    /**
     * Decode dan validasi token JWT
     */
    public static function decode(string $token): ?array
    {
        $parts = explode('.', trim($token));
        if (count($parts) !== 3) {
            return null;
        }

        list($base64Header, $base64Payload, $base64Signature) = $parts;

        $expectedSig = self::base64UrlEncode(hash_hmac('sha256', $base64Header . "." . $base64Payload, self::$secretKey, true));

        if (!hash_equals($expectedSig, $base64Signature)) {
            return null; // Token signature mismatch
        }

        $payload = json_decode(self::base64UrlDecode($base64Payload), true);
        if (!$payload || !isset($payload['exp'])) {
            return null;
        }

        if (time() >= $payload['exp']) {
            return null; // Expired token
        }

        return $payload;
    }

    private static function base64UrlEncode(string $data): string
    {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }

    private static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(str_replace(['-', '_'], ['+', '/'], $data));
    }
}
