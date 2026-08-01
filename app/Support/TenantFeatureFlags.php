<?php

namespace App\Support;

final class TenantFeatureFlags
{
    public const FLAGS = [
        'kitchen_display', 'qr_ordering', 'inventory_basic', 'supplier_portal', 'kiosk_mode', 'table_reservation',
        'hr_timekeeping', 'hr_full', 'shift_management',
        'crm_loyalty', 'marketing_campaign', 'custom_domain',
        'advanced_analytics', 'realtime', 'fraud_detection', 'email_reports',
        'ai_advisor', 'ai_forecasting',
        'delivery_integration', 'e_invoice', 'multi_branch_sync', 'multi_currency', 'api_access', 'automated_backup',
    ];

    public static function keys(): array
    {
        return self::FLAGS;
    }

    public static function label(string $feature): string
    {
        return match ($feature) {
            'kitchen_display' => 'Màn hình bếp',
            'qr_ordering' => 'Đặt món QR',
            'inventory_basic' => 'Quản lý tồn kho',
            'supplier_portal' => 'Cổng nhà cung cấp',
            'hr_timekeeping' => 'Chấm công & lịch',
            'hr_full' => 'Nhân sự & lương',
            'advanced_analytics' => 'Phân tích nâng cao',
            'realtime' => 'Cập nhật thời gian thực',
            'fraud_detection' => 'Phát hiện gian lận',
            'email_reports' => 'Báo cáo email',
            'ai_advisor' => 'AI tư vấn',
            'ai_forecasting' => 'AI dự báo',
            'api_access' => 'API',
            default => str_replace('_', ' ', ucfirst($feature)),
        };
    }
}
