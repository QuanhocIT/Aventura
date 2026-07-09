<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    BarChart3,
    CheckCircle2,
    TrendingDown,
    TrendingUp,
    XCircle,
    Percent,
    Banknote,
    ShoppingCart,
    Scale,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps<{
    revenueTrend: {
        month: string;
        revenue: number;
        orders: number;
        avg_order: number;
        yoy_growth: number | null;
    }[];
    unitEconomics: {
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
    };
    cohorts: {
        cohort: string;
        size: number;
        retention: { month: number; returning: number; rate: number }[];
    }[];
    breakEven: {
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
    };
    benchmarks: {
        metric: string;
        value: number;
        unit: string;
        industry_low: number;
        industry_high: number;
        status: string;
    }[];
    days: number;
}>();

const activeTab = ref<'overview' | 'cohort' | 'breakeven' | 'benchmark'>(
    'overview',
);

const maxRevenue = Math.max(...props.revenueTrend.map((r) => r.revenue), 1);
const ue = props.unitEconomics;

const statusColor: Record<string, string> = {
    excellent: 'text-green-600 bg-green-100',
    normal: 'text-blue-600 bg-blue-100',
    warning: 'text-red-600 bg-red-100',
};
const statusLabel: Record<string, string> = {
    excellent: 'Xuất sắc',
    normal: 'Bình thường',
    warning: 'Cần cải thiện',
};

function retentionColor(rate: number): string {
    if (rate >= 40) {
        return 'bg-green-500 text-white';
    }

    if (rate >= 20) {
        return 'bg-green-300 text-green-900';
    }

    if (rate >= 10) {
        return 'bg-amber-200 text-amber-900';
    }

    if (rate > 0) {
        return 'bg-red-100 text-red-800';
    }

    return 'bg-gray-100 text-gray-400';
}

const needleRotation = computed(() => {
    const ratio = props.unitEconomics.ltv_cac_ratio;
    // Map ratio range [0, 4] to rotation [-90, 90] degrees
    const pct = Math.min(1, Math.max(0, ratio / 4));
    return -90 + pct * 180;
});
</script>

