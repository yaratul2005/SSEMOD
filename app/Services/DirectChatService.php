<?php
declare(strict_types=1);

namespace App\Services;

use App\FileStore\FileStore;
use App\Database\Connection;
use Exception;

class DirectChatService {
    public function __construct(
        private readonly FileStore $fileStore,
        private readonly Connection $dbConnection
    ) {}

    /**
     * Start or resume a direct chat room between User A and User B.
     */
    public function startChat(string $userA, string $userB): string {
        // Deterministic Room ID based on sorted user IDs
        $ids = [$userA, $userB];
        sort($ids);
        $roomId = 'd_' . md5($ids[0] . '_' . $ids[1]);

        $filename = "/rooms/{$roomId}.json";
        if (!$this->fileStore->exists($filename)) {
            $roomData = [
                'room_id' => $roomId,
                'user_a' => $userA,
                'user_b' => $userB,
                'messages' => [],
                'last_event_id' => 0,
                'closed' => false,
                'closed_by' => null,
                'user_a_typing_at' => 0,
                'user_b_typing_at' => 0,
                'is_direct' => true
            ];
            $this->fileStore->writeJson($filename, $roomData);
            $this->persistRoom($roomId, $userA, $userB);
        } else {
            // Re-open if closed
            $roomData = $this->fileStore->readJson($filename);
            if ($roomData && ($roomData['closed'] ?? false) === true) {
                $roomData['closed'] = false;
                $roomData['closed_by'] = null;
                $this->fileStore->writeJson($filename, $roomData);
                $this->persistRoomReopen($roomId);
            }
        }

        // Issue a chat request update to User B
        $this->addChatRequest($userB, $userA, $roomId);

        return $roomId;
    }

    /**
     * Store incoming direct chat requests.
     */
    private function addChatRequest(string $toUserId, string $fromUserId, string $roomId): void {
        $filename = "/requests/{$toUserId}.json";
        $requests = $this->fileStore->readJson($filename) ?? [];
        
        $exists = false;
        foreach ($requests as $req) {
            if (($req['from'] ?? '') === $fromUserId && ($req['room_id'] ?? '') === $roomId) {
                $exists = true;
                break;
            }
        }

        if (!$exists) {
            $requests[] = [
                'from' => $fromUserId,
                'room_id' => $roomId,
                'timestamp' => time()
            ];
            $this->fileStore->writeJson($filename, $requests);
        }
    }

    /**
     * Persist direct room in MySQL.
     */
    private function persistRoom(string $roomId, string $userA, string $userB): void {
        try {
            $pdo = $this->dbConnection->getPdo();
            $stmt = $pdo->prepare('INSERT INTO rooms (room_id, user_a_id, user_b_id, created_at, status) VALUES (?, ?, ?, NOW(), ?)');
            $stmt->execute([$roomId, $userA, $userB, 'active']);
        } catch (Exception $e) {
            $this->logError("DB room insert error: " . $e->getMessage());
        } finally {
            $this->dbConnection->disconnect();
        }
    }

    /**
     * Re-activate direct room in MySQL.
     */
    private function persistRoomReopen(string $roomId): void {
        try {
            $pdo = $this->dbConnection->getPdo();
            $stmt = $pdo->prepare('UPDATE rooms SET closed_at = NULL, status = ? WHERE room_id = ?');
            $stmt->execute(['active', $roomId]);
        } catch (Exception $e) {
            $this->logError("DB room reopen error: " . $e->getMessage());
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
