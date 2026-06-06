<?php
declare(strict_types=1);

namespace App\Helpers;

class Sanitizer {
    /**
     * Sanitize string to prevent XSS.
     */
    public static function clean(string $input): string {
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
