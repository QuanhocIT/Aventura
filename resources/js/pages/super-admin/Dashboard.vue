<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Brain,
    Building2,
    CheckCircle2,
    Clock,
    Crown,
    FileText,
    Gauge,
    Heart,
    ShieldCheck,
    Siren,
    SlidersHorizontal,
    TrendingUp,
    TrendingDown,
    Users,
    XCircle,
    Activity,
    Server,
    Terminal,
    ChevronDown,
    ChevronUp,
    Download,
    Printer,
    Minimize2,
    Maximize2,
    LayoutGrid,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuCheckboxItem, DropdownMenuContent, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import AreaChart from '@/components/charts/AreaChart.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Echo from '@/lib/echo';

defineOptions({ layout: AppLayout });

type GrowthPoint = {
    label: string;
    month: string;
    new_tenants: number;
    free_to_pro: number;
    conversion_rate: number;
};

type RankedTenant = {
    restaurant_id: number;
    name: string;
    code: string | null;
    orders_count?: number;
    storage_bytes?: number;
    files_count?: number;
};

type StatChange = {
    percent: number | null;
    label: string;
    trend: 'up' | 'down' | 'neutral';
};

const props = defineProps<{
    stats: {
        total_restaurants: number;
        active: number;
        suspended: number;
        expired: number;
        total_users: number;
        pro_plan: number;
    };
    saasMetrics: {
        mrr: number;
        arr: number;
        churn_rate: number;
        churned_this_month: number;
        active_subscriptions: number;
        paid_tenants: number;
    };
    tenantGrowth: GrowthPoint[];
    tenantGrowthCompare: GrowthPoint[] | null;
    filters: {
        range: string;
        compare: boolean;
    };
    statChanges: {
        total_restaurants: StatChange;
        total_users: StatChange;
        pro_plan: StatChange;
        mrr: StatChange;
    };
    resourceInsights: {
        top_order_restaurants: RankedTenant[];
        top_storage_restaurants: RankedTenant[];
        totals: {
            orders_last_30_days: number;
            storage_bytes: number;
        };
    };
    recentRestaurants: Array<{
        id: number;
        name: string;
        status: string;
        plan: string;
        plan_code: string;
        owner: string;
        created_at: string;
    }>;
    planDistribution: Array<{ name: string; code: string; count: number }>;
    cohortAnalysis: Array<{ cohort: string; month: string; total: number; m1: number | null; m3: number | null; m6: number | null }>;
    revenueBreakdown: Array<{ label: string; month: string; by_plan: Record<string, number>; total: number }>;
    revenueRetention: {
        period_label: string;
        previous_label: string;
        starting_mrr: number;
        expansion: number;
        contraction: number;
        churned: number;
        nrr: number | null;
        grr: number | null;
    };
    planPerformance: Array<{ plan_code: string; plan_name: string; tenant_count: number; orders_30d: number; avg_orders_per_tenant_per_day: number; active_tenant_ratio: number }>;
    dashboardAlerts: Array<{ source: string; severity: 'critical' | 'warning'; metric_key: string; title: string; message: string; triggered_at: string | null }>;
    reportSubscription: { is_active: boolean; frequency: 'weekly' | 'monthly'; last_sent_at: string | null };
    aiInsights: {
        churn_risks: Array<{ restaurant_id: number; name: string; risk_score: number; risk_level: string; reasons: string[]; actions: string[] }>;
        health_scores: Array<{ restaurant_id: number; name: string; score: number; level: string; order_count_30d: number }>;
        segments: { active_pro: number; trial_active: number; free_inactive: number; at_risk: number; churned: number; new: number };
        mrr_forecast: Array<{ month: string; predicted_mrr: number; trend: string }>;
        overall_health: { score: number; label: string; color: string };
    };
    supportOverview: {
        monitoring: {
            failed_jobs: number;
            pending_jobs: number;
            api_error_rate: number;
            slow_queries: number;
        };
        stats: {
            tickets_open: number;
            alerts_open: number;
        };
    };
}>();

const selectedKpiIdx = ref(0);

// --- F2: Cập nhật real-time qua Reverb/Echo — làm mới các chỉ số thay đổi nhanh
// (ticket mở, cảnh báo ngưỡng...) mà không cần tải lại trang ---
const REALTIME_REFRESH_PROPS = [
    'stats',
    'statChanges',
    'saasMetrics',
    'tenantGrowth',
    'resourceInsights',
    'recentRestaurants',
    'supportOverview',
    'dashboardAlerts',
] as const;

let realtimeRefreshTimer: ReturnType<typeof setTimeout> | null = null;

function scheduleRealtimeRefresh() {
    // Gộp nhiều sự kiện liên tiếp trong khoảng ngắn thành một lần reload duy nhất
    if (realtimeRefreshTimer) {
        clearTimeout(realtimeRefreshTimer);
    }

    realtimeRefreshTimer = setTimeout(() => {
        router.reload({
            only: [...REALTIME_REFRESH_PROPS],
            preserveState: true,
            preserveScroll: true,
        });
    }, 1500);
}

onMounted(() => {
    if (Echo) {
        Echo.channel('superadmin.dashboard')
            .listen('.superadmin.dashboard.updated', () => {
                scheduleRealtimeRefresh();
            });
    }
});

onUnmounted(() => {
    if (realtimeRefreshTimer) {
        clearTimeout(realtimeRefreshTimer);
    }

    if (Echo) {
        Echo.leave('superadmin.dashboard');
    }
});

// --- E2: Chế độ xem thu gọn (compact view) — lưu lựa chọn vào localStorage để giữ qua các phiên ---
const COMPACT_MODE_STORAGE_KEY = 'superadmin-dashboard-compact-mode';
const compactMode = ref(false);

onMounted(() => {
    compactMode.value = localStorage.getItem(COMPACT_MODE_STORAGE_KEY) === '1';
});

watch(compactMode, (value) => {
    localStorage.setItem(COMPACT_MODE_STORAGE_KEY, value ? '1' : '0');
});

function toggleCompactMode() {
    compactMode.value = !compactMode.value;
}

// --- E1: Ẩn/hiện các khối phân tích trên dashboard — lưu lựa chọn vào localStorage ---
const DASHBOARD_SECTIONS_STORAGE_KEY = 'superadmin-dashboard-visible-sections';
const DASHBOARD_SECTIONS: Array<{ key: string; label: string }> = [
    { key: 'growth_chart', label: 'Biểu đồ tăng trưởng & Sức khỏe SaaS' },
    { key: 'kpi_console', label: 'KPI Console & Chỉ số chi tiết' },
    { key: 'ai_insights', label: 'AI Insights (Churn risk, Health score, Dự báo)' },
    { key: 'resource_usage', label: 'Top tài nguyên (đơn hàng & lưu trữ)' },
    { key: 'recent_restaurants', label: 'Nhà hàng gần đây & Tín hiệu hệ thống' },
    { key: 'revenue_plan', label: 'Doanh thu & Hiệu suất theo gói' },
    { key: 'cohort_heatmap', label: 'Phân tích Cohort giữ chân tenant' },
];

const visibleSections = ref<Record<string, boolean>>(
    Object.fromEntries(DASHBOARD_SECTIONS.map((s) => [s.key, true])),
);

onMounted(() => {
    try {
        const saved = localStorage.getItem(DASHBOARD_SECTIONS_STORAGE_KEY);
        if (saved) {
            visibleSections.value = { ...visibleSections.value, ...JSON.parse(saved) };
        }
    } catch {
        // bỏ qua dữ liệu localStorage hỏng — giữ trạng thái mặc định (hiện tất cả)
    }
});

watch(visibleSections, (value) => {
    localStorage.setItem(DASHBOARD_SECTIONS_STORAGE_KEY, JSON.stringify(value));
}, { deep: true });

function isSectionVisible(key: string): boolean {
    return visibleSections.value[key] !== false;
}

function toggleSectionVisibility(key: string, value: boolean) {
    visibleSections.value = { ...visibleSections.value, [key]: value };
}

const hiddenSectionsCount = computed(() => DASHBOARD_SECTIONS.filter((s) => !isSectionVisible(s.key)).length);

// --- Bộ lọc khoảng thời gian cho biểu đồ tăng trưởng tenant (B2) + so sánh kỳ trước (B3) ---
const RANGE_LABELS: Record<string, string> = { '3m': '3 tháng', '6m': '6 tháng', '12m': '12 tháng' };
const selectedRange = ref(props.filters.range);
const compareEnabled = ref(props.filters.compare);

// --- E3: trạng thái "đang tải" khi đổi bộ lọc — hiển thị skeleton thay vì màn hình trắng ---
const isNavigating = ref(false);

function applyGrowthFilters() {
    router.get('/super-admin/dashboard', {
        range: selectedRange.value,
        compare: compareEnabled.value ? '1' : undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onStart: () => { isNavigating.value = true; },
        onFinish: () => { isNavigating.value = false; },
    });
}

function exportDashboardCsv() {
    window.open('/super-admin/dashboard/export/csv', '_blank');
}

function openPrintableReport() {
    window.open('/super-admin/dashboard/export/report', '_blank');
}

// --- C2: Bật/tắt nhận báo cáo định kỳ qua email + chọn tần suất (lưu DashboardReportSubscription) ---
const reportSubActive = ref(props.reportSubscription.is_active);
const reportSubFrequency = ref(props.reportSubscription.frequency);
const reportSubSaving = ref(false);

function saveReportSubscription() {
    reportSubSaving.value = true;
    router.post('/super-admin/dashboard/report-subscription', {
        is_active: reportSubActive.value,
        frequency: reportSubFrequency.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => { reportSubSaving.value = false; },
    });
}

function toggleReportSubscription(value: boolean) {
    reportSubActive.value = value;
    saveReportSubscription();
}

const trendWord = (trend: string) => (trend === 'up' ? 'tăng' : trend === 'down' ? 'giảm' : 'đi ngang');

/**
 * Sinh nhận định/đề xuất cho từng bước của KPI Console dựa trên dữ liệu thật
 * (aiInsights, statChanges, saasMetrics, supportOverview...) thay vì văn bản cố định,
 * để nội dung luôn khớp với những gì khối "AI Insights" phía trên đang hiển thị.
 */
