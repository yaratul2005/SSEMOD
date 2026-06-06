<?php
declare(strict_types=1);

namespace App\Services;

use App\FileStore\FileStore;

class PresenceService {
    private const FILE = '/presence/arena_online.json';

    public function __construct(private readonly FileStore $fileStore) {}

    /**
     * Touch a user's presence, updating their cache card and purging dead sessions (>30s inactive).
     */
    public function touch(string $userId, array $state): void {
        $data = $this->fileStore->readJson(self::FILE) ?? [];
        $now = time();

        $data[$userId] = [
            'id' => $userId,
            'username' => $state['username'] ?? 'stranger',
            'gender' => $state['gender'] ?? 'M',
            'age' => $state['age'] ?? 20,
            'tags' => $state['interests'] ?? [],
            'country_flag' => $state['country_flag'] ?? '🏳️',
            'last_seen' => $now
        ];

        // Clean out users offline for more than 30 seconds
        foreach ($data as $uid => $user) {
            if ($now - ($user['last_seen'] ?? 0) > 30) {
                unset($data[$uid]);
            }
        }

        $this->fileStore->writeJson(self::FILE, $data);
    }

    /**
     * Get list of active users with real-time online/away status.
     */
    public function getActiveUsers(): array {
        $data = $this->fileStore->readJson(self::FILE) ?? [];
        $now = time();
        $users = [];

        foreach ($data as $uid => $user) {
            // User is active (green dot) if seen in the last 10 seconds. Otherwise away (gray dot)
            $user['online'] = ($now - ($user['last_seen'] ?? 0) <= 10);
            $users[$uid] = $user;
        }

        return $users;
    }
}
