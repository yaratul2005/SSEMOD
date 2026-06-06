<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\MessageService;
use App\Services\SessionService;
use App\FileStore\MessageBuffer;
use App\FileStore\FileStore;
use App\Database\Connection;
use App\Helpers\Sanitizer;
use App\Middleware\CSRF;
use Exception;

class ChatController {
    public function __construct(
        private readonly MessageService $messageService,
        private readonly SessionService $sessionService,
        private readonly MessageBuffer $messageBuffer,
        private readonly FileStore $fileStore,
        private readonly Connection $dbConnection
    ) {}

    /**
     * Render the main single-page stranger chat landing/waiting/chat UI.
     */
    public function index(): string {
        $csrfToken = CSRF::getToken();
        $userId = $_SESSION['user_id'] ?? '';
        
        // Lazily run FileStore cleanup (5% probability)
        try {
            if (random_int(1, 100) <= 5) {
                $this->fileStore->garbageCollect();
            }
        } catch (Exception) {
            // Ignore randomness exceptions
        }

        ob_start();
        require __DIR__ . '/../Views/chat.php';
        return ob_get_clean();
    }

    /**
     * Send a message to the active chat room.
     */
    public function send(): string {
        $roomId = $_POST['room_id'] ?? '';
        $content = $_POST['content'] ?? '';
        $userId = $_SESSION['user_id'] ?? '';

        if ($roomId === '') {
            http_response_code(400);
            return json_encode(['error' => 'Missing room_id parameter']);
        }

        $attachmentPath = null;
        $attachmentType = null;

        // Handle attachment file upload
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
                http_response_code(400);
                return json_encode(['error' => 'File upload failed with error code: ' . $_FILES['attachment']['error']]);
            }

            $fileSize = $_FILES['attachment']['size'];
            $maxSize = 25 * 1024 * 1024; // 25MB
            if ($fileSize > $maxSize) {
                http_response_code(400);
                return json_encode(['error' => 'Attachment exceeds the 25MB limit.']);
            }

            $appConfig = require dirname(__DIR__, 2) . '/config/app.php';
            $attachmentsDir = $appConfig['storage_path'] . '/attachments';
            if (!is_dir($attachmentsDir)) {
                mkdir($attachmentsDir, 0777, true);
            }

            $origName = $_FILES['attachment']['name'];
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
            $ext = preg_replace('/[^a-z0-9]/', '', $ext);
            
            $uniqueName = bin2hex(random_bytes(16)) . ($ext !== '' ? '.' . $ext : '');
            $targetPath = $attachmentsDir . '/' . $uniqueName;

            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $targetPath)) {
                $attachmentPath = $uniqueName;
                
                // Get MIME type
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $targetPath);
                finfo_close($finfo);

                if (str_starts_with($mime, 'image/')) {
                    $attachmentType = 'image';
                } elseif (str_starts_with($mime, 'video/')) {
                    $attachmentType = 'video';
                } elseif (str_starts_with($mime, 'audio/')) {
                    $attachmentType = 'audio';
                } else {
                    $attachmentType = 'file';
                }
            } else {
                http_response_code(500);
                return json_encode(['error' => 'Failed to save uploaded attachment.']);
            }
        }

        $cleanContent = Sanitizer::clean($content);
        if ($cleanContent === '' && $attachmentPath === null) {
            http_response_code(400);
            return json_encode(['error' => 'Message content cannot be blank']);
        }

        try {
            $msg = $this->messageService->sendMessage($roomId, $userId, $cleanContent, $attachmentPath, $attachmentType);
            header('Content-Type: application/json');
            return json_encode(['status' => 'sent', 'message' => $msg]);
        } catch (Exception $e) {
            http_response_code(400);
            return json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Receive typing notifications.
     */
    public function typing(): string {
        $roomId = $_POST['room_id'] ?? '';
        $userId = $_SESSION['user_id'] ?? '';

        if ($roomId === '') {
            http_response_code(400);
            return json_encode(['error' => 'Missing room_id parameter']);
        }

        $this->messageService->setTyping($roomId, $userId);
        header('Content-Type: application/json');
        return json_encode(['status' => 'acknowledged']);
    }

    /**
     * Close the current room and reset user status to idle.
     */
    public function next(): string {
        $userId = $_SESSION['user_id'] ?? '';
        $state = $this->sessionService->getUserState($userId);
        $roomId = $state['room_id'] ?? '';

        if ($roomId !== '') {
            $this->messageBuffer->closeRoom($roomId, $userId);
        }

        $state['status'] = 'idle';
        $state['room_id'] = null;
        $this->sessionService->updateUserState($userId, $state);

        header('Content-Type: application/json');
        return json_encode(['status' => 'idle']);
    }

    /**
     * Report the stranger for abusive behavior, closing the room and issuing soft bans.
     */
    public function report(): string {
        $roomId = $_POST['room_id'] ?? '';
        $reason = $_POST['reason'] ?? 'Abusive behavior';
        $userId = $_SESSION['user_id'] ?? '';

        if ($roomId === '') {
            http_response_code(400);
            return json_encode(['error' => 'Missing room_id parameter']);
        }

        $roomFilename = "/rooms/{$roomId}.json";
        $room = $this->fileStore->readJson($roomFilename);
        if ($room === null) {
            http_response_code(400);
            return json_encode(['error' => 'Chat room not found']);
        }

        // Determine reported user's ID
        $isUserA = ($room['user_a'] ?? '') === $userId;
        $reportedId = $isUserA ? ($room['user_b'] ?? '') : ($room['user_a'] ?? '');

        if ($reportedId === '') {
            http_response_code(400);
            return json_encode(['error' => 'Unable to resolve stranger details']);
        }

        try {
            $pdo = $this->dbConnection->getPdo();
            $stmt = $pdo->prepare('INSERT INTO reports (reporter_id, reported_id, room_id, reason, created_at) VALUES (?, ?, ?, ?, NOW())');
            $stmt->execute([$userId, $reportedId, $roomId, Sanitizer::clean($reason)]);

            // Count recent reports to check if soft-ban threshold (3 reports) is reached
            $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM reports WHERE reported_id = ?');
            $stmt->execute([$reportedId]);
            $reportCount = (int)$stmt->fetch()['count'];

            if ($reportCount >= 3) {
                // Update banned status in DB
                $stmt = $pdo->prepare('UPDATE users SET is_banned = 1 WHERE anonymous_id = ?');
                $stmt->execute([$reportedId]);

                // Ban cached status
                $strangerState = $this->sessionService->getUserState($reportedId);
                $strangerState['banned'] = true;
                $this->sessionService->updateUserState($reportedId, $strangerState);
            }
        } catch (Exception $e) {
            $this->logError("DB report processing error: " . $e->getMessage());
        } finally {
            $this->dbConnection->disconnect();
        }

        // Close the room
        $this->messageBuffer->closeRoom($roomId, $userId);

        // Reset reporter status
        $state = $this->sessionService->getUserState($userId);
        $state['status'] = 'idle';
        $state['room_id'] = null;
        $this->sessionService->updateUserState($userId, $state);

        header('Content-Type: application/json');
        return json_encode(['status' => 'reported']);
    }

    /**
     * Log errors.
     */
    private function logError(string $message): void {
        $logFile = $this->fileStore->getPath('../logs/error.log');
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        error_log("[" . date('Y-m-d H:i:s') . "] {$message}\n", 3, $logFile);
    }
}
