<script setup lang="ts">
import { Head, usePage, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { 
    Building2, ArrowRight, Activity, TrendingUp, ShieldAlert 
} from 'lucide-vue-next';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';

// Subcomponents
import AIInsightsCard from '@/components/dashboard/charts/AIInsightsCard.vue';
import ChannelShareChart from '@/components/dashboard/charts/ChannelShareChart.vue';
import RevenueForecastChart from '@/components/dashboard/charts/RevenueForecastChart.vue';
import TopProductsLeaderboard from '@/components/dashboard/charts/TopProductsLeaderboard.vue';
import WeatherForecastCard from '@/components/dashboard/charts/WeatherForecastCard.vue';
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
    channelChartData?: ChannelShare[];
    topProductsChartData?: TopProductStat[];
    forecastData?: ForecastData | null;
    healthScore?: number | null;
    shiftRevenue?: ShiftRevenueRow[];
    ownerSummary?: OwnerSummary | null;
    branchId?: number | null;
    branches?: { id: number; name: string }[];
    branchComparisons?: BranchComparison[];
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
    <div class="mx-auto max-w-7xl px-4 py-8 lg:px-8 space-y-6">
        <!-- Branch selection dropdown (Only if owner has multiple branches) -->
        <div v-if="props.branches && props.branches.length > 1" class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-100 dark:border-slate-800 pb-4">
            <div>
                <h2 class="text-lg font-extrabold tracking-tight text-slate-800 dark:text-slate-100 flex items-center gap-2">
                    <span class="size-2 bg-teal-500 rounded-full animate-pulse" />
                    {{ branchId ? 'Báo cáo chi nhánh' : 'Trung tâm chỉ huy chuỗi' }}
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    {{ branchId ? 'Giám sát vận hành và hiệu suất của chi nhánh hiện tại.' : 'Tổng quan hiệu suất và sức khỏe hoạt động của toàn chuỗi.' }}
                </p>
            </div>
            
            <div class="flex items-center gap-2">
                <span class="text-xs font-bold text-slate-500">Chi nhánh:</span>
                <select 
                    id="branch-selector"
                    v-model="selectedBranch"
                    class="min-w-[200px] text-xs font-semibold rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 px-3.5 py-2 focus:ring-2 focus:ring-teal-500/25 focus:border-teal-500 outline-none shadow-sm dark:text-slate-350 cursor-pointer"
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
        <Card v-if="!branchId && props.branchComparisons && props.branchComparisons.length > 0" class="shadow-sm rounded-2xl overflow-hidden border border-slate-100 dark:border-slate-800">
            <CardHeader class="pb-3 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/10">
                <div>
                    <CardTitle class="text-sm font-bold flex items-center gap-2">
                        <Building2 class="size-4 text-teal-600 dark:text-teal-400" />
                        Hiệu suất chi tiết từng chi nhánh
                    </CardTitle>
                    <CardDescription class="text-xs">
                        So sánh doanh thu hôm nay, biên lợi nhuận, sức khỏe vận hành và tổng lỗi sai phạm trong 30 ngày qua.
                    </CardDescription>
                </div>
            </CardHeader>
            
            <CardContent class="p-0 overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="bg-slate-50/30 dark:bg-slate-900/5 border-b border-slate-100 dark:border-slate-800 text-slate-500 dark:text-slate-400 font-bold">
                            <th class="p-4 pl-6">Chi nhánh</th>
                            <th class="p-4 text-right">Doanh thu hôm nay</th>
                            <th class="p-4 text-right">Biên lợi nhuận</th>
                            <th class="p-4 text-center">Sức khỏe vận hành</th>
                            <th class="p-4 text-center">Sai phạm (30 ngày)</th>
                            <th class="p-4 pr-6 text-center">Hành động</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        <tr 
                            v-for="b in props.branchComparisons" 
                            :key="b.id"
                            class="hover:bg-slate-50/40 dark:hover:bg-slate-900/30 transition duration-150 text-slate-700 dark:text-slate-350"
                        >
                            <td class="p-4 pl-6 font-bold flex items-center gap-2">
                                <span class="size-2 rounded-full bg-teal-500 shrink-0" />
                                {{ b.name }}
                            </td>
                            <td class="p-4 text-right font-mono font-bold text-teal-600 dark:text-teal-400">
                                {{ formatVND(b.revenue) }}
                            </td>
                            <td class="p-4 text-right font-semibold">
                                <div class="inline-flex items-center gap-1">
                                    <TrendingUp class="size-3.5 text-emerald-500" />
                                    <span>{{ b.profit_margin }}%</span>
                                </div>
                            </td>
                            <td class="p-4 text-center">
                                <div class="flex items-center justify-center gap-2">
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
                                            'px-1.5 py-0.5 rounded-md font-bold text-[10px] scale-95',
                                            b.health_score >= 80 ? 'bg-emerald-55 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-450' :
                                            b.health_score >= 50 ? 'bg-amber-55 text-amber-700 dark:bg-amber-950/30 dark:text-amber-450' :
                                            'bg-rose-55 text-rose-700 dark:bg-rose-950/30 dark:text-rose-455'
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
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full font-bold bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-400 animate-pulse"
                                    >
                                        <ShieldAlert class="size-3" />
                                        {{ b.violations_count }} lỗi
                                    </span>
                                    <span 
                                        v-else
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full font-bold bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400"
                                    >
                                        ✅ 0 lỗi
                                    </span>
                                </div>
                            </td>
                            <td class="p-4 pr-6 text-center">
                                <Button 
                                    size="sm" 
                                    variant="outline"
                                    class="h-7 text-[10px] rounded-lg border-teal-200 text-teal-600 hover:bg-teal-50 dark:border-teal-800 dark:hover:bg-teal-950/30 dark:text-teal-400"
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

        <!-- Analytics & Charts Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <RevenueForecastChart
                    :revenue-chart-data="props.revenueChartData"
                    :forecast-data="props.forecastData"
                />
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <ChannelShareChart
                        :channel-chart-data="props.channelChartData"
                    />
                    <TopProductsLeaderboard
                        :top-products-chart-data="props.topProductsChartData"
                    />
                </div>
            </div>
            <div>
                <div class="space-y-6">
                    <AIInsightsCard
                        :forecast-data="props.forecastData"
                        :stats="props.stats"
                        :top-products-chart-data="props.topProductsChartData"
                        :channel-chart-data="props.channelChartData"
                    />
                    <WeatherForecastCard />
                </div>
            </div>
        </div>

        <!-- Operations Monitoring & Sidebar -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 pb-14">
            <div class="lg:col-span-2">
                <OperationsCenter
                    :operation-feed="props.operationFeed"
                    :tables-data="props.tablesData"
                    :low-stock-inventory="props.lowStockInventory"
                    :owner-summary="props.ownerSummary"
                    :shift-revenue="props.shiftRevenue"
                />
            </div>
            <div>
                <DashboardSidebar
                    :onboarding-complete="props.onboardingComplete"
                    :recent-orders="props.recentOrders"
                    :stats="props.stats"
                    :alerts="props.alerts"
                    :user="user"
                />
            </div>
        </div>
    </div>
</template>
