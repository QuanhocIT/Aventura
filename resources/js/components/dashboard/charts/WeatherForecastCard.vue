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
    Gauge,
    Droplets,
    AlertTriangle,
    ExternalLink
} from 'lucide-vue-next';
import { ref, onMounted, computed } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

const isLoading = ref(true);
const forecastData = ref<any[]>([]);
const selectedDayIndex = ref(0);
const apiSource = ref('');
const weatherDataSource = ref('');
const isDegraded = ref(false);

const fetchForecast = async () => {
    try {
        const response = await axios.get('/api/analytics/weather-menu-forecast');

        if (response.data && response.data.forecast) {
            forecastData.value = response.data.forecast;
            apiSource.value = response.data.source || 'AI Analytics';
            weatherDataSource.value = response.data.weather_data_source || '';
            isDegraded.value = response.data.is_degraded ?? false;
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
        dayMonth: `${date.getDate()}/${date.getMonth() + 1}`
    };
};

const getWeatherIcon = (condition: string) => {
    const cond = condition.toLowerCase();

    if (cond.includes('sun') || cond.includes('nắng')) return Sun;
    if (cond.includes('rain') || cond.includes('mưa'))  return CloudRain;
    if (cond.includes('wind') || cond.includes('gió'))  return Wind;
    return Cloud; // default to cloudy
};

const getWeatherColor = (condition: string) => {
    const cond = condition.toLowerCase();

    if (cond.includes('sun') || cond.includes('nắng')) return 'text-amber-500 bg-amber-500/10 border-amber-500/20';
    if (cond.includes('rain') || cond.includes('mưa')) return 'text-sky-500 bg-sky-500/10 border-sky-500/20';
    if (cond.includes('wind') || cond.includes('gió')) return 'text-teal-500 bg-teal-500/10 border-teal-500/20';
    return 'text-slate-400 bg-slate-500/10 border-slate-500/10';
};

// --- Data source badge ---
const sourceBadge = computed(() => {
    if (weatherDataSource.value === 'openweathermap') {
        return { label: 'OpenWeather · Dữ liệu thật', cls: 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20' };
    }
    if (weatherDataSource.value === 'open-meteo') {
        return { label: 'Open-Meteo · Dữ liệu thật', cls: 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20' };
    }
    if (weatherDataSource.value === 'no_coordinates') {
        return { label: 'Chưa cấu hình GPS · Dữ liệu giả lập', cls: 'bg-amber-500/10 text-amber-600 border-amber-500/20' };
    }
    if (weatherDataSource.value) {
        return { label: 'Dữ liệu giả lập', cls: 'bg-amber-500/10 text-amber-600 border-amber-500/20' };
    }
    return null;
});

const noGpsConfigured = computed(() => weatherDataSource.value === 'no_coordinates');

// Selected day extra metrics
const selectedDay = computed(() => forecastData.value[selectedDayIndex.value] ?? null);

const uvLevel = computed(() => {
    const uv = selectedDay.value?.uv_index;
    if (uv === null || uv === undefined) return null;
    if (uv >= 11) return { label: 'Cực nguy hiểm', cls: 'text-violet-600' };
    if (uv >= 8)  return { label: 'Rất cao', cls: 'text-rose-500' };
    if (uv >= 6)  return { label: 'Cao', cls: 'text-orange-500' };
    if (uv >= 3)  return { label: 'Trung bình', cls: 'text-amber-500' };
    return { label: 'Thấp', cls: 'text-emerald-500' };
});
</script>

<template>
    <Card class="bg-card text-card-foreground border border-border shadow-sm overflow-hidden relative">
        <div class="absolute -right-10 -top-10 w-32 h-32 bg-amber-500/5 rounded-full blur-2xl"></div>
        
        <CardHeader class="pb-2 border-b border-border/50 bg-muted/20 flex flex-row items-center justify-between gap-4">
            <div>
                <CardTitle class="text-base font-bold flex items-center gap-2">
                    <Sparkles class="size-4.5 text-amber-500 animate-pulse" />
                    AI Dự báo Món Ăn Theo Thời Tiết
                </CardTitle>
                <p class="text-xs text-muted-foreground mt-0.5">Khuyến nghị chuẩn bị nguyên liệu &amp; chiến dịch marketing</p>
            </div>
            <!-- Dynamic source badge -->
            <span v-if="sourceBadge" :class="['text-[9px] font-semibold px-1.5 py-0.5 rounded border shrink-0', sourceBadge.cls]">
                {{ sourceBadge.label }}
            </span>
        </CardHeader>
        
        <CardContent class="pt-4 text-xs space-y-4">
            <!-- Loading state -->
            <div v-if="isLoading" class="flex flex-col items-center py-12 text-muted-foreground text-center">
                <Loader2 class="size-8 text-amber-500 animate-spin mb-3" />
                <p class="text-xs">Đang tải phân tích thời tiết &amp; nhu cầu món ăn...</p>
            </div>

            <template v-else-if="forecastData.length > 0">
                <!-- ── No-GPS warning banner ── -->
                <div
                    v-if="noGpsConfigured"
                    class="flex items-start gap-2 p-3 bg-amber-50/70 dark:bg-amber-950/20 border border-amber-300/50 rounded-xl text-[10px] text-amber-700 dark:text-amber-400"
                >
                    <AlertTriangle class="size-3.5 shrink-0 mt-0.5 text-amber-500" />
                    <span>
                        Cửa hàng chưa cấu hình tọa độ GPS — dự báo thời tiết đang dùng dữ liệu giả lập.
                        <a href="/schedules?tab=settings" class="font-bold underline underline-offset-2 inline-flex items-center gap-0.5 ml-0.5">
                            Cài đặt ngay <ExternalLink class="size-2.5" />
                        </a>
                    </span>
                </div>

                <!-- Day selector tabs -->
                <div class="flex items-center gap-1.5 overflow-x-auto pb-1 scrollbar-none">
                    <button
                        v-for="(day, idx) in forecastData"
                        :key="idx"
                        @click="selectedDayIndex = idx"
                        :class="[
                            'flex flex-col items-center p-2 rounded-xl border transition-all text-center min-w-[50px] flex-1 cursor-pointer select-none',
                            selectedDayIndex === idx 
                                ? 'bg-amber-500/10 border-amber-500 text-amber-500 font-bold scale-[1.03] shadow-sm'
                                : 'bg-muted/40 border-border/50 text-muted-foreground hover:text-foreground hover:bg-muted/60'
                        ]"
                    >
                        <span class="text-[9px] uppercase tracking-wider">{{ formatTabDate(day.date).dayName }}</span>
                        <span class="text-xs font-black my-1">{{ formatTabDate(day.date).dayMonth }}</span>
                        <component :is="getWeatherIcon(day.condition)" class="size-3.5" />
                    </button>
                </div>
                
                <!-- Selected day detail card -->
                <div v-if="selectedDay" class="bg-muted/30 border border-border/60 rounded-2xl p-4 space-y-3">
                    <div class="flex items-center justify-between pb-2 border-b border-border/50">
                        <div class="flex items-center gap-2">
                            <span :class="['p-1.5 rounded-lg border', getWeatherColor(selectedDay.condition)]">
                                <component :is="getWeatherIcon(selectedDay.condition)" class="size-4" />
                            </span>
                            <div>
                                <h4 class="font-bold text-foreground capitalize text-xs">
                                    {{ selectedDay.condition === 'sunny' ? 'Nắng ráo' :
                                       selectedDay.condition === 'rainy' ? 'Trời mưa lạnh' :
                                       selectedDay.condition === 'windy' ? 'Trời lộng gió' : 'Nhiều mây / Mát mẻ' }}
                                </h4>
                                <p class="text-[10px] text-muted-foreground">Thời tiết dự kiến ngày {{ selectedDay.date }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-1 text-sm font-black text-foreground">
                            <Thermometer class="size-4 text-rose-500" />
                            <span>{{ selectedDay.temperature }}°C</span>
                        </div>
                    </div>

                    <!-- Extra metrics row: UV + precipitation -->
                    <div
                        v-if="selectedDay.uv_index !== null && selectedDay.uv_index !== undefined || selectedDay.precipitation_probability !== null && selectedDay.precipitation_probability !== undefined"
                        class="flex items-center gap-3 text-[10px] text-muted-foreground"
                    >
                        <div v-if="selectedDay.uv_index !== null && selectedDay.uv_index !== undefined" class="flex items-center gap-1">
                            <Gauge class="size-3 text-orange-400" />
                            <span>UV: <strong :class="uvLevel?.cls">{{ selectedDay.uv_index.toFixed(1) }} ({{ uvLevel?.label }})</strong></span>
                        </div>
                        <div v-if="selectedDay.precipitation_probability !== null && selectedDay.precipitation_probability !== undefined" class="flex items-center gap-1">
                            <Droplets class="size-3 text-sky-400" />
                            <span>XS mưa: <strong :class="selectedDay.precipitation_probability >= 70 ? 'text-sky-600 dark:text-sky-400' : ''">{{ selectedDay.precipitation_probability }}%</strong></span>
                        </div>
                    </div>
                    
                    <!-- Recommendations list -->
                    <div class="space-y-2.5">
                        <h5 class="text-[10px] font-black text-muted-foreground uppercase tracking-wider flex items-center gap-1">
                            <Calendar class="size-3 text-amber-500" /> AI Đề xuất thực đơn ca trực:
                        </h5>
                        
                        <div v-if="selectedDay.recommendations.length > 0" class="space-y-2 max-h-[220px] overflow-y-auto pr-0.5">
                            <div 
                                v-for="(rec, rIdx) in selectedDay.recommendations" 
                                :key="rIdx"
                                class="p-3 bg-card border border-border/50 rounded-xl space-y-1.5 hover:border-border transition-all"
                            >
                                <div class="flex items-center justify-between">
                                    <div class="min-w-0 pr-2">
                                        <span class="font-bold text-foreground text-xs block truncate">{{ rec.product_name }}</span>
                                        <span class="text-[8px] font-semibold text-muted-foreground px-1 bg-muted rounded border border-border/50 inline-block mt-0.5">{{ rec.category_name }}</span>
                                    </div>
                                    <span :class="[
                                        'px-2 py-0.5 rounded-full text-[9px] font-black flex items-center gap-0.5 border shrink-0',
                                        rec.change_pct >= 0
                                            ? 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20'
                                            : 'bg-rose-500/10 text-rose-500 border-rose-500/20'
                                    ]">
                                        <component :is="rec.change_pct >= 0 ? TrendingUp : TrendingDown" class="size-2.5" />
                                        {{ rec.change_pct >= 0 ? '+' : '' }}{{ rec.change_pct }}%
                                    </span>
                                </div>
                                <p class="text-[10px] text-muted-foreground leading-relaxed">{{ rec.reason }}</p>
                                <div class="flex items-center justify-between text-[9px] text-muted-foreground pt-1 border-t border-border/30">
                                    <span>Bán ngày thường: <strong>{{ rec.avg_daily_sales }}</strong> đv</span>
                                    <span class="text-amber-500">Dự báo: <strong>{{ rec.predicted_sales }}</strong> đv</span>
                                </div>
                            </div>
                        </div>
                        
                        <div v-else class="text-center py-6 text-muted-foreground italic text-xxs">
                            💡 Dự báo thời tiết ôn hòa, lượng cầu tiêu thụ thực đơn dự kiến ổn định ở mức trung bình.
                        </div>
                    </div>
                </div>
            </template>
            
            <div v-else class="flex flex-col items-center py-10 text-muted-foreground text-center">
                <Sparkles class="size-8 text-muted-foreground/30 mb-2" />
                <p class="text-xs">Không có dữ liệu dự báo thời tiết.</p>
            </div>
        </CardContent>
    </Card>
</template>
