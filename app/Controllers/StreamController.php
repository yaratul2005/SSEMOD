<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\SSEService;
use App\Services\SessionService;
use App\Helpers\DeviceDetector;
use App\FileStore\FileStore;
use App\FileStore\QueueStore;
use App\FileStore\MessageBuffer;
use App\Services\PresenceService;

class StreamController {
    public function __construct(
        private readonly SSEService $sseService,
        private readonly SessionService $sessionService,
        private readonly DeviceDetector $deviceDetector,
        private readonly FileStore $fileStore,
        private readonly QueueStore $queueStore,
        private readonly MessageBuffer $messageBuffer,
        private readonly PresenceService $presenceService
    ) {}

    /**
     * Active chat message stream for matched pairs.
     */
    public function chat(?string $roomId = null): void {
        $userId = $_SESSION['user_id'] ?? '';
        if ($roomId === null || $roomId === '') {
            $roomId = $_GET['room_id'] ?? '';
        }

        $this->logConnection('chat', $userId);

        if ($userId === '' || $roomId === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Missing room or session parameters']);
            return;
        }

        // Verify that room exists and the requesting user is a participant
        $filename = "/rooms/{$roomId}.json";
        $room = $this->fileStore->readJson($filename);
        if ($room === null) {
            http_response_code(403);
            echo json_encode(['error' => 'Room not found']);
            return;
        }

        $isUserA = ($room['user_a'] ?? '') === $userId;
        $isUserB = ($room['user_b'] ?? '') === $userId;
        if (!$isUserA && !$isUserB) {
            http_response_code(403);
            echo json_encode(['error' => 'Unauthorized access to room']);
            return;
        }

        $device = $this->deviceDetector->detect();
        
        // Extract Last-Event-ID from query or HTTP headers
        $lastEventId = (int)($_GET['last_id'] ?? $_SERVER['HTTP_LAST_EVENT_ID'] ?? 0);
        $sentTyping = false;

        $this->sseService->stream('chat', $device, function(SSEService $sse, int $now) use ($roomId, $userId, &$lastEventId, $isUserA, &$sentTyping) {
            // Read fresh room data from FileStore
            $roomData = $this->fileStore->readJson("/rooms/{$roomId}.json");
            if ($roomData === null) {
                return false; // Terminate connection
            }

            // 1. Check for new messages
            $delta = $this->messageBuffer->getMessagesSince($roomId, $lastEventId);
            foreach ($delta['messages'] as $msg) {
                $sse->sendEvent('message', json_encode($msg), (string)$msg['id']);
                $lastEventId = $msg['id'];
            }

            // 2. Check typing status of the stranger
            $strangerPrefix = $isUserA ? 'user_b' : 'user_a';
            $typingKey = $strangerPrefix . '_typing_at';
            $strangerTypingAt = $roomData[$typingKey] ?? 0;
            
            $isTyping = ($now - $strangerTypingAt <= 2);
            if ($isTyping && !$sentTyping) {
                $sse->sendEvent('typing', json_encode(['typing' => true]));
                $sentTyping = true;
            } elseif (!$isTyping && $sentTyping) {
                $sse->sendEvent('typing', json_encode(['typing' => false]));
                $sentTyping = false;
            }

            // 3. Check if room is closed
            if ($delta['closed'] === true) {
                $sse->sendEvent('room_closed', json_encode([
                    'closed_by' => $delta['closed_by'],
                    'reason' => 'disconnected'
                ]));
                return false; // Terminate connection
            }

            return true;
        }, function() use ($roomId, $userId) {
            // Cleanup: Connection aborted (user closed browser tab or lost connection)
            $this->messageBuffer->closeRoom($roomId, $userId);
            
            $state = $this->sessionService->getUserState($userId);
            $state['status'] = 'idle';
            $state['room_id'] = null;
            $this->sessionService->updateUserState($userId, $state);
        });
    }

    /**
     * Waiting room status & match events.
     */
    public function queue(): void {
        $userId = $_SESSION['user_id'] ?? '';
        $this->logConnection('queue', $userId);
        if ($userId === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid session']);
            return;
        }

        $device = $this->deviceDetector->detect();

        $this->sseService->stream('queue', $device, function(SSEService $sse, int $now) use ($userId) {
            // Touch user presence to keep online status
            $this->sessionService->touchPresence($userId);

            $state = $this->sessionService->getUserState($userId);
            if ($state['status'] === 'matched') {
                $sse->sendEvent('matched', json_encode(['room_id' => $state['room_id']]));
                return false; // Exit queue so the user can navigate to the chat screen
            }

            // Send queue details & wait estimates
            $queue = $this->queueStore->getAll();
            $waitEstimate = max(2, (int)(count($queue) * 1.5));
            $sse->sendEvent('wait_status', json_encode([
                'queue_length' => count($queue),
                'estimated_wait_seconds' => $waitEstimate,
            ]));

            return true;
        }, function() use ($userId) {
            // Cleanup: user disconnected/navigated away from queue
            $this->queueStore->leave($userId);
            
            $state = $this->sessionService->getUserState($userId);
            if ($state['status'] === 'waiting') {
                $state['status'] = 'idle';
                $this->sessionService->updateUserState($userId, $state);
            }
        });
    }