const aiNotes = computed(() => {
    const segments = props.aiInsights?.segments;
    const churnRisks = props.aiInsights?.churn_risks ?? [];
    const forecast = props.aiInsights?.mrr_forecast ?? [];
    const health = props.aiInsights?.overall_health;
    const monitoring = props.supportOverview.monitoring;
    const suspendedRatio = props.stats.total_restaurants > 0
        ? Math.round((props.stats.suspended / props.stats.total_restaurants) * 100)
        : 0;
    const proRatio = props.stats.total_restaurants > 0
        ? Math.round((props.stats.pro_plan / props.stats.total_restaurants) * 100)
        : 0;
    const density = props.stats.total_restaurants > 0
        ? props.stats.total_users / props.stats.total_restaurants
        : 0;
    const arpu = props.saasMetrics.paid_tenants > 0
        ? Math.round(props.saasMetrics.mrr / props.saasMetrics.paid_tenants)
        : 0;

    const notes: string[] = [];

    // 1. Tenant Analytics
    if (suspendedRatio > 10) {
        notes.push(`⚠️ ${suspendedRatio}% nhà hàng (${props.stats.suspended}/${props.stats.total_restaurants}) đang ở trạng thái tạm khóa. Nên rà soát nguyên nhân (thanh toán quá hạn, vi phạm chính sách...) và chủ động liên hệ để kích hoạt lại trước khi họ rời bỏ hệ thống.`);
    } else {
        notes.push(`💡 ${props.statChanges.total_restaurants.label}. Hệ thống hiện có ${props.stats.active}/${props.stats.total_restaurants} nhà hàng đang hoạt động ổn định — nên duy trì các kênh thu hút đối tác mới để giữ đà này.`);
    }

    // 2. System Operation
    if (monitoring.failed_jobs > 0 || monitoring.api_error_rate > 5) {
        notes.push(`⚠️ Phát hiện ${monitoring.failed_jobs} job lỗi và tỉ lệ lỗi API ${monitoring.api_error_rate}%. Nên vào "DevOps & Support" kiểm tra ngay để tránh ảnh hưởng tới trải nghiệm của ${props.stats.active} nhà hàng đang hoạt động.`);
    } else if (monitoring.slow_queries > 0) {
        notes.push(`💡 Hệ thống ổn định nhưng có ${monitoring.slow_queries} truy vấn chậm. Với ${props.resourceInsights.totals.orders_last_30_days} đơn hàng trong 30 ngày qua, nên tối ưu các truy vấn này sớm để giữ tốc độ phản hồi khi tải tăng.`);
    } else {
        notes.push(`💡 Hệ thống đang vận hành ổn định: không có job lỗi, tỉ lệ lỗi API ${monitoring.api_error_rate}%. Đã ghi nhận ${props.resourceInsights.totals.orders_last_30_days} đơn hàng trong 30 ngày qua — tiếp tục theo dõi định kỳ tại DevOps & Support.`);
    }

    // 3. Pro Plan Subscriptions
    if ((segments?.free_inactive ?? 0) > 0) {
        notes.push(`💡 Có ${segments?.free_inactive} nhà hàng đang dùng gói Free nhưng ít hoạt động. Nên gửi chiến dịch giới thiệu tính năng Pro (QR Order, AI Forecast tồn kho...) kèm ưu đãi dùng thử để thúc đẩy chuyển đổi.`);
    } else if ((segments?.trial_active ?? 0) > 0) {
        notes.push(`💡 Đang có ${segments?.trial_active} nhà hàng trong giai đoạn dùng thử (Trial). Nên chủ động liên hệ chăm sóc để tăng tỉ lệ chuyển đổi sang gói trả phí trước khi hết hạn.`);
    } else {
        notes.push(`💡 Gói Pro hiện chiếm ${proRatio}% tổng số đối tác (${props.stats.pro_plan}/${props.stats.total_restaurants}, tương đương ${props.saasMetrics.paid_tenants} thuê bao trả phí). Tiếp tục giữ chất lượng trải nghiệm Pro để mở rộng nhóm khách hàng này.`);
    }

    // 4. Team Size Dynamics
    if (density > 0 && density < 2) {
        notes.push(`💡 Mật độ nhân sự bình quân ~${density.toFixed(1)} người/nhà hàng — khá thấp, cho thấy phần lớn là quán quy mô nhỏ. Nên quảng bá các module tự động hoá (Chấm công, Tính lương, Quản lý ca) để giảm tải vận hành thủ công cho nhóm đối tác này.`);
    } else {
        notes.push(`💡 Mật độ nhân sự bình quân ~${density.toFixed(1)} người/nhà hàng. ${props.statChanges.total_users.label} — nên tiếp tục theo dõi để đảm bảo hạ tầng đáp ứng khi số tài khoản tăng thêm.`);
    }

    // 5. MRR Financial Stream
    if (props.saasMetrics.churn_rate > 5 || churnRisks.length > 0) {
        notes.push(`⚠️ Tỉ lệ rời bỏ đang ở mức ${props.saasMetrics.churn_rate}%, với ${churnRisks.length} nhà hàng được đánh giá có nguy cơ rời bỏ cao. Nên ưu tiên chăm sóc nhóm này (xem mục "Nguy cơ rời bỏ" bên dưới) trước khi mất doanh thu định kỳ.`);
    } else {
        notes.push(`💡 Churn rate ở mức ${props.saasMetrics.churn_rate}% — tín hiệu tích cực. ARPU hiện đạt ${formatCurrency(arpu)}/nhà hàng Pro. Có thể cân nhắc xây dựng gói cao cấp hơn (Enterprise) để nâng ARPU mà vẫn giữ tỉ lệ giữ chân tốt.`);
    }

    // 6. ARR Forecast
    const next = forecast[0];
    if (next) {
        notes.push(`💡 Dự báo tháng tới: MRR ước tính ${trendWord(next.trend)} về mức ${formatCurrency(next.predicted_mrr)}. Điểm sức khoẻ tổng thể hệ thống hiện ở mức ${health?.score ?? '-'}/100 (${health?.label ?? 'chưa đủ dữ liệu'}) — nên theo dõi sát các tenant rủi ro để giữ đà tăng trưởng ARR.`);
    } else {
        notes.push(`💡 Chưa có đủ dữ liệu để dự báo MRR các tháng tới. ARR hiện được ước tính trên chu kỳ 12 tháng dựa theo MRR hiện tại (${formatCurrency(props.saasMetrics.arr)}).`);
    }

    return notes;
});

const kpiDetails = computed(() => [
    {
        step: 'Phân tích Dữ liệu Nhà hàng (Tenant Analytics)',
        desc: 'Tổng quan tình hình phân bổ và tốc độ phát triển số lượng nhà hàng đối tác trên hệ thống.',
        metric1_label: 'Tổng số lượng nhà hàng',
        metric1_value: props.stats.total_restaurants + ' đối tác',
        metric2_label: 'Gia tăng tháng này',
        metric2_value: '+' + (props.tenantGrowth.length > 0 ? (props.tenantGrowth[props.tenantGrowth.length - 1]?.new_tenants ?? 0) : 0) + ' đăng ký mới',
        metric3_label: 'Tốc độ phát triển',
        metric3_value: props.statChanges.total_restaurants.label,
        tables: ['Hoạt động: ' + props.stats.active, 'Tạm khóa: ' + props.stats.suspended, 'Hết hạn: ' + props.stats.expired],
        note: aiNotes.value[0],
        color: 'text-sky-400 border-sky-500/30 shadow-sky-500/10'
    },
    {
        step: 'Trạng thái Hoạt động Hệ thống (System Operation)',
        desc: 'Theo dõi mật độ tương tác và tính ổn định vận hành thời gian thực của các nhà hàng đối tác.',
        metric1_label: 'Nhà hàng đang active',
        metric1_value: props.stats.active + ' đang online',
        metric2_label: 'Tỷ lệ hoạt động thực tế',
        metric2_value: Math.round((props.stats.active / Math.max(1, props.stats.total_restaurants)) * 100) + '% tổng hệ thống',
        metric3_label: 'Mật độ tải trung bình',
        metric3_value: props.resourceInsights.totals.orders_last_30_days + ' đơn hàng / 30 ngày',
        tables: [
            'Job lỗi: ' + props.supportOverview.monitoring.failed_jobs,
            'Job đang chờ: ' + props.supportOverview.monitoring.pending_jobs,
            'Tỉ lệ lỗi API: ' + props.supportOverview.monitoring.api_error_rate + '%',
        ],
        note: aiNotes.value[1],
        color: 'text-emerald-400 border-emerald-500/30 shadow-emerald-500/10'
    },
    {
        step: 'Chuyển đổi Gói Dịch Vụ (Pro Plan Subscriptions)',
        desc: 'Phân tích tỷ lệ khách hàng trả phí nâng cao và đánh giá hiệu quả chuyển đổi gói cước.',
        metric1_label: 'Tổng số lượng gói Pro',
        metric1_value: props.stats.pro_plan + ' nhà hàng',
        metric2_label: 'Tỷ lệ Pro / Tổng số',
        metric2_value: Math.round((props.stats.pro_plan / Math.max(1, props.stats.total_restaurants)) * 100) + '% tổng số nhà hàng',
        metric3_label: 'Lượt nâng cấp thành công',
        metric3_value: props.saasMetrics.paid_tenants + ' tài khoản trả phí thực tế',
        tables: [
            'Gói Pro: ' + props.stats.pro_plan,
            'Dùng thử (Trial): ' + (props.aiInsights?.segments?.trial_active ?? 0),
            ...(props.planPerformance[0]
                ? [`Hiệu suất gói ${props.planPerformance[0].plan_name}: ${props.planPerformance[0].avg_orders_per_tenant_per_day} đơn/ngày/tenant · ${props.planPerformance[0].active_tenant_ratio}% đang hoạt động`]
                : []),
        ],
        note: aiNotes.value[2],
        color: 'text-violet-400 border-violet-500/30 shadow-violet-500/10'
    },
    {
        step: 'Mật độ Nhân sự & Tài khoản (Team Size Dynamics)',
        desc: 'Giám sát phân bổ nhân sự vận hành hệ thống bên trong các nhà hàng đối tác.',
        metric1_label: 'Tổng tài khoản người dùng',
        metric1_value: props.stats.total_users + ' người dùng',
        metric2_label: 'Mật độ nhân sự trung bình',
        metric2_value: (props.stats.total_users / Math.max(1, props.stats.total_restaurants)).toFixed(1) + ' nhân sự / nhà hàng',
        metric3_label: 'Gia tăng tài khoản',
        metric3_value: props.statChanges.total_users.label,
        tables: ['Super Admin: 1', 'Tenant Owners: ' + props.stats.total_restaurants, 'Nhân viên: ' + (props.stats.total_users - props.stats.total_restaurants - 1)],
        note: aiNotes.value[3],
        color: 'text-amber-400 border-emerald-500/30 shadow-emerald-500/10'
    },
    {
        step: 'Theo dõi Doanh thu định kỳ (MRR Financial Stream)',
        desc: 'Đo lường sức khỏe dòng tiền định kỳ hàng tháng và tỷ lệ khách hàng rời bỏ dịch vụ.',
        metric1_label: 'Doanh thu MRR hiện tại',
        metric1_value: formatCurrency(props.saasMetrics.mrr),
        metric2_label: 'Tỷ lệ rời bỏ (Churn Rate)',
        metric2_value: props.saasMetrics.churn_rate + '% Churn Rate',
        metric3_label: 'Mức chi tiêu bình quân (ARPU)',
        metric3_value: formatCurrency(props.saasMetrics.paid_tenants > 0 ? Math.round(props.saasMetrics.mrr / props.saasMetrics.paid_tenants) : 0) + ' / nhà hàng Pro',
        tables: [
            'MRR Pro: ' + formatCurrency(props.saasMetrics.mrr),
            'Paid Subscriptions: ' + props.saasMetrics.active_subscriptions,
            `NRR/GRR (${props.revenueRetention.previous_label} → ${props.revenueRetention.period_label}): ${props.revenueRetention.nrr ?? '—'}% / ${props.revenueRetention.grr ?? '—'}%`,
        ],
        note: aiNotes.value[4],
        color: 'text-cyan-400 border-cyan-500/30 shadow-cyan-500/10'
    },
    {
        step: 'Dự báo Tài chính dài hạn (ARR Forecast Projections)',
        desc: 'Phân tích doanh thu tích lũy năm và tốc độ xoay chuyển dòng tiền dài hạn.',
        metric1_label: 'Doanh thu ARR dự tính năm',
        metric1_value: formatCurrency(props.saasMetrics.arr),
        metric2_label: 'Tốc độ tăng trưởng ARR dự kiến',
        metric2_value: props.statChanges.mrr.label,
        metric3_label: 'Mô hình dự báo tích lũy',
        metric3_value: 'Dự tính trên chu kỳ 12 tháng kế tiếp',
        tables: [
            ...((props.aiInsights?.mrr_forecast ?? []).length > 0
                ? props.aiInsights.mrr_forecast.map((f) => `${f.month}: ${formatCurrency(f.predicted_mrr)} (${trendWord(f.trend)})`)
                : ['Chưa có dữ liệu dự báo MRR']),
            props.cohortAnalysis.length
                ? `Cohort ${props.cohortAnalysis[props.cohortAnalysis.length - 1].month}: giữ chân M+1 đạt ${props.cohortAnalysis[props.cohortAnalysis.length - 1].m1 ?? '—'}%`
                : 'Chưa có đủ dữ liệu cohort để phân tích',
            `Cảnh báo đang hoạt động: ${props.dashboardAlerts.length} (${props.dashboardAlerts.filter((a) => a.severity === 'critical').length} nghiêm trọng)`,
        ],
        note: aiNotes.value[5],
        color: 'text-indigo-400 border-indigo-500/30 shadow-indigo-500/10'
    }
]);

const activeKpi = computed(() => kpiDetails.value[selectedKpiIdx.value] ?? kpiDetails.value[0]);

const expandedRiskId = ref<number | null>(null);

const toggleRiskExpand = (id: number) => {
    expandedRiskId.value = expandedRiskId.value === id ? null : id;
};

const isRiskExpanded = (id: number, index: number) => {
    if (expandedRiskId.value === null && index === 0) {
        return true;
    }
    return expandedRiskId.value === id;
};

const statusColor: Record<string, string> = {
    active: 'bg-emerald-100/80 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200/50',
    suspended: 'bg-amber-100/80 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300 border border-amber-200/50',
    expired: 'bg-rose-100/80 text-rose-800 dark:bg-rose-950/40 dark:text-rose-300 border border-rose-200/50',
};

const statusLabel: Record<string, string> = {
    active: 'Hoạt động',
    suspended: 'Tạm ngưng',
    expired: 'Hết hạn',
};

const formatCurrency = (value: number) => new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value);
const formatBytes = (bytes: number) => {
    if (bytes >= 1024 ** 3) {
        return `${(bytes / 1024 ** 3).toFixed(1)} GB`;
    }
    if (bytes >= 1024 ** 2) {
        return `${(bytes / 1024 ** 2).toFixed(1)} MB`;
    }
    if (bytes >= 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }
    return `${bytes} B`;
};

