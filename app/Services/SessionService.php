<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use App\FileStore\FileStore;
use App\Helpers\IdGenerator;
use Exception;

class SessionService {
    public function __construct(
        private readonly Connection $dbConnection,
        private readonly FileStore $fileStore
    ) {}

    /**
     * Start secure anonymous user session.
     */
    /**
     * Start secure session. Does not auto-create users.
     */
    public function start(): array {
        if (session_status() === PHP_SESSION_NONE) {
            session_start([
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax',
                'use_strict_mode' => true,
            ]);
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $fingerprint = hash('sha256', $ua . '|' . $ip);
        $ipHash = hash('sha256', $ip);

        if (!isset($_SESSION['user_type']) || !isset($_SESSION['user_id'])) {
            return [
                'user_id' => '',
                'fingerprint' => $fingerprint,
                'ip_hash' => $ipHash,
            ];
        }

        $userId = $_SESSION['user_id'];
        $userType = $_SESSION['user_type'];

        // Guest session validation
        if ($userType === 'guest') {
            $guestFile = "/guest/" . session_id() . ".json";
            $guestState = $this->fileStore->readJson($guestFile);
            
            if ($guestState === null || (time() - ($guestState['created'] ?? 0) > 86400)) {
                if ($guestState) {
                    $this->fileStore->delete($guestFile);
                }
                $_SESSION = [];
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_destroy();
                }
                return [
                    'user_id' => '',
                    'fingerprint' => $fingerprint,
                    'ip_hash' => $ipHash,
                ];
            }

            // Sync local cache
            $state = $this->getUserState($userId);
            $state['username'] = $guestState['username'];
            $state['age'] = $guestState['age'];
            $state['gender'] = $guestState['gender'];
            $state['interests'] = $guestState['tags'] ?? [];
            $state['country_flag'] = $guestState['country_flag'] ?? '🏳️';
            $this->updateUserState($userId, $state);
        } else {
            // Registered user
            $this->touchDbUser($userId);
            $state = $this->getUserState($userId);
        }

        $state['last_seen'] = time();
        $this->updateUserState($userId, $state);

        // Record presence in global online tracker
        $this->touchPresence($userId);

        return [
            'user_id' => $userId,
            'fingerprint' => $fingerprint,
            'ip_hash' => $ipHash,
        ];
    }

    /**
     * Read user status from cache file, restoring from DB if cache has expired.
     */
    public function getUserState(string $userId): array {
        $filename = "/users/{$userId}.json";
        $state = $this->fileStore->readJson($filename);
        if ($state === null) {
            // Check if guest
            if (str_starts_with($userId, 'g_')) {
                $guestFile = "/guest/" . session_id() . ".json";
                $guestData = $this->fileStore->readJson($guestFile) ?? [];
                
                $state = [
                    'user_id' => $userId,
                    'username' => $guestData['username'] ?? 'guest',
                    'gender' => $guestData['gender'] ?? 'O',
                    'age' => $guestData['age'] ?? 18,
                    'interests' => $guestData['tags'] ?? [],
                    'country_flag' => $guestData['country_flag'] ?? '🏳️',
                    'room_id' => null,
                    'status' => 'idle',
                    'last_seen' => time(),
                    'banned' => false,
                ];
                $this->updateUserState($userId, $state);
                return $state;
            }

            // Restore from MySQL
            try {
                $pdo = $this->dbConnection->getPdo();
                $stmt = $pdo->prepare('SELECT username, display_name, gender, age, country_flag, tags, is_banned, avatar_path, bio FROM users WHERE anonymous_id = ?');
                $stmt->execute([$userId]);
                $row = $stmt->fetch();
                if ($row) {
                    $state = [
                        'user_id' => $userId,
                        'username' => $row['username'] ?? '',
                        'display_name' => $row['display_name'] ?? '',
                        'gender' => $row['gender'] ?? 'O',
                        'age' => $row['age'] !== null ? (int)$row['age'] : 18,
                        'interests' => $row['tags'] ? json_decode($row['tags'], true) : [],
                        'country_flag' => $row['country_flag'] ?? '🏳️',
                        'room_id' => null,
                        'status' => 'idle',
                        'last_seen' => time(),
                        'banned' => (int)$row['is_banned'] === 1,
                        'avatar_path' => $row['avatar_path'] ?? null,
                        'bio' => $row['bio'] ?? null
                    ];
                    $this->updateUserState($userId, $state);
                    return $state;
                }
            } catch (Exception) {
                // Ignore
            } finally {
                $this->dbConnection->disconnect();
            }

            // Fallback initialization
            $state = [
                'user_id' => $userId,
                'username' => 'user' . random_int(100000, 999999),
                'gender' => 'O',
                'age' => 18,
                'interests' => [],
                'country_flag' => '🏳️',
                'room_id' => null,
                'status' => 'idle',
                'last_seen' => time(),
                'banned' => false,
            ];
            $this->updateUserState($userId, $state);
        }
        return $state;
    }

