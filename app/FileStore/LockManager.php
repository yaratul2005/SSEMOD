<?php
declare(strict_types=1);

namespace App\FileStore;

class LockManager {
    /** @var array<string, resource> */
    private array $handles = [];

    public function __construct(private readonly string $lockDir) {
        if (!is_dir($this->lockDir)) {
            mkdir($this->lockDir, 0777, true);
        }
    }

    /**
     * Acquire a file lock for a specific key.
     * 
     * @param string $key Unique identifier for the lock target
     * @param bool $exclusive True for write lock (LOCK_EX), false for read lock (LOCK_SH)
     * @param bool $blocking True to block execution until the lock is acquired, false to fail immediately
     */
    public function acquire(string $key, bool $exclusive = true, bool $blocking = true): bool {
        $lockFile = $this->lockDir . '/' . hash('sha256', $key) . '.lock';
        $handle = fopen($lockFile, 'c');
        if ($handle === false) {
            return false;
        }

        $flags = $exclusive ? LOCK_EX : LOCK_SH;
        if (!$blocking) {
            $flags |= LOCK_NB;
        }

        if (!flock($handle, $flags)) {
            fclose($handle);
            return false;
        }

        // Store active handle
        $this->handles[$key] = $handle;
        return true;
    }

    /**
     * Release lock for a specific key.
     */
    public function release(string $key): void {
        if (isset($this->handles[$key])) {
            flock($this->handles[$key], LOCK_UN);
            fclose($this->handles[$key]);
            unset($this->handles[$key]);
        }
    }

    /**
     * Clean up any active locks on script exit.
     */
    public function __destruct() {
        foreach (array_keys($this->handles) as $key) {
            $this->release($key);
        }
    }
}
