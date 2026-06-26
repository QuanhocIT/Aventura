<script setup lang="ts">
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { 
    Building2, ArrowRight, Activity, TrendingUp, ShieldAlert, Zap,
    Utensils, AlertTriangle, Users, Target, PlusCircle, Layers, CheckCircle2, XCircle, Package, ChevronRight, ShoppingCart
} from 'lucide-vue-next';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';

// Subcomponents
import AIInsightsCard from '@/components/dashboard/charts/AIInsightsCard.vue';
import ChannelShareChart from '@/components/dashboard/charts/ChannelShareChart.vue';
import PeakHoursChart from '@/components/dashboard/charts/PeakHoursChart.vue';
import RevenueForecastChart from '@/components/dashboard/charts/RevenueForecastChart.vue';
import TopProductsLeaderboard from '@/components/dashboard/charts/TopProductsLeaderboard.vue';
import WeatherForecastCard from '@/components/dashboard/charts/WeatherForecastCard.vue';
import CashFlowWidget from '@/components/dashboard/charts/CashFlowWidget.vue';
import DashboardHeader from '@/components/dashboard/DashboardHeader.vue';
import DashboardKPIs from '@/components/dashboard/DashboardKPIs.vue';
import OperationsCenter from '@/components/dashboard/operations/OperationsCenter.vue';
import QuickActions from '@/components/dashboard/QuickActions.vue';
import DashboardSidebar from '@/components/dashboard/sidebar/DashboardSidebar.vue';

interface OperationFeedItem {
    type: string;
    title: string;
    description: string;
    amount: number | null;
    time: string;
    timestamp: number;
    icon: string;
    color: string;
    link: string;
}

interface TableData {
    id: number;
    name: string;
    area: string;
    capacity: number;
    status: string; // 'available', 'occupied', 'reserved', 'cleaning'
}

interface LowStockIngredient {
    id: number;
    ingredient_name: string;
    quantity_on_hand: number;
    min_stock_level: number;
    reorder_level: number;
    unit_name: string;
}

interface Stats {
    products_count: number;
    employees_count: number;
    branches_count: number;
    tables_count: number;
    orders_today: number;
    orders_completed: number;
    orders_cancelled: number;
    revenue_today: number;
    revenue_trend: number | null;
    order_trend: number | null;
    profit_margin_today: number;
    completion_rate: number;
}

interface ForecastData {
    amount: number;
    confidence: string;
    confidence_label: string;
    samples: number;
    day_label: string;
    trend_factor: number;
}

interface ShiftRevenueRow {
    shift_name: string;
    days: { date: string; revenue: number }[];
}

interface OwnerSummary {
    top_products_today: { name: string; qty: number; revenue: number }[];
    active_shifts: { name: string; shift: string; status: string }[];
    pending_over_20min: number;
    revenue_this_week: number;
    revenue_last_week: number;
}

interface RecentOrder {
    id: number;
    order_number: string;
    table_name: string | null;
    total_amount: number;
    status: string;
    payment_status: string;
    channel: string;
    created_at: string;
}

interface Alert {
    type: 'warning' | 'danger' | 'info';
    message: string;
    href: string;
}

interface RevenueDay {
    date: string;
    revenue: number;
    orders: any;
}

interface ChannelShare {
    channel: string;
    label: string;
    count: number;
    percentage: number;
}

interface TopProductStat {
    name: string;
    quantity: number;
    revenue: number;
}

interface BranchComparison {
    id: number;
    name: string;
    revenue: number;
    profit_margin: number;
    health_score: number;
    violations_count: number;
}

const props = defineProps<{
    operationFeed?: OperationFeedItem[];
    tablesData?: TableData[];
    lowStockInventory?: LowStockIngredient[];
    stats?: Stats | null;
    onboardingComplete?: boolean;
    recentOrders?: RecentOrder[];
    alerts?: Alert[];
    revenueChartData?: RevenueDay[];
    peakHoursChartData?: any[];
    channelChartData?: ChannelShare[];
    topProductsChartData?: TopProductStat[];
    forecastData?: ForecastData | null;
    healthScore?: number | null;
    shiftRevenue?: ShiftRevenueRow[];
    ownerSummary?: OwnerSummary | null;
    branchId?: number | null;
    branches?: { id: number; name: string }[];
    branchComparisons?: BranchComparison[];
    cashFlowSummary?: any;
}>();

const page = usePage();
const user = computed(() => (page.props.auth as any)?.user ?? null);
const tenant = computed(() => (page.props as any).tenant ?? null);
const plan = computed(() => tenant.value?.plan ?? null);
const quota = computed(() => tenant.value?.quota_summary ?? null);

const availablePlans = computed(() => (page.props as any).available_plans ?? []);
const roles = computed(() => (page.props as any).roles ?? []);

const selectedBranch = ref(props.branchId || 'all');

