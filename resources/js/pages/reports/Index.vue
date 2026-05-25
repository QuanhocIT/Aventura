<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    BarChart3, TrendingUp, ShoppingCart, Percent,
    Package, Banknote, Award, ChevronUp, ChevronDown
} from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';

defineOptions({ layout: AppLayout });

type DaySummary = {
    date: string;
    date_full: string;
    order_count: number;
    completed_order_count: number;
    net_revenue: number;
    gross_revenue: number;
    discount_total: number;
    cogs_amount: number;
    gross_profit: number;
};

type Totals = {
    net_revenue: number;
    gross_profit: number;
    order_count: number;
    completed_order_count: number;
    discount_total: number;
};

type TopProduct = { name: string; total_qty: number; total_revenue: number };

const props = defineProps<{
    summaries:   DaySummary[];
    totals:      Totals;
    topProducts: TopProduct[];
    period:      string;
    dateRange:   { start: string; end: string };
}>();

const activePeriod = ref(props.period);

const setPeriod = (p: string) => {
    activePeriod.value = p;
    router.get('/reports', { period: p }, { preserveScroll: true });
};

const formatCurrency = (v: number) =>
    new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(v);

const formatCompact = (v: number) =>
    new Intl.NumberFormat('vi-VN', { notation: 'compact', maximumFractionDigits: 1 }).format(v) + 'đ';

const avgOrderValue = computed(() =>
    props.totals.completed_order_count > 0
        ? props.totals.net_revenue / props.totals.completed_order_count
        : 0
);

const profitMargin = computed(() =>
    props.totals.net_revenue > 0
        ? ((props.totals.gross_profit / props.totals.net_revenue) * 100).toFixed(1)
        : '0'
);

// For bar chart — normalize to max
const maxRevenue = computed(() =>
    Math.max(...props.summaries.map(s => s.net_revenue), 1)
);
</script>

