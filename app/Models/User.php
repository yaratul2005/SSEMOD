<?php
declare(strict_types=1);

namespace App\Models;

readonly class User {
    public function __construct(
        public string $anonymousId,
        public string $fingerprint,
        public string $ipHash,
        public string $createdAt,
        public string $lastSeen,
        public bool $isBanned
    ) {}
}
