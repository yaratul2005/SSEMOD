<?php
declare(strict_types=1);

// 1. Register PSR-4 Autoloader
spl_autoload_register(static function (string $class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../app/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// 2. Load .env File
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        
        if (str_contains($line, '=')) {
            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            
            // Remove wrapping quotes
            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }
            
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
            putenv("{$name}={$value}");
        }
    }
}

// 3. Load Configurations
$config = [
    'app' => require __DIR__ . '/../config/app.php',
    'database' => require __DIR__ . '/../config/database.php',
    'sse' => require __DIR__ . '/../config/sse.php',
    'limits' => require __DIR__ . '/../config/limits.php',
    'mail' => require __DIR__ . '/../config/mail.php',
];

// 4. Configure PHP Settings
date_default_timezone_set($config['app']['timezone']);
if ($config['app']['debug']) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(0);
}

// 5. Initialize the Dependency Injection Container
class Container {
    private array $bindings = [];
    private array $instances = [];

    public function bind(string $key, callable $resolver): void {
        $this->bindings[$key] = $resolver;
    }

    public function singleton(string $key, callable $resolver): void {
        $this->instances[$key] = null; // Mark as singleton
        $this->bindings[$key] = $resolver;
    }

    public function get(string $key) {
        if (array_key_exists($key, $this->instances) && $this->instances[$key] !== null) {
            return $this->instances[$key];
        }
        
        if (isset($this->bindings[$key])) {
            $resolved = $this->bindings[$key]($this);
            if (array_key_exists($key, $this->instances)) {
                $this->instances[$key] = $resolved;
            }
            return $resolved;
        }
        
        // Simple auto-wiring for classes with constructors
        if (class_exists($key)) {
            $reflector = new ReflectionClass($key);
            if (!$reflector->isInstantiable()) {
                throw new Exception("Class {$key} is not instantiable.");
            }
            
            $constructor = $reflector->getConstructor();
            if ($constructor === null) {
                return new $key();
            }
            
            $parameters = $constructor->getParameters();
            $dependencies = [];
            
            foreach ($parameters as $parameter) {
                $type = $parameter->getType();
                if ($type === null) {
                    throw new Exception("Cannot auto-wire parameter {$parameter->getName()} in class {$key} (no type hint).");
                }
                if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                    $dependencies[] = $this->get($type->getName());
                } else {
                    throw new Exception("Cannot auto-wire scalar parameter {$parameter->getName()} in class {$key}.");
                }
            }
            
            return $reflector->newInstanceArgs($dependencies);
        }
        
        throw new Exception("No binding found for {$key}");
    }
}

$container = new Container();

// Bind config array so services can access it
$container->singleton('config', fn() => $config);

// Bind core classes
$container->singleton(App\Database\Connection::class, fn($c) => new App\Database\Connection($c->get('config')['database']));
$container->singleton(App\FileStore\LockManager::class, fn($c) => new App\FileStore\LockManager($c->get('config')['app']['storage_path'] . '/filestore/locks'));
$container->singleton(App\FileStore\FileStore::class, fn($c) => new App\FileStore\FileStore($c->get('config')['app']['storage_path'] . '/filestore', $c->get(App\FileStore\LockManager::class)));
$container->singleton(App\FileStore\QueueStore::class, fn($c) => new App\FileStore\QueueStore($c->get(App\FileStore\FileStore::class)));
$container->singleton(App\FileStore\MessageBuffer::class, fn($c) => new App\FileStore\MessageBuffer($c->get(App\FileStore\FileStore::class), $c->get(App\Database\Connection::class)));

$container->singleton(App\Services\SessionService::class, fn($c) => new App\Services\SessionService(
    $c->get(App\Database\Connection::class),
    $c->get(App\FileStore\FileStore::class)
));
$container->singleton(App\Services\RateLimitService::class, fn($c) => new App\Services\RateLimitService($c->get('config')['limits'], $c->get(App\FileStore\FileStore::class), $c->get(App\Database\Connection::class)));
$container->singleton(App\Services\MatchService::class, fn($c) => new App\Services\MatchService(
    $c->get(App\FileStore\QueueStore::class),
    $c->get(App\FileStore\FileStore::class),
    $c->get(App\Database\Connection::class)
));
$container->singleton(App\Services\MessageService::class, fn($c) => new App\Services\MessageService(
    $c->get(App\FileStore\MessageBuffer::class),
    $c->get(App\FileStore\FileStore::class),
    $c->get(App\Database\Connection::class)
));
$container->singleton(App\Services\SSEService::class, fn($c) => new App\Services\SSEService(
    $c->get('config')['sse'],
    $c->get(App\FileStore\FileStore::class),
    $c->get(App\FileStore\QueueStore::class)
));
$container->singleton(App\Services\PresenceService::class, fn($c) => new App\Services\PresenceService($c->get(App\FileStore\FileStore::class)));
$container->singleton(App\Services\DirectChatService::class, fn($c) => new App\Services\DirectChatService(
    $c->get(App\FileStore\FileStore::class),
    $c->get(App\Database\Connection::class)
));

// User Identity Services
$container->singleton(App\Services\AuthService::class, fn($c) => new App\Services\AuthService(
    $c->get(App\Database\Connection::class),
    $c->get(App\FileStore\FileStore::class)
));
$container->singleton(App\Services\MailService::class, fn($c) => new App\Services\MailService(
    $c->get('config')['mail'] ?? []
));
$container->singleton(App\Services\AvatarService::class, fn($c) => new App\Services\AvatarService(
    $c->get('config')['app']
));

return $container;
