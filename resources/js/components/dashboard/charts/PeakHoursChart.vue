<script setup lang="ts">
import { computed, ref } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Activity } from 'lucide-vue-next';

interface HourStat {
    hour: number;
    label: string;
    count: number;
}

const props = defineProps<{
    peakHoursChartData?: HourStat[];
}>();

const dataList = computed(() => props.peakHoursChartData ?? []);
const maxCount = computed(() => Math.max(...dataList.value.map(d => d.count), 1));

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
        const x = PADDING_LEFT + index * (barWidth.value + barGap.value) + barGap.value / 2;
        const height = (item.count / maxCount.value) * chartHeight;
        const y = VIEW_HEIGHT - PADDING_BOTTOM - height;

        return {
            x,
            y,
            width: barWidth.value,
            height: Math.max(height, 2), // min height of 2px
            raw: item,
            index
        };
    });
});

const hoveredBar = computed(() => hoveredIdx.value !== null ? bars.value[hoveredIdx.value] : null);
</script>

<template>
    <Card class="bg-card text-card-foreground border border-border shadow-sm">
        <CardHeader class="pb-2 border-b border-border/50">
            <CardTitle class="text-base font-bold flex items-center gap-2">
                <Activity class="size-4 text-rose-500" />
                Phân phối giờ cao điểm
            </CardTitle>
            <p class="text-xs text-muted-foreground mt-0.5">Tần suất đơn hàng theo các giờ trong ngày (30 ngày qua)</p>
        </CardHeader>
        
        <CardContent class="pt-6 relative">
            <div class="relative w-full h-44">
                <svg viewBox="0 0 500 160" class="w-full h-full overflow-visible select-none">
                    <!-- Gradients for Peak/Normal hours -->
                    <defs>
                        <linearGradient id="peakGrad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#ec4899" />
                            <stop offset="100%" stop-color="#f59e0b" />
                        </linearGradient>
                        <linearGradient id="normalGrad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#6366f1" />
                            <stop offset="100%" stop-color="#4f46e5" />
                        </linearGradient>
                    </defs>

                    <!-- Horizontal Grid lines -->
                    <g opacity="0.08" class="stroke-foreground" stroke-width="0.75" stroke-dasharray="3 3">
                        <line :x1="PADDING_LEFT" :y1="PADDING_TOP" :x2="VIEW_WIDTH - PADDING_RIGHT" :y2="PADDING_TOP" />
                        <line :x1="PADDING_LEFT" :y1="PADDING_TOP + chartHeight * 0.33" :x2="VIEW_WIDTH - PADDING_RIGHT" :y2="PADDING_TOP + chartHeight * 0.33" />
                        <line :x1="PADDING_LEFT" :y1="PADDING_TOP + chartHeight * 0.66" :x2="VIEW_WIDTH - PADDING_RIGHT" :y2="PADDING_TOP + chartHeight * 0.66" />
                        <line :x1="PADDING_LEFT" :y1="VIEW_HEIGHT - PADDING_BOTTOM" :x2="VIEW_WIDTH - PADDING_RIGHT" :y2="VIEW_HEIGHT - PADDING_BOTTOM" />
                    </g>

                    <!-- Axes -->
                    <line :x1="PADDING_LEFT" :y1="VIEW_HEIGHT - PADDING_BOTTOM" :x2="VIEW_WIDTH - PADDING_RIGHT" :y2="VIEW_HEIGHT - PADDING_BOTTOM" class="stroke-border" stroke-width="1.5" />
                    <line :x1="PADDING_LEFT" :y1="PADDING_TOP" :x2="PADDING_LEFT" :y2="VIEW_HEIGHT - PADDING_BOTTOM" class="stroke-border" stroke-width="1.5" />

                    <!-- Axis ticks/labels Y -->
                    <text :x="PADDING_LEFT - 8" :y="PADDING_TOP + 4" text-anchor="end" class="fill-muted-foreground text-[8px] font-mono">{{ maxCount }}</text>
                    <text :x="PADDING_LEFT - 8" :y="PADDING_TOP + chartHeight / 2 + 3" text-anchor="end" class="fill-muted-foreground text-[8px] font-mono">{{ Math.round(maxCount / 2) }}</text>
                    <text :x="PADDING_LEFT - 8" :y="VIEW_HEIGHT - PADDING_BOTTOM + 3" text-anchor="end" class="fill-muted-foreground text-[8px] font-mono">0</text>

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
                            :fill="isPeakHour(bar.raw.hour) ? 'url(#peakGrad)' : 'url(#normalGrad)'"
                            rx="3"
                            class="transition-all duration-200 cursor-pointer hover:opacity-90"
                            :class="{ 'opacity-100 filter drop-shadow-sm brightness-110': hoveredIdx === bar.index, 'opacity-85': hoveredIdx !== null && hoveredIdx !== bar.index }"
                            @mouseenter="hoveredIdx = bar.index"
                            @mouseleave="hoveredIdx = null"
                        />
                        <!-- Label X -->
                        <text
                            :x="bar.x + bar.width / 2"
                            :y="VIEW_HEIGHT - PADDING_BOTTOM + 12"
                            text-anchor="middle"
                            class="fill-muted-foreground text-[8px] font-bold font-mono"
                        >
                            {{ bar.raw.label }}
                        </text>
                    </g>
                </svg>

                <!-- Tooltip -->
                <div
                    v-if="hoveredBar"
                    class="absolute z-10 pointer-events-none rounded-lg border bg-background/95 backdrop-blur-xs p-2 shadow-lg text-[10px] font-bold flex flex-col gap-0.5 transition-all duration-75 text-foreground"
                    :style="{
                        left: `${(hoveredBar.x + hoveredBar.width / 2) / VIEW_WIDTH * 100}%`,
                        top: `${(hoveredBar.y - 30) / VIEW_HEIGHT * 100}%`,
                        transform: 'translateX(-50%)',
                        borderColor: isPeakHour(hoveredBar.raw.hour) ? '#ec489933' : '#6366f133',
                    }"
                >
                    <span class="text-[8px] uppercase tracking-wider text-muted-foreground font-mono">Giờ: {{ hoveredBar.raw.label }}</span>
                    <span class="font-extrabold flex items-center gap-1">
                        <span class="size-1.5 rounded-full" :class="isPeakHour(hoveredBar.raw.hour) ? 'bg-pink-500' : 'bg-indigo-500'"></span>
                        {{ hoveredBar.raw.count }} đơn
                    </span>
                    <span v-if="isPeakHour(hoveredBar.raw.hour)" class="text-[8px] text-pink-500 font-medium">🔥 Giờ cao điểm</span>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
