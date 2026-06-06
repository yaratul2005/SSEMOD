<?php
declare(strict_types=1);

return [
    'name' => 'Stranger Chat',
    'env' => $_ENV['APP_ENV'] ?? 'production',
    'debug' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true',
    'url' => $_ENV['APP_URL'] ?? 'http://localhost/socialcc',
    'timezone' => 'UTC',
    'storage_path' => dirname(__DIR__) . '/storage',
];
