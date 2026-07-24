<script setup lang="ts">
import { Deferred } from '@inertiajs/vue3';
import { Utensils, ShoppingCart, PieChart } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface ChannelShare {
    channel: string;
    label: string;
    count: number;
    percentage: number;
}

const props = defineProps<{
    channelChartData: ChannelShare[] | undefined;
}>();

const hoveredChannel = ref<string | null>(null);

const channelChartList = computed(() => props.channelChartData ?? []);

const channelColors: Record<string, { bg: string; text: string }> = {
    dine_in: { bg: '#6366f1', text: 'text-indigo-500' },
    takeaway: { bg: '#f59e0b', text: 'text-amber-500' },
    delivery: { bg: '#10b981', text: 'text-emerald-500' },
    qr: { bg: '#ec4899', text: 'text-pink-500' },
};

const totalOrders = computed(() =>
    channelChartList.value.reduce((sum, item) => sum + item.count, 0),
);

// Calculation for doughnut slices handling full circle (100%) case correctly
const doughnutSlices = computed(() => {
    const list = channelChartList.value.filter((i) => i.count > 0);
    const total = totalOrders.value;

    if (total === 0 || list.length === 0) {
        return [];
    }

    // Single item / 100% case -> full circle
    if (list.length === 1) {
        const item = list[0];
        const color = channelColors[item.channel]?.bg ?? '#6366f1';

        return [
            {
                ...item,
                color,
                isFullCircle: true,
                path: '',
            },
        ];
    }

    let currentAngle = -Math.PI / 2;

    return list.map((item) => {
        const pct = item.count / total;
        const safePct = Math.min(pct, 0.9999);
        const angle = safePct * 2 * Math.PI;

        const start = currentAngle;
        const end = currentAngle + angle;
        currentAngle = end;

        const outerR = 90;
        const innerR = 56;
        const cx = 100;
        const cy = 100;

        const x1Outer = cx + outerR * Math.cos(start);
        const y1Outer = cy + outerR * Math.sin(start);
        const x2Outer = cx + outerR * Math.cos(end);
        const y2Outer = cy + outerR * Math.sin(end);

        const x1Inner = cx + innerR * Math.cos(end);
        const y1Inner = cy + innerR * Math.sin(end);
        const x2Inner = cx + innerR * Math.cos(start);
        const y2Inner = cy + innerR * Math.sin(start);

        const largeArc = angle > Math.PI ? 1 : 0;

        const path = `
            M ${x1Outer} ${y1Outer}
            A ${outerR} ${outerR} 0 ${largeArc} 1 ${x2Outer} ${y2Outer}
            L ${x1Inner} ${y1Inner}
            A ${innerR} ${innerR} 0 ${largeArc} 0 ${x2Inner} ${y2Inner}
            Z
        `.trim();

        const color = channelColors[item.channel]?.bg ?? '#94a3b8';

        return {
            ...item,
            color,
            isFullCircle: false,
            path,
        };
    });
});

const activeHoveredItem = computed(() => {
    if (!hoveredChannel.value) {
        return null;
    }

    return channelChartList.value.find((i) => i.channel === hoveredChannel.value) ?? null;
});
</script>

