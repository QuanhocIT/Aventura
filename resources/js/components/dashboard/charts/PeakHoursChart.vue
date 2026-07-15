<script setup lang="ts">
import { computed, ref } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Activity } from 'lucide-vue-next';
import { Deferred } from '@inertiajs/vue3';

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
    // Khung giờ cao điểm F&B thông thường: 11h-13h (Trưa) & 18h-20h (Tối)
    return (hour >= 11 && hour <= 13) || (hour >= 18 && hour <= 20);
};

// SVG dimensions
const VIEW_WIDTH = 500;
const VIEW_HEIGHT = 160;
const PADDING_BOTTOM = 25;
const PADDING_LEFT = 35;
const PADDING_RIGHT = 15;
const PADDING_TOP = 15;

const chartWidth = VIEW_WIDTH - PADDING_LEFT - PADDING_RIGHT;
const chartHeight = VIEW_HEIGHT - PADDING_TOP - PADDING_BOTTOM;

const barWidth = computed(() => {
    if (dataList.value.length === 0) return 0;
    return (chartWidth / dataList.value.length) * 0.7; // 70% width, 30% gap
});

const barGap = computed(() => {
    if (dataList.value.length === 0) return 0;
    return (chartWidth / dataList.value.length) * 0.3;
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
            height: Math.max(height, 2), // min height of 2px
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
                <CardContent class="h-[250px] w-full animate-pulse flex flex-col items-center justify-center p-6 gap-3">
                    <Activity class="size-6 text-slate-350 dark:text-slate-650 animate-pulse" />
                    <span class="text-xs text-slate-400 font-bold tracking-tight">Đang tải biểu đồ giờ cao điểm...</span>
                </CardContent>
            </Card>
        </template>
        <Card class="border border-border bg-card text-card-foreground shadow-sm">
        <CardHeader class="border-b border-border/50 pb-2">
            <CardTitle class="flex items-center gap-2 text-base font-bold">
                <Activity class="size-4 text-rose-500" />
                Phân phối giờ cao điểm
            </CardTitle>
            <p class="mt-0.5 text-xs text-muted-foreground">
                Tần suất đơn hàng theo các giờ trong ngày (30 ngày qua)
            </p>
        </CardHeader>

        <CardContent class="relative pt-6">
            <div class="relative h-44 w-full">
                <svg
                    viewBox="0 0 500 160"
                    class="h-full w-full overflow-visible select-none"
                >
                    <!-- Gradients for Peak/Normal hours -->
                    <defs>
                        <linearGradient
                            id="peakGrad"
                            x1="0"
                            y1="0"
                            x2="0"
                            y2="1"
                        >
                            <stop offset="0%" stop-color="#ec4899" />
                            <stop offset="100%" stop-color="#f59e0b" />
                        </linearGradient>
                        <linearGradient
                            id="normalGrad"
                            x1="0"
                            y1="0"
                            x2="0"
                            y2="1"
                        >
                            <stop offset="0%" stop-color="#6366f1" />
                            <stop offset="100%" stop-color="#4f46e5" />
                        </linearGradient>
                    </defs>

                    <!-- Horizontal Grid lines -->
                    <g
                        opacity="0.08"
                        class="stroke-foreground"
                        stroke-width="0.75"
                        stroke-dasharray="3 3"
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

                    <!-- Axes -->
                    <line
                        :x1="PADDING_LEFT"
                        :y1="VIEW_HEIGHT - PADDING_BOTTOM"
                        :x2="VIEW_WIDTH - PADDING_RIGHT"
                        :y2="VIEW_HEIGHT - PADDING_BOTTOM"
                        class="stroke-border"
                        stroke-width="1.5"
                    />
                    <line
                        :x1="PADDING_LEFT"
                        :y1="PADDING_TOP"
                        :x2="PADDING_LEFT"
                        :y2="VIEW_HEIGHT - PADDING_BOTTOM"
                        class="stroke-border"
                        stroke-width="1.5"
                    />

                    <!-- Axis ticks/labels Y -->
                    <text
                        :x="PADDING_LEFT - 8"
                        :y="PADDING_TOP + 4"
                        text-anchor="end"
                        class="fill-muted-foreground font-mono text-[8px]"
                    >
                        {{ maxCount }}
                    </text>
                    <text
                        :x="PADDING_LEFT - 8"
                        :y="PADDING_TOP + chartHeight / 2 + 3"
                        text-anchor="end"
                        class="fill-muted-foreground font-mono text-[8px]"
                    >
                        {{ Math.round(maxCount / 2) }}
                    </text>
                    <text
                        :x="PADDING_LEFT - 8"
                        :y="VIEW_HEIGHT - PADDING_BOTTOM + 3"
                        text-anchor="end"
                        class="fill-muted-foreground font-mono text-[8px]"
                    >
                        0
                    </text>

                    <!-- Draw Bars -->
                    <g v-for="bar in bars" :key="bar.raw.hour">
                        <!-- Bar background block for easier hovering -->
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
                        <!-- Actual visible bar -->
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
                            rx="3"
                            class="cursor-pointer transition-all duration-200 hover:opacity-90"
                            :class="{
                                'opacity-100 brightness-110 drop-shadow-sm filter':
                                    hoveredIdx === bar.index,
                                'opacity-85':
                                    hoveredIdx !== null &&
                                    hoveredIdx !== bar.index,
                            }"
                            @mouseenter="hoveredIdx = bar.index"
                            @mouseleave="hoveredIdx = null"
                        />
                        <!-- Label X -->
                        <text
                            :x="bar.x + bar.width / 2"
                            :y="VIEW_HEIGHT - PADDING_BOTTOM + 12"
                            text-anchor="middle"
                            class="fill-muted-foreground font-mono text-[8px] font-bold"
                        >
                            {{ bar.raw.label }}
                        </text>
                    </g>
                </svg>

                <!-- Tooltip -->
                <div
                    v-if="hoveredBar"
                    class="pointer-events-none absolute z-10 flex flex-col gap-0.5 rounded-lg border bg-background/95 p-2 text-[10px] font-bold text-foreground shadow-lg backdrop-blur-xs transition-all duration-75"
                    :style="{
                        left: `${((hoveredBar.x + hoveredBar.width / 2) / VIEW_WIDTH) * 100}%`,
                        top: `${((hoveredBar.y - 30) / VIEW_HEIGHT) * 100}%`,
                        transform: 'translateX(-50%)',
                        borderColor: isPeakHour(hoveredBar.raw.hour)
                            ? '#ec489933'
                            : '#6366f133',
                    }"
                >
                    <span
                        class="font-mono text-[8px] tracking-wider text-muted-foreground uppercase"
                        >Giờ: {{ hoveredBar.raw.label }}</span
                    >
                    <span class="flex items-center gap-1 font-extrabold">
                        <span
                            class="size-1.5 rounded-full"
                            :class="
                                isPeakHour(hoveredBar.raw.hour)
                                    ? 'bg-pink-500'
                                    : 'bg-indigo-500'
                            "
                        ></span>
                        {{ hoveredBar.raw.count }} đơn
                    </span>
                    <span
                        v-if="isPeakHour(hoveredBar.raw.hour)"
                        class="text-[8px] font-medium text-pink-500"
                        >🔥 Giờ cao điểm</span
                    >
                </div>
            </div>
        </CardContent>
    </Card>
    </Deferred>
</template>
