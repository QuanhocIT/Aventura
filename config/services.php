<?php

return [

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'support_alerts' => [
        'telegram_webhook' => env('TELEGRAM_WEBHOOK_URL'),
        'discord_webhook' => env('DISCORD_WEBHOOK_URL'),
    ],

    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT_URI'),
    ],

    'sepay' => [
        'bank' => env('SEPAY_BANK'),
        'account_number' => env('SEPAY_ACCOUNT_NUMBER'),
        'account_name' => env('SEPAY_ACCOUNT_NAME'),
        'checkout_url' => env('SEPAY_CHECKOUT_URL', 'https://qr.sepay.vn/img'),
        'qr_template' => env('SEPAY_QR_TEMPLATE', 'compact'),
    ],

    'email_microservice' => [
        'url' => env('EMAIL_SERVICE_URL', ''),
    ],

    'chatbot' => [
        'url' => env('CHATBOT_SERVICE_URL', ''),
    ],

];