<?php
declare(strict_types=1);

namespace App\Middleware;

use App\Services\RateLimitService;

class RateLimit {
    public function __construct(private readonly RateLimitService $rateLimitService) {}

    /**
     * Verify the rate limit for the requested action/path.
     */
    public function handle(string $action): void {
        $userId = $_SESSION['user_id'] ?? '';
        if ($userId === '') {
            return;
        }

        if (!$this->rateLimitService->checkAndIncrement($userId, $action)) {
            throw new MiddlewareException("Too many requests. Rate limit exceeded.", 429);
        }
    }
}
