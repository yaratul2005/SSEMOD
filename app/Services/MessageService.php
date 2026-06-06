<?php
declare(strict_types=1);

namespace App\Services;

use App\FileStore\MessageBuffer;
use App\FileStore\FileStore;
use App\Database\Connection;
use Exception;

class MessageService {
    public function __construct(
        private readonly MessageBuffer $messageBuffer,
        private readonly FileStore $fileStore,
        private readonly Connection $dbConnection
    ) {}

    /**
     * Validate and dispatch a message to the active room.
     */
    public function sendMessage(string $roomId, string $senderId, string $content, ?string $attachmentPath = null, ?string $attachmentType = null): array {
        $filename = "/rooms/{$roomId}.json";
        $room = $this->fileStore->readJson($filename);

        if ($room === null) {
            throw new Exception("Chat room not found.");
        }
        if (($room['closed'] ?? false) === true) {
            throw new Exception("This room is already closed.");
        }

        // Limit message content size to 500 characters
        if (mb_strlen($content) > 500) {
            throw new Exception("Message length exceeds the maximum of 500 characters.");
        }

        return $this->messageBuffer->pushMessage($roomId, $senderId, $content, $attachmentPath, $attachmentType);
    }

    /**
     * Touch typing status timer.
     */
    public function setTyping(string $roomId, string $userId): void {
        $filename = "/rooms/{$roomId}.json";
        $room = $this->fileStore->readJson($filename);

        if ($room === null || ($room['closed'] ?? false) === true) {
            return;
        }

        $isUserA = ($room['user_a'] ?? '') === $userId;
        $typingKey = $isUserA ? 'user_a_typing_at' : 'user_b_typing_at';
        $room[$typingKey] = time();

        $this->fileStore->writeJson($filename, $room);
    }
}
