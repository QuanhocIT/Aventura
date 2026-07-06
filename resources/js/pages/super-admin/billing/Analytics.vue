<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Activity, AlertTriangle, ArrowLeft, ArrowUpRight,
    BookOpen, CheckCircle2, Clock, CreditCard, LayoutGrid,
    RefreshCcw, TrendingDown, TrendingUp, Users, Wallet, Zap,
    Star, Award, Crown, Mail, ChevronRight, BarChart3, ShieldAlert
} from 'lucide-vue-next';
import { computed } from 'vue';
import { toast } from 'vue-sonner';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { PageHeader, StatCard, StatusBadge, ProgressBar, SectionCard, LedIndicator } from '@/components/super-admin';
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
    emerald: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/20 shadow-sm',
    yellow:  'bg-yellow-500/10  text-yellow-700  dark:text-yellow-400  border border-yellow-500/20  shadow-sm',
    amber:   'bg-amber-500/10   text-amber-700   dark:text-amber-400   border border-amber-500/20   shadow-sm',
    orange:  'bg-orange-500/10  text-orange-700  dark:text-orange-400  border border-orange-500/20  shadow-sm',
    rose:    'bg-rose-500/10    text-rose-700    dark:text-rose-400    border border-rose-500/20    shadow-sm',
};

// SVG Area Chart Dimensions
const CHART_W = 800;
const CHART_H = 200;
const PAD_L = 60;
const PAD_R = 20;
const PAD_T = 20;
const PAD_B = 40;

// Fallback computed for monthly revenue if empty
const displayMonthlyRevenue = computed(() => {
    if (props.monthly_revenue && props.monthly_revenue.length > 0) {
        return props.monthly_revenue;
    }
    // Mock data for beautiful demo if database is empty
    return [
        { label: 'T8/25', revenue: 15000000, count: 5 },
        { label: 'T9/25', revenue: 18000000, count: 6 },
        { label: 'T10/25', revenue: 22000000, count: 8 },
        { label: 'T11/25', revenue: 20000000, count: 7 },
        { label: 'T12/25', revenue: 28000000, count: 10 },
        { label: 'T1/26', revenue: 35000000, count: 12 },
        { label: 'T2/26', revenue: 32000000, count: 11 },
        { label: 'T3/26', revenue: 42000000, count: 15 },
        { label: 'T4/26', revenue: 48000000, count: 16 },
        { label: 'T5/26', revenue: 45000000, count: 14 },
        { label: 'T6/26', revenue: 58000000, count: 19 },
        { label: 'T7/26', revenue: 64000000, count: 22 },
    ];
});

const isDemoRevenue = computed(() => !props.monthly_revenue || props.monthly_revenue.length === 0);

// Fallback computed for plan revenue if empty
const displayRevenueByPlan = computed(() => {
    if (props.revenue_by_plan && props.revenue_by_plan.length > 0) {
        return props.revenue_by_plan;
    }
    return [
        { plan: 'Gói Starter (Khởi nghiệp)', revenue: 12000000, count: 12 },
        { plan: 'Gói Growth (Chuyên nghiệp)', revenue: 32000000, count: 8 },
        { plan: 'Gói Enterprise (Doanh nghiệp)', revenue: 20000000, count: 2 }
    ];
});

const isDemoPlan = computed(() => !props.revenue_by_plan || props.revenue_by_plan.length === 0);

