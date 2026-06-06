<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\Services\SessionService;
use App\Services\AvatarService;
use App\Services\PrivilegeService;
use App\Helpers\Sanitizer;
use Exception;

class ProfileController {
    public function __construct(
        private readonly Connection $dbConnection,
        private readonly SessionService $sessionService,
        private readonly AvatarService $avatarService
    ) {}

    /**
     * Serve user avatar. Serves initials SVG fallback if not uploaded.
     */
    public function serveAvatar(string $userId): void {
        $cleanUserId = preg_replace('/[^a-zA-Z0-9_]/', '', $userId);
        
        $appConfig = require dirname(__DIR__, 2) . '/config/app.php';
        $path = $appConfig['storage_path'] . '/avatars/' . $cleanUserId . '.webp';

        if (file_exists($path)) {
            header('Content-Type: image/webp');
            header('Cache-Control: public, max-age=86400');
            header('Content-Length: ' . filesize($path));
            readfile($path);
            exit;
        }

        // Generate Initials SVG Fallback
        $state = $this->sessionService->getUserState($userId);
        $name = $state['display_name'] ?? $state['username'] ?? 'User';
        $initial = mb_strtoupper(mb_substr(trim($name), 0, 1));
        if ($initial === '') {
            $initial = '?';
        }

        // Pick a background color based on name hash
        $colors = ['#2979ff', '#00e676', '#d500f9', '#ffea00', '#00e5ff', '#ff1744', '#ff9100'];
        $colorIdx = abs(crc32($userId)) % count($colors);
        $bgColor = $colors[$colorIdx];
        $textColor = ($bgColor === '#ffea00' || $bgColor === '#00e5ff' || $bgColor === '#00e676') ? '#0c0c0c' : '#ffffff';

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="100" height="100">' .
               '<rect width="100" height="100" fill="' . $bgColor . '"/>' .
               '<text x="50%" y="55%" dominant-baseline="middle" text-anchor="middle" ' .
               'fill="' . $textColor . '" font-family="Outfit, sans-serif" font-weight="700" font-size="44">' .
               $initial .
               '</text>' .
               '</svg>';

        header('Content-Type: image/svg+xml');
        header('Cache-Control: public, max-age=86400');
        echo $svg;
        exit;
    }

    /**
     * Upload avatar image for verified registered user.
     */
    public function uploadAvatar(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        header('Content-Type: application/json');

        if (!PrivilegeService::can('upload_photo')) {
            http_response_code(403);
            return json_encode(['error' => 'You must have a registered & verified account to upload an avatar.']);
        }

        $userId = $_SESSION['user_id'] ?? '';
        if ($userId === '') {
            http_response_code(400);
            return json_encode(['error' => 'Active session required.']);
        }

        if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            return json_encode(['error' => 'No image file uploaded or upload error occurred.']);
        }

        $uploadedPath = $this->avatarService->upload($_FILES['avatar'], $userId);
        if (!$uploadedPath) {
            http_response_code(400);
            return json_encode(['error' => 'Invalid image file. Ensure it is a valid JPEG/PNG/WebP, less than 2MB.']);
        }

        // Save path to DB
        try {
            $pdo = $this->dbConnection->getPdo();
            $stmt = $pdo->prepare('UPDATE users SET avatar_path = ? WHERE anonymous_id = ?');
            $stmt->execute([$uploadedPath, $userId]);
            
            // Sync FileStore cache
            $state = $this->sessionService->getUserState($userId);
            $state['avatar_path'] = $uploadedPath;
            $this->sessionService->updateUserState($userId, $state);
        } catch (Exception $e) {
            http_response_code(500);
            return json_encode(['error' => 'Database write error: ' . $e->getMessage()]);
        } finally {
            $this->dbConnection->disconnect();
        }

        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $baseDir = rtrim(dirname($scriptName), '/\\');

