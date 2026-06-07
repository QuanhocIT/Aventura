<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppTopbarLayout from '@/layouts/AppTopbarLayout.vue';

// Subcomponents
import DashboardHeader from '@/components/dashboard/DashboardHeader.vue';
import DashboardKPIs from '@/components/dashboard/DashboardKPIs.vue';
import QuickActions from '@/components/dashboard/QuickActions.vue';
import RevenueForecastChart from '@/components/dashboard/charts/RevenueForecastChart.vue';
import ChannelShareChart from '@/components/dashboard/charts/ChannelShareChart.vue';
import TopProductsLeaderboard from '@/components/dashboard/charts/TopProductsLeaderboard.vue';
import AIInsightsCard from '@/components/dashboard/charts/AIInsightsCard.vue';
import OperationsCenter from '@/components/dashboard/operations/OperationsCenter.vue';
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
}>();

const page = usePage();
const user = computed(() => (page.props.auth as any)?.user ?? null);
const tenant = computed(() => (page.props as any).tenant ?? null);
const plan = computed(() => tenant.value?.plan ?? null);
const quota = computed(() => tenant.value?.quota_summary ?? null);

const availablePlans = computed(() => (page.props as any).available_plans ?? []);
const roles = computed(() => (page.props as any).roles ?? []);
</script>

<template>
    <AppTopbarLayout>
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
            <!-- Today's KPI Row & Health Score -->
            <DashboardKPIs
                :stats="props.stats"
                :health-score="props.healthScore"
            />

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
                    <AIInsightsCard
                        :forecast-data="props.forecastData"
                        :stats="props.stats"
                        :top-products-chart-data="props.topProductsChartData"
                        :channel-chart-data="props.channelChartData"
                    />
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
    </AppTopbarLayout>
</template>
