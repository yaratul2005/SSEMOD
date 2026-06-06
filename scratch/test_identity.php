<?php
declare(strict_types=1);

// Import bootstrap init
$container = require_once __DIR__ . '/../bootstrap/init.php';

use App\Services\AuthService;
use App\Services\PrivilegeService;
use App\Services\AvatarService;

echo "=== ChatArena Identity System Tests ===\n\n";

$authService = $container->get(AuthService::class);
$avatarService = $container->get(AvatarService::class);

// 1. Password Hashing Test
echo "1. Testing Password Hashing...\n";
$password = "Secret123!";
$hash = $authService->hashPassword($password);
if (password_verify($password, $hash)) {
    echo "   [PASS] Password verified successfully using Argon2id.\n";
} else {
    echo "   [FAIL] Password verification failed.\n";
}

// 2. OTP Generation and Verification Test
echo "\n2. Testing OTP Generation & Validation...\n";
$dummySessionId = "test_sess_" . bin2hex(random_bytes(6));
$otpCode = $authService->generateOTP($dummySessionId);
echo "   Generated OTP code: {$otpCode}\n";

$isValid = $authService->verifyOTP($dummySessionId, $otpCode);
if ($isValid) {
    echo "   [PASS] OTP verified successfully.\n";
} else {
    echo "   [FAIL] OTP verification failed.\n";
}

// Verify that once validated, the OTP is deleted/invalidated
$isStillValid = $authService->verifyOTP($dummySessionId, $otpCode);
if (!$isStillValid) {
    echo "   [PASS] OTP correctly invalidated/deleted after validation.\n";
} else {
    echo "   [FAIL] OTP was still valid after verification.\n";
}

// 3. Privilege Layer Checks
echo "\n3. Testing Privilege Layer...\n";

// Set mock session values
$_SESSION['user_type'] = 'guest';
$_SESSION['verified'] = false;
$guestChatCan = PrivilegeService::can('live_chat');
$guestUploadCan = PrivilegeService::can('upload_photo');

echo "   Guest can use live chat? " . ($guestChatCan ? "Yes" : "No") . "\n";
echo "   Guest can upload photo? " . ($guestUploadCan ? "Yes" : "No") . "\n";

if ($guestChatCan === true && $guestUploadCan === false) {
    echo "   [PASS] Guest privilege maps behave correctly.\n";
} else {
    echo "   [FAIL] Guest privilege map mismatch.\n";
}

$_SESSION['user_type'] = 'registered';
$_SESSION['verified'] = true;
$regChatCan = PrivilegeService::can('live_chat');
$regUploadCan = PrivilegeService::can('upload_photo');

echo "   Registered & Verified can use live chat? " . ($regChatCan ? "Yes" : "No") . "\n";
echo "   Registered & Verified can upload photo? " . ($regUploadCan ? "Yes" : "No") . "\n";

if ($regChatCan === true && $regUploadCan === true) {
    echo "   [PASS] Registered user privilege maps behave correctly.\n";
} else {
    echo "   [FAIL] Registered user privilege map mismatch.\n";
}

echo "\n=== All Tests Run Complete ===\n";
