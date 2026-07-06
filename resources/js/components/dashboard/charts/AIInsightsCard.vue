<script setup lang="ts">
import { Sparkles, Brain, Utensils, Trophy } from 'lucide-vue-next';
import { computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface ForecastData {
    amount: number;
    confidence: string;
    confidence_label: string;
    samples: number;
    day_label: string;
    trend_factor: number;
}

interface TopProductStat {
    name: string;
    quantity: number;
    revenue: number;
}

interface ChannelShare {
    channel: string;
    label: string;
    count: number;
    percentage: number;
}

const props = defineProps<{
    forecastData: ForecastData | null | undefined;
    stats: any;
    topProductsChartData: TopProductStat[] | undefined;
    channelChartData: ChannelShare[] | undefined;
}>();

const topDishName = computed(() => props.topProductsChartData?.[0]?.name ?? 'chưa có');

const topChannelLabel = computed(() => {
    if (!props.channelChartData || props.channelChartData.length === 0) {
return 'chưa có';
}

    const sorted = [...props.channelChartData].sort((a, b) => b.count - a.count);

    return sorted[0]?.label ?? 'chưa có';
});

function formatMoney(v: number): string {
    if (v === 0) {
        return '—';
    }

    return new Intl.NumberFormat('vi-VN', { notation: 'compact', maximumFractionDigits: 1 }).format(v) + 'đ';
}
</script>

<template>
    <div class="space-y-4">
        <!-- Dự báo doanh thu ngày mai -->
        <Card v-if="forecastData" class="bg-gradient-to-br from-indigo-50 to-violet-50 dark:from-indigo-950/20 dark:to-violet-950/20 border border-indigo-200 dark:border-indigo-800/40 shadow-sm overflow-hidden">
            <CardHeader class="pb-2 border-b border-indigo-100 dark:border-indigo-800/30">
                <CardTitle class="text-sm font-bold flex items-center gap-2 text-indigo-700 dark:text-indigo-300">
                    <Sparkles class="size-4 text-indigo-500 animate-pulse" />
                    Dự báo doanh thu ngày mai
                </CardTitle>
                <p class="text-[10px] text-indigo-500/80 dark:text-indigo-400/70">{{ forecastData.day_label }}</p>
            </CardHeader>
            <CardContent class="pt-4 text-xs">
                <div class="flex items-end gap-3 mb-3">
                    <div>
                        <p class="text-2xl font-black text-indigo-700 dark:text-indigo-300">
                            {{ formatMoney(forecastData.amount) }}
                        </p>
                        <p class="text-[10px] text-indigo-500/70 mt-0.5">
                            Dựa trên {{ forecastData.samples }} tuần lịch sử
                        </p>
                    </div>
                    <span :class="[
                        'mb-1 px-2 py-0.5 rounded-full text-[9px] font-bold border',
                        forecastData.confidence === 'high'
                            ? 'bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-950/30 dark:text-emerald-400'
                            : forecastData.confidence === 'medium'
                                ? 'bg-amber-100 text-amber-700 border-amber-200 dark:bg-amber-950/30 dark:text-amber-400'
                                : 'bg-rose-100 text-rose-700 border-rose-200 dark:bg-rose-950/30 dark:text-rose-400'
                    ]">
                        Tin cậy: {{ forecastData.confidence_label }}
                    </span>
                </div>
                <!-- So sánh với hôm nay -->
                <div v-if="stats?.revenue_today && forecastData.amount > 0" class="mt-1">
                    <div class="flex items-center justify-between text-[10px] text-indigo-500/80 mb-1">
                        <span>Hôm nay</span>
                        <span>Dự báo ngày mai</span>
                    </div>
                    <div class="h-2 bg-indigo-100 dark:bg-indigo-900/30 rounded-full overflow-hidden">
                        <div class="h-full bg-indigo-500 rounded-full transition-all"
                             :style="`width: ${Math.min(100, stats.revenue_today / forecastData.amount * 100)}%`" />
                    </div>
                    <p class="text-[10px] text-indigo-500/70 mt-1">
                        Hôm nay đạt {{ Math.min(100, Math.round(stats.revenue_today / forecastData.amount * 100)) }}% mục tiêu dự báo
                    </p>
                </div>
            </CardContent>
        </Card>

        <!-- AI Insights Card -->
        <Card class="bg-card text-card-foreground border border-border shadow-sm overflow-hidden relative">
            <div class="absolute -right-10 -bottom-10 w-32 h-32 bg-indigo-500/5 rounded-full blur-2xl"></div>
            <CardHeader class="pb-2 border-b border-border/50 bg-muted/20">
                <CardTitle class="text-base font-bold flex items-center gap-2">
                    <Brain class="size-4.5 text-indigo-500 animate-pulse" />
                    Trợ lý AI Aventura
                </CardTitle>
                <p class="text-xs text-muted-foreground mt-0.5">Phân tích & Khuyến nghị tự động</p>
            </CardHeader>
            <CardContent class="pt-4 space-y-3 relative z-10 text-xs">
                <div v-if="topProductsChartData?.length && channelChartData?.length" class="space-y-3">
                    <div class="flex items-start gap-2">
                        <div class="flex h-5 w-5 shrink-0 items-center justify-center rounded bg-indigo-500/10 text-indigo-500 mt-0.5">
                            <Utensils class="size-3" />
                        </div>
                        <p class="leading-normal text-muted-foreground">
                            Kênh <strong class="text-foreground">{{ topChannelLabel }}</strong> chiếm tỉ trọng đơn hàng lớn nhất.
                        </p>
                    </div>
                    <div class="flex items-start gap-2">
                        <div class="flex h-5 w-5 shrink-0 items-center justify-center rounded bg-emerald-500/10 text-emerald-500 mt-0.5">
                            <Trophy class="size-3" />
                        </div>
                        <p class="leading-normal text-muted-foreground">
                            Món bán chạy nhất: <strong class="text-foreground">{{ topDishName }}</strong>.
                        </p>
                    </div>
                    <div class="flex items-start gap-2 bg-indigo-500/5 dark:bg-indigo-950/20 border border-indigo-500/10 rounded-xl p-3">
                        <span class="text-sm">💡</span>
                        <p class="leading-relaxed text-indigo-700 dark:text-indigo-300 font-medium">
                            Gợi ý: Thiết lập combo kèm <strong>{{ topDishName }}</strong> trên QR Menu để tăng giá trị trung bình đơn hàng.
                        </p>
                    </div>
                </div>
                <div v-else class="flex flex-col items-center py-6 text-muted-foreground text-center">
                    <Brain class="size-6 text-muted-foreground/30 mb-1.5" />
                    <p class="text-xs">Hệ thống đang phân tích dữ liệu bán hàng của bạn.</p>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
