<?php
declare(strict_types=1);

namespace App\Models;

readonly class Room {
    public function __construct(
        public string $roomId,
        public string $userAId,
        public string $userBId,
        public string $createdAt,
        public ?string $closedAt,
        public string $status
    ) {}
}
