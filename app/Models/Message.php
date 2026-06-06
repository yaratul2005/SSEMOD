<?php
declare(strict_types=1);

namespace App\Models;

readonly class Message {
    public function __construct(
        public int $id,
        public string $roomId,
        public string $senderId,
        public string $content,
        public string $sentAt
    ) {}
}
