<?php
declare(strict_types=1);

namespace App\Services;

use App\Database\Connection;
use App\FileStore\FileStore;
use App\Helpers\IdGenerator;
use Exception;

class AuthService {
    public function __construct(
        private readonly Connection $dbConnection,
        private readonly FileStore $fileStore
    ) {}

    /**
     * Hash password using Argon2id.
     */
    public function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_ARGON2ID);
    }

    /**
     * Generate 6-digit OTP, store in FileStore, and return it.
     */
    public function generateOTP(string $sessionId): string {
        $code = (string)random_int(100000, 999999);
        $otpData = [
            'code' => $code,
            'expires' => time() + 600, // 10 minutes window
            'attempts' => 0
        ];
        
        $this->fileStore->writeJson("/otp/{$sessionId}.json", $otpData);
        return $code;
    }

    /**
     * Verify OTP against FileStore storage.
     */
    public function verifyOTP(string $sessionId, string $code): bool {
        $filename = "/otp/{$sessionId}.json";
        $otp = $this->fileStore->readJson($filename);
        
        if ($otp === null) {
            return false;
        }

        // Increment attempt count
        $otp['attempts']++;
        
        if ($otp['attempts'] > 5) {
            $this->fileStore->delete($filename);
            return false;
        }

        $this->fileStore->writeJson($filename, $otp);

        if (time() > $otp['expires']) {
            $this->fileStore->delete($filename);
            return false;
        }

        if ($otp['code'] === $code) {
            $this->fileStore->delete($filename);
            return true;
        }

        return false;
    }

    /**
     * Save registered user to MySQL and migrate their guest data.
     */
    public function registerUser(array $draft, string $sessionId, ?string $oldGuestId = null): string {
        $userId = IdGenerator::generateUser(); // Generates u_...
        $pwdHash = $this->hashPassword($draft['password']);
        $interestsJson = json_encode($draft['interests'] ?? [], JSON_UNESCAPED_SLASHES);

        try {
            $pdo = $this->dbConnection->getPdo();
            $stmt = $pdo->prepare('
                INSERT INTO users (anonymous_id, fingerprint, ip_hash, username, email, password_hash, user_type, verified, avatar_path, bio, display_name, age, gender, country_flag, tags, created_at, last_seen, is_banned) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), 0)
            ');
            
            $fingerprint = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . ($_SERVER['REMOTE_ADDR'] ?? ''));
            $ipHash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');
            
            $stmt->execute([
                $userId,
                $fingerprint,
                $ipHash,
                $draft['username'],
                $draft['email'],
                $pwdHash,
                'registered',
                1, // Verified
                $draft['avatar_path'] ?? null,
                $draft['bio'] ?? null,
                $draft['display_name'] ?? $draft['username'],
                (int)$draft['age'],
                $draft['gender'],
                $draft['country_flag'] ?? '🏳️',
                $interestsJson
            ]);
        } finally {
            $this->dbConnection->disconnect();
        }

        // Migrate guest session history if it exists
        if ($oldGuestId !== null && $oldGuestId !== '') {
            $this->migrateGuestData($oldGuestId, $userId);
        }

        // Clean up guest JSON in FileStore
        $this->fileStore->delete("/guest/{$sessionId}.json");

        return $userId;
    }

    /**
     * Migrate guest rooms, messages, and presence links to the new registered user.
     */
    public function migrateGuestData(string $oldGuestId, string $newUserId): void {
        try {
            $pdo = $this->dbConnection->getPdo();
            
            // 1. Find all rooms where oldGuestId is a participant
            $stmt = $pdo->prepare('SELECT room_id, user_a_id, user_b_id FROM rooms WHERE user_a_id = ? OR user_b_id = ?');
            $stmt->execute([$oldGuestId, $oldGuestId]);
            $rooms = $stmt->fetchAll();

            foreach ($rooms as $room) {
                $oldRoomId = $room['room_id'];
                $userA = $room['user_a_id'];
                $userB = $room['user_b_id'];

                // Replace the old guest user ID with the new user ID
                $newUserA = ($userA === $oldGuestId) ? $newUserId : $userA;
                $newUserB = ($userB === $oldGuestId) ? $newUserId : $userB;

                // Calculate the new deterministic room ID
                $ids = [$newUserA, $newUserB];
                sort($ids);
                $newRoomId = 'd_' . md5($ids[0] . '_' . $ids[1]);

                // Update MySQL Rooms Table
                $updRoom = $pdo->prepare('UPDATE rooms SET room_id = ?, user_a_id = ?, user_b_id = ? WHERE room_id = ?');
                $updRoom->execute([$newRoomId, $newUserA, $newUserB, $oldRoomId]);

                // Update MySQL Messages Table
                $updMsg = $pdo->prepare('UPDATE messages SET room_id = ?, sender_id = CASE WHEN sender_id = ? THEN ? ELSE sender_id END WHERE room_id = ?');
                $updMsg->execute([$newRoomId, $oldGuestId, $newUserId, $oldRoomId]);

                // Migrate FileStore room data
                $oldFile = "/rooms/{$oldRoomId}.json";
                $newFile = "/rooms/{$newRoomId}.json";
                
                $roomData = $this->fileStore->readJson($oldFile);
                if ($roomData) {
                    $roomData['room_id'] = $newRoomId;
                    $roomData['user_a'] = $newUserA;
                    $roomData['user_b'] = $newUserB;
                    
                    // Update sender in buffered messages
                    if (isset($roomData['messages']) && is_array($roomData['messages'])) {
                        foreach ($roomData['messages'] as &$msg) {
                            if (($msg['sender'] ?? '') === $oldGuestId) {
                                $msg['sender'] = $newUserId;
                            }
                        }
                    }
                    $this->fileStore->writeJson($newFile, $roomData);
                    $this->fileStore->delete($oldFile);
                }
            }

            // 2. Migrate tags/interests from guest user profile to new registered user profile in FileStore
            $guestFile = "/users/{$oldGuestId}.json";
            $registeredFile = "/users/{$newUserId}.json";
            $state = $this->fileStore->readJson($guestFile);
            if ($state) {
                $state['user_id'] = $newUserId;
                $this->fileStore->writeJson($registeredFile, $state);
                $this->fileStore->delete($guestFile);
            }
        } catch (Exception) {
            // Silently log or ignore migration errors so registration doesn't crash
        } finally {
            $this->dbConnection->disconnect();
        }
    }
}
