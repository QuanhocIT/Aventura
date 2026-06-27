<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Activity, AlertTriangle, ArrowLeft, ArrowUpRight,
    BookOpen, CheckCircle2, Clock, CreditCard, LayoutGrid,
    RefreshCcw, TrendingDown, TrendingUp, Users, Wallet, Zap,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { PageHeader, StatCard, StatusBadge, ProgressBar, SectionCard } from '@/components/super-admin';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });


interface MonthRevenue { label: string; revenue: number; count: number }
interface PlanRevenue { plan: string; revenue: number; count: number }
interface ExpiringRestaurant { id: number; name: string; plan: string; expires_on: string; days_left: number }

interface AgingBucket { label: string; count: number; amount: number; amount_fmt: string; color: string }
interface AgingBuckets { current: AgingBucket; '0_7': AgingBucket; '8_30': AgingBucket; '31_60': AgingBucket; over_60: AgingBucket }
interface BadDebt {
    written_off_count: number; written_off_amount: string;
    total_adjustments: string; effective_collection_rate: number;
    total_invoiced: string; total_collected: string;
}

const props = defineProps<{
    kpis: {
        mrr: string; arr: string;
        active_count: number; churn_rate: number; churned_30d: number;
        expiring_7d: number; expiring_14d: number; expiring_30d: number;
        webhook_success_rate: number;
    };
    monthly_revenue: MonthRevenue[];
    revenue_by_plan: PlanRevenue[];
    expiring_list: ExpiringRestaurant[];
    aging_buckets: AgingBuckets;
    bad_debt: BadDebt;
}>();

const agingOrder = ['current', '0_7', '8_30', '31_60', 'over_60'] as const;
const agingColorClass: Record<string, string> = {
    emerald: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
    yellow:  'bg-yellow-100  text-yellow-800  dark:bg-yellow-900/30  dark:text-yellow-300',
    amber:   'bg-amber-100   text-amber-800   dark:bg-amber-900/30   dark:text-amber-300',
    orange:  'bg-orange-100  text-orange-800  dark:bg-orange-900/30  dark:text-orange-300',
    rose:    'bg-rose-100    text-rose-800    dark:bg-rose-900/30    dark:text-rose-300',
};

// SVG Area Chart
const CHART_W = 800;
const CHART_H = 200;
const PAD_L = 60;
const PAD_R = 20;
const PAD_T = 20;
const PAD_B = 40;

const chartData = computed(() => {
    const data = props.monthly_revenue;

    if (!data.length) {
return { points: '', area: '', labels: [], maxRevenue: 0, yLines: [] };
}

    const maxRevenue = Math.max(...data.map(d => d.revenue), 1);
    const w = CHART_W - PAD_L - PAD_R;
    const h = CHART_H - PAD_T - PAD_B;

    const x = (i: number) => PAD_L + (i / Math.max(data.length - 1, 1)) * w;
    const y = (v: number) => PAD_T + h - (v / maxRevenue) * h;

    const pts = data.map((d, i) => `${x(i)},${y(d.revenue)}`).join(' ');
    const area = `M${x(0)},${y(data[0].revenue)} ` +
        data.slice(1).map((d, i) => `L${x(i + 1)},${y(d.revenue)}`).join(' ') +
        ` L${x(data.length - 1)},${PAD_T + h} L${x(0)},${PAD_T + h} Z`;

    const labels = data.map((d, i) => ({ text: d.label, x: x(i), y: PAD_T + h + 18 }));
    const yLines = [0, 0.25, 0.5, 0.75, 1].map(pct => ({
        y: PAD_T + h - pct * h,
        label: pct === 0 ? '0' : formatRevenue(maxRevenue * pct),
    }));

    return { points: pts, area, labels, maxRevenue, yLines };
});

function formatRevenue(val: number): string {
    if (val >= 1_000_000) {
return (val / 1_000_000).toFixed(1) + 'M';
}

    if (val >= 1_000) {
return (val / 1_000).toFixed(0) + 'K';
}

    return String(Math.round(val));
}

// Bar chart for plans
const planChartMax = computed(() =>
    Math.max(...props.revenue_by_plan.map(p => p.revenue), 1)
);
</script>

