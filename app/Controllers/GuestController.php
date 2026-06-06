<?php
declare(strict_types=1);

namespace App\Controllers;

use App\FileStore\FileStore;
use App\Helpers\Sanitizer;
use Exception;

class GuestController {
    public function __construct(
        private readonly FileStore $fileStore
    ) {}

    /**
     * Store a guest session in FileStore and PHP session.
     */
    public function store(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $username = Sanitizer::clean($_POST['username'] ?? '');
        $gender = Sanitizer::clean($_POST['gender'] ?? '');
        $age = (int)($_POST['age'] ?? 0);

        header('Content-Type: application/json');

        // Username validation
        if ($username === '' || mb_strlen($username) < 3 || mb_strlen($username) > 20) {
            http_response_code(400);
            return json_encode(['error' => 'Username must be between 3 and 20 characters.']);
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            http_response_code(400);
            return json_encode(['error' => 'Username can only contain alphanumeric characters and underscores.']);
        }

        // Age validation
        if ($age < 13 || $age > 99) {
            http_response_code(400);
            return json_encode(['error' => 'Age must be between 13 and 99. Under 13 not allowed.']);
        }

        // Gender validation
        if (!in_array($gender, ['F', 'M', 'O'], true)) {
            http_response_code(400);
            return json_encode(['error' => 'Invalid gender selected.']);
        }

        // Generate Guest User ID
        $guestId = 'g_' . bin2hex(random_bytes(12));
        $sessionId = session_id();

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $flag = \App\Helpers\IpGeolocation::getFlagEmoji($ip);

        $guestData = [
            'user_id' => $guestId,
            'username' => $username,
            'age' => $age,
            'gender' => $gender,
            'country_flag' => $flag,
            'tags' => [],
            'created' => time()
        ];

        // Write to guest folder in FileStore
        $success = $this->fileStore->writeJson("/guest/{$sessionId}.json", $guestData);
        if (!$success) {
            http_response_code(500);
            return json_encode(['error' => 'Failed to initialize guest session.']);
        }

        // Set session parameters
        $_SESSION['user_type'] = 'guest';
        $_SESSION['user_id'] = $guestId;
        $_SESSION['verified'] = false;

        return json_encode(['success' => true, 'user_id' => $guestId]);
    }
}
