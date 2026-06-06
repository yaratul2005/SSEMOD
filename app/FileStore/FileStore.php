<?php
declare(strict_types=1);

namespace App\FileStore;

class FileStore {
    public function __construct(
        private readonly string $baseDir,
        private readonly LockManager $lockManager
    ) {
        if (!is_dir($this->baseDir)) {
            mkdir($this->baseDir, 0777, true);
        }
    }

    /**
     * Resolve fully qualified absolute path for a filename.
     */
    public function getPath(string $filename): string {
        return $this->baseDir . '/' . ltrim($filename, '/');
    }

    /**
     * Read content from a file using a shared lock.
     */
    public function read(string $filename): ?string {
        $path = $this->getPath($filename);
        if (!file_exists($path)) {
            return null;
        }

        $this->lockManager->acquire($path, false, true);
        try {
            $content = file_get_contents($path);
            return $content !== false ? $content : null;
        } finally {
            $this->lockManager->release($path);
        }
    }

    /**
     * Write content to a file using an exclusive lock.
     */
    public function write(string $filename, string $content): bool {
        $path = $this->getPath($filename);
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $this->lockManager->acquire($path, true, true);
        try {
            return file_put_contents($path, $content) !== false;
        } finally {
            $this->lockManager->release($path);
        }
    }

    /**
     * Delete a file using an exclusive lock.
     */
    public function delete(string $filename): bool {
        $path = $this->getPath($filename);
        if (!file_exists($path)) {
            return false;
        }

        $this->lockManager->acquire($path, true, true);
        try {
            if (file_exists($path)) {
                return unlink($path);
            }
            return false;
        } finally {
            $this->lockManager->release($path);
        }
    }

    /**
     * Read and decode JSON content.
     */
    public function readJson(string $filename): ?array {
        $content = $this->read($filename);
        if ($content === null || trim($content) === '') {
            return null;
        }
        return json_decode($content, true);
    }

    /**
     * Encode and write data as JSON.
     */
    public function writeJson(string $filename, array $data): bool {
        return $this->write($filename, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Check if file exists.
     */
    public function exists(string $filename): bool {
        return file_exists($this->getPath($filename));
    }

    /**
     * Lazily clean up stale chat rooms, rate limits, and inactive user sessions.
     */
    public function garbageCollect(): void {
        $now = time();
        
        // 1. Clean closed rooms older than 2 hours
        $roomsDir = $this->getPath('rooms');
        if (is_dir($roomsDir)) {
            $files = glob($roomsDir . '/*.json');
            if ($files !== false) {
                foreach ($files as $file) {
                    if ($now - filemtime($file) > 7200) {
                        $content = file_get_contents($file);
                        if ($content !== false) {
                            $data = json_decode($content, true);
                            if ($data && ($data['closed'] ?? false) === true) {
                                @unlink($file);
                                
                                // Clean corresponding lock file
                                $lockFile = $this->baseDir . '/locks/' . hash('sha256', $file) . '.lock';
                                if (file_exists($lockFile)) {
                                    @unlink($lockFile);
                                }
                            }
                        }
                    }
                }
            }
        }

        // 2. Clean inactive user profiles & rate limit cache older than 24 hours
        $subDirs = ['users', 'ratelimits'];
        foreach ($subDirs as $subDir) {
            $path = $this->getPath($subDir);
            if (is_dir($path)) {
                $files = glob($path . '/*.json');
                if ($files !== false) {
                    foreach ($files as $file) {
                        if ($now - filemtime($file) > 86400) {
                            @unlink($file);
                        }
                    }
                }
            }
        }
    }
}
