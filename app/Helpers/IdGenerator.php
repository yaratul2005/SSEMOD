<?php
declare(strict_types=1);

namespace App\Helpers;

class IdGenerator {
    /**
     * Generate unique anonymous user ID.
     */
    public static function generateUser(): string {
        return 'u_' . bin2hex(random_bytes(12));
    }

    /**
     * Generate unique room ID.
     */
    public static function generateRoom(): string {
        return 'r_' . bin2hex(random_bytes(16));
    }
}
