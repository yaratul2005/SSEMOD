<?php
declare(strict_types=1);

namespace App\Services;

class PrivilegeService {
    /**
     * Central checker to verify if the current user has permission for an action.
     */
    public static function can(string $action): bool {
        if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
            session_start();
        }

        $userType = $_SESSION['user_type'] ?? null;
        $verified = (bool)($_SESSION['verified'] ?? false);

        return match ($action) {
            'live_chat', 'random_chat', 'send_message', 'view_online', 'edit_tags' => ($userType === 'guest' || $userType === 'registered'),
            'upload_photo', 'change_photo', 'view_inbox', 'view_history', 'view_dashboard', 'friend_request', 'save_chat' => ($userType === 'registered' && $verified),
            default => false
        };
    }
}