const statCards = computed(() => [
    { label: 'Tổng nhà hàng', value: props.stats.total_restaurants, icon: Building2, color: 'text-sky-500 bg-sky-500/10 border-sky-500/20', change: props.statChanges.total_restaurants.label, trend: props.statChanges.total_restaurants.trend },
    { label: 'Đang hoạt động', value: props.stats.active, icon: CheckCircle2, color: 'text-emerald-500 bg-emerald-500/10 border-emerald-500/20', change: 'Đang vận hành liên tục', trend: 'up' },
    { label: 'Gói Pro cao cấp', value: props.stats.pro_plan, icon: Crown, color: 'text-violet-500 bg-violet-500/10 border-violet-500/20', change: props.statChanges.pro_plan.label, trend: props.statChanges.pro_plan.trend },
    { label: 'Tổng người dùng', value: props.stats.total_users, icon: Users, color: 'text-amber-500 bg-amber-500/10 border-amber-500/20', change: props.statChanges.total_users.label, trend: props.statChanges.total_users.trend },
    { label: 'Doanh thu MRR', value: formatCurrency(props.saasMetrics.mrr), icon: TrendingUp, color: 'text-cyan-500 bg-cyan-500/10 border-cyan-500/20', change: props.statChanges.mrr.label, trend: props.statChanges.mrr.trend },
    { label: 'Ước tính ARR', value: formatCurrency(props.saasMetrics.arr), icon: Gauge, color: 'text-indigo-500 bg-indigo-500/10 border-indigo-500/20', change: 'Dự tính trên chu kỳ 12 tháng', trend: 'neutral' },
]);

const riskColor: Record<string, string> = {
    high: 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300 border border-rose-200/50',
    medium: 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300 border border-amber-200/50',
    low: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300 border border-emerald-200/50',
};

const riskBarColor: Record<string, string> = {
    high: 'bg-gradient-to-r from-rose-500 to-rose-400',
    medium: 'bg-gradient-to-r from-amber-500 to-amber-400',
    low: 'bg-gradient-to-r from-emerald-500 to-emerald-400',
};

const healthBarColor: Record<string, string> = {
    good: 'bg-gradient-to-r from-emerald-500 to-teal-400',
    fair: 'bg-gradient-to-r from-amber-500 to-yellow-400',
    poor: 'bg-gradient-to-r from-rose-500 to-pink-500',
};

const segmentCards = computed(() => {
    const s = props.aiInsights?.segments ?? {};
    return [
        { key: 'active_pro', label: 'Pro đang hoạt động', value: s.active_pro ?? 0, color: 'text-violet-400', gradient: 'from-violet-600/20 to-violet-900/30', border: 'border-violet-500/20 hover:border-violet-500/40', icon: '👑' },
        { key: 'trial_active', label: 'Đang dùng thử (Trial)', value: s.trial_active ?? 0, color: 'text-sky-400', gradient: 'from-sky-600/20 to-sky-900/30', border: 'border-sky-500/20 hover:border-sky-500/40', icon: '⚡' },
        { key: 'free_inactive', label: 'Free – Ít hoạt động', value: s.free_inactive ?? 0, color: 'text-amber-400', gradient: 'from-amber-600/20 to-amber-900/30', border: 'border-amber-500/20 hover:border-amber-500/40', icon: '💤' },
        { key: 'at_risk', label: 'Nguy cơ rời bỏ', value: s.at_risk ?? 0, color: 'text-rose-400', gradient: 'from-rose-600/20 to-rose-900/30', border: 'border-rose-500/20 hover:border-rose-500/40', icon: '⚠️' },
    ];
});

// --- Drill-down theo segment (B4): bấm vào card mở Dialog liệt kê nhà hàng thuộc nhóm ---
type SegmentRestaurant = { id: number; name: string; code: string; status: string; plan_code: string; owner_name: string; owner_email: string; subscription_ends_at: string };
const selectedSegment = ref<{ key: string; label: string } | null>(null);
const isLoadingSegmentRestaurants = ref(false);
const segmentRestaurantsList = ref<SegmentRestaurant[]>([]);

async function showSegmentRestaurants(seg: { key: string; label: string }) {
    selectedSegment.value = seg;
    isLoadingSegmentRestaurants.value = true;
    segmentRestaurantsList.value = [];
    try {
        const response = await fetch(`/super-admin/dashboard/segments/${seg.key}`);
        const data = await response.json();
        segmentRestaurantsList.value = data.restaurants ?? [];
    } catch (e) {
        console.error('Error fetching segment restaurants:', e);
    } finally {
        isLoadingSegmentRestaurants.value = false;
    }
}

const donutSlices = computed(() => {
    const total = props.planDistribution.reduce((sum, p) => sum + p.count, 0) || 1;
    let accumulatedPercentage = 0;
    
    const colors: Record<string, { stroke: string; text: string; bg: string }> = {
        free: { stroke: 'stroke-sky-500', text: 'text-sky-500', bg: 'bg-sky-500/10' },
        pro: { stroke: 'stroke-violet-500', text: 'text-violet-500', bg: 'bg-violet-500/10' },
        max: { stroke: 'stroke-amber-500', text: 'text-amber-500', bg: 'bg-amber-500/10' },
        ultra: { stroke: 'stroke-emerald-500', text: 'text-emerald-500', bg: 'bg-emerald-500/10' },
    };
    
    return props.planDistribution
        .filter(p => p.count > 0)
        .map((plan) => {
            const percentage = (plan.count / total) * 100;
            const slice = {
                name: plan.name,
                code: plan.code,
                count: plan.count,
                percentage: Math.round(percentage),
                dasharray: `${percentage.toFixed(2)} 100`,
                dashoffset: `-${accumulatedPercentage.toFixed(2)}`,
                color: colors[plan.code?.toLowerCase()] ?? { stroke: 'stroke-slate-500', text: 'text-slate-500', bg: 'bg-slate-500/10' }
            };
            accumulatedPercentage += percentage;
            return slice;
        });
});

const overallHealthStyle = computed(() => {
    const color = props.aiInsights?.overall_health?.color ?? 'gray';
    const map: Record<string, { ring: string; text: string; bg: string; glow: string }> = {
        green: { ring: 'stroke-emerald-500', text: 'text-emerald-400 dark:text-emerald-300', bg: 'from-emerald-600/10 to-emerald-900/20 border-emerald-500/30', glow: 'shadow-emerald-500/20' },
        yellow: { ring: 'stroke-amber-400', text: 'text-amber-400 dark:text-amber-300', bg: 'from-amber-600/10 to-amber-900/20 border-amber-500/30', glow: 'shadow-amber-500/20' },
        red: { ring: 'stroke-rose-500', text: 'text-rose-400 dark:text-rose-300', bg: 'from-rose-600/10 to-rose-900/20 border-rose-500/30', glow: 'shadow-rose-500/20' },
        gray: { ring: 'stroke-slate-500', text: 'text-slate-400 dark:text-slate-300', bg: 'from-slate-600/10 to-slate-900/20 border-slate-500/30', glow: 'shadow-slate-500/20' },
    };
    return map[color] ?? map.gray;
});

const maxForecastMrr = computed(() => Math.max(...(props.aiInsights?.mrr_forecast ?? []).map((f) => f.predicted_mrr), 1));

const hasAiData = computed(() => {
    const ai = props.aiInsights;
    return ai && ai.overall_health != null && (ai.churn_risks?.length > 0 || ai.health_scores?.length > 0 || Object.keys(ai.segments ?? {}).length > 0);
});

// --- Dữ liệu cho AreaChart dùng chung (thay cho các computed vẽ SVG lặp lại trước đây) ---
const growthSeries = computed(() => props.tenantGrowth.map((point) => ({
    label: point.label,
    value: point.new_tenants,
    rate: point.conversion_rate,
})));

// Đường so sánh "kỳ trước" (B3) — chỉ có dữ liệu khi bật compareEnabled, gắn nhãn riêng
// để tooltip phân biệt rõ với kỳ hiện tại dù cùng vị trí trên trục thời gian.
const compareSeries = computed(() => props.tenantGrowthCompare?.map((point) => ({
    label: `${point.label} (kỳ trước)`,
    value: point.new_tenants,
    rate: point.conversion_rate,
})) ?? undefined);

const freeToProSeries = computed(() => props.tenantGrowth.map((point) => ({
    label: point.label,
    value: point.free_to_pro,
})));

// Calculate percentages for Top Tenants Progress Bars
const topOrdersMax = computed(() => Math.max(...props.resourceInsights.top_order_restaurants.map(i => i.orders_count ?? 1), 1));
const topStorageMax = computed(() => Math.max(...props.resourceInsights.top_storage_restaurants.map(i => i.storage_bytes ?? 1), 1));

// --- Bảng màu theo plan_code, dùng chung cho biểu đồ Revenue Breakdown (D2) & bảng hiệu suất gói (D5) ---
const PLAN_COLORS: Record<string, { bar: string; text: string; dot: string }> = {
    free: { bar: 'bg-sky-500', text: 'text-sky-500', dot: 'bg-sky-500' },
    pro: { bar: 'bg-violet-500', text: 'text-violet-500', dot: 'bg-violet-500' },
    max: { bar: 'bg-amber-500', text: 'text-amber-500', dot: 'bg-amber-500' },
    ultra: { bar: 'bg-emerald-500', text: 'text-emerald-500', dot: 'bg-emerald-500' },
};
const planColor = (code: string) => PLAN_COLORS[code?.toLowerCase()] ?? { bar: 'bg-slate-500', text: 'text-slate-500', dot: 'bg-slate-500' };

// --- D4: Banner cảnh báo ngưỡng — gộp cảnh báo SaaS tính trực tiếp + SystemAlert đang mở ---
const alertSeverityStyle: Record<string, { card: string; badge: string; icon: string }> = {
    critical: { card: 'border-rose-500/30 bg-rose-500/[0.06]', badge: 'text-rose-500 bg-rose-500/10 border-rose-500/25', icon: 'text-rose-500' },
    warning: { card: 'border-amber-500/30 bg-amber-500/[0.06]', badge: 'text-amber-500 bg-amber-500/10 border-amber-500/25', icon: 'text-amber-500' },
};

// --- D2: Doanh thu theo gói (stacked bar ngang theo tháng) ---
const revenueBreakdownPlanCodes = computed(() => {
    const codes = new Set<string>();
    props.revenueBreakdown.forEach((point) => Object.keys(point.by_plan).forEach((code) => codes.add(code)));
    return Array.from(codes);
});
const revenueBreakdownMax = computed(() => Math.max(...props.revenueBreakdown.map((p) => p.total), 1));
const planDisplayName = (code: string) => props.planDistribution.find((p) => p.code?.toLowerCase() === code)?.name ?? code.toUpperCase();

// --- D1: Heatmap cohort — màu nền theo % giữ chân ---
function cohortCellStyle(value: number | null): string {
    if (value === null) return 'bg-muted/30 text-muted-foreground';
    if (value >= 70) return 'bg-emerald-500/80 text-white';
    if (value >= 40) return 'bg-amber-500/70 text-white';
    return 'bg-rose-500/70 text-white';
}
</script>

