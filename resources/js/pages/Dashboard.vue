<script setup lang="ts">
import { Head, Link, usePage, router, Deferred } from '@inertiajs/vue3';
import {
    Building2,
    ArrowRight,
    Activity,
    TrendingUp,
    ShieldAlert,
    Zap,
    Utensils,
    AlertTriangle,
    Users,
    Target,
    PlusCircle,
    Layers,
    CheckCircle2,
    XCircle,
    Package,
    ChevronRight,
    ShoppingCart,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

// Subcomponents
import AIInsightsCard from '@/components/dashboard/charts/AIInsightsCard.vue';
import CashFlowWidget from '@/components/dashboard/charts/CashFlowWidget.vue';
import ChannelShareChart from '@/components/dashboard/charts/ChannelShareChart.vue';
import PeakHoursChart from '@/components/dashboard/charts/PeakHoursChart.vue';
import RevenueForecastChart from '@/components/dashboard/charts/RevenueForecastChart.vue';
import TopProductsLeaderboard from '@/components/dashboard/charts/TopProductsLeaderboard.vue';
import WeatherForecastCard from '@/components/dashboard/charts/WeatherForecastCard.vue';
import DashboardHeader from '@/components/dashboard/DashboardHeader.vue';
import DashboardKPIs from '@/components/dashboard/DashboardKPIs.vue';
import OperationsCenter from '@/components/dashboard/operations/OperationsCenter.vue';
import QuickActions from '@/components/dashboard/QuickActions.vue';
import DashboardSidebar from '@/components/dashboard/sidebar/DashboardSidebar.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

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

const availablePlans = computed(
    () => (page.props as any).available_plans ?? [],
);
const roles = computed(() => (page.props as any).roles ?? []);

const selectedBranch = ref(props.branchId || 'all');

watch(selectedBranch, (newVal) => {
    router.post(
        '/branch/switch',
        newVal === 'all' ? { scope: 'all' } : { branch_id: Number(newVal) },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
});

watch(
    () => props.branchId,
    (newVal) => {
        selectedBranch.value = newVal || 'all';
    },
);

const selectBranchDirectly = (id: number) => {
    selectedBranch.value = id;
};

const formatVND = (value: number) => {
    const num = Number(value);

    if (isNaN(num) || num === undefined || num === null) {
return '0 ₫';
}

    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(num);
};

const activePlanCode = computed(() => plan.value?.code ?? 'free');

const tableStatusMap = {
    available: {
        label: 'Bàn trống',
        class: 'bg-emerald-50 text-emerald-700 border-emerald-100 dark:bg-emerald-950/20 dark:text-emerald-400 dark:border-emerald-900/30',
    },
    occupied: {
        label: 'Có khách',
        class: 'bg-indigo-50 text-indigo-700 border-indigo-100 dark:bg-indigo-950/20 dark:text-indigo-400 dark:border-indigo-900/30',
    },
    reserved: {
        label: 'Đặt trước',
        class: 'bg-violet-50 text-violet-700 border-violet-100 dark:bg-violet-950/20 dark:text-violet-400 dark:border-violet-900/30',
    },
    cleaning: {
        label: 'Dọn dẹp',
        class: 'bg-amber-50 text-amber-700 border-amber-100 dark:bg-amber-950/20 dark:text-amber-400 dark:border-amber-900/30',
    },
};

function getTableStatusInfo(status: string) {
    return (
        tableStatusMap[status as keyof typeof tableStatusMap] ?? {
            label: status,
            class: 'bg-muted text-muted-foreground border-border',
        }
    );
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
    <div class="relative mx-auto max-w-7xl space-y-5 px-4 py-4 lg:px-6">
        <!-- Branch selection dropdown (Only if owner has multiple branches) -->
        <div
            v-if="props.branches && props.branches.length > 1"
            class="flex flex-col gap-3 border-b border-slate-100 pb-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800"
        >
            <div>
                <h2
                    class="text-slate-850 flex items-center gap-2.5 text-xl font-black tracking-tight dark:text-slate-100"
                >
                    <span
                        class="size-2.5 animate-pulse rounded-full bg-teal-500 shadow-sm shadow-teal-500"
                    />
                    {{
                        branchId
                            ? 'Báo cáo chi nhánh'
                            : 'Trung tâm chỉ huy chuỗi'
                    }}
                </h2>
                <p
                    class="mt-1 text-xs font-medium text-slate-500 dark:text-slate-400"
                >
                    {{
                        branchId
                            ? 'Giám sát vận hành và hiệu suất của chi nhánh hiện tại.'
                            : 'Tổng quan hiệu suất và sức khỏe hoạt động của toàn chuỗi.'
                    }}
                </p>
            </div>

            <div class="flex items-center gap-2.5">
                <span
                    class="text-xs font-bold tracking-wider text-slate-500 uppercase"
                    >Chi nhánh:</span
                >
                <select
                    id="branch-selector"
                    v-model="selectedBranch"
                    class="min-w-[220px] cursor-pointer rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold shadow-sm transition-all outline-none focus:border-teal-500 focus:ring-4 focus:ring-teal-500/10 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-300"
                >
                    <option value="all">🌐 Toàn chuỗi (Hợp nhất)</option>
                    <option
                        v-for="b in props.branches"
                        :key="b.id"
                        :value="b.id"
                    >
                        📍 {{ b.name }}
                    </option>
                </select>
            </div>
        </div>

        <!-- Today's KPI Row & Health Score -->
        <DashboardKPIs
            :stats="props.stats"
            :health-score="props.healthScore"
            class="animate-enter stagger-1"
        />

        <!-- Consolidated Branch Comparisons (only in consolidated view) -->
        <Card
            v-if="
                !branchId &&
                props.branchComparisons &&
                props.branchComparisons.length > 0
            "
            class="animate-enter stagger-2 overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-md backdrop-blur-md dark:border-slate-800/80 dark:bg-slate-900/40"
        >
            <CardHeader
                class="dark:border-slate-850 border-b border-slate-100 bg-slate-50/30 pb-4 dark:bg-slate-900/10"
            >
                <div class="flex items-center gap-2.5">
                    <div
                        class="rounded-xl bg-teal-500/10 p-2 text-teal-600 dark:text-teal-400"
                    >
                        <Building2 class="size-4.5" />
                    </div>
                    <div>
                        <CardTitle
                            class="text-slate-850 text-sm font-bold dark:text-slate-100"
                        >
                            Hiệu suất chi tiết từng chi nhánh
                        </CardTitle>
                        <CardDescription class="mt-0.5 text-xs">
                            So sánh doanh thu hôm nay, biên lợi nhuận, sức khỏe
                            vận hành và tổng lỗi sai phạm trong 30 ngày qua.
                        </CardDescription>
                    </div>
                </div>
            </CardHeader>

            <CardContent class="overflow-x-auto p-0">
                <table class="w-full border-collapse text-left text-xs">
                    <thead>
                        <tr
                            class="dark:border-slate-850 border-b border-slate-100 bg-slate-50/50 text-[10px] font-extrabold tracking-wider text-slate-500 uppercase dark:bg-slate-900/20 dark:text-slate-400"
                        >
                            <th class="p-4 pl-6">Chi nhánh</th>
                            <th class="p-4 text-right">Doanh thu hôm nay</th>
                            <th class="p-4 text-right">Biên lợi nhuận</th>
                            <th class="p-4 text-center">Sức khỏe vận hành</th>
                            <th class="p-4 text-center">Sai phạm (30 ngày)</th>
                            <th class="p-4 pr-6 text-center">Hành động</th>
                        </tr>
                    </thead>
                    <tbody
                        class="dark:divide-slate-850 divide-y divide-slate-100"
                    >
                        <tr
                            v-for="b in props.branchComparisons"
                            :key="b.id"
                            class="hover:bg-slate-55/40 text-slate-700 transition duration-150 dark:text-slate-300 dark:hover:bg-slate-900/40"
                        >
                            <td
                                class="flex items-center gap-2 p-4 pl-6 font-bold"
                            >
                                <span
                                    class="size-2 shrink-0 rounded-full bg-teal-500 shadow-sm"
                                />
                                {{ b.name }}
                            </td>
                            <td
                                class="p-4 text-right font-mono font-black text-teal-600 dark:text-teal-400"
                            >
                                {{ formatVND(b.revenue) }}
                            </td>
                            <td class="p-4 text-right font-bold">
                                <div
                                    class="inline-flex items-center gap-1 rounded-md bg-emerald-500/10 px-2 py-0.5 text-[11px] text-emerald-600 dark:text-emerald-400"
                                >
                                    <TrendingUp class="size-3" />
                                    <span>{{ b.profit_margin }}%</span>
                                </div>
                            </td>
                            <td class="p-4 text-center">
                                <div
                                    class="flex items-center justify-center gap-2.5"
                                >
                                    <div
                                        class="h-1.5 w-16 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                                    >
                                        <div
                                            class="h-full rounded-full transition-all"
                                            :class="[
                                                b.health_score >= 80
                                                    ? 'bg-emerald-500'
                                                    : b.health_score >= 50
                                                      ? 'bg-amber-500'
                                                      : 'bg-rose-500',
                                            ]"
                                            :style="`width: ${b.health_score}%`"
                                        />
                                    </div>
                                    <span
                                        :class="[
                                            'scale-95 rounded-md border px-2 py-0.5 text-[10px] font-bold',
                                            b.health_score >= 80
                                                ? 'border-emerald-100 bg-emerald-50 text-emerald-700 dark:border-emerald-900/20 dark:bg-emerald-950/30 dark:text-emerald-400'
                                                : b.health_score >= 50
                                                  ? 'border-amber-100 bg-amber-50 text-amber-700 dark:border-amber-900/20 dark:bg-amber-950/30 dark:text-amber-400'
                                                  : 'dark:text-rose-455 border-rose-100 bg-rose-50 text-rose-700 dark:border-rose-900/20 dark:bg-rose-950/30',
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
                                        class="text-rose-750 inline-flex animate-pulse items-center gap-1 rounded-full border border-rose-100 bg-rose-50 px-2.5 py-0.5 font-bold dark:border-rose-900/30 dark:bg-rose-950/40 dark:text-rose-400"
                                    >
                                        <ShieldAlert class="size-3" />
                                        {{ b.violations_count }} lỗi
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1 rounded-full border border-emerald-100 bg-emerald-50 px-2.5 py-0.5 font-bold text-emerald-700 dark:border-emerald-900/30 dark:bg-emerald-950/40 dark:text-emerald-400"
                                    >
                                        ✅ 0 lỗi
                                    </span>
                                </div>
                            </td>
                            <td class="p-4 pr-6 text-center">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    class="text-teal-650 h-7 cursor-pointer rounded-lg border-teal-200 text-[10px] font-bold hover:bg-teal-50 dark:border-teal-900/50 dark:text-teal-400 dark:hover:bg-teal-950/30"
                                    @click="selectBranchDirectly(b.id)"
                                >
                                    Xem chi tiết
                                    <ArrowRight class="ml-1 size-3" />
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </CardContent>
        </Card>

        <!-- Quick Actions -->
        <QuickActions class="animate-enter stagger-3" />

        <!-- ═══════════════════════════════════════════════════════════════ -->
        <!-- FREE PLAN LAYOUT: 2 cột đơn giản, tập trung vào cơ bản       -->
        <!-- ═══════════════════════════════════════════════════════════════ -->
        <template v-if="activePlanCode === 'free'">
            <!-- Upgrade CTA Banner -->
            <Card
                class="animate-enter stagger-4 overflow-hidden rounded-2xl border border-primary/20 bg-gradient-to-r from-primary/5 via-transparent to-amber-500/5 shadow-sm"
            >
                <CardContent
                    class="flex flex-col items-center justify-between gap-4 p-5 sm:flex-row"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                        >
                            <Zap class="size-5" />
                        </div>
                        <div>
                            <p class="text-sm font-bold text-foreground">
                                Mở khoá Kho, Nhân sự, QR Order & hơn thế
                            </p>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                Nâng cấp lên gói Cơ Bản chỉ từ 299.000đ/tháng
                            </p>
                        </div>
                    </div>
                    <Link
                        href="/billing/history"
                        prefetch
                        class="shrink-0 rounded-xl bg-gradient-to-r from-primary to-amber-500 px-5 py-2.5 text-xs font-bold text-white shadow-md shadow-primary/20 transition-all hover:from-primary/90 hover:to-amber-400"
                    >
                        Nâng cấp ngay →
                    </Link>
                </CardContent>
            </Card>

            <div
                class="animate-enter stagger-5 grid grid-cols-1 gap-5 lg:grid-cols-2"
            >
                <!-- Hiệu suất Bán hàng Hôm nay -->
                <Card
                    class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/40"
                >
                    <CardHeader
                        class="border-b border-slate-100 bg-slate-50/50 pb-3 dark:border-slate-800 dark:bg-slate-900/10"
                    >
                        <CardTitle
                            class="flex items-center gap-1.5 text-sm font-bold"
                        >
                            <TrendingUp
                                class="size-4.5 text-emerald-600 dark:text-emerald-400"
                            />
                            Hiệu suất Bán hàng Hôm nay
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4 p-5">
                        <div
                            class="rounded-xl border border-emerald-100/30 bg-emerald-50/40 p-3.5 dark:bg-emerald-950/20"
                        >
                            <p
                                class="text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                            >
                                Doanh thu hôm nay
                            </p>
                            <p
                                class="mt-1 text-2xl font-black text-emerald-600 dark:text-emerald-400"
                            >
                                {{ formatVND(props.stats?.revenue_today ?? 0) }}
                            </p>
                        </div>
                        <div class="space-y-2">
                            <div
                                class="flex justify-between text-[10px] font-bold tracking-wider text-slate-400 uppercase dark:text-slate-500"
                            >
                                <span>Tỉ lệ hoàn thành đơn</span>
                                <span
                                    class="font-mono font-extrabold text-slate-700 dark:text-slate-200"
                                    >{{
                                        props.stats?.completion_rate ?? 0
                                    }}%</span
                                >
                            </div>
                            <div
                                class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                            >
                                <div
                                    class="h-full rounded-full bg-gradient-to-r from-teal-500 to-emerald-500 transition-all duration-500"
                                    :style="{
                                        width: `${props.stats?.completion_rate ?? 0}%`,
                                    }"
                                ></div>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3 pt-1">
                            <div
                                class="rounded-xl border bg-white p-3 dark:bg-slate-950"
                            >
                                <p
                                    class="flex items-center gap-1 text-[9px] font-bold tracking-wider text-slate-400 uppercase"
                                >
                                    <span
                                        class="size-1.5 rounded-full bg-emerald-500"
                                    />
                                    Hoàn thành
                                </p>
                                <p
                                    class="mt-0.5 text-base font-black text-slate-800 dark:text-slate-100"
                                >
                                    {{ props.stats?.orders_completed ?? 0 }} đơn
                                </p>
                            </div>
                            <div
                                class="rounded-xl border bg-white p-3 dark:bg-slate-950"
                            >
                                <p
                                    class="flex items-center gap-1 text-[9px] font-bold tracking-wider text-slate-400 uppercase"
                                >
                                    <span
                                        class="size-1.5 rounded-full bg-rose-500"
                                    />
                                    Đã hủy
                                </p>
                                <p
                                    class="mt-0.5 text-base font-black text-slate-800 dark:text-slate-100"
                                >
                                    {{ props.stats?.orders_cancelled ?? 0 }} đơn
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Phím tắt Vận hành -->
                <Card
                    class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/40"
                >
                    <CardHeader
                        class="border-b border-slate-100 bg-slate-50/50 pb-3 dark:border-slate-800 dark:bg-slate-900/10"
                    >
                        <CardTitle
                            class="flex items-center gap-1.5 text-sm font-bold"
                        >
                            <Target
                                class="size-4.5 text-indigo-600 dark:text-indigo-400"
                            />
                            Phím tắt Vận hành
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="p-5">
                        <div class="grid grid-cols-2 gap-3">
                            <Link
                                href="/orders"
                                prefetch
                                class="flex flex-col items-center justify-center rounded-xl border border-slate-100 bg-slate-50/30 p-3 text-center transition-all duration-200 hover:bg-slate-50 hover:shadow-sm dark:border-slate-800/80 dark:bg-slate-900/20 dark:hover:bg-slate-900/60"
                            >
                                <div
                                    class="mb-2 flex h-8 w-8 items-center justify-center rounded-lg bg-violet-100 text-violet-600 dark:bg-violet-950/40 dark:text-violet-400"
                                >
                                    <PlusCircle class="size-4.5" />
                                </div>
                                <span
                                    class="text-xs font-bold text-slate-800 dark:text-slate-200"
                                    >Tạo Đơn Hàng</span
                                >
                            </Link>
                            <Link
                                href="/tables"
                                prefetch
                                class="flex flex-col items-center justify-center rounded-xl border border-slate-100 bg-slate-50/30 p-3 text-center transition-all duration-200 hover:bg-slate-50 hover:shadow-sm dark:border-slate-800/80 dark:bg-slate-900/20 dark:hover:bg-slate-900/60"
                            >
                                <div
                                    class="mb-2 flex h-8 w-8 items-center justify-center rounded-lg bg-teal-100 text-teal-600 dark:bg-teal-950/40 dark:text-teal-400"
                                >
                                    <Layers class="size-4.5" />
                                </div>
                                <span
                                    class="text-xs font-bold text-slate-800 dark:text-slate-200"
                                    >Quản lý Bàn</span
                                >
                            </Link>
                            <Link
                                href="/products"
                                prefetch
                                class="flex flex-col items-center justify-center rounded-xl border border-slate-100 bg-slate-50/30 p-3 text-center transition-all duration-200 hover:bg-slate-50 hover:shadow-sm dark:border-slate-800/80 dark:bg-slate-900/20 dark:hover:bg-slate-900/60"
                            >
                                <div
                                    class="mb-2 flex h-8 w-8 items-center justify-center rounded-lg bg-amber-100 text-amber-600 dark:bg-amber-950/40 dark:text-amber-400"
                                >
                                    <Utensils class="size-4.5" />
                                </div>
                                <span
                                    class="text-xs font-bold text-slate-800 dark:text-slate-200"
                                    >Thêm Món</span
                                >
                            </Link>
                            <Link
                                href="/customers"
                                prefetch
                                class="flex flex-col items-center justify-center rounded-xl border border-slate-100 bg-slate-50/30 p-3 text-center transition-all duration-200 hover:bg-slate-50 hover:shadow-sm dark:border-slate-800/80 dark:bg-slate-900/20 dark:hover:bg-slate-900/60"
                            >
                                <div
                                    class="mb-2 flex h-8 w-8 items-center justify-center rounded-lg bg-rose-100 text-rose-600 dark:bg-rose-950/40 dark:text-rose-400"
                                >
                                    <Users class="size-4.5" />
                                </div>
                                <span
                                    class="text-xs font-bold text-slate-800 dark:text-slate-200"
                                    >Khách hàng</span
                                >
                            </Link>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
                <div class="lg:col-span-2">
                    <Deferred data="revenueChartData">
                        <template #fallback>
                            <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/40">
                                <div class="flex items-center justify-between mb-6">
                                    <div class="h-5 w-40 animate-pulse rounded bg-slate-200 dark:bg-slate-800" />
                                    <div class="h-8 w-24 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800/80" />
                                </div>
                                <div class="h-[250px] w-full flex items-end gap-3 pt-4">
                                    <div v-for="n in 7" :key="n" class="w-full bg-slate-100 dark:bg-slate-800/60 rounded-t-lg animate-pulse" :style="{ height: [40, 70, 50, 90, 60, 85, 30][n-1] + '%' }" />
                                </div>
                            </div>
                        </template>
                        <RevenueForecastChart
                            :revenue-chart-data="props.revenueChartData"
                            :forecast-data="null"
                        />
                    </Deferred>
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

            <!-- Thống kê tổng hợp -->
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                <Card
                    class="rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/40"
                >
                    <CardContent
                        class="flex flex-col items-center p-4 text-center"
                    >
                        <div
                            class="mb-2 flex h-10 w-10 items-center justify-center rounded-xl bg-violet-100 text-violet-600 dark:bg-violet-950/40 dark:text-violet-400"
                        >
                            <Package class="size-5" />
                        </div>
                        <p
                            class="text-2xl font-black text-slate-800 dark:text-slate-100"
                        >
                            {{ props.stats?.products_count ?? 0 }}
                        </p>
                        <p
                            class="mt-0.5 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                        >
                            Món trong thực đơn
                        </p>
                    </CardContent>
                </Card>
                <Card
                    class="rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/40"
                >
                    <CardContent
                        class="flex flex-col items-center p-4 text-center"
                    >
                        <div
                            class="mb-2 flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-100 text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-400"
                        >
                            <Users class="size-5" />
                        </div>
                        <p
                            class="text-2xl font-black text-slate-800 dark:text-slate-100"
                        >
                            {{ props.stats?.employees_count ?? 0 }}
                        </p>
                        <p
                            class="mt-0.5 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                        >
                            Nhân viên
                        </p>
                    </CardContent>
                </Card>
                <Card
                    class="rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/40"
                >
                    <CardContent
                        class="flex flex-col items-center p-4 text-center"
                    >
                        <div
                            class="mb-2 flex h-10 w-10 items-center justify-center rounded-xl bg-teal-100 text-teal-600 dark:bg-teal-950/40 dark:text-teal-400"
                        >
                            <Utensils class="size-5" />
                        </div>
                        <p
                            class="text-2xl font-black text-slate-800 dark:text-slate-100"
                        >
                            {{ props.stats?.tables_count ?? 0 }}
                        </p>
                        <p
                            class="mt-0.5 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                        >
                            Bàn ăn
                        </p>
                    </CardContent>
                </Card>
                <Card
                    class="rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/40"
                >
                    <CardContent
                        class="flex flex-col items-center p-4 text-center"
                    >
                        <div
                            class="mb-2 flex h-10 w-10 items-center justify-center rounded-xl bg-amber-100 text-amber-600 dark:bg-amber-950/40 dark:text-amber-400"
                        >
                            <ShoppingCart class="size-5" />
                        </div>
                        <p
                            class="text-2xl font-black text-slate-800 dark:text-slate-100"
                        >
                            {{ props.stats?.orders_today ?? 0 }}
                        </p>
                        <p
                            class="mt-0.5 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                        >
                            Đơn hôm nay
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Sơ đồ Bàn ăn -->
            <Card
                class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/40"
            >
                <CardHeader
                    class="border-b border-slate-100 bg-slate-50/50 pb-3 dark:border-slate-800 dark:bg-slate-900/10"
                >
                    <div class="flex items-center justify-between">
                        <CardTitle
                            class="flex items-center gap-1.5 text-sm font-bold"
                        >
                            <Utensils
                                class="size-4.5 text-teal-600 dark:text-teal-400"
                            />
                            Sơ đồ Bàn ăn
                        </CardTitle>
                        <span
                            class="rounded-full bg-teal-50 px-2 py-0.5 text-[9px] font-black tracking-wider text-teal-700 uppercase dark:bg-teal-950/40 dark:text-teal-400"
                            >{{ props.tablesData?.length ?? 0 }} Bàn</span
                        >
                    </div>
                </CardHeader>
                <CardContent class="p-5">
                    <div
                        v-if="props.tablesData && props.tablesData.length > 0"
                        class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6"
                    >
                        <div
                            v-for="table in props.tablesData"
                            :key="table.id"
                            class="flex flex-col gap-2 rounded-xl border border-slate-100 bg-white p-3 shadow-sm transition-all duration-300 hover:shadow-md dark:border-slate-800/80 dark:bg-slate-900/40"
                        >
                            <div class="flex items-center justify-between">
                                <span
                                    class="text-xs font-bold text-slate-800 dark:text-slate-100"
                                    >{{ table.name }}</span
                                >
                                <span
                                    class="h-2 w-2 rounded-full"
                                    :class="{
                                        'bg-emerald-500':
                                            table.status === 'available',
                                        'bg-indigo-500':
                                            table.status === 'occupied',
                                        'bg-violet-500':
                                            table.status === 'reserved',
                                        'bg-amber-500':
                                            table.status === 'cleaning',
                                    }"
                                ></span>
                            </div>
                            <span
                                class="self-start rounded-md border px-2 py-0.5 text-[9px] font-black uppercase"
                                :class="getTableStatusInfo(table.status).class"
                                >{{
                                    getTableStatusInfo(table.status).label
                                }}</span
                            >
                        </div>
                    </div>
                    <div
                        v-else
                        class="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 py-8 text-center dark:border-slate-800"
                    >
                        <Utensils
                            class="mb-2 size-8 text-slate-300 dark:text-slate-700"
                        />
                        <p class="text-xs text-slate-500">
                            Chưa thiết lập sơ đồ bàn ăn
                        </p>
                    </div>
                </CardContent>
            </Card>

            <!-- Đơn hàng gần đây (Free) -->
            <Card
                class="overflow-hidden rounded-2xl border border-slate-100 bg-white pb-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/40"
            >
                <CardHeader
                    class="border-b border-slate-100 bg-slate-50/50 pb-3 dark:border-slate-800 dark:bg-slate-900/10"
                >
                    <div class="flex items-center justify-between">
                        <CardTitle
                            class="flex items-center gap-1.5 text-sm font-bold"
                            ><ShoppingCart
                                class="size-4.5 text-violet-600 dark:text-violet-400"
                            />
                            Đơn hàng gần đây</CardTitle
                        >
                        <Link
                            href="/orders"
                            prefetch
                            class="flex items-center gap-0.5 text-xs font-bold text-primary hover:underline"
                            >Xem tất cả <ChevronRight class="size-3.5"
                        /></Link>
                    </div>
                </CardHeader>
                <CardContent class="p-0">
                    <div
                        v-if="
                            props.recentOrders && props.recentOrders.length > 0
                        "
                        class="overflow-x-auto"
                    >
                        <table class="w-full border-collapse text-left text-xs">
                            <thead>
                                <tr
                                    class="border-b border-slate-100 bg-slate-50/30 text-[10px] font-extrabold tracking-wider text-slate-500 uppercase dark:border-slate-800 dark:bg-slate-900/20 dark:text-slate-400"
                                >
                                    <th class="p-3.5 pl-6">Mã Đơn</th>
                                    <th class="p-3.5">Bàn</th>
                                    <th class="p-3.5 text-center">
                                        Trạng thái
                                    </th>
                                    <th class="p-3.5 pr-6 text-right">
                                        Tổng tiền
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-slate-100/50 dark:divide-slate-800"
                            >
                                <tr
                                    v-for="order in props.recentOrders"
                                    :key="order.id"
                                    class="text-slate-700 transition duration-150 hover:bg-slate-50/40 dark:text-slate-300 dark:hover:bg-slate-900/20"
                                >
                                    <td class="p-3.5 pl-6 font-mono font-bold">
                                        #{{ order.order_number }}
                                    </td>
                                    <td class="p-3.5">
                                        {{ order.table_name ?? 'Mang về' }}
                                    </td>
                                    <td class="p-3.5 text-center">
                                        <span
                                            class="inline-flex items-center rounded-full border px-2 py-0.5 text-[9px] font-black uppercase"
                                            :class="
                                                order.status === 'completed'
                                                    ? 'border-emerald-100 bg-emerald-50 text-emerald-700 dark:bg-emerald-950/20 dark:text-emerald-400'
                                                    : order.status ===
                                                        'cancelled'
                                                      ? 'border-rose-100 bg-rose-50 text-rose-700 dark:bg-rose-950/20 dark:text-rose-400'
                                                      : 'border-amber-100 bg-amber-50 text-amber-700 dark:bg-amber-950/20 dark:text-amber-400'
                                            "
                                        >
                                            {{
                                                order.status === 'completed'
                                                    ? 'Xong'
                                                    : order.status ===
                                                        'cancelled'
                                                      ? 'Hủy'
                                                      : 'Đang xử lý'
                                            }}
                                        </span>
                                    </td>
                                    <td
                                        class="p-3.5 pr-6 text-right font-mono font-black"
                                    >
                                        {{ formatVND(order.total_amount) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div
                        v-else
                        class="flex flex-col items-center justify-center py-10 text-center text-slate-400"
                    >
                        <ShoppingCart
                            class="mb-2 size-8 text-slate-300 dark:text-slate-700"
                        />
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
            <div
                class="animate-enter stagger-4 grid grid-cols-2 gap-4 md:grid-cols-5"
            >
                <Card
                    class="rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/40"
                >
                    <CardContent
                        class="flex flex-col items-center p-4 text-center"
                    >
                        <p
                            class="text-2xl font-black text-emerald-600 dark:text-emerald-400"
                        >
                            {{ formatVND(props.stats?.revenue_today ?? 0) }}
                        </p>
                        <p
                            class="mt-0.5 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                        >
                            Doanh thu hôm nay
                        </p>
                    </CardContent>
                </Card>
                <Card
                    class="rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/40"
                >
                    <CardContent
                        class="flex flex-col items-center p-4 text-center"
                    >
                        <p
                            class="text-2xl font-black text-slate-800 dark:text-slate-100"
                        >
                            {{ props.stats?.orders_today ?? 0 }}
                        </p>
                        <p
                            class="mt-0.5 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                        >
                            Đơn hôm nay
                        </p>
                    </CardContent>
                </Card>
                <Card
                    class="rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/40"
                >
                    <CardContent
                        class="flex flex-col items-center p-4 text-center"
                    >
                        <p
                            class="text-2xl font-black text-slate-800 dark:text-slate-100"
                        >
                            {{ props.stats?.completion_rate ?? 0 }}%
                        </p>
                        <p
                            class="mt-0.5 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                        >
                            Tỉ lệ hoàn thành
                        </p>
                    </CardContent>
                </Card>
                <Card
                    class="rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/40"
                >
                    <CardContent
                        class="flex flex-col items-center p-4 text-center"
                    >
                        <p
                            class="text-2xl font-black text-slate-800 dark:text-slate-100"
                        >
                            {{ props.stats?.profit_margin_today ?? 0 }}%
                        </p>
                        <p
                            class="mt-0.5 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                        >
                            Biên lợi nhuận
                        </p>
                    </CardContent>
                </Card>
                <Card
                    class="rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/40"
                >
                    <CardContent
                        class="flex flex-col items-center p-4 text-center"
                    >
                        <p
                            class="text-2xl font-black text-slate-800 dark:text-slate-100"
                        >
                            {{ props.stats?.employees_count ?? 0 }}
                        </p>
                        <p
                            class="mt-0.5 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                        >
                            Nhân viên
                        </p>
                    </CardContent>
                </Card>
            </div>

            <div
                class="animate-enter stagger-5 grid grid-cols-1 gap-5 lg:grid-cols-3"
            >
                <div class="space-y-6 lg:col-span-2">
                    <Deferred data="revenueChartData">
                        <template #fallback>
                            <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/40">
                                <div class="flex items-center justify-between mb-6">
                                    <div class="h-5 w-40 animate-pulse rounded bg-slate-200 dark:bg-slate-800" />
                                    <div class="h-8 w-24 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800/80" />
                                </div>
                                <div class="h-[250px] w-full flex items-end gap-3 pt-4">
                                    <div v-for="n in 7" :key="n" class="w-full bg-slate-100 dark:bg-slate-800/60 rounded-t-lg animate-pulse" :style="{ height: [40, 70, 50, 90, 60, 85, 30][n-1] + '%' }" />
                                </div>
                            </div>
                        </template>
                        <RevenueForecastChart
                            :revenue-chart-data="props.revenueChartData"
                            :forecast-data="null"
                        />
                    </Deferred>
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <Deferred data="channelChartData">
                            <template #fallback>
                                <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/40">
                                    <div class="flex items-center justify-between mb-4">
                                        <div class="h-4 w-32 animate-pulse rounded bg-slate-200 dark:bg-slate-800" />
                                    </div>
                                    <div class="flex items-center justify-center h-[200px]">
                                        <div class="h-28 w-28 rounded-full border-[12px] border-slate-100 dark:border-slate-800/80 animate-pulse flex items-center justify-center">
                                            <div class="h-10 w-10 rounded-full bg-slate-50 dark:bg-slate-900" />
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <ChannelShareChart
                                :channel-chart-data="props.channelChartData"
                            />
                        </Deferred>
                        <Deferred data="peakHoursChartData">
                            <template #fallback>
                                <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/40">
                                    <div class="flex items-center justify-between mb-4">
                                        <div class="h-4 w-32 animate-pulse rounded bg-slate-200 dark:bg-slate-800" />
                                    </div>
                                    <div class="h-[200px] w-full flex items-end gap-1.5 pt-4">
                                        <div v-for="n in 12" :key="n" class="w-full bg-slate-100 dark:bg-slate-800/60 rounded-t animate-pulse" :style="{ height: [20, 35, 60, 80, 50, 40, 75, 90, 85, 45, 30, 15][n-1] + '%' }" />
                                    </div>
                                </div>
                            </template>
                            <PeakHoursChart
                                :peak-hours-chart-data="props.peakHoursChartData"
                            />
                        </Deferred>
                    </div>
                    <Card
                        class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/40"
                    >
                        <CardHeader
                            class="border-b border-slate-100 bg-slate-50/50 pb-3 dark:border-slate-800 dark:bg-slate-900/10"
                        >
                            <div class="flex items-center justify-between">
                                <CardTitle
                                    class="flex items-center gap-1.5 text-sm font-bold"
                                    ><AlertTriangle
                                        class="size-4.5 text-rose-500"
                                    />
                                    Cảnh báo Tồn kho</CardTitle
                                >
                                <span
                                    v-if="props.lowStockInventory?.length"
                                    class="rounded-full bg-rose-50 px-2 py-0.5 text-[9px] font-black tracking-wider text-rose-700 uppercase dark:bg-rose-950/40 dark:text-rose-400"
                                    >{{ props.lowStockInventory.length }} Cảnh
                                    báo</span
                                >
                            </div>
                        </CardHeader>
                        <CardContent
                            class="max-h-[300px] space-y-3 overflow-y-auto p-5"
                        >
                            <div
                                v-if="
                                    props.lowStockInventory &&
                                    props.lowStockInventory.length > 0
                                "
                                class="space-y-2.5"
                            >
                                <div
                                    v-for="item in props.lowStockInventory"
                                    :key="item.id"
                                    class="flex items-center justify-between rounded-xl border border-rose-100 bg-rose-50/10 p-2.5 dark:border-rose-950/20 dark:bg-rose-950/5"
                                >
                                    <div
                                        class="flex min-w-0 items-center gap-2"
                                    >
                                        <Package
                                            class="size-3.5 shrink-0 text-rose-500"
                                        />
                                        <span
                                            class="truncate text-xs font-bold text-slate-800 dark:text-slate-100"
                                            >{{ item.ingredient_name }}</span
                                        >
                                    </div>
                                    <span
                                        class="ml-2 shrink-0 font-mono text-[10px] font-extrabold text-rose-600 dark:text-rose-400"
                                        >{{ item.quantity_on_hand }}/{{
                                            item.reorder_level
                                        }}</span
                                    >
                                </div>
                            </div>
                            <div
                                v-else
                                class="flex flex-col items-center justify-center py-6 text-center"
                            >
                                <CheckCircle2
                                    class="mb-1.5 size-6 text-emerald-500"
                                />
                                <p
                                    class="text-xs font-bold text-slate-600 dark:text-slate-300"
                                >
                                    Kho hàng an toàn!
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
                <div class="space-y-6">
                    <CashFlowWidget
                        :cash-flow-summary="props.cashFlowSummary"
                    />
                    <Card
                        class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/40"
                    >
                        <CardHeader
                            class="border-b border-slate-100 bg-slate-50/50 pb-3 dark:border-slate-800 dark:bg-slate-900/10"
                        >
                            <CardTitle
                                class="flex items-center gap-1.5 text-sm font-bold"
                                ><Users
                                    class="size-4.5 text-indigo-600 dark:text-indigo-400"
                                />
                                Nhân sự Hôm nay</CardTitle
                            >
                        </CardHeader>
                        <CardContent class="space-y-2.5 p-5">
                            <div
                                v-if="props.ownerSummary?.active_shifts?.length"
                                class="space-y-2"
                            >
                                <div
                                    v-for="s in props.ownerSummary
                                        .active_shifts"
                                    :key="s.name + s.shift"
                                    class="flex items-center justify-between rounded-lg border border-slate-100/50 bg-slate-50/50 p-2 text-xs dark:border-slate-800/40 dark:bg-slate-900/40"
                                >
                                    <div
                                        class="flex min-w-0 items-center gap-2"
                                    >
                                        <span
                                            :class="[
                                                'h-2 w-2 shrink-0 rounded-full',
                                                s.status === 'checked_in'
                                                    ? 'animate-pulse bg-emerald-500'
                                                    : 'bg-amber-400',
                                            ]"
                                        />
                                        <span
                                            class="truncate font-bold text-slate-800 dark:text-slate-200"
                                            >{{ s.name }}</span
                                        >
                                    </div>
                                    <span
                                        class="rounded-md border border-slate-100 bg-white px-2 py-0.5 text-[9px] font-black text-slate-500 uppercase dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-400"
                                        >{{
                                            s.status === 'checked_in'
                                                ? 'Đã Check-in'
                                                : 'Chưa'
                                        }}</span
                                    >
                                </div>
                            </div>
                            <div
                                v-else
                                class="flex flex-col items-center justify-center py-6 text-center text-slate-400"
                            >
                                <Users
                                    class="mb-1 size-6 text-slate-300 dark:text-slate-700"
                                />
                                <p class="text-xs">
                                    Chưa có nhân viên check-in
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                    <TopProductsLeaderboard
                        :top-products-chart-data="props.topProductsChartData"
                    />
                </div>
            </div>
            <!-- Đơn hàng gần đây (Starter) -->
            <Card
                v-if="props.recentOrders?.length"
                class="overflow-hidden rounded-2xl border border-slate-100 bg-white pb-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/40"
            >
                <CardHeader
                    class="border-b border-slate-100 bg-slate-50/50 pb-3 dark:border-slate-800 dark:bg-slate-900/10"
                >
                    <div class="flex items-center justify-between">
                        <CardTitle
                            class="flex items-center gap-1.5 text-sm font-bold"
                            ><ShoppingCart
                                class="size-4.5 text-violet-600 dark:text-violet-400"
                            />
                            Đơn hàng gần đây</CardTitle
                        >
                        <Link
                            href="/orders"
                            prefetch
                            class="flex items-center gap-0.5 text-xs font-bold text-primary hover:underline"
                            >Xem tất cả <ChevronRight class="size-3.5"
                        /></Link>
                    </div>
                </CardHeader>
                <CardContent class="overflow-x-auto p-0">
                    <table class="w-full border-collapse text-left text-xs">
                        <thead>
                            <tr
                                class="border-b border-slate-100 bg-slate-50/30 text-[10px] font-extrabold tracking-wider text-slate-500 uppercase dark:border-slate-800 dark:bg-slate-900/20 dark:text-slate-400"
                            >
                                <th class="p-3 pl-5">Mã</th>
                                <th class="p-3">Bàn</th>
                                <th class="p-3 text-center">Trạng thái</th>
                                <th class="p-3 pr-5 text-right">Tổng tiền</th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-100/50 dark:divide-slate-800"
                        >
                            <tr
                                v-for="order in props.recentOrders.slice(0, 8)"
                                :key="order.id"
                                class="text-slate-700 hover:bg-slate-50/40 dark:text-slate-300 dark:hover:bg-slate-900/20"
                            >
                                <td class="p-3 pl-5 font-mono font-bold">
                                    #{{ order.order_number }}
                                </td>
                                <td class="p-3">
                                    {{ order.table_name ?? 'Mang về' }}
                                </td>
                                <td class="p-3 text-center">
                                    <span
                                        class="inline-flex rounded-full border px-2 py-0.5 text-[9px] font-black uppercase"
                                        :class="
                                            order.status === 'completed'
                                                ? 'border-emerald-100 bg-emerald-50 text-emerald-700 dark:bg-emerald-950/20 dark:text-emerald-400'
                                                : order.status === 'cancelled'
                                                  ? 'border-rose-100 bg-rose-50 text-rose-700 dark:bg-rose-950/20 dark:text-rose-400'
                                                  : 'border-amber-100 bg-amber-50 text-amber-700 dark:bg-amber-950/20 dark:text-amber-400'
                                        "
                                    >
                                        {{
                                            order.status === 'completed'
                                                ? 'Xong'
                                                : order.status === 'cancelled'
                                                  ? 'Hủy'
                                                  : 'Xử lý'
                                        }}
                                    </span>
                                </td>
                                <td
                                    class="p-3 pr-5 text-right font-mono font-black"
                                >
                                    {{ formatVND(order.total_amount) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>

            <div class="grid grid-cols-1 gap-5 pb-14 lg:grid-cols-3">
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
        </template>

        <!-- ═══════════════════════════════════════════════════════════════ -->
        <!-- PRO PLAN LAYOUT: 3 cột, analytics + fraud, không AI forecast -->
        <!-- ═══════════════════════════════════════════════════════════════ -->
        <template v-else-if="activePlanCode === 'pro'">
            <!-- Thống kê tổng hợp Pro -->
            <div
                class="animate-enter stagger-4 grid grid-cols-2 gap-4 md:grid-cols-5"
            >
                <Card
                    class="rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/40"
                >
                    <CardContent
                        class="flex flex-col items-center p-4 text-center"
                    >
                        <p
                            class="text-2xl font-black text-emerald-600 dark:text-emerald-400"
                        >
                            {{ formatVND(props.stats?.revenue_today ?? 0) }}
                        </p>
                        <p
                            class="mt-0.5 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                        >
                            Doanh thu hôm nay
                        </p>
                    </CardContent>
                </Card>
                <Card
                    class="rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/40"
                >
                    <CardContent
                        class="flex flex-col items-center p-4 text-center"
                    >
                        <p
                            class="text-2xl font-black text-slate-800 dark:text-slate-100"
                        >
                            {{ props.stats?.orders_today ?? 0 }}
                        </p>
                        <p
                            class="mt-0.5 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                        >
                            Đơn hôm nay
                        </p>
                    </CardContent>
                </Card>
                <Card
                    class="rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/40"
                >
                    <CardContent
                        class="flex flex-col items-center p-4 text-center"
                    >
                        <p
                            class="text-2xl font-black text-slate-800 dark:text-slate-100"
                        >
                            {{ props.stats?.completion_rate ?? 0 }}%
                        </p>
                        <p
                            class="mt-0.5 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                        >
                            Tỉ lệ hoàn thành
                        </p>
                    </CardContent>
                </Card>
                <Card
                    class="rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/40"
                >
                    <CardContent
                        class="flex flex-col items-center p-4 text-center"
                    >
                        <p
                            class="text-2xl font-black text-slate-800 dark:text-slate-100"
                        >
                            {{ props.stats?.profit_margin_today ?? 0 }}%
                        </p>
                        <p
                            class="mt-0.5 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                        >
                            Biên lợi nhuận
                        </p>
                    </CardContent>
                </Card>
                <Card
                    class="rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/40"
                >
                    <CardContent
                        class="flex flex-col items-center p-4 text-center"
                    >
                        <p
                            class="text-2xl font-black text-slate-800 dark:text-slate-100"
                        >
                            {{ props.stats?.employees_count ?? 0 }}
                        </p>
                        <p
                            class="mt-0.5 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                        >
                            Nhân viên
                        </p>
                    </CardContent>
                </Card>
            </div>

            <div
                class="animate-enter stagger-5 grid grid-cols-1 gap-5 pb-14 lg:grid-cols-3"
            >
                <div class="space-y-6 lg:col-span-2">
                    <Deferred data="revenueChartData">
                        <template #fallback>
                            <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/40">
                                <div class="flex items-center justify-between mb-6">
                                    <div class="h-5 w-40 animate-pulse rounded bg-slate-200 dark:bg-slate-800" />
                                    <div class="h-8 w-24 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800/80" />
                                </div>
                                <div class="h-[250px] w-full flex items-end gap-3 pt-4">
                                    <div v-for="n in 7" :key="n" class="w-full bg-slate-100 dark:bg-slate-800/60 rounded-t-lg animate-pulse" :style="{ height: [40, 70, 50, 90, 60, 85, 30][n-1] + '%' }" />
                                </div>
                            </div>
                        </template>
                        <RevenueForecastChart
                            :revenue-chart-data="props.revenueChartData"
                            :forecast-data="null"
                        />
                    </Deferred>
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <Deferred data="channelChartData">
                            <template #fallback>
                                <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/40">
                                    <div class="flex items-center justify-between mb-4">
                                        <div class="h-4 w-32 animate-pulse rounded bg-slate-200 dark:bg-slate-800" />
                                    </div>
                                    <div class="flex items-center justify-center h-[200px]">
                                        <div class="h-28 w-28 rounded-full border-[12px] border-slate-100 dark:border-slate-800/80 animate-pulse flex items-center justify-center">
                                            <div class="h-10 w-10 rounded-full bg-slate-50 dark:bg-slate-900" />
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <ChannelShareChart
                                :channel-chart-data="props.channelChartData"
                            />
                        </Deferred>
                        <Deferred data="peakHoursChartData">
                            <template #fallback>
                                <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/40">
                                    <div class="flex items-center justify-between mb-4">
                                        <div class="h-4 w-32 animate-pulse rounded bg-slate-200 dark:bg-slate-800" />
                                    </div>
                                    <div class="h-[200px] w-full flex items-end gap-1.5 pt-4">
                                        <div v-for="n in 12" :key="n" class="w-full bg-slate-100 dark:bg-slate-800/60 rounded-t animate-pulse" :style="{ height: [20, 35, 60, 80, 50, 40, 75, 90, 85, 45, 30, 15][n-1] + '%' }" />
                                    </div>
                                </div>
                            </template>
                            <PeakHoursChart
                                :peak-hours-chart-data="props.peakHoursChartData"
                            />
                        </Deferred>
                    </div>
                    <Deferred data="topProductsChartData">
                        <template #fallback>
                            <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/40">
                                <div class="flex items-center justify-between mb-5">
                                    <div class="h-4 w-40 animate-pulse rounded bg-slate-200 dark:bg-slate-800" />
                                </div>
                                <div class="space-y-4">
                                    <div v-for="n in 5" :key="n" class="flex items-center justify-between gap-4">
                                        <div class="flex items-center gap-3 w-full">
                                            <div class="h-8 w-8 rounded-full bg-slate-100 dark:bg-slate-800 animate-pulse shrink-0" />
                                            <div class="space-y-1.5 w-full">
                                                <div class="h-3 w-1/3 bg-slate-200 dark:bg-slate-800 rounded animate-pulse" />
                                                <div class="h-2 w-1/2 bg-slate-100 dark:bg-slate-800/60 rounded animate-pulse" />
                                            </div>
                                        </div>
                                        <div class="h-4 w-12 bg-slate-200 dark:bg-slate-800 rounded animate-pulse shrink-0" />
                                    </div>
                                </div>
                            </div>
                        </template>
                        <TopProductsLeaderboard
                            :top-products-chart-data="props.topProductsChartData"
                        />
                    </Deferred>
                    <OperationsCenter
                        :operation-feed="props.operationFeed"
                        :tables-data="props.tablesData"
                        :low-stock-inventory="props.lowStockInventory"
                        :owner-summary="props.ownerSummary"
                        :shift-revenue="props.shiftRevenue"
                    />
                    <!-- Đơn hàng gần đây (Pro) -->
                    <Card
                        v-if="props.recentOrders?.length"
                        class="overflow-hidden rounded-2xl border border-slate-100 bg-white pb-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/40"
                    >
                        <CardHeader
                            class="border-b border-slate-100 bg-slate-50/50 pb-3 dark:border-slate-800 dark:bg-slate-900/10"
                        >
                            <div class="flex items-center justify-between">
                                <CardTitle
                                    class="flex items-center gap-1.5 text-sm font-bold"
                                    ><ShoppingCart
                                        class="size-4.5 text-violet-600 dark:text-violet-400"
                                    />
                                    Đơn hàng gần đây</CardTitle
                                >
                                <Link
                                    href="/orders"
                                    prefetch
                                    class="flex items-center gap-0.5 text-xs font-bold text-primary hover:underline"
                                    >Xem tất cả <ChevronRight class="size-3.5"
                                /></Link>
                            </div>
                        </CardHeader>
                        <CardContent class="overflow-x-auto p-0">
                            <table
                                class="w-full border-collapse text-left text-xs"
                            >
                                <thead>
                                    <tr
                                        class="border-b border-slate-100 bg-slate-50/30 text-[10px] font-extrabold tracking-wider text-slate-500 uppercase dark:border-slate-800 dark:bg-slate-900/20 dark:text-slate-400"
                                    >
                                        <th class="p-3 pl-5">Mã</th>
                                        <th class="p-3">Bàn</th>
                                        <th class="p-3 text-center">
                                            Trạng thái
                                        </th>
                                        <th class="p-3 pr-5 text-right">
                                            Tổng tiền
                                        </th>
                                    </tr>
                                </thead>
                                <tbody
                                    class="divide-y divide-slate-100/50 dark:divide-slate-800"
                                >
                                    <tr
                                        v-for="order in props.recentOrders.slice(
                                            0,
                                            8,
                                        )"
                                        :key="order.id"
                                        class="text-slate-700 hover:bg-slate-50/40 dark:text-slate-300 dark:hover:bg-slate-900/20"
                                    >
                                        <td
                                            class="p-3 pl-5 font-mono font-bold"
                                        >
                                            #{{ order.order_number }}
                                        </td>
                                        <td class="p-3">
                                            {{ order.table_name ?? 'Mang về' }}
                                        </td>
                                        <td class="p-3 text-center">
                                            <span
                                                class="inline-flex rounded-full border px-2 py-0.5 text-[9px] font-black uppercase"
                                                :class="
                                                    order.status === 'completed'
                                                        ? 'border-emerald-100 bg-emerald-50 text-emerald-700 dark:bg-emerald-950/20 dark:text-emerald-400'
                                                        : order.status ===
                                                            'cancelled'
                                                          ? 'border-rose-100 bg-rose-50 text-rose-700 dark:bg-rose-950/20 dark:text-rose-400'
                                                          : 'border-amber-100 bg-amber-50 text-amber-700 dark:bg-amber-950/20 dark:text-amber-400'
                                                "
                                            >
                                                {{
                                                    order.status === 'completed'
                                                        ? 'Xong'
                                                        : order.status ===
                                                            'cancelled'
                                                          ? 'Hủy'
                                                          : 'Xử lý'
                                                }}
                                            </span>
                                        </td>
                                        <td
                                            class="p-3 pr-5 text-right font-mono font-black"
                                        >
                                            {{ formatVND(order.total_amount) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                </div>
                <div class="space-y-6">
                    <CashFlowWidget
                        :cash-flow-summary="props.cashFlowSummary"
                    />
                    <Card
                        class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/40"
                    >
                        <CardHeader
                            class="border-b border-slate-100 bg-slate-50/50 pb-3 dark:border-slate-800 dark:bg-slate-900/10"
                        >
                            <CardTitle
                                class="flex items-center gap-1.5 text-sm font-bold"
                                ><Users
                                    class="size-4.5 text-indigo-600 dark:text-indigo-400"
                                />
                                Nhân sự Hôm nay</CardTitle
                            >
                        </CardHeader>
                        <CardContent class="space-y-2.5 p-5">
                            <div
                                v-if="props.ownerSummary?.active_shifts?.length"
                                class="space-y-2"
                            >
                                <div
                                    v-for="s in props.ownerSummary
                                        .active_shifts"
                                    :key="s.name + s.shift"
                                    class="flex items-center justify-between rounded-lg border border-slate-100/50 bg-slate-50/50 p-2 text-xs dark:border-slate-800/40 dark:bg-slate-900/40"
                                >
                                    <div
                                        class="flex min-w-0 items-center gap-2"
                                    >
                                        <span
                                            :class="[
                                                'h-2 w-2 shrink-0 rounded-full',
                                                s.status === 'checked_in'
                                                    ? 'animate-pulse bg-emerald-500'
                                                    : 'bg-amber-400',
                                            ]"
                                        />
                                        <span
                                            class="truncate font-bold text-slate-800 dark:text-slate-200"
                                            >{{ s.name }}</span
                                        >
                                    </div>
                                    <span
                                        class="rounded-md border border-slate-100 bg-white px-2 py-0.5 text-[9px] font-black text-slate-500 uppercase dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-400"
                                        >{{
                                            s.status === 'checked_in'
                                                ? 'Đã Check-in'
                                                : 'Chưa'
                                        }}</span
                                    >
                                </div>
                            </div>
                            <div
                                v-else
                                class="flex flex-col items-center justify-center py-6 text-center text-slate-400"
                            >
                                <Users
                                    class="mb-1 size-6 text-slate-300 dark:text-slate-700"
                                />
                                <p class="text-xs">
                                    Chưa có nhân viên check-in
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                    <AIInsightsCard
                        :forecast-data="null"
                        :stats="props.stats"
                        :top-products-chart-data="props.topProductsChartData"
                        :channel-chart-data="props.channelChartData"
                    />
                    <DashboardSidebar
                        :onboarding-complete="props.onboardingComplete"
                        :recent-orders="props.recentOrders"
                        :stats="props.stats"
                        :alerts="props.alerts"
                        :user="user"
                    />
                </div>
            </div>
        </template>

        <!-- ═══════════════════════════════════════════════════════════════ -->
        <!-- ENTERPRISE LAYOUT: 3 cột đầy đủ, AI + Analytics + Forecast   -->
        <!-- ═══════════════════════════════════════════════════════════════ -->
        <template v-else>
            <!-- Thống kê tổng hợp Enterprise -->
            <div
                class="animate-enter stagger-4 grid grid-cols-2 gap-4 md:grid-cols-5"
            >
                <Card
                    class="rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/40"
                >
                    <CardContent
                        class="flex flex-col items-center p-4 text-center"
                    >
                        <p
                            class="text-2xl font-black text-emerald-600 dark:text-emerald-400"
                        >
                            {{ formatVND(props.stats?.revenue_today ?? 0) }}
                        </p>
                        <p
                            class="mt-0.5 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                        >
                            Doanh thu hôm nay
                        </p>
                    </CardContent>
                </Card>
                <Card
                    class="rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/40"
                >
                    <CardContent
                        class="flex flex-col items-center p-4 text-center"
                    >
                        <p
                            class="text-2xl font-black text-slate-800 dark:text-slate-100"
                        >
                            {{ props.stats?.orders_today ?? 0 }}
                        </p>
                        <p
                            class="mt-0.5 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                        >
                            Đơn hôm nay
                        </p>
                    </CardContent>
                </Card>
                <Card
                    class="rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/40"
                >
                    <CardContent
                        class="flex flex-col items-center p-4 text-center"
                    >
                        <p
                            class="text-2xl font-black text-slate-800 dark:text-slate-100"
                        >
                            {{ props.stats?.completion_rate ?? 0 }}%
                        </p>
                        <p
                            class="mt-0.5 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                        >
                            Tỉ lệ hoàn thành
                        </p>
                    </CardContent>
                </Card>
                <Card
                    class="rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/40"
                >
                    <CardContent
                        class="flex flex-col items-center p-4 text-center"
                    >
                        <p
                            class="text-2xl font-black text-slate-800 dark:text-slate-100"
                        >
                            {{ props.stats?.profit_margin_today ?? 0 }}%
                        </p>
                        <p
                            class="mt-0.5 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                        >
                            Biên lợi nhuận
                        </p>
                    </CardContent>
                </Card>
                <Card
                    class="rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/40"
                >
                    <CardContent
                        class="flex flex-col items-center p-4 text-center"
                    >
                        <p
                            class="text-2xl font-black text-slate-800 dark:text-slate-100"
                        >
                            {{ props.stats?.employees_count ?? 0 }}
                        </p>
                        <p
                            class="mt-0.5 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                        >
                            Nhân viên
                        </p>
                    </CardContent>
                </Card>
            </div>

            <div
                class="animate-enter stagger-5 grid grid-cols-1 gap-5 pb-14 lg:grid-cols-3"
            >
                <div class="space-y-6 lg:col-span-2">
                    <Deferred :data="['revenueChartData', 'forecastData']">
                        <template #fallback>
                            <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/40">
                                <div class="flex items-center justify-between mb-6">
                                    <div class="h-5 w-40 animate-pulse rounded bg-slate-200 dark:bg-slate-800" />
                                    <div class="h-8 w-24 animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800/80" />
                                </div>
                                <div class="h-[250px] w-full flex items-end gap-3 pt-4">
                                    <div v-for="n in 7" :key="n" class="w-full bg-slate-100 dark:bg-slate-800/60 rounded-t-lg animate-pulse" :style="{ height: [40, 70, 50, 90, 60, 85, 30][n-1] + '%' }" />
                                </div>
                            </div>
                        </template>
                        <RevenueForecastChart
                            :revenue-chart-data="props.revenueChartData"
                            :forecast-data="props.forecastData"
                        />
                    </Deferred>
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <Deferred data="channelChartData">
                            <template #fallback>
                                <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/40">
                                    <div class="flex items-center justify-between mb-4">
                                        <div class="h-4 w-32 animate-pulse rounded bg-slate-200 dark:bg-slate-800" />
                                    </div>
                                    <div class="flex items-center justify-center h-[200px]">
                                        <div class="h-28 w-28 rounded-full border-[12px] border-slate-100 dark:border-slate-800/80 animate-pulse flex items-center justify-center">
                                            <div class="h-10 w-10 rounded-full bg-slate-50 dark:bg-slate-900" />
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <ChannelShareChart
                                :channel-chart-data="props.channelChartData"
                            />
                        </Deferred>
                        <Deferred data="peakHoursChartData">
                            <template #fallback>
                                <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/40">
                                    <div class="flex items-center justify-between mb-4">
                                        <div class="h-4 w-32 animate-pulse rounded bg-slate-200 dark:bg-slate-800" />
                                    </div>
                                    <div class="h-[200px] w-full flex items-end gap-1.5 pt-4">
                                        <div v-for="n in 12" :key="n" class="w-full bg-slate-100 dark:bg-slate-800/60 rounded-t animate-pulse" :style="{ height: [20, 35, 60, 80, 50, 40, 75, 90, 85, 45, 30, 15][n-1] + '%' }" />
                                    </div>
                                </div>
                            </template>
                            <PeakHoursChart
                                :peak-hours-chart-data="props.peakHoursChartData"
                            />
                        </Deferred>
                    </div>
                    <Deferred data="topProductsChartData">
                        <template #fallback>
                            <div class="rounded-2xl border border-slate-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900/40">
                                <div class="flex items-center justify-between mb-5">
                                    <div class="h-4 w-40 animate-pulse rounded bg-slate-200 dark:bg-slate-800" />
                                </div>
                                <div class="space-y-4">
                                    <div v-for="n in 5" :key="n" class="flex items-center justify-between gap-4">
                                        <div class="flex items-center gap-3 w-full">
                                            <div class="h-8 w-8 rounded-full bg-slate-100 dark:bg-slate-800 animate-pulse shrink-0" />
                                            <div class="space-y-1.5 w-full">
                                                <div class="h-3 w-1/3 bg-slate-200 dark:bg-slate-800 rounded animate-pulse" />
                                                <div class="h-2 w-1/2 bg-slate-100 dark:bg-slate-800/60 rounded animate-pulse" />
                                            </div>
                                        </div>
                                        <div class="h-4 w-12 bg-slate-200 dark:bg-slate-800 rounded animate-pulse shrink-0" />
                                    </div>
                                </div>
                            </div>
                        </template>
                        <TopProductsLeaderboard
                            :top-products-chart-data="props.topProductsChartData"
                        />
                    </Deferred>
                    <OperationsCenter
                        :operation-feed="props.operationFeed"
                        :tables-data="props.tablesData"
                        :low-stock-inventory="props.lowStockInventory"
                        :owner-summary="props.ownerSummary"
                        :shift-revenue="props.shiftRevenue"
                    />
                    <!-- Đơn hàng gần đây (Enterprise) -->
                    <Card
                        v-if="props.recentOrders?.length"
                        class="overflow-hidden rounded-2xl border border-slate-100 bg-white pb-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/40"
                    >
                        <CardHeader
                            class="border-b border-slate-100 bg-slate-50/50 pb-3 dark:border-slate-800 dark:bg-slate-900/10"
                        >
                            <div class="flex items-center justify-between">
                                <CardTitle
                                    class="flex items-center gap-1.5 text-sm font-bold"
                                    ><ShoppingCart
                                        class="size-4.5 text-violet-600 dark:text-violet-400"
                                    />
                                    Đơn hàng gần đây</CardTitle
                                >
                                <Link
                                    href="/orders"
                                    prefetch
                                    class="flex items-center gap-0.5 text-xs font-bold text-primary hover:underline"
                                    >Xem tất cả <ChevronRight class="size-3.5"
                                /></Link>
                            </div>
                        </CardHeader>
                        <CardContent class="overflow-x-auto p-0">
                            <table
                                class="w-full border-collapse text-left text-xs"
                            >
                                <thead>
                                    <tr
                                        class="border-b border-slate-100 bg-slate-50/30 text-[10px] font-extrabold tracking-wider text-slate-500 uppercase dark:border-slate-800 dark:bg-slate-900/20 dark:text-slate-400"
                                    >
                                        <th class="p-3 pl-5">Mã</th>
                                        <th class="p-3">Bàn</th>
                                        <th class="p-3">Kênh</th>
                                        <th class="p-3 text-center">
                                            Trạng thái
                                        </th>
                                        <th class="p-3 pr-5 text-right">
                                            Tổng tiền
                                        </th>
                                    </tr>
                                </thead>
                                <tbody
                                    class="divide-y divide-slate-100/50 dark:divide-slate-800"
                                >
                                    <tr
                                        v-for="order in props.recentOrders.slice(
                                            0,
                                            10,
                                        )"
                                        :key="order.id"
                                        class="text-slate-700 hover:bg-slate-50/40 dark:text-slate-300 dark:hover:bg-slate-900/20"
                                    >
                                        <td
                                            class="p-3 pl-5 font-mono font-bold"
                                        >
                                            #{{ order.order_number }}
                                        </td>
                                        <td class="p-3">
                                            {{ order.table_name ?? 'Mang về' }}
                                        </td>
                                        <td class="p-3 text-slate-500">
                                            {{
                                                order.channel === 'dine_in'
                                                    ? 'Tại bàn'
                                                    : order.channel ===
                                                        'takeaway'
                                                      ? 'Mang về'
                                                      : order.channel ===
                                                          'delivery'
                                                        ? 'Giao hàng'
                                                        : 'QR'
                                            }}
                                        </td>
                                        <td class="p-3 text-center">
                                            <span
                                                class="inline-flex rounded-full border px-2 py-0.5 text-[9px] font-black uppercase"
                                                :class="
                                                    order.status === 'completed'
                                                        ? 'border-emerald-100 bg-emerald-50 text-emerald-700 dark:bg-emerald-950/20 dark:text-emerald-400'
                                                        : order.status ===
                                                            'cancelled'
                                                          ? 'border-rose-100 bg-rose-50 text-rose-700 dark:bg-rose-950/20 dark:text-rose-400'
                                                          : 'border-amber-100 bg-amber-50 text-amber-700 dark:bg-amber-950/20 dark:text-amber-400'
                                                "
                                            >
                                                {{
                                                    order.status === 'completed'
                                                        ? 'Xong'
                                                        : order.status ===
                                                            'cancelled'
                                                          ? 'Hủy'
                                                          : 'Xử lý'
                                                }}
                                            </span>
                                        </td>
                                        <td
                                            class="p-3 pr-5 text-right font-mono font-black"
                                        >
                                            {{ formatVND(order.total_amount) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>
                </div>
                <div class="space-y-6">
                    <CashFlowWidget
                        :cash-flow-summary="props.cashFlowSummary"
                    />
                    <AIInsightsCard
                        :forecast-data="props.forecastData"
                        :stats="props.stats"
                        :top-products-chart-data="props.topProductsChartData"
                        :channel-chart-data="props.channelChartData"
                    />
                    <WeatherForecastCard />
                    <DashboardSidebar
                        :onboarding-complete="props.onboardingComplete"
                        :recent-orders="props.recentOrders"
                        :stats="props.stats"
                        :alerts="props.alerts"
                        :user="user"
                    />
                </div>
            </div>
        </template>
    </div>
</template>
