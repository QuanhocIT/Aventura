<script setup lang="ts">
import { TrendingUp, Sparkles, BarChart3 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useFeatureGate } from '@/composables/useFeatureGate';
import { Link, Deferred } from '@inertiajs/vue3';

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
    const vals = revenueChartList.value.map((d) => d.revenue);

    return Math.max(...vals, 100000); // at least 100k
});

const maxOrders = computed(() => {
    const list = revenueChartList.value.map((d) => d.orders);

    return Math.max(...list, 5);
});

const ordersLinePath = computed(() => {
    const list = revenueChartList.value;

    if (list.length === 0) {
        return '';
    }

    return list
        .map((day, i) => {
            const x = i * 85 + 80;
            const y = 160 - (day.orders / maxOrders.value) * 125;

            return `${i === 0 ? 'M' : 'L'} ${x} ${y}`;
        })
        .join(' ');
});

function formatMoneyFull(v: number): string {
    if (v === 0) {
        return '—';
    }

    return new Intl.NumberFormat('vi-VN').format(v) + 'đ';
}
</script>

<template>
    <Deferred data="revenueChartData">
        <template #fallback>
            <Card class="relative overflow-hidden border border-border bg-card text-card-foreground shadow-sm">
                <CardContent class="h-[350px] w-full animate-pulse flex flex-col items-center justify-center p-6 gap-3">
                    <BarChart3 class="size-8 text-slate-350 dark:text-slate-650 animate-pulse" />
                    <span class="text-xs text-slate-450 font-bold tracking-tight">Đang tải phân tích doanh thu...</span>
                </CardContent>
            </Card>
        </template>
        <Card
            class="relative overflow-hidden border border-border bg-card text-card-foreground shadow-sm"
        >
        <CardHeader class="border-b border-border/50 pb-2">
            <div class="flex items-center justify-between">
                <div>
                    <CardTitle
                        class="flex items-center gap-2 text-base font-bold"
                    >
                        <TrendingUp class="size-4 text-violet-500" />
                        {{
                            hasForecast
                                ? 'Doanh thu 7 ngày qua + 7 ngày dự báo'
                                : 'Phân tích doanh thu 7 ngày qua'
                        }}
                    </CardTitle>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        <span v-if="hasForecast"
                            >Cột đặc = thực tế · Cột mờ =
                            <span class="font-medium text-indigo-400"
                                >AI dự báo</span
                            ></span
                        >
                        <span v-else
                            >Theo dõi doanh thu thực tế và tổng số lượng đơn
                            hàng theo ngày.</span
                        >
                        <span
                            v-if="hasForecast && forecastData?.confidence_label"
                            class="ml-1 inline-flex items-center gap-0.5 rounded bg-indigo-100 px-1.5 py-0.5 text-[9px] font-semibold text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-400"
                        >
                            <Sparkles class="size-2.5" /> Độ tin cậy:
                            {{ forecastData.confidence_label }}
                        </span>
                    </p>
                </div>
                <div class="flex items-center gap-4 text-xs font-semibold">
                    <span class="flex items-center gap-1.5 text-indigo-500">
                        <span class="h-3 w-3 rounded bg-indigo-500/80"></span>
                        Doanh thu
                    </span>
                    <span class="flex items-center gap-1.5 text-emerald-500">
                        <span
                            class="h-3 w-3 rounded-full border-2 border-emerald-500 bg-background"
                        ></span>
                        Đơn hàng
                    </span>
                </div>
            </div>
        </CardHeader>

        <CardContent class="pt-6">
            <div v-if="revenueChartList.length" class="relative">
                <!-- Tooltip Floating -->
                <div
                    v-if="activeHoverIndex !== null"
                    class="pointer-events-none absolute z-20 rounded-xl border border-slate-700/50 bg-slate-900/95 p-3 text-xs text-white shadow-xl backdrop-blur-md transition-all duration-150"
                    :style="{
                        left: `${(activeHoverIndex / revenueChartList.length) * 85 + 12}%`,
                        transform: 'translateX(-50%)',
                        top: '-15px',
                    }"
                >
                    <p
                        class="mb-1 border-b border-slate-700 pb-1 font-bold text-slate-300"
                    >
                        Ngày {{ revenueChartList[activeHoverIndex].date }}
                    </p>
                    <p class="flex justify-between gap-4">
                        <span class="text-slate-400">Doanh thu:</span>
                        <span class="font-mono font-bold text-indigo-400">{{
                            formatMoneyFull(
                                revenueChartList[activeHoverIndex].revenue,
                            )
                        }}</span>
                    </p>
                    <p class="mt-0.5 flex justify-between gap-4">
                        <span class="text-slate-400">Đơn hàng:</span>
                        <span class="font-mono font-bold text-emerald-400"
                            >{{
                                revenueChartList[activeHoverIndex].orders
                            }}
                            đơn</span
                        >
                    </p>
                </div>

                <!-- The Chart SVG -->
                <svg viewBox="0 0 700 200" class="h-48 w-full overflow-visible">
                    <!-- Y-Axis Grid Lines -->
                    <line
                        x1="50"
                        y1="20"
                        x2="650"
                        y2="20"
                        class="stroke-muted/30 stroke-1"
                        stroke-dasharray="4"
                    />
                    <line
                        x1="50"
                        y1="90"
                        x2="650"
                        y2="90"
                        class="stroke-muted/30 stroke-1"
                        stroke-dasharray="4"
                    />
                    <line
                        x1="50"
                        y1="160"
                        x2="650"
                        y2="160"
                        class="stroke-muted"
                        stroke-width="1.5"
                    />

                    <!-- Bars and Points -->
                    <g v-for="(day, i) in revenueChartList" :key="day.date">
                        <!-- Interactive background column area for hovering -->
                        <rect
                            :x="i * 85 + 50"
                            y="10"
                            width="60"
                            height="150"
                            class="cursor-pointer rounded fill-transparent transition-colors duration-150 hover:fill-muted/20"
                            @mouseenter="activeHoverIndex = i"
                            @mouseleave="activeHoverIndex = null"
                        />

                        <!-- Revenue Bar (forecast = mờ hơn, có stroke-dasharray) -->
                        <rect
                            :x="i * 85 + 65"
                            :y="160 - (day.revenue / maxRevenue) * 130"
                            width="30"
                            :height="
                                Math.max(
                                    (day.revenue / maxRevenue) * 130,
                                    day.revenue > 0 ? 4 : 0,
                                )
                            "
                            rx="4"
                            :class="
                                day.is_forecast
                                    ? 'fill-indigo-300/50 stroke-indigo-400 stroke-1 dark:fill-indigo-400/30'
                                    : 'pointer-events-none fill-indigo-500/80 transition-colors duration-150 hover:fill-indigo-600 dark:fill-indigo-500/60 dark:hover:fill-indigo-500'
                            "
                            :stroke-dasharray="day.is_forecast ? '4 2' : 'none'"
                        />
                    </g>

                    <!-- SVG Orders Line Trend Overlay -->
                    <path
                        :d="ordersLinePath"
                        fill="none"
                        class="stroke-emerald-500"
                        stroke-width="3"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />

                    <!-- Dots on line for each day -->
                    <g
                        v-for="(day, i) in revenueChartList"
                        :key="'dot-' + day.date"
                    >
                        <circle
                            :cx="i * 85 + 80"
                            :cy="160 - (day.orders / maxOrders) * 125"
                            r="5"
                            class="pointer-events-none fill-background stroke-emerald-500 stroke-2"
                        />
                        <!-- Glowing effect circle when hovered -->
                        <circle
                            v-if="activeHoverIndex === i"
                            :cx="i * 85 + 80"
                            :cy="160 - (day.orders / maxOrders) * 125"
                            r="9"
                            class="pointer-events-none animate-ping fill-emerald-500/30 stroke-none"
                        />
                    </g>

                    <!-- X-Axis Labels -->
                    <text
                        v-for="(day, i) in revenueChartList"
                        :key="'txt-' + day.date"
                        :x="i * 85 + 80"
                        y="182"
                        text-anchor="middle"
                        class="fill-muted-foreground font-sans text-[10px] font-medium"
                    >
                        {{ day.date }}
                    </text>
                </svg>
            </div>
            <div
                v-else
                class="flex flex-col items-center justify-center py-8 text-sm text-muted-foreground"
            >
                <BarChart3 class="mb-1.5 size-6 text-muted-foreground/30" />
                Chưa có dữ liệu doanh số tuần này.
            </div>
            <!-- Tip upgrade AI forecasting -->
            <div
                v-if="!can('ai_forecasting')"
                class="flex items-center justify-between rounded-b-2xl border-t border-dashed border-border/80 bg-slate-50/50 p-3 text-xs dark:bg-slate-900/10"
            >
                <span class="font-medium text-muted-foreground"
                    >💡 Muốn dự báo doanh thu 7 ngày tiếp theo bằng Trí Tuệ Nhân
                    Tạo?</span
                >
                <Link
                    href="/billing/history"
                    class="text-indigo-650 flex items-center gap-0.5 font-extrabold hover:underline dark:text-indigo-400"
                >
                    Nâng cấp Doanh Nghiệp →
                </Link>
            </div>
        </CardContent>
    </Card>
    </Deferred>
</template>
