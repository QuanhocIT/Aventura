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

// Format date for tabs (e.g. "Th 2", "14/06")
const formatTabDate = (dateStr: string) => {
    const date = new Date(dateStr);
    const days = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'];

    return {
        dayName: days[date.getDay()],
        dayMonth: `${date.getDate()}/${date.getMonth() + 1}`,
    };
};

const getWeatherIcon = (condition: string) => {
    const cond = condition.toLowerCase();

    if (cond.includes('sun') || cond.includes('nắng')) {
        return Sun;
    }

    if (cond.includes('rain') || cond.includes('mưa')) {
        return CloudRain;
    }

    if (cond.includes('wind') || cond.includes('gió')) {
        return Wind;
    }

    return Cloud; // default to cloudy
};

const getWeatherColor = (condition: string) => {
    const cond = condition.toLowerCase();

    if (cond.includes('sun') || cond.includes('nắng')) {
        return 'text-amber-500 bg-amber-500/10 border-amber-500/20';
    }

    if (cond.includes('rain') || cond.includes('mưa')) {
        return 'text-sky-500 bg-sky-500/10 border-sky-500/20';
    }

    if (cond.includes('wind') || cond.includes('gió')) {
        return 'text-teal-500 bg-teal-500/10 border-teal-500/20';
    }

    return 'text-slate-400 bg-slate-500/10 border-slate-500/10';
};
</script>

