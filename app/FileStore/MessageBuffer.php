<?php
declare(strict_types=1);

namespace App\FileStore;

use App\Database\Connection;
use Exception;

class MessageBuffer {
    private array $fileCache = [];

    public function __construct(
        private readonly FileStore $fileStore,
        private readonly Connection $dbConnection
    ) {}

    /**
     * Push a new message to the room file buffer, slicing it to 100 messages,
     * and persist the message to MySQL database.
     */
    public function pushMessage(string $roomId, string $senderId, string $content, ?string $attachmentPath = null, ?string $attachmentType = null): array {
        $filename = "/rooms/{$roomId}.json";
        $data = $this->fileStore->readJson($filename);

        if ($data === null) {
            $data = [
                'messages' => [],
                'last_event_id' => 0,
                'closed' => false,
                'closed_by' => null,
            ];
        }

        $nextId = ($data['last_event_id'] ?? 0) + 1;
        $sentAt = date('Y-m-d H:i:s');

        $message = [
            'id' => $nextId,
            'sender_id' => $senderId,
            'content' => $content,
            'attachment_path' => $attachmentPath,
            'attachment_type' => $attachmentType,
            'sent_at' => $sentAt,
        ];

        $data['messages'][] = $message;
        $data['last_event_id'] = $nextId;

        // Slice messages list to maintain ring buffer of last 100 messages
        if (count($data['messages']) > 100) {
            array_shift($data['messages']);
        }

        $this->fileStore->writeJson($filename, $data);

        // Persistent write to DB
        $this->persistToDb($roomId, $senderId, $content, $sentAt, $attachmentPath, $attachmentType);

        return $message;
    }

    /**
     * Mark a room as closed in both FileStore and MySQL database.
     */
    public function closeRoom(string $roomId, string $closedBy): void {
        $filename = "/rooms/{$roomId}.json";
        $data = $this->fileStore->readJson($filename);

        if ($data !== null) {
            $data['closed'] = true;
            $data['closed_by'] = $closedBy;
            $this->fileStore->writeJson($filename, $data);
        }

        try {
            $pdo = $this->dbConnection->getPdo();
            $stmt = $pdo->prepare('UPDATE rooms SET closed_at = NOW(), status = ? WHERE room_id = ?');
            $stmt->execute(['closed', $roomId]);
        } catch (Exception $e) {
            $this->logError("DB close room error: " . $e->getMessage());
        } finally {
            $this->dbConnection->disconnect();
        }
    }

    /**
     * Fetch all messages in a room that were created after $lastEventId.
     */
    public function getMessagesSince(string $roomId, int $lastEventId): array {
        $filename = "/rooms/{$roomId}.json";
        $path = $this->fileStore->getPath($filename);

        if (!file_exists($path)) {
            return [];
        }

        clearstatcache(true, $path);
        $mtime = filemtime($path);

        if (isset($this->fileCache[$roomId]) && $this->fileCache[$roomId]['mtime'] === $mtime) {
            $data = $this->fileCache[$roomId]['data'];
        } else {
            $data = $this->fileStore->readJson($filename);
            if ($data !== null) {
                $this->fileCache[$roomId] = [
                    'mtime' => $mtime,
                    'data' => $data
                ];
            }
        }

        if ($data === null) {
            return [];
        }

        $newMessages = [];
        foreach (($data['messages'] ?? []) as $msg) {
            if ($msg['id'] > $lastEventId) {
                $newMessages[] = $msg;
            }
        }

        return [
            'messages' => $newMessages,
            'closed' => $data['closed'] ?? false,
            'closed_by' => $data['closed_by'] ?? null,
            'last_event_id' => $data['last_event_id'] ?? 0,
        ];
    }

    /**
     * Persist message data directly to MySQL.
     */
    private function persistToDb(string $roomId, string $senderId, string $content, string $sentAt, ?string $attachmentPath = null, ?string $attachmentType = null): void {
        try {
            $pdo = $this->dbConnection->getPdo();
            $stmt = $pdo->prepare('INSERT INTO messages (room_id, sender_id, content, sent_at, attachment_path, attachment_type) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$roomId, $senderId, $content, $sentAt, $attachmentPath, $attachmentType]);
        } catch (Exception $e) {
            $this->logError("DB message write error: " . $e->getMessage());
        } finally {
            $this->dbConnection->disconnect();
        }
    }

    /**
     * Log file-writing errors to log directory.
     */
    private function logError(string $message): void {
        $logDir = dirname($this->fileStore->getPath('')) . '/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        error_log("[" . date('Y-m-d H:i:s') . "] {$message}\n", 3, $logDir . '/error.log');
    }
}
