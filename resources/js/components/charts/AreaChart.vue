<script setup lang="ts">
import { computed, ref } from 'vue';

/**
 * Biểu đồ area chart SVG dùng chung cho dashboard SuperAdmin.
 * Tách ra từ Dashboard.vue để tránh lặp lại logic vẽ + hover giữa các biểu đồ
 * (tăng trưởng tenant, conversion, revenue breakdown...).
 */
type SeriesPoint = { label: string; value: number; [key: string]: unknown };
type ChartPoint = { x: number; y: number; value: number; label: string; raw: SeriesPoint };

const props = withDefaults(defineProps<{
    series: SeriesPoint[];
    gradientId: string;
    color?: string;
    compareSeries?: SeriesPoint[];
    compareColor?: string;
}>(), {
    color: '#0ea5e9',
    compareSeries: undefined,
    compareColor: '#94a3b8',
});

const VIEW_WIDTH = 100;
const VIEW_HEIGHT = 40;
const VIEW_BOTTOM = VIEW_HEIGHT + 4;

function buildPoints(series: SeriesPoint[], min: number, span: number): ChartPoint[] {
    return series.map((point, index) => {
        const x = series.length <= 1 ? 0 : (index / (series.length - 1)) * VIEW_WIDTH;
        const y = VIEW_HEIGHT + 2 - ((point.value - min) / span) * VIEW_HEIGHT;

        return { x, y, value: point.value, label: point.label, raw: point };
    });
}

const scale = computed(() => {
    const allValues = [
        ...props.series.map((p) => p.value),
        ...(props.compareSeries ?? []).map((p) => p.value),
    ];
    const max = Math.max(...allValues, 1);
    const min = Math.min(...allValues, 0);

    return { min, span: Math.max(max - min, 1) };
});

const points = computed(() => buildPoints(props.series, scale.value.min, scale.value.span));
const comparePoints = computed(() => props.compareSeries ? buildPoints(props.compareSeries, scale.value.min, scale.value.span) : []);

function toLinePath(pts: ChartPoint[]): string {
    if (pts.length === 0) {
return '';
}

    return pts.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x.toFixed(2)} ${p.y.toFixed(2)}`).join(' ');
}

function toAreaPath(pts: ChartPoint[]): string {
    if (pts.length === 0) {
return '';
}

    return `${toLinePath(pts)} L ${VIEW_WIDTH} ${VIEW_BOTTOM} L 0 ${VIEW_BOTTOM} Z`;
}

const linePath = computed(() => toLinePath(points.value));
const areaPath = computed(() => toAreaPath(points.value));
const compareLinePath = computed(() => toLinePath(comparePoints.value));

const hoveredIdx = ref<number | null>(null);

function handleMouseMove(e: MouseEvent) {
    if (points.value.length === 0) {
return;
}

    const rect = (e.currentTarget as HTMLElement).getBoundingClientRect();
    const percent = (e.clientX - rect.left) / rect.width;
    hoveredIdx.value = Math.min(points.value.length - 1, Math.max(0, Math.round(percent * (points.value.length - 1))));
}

function handleMouseLeave() {
    hoveredIdx.value = null;
}

const hoveredPoint = computed(() => hoveredIdx.value !== null ? points.value[hoveredIdx.value] : null);
const hoveredComparePoint = computed(() => hoveredIdx.value !== null ? (comparePoints.value[hoveredIdx.value] ?? null) : null);
</script>

<template>
    <div
        class="relative h-28 w-full select-none cursor-crosshair mt-4"
        @mousemove="handleMouseMove"
        @mouseleave="handleMouseLeave"
    >
        <!-- Dotted Grid Lines -->
        <svg class="absolute inset-0 h-full w-full opacity-10" viewBox="0 0 100 44" preserveAspectRatio="none">
            <line x1="0" y1="11" x2="100" y2="11" stroke="currentColor" stroke-dasharray="2" stroke-width="0.5" />
            <line x1="0" y1="22" x2="100" y2="22" stroke="currentColor" stroke-dasharray="2" stroke-width="0.5" />
            <line x1="0" y1="33" x2="100" y2="33" stroke="currentColor" stroke-dasharray="2" stroke-width="0.5" />
        </svg>

        <svg viewBox="0 0 100 44" class="h-full w-full overflow-visible" preserveAspectRatio="none">
            <defs>
                <linearGradient :id="gradientId" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" :stop-color="color" stop-opacity="0.35" />
                    <stop offset="100%" :stop-color="color" stop-opacity="0" />
                </linearGradient>
            </defs>

            <path :d="areaPath" :fill="`url(#${gradientId})`" />
            <path :d="linePath" fill="none" :stroke="color" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />

            <!-- Đường so sánh kỳ trước (nếu có) -->
            <path
                v-if="compareSeries && compareSeries.length > 0"
                :d="compareLinePath"
                fill="none"
                :stroke="compareColor"
                stroke-width="2"
                stroke-dasharray="3 2"
                stroke-linecap="round"
                stroke-linejoin="round"
                opacity="0.8"
            />

            <!-- Hover Vertical Guide Line -->
            <line
                v-if="hoveredPoint"
                :x1="hoveredPoint.x" y1="0" :x2="hoveredPoint.x" y2="44"
                :stroke="color" stroke-dasharray="1 1" stroke-width="0.5"
            />

            <!-- Hover Dot Indicator -->
            <circle v-if="hoveredPoint" :cx="hoveredPoint.x" :cy="hoveredPoint.y" r="2" :fill="color" stroke="#fff" stroke-width="0.5" class="animate-ping" />
            <circle v-if="hoveredPoint" :cx="hoveredPoint.x" :cy="hoveredPoint.y" r="1.2" :fill="color" stroke="#fff" stroke-width="0.5" />

            <circle v-if="hoveredComparePoint" :cx="hoveredComparePoint.x" :cy="hoveredComparePoint.y" r="1.2" :fill="compareColor" stroke="#fff" stroke-width="0.5" />
        </svg>

        <!-- Floating Custom Tooltip -->
        <div
            v-if="hoveredPoint"
            class="absolute z-10 pointer-events-none rounded-lg border bg-background/95 backdrop-blur-xs p-2 shadow-lg text-[10px] font-bold flex flex-col gap-0.5 transition-all duration-75 text-foreground"
            :style="{
                left: `${hoveredPoint.x}%`,
                top: `-20px`,
                transform: 'translateX(-50%)',
                borderColor: `${color}33`,
            }"
        >
            <slot name="tooltip" :point="hoveredPoint.raw" :compare-point="hoveredComparePoint?.raw ?? null" :index="hoveredIdx">
                <span class="text-[8px] uppercase tracking-wider text-muted-foreground font-mono">{{ hoveredPoint.label }}</span>
                <span class="font-extrabold" :style="{ color }">{{ hoveredPoint.value }}</span>
            </slot>
        </div>
    </div>
</template>
