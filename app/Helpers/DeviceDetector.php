<?php
declare(strict_types=1);

namespace App\Helpers;

class DeviceDetector {
    /**
     * Detect client device/network profile: 'desktop', 'mobile', or 'slow'.
     */
    public function detect(): string {
        // 1. Check Network Effective Connection Type (Client Hints)
        $ect = $_SERVER['HTTP_ECT'] ?? $_SERVER['HTTP_X_ECT'] ?? '';
        if (in_array(strtolower($ect), ['slow-2g', '2g', '3g'], true)) {
            return 'slow';
        }

        // 2. Check Sec-CH-UA-Mobile Hint (?1 is mobile)
        $mobileHint = $_SERVER['HTTP_SEC_CH_UA_MOBILE'] ?? '';
        if ($mobileHint === '?1') {
            return 'mobile';
        }

        // 3. Fallback to parsing User-Agent
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if ($this->isMobileUserAgent($ua)) {
            return 'mobile';
        }

        return 'desktop';
    }

    /**
     * Check if user agent string matches common mobile patterns.
     */
    private function isMobileUserAgent(string $ua): bool {
        $ua = strtolower($ua);
        return str_contains($ua, 'mobi') 
            || str_contains($ua, 'android') 
            || str_contains($ua, 'iphone') 
            || str_contains($ua, 'ipad') 
            || str_contains($ua, 'ipod') 
            || str_contains($ua, 'webos') 
            || str_contains($ua, 'blackberry') 
            || str_contains($ua, 'iemobile') 
            || str_contains($ua, 'opera mini');
    }
}
