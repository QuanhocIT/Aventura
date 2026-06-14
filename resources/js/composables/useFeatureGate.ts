import { usePage } from '@inertiajs/vue3';

export type FeatureKey =
    | 'kitchen_display'
    | 'qr_ordering'
    | 'inventory_basic'
    | 'hr_timekeeping'
    | 'hr_full'
    | 'advanced_analytics'
    | 'realtime'
    | 'fraud_detection'
    | 'email_reports'
    | 'ai_advisor'
    | 'supplier_portal'
    | 'ai_forecasting'
    | 'api_access';

export const FEATURE_LABELS: Record<FeatureKey, { label: string; required_plan: string }> = {
    kitchen_display:    { label: 'Màn hình Bếp (Kitchen Display)', required_plan: 'Cơ Bản' },
    qr_ordering:        { label: 'Đặt món qua QR', required_plan: 'Cơ Bản' },
    inventory_basic:    { label: 'Quản lý Tồn kho', required_plan: 'Cơ Bản' },
    hr_timekeeping:     { label: 'Chấm công & Lịch làm việc', required_plan: 'Cơ Bản' },
    hr_full:            { label: 'Quản lý Lương & Nhân sự', required_plan: 'Chuyên Nghiệp' },
    advanced_analytics: { label: 'Phân tích & Báo cáo Nâng cao', required_plan: 'Chuyên Nghiệp' },
    realtime:           { label: 'Cập nhật Thời gian thực', required_plan: 'Cơ Bản' },
    fraud_detection:    { label: 'Phát hiện Gian lận', required_plan: 'Chuyên Nghiệp' },
    email_reports:      { label: 'Email Báo cáo Tự động', required_plan: 'Chuyên Nghiệp' },
    ai_advisor:         { label: 'AI Tư vấn Chiến lược', required_plan: 'Chuyên Nghiệp' },
    supplier_portal:    { label: 'Cổng Nhà Cung Cấp', required_plan: 'Doanh Nghiệp' },
    ai_forecasting:     { label: 'AI Dự báo Tồn kho', required_plan: 'Doanh Nghiệp' },
    api_access:         { label: 'Truy cập API', required_plan: 'Doanh Nghiệp' },
};

export function useFeatureGate() {
    const page = usePage();

    const features = (): Record<string, boolean> => {
        const tenant = (page.props as any).tenant;
        return tenant?.quota_summary?.features ?? {};
    };

    const can = (feature: FeatureKey): boolean => {
        return features()[feature] === true;
    };

    const planCode = (): string => {
        const tenant = (page.props as any).tenant;
        return tenant?.quota_summary?.plan_code ?? 'free';
    };

    const planName = (): string => {
        const tenant = (page.props as any).tenant;
        return tenant?.plan?.name ?? 'Miễn Phí';
    };

    return { can, planCode, planName, FEATURE_LABELS };
}
