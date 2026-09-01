<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    BarChart3,
    Boxes,
    BrainCircuit,
    Building2,
    CalendarDays,
    ChevronRight,
    ClipboardCheck,
    Clock3,
    Gauge,
    Lightbulb,
    PackageCheck,
    PackageSearch,
    ShieldAlert,
    ShieldCheck,
    Sparkles,
    TrendingUp,
    Warehouse,
    Zap,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';

import DashboardShell from '@/components/dashboard/DashboardShell.vue';
import NegativeInventoryCases from '@/components/NegativeInventoryCases.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import centralWarehouseRoutes from '@/routes/inventory/central-warehouse';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    centralBranch?: any;
    supplyAnalytics?: any;
    centralWarehouseAnalytics?: any;
    centralWarehouseAi?: any;
    receivingSummary?: any;
    inventorySummary?: any;
    supplyChainAlerts?: any;
    supplyChainReconciliation?: any;
    negativeStockCases?: any[];
}>();

const analytics = computed(() => props.supplyAnalytics ?? {});
const summary = computed(() => analytics.value.summary ?? {});
const inventory = computed(() => props.inventorySummary ?? {});
const receiving = computed(() => props.receivingSummary ?? {});
const warehouseKpi = computed(() => props.centralWarehouseAnalytics ?? {});
const aiAssessment = computed(() => props.centralWarehouseAi ?? {});
const supplyChainAlerts = computed(() => props.supplyChainAlerts ?? {});
const reconciliation = computed(() => props.supplyChainReconciliation ?? {});

const daily = computed(() => analytics.value.daily ?? []);
const branches = computed(() => analytics.value.branches ?? []);
const topIngredients = computed(() => analytics.value.top_ingredients ?? []);
const recommendations = computed(() => analytics.value.recommendations ?? []);
const insights = computed(() => analytics.value.insights ?? []);

const selectedKpiIdx = ref<number>(0);

const maxDailyItems = computed(() =>
    Math.max(...daily.value.map((day: any) => Number(day.items ?? 0)), 1),
);

const maxBranchRequests = computed(() =>
    Math.max(
        ...branches.value.map((branch: any) => Number(branch.requests ?? 0)),
        1,
    ),
);

const maxIngredientQuantity = computed(() =>
    Math.max(
        ...topIngredients.value.map((item: any) =>
            Number(item.total_quantity ?? 0),
        ),
        1,
    ),
);

const branchReport = computed(() => {
    const totalRequests = branches.value.reduce(
        (total: number, branch: any) => total + Number(branch.requests ?? 0),
        0,
    );

    return branches.value.map((branch: any) => ({
        ...branch,
        share:
            totalRequests > 0
                ? (Number(branch.requests ?? 0) / totalRequests) * 100
                : 0,
    }));
});

const formatCurrency = (amount: number | undefined) =>
    new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        maximumFractionDigits: 0,
    }).format(amount ?? 0);

const formatQuantity = (amount: number | undefined) =>
    new Intl.NumberFormat('vi-VN', {
        maximumFractionDigits: 1,
    }).format(amount ?? 0);

const formatPercent = (amount: number | undefined) =>
    `${(amount ?? 0).toFixed(1)}%`;

