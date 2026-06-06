<?php
declare(strict_types=1);

return [
    // Adaptive heartbeat configurations
    'devices' => [
        'desktop' => [
            'heartbeat' => 3,       // seconds
            'poll_interval' => 0.25, // seconds (ultra-fast check)
        ],
        'mobile' => [
            'heartbeat' => 8,
            'poll_interval' => 1.0,  // seconds
        ],
        'slow' => [
            'heartbeat' => 12,
            'poll_interval' => 2.0,  // seconds
        ],
    ],
    
    // Performance and Host Protection
    'max_execution_time' => 25, // seconds (stay safely below typical 30s max_execution_time)
    'memory_ceiling' => 16 * 1024 * 1024, // 16 MB limit per stream process
    'memory_check_cycle' => 5, // check memory usage every N cycles
    
    // Client configuration overrides
    'reconnect_backoff_ms' => 1000,
    'max_reconnect_backoff_ms' => 15000,
];
