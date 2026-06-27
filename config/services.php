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

    'brevo' => [
        'api_key' => env('BREVO_API_KEY', ''),
    ],

    'analytics' => [
        'url' => env('ANALYTICS_SERVICE_URL', 'http://localhost:8003'),
    ],

    'vnpay' => [
        'tmncode' => env('VNPAY_TMNCODE', ''),
        'hashsecret' => env('VNPAY_HASHSECRET', ''),
        'url' => env('VNPAY_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html'),
        'return_url' => env('VNPAY_RETURN_URL', ''),
    ],

    'momo' => [
        'partner_code' => env('MOMO_PARTNER_CODE', ''),
        'access_key' => env('MOMO_ACCESS_KEY', ''),
        'secret_key' => env('MOMO_SECRET_KEY', ''),
        'endpoint' => env('MOMO_ENDPOINT', 'https://test-payment.momo.vn/v2/gateway/api/create'),
    ],

    'zalopay' => [
        'app_id' => env('ZALOPAY_APP_ID', ''),
        'key1' => env('ZALOPAY_KEY1', ''),
        'key2' => env('ZALOPAY_KEY2', ''),
        'endpoint' => env('ZALOPAY_ENDPOINT', 'https://sb-openapi.zalopay.vn/v2/create'),
    ],

    'push' => [
        'driver' => env('PUSH_DRIVER', 'log'),
        'fcm' => [
            'server_key' => env('FCM_SERVER_KEY'),
        ],
    ],

    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'twilio' => [
            'sid' => env('TWILIO_SID'),
            'token' => env('TWILIO_TOKEN'),
            'from' => env('TWILIO_FROM'),
        ],
        'vonage' => [
            'key' => env('VONAGE_KEY'),
            'secret' => env('VONAGE_SECRET'),
            'from' => env('VONAGE_FROM', 'Aventura'),
        ],
    ],

];
