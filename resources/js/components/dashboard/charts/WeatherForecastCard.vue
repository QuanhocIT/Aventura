<script setup lang="ts">
import axios from 'axios';
import {
    Sun,
    CloudRain,
    Cloud,
    Wind,
    Thermometer,
    TrendingUp,
    TrendingDown,
    Sparkles,
    Calendar,
    Loader2,
} from 'lucide-vue-next';
import { ref, onMounted } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

const isLoading = ref(true);
const forecastData = ref<any[]>([]);
const selectedDayIndex = ref(0);
const apiSource = ref('');

const fetchForecast = async () => {
    try {
        const response = await axios.get('/api/analytics/weather-menu-forecast');

        if (response.data && response.data.forecast) {
            forecastData.value = response.data.forecast;
            apiSource.value = response.data.source || 'AI Analytics';
        }
    } catch (e) {
        console.error('Failed to fetch weather menu forecast:', e);
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => {
    fetchForecast();
});

// Format date for tabs (e.g. "T2", "29/8")
const formatTabDate = (dateStr: string) => {
    const date = new Date(dateStr);
    const days = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'];

    return {
        dayName: days[date.getDay()],
        dayMonth: `${date.getDate()}/${date.getMonth() + 1}`,
    };
};

const getWeatherIcon = (condition: string) => {
    const cond = (condition || '').toLowerCase();

    if (cond.includes('sun') || cond.includes('nắng')) {
        return Sun;
    }

    if (cond.includes('rain') || cond.includes('mưa')) {
        return CloudRain;
    }

    if (cond.includes('wind') || cond.includes('gió')) {
        return Wind;
    }

    return Cloud;
};

const getWeatherColor = (condition: string) => {
    const cond = (condition || '').toLowerCase();

    if (cond.includes('sun') || cond.includes('nắng')) {
        return 'text-amber-500 bg-amber-500/10 border-amber-500/30';
    }

    if (cond.includes('rain') || cond.includes('mưa')) {
        return 'text-sky-500 bg-sky-500/10 border-sky-500/30';
    }

    if (cond.includes('wind') || cond.includes('gió')) {
        return 'text-teal-500 bg-teal-500/10 border-teal-500/30';
    }

    return 'text-slate-400 bg-slate-500/10 border-slate-500/20';
};
</script>

<template>
    <Card class="relative overflow-hidden border border-slate-200 bg-white text-slate-900 shadow-sm dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-100">
        <div class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-amber-500/10 blur-2xl"></div>

        <CardHeader class="flex flex-row items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/50 pb-3 dark:border-slate-800 dark:bg-slate-900/40">
            <div>
                <CardTitle class="flex items-center gap-2 text-sm font-bold text-slate-900 dark:text-white">
                    <Sparkles class="size-4 animate-pulse text-amber-500" />
                    AI Dự báo Món Ăn Theo Thời Tiết
                </CardTitle>
                <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                    Khuyến nghị chuẩn bị nguyên liệu & chiến dịch marketing
                </p>
            </div>
            <span
                v-if="apiSource"
                class="shrink-0 rounded-md border border-amber-500/30 bg-amber-500/10 px-2 py-0.5 text-[9px] font-bold text-amber-600 dark:text-amber-400"
            >
                {{ apiSource }}
            </span>
        </CardHeader>

        <CardContent class="space-y-4 pt-4 text-xs">
            <!-- Loading state -->
            <div v-if="isLoading" class="flex flex-col items-center py-10 text-center text-slate-400">
                <Loader2 class="mb-2.5 size-7 animate-spin text-amber-500" />
                <p class="text-xs">Đang phân tích dữ liệu thời tiết & sức mua món ăn...</p>
            </div>

            <div v-else-if="forecastData.length > 0" class="space-y-4">
                <!-- 7 Day selector grid (Fixed uniform width, no clipping or distortion) -->
                <div class="grid grid-cols-7 gap-1 sm:gap-1.5 w-full">
                    <button
                        v-for="(day, idx) in forecastData"
                        :key="idx"
                        type="button"
                        @click="selectedDayIndex = idx"
                        :class="[
                            'flex cursor-pointer flex-col items-center justify-between rounded-xl border py-2 px-1 text-center transition-all select-none',
                            selectedDayIndex === idx
                                ? 'border-amber-500 bg-amber-500/15 font-bold text-amber-600 dark:text-amber-400 shadow-xs ring-1 ring-amber-500/40'
                                : 'border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-900/60 text-slate-600 dark:text-slate-400 hover:border-slate-300 dark:hover:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-slate-200',
                        ]"
                    >
                        <span class="text-[10px] font-extrabold uppercase tracking-tight">{{ formatTabDate(day.date).dayName }}</span>
                        <span class="my-0.5 text-xs font-black leading-none">{{ formatTabDate(day.date).dayMonth }}</span>
                        <component :is="getWeatherIcon(day.condition)" class="size-3.5 shrink-0 mt-0.5 text-amber-500 dark:text-amber-400" />
                    </button>
                </div>

                <!-- Selected day detail card -->
                <div class="space-y-3 rounded-2xl border border-slate-200 bg-slate-50/80 p-3.5 dark:border-slate-800 dark:bg-slate-900/50">
                    <div class="flex items-center justify-between border-b border-slate-200/80 pb-2.5 dark:border-slate-800">
                        <div class="flex items-center gap-2.5">
                            <span :class="['rounded-xl border p-2', getWeatherColor(forecastData[selectedDayIndex]?.condition)]">
                                <component :is="getWeatherIcon(forecastData[selectedDayIndex]?.condition)" class="size-4" />
                            </span>
                            <div>
                                <h4 class="text-xs font-bold text-slate-900 capitalize dark:text-white">
                                    {{
                                        forecastData[selectedDayIndex]?.condition === 'sunny'
                                            ? 'Nắng ráo'
                                            : forecastData[selectedDayIndex]?.condition === 'rainy'
                                              ? 'Trời mưa lạnh'
                                              : forecastData[selectedDayIndex]?.condition === 'windy'
                                                ? 'Trời lộng gió'
                                                : 'Nhiều mây / Mát mẻ'
                                    }}
                                </h4>
                                <p class="text-[10px] text-slate-500 dark:text-slate-400">
                                    Thời tiết dự kiến ngày {{ forecastData[selectedDayIndex]?.date }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-1 text-sm font-black text-slate-900 dark:text-white">
                            <Thermometer class="size-4 text-rose-500" />
                            <span>{{ forecastData[selectedDayIndex]?.temperature }}°C</span>
                        </div>
                    </div>

                    <!-- Recommendations list -->
                    <div class="space-y-2">
                        <h5 class="flex items-center gap-1.5 text-[10px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            <Calendar class="size-3 text-amber-500" /> AI Đề xuất thực đơn ca trực:
                        </h5>

                        <div
                            v-if="forecastData[selectedDayIndex]?.recommendations?.length > 0"
                            class="max-h-[220px] space-y-2 overflow-y-auto pr-0.5"
                        >
                            <div
                                v-for="(rec, rIdx) in forecastData[selectedDayIndex].recommendations"
                                :key="rIdx"
                                class="space-y-1.5 rounded-xl border border-slate-200 bg-white p-3 shadow-xs dark:border-slate-800 dark:bg-slate-950"
                            >
                                <div class="flex items-center justify-between">
                                    <div class="min-w-0 pr-2">
                                        <span class="block truncate text-xs font-bold text-slate-900 dark:text-white">{{ rec.product_name }}</span>
                                        <span class="mt-0.5 inline-block rounded border border-slate-200 bg-slate-100 px-1.5 py-0.5 text-[9px] font-semibold text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400">
                                            {{ rec.category_name }}
                                        </span>
                                    </div>
                                    <span
                                        :class="[
                                            'flex shrink-0 items-center gap-0.5 rounded-full border px-2 py-0.5 text-[9px] font-black',
                                            rec.change_pct >= 0
                                                ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                                : 'border-rose-500/30 bg-rose-500/10 text-rose-600 dark:text-rose-400',
                                        ]"
                                    >
                                        <component :is="rec.change_pct >= 0 ? TrendingUp : TrendingDown" class="size-2.5" />
                                        {{ rec.change_pct >= 0 ? '+' : '' }}{{ rec.change_pct }}%
                                    </span>
                                </div>
                                <p class="text-[10px] leading-relaxed text-slate-600 dark:text-slate-300">
                                    {{ rec.reason }}
                                </p>
                                <div class="flex items-center justify-between border-t border-slate-100 pt-1.5 text-[9px] text-slate-500 dark:border-slate-800/80 dark:text-slate-400">
                                    <span>Bán ngày thường: <strong class="text-slate-800 dark:text-slate-200">{{ rec.avg_daily_sales }}</strong> đv</span>
                                    <span class="font-bold text-amber-600 dark:text-amber-400">Dự báo: <strong>{{ rec.predicted_sales }}</strong> đv</span>
                                </div>
                            </div>
                        </div>

                        <div v-else class="py-5 text-center text-xs italic text-slate-400">
                            💡 Dự báo thời tiết ôn hòa, sức mua thực đơn dự kiến ổn định ở mức trung bình.
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="flex flex-col items-center py-8 text-center text-slate-400">
                <Sparkles class="mb-2 size-7 text-slate-300 dark:text-slate-700" />
                <p class="text-xs">Không có dữ liệu dự báo thời tiết.</p>
            </div>
        </CardContent>
    </Card>
</template>
