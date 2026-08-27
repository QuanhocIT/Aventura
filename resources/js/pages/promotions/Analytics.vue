<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { AlertTriangle, ArrowUpRight, BarChart3, CheckCircle2, DollarSign, Lightbulb, RotateCcw, ShoppingCart, TrendingUp, UserPlus } from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import BackButton from '@/components/BackButton.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

interface PromotionInsight {
    type: 'success' | 'warning' | 'info';
    title: string;
    message: string;
}

interface PromotionDailyStat {
    date: string;
    discount: number;
    revenue: number;
    uses: number;
    new_customers: number;
    repeat_rate: number;
}

interface PerPromotionStat {
    promotion_id: number;
    name: string;
    code: string | null;
    type: 'percent' | 'fixed_amount';
    value: number;
    uses: number;
    unique_customers: number;
    discount_given: number;
    revenue_influenced: number;
    avg_order_value: number;
    roi_percent: number;
    bypass_count: number;
}

interface PromotionAnalyticsMetrics {
    total_discount: number;
    total_revenue: number;
    total_uses: number;
    roi_percent: number;
    new_customers_acquired: number;
    repeat_rate: number;
    aov_with_promo?: number;
    aov_without_promo?: number;
    basket_lift_percent?: number;
    insights?: PromotionInsight[];
    daily?: PromotionDailyStat[];
    per_promotion?: PerPromotionStat[];
}

interface PromotionAnalyticsFilters {
    start_date: string;
    end_date: string;
}

interface Props {
    metrics?: PromotionAnalyticsMetrics;
    filters?: PromotionAnalyticsFilters;
}

const props = withDefaults(defineProps<Props>(), {
    metrics: () => ({
        total_discount: 0,
        total_revenue: 0,
        total_uses: 0,
        roi_percent: 0,
        new_customers_acquired: 0,
        repeat_rate: 0,
        daily: [],
        per_promotion: [],
    }),
    filters: () => ({
        start_date: '',
        end_date: '',
    }),
});

const startDate = ref(props.filters?.start_date ?? '');
const endDate = ref(props.filters?.end_date ?? '');
const isRecalculating = ref(false);

function applyFilter() {
    router.get(
        '/promotions/analytics',
        { start_date: startDate.value, end_date: endDate.value },
        { preserveState: true, replace: true },
    );
}

function setQuickPreset(days: number) {
    const end = new Date();
    const start = new Date();
    start.setDate(end.getDate() - days);

    startDate.value = start.toISOString().split('T')[0];
    endDate.value = end.toISOString().split('T')[0];
    applyFilter();
}

function recalculateMetrics() {
    isRecalculating.value = true;
    router.post(
        '/promotions/analytics/recalculate',
        { start_date: startDate.value, end_date: endDate.value },
        {
            preserveScroll: true,
            onFinish: () => {
                isRecalculating.value = false;
                toast.success('Đã tính toán & cập nhật dữ liệu mới nhất.');
            },
        },
    );
}

function formatVND(val: number) {
    return new Intl.NumberFormat('vi-VN').format(Math.round(val));
}

const maxRevenue = Math.max(
    ...((props.metrics?.daily ?? []).map((d: PromotionDailyStat) => d.revenue) || [1]),
    1,
);
</script>