const formatDateTime = (value: string | undefined) => {
    if (!value) {
        return 'Chưa cập nhật';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleString('vi-VN', {
        hour: '2-digit',
        minute: '2-digit',
        day: '2-digit',
        month: '2-digit',
    });
};

const barHeight = (value: number, max: number) => {
    if (!max || max <= 0) {
        return '0%';
    }

    const percentage = Math.min(100, Math.max(8, (value / max) * 100));

    return `${percentage}%`;
};

// ── KPI CARDS CONFIG (TASTEFUL TONAL COLORS & LIGHT ACCENTS) ──────────
const kpiCards = computed(() => [
    {
        id: 'today',
        title: 'Cấp phát hôm nay',
        value: `${summary.value.today_requests ?? 0}`,
        unit: 'đơn',
        subtitle: `${formatQuantity(summary.value.today_items)} đv nguyên liệu`,
        icon: CalendarDays,
        iconBg: 'bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/20',
        activeBorder: 'border-sky-500/80 ring-1 ring-sky-500/50 shadow-sm shadow-sky-500/10',
        indicatorColor: 'bg-sky-500',
        badge: (summary.value.today_requests ?? 0) > 0 ? 'Đang thực hiện' : 'Bình thường',
        badgeClass: (summary.value.today_requests ?? 0) > 0 ? 'bg-sky-500/15 text-sky-700 dark:text-sky-300 border-sky-500/30' : 'bg-muted text-muted-foreground border-border',
    },
    {
        id: 'demand_7d',
        title: 'Nhu cầu 7 ngày',
        value: `${formatQuantity(summary.value.last7_items)}`,
        unit: 'đv',
        subtitle: `${summary.value.last7_requests ?? 0} đơn · ${formatCurrency(summary.value.last7_value)}`,
        icon: TrendingUp,
        iconBg: 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20',
        activeBorder: 'border-indigo-500/80 ring-1 ring-indigo-500/50 shadow-sm shadow-indigo-500/10',
        indicatorColor: 'bg-indigo-500',
        badge: 'Toàn chuỗi',
        badgeClass: 'bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 border-indigo-500/20',
    },
    {
        id: 'open_orders',
        title: 'Đơn đang mở',
        value: `${summary.value.open_requests ?? 0}`,
        unit: 'đơn',
        subtitle: `${summary.value.due_today_requests ?? 0} đơn đến hạn hôm nay`,
        icon: Clock3,
        iconBg: 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20',
        activeBorder: 'border-amber-500/80 ring-1 ring-amber-500/50 shadow-sm shadow-amber-500/10',
        indicatorColor: 'bg-amber-500',
        badge: (summary.value.due_today_requests ?? 0) > 0 ? 'Cần xử lý' : 'Đúng SLA',
        badgeClass: (summary.value.due_today_requests ?? 0) > 0 ? 'bg-amber-500/15 text-amber-700 dark:text-amber-300 border-amber-500/30' : 'bg-muted text-muted-foreground border-border',
    },
    {
        id: 'otif',
        title: 'OTIF tháng này',
        value: `${formatPercent(warehouseKpi.value.otif_percent ?? 100)}`,
        unit: '',
        subtitle: 'Đúng hẹn & Đủ số lượng',
        icon: Gauge,
        iconBg: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20',
        activeBorder: 'border-emerald-500/80 ring-1 ring-emerald-500/50 shadow-sm shadow-emerald-500/10',
        indicatorColor: 'bg-emerald-500',
        badge: (warehouseKpi.value.otif_percent ?? 100) >= 90 ? 'Đạt chuẩn' : 'Cần chú ý',
        badgeClass: (warehouseKpi.value.otif_percent ?? 100) >= 90 ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/30' : 'bg-destructive/15 text-destructive border-destructive/30',
    },
    {
        id: 'inventory_value',
        title: 'Giá trị tồn kho',
        value: formatCurrency(inventory.value.on_hand_value),
        unit: '',
        subtitle: `${inventory.value.ingredient_count ?? 0} món · ${formatQuantity(inventory.value.on_hand_quantity)} đv`,
        icon: Boxes,
        iconBg: 'bg-cyan-500/10 text-cyan-600 dark:text-cyan-400 border border-cyan-500/20',
        activeBorder: 'border-cyan-500/80 ring-1 ring-cyan-500/50 shadow-sm shadow-cyan-500/10',
        indicatorColor: 'bg-cyan-500',
        badge: 'Tài sản kho',
        badgeClass: 'bg-cyan-500/10 text-cyan-700 dark:text-cyan-300 border-cyan-500/20',
    },
    {
        id: 'urgent_restock',
        title: 'Cần nhập gấp',
        value: `${summary.value.urgent_recommendations ?? 0}`,
        unit: 'món',
        subtitle: 'Rủi ro thiếu trong 7 ngày',
        icon: AlertTriangle,
        iconBg: 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20',
        activeBorder: 'border-rose-500/80 ring-1 ring-rose-500/50 shadow-sm shadow-rose-500/10',
        indicatorColor: 'bg-rose-500',
        badge: (summary.value.urgent_recommendations ?? 0) > 0 ? 'Cảnh báo đỏ' : 'An toàn',
        badgeClass: (summary.value.urgent_recommendations ?? 0) > 0 ? 'bg-rose-500/15 text-rose-700 dark:text-rose-300 border-rose-500/30' : 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border-emerald-500/30',
    },
]);

const kpiDetails = computed(() => [
    {
        step: 'Tiến độ cấp phát trong ngày',
        desc: 'Theo dõi các phiếu xuất kho điều chuyển từ Kho Tổng đến các chi nhánh trong ca làm việc hôm nay.',
        metric1_label: 'Số phiếu cần giao',
        metric1_value: `${summary.value.today_requests ?? 0} phiếu`,
        metric2_label: 'Tổng khối lượng cấp',
        metric2_value: `${formatQuantity(summary.value.today_items)} đv`,
        metric3_label: 'Tỷ lệ hoàn thành cùng ngày',
        metric3_value: `${formatPercent(warehouseKpi.value.same_day_processing_percent ?? 95)}`,
        accentColor: 'text-sky-600 dark:text-sky-400',
        tables: [
            `Chi nhánh yêu cầu: ${branches.value.length} cơ sở`,
            `Đang tiếp nhận: ${summary.value.receiving_requests ?? 0} đơn`,
            `Tranh chấp mở: ${summary.value.disputed_requests ?? 0} đơn`,
        ],
        note: (summary.value.today_requests ?? 0) > 0
            ? `Hôm nay Kho Tổng đang tiếp nhận ${summary.value.today_requests} phiếu cấp phát với ${formatQuantity(summary.value.today_items)} đơn vị nguyên liệu. Đảm bảo nhân sự soạn hàng theo quy tắc FEFO và bàn giao đúng mã GN.`
            : 'Chưa ghi nhận phát sinh cấp phát mới trong ngày hôm nay. Kiểm tra trước các đơn đặt hàng định kỳ của chi nhánh để chuẩn bị sơ chế.',
    },
    {
        step: 'Phân tích nhu cầu toàn chuỗi 7 ngày qua',
        desc: 'Tổng hợp mức tiêu hao và dòng luân chuyển nguyên liệu giữa Kho Tổng và toàn bộ mạng lưới chi nhánh.',
        metric1_label: 'Tổng khối lượng yêu cầu',
        metric1_value: `${formatQuantity(summary.value.last7_items)} đv`,
        metric2_label: 'Tổng giá trị hàng xuất',
        metric2_value: formatCurrency(summary.value.last7_value),
        metric3_label: 'Trung bình đơn / ngày',
        metric3_value: `${formatQuantity(summary.value.average_daily_requests)} đơn/ngày`,
        accentColor: 'text-indigo-600 dark:text-indigo-400',
        tables: [
            `Số lượt điều chuyển: ${summary.value.last7_requests ?? 0} lượt`,
            `Top mặt hàng: ${topIngredients.value[0]?.name || 'N/A'} (${formatQuantity(topIngredients.value[0]?.total_quantity)} ${topIngredients.value[0]?.unit_symbol || ''})`,
            `Tỷ lệ Fill-rate: ${formatPercent(summary.value.fill_rate_percent ?? warehouseKpi.value.fill_rate_percent ?? 100)}`,
        ],
        note: `Nhu cầu 7 ngày gần nhất đạt ${formatQuantity(summary.value.last7_items)} đơn vị (trị giá ${formatCurrency(summary.value.last7_value)}). Mặt hàng có nhu cầu cao nhất là ${topIngredients.value[0]?.name || 'nguyên liệu chính'}.`,
    },
    {
        step: 'Kiểm soát hàng chờ & Tiến độ giao dịch (SLA)',
        desc: 'Giám sát các đơn điều chuyển chưa hoàn tất, đơn đến hạn xử lý và các phiếu đang trong quá trình vận chuyển.',
        metric1_label: 'Tổng đơn đang mở',
        metric1_value: `${summary.value.open_requests ?? 0} phiếu`,
        metric2_label: 'Đơn đến hạn hôm nay',
        metric2_value: `${summary.value.due_today_requests ?? 0} phiếu`,
        metric3_label: 'Đơn đang giao (In-Transit)',
        metric3_value: `${summary.value.dispatched_requests ?? summary.value.open_requests ?? 0} phiếu`,
        accentColor: 'text-amber-600 dark:text-amber-400',
        tables: [
            `GRN cần xác minh: ${receiving.value.pending_review ?? 0}`,
            `Cảnh báo SLA: ${(summary.value.due_today_requests ?? 0) > 0 ? 'Có đơn sắp trễ' : 'Đúng tiến độ'}`,
            `Số mặt hàng chờ xuất: ${formatQuantity(summary.value.open_items ?? 0)} đv`,
        ],
        note: (summary.value.due_today_requests ?? 0) > 0
            ? `Lưu ý: Có ${summary.value.due_today_requests} đơn cấp phát đến hạn hoàn thành trong ngày hôm nay. Trưởng kho cần ưu tiên đóng gói và bàn giao phương tiện vận tải sớm.`
            : 'Toàn bộ các đơn hàng đang mở đều nằm trong khung thời gian cam kết SLA tiêu chuẩn.',
    },
    {
        step: 'Chất lượng Vận hành & Chỉ số OTIF (On-Time In-Full)',
        desc: 'Đo lường độ tin cậy chuỗi cung ứng: Tỷ lệ giao hàng đúng hẹn và đủ số lượng, không bị hao hụt/hư hỏng.',
        metric1_label: 'Chỉ số OTIF tháng này',
        metric1_value: `${formatPercent(warehouseKpi.value.otif_percent ?? 100)}`,
        metric2_label: 'Tỷ lệ đáp ứng (Fill Rate)',
        metric2_value: `${formatPercent(summary.value.fill_rate_percent ?? 100)}`,
        metric3_label: 'Chênh lệch tiếp nhận',
        metric3_value: `${formatQuantity(receiving.value.discrepancy_quantity)} đv`,
        accentColor: 'text-emerald-600 dark:text-emerald-400',
        tables: [
            `Đánh giá chất lượng: ${(warehouseKpi.value.otif_percent ?? 100) >= 90 ? 'Tốt' : 'Cần cải thiện'}`,
            `Lô bị khóa / thu hồi: ${inventory.value.locked_batch_count ?? 0} lô`,
            `Tranh chấp đã xử lý: ${summary.value.resolved_disputes ?? 0} vụ`,
        ],
        note: (warehouseKpi.value.otif_percent ?? 100) >= 90
            ? 'Chỉ số OTIF duy trì ở mức cao trên 90%. Chuỗi cung ứng vận hành ổn định, hàng hóa đến các chi nhánh đầy đủ và đúng quy chuẩn.'
            : 'Chỉ số OTIF có dấu hiệu giảm. Rà soát lại phương tiện vận chuyển và khâu kiểm đếm tại cửa xuất của Kho Tổng.',
    },
    {
        step: 'Định giá Tài sản Tồn kho & Kiểm soát Lưu trữ',
        desc: 'Quản trị tổng giá trị nguyên vật liệu đang lưu trữ tại Kho Tổng, phân bổ lô date và vị trí kệ hàng.',
        metric1_label: 'Tổng giá trị tồn kho',
        metric1_value: formatCurrency(inventory.value.on_hand_value),
        metric2_label: 'Danh mục mặt hàng',
        metric2_value: `${inventory.value.ingredient_count ?? 0} mặt hàng`,
        metric3_label: 'Lô sắp hết hạn (≤ 7 ngày)',
        metric3_value: `${inventory.value.expiring_soon_count ?? 0} lô`,
        accentColor: 'text-cyan-600 dark:text-cyan-400',
        tables: [
            `Tổng số lượng: ${formatQuantity(inventory.value.on_hand_quantity)} đv`,
            `Vị trí kệ: ${inventory.value.location_count ?? 0} vị trí`,
            `Tồn dưới ngưỡng: ${inventory.value.low_stock_count ?? 0} mặt hàng`,
        ],
        note: (inventory.value.expiring_soon_count ?? 0) > 0
            ? `Chú ý: Đang có ${inventory.value.expiring_soon_count} lô nguyên liệu cận hạn sử dụng. Ưu tiên xuất điều chuyển trước cho các chi nhánh có lượng tiêu thụ tốt (FEFO).`
            : `Giá trị tồn kho hiện tại đạt ${formatCurrency(inventory.value.on_hand_value)}. Tồn kho an toàn, không ghi nhận lô hàng tồn đọng quá hạn.`,
    },
    {
        step: 'Cảnh báo Thiếu hụt & Khuyến nghị Mua hàng',
        desc: 'Mô hình AI dự báo các nguyên liệu có nguy cơ đứt gãy cung ứng dựa trên tồn thực tế và tốc độ tiêu thụ 28 ngày.',
        metric1_label: 'Món rủi ro thiếu hàng',
        metric1_value: `${summary.value.urgent_recommendations ?? 0} nguyên liệu`,
        metric2_label: 'Ngân sách dự kiến',
        metric2_value: formatCurrency(recommendations.value.reduce((acc: number, item: any) => acc + (Number(item.estimated_cost) || 0), 0)),
        metric3_label: 'Cảnh báo chuỗi cung ứng',
        metric3_value: `${supplyChainAlerts.value.critical ?? 0} cảnh báo`,
        accentColor: 'text-rose-600 dark:text-rose-400',
        tables: [
            ...recommendations.value.slice(0, 3).map((r: any) => `${r.name}: cần ${formatQuantity(r.recommended_quantity)} ${r.unit_symbol}`),
            `Âm nguyên liệu: ${props.negativeStockCases?.length ?? 0} trường hợp`,
        ],
        note: (summary.value.urgent_recommendations ?? 0) > 0
            ? `Cảnh báo: Phát hiện ${summary.value.urgent_recommendations} nguyên liệu có nguy cơ hết hàng trong 7 ngày tới. Hãy xem bảng "Khuyến nghị tồn kho 7 ngày tới" bên dưới để lập đơn đặt hàng (PO).`
            : 'Tồn kho hiện đáp ứng tốt nhu cầu dự báo cho toàn chuỗi trong 7 ngày tới.',
    },
]);

const activeKpi = computed(
    () => kpiDetails.value[selectedKpiIdx.value] ?? kpiDetails.value[0],
);

const displayAiScore = computed(() => {
    const rawScore = Number(aiAssessment.value.score);

    if (!isNaN(rawScore) && rawScore > 0) {
return rawScore;
}

    return 88;
});

const healthMetrics = computed(() => [
    {
        label: 'Tỷ lệ đáp ứng (Fill rate)',
        value: Number(
            summary.value.fill_rate_percent ??
                warehouseKpi.value.fill_rate_percent ??
                100,
        ),
        suffix: '%',
        description: 'Tỷ lệ số lượng thực cấp so với chi nhánh yêu cầu',
        icon: PackageCheck,
        barColor: 'from-indigo-500 to-sky-500',
    },
    {
        label: 'Tỷ lệ đúng hạn & Đủ hàng (OTIF)',
        value: Number(warehouseKpi.value.otif_percent ?? 100),
        suffix: '%',
        description: 'Đơn cấp phát giao đúng giờ & đủ số lượng',
        icon: Gauge,
        barColor: 'from-emerald-500 to-teal-400',
    },
    {
        label: 'Xử lý hoàn tất cùng ngày',
        value: Number(warehouseKpi.value.same_day_processing_percent ?? 95),
        suffix: '%',
        description: 'Đơn hoàn tất hoặc tiếp nhận trong ngày',
        icon: Clock3,
        barColor: 'from-amber-500 to-orange-400',
    },
]);

const metricStatus = (metric: any) => {
    if (metric.value >= 90) {
return 'Tốt';
}

    if (metric.value >= 75) {
return 'Khá';
}

    return 'Cần cải thiện';
};

const metricStatusClass = (metric: any) => {
    if (metric.value >= 90) {
return 'text-emerald-600 dark:text-emerald-400 font-bold';
}

    if (metric.value >= 75) {
return 'text-amber-600 dark:text-amber-400 font-bold';
}

    return 'text-rose-600 dark:text-rose-400 font-bold';
};

const priorityBadge = (priority: string) => {
    switch (priority) {
        case 'urgent':
            return {
                label: 'Cần nhập gấp',
                class: 'bg-rose-500/10 text-rose-700 dark:text-rose-400 border-rose-500/30',
            };
        case 'high':
            return {
                label: 'Ưu tiên cao',
                class: 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-500/30',
            };
        default:
            return {
                label: 'Theo dõi',
                class: 'bg-slate-500/10 text-slate-700 dark:text-slate-400 border-slate-500/30',
            };
    }
};
</script>

<template>
    <Head title="Tổng quan Kho Tổng" />

    <DashboardShell
        :show-header="false"
        class="max-w-[1600px] space-y-6 pt-2 pb-16"
    >
        <!-- ── 1. HEADER CHUẨN ENTERPRISE (TINH TẾ, ÁNH SÁNG NHẸ) ────────── -->
        <header
            class="relative overflow-hidden rounded-2xl border border-border/80 bg-gradient-to-br from-card via-card to-muted/30 p-5.5 shadow-xs backdrop-blur-md sm:flex-row sm:items-center"
        >
            <!-- Subtle Top Glow Accent -->
            <div
                class="pointer-events-none absolute -top-12 -right-12 size-48 rounded-full bg-primary/10 blur-2xl"
            />

            <div class="relative flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-md border border-primary/20 bg-primary/10 px-2.5 py-0.5 text-[11px] font-bold text-primary"
                        >
                            <Warehouse class="size-3.5" />
                            Kho Tổng
                        </span>
                        <span
                            class="inline-flex items-center gap-1.5 rounded-md border border-emerald-500/25 bg-emerald-500/10 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400 shadow-xs"
                        >
                            <span class="size-1.5 animate-pulse rounded-full bg-emerald-500" />
                            Dữ liệu thời gian thực
                        </span>
                    </div>

                    <h1 class="mt-2 text-xl font-black tracking-tight text-foreground sm:text-2xl lg:text-3xl">
                        Tổng quan Điều hành Kho Tổng
                    </h1>

                    <p class="mt-0.5 text-xs text-muted-foreground">
                        Phạm vi: <strong class="text-foreground">{{ centralBranch?.name || 'Kho Tổng Sài Gòn' }}</strong> · Cập nhật {{ formatDateTime(analytics.generated_at) }}
                    </p>
                </div>

                <!-- Action Buttons with subtle sheens -->
                <div class="flex flex-wrap items-center gap-2.5">
                    <Link
                        href="/inventory/central-warehouse/receiving?create=1"
                        class="inline-flex h-9.5 items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-primary to-primary/90 px-4 text-xs font-bold text-primary-foreground shadow-sm shadow-primary/25 transition-all hover:brightness-105 active:scale-98"
                    >
                        <PackageCheck class="size-4" />
                        Nhập nguyên liệu
                    </Link>

                    <Link
                        href="/inventory/central-warehouse/material-closing"
                        class="inline-flex h-9.5 items-center justify-center gap-2 rounded-xl border border-border/80 bg-background/80 px-3.5 text-xs font-semibold text-foreground shadow-xs transition hover:bg-muted active:scale-98"
                    >
                        <ClipboardCheck class="size-4 text-amber-500" />
                        Chốt nguyên liệu
                    </Link>

                    <a
                        :href="centralWarehouseRoutes.aiAdvisor.url()"
                        class="inline-flex h-9.5 items-center justify-center gap-2 rounded-xl border border-primary/20 bg-primary/5 px-3.5 text-xs font-semibold text-primary shadow-xs transition hover:bg-primary/10 active:scale-98"
                    >
                        <BrainCircuit class="size-4" />
                        Trợ lý AI
                    </a>
                </div>
            </div>
        </header>

        <!-- ── 2. 6 THẺ KPI TƯƠNG TÁC (CÓ MÀU SẮC NHÃ NHẶN & ÁNH SÁNG VIỀN) ── -->
        <section class="space-y-2.5">
            <div class="flex items-center justify-between text-xs text-muted-foreground">
                <span class="font-bold uppercase tracking-wider text-[10px] text-muted-foreground/90">
                    Chỉ số trọng yếu · Chọn thẻ để xem phân tích chi tiết
                </span>
                <span class="text-[11px]">
                    Đang chọn: <strong class="text-foreground">{{ kpiCards[selectedKpiIdx]?.title }}</strong>
                </span>
            </div>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <div
                    v-for="(card, idx) in kpiCards"
                    :key="card.id"
                    @click="selectedKpiIdx = idx"
                    class="group relative cursor-pointer overflow-hidden rounded-2xl border bg-card/80 p-4 transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md backdrop-blur-xs"
                    :class="[
                        selectedKpiIdx === idx
                            ? card.activeBorder
                            : 'border-border/70 hover:border-border',
                    ]"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span
                            class="rounded-md px-1.5 py-0.5 text-[9px] font-extrabold uppercase border"
                            :class="card.badgeClass"
                        >
                            {{ card.badge }}
                        </span>
                        <div
                            class="flex size-7 items-center justify-center rounded-lg transition-transform group-hover:scale-105"
                            :class="card.iconBg"
                        >
                            <component :is="card.icon" class="size-3.5" />
                        </div>
                    </div>

                    <p class="mt-3 text-[11px] font-semibold text-muted-foreground">
                        {{ card.title }}
                    </p>

                    <div class="mt-1 flex items-baseline gap-1">
                        <p class="text-xl font-black tracking-tight text-foreground lg:text-2xl">
                            {{ card.value }}
                        </p>
                        <span v-if="card.unit" class="text-xs font-semibold text-muted-foreground">
                            {{ card.unit }}
                        </span>
                    </div>

                    <p class="mt-1 truncate text-[11px] font-medium text-muted-foreground/90" :title="card.subtitle">
                        {{ card.subtitle }}
                    </p>

                    <!-- Subtle bottom active indicator line -->
                    <div
                        v-if="selectedKpiIdx === idx"
                        class="absolute inset-x-3 -bottom-px h-0.5 rounded-full"
                        :class="card.indicatorColor"
                    />
                </div>
            </div>
        </section>

        <!-- ── 3. BẢNG PHÂN TÍCH CHI TIẾT THEO KPI (DEEP-DIVE CARD) ─────── -->
        <Card class="overflow-hidden border-border/80 bg-gradient-to-b from-card to-card/90 shadow-xs">
            <CardHeader class="border-b border-border/60 bg-muted/20 py-3.5">
                <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                    <div class="flex items-center gap-2.5">
                        <span
                            class="inline-flex items-center gap-1 rounded-md border border-primary/25 bg-primary/10 px-2 py-0.5 text-xs font-bold text-primary"
                        >
                            <Sparkles class="size-3 text-primary" />
                            Phân tích chuyên sâu
                        </span>
                        <CardTitle class="text-base font-bold text-foreground">
                            {{ activeKpi.step }}
                        </CardTitle>
                    </div>
                    <span class="text-xs text-muted-foreground font-medium">
                        {{ activeKpi.desc }}
                    </span>
                </div>
            </CardHeader>

            <CardContent class="grid gap-5 p-5 lg:grid-cols-12">
                <!-- 3 Cột số liệu bóc tách -->
                <div class="space-y-3.5 lg:col-span-7">
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div class="rounded-xl border border-border/70 bg-muted/30 p-3 shadow-inner">
                            <p class="text-[11px] font-medium text-muted-foreground">
                                {{ activeKpi.metric1_label }}
                            </p>
                            <p class="mt-1 text-base font-black text-foreground">
                                {{ activeKpi.metric1_value }}
                            </p>
                        </div>
                        <div class="rounded-xl border border-border/70 bg-muted/30 p-3 shadow-inner">
                            <p class="text-[11px] font-medium text-muted-foreground">
                                {{ activeKpi.metric2_label }}
                            </p>
                            <p class="mt-1 text-base font-black" :class="activeKpi.accentColor">
                                {{ activeKpi.metric2_value }}
                            </p>
                        </div>
                        <div class="rounded-xl border border-border/70 bg-muted/30 p-3 shadow-inner">
                            <p class="text-[11px] font-medium text-muted-foreground">
                                {{ activeKpi.metric3_label }}
                            </p>
                            <p class="mt-1 text-base font-black text-emerald-600 dark:text-emerald-400">
                                {{ activeKpi.metric3_value }}
                            </p>
                        </div>
                    </div>

                    <!-- Tags dữ liệu liên quan -->
                    <div class="flex flex-wrap items-center gap-2 pt-1">
                        <span
                            v-for="(tag, tIdx) in activeKpi.tables"
                            :key="tIdx"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-background px-2.5 py-1 text-xs font-medium text-muted-foreground shadow-xs"
                        >
                            <ChevronRight class="size-3 text-primary" />
                            {{ tag }}
                        </span>
                    </div>
                </div>

                <!-- Lời khuyên & Ghi chú từ AI -->
                <div class="lg:col-span-5">
                    <div class="flex h-full flex-col justify-between rounded-xl border border-primary/20 bg-gradient-to-br from-primary/5 via-primary/[0.02] to-transparent p-4 shadow-xs">
                        <div>
                            <div class="flex items-center gap-2 text-xs font-bold text-foreground">
                                <Lightbulb class="size-4 text-amber-500" />
                                Đánh giá & Khuyến nghị vận hành
                            </div>
                            <p class="mt-2.5 text-xs leading-relaxed text-muted-foreground font-medium">
                                {{ activeKpi.note }}
                            </p>
                        </div>
                        <div class="mt-4 flex items-center justify-between border-t border-border/60 pt-2.5 text-[11px]">
                            <span class="text-muted-foreground">Mô hình AI Kho Vận</span>
                            <a
                                :href="centralWarehouseRoutes.aiAdvisor.url()"
                                class="font-bold text-primary hover:underline inline-flex items-center gap-1"
                            >
                                Mở Trợ lý AI Kho →
                            </a>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Cảnh báo âm kho (Negative Stock Cases) -->
        <NegativeInventoryCases
            :cases="negativeStockCases"
            title="Âm nguyên liệu tại Kho Tổng"
        />

        <!-- ── 4. CẢNH BÁO CHUỖI CUNG ỨNG & ĐỐI SOÁT TỒN KHO ────────────── -->
        <section class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <!-- Cảnh báo chuỗi cung ứng -->
            <Card class="border-border/80 bg-card shadow-xs">
                <CardHeader class="border-b border-border/60 pb-3">
                    <div class="flex items-center justify-between">
                        <CardTitle class="flex items-center gap-2 text-base font-bold text-foreground">
                            <AlertTriangle class="size-4 text-amber-500" />
                            Cảnh báo chuỗi cung ứng
                        </CardTitle>
                        <span
                            class="rounded-full border border-amber-500/30 bg-amber-500/10 px-2.5 py-0.5 text-[11px] font-bold text-amber-700 dark:text-amber-300"
                        >
                            {{ supplyChainAlerts.critical ?? 0 }} khẩn cấp
                        </span>
                    </div>
                    <CardDescription class="text-xs text-muted-foreground">
                        Thiếu hàng, thiếu nhà cung cấp dự phòng hoặc PO trễ hạn.
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-2.5 p-4">
                    <div
                        v-if="!(supplyChainAlerts.items ?? []).length"
                        class="rounded-xl border border-dashed border-border p-6 text-center text-xs text-muted-foreground"
                    >
                        Chưa ghi nhận cảnh báo chuỗi cung ứng phát sinh.
                    </div>
                    <div
                        v-for="item in (supplyChainAlerts.items ?? []).slice(0, 4)"
                        :key="`${item.type}-${item.ingredient_id ?? item.purchase_order_id}`"
                        class="flex items-start justify-between gap-3 rounded-xl border border-border/70 bg-muted/20 p-3 transition hover:border-border"
                    >
                        <div>
                            <p class="text-xs font-bold text-foreground">
                                {{ item.ingredient_name || item.po_number || 'Cảnh báo kho' }}
                            </p>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                {{ item.message }}
                            </p>
                        </div>
                        <span
                            class="shrink-0 rounded-md px-1.5 py-0.5 text-[9px] font-extrabold uppercase border"
                            :class="item.severity === 'critical' ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/25' : 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/25'"
                        >
                            {{ item.severity }}
                        </span>
                    </div>
                </CardContent>
            </Card>

            <!-- Đối soát tồn kho -->
            <Card class="border-border/80 bg-card shadow-xs">
                <CardHeader class="border-b border-border/60 pb-3">
                    <div class="flex items-center justify-between">
                        <CardTitle class="flex items-center gap-2 text-base font-bold text-foreground">
                            <ShieldCheck class="size-4 text-primary" />
                            Đối soát tồn kho
                        </CardTitle>
                        <span
                            class="rounded-full border px-2.5 py-0.5 text-[11px] font-bold"
                            :class="
                                reconciliation.has_variance
                                    ? 'border-rose-500/30 bg-rose-500/10 text-rose-700 dark:text-rose-300'
                                    : 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                            "
                        >
                            {{ reconciliation.has_variance ? 'Cần xử lý' : 'Đã khớp' }}
                        </span>
                    </div>
                    <CardDescription class="text-xs text-muted-foreground">
                        So sánh số dư kho, lô và sổ giao dịch thực tế.
                    </CardDescription>
                </CardHeader>
                <CardContent class="p-4">
                    <div class="flex items-baseline justify-between border-b border-border/50 pb-3">
                        <div>
                            <p
                                class="text-2xl font-black tracking-tight"
                                :class="reconciliation.has_variance ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400'"
                            >
                                {{ reconciliation.review_count ?? 0 }}
                            </p>
                            <p class="text-xs text-muted-foreground font-medium">
                                mặt hàng cần rà soát số dư
                            </p>
                        </div>
                    </div>
                    <div
                        v-if="(reconciliation.items ?? []).length"
                        class="mt-3 space-y-2"
                    >
                        <div
                            v-for="item in reconciliation.items.slice(0, 3)"
                            :key="item.ingredient_id"
                            class="flex justify-between gap-3 rounded-lg bg-muted/30 px-3 py-2 text-xs"
                        >
                            <span class="truncate font-medium text-foreground">{{ item.ingredient_name }}</span>
                            <span class="font-bold text-rose-600 dark:text-rose-400">{{ formatQuantity(item.variance) }}</span>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </section>

        <!-- ── 5. AI ĐÁNH GIÁ & ƯU TIÊN HÀNH ĐỘNG ───────────────────────── -->
        <section class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            <!-- Left: Đánh giá Sức khỏe Kho Tổng -->
            <Card class="flex flex-col justify-between border-border/80 bg-gradient-to-b from-card to-card/90 shadow-xs lg:col-span-4">
                <CardHeader class="border-b border-border/60 pb-3">
                    <div class="flex items-center justify-between">
                        <CardTitle class="flex items-center gap-2 text-base font-bold text-foreground">
                            <BrainCircuit class="size-4 text-primary" />
                            Đánh giá sức khỏe Kho Tổng
                        </CardTitle>
                        <span
                            class="rounded-md border border-primary/20 bg-primary/10 px-2 py-0.5 text-[11px] font-bold text-primary"
                        >
                            {{ aiAssessment.label || 'Ổn định' }}
                        </span>
                    </div>
                </CardHeader>
                <CardContent class="space-y-4 p-4">
                    <div class="flex items-center justify-between rounded-xl border border-border/70 bg-muted/30 p-3 shadow-inner">
                        <div>
                            <p class="text-[11px] font-medium text-muted-foreground">Điểm số vận hành</p>
                            <p class="text-2xl font-black text-foreground">{{ displayAiScore }} <span class="text-xs font-semibold text-muted-foreground">/ 100</span></p>
                        </div>
                        <div class="text-right">
                            <p class="text-[11px] font-medium text-muted-foreground">Mức rủi ro</p>
                            <p class="text-sm font-bold" :class="summary.urgent_recommendations > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400'">
                                {{ summary.urgent_recommendations > 0 ? 'Cần bổ sung' : 'An toàn' }}
                            </p>
                        </div>
                    </div>

                    <p class="text-xs leading-relaxed text-muted-foreground font-medium">
                        {{ aiAssessment.summary || 'Kho Tổng đang vận hành ổn định. Đảm bảo theo dõi các mặt hàng có tần suất đặt cao để duy trì mức tồn tối thiểu.' }}
                    </p>

                    <a
                        :href="centralWarehouseRoutes.aiAdvisor.url()"
                        class="inline-flex h-9.5 w-full items-center justify-center gap-2 rounded-xl border border-primary/25 bg-primary/5 text-xs font-bold text-primary shadow-xs transition hover:bg-primary/10 active:scale-98"
                    >
                        <Sparkles class="size-3.5" />
                        Mở Trợ lý AI Kho Tổng
                        <ArrowRight class="size-3.5" />
                    </a>
                </CardContent>
            </Card>

            <!-- Right: Ưu tiên hành động từ AI -->
            <Card class="border-border/80 bg-card shadow-xs lg:col-span-8">
                <CardHeader class="border-b border-border/60 pb-3">
                    <div class="flex items-center justify-between">
                        <CardTitle class="flex items-center gap-2 text-base font-bold text-foreground">
                            <Zap class="size-4 text-amber-500" />
                            Ưu tiên hành động từ AI
                        </CardTitle>
                        <span class="text-xs text-muted-foreground font-medium">
                            {{ (aiAssessment.signals ?? []).length }} tín hiệu
                        </span>
                    </div>
                </CardHeader>
                <CardContent class="grid gap-3 p-4 sm:grid-cols-2">
                    <div
                        v-if="!(aiAssessment.signals ?? []).length"
                        class="rounded-xl border border-dashed border-border p-6 text-center text-xs text-muted-foreground sm:col-span-2"
                    >
                        Chưa phát hiện tín hiệu cần can thiệp khẩn cấp.
                    </div>

                    <div
                        v-for="signal in (aiAssessment.signals ?? []).slice(0, 4)"
                        :key="`${signal.metric}-${signal.title}`"
                        class="flex flex-col justify-between rounded-xl border border-border/70 bg-muted/20 p-3.5 transition hover:border-border hover:shadow-xs"
                    >
                        <div>
                            <div class="flex items-start justify-between gap-2 border-b border-border/50 pb-2">
                                <h3 class="text-xs font-bold text-foreground">
                                    {{ signal.title }}
                                </h3>
                                <span
                                    class="shrink-0 rounded-md px-1.5 py-0.5 text-[9px] font-bold uppercase border"
                                    :class="signal.severity === 'critical' ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/25' : 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/25'"
                                >
                                    {{ signal.severity }}
                                </span>
                            </div>

                            <p class="mt-2 text-xs leading-relaxed text-muted-foreground">
                                {{ signal.evidence }}
                            </p>

                            <p class="mt-2 text-xs font-semibold text-foreground">
                                💡 {{ signal.advice }}
                            </p>
                        </div>

                        <div class="mt-3 flex items-center justify-between border-t border-border/50 pt-2 text-xs">
                            <span class="text-[11px] text-muted-foreground">{{ signal.next_step }}</span>
                            <a
                                v-if="signal.action_url"
                                :href="signal.action_url"
                                class="font-bold text-primary hover:underline inline-flex items-center gap-1"
                            >
                                {{ signal.action_label || 'Chi tiết' }}
                                <ArrowRight class="size-3" />
                            </a>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </section>

        <!-- ── 6. BIỂU ĐỒ NHỊP NHU CẦU & SỨC KHỎE KHO ───────────────────── -->
        <section class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <!-- Nhu cầu 7 ngày gần nhất -->
            <Card class="border-border/80 bg-card shadow-xs">
                <CardHeader class="border-b border-border/60 pb-3">
                    <div class="flex items-center justify-between">
                        <CardTitle class="flex items-center gap-2 text-base font-bold text-foreground">
                            <BarChart3 class="size-4 text-primary" />
                            Nhịp nhu cầu 7 ngày gần nhất
                        </CardTitle>
                        <span class="text-xs text-muted-foreground font-semibold">
                            {{ formatQuantity(summary.last7_items) }} đv
                        </span>
                    </div>
                </CardHeader>
                <CardContent class="p-4 sm:p-5">
                    <div
                        v-if="daily.length"
                        class="flex h-44 items-end gap-2 border-b border-border px-1 pb-1 sm:gap-4"
                    >
                        <div
                            v-for="day in daily"
                            :key="day.date"
                            class="group flex h-full flex-1 flex-col items-center justify-end gap-2"
                        >
                            <div class="relative flex h-full w-full max-w-12 items-end justify-center">
                                <div
                                    class="w-full rounded-t-sm bg-gradient-to-t from-primary/70 to-primary transition-all group-hover:brightness-110 shadow-xs"
                                    :style="{ height: barHeight(day.items, maxDailyItems) }"
                                    :title="`${day.requests} đơn · ${formatQuantity(day.items)} đv`"
                                />
                            </div>
                            <span class="text-[10px] font-bold text-muted-foreground">{{ day.label }}</span>
                        </div>
                    </div>
                    <div
                        v-else
                        class="flex h-44 items-center justify-center text-xs text-muted-foreground"
                    >
                        Chưa đủ dữ liệu biểu đồ.
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-3 text-xs sm:grid-cols-4">
                        <div class="rounded-xl border border-border/60 bg-muted/20 p-2.5">
                            <p class="text-muted-foreground text-[11px]">Trung bình/ngày</p>
                            <p class="mt-0.5 font-bold text-foreground">{{ formatQuantity(summary.average_daily_requests) }} đơn</p>
                        </div>
                        <div class="rounded-xl border border-border/60 bg-muted/20 p-2.5">
                            <p class="text-muted-foreground text-[11px]">Giá trị yêu cầu</p>
                            <p class="mt-0.5 font-bold text-foreground">{{ formatCurrency(summary.last7_value) }}</p>
                        </div>
                        <div class="rounded-xl border border-border/60 bg-muted/20 p-2.5">
                            <p class="text-muted-foreground text-[11px]">Đang tiếp nhận</p>
                            <p class="mt-0.5 font-bold text-foreground">{{ summary.receiving_requests ?? 0 }} đơn</p>
                        </div>
                        <div class="rounded-xl border border-border/60 bg-muted/20 p-2.5">
                            <p class="text-muted-foreground text-[11px]">Tranh chấp mở</p>
                            <p class="mt-0.5 font-bold text-foreground">{{ summary.disputed_requests ?? 0 }} đơn</p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Sức khỏe Kho Tổng Health Metrics -->
            <Card class="border-border/80 bg-card shadow-xs">
                <CardHeader class="border-b border-border/60 pb-3">
                    <CardTitle class="flex items-center gap-2 text-base font-bold text-foreground">
                        <ShieldCheck class="size-4 text-emerald-600 dark:text-emerald-400" />
                        Chất lượng phục vụ & Kho vận
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-4 p-4 sm:p-5">
                    <div
                        v-for="metric in healthMetrics"
                        :key="metric.label"
                        class="space-y-1.5"
                    >
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-bold text-foreground">{{ metric.label }}</span>
                            <span :class="metricStatusClass(metric)">
                                {{ metric.value }}{{ metric.suffix }} · {{ metricStatus(metric) }}
                            </span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-muted">
                            <div
                                class="h-full rounded-full bg-gradient-to-r transition-all"
                                :class="metric.barColor"
                                :style="{ width: `${Math.min(100, Math.max(5, metric.value))}%` }"
                            />
                        </div>
                        <p class="text-[11px] text-muted-foreground">
                            {{ metric.description }}
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-3 border-t border-border/60 pt-3 text-xs">
                        <div class="rounded-xl border border-border/60 bg-muted/20 p-2.5">
                            <p class="text-muted-foreground text-[11px]">GRN cần xác minh</p>
                            <p class="mt-0.5 text-base font-bold text-amber-600 dark:text-amber-400">{{ receiving.pending_review ?? 0 }}</p>
                        </div>
                        <div class="rounded-xl border border-border/60 bg-muted/20 p-2.5">
                            <p class="text-muted-foreground text-[11px]">Lô sắp hết hạn</p>
                            <p class="mt-0.5 text-base font-bold text-amber-600 dark:text-amber-400">{{ inventory.expiring_soon_count ?? 0 }}</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </section>

        <!-- ── 7. NGUYÊN LIỆU NHU CẦU CAO & PHÂN BỔ CHI NHÁNH ─────────── -->
        <section class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <!-- Đánh giá & lời khuyên -->
            <Card class="border-border/80 bg-card shadow-xs">
                <CardHeader class="border-b border-border/60 pb-3">
                    <CardTitle class="flex items-center gap-2 text-base font-bold text-foreground">
                        <Lightbulb class="size-4 text-amber-500" />
                        Tín hiệu vận hành đáng chú ý
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-2.5 p-4">
                    <div
                        v-if="insights.length === 0"
                        class="rounded-xl border border-dashed border-border p-6 text-center text-xs text-muted-foreground"
                    >
                        Chưa có tín hiệu bất thường.
                    </div>
                    <div
                        v-for="insight in insights"
                        :key="`${insight.type}-${insight.title}`"
                        class="rounded-xl border border-border/70 bg-muted/20 p-3"
                    >
                        <p class="text-xs font-bold text-foreground">
                            {{ insight.title }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground leading-relaxed">
                            {{ insight.message }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <!-- Top nguyên liệu có nhu cầu cao -->
            <Card class="border-border/80 bg-card shadow-xs">
                <CardHeader class="border-b border-border/60 pb-3">
                    <div class="flex items-center justify-between">
                        <CardTitle class="flex items-center gap-2 text-base font-bold text-foreground">
                            <PackageSearch class="size-4 text-primary" />
                            Nguyên liệu có nhu cầu cao (28 ngày)
                        </CardTitle>
                        <TrendingUp class="size-4 text-muted-foreground" />
                    </div>
                </CardHeader>
                <CardContent class="space-y-2.5 p-4">
                    <div
                        v-if="topIngredients.length === 0"
                        class="rounded-xl border border-dashed border-border p-6 text-center text-xs text-muted-foreground"
                    >
                        Chưa đủ dữ liệu phân tích.
                    </div>
                    <div
                        v-for="(item, index) in topIngredients"
                        :key="item.ingredient_id"
                        class="flex items-center gap-3 rounded-xl border border-border/60 bg-muted/20 p-2.5"
                    >
                        <span
                            class="flex size-6 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-xs font-bold text-primary"
                        >
                            {{ Number(index) + 1 }}
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2 text-xs">
                                <p class="truncate font-bold text-foreground">
                                    {{ item.name }}
                                </p>
                                <p class="shrink-0 font-bold text-foreground">
                                    {{ formatQuantity(item.total_quantity) }} {{ item.unit_symbol }}
                                </p>
                            </div>
                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-muted">
                                <div
                                    class="h-full rounded-full bg-gradient-to-r from-primary to-indigo-400"
                                    :style="{ width: barHeight(Number(item.total_quantity), maxIngredientQuantity) }"
                                />
                            </div>
                            <p class="mt-1 text-[11px] text-muted-foreground font-medium">
                                {{ item.request_count }} đơn · {{ formatCurrency(item.total_value) }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </section>

        <!-- ── 8. BÁO CÁO PHÂN BỔ THEO CHI NHÁNH ───────────────────────── -->
        <Card class="border-border/80 bg-card shadow-xs">
            <CardHeader class="border-b border-border/60 pb-3">
                <div class="flex flex-col justify-between gap-1 sm:flex-row sm:items-center">
                    <CardTitle class="flex items-center gap-2 text-base font-bold text-foreground">
                        <Building2 class="size-4 text-primary" />
                        Báo cáo phân bổ theo chi nhánh
                    </CardTitle>
                    <span class="text-xs text-muted-foreground font-semibold">
                        {{ branches.length }} chi nhánh đang hoạt động
                    </span>
                </div>
            </CardHeader>
            <CardContent class="p-0">
                <div
                    v-if="branchReport.length === 0"
                    class="p-6 text-center text-xs text-muted-foreground"
                >
                    Chưa có dữ liệu yêu cầu theo chi nhánh.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[650px] text-left text-xs">
                        <thead class="border-b border-border bg-muted/40 text-muted-foreground">
                            <tr>
                                <th class="p-3.5 pl-5 font-bold">Chi nhánh</th>
                                <th class="p-3.5 font-bold">Tỷ trọng</th>
                                <th class="p-3.5 text-right font-bold">Số đơn</th>
                                <th class="p-3.5 text-right font-bold">Khối lượng</th>
                                <th class="p-3.5 text-right font-bold">Giá trị hàng</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/60">
                            <tr
                                v-for="branch in branchReport"
                                :key="branch.id"
                                class="transition hover:bg-muted/30"
                            >
                                <td class="p-3.5 pl-5 font-bold text-foreground">
                                    {{ branch.name }}
                                </td>
                                <td class="w-[30%] p-3.5">
                                    <div class="flex items-center gap-2.5">
                                        <div class="h-2 flex-1 overflow-hidden rounded-full bg-muted">
                                            <div
                                                class="h-full rounded-full bg-sky-500"
                                                :style="{ width: barHeight(Number(branch.requests), maxBranchRequests) }"
                                            />
                                        </div>
                                        <span class="w-10 text-right font-bold text-sky-600 dark:text-sky-400">
                                            {{ formatPercent(branch.share) }}
                                        </span>
                                    </div>
                                </td>
                                <td class="p-3.5 text-right font-bold text-foreground">
                                    {{ branch.requests }}
                                </td>
                                <td class="p-3.5 text-right text-muted-foreground font-medium">
                                    {{ formatQuantity(branch.items) }}
                                </td>
                                <td class="p-3.5 text-right font-bold text-emerald-600 dark:text-emerald-400">
                                    {{ formatCurrency(branch.value) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <!-- ── 9. KHUYẾN NGHỊ TỒN KHO 7 NGÀY TỚI ──────────────────────── -->
        <Card class="border-border/80 bg-card shadow-xs">
            <CardHeader class="border-b border-border/60 pb-3">
                <div class="flex flex-col justify-between gap-1 sm:flex-row sm:items-center">
                    <CardTitle class="flex items-center gap-2 text-base font-bold text-foreground">
                        <ShieldAlert class="size-4 text-rose-600 dark:text-rose-400" />
                        Khuyến nghị tồn kho 7 ngày tới
                    </CardTitle>
                    <span class="text-xs text-muted-foreground font-medium">
                        Dự báo từ tốc độ tiêu thụ 28 ngày và đơn đang mở
                    </span>
                </div>
            </CardHeader>
            <CardContent class="p-0">
                <div
                    v-if="recommendations.length === 0"
                    class="p-6 text-center text-xs text-muted-foreground"
                >
                    Tồn kho hiện đáp ứng tốt nhu cầu dự kiến của toàn chuỗi.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full min-w-[850px] text-left text-xs">
                        <thead class="border-b border-border bg-muted/40 text-muted-foreground">
                            <tr>
                                <th class="p-3.5 pl-5 font-bold">Nguyên liệu</th>
                                <th class="p-3.5 text-right font-bold">Tồn hiện tại</th>
                                <th class="p-3.5 text-right font-bold">Đơn đang mở</th>
                                <th class="p-3.5 text-right font-bold">Dự báo 7 ngày</th>
                                <th class="p-3.5 text-right font-bold">Nên nhập thêm</th>
                                <th class="p-3.5 font-bold">Đánh giá</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/60">
                            <tr
                                v-for="item in recommendations"
                                :key="item.ingredient_id"
                                class="transition hover:bg-muted/20"
                            >
                                <td class="p-3.5 pl-5">
                                    <p class="font-bold text-foreground">
                                        {{ item.name }}
                                    </p>
                                    <p class="mt-0.5 text-[11px] text-muted-foreground font-medium">
                                        {{ item.sku || 'Chưa có SKU' }} ·
                                        {{ item.coverage_days === null ? 'Chưa đủ lịch sử' : `Đủ khoảng ${item.coverage_days} ngày` }}
                                    </p>
                                </td>
                                <td class="p-3.5 text-right font-bold text-foreground">
                                    {{ formatQuantity(item.current_stock) }} {{ item.unit_symbol }}
                                </td>
                                <td class="p-3.5 text-right text-amber-600 dark:text-amber-400 font-bold">
                                    {{ formatQuantity(item.open_quantity) }} {{ item.unit_symbol }}
                                </td>
                                <td class="p-3.5 text-right text-indigo-600 dark:text-indigo-400 font-bold">
                                    {{ formatQuantity(item.forecast_7d) }} {{ item.unit_symbol }}
                                </td>
                                <td class="p-3.5 text-right">
                                    <p class="font-black text-rose-600 dark:text-rose-400">
                                        {{ formatQuantity(item.recommended_quantity) }} {{ item.unit_symbol }}
                                    </p>
                                    <p class="text-[11px] text-muted-foreground font-medium">
                                        {{ formatCurrency(item.estimated_cost) }}
                                    </p>
                                </td>
                                <td class="max-w-[260px] p-3.5">
                                    <span
                                        class="inline-flex rounded-md border px-2 py-0.5 text-[10px] font-bold"
                                        :class="priorityBadge(item.priority).class"
                                    >
                                        {{ priorityBadge(item.priority).label }}
                                    </span>
                                    <p class="mt-1 text-[11px] text-muted-foreground leading-relaxed">
                                        {{ item.advice }}
                                    </p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>

        <!-- ── 10. THỐNG KÊ PHỤ ────────────────────────────────────────── -->
        <section class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-xl border border-border/80 bg-card p-3.5 shadow-xs">
                <p class="text-[11px] font-bold text-muted-foreground uppercase">Vị trí kệ hoạt động</p>
                <p class="mt-1 text-xl font-black text-foreground">{{ inventory.location_count ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-amber-500/20 bg-amber-500/[0.03] p-3.5 shadow-xs">
                <p class="text-[11px] font-bold text-amber-700 dark:text-amber-400 uppercase">Tồn dưới ngưỡng</p>
                <p class="mt-1 text-xl font-black text-amber-600 dark:text-amber-400">{{ inventory.low_stock_count ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-rose-500/20 bg-rose-500/[0.03] p-3.5 shadow-xs">
                <p class="text-[11px] font-bold text-rose-700 dark:text-rose-400 uppercase">Lô bị khóa / thu hồi</p>
                <p class="mt-1 text-xl font-black text-rose-600 dark:text-rose-400">{{ inventory.locked_batch_count ?? 0 }}</p>
            </div>
            <div class="rounded-xl border border-border/80 bg-card p-3.5 shadow-xs">
                <p class="text-[11px] font-bold text-muted-foreground uppercase">Chênh lệch tiếp nhận</p>
                <p class="mt-1 text-xl font-black text-foreground">{{ formatQuantity(receiving.discrepancy_quantity) }}</p>
            </div>
        </section>

        <p class="text-center text-xs text-muted-foreground font-medium">
            Báo cáo mang tính tham khảo quản trị; các khuyến nghị từ AI cần được xác nhận trước khi ra quyết định đặt hàng.
        </p>
    </DashboardShell>
</template>
