<?php
declare(strict_types=1);

namespace App\FileStore;

class QueueStore {
    private const FILE = '/queue/queue.json';

    public function __construct(private readonly FileStore $fileStore) {}

    /**
     * Get all currently waiting users from the queue.
     */
    public function getAll(): array {
        $data = $this->fileStore->readJson(self::FILE);
        return $data !== null ? $data : [];
    }

    /**
     * Add or update a user in the waiting queue.
     */
    public function join(string $userId, array $interests): bool {
        $queue = $this->getAll();
        
        $found = false;
        foreach ($queue as &$item) {
            if ($item['user_id'] === $userId) {
                $item['interests'] = $interests;
                $item['waiting_since'] = date('Y-m-d H:i:s');
                $found = true;
                break;
            }
        }
        unset($item);
        
        if (!$found) {
            $queue[] = [
                'user_id' => $userId,
                'waiting_since' => date('Y-m-d H:i:s'),
                'interests' => $interests,
            ];
        }

        return $this->fileStore->writeJson(self::FILE, $queue);
    }

    /**
     * Remove a user from the waiting queue.
     */
    public function leave(string $userId): bool {
        $queue = $this->getAll();
        $initialCount = count($queue);
        $queue = array_filter($queue, fn($item) => $item['user_id'] !== $userId);
        $queue = array_values($queue);

        if (count($queue) === $initialCount) {
            return true; // nothing changed but no error
        }

        return $this->fileStore->writeJson(self::FILE, $queue);
    }

    /**
     * Atomically pop matched pairs from the waiting queue.
     */
    public function popMatch(string $userId1, string $userId2): bool {
        $queue = $this->getAll();
        $queue = array_filter($queue, fn($item) => $item['user_id'] !== $userId1 && $item['user_id'] !== $userId2);
        $queue = array_values($queue);

        return $this->fileStore->writeJson(self::FILE, $queue);
    }
}