<template>
    <Head title="Phân tích Khuyến mãi" />

    <div class="flex flex-col gap-6 px-6 py-5">
        <!-- Header & Filters -->
        <div class="flex flex-col gap-4 border-b border-border/70 pb-5 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-3">
                <BackButton fallback-href="/promotions" label="Khuyến mãi" />
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-foreground">
                        Phân tích Hiệu quả Khuyến mãi
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Đo lường ROI, giá trị giỏ hàng tăng trưởng (Basket Lift), chi phí chiết khấu & tỷ lệ đổi mã.
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <div class="flex items-center gap-1 rounded-lg border border-border/70 bg-card p-1">
                    <button
                        type="button"
                        @click="setQuickPreset(7)"
                        class="rounded px-2.5 py-1 text-xs font-semibold text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    >
                        7 ngày
                    </button>
                    <button
                        type="button"
                        @click="setQuickPreset(30)"
                        class="rounded px-2.5 py-1 text-xs font-semibold text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    >
                        30 ngày
                    </button>
                </div>
                <Input v-model="startDate" type="date" class="w-[140px] text-xs" />
                <span class="text-xs text-muted-foreground">đến</span>
                <Input v-model="endDate" type="date" class="w-[140px] text-xs" />
                <Button variant="outline" size="sm" @click="applyFilter" class="font-semibold">
                    Lọc
                </Button>
                <Button
                    variant="secondary"
                    size="sm"
                    @click="recalculateMetrics"
                    :disabled="isRecalculating"
                    class="gap-1.5 font-semibold"
                >
                    <RotateCcw :class="['size-3.5', isRecalculating && 'animate-spin']" />
                    Tính lại
                </Button>
            </div>
        </div>

        <!-- AI Insights & Advisor Cards -->
        <div v-if="metrics.insights?.length" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="(insight, idx) in metrics.insights"
                :key="idx"
                :class="[
                    'flex items-start gap-3 rounded-2xl border p-4 shadow-sm transition-all',
                    insight.type === 'success' ? 'border-emerald-500/30 bg-emerald-500/5 text-emerald-950 dark:text-emerald-200' :
                    insight.type === 'warning' ? 'border-amber-500/30 bg-amber-500/5 text-amber-950 dark:text-amber-200' :
                    'border-primary/30 bg-primary/5 text-foreground'
                ]"
            >
                <div
                    :class="[
                        'rounded-xl p-2 shrink-0',
                        insight.type === 'success' ? 'bg-emerald-500/10 text-emerald-600' :
                        insight.type === 'warning' ? 'bg-amber-500/10 text-amber-600' :
                        'bg-primary/10 text-primary'
                    ]"
                >
                    <Lightbulb v-if="insight.type === 'info'" class="size-5" />
                    <CheckCircle2 v-else-if="insight.type === 'success'" class="size-5" />
                    <AlertTriangle v-else class="size-5" />
                </div>
                <div>
                    <h4 class="text-xs font-bold">{{ insight.title }}</h4>
                    <p class="mt-0.5 text-[11px] opacity-90 leading-relaxed">{{ insight.message }}</p>
                </div>
            </div>
        </div>

        <!-- Metric KPI Summary Grid -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Card class="border-rose-500/20 bg-card/40 shadow-xs backdrop-blur-md transition-all hover:shadow-md">
                <CardContent class="flex items-center gap-4 p-4">
                    <div class="rounded-xl bg-rose-500/10 p-2.5 text-rose-600 shrink-0">
                        <DollarSign class="size-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase truncate">Chi phí chiết khấu</p>
                        <p class="text-xl font-bold text-rose-600 truncate" :title="`${formatVND(metrics.total_discount)}₫`">
                            {{ formatVND(metrics.total_discount) }}₫
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card class="border-emerald-500/20 bg-card/40 shadow-xs backdrop-blur-md transition-all hover:shadow-md">
                <CardContent class="flex items-center gap-4 p-4">
                    <div class="rounded-xl bg-emerald-500/10 p-2.5 text-emerald-600 shrink-0">
                        <TrendingUp class="size-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase truncate">Doanh thu từ KM</p>
                        <p class="text-xl font-bold text-emerald-600 truncate" :title="`${formatVND(metrics.total_revenue)}₫`">
                            {{ formatVND(metrics.total_revenue) }}₫
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card class="border-violet-500/20 bg-card/40 shadow-xs backdrop-blur-md transition-all hover:shadow-md">
                <CardContent class="flex items-center gap-4 p-4">
                    <div class="rounded-xl bg-violet-500/10 p-2.5 text-violet-600 shrink-0">
                        <BarChart3 class="size-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase truncate">Hiệu quả ROI</p>
                        <p :class="['text-xl font-bold truncate', metrics.roi_percent >= 100 ? 'text-emerald-600' : 'text-rose-600']">
                            {{ metrics.roi_percent }}%
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card class="border-sky-500/20 bg-card/40 shadow-xs backdrop-blur-md transition-all hover:shadow-md">
                <CardContent class="flex items-center gap-4 p-4">
                    <div class="rounded-xl bg-sky-500/10 p-2.5 text-sky-600 shrink-0">
                        <ShoppingCart class="size-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase truncate">Lần áp mã thành công</p>
                        <p class="text-xl font-bold text-foreground truncate">
                            {{ metrics.total_uses }} <span class="text-xs font-normal text-muted-foreground">lượt</span>
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card class="border-indigo-500/20 bg-card/40 shadow-xs backdrop-blur-md transition-all hover:shadow-md">
                <CardContent class="flex items-center gap-4 p-4">
                    <div class="rounded-xl bg-indigo-500/10 p-2.5 text-indigo-600 shrink-0">
                        <ArrowUpRight class="size-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase truncate">Tăng trưởng giỏ hàng (Basket Lift)</p>
                        <p :class="['text-xl font-bold truncate', (metrics.basket_lift_percent ?? 0) >= 0 ? 'text-emerald-600' : 'text-rose-600']">
                            +{{ metrics.basket_lift_percent ?? 0 }}%
                        </p>
                        <p class="text-[10px] text-muted-foreground truncate" :title="`TB: ${formatVND(metrics.aov_with_promo ?? 0)}₫/đơn KM`">
                            TB: {{ formatVND(metrics.aov_with_promo ?? 0) }}₫/đơn KM
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card class="border-amber-500/20 bg-card/40 shadow-xs backdrop-blur-md transition-all hover:shadow-md">
                <CardContent class="flex items-center gap-4 p-4">
                    <div class="rounded-xl bg-amber-500/10 p-2.5 text-amber-600 shrink-0">
                        <UserPlus class="size-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-[10px] font-bold tracking-wider text-muted-foreground uppercase truncate">Khách mới nhờ KM</p>
                        <p class="text-xl font-bold text-amber-600 truncate">
                            {{ metrics.new_customers_acquired }} <span class="text-xs font-normal text-muted-foreground">khách</span>
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Daily Trend Bar Chart -->
        <Card>
            <CardHeader class="pb-3">
                <CardTitle class="text-sm font-bold">Doanh thu tác động theo ngày</CardTitle>
            </CardHeader>
            <CardContent>
                <div v-if="metrics.daily?.length" class="space-y-2">
                    <div
                        v-for="day in (metrics.daily ?? [])"
                        :key="day.date"
                        class="flex items-center gap-3"
                    >
                        <span class="w-12 shrink-0 font-mono text-[10px] text-muted-foreground">{{ day.date }}</span>
                        <div class="h-4 flex-1 overflow-hidden rounded-full bg-muted/40">
                            <div
                                class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-teal-400 transition-all duration-500"
                                :style="{
                                    width: `${(day.revenue / maxRevenue) * 100}%`,
                                }"
                            />
                        </div>
                        <span class="w-24 shrink-0 text-right font-mono text-[10px] font-bold text-foreground tabular-nums">{{ formatVND(day.revenue) }}₫</span>
                        <span class="w-10 shrink-0 text-right font-mono text-[10px] text-muted-foreground">{{ day.uses }}x</span>
                    </div>
                </div>
                <p v-else class="py-10 text-center text-xs text-muted-foreground">
                    Chưa có dữ liệu trong khoảng thời gian này. Bấm "Tính lại" để cập nhật dữ liệu mới nhất.
                </p>
            </CardContent>
        </Card>

        <!-- Per Promotion Breakdown Table -->
        <Card>
            <CardHeader class="pb-3">
                <CardTitle class="text-sm font-bold">Hiệu quả chi tiết theo từng chương trình</CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <div v-if="metrics.per_promotion?.length" class="overflow-x-auto">
                    <table class="w-full border-collapse text-left text-xs">
                        <thead>
                            <tr class="border-b bg-muted/40 text-[10px] font-bold tracking-wider text-muted-foreground uppercase">
                                <th class="p-3">Chương trình</th>
                                <th class="p-3">Mã Code</th>
                                <th class="p-3 text-right">Lượt dùng</th>
                                <th class="p-3 text-right">Khách hàng</th>
                                <th class="p-3 text-right">Chi phí chiết khấu</th>
                                <th class="p-3 text-right">Doanh thu tác động</th>
                                <th class="p-3 text-right">Giá trị đơn TB (AOV)</th>
                                <th class="p-3 text-right">ROI</th>
                                <th class="p-3 text-center">Mã vượt quyền</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y border-border/70">
                            <tr v-for="row in (metrics.per_promotion ?? [])" :key="row.promotion_id" class="transition-colors hover:bg-muted/20">
                                <td class="p-3 font-bold text-foreground">{{ row.name }}</td>
                                <td class="p-3">
                                    <span v-if="row.code" class="rounded border border-indigo-500/20 bg-indigo-500/10 px-2 py-0.5 font-mono font-bold text-indigo-600 dark:text-indigo-400">
                                        {{ row.code }}
                                    </span>
                                    <span v-else class="text-muted-foreground">—</span>
                                </td>
                                <td class="p-3 text-right font-mono font-bold text-foreground tabular-nums">{{ row.uses }}</td>
                                <td class="p-3 text-right font-mono text-muted-foreground tabular-nums">{{ row.unique_customers }}</td>
                                <td class="p-3 text-right font-mono font-bold text-rose-600 tabular-nums">-{{ formatVND(row.discount_given) }}₫</td>
                                <td class="p-3 text-right font-mono font-bold text-emerald-600 tabular-nums">{{ formatVND(row.revenue_influenced) }}₫</td>
                                <td class="p-3 text-right font-mono text-muted-foreground tabular-nums">{{ formatVND(row.avg_order_value) }}₫</td>
                                <td :class="['p-3 text-right font-mono font-bold tabular-nums', row.roi_percent >= 100 ? 'text-emerald-600' : 'text-amber-600']">
                                    {{ row.roi_percent }}%
                                </td>
                                <td class="p-3 text-center">
                                    <span v-if="row.bypass_count > 0" class="rounded bg-rose-500/10 px-2 py-0.5 font-mono text-[10px] font-bold text-rose-600 dark:text-rose-400" title="Lượt dùng mã phê duyệt vượt quyền">
                                        {{ row.bypass_count }} lượt
                                    </span>
                                    <span v-else class="text-muted-foreground">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div v-else class="p-10 text-center text-xs text-muted-foreground">
                    Chưa ghi nhận lượt sử dụng mã khuyến mãi nào trong khoảng thời gian này.
                </div>
            </CardContent>
        </Card>
    </div>
</template>