        return json_encode([
            'success' => true,
            'avatar_url' => $baseDir . '/avatar/' . $userId . '?t=' . time()
        ]);
    }

    /**
     * Update profile details (display_name, bio, tags, age, gender, email, password) from dashboard.
     */
    public function update(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        header('Content-Type: application/json');

        $userId = $_SESSION['user_id'] ?? '';
        $userType = $_SESSION['user_type'] ?? '';

        if ($userId === '' || $userType !== 'registered') {
            http_response_code(403);
            return json_encode(['error' => 'Registered account session required.']);
        }

        $displayName = Sanitizer::clean($_POST['display_name'] ?? '');
        $bio = Sanitizer::clean($_POST['bio'] ?? '');
        $age = (int)($_POST['age'] ?? 0);
        $gender = Sanitizer::clean($_POST['gender'] ?? 'O');
        $interestsInput = $_POST['interests'] ?? '';

        // Validate basic fields
        if ($displayName === '' || mb_strlen($displayName) < 3 || mb_strlen($displayName) > 20) {
            http_response_code(400);
            return json_encode(['error' => 'Display name must be between 3 and 20 characters.']);
        }
        if ($age < 13 || $age > 99) {
            http_response_code(400);
            return json_encode(['error' => 'Age must be between 13 and 99.']);
        }
        if (!in_array($gender, ['F', 'M', 'O'], true)) {
            http_response_code(400);
            return json_encode(['error' => 'Invalid gender.']);
        }
        if (mb_strlen($bio) > 160) {
            http_response_code(400);
            return json_encode(['error' => 'Bio cannot exceed 160 characters.']);
        }

        // Parse interest tags
        $interests = [];
        if (is_string($interestsInput) && trim($interestsInput) !== '') {
            $parts = explode(',', $interestsInput);
            foreach ($parts as $part) {
                $clean = Sanitizer::clean($part);
                if ($clean !== '' && mb_strlen($clean) <= 12 && count($interests) < 5) {
                    $interests[] = $clean;
                }
            }
        }

        try {
            $pdo = $this->dbConnection->getPdo();
            
            // Check display name uniqueness if changed
            $stmt = $pdo->prepare('SELECT display_name FROM users WHERE anonymous_id = ?');
            $stmt->execute([$userId]);
            $currentDisplayName = $stmt->fetchColumn();
            
            if ($currentDisplayName !== $displayName) {
                $chk = $pdo->prepare('SELECT COUNT(*) FROM users WHERE display_name = ?');
                $chk->execute([$displayName]);
                if ((int)$chk->fetchColumn() > 0) {
                    http_response_code(400);
                    return json_encode(['error' => 'This display name is already taken.']);
                }
            }

            // Optional change email and password
            $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
            $newPassword = $_POST['new_password'] ?? '';
            $currentPassword = $_POST['current_password'] ?? '';

            if ($email) {
                $stmt = $pdo->prepare('SELECT email FROM users WHERE anonymous_id = ?');
                $stmt->execute([$userId]);
                $currentEmail = $stmt->fetchColumn();

                if ($currentEmail !== $email) {
                    // Check email uniqueness
                    $chk = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
                    $chk->execute([$email]);
                    if ((int)$chk->fetchColumn() > 0) {
                        http_response_code(400);
                        return json_encode(['error' => 'An account with this email already exists.']);
                    }

                    // Update email and reset verified
                    $updEmail = $pdo->prepare('UPDATE users SET email = ?, verified = 0 WHERE anonymous_id = ?');
                    $updEmail->execute([$email, $userId]);
                    $_SESSION['verified'] = false;
                }
            }

            if ($newPassword !== '') {
                // Confirm current password first
                $pwdStmt = $pdo->prepare('SELECT password_hash FROM users WHERE anonymous_id = ?');
                $pwdStmt->execute([$userId]);
                $hash = $pwdStmt->fetchColumn();

                if (!$hash || !password_verify($currentPassword, $hash)) {
                    http_response_code(400);
                    return json_encode(['error' => 'Current password verification failed.']);
                }

                if (strlen($newPassword) < 8 || !preg_match('/[0-9]/', $newPassword) || !preg_match('/[^a-zA-Z0-9]/', $newPassword)) {
                    http_response_code(400);
                    return json_encode(['error' => 'New password must be at least 8 characters long and contain 1 number and 1 special character.']);
                }

                $newHash = password_hash($newPassword, PASSWORD_ARGON2ID);
                $updPwd = $pdo->prepare('UPDATE users SET password_hash = ? WHERE anonymous_id = ?');
                $updPwd->execute([$newHash, $userId]);
            }

            // Update profile fields
            $stmt = $pdo->prepare('
                UPDATE users 
                SET display_name = ?, bio = ?, age = ?, gender = ?, tags = ? 
                WHERE anonymous_id = ?
            ');
            $stmt->execute([$displayName, $bio, $age, $gender, json_encode($interests, JSON_UNESCAPED_SLASHES), $userId]);

            // Sync FileStore cache
            $state = $this->sessionService->getUserState($userId);
            $state['display_name'] = $displayName;
            $state['username'] = $displayName;
            $state['bio'] = $bio;
            $state['age'] = $age;
            $state['gender'] = $gender;
            $state['interests'] = $interests;
            $this->sessionService->updateUserState($userId, $state);

            return json_encode(['success' => true]);
        } catch (Exception $e) {
            http_response_code(500);
            return json_encode(['error' => 'Database update failed: ' . $e->getMessage()]);
        } finally {
            $this->dbConnection->disconnect();
        }
    }

    /**
     * Serve uploaded direct message attachments securely.
     */
    public function serveAttachment(string $filename): void {
        $cleanFilename = basename($filename);
        $appConfig = require dirname(__DIR__, 2) . '/config/app.php';
        $path = $appConfig['storage_path'] . '/attachments/' . $cleanFilename;

        if (!file_exists($path)) {
            http_response_code(404);
            echo "Attachment not found.";
            exit;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $path);
        finfo_close($finfo);

        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=86400');
        header('Content-Length: ' . filesize($path));
        header('Content-Disposition: inline; filename="' . $cleanFilename . '"');
        readfile($path);
        exit;
    }
}
