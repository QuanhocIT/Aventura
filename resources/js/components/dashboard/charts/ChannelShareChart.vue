<script setup lang="ts">
import { Deferred } from '@inertiajs/vue3';
import { Utensils, ShoppingCart } from 'lucide-vue-next';
import { computed } from 'vue';
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

const channelChartList = computed(() => props.channelChartData ?? []);

const channelColors: Record<string, string> = {
    dine_in: '#6366f1', // indigo
    takeaway: '#f59e0b', // amber
    delivery: '#10b981', // emerald
    qr: '#ec4899', // pink
};

const doughnutPaths = computed(() => {
    const list = channelChartList.value;
    const total = list.reduce((sum, item) => sum + item.count, 0);

    if (total === 0) {
        return [];
    }

    let currentAngle = -Math.PI / 2; // start at top

    return list.map((item) => {
        const pct = item.count / total;
        const angle = pct * 2 * Math.PI;
        const start = currentAngle;
        const end = currentAngle + angle;
        currentAngle = end;

        // standard pie arc path with radius 100 centered at (100, 100)
        const startX = 100 + 100 * Math.cos(start);
        const startY = 100 + 100 * Math.sin(start);
        const endX = 100 + 100 * Math.cos(end);
        const endY = 100 + 100 * Math.sin(end);
        const largeArc = angle > Math.PI ? 1 : 0;

        const path = `M 100 100 L ${startX} ${startY} A 100 100 0 ${largeArc} 1 ${endX} ${endY} Z`;

        return {
            ...item,
            color: channelColors[item.channel] ?? '#94a3b8',
            path,
        };
    });
});
</script>

<template>
    <Deferred data="channelChartData">
        <template #fallback>
            <Card class="border border-border bg-card text-card-foreground shadow-sm">
                <CardContent class="h-[250px] w-full animate-pulse flex flex-col items-center justify-center p-6 gap-3">
                    <Utensils class="size-6 text-slate-350 dark:text-slate-650 animate-pulse" />
                    <span class="text-xs text-slate-400 font-bold tracking-tight">Đang tải tỷ lệ kênh...</span>
                </CardContent>
            </Card>
        </template>
        <Card class="border border-border bg-card text-card-foreground shadow-sm">
        <CardHeader class="border-b border-border/50 pb-2">
            <CardTitle class="flex items-center gap-2 text-base font-bold">
                <Utensils class="size-4 text-amber-500" />
                Tỷ lệ kênh bán hàng
            </CardTitle>
            <p class="mt-0.5 text-xs text-muted-foreground">
                Cơ cấu đơn hàng theo kênh dịch vụ
            </p>
        </CardHeader>

        <CardContent class="flex flex-col items-center justify-center pt-6">
            <div
                v-if="doughnutPaths.length"
                class="flex w-full flex-col items-center gap-6"
            >
                <!-- Doughnut SVG -->
                <div
                    class="relative flex h-36 w-36 items-center justify-center"
                >
                    <svg
                        viewBox="0 0 200 200"
                        class="h-full w-full -rotate-90 transform"
                    >
                        <g v-for="slice in doughnutPaths" :key="slice.label">
                            <path
                                :d="slice.path"
                                :fill="slice.color"
                                class="cursor-pointer stroke-card stroke-2 transition-opacity hover:opacity-90"
                            />
                            <title>
                                {{ slice.label }}: {{ slice.count }} đơn ({{
                                    slice.percentage
                                }}%)
                            </title>
                        </g>
                        <!-- Central hole -->
                        <circle cx="100" cy="100" r="62" class="fill-card" />
                    </svg>
                    <!-- Central statistics overlay -->
                    <div
                        class="absolute flex flex-col items-center justify-center text-center"
                    >
                        <span class="text-2xl leading-none font-bold">{{
                            channelChartList.reduce(
                                (sum, item) => sum + item.count,
                                0,
                            )
                        }}</span>
                        <span
                            class="mt-1 text-[10px] font-medium text-muted-foreground"
                            >Tổng đơn</span
                        >
                    </div>
                </div>

                <!-- Custom Legends -->
                <div class="grid w-full grid-cols-2 gap-3 text-xs">
                    <div
                        v-for="slice in doughnutPaths"
                        :key="slice.label"
                        class="flex items-center gap-2 rounded-lg border border-border bg-muted/20 px-2 py-1.5"
                    >
                        <div
                            class="h-2.5 w-2.5 shrink-0 animate-pulse rounded-full"
                            :style="{ backgroundColor: slice.color }"
                        ></div>
                        <span class="truncate font-medium text-foreground">{{
                            slice.label
                        }}</span>
                        <span
                            class="ml-auto font-mono text-[10px] font-bold text-muted-foreground"
                            >{{ slice.percentage }}%</span
                        >
                    </div>
                </div>
            </div>

            <div
                v-else
                class="flex w-full flex-col items-center justify-center py-8 text-sm text-muted-foreground"
            >
                <ShoppingCart class="mb-1.5 size-6 text-muted-foreground/30" />
                Chưa có dữ liệu kênh bán hàng.
            </div>
        </CardContent>
    </Card>
    </Deferred>
</template>
