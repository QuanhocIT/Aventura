<script setup lang="ts">
import { computed } from 'vue';
import { Clock, CalendarDays } from 'lucide-vue-next';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

interface ShiftRevenueRow {
    shift_name: string;
    days: { date: string; revenue: number }[];
}

const props = defineProps<{
    shiftRevenue: ShiftRevenueRow[] | undefined;
}>();

// ── Shift heatmap helpers ────────────────────────────────────────────────────
const shiftHeatmapMax = computed(() => {
    let max = 0;
    (props.shiftRevenue ?? []).forEach(row => row.days.forEach(d => { if (d.revenue > max) max = d.revenue; }));
    return Math.max(max, 1);
});

function shiftHeatColor(revenue: number): string {
    const pct = revenue / shiftHeatmapMax.value;
    if (pct === 0) return 'bg-slate-100 dark:bg-slate-800';
    if (pct < 0.25) return 'bg-indigo-100 dark:bg-indigo-950/30';
    if (pct < 0.5)  return 'bg-indigo-300 dark:bg-indigo-800/50';
    if (pct < 0.75) return 'bg-indigo-500 dark:bg-indigo-600';
    return 'bg-indigo-700 dark:bg-indigo-500';
}

function formatMoneyFull(v: number): string {
    if (v === 0) {
        return '—';
    }
    return new Intl.NumberFormat('vi-VN').format(v) + 'đ';
}
</script>

<template>
    <Card class="bg-card text-card-foreground border border-border shadow-sm overflow-hidden relative">
        <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-indigo-500/5 rounded-full blur-3xl pointer-events-none"></div>
        <CardHeader class="pb-2 border-b border-border/50 bg-muted/10">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div>
                    <CardTitle class="text-base font-bold flex items-center gap-2">
                        <Clock class="size-4.5 text-indigo-500 animate-pulse" />
                        Bản đồ nhiệt doanh thu & Tối ưu ca làm việc
                    </CardTitle>
                    <CardDescription class="text-xs text-muted-foreground mt-0.5">
                        Phân tích mật độ doanh số trong 7 ngày gần đây theo từng ca làm việc để tối ưu hóa nhân lực
                    </CardDescription>
                </div>
                <div class="flex items-center gap-2 text-[10px] text-muted-foreground font-medium bg-muted/40 px-2 py-1 rounded-lg border border-border/50 shrink-0">
                    <span>Thấp</span>
                    <div class="flex gap-0.5">
                        <span class="w-2.5 h-2.5 rounded bg-slate-100 dark:bg-slate-800 border border-border/40"></span>
                        <span class="w-2.5 h-2.5 rounded bg-indigo-100 dark:bg-indigo-950/30"></span>
                        <span class="w-2.5 h-2.5 rounded bg-indigo-300 dark:bg-indigo-800/50"></span>
                        <span class="w-2.5 h-2.5 rounded bg-indigo-500 dark:bg-indigo-600"></span>
                        <span class="w-2.5 h-2.5 rounded bg-indigo-700 dark:bg-indigo-500"></span>
                    </div>
                    <span>Cao</span>
                </div>
            </div>
        </CardHeader>
        <CardContent class="pt-6 relative z-10 space-y-4">
            <div v-if="shiftRevenue && shiftRevenue.length > 0" class="space-y-4">
                <div class="space-y-3">
                    <div 
                        v-for="row in shiftRevenue" 
                        :key="row.shift_name"
                        class="flex flex-col sm:flex-row sm:items-center gap-3 p-3 rounded-2xl border border-border/50 bg-muted/10 hover:bg-muted/20 transition-all"
                    >
                        <div class="sm:w-44 shrink-0">
                            <h4 class="text-sm font-bold text-foreground flex items-center gap-1.5">
                                <CalendarDays class="size-4 text-muted-foreground" />
                                {{ row.shift_name }}
                            </h4>
                            <p class="text-[10px] text-muted-foreground mt-0.5">Doanh số trung bình ca: {{ formatMoneyFull(Math.round(row.days.reduce((sum, d) => sum + d.revenue, 0) / Math.max(row.days.length, 1))) }}</p>
                        </div>
                        <div class="flex-1 flex items-center justify-between gap-1">
                            <div 
                                v-for="day in row.days" 
                                :key="day.date"
                                class="flex-1 group relative flex flex-col items-center justify-center aspect-[5/4] rounded-lg border border-border/20 transition-transform hover:scale-105 shadow-sm cursor-help overflow-hidden"
                                :class="shiftHeatColor(day.revenue)"
                            >
                                <!-- Date Label overlay inside each block -->
                                <span class="text-[9px] font-extrabold"
                                      :class="day.revenue / shiftHeatmapMax >= 0.5 ? 'text-white' : 'text-muted-foreground'"
                                >
                                    {{ day.date }}
                                </span>
                                
                                <!-- Sleek floating tooltip for each block -->
                                <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 hidden group-hover:block z-30 bg-slate-900 text-white text-[10px] px-2 py-1.5 rounded-lg shadow-xl border border-slate-700/50 whitespace-nowrap leading-tight">
                                    <span class="font-bold text-slate-300 block mb-0.5">Ngày {{ day.date }}</span>
                                    <span class="font-mono text-indigo-400 font-bold">{{ formatMoneyFull(day.revenue) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- AI Recommendation for Shifts -->
                <div class="flex items-start gap-2.5 bg-indigo-500/5 dark:bg-indigo-950/20 border border-indigo-500/10 rounded-2xl p-4">
                    <span class="text-base shrink-0 mt-0.5">💡</span>
                    <div class="space-y-1">
                        <h5 class="text-xs font-bold text-indigo-800 dark:text-indigo-300">Khuyến nghị điều phối ca của AI</h5>
                        <p class="text-xs text-indigo-700 dark:text-indigo-400 leading-relaxed font-medium font-sans">
                            Dựa trên mật độ doanh thu thực tế, ca tối và ca chiều cuối tuần (Thứ 6 đến Chủ nhật) đang mang lại doanh thu cao nhất. Hãy đảm bảo bố trí tối thiểu <strong>2 nhân viên phục vụ và 1 thu ngân chuyên trách</strong> vào các ca này để giảm tải vận hành và rút ngắn thời gian chuẩn bị món.
                        </p>
                    </div>
                </div>
            </div>
            <div v-else class="flex flex-col items-center justify-center py-10 text-muted-foreground text-center">
                <Clock class="size-8 text-muted-foreground/30 mb-2" />
                <p class="text-xs">Không có dữ liệu ca làm việc hoạt động.</p>
            </div>
        </CardContent>
    </Card>
</template>