    /**
     * Write user status to cache file.
     */
    public function updateUserState(string $userId, array $state): void {
        $filename = "/users/{$userId}.json";
        $this->fileStore->writeJson($filename, $state);
    }

    /**
     * Touch presence indicator and flush inactive entries.
     */
    public function touchPresence(string $userId): void {
        $filename = '/presence/presence.json';
        $presence = $this->fileStore->readJson($filename) ?? [];
        
        $now = time();
        $presence[$userId] = $now;
        
        // Remove inactive entries (idle > 15 seconds)
        foreach ($presence as $uid => $time) {
            if ($now - $time > 15) {
                unset($presence[$uid]);
            }
        }
        
        $this->fileStore->writeJson($filename, $presence);
    }

    /**
     * Get active online user count.
     */
    public function getOnlineCount(): int {
        $filename = '/presence/presence.json';
        $presence = $this->fileStore->readJson($filename) ?? [];
        
        $now = time();
        $count = 0;
        foreach ($presence as $uid => $time) {
            if ($now - $time <= 15) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Verify if a user is currently banned.
     */
    public function isBanned(string $userId): bool {
        $state = $this->getUserState($userId);
        if ($state['banned'] === true) {
            return true;
        }

        try {
            $pdo = $this->dbConnection->getPdo();
            $stmt = $pdo->prepare('SELECT is_banned FROM users WHERE anonymous_id = ?');
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            $banned = isset($user['is_banned']) && (int)$user['is_banned'] === 1;
            if ($banned) {
                $state['banned'] = true;
                $this->updateUserState($userId, $state);
            }
            return $banned;
        } catch (Exception) {
            return false;
        } finally {
            $this->dbConnection->disconnect();
        }
    }

    /**
     * Create user record in MySQL.
     */
    private function createDbUser(
        string $userId, 
        string $fingerprint, 
        string $ipHash, 
        string $username, 
        string $gender, 
        int $age, 
        string $interestsJson
    ): void {
        try {
            $pdo = $this->dbConnection->getPdo();
            $stmt = $pdo->prepare('
                INSERT INTO users (anonymous_id, fingerprint, ip_hash, username, gender, age, interests, created_at, last_seen, is_banned) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), 0)
            ');
            $stmt->execute([$userId, $fingerprint, $ipHash, $username, $gender, $age, $interestsJson]);
        } catch (Exception) {}
        finally {
            $this->dbConnection->disconnect();
        }
    }

    /**
     * Touch user last_seen in MySQL.
     */
    private function touchDbUser(string $userId): void {
        try {
            $pdo = $this->dbConnection->getPdo();
            $stmt = $pdo->prepare('UPDATE users SET last_seen = NOW() WHERE anonymous_id = ?');
            $stmt->execute([$userId]);
        } catch (Exception) {}
        finally {
            $this->dbConnection->disconnect();
        }
    }
}