<template>
    <Head title="BI Dashboard" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-5 p-4 lg:p-6">
        <!-- ── Header ──────────────────────────────────────────────────────── -->
        <div
            class="flex flex-col gap-4 border-b border-border pb-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-11 w-11 items-center justify-center rounded-2xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400"
                >
                    <BarChart3
                        class="size-6 text-indigo-600 dark:text-indigo-400"
                    />
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight">
                        Business Intelligence Dashboard
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Góc nhìn chiến lược dài hạn — Unit Economics, Cohort,
                        Break-even, Benchmark ngành F&B.
                    </p>
                </div>
            </div>
        </div>

        <!-- Tabs Switcher (Pill Style) -->
        <div
            class="flex max-w-full items-center gap-1 self-start overflow-x-auto rounded-xl border border-border bg-muted p-1"
        >
            <button
                v-for="tab in [
                    { key: 'overview', label: 'Tổng quan & Unit Economics' },
                    { key: 'cohort', label: 'Cohort Analysis' },
                    { key: 'breakeven', label: 'Break-even' },
                    { key: 'benchmark', label: 'Benchmark ngành' },
                ]"
                :key="tab.key"
                @click="activeTab = tab.key as any"
                class="cursor-pointer rounded-lg px-3.5 py-1.5 text-xs font-semibold whitespace-nowrap transition-all"
                :class="
                    activeTab === tab.key
                        ? 'bg-background text-foreground shadow-sm'
                        : 'text-muted-foreground hover:text-foreground'
                "
            >
                {{ tab.label }}
            </button>
        </div>

        <!-- Overview tab -->
        <div v-if="activeTab === 'overview'" class="space-y-6">
            <!-- Unit Economics KPI -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <!-- 1. Doanh thu -->
                <Card>
                    <CardContent class="px-4 pt-4 pb-4">
                        <div class="mb-2 flex items-center justify-between">
                            <span
                                class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                >Doanh thu</span
                            >
                            <Banknote class="size-4 text-indigo-500" />
                        </div>
                        <p
                            class="text-xl font-bold text-indigo-600 dark:text-indigo-400"
                        >
                            {{ ue.revenue.toLocaleString() }}đ
                        </p>
                        <p class="mt-1.5 text-[10px] text-muted-foreground">
                            Doanh thu trong kỳ
                        </p>
                    </CardContent>
                </Card>

                <!-- 2. Gross Margin -->
                <Card>
                    <CardContent class="px-4 pt-4 pb-4">
                        <div class="mb-2 flex items-center justify-between">
                            <span
                                class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                >Gross Margin</span
                            >
                            <Percent class="size-4 text-emerald-500" />
                        </div>
                        <p
                            class="text-xl font-bold"
                            :class="
                                ue.gross_margin >= 55
                                    ? 'text-emerald-600 dark:text-emerald-400'
                                    : 'text-rose-500'
                            "
                        >
                            {{ ue.gross_margin }}%
                        </p>
                        <p class="mt-1.5 text-[10px] text-muted-foreground">
                            Tỷ lệ LN gộp
                        </p>
                    </CardContent>
                </Card>

                <!-- 3. LTV -->
                <Card>
                    <CardContent class="px-4 pt-4 pb-4">
                        <div class="mb-2 flex items-center justify-between">
                            <span
                                class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                >LTV</span
                            >
                            <TrendingUp class="size-4 text-blue-500" />
                        </div>
                        <p
                            class="text-xl font-bold text-blue-600 dark:text-blue-400"
                        >
                            {{ ue.ltv.toLocaleString() }}đ
                        </p>
                        <p class="mt-1.5 text-[10px] text-muted-foreground">
                            Giá trị trọn đời KH
                        </p>
                    </CardContent>
                </Card>

                <!-- 4. CAC -->
                <Card>
                    <CardContent class="px-4 pt-4 pb-4">
                        <div class="mb-2 flex items-center justify-between">
                            <span
                                class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                >CAC</span
                            >
                            <TrendingDown class="size-4 text-amber-500" />
                        </div>
                        <p
                            class="text-xl font-bold text-amber-600 dark:text-amber-400"
                        >
                            {{ ue.cac.toLocaleString() }}đ
                        </p>
                        <p class="mt-1.5 text-[10px] text-muted-foreground">
                            Chi phí có khách hàng
                        </p>
                    </CardContent>
                </Card>

                <!-- 5. LTV/CAC -->
                <Card>
                    <CardContent class="px-4 pt-4 pb-4">
                        <div class="mb-2 flex items-center justify-between">
                            <span
                                class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                >LTV/CAC</span
                            >
                            <Scale class="size-4 text-violet-500" />
                        </div>
                        <p
                            class="text-xl font-bold"
                            :class="
                                ue.ltv_cac_ratio >= 3
                                    ? 'text-emerald-600 dark:text-emerald-400'
                                    : 'text-amber-500'
                            "
                        >
                            {{ ue.ltv_cac_ratio }}x
                        </p>
                        <p class="mt-1.5 text-[10px] text-muted-foreground">
                            Hiệu quả LTV/CAC
                        </p>
                    </CardContent>
                </Card>

                <!-- 6. Đơn TB/KH -->
                <Card>
                    <CardContent class="px-4 pt-4 pb-4">
                        <div class="mb-2 flex items-center justify-between">
                            <span
                                class="text-xs font-medium tracking-wider text-muted-foreground uppercase"
                                >Đơn TB/KH</span
                            >
                            <ShoppingCart class="size-4 text-sky-500" />
                        </div>
                        <p class="text-xl font-bold text-foreground">
                            {{ ue.avg_orders_per_customer }}
                        </p>
                        <p class="mt-1.5 text-[10px] text-muted-foreground">
                            Số đơn TB mỗi KH
                        </p>
                    </CardContent>
                </Card>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Revenue trend bar chart -->
                <Card class="lg:col-span-2">
                    <CardContent class="pt-5">
                        <p class="mb-1 text-sm font-semibold">
                            Doanh thu 12 tháng & YoY
                        </p>
                        <p class="mb-4 text-xs text-muted-foreground">
                            Biểu đồ xu hướng doanh thu và tăng trưởng so với
                            cùng kỳ
                        </p>
                        <div class="flex h-40 items-end gap-1">
                            <div
                                v-for="r in revenueTrend"
                                :key="r.month"
                                class="flex flex-1 flex-col items-center gap-1"
                            >
                                <span
                                    v-if="r.yoy_growth != null"
                                    class="text-[9px] font-bold"
                                    :class="
                                        r.yoy_growth >= 0
                                            ? 'text-green-600'
                                            : 'text-red-500'
                                    "
                                >
                                    {{ r.yoy_growth > 0 ? '+' : ''
                                    }}{{ r.yoy_growth }}%
                                </span>
                                <div
                                    class="w-full rounded-t bg-indigo-400 transition-all dark:bg-indigo-600"
                                    :style="{
                                        height:
                                            (r.revenue / maxRevenue) * 100 +
                                            '%',
                                        minHeight: '4px',
                                    }"
                                ></div>
                                <span
                                    class="text-[8px] text-muted-foreground"
                                    >{{ r.month.slice(5) }}</span
                                >
                            </div>
                        </div>
                        <p
                            v-if="!revenueTrend.length"
                            class="py-8 text-center text-muted-foreground"
                        >
                            Chưa có dữ liệu.
                        </p>
                    </CardContent>
                </Card>

                <!-- LTV/CAC Health Gauge Card -->
                <Card class="lg:col-span-1">
                    <CardContent
                        class="flex h-full min-h-[220px] flex-col items-center justify-between pt-5"
                    >
                        <div class="w-full text-left">
                            <p class="mb-1 text-sm font-semibold">
                                Sức khỏe LTV/CAC
                            </p>
                            <p class="text-xs text-muted-foreground">
                                Chỉ số đo lường hiệu quả chi phí & giá trị KH
                            </p>
                        </div>

                        <div
                            class="relative mt-2 flex aspect-[4/3] w-full max-w-[200px] items-center justify-center"
                        >
                            <svg
                                viewBox="0 0 200 130"
                                class="h-full w-full overflow-visible"
                            >
                                <defs>
                                    <linearGradient
                                        id="gaugeGrad"
                                        x1="0"
                                        y1="0"
                                        x2="1"
                                        y2="0"
                                    >
                                        <stop
                                            offset="0%"
                                            stop-color="#ef4444"
                                        />
                                        <stop
                                            offset="33%"
                                            stop-color="#f59e0b"
                                        />
                                        <stop
                                            offset="75%"
                                            stop-color="#10b981"
                                        />
                                    </linearGradient>
                                </defs>
                                <!-- Gauge background arc -->
                                <path
                                    d="M 20 110 A 80 80 0 0 1 180 110"
                                    fill="none"
                                    stroke="#e2e8f0"
                                    stroke-width="12"
                                    stroke-linecap="round"
                                    class="dark:stroke-slate-800"
                                />
                                <!-- Gauge colored arc using gradient -->
                                <path
                                    d="M 20 110 A 80 80 0 0 1 180 110"
                                    fill="none"
                                    stroke="url(#gaugeGrad)"
                                    stroke-width="12"
                                    stroke-linecap="round"
                                    stroke-dasharray="251.2"
                                    stroke-dashoffset="0"
                                />

                                <!-- Center anchor dot -->
                                <circle
                                    cx="100"
                                    cy="110"
                                    r="6"
                                    class="fill-slate-800 dark:fill-slate-100"
                                />
                                <circle
                                    cx="100"
                                    cy="110"
                                    r="2"
                                    class="fill-white dark:fill-slate-900"
                                />

                                <!-- Needle pointer line -->
                                <line
                                    x1="100"
                                    y1="110"
                                    x2="100"
                                    y2="45"
                                    stroke="currentColor"
                                    stroke-width="3"
                                    stroke-linecap="round"
                                    class="origin-[100px_110px] text-slate-800 transition-transform duration-1000 ease-out dark:text-slate-200"
                                    :style="{
                                        transform: `rotate(${needleRotation}deg)`,
                                        transformOrigin: '100px 110px',
                                    }"
                                />
                            </svg>

                            <!-- Ratio overlay text -->
                            <div
                                class="absolute bottom-1 flex flex-col items-center text-center"
                            >
                                <p
                                    class="text-2xl font-black tracking-tight"
                                    :class="
                                        ue.ltv_cac_ratio >= 3
                                            ? 'text-emerald-500'
                                            : ue.ltv_cac_ratio >= 1.5
                                              ? 'text-amber-500'
                                              : 'text-rose-500'
                                    "
                                >
                                    {{ ue.ltv_cac_ratio }}x
                                </p>
                                <span
                                    class="mt-0.5 rounded-full border px-2 py-0.5 text-[9px] font-black tracking-wider uppercase"
                                    :class="
                                        ue.ltv_cac_ratio >= 3
                                            ? 'border-emerald-100 bg-emerald-50 text-emerald-700 dark:bg-emerald-950/20 dark:text-emerald-400'
                                            : ue.ltv_cac_ratio >= 1.5
                                              ? 'border-amber-100 bg-amber-50 text-amber-700 dark:bg-amber-950/20 dark:text-amber-400'
                                              : 'dark:text-rose-455 border-rose-100 bg-rose-50 text-rose-700 dark:bg-rose-950/20'
                                    "
                                >
                                    {{
                                        ue.ltv_cac_ratio >= 3
                                            ? 'Khỏe mạnh'
                                            : ue.ltv_cac_ratio >= 1.5
                                              ? 'Cần chú ý'
                                              : 'Cảnh báo'
                                    }}
                                </span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Cohort tab -->
        <div v-if="activeTab === 'cohort'">
            <Card>
                <CardContent class="pt-5">
                    <p class="mb-1 text-sm font-semibold">
                        Customer Cohort Retention (6 tháng)
                    </p>
                    <p class="mb-4 text-xs text-muted-foreground">
                        % khách hàng quay lại mua theo tháng sau khi đăng ký.
                    </p>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead>
                                <tr class="text-muted-foreground">
                                    <th class="px-2 py-1.5 text-left">
                                        Cohort
                                    </th>
                                    <th class="px-2 py-1.5 text-center">
                                        Size
                                    </th>
                                    <th
                                        v-for="m in [0, 1, 2, 3, 4, 5]"
                                        :key="m"
                                        class="px-2 py-1.5 text-center"
                                    >
                                        M{{ m }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="c in cohorts"
                                    :key="c.cohort"
                                    class="border-t"
                                >
                                    <td class="px-2 py-1.5 font-medium">
                                        {{ c.cohort }}
                                    </td>
                                    <td class="px-2 py-1.5 text-center">
                                        {{ c.size }}
                                    </td>
                                    <td
                                        v-for="m in [0, 1, 2, 3, 4, 5]"
                                        :key="m"
                                        class="p-1 text-center"
                                    >
                                        <div
                                            v-if="c.retention[m]"
                                            class="flex h-8 items-center justify-center rounded-lg text-[11px] font-extrabold transition-all duration-200 hover:scale-105 hover:shadow-sm"
                                            :style="{
                                                backgroundColor: `rgba(99, 102, 241, ${Math.max(0.04, c.retention[m].rate / 100)})`,
                                                color:
                                                    c.retention[m].rate >= 35
                                                        ? '#ffffff'
                                                        : 'currentColor',
                                                border:
                                                    c.retention[m].rate > 0
                                                        ? '1px solid rgba(99, 102, 241, 0.15)'
                                                        : 'none',
                                            }"
                                        >
                                            {{ c.retention[m].rate }}%
                                        </div>
                                        <div
                                            v-else
                                            class="flex h-8 items-center justify-center rounded-lg bg-slate-50/30 text-[10px] text-muted-foreground/40 dark:bg-slate-900/10"
                                        >
                                            —
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p
                        v-if="!cohorts.length"
                        class="py-12 text-center text-muted-foreground"
                    >
                        Chưa có dữ liệu cohort.
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Break-even tab -->
        <div v-if="activeTab === 'breakeven'" class="space-y-4">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <Card>
                    <CardContent class="space-y-3 pt-5 text-sm">
                        <div class="mb-4 flex items-center gap-2">
                            <component
                                :is="
                                    breakEven.is_profitable
                                        ? CheckCircle2
                                        : XCircle
                                "
                                :class="
                                    breakEven.is_profitable
                                        ? 'text-emerald-500'
                                        : 'text-rose-500'
                                "
                                class="size-5"
                            />
                            <p class="text-sm font-semibold">
                                {{
                                    breakEven.is_profitable
                                        ? 'Đã vượt điểm hòa vốn!'
                                        : 'Chưa đạt điểm hòa vốn'
                                }}
                            </p>
                        </div>
                        <div class="flex justify-between">
                            <span>Doanh thu {{ days }}d</span
                            ><span class="font-bold"
                                >{{ breakEven.revenue.toLocaleString() }}đ</span
                            >
                        </div>
                        <div class="flex justify-between">
                            <span>Chi phí biến đổi</span
                            ><span
                                >{{
                                    breakEven.variable_cost.toLocaleString()
                                }}đ</span
                            >
                        </div>
                        <div class="flex justify-between">
                            <span>Chi phí cố định</span
                            ><span
                                >{{
                                    breakEven.fixed_cost.toLocaleString()
                                }}đ</span
                            >
                        </div>
                        <div class="flex justify-between border-t pt-2">
                            <span>Contribution margin/đơn</span
                            ><span
                                class="font-bold text-indigo-600 dark:text-indigo-400"
                                >{{
                                    breakEven.contribution_margin.toLocaleString()
                                }}đ</span
                            >
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="space-y-3 pt-5 text-sm">
                        <p class="mb-4 text-sm font-semibold">Điểm hòa vốn</p>
                        <div class="flex justify-between">
                            <span>Cần tối thiểu</span
                            ><span
                                class="text-xl font-bold text-indigo-600 dark:text-indigo-400"
                                >{{ breakEven.break_even_orders }} đơn</span
                            >
                        </div>
                        <div class="flex justify-between">
                            <span>Tương đương</span
                            ><span class="font-bold"
                                >{{
                                    breakEven.break_even_revenue.toLocaleString()
                                }}đ</span
                            >
                        </div>
                        <div class="flex justify-between">
                            <span>Thực tế hiện tại</span
                            ><span class="font-bold"
                                >{{ breakEven.total_orders }} đơn</span
                            >
                        </div>
                        <div class="h-3 overflow-hidden rounded-full bg-muted">
                            <div
                                :class="[
                                    'h-full rounded-full',
                                    breakEven.is_profitable
                                        ? 'bg-emerald-500'
                                        : 'bg-amber-500',
                                ]"
                                :style="{
                                    width:
                                        Math.min(
                                            100,
                                            breakEven.break_even_orders > 0
                                                ? (breakEven.total_orders /
                                                      breakEven.break_even_orders) *
                                                      100
                                                : 0,
                                        ) + '%',
                                }"
                            ></div>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            {{
                                breakEven.is_profitable
                                    ? 'Bạn đã vượt điểm hòa vốn ' +
                                      (breakEven.total_orders -
                                          breakEven.break_even_orders) +
                                      ' đơn!'
                                    : 'Còn thiếu ' +
                                      (breakEven.break_even_orders -
                                          breakEven.total_orders) +
                                      ' đơn (~' +
                                      breakEven.break_even_days +
                                      ' ngày).'
                            }}
                        </p>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- Benchmark tab -->
        <div v-if="activeTab === 'benchmark'">
            <Card>
                <CardContent class="space-y-4 pt-5">
                    <p class="mb-1 text-sm font-semibold">
                        Benchmark so với ngành F&B Việt Nam
                    </p>
                    <p class="mb-4 text-xs text-muted-foreground">
                        So sánh các chỉ số chính với trung bình ngành nhà hàng.
                    </p>
                    <div
                        v-for="b in benchmarks"
                        :key="b.metric"
                        class="flex items-center justify-between border-b pb-3 last:border-0"
                    >
                        <div>
                            <p class="text-sm font-medium">{{ b.metric }}</p>
                            <p class="text-xs text-muted-foreground">
                                Ngành: {{ b.industry_low }}{{ b.unit }} —
                                {{ b.industry_high }}{{ b.unit }}
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-lg font-bold"
                                >{{
                                    typeof b.value === 'number' &&
                                    b.unit === 'đ'
                                        ? b.value.toLocaleString()
                                        : b.value
                                }}{{ b.unit }}</span
                            >
                            <Badge
                                :class="statusColor[b.status]"
                                class="text-xs"
                                >{{ statusLabel[b.status] }}</Badge
                            >
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
