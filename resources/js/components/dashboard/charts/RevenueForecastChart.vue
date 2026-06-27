<script setup lang="ts">
import { TrendingUp, Sparkles, BarChart3 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useFeatureGate } from '@/composables/useFeatureGate';
import { Link } from '@inertiajs/vue3';

const { can } = useFeatureGate();

interface RevenueDay {
    date: string;
    revenue: number;
    orders: number;
    is_forecast?: boolean;
}

interface ForecastData {
    amount: number;
    confidence: string;
    confidence_label: string;
    samples: number;
    day_label: string;
    trend_factor: number;
}

const props = defineProps<{
    revenueChartData: RevenueDay[] | undefined;
    forecastData: ForecastData | null | undefined;
}>();

const activeHoverIndex = ref<number | null>(null);

const hasForecast = computed(() => {
    return can('ai_forecasting') && props.revenueChartData?.some(d => d.is_forecast);
});

const revenueChartList = computed(() => props.revenueChartData ?? []);

const maxRevenue = computed(() => {
    const vals = revenueChartList.value.map(d => d.revenue);

    return Math.max(...vals, 100000); // at least 100k
});

const maxOrders = computed(() => {
    const list = revenueChartList.value.map(d => d.orders);

    return Math.max(...list, 5);
});

const ordersLinePath = computed(() => {
    const list = revenueChartList.value;

    if (list.length === 0) {
return '';
}

    return list.map((day, i) => {
        const x = i * 85 + 80;
        const y = 160 - (day.orders / maxOrders.value) * 125;

        return `${i === 0 ? 'M' : 'L'} ${x} ${y}`;
    }).join(' ');
});

function formatMoneyFull(v: number): string {
    if (v === 0) {
        return '—';
    }

    return new Intl.NumberFormat('vi-VN').format(v) + 'đ';
}
</script>

