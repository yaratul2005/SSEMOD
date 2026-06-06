<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database\Connection;
use App\FileStore\FileStore;
use App\Services\AuthService;
use App\Services\MailService;
use App\Services\AvatarService;
use App\Helpers\Sanitizer;
use Exception;

class AuthController {
    public function __construct(
        private readonly Connection $dbConnection,
        private readonly FileStore $fileStore,
        private readonly AuthService $authService,
        private readonly MailService $mailService,
        private readonly AvatarService $avatarService
    ) {}

    /**
     * Step 1 & 2: Validate basic info & account details, store in session.
     */
    public function register(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        header('Content-Type: application/json');

        $displayName = Sanitizer::clean($_POST['display_name'] ?? '');
        $age = (int)($_POST['age'] ?? 0);
        $gender = Sanitizer::clean($_POST['gender'] ?? '');
        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        // Display Name validation
        if ($displayName === '' || mb_strlen($displayName) < 3 || mb_strlen($displayName) > 20) {
            http_response_code(400);
            return json_encode(['error' => 'Display name must be between 3 and 20 characters.']);
        }
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $displayName)) {
            http_response_code(400);
            return json_encode(['error' => 'Display name can only contain alphanumeric characters and underscores.']);
        }

        // Age & Gender
        if ($age < 13 || $age > 99) {
            http_response_code(400);
            return json_encode(['error' => 'Age must be between 13 and 99.']);
        }
        if (!in_array($gender, ['F', 'M', 'O'], true)) {
            http_response_code(400);
            return json_encode(['error' => 'Invalid gender.']);
        }

        // Email validation
        if (!$email) {
            http_response_code(400);
            return json_encode(['error' => 'Please provide a valid email address.']);
        }

        // Password complexity: min 8 chars, 1 number, 1 special char
        if (strlen($password) < 8 || !preg_match('/[0-9]/', $password) || !preg_match('/[^a-zA-Z0-9]/', $password)) {
            http_response_code(400);
            return json_encode(['error' => 'Password must be at least 8 characters long and contain at least 1 number and 1 special character.']);
        }
        if ($password !== $confirmPassword) {
            http_response_code(400);
            return json_encode(['error' => 'Passwords do not match.']);
        }

        try {
            $pdo = $this->dbConnection->getPdo();
            
            // Check display name uniqueness
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE display_name = ?');
            $stmt->execute([$displayName]);
            if ((int)$stmt->fetchColumn() > 0) {
                http_response_code(400);
                return json_encode(['error' => 'This display name is already taken.']);
            }

            // Check email uniqueness
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
            $stmt->execute([$email]);
            if ((int)$stmt->fetchColumn() > 0) {
                http_response_code(400);
                return json_encode(['error' => 'An account with this email already exists.']);
            }
        } finally {
            $this->dbConnection->disconnect();
        }

        // Pre-fill interests & country flag from guest state if available
        $interests = [];
        $countryFlag = '';
        $oldGuestId = $_SESSION['user_id'] ?? null;
        if ($oldGuestId && str_starts_with($oldGuestId, 'g_')) {
            $guestFile = "/guest/" . session_id() . ".json";
            $guestData = $this->fileStore->readJson($guestFile);
            $interests = $guestData['tags'] ?? [];
            $countryFlag = $guestData['country_flag'] ?? '';
        }

        if ($countryFlag === '') {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $countryFlag = \App\Helpers\IpGeolocation::getFlagEmoji($ip);
        }

        // Save to registration draft
        $_SESSION['reg_draft'] = [
            'username' => $displayName,
            'display_name' => $displayName,
            'age' => $age,
            'gender' => $gender,
            'email' => $email,
            'password' => $password,
            'interests' => $interests,
            'country_flag' => $countryFlag,
            'verified' => false
        ];

        return json_encode(['success' => true]);
    }

    /**
     * Send OTP to draft email address.
     */
    public function sendOTP(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        header('Content-Type: application/json');

        $draft = $_SESSION['reg_draft'] ?? null;
        if (!$draft || empty($draft['email'])) {
            http_response_code(400);
            return json_encode(['error' => 'No registration in progress.']);
        }

        $sessionId = session_id();
        $code = $this->authService->generateOTP($sessionId);
        
        $body = "Hi " . $draft['display_name'] . ",\n\n";
        $body .= "Thank you for registering at ChatArena!\n";
        $body .= "Your verification OTP code is: " . $code . "\n\n";
        $body .= "This code will expire in 10 minutes.\n\n";
        $body .= "If you did not request this, please ignore this email.\n";

        $sent = $this->mailService->send($draft['email'], "Your ChatArena verification code", $body);
        if (!$sent) {
            http_response_code(500);
            return json_encode(['error' => 'Failed to send verification email.']);
        }

        return json_encode(['success' => true]);
    }

    /**
     * Verify submitted OTP code.
     */
    public function verifyOTP(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        header('Content-Type: application/json');

        $draft = &$_SESSION['reg_draft'];
        if (!$draft) {
            http_response_code(400);
            return json_encode(['error' => 'No registration in progress.']);
        }

        $code = Sanitizer::clean($_POST['code'] ?? '');
        if ($code === '' || strlen($code) !== 6) {
            http_response_code(400);
            return json_encode(['error' => 'Verification code must be exactly 6 digits.']);
        }

        $sessionId = session_id();
        $valid = $this->authService->verifyOTP($sessionId, $code);

        if ($valid) {
            $draft['verified'] = true;
            return json_encode(['success' => true]);
        }

        http_response_code(400);
        return json_encode(['error' => 'Invalid or expired verification code.']);
    }

    /**
     * Step 4: Complete profile, save user record in DB, and migrate guest history.
     */
    public function completeProfile(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        header('Content-Type: application/json');

        $draft = $_SESSION['reg_draft'] ?? null;
        if (!$draft || !$draft['verified']) {
            http_response_code(400);
            return json_encode(['error' => 'Please verify your email address first.']);
        }

        $bio = Sanitizer::clean($_POST['bio'] ?? '');
        $interestsInput = $_POST['interests'] ?? '';

        if (mb_strlen($bio) > 160) {
            http_response_code(400);
            return json_encode(['error' => 'Bio cannot exceed 160 characters.']);
        }

        // Parse interest tags: max 5 tags, max 12 chars each
        $interests = [];
        if (is_string($interestsInput) && trim($interestsInput) !== '') {
            $parts = explode(',', $interestsInput);
            foreach ($parts as $part) {
                $clean = Sanitizer::clean($part);
                if ($clean !== '' && mb_strlen($clean) <= 12 && count($interests) < 5) {
                    $interests[] = $clean;
                }
            }
        } else {
            $interests = $draft['interests'] ?? [];
        }

        $draft['bio'] = $bio;
        $draft['interests'] = $interests;

        // Process profile avatar upload if present
        $avatarPath = null;
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $tempUserId = 'tmp_' . bin2hex(random_bytes(6));
            $uploaded = $this->avatarService->upload($_FILES['avatar'], $tempUserId);
            if ($uploaded) {
                $avatarPath = $uploaded;
            } else {
                http_response_code(400);
                return json_encode(['error' => 'Invalid avatar image. Must be JPEG/PNG/WebP and under 2MB.']);
            }
        }

        $draft['avatar_path'] = $avatarPath;

        // Commit to DB and migrate old guest data
        $oldGuestId = $_SESSION['user_id'] ?? null;
        $sessionId = session_id();

        try {
            $userId = $this->authService->registerUser($draft, $sessionId, $oldGuestId);
            
            // Rename temporary avatar file to the actual user ID
            if ($avatarPath !== null && file_exists($avatarPath)) {
                $realAvatarPath = dirname($avatarPath) . '/' . $userId . '.webp';
                rename($avatarPath, $realAvatarPath);
                $avatarPath = $realAvatarPath;

                // Update database path
                $pdo = $this->dbConnection->getPdo();
                $stmt = $pdo->prepare('UPDATE users SET avatar_path = ? WHERE anonymous_id = ?');
                $stmt->execute([$avatarPath, $userId]);
            }
        } catch (Exception $e) {
            http_response_code(500);
            return json_encode(['error' => 'Registration database write failed: ' . $e->getMessage()]);
        }

        // Establish the registered session
        $_SESSION['user_type'] = 'registered';
        $_SESSION['user_id'] = $userId;
        $_SESSION['verified'] = true;
        unset($_SESSION['reg_draft']);

        // Set base url path for redirecting
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $baseDir = rtrim(dirname($scriptName), '/\\');

        return json_encode([
            'success' => true,
            'redirect' => $baseDir . '/dashboard'
        ]);
    }

    /**
     * Authenticate email & password login.
     */
    public function login(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        header('Content-Type: application/json');

        $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);

        if (!$email || $password === '') {
            http_response_code(400);
            return json_encode(['error' => 'Please fill in both email and password.']);
        }

        try {
            $pdo = $this->dbConnection->getPdo();
            $stmt = $pdo->prepare('SELECT anonymous_id, password_hash, user_type, verified, is_banned FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                http_response_code(401);
                return json_encode(['error' => 'Invalid email or password.']);
            }

            if ((int)$user['is_banned'] === 1) {
                http_response_code(403);
                return json_encode(['error' => 'This account has been suspended.']);
            }

            // Establish session
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['user_id'] = $user['anonymous_id'];
            $_SESSION['verified'] = (int)$user['verified'] === 1;

            // Handle Remember Me token
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', time() + (30 * 86400)); // 30 days
                
                $upd = $pdo->prepare('UPDATE users SET remember_token = ?, token_expires = ? WHERE anonymous_id = ?');
                $upd->execute([$token, $expires, $user['anonymous_id']]);

                setcookie('remember_token', $token, time() + (30 * 86400), '/', '', false, true);
            }

            $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
            $baseDir = rtrim(dirname($scriptName), '/\\');

            return json_encode([
                'success' => true,
                'redirect' => $baseDir . '/dashboard'
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            return json_encode(['error' => 'Internal authentication error: ' . $e->getMessage()]);
        } finally {
            $this->dbConnection->disconnect();
        }
    }

    /**
     * Clear and destroy active user sessions.
     */
    public function logout(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Clear remember token in DB
        $userId = $_SESSION['user_id'] ?? '';
        if ($userId !== '') {
            try {
                $pdo = $this->dbConnection->getPdo();
                $stmt = $pdo->prepare('UPDATE users SET remember_token = NULL, token_expires = NULL WHERE anonymous_id = ?');
                $stmt->execute([$userId]);
            } catch (Exception) {}
            finally {
                $this->dbConnection->disconnect();
            }
        }

        // Destroy session cookies & parameters
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        // Delete remember cookie
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/');
        }

        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $baseDir = rtrim(dirname($scriptName), '/\\');

        header('Location: ' . $baseDir . '/');
        exit;
    }

    /**
     * Availability check for display name / username.
     */
    public function checkUsername(): string {
        header('Content-Type: application/json');
        
        $username = Sanitizer::clean($_GET['u'] ?? '');
        if ($username === '') {
            return json_encode(['available' => false]);
        }

        try {
            $pdo = $this->dbConnection->getPdo();
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = ? OR display_name = ?');
            $stmt->execute([$username, $username]);
            $count = (int)$stmt->fetchColumn();
            
            return json_encode(['available' => $count === 0]);
        } catch (Exception) {
            return json_encode(['available' => false]);
        } finally {
            $this->dbConnection->disconnect();
        }
    }
}