<template>
    <Card
        class="relative overflow-hidden border border-border bg-card text-card-foreground shadow-sm"
    >
        <div
            class="absolute -top-10 -right-10 h-32 w-32 rounded-full bg-amber-500/5 blur-2xl"
        ></div>

        <CardHeader
            class="flex flex-row items-center justify-between gap-4 border-b border-border/50 bg-muted/20 pb-2"
        >
            <div>
                <CardTitle class="flex items-center gap-2 text-base font-bold">
                    <Sparkles class="size-4.5 animate-pulse text-amber-500" />
                    AI Dự báo Món Ăn Theo Thời Tiết
                </CardTitle>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    Khuyến nghị chuẩn bị nguyên liệu & chiến dịch marketing
                </p>
            </div>
            <span
                v-if="apiSource"
                class="shrink-0 rounded border border-amber-500/20 bg-amber-500/10 px-1.5 py-0.5 text-[9px] font-medium text-amber-600"
            >
                {{ apiSource }}
            </span>
        </CardHeader>

        <CardContent class="space-y-4 pt-4 text-xs">
            <!-- Loading state -->
            <div
                v-if="isLoading"
                class="flex flex-col items-center py-12 text-center text-muted-foreground"
            >
                <Loader2 class="mb-3 size-8 animate-spin text-amber-500" />
                <p class="text-xs">
                    Đang tải phân tích thời tiết & nhu cầu món ăn...
                </p>
            </div>

            <div v-else-if="forecastData.length > 0" class="space-y-4">
                <!-- Day selector tabs -->
                <div
                    class="flex scrollbar-none items-center gap-1.5 overflow-x-auto pb-1"
                >
                    <button
                        v-for="(day, idx) in forecastData"
                        :key="idx"
                        @click="selectedDayIndex = idx"
                        :class="[
                            'flex min-w-[50px] flex-1 cursor-pointer flex-col items-center rounded-xl border p-2 text-center transition-all select-none',
                            selectedDayIndex === idx
                                ? 'scale-[1.03] border-amber-500 bg-amber-500/10 font-bold text-amber-500 shadow-sm'
                                : 'border-border/50 bg-muted/40 text-muted-foreground hover:bg-muted/60 hover:text-foreground',
                        ]"
                    >
                        <span class="text-[9px] tracking-wider uppercase">{{
                            formatTabDate(day.date).dayName
                        }}</span>
                        <span class="my-1 text-xs font-black">{{
                            formatTabDate(day.date).dayMonth
                        }}</span>
                        <component
                            :is="getWeatherIcon(day.condition)"
                            class="size-3.5"
                        />
                    </button>
                </div>

                <!-- Selected day detail card -->
                <div
                    class="space-y-3 rounded-2xl border border-border/60 bg-muted/30 p-4"
                >
                    <div
                        class="flex items-center justify-between border-b border-border/50 pb-2"
                    >
                        <div class="flex items-center gap-2">
                            <span
                                :class="[
                                    'rounded-lg border p-1.5',
                                    getWeatherColor(
                                        forecastData[selectedDayIndex]
                                            .condition,
                                    ),
                                ]"
                            >
                                <component
                                    :is="
                                        getWeatherIcon(
                                            forecastData[selectedDayIndex]
                                                .condition,
                                        )
                                    "
                                    class="size-4"
                                />
                            </span>
                            <div>
                                <h4
                                    class="text-xs font-bold text-foreground capitalize"
                                >
                                    {{
                                        forecastData[selectedDayIndex]
                                            .condition === 'sunny'
                                            ? 'Nắng ráo'
                                            : forecastData[selectedDayIndex]
                                                    .condition === 'rainy'
                                              ? 'Trời mưa lạnh'
                                              : forecastData[selectedDayIndex]
                                                      .condition === 'windy'
                                                ? 'Trời lộng gió'
                                                : 'Nhiều mây / Mát mẻ'
                                    }}
                                </h4>
                                <p class="text-[10px] text-muted-foreground">
                                    Thời tiết dự kiến ngày
                                    {{ forecastData[selectedDayIndex].date }}
                                </p>
                            </div>
                        </div>
                        <div
                            class="flex items-center gap-1 text-sm font-black text-foreground"
                        >
                            <Thermometer class="size-4 text-rose-500" />
                            <span
                                >{{
                                    forecastData[selectedDayIndex].temperature
                                }}°C</span
                            >
                        </div>
                    </div>

                    <!-- Recommendations list -->
                    <div class="space-y-2.5">
                        <h5
                            class="flex items-center gap-1 text-[10px] font-black tracking-wider text-muted-foreground uppercase"
                        >
                            <Calendar class="size-3 text-amber-500" /> AI Đề
                            xuất thực đơn ca trực:
                        </h5>

                        <div
                            v-if="
                                forecastData[selectedDayIndex].recommendations
                                    .length > 0
                            "
                            class="max-h-[220px] space-y-2 overflow-y-auto pr-0.5"
                        >
                            <div
                                v-for="(rec, rIdx) in forecastData[
                                    selectedDayIndex
                                ].recommendations"
                                :key="rIdx"
                                class="space-y-1.5 rounded-xl border border-border/50 bg-card p-3 transition-all hover:border-border"
                            >
                                <div class="flex items-center justify-between">
                                    <div class="min-w-0 pr-2">
                                        <span
                                            class="block truncate text-xs font-bold text-foreground"
                                            >{{ rec.product_name }}</span
                                        >
                                        <span
                                            class="mt-0.5 inline-block rounded border border-border/50 bg-muted px-1 text-[8px] font-semibold text-muted-foreground"
                                            >{{ rec.category_name }}</span
                                        >
                                    </div>
                                    <span
                                        :class="[
                                            'flex shrink-0 items-center gap-0.5 rounded-full border px-2 py-0.5 text-[9px] font-black',
                                            rec.change_pct >= 0
                                                ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-500'
                                                : 'border-rose-500/20 bg-rose-500/10 text-rose-500',
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
                                    class="text-[10px] leading-relaxed text-muted-foreground"
                                >
                                    {{ rec.reason }}
                                </p>
                                <div
                                    class="flex items-center justify-between border-t border-border/30 pt-1 text-[9px] text-muted-foreground"
                                >
                                    <span
                                        >Bán ngày thường:
                                        <strong>{{
                                            rec.avg_daily_sales
                                        }}</strong>
                                        đv</span
                                    >
                                    <span class="text-amber-500"
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
                            class="text-xxs py-6 text-center text-muted-foreground italic"
                        >
                            💡 Dự báo thời tiết ôn hòa, lượng cầu tiêu thụ thực
                            đơn dự kiến ổn định ở mức trung bình.
                        </div>
                    </div>
                </div>
            </div>

            <div
                v-else
                class="flex flex-col items-center py-10 text-center text-muted-foreground"
            >
                <Sparkles class="mb-2 size-8 text-muted-foreground/30" />
                <p class="text-xs">Không có dữ liệu dự báo thời tiết.</p>
            </div>
        </CardContent>
    </Card>
</template>