<template>
    <Card class="relative overflow-hidden bg-card text-card-foreground border border-border shadow-sm">
        <CardHeader class="pb-2 border-b border-border/50">
            <div class="flex items-center justify-between">
                <div>
                    <CardTitle class="text-base font-bold flex items-center gap-2">
                        <TrendingUp class="size-4 text-violet-500" />
                        {{ hasForecast ? 'Doanh thu 7 ngày qua + 7 ngày dự báo' : 'Phân tích doanh thu 7 ngày qua' }}
                    </CardTitle>
                    <p class="text-xs text-muted-foreground mt-0.5">
                        <span v-if="hasForecast">Cột đặc = thực tế · Cột mờ = <span class="text-indigo-400 font-medium">AI dự báo</span></span>
                        <span v-else>Theo dõi doanh thu thực tế và tổng số lượng đơn hàng theo ngày.</span>
                        <span v-if="hasForecast && forecastData?.confidence_label" class="ml-1 inline-flex items-center gap-0.5 bg-indigo-100 dark:bg-indigo-950/40 text-indigo-600 dark:text-indigo-400 px-1.5 py-0.5 rounded text-[9px] font-semibold">
                            <Sparkles class="size-2.5" /> Độ tin cậy: {{ forecastData.confidence_label }}
                        </span>
                    </p>
                </div>
                <div class="flex items-center gap-4 text-xs font-semibold">
                    <span class="flex items-center gap-1.5 text-indigo-500">
                        <span class="h-3 w-3 rounded bg-indigo-500/80"></span> Doanh thu
                    </span>
                    <span class="flex items-center gap-1.5 text-emerald-500">
                        <span class="h-3 w-3 rounded-full border-2 border-emerald-500 bg-background"></span> Đơn hàng
                    </span>
                </div>
            </div>
        </CardHeader>
        
        <CardContent class="pt-6">
            <div v-if="revenueChartList.length" class="relative">
                <!-- Tooltip Floating -->
                <div v-if="activeHoverIndex !== null" 
                     class="absolute z-20 bg-slate-900/95 text-white text-xs p-3 rounded-xl shadow-xl border border-slate-700/50 backdrop-blur-md pointer-events-none transition-all duration-150"
                     :style="{
                         left: `${(activeHoverIndex / revenueChartList.length) * 85 + 12}%`,
                         transform: 'translateX(-50%)',
                         top: '-15px'
                     }"
                >
                    <p class="font-bold text-slate-300 border-b border-slate-700 pb-1 mb-1">Ngày {{ revenueChartList[activeHoverIndex].date }}</p>
                    <p class="flex justify-between gap-4">
                        <span class="text-slate-400">Doanh thu:</span>
                        <span class="font-mono font-bold text-indigo-400">{{ formatMoneyFull(revenueChartList[activeHoverIndex].revenue) }}</span>
                    </p>
                    <p class="flex justify-between gap-4 mt-0.5">
                        <span class="text-slate-400">Đơn hàng:</span>
                        <span class="font-mono font-bold text-emerald-400">{{ revenueChartList[activeHoverIndex].orders }} đơn</span>
                    </p>
                </div>

                <!-- The Chart SVG -->
                <svg viewBox="0 0 700 200" class="w-full h-48 overflow-visible">
                    <!-- Y-Axis Grid Lines -->
                    <line x1="50" y1="20" x2="650" y2="20" class="stroke-muted/30 stroke-1" stroke-dasharray="4" />
                    <line x1="50" y1="90" x2="650" y2="90" class="stroke-muted/30 stroke-1" stroke-dasharray="4" />
                    <line x1="50" y1="160" x2="650" y2="160" class="stroke-muted" stroke-width="1.5" />

                    <!-- Bars and Points -->
                    <g v-for="(day, i) in revenueChartList" :key="day.date">
                        <!-- Interactive background column area for hovering -->
                        <rect :x="i * 85 + 50" y="10" width="60" height="150" 
                              class="fill-transparent hover:fill-muted/20 cursor-pointer transition-colors duration-150 rounded"
                              @mouseenter="activeHoverIndex = i"
                              @mouseleave="activeHoverIndex = null"
                        />

                        <!-- Revenue Bar (forecast = mờ hơn, có stroke-dasharray) -->
                        <rect :x="i * 85 + 65"
                              :y="160 - (day.revenue / maxRevenue) * 130"
                              width="30"
                              :height="Math.max((day.revenue / maxRevenue) * 130, day.revenue > 0 ? 4 : 0)"
                              rx="4"
                              :class="day.is_forecast
                                  ? 'fill-indigo-300/50 dark:fill-indigo-400/30 stroke-indigo-400 stroke-1'
                                  : 'fill-indigo-500/80 hover:fill-indigo-600 dark:fill-indigo-500/60 dark:hover:fill-indigo-500 transition-colors pointer-events-none duration-150'"
                              :stroke-dasharray="day.is_forecast ? '4 2' : 'none'"
                        />
                    </g>

                    <!-- SVG Orders Line Trend Overlay -->
                    <path :d="ordersLinePath" fill="none" class="stroke-emerald-500" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />

                    <!-- Dots on line for each day -->
                    <g v-for="(day, i) in revenueChartList" :key="'dot-' + day.date">
                        <circle :cx="i * 85 + 80" 
                                :cy="160 - (day.orders / maxOrders) * 125" 
                                r="5" 
                                class="fill-background stroke-emerald-500 stroke-2 pointer-events-none" 
                        />
                        <!-- Glowing effect circle when hovered -->
                        <circle v-if="activeHoverIndex === i"
                                :cx="i * 85 + 80" 
                                :cy="160 - (day.orders / maxOrders) * 125" 
                                r="9" 
                                class="fill-emerald-500/30 stroke-none pointer-events-none animate-ping" 
                        />
                    </g>
                    
                    <!-- X-Axis Labels -->
                    <text v-for="(day, i) in revenueChartList" :key="'txt-' + day.date"
                          :x="i * 85 + 80" 
                          y="182" 
                          text-anchor="middle" 
                          class="fill-muted-foreground text-[10px] font-medium font-sans"
                    >
                        {{ day.date }}
                    </text>
                </svg>
            </div>
            <div v-else class="flex flex-col items-center justify-center py-8 text-muted-foreground text-sm">
                <BarChart3 class="size-6 text-muted-foreground/30 mb-1.5" />
                Chưa có dữ liệu doanh số tuần này.
            </div>
            <!-- Tip upgrade AI forecasting -->
            <div v-if="!can('ai_forecasting')" class="p-3 bg-slate-50/50 dark:bg-slate-900/10 flex items-center justify-between text-xs rounded-b-2xl border-t border-dashed border-border/80">
                <span class="text-muted-foreground font-medium">💡 Muốn dự báo doanh thu 7 ngày tiếp theo bằng Trí Tuệ Nhân Tạo?</span>
                <Link href="/billing/history" class="text-indigo-650 dark:text-indigo-400 font-extrabold hover:underline flex items-center gap-0.5">
                    Nâng cấp Doanh Nghiệp →
                </Link>
            </div>
        </CardContent>
    </Card>
</template>
