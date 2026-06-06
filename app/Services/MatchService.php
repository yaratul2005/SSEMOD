<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use App\FileStore\FileStore;
use App\FileStore\QueueStore;
use App\Helpers\IdGenerator;
use Exception;

class MatchService {
    public function __construct(
        private readonly QueueStore $queueStore,
        private readonly FileStore $fileStore,
        private readonly Connection $dbConnection
    ) {}

    /**
     * Add a user to the matchmaking queue and run matching pass.
     */
    public function joinQueue(string $userId, array $interests): array {
        // 1. Write user to FileStore waiting queue
        $this->queueStore->join($userId, $interests);

        // 2. Persist queue status in MySQL
        $this->persistQueueJoin($userId, $interests);

        // 3. Update cached user state status to 'waiting'
        $userFilename = "/users/{$userId}.json";
        $state = $this->fileStore->readJson($userFilename) ?? [
            'user_id' => $userId,
            'banned' => false,
        ];
        $state['status'] = 'waiting';
        $state['room_id'] = null;
        $state['last_seen'] = time();
        $this->fileStore->writeJson($userFilename, $state);

        // 4. Try to find an active match
        $match = $this->findMatch($userId, $interests);
        if ($match !== null) {
            return [
                'status' => 'matched',
                'room_id' => $match['room_id'],
            ];
        }

        return [
            'status' => 'waiting',
        ];
    }

    /**
     * Remove user from the matchmaking queue.
     */
    public function leaveQueue(string $userId): void {
        // 1. Remove from FileStore queue
        $this->queueStore->leave($userId);

        // 2. Persist update in MySQL queue status
        try {
            $pdo = $this->dbConnection->getPdo();
            $stmt = $pdo->prepare("UPDATE queue SET status = 'left' WHERE user_id = ? AND status = 'waiting'");
            $stmt->execute([$userId]);
        } catch (Exception $e) {
            $this->logError("DB leave queue error: " . $e->getMessage());
        } finally {
            $this->dbConnection->disconnect();
        }

        // 3. Reset cached user status to 'idle'
        $userFilename = "/users/{$userId}.json";
        $state = $this->fileStore->readJson($userFilename);
        if ($state !== null && $state['status'] === 'waiting') {
            $state['status'] = 'idle';
            $this->fileStore->writeJson($userFilename, $state);
        }
    }

    /**
     * Match algorithm prioritizing shared interests with FIFO fallback.
     */
    private function findMatch(string $userId, array $interests): ?array {
        $queue = $this->queueStore->getAll();
        $userB = null;

        // Try to match based on case-insensitive intersection of interests
        if (!empty($interests)) {
            foreach ($queue as $item) {
                if ($item['user_id'] === $userId) {
                    continue;
                }
                $otherInterests = $item['interests'] ?? [];
                $intersection = array_intersect(
                    array_map('strtolower', $interests),
                    array_map('strtolower', $otherInterests)
                );
                if (count($intersection) > 0) {
                    $userB = $item;
                    break;
                }
            }
        }

        // FIFO fallback: select oldest waiting stranger
        if ($userB === null) {
            foreach ($queue as $item) {
                if ($item['user_id'] === $userId) {
                    continue;
                }
                $userB = $item;
                break;
            }
        }

        // If a candidate stranger is found, attempt to pair atomically
        if ($userB !== null) {
            $userBId = $userB['user_id'];
            
            $popped = $this->queueStore->popMatch($userId, $userBId);
            if ($popped) {
                $roomId = IdGenerator::generateRoom();

                // Create active room cache
                $roomData = [
                    'room_id' => $roomId,
                    'user_a' => $userId,
                    'user_b' => $userBId,
                    'messages' => [],
                    'last_event_id' => 0,
                    'closed' => false,
                    'closed_by' => null,
                    'user_a_typing_at' => 0,
                    'user_b_typing_at' => 0,
                ];

                $this->fileStore->writeJson("/rooms/{$roomId}.json", $roomData);

                // Update participant A status
                $stateA = $this->fileStore->readJson("/users/{$userId}.json") ?? [];
                $stateA['status'] = 'matched';
                $stateA['room_id'] = $roomId;
                $this->fileStore->writeJson("/users/{$userId}.json", $stateA);

                // Update participant B status
                $stateB = $this->fileStore->readJson("/users/{$userBId}.json") ?? [];
                $stateB['status'] = 'matched';
                $stateB['room_id'] = $roomId;
                $this->fileStore->writeJson("/users/{$userBId}.json", $stateB);

                // Record active match in MySQL database
                $this->persistMatch($roomId, $userId, $userBId);

                return [
                    'room_id' => $roomId,
                    'stranger_id' => $userBId,
                ];
            }
        }

        return null;
    }

    /**
     * Clean old waiting references and record new join in DB.
     */
    private function persistQueueJoin(string $userId, array $interests): void {
        try {
            $pdo = $this->dbConnection->getPdo();
            
            $stmt = $pdo->prepare("UPDATE queue SET status = 'left' WHERE user_id = ? AND status = 'waiting'");
            $stmt->execute([$userId]);

            $interestsJson = json_encode($interests);
            $stmt = $pdo->prepare('INSERT INTO queue (user_id, waiting_since, interests, status) VALUES (?, NOW(), ?, ?)');
            $stmt->execute([$userId, $interestsJson, 'waiting']);
        } catch (Exception $e) {
            $this->logError("DB queue join error: " . $e->getMessage());
        } finally {
            $this->dbConnection->disconnect();
        }
    }

    /**
     * Save active room in DB and mark queue statuses as matched.
     */
    private function persistMatch(string $roomId, string $userA, string $userB): void {
        try {
            $pdo = $this->dbConnection->getPdo();
            
            $stmt = $pdo->prepare('INSERT INTO rooms (room_id, user_a_id, user_b_id, created_at, status) VALUES (?, ?, ?, NOW(), ?)');
            $stmt->execute([$roomId, $userA, $userB, 'active']);

            $stmt = $pdo->prepare("UPDATE queue SET status = 'matched' WHERE user_id IN (?, ?) AND status = 'waiting'");
            $stmt->execute([$userA, $userB]);
        } catch (Exception $e) {
            $this->logError("DB match saving error: " . $e->getMessage());
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
}