<template>
    <Head title="Báo cáo doanh thu" />

    <div class="flex flex-col gap-6 p-6 max-w-7xl mx-auto w-full">
        <!-- Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b pb-5">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-violet-50 dark:bg-violet-950/60 text-violet-600 dark:text-violet-400">
                    <BarChart3 class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Báo Cáo Doanh Thu</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ dateRange.start }} — {{ dateRange.end }}</p>
                </div>
            </div>
            <!-- Period picker -->
            <div class="flex items-center gap-1.5 rounded-xl border border-border bg-muted/30 p-1">
                <button
                    v-for="(label, key) in ({ '7days': '7 ngày', '30days': '30 ngày', 'month': 'Tháng này' } as Record<string, string>)"
                    :key="key"
                    @click="setPeriod(key)"
                    class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all"
                    :class="activePeriod === key
                        ? 'bg-background shadow text-foreground'
                        : 'text-muted-foreground hover:text-foreground'"
                >
                    {{ label }}
                </button>
            </div>
        </div>

        <!-- KPI cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <Card>
                <CardContent class="pt-5">
                    <div class="flex items-center justify-between mb-1">
                        <Banknote class="size-5 text-emerald-500" />
                    </div>
                    <p class="text-2xl font-bold text-emerald-700 dark:text-emerald-400">{{ formatCompact(totals.net_revenue) }}</p>
                    <p class="text-xs text-muted-foreground mt-0.5">Doanh thu thuần</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-5">
                    <div class="flex items-center justify-between mb-1">
                        <TrendingUp class="size-5 text-violet-500" />
                    </div>
                    <p class="text-2xl font-bold">{{ profitMargin }}%</p>
                    <p class="text-xs text-muted-foreground mt-0.5">Biên lợi nhuận gộp</p>
                    <p class="text-[10px] text-violet-600 mt-0.5">{{ formatCompact(totals.gross_profit) }} lợi nhuận</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-5">
                    <div class="flex items-center justify-between mb-1">
                        <ShoppingCart class="size-5 text-sky-500" />
                    </div>
                    <p class="text-2xl font-bold">{{ totals.completed_order_count }}</p>
                    <p class="text-xs text-muted-foreground mt-0.5">Đơn hoàn thành</p>
                    <p class="text-[10px] text-sky-600 mt-0.5">/ {{ totals.order_count }} tổng đơn</p>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="pt-5">
                    <div class="flex items-center justify-between mb-1">
                        <Award class="size-5 text-amber-500" />
                    </div>
                    <p class="text-2xl font-bold">{{ formatCompact(avgOrderValue) }}</p>
                    <p class="text-xs text-muted-foreground mt-0.5">Giá trị đơn TB</p>
                    <p v-if="totals.discount_total > 0" class="text-[10px] text-rose-500 mt-0.5">
                        Giảm giá: {{ formatCompact(totals.discount_total) }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Revenue bar chart (2/3) -->
            <Card class="lg:col-span-2">
                <CardHeader class="pb-3 border-b">
                    <CardTitle class="text-base">Doanh thu theo ngày</CardTitle>
                    <CardDescription class="text-xs">Biểu đồ doanh thu thuần từng ngày trong kỳ</CardDescription>
                </CardHeader>
                <CardContent class="pt-5">
                    <div v-if="summaries.length" class="flex items-end gap-1.5 h-40">
                        <div
                            v-for="s in summaries" :key="s.date_full"
                            class="flex-1 flex flex-col items-center gap-1 group"
                        >
                            <div class="w-full relative flex flex-col justify-end" style="height: 120px">
                                <div
                                    class="w-full rounded-t-md bg-violet-500/80 hover:bg-violet-600 transition-colors cursor-default"
                                    :style="`height: ${Math.max((s.net_revenue / maxRevenue) * 100, s.net_revenue > 0 ? 4 : 0)}%`"
                                >
                                    <!-- Tooltip -->
                                    <div class="absolute -top-8 left-1/2 -translate-x-1/2 hidden group-hover:block z-10 whitespace-nowrap bg-slate-900 text-white text-[10px] px-2 py-1 rounded-lg">
                                        {{ formatCompact(s.net_revenue) }}
                                    </div>
                                </div>
                            </div>
                            <span class="text-[9px] text-slate-400 font-mono">{{ s.date }}</span>
                        </div>
                    </div>
                    <div v-else class="flex items-center justify-center h-40 text-sm text-muted-foreground">
                        Chưa có dữ liệu doanh thu trong kỳ này.
                    </div>

                    <!-- Table below chart -->
                    <div v-if="summaries.length" class="mt-5 border rounded-xl overflow-hidden">
                        <table class="w-full text-xs text-left">
                            <thead class="bg-muted/50">
                                <tr class="text-[10px] uppercase font-bold text-muted-foreground">
                                    <th class="px-3 py-2">Ngày</th>
                                    <th class="px-3 py-2 text-right">Đơn hoàn thành</th>
                                    <th class="px-3 py-2 text-right">Doanh thu</th>
                                    <th class="px-3 py-2 text-right">Lợi nhuận gộp</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <tr
                                    v-for="s in summaries" :key="s.date_full"
                                    class="hover:bg-slate-50/50 dark:hover:bg-slate-900/30 transition-colors"
                                >
                                    <td class="px-3 py-2 font-medium">{{ s.date }}</td>
                                    <td class="px-3 py-2 text-right">{{ s.completed_order_count }}</td>
                                    <td class="px-3 py-2 text-right font-mono font-semibold text-emerald-600 dark:text-emerald-400">
                                        {{ formatCompact(s.net_revenue) }}
                                    </td>
                                    <td class="px-3 py-2 text-right font-mono text-violet-600 dark:text-violet-400">
                                        {{ formatCompact(s.gross_profit) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <!-- Top products (1/3) -->
            <Card>
                <CardHeader class="pb-3 border-b">
                    <CardTitle class="text-base flex items-center gap-1.5">
                        <Package class="size-4 text-amber-500" />
                        Top món bán chạy
                    </CardTitle>
                    <CardDescription class="text-xs">Dựa trên đơn hoàn thành trong kỳ</CardDescription>
                </CardHeader>
                <CardContent class="p-0">
                    <div v-if="topProducts.length" class="divide-y divide-slate-100 dark:divide-slate-800">
                        <div
                            v-for="(p, idx) in topProducts" :key="p.name"
                            class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50/50 dark:hover:bg-slate-900/30 transition-colors"
                        >
                            <span
                                class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[10px] font-bold"
                                :class="idx === 0 ? 'bg-amber-100 text-amber-700' : idx === 1 ? 'bg-slate-100 text-slate-600' : idx === 2 ? 'bg-orange-100 text-orange-700' : 'bg-muted text-muted-foreground'"
                            >
                                {{ idx + 1 }}
                            </span>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-xs truncate">{{ p.name }}</p>
                                <p class="text-[10px] text-muted-foreground mt-0.5">{{ p.total_qty }} phần</p>
                            </div>
                            <span class="font-mono text-xs font-bold text-emerald-600 dark:text-emerald-400 shrink-0">
                                {{ formatCompact(p.total_revenue) }}
                            </span>
                        </div>
                    </div>
                    <div v-else class="flex flex-col items-center justify-center py-12 text-center text-sm text-muted-foreground">
                        <Package class="size-8 text-muted-foreground/30 mb-2" />
                        Chưa có dữ liệu bán hàng.
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
