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

const topDishName = computed(
    () => props.topProductsChartData?.[0]?.name ?? 'chưa có',
);

const topChannelLabel = computed(() => {
    if (!props.channelChartData || props.channelChartData.length === 0) {
        return 'chưa có';
    }

    const sorted = [...props.channelChartData].sort(
        (a, b) => b.count - a.count,
    );

    return sorted[0]?.label ?? 'chưa có';
});

function formatMoney(v: number): string {
    if (v === 0) {
        return '—';
    }

    return (
        new Intl.NumberFormat('vi-VN', {
            notation: 'compact',
            maximumFractionDigits: 1,
        }).format(v) + 'đ'
    );
}
</script>

<template>
    <div class="space-y-4">
        <!-- Dự báo doanh thu ngày mai -->
        <Card
            v-if="forecastData"
            class="overflow-hidden border border-indigo-200 bg-gradient-to-br from-indigo-50 to-violet-50 shadow-sm dark:border-indigo-800/40 dark:from-indigo-950/20 dark:to-violet-950/20"
        >
            <CardHeader
                class="border-b border-indigo-100 pb-2 dark:border-indigo-800/30"
            >
                <CardTitle
                    class="flex items-center gap-2 text-sm font-bold text-indigo-700 dark:text-indigo-300"
                >
                    <Sparkles class="size-4 animate-pulse text-indigo-500" />
                    Dự báo doanh thu ngày mai
                </CardTitle>
                <p
                    class="text-[10px] text-indigo-500/80 dark:text-indigo-400/70"
                >
                    {{ forecastData.day_label }}
                </p>
            </CardHeader>
            <CardContent class="pt-4 text-xs">
                <div class="mb-3 flex items-end gap-3">
                    <div>
                        <p
                            class="text-2xl font-black text-indigo-700 dark:text-indigo-300"
                        >
                            {{ formatMoney(forecastData.amount) }}
                        </p>
                        <p class="mt-0.5 text-[10px] text-indigo-500/70">
                            Dựa trên {{ forecastData.samples }} tuần lịch sử
                        </p>
                    </div>
                    <span
                        :class="[
                            'mb-1 rounded-full border px-2 py-0.5 text-[9px] font-bold',
                            forecastData.confidence === 'high'
                                ? 'border-emerald-200 bg-emerald-100 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400'
                                : forecastData.confidence === 'medium'
                                  ? 'border-amber-200 bg-amber-100 text-amber-700 dark:bg-amber-950/30 dark:text-amber-400'
                                  : 'border-rose-200 bg-rose-100 text-rose-700 dark:bg-rose-950/30 dark:text-rose-400',
                        ]"
                    >
                        Tin cậy: {{ forecastData.confidence_label }}
                    </span>
                </div>
                <!-- So sánh với hôm nay -->
                <div
                    v-if="stats?.revenue_today && forecastData.amount > 0"
                    class="mt-1"
                >
                    <div
                        class="mb-1 flex items-center justify-between text-[10px] text-indigo-500/80"
                    >
                        <span>Hôm nay</span>
                        <span>Dự báo ngày mai</span>
                    </div>
                    <div
                        class="h-2 overflow-hidden rounded-full bg-indigo-100 dark:bg-indigo-900/30"
                    >
                        <div
                            class="h-full rounded-full bg-indigo-500 transition-all"
                            :style="`width: ${Math.min(100, (stats.revenue_today / forecastData.amount) * 100)}%`"
                        />
                    </div>
                    <p class="mt-1 text-[10px] text-indigo-500/70">
                        Hôm nay đạt
                        {{
                            Math.min(
                                100,
                                Math.round(
                                    (stats.revenue_today /
                                        forecastData.amount) *
                                        100,
                                ),
                            )
                        }}% mục tiêu dự báo
                    </p>
                </div>
            </CardContent>
        </Card>

        <!-- AI Insights Card -->
        <Card
            class="premium-ai-glow relative overflow-hidden border border-border bg-card text-card-foreground shadow-sm"
        >
            <div
                class="absolute -right-10 -bottom-10 h-32 w-32 rounded-full bg-indigo-500/5 blur-2xl"
            ></div>
            <CardHeader class="border-b border-border/50 bg-muted/20 pb-2">
                <CardTitle class="flex items-center gap-2 text-base font-bold">
                    <Brain class="size-4.5 animate-pulse text-indigo-500" />
                    Trợ lý AI Aventura
                </CardTitle>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    Phân tích & Khuyến nghị tự động
                </p>
            </CardHeader>
            <CardContent class="relative z-10 space-y-3 pt-4 text-xs">
                <div
                    v-if="
                        topProductsChartData?.length && channelChartData?.length
                    "
                    class="space-y-3"
                >
                    <div class="animate-enter stagger-1 flex items-start gap-2">
                        <div
                            class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded bg-indigo-500/10 text-indigo-500"
                        >
                            <Utensils class="size-3" />
                        </div>
                        <p class="leading-normal text-muted-foreground">
                            Kênh
                            <strong class="text-foreground">{{
                                topChannelLabel
                            }}</strong>
                            chiếm tỉ trọng đơn hàng lớn nhất.
                        </p>
                    </div>
                    <div class="animate-enter stagger-2 flex items-start gap-2">
                        <div
                            class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded bg-emerald-500/10 text-emerald-500"
                        >
                            <Trophy class="size-3" />
                        </div>
                        <p class="leading-normal text-muted-foreground">
                            Món bán chạy nhất:
                            <strong class="text-foreground">{{
                                topDishName
                            }}</strong
                            >.
                        </p>
                    </div>
                    <div
                        class="animate-enter stagger-3 flex items-start gap-2 rounded-xl border border-indigo-500/10 bg-indigo-500/5 p-3 dark:bg-indigo-950/20"
                    >
                        <span class="text-sm">💡</span>
                        <p
                            class="leading-relaxed font-medium text-indigo-700 dark:text-indigo-300"
                        >
                            Gợi ý: Thiết lập combo kèm
                            <strong>{{ topDishName }}</strong> trên QR Menu để
                            tăng giá trị trung bình đơn hàng.
                        </p>
                    </div>
                </div>
                <div
                    v-else
                    class="flex flex-col items-center py-6 text-center text-muted-foreground"
                >
                    <Brain class="mb-1.5 size-6 text-muted-foreground/30" />
                    <p class="text-xs">
                        Hệ thống đang phân tích dữ liệu bán hàng của bạn.
                    </p>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
