<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Services\SessionService;
use App\Middleware\CSRF;
use Exception;

class InboxController {
    public function __construct(
        private readonly Connection $dbConnection,
        private readonly SessionService $sessionService
    ) {}

    /**
     * Render the Inbox list view.
     */
    public function index(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $userId = $_SESSION['user_id'] ?? '';
        $userType = $_SESSION['user_type'] ?? '';
        $verified = $_SESSION['verified'] ?? false;

        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $baseDir = rtrim(dirname($scriptName), '/\\');

        if ($userId === '' || $userType !== 'registered' || !$verified) {
            header('Location: ' . $baseDir . '/arena?error=auth');
            exit;
        }

        $csrfToken = CSRF::getToken();

        try {
            $pdo = $this->dbConnection->getPdo();
            
            // Query all rooms involving current user
            $inboxQuery = '
                SELECT r.room_id, r.created_at,
                       u.anonymous_id as other_id, u.display_name as other_name, u.gender as other_gender,
                       m.content as last_message, m.sent_at as last_message_time
                FROM rooms r
                JOIN users u ON u.anonymous_id = CASE WHEN r.user_a_id = ? THEN r.user_b_id ELSE r.user_a_id END
                LEFT JOIN (
                    SELECT m1.room_id, m1.content, m1.sent_at
                    FROM messages m1
                    INNER JOIN (
                        SELECT room_id, MAX(id) as max_id
                        FROM messages
                        GROUP BY room_id
                    ) m2 ON m1.id = m2.max_id
                ) m ON r.room_id = m.room_id
                WHERE r.user_a_id = ? OR r.user_b_id = ?
                ORDER BY COALESCE(m.sent_at, r.created_at) DESC
            ';
            $stmt = $pdo->prepare($inboxQuery);
            $stmt->execute([$userId, $userId, $userId]);
            $conversations = $stmt->fetchAll();
        } catch (Exception $e) {
            $conversations = [];
        } finally {
            $this->dbConnection->disconnect();
        }

        ob_start();
        require __DIR__ . '/../Views/inbox.php';
        return ob_get_clean();
    }
}
