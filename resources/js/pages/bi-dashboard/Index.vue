<script setup lang="ts">
import { Head, Deferred } from '@inertiajs/vue3';
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
    HelpCircle,
    Info,
    RefreshCw,
    AlertTriangle,
    Sliders,
    Building2,
    Users,
} from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = withDefaults(
    defineProps<{
        revenueTrend?: {
            month: string;
            revenue: number;
            orders: number;
            avg_order: number;
            yoy_growth: number | null;
        }[];
        unitEconomics?: {
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
        cohorts?: {
            cohort: string;
            size: number;
            retention: { month: number; returning: number; rate: number }[];
        }[];
        breakEven?: {
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
        benchmarks?: {
            metric: string;
            value: number;
            unit: string;
            industry_low: number;
            industry_high: number;
            status: string;
        }[];
        days: number;
    }>(),
    {
        revenueTrend: () => [],
        unitEconomics: () => ({
            revenue: 0, cogs: 0, waste_cost: 0, gross_margin: 0,
            new_customers: 0, total_customers: 0, avg_order_value: 0,
            ltv: 0, cac: 0, ltv_cac_ratio: 0, avg_orders_per_customer: 0
        }),
        cohorts: () => [],
        breakEven: () => ({
            revenue: 0, variable_cost: 0, fixed_cost: 0, total_orders: 0,
            avg_order_value: 0, variable_cost_per_order: 0, contribution_margin: 0,
            break_even_orders: 0, break_even_revenue: 0, break_even_days: 0,
            is_profitable: false
        }),
        benchmarks: () => [],
    }
);

const activeTab = ref<'overview' | 'cohort' | 'breakeven' | 'benchmark'>(
    'overview',
);

const formatNum = (v: number | null | undefined) => {
    const num = Number(v);

    if (isNaN(num) || num === undefined || num === null) {
return '0';
}

    return num.toLocaleString('vi-VN');
};

const vnd = (v: number) => {
    return formatNum(v) + ' ₫';
};

// ── 1. WHAT-IF ECONOMIC SIMULATION (New Feature) ────────────────────────────────
const simAov = ref(0);
const simFrequency = ref(0);
const simCac = ref(0);
const isSimulating = ref(false);

// Initialize simulation refs when unit economics data resolves
watch(() => props.unitEconomics, (newUe) => {
    if (newUe && !isSimulating.value) {
        simAov.value = Math.round(newUe.avg_order_value || 0);
        simFrequency.value = Number((newUe.avg_orders_per_customer || 0).toFixed(1));
        simCac.value = Math.round(newUe.cac || 0);
    }
}, { immediate: true });

function resetSimulation() {
    if (props.unitEconomics) {
        simAov.value = Math.round(props.unitEconomics.avg_order_value || 0);
        simFrequency.value = Number((props.unitEconomics.avg_orders_per_customer || 0).toFixed(1));
        simCac.value = Math.round(props.unitEconomics.cac || 0);
        isSimulating.value = false;
        toastSuccess('Đã đặt lại bộ mô phỏng!');
    }
}

function startSimulationValChange() {
    isSimulating.value = true;
}

const simLtv = computed(() => {
    const margin = (props.unitEconomics?.gross_margin ?? 0) / 100;

    return Math.round(simAov.value * simFrequency.value * margin);
});

const simLtvCacRatio = computed(() => {
    if (simCac.value <= 0) {
return 0;
}

    return Number((simLtv.value / simCac.value).toFixed(2));
});

const ltvDiffPct = computed(() => {
    const originalLtv = props.unitEconomics?.ltv ?? 0;

    if (originalLtv <= 0) {
return 0;
}

    return ((simLtv.value - originalLtv) / originalLtv) * 100;
});

const ratioDiff = computed(() => {
    const originalRatio = props.unitEconomics?.ltv_cac_ratio ?? 0;

    return simLtvCacRatio.value - originalRatio;
});

// ── 2. BREAK-EVEN CHART (New Feature) ───────────────────────────────────────────
const maxRevenue = computed(() => {
    return props.revenueTrend && props.revenueTrend.length
        ? Math.max(...props.revenueTrend.map((r) => r.revenue), 1)
        : 1;
});

const beMaxOrders = computed(() => {
    const required = props.breakEven?.break_even_orders ?? 0;
    const actual = props.breakEven?.total_orders ?? 0;

    return Math.max(required * 1.4, actual * 1.2, 10);
});

const beMaxRevenue = computed(() => {
    const targetRev = (props.breakEven?.avg_order_value ?? 0) * beMaxOrders.value;
    const totalCost = (props.breakEven?.fixed_cost ?? 0) + (props.breakEven?.variable_cost ?? 0);

    return Math.max(targetRev * 1.1, totalCost * 1.2, 1000000);
});

// Map real coordinates to SVG viewBox coords (400x240)
// Padding left: 55px, padding bottom: 35px, padding top: 20px, padding right: 20px
const getSvgCoords = (orders: number, money: number) => {
    const x = 55 + (orders / beMaxOrders.value) * 325;
    const y = 205 - (money / beMaxRevenue.value) * 185;

    return { x: Math.round(x), y: Math.round(y) };
};

const fixedCostStartCoords = computed(() => getSvgCoords(0, props.breakEven?.fixed_cost ?? 0));
const fixedCostEndCoords = computed(() => getSvgCoords(beMaxOrders.value, props.breakEven?.fixed_cost ?? 0));

const totalCostStartCoords = computed(() => getSvgCoords(0, props.breakEven?.fixed_cost ?? 0));
const totalCostEndCoords = computed(() => {
    const variableTotal = (props.breakEven?.variable_cost_per_order ?? 0) * beMaxOrders.value;
    const totalCost = (props.breakEven?.fixed_cost ?? 0) + variableTotal;

    return getSvgCoords(beMaxOrders.value, totalCost);
});

const revenueStartCoords = computed(() => getSvgCoords(0, 0));
const revenueEndCoords = computed(() => {
    const money = (props.breakEven?.avg_order_value ?? 0) * beMaxOrders.value;

    return getSvgCoords(beMaxOrders.value, money);
});

const bePointCoords = computed(() => {
    const orders = props.breakEven?.break_even_orders ?? 0;
    const money = props.breakEven?.break_even_revenue ?? 0;

    return getSvgCoords(orders, money);
});

const actualPointCoords = computed(() => {
    const orders = props.breakEven?.total_orders ?? 0;
    const money = props.breakEven?.revenue ?? 0;

    return getSvgCoords(orders, money);
});

// ── 3. COHORT RETENTION HEATMAP (Enhancement) ───────────────────────────────────
const cohortAverages = computed(() => {
    if (!props.cohorts || props.cohorts.length === 0) {
return [];
}

    const averages: number[] = [];

    for (let m = 0; m <= 5; m++) {
        let totalRate = 0;
        let count = 0;
        props.cohorts.forEach(c => {
            if (c.retention[m]) {
                totalRate += c.retention[m].rate;
                count++;
            }
        });
        averages.push(count > 0 ? Number((totalRate / count).toFixed(1)) : 0);
    }

    return averages;
});

// ── 4. BENCHMARK POSITION GAUGES (Enhancement) ───────────────────────────────────
// Calculate relative position of value between lower and upper industry boundaries
function getBenchmarkPosition(b: { value: number; industry_low: number; industry_high: number }) {
    const minVal = Math.min(b.industry_low * 0.6, b.value * 0.7);
    const maxVal = Math.max(b.industry_high * 1.4, b.value * 1.3);
    const range = maxVal - minVal;

    if (range <= 0) {
return { valPos: 50, lowPos: 30, highPos: 70 };
}

    const valPos = ((b.value - minVal) / range) * 100;
    const lowPos = ((b.industry_low - minVal) / range) * 100;
    const highPos = ((b.industry_high - minVal) / range) * 100;

    return {
        valPos: Math.min(100, Math.max(0, valPos)),
        lowPos: Math.min(100, Math.max(0, lowPos)),
        highPos: Math.min(100, Math.max(0, highPos)),
    };
}

const statusColor: Record<string, string> = {
    excellent: 'bg-emerald-500/10 text-emerald-600 dark:bg-emerald-950/20 dark:text-emerald-400',
    normal: 'bg-indigo-500/10 text-indigo-600 dark:bg-indigo-950/20 dark:text-indigo-400',
    warning: 'bg-rose-500/10 text-rose-600 dark:bg-rose-950/20 dark:text-rose-455',
};

const statusLabel: Record<string, string> = {
    excellent: 'Xuất sắc',
    normal: 'Bình thường',
    warning: 'Cần chú ý',
};

const needleRotation = computed(() => {
    const ratio = props.unitEconomics?.ltv_cac_ratio ?? 0;
    const pct = Math.min(1, Math.max(0, ratio / 4));

    return -90 + pct * 180;
});

function toastSuccess(msg: string) {
    import('vue-sonner').then(({ toast }) => toast.success(msg));
}
</script>

<template>
    <Head title="BI Dashboard & Analytics" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-6 text-slate-800 dark:text-slate-100">
        <!-- Dashboard Premium Hero Header -->
        <div class="relative overflow-hidden rounded-3xl border border-slate-100 bg-gradient-to-r from-indigo-55/30 via-slate-50/10 to-transparent p-6 shadow-xs dark:border-slate-800/80 dark:from-slate-900/60 dark:via-slate-900/10 dark:to-transparent">
            <!-- Glow points -->
            <div class="absolute -right-24 -top-24 h-48 w-48 rounded-full bg-indigo-500/5 blur-3xl" />
            <div class="absolute -left-12 -bottom-12 h-36 w-36 rounded-full bg-violet-500/5 blur-3xl" />

            <div class="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <div class="relative flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-tr from-indigo-600 to-violet-600 text-white shadow-lg shadow-indigo-500/25">
                        <BarChart3 class="size-7" />
                    </div>
                    <div>
                        <h1 class="text-2xl font-black tracking-tight bg-gradient-to-r from-slate-900 via-slate-800 to-indigo-950 bg-clip-text text-transparent dark:from-slate-100 dark:via-slate-200 dark:to-indigo-300">
                            Business Intelligence Dashboard
                        </h1>
                        <p class="mt-1.5 text-xs font-semibold text-slate-500 dark:text-slate-400 leading-relaxed">
                            Báo cáo phân tích chuyên sâu dài hạn: Chỉ số kinh tế đơn vị, Phân tích Cohort, Điểm hòa vốn và so sánh đối chuẩn hiệu quả F&B Việt Nam.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs with modern Sliding Pill design -->
        <div class="flex shrink-0 gap-2 overflow-x-auto border-b border-slate-150 pb-1.5 dark:border-slate-800/80">
            <button
                @click="activeTab = 'overview'"
                :class="[
                    'flex cursor-pointer items-center gap-2 rounded-xl px-4 py-2.5 text-xs font-bold transition-all duration-300 active:scale-95 whitespace-nowrap',
                    activeTab === 'overview'
                        ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/15 dark:bg-indigo-500'
                        : 'text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-900/60 dark:hover:text-slate-200',
                ]"
            >
                <Scale class="size-4" />
                Tổng quan & Chỉ số Đơn vị
            </button>
            <button
                @click="activeTab = 'cohort'"
                :class="[
                    'flex cursor-pointer items-center gap-2 rounded-xl px-4 py-2.5 text-xs font-bold transition-all duration-300 active:scale-95 whitespace-nowrap',
                    activeTab === 'cohort'
                        ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/15 dark:bg-indigo-500'
                        : 'text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-900/60 dark:hover:text-slate-200',
                ]"
            >
                <Users class="size-4" />
                Phân tích Nhóm (Cohort)
            </button>
            <button
                @click="activeTab = 'breakeven'"
                :class="[
                    'flex cursor-pointer items-center gap-2 rounded-xl px-4 py-2.5 text-xs font-bold transition-all duration-300 active:scale-95 whitespace-nowrap',
                    activeTab === 'breakeven'
                        ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/15 dark:bg-indigo-500'
                        : 'text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-900/60 dark:hover:text-slate-200',
                ]"
            >
                <RefreshCw class="size-4" />
                Phân tích Điểm hòa vốn
            </button>
            <button
                @click="activeTab = 'benchmark'"
                :class="[
                    'flex cursor-pointer items-center gap-2 rounded-xl px-4 py-2.5 text-xs font-bold transition-all duration-300 active:scale-95 whitespace-nowrap',
                    activeTab === 'benchmark'
                        ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/15 dark:bg-indigo-500'
                        : 'text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-900/60 dark:hover:text-slate-200',
                ]"
            >
                <Building2 class="size-4" />
                So sánh đối chuẩn ngành
            </button>
        </div>

        <!-- Inertia Asynchronous Prop Deferment -->
        <Deferred :data="['revenueTrend', 'unitEconomics', 'cohorts', 'breakEven', 'benchmarks']">
            
            <!-- FALLBACK SKELETON LOADER -->
            <template #fallback>
                <div class="space-y-6 animate-pulse">
                    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                        <div v-for="n in 6" :key="n" class="rounded-2xl border border-slate-100 bg-white p-5 dark:border-slate-800/80 dark:bg-slate-900/30">
                            <div class="flex items-center justify-between mb-3">
                                <div class="h-3 w-16 bg-slate-200 dark:bg-slate-800 rounded" />
                                <div class="h-6 w-6 rounded bg-slate-100 dark:bg-slate-800" />
                            </div>
                            <div class="h-6 w-24 bg-slate-200 dark:bg-slate-800 rounded mb-2" />
                            <div class="h-3 w-20 bg-slate-100 dark:bg-slate-800 rounded" />
                        </div>
                    </div>
                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                        <div class="lg:col-span-2 rounded-2xl border border-slate-100 bg-white p-6 dark:border-slate-800 dark:bg-slate-900/30">
                            <div class="h-4 w-40 bg-slate-200 dark:bg-slate-800 rounded mb-6" />
                            <div class="h-44 w-full bg-slate-100 dark:bg-slate-800 rounded" />
                        </div>
                        <div class="rounded-2xl border border-slate-100 bg-white p-6 dark:border-slate-800 dark:bg-slate-900/30">
                            <div class="h-4 w-40 bg-slate-200 dark:bg-slate-800 rounded mb-6" />
                            <div class="h-44 w-full bg-slate-100 dark:bg-slate-800 rounded" />
                        </div>
                    </div>
                </div>
            </template>

            <!-- 1. OVERVIEW & UNIT ECONOMICS TAB -->
            <div v-if="activeTab === 'overview'" class="space-y-6">
                <!-- Unit Economics KPI Cards Row -->
                <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
                    <!-- Doanh thu -->
                    <Card class="overflow-hidden border-slate-100 shadow-md shadow-slate-200/40 hover:shadow-lg transition dark:border-slate-800/80 dark:shadow-none">
                        <CardContent class="p-5">
                            <div class="mb-3 flex items-center justify-between">
                                <span class="text-[10px] font-black tracking-wider text-slate-400 uppercase">Doanh thu</span>
                                <div class="rounded-lg bg-indigo-50 p-1.5 text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-400"><Banknote class="size-4" /></div>
                            </div>
                            <p class="font-mono text-xl font-black text-indigo-600 dark:text-indigo-400">{{ formatNum(props.unitEconomics?.revenue) }} ₫</p>
                            <p class="mt-1 text-[9px] font-bold text-slate-400 uppercase">Doanh thu trong kỳ</p>
                        </CardContent>
                    </Card>

                    <!-- Gross Margin -->
                    <Card class="overflow-hidden border-slate-100 shadow-md shadow-slate-200/40 hover:shadow-lg transition dark:border-slate-800/80 dark:shadow-none">
                        <CardContent class="p-5">
                            <div class="mb-3 flex items-center justify-between">
                                <span class="text-[10px] font-black tracking-wider text-slate-400 uppercase">Tỷ lệ Lợi nhuận gộp</span>
                                <div class="rounded-lg bg-emerald-50 p-1.5 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400"><Percent class="size-4" /></div>
                            </div>
                            <p class="font-mono text-xl font-black text-emerald-600 dark:text-emerald-400">{{ props.unitEconomics?.gross_margin }}%</p>
                            <p class="mt-1 text-[9px] font-bold text-slate-400 uppercase">Hiệu suất món ăn</p>
                        </CardContent>
                    </Card>

                    <!-- LTV -->
                    <Card class="overflow-hidden border-slate-100 shadow-md shadow-slate-200/40 hover:shadow-lg transition dark:border-slate-800/80 dark:shadow-none">
                        <CardContent class="p-5">
                            <div class="mb-3 flex items-center justify-between">
                                <span class="text-[10px] font-black tracking-wider text-slate-400 uppercase">Giá trị trọn đời (LTV)</span>
                                <div class="rounded-lg bg-blue-50 p-1.5 text-blue-600 dark:bg-blue-950/40 dark:text-blue-400"><TrendingUp class="size-4" /></div>
                            </div>
                            <p class="font-mono text-xl font-black text-blue-600 dark:text-blue-400">{{ formatNum(props.unitEconomics?.ltv) }} ₫</p>
                            <p class="mt-1 text-[9px] font-bold text-slate-400 uppercase">Giá trị trọn đời KH</p>
                        </CardContent>
                    </Card>

                    <!-- CAC -->
                    <Card class="overflow-hidden border-slate-100 shadow-md shadow-slate-200/40 hover:shadow-lg transition dark:border-slate-800/80 dark:shadow-none">
                        <CardContent class="p-5">
                            <div class="mb-3 flex items-center justify-between">
                                <span class="text-[10px] font-black tracking-wider text-slate-400 uppercase">Chi phí tìm KH (CAC)</span>
                                <div class="rounded-lg bg-amber-50 p-1.5 text-amber-600 dark:bg-amber-950/40 dark:text-amber-400"><TrendingDown class="size-4" /></div>
                            </div>
                            <p class="font-mono text-xl font-black text-amber-600 dark:text-amber-400">{{ formatNum(props.unitEconomics?.cac) }} ₫</p>
                            <p class="mt-1 text-[9px] font-bold text-slate-400 uppercase">Chi phí có KH mới</p>
                        </CardContent>
                    </Card>

                    <!-- LTV/CAC Ratio -->
                    <Card class="overflow-hidden border-slate-100 shadow-md shadow-slate-200/40 hover:shadow-lg transition dark:border-slate-800/80 dark:shadow-none">
                        <CardContent class="p-5">
                            <div class="mb-3 flex items-center justify-between">
                                <span class="text-[10px] font-black tracking-wider text-slate-400 uppercase">Hệ số LTV/CAC</span>
                                <div class="rounded-lg bg-violet-50 p-1.5 text-violet-600 dark:bg-violet-950/40 dark:text-violet-400"><Scale class="size-4" /></div>
                            </div>
                            <p class="font-mono text-xl font-black text-violet-600 dark:text-violet-400">{{ props.unitEconomics?.ltv_cac_ratio }}x</p>
                            <p class="mt-1 text-[9px] font-bold text-slate-400 uppercase">Hiệu quả tiếp thị</p>
                        </CardContent>
                    </Card>

                    <!-- Orders per Customer -->
                    <Card class="overflow-hidden border-slate-100 shadow-md shadow-slate-200/40 hover:shadow-lg transition dark:border-slate-800/80 dark:shadow-none">
                        <CardContent class="p-5">
                            <div class="mb-3 flex items-center justify-between">
                                <span class="text-[10px] font-black tracking-wider text-slate-400 uppercase">Đơn TB/Khách</span>
                                <div class="rounded-lg bg-sky-50 p-1.5 text-sky-600 dark:bg-sky-950/40 dark:text-sky-400"><ShoppingCart class="size-4" /></div>
                            </div>
                            <p class="font-mono text-xl font-black text-sky-600 dark:text-sky-400">{{ props.unitEconomics?.avg_orders_per_customer }}</p>
                            <p class="mt-1 text-[9px] font-bold text-slate-400 uppercase">Tần suất mua hàng</p>
                        </CardContent>
                    </Card>
                </div>

                <!-- Charts & Gauges Grid -->
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <!-- Revenue Trends Chart -->
                    <Card class="lg:col-span-2 border-slate-100 shadow-md dark:border-slate-800/80">
                        <CardContent class="p-6">
                            <div class="mb-4">
                                <h3 class="text-sm font-black tracking-wider text-slate-500 uppercase flex items-center gap-1.5">
                                    <BarChart3 class="size-4.5 text-indigo-500" />
                                    Doanh thu 12 tháng & YoY
                                </h3>
                                <p class="text-[10px] font-bold text-slate-400 uppercase">Biểu đồ xu hướng doanh thu và tăng trưởng so với cùng kỳ</p>
                            </div>

                            <div class="relative flex h-48 items-end gap-2.5 overflow-x-auto border-b border-slate-100 pb-2 dark:border-slate-800">
                                <!-- Chart background bars grid lines -->
                                <div class="absolute inset-0 flex flex-col justify-between pointer-events-none pb-8 text-[9px] text-slate-300/40 dark:text-slate-800/20">
                                    <div class="border-b border-dashed border-slate-150 dark:border-slate-800 w-full" />
                                    <div class="border-b border-dashed border-slate-150 dark:border-slate-800 w-full" />
                                    <div class="border-b border-dashed border-slate-150 dark:border-slate-800 w-full" />
                                </div>

                                <div
                                    v-for="r in props.revenueTrend"
                                    :key="r.month"
                                    class="group relative flex flex-1 min-w-[36px] flex-col items-center gap-1.5"
                                >
                                    <!-- Growth bubble -->
                                    <span
                                        v-if="r.yoy_growth != null"
                                        class="rounded-md px-1 py-0.5 text-[9px] font-black"
                                        :class="r.yoy_growth >= 0 ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/20' : 'bg-rose-50 text-rose-600 dark:bg-rose-950/20'"
                                    >
                                        {{ r.yoy_growth > 0 ? '+' : '' }}{{ r.yoy_growth }}%
                                    </span>
                                    
                                    <!-- Gradient bar -->
                                    <div
                                        class="w-full rounded-t-lg bg-gradient-to-t from-indigo-500/80 to-indigo-600 transition-all duration-300 group-hover:from-indigo-600 group-hover:to-indigo-500 group-hover:shadow-[0_0_8px_rgba(99,102,241,0.25)]"
                                        :style="{
                                            height: (r.revenue / maxRevenue) * 110 + 'px',
                                            minHeight: '4px',
                                        }"
                                    />

                                    <!-- Label -->
                                    <span class="font-mono text-[9px] font-bold text-slate-400">{{ r.month.slice(5) }}/{{ r.month.slice(2, 4) }}</span>

                                    <!-- Chart Tooltip -->
                                    <div class="absolute bottom-full left-1/2 z-30 mb-2 hidden -translate-x-1/2 rounded-2xl border border-slate-200/80 bg-white/95 p-3 text-[10px] font-bold text-slate-800 shadow-xl backdrop-blur-md dark:border-slate-850 dark:bg-slate-950/95 dark:text-slate-200 group-hover:block whitespace-nowrap">
                                        <p class="border-b border-slate-100 pb-1 text-center font-black text-slate-400 dark:border-slate-800">Tháng {{ r.month }}</p>
                                        <div class="mt-1.5 space-y-1 text-left">
                                            <p class="flex justify-between gap-4">Doanh thu: <span class="font-mono text-indigo-600 dark:text-indigo-400">{{ vnd(r.revenue) }}</span></p>
                                            <p class="flex justify-between gap-4">Số đơn: <span class="font-mono">{{ formatNum(r.orders) }}</span></p>
                                            <p class="flex justify-between gap-4">Đơn TB: <span class="font-mono">{{ vnd(r.avg_order) }}</span></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div v-if="!props.revenueTrend?.length" class="flex flex-col items-center justify-center py-10 text-slate-400">
                                <Info class="size-8 opacity-25 mb-1" />
                                <p class="text-xs font-bold">Chưa có dữ liệu xu hướng doanh thu</p>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- LTV/CAC Health Gauge -->
                    <Card class="border-slate-100 shadow-md dark:border-slate-800/80">
                        <CardContent class="flex h-full min-h-[220px] flex-col items-center justify-between p-6">
                            <div class="w-full text-left">
                                <h3 class="text-sm font-black tracking-wider text-slate-500 uppercase flex items-center gap-1.5">
                                    <Scale class="size-4.5 text-violet-500" />
                                    Sức khỏe LTV/CAC
                                </h3>
                                <p class="text-[10px] font-bold text-slate-400 uppercase">Chỉ số đo lường hiệu quả chi phí & giá trị KH</p>
                            </div>

                            <div class="relative mt-4 flex aspect-[4/3] w-full max-w-[180px] items-center justify-center">
                                <svg viewBox="0 0 200 130" class="h-full w-full overflow-visible">
                                    <defs>
                                        <linearGradient id="gaugeGrad" x1="0" y1="0" x2="1" y2="0">
                                            <stop offset="0%" stop-color="#ef4444" />
                                            <stop offset="33%" stop-color="#f59e0b" />
                                            <stop offset="75%" stop-color="#10b981" />
                                        </linearGradient>
                                    </defs>
                                    <path d="M 20 110 A 80 80 0 0 1 180 110" fill="none" stroke="#e2e8f0" stroke-width="12" stroke-linecap="round" class="dark:stroke-slate-800" />
                                    <path d="M 20 110 A 80 80 0 0 1 180 110" fill="none" stroke="url(#gaugeGrad)" stroke-width="12" stroke-linecap="round" stroke-dasharray="251.2" stroke-dashoffset="0" />
                                    <circle cx="100" cy="110" r="7" class="fill-slate-800 dark:fill-slate-100" />
                                    <line x1="100" y1="110" x2="100" y2="45" stroke="currentColor" stroke-width="4.5" stroke-linecap="round" class="origin-[100px_110px] text-slate-800 transition-transform duration-1000 ease-out dark:text-slate-200" :style="{ transform: `rotate(${needleRotation}deg)`, transformOrigin: '100px 110px' }" />
                                </svg>

                                <!-- Ratio overlay text -->
                                <div class="absolute bottom-1 flex flex-col items-center text-center">
                                    <p class="text-3xl font-black tracking-tight" :class="props.unitEconomics?.ltv_cac_ratio >= 3 ? 'text-emerald-500' : props.unitEconomics?.ltv_cac_ratio >= 1.5 ? 'text-amber-500' : 'text-rose-500'">
                                        {{ props.unitEconomics?.ltv_cac_ratio }}x
                                    </p>
                                    <span
                                        class="mt-1 rounded-full border px-2.5 py-0.5 text-[9px] font-black tracking-wider uppercase shadow-2xs"
                                        :class="props.unitEconomics?.ltv_cac_ratio >= 3 ? 'border-emerald-100 bg-emerald-50 text-emerald-700 dark:bg-emerald-950/20 dark:text-emerald-400' : props.unitEconomics?.ltv_cac_ratio >= 1.5 ? 'border-amber-100 bg-amber-50 text-amber-700 dark:bg-amber-950/20 dark:text-amber-400' : 'border-rose-100 bg-rose-50 text-rose-700 dark:bg-rose-950/20 dark:text-rose-455'"
                                    >
                                        {{ props.unitEconomics?.ltv_cac_ratio >= 3 ? 'Khỏe mạnh (>=3x)' : props.unitEconomics?.ltv_cac_ratio >= 1.5 ? 'Cần chú ý' : 'Nguy cấp (<1.5x)' }}
                                    </span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- NEW FEATURE: WHAT-IF SIMULATOR ENGINE CARD -->
                <Card class="w-full border-slate-100 shadow-md dark:border-slate-800/80">
                    <CardContent class="p-6">
                        <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-4 dark:border-slate-800">
                            <div>
                                <h3 class="text-sm font-black tracking-wider text-slate-500 uppercase flex items-center gap-1.5">
                                    <Sliders class="size-4.5 text-indigo-600" />
                                    Bộ giả lập Mô phỏng Kinh tế Đơn vị (What-if Simulation)
                                </h3>
                                <p class="text-[10px] font-bold text-slate-400 uppercase">Thay đổi các thông số giả định để ước tính giá trị trọn đời LTV & hệ số hiệu quả Marketing</p>
                            </div>
                            <Button
                                v-if="isSimulating"
                                @click="resetSimulation"
                                variant="outline"
                                class="h-8 cursor-pointer rounded-xl border-slate-200 text-xs font-bold text-slate-500 hover:bg-slate-50 active:scale-95"
                            >
                                <RefreshCw class="mr-1.5 size-3.5" />Đặt lại thông số gốc
                            </Button>
                        </div>

                        <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                            <!-- Left 2 columns: sliders -->
                            <div class="lg:col-span-2 space-y-6">
                                <!-- Slider 1: Average Order Value (AOV) -->
                                <div class="space-y-2">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="font-extrabold text-slate-600 dark:text-slate-350">Giá trị đơn hàng trung bình (AOV):</span>
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono font-black text-indigo-600 dark:text-indigo-400">{{ vnd(simAov) }}</span>
                                            <span v-if="props.unitEconomics?.avg_order_value" class="text-[10px] font-bold" :class="simAov > props.unitEconomics.avg_order_value ? 'text-emerald-500' : simAov < props.unitEconomics.avg_order_value ? 'text-rose-500' : 'text-slate-400'">
                                                ({{ simAov > props.unitEconomics.avg_order_value ? '+' : '' }}{{ Math.round(((simAov - props.unitEconomics.avg_order_value)/props.unitEconomics.avg_order_value)*100) }}%)
                                            </span>
                                        </div>
                                    </div>
                                    <input
                                        v-model.number="simAov"
                                        @input="startSimulationValChange"
                                        type="range"
                                        :min="Math.round((props.unitEconomics?.avg_order_value ?? 10000) * 0.4)"
                                        :max="Math.round((props.unitEconomics?.avg_order_value ?? 100000) * 2.0)"
                                        step="1000"
                                        class="h-1.5 w-full cursor-pointer appearance-none rounded-lg bg-slate-100 dark:bg-slate-800 accent-indigo-600"
                                    />
                                </div>

                                <!-- Slider 2: Purchase Frequency (Orders per customer) -->
                                <div class="space-y-2">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="font-extrabold text-slate-600 dark:text-slate-350">Tần suất mua hàng trung bình (Đơn/Khách):</span>
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono font-black text-indigo-600 dark:text-indigo-400">{{ simFrequency }} lần</span>
                                            <span v-if="props.unitEconomics?.avg_orders_per_customer" class="text-[10px] font-bold" :class="simFrequency > props.unitEconomics.avg_orders_per_customer ? 'text-emerald-500' : simFrequency < props.unitEconomics.avg_orders_per_customer ? 'text-rose-500' : 'text-slate-400'">
                                                ({{ simFrequency > props.unitEconomics.avg_orders_per_customer ? '+' : '' }}{{ Math.round(((simFrequency - props.unitEconomics.avg_orders_per_customer)/props.unitEconomics.avg_orders_per_customer)*100) }}%)
                                            </span>
                                        </div>
                                    </div>
                                    <input
                                        v-model.number="simFrequency"
                                        @input="startSimulationValChange"
                                        type="range"
                                        min="1"
                                        max="15"
                                        step="0.1"
                                        class="h-1.5 w-full cursor-pointer appearance-none rounded-lg bg-slate-100 dark:bg-slate-800 accent-indigo-600"
                                    />
                                </div>

                                <!-- Slider 3: Customer Acquisition Cost (CAC) -->
                                <div class="space-y-2">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="font-extrabold text-slate-600 dark:text-slate-350">Chi phí tiếp thị tìm một KH mới (CAC):</span>
                                        <div class="flex items-center gap-2">
                                            <span class="font-mono font-black text-indigo-600 dark:text-indigo-400">{{ vnd(simCac) }}</span>
                                            <span v-if="props.unitEconomics?.cac" class="text-[10px] font-bold" :class="simCac < props.unitEconomics.cac ? 'text-emerald-500' : simCac > props.unitEconomics.cac ? 'text-rose-500' : 'text-slate-400'">
                                                ({{ simCac > props.unitEconomics.cac ? '+' : '' }}{{ Math.round(((simCac - props.unitEconomics.cac)/props.unitEconomics.cac)*100) }}%)
                                            </span>
                                        </div>
                                    </div>
                                    <input
                                        v-model.number="simCac"
                                        @input="startSimulationValChange"
                                        type="range"
                                        min="5000"
                                        max="500000"
                                        step="1000"
                                        class="h-1.5 w-full cursor-pointer appearance-none rounded-lg bg-slate-100 dark:bg-slate-800 accent-indigo-600"
                                    />
                                </div>
                            </div>

                            <!-- Right column: Simulated Results comparison panel -->
                            <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/50 p-5 dark:border-slate-800 dark:bg-slate-900/10 flex flex-col justify-between">
                                <div class="space-y-4">
                                    <p class="text-xs font-black tracking-wider text-slate-400 uppercase">Giá trị mô phỏng dự kiến</p>
                                    
                                    <!-- LTV result -->
                                    <div>
                                        <span class="text-[10px] font-bold text-slate-400">GIÁ TRỊ TRỌN ĐỜI KH (LTV DỰ KIẾN):</span>
                                        <p class="font-mono text-2xl font-black text-indigo-600 dark:text-indigo-400">{{ vnd(simLtv) }}</p>
                                        <p class="text-[10px] font-bold" :class="ltvDiffPct >= 0 ? 'text-emerald-500' : 'text-rose-500'">
                                            {{ ltvDiffPct >= 0 ? 'Tăng' : 'Giảm' }} {{ Math.abs(ltvDiffPct).toFixed(1) }}% so với thực tế ({{ vnd(props.unitEconomics?.ltv || 0) }})
                                        </p>
                                    </div>

                                    <!-- LTV/CAC ratio result -->
                                    <div>
                                        <span class="text-[10px] font-bold text-slate-400">HIỆU QUẢ MARKETING LTV/CAC:</span>
                                        <p class="font-mono text-2xl font-black" :class="simLtvCacRatio >= 3 ? 'text-emerald-500' : simLtvCacRatio >= 1.5 ? 'text-amber-500' : 'text-rose-500'">
                                            {{ simLtvCacRatio }}x
                                        </p>
                                        <p class="text-[10px] font-bold" :class="ratioDiff >= 0 ? 'text-emerald-500' : 'text-rose-500'">
                                            {{ ratioDiff >= 0 ? 'Tăng' : 'Giảm' }} {{ Math.abs(ratioDiff).toFixed(2) }}x so với thực tế ({{ props.unitEconomics?.ltv_cac_ratio || 0 }}x)
                                        </p>
                                    </div>
                                </div>

                                <!-- Action button -->
                                <div class="mt-4 pt-3 border-t border-slate-200 dark:border-slate-800">
                                    <span
                                        class="rounded-md px-2 py-0.5 text-[10px] font-black uppercase tracking-wider block text-center"
                                        :class="simLtvCacRatio >= 3 ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/20 dark:text-emerald-400' : simLtvCacRatio >= 1.5 ? 'bg-amber-50 text-amber-700 dark:bg-amber-950/20' : 'bg-rose-50 text-rose-700 dark:bg-rose-950/20'"
                                    >
                                        Khuyến nghị: {{ simLtvCacRatio >= 3 ? 'Mô hình bền vững. Đẩy mạnh quy mô marketing' : simLtvCacRatio >= 1.5 ? 'Mô hình trung bình. Cần tối ưu CAC hoặc tăng AOV' : 'Mô hình nguy hiểm. Ngừng chạy QC và cơ cấu lại chi phí!' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- 2. COHORT RETENTION TAB (Mixpanel style heatmap) -->
            <div v-if="activeTab === 'cohort'" class="space-y-6">
                <Card class="border-slate-100 shadow-md dark:border-slate-800/80">
                    <CardContent class="p-6">
                        <div class="mb-4">
                            <h3 class="text-sm font-black tracking-wider text-slate-500 uppercase flex items-center gap-1.5">
                                <Users class="size-4.5 text-indigo-500" />
                                Tỷ lệ giữ chân khách hàng (Cohort Retention - 6 tháng)
                            </h3>
                            <p class="text-[10px] font-bold text-slate-400 uppercase">% lượng khách hàng tiếp tục đặt đơn trong các tháng tiếp theo kể từ tháng đăng ký tài khoản đầu tiên.</p>
                        </div>

                        <div class="overflow-x-auto rounded-xl border border-slate-150 dark:border-slate-800">
                            <table class="w-full text-xs">
                                <thead>
                                    <tr class="bg-slate-50/50 text-slate-500 border-b border-slate-150 dark:bg-slate-900/10 dark:border-slate-850">
                                        <th class="px-4 py-3 text-left font-black uppercase">Tháng đăng ký (Cohort)</th>
                                        <th class="px-4 py-3 text-center font-black uppercase">Khách mới (Size)</th>
                                        <th v-for="m in [0, 1, 2, 3, 4, 5]" :key="m" class="px-4 py-3 text-center font-black uppercase">Tháng {{ m }} (M{{ m }})</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/80">
                                    <!-- Averages row -->
                                    <tr class="bg-indigo-50/20 font-black text-indigo-950 dark:bg-indigo-950/10 dark:text-indigo-400">
                                        <td class="px-4 py-3 font-black">Trung bình tỷ lệ giữ chân</td>
                                        <td class="px-4 py-3 text-center">—</td>
                                        <td v-for="(avg, idx) in cohortAverages" :key="idx" class="px-4 py-3 text-center font-mono">
                                            {{ avg }}%
                                        </td>
                                    </tr>

                                    <tr v-for="c in props.cohorts" :key="c.cohort">
                                        <td class="px-4 py-3 font-bold text-slate-700 dark:text-slate-200">{{ c.cohort }}</td>
                                        <td class="px-4 py-3 text-center font-mono font-bold text-slate-600 dark:text-slate-350">{{ formatNum(c.size) }}</td>
                                        <td v-for="m in [0, 1, 2, 3, 4, 5]" :key="m" class="p-1.5 text-center">
                                            <div
                                                v-if="c.retention[m]"
                                                class="flex h-9 items-center justify-center rounded-xl text-[11px] font-black transition-all hover:scale-103 shadow-2xs"
                                                :style="{
                                                    backgroundColor: `rgba(99, 102, 241, ${Math.max(0.05, c.retention[m].rate / 100)})`,
                                                    color: c.retention[m].rate >= 35 ? '#ffffff' : 'currentColor',
                                                    border: c.retention[m].rate > 0 ? '1px solid rgba(99, 102, 241, 0.15)' : 'none',
                                                }"
                                            >
                                                {{ c.retention[m].rate }}%
                                            </div>
                                            <div v-else class="flex h-9 items-center justify-center rounded-xl bg-slate-50/20 text-slate-300 dark:bg-slate-900/10 dark:text-slate-700">—</div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div v-if="!props.cohorts?.length" class="flex flex-col items-center justify-center py-10 text-slate-400">
                            <Info class="size-8 opacity-25 mb-1" />
                            <p class="text-xs font-bold">Chưa có dữ liệu Cohort giữ chân khách hàng</p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- 3. BREAK-EVEN ANALYSIS TAB -->
            <div v-if="activeTab === 'breakeven'" class="space-y-6">
                <!-- Visual break-even graph and numerical cards grid -->
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    
                    <!-- Costs list card -->
                    <Card class="border-slate-100 shadow-md dark:border-slate-800/80">
                        <CardContent class="p-6 space-y-4">
                            <div class="mb-4">
                                <h3 class="text-sm font-black tracking-wider text-slate-500 uppercase flex items-center gap-2">
                                    <component
                                        :is="props.breakEven?.is_profitable ? CheckCircle2 : XCircle"
                                        :class="props.breakEven?.is_profitable ? 'text-emerald-500 animate-pulse' : 'text-rose-500'"
                                        class="size-5"
                                    />
                                    Hiệu suất Điểm hòa vốn
                                </h3>
                                <p class="text-[10px] font-bold text-slate-400 uppercase">Trạng thái kinh doanh trong kỳ {{ days }} ngày</p>
                            </div>

                            <div class="divide-y divide-slate-100 text-xs dark:divide-slate-800">
                                <div class="flex justify-between py-2.5">
                                    <span class="font-bold text-slate-400">Tổng doanh thu thực tế</span>
                                    <span class="font-mono font-black text-slate-700 dark:text-slate-200">{{ vnd(props.breakEven?.revenue ?? 0) }}</span>
                                </div>
                                <div class="flex justify-between py-2.5">
                                    <span class="font-bold text-slate-400">Chi phí biến đổi (Opex biến động)</span>
                                    <span class="font-mono font-semibold text-slate-600 dark:text-slate-300">{{ vnd(props.breakEven?.variable_cost ?? 0) }}</span>
                                </div>
                                <div class="flex justify-between py-2.5">
                                    <span class="font-bold text-slate-400">Chi phí cố định (Mặt bằng, Lương cố định)</span>
                                    <span class="font-mono font-semibold text-slate-600 dark:text-slate-300">{{ vnd(props.breakEven?.fixed_cost ?? 0) }}</span>
                                </div>
                                <div class="flex justify-between py-2.5 border-t pt-3">
                                    <span class="font-extrabold text-indigo-600 dark:text-indigo-400">Lợi nhuận đóng góp mỗi đơn (AOV - VC)</span>
                                    <span class="font-mono font-black text-indigo-650 dark:text-indigo-400">{{ vnd(props.breakEven?.contribution_margin ?? 0) }}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Break-even status progress & target -->
                    <Card class="border-slate-100 shadow-md dark:border-slate-800/80">
                        <CardContent class="p-6 space-y-4">
                            <div class="mb-4">
                                <h3 class="text-sm font-black tracking-wider text-slate-500 uppercase">Điểm hòa vốn kỳ này</h3>
                                <p class="text-[10px] font-bold text-slate-400 uppercase">Số lượng đơn hàng tối thiểu để bắt đầu có lãi</p>
                            </div>

                            <div class="space-y-4">
                                <div class="flex justify-between items-baseline">
                                    <span class="text-xs font-bold text-slate-400">Số đơn tối thiểu:</span>
                                    <span class="font-mono text-xl font-black text-indigo-600 dark:text-indigo-400">{{ formatNum(props.breakEven?.break_even_orders ?? 0) }} đơn</span>
                                </div>
                                <div class="flex justify-between items-baseline">
                                    <span class="text-xs font-bold text-slate-400">Tương đương Doanh thu:</span>
                                    <span class="font-mono font-bold text-slate-700 dark:text-slate-200">{{ vnd(props.breakEven?.break_even_revenue ?? 0) }}</span>
                                </div>
                                <div class="flex justify-between items-baseline">
                                    <span class="text-xs font-bold text-slate-400">Thực tế đã đạt:</span>
                                    <span class="font-mono font-bold text-slate-700 dark:text-slate-200">{{ props.breakEven?.total_orders }} đơn</span>
                                </div>

                                <!-- Progress bar -->
                                <div class="space-y-1.5">
                                    <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                        <div
                                            :class="['h-full rounded-full', props.breakEven?.is_profitable ? 'bg-emerald-500' : 'bg-amber-500']"
                                            :style="{
                                                width: Math.min(100, (props.breakEven?.break_even_orders ?? 0) > 0 ? ((props.breakEven?.total_orders ?? 0) / (props.breakEven?.break_even_orders ?? 1)) * 100 : 0) + '%',
                                            }"
                                        />
                                    </div>
                                    <p class="text-[10px] font-bold text-slate-400 uppercase">
                                        {{
                                            props.breakEven?.is_profitable
                                                ? 'Đã vượt điểm hòa vốn ' + ((props.breakEven?.total_orders ?? 0) - (props.breakEven?.break_even_orders ?? 0)) + ' đơn!'
                                                : 'Còn thiếu ' + ((props.breakEven?.break_even_orders ?? 0) - (props.breakEven?.total_orders ?? 0)) + ' đơn (~' + props.breakEven?.break_even_days + ' ngày)'
                                        }}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- NEW FEATURE: VISUAL BREAK-EVEN COORDINATE PLOT -->
                    <Card class="border-slate-100 shadow-md dark:border-slate-800/80">
                        <CardContent class="p-6">
                            <div class="mb-4">
                                <h3 class="text-sm font-black tracking-wider text-slate-500 uppercase">Đồ thị hòa vốn (Break-even Chart)</h3>
                                <p class="text-[10px] font-bold text-slate-400 uppercase">Giao điểm giữa Doanh thu và Chi phí</p>
                            </div>

                            <div class="relative w-full aspect-[4/3] flex items-center justify-center">
                                <svg viewBox="0 0 400 240" class="w-full h-full overflow-visible">
                                    <!-- Axes -->
                                    <line x1="55" y1="205" x2="380" y2="205" stroke="#94a3b8" stroke-width="1.5" stroke-linecap="round" />
                                    <line x1="55" y1="20" x2="55" y2="205" stroke="#94a3b8" stroke-width="1.5" stroke-linecap="round" />
                                    
                                    <!-- Labels Y axis -->
                                    <text x="45" y="208" text-anchor="end" font-size="8" font-weight="bold" fill="#94a3b8" class="font-mono">0</text>
                                    <text x="45" y="118" text-anchor="end" font-size="8" font-weight="bold" fill="#94a3b8" class="font-mono">{{ formatNum(Math.round(beMaxRevenue/2000000)) }}M</text>
                                    <text x="45" y="28" text-anchor="end" font-size="8" font-weight="bold" fill="#94a3b8" class="font-mono">{{ formatNum(Math.round(beMaxRevenue/1000000)) }}M</text>

                                    <!-- Fixed Costs line -->
                                    <line :x1="fixedCostStartCoords.x" :y1="fixedCostStartCoords.y" :x2="fixedCostEndCoords.x" :y2="fixedCostEndCoords.y" stroke="#94a3b8" stroke-width="1" stroke-dasharray="3,3" />
                                    <text :x="fixedCostEndCoords.x" :y="fixedCostEndCoords.y - 4" text-anchor="end" font-size="7" font-weight="black" fill="#94a3b8" class="uppercase">Cố định</text>

                                    <!-- Total Costs Line (Fixed + Variable) -->
                                    <line :x1="totalCostStartCoords.x" :y1="totalCostStartCoords.y" :x2="totalCostEndCoords.x" :y2="totalCostEndCoords.y" stroke="#f43f5e" stroke-width="2" />
                                    <text :x="totalCostEndCoords.x" :y="totalCostEndCoords.y - 4" text-anchor="end" font-size="7" font-weight="black" fill="#f43f5e" class="uppercase">Tổng chi phí</text>

                                    <!-- Revenue Line -->
                                    <line :x1="revenueStartCoords.x" :y1="revenueStartCoords.y" :x2="revenueEndCoords.x" :y2="revenueEndCoords.y" stroke="#10b981" stroke-width="2" />
                                    <text :x="revenueEndCoords.x" :y="revenueEndCoords.y - 4" text-anchor="end" font-size="7" font-weight="black" fill="#10b981" class="uppercase">Doanh thu</text>

                                    <!-- Break-even Point indicator -->
                                    <line :x1="bePointCoords.x" :y1="bePointCoords.y" :x2="bePointCoords.x" y2="205" stroke="#6366f1" stroke-width="1" stroke-dasharray="2,2" />
                                    <circle :cx="bePointCoords.x" :cy="bePointCoords.y" r="5" fill="#6366f1" class="shadow-sm animate-pulse" />
                                    <text :x="bePointCoords.x" :y="bePointCoords.y - 8" text-anchor="middle" font-size="8" font-weight="black" fill="#6366f1">Hòa vốn</text>

                                    <!-- Actual point indicator -->
                                    <circle :cx="actualPointCoords.x" :cy="actualPointCoords.y" r="5" :fill="props.breakEven?.is_profitable ? '#10b981' : '#f59e0b'" />
                                    <text :x="actualPointCoords.x" :y="actualPointCoords.y - 8" text-anchor="middle" font-size="8" font-weight="black" :fill="props.breakEven?.is_profitable ? '#10b981' : '#f59e0b'">Thực tế</text>
                                </svg>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- 4. INDUSTRY BENCHMARKS TAB (Visual Gauges scale) -->
            <div v-if="activeTab === 'benchmark'" class="space-y-6">
                <Card class="border-slate-100 shadow-md dark:border-slate-800/80">
                    <CardContent class="p-6">
                        <div class="mb-6">
                            <h3 class="text-sm font-black tracking-wider text-slate-500 uppercase flex items-center gap-1.5">
                                <Building2 class="size-4.5 text-indigo-500" />
                                So sánh đối chuẩn Benchmark ngành F&B Việt Nam
                            </h3>
                            <p class="text-[10px] font-bold text-slate-400 uppercase">Đối chiếu hiệu suất kinh doanh của bạn so với biên độ trung bình trong ngành nhà hàng.</p>
                        </div>

                        <div class="space-y-8">
                            <div
                                v-for="b in props.benchmarks"
                                :key="b.metric"
                                class="space-y-2 border-b border-slate-100 pb-5 last:border-0 last:pb-0 dark:border-slate-800/80"
                            >
                                <div class="flex items-center justify-between text-xs sm:text-sm">
                                    <div>
                                        <p class="font-extrabold text-slate-700 dark:text-slate-200">{{ b.metric }}</p>
                                        <p class="text-[10px] font-bold text-slate-400">Biên trung bình ngành: {{ b.industry_low }}{{ b.unit }} — {{ b.industry_high }}{{ b.unit }}</p>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="font-mono text-base font-black text-slate-800 dark:text-slate-100">
                                            {{ typeof b.value === 'number' && b.unit === 'đ' ? b.value.toLocaleString() : b.value }}{{ b.unit }}
                                        </span>
                                        <Badge :class="statusColor[b.status]" class="text-[10px] font-extrabold uppercase tracking-wider rounded-md border-0">{{ statusLabel[b.status] }}</Badge>
                                    </div>
                                </div>

                                <!-- Linear visual benchmark position bar -->
                                <div class="relative py-3.5">
                                    <!-- Back track bar -->
                                    <div class="h-2 w-full rounded-full bg-slate-100 dark:bg-slate-800" />
                                    
                                    <!-- Industry average boundary highlight range -->
                                    <div
                                        class="absolute top-3.5 h-2 bg-indigo-200 dark:bg-indigo-900/60"
                                        :style="{
                                            left: getBenchmarkPosition(b).lowPos + '%',
                                            width: (getBenchmarkPosition(b).highPos - getBenchmarkPosition(b).lowPos) + '%',
                                        }"
                                    />

                                    <!-- Pointer for actual restaurant value -->
                                    <div
                                        class="absolute top-1 flex flex-col items-center"
                                        :style="{ left: `calc(${getBenchmarkPosition(b).valPos}% - 6px)` }"
                                    >
                                        <div class="h-3 w-3 rounded-full border-2 border-white bg-indigo-650 shadow-md animate-pulse dark:border-slate-900 dark:bg-indigo-400" />
                                        <span class="text-[8px] font-black text-indigo-600 dark:text-indigo-400 mt-1 uppercase">Của bạn</span>
                                    </div>

                                    <!-- Range indicators -->
                                    <div class="flex justify-between text-[8px] font-black text-slate-400 uppercase pt-2">
                                        <span>Kém</span>
                                        <span>Trung bình ngành</span>
                                        <span>Tốt</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="!props.benchmarks?.length" class="flex flex-col items-center justify-center py-10 text-slate-400">
                            <Info class="size-8 opacity-25 mb-1" />
                            <p class="text-xs font-bold">Chưa có dữ liệu Benchmark đối chuẩn</p>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </Deferred>
    </div>
</template>