<template>
    <Head title="Analytics — Billing" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <!-- Header -->
        <PageHeader
            title="Revenue Analytics"
            subtitle="Tổng quan doanh thu và sức khoẻ tài chính hệ thống."
            :icon="Activity"
        >
            <template #actions>
                <Link href="/super-admin/billing">
                    <Button variant="outline" size="sm"><ArrowLeft class="mr-1.5 size-4" /> Billing</Button>
                </Link>
                <Link href="/super-admin/billing/ledger">
                    <Button variant="outline" size="sm"><BookOpen class="mr-1.5 size-4" />Sổ Cái</Button>
                </Link>
                <Link href="/super-admin/billing/revenue-recognition">
                    <Button variant="outline" size="sm"><Wallet class="mr-1.5 size-4" />Doanh Thu</Button>
                </Link>
                <Link href="/super-admin/billing/dunning">
                    <Button variant="outline" size="sm"><RefreshCcw class="mr-1.5 size-4" />Dunning</Button>
                </Link>
                <Link href="/super-admin/billing/lifecycle">
                    <Button variant="outline" size="sm"><LayoutGrid class="mr-1.5 size-4" />Lifecycle</Button>
                </Link>
            </template>
        </PageHeader>

        <!-- KPI Cards Row 1 -->
        <div class="grid gap-4 md:grid-cols-4">
            <StatCard label="MRR" :value="`${kpis.mrr}₫`" :icon="TrendingUp" color="emerald" class="" />
            <StatCard label="ARR" :value="`${kpis.arr}₫`" :icon="ArrowUpRight" color="sky" class="" />
            <StatCard label="Nhà hàng trả phí" :value="kpis.active_count" :icon="Users" color="violet" class="" />
            <StatCard label="Churn Rate (30d)" :value="`${kpis.churn_rate}%`" :icon="TrendingDown" :color="kpis.churn_rate > 5 ? 'rose' : 'sky'" :change="`${kpis.churned_30d} hết hạn`" class="" />
        </div>

        <!-- KPI Cards Row 2 -->
        <div class="grid gap-4 md:grid-cols-4">
            <Card :class="kpis.expiring_7d > 0 ? 'border-rose-200 dark:border-rose-900/40' : ''">
                <CardContent class="flex items-center gap-3 p-4">
                    <div :class="['rounded-lg p-2', kpis.expiring_7d > 0 ? 'bg-rose-100 dark:bg-rose-900/30' : 'bg-slate-100 dark:bg-slate-800']">
                        <AlertTriangle :class="['size-4', kpis.expiring_7d > 0 ? 'text-rose-600' : 'text-slate-400']" />
                    </div>
                    <div>
                        <p class="text-lg font-bold" :class="kpis.expiring_7d > 0 ? 'text-rose-600' : ''">{{ kpis.expiring_7d }}</p>
                        <p class="text-xs text-muted-foreground">Hết hạn trong 7 ngày</p>
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="flex items-center gap-3 p-4">
                    <div class="rounded-lg bg-amber-100 dark:bg-amber-900/30 p-2">
                        <Clock class="size-4 text-amber-600" />
                    </div>
                    <div>
                        <p class="text-lg font-bold">{{ kpis.expiring_14d }}</p>
                        <p class="text-xs text-muted-foreground">Hết hạn trong 14 ngày</p>
                    </div>
                </CardContent>
            </Card>
            <Card>
                <CardContent class="flex items-center gap-3 p-4">
                    <div class="rounded-lg bg-blue-100 dark:bg-blue-900/30 p-2">
                        <Activity class="size-4 text-blue-600" />
                    </div>
                    <div>
                        <p class="text-lg font-bold">{{ kpis.expiring_30d }}</p>
                        <p class="text-xs text-muted-foreground">Hết hạn trong 30 ngày</p>
                    </div>
                </CardContent>
            </Card>
            <Card :class="kpis.webhook_success_rate < 90 ? 'border-amber-200 dark:border-amber-900/40' : 'border-emerald-200 dark:border-emerald-900/40'">
                <CardContent class="flex items-center gap-3 p-4">
                    <div :class="['rounded-lg p-2', kpis.webhook_success_rate >= 90 ? 'bg-emerald-100 dark:bg-emerald-900/30' : 'bg-amber-100 dark:bg-amber-900/30']">
                        <Zap :class="['size-4', kpis.webhook_success_rate >= 90 ? 'text-emerald-600' : 'text-amber-600']" />
                    </div>
                    <div>
                        <p class="text-lg font-bold">{{ kpis.webhook_success_rate }}%</p>
                        <p class="text-xs text-muted-foreground">Webhook success rate</p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Revenue Chart + Plan Breakdown -->
        <div class="grid gap-6 xl:grid-cols-3">
            <!-- Area chart -->
            <Card class="xl:col-span-2">
                <CardHeader class="pb-3">
                    <CardTitle class="text-base">Doanh thu 12 tháng gần nhất</CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="monthly_revenue.length === 0" class="flex h-52 items-center justify-center text-sm text-muted-foreground">
                        Chưa có dữ liệu doanh thu.
                    </div>
                    <svg v-else :viewBox="`0 0 ${800} ${240}`" class="w-full" style="height: 220px;">
                        <defs>
                            <linearGradient id="rev-grad" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#10b981" stop-opacity="0.35" />
                                <stop offset="100%" stop-color="#10b981" stop-opacity="0.02" />
                            </linearGradient>
                        </defs>

                        <!-- Y grid lines -->
                        <g v-for="line in chartData.yLines" :key="line.y">
                            <line :x1="PAD_L" :y1="line.y" :x2="CHART_W - PAD_R" :y2="line.y" stroke="currentColor" stroke-opacity="0.08" stroke-width="1" />
                            <text :x="PAD_L - 8" :y="line.y + 4" text-anchor="end" font-size="10" fill="currentColor" fill-opacity="0.5">{{ line.label }}</text>
                        </g>

                        <!-- Area fill -->
                        <path :d="chartData.area" fill="url(#rev-grad)" />

                        <!-- Line -->
                        <polyline :points="chartData.points" fill="none" stroke="#10b981" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" />

                        <!-- Dots -->
                        <g v-for="(d, i) in monthly_revenue" :key="i">
                            <circle
                                :cx="PAD_L + (i / Math.max(monthly_revenue.length - 1, 1)) * (CHART_W - PAD_L - PAD_R)"
                                :cy="PAD_T + (CHART_H - PAD_T - PAD_B) - (d.revenue / (chartData.maxRevenue || 1)) * (CHART_H - PAD_T - PAD_B)"
                                r="4"
                                fill="#10b981"
                                stroke="white"
                                stroke-width="2"
                            />
                        </g>

                        <!-- X labels -->
                        <g v-for="lbl in chartData.labels.filter((_, i) => i % Math.max(Math.floor(monthly_revenue.length / 6), 1) === 0)" :key="lbl.text">
                            <text :x="lbl.x" :y="lbl.y" text-anchor="middle" font-size="10" fill="currentColor" fill-opacity="0.5">{{ lbl.text }}</text>
                        </g>
                    </svg>
                </CardContent>
            </Card>

            <!-- Plan breakdown -->
            <Card>
                <CardHeader class="pb-3">
                    <CardTitle class="text-base">Doanh thu theo gói</CardTitle>
                </CardHeader>
                <CardContent>
                    <div v-if="revenue_by_plan.length === 0" class="flex h-52 items-center justify-center text-sm text-muted-foreground">
                        Chưa có dữ liệu.
                    </div>
                    <div v-else class="space-y-3">
                        <div v-for="plan in revenue_by_plan" :key="plan.plan" class="space-y-1">
                            <div class="flex items-center justify-between text-sm">
                                <span class="font-medium">{{ plan.plan }}</span>
                                <span class="text-muted-foreground text-xs">{{ formatRevenue(plan.revenue) }}₫ · {{ plan.count }} đơn</span>
                            </div>
                            <div class="h-2 rounded-full bg-muted overflow-hidden">
                                <div
                                    class="h-full rounded-full bg-gradient-to-r from-emerald-500 to-sky-500 transition-all duration-700"
                                    :style="{ width: `${(plan.revenue / planChartMax) * 100}%` }"
                                />
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Invoice Aging Report -->
        <div class="grid gap-6 xl:grid-cols-2">
            <Card>
                <CardHeader class="pb-3">
                    <CardTitle class="flex items-center gap-2 text-base">
                        <CreditCard class="size-4 text-amber-500" />
                        Invoice Aging Report
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <div class="space-y-3">
                        <div
                            v-for="key in agingOrder"
                            :key="key"
                            class="flex items-center justify-between rounded-xl p-3"
                            :class="agingColorClass[aging_buckets[key].color]"
                        >
                            <div class="flex items-center gap-3">
                                <span class="text-lg font-bold">{{ aging_buckets[key].count }}</span>
                                <span class="text-sm font-medium">{{ aging_buckets[key].label }}</span>
                            </div>
                            <span class="text-sm font-semibold">{{ aging_buckets[key].amount_fmt }}₫</span>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Bad Debt & Collection Rate -->
            <Card :class="bad_debt.effective_collection_rate < 90 ? 'border-rose-200 dark:border-rose-900/40' : 'border-emerald-200 dark:border-emerald-900/40'">
                <CardHeader class="pb-3">
                    <CardTitle class="flex items-center gap-2 text-base">
                        <AlertTriangle class="size-4 text-rose-500" />
                        Nợ Xấu & Tỷ Lệ Thu Hồi
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <!-- Collection Rate -->
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-muted-foreground">Tỷ lệ thu hồi hiệu quả</span>
                        <span
                            class="text-xl font-bold"
                            :class="bad_debt.effective_collection_rate >= 95 ? 'text-emerald-600' : bad_debt.effective_collection_rate >= 85 ? 'text-amber-600' : 'text-rose-600'"
                        >
                            {{ bad_debt.effective_collection_rate }}%
                        </span>
                    </div>
                    <div class="h-2 w-full rounded-full bg-muted overflow-hidden">
                        <div
                            class="h-full rounded-full transition-all duration-700"
                            :class="bad_debt.effective_collection_rate >= 95 ? 'bg-emerald-500' : bad_debt.effective_collection_rate >= 85 ? 'bg-amber-500' : 'bg-rose-500'"
                            :style="{ width: `${bad_debt.effective_collection_rate}%` }"
                        />
                    </div>
                    <!-- Stats grid -->
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div class="rounded-lg bg-muted/40 p-3">
                            <p class="text-muted-foreground text-xs">Tổng hóa đơn</p>
                            <p class="font-semibold mt-0.5">{{ bad_debt.total_invoiced }}₫</p>
                        </div>
                        <div class="rounded-lg bg-muted/40 p-3">
                            <p class="text-muted-foreground text-xs">Đã thu</p>
                            <p class="font-semibold mt-0.5 text-emerald-600">{{ bad_debt.total_collected }}₫</p>
                        </div>
                        <div class="rounded-lg bg-rose-50 dark:bg-rose-900/20 p-3">
                            <p class="text-muted-foreground text-xs">Nợ xấu ({{ bad_debt.written_off_count }} HĐ)</p>
                            <p class="font-semibold mt-0.5 text-rose-600">{{ bad_debt.written_off_amount }}₫</p>
                        </div>
                        <div class="rounded-lg bg-amber-50 dark:bg-amber-900/20 p-3">
                            <p class="text-muted-foreground text-xs">Tổng điều chỉnh/giảm giá</p>
                            <p class="font-semibold mt-0.5 text-amber-600">{{ bad_debt.total_adjustments }}₫</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Expiring restaurants table -->
        <Card>
            <CardHeader class="pb-3">
                <CardTitle class="text-base flex items-center gap-2">
                    <AlertTriangle class="size-4 text-amber-500" />
                    Nhà hàng sắp hết hạn (30 ngày tới)
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div v-if="expiring_list.length === 0" class="py-10 text-center text-sm text-muted-foreground">
                    <CheckCircle2 class="mx-auto mb-2 size-8 text-emerald-400" />
                    Tất cả nhà hàng đều còn hạn tốt!
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b text-xs text-muted-foreground">
                                <th class="pb-3 text-left font-medium">Nhà hàng</th>
                                <th class="pb-3 text-left font-medium">Gói</th>
                                <th class="pb-3 text-left font-medium">Hết hạn</th>
                                <th class="pb-3 text-left font-medium">Còn lại</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr v-for="r in expiring_list" :key="r.id" class="hover:bg-muted/30 transition-colors">
                                <td class="py-2.5 pr-3 font-medium">{{ r.name }}</td>
                                <td class="py-2.5 pr-3 text-muted-foreground text-xs">{{ r.plan }}</td>
                                <td class="py-2.5 pr-3">{{ r.expires_on }}</td>
                                <td class="py-2.5">
                                    <Badge :class="r.days_left <= 7
                                        ? 'bg-rose-100 text-rose-700'
                                        : r.days_left <= 14
                                            ? 'bg-amber-100 text-amber-700'
                                            : 'bg-blue-100 text-blue-700'">
                                        {{ r.days_left }} ngày
                                    </Badge>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
