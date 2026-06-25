<script setup lang="ts">
import { Head, usePage, router } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { 
    Building2, ArrowRight, Activity, TrendingUp, ShieldAlert,
    Utensils, AlertTriangle, Users, Target, PlusCircle, Layers, CheckCircle2, XCircle, Package, ChevronRight
} from 'lucide-vue-next';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';

// Subcomponents
import AIInsightsCard from '@/components/dashboard/charts/AIInsightsCard.vue';
import ChannelShareChart from '@/components/dashboard/charts/ChannelShareChart.vue';
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

        <!-- Analytics & Charts Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left column (lg:col-span-2) -->
            <div class="lg:col-span-2 space-y-6">
                <RevenueForecastChart
                    :revenue-chart-data="props.revenueChartData"
                    :forecast-data="props.forecastData"
                />
                
                <!-- Free Plan Left sub-widgets -->
                <div v-if="activePlanCode === 'free'" class="space-y-6">
                    <!-- Real-time Tables Map (Sơ đồ Bàn ăn) -->
                    <Card class="shadow-sm border border-slate-100 dark:border-slate-800 rounded-2xl overflow-hidden bg-white dark:bg-slate-900/40">
                        <CardHeader class="pb-3 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/10">
                            <div class="flex items-center justify-between">
                                <div>
                                    <CardTitle class="text-sm font-bold flex items-center gap-1.5">
                                        <Utensils class="size-4.5 text-teal-600 dark:text-teal-400" />
                                        Sơ đồ Bàn ăn (Thời gian thực)
                                    </CardTitle>
                                    <CardDescription class="text-[11px] mt-0.5">Trạng thái phục vụ tại các khu vực bàn ăn</CardDescription>
                                </div>
                                <span class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-teal-50 text-teal-700 dark:bg-teal-950/40 dark:text-teal-400">
                                    {{ props.tablesData?.length ?? 0 }} Bàn ăn
                                </span>
                            </div>
                        </CardHeader>
                        <CardContent class="p-5">
                            <div v-if="props.tablesData && props.tablesData.length > 0" class="grid grid-cols-2 sm:grid-cols-3 gap-3.5">
                                <div 
                                    v-for="table in props.tablesData" 
                                    :key="table.id"
                                    class="group relative flex flex-col gap-3 p-3.5 rounded-2xl border border-slate-100 dark:border-slate-800/80 bg-white dark:bg-slate-900/40 shadow-sm hover:shadow-md hover:border-slate-200 dark:hover:border-slate-700/80 transition-all duration-300 cursor-pointer"
                                >
                                    <div class="flex items-start justify-between gap-1.5">
                                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider truncate">{{ table.area }}</span>
                                        <span class="h-2.5 w-2.5 rounded-full shrink-0"
                                              :class="{
                                                  'bg-emerald-500 animate-pulse ring-4 ring-emerald-500/10': table.status === 'available',
                                                  'bg-indigo-500 ring-4 ring-indigo-500/10': table.status === 'occupied',
                                                  'bg-violet-500 ring-4 ring-violet-500/10': table.status === 'reserved',
                                                  'bg-amber-500 ring-4 ring-amber-500/10': table.status === 'cleaning'
                                              }"
                                        ></span>
                                    </div>

                                    <div>
                                        <h4 class="text-sm font-bold text-slate-800 dark:text-slate-100 group-hover:text-teal-500 dark:group-hover:text-teal-400 transition-colors flex items-center gap-2">
                                            <Utensils class="size-4 text-slate-400" />
                                            {{ table.name }}
                                        </h4>
                                    </div>

                                    <div class="mt-auto flex items-center justify-between gap-2 pt-2 border-t border-slate-100 dark:border-slate-800/40">
                                        <span class="flex items-center gap-1 text-[10px] font-bold text-slate-400">
                                            <Users class="size-3" />
                                            {{ table.capacity }} chỗ
                                        </span>
                                        <span class="text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-md border"
                                              :class="getTableStatusInfo(table.status).class"
                                        >
                                            {{ getTableStatusInfo(table.status).label }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="flex flex-col items-center justify-center py-10 text-center border border-dashed border-slate-200 dark:border-slate-800 rounded-xl bg-slate-50/50">
                                <Utensils class="size-10 text-slate-300 dark:text-slate-700 mb-2" />
                                <p class="text-xs text-slate-500">Chưa thiết lập sơ đồ bàn ăn</p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Basic Plan Left sub-widgets -->
                <div v-else-if="activePlanCode === 'basic'" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <ChannelShareChart
                        :channel-chart-data="props.channelChartData"
                    />
                    
                    <!-- Cảnh báo Tồn kho (Low Stock Inventory) -->
                    <Card class="shadow-sm border border-slate-100 dark:border-slate-800 rounded-2xl overflow-hidden bg-white dark:bg-slate-900/40">
                        <CardHeader class="pb-3 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/10">
                            <div class="flex items-center justify-between">
                                <div>
                                    <CardTitle class="text-sm font-bold flex items-center gap-1.5">
                                        <AlertTriangle class="size-4.5 text-rose-500" />
                                        Cảnh báo Tồn kho
                                    </CardTitle>
                                    <CardDescription class="text-[11px] mt-0.5">Danh sách nguyên liệu chạm mốc báo động</CardDescription>
                                </div>
                                <span v-if="props.lowStockInventory?.length" class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase tracking-wider bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-455">
                                    {{ props.lowStockInventory.length }} Cảnh báo
                                </span>
                            </div>
                        </CardHeader>
                        <CardContent class="p-5 space-y-3.5 max-h-[350px] overflow-y-auto">
                            <div v-if="props.lowStockInventory && props.lowStockInventory.length > 0" class="space-y-3">
                                <div 
                                    v-for="item in props.lowStockInventory" 
                                    :key="item.id"
                                    class="group flex flex-col gap-2 p-3 rounded-xl border border-rose-100 dark:border-rose-950/20 bg-rose-50/10 dark:bg-rose-950/5 hover:shadow-sm transition-all duration-300"
                                >
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <h4 class="text-xs font-bold text-slate-800 dark:text-slate-100 truncate flex items-center gap-1.5">
                                                <Package class="size-3.5 text-rose-500" />
                                                {{ item.ingredient_name }}
                                            </h4>
                                            <p class="text-[10px] font-semibold text-slate-400 dark:text-slate-500 mt-0.5">Tối thiểu: {{ item.min_stock_level }} {{ item.unit_name }}</p>
                                        </div>
                                        <Link href="/inventory" class="shrink-0 text-[10px] font-bold text-rose-600 dark:text-rose-400 hover:text-rose-700 hover:underline flex items-center gap-0.5">
                                            Nhập kho <ChevronRight class="size-3" />
                                        </Link>
                                    </div>
                                    <div class="mt-0.5 space-y-1">
                                        <div class="flex justify-between text-[9px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
                                            <span>Tồn hiện tại</span>
                                            <span class="text-rose-600 dark:text-rose-400 font-extrabold font-mono">{{ item.quantity_on_hand }} / {{ item.reorder_level }} {{ item.unit_name }}</span>
                                        </div>
                                        <div class="h-1.5 rounded-full bg-slate-100 dark:bg-slate-850 overflow-hidden">
                                            <div class="h-full rounded-full bg-gradient-to-r from-rose-500 to-red-650 transition-all duration-500"
                                                 :style="{ width: `${Math.min((item.quantity_on_hand / Math.max(item.reorder_level, 1)) * 100, 100)}%` }"
                                            ></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="flex flex-col items-center justify-center py-10 text-center">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600 dark:bg-emerald-950/20 dark:text-emerald-400 mb-2">
                                    <CheckCircle2 class="size-5" />
                                </div>
                                <h4 class="text-xs font-bold text-slate-800 dark:text-slate-100">Kho hàng an toàn!</h4>
                                <p class="text-[10px] text-slate-400 mt-0.5">Không có nguyên liệu nào sắp hết hàng</p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Pro & Enterprise Left sub-widgets -->
                <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <ChannelShareChart
                        :channel-chart-data="props.channelChartData"
                    />
                    <TopProductsLeaderboard
                        :top-products-chart-data="props.topProductsChartData"
                    />
                </div>
            </div>

            <!-- Right column (lg:col-span-1) -->
            <div>
                <!-- Free Plan Right widgets -->
                <div v-if="activePlanCode === 'free'" class="space-y-6">
                    <!-- Phím tắt Vận hành (Shortcuts) -->
                    <Card class="shadow-sm border border-slate-100 dark:border-slate-800 rounded-2xl overflow-hidden bg-white dark:bg-slate-900/40">
                        <CardHeader class="pb-3 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/10">
                            <CardTitle class="text-sm font-bold flex items-center gap-1.5">
                                <Target class="size-4.5 text-indigo-600 dark:text-indigo-400" />
                                Phím tắt Vận hành
                            </CardTitle>
                            <CardDescription class="text-[11px] mt-0.5">Các thao tác vận hành nhanh tại cửa hàng</CardDescription>
                        </CardHeader>
                        <CardContent class="p-5">
                            <div class="grid grid-cols-2 gap-3">
                                <Link 
                                    href="/orders" 
                                    class="flex flex-col items-center justify-center p-3 rounded-xl border border-slate-100 dark:border-slate-800/80 bg-slate-50/30 hover:bg-slate-50 dark:bg-slate-900/20 dark:hover:bg-slate-900/60 hover:shadow-sm hover:scale-102 transition-all duration-200 text-center"
                                >
                                    <div class="h-8 w-8 bg-violet-100 text-violet-600 dark:bg-violet-950/40 dark:text-violet-400 rounded-lg flex items-center justify-center mb-2">
                                        <PlusCircle class="size-4.5" />
                                    </div>
                                    <span class="text-xs font-bold text-slate-800 dark:text-slate-200">Tạo Đơn Hàng</span>
                                    <span class="text-[9px] text-slate-400 dark:text-slate-500 mt-0.5">Xử lý order mới</span>
                                </Link>
                                <Link 
                                    href="/tables" 
                                    class="flex flex-col items-center justify-center p-3 rounded-xl border border-slate-100 dark:border-slate-800/80 bg-slate-50/30 hover:bg-slate-50 dark:bg-slate-900/20 dark:hover:bg-slate-900/60 hover:shadow-sm hover:scale-102 transition-all duration-200 text-center"
                                >
                                    <div class="h-8 w-8 bg-teal-100 text-teal-600 dark:bg-teal-950/40 dark:text-teal-400 rounded-lg flex items-center justify-center mb-2">
                                        <Layers class="size-4.5" />
                                    </div>
                                    <span class="text-xs font-bold text-slate-800 dark:text-slate-200">Quản lý Bàn</span>
                                    <span class="text-[9px] text-slate-400 dark:text-slate-500 mt-0.5">Sắp xếp sơ đồ</span>
                                </Link>
                                <Link 
                                    href="/products" 
                                    class="flex flex-col items-center justify-center p-3 rounded-xl border border-slate-100 dark:border-slate-800/80 bg-slate-50/30 hover:bg-slate-50 dark:bg-slate-900/20 dark:hover:bg-slate-900/60 hover:shadow-sm hover:scale-102 transition-all duration-200 text-center"
                                >
                                    <div class="h-8 w-8 bg-amber-100 text-amber-600 dark:bg-amber-950/40 dark:text-amber-400 rounded-lg flex items-center justify-center mb-2">
                                        <Utensils class="size-4.5" />
                                    </div>
                                    <span class="text-xs font-bold text-slate-800 dark:text-slate-200">Thêm Món</span>
                                    <span class="text-[9px] text-slate-400 dark:text-slate-500 mt-0.5">Cập nhật thực đơn</span>
                                </Link>
                                <Link 
                                    href="/reports" 
                                    class="flex flex-col items-center justify-center p-3 rounded-xl border border-slate-100 dark:border-slate-800/80 bg-slate-50/30 hover:bg-slate-50 dark:bg-slate-900/20 dark:hover:bg-slate-900/60 hover:shadow-sm hover:scale-102 transition-all duration-200 text-center"
                                >
                                    <div class="h-8 w-8 bg-rose-100 text-rose-600 dark:bg-rose-950/40 dark:text-rose-455 rounded-lg flex items-center justify-center mb-2">
                                        <BarChart3 class="size-4.5" />
                                    </div>
                                    <span class="text-xs font-bold text-slate-800 dark:text-slate-200">Báo cáo Cơ bản</span>
                                    <span class="text-[9px] text-slate-400 dark:text-slate-500 mt-0.5">Doanh thu & Đơn</span>
                                </Link>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Hiệu suất Bán hàng Hôm nay (Sales Performance) -->
                    <Card class="shadow-sm border border-slate-100 dark:border-slate-800 rounded-2xl overflow-hidden bg-white dark:bg-slate-900/40">
                        <CardHeader class="pb-3 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/10">
                            <CardTitle class="text-sm font-bold flex items-center gap-1.5">
                                <TrendingUp class="size-4.5 text-emerald-600 dark:text-emerald-400" />
                                Hiệu suất Bán hàng Hôm nay
                            </CardTitle>
                            <CardDescription class="text-[11px] mt-0.5">Tổng hợp kết quả bán hàng và tỉ lệ hoàn thành</CardDescription>
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
                                    <div 
                                        class="h-full rounded-full bg-gradient-to-r from-teal-500 to-emerald-500 transition-all duration-500"
                                        :style="{ width: `${props.stats?.completion_rate ?? 0}%` }"
                                    ></div>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-3 pt-1">
                                <div class="p-3 border rounded-xl dark:bg-slate-950 bg-white">
                                    <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1">
                                        <span class="size-1.5 bg-emerald-500 rounded-full" /> Hoàn thành
                                    </p>
                                    <p class="text-base font-black text-slate-800 dark:text-slate-100 mt-0.5">
                                        {{ props.stats?.orders_completed ?? 0 }} đơn
                                    </p>
                                </div>
                                <div class="p-3 border rounded-xl dark:bg-slate-950 bg-white">
                                    <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1">
                                        <span class="size-1.5 bg-rose-500 rounded-full" /> Đã hủy
                                    </p>
                                    <p class="text-base font-black text-slate-800 dark:text-slate-100 mt-0.5">
                                        {{ props.stats?.orders_cancelled ?? 0 }} đơn
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <WeatherForecastCard />
                </div>

                <!-- Basic Plan Right widgets -->
                <div v-else-if="activePlanCode === 'basic'" class="space-y-6">
                    <CashFlowWidget :cash-flow-summary="props.cashFlowSummary" />
                    
                    <!-- Nhân sự làm việc Hôm nay (Active Shifts) -->
                    <Card class="shadow-sm border border-slate-100 dark:border-slate-800 rounded-2xl overflow-hidden bg-white dark:bg-slate-900/40">
                        <CardHeader class="pb-3 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/10">
                            <CardTitle class="text-sm font-bold flex items-center gap-1.5">
                                <Users class="size-4.5 text-indigo-650 dark:text-indigo-400" />
                                Nhân sự làm việc Hôm nay
                            </CardTitle>
                            <CardDescription class="text-[11px] mt-0.5">Danh sách ca trực và trạng thái chấm công</CardDescription>
                        </CardHeader>
                        <CardContent class="p-5 space-y-3">
                            <div v-if="props.ownerSummary?.active_shifts && props.ownerSummary.active_shifts.length > 0" class="space-y-2.5">
                                <div 
                                    v-for="s in props.ownerSummary.active_shifts" 
                                    :key="s.name + s.shift"
                                    class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50/50 dark:bg-slate-900/40 border border-slate-100/50 dark:border-slate-800/40 text-xs"
                                >
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        <span :class="['h-2 w-2 rounded-full shrink-0', s.status === 'checked_in' ? 'bg-emerald-500 animate-pulse' : 'bg-amber-400']" />
                                        <div class="min-w-0">
                                            <p class="font-bold text-slate-800 dark:text-slate-200 truncate leading-snug">{{ s.name }}</p>
                                            <p class="text-[10px] text-slate-400 mt-0.5">{{ s.shift }}</p>
                                        </div>
                                    </div>
                                    <span class="text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-md border border-slate-100 bg-white text-slate-500 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-400">
                                        {{ s.status === 'checked_in' ? 'Đã Check-in' : 'Chưa Check-in' }}
                                    </span>
                                </div>
                            </div>
                            <div v-else class="flex flex-col items-center justify-center py-8 text-center text-slate-400">
                                <Users class="size-8 text-slate-300 dark:text-slate-700 mb-2" />
                                <p class="text-xs">Chưa có nhân viên nào check-in hôm nay</p>
                            </div>
                        </CardContent>
                    </Card>

                    <WeatherForecastCard />
                </div>

                <!-- Pro & Enterprise Right widgets -->
                <div v-else class="space-y-6">
                    <CashFlowWidget :cash-flow-summary="props.cashFlowSummary" />
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
            <div class="lg:col-span-2 space-y-6">
                <OperationsCenter
                    :operation-feed="props.operationFeed"
                    :tables-data="props.tablesData"
                    :low-stock-inventory="props.lowStockInventory"
                    :owner-summary="props.ownerSummary"
                    :shift-revenue="props.shiftRevenue"
                />

                <!-- Recent Orders Table for Free/Basic plans to balance the layout -->
                <Card v-if="activePlanCode === 'free' || activePlanCode === 'basic'" class="shadow-sm border border-slate-100 dark:border-slate-800 rounded-2xl overflow-hidden bg-white dark:bg-slate-900/40">
                    <CardHeader class="pb-3 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/10">
                        <div class="flex items-center justify-between">
                            <div>
                                <CardTitle class="text-sm font-bold flex items-center gap-1.5">
                                    <ShoppingCart class="size-4.5 text-violet-600 dark:text-violet-400" />
                                    Đơn hàng vừa nhận
                                </CardTitle>
                                <CardDescription class="text-[11px] mt-0.5">Danh sách các đơn hàng mới nhất hôm nay</CardDescription>
                            </div>
                            <Link href="/orders" class="text-xs font-bold text-violet-650 dark:text-violet-400 hover:text-violet-750 hover:underline flex items-center gap-0.5">
                                Xem tất cả <ChevronRight class="size-3.5" />
                            </Link>
                        </div>
                    </CardHeader>
                    <CardContent class="p-0">
                        <div v-if="props.recentOrders && props.recentOrders.length > 0" class="overflow-x-auto">
                            <table class="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr class="bg-slate-50/30 dark:bg-slate-900/20 border-b border-slate-100 dark:border-slate-850 text-slate-500 dark:text-slate-400 font-extrabold uppercase tracking-wider text-[10px]">
                                        <th class="p-3.5 pl-6">Mã Đơn</th>
                                        <th class="p-3.5">Bàn / Khu vực</th>
                                        <th class="p-3.5">Kênh bán</th>
                                        <th class="p-3.5 text-center">Trạng thái</th>
                                        <th class="p-3.5 text-right">Tổng tiền</th>
                                        <th class="p-3.5 pr-6 text-center">Thời gian</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100/50 dark:divide-slate-850">
                                    <tr 
                                        v-for="order in props.recentOrders" 
                                        :key="order.id"
                                        class="hover:bg-slate-50/40 dark:hover:bg-slate-900/20 transition duration-150 text-slate-700 dark:text-slate-350"
                                    >
                                        <td class="p-3.5 pl-6 font-bold font-mono text-slate-900 dark:text-slate-100">
                                            #{{ order.order_number }}
                                        </td>
                                        <td class="p-3.5 font-semibold">
                                            {{ order.table_name ?? 'Mang về / Giao hàng' }}
                                        </td>
                                        <td class="p-3.5 font-medium text-slate-500">
                                            {{ order.channel === 'dine_in' ? 'Tại bàn' : order.channel === 'takeaway' ? 'Mang về' : order.channel === 'delivery' ? 'Giao hàng' : 'QR Code' }}
                                        </td>
                                        <td class="p-3.5 text-center">
                                            <span 
                                                class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[9px] font-black uppercase tracking-wider border"
                                                :class="[
                                                    order.status === 'completed' ? 'bg-emerald-50 text-emerald-700 border-emerald-100 dark:bg-emerald-950/20 dark:text-emerald-400 dark:border-emerald-900/30' :
                                                    order.status === 'cancelled' ? 'bg-rose-50 text-rose-700 border-rose-100 dark:bg-rose-950/20 dark:text-rose-455 dark:border-rose-900/30' :
                                                    'bg-amber-50 text-amber-700 border-amber-100 dark:bg-amber-950/20 dark:text-amber-400 dark:border-emerald-900/30'
                                                ]"
                                            >
                                                {{ order.status === 'completed' ? 'Hoàn thành' : order.status === 'cancelled' ? 'Đã hủy' : 'Đang xử lý' }}
                                            </span>
                                        </td>
                                        <td class="p-3.5 text-right font-black font-mono text-slate-900 dark:text-slate-100">
                                            {{ formatVND(order.total_amount) }}
                                        </td>
                                        <td class="p-3.5 pr-6 text-center font-bold text-slate-400 font-mono">
                                            {{ order.created_at }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="flex flex-col items-center justify-center py-12 text-center text-slate-400">
                            <ShoppingCart class="size-8 text-slate-300 dark:text-slate-700 mb-2" />
                            <p class="text-xs">Chưa có đơn hàng nào hôm nay</p>
                        </div>
                    </CardContent>
                </Card>
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