watch(selectedBranch, (newVal) => {
    router.get('/dashboard', { branch_id: newVal }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
});

watch(() => props.branchId, (newVal) => {
    selectedBranch.value = newVal || 'all';
});

const selectBranchDirectly = (id: number) => {
    selectedBranch.value = id;
};

const formatVND = (value: number) => {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(value);
};

const activePlanCode = computed(() => plan.value?.code ?? 'free');

const tableStatusMap = {
    available: { label: 'Bàn trống', class: 'bg-emerald-50 text-emerald-700 border-emerald-100 dark:bg-emerald-950/20 dark:text-emerald-400 dark:border-emerald-900/30' },
    occupied:  { label: 'Có khách', class: 'bg-indigo-50 text-indigo-700 border-indigo-100 dark:bg-indigo-950/20 dark:text-indigo-400 dark:border-indigo-900/30' },
    reserved:  { label: 'Đặt trước', class: 'bg-violet-50 text-violet-700 border-violet-100 dark:bg-violet-950/20 dark:text-violet-400 dark:border-violet-900/30' },
    cleaning:  { label: 'Dọn dẹp', class: 'bg-amber-50 text-amber-700 border-amber-100 dark:bg-amber-950/20 dark:text-amber-400 dark:border-amber-900/30' },
};

function getTableStatusInfo(status: string) {
    return tableStatusMap[status as keyof typeof tableStatusMap] ?? { label: status, class: 'bg-muted text-muted-foreground border-border' };
}
</script>

<template>
    <Head title="Dashboard · Aventura" />

    <!-- ── Trial Countdown Banner & Welcome header ── -->
    <DashboardHeader
        :user="user"
        :tenant="tenant"
        :plan="plan"
        :quota="quota"
        :available-plans="availablePlans"
        :roles="roles"
    />

    <!-- Main Content Section -->
    <div class="mx-auto max-w-7xl px-4 py-8 lg:px-8 space-y-7 relative">
        
        <!-- Branch selection dropdown (Only if owner has multiple branches) -->
        <div v-if="props.branches && props.branches.length > 1" class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-100 dark:border-slate-800 pb-5">
            <div>
                <h2 class="text-xl font-black tracking-tight text-slate-850 dark:text-slate-100 flex items-center gap-2.5">
                    <span class="size-2.5 bg-teal-500 rounded-full animate-pulse shadow-sm shadow-teal-500" />
                    {{ branchId ? 'Báo cáo chi nhánh' : 'Trung tâm chỉ huy chuỗi' }}
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 font-medium">
                    {{ branchId ? 'Giám sát vận hành và hiệu suất của chi nhánh hiện tại.' : 'Tổng quan hiệu suất và sức khỏe hoạt động của toàn chuỗi.' }}
                </p>
            </div>
            
            <div class="flex items-center gap-2.5">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Chi nhánh:</span>
                <select 
                    id="branch-selector"
                    v-model="selectedBranch"
                    class="min-w-[220px] text-xs font-bold rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 px-4 py-2.5 focus:ring-4 focus:ring-teal-500/10 focus:border-teal-500 outline-none shadow-sm dark:text-slate-300 cursor-pointer transition-all"
                >
                    <option value="all">🌐 Toàn chuỗi (Hợp nhất)</option>
                    <option v-for="b in props.branches" :key="b.id" :value="b.id">
                        📍 {{ b.name }}
                    </option>
                </select>
            </div>
        </div>

        <!-- Today's KPI Row & Health Score -->
        <DashboardKPIs
            :stats="props.stats"
            :health-score="props.healthScore"
        />

        <!-- Consolidated Branch Comparisons (only in consolidated view) -->
        <Card v-if="!branchId && props.branchComparisons && props.branchComparisons.length > 0" class="shadow-md rounded-2xl overflow-hidden border border-slate-100 dark:border-slate-800/80 bg-white dark:bg-slate-900/40 backdrop-blur-md">
            <CardHeader class="pb-4 border-b border-slate-100 dark:border-slate-850 bg-slate-50/30 dark:bg-slate-900/10">
                <div class="flex items-center gap-2.5">
                    <div class="p-2 rounded-xl bg-teal-500/10 text-teal-600 dark:text-teal-400">
                        <Building2 class="size-4.5" />
                    </div>
                    <div>
                        <CardTitle class="text-sm font-bold text-slate-850 dark:text-slate-100">
                            Hiệu suất chi tiết từng chi nhánh
                        </CardTitle>
                        <CardDescription class="text-xs mt-0.5">
                            So sánh doanh thu hôm nay, biên lợi nhuận, sức khỏe vận hành và tổng lỗi sai phạm trong 30 ngày qua.
                        </CardDescription>
                    </div>
                </div>
            </CardHeader>
            
            <CardContent class="p-0 overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-50/50 dark:bg-slate-900/20 border-b border-slate-100 dark:border-slate-850 text-slate-500 dark:text-slate-400 font-extrabold uppercase tracking-wider text-[10px]">
                            <th class="p-4 pl-6">Chi nhánh</th>
                            <th class="p-4 text-right">Doanh thu hôm nay</th>
                            <th class="p-4 text-right">Biên lợi nhuận</th>
                            <th class="p-4 text-center">Sức khỏe vận hành</th>
                            <th class="p-4 text-center">Sai phạm (30 ngày)</th>
                            <th class="p-4 pr-6 text-center">Hành động</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-850">
                        <tr 
                            v-for="b in props.branchComparisons" 
                            :key="b.id"
                            class="hover:bg-slate-55/40 dark:hover:bg-slate-900/40 transition duration-150 text-slate-700 dark:text-slate-300"
                        >
                            <td class="p-4 pl-6 font-bold flex items-center gap-2">
                                <span class="size-2 rounded-full bg-teal-500 shrink-0 shadow-sm" />
                                {{ b.name }}
                            </td>
                            <td class="p-4 text-right font-mono font-black text-teal-600 dark:text-teal-400">
                                {{ formatVND(b.revenue) }}
                            </td>
                            <td class="p-4 text-right font-bold">
                                <div class="inline-flex items-center gap-1 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 px-2 py-0.5 rounded-md text-[11px]">
                                    <TrendingUp class="size-3" />
                                    <span>{{ b.profit_margin }}%</span>
                                </div>
                            </td>
                            <td class="p-4 text-center">
                                <div class="flex items-center justify-center gap-2.5">
                                    <div class="w-16 bg-slate-100 dark:bg-slate-800 rounded-full h-1.5 overflow-hidden">
                                        <div 
                                            class="h-full rounded-full transition-all"
                                            :class="[
                                                b.health_score >= 80 ? 'bg-emerald-500' :
                                                b.health_score >= 50 ? 'bg-amber-500' : 'bg-rose-500'
                                            ]"
                                            :style="`width: ${b.health_score}%`"
                                        />
                                    </div>
                                    <span 
                                        :class="[
                                            'px-2 py-0.5 rounded-md font-bold text-[10px] scale-95 border',
                                            b.health_score >= 80 ? 'bg-emerald-50 text-emerald-700 border-emerald-100 dark:bg-emerald-950/30 dark:text-emerald-400 dark:border-emerald-900/20' :
                                            b.health_score >= 50 ? 'bg-amber-50 text-amber-700 border-amber-100 dark:bg-amber-950/30 dark:text-amber-400 dark:border-amber-900/20' :
                                            'bg-rose-50 text-rose-700 border-rose-100 dark:bg-rose-950/30 dark:text-rose-455 dark:border-rose-900/20'
                                        ]"
                                    >
                                        {{ b.health_score }}
                                    </span>
                                </div>
                            </td>
                            <td class="p-4 text-center">
                                <div class="flex justify-center">
                                    <span 
                                        v-if="b.violations_count > 0"
                                        class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full font-bold bg-rose-50 text-rose-750 border border-rose-100 dark:bg-rose-950/40 dark:text-rose-400 dark:border-rose-900/30 animate-pulse"
                                    >
                                        <ShieldAlert class="size-3" />
                                        {{ b.violations_count }} lỗi
                                    </span>
                                    <span 
                                        v-else
                                        class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full font-bold bg-emerald-50 text-emerald-700 border border-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-900/30"
                                    >
                                        ✅ 0 lỗi
                                    </span>
                                </div>
                            </td>
                            <td class="p-4 pr-6 text-center">
                                <Button 
                                    size="sm" 
                                    variant="outline"
                                    class="h-7 text-[10px] font-bold rounded-lg border-teal-200 text-teal-650 hover:bg-teal-50 dark:border-teal-900/50 dark:hover:bg-teal-950/30 dark:text-teal-400 cursor-pointer"
                                    @click="selectBranchDirectly(b.id)"
                                >
                                    Xem chi tiết <ArrowRight class="size-3 ml-1" />
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>

        <!-- Quick Actions -->
        <QuickActions />

        <!-- ═══════════════════════════════════════════════════════════════ -->
        <!-- FREE PLAN LAYOUT: 2 cột đơn giản, tập trung vào cơ bản       -->
        <!-- ═══════════════════════════════════════════════════════════════ -->
        <template v-if="activePlanCode === 'free'">
            <!-- Upgrade CTA Banner -->
            <Card class="shadow-sm border border-primary/20 rounded-2xl overflow-hidden bg-gradient-to-r from-primary/5 via-transparent to-amber-500/5">
                <CardContent class="p-5 flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="h-10 w-10 bg-primary/10 text-primary rounded-xl flex items-center justify-center shrink-0">
                            <Zap class="size-5" />
                        </div>
                        <div>
                            <p class="text-sm font-bold text-foreground">Mở khoá Kho, Nhân sự, QR Order & hơn thế</p>
                            <p class="text-xs text-muted-foreground mt-0.5">Nâng cấp lên gói Cơ Bản chỉ từ 299.000đ/tháng</p>
                        </div>
                    </div>
                    <Link href="/billing/history" class="shrink-0 rounded-xl bg-gradient-to-r from-primary to-amber-500 text-white text-xs font-bold px-5 py-2.5 hover:from-primary/90 hover:to-amber-400 transition-all shadow-md shadow-primary/20">
                        Nâng cấp ngay →
                    </Link>
                </CardContent>
            </Card>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Hiệu suất Bán hàng Hôm nay -->
                <Card class="shadow-sm border border-slate-100 dark:border-slate-800 rounded-2xl overflow-hidden bg-white dark:bg-slate-900/40">
                    <CardHeader class="pb-3 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/10">
                        <CardTitle class="text-sm font-bold flex items-center gap-1.5">
                            <TrendingUp class="size-4.5 text-emerald-600 dark:text-emerald-400" />
                            Hiệu suất Bán hàng Hôm nay
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="p-5 space-y-4">
                        <div class="bg-emerald-50/40 dark:bg-emerald-950/20 rounded-xl p-3.5 border border-emerald-100/30">
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Doanh thu hôm nay</p>
                            <p class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-1">
                                {{ formatVND(props.stats?.revenue_today ?? 0) }}
                            </p>
                        </div>
                        <div class="space-y-2">
                            <div class="flex justify-between text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
                                <span>Tỉ lệ hoàn thành đơn</span>
                                <span class="text-slate-700 dark:text-slate-200 font-extrabold font-mono">{{ props.stats?.completion_rate ?? 0 }}%</span>
                            </div>
                            <div class="h-2 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                                <div class="h-full rounded-full bg-gradient-to-r from-teal-500 to-emerald-500 transition-all duration-500" :style="{ width: `${props.stats?.completion_rate ?? 0}%` }"></div>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3 pt-1">
                            <div class="p-3 border rounded-xl dark:bg-slate-950 bg-white">
                                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1"><span class="size-1.5 bg-emerald-500 rounded-full" /> Hoàn thành</p>
                                <p class="text-base font-black text-slate-800 dark:text-slate-100 mt-0.5">{{ props.stats?.orders_completed ?? 0 }} đơn</p>
                            </div>
                            <div class="p-3 border rounded-xl dark:bg-slate-950 bg-white">
                                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1"><span class="size-1.5 bg-rose-500 rounded-full" /> Đã hủy</p>
                                <p class="text-base font-black text-slate-800 dark:text-slate-100 mt-0.5">{{ props.stats?.orders_cancelled ?? 0 }} đơn</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Phím tắt Vận hành -->
                <Card class="shadow-sm border border-slate-100 dark:border-slate-800 rounded-2xl overflow-hidden bg-white dark:bg-slate-900/40">
                    <CardHeader class="pb-3 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/10">
                        <CardTitle class="text-sm font-bold flex items-center gap-1.5">
                            <Target class="size-4.5 text-indigo-600 dark:text-indigo-400" />
                            Phím tắt Vận hành
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="p-5">
                        <div class="grid grid-cols-2 gap-3">
                            <Link href="/orders" class="flex flex-col items-center justify-center p-3 rounded-xl border border-slate-100 dark:border-slate-800/80 bg-slate-50/30 hover:bg-slate-50 dark:bg-slate-900/20 dark:hover:bg-slate-900/60 hover:shadow-sm transition-all duration-200 text-center">
                                <div class="h-8 w-8 bg-violet-100 text-violet-600 dark:bg-violet-950/40 dark:text-violet-400 rounded-lg flex items-center justify-center mb-2"><PlusCircle class="size-4.5" /></div>
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-200">Tạo Đơn Hàng</span>
                            </Link>
                            <Link href="/tables" class="flex flex-col items-center justify-center p-3 rounded-xl border border-slate-100 dark:border-slate-800/80 bg-slate-50/30 hover:bg-slate-50 dark:bg-slate-900/20 dark:hover:bg-slate-900/60 hover:shadow-sm transition-all duration-200 text-center">
                                <div class="h-8 w-8 bg-teal-100 text-teal-600 dark:bg-teal-950/40 dark:text-teal-400 rounded-lg flex items-center justify-center mb-2"><Layers class="size-4.5" /></div>
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-200">Quản lý Bàn</span>
                            </Link>
                            <Link href="/products" class="flex flex-col items-center justify-center p-3 rounded-xl border border-slate-100 dark:border-slate-800/80 bg-slate-50/30 hover:bg-slate-50 dark:bg-slate-900/20 dark:hover:bg-slate-900/60 hover:shadow-sm transition-all duration-200 text-center">
                                <div class="h-8 w-8 bg-amber-100 text-amber-600 dark:bg-amber-950/40 dark:text-amber-400 rounded-lg flex items-center justify-center mb-2"><Utensils class="size-4.5" /></div>
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-200">Thêm Món</span>
                            </Link>
                            <Link href="/customers" class="flex flex-col items-center justify-center p-3 rounded-xl border border-slate-100 dark:border-slate-800/80 bg-slate-50/30 hover:bg-slate-50 dark:bg-slate-900/20 dark:hover:bg-slate-900/60 hover:shadow-sm transition-all duration-200 text-center">
                                <div class="h-8 w-8 bg-rose-100 text-rose-600 dark:bg-rose-950/40 dark:text-rose-400 rounded-lg flex items-center justify-center mb-2"><Users class="size-4.5" /></div>
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-200">Khách hàng</span>
                            </Link>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Doanh thu + Sidebar -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2">
                    <RevenueForecastChart :revenue-chart-data="props.revenueChartData" :forecast-data="null" />
                </div>
                <div>
                    <DashboardSidebar :onboarding-complete="props.onboardingComplete" :recent-orders="props.recentOrders" :stats="props.stats" :alerts="props.alerts" :user="user" />
                </div>
            </div>

            <!-- Thống kê tổng hợp -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <Card class="shadow-sm border border-slate-100 dark:border-slate-800 rounded-2xl bg-white dark:bg-slate-900/40">
                    <CardContent class="p-4 flex flex-col items-center text-center">
                        <div class="h-10 w-10 bg-violet-100 text-violet-600 dark:bg-violet-950/40 dark:text-violet-400 rounded-xl flex items-center justify-center mb-2"><Package class="size-5" /></div>
                        <p class="text-2xl font-black text-slate-800 dark:text-slate-100">{{ props.stats?.products_count ?? 0 }}</p>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mt-0.5">Món trong thực đơn</p>
                    </CardContent>
                </Card>
                <Card class="shadow-sm border border-slate-100 dark:border-slate-800 rounded-2xl bg-white dark:bg-slate-900/40">
                    <CardContent class="p-4 flex flex-col items-center text-center">
                        <div class="h-10 w-10 bg-indigo-100 text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-400 rounded-xl flex items-center justify-center mb-2"><Users class="size-5" /></div>
                        <p class="text-2xl font-black text-slate-800 dark:text-slate-100">{{ props.stats?.employees_count ?? 0 }}</p>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mt-0.5">Nhân viên</p>
                    </CardContent>
                </Card>
                <Card class="shadow-sm border border-slate-100 dark:border-slate-800 rounded-2xl bg-white dark:bg-slate-900/40">
                    <CardContent class="p-4 flex flex-col items-center text-center">
                        <div class="h-10 w-10 bg-teal-100 text-teal-600 dark:bg-teal-950/40 dark:text-teal-400 rounded-xl flex items-center justify-center mb-2"><Utensils class="size-5" /></div>
                        <p class="text-2xl font-black text-slate-800 dark:text-slate-100">{{ props.stats?.tables_count ?? 0 }}</p>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mt-0.5">Bàn ăn</p>
                    </CardContent>
                </Card>
                <Card class="shadow-sm border border-slate-100 dark:border-slate-800 rounded-2xl bg-white dark:bg-slate-900/40">
                    <CardContent class="p-4 flex flex-col items-center text-center">
                        <div class="h-10 w-10 bg-amber-100 text-amber-600 dark:bg-amber-950/40 dark:text-amber-400 rounded-xl flex items-center justify-center mb-2"><ShoppingCart class="size-5" /></div>
                        <p class="text-2xl font-black text-slate-800 dark:text-slate-100">{{ props.stats?.orders_today ?? 0 }}</p>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mt-0.5">Đơn hôm nay</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Sơ đồ Bàn ăn -->
            <Card class="shadow-sm border border-slate-100 dark:border-slate-800 rounded-2xl overflow-hidden bg-white dark:bg-slate-900/40">
                <CardHeader class="pb-3 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/10">
                    <div class="flex items-center justify-between">
                        <CardTitle class="text-sm font-bold flex items-center gap-1.5">
                            <Utensils class="size-4.5 text-teal-600 dark:text-teal-400" />
                            Sơ đồ Bàn ăn
                        </CardTitle>
                        <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-teal-50 text-teal-700 dark:bg-teal-950/40 dark:text-teal-400">{{ props.tablesData?.length ?? 0 }} Bàn</span>
                    </div>
                </CardHeader>
                <CardContent class="p-5">
                    <div v-if="props.tablesData && props.tablesData.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-3">
                        <div v-for="table in props.tablesData" :key="table.id" class="flex flex-col gap-2 p-3 rounded-xl border border-slate-100 dark:border-slate-800/80 bg-white dark:bg-slate-900/40 shadow-sm hover:shadow-md transition-all duration-300">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold text-slate-800 dark:text-slate-100">{{ table.name }}</span>
                                <span class="h-2 w-2 rounded-full" :class="{ 'bg-emerald-500': table.status === 'available', 'bg-indigo-500': table.status === 'occupied', 'bg-violet-500': table.status === 'reserved', 'bg-amber-500': table.status === 'cleaning' }"></span>
                            </div>
                            <span class="text-[9px] font-black uppercase px-2 py-0.5 rounded-md border self-start" :class="getTableStatusInfo(table.status).class">{{ getTableStatusInfo(table.status).label }}</span>
                        </div>
                    </div>
                    <div v-else class="flex flex-col items-center justify-center py-8 text-center border border-dashed border-slate-200 dark:border-slate-800 rounded-xl">
                        <Utensils class="size-8 text-slate-300 dark:text-slate-700 mb-2" />
                        <p class="text-xs text-slate-500">Chưa thiết lập sơ đồ bàn ăn</p>
                    </div>
                </CardContent>
            </Card>

            <!-- Đơn hàng gần đây (Free) -->
            <Card class="shadow-sm border border-slate-100 dark:border-slate-800 rounded-2xl overflow-hidden bg-white dark:bg-slate-900/40 pb-6">
                <CardHeader class="pb-3 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/10">
                    <div class="flex items-center justify-between">
                        <CardTitle class="text-sm font-bold flex items-center gap-1.5"><ShoppingCart class="size-4.5 text-violet-600 dark:text-violet-400" /> Đơn hàng gần đây</CardTitle>
                        <Link href="/orders" class="text-xs font-bold text-primary hover:underline flex items-center gap-0.5">Xem tất cả <ChevronRight class="size-3.5" /></Link>
                    </div>
                </CardHeader>
                <CardContent class="p-0">
                    <div v-if="props.recentOrders && props.recentOrders.length > 0" class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <thead>
                                <tr class="bg-slate-50/30 dark:bg-slate-900/20 border-b border-slate-100 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-extrabold uppercase tracking-wider text-[10px]">
                                    <th class="p-3.5 pl-6">Mã Đơn</th>
                                    <th class="p-3.5">Bàn</th>
                                    <th class="p-3.5 text-center">Trạng thái</th>
                                    <th class="p-3.5 text-right pr-6">Tổng tiền</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100/50 dark:divide-slate-800">
                                <tr v-for="order in props.recentOrders" :key="order.id" class="hover:bg-slate-50/40 dark:hover:bg-slate-900/20 transition duration-150 text-slate-700 dark:text-slate-300">
                                    <td class="p-3.5 pl-6 font-bold font-mono">#{{ order.order_number }}</td>
                                    <td class="p-3.5">{{ order.table_name ?? 'Mang về' }}</td>
                                    <td class="p-3.5 text-center">
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[9px] font-black uppercase border" :class="order.status === 'completed' ? 'bg-emerald-50 text-emerald-700 border-emerald-100 dark:bg-emerald-950/20 dark:text-emerald-400' : order.status === 'cancelled' ? 'bg-rose-50 text-rose-700 border-rose-100 dark:bg-rose-950/20 dark:text-rose-400' : 'bg-amber-50 text-amber-700 border-amber-100 dark:bg-amber-950/20 dark:text-amber-400'">
                                            {{ order.status === 'completed' ? 'Xong' : order.status === 'cancelled' ? 'Hủy' : 'Đang xử lý' }}
                                        </span>
                                    </td>
                                    <td class="p-3.5 text-right pr-6 font-black font-mono">{{ formatVND(order.total_amount) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div v-else class="flex flex-col items-center justify-center py-10 text-center text-slate-400">
                        <ShoppingCart class="size-8 text-slate-300 dark:text-slate-700 mb-2" />
                        <p class="text-xs">Chưa có đơn hàng nào hôm nay</p>
                    </div>
                </CardContent>
            </Card>
        </template>

        <!-- ═══════════════════════════════════════════════════════════════ -->
        <!-- STARTER PLAN LAYOUT: 3 cột, thêm kho/nhân sự, không AI       -->
        <!-- ═══════════════════════════════════════════════════════════════ -->
        <template v-else-if="activePlanCode === 'starter'">
            <!-- Thống kê tổng hợp Starter -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <Card class="shadow-sm border border-slate-100 dark:border-slate-800 rounded-2xl bg-white dark:bg-slate-900/40">
                    <CardContent class="p-4 flex flex-col items-center text-center">
                        <p class="text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ formatVND(props.stats?.revenue_today ?? 0) }}</p>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mt-0.5">Doanh thu hôm nay</p>
                    </CardContent>
                </Card>
                <Card class="shadow-sm border border-slate-100 dark:border-slate-800 rounded-2xl bg-white dark:bg-slate-900/40">
                    <CardContent class="p-4 flex flex-col items-center text-center">
                        <p class="text-2xl font-black text-slate-800 dark:text-slate-100">{{ props.stats?.orders_today ?? 0 }}</p>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mt-0.5">Đơn hôm nay</p>
                    </CardContent>
                </Card>
                <Card class="shadow-sm border border-slate-100 dark:border-slate-800 rounded-2xl bg-white dark:bg-slate-900/40">
                    <CardContent class="p-4 flex flex-col items-center text-center">
                        <p class="text-2xl font-black text-slate-800 dark:text-slate-100">{{ props.stats?.completion_rate ?? 0 }}%</p>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mt-0.5">Tỉ lệ hoàn thành</p>
                    </CardContent>
                </Card>
                <Card class="shadow-sm border border-slate-100 dark:border-slate-800 rounded-2xl bg-white dark:bg-slate-900/40">
                    <CardContent class="p-4 flex flex-col items-center text-center">
                        <p class="text-2xl font-black text-slate-800 dark:text-slate-100">{{ props.stats?.profit_margin_today ?? 0 }}%</p>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mt-0.5">Biên lợi nhuận</p>
                    </CardContent>
                </Card>
                <Card class="shadow-sm border border-slate-100 dark:border-slate-800 rounded-2xl bg-white dark:bg-slate-900/40">
                    <CardContent class="p-4 flex flex-col items-center text-center">
                        <p class="text-2xl font-black text-slate-800 dark:text-slate-100">{{ props.stats?.employees_count ?? 0 }}</p>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mt-0.5">Nhân viên</p>
                    </CardContent>
                </Card>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 space-y-6">
                    <RevenueForecastChart :revenue-chart-data="props.revenueChartData" :forecast-data="null" />
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <ChannelShareChart :channel-chart-data="props.channelChartData" />
                        <PeakHoursChart :peak-hours-chart-data="props.peakHoursChartData" />
                    </div>
                    <Card class="shadow-sm border border-slate-100 dark:border-slate-800 rounded-2xl overflow-hidden bg-white dark:bg-slate-900/40">
                        <CardHeader class="pb-3 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/10">
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-sm font-bold flex items-center gap-1.5"><AlertTriangle class="size-4.5 text-rose-500" /> Cảnh báo Tồn kho</CardTitle>
                                <span v-if="props.lowStockInventory?.length" class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400">{{ props.lowStockInventory.length }} Cảnh báo</span>
                            </div>
                        </CardHeader>
                        <CardContent class="p-5 space-y-3 max-h-[300px] overflow-y-auto">
                            <div v-if="props.lowStockInventory && props.lowStockInventory.length > 0" class="space-y-2.5">
                                <div v-for="item in props.lowStockInventory" :key="item.id" class="flex items-center justify-between p-2.5 rounded-xl border border-rose-100 dark:border-rose-950/20 bg-rose-50/10 dark:bg-rose-950/5">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <Package class="size-3.5 text-rose-500 shrink-0" />
                                        <span class="text-xs font-bold text-slate-800 dark:text-slate-100 truncate">{{ item.ingredient_name }}</span>
                                    </div>
                                    <span class="text-[10px] font-extrabold font-mono text-rose-600 dark:text-rose-400 shrink-0 ml-2">{{ item.quantity_on_hand }}/{{ item.reorder_level }}</span>
                                </div>
                            </div>
                            <div v-else class="flex flex-col items-center justify-center py-6 text-center">
                                <CheckCircle2 class="size-6 text-emerald-500 mb-1.5" />
                                <p class="text-xs font-bold text-slate-600 dark:text-slate-300">Kho hàng an toàn!</p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
                <div class="space-y-6">
                    <CashFlowWidget :cash-flow-summary="props.cashFlowSummary" />
                    <Card class="shadow-sm border border-slate-100 dark:border-slate-800 rounded-2xl overflow-hidden bg-white dark:bg-slate-900/40">
                        <CardHeader class="pb-3 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/10">
                            <CardTitle class="text-sm font-bold flex items-center gap-1.5"><Users class="size-4.5 text-indigo-600 dark:text-indigo-400" /> Nhân sự Hôm nay</CardTitle>
                        </CardHeader>
                        <CardContent class="p-5 space-y-2.5">
                            <div v-if="props.ownerSummary?.active_shifts?.length" class="space-y-2">
                                <div v-for="s in props.ownerSummary.active_shifts" :key="s.name + s.shift" class="flex items-center justify-between p-2 rounded-lg bg-slate-50/50 dark:bg-slate-900/40 border border-slate-100/50 dark:border-slate-800/40 text-xs">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span :class="['h-2 w-2 rounded-full shrink-0', s.status === 'checked_in' ? 'bg-emerald-500 animate-pulse' : 'bg-amber-400']" />
                                        <span class="font-bold text-slate-800 dark:text-slate-200 truncate">{{ s.name }}</span>
                                    </div>
                                    <span class="text-[9px] font-black uppercase px-2 py-0.5 rounded-md border border-slate-100 bg-white text-slate-500 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-400">{{ s.status === 'checked_in' ? 'Đã Check-in' : 'Chưa' }}</span>
                                </div>
                            </div>
                            <div v-else class="flex flex-col items-center justify-center py-6 text-center text-slate-400">
                                <Users class="size-6 text-slate-300 dark:text-slate-700 mb-1" />
                                <p class="text-xs">Chưa có nhân viên check-in</p>
                            </div>
                        </CardContent>
                    </Card>
                    <TopProductsLeaderboard :top-products-chart-data="props.topProductsChartData" />
                </div>
            </div>
            <!-- Đơn hàng gần đây (Starter) -->
            <Card v-if="props.recentOrders?.length" class="shadow-sm border border-slate-100 dark:border-slate-800 rounded-2xl overflow-hidden bg-white dark:bg-slate-900/40 pb-4">
                <CardHeader class="pb-3 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/10">
                    <div class="flex items-center justify-between">
                        <CardTitle class="text-sm font-bold flex items-center gap-1.5"><ShoppingCart class="size-4.5 text-violet-600 dark:text-violet-400" /> Đơn hàng gần đây</CardTitle>
                        <Link href="/orders" class="text-xs font-bold text-primary hover:underline flex items-center gap-0.5">Xem tất cả <ChevronRight class="size-3.5" /></Link>
                    </div>
                </CardHeader>
                <CardContent class="p-0 overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50/30 dark:bg-slate-900/20 border-b border-slate-100 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-extrabold uppercase tracking-wider text-[10px]">
                                <th class="p-3 pl-5">Mã</th><th class="p-3">Bàn</th><th class="p-3 text-center">Trạng thái</th><th class="p-3 text-right pr-5">Tổng tiền</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100/50 dark:divide-slate-800">
                            <tr v-for="order in props.recentOrders.slice(0, 8)" :key="order.id" class="hover:bg-slate-50/40 dark:hover:bg-slate-900/20 text-slate-700 dark:text-slate-300">
                                <td class="p-3 pl-5 font-bold font-mono">#{{ order.order_number }}</td>
                                <td class="p-3">{{ order.table_name ?? 'Mang về' }}</td>
                                <td class="p-3 text-center">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-[9px] font-black uppercase border" :class="order.status === 'completed' ? 'bg-emerald-50 text-emerald-700 border-emerald-100 dark:bg-emerald-950/20 dark:text-emerald-400' : order.status === 'cancelled' ? 'bg-rose-50 text-rose-700 border-rose-100 dark:bg-rose-950/20 dark:text-rose-400' : 'bg-amber-50 text-amber-700 border-amber-100 dark:bg-amber-950/20 dark:text-amber-400'">
                                        {{ order.status === 'completed' ? 'Xong' : order.status === 'cancelled' ? 'Hủy' : 'Xử lý' }}
                                    </span>
                                </td>
                                <td class="p-3 text-right pr-5 font-black font-mono">{{ formatVND(order.total_amount) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 pb-14">
                <div class="lg:col-span-2">
                    <OperationsCenter :operation-feed="props.operationFeed" :tables-data="props.tablesData" :low-stock-inventory="props.lowStockInventory" :owner-summary="props.ownerSummary" :shift-revenue="props.shiftRevenue" />
                </div>
                <div>
                    <DashboardSidebar :onboarding-complete="props.onboardingComplete" :recent-orders="props.recentOrders" :stats="props.stats" :alerts="props.alerts" :user="user" />
                </div>
            </div>
        </template>

        <!-- ═══════════════════════════════════════════════════════════════ -->
        <!-- PRO PLAN LAYOUT: 3 cột, analytics + fraud, không AI forecast -->
        <!-- ═══════════════════════════════════════════════════════════════ -->
        <template v-else-if="activePlanCode === 'pro'">
            <!-- Thống kê tổng hợp Pro -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <Card class="shadow-sm border border-slate-100 dark:border-slate-800 rounded-2xl bg-white dark:bg-slate-900/40">
                    <CardContent class="p-4 flex flex-col items-center text-center">
                        <p class="text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ formatVND(props.stats?.revenue_today ?? 0) }}</p>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mt-0.5">Doanh thu hôm nay</p>
                    </CardContent>
                </Card>
                <Card class="shadow-sm border border-slate-100 dark:border-slate-800 rounded-2xl bg-white dark:bg-slate-900/40">
                    <CardContent class="p-4 flex flex-col items-center text-center">
                        <p class="text-2xl font-black text-slate-800 dark:text-slate-100">{{ props.stats?.orders_today ?? 0 }}</p>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mt-0.5">Đơn hôm nay</p>
                    </CardContent>
                </Card>
                <Card class="shadow-sm border border-slate-100 dark:border-slate-800 rounded-2xl bg-white dark:bg-slate-900/40">
                    <CardContent class="p-4 flex flex-col items-center text-center">
                        <p class="text-2xl font-black text-slate-800 dark:text-slate-100">{{ props.stats?.completion_rate ?? 0 }}%</p>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mt-0.5">Tỉ lệ hoàn thành</p>
                    </CardContent>
                </Card>
                <Card class="shadow-sm border border-slate-100 dark:border-slate-800 rounded-2xl bg-white dark:bg-slate-900/40">
                    <CardContent class="p-4 flex flex-col items-center text-center">
                        <p class="text-2xl font-black text-slate-800 dark:text-slate-100">{{ props.stats?.profit_margin_today ?? 0 }}%</p>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mt-0.5">Biên lợi nhuận</p>
                    </CardContent>
                </Card>
                <Card class="shadow-sm border border-slate-100 dark:border-slate-800 rounded-2xl bg-white dark:bg-slate-900/40">
                    <CardContent class="p-4 flex flex-col items-center text-center">
                        <p class="text-2xl font-black text-slate-800 dark:text-slate-100">{{ props.stats?.employees_count ?? 0 }}</p>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mt-0.5">Nhân viên</p>
                    </CardContent>
                </Card>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 pb-14">
                <div class="lg:col-span-2 space-y-6">
                    <RevenueForecastChart :revenue-chart-data="props.revenueChartData" :forecast-data="null" />
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <ChannelShareChart :channel-chart-data="props.channelChartData" />
                        <PeakHoursChart :peak-hours-chart-data="props.peakHoursChartData" />
                    </div>
                    <TopProductsLeaderboard :top-products-chart-data="props.topProductsChartData" />
                    <OperationsCenter :operation-feed="props.operationFeed" :tables-data="props.tablesData" :low-stock-inventory="props.lowStockInventory" :owner-summary="props.ownerSummary" :shift-revenue="props.shiftRevenue" />
                    <!-- Đơn hàng gần đây (Pro) -->
                    <Card v-if="props.recentOrders?.length" class="shadow-sm border border-slate-100 dark:border-slate-800 rounded-2xl overflow-hidden bg-white dark:bg-slate-900/40 pb-4">
                        <CardHeader class="pb-3 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/10">
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-sm font-bold flex items-center gap-1.5"><ShoppingCart class="size-4.5 text-violet-600 dark:text-violet-400" /> Đơn hàng gần đây</CardTitle>
                                <Link href="/orders" class="text-xs font-bold text-primary hover:underline flex items-center gap-0.5">Xem tất cả <ChevronRight class="size-3.5" /></Link>
                            </div>
                        </CardHeader>
                        <CardContent class="p-0 overflow-x-auto">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="bg-slate-50/30 dark:bg-slate-900/20 border-b border-slate-100 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-extrabold uppercase tracking-wider text-[10px]">
                                        <th class="p-3 pl-5">Mã</th><th class="p-3">Bàn</th><th class="p-3 text-center">Trạng thái</th><th class="p-3 text-right pr-5">Tổng tiền</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100/50 dark:divide-slate-800">
                                    <tr v-for="order in props.recentOrders.slice(0, 8)" :key="order.id" class="hover:bg-slate-50/40 dark:hover:bg-slate-900/20 text-slate-700 dark:text-slate-300">
                                        <td class="p-3 pl-5 font-bold font-mono">#{{ order.order_number }}</td>
                                        <td class="p-3">{{ order.table_name ?? 'Mang về' }}</td>
                                        <td class="p-3 text-center">
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-[9px] font-black uppercase border" :class="order.status === 'completed' ? 'bg-emerald-50 text-emerald-700 border-emerald-100 dark:bg-emerald-950/20 dark:text-emerald-400' : order.status === 'cancelled' ? 'bg-rose-50 text-rose-700 border-rose-100 dark:bg-rose-950/20 dark:text-rose-400' : 'bg-amber-50 text-amber-700 border-amber-100 dark:bg-amber-950/20 dark:text-amber-400'">
                                                {{ order.status === 'completed' ? 'Xong' : order.status === 'cancelled' ? 'Hủy' : 'Xử lý' }}
                                            </span>
                                        </td>
                                        <td class="p-3 text-right pr-5 font-black font-mono">{{ formatVND(order.total_amount) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                </div>
                <div class="space-y-6">
                    <CashFlowWidget :cash-flow-summary="props.cashFlowSummary" />
                    <Card class="shadow-sm border border-slate-100 dark:border-slate-800 rounded-2xl overflow-hidden bg-white dark:bg-slate-900/40">
                        <CardHeader class="pb-3 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/10">
                            <CardTitle class="text-sm font-bold flex items-center gap-1.5"><Users class="size-4.5 text-indigo-600 dark:text-indigo-400" /> Nhân sự Hôm nay</CardTitle>
                        </CardHeader>
                        <CardContent class="p-5 space-y-2.5">
                            <div v-if="props.ownerSummary?.active_shifts?.length" class="space-y-2">
                                <div v-for="s in props.ownerSummary.active_shifts" :key="s.name + s.shift" class="flex items-center justify-between p-2 rounded-lg bg-slate-50/50 dark:bg-slate-900/40 border border-slate-100/50 dark:border-slate-800/40 text-xs">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span :class="['h-2 w-2 rounded-full shrink-0', s.status === 'checked_in' ? 'bg-emerald-500 animate-pulse' : 'bg-amber-400']" />
                                        <span class="font-bold text-slate-800 dark:text-slate-200 truncate">{{ s.name }}</span>
                                    </div>
                                    <span class="text-[9px] font-black uppercase px-2 py-0.5 rounded-md border border-slate-100 bg-white text-slate-500 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-400">{{ s.status === 'checked_in' ? 'Đã Check-in' : 'Chưa' }}</span>
                                </div>
                            </div>
                            <div v-else class="flex flex-col items-center justify-center py-6 text-center text-slate-400">
                                <Users class="size-6 text-slate-300 dark:text-slate-700 mb-1" />
                                <p class="text-xs">Chưa có nhân viên check-in</p>
                            </div>
                        </CardContent>
                    </Card>
                    <AIInsightsCard :forecast-data="null" :stats="props.stats" :top-products-chart-data="props.topProductsChartData" :channel-chart-data="props.channelChartData" />
                    <DashboardSidebar :onboarding-complete="props.onboardingComplete" :recent-orders="props.recentOrders" :stats="props.stats" :alerts="props.alerts" :user="user" />
                </div>
            </div>
        </template>

        <!-- ═══════════════════════════════════════════════════════════════ -->
        <!-- ENTERPRISE LAYOUT: 3 cột đầy đủ, AI + Analytics + Forecast   -->
        <!-- ═══════════════════════════════════════════════════════════════ -->
        <template v-else>
            <!-- Thống kê tổng hợp Enterprise -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <Card class="shadow-sm border border-slate-100 dark:border-slate-800 rounded-2xl bg-white dark:bg-slate-900/40">
                    <CardContent class="p-4 flex flex-col items-center text-center">
                        <p class="text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ formatVND(props.stats?.revenue_today ?? 0) }}</p>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mt-0.5">Doanh thu hôm nay</p>
                    </CardContent>
                </Card>
                <Card class="shadow-sm border border-slate-100 dark:border-slate-800 rounded-2xl bg-white dark:bg-slate-900/40">
                    <CardContent class="p-4 flex flex-col items-center text-center">
                        <p class="text-2xl font-black text-slate-800 dark:text-slate-100">{{ props.stats?.orders_today ?? 0 }}</p>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mt-0.5">Đơn hôm nay</p>
                    </CardContent>
                </Card>
                <Card class="shadow-sm border border-slate-100 dark:border-slate-800 rounded-2xl bg-white dark:bg-slate-900/40">
                    <CardContent class="p-4 flex flex-col items-center text-center">
                        <p class="text-2xl font-black text-slate-800 dark:text-slate-100">{{ props.stats?.completion_rate ?? 0 }}%</p>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mt-0.5">Tỉ lệ hoàn thành</p>
                    </CardContent>
                </Card>
                <Card class="shadow-sm border border-slate-100 dark:border-slate-800 rounded-2xl bg-white dark:bg-slate-900/40">
                    <CardContent class="p-4 flex flex-col items-center text-center">
                        <p class="text-2xl font-black text-slate-800 dark:text-slate-100">{{ props.stats?.profit_margin_today ?? 0 }}%</p>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mt-0.5">Biên lợi nhuận</p>
                    </CardContent>
                </Card>
                <Card class="shadow-sm border border-slate-100 dark:border-slate-800 rounded-2xl bg-white dark:bg-slate-900/40">
                    <CardContent class="p-4 flex flex-col items-center text-center">
                        <p class="text-2xl font-black text-slate-800 dark:text-slate-100">{{ props.stats?.employees_count ?? 0 }}</p>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mt-0.5">Nhân viên</p>
                    </CardContent>
                </Card>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 pb-14">
                <div class="lg:col-span-2 space-y-6">
                    <RevenueForecastChart :revenue-chart-data="props.revenueChartData" :forecast-data="props.forecastData" />
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <ChannelShareChart :channel-chart-data="props.channelChartData" />
                        <PeakHoursChart :peak-hours-chart-data="props.peakHoursChartData" />
                    </div>
                    <TopProductsLeaderboard :top-products-chart-data="props.topProductsChartData" />
                    <OperationsCenter :operation-feed="props.operationFeed" :tables-data="props.tablesData" :low-stock-inventory="props.lowStockInventory" :owner-summary="props.ownerSummary" :shift-revenue="props.shiftRevenue" />
                    <!-- Đơn hàng gần đây (Enterprise) -->
                    <Card v-if="props.recentOrders?.length" class="shadow-sm border border-slate-100 dark:border-slate-800 rounded-2xl overflow-hidden bg-white dark:bg-slate-900/40 pb-4">
                        <CardHeader class="pb-3 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/10">
                            <div class="flex items-center justify-between">
                                <CardTitle class="text-sm font-bold flex items-center gap-1.5"><ShoppingCart class="size-4.5 text-violet-600 dark:text-violet-400" /> Đơn hàng gần đây</CardTitle>
                                <Link href="/orders" class="text-xs font-bold text-primary hover:underline flex items-center gap-0.5">Xem tất cả <ChevronRight class="size-3.5" /></Link>
                            </div>
                        </CardHeader>
                        <CardContent class="p-0 overflow-x-auto">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="bg-slate-50/30 dark:bg-slate-900/20 border-b border-slate-100 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-extrabold uppercase tracking-wider text-[10px]">
                                        <th class="p-3 pl-5">Mã</th><th class="p-3">Bàn</th><th class="p-3">Kênh</th><th class="p-3 text-center">Trạng thái</th><th class="p-3 text-right pr-5">Tổng tiền</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100/50 dark:divide-slate-800">
                                    <tr v-for="order in props.recentOrders.slice(0, 10)" :key="order.id" class="hover:bg-slate-50/40 dark:hover:bg-slate-900/20 text-slate-700 dark:text-slate-300">
                                        <td class="p-3 pl-5 font-bold font-mono">#{{ order.order_number }}</td>
                                        <td class="p-3">{{ order.table_name ?? 'Mang về' }}</td>
                                        <td class="p-3 text-slate-500">{{ order.channel === 'dine_in' ? 'Tại bàn' : order.channel === 'takeaway' ? 'Mang về' : order.channel === 'delivery' ? 'Giao hàng' : 'QR' }}</td>
                                        <td class="p-3 text-center">
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-[9px] font-black uppercase border" :class="order.status === 'completed' ? 'bg-emerald-50 text-emerald-700 border-emerald-100 dark:bg-emerald-950/20 dark:text-emerald-400' : order.status === 'cancelled' ? 'bg-rose-50 text-rose-700 border-rose-100 dark:bg-rose-950/20 dark:text-rose-400' : 'bg-amber-50 text-amber-700 border-amber-100 dark:bg-amber-950/20 dark:text-amber-400'">
                                                {{ order.status === 'completed' ? 'Xong' : order.status === 'cancelled' ? 'Hủy' : 'Xử lý' }}
                                            </span>
                                        </td>
                                        <td class="p-3 text-right pr-5 font-black font-mono">{{ formatVND(order.total_amount) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                </div>
                <div class="space-y-6">
                    <CashFlowWidget :cash-flow-summary="props.cashFlowSummary" />
                    <AIInsightsCard :forecast-data="props.forecastData" :stats="props.stats" :top-products-chart-data="props.topProductsChartData" :channel-chart-data="props.channelChartData" />
                    <WeatherForecastCard />
                    <DashboardSidebar :onboarding-complete="props.onboardingComplete" :recent-orders="props.recentOrders" :stats="props.stats" :alerts="props.alerts" :user="user" />
                </div>
            </div>
        </template>
    </div>
</template>
