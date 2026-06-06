<?php
declare(strict_types=1);

return [
    'GET' => [
        '/' => [\App\Controllers\ArenaController::class, 'index'],
        '/arena' => [\App\Controllers\ArenaController::class, 'index'],
        '/api/users/online' => [\App\Controllers\ArenaController::class, 'onlineUsers'],
        '/stream/chat' => [\App\Controllers\StreamController::class, 'chat'],
        '/stream/direct/{room_id}' => [\App\Controllers\StreamController::class, 'chat'],
        '/stream/queue' => [\App\Controllers\StreamController::class, 'queue'],
        '/stream/presence' => [\App\Controllers\StreamController::class, 'presence'],
        '/stream/arena-presence' => [\App\Controllers\StreamController::class, 'arenaPresence'],
        '/stream/system' => [\App\Controllers\StreamController::class, 'system'],
        '/api/check-username' => [\App\Controllers\AuthController::class, 'checkUsername'],
        '/avatar/{user_id}' => [\App\Controllers\ProfileController::class, 'serveAvatar'],
        '/attachment/{filename}' => [\App\Controllers\ProfileController::class, 'serveAttachment'],
        '/dashboard' => [\App\Controllers\DashboardController::class, 'index'],
        '/inbox' => [\App\Controllers\InboxController::class, 'index'],
    ],
    'POST' => [
        '/queue/join' => [\App\Controllers\MatchController::class, 'join'],
        '/queue/leave' => [\App\Controllers\MatchController::class, 'leave'],
        '/chat/send' => [\App\Controllers\ChatController::class, 'send'],
        '/api/message/send' => [\App\Controllers\ChatController::class, 'send'],
        '/chat/typing' => [\App\Controllers\ChatController::class, 'typing'],
        '/chat/next' => [\App\Controllers\ChatController::class, 'next'],
        '/chat/report' => [\App\Controllers\ChatController::class, 'report'],
        '/api/chat/start/{user_id}' => [\App\Controllers\ArenaController::class, 'startChat'],
        '/api/settings/save' => [\App\Controllers\ArenaController::class, 'saveSettings'],
        '/guest/start' => [\App\Controllers\GuestController::class, 'store'],
        '/auth/register' => [\App\Controllers\AuthController::class, 'register'],
        '/auth/send-verification' => [\App\Controllers\AuthController::class, 'sendOTP'],
        '/auth/verify-otp' => [\App\Controllers\AuthController::class, 'verifyOTP'],
        '/auth/complete-profile' => [\App\Controllers\AuthController::class, 'completeProfile'],
        '/auth/login' => [\App\Controllers\AuthController::class, 'login'],
        '/auth/logout' => [\App\Controllers\AuthController::class, 'logout'],
        '/profile/avatar' => [\App\Controllers\ProfileController::class, 'uploadAvatar'],
        '/profile/update' => [\App\Controllers\ProfileController::class, 'update'],
    ]
];
