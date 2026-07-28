<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    Activity,
    AlertTriangle,
    BarChart3,
    Bell,
    Building2,
    Calendar,
    ChevronRight,
    CheckCircle2,
    Crown,
    Info,
    Package,
    Settings,
    ShieldCheck,
    Star,
    TrendingUp,
    TrendingDown,
    Users,
    Zap,
    ShoppingCart,
    Banknote,
    XCircle,
    Clock,
    Utensils,
    Brain,
    ArrowUp,
    ArrowDown,
    Sparkles,
    Trophy,
    Target,
    Percent,
    CalendarDays,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppTopbarLayout from '@/layouts/AppTopbarLayout.vue';

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
    orders: number;
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

const availablePlans = computed(
    () => (page.props as any).available_plans ?? [],
);
const roles = computed(() => (page.props as any).roles ?? []);
const canManageBilling = computed(
    () =>
        tenant.value != null &&
        (roles.value.includes('owner') ||
            roles.value.includes('admin') ||
            roles.value.includes('super_admin')),
);

const currentPlanRank = computed(() => {
    if (!plan.value?.code) {
        return 0;
    }

    const idx = availablePlans.value.findIndex(
        (p: any) => p.code === plan.value.code,
    );

    return idx === -1 ? 0 : idx;
});

const nextPlan = computed(() =>
    canManageBilling.value
        ? (availablePlans.value[currentPlanRank.value + 1] ?? null)
        : null,
);

const isFreePlan = computed(() => !plan.value || plan.value.code === 'free');

const planBadgeClass = computed(() => {
    switch (plan.value?.code) {
        case 'pro':
            return 'bg-primary text-primary-foreground hover:bg-primary/90';
        case 'max':
            return 'bg-sky-600 text-white hover:bg-sky-700';
        case 'ultra':
            return 'bg-violet-600 text-white hover:bg-violet-700';
        default:
            return '';
    }
});

const planIcon = computed(() => {
    switch (plan.value?.code) {
        case 'ultra':
            return Crown;
        case 'max':
            return Zap;
        case 'pro':
            return Star;
        default:
            return null;
    }
});

const activeTab = ref<'feed' | 'tables' | 'inventory' | 'owner'>('feed');

// ── Health Score helpers ─────────────────────────────────────────────────────
const healthScoreColor = computed(() => {
    const s = props.healthScore ?? 0;

    if (s >= 70) {
        return {
            bar: 'bg-emerald-500',
            text: 'text-emerald-600 dark:text-emerald-400',
            bg: 'bg-emerald-50 dark:bg-emerald-950/20',
            label: 'Tốt',
        };
    }

    if (s >= 40) {
        return {
            bar: 'bg-amber-500',
            text: 'text-amber-600 dark:text-amber-400',
            bg: 'bg-amber-50 dark:bg-amber-950/20',
            label: 'Trung bình',
        };
    }

    return {
        bar: 'bg-rose-500',
        text: 'text-rose-600 dark:text-rose-400',
        bg: 'bg-rose-50 dark:bg-rose-950/20',
        label: 'Cần cải thiện',
    };
});

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

// ── Forecast bar color ───────────────────────────────────────────────────────
function barFillClass(isForecast: boolean) {
    return isForecast
        ? 'fill-indigo-300/50 dark:fill-indigo-400/30'
        : 'fill-indigo-500/80 dark:fill-indigo-500/60';
}

const operationFeedList = computed(() => props.operationFeed ?? []);
const tablesList = computed(() => props.tablesData ?? []);
const lowStockList = computed(() => props.lowStockInventory ?? []);
const recentOrdersList = computed(() => props.recentOrders ?? []);
const alertsList = computed(() => props.alerts ?? []);

function getFeedIcon(iconName: string) {
    switch (iconName) {
        case 'ShoppingCart':
            return ShoppingCart;
        case 'Utensils':
            return Utensils;
        case 'CheckCircle2':
            return CheckCircle2;
        case 'XCircle':
            return XCircle;
        case 'AlertTriangle':
            return AlertTriangle;
        case 'Users':
            return Users;
        default:
            return Activity;
    }
}

function getFeedColorClasses(colorName: string) {
    switch (colorName) {
        case 'amber':
            return 'border-amber-100 bg-amber-50 text-amber-600 dark:border-amber-950/30 dark:bg-amber-950/20 dark:text-amber-400';
        case 'violet':
            return 'border-violet-100 bg-violet-50 text-violet-600 dark:border-violet-950/30 dark:bg-violet-950/20 dark:text-violet-400';
        case 'emerald':
            return 'border-emerald-100 bg-emerald-50 text-emerald-600 dark:border-emerald-950/30 dark:bg-emerald-950/20 dark:text-emerald-400';
        case 'rose':
            return 'border-rose-100 bg-rose-50 text-rose-600 dark:border-rose-950/30 dark:bg-rose-950/20 dark:text-rose-400';
        case 'sky':
            return 'border-sky-100 bg-sky-50 text-sky-600 dark:border-sky-900/30 dark:bg-sky-950/20 dark:text-sky-400';
        default:
            return 'border-slate-100 bg-slate-50 text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400';
    }
}

