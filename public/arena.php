<?php
declare(strict_types=1);

// Route virtual path as /arena and require front controller
$_SERVER['REQUEST_URI'] = '/arena';
require_once __DIR__ . '/index.php';
