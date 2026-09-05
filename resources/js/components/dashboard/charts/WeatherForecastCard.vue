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
        const response = await axios.get(
            '/api/analytics/weather-menu-forecast',
        );

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
        return 'text-amber-500 bg-amber-500/10 border-amber-500/20 dark:text-amber-400 dark:border-amber-400/20 dark:bg-amber-400/10';
    }

    if (cond.includes('rain') || cond.includes('mưa')) {
        return 'text-sky-500 bg-sky-500/10 border-sky-500/20 dark:text-sky-400 dark:border-sky-400/20 dark:bg-sky-400/10';
    }

    if (cond.includes('wind') || cond.includes('gió')) {
        return 'text-teal-500 bg-teal-500/10 border-teal-500/20 dark:text-teal-400 dark:border-teal-400/20 dark:bg-teal-400/10';
    }

    return 'text-slate-500 bg-slate-500/10 border-slate-500/20 dark:text-slate-400 dark:border-slate-400/20 dark:bg-slate-400/10';
};

const getWeatherIconColor = (condition: string, isSelected: boolean) => {
    if (isSelected) {
        return 'text-sky-600 dark:text-sky-300';
    }

    const cond = (condition || '').toLowerCase();

    if (cond.includes('sun') || cond.includes('nắng')) {
        return 'text-amber-500/80 dark:text-amber-400/80';
    }

    if (cond.includes('rain') || cond.includes('mưa')) {
        return 'text-sky-500/80 dark:text-sky-400/80';
    }

    if (cond.includes('wind') || cond.includes('gió')) {
        return 'text-teal-500/80 dark:text-teal-400/80';
    }

    return 'text-slate-400 dark:text-slate-500';
};
</script>

