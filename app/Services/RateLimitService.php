<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use App\FileStore\FileStore;
use Exception;

class RateLimitService {
    public function __construct(
        private readonly array $limitsConfig,
        private readonly FileStore $fileStore,
        private readonly Connection $dbConnection
    ) {}

    /**
     * Check if the user is within limits for a specific action, and increment the counter.
     */
    public function checkAndIncrement(string $userId, string $action): bool {
        // Map paths/actions to config keys
        $configKey = match($action) {
            '/chat/send', '/api/message/send' => 'messages',
            '/stream/chat', '/stream/direct/{room_id}', '/stream/queue', '/stream/presence', '/stream/arena-presence', '/stream/system' => 'connections',
            '/chat/report' => 'reports',
            '/queue/join', '/api/chat/start/{user_id}' => 'new_chats',
            default => null
        };

        if ($configKey === null) {
            return true; // No rate limits configured for this endpoint
        }

        $limit = $this->limitsConfig[$configKey];
        $maxRequests = $limit['max_requests'];
        $window = $limit['window'];

        $filename = "/ratelimits/{$userId}.json";
        $data = $this->fileStore->readJson($filename) ?? [];

        $now = time();
        $current = $data[$action] ?? ['count' => 0, 'window_start' => $now];

        // Reset window if elapsed
        if ($now - $current['window_start'] > $window) {
            $current['count'] = 1;
            $current['window_start'] = $now;
        } else {
            if ($current['count'] >= $maxRequests) {
                $this->logAbuse($userId, $action);
                return false; // Rate limit exceeded
            }
            $current['count']++;
        }

        $data[$action] = $current;
        $this->fileStore->writeJson($filename, $data);

        // Sync with MySQL
        $this->persistToDb($userId, $action, $current['count'], $current['window_start']);

        return true;
    }

    /**
     * Update rate limit data in the MySQL persistent table.
     */
    private function persistToDb(string $userId, string $action, int $count, int $windowStart): void {
        try {
            $pdo = $this->dbConnection->getPdo();
            $stmt = $pdo->prepare('
                INSERT INTO rate_limits (identifier, action, count, window_start) 
                VALUES (?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE count = VALUES(count), window_start = VALUES(window_start)
            ');
            $stmt->execute([$userId, $action, $count, $windowStart]);
        } catch (Exception $e) {
            $this->logError("DB rate limit write error: " . $e->getMessage());
        } finally {
            $this->dbConnection->disconnect();
        }
    }

    /**
     * Log errors.
     */
    private function logError(string $message): void {
        $logFile = $this->fileStore->getPath('../logs/error.log');
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        error_log("[" . date('Y-m-d H:i:s') . "] {$message}\n", 3, $logFile);
    }

    /**
     * Log abuse events to abuse.log.
     */
    private function logAbuse(string $userId, string $action): void {
        $logFile = $this->fileStore->getPath('../logs/abuse.log');
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $timestamp = date('Y-m-d H:i:s');
        error_log("[{$timestamp}] Abuse: User {$userId} exceeded rate limit for {$action}. IP: {$ip}\n", 3, $logFile);
    }
}
