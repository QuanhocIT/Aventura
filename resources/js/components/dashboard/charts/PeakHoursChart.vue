<script setup lang="ts">
import { Deferred } from '@inertiajs/vue3';
import { Activity, Flame } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface HourStat {
    hour: number;
    label: string;
    count: number;
}

const props = defineProps<{
    peakHoursChartData?: HourStat[];
}>();

const dataList = computed(() => props.peakHoursChartData ?? []);
const maxCount = computed(() =>
    Math.max(...dataList.value.map((d) => d.count), 1),
);

const hoveredIdx = ref<number | null>(null);

const isPeakHour = (hour: number) => {
    // F&B peak hours: 11h-13h (Lunch) & 18h-20h (Dinner)
    return (hour >= 11 && hour <= 13) || (hour >= 18 && hour <= 20);
};

// SVG dimensions
const VIEW_WIDTH = 540;
const VIEW_HEIGHT = 180;
const PADDING_BOTTOM = 32;
const PADDING_LEFT = 35;
const PADDING_RIGHT = 15;
const PADDING_TOP = 20;

const chartWidth = VIEW_WIDTH - PADDING_LEFT - PADDING_RIGHT;
const chartHeight = VIEW_HEIGHT - PADDING_TOP - PADDING_BOTTOM;

const barWidth = computed(() => {
    if (dataList.value.length === 0) {
        return 0;
    }

    return (chartWidth / dataList.value.length) * 0.68;
});

const barGap = computed(() => {
    if (dataList.value.length === 0) {
        return 0;
    }

    return (chartWidth / dataList.value.length) * 0.32;
});

const bars = computed(() => {
    return dataList.value.map((item, index) => {
        const x =
            PADDING_LEFT +
            index * (barWidth.value + barGap.value) +
            barGap.value / 2;
        const height = (item.count / maxCount.value) * chartHeight;
        const y = VIEW_HEIGHT - PADDING_BOTTOM - height;

        return {
            x,
            y,
            width: barWidth.value,
            height: Math.max(height, 3),
            raw: item,
            index,
        };
    });
});

const hoveredBar = computed(() =>
    hoveredIdx.value !== null ? bars.value[hoveredIdx.value] : null,
);
</script>

