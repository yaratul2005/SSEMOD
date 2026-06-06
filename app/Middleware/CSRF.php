<?php
declare(strict_types=1);

namespace App\Middleware;

class CSRF {
    /**
     * Get or generate the active session's CSRF token.
     */
    public static function getToken(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start([
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax',
                'use_strict_mode' => true,
            ]);
        }
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Handle incoming requests, validating CSRF headers for POST endpoints.
     */
    public function handle(): void {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
        $sessionToken = $_SESSION['csrf_token'] ?? '';

        if ($sessionToken === '' || !hash_equals($sessionToken, $token)) {
            throw new MiddlewareException("Security validation failed (CSRF token mismatch).", 403);
        }
    }
}
