<?php
declare(strict_types=1);

namespace App\Models;

readonly class Queue {
    public function __construct(
        public int $id,
        public string $userId,
        public string $waitingSince,
        public array $interests,
        public string $status
    ) {}
}