<template>
    <Head title="Super Admin Analytics" />

    <div :class="['flex flex-col gap-6 p-6 max-w-7xl mx-auto w-full', { 'compact-mode': compactMode }]">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-4 border-b pb-5 border-border/60">
            <div>
                <h1 class="text-2xl font-bold tracking-tight bg-gradient-to-r from-slate-900 to-slate-700 dark:from-white dark:to-slate-300 bg-clip-text text-transparent">
                    Dashboard Phân Tích SaaS
                </h1>
                <p class="text-sm text-muted-foreground flex items-center gap-1.5 mt-0.5">
                    <Activity class="size-4 text-primary animate-pulse" /> Trung tâm dữ liệu vĩ mô và hỗ trợ giám sát toàn hệ thống
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <button type="button"
                            class="inline-flex items-center gap-2 rounded-xl border border-border/80 px-4 py-2 text-xs font-semibold hover:bg-muted/70 transition-all shadow-xs cursor-pointer">
                            <LayoutGrid class="size-3.5" /> Tuỳ chỉnh khối
                            <Badge v-if="hiddenSectionsCount > 0" variant="secondary" class="ml-0.5 h-4 min-w-4 rounded-full px-1 text-[9px] font-bold">
                                {{ hiddenSectionsCount }}
                            </Badge>
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" class="w-72">
                        <DropdownMenuLabel class="text-xs">Hiển thị / Ẩn khối phân tích</DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuCheckboxItem
                            v-for="section in DASHBOARD_SECTIONS"
                            :key="section.key"
                            :model-value="isSectionVisible(section.key)"
                            class="text-xs"
                            @select="(e: Event) => e.preventDefault()"
                            @update:model-value="(v: boolean) => toggleSectionVisibility(section.key, v)">
                            {{ section.label }}
                        </DropdownMenuCheckboxItem>
                    </DropdownMenuContent>
                </DropdownMenu>
                <button type="button" @click="toggleCompactMode"
                    :title="compactMode ? 'Chuyển về chế độ xem đầy đủ' : 'Chuyển sang chế độ xem thu gọn'"
                    class="inline-flex items-center gap-2 rounded-xl border border-border/80 px-4 py-2 text-xs font-semibold hover:bg-muted/70 transition-all shadow-xs cursor-pointer">
                    <component :is="compactMode ? Maximize2 : Minimize2" class="size-3.5" /> {{ compactMode ? 'Xem đầy đủ' : 'Thu gọn' }}
                </button>
                <button type="button" @click="exportDashboardCsv"
                    class="inline-flex items-center gap-2 rounded-xl border border-border/80 px-4 py-2 text-xs font-semibold hover:bg-muted/70 transition-all shadow-xs cursor-pointer">
                    <Download class="size-3.5" /> Xuất CSV
                </button>
                <button type="button" @click="openPrintableReport"
                    class="inline-flex items-center gap-2 rounded-xl border border-border/80 px-4 py-2 text-xs font-semibold hover:bg-muted/70 transition-all shadow-xs cursor-pointer">
                    <Printer class="size-3.5" /> Xuất báo cáo (PDF)
                </button>
                <Link href="/super-admin/restaurants" class="inline-flex items-center gap-2 rounded-xl border border-border/80 px-4 py-2 text-xs font-semibold hover:bg-muted/70 transition-all shadow-xs">
                    <Building2 class="size-3.5" /> Tenants
                </Link>
                <Link href="/super-admin/support" class="inline-flex items-center gap-2 rounded-xl border border-border/80 px-4 py-2 text-xs font-semibold hover:bg-muted/70 transition-all shadow-xs">
                    <Siren class="size-3.5" /> Support Portal
                </Link>
                <Link href="/super-admin/accounts" class="inline-flex items-center gap-2 rounded-xl border border-border/80 px-4 py-2 text-xs font-semibold hover:bg-muted/70 transition-all shadow-xs">
                    <ShieldCheck class="size-3.5" /> Accounts
                </Link>
                <Link href="/super-admin/audit-logs" class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-xs font-semibold text-primary-foreground hover:bg-primary/90 transition-all shadow-md">
                    <FileText class="size-3.5" /> Audit Log
                </Link>
            </div>
        </div>

        <!-- D4: Banner cảnh báo ngưỡng SaaS + SystemAlert đang mở -->
        <div v-if="dashboardAlerts.length" class="grid gap-2.5 sm:grid-cols-2">
            <div v-for="(alert, idx) in dashboardAlerts" :key="`${alert.source}-${alert.metric_key}-${idx}`"
                :class="['flex items-start gap-3 rounded-2xl border px-4 py-3', alertSeverityStyle[alert.severity]?.card ?? alertSeverityStyle.warning.card]">
                <Siren :class="['size-4.5 shrink-0 mt-0.5', alertSeverityStyle[alert.severity]?.icon ?? alertSeverityStyle.warning.icon]" />
                <div class="min-w-0 space-y-0.5">
                    <div class="flex items-center gap-2 flex-wrap">
                        <p class="text-xs font-black text-slate-800 dark:text-slate-100">{{ alert.title }}</p>
                        <span :class="['rounded-full border px-1.5 py-0.5 text-[9px] font-extrabold uppercase tracking-wider', alertSeverityStyle[alert.severity]?.badge ?? alertSeverityStyle.warning.badge]">
                            {{ alert.source === 'system' ? 'Hệ thống' : 'SaaS' }}
                        </span>
                        <span v-if="alert.triggered_at" class="text-[10px] font-semibold text-muted-foreground font-mono">{{ alert.triggered_at }}</span>
                    </div>
                    <p class="text-[11px] text-muted-foreground font-medium leading-relaxed">{{ alert.message }}</p>
                </div>
            </div>
        </div>

        <!-- C2: Bật/tắt báo cáo định kỳ qua email -->
        <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-border/70 bg-card/50 px-4 py-3">
            <div class="flex items-center gap-3">
                <div class="flex size-9 items-center justify-center rounded-xl bg-primary/10 text-primary">
                    <FileText class="size-4.5" />
                </div>
                <div>
                    <p class="text-xs font-bold text-slate-800 dark:text-slate-100">Báo cáo định kỳ qua email</p>
                    <p class="text-[11px] text-muted-foreground">
                        Nhận tóm tắt KPI &amp; cảnh báo tự động vào email của bạn
                        <span v-if="reportSubscription.last_sent_at"> · Lần gửi gần nhất: {{ reportSubscription.last_sent_at }}</span>
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <Select v-model="reportSubFrequency" :disabled="!reportSubActive || reportSubSaving" @update:modelValue="saveReportSubscription">
                    <SelectTrigger class="h-8 w-[110px] text-xs">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="weekly">Hàng tuần</SelectItem>
                        <SelectItem value="monthly">Hàng tháng</SelectItem>
                    </SelectContent>
                </Select>
                <label class="flex items-center gap-1.5 text-[11px] font-semibold text-muted-foreground cursor-pointer select-none">
                    <Checkbox :checked="reportSubActive" :disabled="reportSubSaving" @update:checked="toggleReportSubscription" />
                    {{ reportSubActive ? 'Đang bật' : 'Đang tắt' }}
                </label>
            </div>
        </div>

        <!-- Main Chart + Health Grid -->
        <div v-if="isSectionVisible('growth_chart')" class="grid gap-4 xl:grid-cols-[1.6fr_1fr]">
            <!-- Tenant Growth & Conversion Chart Card -->
            <Card class="bg-card/60 dark:bg-card/30 backdrop-blur-xs">
                <CardHeader class="flex-row flex-wrap items-center justify-between gap-3 pb-3 border-b border-border/40">
                    <div>
                        <CardTitle class="text-base font-bold">Biến động & Chuyển đổi Tenant</CardTitle>
                        <p class="text-xs text-muted-foreground">Đăng ký mới so với tỷ lệ nâng cấp từ Free lên Pro</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <label class="flex items-center gap-1.5 text-[11px] font-semibold text-muted-foreground cursor-pointer select-none">
                            <Checkbox :checked="compareEnabled" @update:checked="(v: boolean) => { compareEnabled = v; applyGrowthFilters(); }" />
                            So với kỳ trước
                        </label>
                        <Select v-model="selectedRange" @update:modelValue="applyGrowthFilters">
                            <SelectTrigger class="h-8 w-[120px] text-xs">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="(label, key) in RANGE_LABELS" :key="key" :value="key">{{ label }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <Badge variant="secondary" class="gap-1 rounded-lg text-[10px] font-bold px-2 py-0.5 border border-border/80">
                            <TrendingUp class="size-3" /> {{ tenantGrowth.length }} tháng
                        </Badge>
                    </div>
                </CardHeader>
                <CardContent class="relative pt-5 space-y-6">
                    <!-- E3: Skeleton overlay khi đang đổi bộ lọc khoảng thời gian / so sánh kỳ trước -->
                    <div v-if="isNavigating" class="absolute inset-0 z-10 grid gap-4 rounded-b-xl bg-card/80 p-4 backdrop-blur-xs md:grid-cols-2">
                        <div v-for="i in 2" :key="i" class="space-y-3 rounded-xl border border-border bg-white/50 p-4 dark:bg-slate-950/20">
                            <div class="flex items-center justify-between">
                                <Skeleton class="h-3.5 w-32" />
                                <Skeleton class="h-4 w-20 rounded-full" />
                            </div>
                            <Skeleton class="h-36 w-full rounded-xl" />
                            <Skeleton class="h-3 w-2/3" />
                        </div>
                    </div>

                    <!-- Twin Custom SVG Charts -->
                    <div :class="['grid gap-4 md:grid-cols-2', { 'opacity-40 pointer-events-none transition-opacity': isNavigating }]">
                        <!-- Chart 1: New Tenants -->
                        <div class="relative overflow-hidden rounded-xl border border-border bg-white/50 dark:bg-slate-950/20 p-4 transition-all duration-300 hover:border-sky-500/30">
                            <div class="mb-3 flex items-center justify-between text-xs">
                                <span class="font-bold text-slate-600 dark:text-slate-300">Nhà hàng đăng ký mới</span>
                                <span class="text-[10px] font-extrabold text-sky-500 bg-sky-500/10 border border-sky-500/20 px-2 py-0.5 rounded-full">
                                    Tháng này: +{{ tenantGrowth[tenantGrowth.length - 1]?.new_tenants ?? 0 }}
                                </span>
                            </div>

                            <!-- Interactive Chart Body -->
                            <AreaChart :series="growthSeries" gradient-id="growthGrad" color="#0ea5e9" :compare-series="compareSeries" compare-color="#94a3b8">
                                <template #tooltip="{ point, comparePoint }">
                                    <span class="text-[8px] uppercase tracking-wider text-muted-foreground font-mono">{{ point.label }}</span>
                                    <span class="font-extrabold text-sky-500">{{ point.value }} đăng ký mới</span>
                                    <span class="text-[8px] text-emerald-500 font-semibold font-mono">Chuyển đổi: {{ point.rate }}%</span>
                                    <span v-if="comparePoint" class="text-[8px] text-slate-400 font-semibold font-mono">Kỳ trước: {{ comparePoint.value }}</span>
                                </template>
                            </AreaChart>
                        </div>

                        <!-- Chart 2: Free to Pro conversions -->
                        <div class="relative overflow-hidden rounded-xl border border-border bg-white/50 dark:bg-slate-950/20 p-4 transition-all duration-300 hover:border-violet-500/30">
                            <div class="mb-3 flex items-center justify-between text-xs">
                                <span class="font-bold text-slate-600 dark:text-slate-300">Chuyển đổi Free sang Pro</span>
                                <span class="text-[10px] font-extrabold text-violet-500 bg-violet-500/10 border border-violet-500/20 px-2 py-0.5 rounded-full">
                                    Tháng này: +{{ tenantGrowth[tenantGrowth.length - 1]?.free_to_pro ?? 0 }}
                                </span>
                            </div>

                            <AreaChart :series="freeToProSeries" gradient-id="proGrad" color="#8b5cf6">
                                <template #tooltip="{ point }">
                                    <span class="text-[8px] uppercase tracking-wider text-muted-foreground font-mono">{{ point.label }}</span>
                                    <span class="font-extrabold text-violet-500">{{ point.value }} nâng cấp Pro</span>
                                </template>
                            </AreaChart>
                        </div>
                    </div>

                    <!-- Growth Data Table -->
                    <div class="overflow-hidden rounded-xl border border-border bg-white dark:bg-slate-950/20 shadow-2xs">
                        <div class="overflow-x-auto w-full">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-muted/60 text-muted-foreground font-bold border-b border-border">
                                <tr>
                                    <th class="px-4 py-2.5 font-bold uppercase tracking-wider">Tháng</th>
                                    <th class="px-4 py-2.5 font-bold uppercase tracking-wider text-center">New Tenants</th>
                                    <th class="px-4 py-2.5 font-bold uppercase tracking-wider text-center">Free to Pro</th>
                                    <th class="px-4 py-2.5 font-bold uppercase tracking-wider text-right">Tỷ lệ Chuyển đổi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/60">
                                <tr v-for="point in tenantGrowth" :key="point.month" class="hover:bg-muted/30 transition-all font-medium text-slate-700 dark:text-slate-300">
                                    <td class="px-4 py-2.5 font-bold font-mono">{{ point.label }}</td>
                                    <td class="px-4 py-2.5 text-center font-mono font-semibold">{{ point.new_tenants }}</td>
                                    <td class="px-4 py-2.5 text-center font-mono font-semibold">{{ point.free_to_pro }}</td>
                                    <td class="px-4 py-2.5 text-right font-mono font-bold text-violet-600 dark:text-violet-400">
                                        {{ point.conversion_rate }}%
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Right Column: SaaS Health & Usage -->
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
                <!-- SaaS Health Card -->
                <Card class="bg-card/60 dark:bg-card/30 backdrop-blur-xs">
                    <CardHeader class="pb-2 border-b border-border/40">
                        <CardTitle class="text-base font-bold flex items-center gap-2">
                            <Activity class="size-4.5 text-emerald-500" /> Sức khỏe SaaS (SaaS Health)
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="pt-4 space-y-3.5 font-semibold text-xs">
                        <div class="flex items-center justify-between border-b pb-2 border-border/30">
                            <span class="text-muted-foreground">Active Subscription</span>
                            <span class="font-bold text-slate-800 dark:text-slate-200 font-mono">{{ saasMetrics.active_subscriptions }}</span>
                        </div>
                        <div class="flex items-center justify-between border-b pb-2 border-border/30">
                            <span class="text-muted-foreground">Paid Tenants</span>
                            <span class="font-bold text-slate-800 dark:text-slate-200 font-mono">{{ saasMetrics.paid_tenants }}</span>
                        </div>
                        <div class="flex items-center justify-between border-b pb-2 border-border/30">
                            <span class="text-muted-foreground">Churn Rate</span>
                            <span :class="['font-bold font-mono', saasMetrics.churn_rate > 5 ? 'text-rose-500' : 'text-emerald-500']">
                                {{ saasMetrics.churn_rate }}%
                            </span>
                        </div>
                        <div class="flex items-center justify-between pb-0">
                            <span class="text-muted-foreground">Rời bỏ tháng này</span>
                            <span class="font-bold text-slate-800 dark:text-slate-200 font-mono">{{ saasMetrics.churned_this_month }}</span>
                        </div>
                    </CardContent>
                </Card>

                <!-- D3: NRR/GRR — Doanh thu giữ lại (Net/Gross Revenue Retention) -->
                <Card class="bg-card/60 dark:bg-card/30 backdrop-blur-xs">
                    <CardHeader class="pb-2 border-b border-border/40">
                        <CardTitle class="text-base font-bold flex items-center gap-2">
                            <TrendingUp class="size-4.5 text-cyan-500" /> Doanh thu giữ lại (NRR / GRR)
                        </CardTitle>
                        <p class="text-[11px] text-muted-foreground font-medium">So sánh MRR {{ revenueRetention.previous_label }} → {{ revenueRetention.period_label }}</p>
                    </CardHeader>
                    <CardContent class="pt-4 space-y-3.5 font-semibold text-xs">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="rounded-xl border border-border/40 bg-muted/20 p-3 text-center">
                                <p class="text-[9px] font-extrabold uppercase tracking-widest text-muted-foreground">NRR</p>
                                <p :class="['mt-1 text-xl font-black font-mono', revenueRetention.nrr === null ? 'text-muted-foreground' : revenueRetention.nrr >= 100 ? 'text-emerald-500' : 'text-amber-500']">
                                    {{ revenueRetention.nrr !== null ? `${revenueRetention.nrr}%` : '—' }}
                                </p>
                            </div>
                            <div class="rounded-xl border border-border/40 bg-muted/20 p-3 text-center">
                                <p class="text-[9px] font-extrabold uppercase tracking-widest text-muted-foreground">GRR</p>
                                <p :class="['mt-1 text-xl font-black font-mono', revenueRetention.grr === null ? 'text-muted-foreground' : revenueRetention.grr >= 90 ? 'text-emerald-500' : 'text-amber-500']">
                                    {{ revenueRetention.grr !== null ? `${revenueRetention.grr}%` : '—' }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center justify-between border-b pb-2 border-border/30">
                            <span class="text-muted-foreground">MRR đầu kỳ</span>
                            <span class="font-bold text-slate-800 dark:text-slate-200 font-mono">{{ formatCurrency(revenueRetention.starting_mrr) }}</span>
                        </div>
                        <div class="flex items-center justify-between border-b pb-2 border-border/30">
                            <span class="text-muted-foreground">Mở rộng (Expansion)</span>
                            <span class="font-bold text-emerald-500 font-mono">+{{ formatCurrency(revenueRetention.expansion) }}</span>
                        </div>
                        <div class="flex items-center justify-between border-b pb-2 border-border/30">
                            <span class="text-muted-foreground">Co lại (Contraction)</span>
                            <span class="font-bold text-amber-500 font-mono">-{{ formatCurrency(revenueRetention.contraction) }}</span>
                        </div>
                        <div class="flex items-center justify-between pb-0">
                            <span class="text-muted-foreground">Mất đi (Churned)</span>
                            <span class="font-bold text-rose-500 font-mono">-{{ formatCurrency(revenueRetention.churned) }}</span>
                        </div>
                    </CardContent>
                </Card>

                <!-- Resource Usage Card -->
                <Card class="bg-card/60 dark:bg-card/30 backdrop-blur-xs">
                    <CardHeader class="pb-2 border-b border-border/40">
                        <CardTitle class="text-base font-bold flex items-center gap-2">
                            <Server class="size-4.5 text-sky-500" /> Sử dụng tài nguyên (Resources)
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="pt-4 space-y-3.5 font-semibold text-xs">
                        <div class="flex items-center justify-between border-b pb-2 border-border/30">
                            <span class="text-muted-foreground">Orders (30 ngày qua)</span>
                            <span class="font-bold text-slate-800 dark:text-slate-200 font-mono">{{ resourceInsights.totals.orders_last_30_days }}</span>
                        </div>
                        <div class="flex items-center justify-between border-b pb-2 border-border/30">
                            <span class="text-muted-foreground">Dung lượng Cloud</span>
                            <span class="font-bold text-slate-800 dark:text-slate-200 font-mono">{{ formatBytes(resourceInsights.totals.storage_bytes) }}</span>
                        </div>
                        <div class="flex items-center justify-between border-b pb-2 border-border/30">
                            <span class="text-muted-foreground">Doanh thu MRR</span>
                            <span class="font-bold text-cyan-600 dark:text-cyan-400 font-mono">{{ formatCurrency(saasMetrics.mrr) }}</span>
                        </div>
                        <div class="flex items-center justify-between pb-0">
                            <span class="text-muted-foreground">Doanh thu ARR dự báo</span>
                            <span class="font-bold text-indigo-600 dark:text-indigo-400 font-mono">{{ formatCurrency(saasMetrics.arr) }}</span>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Restructured Top KPI Section: Left Detail Console & Right 2x3 KPI Grid -->
        <div v-if="isSectionVisible('kpi_console')" class="grid gap-4 lg:grid-cols-[1fr_1.3fr]">
            <!-- LEFT: 1 Big Detailed Card (Terminal Console style) -->
            <div class="relative overflow-hidden rounded-3xl border border-slate-800 bg-slate-950 p-6 text-slate-200 shadow-2xl flex flex-col justify-between h-auto lg:h-[500px] hover:border-slate-700/60 transition-all duration-300">
                <!-- Mac style header dots -->
                <div class="absolute top-4 left-5 flex items-center gap-1.5 select-none">
                    <span class="size-3 rounded-full bg-rose-500/90 shadow-[0_0_8px_#f43f5e]"></span>
                    <span class="size-3 rounded-full bg-amber-500/90 shadow-[0_0_8px_#f59e0b]"></span>
                    <span class="size-3 rounded-full bg-emerald-500/90 shadow-[0_0_8px_#10b981]"></span>
                    <span class="text-[9px] font-mono text-slate-500 ml-2 uppercase font-extrabold tracking-wider">Business Insights Monitor v1.2.0</span>
                </div>
                <!-- Status tag -->
                <div class="absolute top-3.5 right-5 flex items-center gap-1.5 bg-emerald-500/10 border border-emerald-500/30 px-2.5 py-0.5 rounded-full text-[9px] font-bold text-emerald-400">
                    <span class="size-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    STATUS: LIVE
                </div>

                <!-- Step Title & Info -->
                <div class="space-y-4 mt-8">
                    <div class="space-y-1.5">
                        <h3 class="text-sm font-black uppercase tracking-wider text-cyan-400 font-mono">
                            {{ activeKpi.step }}
                        </h3>
                        <p class="text-xs font-semibold text-slate-400 leading-relaxed">
                            {{ activeKpi.desc }}
                        </p>
                    </div>

                    <!-- Business Operational Metrics -->
                    <div class="space-y-3.5 border-t border-slate-800/80 pt-4 text-xs font-semibold">
                        <div class="space-y-0.5">
                            <p class="text-[9px] font-bold text-slate-500 uppercase tracking-widest">📊 {{ activeKpi.metric1_label }}</p>
                            <p class="text-lg font-black text-white font-mono tracking-wide">
                                {{ activeKpi.metric1_value }}
                            </p>
                        </div>
                        <div class="space-y-0.5">
                            <p class="text-[9px] font-bold text-slate-500 uppercase tracking-widest">📈 {{ activeKpi.metric2_label }}</p>
                            <p class="text-sm font-black text-emerald-400 font-mono">
                                {{ activeKpi.metric2_value }}
                            </p>
                        </div>
                        <div class="space-y-0.5">
                            <p class="text-[9px] font-bold text-slate-500 uppercase tracking-widest">⚡ {{ activeKpi.metric3_label }}</p>
                            <p class="text-xs font-bold text-cyan-400 font-mono">
                                {{ activeKpi.metric3_value }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Visual Allocation & AI Strategic Recommendation -->
                <div class="space-y-3.5 border-t border-slate-800/80 pt-4 mt-4 text-xs font-semibold">
                    <div class="space-y-1.5">
                        <p class="text-[9px] font-bold text-slate-500 uppercase tracking-widest">🔍 PHÂN BỔ CHI TIẾT (STATE DISTRIBUTION)</p>
                        <div class="flex flex-wrap gap-1.5">
                            <Badge v-for="tag in activeKpi.tables" :key="tag" 
                                class="rounded-lg text-[10px] font-extrabold px-2.5 py-0.5 bg-slate-900 border border-slate-850 text-slate-300">
                                {{ tag }}
                            </Badge>
                        </div>
                    </div>
                    
                    <div class="rounded-2xl bg-gradient-to-br from-violet-600/10 to-indigo-600/10 border border-violet-500/25 p-3.5 space-y-1.5 shadow-sm">
                        <p class="text-[9px] font-black text-violet-400 uppercase tracking-widest flex items-center gap-1.5">
                            🤖 AI STRATEGIC GROWTH RECOMMENDATION
                        </p>
                        <p class="text-[11px] text-violet-200/90 leading-relaxed font-semibold">
                            {{ activeKpi.note }}
                        </p>
                    </div>
                </div>
            </div>

            <!-- RIGHT: 6 KPI Cards Grid -->
            <div class="grid gap-4 sm:grid-cols-2 h-auto lg:h-[500px]">
                <div v-for="(card, index) in statCards" :key="card.label" 
                    @click="selectedKpiIdx = index"
                    :class="[
                        'group relative overflow-hidden transition-all duration-300 rounded-2xl border p-5 cursor-pointer bg-card/60 dark:bg-card/30 backdrop-blur-xs select-none flex flex-col justify-between min-h-[116px]',
                        selectedKpiIdx === index 
                            ? 'border-primary ring-1 ring-primary shadow-lg dark:bg-card/50' 
                            : 'hover:-translate-y-0.5 hover:shadow-md hover:border-primary/25 border-border/80'
                    ]"
                >
                    <!-- Active indicator dot -->
                    <div v-if="selectedKpiIdx === index" class="absolute top-3.5 right-3.5 flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-primary"></span>
                    </div>

                    <div class="flex items-center justify-between gap-4">
                        <div class="space-y-1">
                            <p class="text-[10px] font-extrabold uppercase tracking-widest text-muted-foreground">{{ card.label }}</p>
                            <p class="text-xl font-black tracking-tight text-slate-800 dark:text-slate-100 font-mono">{{ card.value }}</p>
                        </div>
                        <div :class="['flex size-10 items-center justify-center rounded-xl border transition-all duration-500 group-hover:scale-105 shrink-0', card.color]">
                            <component :is="card.icon" class="size-4.5" />
                        </div>
                    </div>
                    <div class="flex items-center gap-1.5 text-[10px] font-bold mt-2.5">
                        <span :class="[card.trend === 'up' ? 'text-emerald-500' : 'text-slate-400']">
                            {{ card.trend === 'up' ? '↑' : '•' }} {{ card.change }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- AI Insights Section (Glassmorphic Premium) -->
        <div v-if="hasAiData && isSectionVisible('ai_insights')" class="space-y-4 rounded-3xl border border-violet-500/20 dark:border-violet-500/30 p-6 bg-gradient-to-br from-violet-500/[0.04] via-indigo-500/[0.03] to-sky-500/[0.04] backdrop-blur-xl shadow-xs">
            <!-- Header + Overall Health -->
            <div class="flex flex-wrap items-center justify-between gap-4 border-b border-violet-500/10 pb-4">
                <div class="flex items-center gap-3.5">
                    <div class="flex size-11 items-center justify-center rounded-2xl bg-gradient-to-tr from-violet-600 to-indigo-600 shadow-md shadow-violet-500/20 text-white animate-pulse">
                        <Brain class="size-5.5" />
                    </div>
                    <div>
                        <h2 class="text-lg font-black tracking-tight text-slate-900 dark:text-slate-100 flex items-center gap-1.5">
                            AI Co-Pilot Analytics
                        </h2>
                        <p class="text-xs text-muted-foreground font-semibold">Công cụ trí tuệ dự báo hành vi, sức khoẻ tenant và dự phòng rủi ro rời bỏ</p>
                    </div>
                </div>

                <!-- Circular Overall Health Glass Badge -->
                <div v-if="aiInsights.overall_health" :class="['flex items-center gap-3.5 rounded-2xl border px-5 py-2.5 bg-card/40 dark:bg-slate-950/40 shadow-lg transition-all duration-300 hover:shadow-xl', overallHealthStyle.bg, overallHealthStyle.glow]">
                    <svg class="size-11 -rotate-90 filter drop-shadow-[0_0_4px_rgba(var(--health-glow),0.2)]" viewBox="0 0 36 36">
                        <circle cx="18" cy="18" r="14" fill="none" class="stroke-slate-200 dark:stroke-slate-800" stroke-width="3" />
                        <circle cx="18" cy="18" r="14" fill="none" :class="[overallHealthStyle.ring, 'transition-all duration-1000 ease-out']" stroke-width="3.2"
                            stroke-linecap="round"
                            :stroke-dasharray="`${(aiInsights.overall_health.score / 100) * 87.96} 87.96`" />
                    </svg>
                    <div class="space-y-0.5">
                        <p :class="['text-2xl font-black font-mono', overallHealthStyle.text]">
                            {{ aiInsights.overall_health.score }}<span class="text-xs font-semibold text-muted-foreground">/100</span>
                        </p>
                        <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider">{{ aiInsights.overall_health.label }}</p>
                    </div>
                </div>
            </div>

            <!-- Segment Indicators -->
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <button type="button" v-for="seg in segmentCards" :key="seg.key" @click="showSegmentRestaurants(seg)"
                    :class="['relative overflow-hidden rounded-2xl border p-4.5 bg-gradient-to-br bg-card/40 dark:bg-slate-950/20 backdrop-blur-xs transition-all duration-300 hover:-translate-y-0.5 text-left cursor-pointer', seg.gradient, seg.border]">
                    <p class="text-[10px] font-extrabold uppercase tracking-widest text-muted-foreground">{{ seg.label }}</p>
                    <p :class="['mt-1.5 text-3xl font-black font-mono tracking-tight', seg.color]">{{ seg.value }}</p>
                    <span class="absolute right-4 top-3 text-2xl opacity-20 select-none">{{ seg.icon }}</span>
                </button>
            </div>

            <div class="grid gap-5 xl:grid-cols-[1.1fr_1fr]">
                <!-- Churn Risk Assessment -->
                <Card class="overflow-hidden border-rose-500/25 bg-card/40 dark:bg-slate-950/15 backdrop-blur-xs shadow-xs">
                    <CardHeader class="flex-row items-center justify-between pb-3 border-b border-rose-500/10">
                        <div class="flex items-center gap-2">
                            <div class="flex size-8 items-center justify-center rounded-xl bg-rose-500/15 border border-rose-500/20 text-rose-500">
                                <AlertTriangle class="size-4" />
                            </div>
                            <div>
                                <CardTitle class="text-sm font-black">Nguy cơ rời bỏ (Churn Risks)</CardTitle>
                                <p class="text-[10px] text-muted-foreground">Cảnh báo rủi ro tự động dựa trên tần suất vận hành</p>
                            </div>
                        </div>
                        <Badge variant="secondary" class="text-[10px] font-extrabold rounded-lg px-2 bg-rose-500/10 text-rose-500 border border-rose-500/20">
                            Cảnh báo: {{ aiInsights.churn_risks.length }}
                        </Badge>
                    </CardHeader>
                    <CardContent class="divide-y divide-border/40 p-0">
                        <div v-for="(r, idx) in aiInsights.churn_risks" :key="r.restaurant_id" 
                            @click="toggleRiskExpand(r.restaurant_id)"
                            class="px-5 py-4 hover:bg-muted/30 dark:hover:bg-slate-900/20 transition-all cursor-pointer select-none">
                            <div class="flex items-center justify-between gap-2">
                                <p class="font-extrabold text-sm truncate text-slate-800 dark:text-slate-200 flex items-center gap-1.5 min-w-0">
                                    <component :is="isRiskExpanded(r.restaurant_id, idx) ? ChevronUp : ChevronDown" class="size-4 text-muted-foreground shrink-0" />
                                    <Link :href="`/super-admin/restaurants/${r.restaurant_id}`" class="truncate hover:underline hover:text-rose-500 transition-colors" @click.stop>{{ r.name }}</Link>
                                </p>
                                <span :class="['shrink-0 rounded-full px-2.5 py-0.5 text-[9px] font-extrabold uppercase border', riskColor[r.risk_level]]">
                                    {{ r.risk_level === 'high' ? '🔴 Khẩn cấp' : r.risk_level === 'medium' ? '🟡 Trung bình' : '🟢 Thấp' }}
                                </span>
                            </div>
                            <!-- Progress bar -->
                            <div class="flex items-center gap-2.5 mt-2">
                                <div class="h-2 flex-1 rounded-full bg-muted/60 overflow-hidden border">
                                    <div :class="['h-full rounded-full transition-all duration-1000 ease-out', riskBarColor[r.risk_level]]"
                                        :style="{ width: r.risk_score + '%' }" />
                                </div>
                                <span class="text-xs font-black font-mono w-9 text-right text-slate-700 dark:text-slate-300">{{ r.risk_score }}%</span>
                            </div>
                            <!-- Collapsible details -->
                            <div v-if="isRiskExpanded(r.restaurant_id, idx)" class="space-y-3 pt-3">
                                <!-- Reasons -->
                                <div v-if="r.reasons.length" class="space-y-1 bg-white/30 dark:bg-slate-950/20 p-2.5 rounded-xl border border-border/40">
                                    <p class="text-[9px] font-bold text-muted-foreground uppercase tracking-widest">Dấu hiệu bất thường:</p>
                                    <ul class="space-y-1">
                                        <li v-for="reason in r.reasons" :key="reason" class="text-xs text-muted-foreground/90 font-medium flex items-start gap-2">
                                            <span class="shrink-0 mt-1 text-rose-500">•</span>{{ reason }}
                                        </li>
                                    </ul>
                                </div>
                                <!-- Actions -->
                                <div v-if="r.actions?.length" class="rounded-xl bg-amber-500/10 dark:bg-amber-950/20 border border-amber-500/25 p-3 space-y-1 shadow-2xs">
                                    <p class="text-[9px] font-bold text-amber-600 dark:text-amber-400 uppercase tracking-widest flex items-center gap-1">
                                        💡 Gợi ý hành động can thiệp:
                                    </p>
                                    <ul class="space-y-1">
                                        <li v-for="action in r.actions" :key="action" class="text-xs text-amber-700 dark:text-amber-300 font-semibold flex items-start gap-2">
                                            <span class="shrink-0 text-amber-500">→</span>{{ action }}
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div v-if="!aiInsights.churn_risks.length" class="px-5 py-12 text-center text-xs text-muted-foreground font-semibold">
                            🎉 Hệ thống khoẻ mạnh! Không phát hiện rủi ro rời bỏ nào.
                        </div>
                    </CardContent>
                </Card>

                <div class="space-y-4">
                    <!-- Health Scores list -->
                    <Card class="border-emerald-500/25 bg-card/40 dark:bg-slate-950/15 backdrop-blur-xs shadow-xs">
                        <CardHeader class="flex-row items-center gap-2 pb-3 border-b border-emerald-500/10">
                            <div class="flex size-8 items-center justify-center rounded-xl bg-emerald-500/15 border border-emerald-500/20 text-emerald-500">
                                <Heart class="size-4" />
                            </div>
                            <div>
                                <CardTitle class="text-sm font-black">Top sức khoẻ Tenant (Health Scores)</CardTitle>
                                <p class="text-[10px] text-muted-foreground">Các nhà hàng có chỉ số vận hành ổn định và phát triển tốt</p>
                            </div>
                        </CardHeader>
                        <CardContent class="pt-4 space-y-4.5">
                            <div v-for="(h, idx) in aiInsights.health_scores" :key="h.restaurant_id" class="flex items-center gap-3.5 hover:bg-muted/20 p-1.5 rounded-xl transition-all">
                                <span class="text-xs font-black text-muted-foreground w-4 text-center font-mono">{{ idx + 1 }}</span>
                                <div class="flex-1 min-w-0 space-y-0.5">
                                    <Link :href="`/super-admin/restaurants/${h.restaurant_id}`" class="block text-xs font-black truncate text-slate-800 dark:text-slate-200 hover:underline hover:text-emerald-500 transition-colors">{{ h.name }}</Link>
                                    <p class="text-[10px] font-bold text-muted-foreground font-mono">{{ h.order_count_30d }} đơn / 30 ngày</p>
                                </div>
                                <div class="flex items-center gap-3 shrink-0">
                                    <div class="h-2 w-20 rounded-full bg-muted/60 overflow-hidden border">
                                        <div :class="['h-full rounded-full transition-all duration-1000 ease-out', healthBarColor[h.level]]"
                                            :style="{ width: h.score + '%' }" />
                                    </div>
                                    <span :class="['text-xs font-black w-8 text-right font-mono', h.level === 'good' ? 'text-emerald-500' : h.level === 'fair' ? 'text-amber-500' : 'text-rose-500']">
                                        {{ h.score }}
                                    </span>
                                </div>
                            </div>
                            <p v-if="!aiInsights.health_scores.length" class="text-xs text-muted-foreground font-semibold text-center py-4">Chưa ghi nhận dữ liệu.</p>
                        </CardContent>
                    </Card>

                    <!-- MRR Forecast Bar Chart -->
                    <Card v-if="aiInsights.mrr_forecast?.length" class="border-cyan-500/25 bg-card/40 dark:bg-slate-950/15 backdrop-blur-xs shadow-xs">
                        <CardHeader class="flex-row items-center justify-between pb-2 border-b border-cyan-500/10">
                            <div class="flex items-center gap-2">
                                <div class="flex size-8 items-center justify-center rounded-xl bg-cyan-500/15 border border-cyan-500/20 text-cyan-500">
                                    <TrendingUp class="size-4" />
                                </div>
                                <div>
                                    <CardTitle class="text-sm font-black">Dự báo MRR (3 tháng tới)</CardTitle>
                                    <p class="text-[10px] text-muted-foreground">Mô hình hồi quy tuyến tính tích lũy doanh thu tương lai</p>
                                </div>
                            </div>
                            <Badge variant="secondary" class="text-[10px] font-extrabold rounded-lg px-2 bg-cyan-500/10 text-cyan-500 border border-cyan-500/20">
                                AI Forecast
                            </Badge>
                        </CardHeader>
                        <CardContent class="pt-5">
                            <div class="flex items-end justify-around gap-4 h-28 select-none">
                                <div v-for="f in aiInsights.mrr_forecast" :key="f.month" class="group/bar flex flex-col items-center gap-2 flex-1">
                                    <!-- Tooltip hover trên cột -->
                                    <span class="text-[9px] font-bold text-cyan-600 dark:text-cyan-400 font-mono transition-transform duration-300 group-hover/bar:-translate-y-1 text-center">
                                        {{ formatCurrency(f.predicted_mrr).replace(',00 ₫', '').replace(' ₫', 'đ') }}
                                    </span>
                                    
                                    <!-- Column Graphic representation -->
                                    <div class="w-full rounded-t-lg overflow-hidden bg-muted/50 dark:bg-slate-900/50 relative border border-border/40 hover:border-cyan-500/40" style="min-height: 8px; max-height: 60px;"
                                        :style="{ height: Math.max(8, (f.predicted_mrr / maxForecastMrr) * 60) + 'px' }">
                                        <div :class="['absolute inset-0 rounded-t-md transition-all duration-500 group-hover/bar:brightness-110', f.trend === 'up' ? 'bg-gradient-to-t from-cyan-600 to-cyan-400' : 'bg-gradient-to-t from-slate-600 to-slate-400']" />
                                    </div>
                                    
                                    <div class="flex flex-col items-center gap-0.5">
                                        <span class="text-[10px] font-bold text-muted-foreground font-mono">{{ f.month }}</span>
                                        <span class="text-[10px] text-emerald-500 font-bold">{{ f.trend === 'up' ? '↑ Tăng' : '↓' }}</span>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>

        <!-- Top Order & Top Storage Capacity Bars -->
        <div v-if="isSectionVisible('resource_usage')" class="grid gap-4 xl:grid-cols-2">
            <!-- Top Order Volume with dynamic progress bars -->
            <Card class="bg-card/60 dark:bg-card/30 backdrop-blur-xs">
                <CardHeader class="pb-2 border-b border-border/40">
                    <CardTitle class="text-base font-bold flex items-center gap-2">
                        <Crown class="size-4.5 text-violet-500" /> Top Tenant theo lượng đơn hàng
                    </CardTitle>
                </CardHeader>
                <CardContent class="pt-4 space-y-4">
                    <div v-for="item in resourceInsights.top_order_restaurants" :key="item.restaurant_id" class="space-y-2 hover:bg-muted/10 p-2 rounded-xl transition-all border border-transparent hover:border-border/30">
                        <div class="flex items-center justify-between text-xs font-semibold">
                            <div class="min-w-0">
                                <Link :href="`/super-admin/restaurants/${item.restaurant_id}`" class="font-bold text-slate-800 dark:text-slate-200 hover:underline hover:text-violet-500 transition-colors">{{ item.name }}</Link>
                                <p class="text-[10px] text-muted-foreground font-mono uppercase">{{ item.code ?? 'tenant' }}</p>
                            </div>
                            <Badge variant="secondary" class="font-bold font-mono text-[10px] bg-violet-500/10 text-violet-500 hover:bg-violet-500/10 border border-violet-500/25">
                                {{ item.orders_count }} orders
                            </Badge>
                        </div>
                        <div class="h-2 w-full rounded-full bg-muted/60 overflow-hidden border">
                            <div class="h-full rounded-full bg-gradient-to-r from-violet-600 to-indigo-500 transition-all duration-1000 ease-out" 
                                :style="{ width: `${((item.orders_count ?? 0) / topOrdersMax) * 100}%` }" />
                        </div>
                    </div>
                    <p v-if="!resourceInsights.top_order_restaurants.length" class="text-xs text-muted-foreground font-semibold text-center py-4">Chưa có dữ liệu đơn hàng.</p>
                </CardContent>
            </Card>

            <!-- Top Storage Capacity with dynamic progress bars -->
            <Card class="bg-card/60 dark:bg-card/30 backdrop-blur-xs">
                <CardHeader class="pb-2 border-b border-border/40">
                    <CardTitle class="text-base font-bold flex items-center gap-2">
                        <Server class="size-4.5 text-sky-500" /> Top Tenant theo dung lượng lưu trữ Cloud
                    </CardTitle>
                </CardHeader>
                <CardContent class="pt-4 space-y-4">
                    <div v-for="item in resourceInsights.top_storage_restaurants" :key="item.restaurant_id" class="space-y-2 hover:bg-muted/10 p-2 rounded-xl transition-all border border-transparent hover:border-border/30">
                        <div class="flex items-center justify-between text-xs font-semibold">
                            <div class="min-w-0">
                                <Link :href="`/super-admin/restaurants/${item.restaurant_id}`" class="font-bold text-slate-800 dark:text-slate-200 hover:underline hover:text-sky-500 transition-colors">{{ item.name }}</Link>
                                <p class="text-[10px] text-muted-foreground font-mono">{{ item.files_count ?? 0 }} tệp tin</p>
                            </div>
                            <Badge variant="secondary" class="font-bold font-mono text-[10px] bg-sky-500/10 text-sky-500 hover:bg-sky-500/10 border border-sky-500/25">
                                {{ formatBytes(item.storage_bytes ?? 0) }}
                            </Badge>
                        </div>
                        <div class="h-2 w-full rounded-full bg-muted/60 overflow-hidden border">
                            <div class="h-full rounded-full bg-gradient-to-r from-sky-500 to-cyan-400 transition-all duration-1000 ease-out" 
                                :style="{ width: `${((item.storage_bytes ?? 0) / topStorageMax) * 100}%` }" />
                        </div>
                    </div>
                    <p v-if="!resourceInsights.top_storage_restaurants.length" class="text-xs text-muted-foreground font-semibold text-center py-4">Chưa có dữ liệu dung lượng.</p>
                </CardContent>
            </Card>
        </div>

        <!-- Recent Restaurants & System signals -->
        <div v-if="isSectionVisible('recent_restaurants')" class="grid gap-4 xl:grid-cols-[1.5fr_1fr]">
            <!-- Recent Restaurants Card Table -->
            <Card class="bg-card/60 dark:bg-card/30 backdrop-blur-xs overflow-hidden">
                <CardHeader class="flex-row items-center justify-between pb-3 border-b border-border/40">
                    <div>
                        <CardTitle class="text-base font-bold">Danh sách Tenant đăng ký mới nhất</CardTitle>
                        <p class="text-xs text-muted-foreground">Theo dõi và xét duyệt trạng thái kích hoạt nhà hàng mới</p>
                    </div>
                    <Link href="/super-admin/restaurants" class="text-[11px] font-bold text-primary hover:underline">Xem tất cả</Link>
                </CardHeader>
                <CardContent class="p-0">
                    <div class="overflow-x-auto w-full">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-muted/40 border-b border-border/60 text-muted-foreground font-bold">
                                <tr>
                                    <th class="px-5 py-3 font-bold uppercase tracking-wider">Nhà hàng</th>
                                    <th class="px-4 py-3 font-bold uppercase tracking-wider text-center">Gói cước</th>
                                    <th class="px-4 py-3 font-bold uppercase tracking-wider text-center">Trạng thái</th>
                                    <th class="px-4 py-3 font-bold uppercase tracking-wider text-right">Ngày tạo</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/60">
                                <tr v-for="r in recentRestaurants" :key="r.id" class="hover:bg-muted/30 transition-all font-medium text-slate-700 dark:text-slate-300">
                                    <td class="px-5 py-3">
                                        <Link :href="`/super-admin/restaurants/${r.id}`" class="font-bold hover:underline hover:text-primary transition-all">{{ r.name }}</Link>
                                        <p class="text-[10px] text-slate-400 mt-0.5 font-semibold">Chủ quán: {{ r.owner }}</p>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span :class="['rounded-full px-2 py-0.5 text-[10px] font-extrabold border', r.plan_code?.toLowerCase() === 'pro' ? 'text-violet-600 bg-violet-500/10 border-violet-500/25' : 'text-slate-500 bg-slate-500/10 border-slate-500/25']">
                                            {{ r.plan }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span :class="['inline-flex items-center rounded-full px-2.5 py-0.5 text-[9px] font-extrabold uppercase', statusColor[r.status]]">
                                            {{ statusLabel[r.status] ?? r.status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono font-semibold text-slate-500">{{ r.created_at }}</td>
                                </tr>
                                <tr v-if="!recentRestaurants.length">
                                    <td colspan="4" class="px-5 py-10 text-center text-muted-foreground font-semibold">Hiện chưa có nhà hàng nào.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <!-- Plan Distribution & System Signals (Pulsing LEDs) -->
            <div class="space-y-4">
                <!-- Plan Distribution -->
                <Card class="bg-card/60 dark:bg-card/30 backdrop-blur-xs">
                    <CardHeader class="pb-2 border-b border-border/40">
                        <CardTitle class="text-base font-bold flex items-center gap-2">
                            <Crown class="size-4.5 text-violet-500" /> Phân bổ gói dịch vụ (Plan Distribution)
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="pt-4 space-y-4">
                        <div class="flex flex-col sm:flex-row items-center justify-around gap-6 py-2">
                            <!-- Custom SVG Donut/Pie Chart -->
                            <div class="relative size-32 flex items-center justify-center shrink-0">
                                <svg class="size-full -rotate-90" viewBox="0 0 36 36">
                                    <!-- Track background -->
                                    <circle cx="18" cy="18" r="15.9155" fill="none" class="stroke-slate-100 dark:stroke-slate-900/60" stroke-width="4.2" />
                                    <!-- Dynamic Donut Segments -->
                                    <circle v-for="slice in donutSlices" :key="slice.code"
                                        cx="18" cy="18" r="15.9155" fill="none"
                                        :class="[slice.color.stroke, 'transition-all duration-1000 ease-out hover:stroke-[5.2] cursor-pointer']"
                                        stroke-width="4.2"
                                        stroke-linecap="round"
                                        :stroke-dasharray="slice.dasharray"
                                        :stroke-dashoffset="slice.dashoffset"
                                    />
                                </svg>
                                <!-- Central Total Count Label -->
                                <div class="absolute flex flex-col items-center justify-center text-center">
                                    <span class="text-2xl font-black font-mono tracking-tight text-slate-800 dark:text-slate-100">
                                        {{ props.planDistribution.reduce((sum, p) => sum + p.count, 0) }}
                                    </span>
                                    <span class="text-[9px] font-bold text-muted-foreground uppercase tracking-widest">Tenants</span>
                                </div>
                            </div>

                            <!-- Plan Legends & Details list -->
                            <div class="flex-1 w-full space-y-2 font-semibold text-xs">
                                <div v-for="plan in props.planDistribution" :key="plan.code"
                                    class="flex items-center justify-between hover:bg-muted/15 px-2 py-1.5 rounded-xl transition-all border border-transparent hover:border-border/30">
                                    <div class="flex items-center gap-2">
                                        <span :class="[
                                            'size-2.5 rounded-full shrink-0',
                                            plan.code?.toLowerCase() === 'pro' ? 'bg-violet-500 shadow-[0_0_6px_#8b5cf6]' :
                                            plan.code?.toLowerCase() === 'free' ? 'bg-sky-500 shadow-[0_0_6px_#0ea5e9]' :
                                            plan.code?.toLowerCase() === 'max' ? 'bg-amber-500 shadow-[0_0_6px_#f59e0b]' :
                                            'bg-emerald-500 shadow-[0_0_6px_#10b981]'
                                        ]" />
                                        <span class="text-slate-700 dark:text-slate-300 font-bold">{{ plan.name }}</span>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-black font-mono text-slate-800 dark:text-slate-200">{{ plan.count }}</span>
                                        <span class="text-[10px] font-bold text-muted-foreground font-mono">
                                            ({{ plan.count > 0 ? Math.round((plan.count / Math.max(1, props.planDistribution.reduce((sum, p) => sum + p.count, 0))) * 100) : 0 }}%)
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- System Signals with Pulsing LEDs -->
                <Card class="bg-card/60 dark:bg-card/30 backdrop-blur-xs">
                    <CardHeader class="pb-2 border-b border-border/40">
                        <CardTitle class="text-base font-bold flex items-center gap-2">
                            <Terminal class="size-4.5 text-emerald-500" /> Tín hiệu hệ thống (System Signals)
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="pt-4 space-y-4 text-xs font-semibold">
                        <!-- LED Signals Grid -->
                        <div class="grid grid-cols-2 gap-3.5">
                            <!-- Signal 1: WebSockets Reverb -->
                            <div class="flex items-center gap-2 bg-slate-100/50 dark:bg-slate-900/50 border rounded-xl p-2">
                                <span class="relative flex h-2 w-2 shrink-0">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-cyan-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-cyan-500 shadow-[0_0_8px_#06b6d4]"></span>
                                </span>
                                <div class="min-w-0">
                                    <p class="text-[9px] text-muted-foreground uppercase font-extrabold tracking-wider">WebSockets</p>
                                    <p class="text-[10px] font-black text-slate-800 dark:text-slate-200 truncate">Reverb Online</p>
                                </div>
                            </div>
                            <!-- Signal 2: Horizon Queue -->
                            <div class="flex items-center gap-2 bg-slate-100/50 dark:bg-slate-900/50 border rounded-xl p-2">
                                <span class="relative flex h-2 w-2 shrink-0">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500 shadow-[0_0_8px_#10b981]"></span>
                                </span>
                                <div class="min-w-0">
                                    <p class="text-[9px] text-muted-foreground uppercase font-extrabold tracking-wider">Queue Worker</p>
                                    <p class="text-[10px] font-black text-slate-800 dark:text-slate-200 truncate">Horizon Active</p>
                                </div>
                            </div>
                            <!-- Signal 3: Pulse Analytics -->
                            <div class="flex items-center gap-2 bg-slate-100/50 dark:bg-slate-900/50 border rounded-xl p-2">
                                <span class="relative flex h-2 w-2 shrink-0">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-violet-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-violet-500 shadow-[0_0_8px_#8b5cf6]"></span>
                                </span>
                                <div class="min-w-0">
                                    <p class="text-[9px] text-muted-foreground uppercase font-extrabold tracking-wider">Laravel Pulse</p>
                                    <p class="text-[10px] font-black text-slate-800 dark:text-slate-200 truncate">Pulse Online</p>
                                </div>
                            </div>
                            <!-- Signal 4: API Gateway -->
                            <div class="flex items-center gap-2 bg-slate-100/50 dark:bg-slate-900/50 border rounded-xl p-2">
                                <span class="relative flex h-2 w-2 shrink-0">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500 shadow-[0_0_8px_#10b981]"></span>
                                </span>
                                <div class="min-w-0">
                                    <p class="text-[9px] text-muted-foreground uppercase font-extrabold tracking-wider">API Health</p>
                                    <p class="text-[10px] font-black text-slate-800 dark:text-slate-200 truncate">Gateway Stable</p>
                                </div>
                            </div>
                        </div>

                        <!-- System metrics detail list -->
                        <div class="border-t pt-3.5 space-y-2 text-[10px] font-bold text-muted-foreground">
                            <div class="flex items-center justify-between">
                                <span>Hoạt động / Tạm khóa / Hết hạn:</span>
                                <span class="font-mono text-slate-700 dark:text-slate-300 font-bold">
                                    {{ stats.active }} / {{ stats.suspended }} / {{ stats.expired }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span>Failed Jobs / Pending Jobs:</span>
                                <span :class="['font-mono font-bold', supportOverview.monitoring.failed_jobs > 0 ? 'text-rose-500' : 'text-slate-600 dark:text-slate-400']">
                                    {{ supportOverview.monitoring.failed_jobs }} / {{ supportOverview.monitoring.pending_jobs }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span>API Error Rate / Slow Queries:</span>
                                <span :class="['font-mono font-bold', supportOverview.monitoring.api_error_rate > 1 ? 'text-amber-500' : 'text-slate-600 dark:text-slate-400']">
                                    {{ supportOverview.monitoring.api_error_rate }}% / {{ supportOverview.monitoring.slow_queries }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span>Support Tickets / Alerts Open:</span>
                                <span :class="['font-mono font-bold', supportOverview.stats.alerts_open > 0 ? 'text-rose-500' : 'text-slate-600 dark:text-slate-400']">
                                    {{ supportOverview.stats.tickets_open }} / {{ supportOverview.stats.alerts_open }}
                                </span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- D2 + D5: Doanh thu theo gói & Hiệu suất sử dụng giữa các gói -->
        <div v-if="isSectionVisible('revenue_plan')" class="grid gap-4 xl:grid-cols-[1.3fr_1fr]">
            <!-- D2: Revenue Breakdown theo gói (stacked bar ngang theo tháng) -->
            <Card class="bg-card/60 dark:bg-card/30 backdrop-blur-xs">
                <CardHeader class="pb-2 border-b border-border/40">
                    <CardTitle class="text-base font-bold flex items-center gap-2">
                        <Gauge class="size-4.5 text-cyan-500" /> Doanh thu MRR theo gói (6 tháng gần nhất)
                    </CardTitle>
                    <p class="text-[11px] text-muted-foreground font-medium">Tỷ trọng đóng góp doanh thu định kỳ của từng gói dịch vụ theo từng tháng</p>
                </CardHeader>
                <CardContent class="pt-4 space-y-3">
                    <div class="flex flex-wrap items-center gap-3 text-[10px] font-bold text-muted-foreground">
                        <span v-for="code in revenueBreakdownPlanCodes" :key="code" class="flex items-center gap-1.5">
                            <span :class="['size-2.5 rounded-full', planColor(code).dot]" /> {{ planDisplayName(code) }}
                        </span>
                    </div>
                    <div class="space-y-2.5">
                        <div v-for="point in revenueBreakdown" :key="point.month" class="space-y-1">
                            <div class="flex items-center justify-between text-[10px] font-bold text-muted-foreground">
                                <span class="font-mono">{{ point.label }}</span>
                                <span class="font-mono text-slate-700 dark:text-slate-300">{{ formatCurrency(point.total) }}</span>
                            </div>
                            <div class="flex h-3.5 w-full overflow-hidden rounded-full bg-muted/30">
                                <div v-for="code in revenueBreakdownPlanCodes" :key="code"
                                    :class="['h-full transition-all duration-700', planColor(code).bar]"
                                    :style="{ width: `${revenueBreakdownMax > 0 ? ((point.by_plan[code] ?? 0) / revenueBreakdownMax) * 100 : 0}%` }"
                                    :title="`${planDisplayName(code)}: ${formatCurrency(point.by_plan[code] ?? 0)}`"
                                />
                            </div>
                        </div>
                        <p v-if="!revenueBreakdown.length" class="text-xs text-muted-foreground font-semibold text-center py-4">Chưa có dữ liệu doanh thu để hiển thị.</p>
                    </div>
                </CardContent>
            </Card>

            <!-- D5: Bảng so sánh hiệu suất giữa các gói -->
            <Card class="bg-card/60 dark:bg-card/30 backdrop-blur-xs overflow-hidden">
                <CardHeader class="pb-2 border-b border-border/40">
                    <CardTitle class="text-base font-bold flex items-center gap-2">
                        <Crown class="size-4.5 text-violet-500" /> Hiệu suất sử dụng theo gói
                    </CardTitle>
                    <p class="text-[11px] text-muted-foreground font-medium">Mức độ hoạt động trung bình của tenant theo từng gói (30 ngày qua)</p>
                </CardHeader>
                <CardContent class="p-0">
                    <div class="overflow-x-auto w-full">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-muted/40 border-b border-border/60 text-muted-foreground font-bold">
                                <tr>
                                    <th class="px-4 py-2.5 font-bold uppercase tracking-wider">Gói</th>
                                    <th class="px-3 py-2.5 font-bold uppercase tracking-wider text-center">Tenant</th>
                                    <th class="px-3 py-2.5 font-bold uppercase tracking-wider text-center">Đơn / 30 ngày</th>
                                    <th class="px-3 py-2.5 font-bold uppercase tracking-wider text-center">TB đơn/ngày/tenant</th>
                                    <th class="px-4 py-2.5 font-bold uppercase tracking-wider text-right">Tỷ lệ hoạt động</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border/60">
                                <tr v-for="plan in planPerformance" :key="plan.plan_code" class="hover:bg-muted/30 transition-all font-medium text-slate-700 dark:text-slate-300">
                                    <td class="px-4 py-2.5">
                                        <span class="flex items-center gap-1.5 font-bold">
                                            <span :class="['size-2.5 rounded-full shrink-0', planColor(plan.plan_code).dot]" /> {{ plan.plan_name }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2.5 text-center font-mono font-semibold">{{ plan.tenant_count }}</td>
                                    <td class="px-3 py-2.5 text-center font-mono font-semibold">{{ plan.orders_30d }}</td>
                                    <td class="px-3 py-2.5 text-center font-mono font-semibold">{{ plan.avg_orders_per_tenant_per_day }}</td>
                                    <td :class="['px-4 py-2.5 text-right font-mono font-bold', plan.active_tenant_ratio >= 50 ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400']">
                                        {{ plan.active_tenant_ratio }}%
                                    </td>
                                </tr>
                                <tr v-if="!planPerformance.length">
                                    <td colspan="5" class="px-5 py-10 text-center text-muted-foreground font-semibold">Chưa có dữ liệu để so sánh.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- D1: Cohort Analysis Heatmap -->
        <Card v-if="isSectionVisible('cohort_heatmap')" class="bg-card/60 dark:bg-card/30 backdrop-blur-xs overflow-hidden">
            <CardHeader class="pb-2 border-b border-border/40">
                <CardTitle class="text-base font-bold flex items-center gap-2">
                    <Users class="size-4.5 text-emerald-500" /> Phân tích Cohort — Tỷ lệ giữ chân theo nhóm đăng ký
                </CardTitle>
                <p class="text-[11px] text-muted-foreground font-medium">Tỷ lệ % nhà hàng còn hoạt động (active, có đơn hàng) ở các mốc M+1 / M+3 / M+6 sau khi đăng ký</p>
            </CardHeader>
            <CardContent class="pt-4">
                <div class="overflow-x-auto w-full">
                    <table class="w-full text-xs text-left">
                        <thead class="text-muted-foreground font-bold">
                            <tr>
                                <th class="px-4 py-2 font-bold uppercase tracking-wider">Cohort (tháng đăng ký)</th>
                                <th class="px-3 py-2 font-bold uppercase tracking-wider text-center">Số tenant</th>
                                <th class="px-3 py-2 font-bold uppercase tracking-wider text-center">M+1</th>
                                <th class="px-3 py-2 font-bold uppercase tracking-wider text-center">M+3</th>
                                <th class="px-3 py-2 font-bold uppercase tracking-wider text-center">M+6</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in cohortAnalysis" :key="row.month" class="font-semibold text-slate-700 dark:text-slate-300">
                                <td class="px-4 py-1.5 font-bold font-mono">{{ row.cohort }}</td>
                                <td class="px-3 py-1.5 text-center font-mono">{{ row.total }}</td>
                                <td class="px-2 py-1.5 text-center">
                                    <span :class="['inline-flex min-w-[3.25rem] justify-center rounded-lg px-2 py-1 text-[11px] font-extrabold font-mono', cohortCellStyle(row.m1)]">
                                        {{ row.m1 !== null ? `${row.m1}%` : '—' }}
                                    </span>
                                </td>
                                <td class="px-2 py-1.5 text-center">
                                    <span :class="['inline-flex min-w-[3.25rem] justify-center rounded-lg px-2 py-1 text-[11px] font-extrabold font-mono', cohortCellStyle(row.m3)]">
                                        {{ row.m3 !== null ? `${row.m3}%` : '—' }}
                                    </span>
                                </td>
                                <td class="px-2 py-1.5 text-center">
                                    <span :class="['inline-flex min-w-[3.25rem] justify-center rounded-lg px-2 py-1 text-[11px] font-extrabold font-mono', cohortCellStyle(row.m6)]">
                                        {{ row.m6 !== null ? `${row.m6}%` : '—' }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="!cohortAnalysis.length">
                                <td colspan="5" class="px-5 py-10 text-center text-muted-foreground font-semibold">Chưa có đủ dữ liệu cohort để phân tích.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="mt-3 text-[10px] text-muted-foreground font-semibold flex flex-wrap items-center gap-3">
                    <span class="flex items-center gap-1.5"><span class="size-2.5 rounded bg-emerald-500/80" /> ≥ 70% — Giữ chân tốt</span>
                    <span class="flex items-center gap-1.5"><span class="size-2.5 rounded bg-amber-500/70" /> 40–69% — Cần theo dõi</span>
                    <span class="flex items-center gap-1.5"><span class="size-2.5 rounded bg-rose-500/70" /> &lt; 40% — Nguy cơ rời bỏ cao</span>
                    <span class="flex items-center gap-1.5"><span class="size-2.5 rounded bg-muted/40 border border-border" /> — Chưa đủ thời gian quan sát</span>
                </p>
            </CardContent>
        </Card>

        <!-- Segment Restaurant Drill-down Dialog (B4) -->
        <Dialog :open="!!selectedSegment" @update:open="val => { if (!val) selectedSegment = null }">
            <DialogContent class="sm:max-w-2xl max-h-[80vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Nhà hàng thuộc nhóm: {{ selectedSegment?.label }}</DialogTitle>
                    <DialogDescription>Danh sách nhà hàng được phân loại vào phân khúc này dựa trên dữ liệu hoạt động thực tế.</DialogDescription>
                </DialogHeader>

                <div class="py-4">
                    <div v-if="isLoadingSegmentRestaurants" class="space-y-2">
                        <Skeleton v-for="i in 5" :key="i" class="h-9 w-full rounded-lg" />
                    </div>
                    <div v-else-if="segmentRestaurantsList.length === 0" class="text-center py-8 text-sm text-muted-foreground">
                        Không có nhà hàng nào thuộc nhóm này.
                    </div>
                    <div v-else class="overflow-x-auto rounded-lg border border-border">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-muted border-b border-border text-muted-foreground uppercase font-semibold">
                                    <th class="p-3">Tên Nhà Hàng</th>
                                    <th class="p-3">Mã Code</th>
                                    <th class="p-3">Chủ sở hữu</th>
                                    <th class="p-3">Ngày hết hạn</th>
                                    <th class="p-3 text-right">Trạng thái</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="res in segmentRestaurantsList" :key="res.id" class="border-b border-border last:border-0 hover:bg-muted/30">
                                    <td class="p-3 font-semibold">
                                        <Link :href="`/super-admin/restaurants/${res.id}`" class="text-primary hover:underline">
                                            {{ res.name }}
                                        </Link>
                                    </td>
                                    <td class="p-3 font-mono text-[10px]">{{ res.code }}</td>
                                    <td class="p-3">
                                        <div>{{ res.owner_name }}</div>
                                        <div class="text-[10px] text-muted-foreground">{{ res.owner_email }}</div>
                                    </td>
                                    <td class="p-3">{{ res.subscription_ends_at }}</td>
                                    <td class="p-3 text-right">
                                        <Badge
                                            :variant="res.status === 'active' ? 'default' : 'secondary'"
                                            :class="res.status === 'active' ? 'bg-emerald-500 text-white' : ''"
                                        >
                                            {{ res.status }}
                                        </Badge>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>

<style>
/* E2: Chế độ xem thu gọn — giảm khoảng cách/đệm của các khối chính trên dashboard
   để hiển thị nhiều thông tin hơn trong cùng một màn hình. Dùng selector toàn cục
   (không scoped) vì cần ghi đè các lớp tiện ích Tailwind dùng trong template. */
.compact-mode.flex.flex-col.gap-6 {
    gap: 0.875rem;
}
.compact-mode .gap-6 {
    gap: 0.75rem;
}
.compact-mode .gap-4 {
    gap: 0.625rem;
}
.compact-mode .gap-2\.5 {
    gap: 0.5rem;
}
.compact-mode .pt-5 {
    padding-top: 0.75rem;
}
.compact-mode .pt-4 {
    padding-top: 0.625rem;
}
.compact-mode .space-y-6 > :not([hidden]) ~ :not([hidden]) {
    margin-top: 0.875rem;
}
.compact-mode .space-y-4 > :not([hidden]) ~ :not([hidden]),
.compact-mode .space-y-4\.5 > :not([hidden]) ~ :not([hidden]) {
    margin-top: 0.625rem;
}
.compact-mode .space-y-3 > :not([hidden]) ~ :not([hidden]),
.compact-mode .space-y-3\.5 > :not([hidden]) ~ :not([hidden]) {
    margin-top: 0.5rem;
}
.compact-mode .space-y-2 > :not([hidden]) ~ :not([hidden]) {
    margin-top: 0.375rem;
}
.compact-mode [data-slot="card-header"] {
    padding-bottom: 0.625rem;
}
</style>

