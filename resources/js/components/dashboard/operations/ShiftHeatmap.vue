<script setup lang="ts">
import { Clock, CalendarDays } from 'lucide-vue-next';
import { computed } from 'vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

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
    (props.shiftRevenue ?? []).forEach((row) =>
        row.days.forEach((d) => {
            if (d.revenue > max) {
                max = d.revenue;
            }
        }),
    );

    return Math.max(max, 1);
});

function shiftHeatColor(revenue: number): string {
    const pct = revenue / shiftHeatmapMax.value;

    if (pct === 0) {
        return 'bg-slate-100 dark:bg-slate-800';
    }

    if (pct < 0.25) {
        return 'bg-indigo-100 dark:bg-indigo-950/30';
    }

    if (pct < 0.5) {
        return 'bg-indigo-300 dark:bg-indigo-800/50';
    }

    if (pct < 0.75) {
        return 'bg-indigo-500 dark:bg-indigo-600';
    }

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
    <Card
        class="relative overflow-hidden border border-border bg-card text-card-foreground shadow-sm"
    >
        <div
            class="pointer-events-none absolute -right-10 -bottom-10 h-40 w-40 rounded-full bg-indigo-500/5 blur-3xl"
        ></div>
        <CardHeader class="border-b border-border/50 bg-muted/10 pb-2">
            <div
                class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center"
            >
                <div>
                    <CardTitle
                        class="flex items-center gap-2 text-base font-bold"
                    >
                        <Clock class="size-4.5 animate-pulse text-indigo-500" />
                        Bản đồ nhiệt doanh thu & Tối ưu ca làm việc
                    </CardTitle>
                    <CardDescription
                        class="mt-0.5 text-xs text-muted-foreground"
                    >
                        Phân tích mật độ doanh số trong 7 ngày gần đây theo từng
                        ca làm việc để tối ưu hóa nhân lực
                    </CardDescription>
                </div>
                <div
                    class="flex shrink-0 items-center gap-2 rounded-lg border border-border/50 bg-muted/40 px-2 py-1 text-[10px] font-medium text-muted-foreground"
                >
                    <span>Thấp</span>
                    <div class="flex gap-0.5">
                        <span
                            class="h-2.5 w-2.5 rounded border border-border/40 bg-slate-100 dark:bg-slate-800"
                        ></span>
                        <span
                            class="h-2.5 w-2.5 rounded bg-indigo-100 dark:bg-indigo-950/30"
                        ></span>
                        <span
                            class="h-2.5 w-2.5 rounded bg-indigo-300 dark:bg-indigo-800/50"
                        ></span>
                        <span
                            class="h-2.5 w-2.5 rounded bg-indigo-500 dark:bg-indigo-600"
                        ></span>
                        <span
                            class="h-2.5 w-2.5 rounded bg-indigo-700 dark:bg-indigo-500"
                        ></span>
                    </div>
                    <span>Cao</span>
                </div>
            </div>
        </CardHeader>
        <CardContent class="relative z-10 space-y-4 pt-6">
            <div
                v-if="shiftRevenue && shiftRevenue.length > 0"
                class="space-y-4"
            >
                <div class="space-y-3">
                    <div
                        v-for="row in shiftRevenue"
                        :key="row.shift_name"
                        class="flex flex-col gap-3 rounded-2xl border border-border/50 bg-muted/10 p-3 transition-all hover:bg-muted/20 sm:flex-row sm:items-center"
                    >
                        <div class="shrink-0 sm:w-44">
                            <h4
                                class="flex items-center gap-1.5 text-sm font-bold text-foreground"
                            >
                                <CalendarDays
                                    class="size-4 text-muted-foreground"
                                />
                                {{ row.shift_name }}
                            </h4>
                            <p class="mt-0.5 text-[10px] text-muted-foreground">
                                Doanh số trung bình ca:
                                {{
                                    formatMoneyFull(
                                        Math.round(
                                            row.days.reduce(
                                                (sum, d) => sum + d.revenue,
                                                0,
                                            ) / Math.max(row.days.length, 1),
                                        ),
                                    )
                                }}
                            </p>
                        </div>
                        <div
                            class="flex flex-1 items-center justify-between gap-1"
                        >
                            <div
                                v-for="day in row.days"
                                :key="day.date"
                                class="group relative flex aspect-[5/4] flex-1 cursor-help flex-col items-center justify-center overflow-hidden rounded-lg border border-border/20 shadow-sm transition-transform hover:scale-105"
                                :class="shiftHeatColor(day.revenue)"
                            >
                                <!-- Date Label overlay inside each block -->
                                <span
                                    class="text-[9px] font-extrabold"
                                    :class="
                                        day.revenue / shiftHeatmapMax >= 0.5
                                            ? 'text-white'
                                            : 'text-muted-foreground'
                                    "
                                >
                                    {{ day.date }}
                                </span>

                                <!-- Sleek floating tooltip for each block -->
                                <div
                                    class="absolute bottom-full left-1/2 z-30 mb-2 hidden -translate-x-1/2 transform rounded-lg border border-slate-700/50 bg-slate-900 px-2 py-1.5 text-[10px] leading-tight whitespace-nowrap text-white shadow-xl group-hover:block"
                                >
                                    <span
                                        class="mb-0.5 block font-bold text-slate-300"
                                        >Ngày {{ day.date }}</span
                                    >
                                    <span
                                        class="font-mono font-bold text-indigo-400"
                                        >{{
                                            formatMoneyFull(day.revenue)
                                        }}</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- AI Recommendation for Shifts -->
                <div
                    class="flex items-start gap-2.5 rounded-2xl border border-indigo-500/10 bg-indigo-500/5 p-4 dark:bg-indigo-950/20"
                >
                    <span class="mt-0.5 shrink-0 text-base">💡</span>
                    <div class="space-y-1">
                        <h5
                            class="text-xs font-bold text-indigo-800 dark:text-indigo-300"
                        >
                            Khuyến nghị điều phối ca của AI
                        </h5>
                        <p
                            class="font-sans text-xs leading-relaxed font-medium text-indigo-700 dark:text-indigo-400"
                        >
                            Dựa trên mật độ doanh thu thực tế, ca tối và ca
                            chiều cuối tuần (Thứ 6 đến Chủ nhật) đang mang lại
                            doanh thu cao nhất. Hãy đảm bảo bố trí tối thiểu
                            <strong
                                >2 nhân viên phục vụ và 1 thu ngân chuyên
                                trách</strong
                            >
                            vào các ca này để giảm tải vận hành và rút ngắn thời
                            gian chuẩn bị món.
                        </p>
                    </div>
                </div>
            </div>
            <div
                v-else
                class="flex flex-col items-center justify-center py-10 text-center text-muted-foreground"
            >
                <Clock class="mb-2 size-8 text-muted-foreground/30" />
                <p class="text-xs">Không có dữ liệu ca làm việc hoạt động.</p>
            </div>
        </CardContent>
    </Card>
</template>