const tableStatusMap = {
    available: {
        label: 'Bàn trống',
        class: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/20 dark:text-emerald-450 dark:border-emerald-900/40',
    },
    occupied: {
        label: 'Có khách',
        class: 'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-950/20 dark:text-indigo-400 dark:border-indigo-900/40',
    },
    reserved: {
        label: 'Đặt trước',
        class: 'bg-violet-50 text-violet-700 border-violet-200 dark:bg-violet-950/20 dark:text-violet-400 dark:border-violet-900/40',
    },
    cleaning: {
        label: 'Dọn dẹp',
        class: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/20 dark:text-amber-400 dark:border-amber-900/40',
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

// Trial countdown
const isOnTrial = computed(() => tenant.value?.status === 'trial');
const trialDaysLeft = computed(() => {
    if (!tenant.value?.trial_ends_at) {
        return 0;
    }

    const end = new Date(tenant.value.trial_ends_at);
    const now = new Date();

    return Math.max(
        0,
        Math.ceil((end.getTime() - now.getTime()) / (1000 * 60 * 60 * 24)),
    );
});

// Onboarding steps derived from user's onboarding_status
// Cấu trúc thực tế: { current_day, day_1: { completed_at, ... }, day_2: {...}, day_3: {...} }
const onboardingSteps = computed(() => {
    const status = (user.value?.onboarding_status as any) ?? {};

    return [
        {
            key: 'day_1',
            label: 'Thêm sản phẩm & thực đơn',
            href: '/products',
            done: !!status.day_1?.completed_at,
        },
        {
            key: 'day_2',
            label: 'Cấu hình kho nguyên liệu',
            href: '/inventory',
            done: !!status.day_2?.completed_at,
        },
        {
            key: 'day_3',
            label: 'Thêm nhân viên & xếp lịch ca',
            href: '/employees',
            done: !!status.day_3?.completed_at,
        },
    ];
});

const onboardingProgress = computed(
    () => onboardingSteps.value.filter((s) => s.done).length,
);

const quickActions = [
    {
        label: 'Đơn hàng',
        description: 'Xử lý, theo dõi đơn',
        icon: ShoppingCart,
        href: '/orders',
        color: 'violet',
    },
    {
        label: 'Sản phẩm',
        description: 'Menu & danh mục',
        icon: Package,
        href: '/products',
        color: 'amber',
    },
    {
        label: 'Bàn & Khu vực',
        description: 'Sơ đồ, trạng thái bàn',
        icon: BarChart3,
        href: '/tables',
        color: 'teal',
    },
    {
        label: 'Nhân viên',
        description: 'Ca làm, phân quyền',
        icon: Users,
        href: '/employees',
        color: 'sky',
    },
    {
        label: 'Kho nguyên liệu',
        description: 'Nhập xuất, tồn kho',
        icon: TrendingUp,
        href: '/inventory',
        color: 'emerald',
    },
    {
        label: 'Báo cáo',
        description: 'Doanh thu, lợi nhuận',
        icon: BarChart3,
        href: '/reports',
        color: 'rose',
    },
];

// Đọc usage từ quota_summary.resources
function resourceData(key: string) {
    return (quota.value as any)?.resources?.[key] ?? null;
}

function formatLimit(v: number | null): string {
    return v === null ? '∞' : String(v);
}

function formatMoney(v: number): string {
    if (v === 0) {
        return '—';
    }

    return (
        new Intl.NumberFormat('vi-VN', {
            notation: 'compact',
            maximumFractionDigits: 1,
        }).format(v) + 'đ'
    );
}

function formatMoneyFull(v: number): string {
    if (v === 0) {
        return '—';
    }

    return new Intl.NumberFormat('vi-VN').format(v) + 'đ';
}

const quotaStats = computed(() => [
    { key: 'branches', label: 'Chi nhánh', icon: Building2 },
    { key: 'employees', label: 'Nhân viên', icon: Users },
    { key: 'tables', label: 'Bàn', icon: BarChart3 },
]);

// Trạng thái đơn hàng
const orderStatusMap: Record<string, { label: string; class: string }> = {
    pending: {
        label: 'Chờ xác nhận',
        class: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    },
    confirmed: {
        label: 'Đã xác nhận',
        class: 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400',
    },
    preparing: {
        label: 'Đang chế biến',
        class: 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400',
    },
    completed: {
        label: 'Hoàn thành',
        class: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    },
    cancelled: {
        label: 'Đã huỷ',
        class: 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
    },
};

const channelMap: Record<string, string> = {
    dine_in: 'Tại bàn',
    takeaway: 'Mang về',
    delivery: 'Giao hàng',
    qr: 'QR',
};

// Hiệu suất hôm nay — tính toán từ stats
const completionRate = computed(() => {
    if (!props.stats?.orders_today) {
        return 0;
    }

    return Math.round(
        (props.stats.orders_completed / props.stats.orders_today) * 100,
    );
});

const cancellationRate = computed(() => {
    if (!props.stats?.orders_today) {
        return 0;
    }

    return Math.round(
        ((props.stats.orders_cancelled ?? 0) / props.stats.orders_today) * 100,
    );
});

const pendingCount = computed(() => {
    if (!props.stats) {
        return 0;
    }

    return (
        props.stats.orders_today -
        props.stats.orders_completed -
        (props.stats.orders_cancelled ?? 0)
    );
});

// Alert icon map
const alertIconMap = { warning: AlertTriangle, danger: XCircle, info: Info };
const alertColorMap = {
    warning:
        'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800/50 dark:bg-amber-950/30 dark:text-amber-300',
    danger: 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-800/50 dark:bg-rose-950/30 dark:text-rose-300',
    info: 'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-800/50 dark:bg-sky-950/30 dark:text-sky-300',
};

// Chart calculations
const revenueChartList = computed(() => props.revenueChartData ?? []);
const maxRevenue = computed(() => {
    const vals = revenueChartList.value.map((d) => d.revenue);

    return Math.max(...vals, 100000); // at least 100k
});

const channelChartList = computed(() => props.channelChartData ?? []);
const channelColors: Record<string, string> = {
    dine_in: '#6366f1', // indigo
    takeaway: '#f59e0b', // amber
    delivery: '#10b981', // emerald
    qr: '#ec4899', // pink
};

const doughnutPaths = computed(() => {
    const list = channelChartList.value;
    const total = list.reduce((sum, item) => sum + item.count, 0);

    if (total === 0) {
        return [];
    }

    let currentAngle = -Math.PI / 2; // start at top

    return list.map((item) => {
        const pct = item.count / total;
        const angle = pct * 2 * Math.PI;
        const start = currentAngle;
        const end = currentAngle + angle;
        currentAngle = end;

        // standard pie arc path with radius 100 centered at (100, 100)
        const startX = 100 + 100 * Math.cos(start);
        const startY = 100 + 100 * Math.sin(start);
        const endX = 100 + 100 * Math.cos(end);
        const endY = 100 + 100 * Math.sin(end);
        const largeArc = angle > Math.PI ? 1 : 0;

        const path = `M 100 100 L ${startX} ${startY} A 100 100 0 ${largeArc} 1 ${endX} ${endY} Z`;

        return {
            ...item,
            color: channelColors[item.channel] ?? '#94a3b8',
            path,
        };
    });
});

const topProductsList = computed(() => props.topProductsChartData ?? []);
const maxProductQty = computed(() => {
    const qtyList = topProductsList.value.map((p) => p.quantity);

    return Math.max(...qtyList, 1);
});

const activeHoverIndex = ref<number | null>(null);

const maxOrders = computed(() => {
    const list = revenueChartList.value.map((d) => d.orders);

    return Math.max(...list, 5);
});

const ordersLinePath = computed(() => {
    const list = revenueChartList.value;

    if (list.length === 0) {
        return '';
    }

    return list
        .map((day, i) => {
            const x = i * 85 + 80;
            const y = 160 - (day.orders / maxOrders.value) * 125;

            return `${i === 0 ? 'M' : 'L'} ${x} ${y}`;
        })
        .join(' ');
});

const topDishName = computed(() => topProductsList.value[0]?.name ?? 'chưa có');
const topChannelLabel = computed(() => {
    if (doughnutPaths.value.length === 0) {
        return 'chưa có';
    }

    const sorted = [...doughnutPaths.value].sort((a, b) => b.count - a.count);

    return sorted[0]?.label ?? 'chưa có';
});
</script>

<template>
    <AppTopbarLayout>
        <Head title="Dashboard · Aventura" />

        <!-- ── Trial Countdown Banner ────────────────────────────── -->
        <div
            v-if="isOnTrial"
            class="border-b border-amber-200 bg-amber-50 px-4 py-4 sm:px-6 lg:px-8 dark:border-amber-800 dark:bg-amber-950/40"
        >
            <div
                class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-3 sm:flex-row"
            >
                <div class="flex items-center gap-2.5 text-sm">
                    <span
                        class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber-500 text-[10px] font-bold text-white"
                        >!</span
                    >
                    <span
                        class="font-medium text-amber-800 dark:text-amber-200"
                    >
                        Tài khoản đang dùng thử —
                        <strong>còn {{ trialDaysLeft }} ngày</strong>. Nâng cấp
                        để không gián đoạn vận hành.
                    </span>
                </div>
                <Link
                    :href="
                        nextPlan
                            ? `/billing/checkout?plan=${nextPlan.code}`
                            : '/billing/checkout'
                    "
                    class="shrink-0 rounded-lg bg-amber-500 px-4 py-1.5 text-xs font-semibold text-white transition-colors hover:bg-amber-600"
                >
                    Nâng cấp ngay →
                </Link>
            </div>
        </div>

        <!-- ── Welcome header ───────────────────────────────────── -->
        <section class="border-b border-border bg-muted/30 px-4 py-8 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <div
                    class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <p class="text-sm text-muted-foreground">
                            Xin chào trở lại,
                        </p>
                        <h1 class="mt-0.5 text-2xl font-black tracking-tight text-foreground dark:text-zinc-100">
                            {{ user?.name }}
                        </h1>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <span
                                v-if="tenant?.name"
                                class="flex items-center gap-1 text-sm text-muted-foreground"
                            >
                                <Building2 class="size-3.5" />
                                {{ tenant.name }}
                            </span>
                            <Badge
                                v-if="plan"
                                :class="planBadgeClass"
                                class="gap-1"
                            >
                                <component
                                    v-if="planIcon"
                                    :is="planIcon"
                                    class="size-3"
                                />
                                Gói {{ plan.name }}
                            </Badge>
                            <Badge v-else variant="secondary">Gói Free</Badge>
                        </div>
                    </div>

                    <Button v-if="nextPlan" as-child size="sm" class="shrink-0">
                        <Link
                            :href="`/billing/checkout?plan=${nextPlan.code}`"
                            class="flex items-center gap-1.5"
                        >
                            <Zap class="size-3.5" />
                            Nâng lên {{ nextPlan.name }}
                        </Link>
                    </Button>
                </div>

                <!-- Quota stats row -->
                <div v-if="quota && plan" class="mt-6 grid grid-cols-3 gap-3">
                    <div
                        v-for="stat in quotaStats"
                        :key="stat.key"
                        class="rounded-lg border border-border bg-background p-3"
                    >
                        <div
                            class="flex items-center gap-1.5 text-xs text-muted-foreground"
                        >
                            <component :is="stat.icon" class="size-3.5" />
                            {{ stat.label }}
                        </div>
                        <p class="mt-1 text-xl font-bold">
                            {{ resourceData(stat.key)?.used ?? 0 }}
                            <span
                                class="text-sm font-normal text-muted-foreground"
                            >
                                /
                                {{
                                    resourceData(stat.key)?.unlimited
                                        ? '∞'
                                        : formatLimit(
                                              resourceData(stat.key)?.limit ??
                                                  null,
                                          )
                                }}
                            </span>
                        </p>
                        <!-- Progress bar -->
                        <div class="mt-2 h-1.5 rounded-full bg-muted">
                            <div
                                v-if="!resourceData(stat.key)?.unlimited"
                                class="h-full rounded-full transition-all"
                                :class="
                                    (resourceData(stat.key)?.percentage ?? 0) >=
                                    90
                                        ? 'bg-rose-500'
                                        : 'bg-primary'
                                "
                                :style="`width: ${resourceData(stat.key)?.percentage ?? 0}%`"
                            />
                            <div
                                v-else
                                class="h-full w-1/3 rounded-full bg-gradient-to-r from-primary/50 to-primary/20"
                            />
                        </div>
                    </div>
                </div>

                <!-- Today's KPI row (8 cards: 6 existing + 2 new) -->
                <div
                    v-if="props.stats"
                    class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-8"
                >
                    <!-- Đơn hàng -->
                    <div
                        class="flex items-center gap-2 rounded-xl border border-border bg-background px-3 py-2.5"
                    >
                        <ShoppingCart class="size-4 shrink-0 text-violet-500" />
                        <div class="min-w-0">
                            <p class="text-lg leading-none font-bold">
                                {{ props.stats.orders_today }}
                            </p>
                            <p class="mt-0.5 text-[10px] text-muted-foreground">
                                Đơn hôm nay
                            </p>
                        </div>
                    </div>
                    <!-- Doanh thu + xu hướng -->
                    <div
                        class="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2.5 dark:border-emerald-900/40 dark:bg-emerald-950/20"
                    >
                        <Banknote class="size-4 shrink-0 text-emerald-600" />
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1">
                                <p
                                    class="truncate text-lg leading-none font-bold text-emerald-700 dark:text-emerald-400"
                                >
                                    {{ formatMoney(props.stats.revenue_today) }}
                                </p>
                                <span
                                    v-if="props.stats.revenue_trend !== null"
                                    :class="
                                        props.stats.revenue_trend >= 0
                                            ? 'text-emerald-600'
                                            : 'text-rose-500'
                                    "
                                    class="flex shrink-0 items-center gap-0.5 text-[9px] font-bold"
                                >
                                    <component
                                        :is="
                                            props.stats.revenue_trend >= 0
                                                ? ArrowUp
                                                : ArrowDown
                                        "
                                        class="size-2.5"
                                    />
                                    {{ Math.abs(props.stats.revenue_trend) }}%
                                </span>
                            </div>
                            <p
                                class="mt-0.5 text-[10px] text-emerald-600/70 dark:text-emerald-500"
                            >
                                Doanh thu
                            </p>
                        </div>
                    </div>
                    <!-- Đơn hoàn thành + xu hướng -->
                    <div
                        class="flex items-center gap-2 rounded-xl border border-border bg-background px-3 py-2.5"
                    >
                        <CheckCircle2 class="size-4 shrink-0 text-sky-500" />
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1">
                                <p class="text-lg leading-none font-bold">
                                    {{ props.stats.orders_completed }}
                                </p>
                                <span
                                    v-if="props.stats.order_trend !== null"
                                    :class="
                                        props.stats.order_trend >= 0
                                            ? 'text-emerald-600'
                                            : 'text-rose-500'
                                    "
                                    class="flex shrink-0 items-center gap-0.5 text-[9px] font-bold"
                                >
                                    <component
                                        :is="
                                            props.stats.order_trend >= 0
                                                ? ArrowUp
                                                : ArrowDown
                                        "
                                        class="size-2.5"
                                    />
                                    {{ Math.abs(props.stats.order_trend) }}%
                                </span>
                            </div>
                            <p class="mt-0.5 text-[10px] text-muted-foreground">
                                Hoàn thành
                            </p>
                        </div>
                    </div>
                    <!-- Sản phẩm -->
                    <div
                        class="flex items-center gap-2 rounded-xl border border-border bg-background px-3 py-2.5"
                    >
                        <Package class="size-4 shrink-0 text-amber-500" />
                        <div>
                            <p class="text-lg leading-none font-bold">
                                {{ props.stats.products_count }}
                            </p>
                            <p class="mt-0.5 text-[10px] text-muted-foreground">
                                Sản phẩm
                            </p>
                        </div>
                    </div>
                    <!-- Nhân viên -->
                    <div
                        class="flex items-center gap-2 rounded-xl border border-border bg-background px-3 py-2.5"
                    >
                        <Users class="size-4 shrink-0 text-indigo-500" />
                        <div>
                            <p class="text-lg leading-none font-bold">
                                {{ props.stats.employees_count }}
                            </p>
                            <p class="mt-0.5 text-[10px] text-muted-foreground">
                                Nhân viên
                            </p>
                        </div>
                    </div>
                    <!-- Chi nhánh / Bàn -->
                    <div
                        class="flex items-center gap-2 rounded-xl border border-border bg-background px-3 py-2.5"
                    >
                        <Building2 class="size-4 shrink-0 text-rose-500" />
                        <div>
                            <p class="text-lg leading-none font-bold">
                                {{ props.stats.branches_count }}/{{
                                    props.stats.tables_count
                                }}
                            </p>
                            <p class="mt-0.5 text-[10px] text-muted-foreground">
                                CN / Bàn
                            </p>
                        </div>
                    </div>
                    <!-- MỚI: Biên lợi nhuận hôm nay -->
                    <div
                        class="flex items-center gap-2 rounded-xl border border-violet-200 bg-violet-50 px-3 py-2.5 dark:border-violet-900/40 dark:bg-violet-950/20"
                    >
                        <Percent class="size-4 shrink-0 text-violet-600" />
                        <div>
                            <p
                                class="text-lg leading-none font-bold text-violet-700 dark:text-violet-400"
                            >
                                {{ props.stats.profit_margin_today }}%
                            </p>
                            <p class="mt-0.5 text-[10px] text-violet-600/70">
                                Biên LN
                            </p>
                        </div>
                    </div>
                    <!-- MỚI: Tỉ lệ hoàn thành -->
                    <div
                        :class="[
                            'flex items-center gap-2 rounded-xl border px-3 py-2.5',
                            props.stats.completion_rate >= 80
                                ? 'border-teal-200 bg-teal-50 dark:border-teal-900/40 dark:bg-teal-950/20'
                                : props.stats.completion_rate >= 50
                                  ? 'border-amber-200 bg-amber-50 dark:border-amber-900/40 dark:bg-amber-950/20'
                                  : 'border-rose-200 bg-rose-50 dark:border-rose-900/40 dark:bg-rose-950/20',
                        ]"
                    >
                        <Target
                            class="size-4 shrink-0"
                            :class="
                                props.stats.completion_rate >= 80
                                    ? 'text-teal-600'
                                    : props.stats.completion_rate >= 50
                                      ? 'text-amber-600'
                                      : 'text-rose-600'
                            "
                        />
                        <div>
                            <p
                                class="text-lg leading-none font-bold"
                                :class="
                                    props.stats.completion_rate >= 80
                                        ? 'text-teal-700 dark:text-teal-400'
                                        : props.stats.completion_rate >= 50
                                          ? 'text-amber-700 dark:text-amber-400'
                                          : 'text-rose-700 dark:text-rose-400'
                                "
                            >
                                {{ props.stats.completion_rate }}%
                            </p>
                            <p
                                class="mt-0.5 text-[10px]"
                                :class="
                                    props.stats.completion_rate >= 80
                                        ? 'text-teal-600/70'
                                        : props.stats.completion_rate >= 50
                                          ? 'text-amber-600/70'
                                          : 'text-rose-600/70'
                                "
                            >
                                Tỉ lệ HT
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Business Health Score (hiển thị sau KPI row) -->
                <div
                    v-if="
                        props.healthScore !== null &&
                        props.healthScore !== undefined
                    "
                    :class="[
                        'mt-3 flex items-center gap-4 rounded-xl border px-4 py-3',
                        healthScoreColor.bg,
                    ]"
                >
                    <div class="shrink-0">
                        <div
                            :class="[
                                'text-3xl font-black',
                                healthScoreColor.text,
                            ]"
                        >
                            {{ props.healthScore }}
                        </div>
                        <div class="text-[10px] text-muted-foreground">
                            / 100
                        </div>
                    </div>
                    <div class="flex-1">
                        <div class="mb-1 flex items-center justify-between">
                            <span class="text-xs font-bold"
                                >Sức khoẻ kinh doanh hôm nay</span
                            >
                            <span
                                :class="[
                                    'rounded-full px-2 py-0.5 text-[10px] font-bold',
                                    healthScoreColor.text,
                                    healthScoreColor.bg,
                                ]"
                            >
                                {{ healthScoreColor.label }}
                            </span>
                        </div>
                        <div
                            class="h-2 overflow-hidden rounded-full bg-white/50 dark:bg-black/20"
                        >
                            <div
                                :class="[
                                    'h-full rounded-full transition-all duration-700',
                                    healthScoreColor.bar,
                                ]"
                                :style="`width: ${props.healthScore}%`"
                            />
                        </div>
                        <div
                            class="mt-1.5 flex flex-wrap gap-x-4 gap-y-0.5 text-[10px] text-muted-foreground"
                        >
                            <span
                                >✓ Tỉ lệ HT:
                                {{ props.stats?.completion_rate ?? 0 }}%</span
                            >
                            <span
                                >✓ Biên LN:
                                {{
                                    props.stats?.profit_margin_today ?? 0
                                }}%</span
                            >
                            <span v-if="props.stats?.revenue_trend !== null">
                                {{
                                    (props.stats?.revenue_trend ?? 0) >= 0
                                        ? '↑'
                                        : '↓'
                                }}
                                Doanh thu {{ props.stats?.revenue_trend }}% so
                                hôm qua
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── Quick actions ─────────────────────────────────────── -->
        <section class="px-4 py-8 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <h2 class="text-base font-semibold">Truy cập nhanh</h2>
                <div
                    class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6"
                >
                    <Link
                        v-for="action in quickActions"
                        :key="action.label"
                        :href="action.href"
                        class="group flex flex-col items-center gap-2.5 rounded-xl border border-border bg-card p-4 text-center transition-all hover:border-primary/30 hover:bg-muted/40 hover:shadow-sm"
                    >
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-xl border transition-all group-hover:scale-110"
                            :class="{
                                'border-amber-100 bg-amber-50 text-amber-600 dark:border-amber-900/30 dark:bg-amber-950/20 dark:text-amber-400':
                                    action.color === 'amber',
                                'border-emerald-100 bg-emerald-50 text-emerald-600 dark:border-emerald-900/30 dark:bg-emerald-950/20 dark:text-emerald-400':
                                    action.color === 'emerald',
                                'border-sky-100 bg-sky-50 text-sky-600 dark:border-sky-900/30 dark:bg-sky-950/20 dark:text-sky-400':
                                    action.color === 'sky',
                                'border-rose-100 bg-rose-50 text-rose-600 dark:border-rose-900/30 dark:bg-rose-950/20 dark:text-rose-400':
                                    action.color === 'rose',
                                'border-violet-100 bg-violet-50 text-violet-600 dark:border-violet-900/30 dark:bg-violet-950/20 dark:text-violet-400':
                                    action.color === 'violet',
                                'border-teal-100 bg-teal-50 text-teal-600 dark:border-teal-900/30 dark:bg-teal-950/20 dark:text-teal-400':
                                    action.color === 'teal',
                                'border-slate-100 bg-slate-50 text-slate-600 dark:border-slate-900/30 dark:bg-slate-950/20 dark:text-slate-400':
                                    action.color === 'slate',
                            }"
                        >
                            <component :is="action.icon" class="size-5" />
                        </div>
                        <div>
                            <p class="text-sm font-semibold">
                                {{ action.label }}
                            </p>
                            <p
                                class="mt-0.5 text-xs leading-snug text-muted-foreground"
                            >
                                {{ action.description }}
                            </p>
                        </div>
                    </Link>
                </div>
            </div>
        </section>

        <!-- ── Phân tích & Thống kê (Biểu đồ) ────────────────────── -->
        <section class="px-4 pb-8 lg:px-8">
            <div class="mx-auto max-w-7xl space-y-6">
                <!-- Row 1: Sales Chart & Channels Doughnut -->
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <!-- Sales & Orders Chart (2/3) -->
                    <Card
                        class="relative overflow-hidden border border-border bg-card text-card-foreground shadow-sm lg:col-span-2"
                    >
                        <CardHeader class="border-b border-border/50 pb-2">
                            <div class="flex items-center justify-between">
                                <div>
                                    <CardTitle
                                        class="flex items-center gap-2 text-base font-bold"
                                    >
                                        <TrendingUp
                                            class="size-4 text-violet-500"
                                        />
                                        Doanh thu 7 ngày qua + 7 ngày dự báo
                                    </CardTitle>
                                    <p
                                        class="mt-0.5 text-xs text-muted-foreground"
                                    >
                                        Cột đặc = thực tế · Cột mờ =
                                        <span
                                            class="font-medium text-indigo-400"
                                            >AI dự báo</span
                                        >
                                        <span
                                            v-if="
                                                forecastData?.confidence_label
                                            "
                                            class="ml-1 inline-flex items-center gap-0.5 rounded bg-indigo-100 px-1.5 py-0.5 text-[9px] font-semibold text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-400"
                                        >
                                            <Sparkles class="size-2.5" /> Độ tin
                                            cậy:
                                            {{ forecastData.confidence_label }}
                                        </span>
                                    </p>
                                </div>
                                <div
                                    class="flex items-center gap-4 text-xs font-semibold"
                                >
                                    <span
                                        class="flex items-center gap-1.5 text-indigo-500"
                                    >
                                        <span
                                            class="h-3 w-3 rounded bg-indigo-500/80"
                                        ></span>
                                        Doanh thu
                                    </span>
                                    <span
                                        class="flex items-center gap-1.5 text-emerald-500"
                                    >
                                        <span
                                            class="h-3 w-3 rounded-full border-2 border-emerald-500 bg-background"
                                        ></span>
                                        Đơn hàng
                                    </span>
                                </div>
                            </div>
                        </CardHeader>

                        <CardContent class="pt-6">
                            <div
                                v-if="revenueChartList.length"
                                class="relative"
                            >
                                <!-- Tooltip Floating -->
                                <div
                                    v-if="activeHoverIndex !== null"
                                    class="pointer-events-none absolute z-20 rounded-xl border border-slate-700/50 bg-slate-900/95 p-3 text-xs text-white shadow-xl backdrop-blur-md transition-all duration-150"
                                    :style="{
                                        left: `${(activeHoverIndex / revenueChartList.length) * 85 + 12}%`,
                                        transform: 'translateX(-50%)',
                                        top: '-15px',
                                    }"
                                >
                                    <p
                                        class="mb-1 border-b border-slate-700 pb-1 font-bold text-slate-300"
                                    >
                                        Ngày
                                        {{
                                            revenueChartList[activeHoverIndex]
                                                .date
                                        }}
                                    </p>
                                    <p class="flex justify-between gap-4">
                                        <span class="text-slate-400"
                                            >Doanh thu:</span
                                        >
                                        <span
                                            class="font-mono font-bold text-indigo-400"
                                            >{{
                                                formatMoneyFull(
                                                    revenueChartList[
                                                        activeHoverIndex
                                                    ].revenue,
                                                )
                                            }}</span
                                        >
                                    </p>
                                    <p
                                        class="mt-0.5 flex justify-between gap-4"
                                    >
                                        <span class="text-slate-400"
                                            >Đơn hàng:</span
                                        >
                                        <span
                                            class="font-mono font-bold text-emerald-400"
                                            >{{
                                                revenueChartList[
                                                    activeHoverIndex
                                                ].orders
                                            }}
                                            đơn</span
                                        >
                                    </p>
                                </div>

                                <!-- The Chart SVG -->
                                <svg
                                    viewBox="0 0 700 200"
                                    class="h-48 w-full overflow-visible"
                                >
                                    <!-- Y-Axis Grid Lines -->
                                    <line
                                        x1="50"
                                        y1="20"
                                        x2="650"
                                        y2="20"
                                        class="stroke-muted/30 stroke-1"
                                        stroke-dasharray="4"
                                    />
                                    <line
                                        x1="50"
                                        y1="90"
                                        x2="650"
                                        y2="90"
                                        class="stroke-muted/30 stroke-1"
                                        stroke-dasharray="4"
                                    />
                                    <line
                                        x1="50"
                                        y1="160"
                                        x2="650"
                                        y2="160"
                                        class="stroke-muted"
                                        stroke-width="1.5"
                                    />

                                    <!-- Bars and Points -->
                                    <g
                                        v-for="(day, i) in revenueChartList"
                                        :key="day.date"
                                    >
                                        <!-- Interactive background column area for hovering -->
                                        <rect
                                            :x="i * 85 + 50"
                                            y="10"
                                            width="60"
                                            height="150"
                                            class="cursor-pointer rounded fill-transparent transition-colors duration-150 hover:fill-muted/20"
                                            @mouseenter="activeHoverIndex = i"
                                            @mouseleave="
                                                activeHoverIndex = null
                                            "
                                        />

                                        <!-- Revenue Bar (forecast = mờ hơn, có stroke-dasharray) -->
                                        <rect
                                            :x="i * 85 + 65"
                                            :y="
                                                160 -
                                                (day.revenue / maxRevenue) * 130
                                            "
                                            width="30"
                                            :height="
                                                Math.max(
                                                    (day.revenue / maxRevenue) *
                                                        130,
                                                    day.revenue > 0 ? 4 : 0,
                                                )
                                            "
                                            rx="4"
                                            :class="
                                                (day as any).is_forecast
                                                    ? 'fill-indigo-300/50 stroke-indigo-400 stroke-1 dark:fill-indigo-400/30'
                                                    : 'pointer-events-none fill-indigo-500/80 transition-colors duration-150 hover:fill-indigo-600 dark:fill-indigo-500/60 dark:hover:fill-indigo-500'
                                            "
                                            :stroke-dasharray="
                                                (day as any).is_forecast
                                                    ? '4 2'
                                                    : 'none'
                                            "
                                        />
                                    </g>

                                    <!-- SVG Orders Line Trend Overlay -->
                                    <path
                                        :d="ordersLinePath"
                                        fill="none"
                                        class="stroke-emerald-500"
                                        stroke-width="3"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />

                                    <!-- Dots on line for each day -->
                                    <g
                                        v-for="(day, i) in revenueChartList"
                                        :key="'dot-' + day.date"
                                    >
                                        <circle
                                            :cx="i * 85 + 80"
                                            :cy="
                                                160 -
                                                (day.orders / maxOrders) * 125
                                            "
                                            r="5"
                                            class="pointer-events-none fill-background stroke-emerald-500 stroke-2"
                                        />
                                        <!-- Glowing effect circle when hovered -->
                                        <circle
                                            v-if="activeHoverIndex === i"
                                            :cx="i * 85 + 80"
                                            :cy="
                                                160 -
                                                (day.orders / maxOrders) * 125
                                            "
                                            r="9"
                                            class="pointer-events-none animate-ping fill-emerald-500/30 stroke-none"
                                        />
                                    </g>

                                    <!-- X-Axis Labels -->
                                    <text
                                        v-for="(day, i) in revenueChartList"
                                        :key="'txt-' + day.date"
                                        :x="i * 85 + 80"
                                        y="182"
                                        text-anchor="middle"
                                        class="fill-muted-foreground font-sans text-[10px] font-medium"
                                    >
                                        {{ day.date }}
                                    </text>
                                </svg>
                            </div>
                            <div
                                v-else
                                class="flex flex-col items-center justify-center py-16 text-sm text-muted-foreground"
                            >
                                <BarChart3
                                    class="mb-2 size-8 text-muted-foreground/30"
                                />
                                Chưa có dữ liệu doanh số tuần này.
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Channels Doughnut Chart (1/3) -->
                    <Card
                        class="border border-border bg-card text-card-foreground shadow-sm"
                    >
                        <CardHeader class="border-b border-border/50 pb-2">
                            <CardTitle
                                class="flex items-center gap-2 text-base font-bold"
                            >
                                <Utensils class="size-4 text-amber-500" />
                                Tỷ lệ kênh bán hàng
                            </CardTitle>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                Cơ cấu đơn hàng theo kênh dịch vụ
                            </p>
                        </CardHeader>

                        <CardContent
                            class="flex flex-col items-center justify-center pt-6"
                        >
                            <div
                                v-if="doughnutPaths.length"
                                class="flex w-full flex-col items-center gap-6"
                            >
                                <!-- Doughnut SVG -->
                                <div
                                    class="relative flex h-36 w-36 items-center justify-center"
                                >
                                    <svg
                                        viewBox="0 0 200 200"
                                        class="h-full w-full -rotate-90 transform"
                                    >
                                        <g
                                            v-for="slice in doughnutPaths"
                                            :key="slice.label"
                                        >
                                            <path
                                                :d="slice.path"
                                                :fill="slice.color"
                                                class="cursor-pointer stroke-card stroke-2 transition-opacity hover:opacity-90"
                                            />
                                            <title>
                                                {{ slice.label }}:
                                                {{ slice.count }} đơn ({{
                                                    slice.percentage
                                                }}%)
                                            </title>
                                        </g>
                                        <!-- Central hole -->
                                        <circle
                                            cx="100"
                                            cy="100"
                                            r="62"
                                            class="fill-card"
                                        />
                                    </svg>
                                    <!-- Central statistics overlay -->
                                    <div
                                        class="absolute flex flex-col items-center justify-center text-center"
                                    >
                                        <span
                                            class="text-2xl leading-none font-bold"
                                            >{{
                                                channelChartList.reduce(
                                                    (sum, item) =>
                                                        sum + item.count,
                                                    0,
                                                )
                                            }}</span
                                        >
                                        <span
                                            class="mt-1 text-[10px] font-medium text-muted-foreground"
                                            >Tổng đơn</span
                                        >
                                    </div>
                                </div>

                                <!-- Custom Legends -->
                                <div
                                    class="grid w-full grid-cols-2 gap-3 text-xs"
                                >
                                    <div
                                        v-for="slice in doughnutPaths"
                                        :key="slice.label"
                                        class="flex items-center gap-2 rounded-lg border border-border bg-muted/20 px-2 py-1.5"
                                    >
                                        <div
                                            class="h-2.5 w-2.5 shrink-0 animate-pulse rounded-full"
                                            :style="{
                                                backgroundColor: slice.color,
                                            }"
                                        ></div>
                                        <span
                                            class="truncate font-medium text-foreground"
                                            >{{ slice.label }}</span
                                        >
                                        <span
                                            class="ml-auto font-mono text-[10px] font-bold text-muted-foreground"
                                            >{{ slice.percentage }}%</span
                                        >
                                    </div>
                                </div>
                            </div>

                            <div
                                v-else
                                class="flex w-full flex-col items-center justify-center py-16 text-sm text-muted-foreground"
                            >
                                <ShoppingCart
                                    class="mb-2 size-8 text-muted-foreground/30"
                                />
                                Chưa có dữ liệu kênh bán hàng.
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Row 2: Top Products & AI Suggestion -->
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <!-- Top Products Leaderboard (2/3) -->
                    <Card
                        class="border border-border bg-card text-card-foreground shadow-sm lg:col-span-2"
                    >
                        <CardHeader class="border-b border-border/50 pb-2">
                            <CardTitle
                                class="flex items-center gap-2 text-base font-bold"
                            >
                                <Package class="size-4 text-emerald-500" />
                                Top 5 món bán chạy nhất (30 ngày qua)
                            </CardTitle>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                Xếp hạng theo số lượng phần ăn phục vụ thành
                                công
                            </p>
                        </CardHeader>

                        <CardContent class="space-y-4 pt-6">
                            <div
                                v-if="topProductsList.length"
                                class="space-y-4"
                            >
                                <div
                                    v-for="(p, idx) in topProductsList"
                                    :key="p.name"
                                    class="group space-y-1.5"
                                >
                                    <div
                                        class="flex items-center justify-between text-xs font-semibold"
                                    >
                                        <div
                                            class="flex min-w-0 items-center gap-2"
                                        >
                                            <span
                                                class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[10px] font-extrabold"
                                                :class="{
                                                    'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400':
                                                        idx === 0,
                                                    'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400':
                                                        idx === 1,
                                                    'bg-orange-100 text-orange-700 dark:bg-orange-950/40 dark:text-orange-400':
                                                        idx === 2,
                                                    'bg-muted text-muted-foreground':
                                                        idx > 2,
                                                }"
                                            >
                                                {{ idx + 1 }}
                                            </span>
                                            <span
                                                class="truncate text-foreground transition-colors group-hover:text-primary"
                                                >{{ p.name }}</span
                                            >
                                        </div>
                                        <div
                                            class="flex shrink-0 items-center gap-3 font-mono text-muted-foreground"
                                        >
                                            <span
                                                class="font-bold text-foreground"
                                                >{{ p.quantity }} phần</span
                                            >
                                            <span>·</span>
                                            <span
                                                class="font-bold text-emerald-600 dark:text-emerald-400"
                                                >{{
                                                    formatMoneyFull(p.revenue)
                                                }}</span
                                            >
                                        </div>
                                    </div>
                                    <!-- Sleek Progress Bar -->
                                    <div
                                        class="h-2 overflow-hidden rounded-full bg-muted"
                                    >
                                        <div
                                            class="h-full rounded-full bg-gradient-to-r transition-all duration-500"
                                            :class="{
                                                'from-amber-400 to-amber-500':
                                                    idx === 0,
                                                'from-indigo-400 to-indigo-500':
                                                    idx === 1,
                                                'from-emerald-400 to-emerald-500':
                                                    idx === 2,
                                                'from-slate-400 to-slate-500':
                                                    idx > 2,
                                            }"
                                            :style="{
                                                width: `${(p.quantity / maxProductQty) * 100}%`,
                                            }"
                                        />
                                    </div>
                                </div>
                            </div>

                            <div
                                v-else
                                class="flex flex-col items-center justify-center py-16 text-sm text-muted-foreground"
                            >
                                <Package
                                    class="mb-2 size-8 text-muted-foreground/30"
                                />
                                Chưa có dữ liệu sản phẩm bán chạy.
                            </div>
                        </CardContent>
                    </Card>

                    <!-- AI Insights + Forecast (1/3) -->
                    <div class="space-y-4">
                        <!-- Dự báo doanh thu ngày mai -->
                        <Card
                            v-if="forecastData"
                            class="overflow-hidden border border-indigo-200 bg-gradient-to-br from-indigo-50 to-violet-50 shadow-sm dark:border-indigo-800/40 dark:from-indigo-950/20 dark:to-violet-950/20"
                        >
                            <CardHeader
                                class="border-b border-indigo-100 pb-2 dark:border-indigo-800/30"
                            >
                                <CardTitle
                                    class="flex items-center gap-2 text-sm font-bold text-indigo-700 dark:text-indigo-300"
                                >
                                    <Sparkles
                                        class="size-4 animate-pulse text-indigo-500"
                                    />
                                    Dự báo doanh thu ngày mai
                                </CardTitle>
                                <p
                                    class="text-[10px] text-indigo-500/80 dark:text-indigo-400/70"
                                >
                                    {{ forecastData.day_label }}
                                </p>
                            </CardHeader>
                            <CardContent class="pt-4 text-xs">
                                <div class="mb-3 flex items-end gap-3">
                                    <div>
                                        <p
                                            class="text-2xl font-black text-indigo-700 dark:text-indigo-300"
                                        >
                                            {{
                                                formatMoney(forecastData.amount)
                                            }}
                                        </p>
                                        <p
                                            class="mt-0.5 text-[10px] text-indigo-500/70"
                                        >
                                            Dựa trên
                                            {{ forecastData.samples }} tuần lịch
                                            sử
                                        </p>
                                    </div>
                                    <span
                                        :class="[
                                            'mb-1 rounded-full border px-2 py-0.5 text-[9px] font-bold',
                                            forecastData.confidence === 'high'
                                                ? 'border-emerald-200 bg-emerald-100 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400'
                                                : forecastData.confidence ===
                                                    'medium'
                                                  ? 'border-amber-200 bg-amber-100 text-amber-700 dark:bg-amber-950/30 dark:text-amber-400'
                                                  : 'border-rose-200 bg-rose-100 text-rose-700 dark:bg-rose-950/30 dark:text-rose-400',
                                        ]"
                                    >
                                        Tin cậy:
                                        {{ forecastData.confidence_label }}
                                    </span>
                                </div>
                                <!-- So sánh với hôm nay -->
                                <div
                                    v-if="
                                        props.stats?.revenue_today &&
                                        forecastData.amount > 0
                                    "
                                    class="mt-1"
                                >
                                    <div
                                        class="mb-1 flex items-center justify-between text-[10px] text-indigo-500/80"
                                    >
                                        <span>Hôm nay</span>
                                        <span>Dự báo ngày mai</span>
                                    </div>
                                    <div
                                        class="h-2 overflow-hidden rounded-full bg-indigo-100 dark:bg-indigo-900/30"
                                    >
                                        <div
                                            class="h-full rounded-full bg-indigo-500 transition-all"
                                            :style="`width: ${Math.min(100, (props.stats.revenue_today / forecastData.amount) * 100)}%`"
                                        />
                                    </div>
                                    <p
                                        class="mt-1 text-[10px] text-indigo-500/70"
                                    >
                                        Hôm nay đạt
                                        {{
                                            Math.min(
                                                100,
                                                Math.round(
                                                    (props.stats.revenue_today /
                                                        forecastData.amount) *
                                                        100,
                                                ),
                                            )
                                        }}% mục tiêu dự báo
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- AI Insights Card -->
                        <Card
                            class="relative overflow-hidden border border-border bg-card text-card-foreground shadow-sm"
                        >
                            <div
                                class="absolute -right-10 -bottom-10 h-32 w-32 rounded-full bg-indigo-500/5 blur-2xl"
                            ></div>
                            <CardHeader
                                class="border-b border-border/50 bg-muted/20 pb-2"
                            >
                                <CardTitle
                                    class="flex items-center gap-2 text-base font-bold"
                                >
                                    <Brain
                                        class="size-4.5 animate-pulse text-indigo-500"
                                    />
                                    Trợ lý AI Aventura
                                </CardTitle>
                                <p class="mt-0.5 text-xs text-muted-foreground">
                                    Phân tích & Khuyến nghị tự động
                                </p>
                            </CardHeader>
                            <CardContent
                                class="relative z-10 space-y-3 pt-4 text-xs"
                            >
                                <div
                                    v-if="
                                        topProductsList.length &&
                                        doughnutPaths.length
                                    "
                                    class="space-y-3"
                                >
                                    <div class="flex items-start gap-2">
                                        <div
                                            class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded bg-indigo-500/10 text-indigo-500"
                                        >
                                            <Utensils class="size-3" />
                                        </div>
                                        <p
                                            class="leading-normal text-muted-foreground"
                                        >
                                            Kênh
                                            <strong class="text-foreground">{{
                                                topChannelLabel
                                            }}</strong>
                                            chiếm tỉ trọng đơn hàng lớn nhất.
                                        </p>
                                    </div>
                                    <div class="flex items-start gap-2">
                                        <div
                                            class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded bg-emerald-500/10 text-emerald-500"
                                        >
                                            <Trophy class="size-3" />
                                        </div>
                                        <p
                                            class="leading-normal text-muted-foreground"
                                        >
                                            Món bán chạy nhất:
                                            <strong class="text-foreground">{{
                                                topDishName
                                            }}</strong
                                            >.
                                        </p>
                                    </div>
                                    <div
                                        class="flex items-start gap-2 rounded-xl border border-indigo-500/10 bg-indigo-500/5 p-3 dark:bg-indigo-950/20"
                                    >
                                        <span class="text-sm">💡</span>
                                        <p
                                            class="leading-relaxed font-medium text-indigo-700 dark:text-indigo-300"
                                        >
                                            Gợi ý: Thiết lập combo kèm
                                            <strong>{{ topDishName }}</strong>
                                            trên QR Menu để tăng giá trị trung
                                            bình đơn hàng.
                                        </p>
                                    </div>
                                </div>
                                <div
                                    v-else
                                    class="flex flex-col items-center py-10 text-center text-muted-foreground"
                                >
                                    <Brain
                                        class="mb-2 size-8 text-muted-foreground/30"
                                    />
                                    <p class="text-xs">
                                        Đang thu thập dữ liệu...
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── News + Right sidebar ──────────────────────────────── -->
        <section class="px-4 pb-14 lg:px-8">
            <div class="mx-auto grid max-w-7xl gap-6 lg:grid-cols-3">
                <!-- Bảng điều khiển Vận hành (2/3) -->
                <div class="space-y-4 lg:col-span-2">
                    <div
                        class="flex flex-col justify-between gap-3 border-b border-border/60 pb-1 sm:flex-row sm:items-center"
                    >
                        <div>
                            <h2
                                class="flex items-center gap-2 text-base font-bold"
                            >
                                <span class="relative flex h-2.5 w-2.5">
                                    <span
                                        class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"
                                    ></span>
                                    <span
                                        class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"
                                    ></span>
                                </span>
                                Trung tâm Giám sát Vận hành
                            </h2>
                            <p class="text-xs text-muted-foreground">
                                Theo dõi hoạt động, trạng thái bàn và cảnh báo
                                thời gian thực
                            </p>
                        </div>

                        <!-- Tab Switcher -->
                        <div
                            class="flex flex-wrap self-start rounded-xl border border-border bg-muted/50 p-0.5 text-xs text-muted-foreground shadow-inner sm:self-auto"
                        >
                            <button
                                @click="activeTab = 'feed'"
                                :class="
                                    activeTab === 'feed'
                                        ? 'bg-background font-semibold text-foreground shadow-sm'
                                        : 'hover:text-foreground'
                                "
                                class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 transition-all"
                            >
                                <Activity class="size-3.5" />
                                Nhật ký
                            </button>
                            <button
                                @click="activeTab = 'tables'"
                                :class="
                                    activeTab === 'tables'
                                        ? 'bg-background font-semibold text-foreground shadow-sm'
                                        : 'hover:text-foreground'
                                "
                                class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 transition-all"
                            >
                                <BarChart3 class="size-3.5" />
                                Sơ đồ Bàn
                            </button>
                            <button
                                @click="activeTab = 'inventory'"
                                :class="
                                    activeTab === 'inventory'
                                        ? 'bg-background font-semibold text-foreground shadow-sm'
                                        : 'hover:text-foreground'
                                "
                                class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 transition-all"
                            >
                                <AlertTriangle class="size-3.5" />
                                Tồn kho
                                <span
                                    v-if="lowStockList.length"
                                    class="flex h-4 w-4 scale-90 items-center justify-center rounded-full bg-rose-500 text-[9px] leading-none font-bold text-white"
                                >
                                    {{ lowStockList.length }}
                                </span>
                            </button>
                            <button
                                @click="activeTab = 'owner'"
                                :class="
                                    activeTab === 'owner'
                                        ? 'bg-background font-semibold text-foreground shadow-sm'
                                        : 'hover:text-foreground'
                                "
                                class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 transition-all"
                            >
                                <Crown class="size-3.5 text-amber-500" />
                                Chủ quán
                            </button>
                        </div>
                    </div>

                    <!-- TAB Content: Live Activity Feed -->
                    <div
                        v-if="activeTab === 'feed'"
                        class="animate-in space-y-3 duration-200 fade-in-50"
                    >
                        <div
                            v-if="operationFeedList.length > 0"
                            class="relative space-y-4 border-l border-border/80 py-2 pl-4"
                        >
                            <div
                                v-for="(item, idx) in operationFeedList"
                                :key="idx"
                                class="group relative flex gap-4 rounded-2xl border border-border bg-card p-3.5 shadow-sm transition-all hover:border-primary/20 hover:shadow-md"
                            >
                                <!-- Line pointer dot -->
                                <div
                                    class="absolute top-7 -left-[21px] h-2.5 w-2.5 rounded-full border-2 border-background"
                                    :class="{
                                        'bg-amber-500 ring-4 ring-amber-500/20':
                                            item.color === 'amber',
                                        'bg-violet-500 ring-4 ring-violet-500/20':
                                            item.color === 'violet',
                                        'bg-emerald-500 ring-4 ring-emerald-500/20':
                                            item.color === 'emerald',
                                        'bg-rose-500 ring-4 ring-rose-500/20':
                                            item.color === 'rose',
                                        'bg-sky-500 ring-4 ring-sky-500/20':
                                            item.color === 'sky',
                                    }"
                                ></div>

                                <!-- Icon -->
                                <div
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border transition-transform group-hover:scale-105"
                                    :class="getFeedColorClasses(item.color)"
                                >
                                    <component
                                        :is="getFeedIcon(item.icon)"
                                        class="size-4.5"
                                    />
                                </div>

                                <!-- Content -->
                                <div class="min-w-0 flex-1">
                                    <div
                                        class="flex items-center justify-between gap-2"
                                    >
                                        <h4
                                            class="truncate text-sm font-semibold text-foreground"
                                        >
                                            {{ item.title }}
                                        </h4>
                                        <span
                                            class="shrink-0 font-mono text-[10px] font-medium text-muted-foreground"
                                            >{{ item.time }}</span
                                        >
                                    </div>
                                    <p
                                        class="mt-1 text-xs leading-relaxed text-muted-foreground"
                                    >
                                        {{ item.description }}
                                    </p>
                                </div>

                                <!-- Link button or details arrow -->
                                <div
                                    class="flex shrink-0 items-center justify-center self-center opacity-0 transition-opacity group-hover:opacity-100"
                                >
                                    <Link
                                        :href="item.link"
                                        class="rounded-lg p-1 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                    >
                                        <ChevronRight class="size-4" />
                                    </Link>
                                </div>
                            </div>
                        </div>
                        <div
                            v-else
                            class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-muted/20 py-16 text-center"
                        >
                            <Activity
                                class="mb-2.5 size-10 animate-pulse text-muted-foreground/30"
                            />
                            <p
                                class="text-sm font-medium text-muted-foreground"
                            >
                                Chưa ghi nhận hoạt động vận hành nào hôm nay
                            </p>
                        </div>
                    </div>

                    <!-- TAB Content: Live Table Grid Monitor -->
                    <div
                        v-else-if="activeTab === 'tables'"
                        class="animate-in space-y-4 duration-200 fade-in-50"
                    >
                        <div
                            v-if="tablesList.length > 0"
                            class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4"
                        >
                            <div
                                v-for="table in tablesList"
                                :key="table.id"
                                class="group relative flex cursor-pointer flex-col gap-2 rounded-2xl border border-border bg-card p-3 shadow-sm transition-all hover:border-primary/20 hover:shadow-md"
                            >
                                <div
                                    class="flex items-start justify-between gap-1.5"
                                >
                                    <span
                                        class="truncate text-[10px] font-medium text-muted-foreground"
                                        >{{ table.area }}</span
                                    >
                                    <!-- Status dot/indicator -->
                                    <span
                                        class="mt-1 h-2 w-2 shrink-0 rounded-full"
                                        :class="{
                                            'animate-pulse bg-emerald-500':
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

                                <div class="mt-0.5">
                                    <h4
                                        class="flex items-center gap-1.5 text-sm font-bold text-foreground transition-colors group-hover:text-primary"
                                    >
                                        <Utensils
                                            class="size-3.5 text-muted-foreground"
                                        />
                                        {{ table.name }}
                                    </h4>
                                </div>

                                <div
                                    class="mt-auto flex items-center justify-between gap-2 border-t border-border/40 pt-1.5"
                                >
                                    <span
                                        class="flex items-center gap-1 text-[10px] text-muted-foreground"
                                    >
                                        <Users class="size-3" />
                                        {{ table.capacity }} chỗ
                                    </span>
                                    <span
                                        class="rounded-full border px-2 py-0.5 text-[10px] font-semibold"
                                        :class="
                                            getTableStatusInfo(table.status)
                                                .class
                                        "
                                    >
                                        {{
                                            getTableStatusInfo(table.status)
                                                .label
                                        }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div
                            v-else
                            class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-muted/20 py-16 text-center"
                        >
                            <BarChart3
                                class="mb-2.5 size-10 text-muted-foreground/30"
                            />
                            <p
                                class="text-sm font-medium text-muted-foreground"
                            >
                                Không tìm thấy thông tin bàn ăn nào
                            </p>
                        </div>
                    </div>

                    <!-- TAB Content: Low Stock Alerts -->
                    <div
                        v-else-if="activeTab === 'inventory'"
                        class="animate-in space-y-3 duration-200 fade-in-50"
                    >
                        <div
                            v-if="lowStockList.length > 0"
                            class="grid gap-3 sm:grid-cols-2"
                        >
                            <div
                                v-for="item in lowStockList"
                                :key="item.id"
                                class="flex flex-col gap-2 rounded-2xl border border-rose-100 bg-rose-50/10 p-3.5 shadow-sm transition-all hover:shadow-md dark:border-rose-950/20 dark:bg-rose-950/5"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div class="min-w-0">
                                        <h4
                                            class="flex items-center gap-2 truncate text-sm font-bold text-foreground"
                                        >
                                            <Package
                                                class="size-4 text-rose-500"
                                            />
                                            {{ item.ingredient_name }}
                                        </h4>
                                        <p
                                            class="mt-0.5 text-xs text-muted-foreground"
                                        >
                                            Mức tối thiểu:
                                            {{ item.min_stock_level }}
                                            {{ item.unit_name }}
                                        </p>
                                    </div>
                                    <Link
                                        href="/inventory"
                                        class="flex shrink-0 items-center gap-0.5 text-xs font-semibold text-rose-600 hover:text-rose-700 hover:underline"
                                    >
                                        Nhập hàng
                                        <ChevronRight class="size-3" />
                                    </Link>
                                </div>

                                <!-- Custom Progress Bar representing Stock levels -->
                                <div class="mt-2.5 space-y-1">
                                    <div
                                        class="flex justify-between text-[10px] font-semibold text-muted-foreground"
                                    >
                                        <span>Tồn kho hiện tại</span>
                                        <span
                                            class="font-mono font-bold text-rose-600 dark:text-rose-400"
                                            >{{ item.quantity_on_hand }} /
                                            {{ item.reorder_level }}
                                            {{ item.unit_name }}</span
                                        >
                                    </div>
                                    <div
                                        class="h-2 overflow-hidden rounded-full bg-muted"
                                    >
                                        <div
                                            class="h-full rounded-full bg-rose-500 transition-all duration-500"
                                            :style="{
                                                width: `${Math.min((item.quantity_on_hand / Math.max(item.reorder_level, 1)) * 100, 100)}%`,
                                            }"
                                        ></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div
                            v-else
                            class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-emerald-500/5 py-12 text-center"
                        >
                            <div
                                class="mb-3 flex h-12 w-12 animate-bounce items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-950/20 dark:text-emerald-400"
                            >
                                <CheckCircle2 class="size-6" />
                            </div>
                            <h4 class="text-sm font-bold text-foreground">
                                Tất cả nguyên liệu an toàn!
                            </h4>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Không ghi nhận nguyên liệu nào đang ở mức báo
                                động tồn kho
                            </p>
                        </div>
                    </div>

                    <!-- TAB Content: Tổng quan Chủ quán -->
                    <div
                        v-if="activeTab === 'owner'"
                        class="animate-in space-y-4 duration-200 fade-in-50"
                    >
                        <div v-if="ownerSummary" class="space-y-4">
                            <!-- Doanh thu tuần này vs tuần trước -->
                            <div class="grid grid-cols-2 gap-3">
                                <div
                                    class="rounded-xl border border-indigo-200 bg-indigo-50 p-3 dark:border-indigo-800/40 dark:bg-indigo-950/20"
                                >
                                    <p
                                        class="text-[10px] font-bold tracking-wider text-indigo-500 uppercase"
                                    >
                                        Tuần này
                                    </p>
                                    <p
                                        class="mt-1 text-xl font-black text-indigo-700 dark:text-indigo-300"
                                    >
                                        {{
                                            formatMoney(
                                                ownerSummary.revenue_this_week,
                                            )
                                        }}
                                    </p>
                                </div>
                                <div
                                    class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-900/40"
                                >
                                    <p
                                        class="text-[10px] font-bold tracking-wider text-slate-500 uppercase"
                                    >
                                        Tuần trước
                                    </p>
                                    <p
                                        class="mt-1 text-xl font-black text-slate-600 dark:text-slate-400"
                                    >
                                        {{
                                            formatMoney(
                                                ownerSummary.revenue_last_week,
                                            )
                                        }}
                                    </p>
                                    <p
                                        v-if="
                                            ownerSummary.revenue_last_week > 0
                                        "
                                        class="mt-0.5 text-[10px]"
                                        :class="
                                            ownerSummary.revenue_this_week >=
                                            ownerSummary.revenue_last_week
                                                ? 'text-emerald-600'
                                                : 'text-rose-500'
                                        "
                                    >
                                        {{
                                            ownerSummary.revenue_this_week >=
                                            ownerSummary.revenue_last_week
                                                ? '↑'
                                                : '↓'
                                        }}
                                        {{
                                            Math.abs(
                                                Math.round(
                                                    ((ownerSummary.revenue_this_week -
                                                        ownerSummary.revenue_last_week) /
                                                        ownerSummary.revenue_last_week) *
                                                        100,
                                                ),
                                            )
                                        }}%
                                    </p>
                                </div>
                            </div>

                            <!-- Cảnh báo đơn chờ lâu -->
                            <div
                                v-if="ownerSummary.pending_over_20min > 0"
                                class="flex items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs dark:border-amber-800/40 dark:bg-amber-950/20"
                            >
                                <AlertTriangle
                                    class="size-4 shrink-0 text-amber-500"
                                />
                                <span
                                    class="font-semibold text-amber-700 dark:text-amber-400"
                                >
                                    {{ ownerSummary.pending_over_20min }} đơn
                                    chờ quá 20 phút chưa xử lý
                                </span>
                                <Link
                                    href="/orders?status=pending"
                                    class="ml-auto shrink-0 text-amber-600 hover:underline"
                                    >Xem →</Link
                                >
                            </div>

                            <!-- Top 3 sản phẩm hôm nay -->
                            <div>
                                <p
                                    class="mb-2 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    🏆 Top sản phẩm hôm nay
                                </p>
                                <div
                                    v-if="
                                        ownerSummary.top_products_today.length
                                    "
                                    class="space-y-2"
                                >
                                    <div
                                        v-for="(
                                            p, i
                                        ) in ownerSummary.top_products_today"
                                        :key="p.name"
                                        class="flex items-center gap-2 text-xs"
                                    >
                                        <span class="w-5 shrink-0 text-sm">{{
                                            ['🥇', '🥈', '🥉'][i] ?? '▪️'
                                        }}</span>
                                        <span
                                            class="flex-1 truncate font-medium"
                                            >{{ p.name }}</span
                                        >
                                        <span
                                            class="font-mono text-muted-foreground"
                                            >{{ p.qty }} đơn</span
                                        >
                                    </div>
                                </div>
                                <p
                                    v-else
                                    class="text-xs text-muted-foreground italic"
                                >
                                    Chưa có đơn hàng hoàn thành hôm nay
                                </p>
                            </div>

                            <!-- Nhân viên đang làm việc -->
                            <div>
                                <p
                                    class="mb-2 text-[10px] font-bold tracking-wider text-muted-foreground uppercase"
                                >
                                    👥 Nhân sự hôm nay
                                </p>
                                <div
                                    v-if="ownerSummary.active_shifts.length"
                                    class="space-y-1.5"
                                >
                                    <div
                                        v-for="s in ownerSummary.active_shifts"
                                        :key="s.name + s.shift"
                                        class="flex items-center gap-2 text-xs"
                                    >
                                        <span
                                            :class="[
                                                'h-1.5 w-1.5 shrink-0 rounded-full',
                                                s.status === 'checked_in'
                                                    ? 'bg-emerald-500'
                                                    : 'bg-amber-400',
                                            ]"
                                        />
                                        <span class="flex-1 truncate">{{
                                            s.name
                                        }}</span>
                                        <span
                                            class="text-[10px] text-muted-foreground"
                                            >{{ s.shift }}</span
                                        >
                                    </div>
                                </div>
                                <p
                                    v-else
                                    class="text-xs text-muted-foreground italic"
                                >
                                    Chưa có nhân viên check-in hôm nay
                                </p>
                            </div>
                        </div>
                        <div
                            v-else
                            class="flex flex-col items-center py-12 text-center text-muted-foreground"
                        >
                            <Crown class="mb-2 size-8 text-amber-400/30" />
                            <p class="text-xs">Dữ liệu đang được tải...</p>
                        </div>
                    </div>

                    <!-- Biểu đồ phân tích doanh thu theo ca (Heatmap) -->
                    <Card
                        class="relative overflow-hidden border border-border bg-card text-card-foreground shadow-sm"
                    >
                        <div
                            class="pointer-events-none absolute -right-10 -bottom-10 h-40 w-40 rounded-full bg-indigo-500/5 blur-3xl"
                        ></div>
                        <CardHeader
                            class="border-b border-border/50 bg-muted/10 pb-2"
                        >
                            <div
                                class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center"
                            >
                                <div>
                                    <CardTitle
                                        class="flex items-center gap-2 text-base font-bold"
                                    >
                                        <Clock
                                            class="size-4.5 animate-pulse text-indigo-500"
                                        />
                                        Bản đồ nhiệt doanh thu & Tối ưu ca làm
                                        việc
                                    </CardTitle>
                                    <CardDescription
                                        class="mt-0.5 text-xs text-muted-foreground"
                                    >
                                        Phân tích mật độ doanh số trong 7 ngày
                                        gần đây theo từng ca làm việc để tối ưu
                                        hóa nhân lực
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
                                v-if="
                                    props.shiftRevenue &&
                                    props.shiftRevenue.length > 0
                                "
                                class="space-y-4"
                            >
                                <div class="space-y-3">
                                    <div
                                        v-for="row in props.shiftRevenue"
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
                                            <p
                                                class="mt-0.5 text-[10px] text-muted-foreground"
                                            >
                                                Doanh số trung bình ca:
                                                {{
                                                    formatMoneyFull(
                                                        Math.round(
                                                            row.days.reduce(
                                                                (sum, d) =>
                                                                    sum +
                                                                    d.revenue,
                                                                0,
                                                            ) /
                                                                Math.max(
                                                                    row.days
                                                                        .length,
                                                                    1,
                                                                ),
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
                                                :class="
                                                    shiftHeatColor(day.revenue)
                                                "
                                            >
                                                <!-- Date Label overlay inside each block -->
                                                <span
                                                    class="text-[9px] font-extrabold"
                                                    :class="
                                                        day.revenue /
                                                            shiftHeatmapMax >=
                                                        0.5
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
                                                        >Ngày
                                                        {{ day.date }}</span
                                                    >
                                                    <span
                                                        class="font-mono font-bold text-indigo-400"
                                                        >{{
                                                            formatMoneyFull(
                                                                day.revenue,
                                                            )
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
                                    <span class="mt-0.5 shrink-0 text-base"
                                        >💡</span
                                    >
                                    <div class="space-y-1">
                                        <h5
                                            class="text-xs font-bold text-indigo-800 dark:text-indigo-300"
                                        >
                                            Khuyến nghị điều phối ca của AI
                                        </h5>
                                        <p
                                            class="text-xs leading-relaxed font-medium text-indigo-700 dark:text-indigo-400"
                                        >
                                            Dựa trên mật độ doanh thu thực tế,
                                            ca tối và ca chiều cuối tuần (Thứ 6
                                            đến Chủ nhật) đang mang lại doanh
                                            thu cao nhất. Hãy đảm bảo bố trí tối
                                            thiểu
                                            <strong
                                                >2 nhân viên phục vụ và 1 thu
                                                ngân chuyên trách</strong
                                            >
                                            vào các ca này để giảm tải vận hành
                                            và rút ngắn thời gian chuẩn bị món.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            <div
                                v-else
                                class="flex flex-col items-center justify-center py-10 text-center text-muted-foreground"
                            >
                                <Clock
                                    class="mb-2 size-8 text-muted-foreground/30"
                                />
                                <p class="text-xs">
                                    Không có dữ liệu ca làm việc hoạt động.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Right sidebar (1/3) -->
                <div class="flex flex-col gap-6">
                    <!-- Onboarding widget -->
                    <template v-if="!props.onboardingComplete">
                        <div>
                            <h2 class="text-base font-semibold">
                                Hoàn thiện thiết lập
                            </h2>
                            <Card class="mt-4">
                                <CardHeader class="pb-3">
                                    <CardTitle class="text-sm font-semibold">
                                        {{ onboardingProgress }}/{{
                                            onboardingSteps.length
                                        }}
                                        bước hoàn thành
                                    </CardTitle>
                                    <div
                                        class="mt-2 h-1.5 rounded-full bg-muted"
                                    >
                                        <div
                                            class="h-full rounded-full bg-primary transition-all"
                                            :style="`width: ${(onboardingProgress / onboardingSteps.length) * 100}%`"
                                        />
                                    </div>
                                </CardHeader>
                                <CardContent class="space-y-2 pb-4">
                                    <Link
                                        v-for="step in onboardingSteps"
                                        :key="step.key"
                                        :href="step.done ? '#' : step.href"
                                        class="flex items-center gap-2.5 rounded-lg p-2 text-sm transition-colors hover:bg-muted/50"
                                        :class="
                                            step.done
                                                ? 'text-muted-foreground'
                                                : 'text-foreground'
                                        "
                                    >
                                        <span
                                            class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border text-xs"
                                            :class="
                                                step.done
                                                    ? 'border-emerald-500 bg-emerald-500 text-white'
                                                    : 'border-border bg-background'
                                            "
                                        >
                                            <ShieldCheck
                                                v-if="step.done"
                                                class="size-3"
                                            />
                                            <span v-else></span>
                                        </span>
                                        <span
                                            :class="
                                                step.done
                                                    ? 'line-through opacity-60'
                                                    : ''
                                            "
                                            >{{ step.label }}</span
                                        >
                                    </Link>
                                </CardContent>
                            </Card>
                        </div>
                    </template>

                    <!-- ── 1. Đơn hàng gần đây ───────────────────── -->
                    <div>
                        <div class="flex items-center justify-between">
                            <h2 class="text-base font-semibold">
                                Đơn hàng gần đây
                            </h2>
                            <Link
                                href="/orders"
                                class="flex items-center gap-1 text-xs text-primary hover:underline"
                            >
                                Xem tất cả <ChevronRight class="size-3.5" />
                            </Link>
                        </div>

                        <Card class="mt-4">
                            <CardContent class="p-0">
                                <!-- Có đơn -->
                                <div
                                    v-if="recentOrdersList.length > 0"
                                    class="divide-y divide-border"
                                >
                                    <Link
                                        v-for="order in recentOrdersList"
                                        :key="order.id"
                                        href="/orders"
                                        class="group flex items-center gap-3 px-4 py-3 transition-colors hover:bg-muted/40"
                                    >
                                        <!-- Icon channel -->
                                        <div
                                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-muted"
                                        >
                                            <Utensils
                                                class="size-3.5 text-muted-foreground"
                                            />
                                        </div>

                                        <!-- Info -->
                                        <div class="min-w-0 flex-1">
                                            <div
                                                class="flex items-center gap-1.5"
                                            >
                                                <span
                                                    class="truncate text-sm font-semibold"
                                                    >#{{
                                                        order.order_number
                                                    }}</span
                                                >
                                                <span
                                                    v-if="order.table_name"
                                                    class="truncate text-xs text-muted-foreground"
                                                    >·
                                                    {{ order.table_name }}</span
                                                >
                                            </div>
                                            <div
                                                class="mt-0.5 flex items-center gap-1.5"
                                            >
                                                <span
                                                    class="text-xs text-muted-foreground"
                                                    >{{
                                                        channelMap[
                                                            order.channel
                                                        ] ?? order.channel
                                                    }}</span
                                                >
                                                <span
                                                    class="text-xs text-muted-foreground"
                                                    >·</span
                                                >
                                                <span
                                                    class="text-xs text-muted-foreground"
                                                    >{{
                                                        order.created_at
                                                    }}</span
                                                >
                                            </div>
                                        </div>

                                        <!-- Right: badge + price -->
                                        <div
                                            class="flex shrink-0 flex-col items-end gap-1"
                                        >
                                            <span
                                                class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium"
                                                :class="
                                                    orderStatusMap[order.status]
                                                        ?.class ??
                                                    'bg-muted text-muted-foreground'
                                                "
                                            >
                                                {{
                                                    orderStatusMap[order.status]
                                                        ?.label ?? order.status
                                                }}
                                            </span>
                                            <span
                                                class="text-xs font-semibold"
                                                >{{
                                                    formatMoneyFull(
                                                        order.total_amount,
                                                    )
                                                }}</span
                                            >
                                        </div>
                                    </Link>
                                </div>

                                <!-- Không có đơn -->
                                <div
                                    v-else
                                    class="flex flex-col items-center justify-center py-8 text-center"
                                >
                                    <ShoppingCart
                                        class="mb-2 size-8 text-muted-foreground/40"
                                    />
                                    <p class="text-sm text-muted-foreground">
                                        Chưa có đơn hàng nào hôm nay
                                    </p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- ── 2. Hiệu suất hôm nay ───────────────────── -->
                    <div v-if="props.stats">
                        <h2 class="text-base font-semibold">
                            Hiệu suất hôm nay
                        </h2>
                        <Card class="mt-4">
                            <CardContent class="space-y-4 pt-4 pb-4">
                                <!-- Tỉ lệ hoàn thành -->
                                <div>
                                    <div
                                        class="mb-1.5 flex items-center justify-between text-sm"
                                    >
                                        <span
                                            class="flex items-center gap-1.5 text-muted-foreground"
                                        >
                                            <CheckCircle2
                                                class="size-3.5 text-emerald-500"
                                            />
                                            Tỉ lệ hoàn thành
                                        </span>
                                        <span
                                            class="font-semibold text-emerald-600 dark:text-emerald-400"
                                        >
                                            {{ completionRate }}%
                                        </span>
                                    </div>
                                    <div
                                        class="h-2 overflow-hidden rounded-full bg-muted"
                                    >
                                        <div
                                            class="h-full rounded-full bg-emerald-500 transition-all duration-500"
                                            :style="`width: ${completionRate}%`"
                                        />
                                    </div>
                                    <p
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        {{ props.stats.orders_completed }} /
                                        {{ props.stats.orders_today }} đơn
                                    </p>
                                </div>

                                <!-- Tỉ lệ huỷ -->
                                <div>
                                    <div
                                        class="mb-1.5 flex items-center justify-between text-sm"
                                    >
                                        <span
                                            class="flex items-center gap-1.5 text-muted-foreground"
                                        >
                                            <XCircle
                                                class="size-3.5 text-rose-500"
                                            />
                                            Tỉ lệ huỷ đơn
                                        </span>
                                        <span
                                            class="font-semibold"
                                            :class="
                                                cancellationRate > 20
                                                    ? 'text-rose-600 dark:text-rose-400'
                                                    : 'text-muted-foreground'
                                            "
                                        >
                                            {{ cancellationRate }}%
                                        </span>
                                    </div>
                                    <div
                                        class="h-2 overflow-hidden rounded-full bg-muted"
                                    >
                                        <div
                                            class="h-full rounded-full transition-all duration-500"
                                            :class="
                                                cancellationRate > 20
                                                    ? 'bg-rose-500'
                                                    : 'bg-rose-300'
                                            "
                                            :style="`width: ${cancellationRate}%`"
                                        />
                                    </div>
                                    <p
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        {{ props.stats.orders_cancelled ?? 0 }}
                                        đơn đã huỷ
                                    </p>
                                </div>

                                <!-- Đang xử lý / pending -->
                                <div
                                    class="flex items-center justify-between rounded-lg bg-muted/50 px-3 py-2.5"
                                >
                                    <span
                                        class="flex items-center gap-2 text-sm text-muted-foreground"
                                    >
                                        <Clock
                                            class="size-3.5 text-amber-500"
                                        />
                                        Đơn đang xử lý
                                    </span>
                                    <span
                                        class="text-sm font-bold"
                                        :class="
                                            pendingCount > 5
                                                ? 'text-amber-600'
                                                : ''
                                        "
                                    >
                                        {{ pendingCount }}
                                    </span>
                                </div>

                                <!-- Doanh thu -->
                                <div
                                    class="flex items-center justify-between rounded-lg bg-emerald-50 px-3 py-2.5 dark:bg-emerald-950/20"
                                >
                                    <span
                                        class="flex items-center gap-2 text-sm text-emerald-700 dark:text-emerald-400"
                                    >
                                        <Banknote class="size-3.5" />
                                        Doanh thu
                                    </span>
                                    <span
                                        class="text-sm font-bold text-emerald-700 dark:text-emerald-400"
                                    >
                                        {{
                                            formatMoneyFull(
                                                props.stats.revenue_today,
                                            )
                                        }}
                                    </span>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- ── 3. Cảnh báo & Nhắc nhở ─────────────────── -->
                    <div v-if="alertsList.length > 0">
                        <div class="flex items-center gap-2">
                            <h2 class="text-base font-semibold">
                                Cảnh báo & Nhắc nhở
                            </h2>
                            <span
                                class="flex h-5 w-5 items-center justify-center rounded-full bg-rose-500 text-[10px] font-bold text-white"
                            >
                                {{ alertsList.length }}
                            </span>
                        </div>
                        <div class="mt-4 flex flex-col gap-2">
                            <Link
                                v-for="(alert, i) in alertsList"
                                :key="i"
                                :href="alert.href"
                                class="flex items-start gap-2.5 rounded-xl border px-3.5 py-3 text-sm transition-all hover:opacity-80"
                                :class="alertColorMap[alert.type]"
                            >
                                <component
                                    :is="alertIconMap[alert.type]"
                                    class="mt-0.5 size-4 shrink-0"
                                />
                                <span class="leading-snug">{{
                                    alert.message
                                }}</span>
                                <ChevronRight
                                    class="mt-0.5 ml-auto size-3.5 shrink-0 opacity-60"
                                />
                            </Link>
                        </div>
                    </div>
                </div>
                <!-- /right sidebar -->
            </div>
        </section>
    </AppTopbarLayout>
</template>
