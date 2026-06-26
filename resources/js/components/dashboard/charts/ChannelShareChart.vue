<script setup lang="ts">
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
    dine_in: '#6366f1',  // indigo
    takeaway: '#f59e0b', // amber
    delivery: '#10b981', // emerald
    qr: '#ec4899',       // pink
};

const doughnutPaths = computed(() => {
    const list = channelChartList.value;
    const total = list.reduce((sum, item) => sum + item.count, 0);

    if (total === 0) {
return [];
}

    let currentAngle = -Math.PI / 2; // start at top

    return list.map(item => {
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
    <Card class="bg-card text-card-foreground border border-border shadow-sm">
        <CardHeader class="pb-2 border-b border-border/50">
            <CardTitle class="text-base font-bold flex items-center gap-2">
                <Utensils class="size-4 text-amber-500" />
                Tỷ lệ kênh bán hàng
            </CardTitle>
            <p class="text-xs text-muted-foreground mt-0.5">Cơ cấu đơn hàng theo kênh dịch vụ</p>
        </CardHeader>
        
        <CardContent class="pt-6 flex flex-col items-center justify-center">
            <div v-if="doughnutPaths.length" class="flex flex-col items-center gap-6 w-full">
                <!-- Doughnut SVG -->
                <div class="relative w-36 h-36 flex items-center justify-center">
                    <svg viewBox="0 0 200 200" class="w-full h-full transform -rotate-90">
                        <g v-for="slice in doughnutPaths" :key="slice.label">
                            <path :d="slice.path" :fill="slice.color" class="stroke-card stroke-2 hover:opacity-90 transition-opacity cursor-pointer" />
                            <title>{{ slice.label }}: {{ slice.count }} đơn ({{ slice.percentage }}%)</title>
                        </g>
                        <!-- Central hole -->
                        <circle cx="100" cy="100" r="62" class="fill-card" />
                    </svg>
                    <!-- Central statistics overlay -->
                    <div class="absolute flex flex-col items-center justify-center text-center">
                        <span class="text-2xl font-bold leading-none">{{ channelChartList.reduce((sum, item) => sum + item.count, 0) }}</span>
                        <span class="text-[10px] text-muted-foreground mt-1 font-medium">Tổng đơn</span>
                    </div>
                </div>

                <!-- Custom Legends -->
                <div class="grid grid-cols-2 gap-3 text-xs w-full">
                    <div v-for="slice in doughnutPaths" :key="slice.label" 
                         class="flex items-center gap-2 px-2 py-1.5 rounded-lg border border-border bg-muted/20"
                    >
                        <div class="w-2.5 h-2.5 rounded-full shrink-0 animate-pulse" :style="{ backgroundColor: slice.color }"></div>
                        <span class="font-medium text-foreground truncate">{{ slice.label }}</span>
                        <span class="ml-auto font-bold text-muted-foreground font-mono text-[10px]">{{ slice.percentage }}%</span>
                    </div>
                </div>
            </div>
            
            <div v-else class="flex flex-col items-center justify-center py-8 text-muted-foreground text-sm w-full">
                <ShoppingCart class="size-6 text-muted-foreground/30 mb-1.5" />
                Chưa có dữ liệu kênh bán hàng.
            </div>
        </CardContent>
    </Card>
</template>
