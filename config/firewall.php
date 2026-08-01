<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Web Application Firewall (WAF) Configuration
    |--------------------------------------------------------------------------
    |
    | Define custom rules to monitor and filter traffic.
    |
    */

    'waf' => [
        'login' => [
            // Maximum failed login attempts allowed before IP is blocked
            'max_attempts' => (int) env('WAF_LOGIN_MAX_ATTEMPTS', 5),

            // Time window (in seconds) to count failed attempts
            'decay_seconds' => (int) env('WAF_LOGIN_DECAY_SECONDS', 10),

            // Duration (in minutes) to block the offending IP
            'block_minutes' => (int) env('WAF_LOGIN_BLOCK_MINUTES', 30),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Global Rate Limiter Configuration
    |--------------------------------------------------------------------------
    |
    | Define global rate limiting threshold for requests.
    |
    */

    'rate_limit' => [
        'global' => [
            // Maximum requests allowed within the decay time
            'max_attempts' => (int) env('RATE_LIMIT_GLOBAL_MAX', 60),

            // Decay time window (in seconds)
            'decay_seconds' => (int) env('RATE_LIMIT_GLOBAL_DECAY', 60),
        ],
    ],

];
