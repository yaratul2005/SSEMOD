<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Services\SessionService;
use App\Services\PrivilegeService;
use App\Middleware\CSRF;
use Exception;

class DashboardController {
    public function __construct(
        private readonly Connection $dbConnection,
        private readonly SessionService $sessionService
    ) {}

    /**
     * Render the Dashboard page.
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
        $user = $this->sessionService->getUserState($userId);

        // Fetch Stats
        $stats = [
            'total_chats' => 0,
            'messages_sent' => 0,
            'member_since' => 'N/A'
        ];

        try {
            $pdo = $this->dbConnection->getPdo();
            
            // Total chats
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM rooms WHERE user_a_id = ? OR user_b_id = ?');
            $stmt->execute([$userId, $userId]);
            $stats['total_chats'] = (int)$stmt->fetchColumn();

            // Total messages sent
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE sender_id = ?');
            $stmt->execute([$userId]);
            $stats['messages_sent'] = (int)$stmt->fetchColumn();

            // Member since
            $stmt = $pdo->prepare('SELECT created_at FROM users WHERE anonymous_id = ?');
            $stmt->execute([$userId]);
            $createdAt = $stmt->fetchColumn();
            if ($createdAt) {
                $stats['member_since'] = date('F j, Y', strtotime($createdAt));
            }

            // Inbox Preview: Last 5 conversations
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
                LIMIT 5
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
        require __DIR__ . '/../Views/dashboard.php';
        return ob_get_clean();
    }
}