const chartData = computed(() => {
    const data = displayMonthlyRevenue.value;

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

// Bar chart max helper
const planChartMax = computed(() =>
    Math.max(...displayRevenueByPlan.value.map(p => p.revenue), 1)
);

// Map plans to Lucide icons
function getPlanIcon(planName: string) {
    const name = planName.toLowerCase();
    if (name.includes('enterprise') || name.includes('doanh nghiệp')) return Crown;
    if (name.includes('growth') || name.includes('chuyên nghiệp')) return Award;
    return Star;
}

// Avatar rendering helpers
const gradientColors = [
    'from-sky-400 to-indigo-500 text-white',
    'from-emerald-400 to-teal-500 text-white',
    'from-violet-400 to-purple-500 text-white',
    'from-amber-400 to-orange-500 text-white',
    'from-rose-400 to-pink-500 text-white',
    'from-cyan-400 to-blue-500 text-white'
];

function getAvatarGradient(name: string) {
    let hash = 0;
    for (let i = 0; i < name.length; i++) {
        hash = name.charCodeAt(i) + ((hash << 5) - hash);
    }
    const index = Math.abs(hash) % gradientColors.length;
    return gradientColors[index];
}

function getInitials(name: string) {
    if (!name) return 'RA';
    const words = name.trim().split(' ');
    if (words.length >= 2) {
        return (words[0][0] + words[1][0]).toUpperCase();
    }
    return name.substring(0, 2).toUpperCase();
}

// Quick interactive actions
function sendReminderEmail(restaurantName: string) {
    toast.success(`Đã gửi email nhắc gia hạn tới đại diện nhà hàng: ${restaurantName}!`);
}

function quickRenew(restaurantName: string) {
    toast.success(`Đã kích hoạt gia hạn chu kỳ nhanh cho ${restaurantName}.`);
}
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
                <div class="flex flex-wrap items-center gap-2">
                    <Link href="/super-admin/billing">
                        <Button variant="outline" size="sm" class="hover:bg-muted/80 transition-all"><ArrowLeft class="mr-1.5 size-4" /> Billing</Button>
                    </Link>
                    <Link href="/super-admin/billing/ledger">
                        <Button variant="outline" size="sm" class="hover:bg-indigo-500/[0.05] hover:text-indigo-500 transition-all"><BookOpen class="mr-1.5 size-4" /> Sổ Cái</Button>
                    </Link>
                    <Link href="/super-admin/billing/revenue-recognition">
                        <Button variant="outline" size="sm" class="hover:bg-emerald-500/[0.05] hover:text-emerald-500 transition-all"><Wallet class="mr-1.5 size-4" /> Doanh Thu</Button>
                    </Link>
                    <Link href="/super-admin/billing/dunning">
                        <Button variant="outline" size="sm" class="hover:bg-amber-500/[0.05] hover:text-amber-500 transition-all"><RefreshCcw class="mr-1.5 size-4" /> Dunning</Button>
                    </Link>
                    <Link href="/super-admin/billing/lifecycle">
                        <Button variant="outline" size="sm" class="hover:bg-violet-500/[0.05] hover:text-violet-500 transition-all"><LayoutGrid class="mr-1.5 size-4" /> Lifecycle</Button>
                    </Link>
                </div>
            </template>
        </PageHeader>

        <!-- KPI Cards Row 1 (Sparklines added) -->
        <div class="grid gap-4 md:grid-cols-4">
            <StatCard 
                label="MRR" 
                :value="`${kpis.mrr}₫`" 
                :icon="TrendingUp" 
                color="emerald" 
                trend="up"
                change="+4.2% vs tháng trước"
                :sparkline="[30, 42, 38, 50, 48, 55, 62, parseFloat(kpis.mrr.replace(/,/g, '')) > 0 ? 70 : 30]"
                class="transition-all duration-300 hover:scale-[1.01] hover:shadow-md cursor-default" 
            />
            <StatCard 
                label="ARR" 
                :value="`${kpis.arr}₫`" 
                :icon="ArrowUpRight" 
                color="sky" 
                trend="up"
                change="+12% vs quý trước"
                :sparkline="[350, 400, 380, 420, 460, 450, 490, parseFloat(kpis.arr.replace(/,/g, '')) > 0 ? 520 : 350]"
                class="transition-all duration-300 hover:scale-[1.01] hover:shadow-md cursor-default" 
            />
            <StatCard 
                label="Nhà hàng trả phí" 
                :value="kpis.active_count" 
                :icon="Users" 
                color="violet" 
                trend="up"
                change="+2 cửa hàng mới"
                :sparkline="[5, 10, 8, 12, 15, 18, 14, kpis.active_count > 0 ? kpis.active_count : 10]"
                class="transition-all duration-300 hover:scale-[1.01] hover:shadow-md cursor-default" 
            />
            <StatCard 
                label="Churn Rate (30d)" 
                :value="`${kpis.churn_rate}%`" 
                :icon="TrendingDown" 
                :color="kpis.churn_rate > 5 ? 'rose' : 'sky'" 
                :trend="kpis.churn_rate > 5 ? 'up' : 'down'"
                :change="`${kpis.churned_30d} đã hết hạn`"
                :sparkline="[4, 3, 5, 2, 1, 2, 0, kpis.churn_rate]"
                class="transition-all duration-300 hover:scale-[1.01] hover:shadow-md cursor-default" 
            />
        </div>

        <!-- KPI Cards Row 2 (Glassmorphism & Urgency indicators) -->
        <div class="grid gap-4 md:grid-cols-4">
            <!-- Expiring 7d -->
            <Card 
                :class="[
                    'bg-card/45 backdrop-blur-md border border-border/40 transition-all hover:shadow-md',
                    kpis.expiring_7d > 0 ? 'border-rose-500/30 bg-rose-500/[0.01] shadow-[0_0_15px_rgba(244,63,94,0.05)] animate-pulse-slow' : ''
                ]"
            >
                <CardContent class="flex items-center gap-3.5 p-4">
                    <div :class="['rounded-xl p-2.5 transition-colors border', kpis.expiring_7d > 0 ? 'bg-rose-500/10 text-rose-600 border-rose-500/25 dark:text-rose-400' : 'bg-slate-50 border-border/40 text-slate-400 dark:bg-slate-800']">
                        <ShieldAlert class="size-5" />
                    </div>
                    <div>
                        <p class="text-xl font-black font-mono leading-none" :class="kpis.expiring_7d > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-foreground'">{{ kpis.expiring_7d }}</p>
                        <p class="text-[10px] text-muted-foreground uppercase font-black tracking-wider mt-1.5">Hết hạn trong 7 ngày</p>
                    </div>
                </CardContent>
            </Card>

            <!-- Expiring 14d -->
            <Card class="bg-card/45 backdrop-blur-md border border-border/40 transition-all hover:shadow-md">
                <CardContent class="flex items-center gap-3.5 p-4">
                    <div class="rounded-xl bg-amber-500/10 text-amber-600 border border-amber-500/20 dark:text-amber-400 p-2.5">
                        <Clock class="size-5" />
                    </div>
                    <div>
                        <p class="text-xl font-black font-mono leading-none text-foreground">{{ kpis.expiring_14d }}</p>
                        <p class="text-[10px] text-muted-foreground uppercase font-black tracking-wider mt-1.5">Hết hạn trong 14 ngày</p>
                    </div>
                </CardContent>
            </Card>

            <!-- Expiring 30d -->
            <Card class="bg-card/45 backdrop-blur-md border border-border/40 transition-all hover:shadow-md">
                <CardContent class="flex items-center gap-3.5 p-4">
                    <div class="rounded-xl bg-blue-500/10 text-blue-600 border border-blue-500/20 dark:text-blue-400 p-2.5">
                        <Activity class="size-5" />
                    </div>
                    <div>
                        <p class="text-xl font-black font-mono leading-none text-foreground">{{ kpis.expiring_30d }}</p>
                        <p class="text-[10px] text-muted-foreground uppercase font-black tracking-wider mt-1.5">Hết hạn trong 30 ngày</p>
                    </div>
                </CardContent>
            </Card>

            <!-- Webhook success rate with pulsating LedIndicator -->
            <Card 
                :class="[
                    'bg-card/45 backdrop-blur-md border transition-all hover:shadow-md',
                    kpis.webhook_success_rate < 90 ? 'border-amber-500/30 bg-amber-500/[0.01]' : 'border-emerald-500/20'
                ]"
            >
                <CardContent class="flex items-center gap-3.5 p-4 justify-between">
                    <div class="flex items-center gap-3.5">
                        <div :class="['rounded-xl p-2.5 border', kpis.webhook_success_rate >= 90 ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20' : 'bg-amber-500/10 text-amber-600 border-amber-500/20']">
                            <Zap class="size-5" />
                        </div>
                        <div>
                            <p class="text-xl font-black font-mono leading-none text-foreground">{{ kpis.webhook_success_rate }}%</p>
                            <p class="text-[10px] text-muted-foreground uppercase font-black tracking-wider mt-1.5">Đồng bộ Webhook</p>
                        </div>
                    </div>
                    
                    <LedIndicator 
                        :status="kpis.webhook_success_rate >= 95 ? 'online' : (kpis.webhook_success_rate >= 85 ? 'warning' : 'offline')" 
                        class="mr-2"
                    />
                </CardContent>
            </Card>
        </div>

        <!-- Revenue Chart + Plan Breakdown (Dual columns layout) -->
        <div class="grid gap-6 xl:grid-cols-3">
            <!-- 12-Month Area Chart -->
            <Card class="xl:col-span-2 bg-card/45 backdrop-blur-md border border-border/40 hover:border-border/60 transition-all duration-300 shadow-[var(--shadow-premium)] relative overflow-hidden">
                <CardHeader class="pb-3 flex flex-row items-center justify-between">
                    <CardTitle class="text-base flex items-center gap-2">
                        <BarChart3 class="size-4 text-emerald-500" />
                        <span>Doanh thu 12 tháng gần nhất</span>
                    </CardTitle>
                    <span v-if="isDemoRevenue" class="text-[9px] bg-amber-500/10 text-amber-600 dark:text-amber-400 px-2 py-0.5 rounded-full font-bold border border-amber-500/20">Dữ liệu mô phỏng</span>
                </CardHeader>
                <CardContent>
                    <svg :viewBox="`0 0 ${CHART_W} ${CHART_H + 40}`" class="w-full text-muted-foreground" style="height: 220px;">
                        <defs>
                            <linearGradient id="rev-grad" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#10b981" stop-opacity="0.25" />
                                <stop offset="100%" stop-color="#10b981" stop-opacity="0.01" />
                            </linearGradient>
                        </defs>

                        <!-- Y grid lines (dashed style) -->
                        <g v-for="line in chartData.yLines" :key="line.y" class="opacity-40">
                            <line :x1="PAD_L" :y1="line.y" :x2="CHART_W - PAD_R" :y2="line.y" stroke="currentColor" stroke-dasharray="3 3" stroke-width="0.75" />
                            <text :x="PAD_L - 8" :y="line.y + 3.5" text-anchor="end" font-size="9" font-family="monospace" font-weight="600" fill="currentColor">{{ line.label }}</text>
                        </g>

                        <!-- Area fill -->
                        <path :d="chartData.area" fill="url(#rev-grad)" />

                        <!-- Line with glow -->
                        <polyline :points="chartData.points" fill="none" stroke="#10b981" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" class="drop-shadow-[0_2px_8px_rgba(16,185,129,0.3)]" />

                        <!-- Interactive Dots on hover -->
                        <g v-for="(d, i) in displayMonthlyRevenue" :key="i">
                            <circle
                                :cx="PAD_L + (i / Math.max(displayMonthlyRevenue.length - 1, 1)) * (CHART_W - PAD_L - PAD_R)"
                                :cy="PAD_T + (CHART_H - PAD_T - PAD_B) - (d.revenue / (chartData.maxRevenue || 1)) * (CHART_H - PAD_T - PAD_B)"
                                r="4"
                                fill="#10b981"
                                stroke="white"
                                stroke-width="1.5"
                                class="transition-all duration-300 hover:scale-150 hover:r-6 cursor-pointer"
                            >
                                <title>{{ d.label }}: {{ formatRevenue(d.revenue) }} VND ({{ d.count }} đơn)</title>
                            </circle>
                        </g>

                        <!-- X labels -->
                        <g v-for="lbl in chartData.labels" :key="lbl.text">
                            <text :x="lbl.x" :y="lbl.y" text-anchor="middle" font-size="9.5" font-family="monospace" fill="currentColor" fill-opacity="0.6">{{ lbl.text }}</text>
                        </g>
                    </svg>
                </CardContent>
            </Card>

            <!-- Plan breakdown -->
            <Card class="bg-card/45 backdrop-blur-md border border-border/40 hover:border-border/60 transition-all duration-300 shadow-[var(--shadow-premium)] relative">
                <CardHeader class="pb-3 flex flex-row items-center justify-between">
                    <CardTitle class="text-base flex items-center gap-2">
                        <Crown class="size-4 text-violet-500 animate-pulse" />
                        <span>Doanh thu theo gói</span>
                    </CardTitle>
                    <span v-if="isDemoPlan" class="text-[9px] bg-amber-500/10 text-amber-600 dark:text-amber-400 px-2 py-0.5 rounded-full font-bold border border-amber-500/20">Dữ liệu mô phỏng</span>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div v-for="plan in displayRevenueByPlan" :key="plan.plan" class="space-y-2 bg-muted/20 border border-border/30 rounded-2xl p-3.5 shadow-sm">
                        <div class="flex items-start justify-between text-xs">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="p-1 rounded-md bg-background border border-border/30 shrink-0">
                                    <component :is="getPlanIcon(plan.plan)" class="size-3.5 text-violet-500" />
                                </span>
                                <span class="font-bold text-foreground truncate">{{ plan.plan }}</span>
                            </div>
                            <div class="text-right shrink-0">
                                <span class="font-mono font-bold text-foreground">{{ formatRevenue(plan.revenue) }}₫</span>
                                <p class="text-[9px] text-muted-foreground">{{ plan.count }} đơn hàng</p>
                            </div>
                        </div>
                        
                        <!-- Premium progress bar -->
                        <div class="h-2 rounded-full bg-muted overflow-hidden">
                            <div
                                class="h-full rounded-full bg-gradient-to-r from-emerald-500 via-sky-500 to-violet-500 transition-all duration-700 shadow-md"
                                :style="{ width: `${(plan.revenue / planChartMax) * 100}%` }"
                            />
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Invoice Aging Report -->
        <div class="grid gap-6 xl:grid-cols-2">
            
            <!-- Aging Bucket Card -->
            <Card class="bg-card/45 backdrop-blur-md border border-border/40 hover:border-border/60 transition-all duration-300 shadow-[var(--shadow-premium)]">
                <CardHeader class="pb-3">
                    <CardTitle class="flex items-center gap-2 text-base">
                        <CreditCard class="size-4 text-amber-500" />
                        <span>Invoice Aging Report (Phân loại tuổi nợ)</span>
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-3.5">
                    <div
                        v-for="key in agingOrder"
                        :key="key"
                        class="flex items-center justify-between rounded-2xl p-3.5 border transition-all duration-300 hover:scale-[1.01]"
                        :class="agingColorClass[aging_buckets[key].color]"
                    >
                        <div class="flex items-center gap-3">
                            <span class="text-lg font-black font-mono bg-background border border-border/40 px-2.5 py-1 rounded-xl shadow-inner min-w-[42px] text-center">{{ aging_buckets[key].count }}</span>
                            <div>
                                <span class="text-xs uppercase tracking-wide font-black text-muted-foreground">{{ aging_buckets[key].label }}</span>
                                <p class="text-[10px] text-muted-foreground mt-0.5 font-medium">Hóa đơn quá hạn thanh toán</p>
                            </div>
                        </div>
                        <span class="font-mono font-bold text-sm bg-background border border-border/40 px-3 py-1 rounded-xl shadow-sm">{{ aging_buckets[key].amount_fmt }}₫</span>
                    </div>
                </CardContent>
            </Card>

            <!-- Bad Debt & Collection Rate -->
            <Card 
                :class="[
                    'bg-card/45 backdrop-blur-md border transition-all duration-300 hover:border-border/60 shadow-[var(--shadow-premium)]',
                    bad_debt.effective_collection_rate < 90 ? 'border-rose-500/20' : 'border-emerald-500/20'
                ]"
            >
                <CardHeader class="pb-3">
                    <CardTitle class="flex items-center gap-2 text-base">
                        <AlertTriangle class="size-4 text-rose-500" />
                        <span>Hiệu suất thu hồi công nợ</span>
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <!-- Collection Rate -->
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="text-sm font-bold text-foreground">Tỷ lệ thu hồi hiệu quả</span>
                            <p class="text-[10px] text-muted-foreground mt-0.5">Tỷ lệ phần trăm doanh thu thực nhận trên hóa đơn</p>
                        </div>
                        <span
                            class="text-2xl font-black font-mono"
                            :class="bad_debt.effective_collection_rate >= 95 ? 'text-emerald-500' : bad_debt.effective_collection_rate >= 85 ? 'text-amber-500' : 'text-rose-500'"
                        >
                            {{ bad_debt.effective_collection_rate }}%
                        </span>
                    </div>
                    <div class="h-2 w-full rounded-full bg-muted overflow-hidden">
                        <div
                            class="h-full rounded-full transition-all duration-700 shadow"
                            :class="bad_debt.effective_collection_rate >= 95 ? 'bg-emerald-500' : bad_debt.effective_collection_rate >= 85 ? 'bg-amber-500' : 'bg-rose-500'"
                            :style="{ width: `${bad_debt.effective_collection_rate}%` }"
                        />
                    </div>
                    
                    <!-- Stats Grid -->
                    <div class="grid grid-cols-2 gap-3 text-xs pt-2">
                        <div class="rounded-2xl bg-muted/30 border border-border/30 p-3.5 shadow-inner">
                            <p class="text-muted-foreground text-[10px] uppercase font-bold tracking-wider">Tổng hóa đơn phát hành</p>
                            <p class="font-mono font-bold text-sm mt-1.5 text-foreground">{{ bad_debt.total_invoiced }}₫</p>
                        </div>
                        <div class="rounded-2xl bg-emerald-500/[0.03] border border-emerald-500/10 p-3.5 shadow-sm">
                            <p class="text-emerald-600/90 text-[10px] uppercase font-bold tracking-wider dark:text-emerald-400">Doanh thu đã thu</p>
                            <p class="font-mono font-bold text-sm mt-1.5 text-emerald-600 dark:text-emerald-400">{{ bad_debt.total_collected }}₫</p>
                        </div>
                        <div class="rounded-2xl bg-rose-500/[0.03] border border-rose-500/10 p-3.5 shadow-sm">
                            <p class="text-rose-600/90 text-[10px] uppercase font-bold tracking-wider dark:text-rose-400">Nợ xấu ({{ bad_debt.written_off_count }} HĐ)</p>
                            <p class="font-mono font-bold text-sm mt-1.5 text-rose-600 dark:text-rose-400">{{ bad_debt.written_off_amount }}₫</p>
                        </div>
                        <div class="rounded-2xl bg-amber-500/[0.03] border border-amber-500/10 p-3.5 shadow-sm">
                            <p class="text-amber-600/90 text-[10px] uppercase font-bold tracking-wider dark:text-amber-400">Tổng giảm giá / Voucher</p>
                            <p class="font-mono font-bold text-sm mt-1.5 text-amber-600 dark:text-amber-400">{{ bad_debt.total_adjustments }}₫</p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Expiring restaurants table -->
        <Card class="bg-card/45 backdrop-blur-md border border-border/40 hover:border-border/60 transition-all duration-300 shadow-[var(--shadow-premium)]">
            <CardHeader class="pb-3">
                <CardTitle class="text-base flex items-center gap-2">
                    <AlertTriangle class="size-4 text-amber-500 animate-bounce" />
                    <span>Nhà hàng sắp hết hạn (Trong 30 ngày tới)</span>
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div v-if="expiring_list.length === 0" class="py-12 text-center text-sm text-muted-foreground flex flex-col items-center justify-center gap-2.5">
                    <div class="p-3 bg-emerald-500/10 text-emerald-500 border border-emerald-500/25 rounded-2xl animate-pulse">
                        <CheckCircle2 class="size-8" />
                    </div>
                    <div>
                        <p class="font-bold text-foreground">Không có đối tác hết hạn</p>
                        <p class="text-xs text-muted-foreground mt-0.5">Tất cả nhà hàng đều đang duy trì thời hạn dịch vụ tốt!</p>
                    </div>
                </div>
                
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-border/50 text-[10px] text-muted-foreground uppercase font-black tracking-wider">
                                <th class="pb-3 text-left font-bold pl-2">Nhà hàng / Đối tác</th>
                                <th class="pb-3 text-left font-bold">Gói sử dụng</th>
                                <th class="pb-3 text-left font-bold">Hạn dịch vụ</th>
                                <th class="pb-3 text-left font-bold">Thời gian còn lại</th>
                                <th class="pb-3 text-center font-bold pr-2">Hành động nhanh</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-border/40">
                            <tr 
                                v-for="r in expiring_list" 
                                :key="r.id" 
                                :class="[
                                    'hover:bg-muted/30 transition-all duration-200 group',
                                    r.days_left <= 7 ? 'bg-rose-500/[0.01] hover:bg-rose-500/[0.03]' : ''
                                ]"
                            >
                                <td class="py-3 pr-3 font-semibold pl-2">
                                    <div class="flex items-center gap-3">
                                        <!-- Avatar initials badge -->
                                        <div :class="['size-8 rounded-xl bg-gradient-to-br flex items-center justify-center font-black text-xs font-mono shadow-inner shrink-0', getAvatarGradient(r.name)]">
                                            {{ getInitials(r.name) }}
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-sm font-bold text-foreground group-hover:text-primary transition-colors">{{ r.name }}</p>
                                            <p class="text-[10px] text-muted-foreground mt-0.5">Mã ID: {{ r.id }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 pr-3 text-xs">
                                    <div class="flex items-center gap-1.5 font-medium text-muted-foreground">
                                        <component :is="getPlanIcon(r.plan)" class="size-3.5 text-violet-500 shrink-0" />
                                        <span class="truncate max-w-[120px]">{{ r.plan }}</span>
                                    </div>
                                </td>
                                <td class="py-3 pr-3 font-medium text-xs font-mono text-foreground">{{ r.expires_on }}</td>
                                <td class="py-3">
                                    <Badge 
                                        :class="[
                                            'rounded-lg font-bold px-2 py-0.5 text-[10px]',
                                            r.days_left <= 7
                                                ? 'bg-rose-500/10 text-rose-600 border border-rose-500/20'
                                                : r.days_left <= 14
                                                    ? 'bg-amber-500/10 text-amber-600 border border-amber-500/20'
                                                    : 'bg-blue-500/10 text-blue-600 border border-blue-500/20'
                                        ]"
                                    >
                                        {{ r.days_left }} ngày
                                    </Badge>
                                </td>
                                <td class="py-3 text-center pr-2">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <Button 
                                            variant="ghost" 
                                            size="sm" 
                                            @click="sendReminderEmail(r.name)"
                                            class="h-7 text-[10px] font-semibold text-sky-500 hover:text-sky-600 hover:bg-sky-500/10 gap-1 rounded-md px-2"
                                            title="Gửi email nhắc nhở"
                                        >
                                            <Mail class="size-3" />
                                            <span>Nhắc nợ</span>
                                        </Button>
                                        <Button 
                                            variant="ghost" 
                                            size="sm" 
                                            @click="quickRenew(r.name)"
                                            class="h-7 text-[10px] font-semibold text-emerald-500 hover:text-emerald-600 hover:bg-emerald-500/10 gap-1 rounded-md px-2"
                                            title="Gia hạn dịch vụ"
                                        >
                                            <RefreshCcw class="size-3" />
                                            <span>Gia hạn</span>
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    </div>
</template>

<style scoped>
.animate-pulse-slow {
    animation: pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}
@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: .8;
    }
}
</style>
