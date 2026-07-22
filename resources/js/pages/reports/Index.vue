<script setup lang="ts">
import { Head, router, useForm, Deferred } from '@inertiajs/vue3';
import {
    ArrowDownRight,
    ArrowUpRight,
    Award,
    Banknote,
    BarChart3,
    CheckCircle2,
    ChevronDown,
    ChevronUp,
    Clock,
    CreditCard,
    Download,
    Flame,
    Package,
    Percent,
    RefreshCw,
    Send,
    ShoppingCart,
    TrendingUp,
    Wallet,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

// ── Types ────────────────────────────────────────────────────────────────────

type DaySummary = {
    date: string;
    date_full: string;
    order_count: number;
    completed_order_count: number;
    cancelled_count: number;
    net_revenue: number;
    gross_revenue: number;
    discount_total: number;
    cogs_amount: number;
    gross_profit: number;
    cash_revenue: number;
    bank_transfer_revenue: number;
    card_revenue: number;
    ewallet_revenue: number;
    average_order_value: number;
};

type Totals = {
    net_revenue: number;
    gross_revenue: number;
    gross_profit: number;
    order_count: number;
    completed_order_count: number;
    discount_total: number;
    cancelled_count: number;
};

type TopProduct = { name: string; total_qty: number; total_revenue: number };

type PaymentBreakdown = {
    cash: number;
    bank_transfer: number;
    card: number;
    ewallet: number;
    cash_pct: number;
    bank_transfer_pct: number;
    card_pct: number;
    ewallet_pct: number;
};

type PeriodComparison = {
    prev_net_revenue: number;
    prev_order_count: number;
    revenue_change_pct: number | null;
    order_change_pct: number | null;
    avg_change_pct: number | null;
    prev_date_range: { start: string; end: string };
    has_prev_data: boolean;
};

type CancelledStats = { total_cancelled: number; cancellation_rate: number };

type PeakHour = {
    hour: number;
    label: string;
    revenue: number;
    order_count: number;
    width_pct: number;
};

type TodaySummary = {
    net_revenue: number;
    gross_revenue: number;
    completed_count: number;
    order_count: number;
    average_order_value: number;
    calculated_at: string;
} | null;

type ComparisonCards = {
    revenue_delta: number;
    revenue_delta_pct: number;
    order_delta: number;
    yesterday_revenue: number;
    yesterday_orders: number;
} | null;

type TodayRealtimeStats = {
    total_revenue: number;
    total_orders: number;
    active_orders: number;
    active_revenue: number;
    paid_orders: number;
    paid_revenue: number;
    cancelled_orders: number;
    avg_order_value: number;
    calculated_at: string;
};

type ProfitBreakdown = {
    gross_profit: number;
    total_cogs: number;
    total_payroll: number;
    total_opex: number;
    opex_pct: number;
    net_profit: number;
    net_profit_pct: number;
    cogs_pct: number;
    payroll_pct: number;
};

const props = defineProps<{
    summaries: DaySummary[];
    totals: Totals;
    topProducts: TopProduct[];
    period: string;
    dateRange: { start: string; end: string };
    paymentBreakdown: PaymentBreakdown;
    periodComparison: PeriodComparison;
    cancelledStats: CancelledStats;
    peakHours: PeakHour[];
    todaySummary: TodaySummary;
    comparisonCards: ComparisonCards;
    profitBreakdown: ProfitBreakdown;
    canGenerate: boolean;
    canSendEmail: boolean;
    bcgData: {
        name: string;
        product_id: number;
        total_revenue: number;
        total_qty: number;
        quadrant: string;
        ai_recommendation: string;
    }[];
    productMargins: {
        name: string;
        margin: number | null;
        revenue: number;
        qty: number;
    }[];
    todayRealtimeStats: TodayRealtimeStats;
}>();

// ── Period ───────────────────────────────────────────────────────────────────

const activePeriod = ref(props.period);
const setPeriod = (p: string) => {
    activePeriod.value = p;
    router.get('/reports', { period: p }, { preserveScroll: true });
};

const periodLabels: Record<string, string> = {
    today: 'Hôm nay',
    '7days': '7 ngày',
    '30days': '30 ngày',
    month: 'Tháng này',
};

// ── Daily report actions ──────────────────────────────────────────────────────

const generating = ref(false);
const sendingMail = ref(false);
const generateForm = useForm({});
const sendEmailForm = useForm({});

function generateReport() {
    generating.value = true;
    generateForm.post('/reports/generate', {
        onSuccess: () => toast.success('Đã tạo báo cáo hôm nay thành công!'),
        onError: () => toast.error('Có lỗi khi tạo báo cáo.'),
        onFinish: () => {
            generating.value = false;
        },
    });
}

function sendEmailReport() {
    sendingMail.value = true;
    sendEmailForm.post('/reports/send-email', {
        onSuccess: () => toast.success('Email báo cáo đã được xếp hàng gửi!'),
        onError: () => toast.error('Có lỗi khi gửi email.'),
        onFinish: () => {
            sendingMail.value = false;
        },
    });
}

// ── Formatting ────────────────────────────────────────────────────────────────

const vnd = (v: number) => {
    const num = Number(v);
    if (isNaN(num) || num === undefined || num === null) return '0 ₫';
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(num);
};

const formatCompact = (v: number) => {
    const num = Number(v);
    if (isNaN(num) || num === undefined || num === null) return '0đ';
    return new Intl.NumberFormat('vi-VN', {
        notation: 'compact',
        maximumFractionDigits: 1,
    }).format(num) + 'đ';
};

// ── KPI computed ──────────────────────────────────────────────────────────────

const avgOrderValue = computed(() =>
    props.totals.completed_order_count > 0
        ? props.totals.net_revenue / props.totals.completed_order_count
        : 0,
);

const profitMargin = computed(() =>
    props.totals.net_revenue > 0
        ? (
              (props.totals.gross_profit / props.totals.net_revenue) *
              100
          ).toFixed(1)
        : '0',
);

const hasData = computed(
    () => props.summaries.length > 0,
);

// ── Bar chart ─────────────────────────────────────────────────────────────────

const maxRevenue = computed(() =>
    Math.max(...props.summaries.map((s) => s.net_revenue), 1),
);
const hoveredBar = ref<string | null>(null);

// ── Payment donut ─────────────────────────────────────────────────────────────

const paymentSlices = computed(() => {
    const total =
        props.paymentBreakdown.cash +
        props.paymentBreakdown.bank_transfer +
        props.paymentBreakdown.card +
        props.paymentBreakdown.ewallet;

    if (total === 0) {
        return [];
    }

    const entries = [
        {
            key: 'cash',
            label: 'Tiền mặt',
            color: '#3b82f6',
            value: props.paymentBreakdown.cash,
            pct: props.paymentBreakdown.cash_pct,
        },
        {
            key: 'bank_transfer',
            label: 'Chuyển khoản',
            color: '#8b5cf6',
            value: props.paymentBreakdown.bank_transfer,
            pct: props.paymentBreakdown.bank_transfer_pct,
        },
        {
            key: 'card',
            label: 'Thẻ ngân hàng',
            color: '#0ea5e9',
            value: props.paymentBreakdown.card,
            pct: props.paymentBreakdown.card_pct,
        },
        {
            key: 'ewallet',
            label: 'Ví điện tử',
            color: '#ec4899',
            value: props.paymentBreakdown.ewallet,
            pct: props.paymentBreakdown.ewallet_pct,
        },
    ];

    return entries.filter((e) => e.value > 0);
});

const donutPaths = computed(() => {
    const total = paymentSlices.value.reduce((s, e) => s + e.value, 0);

    if (total === 0) {
        return [];
    }

    let angle = -Math.PI / 2;
    const r = 60;
    const cx = 70;
    const cy = 70;

    return paymentSlices.value.map((slice) => {
        const sweep = (slice.value / total) * 2 * Math.PI;
        const x1 = cx + r * Math.cos(angle);
        const y1 = cy + r * Math.sin(angle);
        const x2 = cx + r * Math.cos(angle + sweep);
        const y2 = cy + r * Math.sin(angle + sweep);
        const large = sweep > Math.PI ? 1 : 0;
        const path = `M ${cx} ${cy} L ${x1.toFixed(2)} ${y1.toFixed(2)} A ${r} ${r} 0 ${large} 1 ${x2.toFixed(2)} ${y2.toFixed(2)} Z`;
        angle += sweep;

        return { ...slice, path };
    });
});

// ── Product pie ───────────────────────────────────────────────────────────────

const pieSlices = computed(() => {
    if (!props.topProducts || props.topProducts.length === 0) return [];
    const top5 = props.topProducts.slice(0, 5);
    const top5Rev = top5.reduce((s, p) => s + (Number(p.total_revenue) || 0), 0);
    const netRev = Number(props.totals?.net_revenue) || 0;
    const total = netRev > 0 ? netRev : (top5Rev > 0 ? top5Rev : 0);

    if (total === 0) {
        return [];
    }

    const colors = ['#4f46e5', '#f97316', '#10b981', '#f59e0b', '#ef4444'];
    const slices = top5
        .map((p, i) => {
            const val = Number(p.total_revenue) || 0;
            const pctVal = (val / total) * 100;
            return {
                name: p.name || 'Sản phẩm',
                value: val,
                color: colors[i % colors.length],
                pct: isNaN(pctVal) ? 0 : pctVal,
            };
        })
        .filter((s) => s.value > 0);

    if (netRev > top5Rev && total > 0) {
        const other = netRev - top5Rev;
        const otherPct = (other / total) * 100;
        if (other > 0) {
            slices.push({
                name: 'Khác',
                value: other,
                color: '#94a3b8',
                pct: isNaN(otherPct) ? 0 : otherPct,
            });
        }
    }

    return slices;
});

const piePaths = computed(() => {
    const total = pieSlices.value.reduce((s, p) => s + p.value, 0);

    if (total === 0) {
        return [];
    }

    let angle = -Math.PI / 2;
    const r = 100;
    const cx = 100;
    const cy = 100;

    return pieSlices.value.map((slice) => {
        const sweep = (slice.value / total) * 2 * Math.PI;
        const x1 = cx + r * Math.cos(angle);
        const y1 = cy + r * Math.sin(angle);
        const x2 = cx + r * Math.cos(angle + sweep);
        const y2 = cy + r * Math.sin(angle + sweep);
        const large = sweep > Math.PI ? 1 : 0;
        const path = `M ${x1.toFixed(2)} ${y1.toFixed(2)} A ${r} ${r} 0 ${large} 1 ${x2.toFixed(2)} ${y2.toFixed(2)} L ${cx} ${cy} Z`;
        angle += sweep;

        return { ...slice, path };
    });
});

// ── Peak hours ────────────────────────────────────────────────────────────────

const topPeakHours = computed(() => {
    if (!props.peakHours) return [];
    return [...props.peakHours]
        .sort((a, b) => b.revenue - a.revenue)
        .slice(0, 3)
        .map((h) => h.hour);
});

// ── Export CSV ────────────────────────────────────────────────────────────────

function exportCSV() {
    const headers = [
        'Ngày',
        'Đơn tổng',
        'Đơn HT',
        'Đơn hủy',
        'Doanh thu gộp',
        'Giảm giá',
        'Doanh thu thuần',
        'COGS',
        'Lợi nhuận gộp',
        'Tiền mặt',
        'Chuyển khoản',
        'Thẻ',
        'Ví điện tử',
        'Giá trị TB/đơn',
    ];
    const rows = props.summaries.map((s) => [
        s.date_full,
        s.order_count,
        s.completed_order_count,
        s.cancelled_count,
        s.gross_revenue,
        s.discount_total,
        s.net_revenue,
        s.cogs_amount,
        s.gross_profit,
        s.cash_revenue,
        s.bank_transfer_revenue,
        s.card_revenue,
        s.ewallet_revenue,
        s.average_order_value,
    ]);
    const totalRow = [
        'TỔNG CỘNG',
        props.totals.order_count,
        props.totals.completed_order_count,
        props.totals.cancelled_count,
        props.totals.gross_revenue,
        props.totals.discount_total,
        props.totals.net_revenue,
        props.profitBreakdown.total_cogs,
        props.profitBreakdown.gross_profit,
        props.paymentBreakdown.cash,
        props.paymentBreakdown.bank_transfer,
        props.paymentBreakdown.card,
        props.paymentBreakdown.ewallet,
        avgOrderValue.value,
    ];
    const csv = [headers, ...rows, [], totalRow]
        .map((r) => r.join(','))
        .join('\n');
    const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `bao-cao-doanh-thu-${props.dateRange.start.replace(/\//g, '-')}-den-${props.dateRange.end.replace(/\//g, '-')}.csv`;
    a.click();
    URL.revokeObjectURL(url);
    toast.success('Đã xuất file CSV thành công!');
}

// ── Delta badge helper ────────────────────────────────────────────────────────

function deltaBadge(pct: number | null) {
    if (pct === null) {
        return null;
    }

    return { positive: pct >= 0, text: (pct >= 0 ? '+' : '') + pct + '%' };
}
</script>

<template>
    <Head title="Báo cáo doanh thu" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-5 p-4 lg:p-6">
        <!-- ── Header ──────────────────────────────────────────────────────── -->
        <div
            class="flex flex-col gap-4 border-b border-border pb-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-11 w-11 items-center justify-center rounded-2xl bg-violet-500/10"
                >
                    <BarChart3 class="size-6 text-violet-500" />
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight">
                        Báo Cáo Doanh Thu
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{ dateRange.start }} — {{ dateRange.end }}
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <!-- Period picker -->
                <div
                    class="flex items-center gap-1 rounded-xl border border-border bg-muted p-1"
                >
                    <button
                        v-for="[key, label] in Object.entries(periodLabels)"
                        :key="key"
                        @click="setPeriod(key)"
                        class="cursor-pointer rounded-lg px-3.5 py-1.5 text-xs font-semibold transition-all"
                        :class="
                            activePeriod === key
                                ? 'bg-background text-foreground shadow-sm'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                    >
                        {{ label }}
                    </button>
                </div>

                <!-- Export CSV -->
                <button
                    v-if="hasData"
                    @click="exportCSV"
                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-border bg-background px-3.5 py-2 text-xs font-semibold transition hover:bg-muted active:scale-95"
                >
                    <Download class="size-3.5" />
                    Xuất CSV
                </button>
            </div>
        </div>

        <!-- ── Daily Report Status Bar ─────────────────────────────────────── -->
        <div
            class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm min-h-[120px]"
        >
            <Deferred data="todayRealtimeStats">
                <template #fallback>
                    <div class="flex h-[120px] items-center justify-center text-xs text-muted-foreground animate-pulse gap-2">
                        <RefreshCw class="size-3.5 animate-spin text-violet-500" />
                        Đang tải báo cáo realtime hôm nay...
                    </div>
                </template>
                <div
                    class="flex flex-col gap-4 border-b border-border px-5 py-4 sm:flex-row sm:items-center sm:justify-between"
                >
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-violet-500/10"
                    >
                        <BarChart3 class="size-5 text-violet-600 dark:text-violet-400" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="relative flex h-2 w-2">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                            </span>
                            <p class="text-xs font-bold tracking-wider text-emerald-600 dark:text-emerald-400 uppercase">
                                Báo cáo hoạt động hôm nay (Thời gian thực)
                            </p>
                        </div>
                        <p class="mt-0.5 text-[11px] text-muted-foreground">
                            Hệ thống cập nhật thông tin mới nhất lúc {{ todayRealtimeStats.calculated_at }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="canGenerate"
                    class="flex shrink-0 items-center gap-2"
                >
                    <button
                        @click="generateReport"
                        :disabled="generating"
                        class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-border bg-background px-3.5 py-2 text-xs font-semibold transition hover:bg-muted active:scale-95 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <RefreshCw
                            class="size-3.5"
                            :class="generating ? 'animate-spin' : ''"
                        />
                        {{ generating ? 'Đang tạo...' : 'Tổng hợp lại' }}
                    </button>
                    <button
                        v-if="canSendEmail"
                        @click="sendEmailReport"
                        :disabled="sendingMail"
                        class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-violet-600 px-3.5 py-2 text-xs font-semibold text-white transition hover:bg-violet-700 active:scale-95 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        <Send
                            class="size-3.5"
                            :class="sendingMail ? 'animate-pulse' : ''"
                        />
                        {{ sendingMail ? 'Đang gửi...' : 'Gửi email' }}
                    </button>
                </div>
            </div>

            <!-- Realtime Daily Stats Grid -->
            <div class="grid grid-cols-2 gap-px bg-border md:grid-cols-5">
                <!-- 1. Doanh thu hôm nay (Tạm tính) -->
                <div class="bg-card p-4 transition duration-300 hover:bg-muted/30">
                    <div class="mb-1 flex items-center justify-between">
                        <span class="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">Doanh thu tạm tính</span>
                        <Banknote class="size-4 text-emerald-500" />
                    </div>
                    <p class="text-base font-black text-emerald-600 dark:text-emerald-400">
                        {{ vnd(todayRealtimeStats.total_revenue) }}
                    </p>
                    <p class="mt-1 text-[10px] text-muted-foreground">Đã thu + đơn hoạt động</p>
                </div>

                <!-- 2. Tổng số đơn hàng -->
                <div class="bg-card p-4 transition duration-300 hover:bg-muted/30">
                    <div class="mb-1 flex items-center justify-between">
                        <span class="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">Tổng số đơn hàng</span>
                        <ShoppingCart class="size-4 text-sky-500" />
                    </div>
                    <p class="text-base font-black text-foreground">
                        {{ todayRealtimeStats.total_orders }} đơn
                    </p>
                    <p class="mt-1 text-[10px] text-muted-foreground">Phát sinh trong ngày</p>
                </div>

                <!-- 3. Đơn đã thanh toán -->
                <div class="bg-card p-4 transition duration-300 hover:bg-muted/30">
                    <div class="mb-1 flex items-center justify-between">
                        <span class="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">Đơn đã thanh toán</span>
                        <CheckCircle2 class="size-4 text-emerald-500" />
                    </div>
                    <p class="text-base font-black text-foreground">
                        {{ todayRealtimeStats.paid_orders }} đơn
                    </p>
                    <p class="mt-1 text-[10px] text-muted-foreground">Đã thu: {{ formatCompact(todayRealtimeStats.paid_revenue) }}</p>
                </div>

                <!-- 4. Đơn đang hoạt động -->
                <div class="bg-card p-4 transition duration-300 hover:bg-muted/30">
                    <div class="mb-1 flex items-center justify-between">
                        <span class="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">Đơn đang hoạt động</span>
                        <Clock class="size-4 text-amber-500 animate-pulse" />
                    </div>
                    <p class="text-base font-black text-amber-600 dark:text-amber-400">
                        {{ todayRealtimeStats.active_orders }} đơn
                    </p>
                    <p class="mt-1 text-[10px] text-muted-foreground">Tạm tính: {{ formatCompact(todayRealtimeStats.active_revenue) }}</p>
                </div>

                <!-- 5. Đơn đã hủy -->
                <div class="bg-card p-4 transition duration-300 hover:bg-muted/30">
                    <div class="mb-1 flex items-center justify-between">
                        <span class="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">Đơn đã hủy</span>
                        <XCircle class="size-4 text-rose-500" />
                    </div>
                    <p class="text-base font-black text-rose-500">
                        {{ todayRealtimeStats.cancelled_orders }} đơn
                    </p>
                    <p class="mt-1 text-[10px] text-muted-foreground">Đơn TB: {{ formatCompact(todayRealtimeStats.avg_order_value) }}</p>
                </div>
            </div>

            <!-- Today vs yesterday comparison -->
            <div
                v-if="comparisonCards"
                class="grid grid-cols-3 gap-px border-t border-border bg-border"
            >
                <div class="bg-card px-5 py-3">
                    <p
                        class="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        Hôm nay vs hôm qua
                    </p>
                    <div class="mt-1 flex items-center gap-1.5">
                        <component
                            :is="
                                comparisonCards.revenue_delta >= 0
                                    ? ArrowUpRight
                                    : ArrowDownRight
                            "
                            :class="
                                comparisonCards.revenue_delta >= 0
                                    ? 'text-emerald-500'
                                    : 'text-rose-500'
                            "
                            class="size-4 shrink-0"
                        />
                        <span
                            class="text-sm font-bold"
                            :class="
                                comparisonCards.revenue_delta >= 0
                                    ? 'text-emerald-600 dark:text-emerald-400'
                                    : 'text-rose-600 dark:text-rose-400'
                            "
                        >
                            {{ comparisonCards.revenue_delta >= 0 ? '+' : ''
                            }}{{ comparisonCards.revenue_delta_pct }}%
                        </span>
                    </div>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        HQ:
                        {{ formatCompact(comparisonCards.yesterday_revenue) }}
                    </p>
                </div>
                <div class="bg-card px-5 py-3">
                    <p
                        class="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        Đơn hoàn thành
                    </p>
                    <div class="mt-1 flex items-center gap-1.5">
                        <component
                            :is="
                                comparisonCards.order_delta >= 0
                                    ? ArrowUpRight
                                    : ArrowDownRight
                            "
                            :class="
                                comparisonCards.order_delta >= 0
                                    ? 'text-emerald-500'
                                    : 'text-rose-500'
                            "
                            class="size-4 shrink-0"
                        />
                        <span
                            class="text-sm font-bold"
                            :class="
                                comparisonCards.order_delta >= 0
                                    ? 'text-emerald-600 dark:text-emerald-400'
                                    : 'text-rose-600 dark:text-rose-400'
                            "
                        >
                            {{ comparisonCards.order_delta >= 0 ? '+' : ''
                            }}{{ comparisonCards.order_delta }} đơn
                        </span>
                    </div>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        HQ: {{ comparisonCards.yesterday_orders }} đơn
                    </p>
                </div>
                <div v-if="todaySummary" class="bg-card px-5 py-3">
                    <p
                        class="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        Giá trị TB/đơn
                    </p>
                    <p class="mt-1 text-sm font-bold">
                        {{ formatCompact(todaySummary.average_order_value) }}
                    </p>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        {{ todaySummary.completed_count }}/{{
                            todaySummary.order_count
                        }}
                        đơn
                    </p>
                </div>
            </div>
            </Deferred>
        </div>

        <!-- ── Empty state ──────────────────────────────────────────────────── -->
        <div
            v-if="!hasData"
            class="flex flex-col items-center gap-4 rounded-2xl border border-border bg-card py-16 text-center"
        >
            <BarChart3 class="size-16 text-muted-foreground/25" />
            <div>
                <p class="font-semibold text-muted-foreground">
                    Chưa có dữ liệu cho kỳ này
                </p>
                <p class="mt-1 text-sm text-muted-foreground/70">
                    Nhấn "Tạo ngay" ở trên để tổng hợp dữ liệu hôm nay
                </p>
            </div>
            <button
                v-if="canGenerate"
                @click="generateReport"
                :disabled="generating"
                class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-violet-700 active:scale-95 disabled:opacity-50"
            >
                <RefreshCw
                    class="size-4"
                    :class="generating ? 'animate-spin' : ''"
                />
                {{ generating ? 'Đang tạo...' : 'Tạo báo cáo ngay' }}
            </button>
        </div>

        <template v-else>
            <!-- ── KPI Cards ─────────────────────────────────────────────────────── -->
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
                <!-- 1. Doanh thu thuần -->
                <Card class="lg:col-span-1">
                    <CardContent class="pt-4 pb-4">
                        <div class="mb-2 flex items-center justify-between">
                            <span
                                class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                >Doanh thu</span
                            >
                            <Banknote class="size-4 text-emerald-500" />
                        </div>
                        <p
                            class="text-xl font-bold text-emerald-700 dark:text-emerald-400"
                        >
                            {{ formatCompact(totals.net_revenue) }}
                        </p>
                        <div
                            v-if="
                                periodComparison.has_prev_data &&
                                periodComparison.revenue_change_pct !== null
                            "
                            class="mt-1.5 flex items-center gap-1"
                        >
                            <component
                                :is="
                                    periodComparison.revenue_change_pct >= 0
                                        ? ArrowUpRight
                                        : ArrowDownRight
                                "
                                :class="
                                    periodComparison.revenue_change_pct >= 0
                                        ? 'text-emerald-500'
                                        : 'text-rose-500'
                                "
                                class="size-3"
                            />
                            <span
                                class="text-[11px] font-semibold"
                                :class="
                                    periodComparison.revenue_change_pct >= 0
                                        ? 'text-emerald-600'
                                        : 'text-rose-600'
                                "
                            >
                                {{
                                    periodComparison.revenue_change_pct >= 0
                                        ? '+'
                                        : ''
                                }}{{ periodComparison.revenue_change_pct }}% vs
                                kỳ trước
                            </span>
                        </div>
                        <p
                            v-else
                            class="mt-1.5 text-[10px] text-muted-foreground"
                        >
                            Doanh thu thuần trong kỳ
                        </p>
                    </CardContent>
                </Card>

                <!-- 2. Lợi nhuận ròng -->
                <Card>
                    <CardContent class="pt-4 pb-4">
                        <div class="mb-2 flex items-center justify-between">
                            <span
                                class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                >Lợi nhuận ròng</span
                            >
                            <TrendingUp class="size-4 text-violet-500" />
                        </div>
                        <p
                            class="text-xl font-bold"
                            :class="
                                profitBreakdown.net_profit >= 0
                                    ? 'text-violet-700 dark:text-violet-400'
                                    : 'text-rose-600'
                            "
                        >
                            {{ formatCompact(profitBreakdown.net_profit) }}
                        </p>
                        <p
                            class="mt-1.5 text-[10px]"
                            :class="
                                profitBreakdown.net_profit_pct >= 0
                                    ? 'text-violet-600'
                                    : 'text-rose-500'
                            "
                        >
                            {{ profitBreakdown.net_profit_pct }}% biên lợi nhuận
                            ròng
                        </p>
                    </CardContent>
                </Card>

                <!-- 3. Đơn hoàn thành -->
                <Card>
                    <CardContent class="pt-4 pb-4">
                        <div class="mb-2 flex items-center justify-between">
                            <span
                                class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                >Đơn hàng</span
                            >
                            <ShoppingCart class="size-4 text-sky-500" />
                        </div>
                        <p class="text-xl font-bold">
                            {{ totals.completed_order_count }}
                        </p>
                        <p class="mt-1.5 text-[10px] text-muted-foreground">
                            / {{ totals.order_count }} tổng đơn
                            <template
                                v-if="
                                    periodComparison.has_prev_data &&
                                    periodComparison.order_change_pct !== null
                                "
                            >
                                ·
                                <span
                                    :class="
                                        periodComparison.order_change_pct >= 0
                                            ? 'text-emerald-600'
                                            : 'text-rose-600'
                                    "
                                    class="font-semibold"
                                >
                                    {{
                                        periodComparison.order_change_pct >= 0
                                            ? '+'
                                            : ''
                                    }}{{ periodComparison.order_change_pct }}%
                                </span>
                            </template>
                        </p>
                    </CardContent>
                </Card>

                <!-- 4. Giá trị TB/đơn -->
                <Card>
                    <CardContent class="pt-4 pb-4">
                        <div class="mb-2 flex items-center justify-between">
                            <span
                                class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                >TB/đơn</span
                            >
                            <Award class="size-4 text-amber-500" />
                        </div>
                        <p class="text-xl font-bold">
                            {{ formatCompact(avgOrderValue) }}
                        </p>
                        <div
                            v-if="
                                periodComparison.has_prev_data &&
                                periodComparison.avg_change_pct !== null
                            "
                            class="mt-1.5 flex items-center gap-1"
                        >
                            <component
                                :is="
                                    periodComparison.avg_change_pct >= 0
                                        ? ArrowUpRight
                                        : ArrowDownRight
                                "
                                :class="
                                    periodComparison.avg_change_pct >= 0
                                        ? 'text-emerald-500'
                                        : 'text-rose-500'
                                "
                                class="size-3"
                            />
                            <span
                                class="text-[11px] font-semibold"
                                :class="
                                    periodComparison.avg_change_pct >= 0
                                        ? 'text-emerald-600'
                                        : 'text-rose-600'
                                "
                            >
                                {{
                                    periodComparison.avg_change_pct >= 0
                                        ? '+'
                                        : ''
                                }}{{ periodComparison.avg_change_pct }}%
                            </span>
                        </div>
                        <p v-else class="mt-1.5 text-[10px] text-amber-600">
                            Giảm giá: {{ formatCompact(totals.discount_total) }}
                        </p>
                    </CardContent>
                </Card>

                <!-- 5. Tỷ lệ hủy -->
                <Card>
                    <CardContent class="pt-4 pb-4">
                        <div class="mb-2 flex items-center justify-between">
                            <span
                                class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                >Đơn hủy</span
                            >
                            <XCircle class="size-4 text-rose-500" />
                        </div>
                        <p
                            class="text-xl font-bold"
                            :class="
                                cancelledStats.cancellation_rate > 10
                                    ? 'text-rose-600'
                                    : ''
                            "
                        >
                            {{ cancelledStats.cancellation_rate }}%
                        </p>
                        <p class="mt-1.5 text-[10px] text-muted-foreground">
                            {{ cancelledStats.total_cancelled }} đơn bị hủy
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- ── Period Comparison Banner ─────────────────────────────────────── -->
            <div
                v-if="periodComparison.has_prev_data"
                class="rounded-2xl border border-border bg-muted/40 px-5 py-3.5"
            >
                <p
                    class="mb-1.5 text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                >
                    So sánh với kỳ trước ({{
                        periodComparison.prev_date_range.start
                    }}
                    — {{ periodComparison.prev_date_range.end }})
                </p>
                <div class="flex flex-wrap gap-4 text-sm">
                    <span class="flex items-center gap-1.5">
                        <Banknote class="size-3.5 text-muted-foreground" />
                        <span class="text-muted-foreground">Doanh thu:</span>
                        <span
                            class="font-bold"
                            :class="
                                (periodComparison.revenue_change_pct ?? 0) >= 0
                                    ? 'text-emerald-600 dark:text-emerald-400'
                                    : 'text-rose-600'
                            "
                        >
                            {{
                                periodComparison.revenue_change_pct !== null
                                    ? (periodComparison.revenue_change_pct >= 0
                                          ? '▲ +'
                                          : '▼ ') +
                                      periodComparison.revenue_change_pct +
                                      '%'
                                    : '—'
                            }}
                        </span>
                        <span class="text-xs text-muted-foreground"
                            >vs
                            {{
                                formatCompact(periodComparison.prev_net_revenue)
                            }}</span
                        >
                    </span>
                    <span class="flex items-center gap-1.5">
                        <ShoppingCart class="size-3.5 text-muted-foreground" />
                        <span class="text-muted-foreground">Đơn hàng:</span>
                        <span
                            class="font-bold"
                            :class="
                                (periodComparison.order_change_pct ?? 0) >= 0
                                    ? 'text-emerald-600 dark:text-emerald-400'
                                    : 'text-rose-600'
                            "
                        >
                            {{
                                periodComparison.order_change_pct !== null
                                    ? (periodComparison.order_change_pct >= 0
                                          ? '▲ +'
                                          : '▼ ') +
                                      periodComparison.order_change_pct +
                                      '%'
                                    : '—'
                            }}
                        </span>
                        <span class="text-xs text-muted-foreground"
                            >vs
                            {{ periodComparison.prev_order_count }} đơn</span
                        >
                    </span>
                    <span
                        v-if="periodComparison.avg_change_pct !== null"
                        class="flex items-center gap-1.5"
                    >
                        <Award class="size-3.5 text-muted-foreground" />
                        <span class="text-muted-foreground"
                            >Giá trị TB/đơn:</span
                        >
                        <span
                            class="font-bold"
                            :class="
                                periodComparison.avg_change_pct >= 0
                                    ? 'text-emerald-600 dark:text-emerald-400'
                                    : 'text-rose-600'
                            "
                        >
                            {{
                                (periodComparison.avg_change_pct >= 0
                                    ? '▲ +'
                                    : '▼ ') +
                                periodComparison.avg_change_pct +
                                '%'
                            }}
                        </span>
                    </span>
                </div>
            </div>

            <!-- ── Profit Breakdown ─────────────────────────────────────────────── -->
            <Card
                v-if="
                    profitBreakdown.total_cogs > 0 ||
                    profitBreakdown.total_payroll > 0 ||
                    (profitBreakdown.total_opex &&
                        profitBreakdown.total_opex > 0)
                "
            >
                <CardContent class="pt-5">
                    <div class="mb-3 flex items-center gap-2">
                        <TrendingUp class="size-4 text-muted-foreground" />
                        <p class="text-sm font-semibold">
                            Phân tích cơ cấu chi phí
                        </p>
                        <span class="text-xs text-muted-foreground"
                            >(% so với doanh thu thuần)</span
                        >
                    </div>

                    <!-- Stacked horizontal bar -->
                    <div
                        class="mb-3 flex h-6 overflow-hidden rounded-full bg-muted"
                    >
                        <div
                            class="h-full bg-rose-400 transition-all"
                            :style="{
                                width:
                                    Math.max(profitBreakdown.cogs_pct, 0) + '%',
                            }"
                            :title="'COGS: ' + profitBreakdown.cogs_pct + '%'"
                        />
                        <div
                            class="h-full bg-blue-400 transition-all"
                            :style="{
                                width:
                                    Math.max(profitBreakdown.payroll_pct, 0) +
                                    '%',
                            }"
                            :title="
                                'Lương: ' + profitBreakdown.payroll_pct + '%'
                            "
                        />
                        <div
                            class="h-full bg-amber-400 transition-all"
                            :style="{
                                width:
                                    Math.max(profitBreakdown.opex_pct, 0) + '%',
                            }"
                            :title="'OPEX: ' + profitBreakdown.opex_pct + '%'"
                        />
                        <div
                            class="h-full flex-1 bg-violet-400 transition-all"
                            :title="
                                'Lợi nhuận ròng: ' +
                                profitBreakdown.net_profit_pct +
                                '%'
                            "
                        />
                    </div>

                    <div class="flex flex-wrap gap-x-6 gap-y-2 text-xs">
                        <div class="flex items-center gap-2">
                            <span
                                class="h-3 w-3 shrink-0 rounded-full bg-rose-400"
                            ></span>
                            <span class="text-muted-foreground"
                                >COGS (nguyên liệu)</span
                            >
                            <span class="font-bold"
                                >{{ profitBreakdown.cogs_pct }}%</span
                            >
                            <span class="text-muted-foreground"
                                >·
                                {{
                                    formatCompact(profitBreakdown.total_cogs)
                                }}</span
                            >
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                class="h-3 w-3 shrink-0 rounded-full bg-blue-400"
                            ></span>
                            <span class="text-muted-foreground"
                                >Chi phí lương</span
                            >
                            <span class="font-bold"
                                >{{ profitBreakdown.payroll_pct }}%</span
                            >
                            <span class="text-muted-foreground"
                                >·
                                {{
                                    formatCompact(profitBreakdown.total_payroll)
                                }}</span
                            >
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                class="h-3 w-3 shrink-0 rounded-full bg-amber-400"
                            ></span>
                            <span class="text-muted-foreground"
                                >Chi phí vận hành (OPEX)</span
                            >
                            <span class="font-bold"
                                >{{ profitBreakdown.opex_pct }}%</span
                            >
                            <span class="text-muted-foreground"
                                >·
                                {{
                                    formatCompact(profitBreakdown.total_opex)
                                }}</span
                            >
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                class="h-3 w-3 shrink-0 rounded-full bg-violet-400"
                            ></span>
                            <span class="text-muted-foreground"
                                >Lợi nhuận ròng</span
                            >
                            <span
                                class="font-bold"
                                :class="
                                    profitBreakdown.net_profit_pct >= 0
                                        ? 'text-violet-600 dark:text-violet-400'
                                        : 'text-rose-600'
                                "
                            >
                                {{ profitBreakdown.net_profit_pct }}%
                            </span>
                            <span class="text-muted-foreground"
                                >·
                                {{
                                    formatCompact(profitBreakdown.net_profit)
                                }}</span
                            >
                        </div>
                    </div>

                    <p
                        v-if="profitBreakdown.total_payroll === 0"
                        class="mt-3 flex items-center gap-1.5 text-[11px] text-amber-600 dark:text-amber-400"
                    >
                        <Percent class="size-3.5" />
                        Chưa có dữ liệu lương tháng này — lợi nhuận ròng chưa
                        trừ chi phí nhân sự.
                    </p>
                </CardContent>
            </Card>

            <!-- ── Revenue Bar Chart + Top Products ─────────────────────────────── -->
            <div class="grid gap-4 lg:grid-cols-3">
                <!-- Bar chart -->
                <Card class="lg:col-span-2">
                    <CardContent class="pt-5">
                        <p class="mb-1 text-sm font-semibold">
                            Doanh thu theo ngày
                        </p>
                        <p class="mb-4 text-xs text-muted-foreground">
                            Biểu đồ doanh thu thuần từng ngày trong kỳ
                        </p>

                        <div class="relative">
                            <!-- Y-axis labels -->
                            <div
                                class="pointer-events-none absolute top-0 left-0 flex h-40 flex-col justify-between"
                            >
                                <span
                                    class="text-[10px] text-muted-foreground"
                                    >{{ formatCompact(maxRevenue) }}</span
                                >
                                <span
                                    class="text-[10px] text-muted-foreground"
                                    >{{ formatCompact(maxRevenue / 2) }}</span
                                >
                                <span class="text-[10px] text-muted-foreground"
                                    >0</span
                                >
                            </div>

                            <!-- Bars -->
                            <div class="ml-10 flex h-40 items-end gap-1">
                                <div
                                    v-for="s in summaries"
                                    :key="s.date_full"
                                    class="group relative flex flex-1 flex-col items-center"
                                    @mouseenter="hoveredBar = s.date_full"
                                    @mouseleave="hoveredBar = null"
                                >
                                    <!-- Tooltip -->
                                    <div
                                        v-if="hoveredBar === s.date_full"
                                        class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-2 min-w-[140px] -translate-x-1/2 rounded-xl border border-border bg-card p-2.5 text-xs shadow-xl"
                                    >
                                        <p class="mb-1.5 font-bold">
                                            {{ s.date_full }}
                                        </p>
                                        <div
                                            class="space-y-1 text-muted-foreground"
                                        >
                                            <div
                                                class="flex justify-between gap-3"
                                            >
                                                <span>Doanh thu</span
                                                ><span
                                                    class="font-semibold text-foreground"
                                                    >{{
                                                        formatCompact(
                                                            s.net_revenue,
                                                        )
                                                    }}</span
                                                >
                                            </div>
                                            <div
                                                class="flex justify-between gap-3"
                                            >
                                                <span>Đơn HT</span
                                                ><span
                                                    class="font-semibold text-foreground"
                                                    >{{
                                                        s.completed_order_count
                                                    }}</span
                                                >
                                            </div>
                                            <div
                                                v-if="s.cancelled_count > 0"
                                                class="flex justify-between gap-3"
                                            >
                                                <span>Đơn hủy</span
                                                ><span
                                                    class="font-semibold text-rose-500"
                                                    >{{
                                                        s.cancelled_count
                                                    }}</span
                                                >
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Bar -->
                                    <div
                                        class="w-full rounded-t-md transition-all duration-200"
                                        :class="
                                            hoveredBar === s.date_full
                                                ? 'bg-violet-500'
                                                : 'bg-violet-400/70'
                                        "
                                        :style="{
                                            height:
                                                Math.max(
                                                    (s.net_revenue /
                                                        maxRevenue) *
                                                        100,
                                                    s.net_revenue > 0 ? 4 : 0,
                                                ) + '%',
                                        }"
                                    />
                                </div>
                            </div>

                            <!-- X-axis labels -->
                            <div class="mt-1.5 ml-10 flex gap-1">
                                <div
                                    v-for="s in summaries"
                                    :key="s.date_full"
                                    class="flex-1 truncate text-center text-[9px] text-muted-foreground"
                                >
                                    {{ s.date }}
                                </div>
                            </div>
                        </div>

                        <!-- Table -->
                        <div
                            class="mt-5 overflow-hidden rounded-xl border border-border"
                        >
                            <div
                                class="grid grid-cols-4 gap-3 bg-muted/40 px-4 py-2 text-[10px] font-semibold tracking-wider text-muted-foreground uppercase"
                            >
                                <div>Ngày</div>
                                <div class="text-right">Đơn HT</div>
                                <div class="text-right">Doanh thu</div>
                                <div class="text-right">Đơn hủy</div>
                            </div>
                            <div
                                v-for="s in summaries"
                                :key="s.date_full"
                                class="grid grid-cols-4 gap-3 border-t border-border px-4 py-2 text-sm transition-colors hover:bg-muted/20"
                            >
                                <div class="font-medium">{{ s.date_full }}</div>
                                <div class="text-right">
                                    {{ s.completed_order_count }}
                                </div>
                                <div
                                    class="text-right font-semibold text-emerald-600 dark:text-emerald-400"
                                >
                                    {{ formatCompact(s.net_revenue) }}
                                </div>
                                <div
                                    class="text-right"
                                    :class="
                                        s.cancelled_count > 0
                                            ? 'font-semibold text-rose-500'
                                            : 'text-muted-foreground'
                                    "
                                >
                                    {{
                                        s.cancelled_count > 0
                                            ? s.cancelled_count
                                            : '—'
                                    }}
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <Deferred data="topProducts">
                        <template #fallback>
                            <CardContent class="pt-5 flex h-[350px] items-center justify-center text-xs text-muted-foreground animate-pulse gap-2">
                                <RefreshCw class="size-3.5 animate-spin text-violet-500" />
                                Đang tải top món bán chạy...
                            </CardContent>
                        </template>
                        <CardContent class="pt-5">
                            <p class="mb-1 text-sm font-semibold">
                                Top món bán chạy
                            </p>
                            <p class="mb-4 text-xs text-muted-foreground">
                                Dựa trên đơn hoàn thành trong kỳ
                            </p>

                        <div
                            v-if="topProducts.length === 0"
                            class="flex flex-col items-center gap-2 py-8 text-center text-muted-foreground"
                        >
                            <Package class="size-10 opacity-30" />
                            <p class="text-sm">Chưa có dữ liệu bán hàng.</p>
                        </div>

                        <div v-else class="space-y-2.5">
                            <div
                                v-for="(p, i) in topProducts"
                                :key="p.name"
                                class="flex items-center gap-3"
                            >
                                <span
                                    class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[11px] font-bold"
                                    :class="
                                        i === 0
                                            ? 'bg-amber-500 text-zinc-950'
                                            : i === 1
                                              ? 'bg-slate-300 text-slate-800 dark:bg-slate-600 dark:text-slate-100'
                                              : i === 2
                                                ? 'bg-orange-400 text-zinc-950'
                                                : 'bg-muted text-muted-foreground'
                                    "
                                >
                                    {{ i + 1 }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium">
                                        {{ p.name }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ p.total_qty }} phần
                                    </p>
                                </div>
                                <span
                                    class="shrink-0 text-sm font-semibold text-emerald-600 dark:text-emerald-400"
                                >
                                    {{ formatCompact(p.total_revenue) }}
                                </span>
                            </div>
                        </div>
                    </CardContent>
                    </Deferred>
                </Card>
            </div>

            <!-- ── Payment Donut + Peak Hours ───────────────────────────────────── -->
            <div class="grid gap-4 lg:grid-cols-2">
                <!-- Payment breakdown donut -->
                <Card>
                    <CardContent class="pt-5">
                        <div class="mb-4 flex items-center gap-2">
                            <CreditCard class="size-4 text-muted-foreground" />
                            <p class="text-sm font-semibold">
                                Cơ cấu thanh toán
                            </p>
                        </div>

                        <div
                            v-if="paymentSlices.length === 0"
                            class="flex flex-col items-center gap-2 py-8 text-center text-muted-foreground"
                        >
                            <Wallet class="size-10 opacity-30" />
                            <p class="text-sm">Chưa có dữ liệu thanh toán.</p>
                        </div>

                        <div v-else class="flex items-center gap-6">
                            <!-- SVG Donut -->
                            <div class="shrink-0">
                                <svg
                                    width="140"
                                    height="140"
                                    viewBox="0 0 140 140"
                                >
                                    <path
                                        v-for="s in donutPaths"
                                        :key="s.key"
                                        :d="s.path"
                                        :fill="s.color"
                                        opacity="0.85"
                                    />
                                    <!-- Donut hole -->
                                    <circle
                                        cx="70"
                                        cy="70"
                                        r="38"
                                        fill="var(--card)"
                                    />
                                    <!-- Center label -->
                                    <text
                                        x="70"
                                        y="66"
                                        text-anchor="middle"
                                        class="fill-foreground"
                                        font-size="11"
                                        font-weight="700"
                                    >
                                        Tổng
                                    </text>
                                    <text
                                        x="70"
                                        y="80"
                                        text-anchor="middle"
                                        fill="#6b7280"
                                        font-size="9"
                                    >
                                        thanh toán
                                    </text>
                                </svg>
                            </div>

                            <!-- Legend -->
                            <div class="flex-1 space-y-2.5">
                                <div
                                    v-for="s in paymentSlices"
                                    :key="s.key"
                                    class="flex items-center justify-between gap-2"
                                >
                                    <div
                                        class="flex min-w-0 items-center gap-2"
                                    >
                                        <span
                                            class="h-2.5 w-2.5 shrink-0 rounded-full"
                                            :style="{ background: s.color }"
                                        />
                                        <span
                                            class="truncate text-xs text-muted-foreground"
                                            >{{ s.label }}</span
                                        >
                                    </div>
                                    <div class="shrink-0 text-right">
                                        <p class="text-xs font-bold">
                                            {{ s.pct }}%
                                        </p>
                                        <p
                                            class="text-[10px] text-muted-foreground"
                                        >
                                            {{ formatCompact(s.value) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <Deferred data="peakHours">
                        <template #fallback>
                            <CardContent class="pt-5 flex h-[350px] items-center justify-center text-xs text-muted-foreground animate-pulse gap-2">
                                <RefreshCw class="size-3.5 animate-spin text-violet-500" />
                                Đang tải giờ cao điểm...
                            </CardContent>
                        </template>
                        <CardContent class="pt-5">
                            <div class="mb-4 flex items-center gap-2">
                            <Clock class="size-4 text-muted-foreground" />
                            <p class="text-sm font-semibold">
                                Giờ cao điểm trong kỳ
                            </p>
                        </div>

                        <div
                            v-if="!peakHours || peakHours.length === 0 || !peakHours.some(h => Number(h.revenue) > 0 || Number(h.order_count) > 0)"
                            class="flex flex-col items-center gap-2 py-8 text-center text-muted-foreground"
                        >
                            <Clock class="size-10 opacity-30" />
                            <p class="text-sm">Chưa có dữ liệu giờ cao điểm trong kỳ này.</p>
                        </div>

                        <div v-else class="space-y-2">
                            <div
                                v-for="h in peakHours"
                                :key="h.hour"
                                class="flex items-center gap-2.5"
                            >
                                <span
                                    class="w-12 shrink-0 text-right font-mono text-xs text-muted-foreground"
                                    >{{ h.label }}</span
                                >
                                <div
                                    class="relative h-6 flex-1 overflow-hidden rounded-md bg-muted/50"
                                >
                                    <div
                                        class="h-full rounded-md transition-all duration-500"
                                        :class="
                                            topPeakHours.includes(h.hour)
                                                ? 'bg-amber-400'
                                                : 'bg-violet-400/60'
                                        "
                                        :style="{ width: (h.width_pct || 0) + '%' }"
                                    />
                                </div>
                                <div class="w-16 shrink-0 text-right">
                                    <span class="text-xs font-semibold">{{
                                        formatCompact(h.revenue || 0)
                                    }}</span>
                                </div>
                                <Flame
                                    v-if="topPeakHours.includes(h.hour)"
                                    class="size-3.5 shrink-0 text-amber-500"
                                />
                                <span v-else class="w-3.5 shrink-0" />
                            </div>
                        </div>
                    </CardContent>
                    </Deferred>
                </Card>
            </div>

            <!-- ── Product Pie Chart ─────────────────────────────────────────────── -->
            <Card v-if="topProducts && topProducts.length > 0">
                <CardContent class="pt-5">
                    <p class="mb-1 text-sm font-semibold">
                        Cơ cấu doanh thu theo món
                    </p>
                    <p class="mb-5 text-xs text-muted-foreground">
                        Top 5 sản phẩm đóng góp
                    </p>

                    <div
                        v-if="pieSlices.length === 0"
                        class="flex flex-col items-center gap-2 py-8 text-center text-muted-foreground"
                    >
                        <Package class="size-10 opacity-30" />
                        <p class="text-sm">Chưa có dữ liệu doanh thu món trong kỳ này.</p>
                    </div>

                    <div
                        v-else
                        class="flex flex-col items-center gap-5 sm:flex-row sm:items-start"
                    >
                        <svg
                            width="200"
                            height="200"
                            viewBox="0 0 200 200"
                            class="shrink-0"
                        >
                            <path
                                v-for="(p, i) in piePaths"
                                :key="i"
                                :d="p.path"
                                :fill="p.color"
                                opacity="0.85"
                            />
                        </svg>
                        <div
                            class="grid flex-1 grid-cols-1 gap-2 sm:grid-cols-2"
                        >
                            <div
                                v-for="(s, i) in pieSlices"
                                :key="i"
                                class="flex items-center gap-2.5"
                            >
                                <span
                                    class="h-3 w-3 shrink-0 rounded-full"
                                    :style="{ background: s.color }"
                                />
                                <div class="min-w-0">
                                    <p class="truncate text-xs font-medium">
                                        {{ s.name }}
                                    </p>
                                    <p
                                        class="text-[11px] text-muted-foreground"
                                    >
                                        {{ s.pct.toFixed(1) }}% ·
                                        {{ formatCompact(s.value) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- ── BCG Matrix ─────────────────────────────────────────────────────── -->
            <Deferred :data="['bcgData', 'productMargins']">
                <template #fallback>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <Card>
                            <CardContent class="pt-5 flex h-[350px] items-center justify-center text-xs text-muted-foreground animate-pulse gap-2">
                                <RefreshCw class="size-3.5 animate-spin text-violet-500" />
                                Đang tải phân tích ma trận BCG...
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent class="pt-5 flex h-[350px] items-center justify-center text-xs text-muted-foreground animate-pulse gap-2">
                                <RefreshCw class="size-3.5 animate-spin text-violet-500" />
                                Đang tải phân tích biên lợi nhuận món...
                            </CardContent>
                        </Card>
                    </div>
                </template>
                <Card v-if="bcgData && bcgData.length > 0">
                    <CardContent class="pt-5">
                    <p
                        class="mb-1 flex items-center gap-2 text-sm font-semibold"
                    >
                        <span>🔲</span> Ma trận BCG Sản phẩm
                    </p>
                    <p class="mb-4 text-xs text-muted-foreground">
                        Phân loại sản phẩm theo doanh thu và tần suất bán
                    </p>

                    <div class="grid grid-cols-2 gap-3">
                        <div
                            v-for="quad in [
                                {
                                    key: 'star',
                                    icon: '⭐',
                                    label: 'Ngôi sao',
                                    desc: 'Bán nhiều, doanh thu cao',
                                    cls: 'border-amber-200 bg-amber-50 dark:bg-amber-950/10',
                                },
                                {
                                    key: 'cash_cow',
                                    icon: '🐄',
                                    label: 'Bò sữa',
                                    desc: 'Ít bán nhưng giá trị cao',
                                    cls: 'border-emerald-200 bg-emerald-50 dark:bg-emerald-950/10',
                                },
                                {
                                    key: 'question',
                                    icon: '❓',
                                    label: 'Dấu hỏi',
                                    desc: 'Bán nhiều nhưng doanh thu thấp',
                                    cls: 'border-blue-200 bg-blue-50 dark:bg-blue-950/10',
                                },
                                {
                                    key: 'dog',
                                    icon: '🐶',
                                    label: 'Chú chó',
                                    desc: 'Bán ít, doanh thu thấp',
                                    cls: 'border-slate-200 bg-slate-50 dark:bg-slate-800/30',
                                },
                            ]"
                            :key="quad.key"
                            :class="['rounded-xl border p-3', quad.cls]"
                        >
                            <div class="mb-2 flex items-center gap-1.5">
                                <span class="text-base">{{ quad.icon }}</span>
                                <div>
                                    <p class="text-xs font-bold">
                                        {{ quad.label }}
                                    </p>
                                    <p
                                        class="text-[10px] text-muted-foreground"
                                    >
                                        {{ quad.desc }}
                                    </p>
                                </div>
                            </div>
                            <div class="space-y-1">
                                <div
                                    v-for="p in bcgData.filter(
                                        (d) => d.quadrant === quad.key,
                                    )"
                                    :key="p.name"
                                    class="flex items-center justify-between text-[10px]"
                                >
                                    <span
                                        class="mr-2 flex-1 truncate font-medium"
                                        >{{ p.name }}</span
                                    >
                                    <span class="shrink-0 text-muted-foreground"
                                        >{{ p.total_qty }} đơn</span
                                    >
                                </div>
                                <p
                                    v-if="
                                        !bcgData.filter(
                                            (d) => d.quadrant === quad.key,
                                        ).length
                                    "
                                    class="text-[10px] text-muted-foreground italic"
                                >
                                    Không có sản phẩm
                                </p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- ── Product Margin Chart ───────────────────────────────────────────── -->
            <Card v-if="productMargins && productMargins.length > 0">
                <CardContent class="pt-5">
                    <p
                        class="mb-1 flex items-center gap-2 text-sm font-semibold"
                    >
                        <Percent class="size-4 text-violet-500" /> Biên lợi
                        nhuận theo sản phẩm
                    </p>
                    <p class="mb-4 text-xs text-muted-foreground">
                        Tỉ lệ (Giá bán - Giá vốn) / Giá bán · Ngưỡng an toàn:
                        25%
                    </p>
                    <div class="space-y-2.5">
                        <div
                            v-for="p in productMargins"
                            :key="p.name"
                            class="flex items-center gap-3"
                        >
                            <span
                                class="w-36 shrink-0 truncate text-xs font-medium"
                                >{{ p.name }}</span
                            >
                            <div
                                class="relative h-5 flex-1 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                            >
                                <div
                                    :class="[
                                        'h-full rounded-full transition-all duration-500',
                                        (p.margin ?? 0) < 10
                                            ? 'bg-rose-500'
                                            : (p.margin ?? 0) < 25
                                              ? 'bg-amber-500'
                                              : (p.margin ?? 0) < 50
                                                ? 'bg-teal-500'
                                                : 'bg-emerald-500',
                                    ]"
                                    :style="`width: ${Math.min(p.margin ?? 0, 100)}%`"
                                />
                                <span
                                    class="absolute inset-0 flex items-center justify-center text-[10px] font-bold text-white mix-blend-difference"
                                >
                                    {{
                                        p.margin !== null
                                            ? p.margin + '%'
                                            : 'N/A'
                                    }}
                                </span>
                            </div>
                            <span
                                class="w-16 shrink-0 text-right text-[10px] text-muted-foreground"
                            >
                                {{ p.qty }} đơn
                            </span>
                        </div>
                    </div>
                    <div
                        class="mt-3 flex items-center gap-4 text-[10px] text-muted-foreground"
                    >
                        <span class="flex items-center gap-1"
                            ><span
                                class="inline-block h-2 w-4 rounded-full bg-rose-500"
                            ></span>
                            &lt;10% — Lỗ</span
                        >
                        <span class="flex items-center gap-1"
                            ><span
                                class="inline-block h-2 w-4 rounded-full bg-amber-500"
                            ></span>
                            10-25% — Thấp</span
                        >
                        <span class="flex items-center gap-1"
                            ><span
                                class="inline-block h-2 w-4 rounded-full bg-teal-500"
                            ></span>
                            25-50% — Tốt</span
                        >
                        <span class="flex items-center gap-1"
                            ><span
                                class="inline-block h-2 w-4 rounded-full bg-emerald-500"
                            ></span>
                            &gt;50% — Xuất sắc</span
                        >
                    </div>
                </CardContent>
            </Card>
            </Deferred>
        </template>
    </div>
</template>
