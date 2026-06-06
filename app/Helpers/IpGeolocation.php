<?php
declare(strict_types=1);

namespace App\Helpers;

class IpGeolocation {
    /**
     * Get country flag emoji from user IP.
     */
    public static function getFlagEmoji(string $ip): string {
        if ($ip === '127.0.0.1' || $ip === '::1' || str_starts_with($ip, '10.') || str_starts_with($ip, '192.168.')) {
            // Localhost / private network fallback
            return '🇺🇸';
        }

        try {
            $ctx = stream_context_create([
                'http' => [
                    'timeout' => 1.5, // Fast timeout (1.5 seconds)
                    'user_agent' => 'ChatArena'
                ]
            ]);
            $response = @file_get_contents("http://ip-api.com/json/" . urlencode($ip), false, $ctx);
            if ($response !== false) {
                $data = json_decode($response, true);
                if (isset($data['countryCode']) && strlen($data['countryCode']) === 2) {
                    return self::countryCodeToFlag($data['countryCode']);
                }
            }
        } catch (\Throwable) {
            // Ignore geolocation request failures
        }

        return '🏳️'; // Unknown flag fallback
    }

    /**
     * Convert 2-letter ISO country code to Unicode Flag Emoji.
     */
    private static function countryCodeToFlag(string $code): string {
        $code = strtoupper($code);
        $codePoints = [
            127397 + ord($code[0]),
            127397 + ord($code[1])
        ];
        return mb_chr($codePoints[0], 'UTF-8') . mb_chr($codePoints[1], 'UTF-8');
    }
}
