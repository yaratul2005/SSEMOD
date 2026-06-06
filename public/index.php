<?php
declare(strict_types=1);

// 1. Boot the application container
/** @var Container $container */
$container = require_once __DIR__ . '/../bootstrap/init.php';

// 2. Parse request details
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$parsedUrl = parse_url($requestUri);
$pathOnly = $parsedUrl['path'] ?? '/';

$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$baseDir = dirname($scriptName);

// Redirect base path without trailing slash to trailing slash version to fix relative assets
if ($baseDir !== '/' && $baseDir !== '\\') {
    $normalizedBase = rtrim(str_replace('\\', '/', $baseDir), '/');
    $normalizedPath = rtrim(str_replace('\\', '/', $pathOnly), '/');
    if ($normalizedPath === $normalizedBase && !str_ends_with($pathOnly, '/')) {
        $query = isset($parsedUrl['query']) ? '?' . $parsedUrl['query'] : '';
        header('Location: ' . $normalizedBase . '/' . $query, true, 301);
        exit;
    }
}

$path = $pathOnly;
if ($baseDir !== '/' && $baseDir !== '\\' && str_starts_with($path, $baseDir)) {
    $path = substr($path, strlen($baseDir));
}
if ($path === '' || $path === false) {
    $path = '/';
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// 3. Resolve routes
$routes = require __DIR__ . '/../routes/routes.php';

$matchedRoute = null;
$routeParams = [];
$matchedPattern = '';

foreach ($routes[$method] ?? [] as $routePath => $target) {
    $pattern = '@^' . preg_replace('/\{[a-zA-Z0-9_]+\}/', '([^/]+)', $routePath) . '$@';
    if (preg_match($pattern, $path, $matches)) {
        $matchedRoute = $target;
        array_shift($matches); // Remove full match
        $routeParams = $matches;
        $matchedPattern = $routePath;
        break;
    }
}

if ($matchedRoute === null) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Route not found']);
    exit;
}

[$controllerClass, $action] = $matchedRoute;

try {
    // 4. Run Global / Route Middleware
    // For POST requests (except queue actions maybe, or all POSTs), verify CSRF.
    // For all routes, run session initialization and rate limiting.
    
    // We execute SessionGuard middleware
    $sessionGuard = $container->get(App\Middleware\SessionGuard::class);
    $sessionGuard->handle();
    
    if ($method === 'POST') {
        $csrf = $container->get(App\Middleware\CSRF::class);
        $csrf->handle();
    }
    
    // Rate limit check
    $rateLimit = $container->get(App\Middleware\RateLimit::class);
    $rateLimit->handle($matchedPattern);
    
    // 5. Dispatch controller
    $controller = $container->get($controllerClass);
    
    // Call controller action with dynamic parameters
    $response = $controller->$action(...$routeParams);
    
    // Output response if string (like HTML)
    if (is_string($response)) {
        echo $response;
    }
    
} catch (\App\Middleware\MiddlewareException $e) {
    http_response_code($e->getCode() ?: 400);
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
    exit;
} catch (\Exception $e) {
    // Log error
    $logFile = $container->get('config')['app']['storage_path'] . '/logs/error.log';
    $timestamp = date('Y-m-d H:i:s');
    error_log("[{$timestamp}] Error: {$e->getMessage()}\nTrace: {$e->getTraceAsString()}\n", 3, $logFile);
    
    http_response_code(500);
    header('Content-Type: application/json');
    $debug = $container->get('config')['app']['debug'];
    echo json_encode([
        'error' => 'Internal Server Error',
        'message' => $debug ? $e->getMessage() : null,
        'trace' => $debug ? $e->getTraceAsString() : null
    ]);
    exit;
}