    /**
     * Online active presence counter.
     */
    public function presence(): void {
        $userId = $_SESSION['user_id'] ?? '';
        $this->logConnection('presence', $userId);
        $device = $this->deviceDetector->detect();
        $lastCount = -1;

        $this->sseService->stream('presence', $device, function(SSEService $sse, int $now) use (&$lastCount) {
            if ($userId !== '') {
                $this->sessionService->touchPresence($userId);
            }

            $count = $this->sessionService->getOnlineCount();
            if ($count !== $lastCount) {
                $sse->sendEvent('presence', json_encode(['count' => $count]));
                $lastCount = $count;
            }

            return true;
        });
    }

    /**
     * System broadcast & ban messages stream.
     */
    public function system(): void {
        $userId = $_SESSION['user_id'] ?? '';
        $this->logConnection('system', $userId);
        if ($userId === '') {
            http_response_code(400);
            return;
        }

        $device = $this->deviceDetector->detect();

        $this->sseService->stream('system', $device, function(SSEService $sse, int $now) use ($userId) {
            $state = $this->sessionService->getUserState($userId);
            if ($state['banned'] === true) {
                $sse->sendEvent('ban', json_encode(['message' => 'Your session has been banned.']));
                return false;
            }
            return true;
        });
    }

    /**
     * SSE stream for live Arena online presence updates and chat requests.
     */
    public function arenaPresence(): void {
        $userId = $_SESSION['user_id'] ?? '';
        $this->logConnection('arena-presence', $userId);
        
        $device = $this->deviceDetector->detect();
        $previousActiveUsers = [];

        $this->sseService->stream('arena-presence', $device, function(SSEService $sse, int $now) use (&$previousActiveUsers, $userId) {
            // 1. Refresh active user presence
            if ($userId !== '') {
                $state = $this->sessionService->getUserState($userId);
                $this->presenceService->touch($userId, $state);
            }

            // 2. Fetch current active list
            $currentUsers = $this->presenceService->getActiveUsers();

            // 3. Compute joined/left deltas
            $joined = [];
            $left = [];

            foreach ($currentUsers as $uid => $user) {
                // Skip the current user
                if ($uid === $userId) {
                    continue;
                }
                if (!isset($previousActiveUsers[$uid])) {
                    $joined[] = $user;
                } elseif (($previousActiveUsers[$uid]['online'] ?? false) !== ($user['online'] ?? false)) {
                    // Status changed
                    $joined[] = $user;
                }
            }

            foreach ($previousActiveUsers as $uid => $user) {
                if ($uid === $userId) {
                    continue;
                }
                if (!isset($currentUsers[$uid])) {
                    $left[] = $uid;
                }
            }

            // Store current state for next tick
            $previousActiveUsers = $currentUsers;

            // 4. Send event if there are changes or if this is the first tick (to initialize list)
            static $firstTick = true;
            if ($firstTick || count($joined) > 0 || count($left) > 0) {
                $firstTick = false;
                $total = count($currentUsers);
                $sse->sendEvent('presence', json_encode([
                    'joined' => $joined,
                    'left' => $left,
                    'total' => $total
                ], JSON_THROW_ON_ERROR));
            }

            // 5. Check if there are any pending chat requests for this user
            if ($userId !== '') {
                $requestFilename = "/requests/{$userId}.json";
                $requests = $this->fileStore->readJson($requestFilename);
                if ($requests !== null && count($requests) > 0) {
                    foreach ($requests as $req) {
                        $fromState = $this->sessionService->getUserState($req['from']);
                        $sse->sendEvent('chat_request', json_encode([
                            'from' => $req['from'],
                            'from_username' => $fromState['username'] ?? 'stranger',
                            'room_id' => $req['room_id']
                        ], JSON_THROW_ON_ERROR));
                    }
                    // Clear requests
                    $this->fileStore->writeJson($requestFilename, []);
                }
            }

            return true;
        });
    }

    /**
     * Log connection startup to connections.log.
     */
    private function logConnection(string $type, string $userId): void {
        $logFile = $this->fileStore->getPath('../logs/connections.log');
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $ipHash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');
        $timestamp = date('Y-m-d H:i:s');
        error_log("[{$timestamp}] Connection: [{$type}] User: {$userId} IP Hash: {$ipHash}\n", 3, $logFile);
    }
}
