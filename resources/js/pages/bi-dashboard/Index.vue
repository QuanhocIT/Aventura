<script setup lang="ts">
import { Head, Deferred, router } from '@inertiajs/vue3';
import {
    Activity,
    ArrowDownRight,
    ArrowUpRight,
    Banknote,
    BarChart3,
    CalendarDays,
    CheckCircle2,
    ChevronRight,
    CircleAlert,
    ClipboardCheck,
    Database,
    Gauge,
    Info,
    Lightbulb,
    RefreshCw,
    Scale,
    ShoppingCart,
    Target,
    TrendingUp,
    Users,
    Warehouse,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type RevenuePoint = {
    month: string;
    revenue: number;
    orders: number;
    avg_order: number;
    yoy_growth: number | null;
};

type UnitEconomics = {
    revenue: number;
    cogs: number;
    waste_cost: number;
    gross_margin: number;
    new_customers: number;
    total_customers: number;
    avg_order_value: number;
    ltv: number;
    cac: number;
    ltv_cac_ratio: number;
    avg_orders_per_customer: number;
    order_count?: number;
    cost_basis?: 'actual_consumption' | 'purchases_fallback';
    marketing_cost?: number;
    cac_basis?: 'marketing_expense' | 'not_recorded';
};

type Cohort = {
    cohort: string;
    size: number;
    retention: { month: number; returning: number; rate: number }[];
};

type BreakEven = {
    revenue: number;
    variable_cost: number;
    fixed_cost: number;
    total_orders: number;
    avg_order_value: number;
    variable_cost_per_order: number;
    contribution_margin: number;
    break_even_orders: number;
    break_even_revenue: number;
    break_even_days: number;
    is_profitable: boolean;
    cost_basis?: 'actual_consumption' | 'purchases_fallback';
};

type Benchmark = {
    metric: string;
    value: number;
    unit: string;
    industry_low: number;
    industry_high: number;
    status: string;
    good_direction?: 'higher' | 'lower';
};

const props = withDefaults(
    defineProps<{
        revenueTrend?: RevenuePoint[];
        unitEconomics?: UnitEconomics;
        cohorts?: Cohort[];
        breakEven?: BreakEven;
        benchmarks?: Benchmark[];
        days?: number;
        branchContext?: { scope: string; active_branch_id: number | null };
    }>(),
    {
        revenueTrend: () => [],
        unitEconomics: () => ({
            revenue: 0,
            cogs: 0,
            waste_cost: 0,
            gross_margin: 0,
            new_customers: 0,
            total_customers: 0,
            avg_order_value: 0,
            ltv: 0,
            cac: 0,
            ltv_cac_ratio: 0,
            avg_orders_per_customer: 0,
            order_count: 0,
            marketing_cost: 0,
            cac_basis: 'not_recorded',
        }),
        cohorts: () => [],
        breakEven: () => ({
            revenue: 0,
            variable_cost: 0,
            fixed_cost: 0,
            total_orders: 0,
            avg_order_value: 0,
            variable_cost_per_order: 0,
            contribution_margin: 0,
            break_even_orders: 0,
            break_even_revenue: 0,
            break_even_days: 0,
            is_profitable: false,
        }),
        benchmarks: () => [],
        days: 30,
    },
);

const activeTab = ref<'overview' | 'cohort' | 'breakeven' | 'benchmark'>(
    'overview',
);
const selectedDays = ref(props.days ?? 30);
const isRefreshing = ref(false);
const periodOptions = [
    { value: 7, label: '7 ngày' },
    { value: 30, label: '30 ngày' },
    { value: 90, label: '90 ngày' },
    { value: 365, label: '365 ngày' },
];
const cohortMonths = [0, 1, 2, 3, 4, 5];

watch(
    () => props.days,
    (value) => {
        if (value) {
            selectedDays.value = value;
        }
    },
);

const ue = computed(() => props.unitEconomics);
const be = computed(() => props.breakEven);
const hasData = computed(
    () =>
        ue.value.revenue > 0 ||
        be.value.total_orders > 0 ||
        props.revenueTrend.length > 0,
);

const periodLabel = computed(
    () =>
        periodOptions.find((option) => option.value === selectedDays.value)
            ?.label ?? `${selectedDays.value} ngày`,
);

function formatNumber(
    value: number | null | undefined,
    fractionDigits = 0,
): string {
    const number = Number(value ?? 0);

    if (!Number.isFinite(number)) {
        return '0';
    }

    return number.toLocaleString('vi-VN', {
        maximumFractionDigits: fractionDigits,
        minimumFractionDigits: fractionDigits,
    });
}

function formatMoney(value: number | null | undefined): string {
    return `${formatNumber(value)} ₫`;
}

function compactMoney(value: number | null | undefined): string {
    const number = Number(value ?? 0);

    if (!Number.isFinite(number)) {
        return '0 ₫';
    }

    if (Math.abs(number) >= 1_000_000_000) {
        return `${formatNumber(number / 1_000_000_000, 1)} tỷ`;
    }

    if (Math.abs(number) >= 1_000_000) {
        return `${formatNumber(number / 1_000_000, 1)} tr`;
    }

    if (Math.abs(number) >= 1_000) {
        return `${formatNumber(number / 1_000, 0)}k`;
    }

    return formatMoney(number);
}

function monthLabel(value: string): string {
    const [year, month] = value.split('-');

    return year && month ? `T${Number(month)}/${year.slice(2)}` : value;
}

function applyPeriod(days: number): void {
    if (days === selectedDays.value) {
        return;
    }

    selectedDays.value = days;
    router.get(
        '/bi-dashboard',
        { days },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function refreshDashboard(): void {
    if (isRefreshing.value) {
        return;
    }

    isRefreshing.value = true;
    router.reload({
        only: [
            'revenueTrend',
            'unitEconomics',
            'cohorts',
            'breakEven',
            'benchmarks',
        ],
        preserveScroll: true,
        onFinish: () => {
            isRefreshing.value = false;
            toast.success('Đã cập nhật số liệu BI.');
        },
    });
}

const latestRevenue = computed(() => props.revenueTrend.at(-1));
const previousRevenue = computed(() => props.revenueTrend.at(-2));
const revenueGrowth = computed(() => {
    if (
        !latestRevenue.value ||
        !previousRevenue.value ||
        previousRevenue.value.revenue <= 0
    ) {
        return null;
    }

    return (
        ((latestRevenue.value.revenue - previousRevenue.value.revenue) /
            previousRevenue.value.revenue) *
        100
    );
});
const revenueMax = computed(() =>
    Math.max(...props.revenueTrend.map((point) => point.revenue), 1),
);

const grossProfit = computed(() =>
    Math.max(0, ue.value.revenue - ue.value.cogs),
);
const foodCostRatio = computed(() =>
    ue.value.revenue > 0 ? (ue.value.cogs / ue.value.revenue) * 100 : 0,
);
const wasteRatio = computed(() =>
    ue.value.revenue > 0 ? (ue.value.waste_cost / ue.value.revenue) * 100 : 0,
);
const breakEvenGap = computed(() =>
    Math.max(0, be.value.break_even_orders - be.value.total_orders),
);
const breakEvenProgress = computed(() => {
    if (be.value.break_even_orders <= 0) {
        return 0;
    }

    return Math.min(
        100,
        (be.value.total_orders / be.value.break_even_orders) * 100,
    );
});

const kpis = computed(() => [
    {
        label: 'Doanh thu thuần',
        value: compactMoney(ue.value.revenue),
        detail: `${periodLabel.value} • đơn hoàn tất`,
        icon: Banknote,
        tone: 'orange',
    },
    {
        label: 'Đơn hoàn tất',
        value: formatNumber(be.value.total_orders),
        detail: `AOV ${compactMoney(ue.value.avg_order_value)}`,
        icon: ShoppingCart,
        tone: 'blue',
    },
    {
        label: 'Biên lợi nhuận gộp',
        value: `${formatNumber(ue.value.gross_margin, 1)}%`,
        detail: `Giá vốn ${formatNumber(foodCostRatio.value, 1)}% doanh thu`,
        icon: Scale,
        tone: ue.value.gross_margin >= 55 ? 'green' : 'red',
    },
    {
        label: 'Khách mới',
        value: formatNumber(ue.value.new_customers),
        detail: `Tổng tệp ${formatNumber(ue.value.total_customers)}`,
        icon: Users,
        tone: 'violet',
    },
    {
        label: 'LTV / CAC',
        value:
            ue.value.cac > 0
                ? `${formatNumber(ue.value.ltv_cac_ratio, 2)}x`
                : '—',
        detail:
            ue.value.cac > 0
                ? `LTV ${compactMoney(ue.value.ltv)} • CAC ${compactMoney(ue.value.cac)}`
                : 'Chưa ghi nhận chi phí marketing',
        icon: Target,
        tone: ue.value.ltv_cac_ratio >= 2 ? 'green' : 'amber',
    },
    {
        label: 'Hao hụt',
        value: `${formatNumber(wasteRatio.value, 1)}%`,
        detail: `${formatMoney(ue.value.waste_cost)} trong kỳ`,
        icon: Warehouse,
        tone: wasteRatio.value <= 5 ? 'green' : 'red',
    },
]);

const economicsBars = computed(() => [
    {
        label: 'Doanh thu',
        value: ue.value.revenue,
        percent: 100,
        tone: 'bg-orange-500',
    },
    {
        label: 'Giá vốn tiêu hao',
        value: ue.value.cogs,
        percent: Math.min(100, foodCostRatio.value),
        tone: 'bg-blue-500',
    },
    {
        label: 'Hao hụt / hủy',
        value: ue.value.waste_cost,
        percent: Math.min(100, wasteRatio.value),
        tone: 'bg-amber-500',
    },
    {
        label: 'Lợi nhuận gộp',
        value: grossProfit.value,
        percent: Math.min(100, Math.max(0, ue.value.gross_margin)),
        tone: 'bg-emerald-500',
    },
]);

type Insight = {
    title: string;
    detail: string;
    tone: 'warning' | 'danger' | 'success' | 'info';
    action: string;
};

const insights = computed<Insight[]>(() => {
    const items: Insight[] = [];

    if (!hasData.value) {
        return [
            {
                title: 'Chưa đủ dữ liệu để kết luận',
                detail: 'Hãy hoàn tất đơn hàng và ghi nhận nhập – xuất kho trong kỳ để BI phản ánh đúng hoạt động.',
                tone: 'info',
                action: 'Kiểm tra dữ liệu nguồn',
            },
        ];
    }

    if (be.value.break_even_orders > 0 && !be.value.is_profitable) {
        items.push({
            title: `Còn thiếu ${formatNumber(breakEvenGap.value)} đơn để hòa vốn`,
            detail: `Mục tiêu ${formatNumber(be.value.break_even_orders)} đơn / kỳ • cần thêm ${formatMoney(Math.max(0, be.value.break_even_revenue - be.value.revenue))}`,
            tone: 'danger',
            action: 'Xem điểm hòa vốn',
        });
    }

    if (ue.value.gross_margin > 0 && ue.value.gross_margin < 55) {
        items.push({
            title: 'Biên gộp thấp hơn ngưỡng vận hành',
            detail: `Biên hiện tại ${formatNumber(ue.value.gross_margin, 1)}%; cần rà soát giá bán, định lượng và giá vốn món chủ lực.`,
            tone: 'warning',
            action: 'Rà soát biên gộp',
        });
    }

    if (wasteRatio.value > 5) {
        items.push({
            title: 'Hao hụt đang ăn vào lợi nhuận',
            detail: `Hao hụt ${formatNumber(wasteRatio.value, 1)}% doanh thu, cao hơn ngưỡng kiểm soát 5%.`,
            tone: 'warning',
            action: 'Mở quản lý hao hụt',
        });
    }

    if (ue.value.ltv_cac_ratio > 0 && ue.value.ltv_cac_ratio < 2) {
        items.push({
            title: 'Chi phí thu hút khách chưa hiệu quả',
            detail: `LTV/CAC đạt ${formatNumber(ue.value.ltv_cac_ratio, 2)}x; nên tối ưu CAC hoặc tăng tần suất quay lại.`,
            tone: 'warning',
            action: 'Xem Cohort',
        });
    }

    if (ue.value.new_customers > 0 && ue.value.cac <= 0) {
        items.push({
            title: 'Chưa ghi nhận chi phí Marketing',
            detail: `Có ${formatNumber(ue.value.new_customers)} khách mới nhưng chưa có chi phí Marketing & Quảng cáo trong kỳ để tính CAC thực tế.`,
            tone: 'info',
            action: 'Cập nhật chi phí',
        });
    }

    if (items.length === 0) {
        items.push({
            title: 'Các chỉ số chính đang trong vùng kiểm soát',
            detail: 'Chưa phát hiện tín hiệu vượt ngưỡng trong kỳ đang xem. Tiếp tục theo dõi xu hướng và điểm hòa vốn.',
            tone: 'success',
            action: 'Theo dõi xu hướng',
        });
    }

    return items.slice(0, 3);
});

const insightToneClasses: Record<
    Insight['tone'],
    { box: string; icon: string; text: string }
> = {
    danger: {
        box: 'border-rose-500/20 bg-rose-500/[0.06]',
        icon: 'bg-rose-500/10 text-rose-500',
        text: 'text-rose-600 dark:text-rose-300',
    },
    warning: {
        box: 'border-amber-500/20 bg-amber-500/[0.06]',
        icon: 'bg-amber-500/10 text-amber-500',
        text: 'text-amber-700 dark:text-amber-300',
    },
    success: {
        box: 'border-emerald-500/20 bg-emerald-500/[0.06]',
        icon: 'bg-emerald-500/10 text-emerald-500',
        text: 'text-emerald-700 dark:text-emerald-300',
    },
    info: {
        box: 'border-sky-500/20 bg-sky-500/[0.06]',
        icon: 'bg-sky-500/10 text-sky-500',
        text: 'text-sky-700 dark:text-sky-300',
    },
};

function focusInsight(insight: Insight): void {
    if (insight.action.includes('Cohort')) {
        activeTab.value = 'cohort';
    } else if (insight.action.includes('hòa vốn')) {
        activeTab.value = 'breakeven';
    } else if (insight.action.includes('biên')) {
        activeTab.value = 'benchmark';
    } else if (insight.action.includes('hao hụt')) {
        window.location.href = '/waste-management';
    } else if (insight.action.includes('chi phí')) {
        window.location.href = '/expenses';
    } else {
        activeTab.value = 'overview';
    }
}

// What-if simulator: lượng hóa tác động của AOV, tần suất quay lại và CAC.
const simAov = ref(0);
const simFrequency = ref(0);
const simCac = ref(0);
const isSimulating = ref(false);

watch(
    () => props.unitEconomics,
    (value) => {
        if (value && !isSimulating.value) {
            simAov.value = Math.round(value.avg_order_value || 0);
            simFrequency.value = Number(
                (value.avg_orders_per_customer || 0).toFixed(1),
            );
            simCac.value = Math.round(value.cac || 0);
        }
    },
    { immediate: true },
);

const simLtv = computed(() =>
    Math.round(
        simAov.value * simFrequency.value * (ue.value.gross_margin / 100),
    ),
);
const simRatio = computed(() =>
    simCac.value > 0 ? Number((simLtv.value / simCac.value).toFixed(2)) : 0,
);
const simRatioDiff = computed(() => simRatio.value - ue.value.ltv_cac_ratio);

function resetSimulation(): void {
    isSimulating.value = false;
    simAov.value = Math.round(ue.value.avg_order_value || 0);
    simFrequency.value = Number(
        (ue.value.avg_orders_per_customer || 0).toFixed(1),
    );
    simCac.value = Math.round(ue.value.cac || 0);
    toast.success('Đã đặt lại bộ mô phỏng.');
}

// Cohort retention
function retentionValue(cohort: Cohort, month: number) {
    return cohort.retention.find((item) => item.month === month) ?? null;
}

const cohortAverages = computed(() =>
    cohortMonths.map((month) => {
        const values = props.cohorts
            .map((cohort) => retentionValue(cohort, month)?.rate)
            .filter((value): value is number => value !== undefined);

        return values.length
            ? values.reduce((sum, value) => sum + value, 0) / values.length
            : null;
    }),
);
const latestCohort = computed(() => props.cohorts.at(-1));
const cohortM1 = computed(() => cohortAverages.value[1] ?? 0);

function retentionClass(rate: number | null): string {
    if (rate === null) {
        return 'bg-muted/40 text-muted-foreground';
    }

    if (rate >= 60) {
        return 'bg-emerald-500/20 text-emerald-700 dark:text-emerald-300';
    }

    if (rate >= 35) {
        return 'bg-sky-500/20 text-sky-700 dark:text-sky-300';
    }

    if (rate >= 15) {
        return 'bg-amber-500/20 text-amber-700 dark:text-amber-300';
    }

    return 'bg-rose-500/15 text-rose-700 dark:text-rose-300';
}

// Break-even chart coordinates
const chartMaxOrders = computed(() =>
    Math.max(
        be.value.break_even_orders * 1.35,
        be.value.total_orders * 1.2,
        10,
    ),
);
const chartMaxMoney = computed(() =>
    Math.max(
        be.value.avg_order_value * chartMaxOrders.value,
        be.value.fixed_cost +
            be.value.variable_cost_per_order * chartMaxOrders.value,
        1_000_000,
    ),
);

function chartPoint(orders: number, amount: number): { x: number; y: number } {
    return {
        x: 64 + (Math.max(0, orders) / chartMaxOrders.value) * 616,
        y: 250 - (Math.max(0, amount) / chartMaxMoney.value) * 210,
    };
}

const fixedStart = computed(() => chartPoint(0, be.value.fixed_cost));
const fixedEnd = computed(() =>
    chartPoint(chartMaxOrders.value, be.value.fixed_cost),
);
const costEnd = computed(() =>
    chartPoint(
        chartMaxOrders.value,
        be.value.fixed_cost +
            be.value.variable_cost_per_order * chartMaxOrders.value,
    ),
);
const revenueEnd = computed(() =>
    chartPoint(
        chartMaxOrders.value,
        be.value.avg_order_value * chartMaxOrders.value,
    ),
);
const breakEvenPoint = computed(() =>
    chartPoint(be.value.break_even_orders, be.value.break_even_revenue),
);
const actualPoint = computed(() =>
    chartPoint(be.value.total_orders, be.value.revenue),
);

// Industry benchmarks
function benchmarkValue(benchmark: Benchmark): string {
    if (benchmark.unit === 'đ') {
        return compactMoney(benchmark.value);
    }

    return `${formatNumber(benchmark.value, benchmark.unit === 'x' ? 2 : 1)}${benchmark.unit}`;
}

function benchmarkRange(benchmark: Benchmark): string {
    if (benchmark.unit === 'đ') {
        return `${compactMoney(benchmark.industry_low)} – ${compactMoney(benchmark.industry_high)}`;
    }

    return `${formatNumber(benchmark.industry_low, 1)}${benchmark.unit} – ${formatNumber(benchmark.industry_high, 1)}${benchmark.unit}`;
}

function benchmarkPosition(benchmark: Benchmark): {
    low: number;
    high: number;
    value: number;
} {
    const min = Math.min(benchmark.industry_low * 0.5, benchmark.value * 0.7);
    const max = Math.max(
        benchmark.industry_high * 1.5,
        benchmark.value * 1.3,
        min + 1,
    );
    const scale = max - min;

    return {
        low: ((benchmark.industry_low - min) / scale) * 100,
        high: ((benchmark.industry_high - min) / scale) * 100,
        value: Math.min(
            100,
            Math.max(0, ((benchmark.value - min) / scale) * 100),
        ),
    };
}

const statusLabels: Record<string, string> = {
    excellent: 'Tốt',
    normal: 'Trong vùng',
    warning: 'Cần xử lý',
};
const statusClasses: Record<string, string> = {
    excellent:
        'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    normal: 'border-sky-500/20 bg-sky-500/10 text-sky-700 dark:text-sky-300',
    warning:
        'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:text-amber-300',
};
</script>

<template>
    <Head title="BI — Điều hành kinh doanh" />

    <div class="dashboard-shell mx-auto w-full max-w-[1600px] p-4 lg:p-7">
        <header
            class="overflow-hidden rounded-[1.75rem] border border-slate-200/80 bg-white shadow-sm dark:border-white/[0.08] dark:bg-slate-950/70"
        >
            <div class="relative px-5 py-6 lg:px-7">
                <div
                    class="absolute -top-28 -right-16 h-72 w-72 rounded-full bg-orange-500/[0.08] blur-3xl"
                />
                <div
                    class="relative flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between"
                >
                    <div class="flex items-start gap-4">
                        <div
                            class="flex size-14 shrink-0 items-center justify-center rounded-2xl bg-orange-500 text-white shadow-lg shadow-orange-500/20"
                        >
                            <BarChart3 class="size-7" />
                        </div>
                        <div>
                            <div
                                class="mb-1 flex items-center gap-2 text-[11px] font-bold tracking-[0.18em] text-orange-600 uppercase dark:text-orange-400"
                            >
                                <Activity class="size-3.5" /> BI / Điều hành
                                kinh doanh
                            </div>
                            <h1
                                class="text-2xl font-semibold tracking-tight text-slate-950 lg:text-3xl dark:text-white"
                            >
                                Từ dữ liệu đến quyết định
                            </h1>
                            <p
                                class="mt-2 max-w-2xl text-sm leading-6 text-slate-500 dark:text-slate-400"
                            >
                                Một màn hình để nắm sức khỏe chuỗi, phát hiện
                                điểm nghẽn và ưu tiên hành động trong ngày.
                            </p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="isRefreshing"
                            @click="refreshDashboard"
                            ><RefreshCw
                                class="size-4"
                                :class="isRefreshing && 'animate-spin'"
                            />
                            Làm mới số liệu</Button
                        >
                        <Button
                            variant="brand"
                            size="sm"
                            @click="activeTab = 'breakeven'"
                            ><Target class="size-4" /> Kiểm tra hòa vốn</Button
                        >
                    </div>
                </div>
            </div>

            <div
                class="flex flex-col gap-4 border-t border-slate-200/80 bg-slate-50/70 px-5 py-3.5 lg:flex-row lg:items-center lg:justify-between lg:px-7 dark:border-white/[0.08] dark:bg-white/[0.03]"
            >
                <div
                    class="flex items-center gap-2 text-xs font-semibold text-slate-500 dark:text-slate-400"
                >
                    <CalendarDays class="size-4 text-orange-500" /> Kỳ phân tích
                    <div
                        class="ml-1 flex rounded-lg border border-slate-200 bg-white p-0.5 dark:border-white/[0.1] dark:bg-slate-900"
                    >
                        <button
                            v-for="option in periodOptions"
                            :key="option.value"
                            type="button"
                            class="rounded-md px-3 py-1.5 text-xs font-semibold transition"
                            :class="
                                selectedDays === option.value
                                    ? 'bg-slate-950 text-white shadow-sm dark:bg-white dark:text-slate-950'
                                    : 'text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-white/[0.06]'
                            "
                            @click="applyPeriod(option.value)"
                        >
                            {{ option.label }}
                        </button>
                    </div>
                </div>
                <div
                    class="flex flex-wrap items-center gap-3 text-xs text-slate-500 dark:text-slate-400"
                >
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-3 py-1.5 font-semibold dark:border-white/[0.1] dark:bg-slate-900"
                        ><span class="size-1.5 rounded-full bg-emerald-500" />
                        {{
                            props.branchContext?.scope === 'all'
                                ? 'Toàn chuỗi'
                                : 'Chi nhánh hiện tại'
                        }}</span
                    ><span class="inline-flex items-center gap-1.5"
                        ><Database class="size-3.5" /> Đơn hoàn tất + sổ
                        kho</span
                    >
                </div>
            </div>
        </header>

        <div
            v-if="props.branchContext?.scope === 'all'"
            class="flex items-start gap-3 rounded-xl border border-sky-500/20 bg-sky-500/[0.06] px-4 py-3 text-sm text-sky-800 dark:text-sky-200"
        >
            <Info class="mt-0.5 size-4 shrink-0 text-sky-500" />
            <p>
                Đang xem <strong>toàn chuỗi</strong>. Các chỉ số doanh thu, chi
                phí và điểm hòa vốn được cộng từ toàn bộ chi nhánh.
            </p>
        </div>

        <Deferred
            :data="[
                'revenueTrend',
                'unitEconomics',
                'cohorts',
                'breakEven',
                'benchmarks',
            ]"
        >
            <template #fallback
                ><div
                    class="grid animate-pulse grid-cols-2 gap-3 lg:grid-cols-6"
                >
                    <div
                        v-for="index in 6"
                        :key="index"
                        class="h-32 rounded-2xl bg-slate-200 dark:bg-white/[0.06]"
                    /></div
            ></template>

            <div class="space-y-5">
                <section class="grid gap-3 lg:grid-cols-3">
                    <div
                        class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm lg:col-span-2 dark:border-white/[0.08] dark:bg-slate-950/60"
                    >
                        <div
                            class="mb-4 flex items-start justify-between gap-3"
                        >
                            <div>
                                <p
                                    class="text-xs font-bold tracking-[0.14em] text-slate-400 uppercase"
                                >
                                    Tín hiệu điều hành
                                </p>
                                <h2
                                    class="mt-1 text-lg font-semibold text-slate-900 dark:text-white"
                                >
                                    Việc cần ưu tiên
                                </h2>
                            </div>
                            <Badge
                                variant="outline"
                                class="border-orange-500/20 bg-orange-500/[0.06] text-orange-600 dark:text-orange-300"
                                >{{ insights.length }} tín hiệu</Badge
                            >
                        </div>
                        <div class="grid gap-3 md:grid-cols-3">
                            <div
                                v-for="insight in insights"
                                :key="insight.title"
                                class="rounded-xl border p-3.5"
                                :class="insightToneClasses[insight.tone].box"
                            >
                                <div
                                    class="flex items-start justify-between gap-2"
                                >
                                    <div
                                        class="flex size-8 items-center justify-center rounded-lg"
                                        :class="
                                            insightToneClasses[insight.tone]
                                                .icon
                                        "
                                    >
                                        <CircleAlert
                                            v-if="
                                                insight.tone === 'danger' ||
                                                insight.tone === 'warning'
                                            "
                                            class="size-4"
                                        /><CheckCircle2
                                            v-else-if="
                                                insight.tone === 'success'
                                            "
                                            class="size-4"
                                        /><Lightbulb v-else class="size-4" />
                                    </div>
                                    <button
                                        type="button"
                                        class="text-slate-400 transition hover:text-slate-900 dark:hover:text-white"
                                        :aria-label="insight.action"
                                        @click="focusInsight(insight)"
                                    >
                                        <ChevronRight class="size-4" />
                                    </button>
                                </div>
                                <p
                                    class="mt-3 text-sm leading-5 font-semibold"
                                    :class="
                                        insightToneClasses[insight.tone].text
                                    "
                                >
                                    {{ insight.title }}
                                </p>
                                <p
                                    class="mt-1.5 line-clamp-3 text-xs leading-5 text-slate-500 dark:text-slate-400"
                                >
                                    {{ insight.detail }}
                                </p>
                                <button
                                    type="button"
                                    class="mt-3 text-[11px] font-bold text-slate-600 underline decoration-slate-300 underline-offset-4 hover:text-slate-950 dark:text-slate-300 dark:hover:text-white"
                                    @click="focusInsight(insight)"
                                >
                                    {{ insight.action }}
                                </button>
                            </div>
                        </div>
                    </div>
                    <div
                        class="rounded-2xl border border-slate-200/80 bg-white p-5 text-slate-900 shadow-sm dark:border-white/[0.08] dark:bg-slate-950 dark:text-white"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p
                                    class="text-xs font-bold tracking-[0.14em] text-slate-500 uppercase dark:text-slate-400"
                                >
                                    Điểm hòa vốn
                                </p>
                                <h2 class="mt-1 text-lg font-semibold">
                                    {{
                                        be.is_profitable
                                            ? 'Đang có lãi'
                                            : 'Chưa đạt mục tiêu'
                                    }}
                                </h2>
                            </div>
                            <div
                                class="flex size-9 items-center justify-center rounded-xl"
                                :class="
                                    be.is_profitable
                                        ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
                                        : 'bg-amber-500/15 text-amber-600 dark:text-amber-300'
                                "
                            >
                                <Gauge class="size-5" />
                            </div>
                        </div>
                        <div class="mt-5 flex items-end justify-between">
                            <div>
                                <p
                                    class="text-3xl font-semibold tracking-tight text-slate-900 dark:text-white"
                                >
                                    {{ formatNumber(be.total_orders) }}
                                </p>
                                <p
                                    class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                                >
                                    đơn thực tế trong kỳ
                                </p>
                            </div>
                            <p
                                class="text-right text-sm font-semibold text-slate-700 dark:text-slate-300"
                            >
                                {{ formatNumber(be.break_even_orders) }}
                                đơn<br /><span
                                    class="text-xs font-normal text-slate-400 dark:text-slate-500"
                                    >để hòa vốn</span
                                >
                            </p>
                        </div>
                        <div
                            class="mt-5 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-white/10"
                        >
                            <div
                                class="h-full rounded-full bg-orange-500 transition-all"
                                :style="{ width: `${breakEvenProgress}%` }"
                            />
                        </div>
                        <div
                            class="mt-2 flex justify-between text-[11px] text-slate-500 dark:text-slate-400"
                        >
                            <span
                                >{{ formatNumber(breakEvenProgress, 0) }}% tiến
                                độ</span
                            ><span
                                >{{ compactMoney(be.break_even_revenue) }} doanh
                                thu mục tiêu</span
                            >
                        </div>
                    </div>
                </section>

                <section
                    class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6"
                >
                    <div
                        v-for="kpi in kpis"
                        :key="kpi.label"
                        class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-sm dark:border-white/[0.08] dark:bg-slate-950/60"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <p
                                class="text-xs font-semibold text-slate-500 dark:text-slate-400"
                            >
                                {{ kpi.label }}
                            </p>
                            <div
                                class="flex size-8 items-center justify-center rounded-lg"
                                :class="{
                                    'bg-orange-500/10 text-orange-500':
                                        kpi.tone === 'orange',
                                    'bg-sky-500/10 text-sky-500':
                                        kpi.tone === 'blue',
                                    'bg-emerald-500/10 text-emerald-500':
                                        kpi.tone === 'green',
                                    'bg-rose-500/10 text-rose-500':
                                        kpi.tone === 'red',
                                    'bg-violet-500/10 text-violet-500':
                                        kpi.tone === 'violet',
                                    'bg-amber-500/10 text-amber-500':
                                        kpi.tone === 'amber',
                                }"
                            >
                                <component :is="kpi.icon" class="size-4" />
                            </div>
                        </div>
                        <p
                            class="mt-4 truncate text-xl font-semibold tracking-tight text-slate-950 dark:text-white"
                        >
                            {{ kpi.value }}
                        </p>
                        <p
                            class="mt-1 truncate text-[11px] text-slate-400"
                            :title="kpi.detail"
                        >
                            {{ kpi.detail }}
                        </p>
                    </div>
                </section>

                <nav
                    class="flex gap-1 overflow-x-auto rounded-xl border border-slate-200/80 bg-white p-1 shadow-sm dark:border-white/[0.08] dark:bg-slate-950/60"
                    aria-label="Các phân tích BI"
                >
                    <button
                        type="button"
                        class="flex shrink-0 items-center gap-2 rounded-lg px-4 py-2.5 text-xs font-bold transition"
                        :class="
                            activeTab === 'overview'
                                ? 'bg-slate-950 text-white shadow-sm dark:bg-white dark:text-slate-950'
                                : 'text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-white/[0.06]'
                        "
                        @click="activeTab = 'overview'"
                    >
                        <TrendingUp class="size-4" /> Tổng quan
                    </button>
                    <button
                        type="button"
                        class="flex shrink-0 items-center gap-2 rounded-lg px-4 py-2.5 text-xs font-bold transition"
                        :class="
                            activeTab === 'cohort'
                                ? 'bg-slate-950 text-white shadow-sm dark:bg-white dark:text-slate-950'
                                : 'text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-white/[0.06]'
                        "
                        @click="activeTab = 'cohort'"
                    >
                        <Users class="size-4" /> Giữ chân Cohort
                    </button>
                    <button
                        type="button"
                        class="flex shrink-0 items-center gap-2 rounded-lg px-4 py-2.5 text-xs font-bold transition"
                        :class="
                            activeTab === 'breakeven'
                                ? 'bg-slate-950 text-white shadow-sm dark:bg-white dark:text-slate-950'
                                : 'text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-white/[0.06]'
                        "
                        @click="activeTab = 'breakeven'"
                    >
                        <Scale class="size-4" /> Điểm hòa vốn
                    </button>
                    <button
                        type="button"
                        class="flex shrink-0 items-center gap-2 rounded-lg px-4 py-2.5 text-xs font-bold transition"
                        :class="
                            activeTab === 'benchmark'
                                ? 'bg-slate-950 text-white shadow-sm dark:bg-white dark:text-slate-950'
                                : 'text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-white/[0.06]'
                        "
                        @click="activeTab = 'benchmark'"
                    >
                        <Gauge class="size-4" /> Benchmark ngành
                    </button>
                </nav>

                <!-- Tổng quan -->
                <div
                    v-if="activeTab === 'overview'"
                    class="grid gap-5 xl:grid-cols-[1.65fr_1fr]"
                >
                    <section
                        class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm lg:p-6 dark:border-white/[0.08] dark:bg-slate-950/60"
                    >
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div>
                                <div class="flex items-center gap-2">
                                    <div
                                        class="flex size-8 items-center justify-center rounded-lg bg-orange-500/10 text-orange-500"
                                    >
                                        <TrendingUp class="size-4" />
                                    </div>
                                    <h2
                                        class="text-base font-semibold text-slate-900 dark:text-white"
                                    >
                                        Xu hướng doanh thu
                                    </h2>
                                </div>
                                <p
                                    class="mt-2 text-xs text-slate-500 dark:text-slate-400"
                                >
                                    Theo dõi nhịp tăng trưởng theo tháng để phát
                                    hiện sớm điểm lệch.
                                </p>
                            </div>
                            <div
                                v-if="revenueGrowth !== null"
                                class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-bold"
                                :class="
                                    revenueGrowth >= 0
                                        ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-300'
                                        : 'bg-rose-500/10 text-rose-600 dark:text-rose-300'
                                "
                            >
                                <ArrowUpRight
                                    v-if="revenueGrowth >= 0"
                                    class="size-3.5"
                                /><ArrowDownRight v-else class="size-3.5" />
                                {{ formatNumber(Math.abs(revenueGrowth), 1) }}%
                                so với tháng trước
                            </div>
                        </div>
                        <div v-if="props.revenueTrend.length" class="mt-7">
                            <div
                                class="flex h-56 items-end gap-2 border-b border-l border-slate-200 px-3 pb-0 sm:gap-3 dark:border-white/[0.1]"
                            >
                                <div
                                    v-for="point in props.revenueTrend.slice(
                                        -12,
                                    )"
                                    :key="point.month"
                                    class="group flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-2"
                                >
                                    <div
                                        class="relative flex w-full flex-1 items-end justify-center"
                                    >
                                        <div
                                            class="absolute bottom-0 h-full w-full rounded-t-lg bg-slate-100/70 dark:bg-white/[0.025]"
                                        />
                                        <div
                                            class="relative z-10 w-full max-w-10 rounded-t-lg bg-gradient-to-t from-orange-600 to-orange-400 transition-all group-hover:from-orange-500 group-hover:to-amber-300"
                                            :class="
                                                latestRevenue?.month ===
                                                    point.month &&
                                                'ring-2 ring-orange-500/30 ring-offset-2 ring-offset-white dark:ring-offset-slate-950'
                                            "
                                            :style="{
                                                height: `${Math.max(3, (point.revenue / revenueMax) * 100)}%`,
                                            }"
                                            :title="`${monthLabel(point.month)}: ${formatMoney(point.revenue)}`"
                                        />
                                    </div>
                                    <span
                                        class="text-[10px] font-semibold text-slate-400"
                                        >{{ monthLabel(point.month) }}</span
                                    >
                                </div>
                            </div>
                            <div
                                class="mt-4 flex items-center justify-between text-xs text-slate-400"
                            >
                                <span>Tháng thấp nhất trong 12 tháng</span
                                ><span v-if="latestRevenue"
                                    >Mới nhất:
                                    <strong
                                        class="text-slate-700 dark:text-slate-200"
                                        >{{
                                            formatMoney(latestRevenue.revenue)
                                        }}</strong
                                    ></span
                                >
                            </div>
                        </div>
                        <div
                            v-else
                            class="flex min-h-56 flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 text-center dark:border-white/[0.1]"
                        >
                            <BarChart3
                                class="size-8 text-slate-300 dark:text-slate-600"
                            />
                            <p
                                class="mt-3 text-sm font-semibold text-slate-500"
                            >
                                Chưa có dữ liệu doanh thu
                            </p>
                            <p class="mt-1 max-w-xs text-xs text-slate-400">
                                Biểu đồ sẽ xuất hiện khi có đơn hoàn tất trong
                                hệ thống.
                            </p>
                        </div>
                    </section>

                    <section
                        class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm lg:p-6 dark:border-white/[0.08] dark:bg-slate-950/60"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="flex items-center gap-2">
                                    <div
                                        class="flex size-8 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-500"
                                    >
                                        <ClipboardCheck class="size-4" />
                                    </div>
                                    <h2
                                        class="text-base font-semibold text-slate-900 dark:text-white"
                                    >
                                        Cấu trúc lợi nhuận
                                    </h2>
                                </div>
                                <p
                                    class="mt-2 text-xs text-slate-500 dark:text-slate-400"
                                >
                                    Doanh thu đang được chuyển hóa thành lợi
                                    nhuận như thế nào.
                                </p>
                            </div>
                            <Badge
                                variant="outline"
                                class="border-slate-200 text-[10px] dark:border-white/[0.1]"
                                >{{ formatNumber(ue.gross_margin, 1) }}% biên
                                gộp</Badge
                            >
                        </div>
                        <div class="mt-7 space-y-5">
                            <div v-for="bar in economicsBars" :key="bar.label">
                                <div
                                    class="mb-2 flex items-center justify-between text-xs"
                                >
                                    <span
                                        class="font-semibold text-slate-600 dark:text-slate-300"
                                        >{{ bar.label }}</span
                                    ><span
                                        class="font-bold text-slate-900 dark:text-white"
                                        >{{ compactMoney(bar.value) }}</span
                                    >
                                </div>
                                <div
                                    class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-white/[0.08]"
                                >
                                    <div
                                        class="h-full rounded-full transition-all"
                                        :class="bar.tone"
                                        :style="{
                                            width: `${Math.max(bar.value > 0 ? 3 : 0, bar.percent)}%`,
                                        }"
                                    />
                                </div>
                            </div>
                        </div>
                        <div
                            class="mt-7 grid grid-cols-2 gap-3 border-t border-slate-200 pt-4 dark:border-white/[0.08]"
                        >
                            <div>
                                <p class="text-[11px] text-slate-400">
                                    Tần suất / khách
                                </p>
                                <p
                                    class="mt-1 text-lg font-semibold text-slate-900 dark:text-white"
                                >
                                    {{
                                        formatNumber(
                                            ue.avg_orders_per_customer,
                                            1,
                                        )
                                    }}x
                                </p>
                            </div>
                            <div>
                                <p class="text-[11px] text-slate-400">
                                    Lợi nhuận gộp ước tính
                                </p>
                                <p
                                    class="mt-1 text-lg font-semibold text-emerald-600 dark:text-emerald-400"
                                >
                                    {{ compactMoney(grossProfit) }}
                                </p>
                            </div>
                        </div>
                        <p
                            v-if="ue.cost_basis === 'purchases_fallback'"
                            class="mt-4 flex items-start gap-1.5 text-[11px] leading-4 text-amber-600 dark:text-amber-300"
                        >
                            <Info class="mt-0.5 size-3.5 shrink-0" /> Chưa có đủ
                            giao dịch tiêu hao; giá vốn đang dùng dữ liệu mua
                            hàng làm ước tính.
                        </p>
                    </section>

                    <section
                        class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm lg:p-6 xl:col-span-2 dark:border-white/[0.08] dark:bg-slate-950/60"
                    >
                        <div
                            class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                        >
                            <div>
                                <div class="flex items-center gap-2">
                                    <div
                                        class="flex size-8 items-center justify-center rounded-lg bg-violet-500/10 text-violet-500"
                                    >
                                        <Lightbulb class="size-4" />
                                    </div>
                                    <h2
                                        class="text-base font-semibold text-slate-900 dark:text-white"
                                    >
                                        Mô phỏng kịch bản tăng trưởng
                                    </h2>
                                </div>
                                <p
                                    class="mt-2 max-w-2xl text-xs leading-5 text-slate-500 dark:text-slate-400"
                                >
                                    Kéo các biến vận hành để ước lượng tác động
                                    lên giá trị vòng đời khách hàng. Đây là công
                                    cụ hỗ trợ quyết định, không thay thế ngân
                                    sách đã phê duyệt.
                                </p>
                            </div>
                            <button
                                type="button"
                                class="text-xs font-semibold text-slate-400 underline underline-offset-4 hover:text-slate-900 dark:hover:text-white"
                                @click="resetSimulation"
                            >
                                Đặt lại giả định
                            </button>
                        </div>
                        <div class="mt-6 grid gap-6 lg:grid-cols-[1.2fr_1fr]">
                            <div class="grid gap-5 sm:grid-cols-3">
                                <label class="space-y-2"
                                    ><span
                                        class="text-xs font-semibold text-slate-600 dark:text-slate-300"
                                        >AOV / đơn</span
                                    ><input
                                        v-model.number="simAov"
                                        type="range"
                                        min="0"
                                        :max="
                                            Math.max(
                                                500000,
                                                ue.avg_order_value * 2,
                                            )
                                        "
                                        step="5000"
                                        class="w-full accent-orange-500"
                                        @input="isSimulating = true"
                                    /><span
                                        class="block text-sm font-bold text-slate-900 dark:text-white"
                                        >{{ compactMoney(simAov) }}</span
                                    ></label
                                ><label class="space-y-2"
                                    ><span
                                        class="text-xs font-semibold text-slate-600 dark:text-slate-300"
                                        >Tần suất / khách</span
                                    ><input
                                        v-model.number="simFrequency"
                                        type="range"
                                        min="0"
                                        max="12"
                                        step="0.1"
                                        class="w-full accent-violet-500"
                                        @input="isSimulating = true"
                                    /><span
                                        class="block text-sm font-bold text-slate-900 dark:text-white"
                                        >{{
                                            formatNumber(simFrequency, 1)
                                        }}
                                        lần</span
                                    ></label
                                ><label class="space-y-2"
                                    ><span
                                        class="text-xs font-semibold text-slate-600 dark:text-slate-300"
                                        >CAC / khách</span
                                    ><input
                                        v-model.number="simCac"
                                        type="range"
                                        min="0"
                                        :max="Math.max(1000000, ue.cac * 2)"
                                        step="5000"
                                        class="w-full accent-sky-500"
                                        @input="isSimulating = true"
                                    /><span
                                        class="block text-sm font-bold text-slate-900 dark:text-white"
                                        >{{ compactMoney(simCac) }}</span
                                    ></label
                                >
                            </div>
                            <div
                                class="rounded-xl bg-slate-50 p-4 dark:bg-white/[0.04]"
                            >
                                <div class="flex items-center justify-between">
                                    <p
                                        class="text-xs font-semibold text-slate-500 dark:text-slate-400"
                                    >
                                        Kết quả mô phỏng
                                    </p>
                                    <Badge
                                        variant="outline"
                                        :class="
                                            simRatio >= 2
                                                ? 'border-emerald-500/20 text-emerald-600'
                                                : 'border-amber-500/20 text-amber-600'
                                        "
                                        >{{
                                            simRatio >= 2
                                                ? 'Khả thi'
                                                : 'Cần tối ưu'
                                        }}</Badge
                                    >
                                </div>
                                <div class="mt-4 grid grid-cols-2 gap-4">
                                    <div>
                                        <p class="text-[11px] text-slate-400">
                                            LTV mô phỏng
                                        </p>
                                        <p
                                            class="mt-1 text-xl font-semibold text-slate-900 dark:text-white"
                                        >
                                            {{ compactMoney(simLtv) }}
                                        </p>
                                    </div>
                                    <div>
                                        <p class="text-[11px] text-slate-400">
                                            LTV / CAC
                                        </p>
                                        <p
                                            class="mt-1 text-xl font-semibold text-slate-900 dark:text-white"
                                        >
                                            {{ formatNumber(simRatio, 2) }}x
                                        </p>
                                    </div>
                                </div>
                                <p
                                    class="mt-4 flex items-center gap-1.5 text-xs"
                                    :class="
                                        simRatioDiff >= 0
                                            ? 'text-emerald-600 dark:text-emerald-400'
                                            : 'text-rose-600 dark:text-rose-400'
                                    "
                                >
                                    <ArrowUpRight
                                        v-if="simRatioDiff >= 0"
                                        class="size-3.5"
                                    /><ArrowDownRight v-else class="size-3.5" />
                                    {{
                                        formatNumber(Math.abs(simRatioDiff), 2)
                                    }}x so với hiện tại
                                </p>
                            </div>
                        </div>
                    </section>
                </div>

                <!-- Cohort -->
                <section v-else-if="activeTab === 'cohort'" class="space-y-5">
                    <div class="grid gap-3 md:grid-cols-3">
                        <div
                            class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-white/[0.08] dark:bg-slate-950/60"
                        >
                            <p class="text-xs font-semibold text-slate-400">
                                Giữ chân M1 bình quân
                            </p>
                            <p
                                class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white"
                            >
                                {{ formatNumber(cohortM1, 1) }}%
                            </p>
                            <p class="mt-1 text-xs text-slate-500">
                                Khách quay lại trong tháng kế tiếp
                            </p>
                        </div>
                        <div
                            class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-white/[0.08] dark:bg-slate-950/60"
                        >
                            <p class="text-xs font-semibold text-slate-400">
                                Cohort mới nhất
                            </p>
                            <p
                                class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white"
                            >
                                {{
                                    latestCohort
                                        ? monthLabel(latestCohort.cohort)
                                        : '—'
                                }}
                            </p>
                            <p class="mt-1 text-xs text-slate-500">
                                {{
                                    latestCohort
                                        ? `${formatNumber(latestCohort.size)} khách đăng ký`
                                        : 'Chưa có dữ liệu'
                                }}
                            </p>
                        </div>
                        <div
                            class="rounded-2xl border border-orange-500/20 bg-orange-500/[0.05] p-5 shadow-sm"
                        >
                            <p
                                class="text-xs font-semibold text-orange-600 dark:text-orange-300"
                            >
                                Cách đọc
                            </p>
                            <p
                                class="mt-2 text-sm font-semibold text-slate-900 dark:text-white"
                            >
                                M0 là mốc chuẩn 100%
                            </p>
                            <p
                                class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400"
                            >
                                M1–M5 cho biết khả năng khách quay lại theo tuổi
                                đời cohort.
                            </p>
                        </div>
                    </div>
                    <div
                        class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm lg:p-6 dark:border-white/[0.08] dark:bg-slate-950/60"
                    >
                        <div
                            class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div>
                                <p
                                    class="text-xs font-bold tracking-[0.14em] text-slate-400 uppercase"
                                >
                                    Customer retention
                                </p>
                                <h2
                                    class="mt-1 text-lg font-semibold text-slate-900 dark:text-white"
                                >
                                    Khách hàng quay lại theo cohort
                                </h2>
                                <p
                                    class="mt-2 text-xs text-slate-500 dark:text-slate-400"
                                >
                                    Tỷ lệ được tính trên khách đăng ký trong
                                    từng tháng và có đơn hoàn tất ở các tháng
                                    tiếp theo.
                                </p>
                            </div>
                            <div
                                class="flex items-center gap-2 text-[11px] text-slate-400"
                            >
                                <span
                                    class="size-2.5 rounded bg-emerald-500/30"
                                />
                                tốt
                                <span
                                    class="size-2.5 rounded bg-amber-500/30"
                                />
                                cần theo dõi
                                <span class="size-2.5 rounded bg-rose-500/25" />
                                thấp
                            </div>
                        </div>
                        <div
                            v-if="props.cohorts.length"
                            class="mt-6 overflow-x-auto"
                        >
                            <table
                                class="w-full min-w-[760px] border-separate border-spacing-0 text-left text-xs"
                            >
                                <thead>
                                    <tr
                                        class="text-[11px] font-bold tracking-wide text-slate-400 uppercase"
                                    >
                                        <th
                                            class="border-b border-slate-200 px-3 py-3 dark:border-white/[0.08]"
                                        >
                                            Cohort đăng ký
                                        </th>
                                        <th
                                            class="border-b border-slate-200 px-3 py-3 text-right dark:border-white/[0.08]"
                                        >
                                            Quy mô
                                        </th>
                                        <th
                                            v-for="month in cohortMonths"
                                            :key="month"
                                            class="border-b border-slate-200 px-3 py-3 text-center dark:border-white/[0.08]"
                                        >
                                            M{{ month }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        class="font-bold text-slate-500 dark:text-slate-400"
                                    >
                                        <td
                                            class="border-b border-slate-100 px-3 py-3 dark:border-white/[0.06]"
                                        >
                                            Bình quân
                                        </td>
                                        <td
                                            class="border-b border-slate-100 px-3 py-3 text-right dark:border-white/[0.06]"
                                        >
                                            —
                                        </td>
                                        <td
                                            v-for="(
                                                average, index
                                            ) in cohortAverages"
                                            :key="index"
                                            class="border-b border-slate-100 px-2 py-3 text-center dark:border-white/[0.06]"
                                        >
                                            <span
                                                v-if="average !== null"
                                                class="inline-flex min-w-14 justify-center rounded-md px-2 py-1"
                                                :class="retentionClass(average)"
                                                >{{
                                                    formatNumber(average, 1)
                                                }}%</span
                                            ><span v-else>—</span>
                                        </td>
                                    </tr>
                                    <tr
                                        v-for="cohort in props.cohorts"
                                        :key="cohort.cohort"
                                        class="text-slate-700 dark:text-slate-200"
                                    >
                                        <td
                                            class="border-b border-slate-100 px-3 py-3 font-semibold dark:border-white/[0.06]"
                                        >
                                            {{ monthLabel(cohort.cohort) }}
                                        </td>
                                        <td
                                            class="border-b border-slate-100 px-3 py-3 text-right text-slate-500 dark:border-white/[0.06]"
                                        >
                                            {{ formatNumber(cohort.size) }}
                                        </td>
                                        <td
                                            v-for="month in cohortMonths"
                                            :key="month"
                                            class="border-b border-slate-100 px-2 py-2 text-center dark:border-white/[0.06]"
                                        >
                                            <span
                                                v-if="
                                                    retentionValue(
                                                        cohort,
                                                        month,
                                                    )
                                                "
                                                class="inline-flex min-w-14 justify-center rounded-md px-2 py-2 font-semibold"
                                                :class="
                                                    retentionClass(
                                                        retentionValue(
                                                            cohort,
                                                            month,
                                                        )?.rate ?? null,
                                                    )
                                                "
                                                >{{
                                                    formatNumber(
                                                        retentionValue(
                                                            cohort,
                                                            month,
                                                        )?.rate,
                                                        1,
                                                    )
                                                }}%</span
                                            ><span
                                                v-else
                                                class="text-slate-300 dark:text-slate-600"
                                                >—</span
                                            >
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div
                            v-else
                            class="flex min-h-52 flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 text-center dark:border-white/[0.1]"
                        >
                            <Users
                                class="size-8 text-slate-300 dark:text-slate-600"
                            />
                            <p
                                class="mt-3 text-sm font-semibold text-slate-500"
                            >
                                Chưa có dữ liệu cohort
                            </p>
                            <p class="mt-1 max-w-sm text-xs text-slate-400">
                                Cần có khách hàng đăng ký và đơn hoàn tất để
                                tính tỷ lệ giữ chân.
                            </p>
                        </div>
                    </div>
                </section>

                <!-- Break-even -->
                <section
                    v-else-if="activeTab === 'breakeven'"
                    class="space-y-5"
                >
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div
                            class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-white/[0.08] dark:bg-slate-950/60"
                        >
                            <p class="text-xs font-semibold text-slate-400">
                                Đơn thực tế
                            </p>
                            <p
                                class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white"
                            >
                                {{ formatNumber(be.total_orders) }}
                            </p>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ compactMoney(be.revenue) }} doanh thu
                            </p>
                        </div>
                        <div
                            class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-white/[0.08] dark:bg-slate-950/60"
                        >
                            <p class="text-xs font-semibold text-slate-400">
                                Mục tiêu hòa vốn
                            </p>
                            <p
                                class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white"
                            >
                                {{ formatNumber(be.break_even_orders) }} đơn
                            </p>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ compactMoney(be.break_even_revenue) }} doanh
                                thu
                            </p>
                        </div>
                        <div
                            class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-white/[0.08] dark:bg-slate-950/60"
                        >
                            <p class="text-xs font-semibold text-slate-400">
                                Lãi góp / đơn
                            </p>
                            <p
                                class="mt-2 text-2xl font-semibold text-emerald-600 dark:text-emerald-400"
                            >
                                {{ compactMoney(be.contribution_margin) }}
                            </p>
                            <p class="mt-1 text-xs text-slate-500">
                                AOV {{ compactMoney(be.avg_order_value) }}
                            </p>
                        </div>
                        <div
                            class="rounded-2xl border p-5 shadow-sm"
                            :class="
                                be.is_profitable
                                    ? 'border-emerald-500/20 bg-emerald-500/[0.05]'
                                    : 'border-amber-500/20 bg-amber-500/[0.05]'
                            "
                        >
                            <p
                                class="text-xs font-semibold"
                                :class="
                                    be.is_profitable
                                        ? 'text-emerald-600 dark:text-emerald-300'
                                        : 'text-amber-600 dark:text-amber-300'
                                "
                            >
                                Trạng thái kỳ
                            </p>
                            <p
                                class="mt-2 text-2xl font-semibold text-slate-950 dark:text-white"
                            >
                                {{
                                    be.is_profitable ? 'Có lãi' : 'Chưa hòa vốn'
                                }}
                            </p>
                            <p class="mt-1 text-xs text-slate-500">
                                {{
                                    be.is_profitable
                                        ? `Vượt ${formatNumber(be.total_orders - be.break_even_orders)} đơn`
                                        : `Thiếu ${formatNumber(breakEvenGap)} đơn`
                                }}
                            </p>
                        </div>
                    </div>
                    <div class="grid gap-5 xl:grid-cols-[1.45fr_1fr]">
                        <section
                            class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm lg:p-6 dark:border-white/[0.08] dark:bg-slate-950/60"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p
                                        class="text-xs font-bold tracking-[0.14em] text-slate-400 uppercase"
                                    >
                                        Financial model
                                    </p>
                                    <h2
                                        class="mt-1 text-lg font-semibold text-slate-900 dark:text-white"
                                    >
                                        Doanh thu, chi phí và điểm hòa vốn
                                    </h2>
                                    <p
                                        class="mt-2 text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        Đường doanh thu cắt đường tổng chi phí
                                        tại số đơn cần đạt.
                                    </p>
                                </div>
                                <Badge
                                    variant="outline"
                                    :class="
                                        be.is_profitable
                                            ? 'border-emerald-500/20 text-emerald-600'
                                            : 'border-amber-500/20 text-amber-600'
                                    "
                                    >{{ formatNumber(breakEvenProgress, 0) }}%
                                    tiến độ</Badge
                                >
                            </div>
                            <div class="mt-6 overflow-x-auto">
                                <svg
                                    viewBox="0 0 720 300"
                                    class="h-auto w-full min-w-[620px]"
                                    role="img"
                                    aria-label="Biểu đồ điểm hòa vốn"
                                >
                                    <line
                                        x1="64"
                                        y1="250"
                                        x2="680"
                                        y2="250"
                                        class="stroke-slate-200 dark:stroke-white/[0.12]"
                                    />
                                    <line
                                        x1="64"
                                        y1="40"
                                        x2="64"
                                        y2="250"
                                        class="stroke-slate-200 dark:stroke-white/[0.12]"
                                    />
                                    <line
                                        x1="64"
                                        y1="145"
                                        x2="680"
                                        y2="145"
                                        class="stroke-slate-100 dark:stroke-white/[0.06]"
                                    />
                                    <line
                                        x1="64"
                                        y1="40"
                                        x2="680"
                                        y2="40"
                                        class="stroke-slate-100 dark:stroke-white/[0.06]"
                                    />
                                    <line
                                        :x1="fixedStart.x"
                                        :y1="fixedStart.y"
                                        :x2="fixedEnd.x"
                                        :y2="fixedEnd.y"
                                        class="stroke-slate-400"
                                        stroke-dasharray="5 5"
                                    />
                                    <line
                                        :x1="fixedStart.x"
                                        :y1="fixedStart.y"
                                        :x2="costEnd.x"
                                        :y2="costEnd.y"
                                        class="stroke-amber-500"
                                        stroke-width="3"
                                    />
                                    <line
                                        x1="64"
                                        y1="250"
                                        :x2="revenueEnd.x"
                                        :y2="revenueEnd.y"
                                        class="stroke-emerald-500"
                                        stroke-width="3"
                                    />
                                    <line
                                        :x1="breakEvenPoint.x"
                                        y1="250"
                                        :x2="breakEvenPoint.x"
                                        :y2="breakEvenPoint.y"
                                        class="stroke-slate-300 dark:stroke-white/[0.18]"
                                        stroke-dasharray="4 4"
                                    />
                                    <circle
                                        :cx="breakEvenPoint.x"
                                        :cy="breakEvenPoint.y"
                                        r="6"
                                        class="fill-orange-500 stroke-white dark:stroke-slate-950"
                                        stroke-width="3"
                                    />
                                    <circle
                                        :cx="actualPoint.x"
                                        :cy="actualPoint.y"
                                        r="6"
                                        class="fill-sky-500 stroke-white dark:stroke-slate-950"
                                        stroke-width="3"
                                    />
                                    <text
                                        x="64"
                                        y="274"
                                        class="fill-slate-400 text-[11px]"
                                    >
                                        0 đơn
                                    </text>
                                    <text
                                        x="640"
                                        y="274"
                                        class="fill-slate-400 text-[11px]"
                                    >
                                        {{ formatNumber(chartMaxOrders) }} đơn
                                    </text>
                                    <text
                                        x="75"
                                        y="32"
                                        class="fill-slate-400 text-[11px]"
                                    >
                                        Doanh thu / chi phí
                                    </text>
                                    <text
                                        :x="breakEvenPoint.x + 8"
                                        :y="breakEvenPoint.y - 10"
                                        class="fill-orange-600 text-[11px] font-bold dark:fill-orange-300"
                                    >
                                        Hòa vốn
                                    </text>
                                    <text
                                        :x="actualPoint.x + 8"
                                        :y="actualPoint.y + 18"
                                        class="fill-sky-600 text-[11px] font-bold dark:fill-sky-300"
                                    >
                                        Thực tế
                                    </text>
                                </svg>
                            </div>
                            <div
                                class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-[11px] text-slate-500 dark:text-slate-400"
                            >
                                <span class="inline-flex items-center gap-2"
                                    ><span class="h-0.5 w-4 bg-emerald-500" />
                                    Doanh thu</span
                                ><span class="inline-flex items-center gap-2"
                                    ><span class="h-0.5 w-4 bg-amber-500" />
                                    Tổng chi phí</span
                                ><span class="inline-flex items-center gap-2"
                                    ><span
                                        class="h-0.5 w-4 border-t border-dashed border-slate-400"
                                    />
                                    Chi phí cố định</span
                                >
                            </div>
                        </section>
                        <section
                            class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm lg:p-6 dark:border-white/[0.08] dark:bg-slate-950/60"
                        >
                            <div class="flex items-center gap-2">
                                <div
                                    class="flex size-8 items-center justify-center rounded-lg bg-amber-500/10 text-amber-500"
                                >
                                    <ClipboardCheck class="size-4" />
                                </div>
                                <h2
                                    class="text-base font-semibold text-slate-900 dark:text-white"
                                >
                                    Bảng điều khiển chi phí
                                </h2>
                            </div>
                            <div class="mt-6 space-y-4">
                                <div
                                    class="flex items-center justify-between border-b border-slate-100 pb-4 dark:border-white/[0.06]"
                                >
                                    <span class="text-xs text-slate-500"
                                        >Chi phí biến đổi</span
                                    ><span
                                        class="text-sm font-bold text-slate-900 dark:text-white"
                                        >{{
                                            compactMoney(be.variable_cost)
                                        }}</span
                                    >
                                </div>
                                <div
                                    class="flex items-center justify-between border-b border-slate-100 pb-4 dark:border-white/[0.06]"
                                >
                                    <span class="text-xs text-slate-500"
                                        >Chi phí cố định</span
                                    ><span
                                        class="text-sm font-bold text-slate-900 dark:text-white"
                                        >{{ compactMoney(be.fixed_cost) }}</span
                                    >
                                </div>
                                <div
                                    class="flex items-center justify-between border-b border-slate-100 pb-4 dark:border-white/[0.06]"
                                >
                                    <span class="text-xs text-slate-500"
                                        >Chi phí biến đổi / đơn</span
                                    ><span
                                        class="text-sm font-bold text-slate-900 dark:text-white"
                                        >{{
                                            compactMoney(
                                                be.variable_cost_per_order,
                                            )
                                        }}</span
                                    >
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-xs text-slate-500"
                                        >Số ngày để hòa vốn</span
                                    ><span
                                        class="text-sm font-bold text-orange-600 dark:text-orange-300"
                                        >{{
                                            be.break_even_days > 0
                                                ? `${formatNumber(be.break_even_days)} ngày`
                                                : 'Chưa xác định'
                                        }}</span
                                    >
                                </div>
                            </div>
                            <div
                                class="mt-7 rounded-xl bg-slate-50 p-4 dark:bg-white/[0.04]"
                            >
                                <p
                                    class="text-xs font-semibold text-slate-700 dark:text-slate-200"
                                >
                                    Gợi ý quản trị
                                </p>
                                <p
                                    class="mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400"
                                >
                                    {{
                                        be.is_profitable
                                            ? 'Đang vượt điểm hòa vốn. Ưu tiên giữ biên lợi nhuận và kiểm soát hao hụt khi tăng sản lượng.'
                                            : `Cần thêm ${formatNumber(breakEvenGap)} đơn trong kỳ hoặc tăng lãi góp mỗi đơn để rút ngắn thời gian hòa vốn.`
                                    }}
                                </p>
                            </div>
                            <p
                                v-if="be.cost_basis === 'purchases_fallback'"
                                class="mt-4 flex gap-1.5 text-[11px] leading-4 text-amber-600 dark:text-amber-300"
                            >
                                <Info class="size-3.5 shrink-0" /> Giá vốn đang
                                dùng mua hàng làm ước tính do chưa đủ dữ liệu
                                tiêu hao.
                            </p>
                        </section>
                    </div>
                </section>

                <!-- Benchmark -->
                <section v-else class="space-y-5">
                    <div
                        class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm lg:p-6 dark:border-white/[0.08] dark:bg-slate-950/60"
                    >
                        <div
                            class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div>
                                <p
                                    class="text-xs font-bold tracking-[0.14em] text-slate-400 uppercase"
                                >
                                    F&B Vietnam reference
                                </p>
                                <h2
                                    class="mt-1 text-lg font-semibold text-slate-900 dark:text-white"
                                >
                                    Bạn đang đứng ở đâu so với mặt bằng ngành?
                                </h2>
                                <p
                                    class="mt-2 text-xs text-slate-500 dark:text-slate-400"
                                >
                                    Khoảng màu xanh là biên tham chiếu ngành.
                                    Với chỉ số chi phí, càng thấp càng tốt; với
                                    doanh thu và biên, càng cao càng tốt.
                                </p>
                            </div>
                            <Badge
                                variant="outline"
                                class="border-slate-200 dark:border-white/[0.1]"
                                >{{ props.benchmarks.length }} chỉ số</Badge
                            >
                        </div>
                        <div
                            v-if="props.benchmarks.length"
                            class="mt-7 grid gap-4 lg:grid-cols-2"
                        >
                            <div
                                v-for="benchmark in props.benchmarks"
                                :key="benchmark.metric"
                                class="rounded-xl border border-slate-200/80 p-4 dark:border-white/[0.08]"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div>
                                        <p
                                            class="text-sm font-semibold text-slate-800 dark:text-slate-200"
                                        >
                                            {{ benchmark.metric }}
                                        </p>
                                        <p
                                            class="mt-1 text-[11px] text-slate-400"
                                        >
                                            Tham chiếu:
                                            {{ benchmarkRange(benchmark) }}
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p
                                            class="text-lg font-semibold text-slate-950 dark:text-white"
                                        >
                                            {{ benchmarkValue(benchmark) }}
                                        </p>
                                        <Badge
                                            variant="outline"
                                            class="mt-1 text-[10px]"
                                            :class="
                                                statusClasses[
                                                    benchmark.status
                                                ] ?? statusClasses.normal
                                            "
                                            >{{
                                                statusLabels[
                                                    benchmark.status
                                                ] ?? benchmark.status
                                            }}</Badge
                                        >
                                    </div>
                                </div>
                                <div
                                    class="relative mt-6 h-2 rounded-full bg-slate-100 dark:bg-white/[0.08]"
                                >
                                    <div
                                        class="absolute top-0 h-2 rounded-full bg-sky-400/40"
                                        :style="{
                                            left: `${benchmarkPosition(benchmark).low}%`,
                                            width: `${Math.max(2, benchmarkPosition(benchmark).high - benchmarkPosition(benchmark).low)}%`,
                                        }"
                                    />
                                    <div
                                        class="absolute -top-1.5 size-5 -translate-x-1/2 rounded-full border-2 border-white bg-slate-950 shadow-md dark:border-slate-950 dark:bg-white"
                                        :style="{
                                            left: `${benchmarkPosition(benchmark).value}%`,
                                        }"
                                    />
                                </div>
                                <div
                                    class="mt-2 flex justify-between text-[10px] text-slate-400"
                                >
                                    <span>{{
                                        benchmark.good_direction === 'lower'
                                            ? 'Cao hơn'
                                            : 'Thấp hơn'
                                    }}</span
                                    ><span>Vùng ngành</span
                                    ><span>{{
                                        benchmark.good_direction === 'lower'
                                            ? 'Tốt hơn'
                                            : 'Cao hơn'
                                    }}</span>
                                </div>
                            </div>
                        </div>
                        <div
                            v-else
                            class="flex min-h-52 flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 text-center dark:border-white/[0.1]"
                        >
                            <Gauge
                                class="size-8 text-slate-300 dark:text-slate-600"
                            />
                            <p
                                class="mt-3 text-sm font-semibold text-slate-500"
                            >
                                Chưa có dữ liệu benchmark
                            </p>
                            <p class="mt-1 text-xs text-slate-400">
                                Benchmark sẽ được tính khi có dữ liệu doanh thu
                                và giá vốn trong kỳ.
                            </p>
                        </div>
                    </div>
                </section>

                <footer
                    class="flex flex-col gap-2 border-t border-slate-200/80 px-1 pt-4 text-[11px] text-slate-400 sm:flex-row sm:items-center sm:justify-between dark:border-white/[0.08]"
                >
                    <span class="inline-flex items-center gap-1.5"
                        ><Database class="size-3.5" /> Nguồn: đơn hoàn tất,
                        khách hàng, sổ kho và chi phí vận hành.</span
                    ><span
                        >Phạm vi {{ periodLabel.toLowerCase() }} •
                        {{
                            props.branchContext?.scope === 'all'
                                ? 'toàn chuỗi'
                                : 'chi nhánh hiện tại'
                        }}</span
                    >
                </footer>
            </div>
        </Deferred>
    </div>
</template>
