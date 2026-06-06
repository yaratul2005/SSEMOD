<?php
declare(strict_types=1);

namespace App\Services;

use App\FileStore\FileStore;
use App\FileStore\QueueStore;
use Exception;

class SSEService {
    public function __construct(
        private readonly array $config,
        private readonly FileStore $fileStore,
        private readonly QueueStore $queueStore
    ) {}

    /**
     * Disable output compression and buffering, set SSE response headers.
     */
    public function startHeaders(): void {
        // Disable output compression
        ini_set('zlib.output_compression', '0');
        ini_set('implicit_flush', '1');

        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }

        // Standard SSE Headers
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no'); // Disable buffering on Nginx/reverse proxies

        // End and clean all active output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        ob_implicit_flush(true);
        ignore_user_abort(true);
    }

    /**
     * Format and send an SSE message block.
     */
    public function sendEvent(string $event, string $data, ?string $id = null): void {
        if ($id !== null) {
            echo "id: {$id}\n";
        }
        echo "event: {$event}\n";
        // Support multi-line data payloads
        $lines = explode("\n", $data);
        foreach ($lines as $line) {
            echo "data: {$line}\n";
        }
        echo "\n";
        
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }

    /**
     * Send heartbeat tick to client to keep connection alive.
     */
    public function sendHeartbeat(): void {
        echo ": heartbeat\n\n";
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
    }

    /**
     * Run the SSE event loop.
     * 
     * @param string $type The stream type identifier
     * @param string $deviceProfile 'desktop'|'mobile'|'slow'
     * @param callable $tick Callback executed on each poll interval. Must return true to continue, false to break.
     * @param callable|null $cleanup Optional callback executed when user aborts connection.
     */
    public function stream(
        string $type,
        string $deviceProfile,
        callable $tick,
        ?callable $cleanup = null
    ): void {
        $this->startHeaders();

        // Load intervals based on device profile
        $profile = $this->config['devices'][$deviceProfile] ?? $this->config['devices']['desktop'];
        $heartbeatInterval = $profile['heartbeat'];
        $pollInterval = $profile['poll_interval'];

        $startTime = time();
        $lastHeartbeat = $startTime;
        $lastPoll = 0;
        $cycleCount = 0;

        // Send initial connection sync settings (Safari/Chrome handshake booster)
        $this->sendEvent('connected', json_encode([
            'heartbeat' => $heartbeatInterval,
            'poll_interval' => $pollInterval,
            'profile' => $deviceProfile,
            'stream' => $type,
            'timestamp' => $startTime
        ], JSON_THROW_ON_ERROR));

        while (true) {
            // 1. Check if the user closed the page/tab
            if (connection_aborted()) {
                if ($cleanup !== null) {
                    try {
                        $cleanup();
                    } catch (Exception $e) {
                        $this->logError("SSE cleanup error for type {$type}: " . $e->getMessage());
                    }
                }
                break;
            }

            $now = time();

            // 2. Perform periodic resource limits checks
            $cycleCount++;
            if ($cycleCount % $this->config['memory_check_cycle'] === 0) {
                if (memory_get_usage(true) >= $this->config['memory_ceiling']) {
                    $this->sendEvent('system', json_encode([
                        'action' => 'reconnect',
                        'reason' => 'memory_ceiling'
                    ], JSON_THROW_ON_ERROR));
                    break;
                }
            }

            // 3. Graceful self-restart token before host timeout (prevents abrupt Gateway Timeout errors)
            if (($now - $startTime) >= $this->config['max_execution_time']) {
                $this->sendEvent('system', json_encode([
                    'action' => 'reconnect',
                    'reason' => 'time_limit'
                ], JSON_THROW_ON_ERROR));
                break;
            }

            // 4. Heartbeat
            if (($now - $lastHeartbeat) >= $heartbeatInterval) {
                $this->sendHeartbeat();
                $lastHeartbeat = $now;
            }

            // 5. Query changes on poll interval
            if (($now - $lastPoll) >= $pollInterval) {
                $lastPoll = $now;
                
                try {
                    $shouldContinue = $tick($this, $now);
                    if ($shouldContinue === false) {
                        break;
                    }
                } catch (Exception $e) {
                    $this->sendEvent('error', json_encode(['message' => $e->getMessage()]));
                    $this->logError("SSE tick error in {$type}: " . $e->getMessage());
                    break;
                }
            }

            // High-precision sleep (1 second interval) to conserve CPU
            sleep(1);
        }
    }

    /**
     * Log errors safely.
     */
    private function logError(string $message): void {
        $logFile = $this->fileStore->getPath('../logs/error.log');
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        error_log("[" . date('Y-m-d H:i:s') . "] {$message}\n", 3, $logFile);
    }
}
