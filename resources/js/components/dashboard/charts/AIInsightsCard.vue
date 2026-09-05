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
            class="relative overflow-hidden rounded-2xl border border-slate-200/80 !bg-transparent backdrop-blur-none shadow-xs transition-all duration-200 hover:shadow-md dark:border-slate-800/80 dark:!bg-transparent"
        >
            <CardHeader
                class="flex flex-row items-center justify-between border-b border-slate-100 !bg-transparent pb-3 dark:border-slate-800/80 dark:!bg-transparent"
            >
                <div class="flex items-center gap-2.5">
                    <div
                        class="flex size-8 shrink-0 items-center justify-center rounded-xl border border-indigo-500/20 bg-indigo-500/10 text-indigo-600 dark:border-indigo-400/20 dark:bg-indigo-400/10 dark:text-indigo-400"
                    >
                        <Sparkles class="size-4" />
                    </div>
                    <div>
                        <CardTitle
                            class="text-sm font-bold tracking-tight text-slate-900 dark:text-slate-100"
                        >
                            Dự báo doanh thu ngày mai
                        </CardTitle>
                        <p
                            class="mt-0.5 text-[11px] font-medium text-slate-500 capitalize dark:text-slate-400"
                        >
                            {{ forecastData.day_label }}
                        </p>
                    </div>
                </div>

                <span
                    :class="[
                        'inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[10px] font-semibold',
                        forecastData.confidence === 'high'
                            ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-300'
                            : forecastData.confidence === 'medium'
                              ? 'border-amber-500/20 bg-amber-500/10 text-amber-700 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-300'
                              : 'border-rose-500/20 bg-rose-500/10 text-rose-700 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-300',
                    ]"
                >
                    <span
                        class="size-1.5 rounded-full"
                        :class="
                            forecastData.confidence === 'high'
                                ? 'bg-emerald-500'
                                : forecastData.confidence === 'medium'
                                  ? 'bg-amber-500'
                                  : 'bg-rose-500'
                        "
                    />
                    {{
                        forecastData.confidence_label
                            ? forecastData.confidence_label.replace(/\s*\(Laravel Fallback\)/i, '')
                            : 'Tin cậy: Cao'
                    }}
                </span>
            </CardHeader>
            <CardContent class="pt-4 text-xs space-y-3.5">
                <div>
                    <p
                        class="text-2xl sm:text-[28px] font-black tracking-tight text-slate-900 tabular-nums dark:text-slate-100"
                    >
                        {{ formatMoney(forecastData.amount) }}
                    </p>
                    <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                        Dựa trên {{ forecastData.samples }} tuần lịch sử
                    </p>
                </div>

                <!-- So sánh với hôm nay -->
                <div
                    v-if="stats?.revenue_today && forecastData.amount > 0"
                    class="space-y-1.5 rounded-xl border border-slate-100/80 bg-transparent p-2.5 dark:border-slate-800/60 dark:bg-transparent"
                >
                    <div
                        class="flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400"
                    >
                        <span>Thực tế hôm nay</span>
                        <span class="font-semibold text-slate-700 dark:text-slate-300">
                            {{
                                Math.min(
                                    100,
                                    Math.round(
                                        (stats.revenue_today /
                                            forecastData.amount) *
                                            100,
                                    ),
                                )
                            }}% mục tiêu
                        </span>
                    </div>
                    <div
                        class="h-2 overflow-hidden rounded-full bg-slate-200/70 dark:bg-slate-800"
                    >
                        <div
                            class="h-full rounded-full bg-gradient-to-r from-indigo-500 to-violet-500 transition-all duration-500"
                            :style="`width: ${Math.min(100, (stats.revenue_today / forecastData.amount) * 100)}%`"
                        />
                    </div>
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
