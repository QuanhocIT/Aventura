<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker Services
    |--------------------------------------------------------------------------
    |
    | Mỗi microservice Python (Email, Chatbot, Analytics) có một breaker
    | riêng. failure_threshold: số lỗi liên tiếp trước khi mở mạch (OPEN).
    | open_seconds: thời gian giữ mạch OPEN trước khi cho phép 1 request
    | dò thử (HALF_OPEN). max_open_seconds: trần backoff khi probe liên
    | tục thất bại, tránh mạch mở vô hạn.
    |
    */

    'email_service' => [
        'failure_threshold' => 3,
        'open_seconds' => 60,
        'max_open_seconds' => 300,
    ],

    'chatbot_service' => [
        'failure_threshold' => 3,
        'open_seconds' => 60,
        'max_open_seconds' => 300,
    ],

    'analytics_service' => [
        'failure_threshold' => 3,
        'open_seconds' => 90,
        'max_open_seconds' => 300,
    ],

];
