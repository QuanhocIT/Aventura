<script setup lang="ts">
import { Link, Deferred } from '@inertiajs/vue3';
import { TrendingUp, Sparkles, BarChart3 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useFeatureGate } from '@/composables/useFeatureGate';

const { can } = useFeatureGate();

interface RevenueDay {
    date: string;
    revenue: number;
    orders: number;
    is_forecast?: boolean;
}

interface ForecastData {
    amount: number;
    confidence: string;
    confidence_label: string;
    samples: number;
    day_label: string;
    trend_factor: number;
}

const props = defineProps<{
    revenueChartData: RevenueDay[] | undefined;
    forecastData: ForecastData | null | undefined;
}>();

const activeHoverIndex = ref<number | null>(null);

const hasForecast = computed(() => {
    return (
        can('ai_forecasting') &&
        props.revenueChartData?.some((d) => d.is_forecast)
    );
});

const revenueChartList = computed(() => props.revenueChartData ?? []);

const maxRevenue = computed(() => {
    const vals = revenueChartList.value.map((d) => Number(d?.revenue) || 0);

    return Math.max(...vals, 100000);
});

const maxOrders = computed(() => {
    const list = revenueChartList.value.map((d) => Number(d?.orders) || 0);

    return Math.max(...list, 5);
});

// Dynamic layout parameters for SVG viewBox (0 0 700 210)
const SVG_WIDTH = 700;
const CHART_LEFT = 50;
const CHART_RIGHT = 660;
const CHART_WIDTH = CHART_RIGHT - CHART_LEFT;
const BASE_Y = 165;
const MAX_BAR_HEIGHT = 135;

const barStep = computed(() => {
    const count = revenueChartList.value.length;

    if (count === 0) {
        return CHART_WIDTH;
    }

    return CHART_WIDTH / count;
});

const barWidth = computed(() => {
    return Math.min(barStep.value * 0.45, 34);
});

const calculatedItems = computed(() => {
    const step = barStep.value;
    const bw = barWidth.value;
    const maxRev = maxRevenue.value || 1;
    const maxOrd = maxOrders.value || 1;

    return revenueChartList.value.map((day, i) => {
        const rev = Number(day?.revenue) || 0;
        const ord = Number(day?.orders) || 0;

        const centerX = CHART_LEFT + i * step + step / 2;
        const barX = centerX - bw / 2;
        const barHeight = Math.max((rev / maxRev) * MAX_BAR_HEIGHT, rev > 0 ? 4 : 0);
        const barY = BASE_Y - barHeight;

        const lineY = BASE_Y - (ord / maxOrd) * MAX_BAR_HEIGHT;

        return {
            ...day,
            index: i,
            centerX,
            barX,
            barY,
            barWidth: bw,
            barHeight,
            lineY,
        };
    });
});

const ordersLinePath = computed(() => {
    const list = calculatedItems.value;

    if (list.length === 0) {
        return '';
    }

    return list
        .map((item, i) => `${i === 0 ? 'M' : 'L'} ${item.centerX} ${item.lineY}`)
        .join(' ');
});

function formatMoneyFull(v: number): string {
    if (v === 0) {
        return '0đ';
    }

    return new Intl.NumberFormat('vi-VN').format(v) + 'đ';
}

function formatMoneyShort(v: number): string {
    if (v >= 1_000_000) {
        return (v / 1_000_000).toFixed(1).replace('.0', '') + 'M';
    }

    if (v >= 1_000) {
        return (v / 1_000).toFixed(0) + 'k';
    }

    return String(v);
}
</script>

