<?php
declare(strict_types=1);

return [
    // Rate limits (actions per window)
    'messages' => [
        'max_requests' => 20,
        'window' => 60, // 1 minute
    ],
    'connections' => [
        'max_requests' => 5,
        'window' => 60, // 1 minute
    ],
    'reports' => [
        'max_requests' => 3,
        'window' => 3600, // 1 hour
    ],
    'new_chats' => [
        'max_requests' => 10,
        'window' => 3600, // 1 hour
    ],
    
    // Abuse bans
    'ban_duration' => 86400, // 24 hours soft ban on IP / fingerprint
];