<template>
    <Deferred data="channelChartData">
        <template #fallback>
            <Card class="border border-border bg-card text-card-foreground shadow-sm">
                <CardContent class="h-[270px] w-full animate-pulse flex flex-col items-center justify-center p-6 gap-3">
                    <PieChart class="size-6 text-slate-400 animate-pulse" />
                    <span class="text-xs text-slate-400 font-bold tracking-tight">Đang tải tỷ lệ kênh...</span>
                </CardContent>
            </Card>
        </template>

        <Card class="overflow-hidden border border-border bg-card text-card-foreground shadow-sm transition-all duration-300 hover:shadow-md">
            <CardHeader class="border-b border-border/50 bg-slate-50/40 pb-3 dark:bg-slate-900/20">
                <div class="flex items-center justify-between">
                    <div>
                        <CardTitle class="flex items-center gap-2 text-sm font-black text-foreground">
                            <Utensils class="size-4 text-amber-500" />
                            Tỷ lệ kênh bán hàng
                        </CardTitle>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            Cơ cấu đơn hàng theo từng kênh phục vụ
                        </p>
                    </div>
                    <span class="rounded-full bg-amber-500/10 px-2.5 py-0.5 font-mono text-[11px] font-extrabold text-amber-600 dark:text-amber-400">
                        {{ totalOrders }} đơn
                    </span>
                </div>
            </CardHeader>

            <CardContent class="flex flex-col items-center justify-center pt-6 pb-5">
                <div v-if="doughnutSlices.length" class="flex w-full flex-col items-center gap-6">
                    <!-- Doughnut SVG -->
                    <div class="relative flex h-40 w-40 items-center justify-center">
                        <svg viewBox="0 0 200 200" class="h-full w-full select-none">
                            <g v-for="slice in doughnutSlices" :key="slice.channel">
                                <!-- Single full circle rendering -->
                                <circle
                                    v-if="slice.isFullCircle"
                                    cx="100"
                                    cy="100"
                                    r="90"
                                    :fill="slice.color"
                                    class="cursor-pointer transition-all duration-300"
                                    :class="{ 'opacity-100 scale-105': hoveredChannel === slice.channel, 'opacity-90': hoveredChannel && hoveredChannel !== slice.channel }"
                                    @mouseenter="hoveredChannel = slice.channel"
                                    @mouseleave="hoveredChannel = null"
                                />
                                <circle
                                    v-if="slice.isFullCircle"
                                    cx="100"
                                    cy="100"
                                    r="56"
                                    class="fill-card"
                                />

                                <!-- Multi-slice arc rendering -->
                                <path
                                    v-else
                                    :d="slice.path"
                                    :fill="slice.color"
                                    class="cursor-pointer stroke-card stroke-2 transition-all duration-200"
                                    :class="{
                                        'opacity-100 brightness-110 filter drop-shadow-md': hoveredChannel === slice.channel,
                                        'opacity-75': hoveredChannel && hoveredChannel !== slice.channel
                                    }"
                                    @mouseenter="hoveredChannel = slice.channel"
                                    @mouseleave="hoveredChannel = null"
                                />
                                <title>{{ slice.label }}: {{ slice.count }} đơn ({{ slice.percentage }}%)</title>
                            </g>
                        </svg>

                        <!-- Central Stats Overlay -->
                        <div class="absolute flex flex-col items-center justify-center text-center">
                            <template v-if="activeHoveredItem">
                                <span class="font-mono text-xl font-black leading-none text-foreground">
                                    {{ activeHoveredItem.percentage }}%
                                </span>
                                <span class="mt-1 max-w-[80px] truncate text-[10px] font-bold text-muted-foreground">
                                    {{ activeHoveredItem.label }} ({{ activeHoveredItem.count }})
                                </span>
                            </template>
                            <template v-else>
                                <span class="font-mono text-2xl font-black leading-none text-foreground">
                                    {{ totalOrders }}
                                </span>
                                <span class="mt-1 text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                    Tổng đơn
                                </span>
                            </template>
                        </div>
                    </div>

                    <!-- Custom Legends Grid -->
                    <div class="grid w-full grid-cols-2 gap-2 text-xs">
                        <div
                            v-for="slice in doughnutSlices"
                            :key="slice.channel"
                            @mouseenter="hoveredChannel = slice.channel"
                            @mouseleave="hoveredChannel = null"
                            class="flex cursor-pointer items-center gap-2 rounded-xl border border-border/80 bg-slate-50/50 px-3 py-2 transition-all duration-200 hover:border-border hover:bg-slate-100/80 dark:bg-slate-900/40 dark:hover:bg-slate-800/60"
                            :class="{ 'border-primary/50 bg-primary/5 ring-1 ring-primary/20 dark:bg-primary/10': hoveredChannel === slice.channel }"
                        >
                            <span
                                class="h-3 w-3 shrink-0 rounded-full shadow-xs"
                                :style="{ backgroundColor: slice.color }"
                            />
                            <span class="truncate text-xs font-bold text-foreground">
                                {{ slice.label }}
                            </span>
                            <span class="ml-auto font-mono text-[11px] font-black text-muted-foreground">
                                {{ slice.percentage }}%
                            </span>
                        </div>
                    </div>
                </div>

                <div
                    v-else
                    class="flex w-full flex-col items-center justify-center py-10 text-sm text-muted-foreground"
                >
                    <ShoppingCart class="mb-2 size-8 text-muted-foreground/30" />
                    <p class="font-semibold">Chưa có dữ liệu kênh bán hàng</p>
                </div>
            </CardContent>
        </Card>
    </Deferred>
</template>