<template>
    <Deferred data="revenueChartData">
        <template #fallback>
            <Card class="relative overflow-hidden border border-border bg-card text-card-foreground shadow-sm">
                <CardContent class="h-[350px] w-full animate-pulse flex flex-col items-center justify-center p-6 gap-3">
                    <BarChart3 class="size-8 text-slate-400 animate-pulse" />
                    <span class="text-xs text-slate-400 font-bold tracking-tight">Đang tải phân tích doanh thu...</span>
                </CardContent>
            </Card>
        </template>

        <Card class="relative overflow-hidden border border-border bg-card text-card-foreground shadow-sm transition-all duration-300 hover:shadow-md">
            <CardHeader class="border-b border-border/50 bg-slate-50/40 pb-3 dark:bg-slate-900/20">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <CardTitle class="flex items-center gap-2 text-base font-black text-foreground">
                            <TrendingUp class="size-4 text-violet-500" />
                            {{
                                hasForecast
                                    ? 'Doanh thu 7 ngày qua + 7 ngày dự báo'
                                    : 'Phân tích doanh thu 7 ngày qua'
                            }}
                        </CardTitle>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            <span v-if="hasForecast">
                                Cột đặc = Thực tế · Cột viền đứt =
                                <span class="font-bold text-indigo-500">AI dự báo</span>
                            </span>
                            <span v-else>
                                Theo dõi doanh thu thực tế và tổng số lượng đơn hàng theo ngày.
                            </span>
                            <span
                                v-if="hasForecast && forecastData?.confidence_label"
                                class="ml-1.5 inline-flex items-center gap-1 rounded-full bg-indigo-500/10 px-2 py-0.5 font-mono text-[10px] font-extrabold text-indigo-600 dark:text-indigo-400"
                            >
                                <Sparkles class="size-3" /> Độ tin cậy: {{ forecastData.confidence_label }}
                            </span>
                        </p>
                    </div>

                    <div class="flex items-center gap-4 text-xs font-bold">
                        <span class="flex items-center gap-1.5 text-indigo-500">
                            <span class="h-3 w-3 rounded-xs bg-indigo-500"></span>
                            Doanh thu
                        </span>
                        <span class="flex items-center gap-1.5 text-emerald-500">
                            <span class="h-3 w-3 rounded-full border-2 border-emerald-500 bg-card"></span>
                            Đơn hàng
                        </span>
                    </div>
                </div>
            </CardHeader>

            <CardContent class="pt-6 pb-4">
                <div v-if="revenueChartList.length" class="relative">
                    <!-- Floating Tooltip Box -->
                    <div
                        v-if="activeHoverIndex !== null && calculatedItems[activeHoverIndex]"
                        class="pointer-events-none absolute z-20 rounded-xl border border-slate-700/60 bg-slate-900/95 p-3 text-xs text-white shadow-2xl backdrop-blur-md transition-all duration-150"
                        :style="{
                            left: `${(calculatedItems[activeHoverIndex].centerX / SVG_WIDTH) * 100}%`,
                            transform: 'translateX(-50%)',
                            top: '-15px',
                        }"
                    >
                        <p class="mb-1 border-b border-slate-700/80 pb-1 font-bold text-slate-300">
                            Ngày {{ calculatedItems[activeHoverIndex].date }}
                            <span v-if="calculatedItems[activeHoverIndex].is_forecast" class="ml-1 text-indigo-400">(AI dự báo)</span>
                        </p>
                        <p class="flex justify-between gap-4">
                            <span class="text-slate-400">Doanh thu:</span>
                            <span class="font-mono font-bold text-indigo-400">{{ formatMoneyFull(calculatedItems[activeHoverIndex].revenue) }}</span>
                        </p>
                        <p class="mt-0.5 flex justify-between gap-4">
                            <span class="text-slate-400">Đơn hàng:</span>
                            <span class="font-mono font-bold text-emerald-400">{{ calculatedItems[activeHoverIndex].orders }} đơn</span>
                        </p>
                    </div>

                    <!-- SVG Chart -->
                    <svg viewBox="0 0 700 210" class="h-52 w-full overflow-visible select-none">
                        <defs>
                            <linearGradient id="revGrad" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#6366f1" />
                                <stop offset="100%" stop-color="#4f46e5" />
                            </linearGradient>
                            <linearGradient id="forecastGrad" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#a5b4fc" stop-opacity="0.6" />
                                <stop offset="100%" stop-color="#818cf8" stop-opacity="0.3" />
                            </linearGradient>
                        </defs>

                        <!-- Y-Axis Grid Lines -->
                        <g class="stroke-border/40" stroke-width="1" stroke-dasharray="4 4">
                            <line x1="45" y1="30" x2="665" y2="30" />
                            <line x1="45" y1="97" x2="665" y2="97" />
                            <line x1="45" y1="165" x2="665" y2="165" />
                        </g>

                        <!-- Base line -->
                        <line x1="45" y1="165" x2="665" y2="165" class="stroke-border" stroke-width="1.5" />

                        <!-- Y-Axis Labels -->
                        <text x="38" y="34" text-anchor="end" class="fill-muted-foreground font-mono text-[9px] font-bold">
                            {{ formatMoneyShort(maxRevenue) }}
                        </text>
                        <text x="38" y="101" text-anchor="end" class="fill-muted-foreground font-mono text-[9px] font-bold">
                            {{ formatMoneyShort(Math.round(maxRevenue / 2)) }}
                        </text>
                        <text x="38" y="168" text-anchor="end" class="fill-muted-foreground font-mono text-[9px] font-bold">
                            0
                        </text>

                        <!-- Interactive Bars & Columns -->
                        <g v-for="item in calculatedItems" :key="item.date">
                            <!-- Background hit target -->
                            <rect
                                :x="item.centerX - barStep / 2"
                                y="15"
                                :width="barStep"
                                height="150"
                                class="cursor-pointer fill-transparent transition-colors duration-150 hover:fill-muted/15"
                                @mouseenter="activeHoverIndex = item.index"
                                @mouseleave="activeHoverIndex = null"
                            />

                            <!-- Visible Revenue Bar -->
                            <rect
                                :x="item.barX"
                                :y="item.barY"
                                :width="item.barWidth"
                                :height="item.barHeight"
                                rx="4"
                                :fill="item.is_forecast ? 'url(#forecastGrad)' : 'url(#revGrad)'"
                                :stroke="item.is_forecast ? '#818cf8' : 'none'"
                                :stroke-width="item.is_forecast ? 1.5 : 0"
                                :stroke-dasharray="item.is_forecast ? '4 3' : 'none'"
                                class="cursor-pointer transition-all duration-200"
                                :class="{
                                    'opacity-100 brightness-115 filter drop-shadow-md': activeHoverIndex === item.index,
                                    'opacity-85': activeHoverIndex !== null && activeHoverIndex !== item.index
                                }"
                                @mouseenter="activeHoverIndex = item.index"
                                @mouseleave="activeHoverIndex = null"
                            />
                        </g>

                        <!-- Orders Line Overlay -->
                        <path
                            :d="ordersLinePath"
                            fill="none"
                            class="stroke-emerald-500"
                            stroke-width="3"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        />

                        <!-- Dots on line for each day -->
                        <g v-for="item in calculatedItems" :key="'dot-' + item.date">
                            <circle
                                :cx="item.centerX"
                                :cy="item.lineY"
                                r="4.5"
                                class="pointer-events-none fill-card stroke-emerald-500 stroke-2"
                            />
                            <circle
                                v-if="activeHoverIndex === item.index"
                                :cx="item.centerX"
                                :cy="item.lineY"
                                r="9"
                                class="pointer-events-none animate-ping fill-emerald-500/30 stroke-none"
                            />
                        </g>

                        <!-- X-Axis Labels -->
                        <text
                            v-for="item in calculatedItems"
                            :key="'txt-' + item.date"
                            :x="item.centerX"
                            y="185"
                            text-anchor="middle"
                            class="transition-colors duration-150 font-sans text-[10px] font-medium"
                            :class="[
                                activeHoverIndex === item.index
                                    ? 'fill-foreground font-extrabold'
                                    : item.is_forecast
                                      ? 'fill-indigo-400 font-bold'
                                      : 'fill-muted-foreground'
                            ]"
                        >
                            {{ item.date }}
                        </text>
                    </svg>
                </div>

                <div
                    v-else
                    class="flex flex-col items-center justify-center py-10 text-sm text-muted-foreground"
                >
                    <BarChart3 class="mb-2 size-8 text-muted-foreground/30" />
                    <p class="font-semibold">Chưa có dữ liệu doanh số tuần này</p>
                </div>

                <!-- Upgrade AI forecasting banner -->
                <div
                    v-if="!can('ai_forecasting')"
                    class="mt-3 flex items-center justify-between rounded-xl border border-dashed border-indigo-200/80 bg-indigo-50/50 p-3 text-xs dark:border-indigo-900/40 dark:bg-indigo-950/20"
                >
                    <span class="font-medium text-muted-foreground">
                        💡 Muốn dự báo doanh thu 7 ngày tiếp theo bằng Trí Tuệ Nhân Tạo (AI)?
                    </span>
                    <Link
                        href="/billing/checkout"
                        class="flex shrink-0 items-center gap-1 font-extrabold text-indigo-600 hover:underline dark:text-indigo-400"
                    >
                        Nâng cấp Doanh Nghiệp →
                    </Link>
                </div>
            </CardContent>
        </Card>
    </Deferred>
</template>

