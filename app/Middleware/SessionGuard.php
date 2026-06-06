<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Services\SessionService;
use App\Database\Connection;

class SessionGuard {
    public function __construct(
        private readonly SessionService $sessionService,
        private readonly Connection $dbConnection
    ) {}

    /**
     * Start user session and check permissions.
     */
    public function handle(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start([
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax',
                'use_strict_mode' => true,
            ]);
        }

        // Auto-login via remember-me token if no active session
        if (empty($_SESSION['user_type']) && isset($_COOKIE['remember_token'])) {
            $token = $_COOKIE['remember_token'];
            try {
                $pdo = $this->dbConnection->getPdo();
                $stmt = $pdo->prepare('SELECT anonymous_id, user_type, verified, is_banned FROM users WHERE remember_token = ? AND token_expires > NOW()');
                $stmt->execute([$token]);
                $user = $stmt->fetch();
                if ($user && (int)$user['is_banned'] === 0) {
                    $_SESSION['user_type'] = $user['user_type'];
                    $_SESSION['user_id'] = $user['anonymous_id'];
                    $_SESSION['verified'] = (int)$user['verified'] === 1;
                }
            } catch (\Exception $e) {
                // Ignore
            } finally {
                $this->dbConnection->disconnect();
            }
        }

        // Start session tracking
        $session = $this->sessionService->start();

        // Normalize path relative to subdirectories
        $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($requestUri, PHP_URL_PATH) ?: '/';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $baseDir = dirname($scriptName);
        if ($baseDir !== '/' && $baseDir !== '\\' && str_starts_with($path, $baseDir)) {
            $path = substr($path, strlen($baseDir));
        }
        if ($path === '' || $path === false) {
            $path = '/';
        }

        $publicRoutes = [
            '/guest/start',
            '/auth/register',
            '/auth/send-verification',
            '/auth/verify-otp',
            '/auth/complete-profile',
            '/auth/login',
            '/auth/logout',
            '/api/check-username'
        ];

        // Dynamic avatar serving is public
        if (str_starts_with($path, '/avatar/')) {
            return;
        }

        if (in_array($path, $publicRoutes, true)) {
            return;
        }

        // If no active session, redirect page requests or abort API requests
        if (empty($_SESSION['user_type'])) {
            if ($path === '/' || $path === '/arena') {
                return; // Let through to page, view will display modal
            }

            if (str_starts_with($path, '/api/') || str_starts_with($path, '/stream/') || str_starts_with($path, '/chat/') || str_starts_with($path, '/queue/') || str_starts_with($path, '/profile/')) {
                throw new MiddlewareException("Active session required.", 401);
            }

            $redirectUrl = rtrim($baseDir, '/\\') ?: '/';
            header('Location: ' . $redirectUrl);
            exit;
        }

        // Check if banned
        if ($session['user_id'] !== '' && $this->sessionService->isBanned($session['user_id'])) {
            throw new MiddlewareException("Your session has been suspended due to abuse.", 403);
        }
    }
}
