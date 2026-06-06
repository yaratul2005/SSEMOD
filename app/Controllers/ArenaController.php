<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\SessionService;
use App\Services\PresenceService;
use App\Services\DirectChatService;
use App\Helpers\Sanitizer;
use App\Middleware\CSRF;
use App\Database\Connection;
use Exception;

class ArenaController {
    public function __construct(
        private readonly SessionService $sessionService,
        private readonly PresenceService $presenceService,
        private readonly DirectChatService $directChatService,
        private readonly Connection $dbConnection
    ) {}

    /**
     * Render the Chat Arena page.
     */
    public function index(): string {
        $csrfToken = CSRF::getToken();
        $userId = $_SESSION['user_id'] ?? '';
        
        // Load details for current session
        $userProfile = $this->sessionService->getUserState($userId);

        ob_start();
        require __DIR__ . '/../Views/arena.php';
        return ob_get_clean();
    }

    /**
     * Get paginated online users.
     */
    public function onlineUsers(): string {
        $users = $this->presenceService->getActiveUsers();
        
        $userId = $_SESSION['user_id'] ?? '';
        if (isset($users[$userId])) {
            unset($users[$userId]);
        }

        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = max(1, (int)($_GET['limit'] ?? 20));
        
        $offset = ($page - 1) * $limit;
        $slicedUsers = array_slice(array_values($users), $offset, $limit);

        header('Content-Type: application/json');
        return json_encode([
            'users' => $slicedUsers,
            'total' => count($users),
            'page' => $page,
            'limit' => $limit
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * Start a direct chat room with a selected online stranger.
     */
    public function startChat(string $targetUserId): string {
        $currentUserId = $_SESSION['user_id'] ?? '';
        if ($currentUserId === '') {
            http_response_code(400);
            return json_encode(['error' => 'Active session required']);
        }

        if ($currentUserId === $targetUserId) {
            http_response_code(400);
            return json_encode(['error' => 'Cannot initiate chat with yourself']);
        }

        try {
            $roomId = $this->directChatService->startChat($currentUserId, $targetUserId);
            header('Content-Type: application/json');
            return json_encode(['room_id' => $roomId], JSON_THROW_ON_ERROR);
        } catch (Exception $e) {
            http_response_code(500);
            return json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Save custom profile settings.
     */
    public function saveSettings(): string {
        $userId = $_SESSION['user_id'] ?? '';
        if ($userId === '') {
            http_response_code(400);
            return json_encode(['error' => 'Active session required']);
        }

        $username = Sanitizer::clean($_POST['username'] ?? '');
        $gender = Sanitizer::clean($_POST['gender'] ?? 'M');
        $age = (int)($_POST['age'] ?? 18);
        $interestsInput = $_POST['interests'] ?? '';

        if ($username === '' || mb_strlen($username) < 3) {
            http_response_code(400);
            return json_encode(['error' => 'Username must be at least 3 characters long.']);
        }

        if ($age < 18 || $age > 99) {
            http_response_code(400);
            return json_encode(['error' => 'Age must be between 18 and 99.']);
        }

        // Parse interest tags (limit: max 3 tags, max 12 chars each)
        $interests = [];
        if (is_string($interestsInput) && trim($interestsInput) !== '') {
            $parts = explode(',', $interestsInput);
            foreach ($parts as $part) {
                $clean = Sanitizer::clean($part);
                if ($clean !== '' && mb_strlen($clean) <= 12 && count($interests) < 3) {
                    $interests[] = $clean;
                }
            }
        }

        // Update local FileStore user cache
        $state = $this->sessionService->getUserState($userId);
        $state['username'] = $username;
        $state['gender'] = $gender;
        $state['age'] = $age;
        $state['interests'] = $interests;
        $this->sessionService->updateUserState($userId, $state);

        // Update MySQL database record
        try {
            $pdo = $this->dbConnection->getPdo();
            $stmt = $pdo->prepare('UPDATE users SET username = ?, gender = ?, age = ?, interests = ? WHERE anonymous_id = ?');
            $stmt->execute([$username, $gender, $age, json_encode($interests, JSON_UNESCAPED_SLASHES), $userId]);
        } catch (Exception $e) {
            $this->logError("DB settings update error: " . $e->getMessage());
        } finally {
            $this->dbConnection->disconnect();
        }

        header('Content-Type: application/json');
        return json_encode(['status' => 'success', 'user' => $state], JSON_THROW_ON_ERROR);
    }

    /**
     * Log errors.
     */
    private function logError(string $message): void {
        $logFile = $this->dbConnection->getPdo(); // Wait, let's just resolve log path manually to prevent PDO load
        $logPath = dirname(__DIR__, 2) . '/storage/logs/error.log';
        $dir = dirname($logPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        error_log("[" . date('Y-m-d H:i:s') . "] {$message}\n", 3, $logPath);
    }
}
