<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Data lifecycle and capacity policy
    |--------------------------------------------------------------------------
    |
    | Destructive maintenance is intentionally approval-first. Scheduled jobs
    | may collect metrics and produce dry-run reports, but they must not
    | permanently delete business data unless an operator explicitly approves
    | the cleanup run.
    |
    */
    'enabled' => (bool) env('DATA_LIFECYCLE_ENABLED', true),
    'require_approval' => (bool) env('DATA_LIFECYCLE_REQUIRE_APPROVAL', true),
    'allow_direct_cli_purge' => (bool) env('DATA_LIFECYCLE_ALLOW_DIRECT_CLI_PURGE', false),

    'orders' => [
        'archive_months' => (int) env('DATA_ORDERS_ARCHIVE_MONTHS', 3),
        'purge_months' => (int) env('DATA_ORDERS_PURGE_MONTHS', 36),
    ],

    'audit' => [
        'archive_months' => (int) env('DATA_AUDIT_ARCHIVE_MONTHS', 6),
    ],

    'logs' => [
        'search_query_logs' => 6,
        'notifications' => 6,
        'customer_behavior_logs' => 12,
        'webhook_deliveries' => 3,
        'commission_logs' => null,
        'loyalty_transactions' => null,
        'cash_transactions' => null,
        'inventory_transactions' => null,
    ],

    'storage' => [
        'snapshot_retention_days' => (int) env('DATA_STORAGE_SNAPSHOT_RETENTION_DAYS', 730),
        'orphan_grace_days' => (int) env('DATA_MEDIA_ORPHAN_GRACE_DAYS', 30),
        'orphan_batch_size' => (int) env('DATA_MEDIA_ORPHAN_BATCH_SIZE', 500),
        'warning_percentages' => [70, 85, 95, 100],
        'database_limit_gb' => env('DATA_DATABASE_LIMIT_GB') !== null
            ? (float) env('DATA_DATABASE_LIMIT_GB')
            : null,
        'tenant_database_limit_mb' => env('DATA_TENANT_DATABASE_LIMIT_MB') !== null
            ? (int) env('DATA_TENANT_DATABASE_LIMIT_MB')
            : null,
        'tenant_row_bytes' => [
            'orders' => 1200,
            'orders_archive' => 1200,
            'order_items' => 350,
            'order_items_archive' => 350,
            'audit_logs' => 1400,
            'media_assets' => 250,
        ],
    ],

    'backups' => [
        'disk' => env('BACKUP_DISK', env('AWS_BUCKET') ? 's3' : 'local'),
        'daily_days' => (int) env('BACKUP_DAILY_RETENTION_DAYS', 7),
        'weekly_weeks' => (int) env('BACKUP_WEEKLY_RETENTION_WEEKS', 8),
        'monthly_months' => (int) env('BACKUP_MONTHLY_RETENTION_MONTHS', 12),
    ],

    'archives' => [
        'disk' => env('ARCHIVE_DISK', env('AWS_BUCKET') ? 's3' : 'local'),
    ],

    'media' => [
        'disk' => env('MEDIA_DISK', env('FILESYSTEM_DISK', 'local')),
    ],

    'temporary' => [
        'retention_hours' => (int) env('DATA_TEMPORARY_RETENTION_HOURS', 24),
        'directories' => [
            'temp-backups',
            'temp-archives',
        ],
    ],

    'tenant_tables' => [
        'orders',
        'orders_archive',
        'order_items',
        'order_items_archive',
        'order_related_archives',
        'audit_logs',
        'media_assets',
        'customer_behavior_logs',
        'cash_transactions',
        'inventory_transactions',
        'loyalty_transactions',
    ],
];