<template>
    <Card
        class="relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white text-slate-900 shadow-xs transition-all duration-200 hover:shadow-md dark:border-slate-800/80 dark:bg-slate-900/60 dark:text-slate-100"
    >
        <CardHeader
            class="flex flex-row items-center justify-between gap-3 border-b border-slate-100 pb-3 dark:border-slate-800/80"
        >
            <div class="flex items-center gap-2.5">
                <div
                    class="flex size-8 shrink-0 items-center justify-center rounded-xl border border-sky-500/20 bg-sky-500/10 text-sky-600 dark:border-sky-400/20 dark:bg-sky-400/10 dark:text-sky-400"
                >
                    <Sparkles class="size-4" />
                </div>
                <div>
                    <CardTitle
                        class="text-sm font-bold tracking-tight text-slate-900 dark:text-slate-100"
                    >
                        AI Dự báo Món Ăn Theo Thời Tiết
                    </CardTitle>
                    <p
                        class="mt-0.5 text-[11px] font-medium text-slate-500 dark:text-slate-400"
                    >
                        Khuyến nghị chuẩn bị nguyên liệu & chiến dịch marketing
                    </p>
                </div>
            </div>
            <span
                v-if="apiSource"
                class="shrink-0 inline-flex items-center gap-1.5 rounded-full border border-sky-500/20 bg-sky-500/5 px-2.5 py-0.5 text-[10px] font-medium text-sky-700 dark:border-sky-400/20 dark:bg-sky-400/10 dark:text-sky-300"
            >
                <span class="size-1.5 rounded-full bg-sky-500 animate-pulse" />
                {{ apiSource.replace(/\s*\(Rules Engine\)/i, '').replace(/Laravel Fallback/i, 'AI Engine') }}
            </span>
        </CardHeader>

        <CardContent class="space-y-4 pt-4 text-xs">
            <!-- Loading state -->
            <div
                v-if="isLoading"
                class="flex flex-col items-center py-10 text-center text-slate-400"
            >
                <Loader2 class="mb-2.5 size-7 animate-spin text-sky-500" />
                <p class="text-xs">
                    Đang phân tích dữ liệu thời tiết & sức mua món ăn...
                </p>
            </div>

            <div v-else-if="forecastData.length > 0" class="space-y-4">
                <!-- 7 Day selector grid (Fixed uniform width, no clipping or distortion) -->
                <div class="grid w-full grid-cols-7 gap-1 sm:gap-1.5">
                    <button
                        v-for="(day, idx) in forecastData"
                        :key="idx"
                        type="button"
                        @click="selectedDayIndex = idx"
                        :class="[
                            'flex cursor-pointer flex-col items-center justify-between rounded-xl border px-1 py-2 text-center transition-all select-none',
                            selectedDayIndex === idx
                                ? 'border-sky-500/50 bg-sky-500/10 font-bold text-sky-700 shadow-xs dark:border-sky-400/50 dark:bg-sky-400/15 dark:text-sky-300'
                                : 'border-slate-200/80 bg-slate-50/70 text-slate-600 hover:border-slate-300 hover:bg-slate-100 hover:text-slate-900 dark:border-slate-800/80 dark:bg-slate-900/50 dark:text-slate-400 dark:hover:border-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200',
                        ]"
                    >
                        <span
                            class="text-[10.5px] font-extrabold tracking-tight uppercase"
                            >{{ formatTabDate(day.date).dayName }}</span
                        >
                        <span class="my-0.5 text-xs leading-none font-bold">{{
                            formatTabDate(day.date).dayMonth
                        }}</span>
                        <component
                            :is="getWeatherIcon(day.condition)"
                            class="mt-0.5 size-3.5 shrink-0"
                            :class="getWeatherIconColor(day.condition, selectedDayIndex === idx)"
                        />
                    </button>
                </div>

                <!-- Selected day detail card -->
                <div
                    class="space-y-3 rounded-2xl border border-slate-200/80 bg-slate-50/60 p-3.5 dark:border-slate-800/80 dark:bg-slate-900/40"
                >
                    <div
                        class="flex items-center justify-between border-b border-slate-200/60 pb-2.5 dark:border-slate-800/60"
                    >
                        <div class="flex items-center gap-2.5">
                            <span
                                :class="[
                                    'rounded-xl border p-2',
                                    getWeatherColor(
                                        forecastData[selectedDayIndex]
                                            ?.condition,
                                    ),
                                ]"
                            >
                                <component
                                    :is="
                                        getWeatherIcon(
                                            forecastData[selectedDayIndex]
                                                ?.condition,
                                        )
                                    "
                                    class="size-4"
                                />
                            </span>
                            <div>
                                <h4
                                    class="text-xs font-bold text-slate-900 capitalize dark:text-slate-100"
                                >
                                    {{
                                        forecastData[selectedDayIndex]
                                            ?.condition === 'sunny'
                                            ? 'Nắng ráo'
                                            : forecastData[selectedDayIndex]
                                                    ?.condition === 'rainy'
                                              ? 'Trời mưa lạnh'
                                              : forecastData[selectedDayIndex]
                                                      ?.condition === 'windy'
                                                ? 'Trời lộng gió'
                                                : 'Nhiều mây / Mát mẻ'
                                    }}
                                </h4>
                                <p
                                    class="text-[10.5px] text-slate-500 dark:text-slate-400"
                                >
                                    Thời tiết dự kiến ngày
                                    {{ forecastData[selectedDayIndex]?.date }}
                                </p>
                            </div>
                        </div>
                        <div
                            class="flex items-center gap-1.5 text-sm font-extrabold text-slate-900 dark:text-slate-100"
                        >
                            <Thermometer class="size-4 text-rose-500/80 dark:text-rose-400" />
                            <span
                                >{{
                                    forecastData[selectedDayIndex]?.temperature
                                }}°C</span
                            >
                        </div>
                    </div>

                    <!-- Recommendations list -->
                    <div class="space-y-2.5">
                        <h5
                            class="flex items-center gap-1.5 text-[11px] font-bold tracking-wider text-slate-700 uppercase dark:text-slate-300"
                        >
                            <Calendar class="size-3.5 text-sky-500 dark:text-sky-400" /> AI Đề
                            xuất thực đơn ca trực:
                        </h5>

                        <div
                            v-if="
                                forecastData[selectedDayIndex]?.recommendations
                                    ?.length > 0
                            "
                            class="max-h-[220px] space-y-2 overflow-y-auto pr-0.5"
                        >
                            <div
                                v-for="(rec, rIdx) in forecastData[
                                    selectedDayIndex
                                ].recommendations"
                                :key="rIdx"
                                class="space-y-1.5 rounded-xl border border-slate-200/80 bg-white p-3 shadow-xs dark:border-slate-800/80 dark:bg-slate-950/80"
                            >
                                <div class="flex items-center justify-between">
                                    <div class="min-w-0 pr-2">
                                        <span
                                            class="block truncate text-xs font-bold text-slate-900 dark:text-slate-100"
                                            >{{ rec.product_name }}</span
                                        >
                                        <span
                                            class="mt-0.5 inline-block rounded border border-slate-200 bg-slate-100 px-1.5 py-0.5 text-[9.5px] font-medium text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400"
                                        >
                                            {{ rec.category_name }}
                                        </span>
                                    </div>
                                    <span
                                        :class="[
                                            'flex shrink-0 items-center gap-0.5 rounded-full border px-2 py-0.5 text-[9.5px] font-bold',
                                            rec.change_pct >= 0
                                                ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-700 dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-300'
                                                : 'border-rose-500/20 bg-rose-500/10 text-rose-700 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-300',
                                        ]"
                                    >
                                        <component
                                            :is="
                                                rec.change_pct >= 0
                                                    ? TrendingUp
                                                    : TrendingDown
                                            "
                                            class="size-2.5"
                                        />
                                        {{ rec.change_pct >= 0 ? '+' : ''
                                        }}{{ rec.change_pct }}%
                                    </span>
                                </div>
                                <p
                                    class="text-[10.5px] leading-relaxed text-slate-600 dark:text-slate-300"
                                >
                                    {{ rec.reason }}
                                </p>
                                <div
                                    class="flex items-center justify-between border-t border-slate-100 pt-1.5 text-[10px] text-slate-500 dark:border-slate-800/80 dark:text-slate-400"
                                >
                                    <span
                                        >Bán ngày thường:
                                        <strong
                                            class="text-slate-800 dark:text-slate-200"
                                            >{{ rec.avg_daily_sales }}</strong
                                        >
                                        đv</span
                                    >
                                    <span
                                        class="font-semibold text-sky-600 dark:text-sky-400"
                                        >Dự báo:
                                        <strong>{{
                                            rec.predicted_sales
                                        }}</strong>
                                        đv</span
                                    >
                                </div>
                            </div>
                        </div>

                        <div
                            v-else
                            class="flex items-center gap-3 rounded-xl border border-slate-200/60 bg-white/60 p-3 text-xs text-slate-600 dark:border-slate-800/60 dark:bg-slate-950/40 dark:text-slate-300"
                        >
                            <div class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-sky-500/10 text-sky-500 dark:bg-sky-400/10 dark:text-sky-400">
                                <Sparkles class="size-3.5" />
                            </div>
                            <p class="text-[11px] leading-relaxed">
                                Dự báo thời tiết ôn hòa, sức mua thực đơn dự kiến ổn định ở mức trung bình.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-else
                class="flex flex-col items-center py-8 text-center text-slate-400"
            >
                <Sparkles
                    class="mb-2 size-7 text-slate-300 dark:text-slate-700"
                />
                <p class="text-xs">Không có dữ liệu dự báo thời tiết.</p>
            </div>
        </CardContent>
    </Card>
</template>
