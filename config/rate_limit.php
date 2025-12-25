<?php
/**
 * GDOLS Panel - Rate Limiting Configuration
 * Author: GoDiMyID
 * Website: godi.my.id
 * Version: 1.0.0
 * Description: Rate limiting rules and limits for API endpoints
 */

return [
    // Default rate limits (applied to all endpoints unless overridden)
    'default' => [
        [
            'max_requests' => 60,      // Maximum requests
            'window' => 60,           // Time window in seconds (1 minute)
        ],
        [
            'max_requests' => 1000,    // Maximum requests
            'window' => 3600,         // Time window in seconds (1 hour)
        ],
    ],

    // Endpoint-specific rate limits
    'endpoints' => [
        // Authentication endpoints - stricter limits
        'auth' => [
            [
                'max_requests' => 5,    // 5 login attempts
                'window' => 300,       // per 5 minutes
            ],
            [
                'max_requests' => 10,   // 10 login attempts
                'window' => 3600,      // per hour
            ],
        ],

        // Login endpoint - very strict to prevent brute force
        'login' => [
            [
                'max_requests' => 3,    // 3 attempts
                'window' => 300,       // per 5 minutes
            ],
            [
                'max_requests' => 10,   // 10 attempts
                'window' => 3600,      // per hour
            ],
        ],

        // Virtual host operations
        'vhost' => [
            [
                'max_requests' => 30,   // 30 requests
                'window' => 60,        // per minute
            ],
            [
                'max_requests' => 200,  // 200 requests
                'window' => 3600,      // per hour
            ],
        ],

        // Database operations
        'database' => [
            [
                'max_requests' => 20,   // 20 requests
                'window' => 60,        // per minute
            ],
            [
                'max_requests' => 150,  // 150 requests
                'window' => 3600,      // per hour
            ],
        ],

        // PHP extension management
        'php_extensions' => [
            [
                'max_requests' => 10,   // 10 requests
                'window' => 60,        // per minute
            ],
            [
                'max_requests' => 50,   // 50 requests
                'window' => 3600,      // per hour
            ],
        ],

        // Firewall management
        'firewall' => [
            [
                'max_requests' => 15,   // 15 requests
                'window' => 60,        // per minute
            ],
            [
                'max_requests' => 100,  // 100 requests
                'window' => 3600,      // per hour
            ],
        ],

        // Redis operations
        'redis' => [
            [
                'max_requests' => 40,   // 40 requests
                'window' => 60,        // per minute
            ],
            [
                'max_requests' => 500,  // 500 requests
                'window' => 3600,      // per hour
            ],
        ],

        // System monitoring (read-heavy, can be more lenient)
        'system' => [
            [
                'max_requests' => 100,  // 100 requests
                'window' => 60,        // per minute
            ],
            [
                'max_requests' => 2000, // 2000 requests
                'window' => 3600,      // per hour
            ],
        ],

        // Settings operations
        'settings' => [
            [
                'max_requests' => 20,   // 20 requests
                'window' => 60,        // per minute
            ],
            [
                'max_requests' => 200,  // 200 requests
                'window' => 3600,      // per hour
            ],
        ],
    ],

    // Whitelist IPs (these bypass rate limiting)
    'whitelist' => [
        '127.0.0.1',
        '::1',
        // Add trusted IPs here
    ],

    // Blacklist IPs (these are always blocked)
    'blacklist' => [
        // Add malicious IPs here
    ],

    // Storage configuration
    'storage' => [
        'driver' => 'file', // 'file' or 'redis'
        'redis' => [
            'host' => '127.0.0.1',
            'port' => 6379,
            'database' => 0,
            'prefix' => 'gdpanel:ratelimit:',
        ],
        'file' => [
            'path' => LOG_PATH . '/rate_limit',
            'cleanup_interval' => 3600, // Clean expired records every hour
        ],
    ],

    // Penalty configuration for repeated violations
    'penalties' => [
        'enabled' => true,
        'threshold' => 5, // Number of violations before penalty
        'penalty_duration' => 1800, // 30 minutes block
        'multiplier' => 2, // Multiply the normal window time
    ],

    // Response headers configuration
    'headers' => [
        'enabled' => true,
        'expose_headers' => true, // Expose rate limit info in response headers
        'reset_format' => 'unix_timestamp', // 'unix_timestamp' or 'seconds'
    ],
];