<template>
    <Deferred data="peakHoursChartData">
        <template #fallback>
            <Card class="border border-border bg-card text-card-foreground shadow-sm">
                <CardContent class="h-[270px] w-full animate-pulse flex flex-col items-center justify-center p-6 gap-3">
                    <Activity class="size-6 text-slate-400 animate-pulse" />
                    <span class="text-xs text-slate-400 font-bold tracking-tight">Đang tải biểu đồ giờ cao điểm...</span>
                </CardContent>
            </Card>
        </template>

        <Card class="overflow-hidden border border-border bg-card text-card-foreground shadow-sm transition-all duration-300 hover:shadow-md">
            <CardHeader class="border-b border-border/50 bg-slate-50/40 pb-3 dark:bg-slate-900/20">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <CardTitle class="flex items-center gap-2 text-sm font-black text-foreground">
                            <Activity class="size-4 text-rose-500" />
                            Phân phối giờ cao điểm
                        </CardTitle>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            Tần suất đơn hàng theo các giờ trong ngày (30 ngày qua)
                        </p>
                    </div>
                    <div class="flex items-center gap-1.5 rounded-full bg-rose-500/10 px-2.5 py-0.5 text-[11px] font-bold text-rose-600 dark:text-rose-400">
                        <Flame class="size-3.5" />
                        Cao điểm: 11h–13h & 18h–20h
                    </div>
                </div>
            </CardHeader>

            <CardContent class="relative pt-6 pb-4">
                <!-- Floating Tooltip Box -->
                <div
                    v-if="hoveredBar"
                    class="pointer-events-none absolute top-2 z-20 rounded-xl border border-slate-700/60 bg-slate-900/95 px-3 py-2 text-xs font-medium text-white shadow-xl backdrop-blur-md transition-all duration-150"
                    :style="{
                        left: `${(hoveredBar.x / VIEW_WIDTH) * 100}%`,
                        transform: 'translateX(-50%)',
                    }"
                >
                    <p class="font-bold text-slate-300">
                        Khung {{ hoveredBar.raw.hour }}:00 - {{ hoveredBar.raw.hour + 1 }}:00
                        <span v-if="isPeakHour(hoveredBar.raw.hour)" class="ml-1 text-rose-400">🔥 Peak</span>
                    </p>
                    <p class="mt-0.5 font-mono text-sm font-black text-amber-400">
                        {{ hoveredBar.raw.count.toLocaleString() }} đơn hàng
                    </p>
                </div>

                <div class="relative h-48 w-full">
                    <svg
                        viewBox="0 0 540 180"
                        class="h-full w-full overflow-visible select-none"
                    >
                        <!-- Gradients for Peak / Normal hours -->
                        <defs>
                            <linearGradient
                                id="peakGrad"
                                x1="0"
                                y1="0"
                                x2="0"
                                y2="1"
                            >
                                <stop offset="0%" stop-color="#f43f5e" />
                                <stop offset="100%" stop-color="#fb923c" />
                            </linearGradient>
                            <linearGradient
                                id="normalGrad"
                                x1="0"
                                y1="0"
                                x2="0"
                                y2="1"
                            >
                                <stop offset="0%" stop-color="#6366f1" />
                                <stop offset="100%" stop-color="#818cf8" />
                            </linearGradient>
                        </defs>

                        <!-- Horizontal Grid lines -->
                        <g
                            class="stroke-border/40"
                            stroke-width="1"
                            stroke-dasharray="4 4"
                        >
                            <line
                                :x1="PADDING_LEFT"
                                :y1="PADDING_TOP"
                                :x2="VIEW_WIDTH - PADDING_RIGHT"
                                :y2="PADDING_TOP"
                            />
                            <line
                                :x1="PADDING_LEFT"
                                :y1="PADDING_TOP + chartHeight * 0.33"
                                :x2="VIEW_WIDTH - PADDING_RIGHT"
                                :y2="PADDING_TOP + chartHeight * 0.33"
                            />
                            <line
                                :x1="PADDING_LEFT"
                                :y1="PADDING_TOP + chartHeight * 0.66"
                                :x2="VIEW_WIDTH - PADDING_RIGHT"
                                :y2="PADDING_TOP + chartHeight * 0.66"
                            />
                            <line
                                :x1="PADDING_LEFT"
                                :y1="VIEW_HEIGHT - PADDING_BOTTOM"
                                :x2="VIEW_WIDTH - PADDING_RIGHT"
                                :y2="VIEW_HEIGHT - PADDING_BOTTOM"
                            />
                        </g>

                        <!-- Base line -->
                        <line
                            :x1="PADDING_LEFT"
                            :y1="VIEW_HEIGHT - PADDING_BOTTOM"
                            :x2="VIEW_WIDTH - PADDING_RIGHT"
                            :y2="VIEW_HEIGHT - PADDING_BOTTOM"
                            class="stroke-border"
                            stroke-width="1.5"
                        />

                        <!-- Y-Axis Labels -->
                        <text
                            :x="PADDING_LEFT - 8"
                            :y="PADDING_TOP + 4"
                            text-anchor="end"
                            class="fill-muted-foreground font-mono text-[9px] font-bold"
                        >
                            {{ maxCount }}
                        </text>
                        <text
                            :x="PADDING_LEFT - 8"
                            :y="PADDING_TOP + chartHeight / 2 + 3"
                            text-anchor="end"
                            class="fill-muted-foreground font-mono text-[9px] font-bold"
                        >
                            {{ Math.round(maxCount / 2) }}
                        </text>
                        <text
                            :x="PADDING_LEFT - 8"
                            :y="VIEW_HEIGHT - PADDING_BOTTOM + 3"
                            text-anchor="end"
                            class="fill-muted-foreground font-mono text-[9px] font-bold"
                        >
                            0
                        </text>

                        <!-- Draw Bars -->
                        <g v-for="bar in bars" :key="bar.raw.hour">
                            <!-- Background rect for hover detection -->
                            <rect
                                :x="bar.x - barGap / 2"
                                :y="PADDING_TOP"
                                :width="bar.width + barGap"
                                :height="chartHeight"
                                fill="transparent"
                                class="cursor-pointer"
                                @mouseenter="hoveredIdx = bar.index"
                                @mouseleave="hoveredIdx = null"
                            />

                            <!-- Visible Bar -->
                            <rect
                                :x="bar.x"
                                :y="bar.y"
                                :width="bar.width"
                                :height="bar.height"
                                :fill="
                                    isPeakHour(bar.raw.hour)
                                        ? 'url(#peakGrad)'
                                        : 'url(#normalGrad)'
                                "
                                rx="4"
                                class="cursor-pointer transition-all duration-200"
                                :class="{
                                    'opacity-100 brightness-115 filter drop-shadow-md':
                                        hoveredIdx === bar.index,
                                    'opacity-80':
                                        hoveredIdx !== null &&
                                        hoveredIdx !== bar.index,
                                }"
                                @mouseenter="hoveredIdx = bar.index"
                                @mouseleave="hoveredIdx = null"
                            />

                            <!-- X-Axis Label -->
                            <text
                                :x="bar.x + bar.width / 2"
                                :y="VIEW_HEIGHT - PADDING_BOTTOM + 16"
                                text-anchor="middle"
                                class="transition-colors duration-150 font-sans text-[10px] font-semibold"
                                :class="[
                                    hoveredIdx === bar.index
                                        ? 'fill-foreground font-extrabold'
                                        : isPeakHour(bar.raw.hour)
                                          ? 'fill-rose-500 font-bold'
                                          : 'fill-muted-foreground',
                                ]"
                            >
                                {{ bar.raw.label || bar.raw.hour + 'h' }}
                            </text>
                        </g>
                    </svg>
                </div>
            </CardContent>
        </Card>
    </Deferred>
</template>
