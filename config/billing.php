<?php

return [
    'grace_period_days' => (int) env('BILLING_GRACE_PERIOD_DAYS', 30),
    'upcoming_due_days' => (int) env('BILLING_UPCOMING_DUE_DAYS', 7),
    'webhook_secret' => env('BILLING_WEBHOOK_SECRET'),
    'webhook_provider' => env('BILLING_WEBHOOK_PROVIDER', 'bank'),
    'queue' => env('BILLING_QUEUE', 'billing'),
];