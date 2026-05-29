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
    Users,
    Zap,
    ShoppingCart,
    Banknote,
    XCircle,
    Clock,
    Utensils,
    Brain,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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
}>();

const page = usePage();
const user = computed(() => (page.props.auth as any)?.user ?? null);
const tenant = computed(() => (page.props as any).tenant ?? null);
const plan = computed(() => tenant.value?.plan ?? null);
const quota = computed(() => tenant.value?.quota_summary ?? null);

const availablePlans = computed(() => (page.props as any).available_plans ?? []);
const roles = computed(() => (page.props as any).roles ?? []);
const canManageBilling = computed(() =>
    tenant.value != null && (roles.value.includes('owner') || roles.value.includes('manager') || roles.value.includes('admin') || roles.value.includes('super_admin'))
);

const currentPlanRank = computed(() => {
    if (!plan.value?.code) {
        return 0;
    }

    const idx = availablePlans.value.findIndex((p: any) => p.code === plan.value.code);

    return idx === -1 ? 0 : idx;
});

const nextPlan = computed(() =>
    canManageBilling.value ? (availablePlans.value[currentPlanRank.value + 1] ?? null) : null
);

const isFreePlan = computed(() => !plan.value || plan.value.code === 'free');

const planBadgeClass = computed(() => {
    switch (plan.value?.code) {
        case 'pro':   return 'bg-primary text-primary-foreground hover:bg-primary/90';
        case 'max':   return 'bg-sky-600 text-white hover:bg-sky-700';
        case 'ultra': return 'bg-violet-600 text-white hover:bg-violet-700';
        default:      return '';
    }
});

const planIcon = computed(() => {
    switch (plan.value?.code) {
        case 'ultra': return Crown;
        case 'max':   return Zap;
        case 'pro':   return Star;
        default:      return null;
    }
});

const activeTab = ref<'feed' | 'tables' | 'inventory'>('feed');

const operationFeedList = computed(() => props.operationFeed ?? []);
const tablesList = computed(() => props.tablesData ?? []);
const lowStockList = computed(() => props.lowStockInventory ?? []);
const recentOrdersList = computed(() => props.recentOrders ?? []);
const alertsList = computed(() => props.alerts ?? []);

function getFeedIcon(iconName: string) {
    switch (iconName) {
        case 'ShoppingCart': return ShoppingCart;
        case 'Utensils':     return Utensils;
        case 'CheckCircle2': return CheckCircle2;
        case 'XCircle':      return XCircle;
        case 'AlertTriangle':return AlertTriangle;
        case 'Users':        return Users;
        default:             return Activity;
    }
}

function getFeedColorClasses(colorName: string) {
    switch (colorName) {
        case 'amber':   return 'border-amber-100 bg-amber-50 text-amber-600 dark:border-amber-950/30 dark:bg-amber-950/20 dark:text-amber-400';
        case 'violet':  return 'border-violet-100 bg-violet-50 text-violet-600 dark:border-violet-950/30 dark:bg-violet-950/20 dark:text-violet-400';
        case 'emerald': return 'border-emerald-100 bg-emerald-50 text-emerald-600 dark:border-emerald-950/30 dark:bg-emerald-950/20 dark:text-emerald-400';
        case 'rose':    return 'border-rose-100 bg-rose-50 text-rose-600 dark:border-rose-950/30 dark:bg-rose-950/20 dark:text-rose-400';
        case 'sky':     return 'border-sky-100 bg-sky-50 text-sky-600 dark:border-sky-900/30 dark:bg-sky-950/20 dark:text-sky-400';
        default:        return 'border-slate-100 bg-slate-50 text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400';
    }
}

const tableStatusMap = {
    available: { label: 'Bàn trống', class: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/20 dark:text-emerald-450 dark:border-emerald-900/40' },
    occupied:  { label: 'Có khách', class: 'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-950/20 dark:text-indigo-400 dark:border-indigo-900/40' },
    reserved:  { label: 'Đặt trước', class: 'bg-violet-50 text-violet-700 border-violet-200 dark:bg-violet-950/20 dark:text-violet-400 dark:border-violet-900/40' },
    cleaning:  { label: 'Dọn dẹp', class: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/20 dark:text-amber-400 dark:border-amber-900/40' },
};

function getTableStatusInfo(status: string) {
    return tableStatusMap[status as keyof typeof tableStatusMap] ?? { label: status, class: 'bg-muted text-muted-foreground border-border' };
}

// Trial countdown
const isOnTrial = computed(() => tenant.value?.status === 'trial');
const trialDaysLeft = computed(() => {
    if (!tenant.value?.trial_ends_at) {
        return 0;
    }

    const end  = new Date(tenant.value.trial_ends_at);
    const now  = new Date();

    return Math.max(0, Math.ceil((end.getTime() - now.getTime()) / (1000 * 60 * 60 * 24)));
});

// Onboarding steps derived from user's onboarding_status
// Cấu trúc thực tế: { current_day, day_1: { completed_at, ... }, day_2: {...}, day_3: {...} }
const onboardingSteps = computed(() => {
    const status = (user.value?.onboarding_status as any) ?? {};

    return [
        { key: 'day_1', label: 'Thêm sản phẩm & thực đơn',     href: '/products',  done: !!status.day_1?.completed_at },
        { key: 'day_2', label: 'Cấu hình kho nguyên liệu',       href: '/inventory', done: !!status.day_2?.completed_at },
        { key: 'day_3', label: 'Thêm nhân viên & xếp lịch ca', href: '/employees', done: !!status.day_3?.completed_at },
    ];
});

const onboardingProgress = computed(() =>
    onboardingSteps.value.filter(s => s.done).length
);

const quickActions = [
    { label: 'Đơn hàng',        description: 'Xử lý, theo dõi đơn',  icon: ShoppingCart, href: '/orders',          color: 'violet' },
    { label: 'Sản phẩm',        description: 'Menu & danh mục',       icon: Package,      href: '/products',        color: 'amber' },
    { label: 'Bàn & Khu vực',   description: 'Sơ đồ, trạng thái bàn', icon: BarChart3,    href: '/tables',          color: 'teal' },
    { label: 'Nhân viên',        description: 'Ca làm, phân quyền',    icon: Users,        href: '/employees',       color: 'sky' },
    { label: 'Kho nguyên liệu',  description: 'Nhập xuất, tồn kho',   icon: TrendingUp,   href: '/inventory',       color: 'emerald' },
    { label: 'Báo cáo',          description: 'Doanh thu, lợi nhuận',  icon: BarChart3,    href: '/reports',         color: 'rose' },
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

    return new Intl.NumberFormat('vi-VN', { notation: 'compact', maximumFractionDigits: 1 }).format(v) + 'đ';
}

function formatMoneyFull(v: number): string {
    if (v === 0) {
return '—';
}

    return new Intl.NumberFormat('vi-VN').format(v) + 'đ';
}

const quotaStats = computed(() => [
    { key: 'branches',  label: 'Chi nhánh', icon: Building2 },
    { key: 'employees', label: 'Nhân viên',  icon: Users },
    { key: 'tables',    label: 'Bàn',        icon: BarChart3 },
]);

// Trạng thái đơn hàng
const orderStatusMap: Record<string, { label: string; class: string }> = {
    pending:   { label: 'Chờ xác nhận', class: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' },
    confirmed: { label: 'Đã xác nhận',  class: 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400' },
    preparing: { label: 'Đang chế biến', class: 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-400' },
    completed: { label: 'Hoàn thành',   class: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' },
    cancelled: { label: 'Đã huỷ',       class: 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400' },
};

const channelMap: Record<string, string> = {
    dine_in:  'Tại bàn',
    takeaway: 'Mang về',
    delivery: 'Giao hàng',
    qr:       'QR',
};

// Hiệu suất hôm nay — tính toán từ stats
const completionRate = computed(() => {
    if (!props.stats?.orders_today) {
return 0;
}

    return Math.round((props.stats.orders_completed / props.stats.orders_today) * 100);
});

const cancellationRate = computed(() => {
    if (!props.stats?.orders_today) {
return 0;
}

    return Math.round(((props.stats.orders_cancelled ?? 0) / props.stats.orders_today) * 100);
});

const pendingCount = computed(() => {
    if (!props.stats) {
return 0;
}

    return props.stats.orders_today - props.stats.orders_completed - (props.stats.orders_cancelled ?? 0);
});

// Alert icon map
const alertIconMap = { warning: AlertTriangle, danger: XCircle, info: Info };
const alertColorMap = {
    warning: 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800/50 dark:bg-amber-950/30 dark:text-amber-300',
    danger:  'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-800/50 dark:bg-rose-950/30 dark:text-rose-300',
    info:    'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-800/50 dark:bg-sky-950/30 dark:text-sky-300',
};

// Chart calculations
const revenueChartList = computed(() => props.revenueChartData ?? []);
const maxRevenue = computed(() => {
    const vals = revenueChartList.value.map(d => d.revenue);
    return Math.max(...vals, 100000); // at least 100k
});

const channelChartList = computed(() => props.channelChartData ?? []);
const channelColors: Record<string, string> = {
    dine_in: '#6366f1',  // indigo
    takeaway: '#f59e0b', // amber
    delivery: '#10b981', // emerald
    qr: '#ec4899',       // pink
};

const doughnutPaths = computed(() => {
    const list = channelChartList.value;
    const total = list.reduce((sum, item) => sum + item.count, 0);
    if (total === 0) return [];

    let currentAngle = -Math.PI / 2; // start at top
    return list.map(item => {
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
    const qtyList = topProductsList.value.map(p => p.quantity);
    return Math.max(...qtyList, 1);
});

const activeHoverIndex = ref<number | null>(null);

const maxOrders = computed(() => {
    const list = revenueChartList.value.map(d => d.orders);
    return Math.max(...list, 5);
});

const ordersLinePath = computed(() => {
    const list = revenueChartList.value;
    if (list.length === 0) return '';
    return list.map((day, i) => {
        const x = i * 85 + 80;
        const y = 160 - (day.orders / maxOrders.value) * 125;
        return `${i === 0 ? 'M' : 'L'} ${x} ${y}`;
    }).join(' ');
});

const topDishName = computed(() => topProductsList.value[0]?.name ?? 'chưa có');
const topChannelLabel = computed(() => {
    if (doughnutPaths.value.length === 0) return 'chưa có';
    const sorted = [...doughnutPaths.value].sort((a, b) => b.count - a.count);
    return sorted[0]?.label ?? 'chưa có';
});
</script>

<template>
    <AppTopbarLayout>
        <Head title="Dashboard · Aventura" />

        <!-- ── Trial Countdown Banner ────────────────────────────── -->
        <div v-if="isOnTrial" class="border-b border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/40 px-4 py-3 lg:px-8">
            <div class="mx-auto max-w-7xl flex flex-col sm:flex-row items-center justify-between gap-3">
                <div class="flex items-center gap-2.5 text-sm">
                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-amber-500 text-white text-[10px] font-bold">!</span>
                    <span class="text-amber-800 dark:text-amber-200 font-medium">
                        Tài khoản đang dùng thử —
                        <strong>còn {{ trialDaysLeft }} ngày</strong>.
                        Nâng cấp để không gián đoạn vận hành.
                    </span>
                </div>
                <Link :href="nextPlan ? `/billing/checkout?plan=${nextPlan.code}` : '/billing/checkout'"
                    class="shrink-0 rounded-lg bg-amber-500 hover:bg-amber-600 text-white text-xs font-semibold px-4 py-1.5 transition-colors">
                    Nâng cấp ngay →
                </Link>
            </div>
        </div>

        <!-- ── Welcome header ───────────────────────────────────── -->
        <section class="border-b border-border bg-muted/30 px-4 py-8 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm text-muted-foreground">Xin chào trở lại,</p>
                        <h1 class="mt-0.5 text-2xl font-bold tracking-tight">{{ user?.name }}</h1>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <span v-if="tenant?.name" class="flex items-center gap-1 text-sm text-muted-foreground">
                                <Building2 class="size-3.5" />
                                {{ tenant.name }}
                            </span>
                            <Badge v-if="plan" :class="planBadgeClass" class="gap-1">
                                <component v-if="planIcon" :is="planIcon" class="size-3" />
                                Gói {{ plan.name }}
                            </Badge>
                            <Badge v-else variant="secondary">Gói Free</Badge>
                        </div>
                    </div>

                    <Button v-if="nextPlan" as-child size="sm" class="shrink-0">
                        <Link :href="`/billing/checkout?plan=${nextPlan.code}`" class="flex items-center gap-1.5">
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
                        <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                            <component :is="stat.icon" class="size-3.5" />
                            {{ stat.label }}
                        </div>
                        <p class="mt-1 text-xl font-bold">
                            {{ resourceData(stat.key)?.used ?? 0 }}
                            <span class="text-sm font-normal text-muted-foreground">
                                / {{ resourceData(stat.key)?.unlimited ? '∞' : formatLimit(resourceData(stat.key)?.limit ?? null) }}
                            </span>
                        </p>
                        <!-- Progress bar -->
                        <div class="mt-2 h-1.5 rounded-full bg-muted">
                            <div
                                v-if="!resourceData(stat.key)?.unlimited"
                                class="h-full rounded-full transition-all"
                                :class="(resourceData(stat.key)?.percentage ?? 0) >= 90 ? 'bg-rose-500' : 'bg-primary'"
                                :style="`width: ${resourceData(stat.key)?.percentage ?? 0}%`"
                            />
                            <div
                                v-else
                                class="h-full w-1/3 rounded-full bg-gradient-to-r from-primary/50 to-primary/20"
                            />
                        </div>
                    </div>
                </div>

                <!-- Today's KPI row -->
                <div v-if="props.stats" class="mt-4 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2">
                    <div class="rounded-xl border border-border bg-background px-3 py-2.5 flex items-center gap-2">
                        <ShoppingCart class="size-4 text-violet-500 shrink-0" />
                        <div>
                            <p class="text-lg font-bold leading-none">{{ props.stats.orders_today }}</p>
                            <p class="text-[10px] text-muted-foreground mt-0.5">Đơn hôm nay</p>
                        </div>
                    </div>
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 dark:border-emerald-900/40 dark:bg-emerald-950/20 px-3 py-2.5 flex items-center gap-2">
                        <Banknote class="size-4 text-emerald-600 shrink-0" />
                        <div>
                            <p class="text-lg font-bold leading-none text-emerald-700 dark:text-emerald-400">
                                {{ formatMoney(props.stats.revenue_today) }}
                            </p>
                            <p class="text-[10px] text-emerald-600/70 dark:text-emerald-500 mt-0.5">Doanh thu hôm nay</p>
                        </div>
                    </div>
                    <div class="rounded-xl border border-border bg-background px-3 py-2.5 flex items-center gap-2">
                        <CheckCircle2 class="size-4 text-sky-500 shrink-0" />
                        <div>
                            <p class="text-lg font-bold leading-none">{{ props.stats.orders_completed }}</p>
                            <p class="text-[10px] text-muted-foreground mt-0.5">Đơn hoàn thành</p>
                        </div>
                    </div>
                    <div class="rounded-xl border border-border bg-background px-3 py-2.5 flex items-center gap-2">
                        <Package class="size-4 text-amber-500 shrink-0" />
                        <div>
                            <p class="text-lg font-bold leading-none">{{ props.stats.products_count }}</p>
                            <p class="text-[10px] text-muted-foreground mt-0.5">Sản phẩm</p>
                        </div>
                    </div>
                    <div class="rounded-xl border border-border bg-background px-3 py-2.5 flex items-center gap-2">
                        <Users class="size-4 text-indigo-500 shrink-0" />
                        <div>
                            <p class="text-lg font-bold leading-none">{{ props.stats.employees_count }}</p>
                            <p class="text-[10px] text-muted-foreground mt-0.5">Nhân viên</p>
                        </div>
                    </div>
                    <div class="rounded-xl border border-border bg-background px-3 py-2.5 flex items-center gap-2">
                        <Building2 class="size-4 text-rose-500 shrink-0" />
                        <div>
                            <p class="text-lg font-bold leading-none">{{ props.stats.branches_count }} / {{ props.stats.tables_count }}</p>
                            <p class="text-[10px] text-muted-foreground mt-0.5">Chi nhánh / Bàn</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ── Quick actions ─────────────────────────────────────── -->
        <section class="px-4 py-8 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <h2 class="text-base font-semibold">Truy cập nhanh</h2>
                <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                    <Link
                        v-for="action in quickActions"
                        :key="action.label"
                        :href="action.href"
                        class="group flex flex-col items-center gap-2.5 rounded-xl border border-border bg-card p-4 text-center transition-all hover:border-primary/30 hover:bg-muted/40 hover:shadow-sm"
                    >
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-xl border transition-all group-hover:scale-110"
                            :class="{
                                'border-amber-100   bg-amber-50   text-amber-600   dark:border-amber-900/30   dark:bg-amber-950/20   dark:text-amber-400':   action.color === 'amber',
                                'border-emerald-100 bg-emerald-50 text-emerald-600 dark:border-emerald-900/30 dark:bg-emerald-950/20 dark:text-emerald-400': action.color === 'emerald',
                                'border-sky-100     bg-sky-50     text-sky-600     dark:border-sky-900/30     dark:bg-sky-950/20     dark:text-sky-400':     action.color === 'sky',
                                'border-rose-100    bg-rose-50    text-rose-600    dark:border-rose-900/30    dark:bg-rose-950/20    dark:text-rose-400':    action.color === 'rose',
                                'border-violet-100  bg-violet-50  text-violet-600  dark:border-violet-900/30  dark:bg-violet-950/20  dark:text-violet-400':  action.color === 'violet',
                                'border-teal-100    bg-teal-50    text-teal-600    dark:border-teal-900/30    dark:bg-teal-950/20    dark:text-teal-400':    action.color === 'teal',
                                'border-slate-100   bg-slate-50   text-slate-600   dark:border-slate-900/30   dark:bg-slate-950/20   dark:text-slate-400':   action.color === 'slate',
                            }"
                        >
                            <component :is="action.icon" class="size-5" />
                        </div>
                        <div>
                            <p class="text-sm font-semibold">{{ action.label }}</p>
                            <p class="mt-0.5 text-xs leading-snug text-muted-foreground">{{ action.description }}</p>
                        </div>
                    </Link>
                </div>
            </div>
        </section>

        <!-- ── Phân tích & Thống kê (Biểu đồ) ────────────────────── -->
        <section class="px-4 pb-8 lg:px-8">
            <div class="mx-auto max-w-7xl space-y-6">
                <!-- Row 1: Sales Chart & Channels Doughnut -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    
                    <!-- Sales & Orders Chart (2/3) -->
                    <Card class="lg:col-span-2 relative overflow-hidden bg-card text-card-foreground border border-border shadow-sm">
                        <CardHeader class="pb-2 border-b border-border/50">
                            <div class="flex items-center justify-between">
                                <div>
                                    <CardTitle class="text-base font-bold flex items-center gap-2">
                                        <TrendingUp class="size-4 text-violet-500" />
                                        Doanh thu & Đơn hàng 7 ngày qua
                                    </CardTitle>
                                    <p class="text-xs text-muted-foreground mt-0.5">Biểu đồ kết hợp Doanh thu thuần (cột) và số lượng Đơn hàng (đường)</p>
                                </div>
                                <div class="flex items-center gap-4 text-xs font-semibold">
                                    <span class="flex items-center gap-1.5 text-indigo-500">
                                        <span class="h-3 w-3 rounded bg-indigo-500/80"></span> Doanh thu
                                    </span>
                                    <span class="flex items-center gap-1.5 text-emerald-500">
                                        <span class="h-3 w-3 rounded-full border-2 border-emerald-500 bg-background"></span> Đơn hàng
                                    </span>
                                </div>
                            </div>
                        </CardHeader>
                        
                        <CardContent class="pt-6">
                            <div v-if="revenueChartList.length" class="relative">
                                <!-- Tooltip Floating -->
                                <div v-if="activeHoverIndex !== null" 
                                     class="absolute z-20 bg-slate-900/95 text-white text-xs p-3 rounded-xl shadow-xl border border-slate-700/50 backdrop-blur-md pointer-events-none transition-all duration-150"
                                     :style="{
                                         left: `${(activeHoverIndex / revenueChartList.length) * 85 + 12}%`,
                                         transform: 'translateX(-50%)',
                                         top: '-15px'
                                     }"
                                >
                                    <p class="font-bold text-slate-300 border-b border-slate-700 pb-1 mb-1">Ngày {{ revenueChartList[activeHoverIndex].date }}</p>
                                    <p class="flex justify-between gap-4">
                                        <span class="text-slate-400">Doanh thu:</span>
                                        <span class="font-mono font-bold text-indigo-400">{{ formatMoneyFull(revenueChartList[activeHoverIndex].revenue) }}</span>
                                    </p>
                                    <p class="flex justify-between gap-4 mt-0.5">
                                        <span class="text-slate-400">Đơn hàng:</span>
                                        <span class="font-mono font-bold text-emerald-400">{{ revenueChartList[activeHoverIndex].orders }} đơn</span>
                                    </p>
                                </div>

                                <!-- The Chart SVG -->
                                <svg viewBox="0 0 700 200" class="w-full h-48 overflow-visible">
                                    <!-- Y-Axis Grid Lines -->
                                    <line x1="50" y1="20" x2="650" y2="20" class="stroke-muted/30 stroke-1" stroke-dasharray="4" />
                                    <line x1="50" y1="90" x2="650" y2="90" class="stroke-muted/30 stroke-1" stroke-dasharray="4" />
                                    <line x1="50" y1="160" x2="650" y2="160" class="stroke-muted" stroke-width="1.5" />

                                    <!-- Bars and Points -->
                                    <g v-for="(day, i) in revenueChartList" :key="day.date">
                                        <!-- Interactive background column area for hovering -->
                                        <rect :x="i * 85 + 50" y="10" width="60" height="150" 
                                              class="fill-transparent hover:fill-muted/20 cursor-pointer transition-colors duration-150 rounded"
                                              @mouseenter="activeHoverIndex = i"
                                              @mouseleave="activeHoverIndex = null"
                                        />

                                        <!-- Revenue Bar -->
                                        <rect :x="i * 85 + 65" 
                                              :y="160 - (day.revenue / maxRevenue) * 130" 
                                              width="30" 
                                              :height="Math.max((day.revenue / maxRevenue) * 130, day.revenue > 0 ? 4 : 0)" 
                                              rx="4"
                                              class="fill-indigo-500/80 hover:fill-indigo-600 dark:fill-indigo-500/60 dark:hover:fill-indigo-500 transition-colors pointer-events-none duration-150"
                                        />
                                    </g>

                                    <!-- SVG Orders Line Trend Overlay -->
                                    <path :d="ordersLinePath" fill="none" class="stroke-emerald-500" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" />

                                    <!-- Dots on line for each day -->
                                    <g v-for="(day, i) in revenueChartList" :key="'dot-' + day.date">
                                        <circle :cx="i * 85 + 80" 
                                                :cy="160 - (day.orders / maxOrders) * 125" 
                                                r="5" 
                                                class="fill-background stroke-emerald-500 stroke-2 pointer-events-none" 
                                        />
                                        <!-- Glowing effect circle when hovered -->
                                        <circle v-if="activeHoverIndex === i"
                                                :cx="i * 85 + 80" 
                                                :cy="160 - (day.orders / maxOrders) * 125" 
                                                r="9" 
                                                class="fill-emerald-500/30 stroke-none pointer-events-none animate-ping" 
                                        />
                                    </g>
                                    
                                    <!-- X-Axis Labels -->
                                    <text v-for="(day, i) in revenueChartList" :key="'txt-' + day.date"
                                          :x="i * 85 + 80" 
                                          y="182" 
                                          text-anchor="middle" 
                                          class="fill-muted-foreground text-[10px] font-medium font-sans"
                                    >
                                        {{ day.date }}
                                    </text>
                                </svg>
                            </div>
                            <div v-else class="flex flex-col items-center justify-center py-16 text-muted-foreground text-sm">
                                <BarChart3 class="size-8 text-muted-foreground/30 mb-2" />
                                Chưa có dữ liệu doanh số tuần này.
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Channels Doughnut Chart (1/3) -->
                    <Card class="bg-card text-card-foreground border border-border shadow-sm">
                        <CardHeader class="pb-2 border-b border-border/50">
                            <CardTitle class="text-base font-bold flex items-center gap-2">
                                <Utensils class="size-4 text-amber-500" />
                                Tỷ lệ kênh bán hàng
                            </CardTitle>
                            <p class="text-xs text-muted-foreground mt-0.5">Cơ cấu đơn hàng theo kênh dịch vụ</p>
                        </CardHeader>
                        
                        <CardContent class="pt-6 flex flex-col items-center justify-center">
                            <div v-if="doughnutPaths.length" class="flex flex-col items-center gap-6 w-full">
                                <!-- Doughnut SVG -->
                                <div class="relative w-36 h-36 flex items-center justify-center">
                                    <svg viewBox="0 0 200 200" class="w-full h-full transform -rotate-90">
                                        <g v-for="slice in doughnutPaths" :key="slice.label">
                                            <path :d="slice.path" :fill="slice.color" class="stroke-card stroke-2 hover:opacity-90 transition-opacity cursor-pointer" />
                                            <title>{{ slice.label }}: {{ slice.count }} đơn ({{ slice.percentage }}%)</title>
                                        </g>
                                        <!-- Central hole -->
                                        <circle cx="100" cy="100" r="62" class="fill-card" />
                                    </svg>
                                    <!-- Central statistics overlay -->
                                    <div class="absolute flex flex-col items-center justify-center text-center">
                                        <span class="text-2xl font-bold leading-none">{{ channelChartList.reduce((sum, item) => sum + item.count, 0) }}</span>
                                        <span class="text-[10px] text-muted-foreground mt-1 font-medium">Tổng đơn</span>
                                    </div>
                                </div>

                                <!-- Custom Legends -->
                                <div class="grid grid-cols-2 gap-3 text-xs w-full">
                                    <div v-for="slice in doughnutPaths" :key="slice.label" 
                                         class="flex items-center gap-2 px-2 py-1.5 rounded-lg border border-border bg-muted/20"
                                    >
                                        <div class="w-2.5 h-2.5 rounded-full shrink-0 animate-pulse" :style="{ backgroundColor: slice.color }"></div>
                                        <span class="font-medium text-foreground truncate">{{ slice.label }}</span>
                                        <span class="ml-auto font-bold text-muted-foreground font-mono text-[10px]">{{ slice.percentage }}%</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div v-else class="flex flex-col items-center justify-center py-16 text-muted-foreground text-sm w-full">
                                <ShoppingCart class="size-8 text-muted-foreground/30 mb-2" />
                                Chưa có dữ liệu kênh bán hàng.
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Row 2: Top Products & AI Suggestion -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    
                    <!-- Top Products Leaderboard (2/3) -->
                    <Card class="lg:col-span-2 bg-card text-card-foreground border border-border shadow-sm">
                        <CardHeader class="pb-2 border-b border-border/50">
                            <CardTitle class="text-base font-bold flex items-center gap-2">
                                <Package class="size-4 text-emerald-500" />
                                Top 5 món bán chạy nhất (30 ngày qua)
                            </CardTitle>
                            <p class="text-xs text-muted-foreground mt-0.5">Xếp hạng theo số lượng phần ăn phục vụ thành công</p>
                        </CardHeader>
                        
                        <CardContent class="pt-6 space-y-4">
                            <div v-if="topProductsList.length" class="space-y-4">
                                <div v-for="(p, idx) in topProductsList" :key="p.name" class="space-y-1.5 group">
                                    <div class="flex items-center justify-between text-xs font-semibold">
                                        <div class="flex items-center gap-2 min-w-0">
                                            <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[10px] font-extrabold"
                                                  :class="{
                                                      'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400': idx === 0,
                                                      'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400': idx === 1,
                                                      'bg-orange-100 text-orange-700 dark:bg-orange-950/40 dark:text-orange-400': idx === 2,
                                                      'bg-muted text-muted-foreground': idx > 2
                                                  }"
                                            >
                                                {{ idx + 1 }}
                                            </span>
                                            <span class="truncate text-foreground group-hover:text-primary transition-colors">{{ p.name }}</span>
                                        </div>
                                        <div class="flex items-center gap-3 shrink-0 font-mono text-muted-foreground">
                                            <span class="font-bold text-foreground">{{ p.quantity }} phần</span>
                                            <span>·</span>
                                            <span class="text-emerald-600 dark:text-emerald-400 font-bold">{{ formatMoneyFull(p.revenue) }}</span>
                                        </div>
                                    </div>
                                    <!-- Sleek Progress Bar -->
                                    <div class="h-2 rounded-full bg-muted overflow-hidden">
                                        <div class="h-full rounded-full transition-all duration-500 bg-gradient-to-r"
                                             :class="{
                                                 'from-amber-400 to-amber-500': idx === 0,
                                                 'from-indigo-400 to-indigo-500': idx === 1,
                                                 'from-emerald-400 to-emerald-500': idx === 2,
                                                 'from-slate-400 to-slate-500': idx > 2
                                             }"
                                             :style="{ width: `${(p.quantity / maxProductQty) * 100}%` }"
                                        />
                                    </div>
                                </div>
                            </div>
                            
                            <div v-else class="flex flex-col items-center justify-center py-16 text-muted-foreground text-sm">
                                <Package class="size-8 text-muted-foreground/30 mb-2" />
                                Chưa có dữ liệu sản phẩm bán chạy.
                            </div>
                        </CardContent>
                    </Card>

                    <!-- AI Insights and Advisor (1/3) -->
                    <Card class="bg-card text-card-foreground border border-border shadow-sm overflow-hidden relative">
                        <!-- Visual subtle background element -->
                        <div class="absolute -right-10 -bottom-10 w-32 h-32 bg-indigo-500/5 rounded-full blur-2xl"></div>
                        
                        <CardHeader class="pb-2 border-b border-border/50 bg-muted/20">
                            <CardTitle class="text-base font-bold flex items-center gap-2">
                                <Brain class="size-4.5 text-indigo-500 animate-pulse" />
                                Trợ lý thông minh Aventura AI
                            </CardTitle>
                            <p class="text-xs text-muted-foreground mt-0.5">Khuyến nghị vận hành tự động</p>
                        </CardHeader>
                        
                        <CardContent class="pt-6 space-y-4 relative z-10 text-xs">
                            <div v-if="topProductsList.length && doughnutPaths.length" class="space-y-4">
                                <p class="leading-relaxed text-muted-foreground">
                                    Dựa trên hiệu suất doanh số 7 ngày qua, AI ghi nhận kết quả hoạt động xuất sắc:
                                </p>
                                
                                <div class="space-y-3">
                                    <div class="flex items-start gap-2.5">
                                        <div class="flex h-5 w-5 shrink-0 items-center justify-center rounded bg-indigo-500/10 text-indigo-500 mt-0.5">
                                            <Utensils class="size-3" />
                                        </div>
                                        <p class="leading-normal">
                                            Kênh dịch vụ <strong class="text-foreground">{{ topChannelLabel }}</strong> đóng góp lượng đơn hàng lớn nhất của quán.
                                        </p>
                                    </div>

                                    <div class="flex items-start gap-2.5">
                                        <div class="flex h-5 w-5 shrink-0 items-center justify-center rounded bg-emerald-500/10 text-emerald-500 mt-0.5">
                                            <Package class="size-3" />
                                        </div>
                                        <p class="leading-normal">
                                            Món ăn được thực khách ưa chuộng nhất là <strong class="text-foreground">{{ topDishName }}</strong>.
                                        </p>
                                    </div>

                                    <div class="flex items-start gap-2.5 bg-indigo-500/5 dark:bg-indigo-950/20 border border-indigo-500/10 rounded-xl p-3 mt-4">
                                        <div class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-indigo-500 text-white mt-0.5 text-[9px] font-bold">💡</div>
                                        <p class="leading-relaxed text-indigo-700 dark:text-indigo-300 font-medium">
                                            Khuyến nghị: Hãy cân nhắc thiết lập combo quà tặng hoặc giảm giá kèm món <strong>{{ topDishName }}</strong> trên trang QR Menu để kích thích thực khách gọi thêm các món ăn kèm khác!
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            <div v-else class="flex flex-col items-center justify-center py-16 text-muted-foreground text-center text-sm">
                                <Brain class="size-8 text-muted-foreground/30 mb-2" />
                                Đang thu thập thêm dữ liệu để đưa ra khuyến nghị AI...
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </section>

        <!-- ── News + Right sidebar ──────────────────────────────── -->
        <section class="px-4 pb-14 lg:px-8">
            <div class="mx-auto max-w-7xl grid gap-6 lg:grid-cols-3">

                <!-- Bảng điều khiển Vận hành (2/3) -->
                <div class="lg:col-span-2 space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-1 border-b border-border/60">
                        <div>
                            <h2 class="text-base font-bold flex items-center gap-2">
                                <span class="relative flex h-2.5 w-2.5">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                                </span>
                                Trung tâm Giám sát Vận hành
                            </h2>
                            <p class="text-xs text-muted-foreground">Theo dõi hoạt động, trạng thái bàn và cảnh báo thời gian thực</p>
                        </div>
                        
                        <!-- Premium Glassmorphic Tab Switcher -->
                        <div class="flex p-0.5 rounded-xl border border-border bg-muted/50 text-muted-foreground text-xs self-start sm:self-auto shadow-inner">
                            <button
                                @click="activeTab = 'feed'"
                                :class="activeTab === 'feed' ? 'bg-background text-foreground shadow-sm font-semibold' : 'hover:text-foreground'"
                                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg transition-all"
                            >
                                <Activity class="size-3.5" />
                                Nhật ký
                            </button>
                            <button
                                @click="activeTab = 'tables'"
                                :class="activeTab === 'tables' ? 'bg-background text-foreground shadow-sm font-semibold' : 'hover:text-foreground'"
                                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg transition-all"
                            >
                                <BarChart3 class="size-3.5" />
                                Sơ đồ Bàn
                            </button>
                            <button
                                @click="activeTab = 'inventory'"
                                :class="activeTab === 'inventory' ? 'bg-background text-foreground shadow-sm font-semibold' : 'hover:text-foreground'"
                                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg transition-all"
                            >
                                <AlertTriangle class="size-3.5" />
                                Tồn kho
                                <span v-if="lowStockList.length" class="flex h-4 w-4 items-center justify-center rounded-full bg-rose-500 text-[9px] font-bold text-white leading-none scale-90">
                                    {{ lowStockList.length }}
                                </span>
                            </button>
                        </div>
                    </div>

                    <!-- TAB Content: Live Activity Feed -->
                    <div v-if="activeTab === 'feed'" class="space-y-3 animate-in fade-in-50 duration-200">
                        <div v-if="operationFeedList.length > 0" class="relative pl-4 border-l border-border/80 space-y-4 py-2">
                            <div 
                                v-for="(item, idx) in operationFeedList" 
                                :key="idx"
                                class="relative flex gap-4 p-3.5 rounded-2xl border border-border bg-card shadow-sm hover:shadow-md hover:border-primary/20 transition-all group"
                            >
                                <!-- Line pointer dot -->
                                <div class="absolute -left-[21px] top-7 w-2.5 h-2.5 rounded-full border-2 border-background"
                                     :class="{
                                         'bg-amber-500 ring-4 ring-amber-500/20': item.color === 'amber',
                                         'bg-violet-500 ring-4 ring-violet-500/20': item.color === 'violet',
                                         'bg-emerald-500 ring-4 ring-emerald-500/20': item.color === 'emerald',
                                         'bg-rose-500 ring-4 ring-rose-500/20': item.color === 'rose',
                                         'bg-sky-500 ring-4 ring-sky-500/20': item.color === 'sky',
                                     }"
                                ></div>

                                <!-- Icon -->
                                <div 
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border transition-transform group-hover:scale-105"
                                    :class="getFeedColorClasses(item.color)"
                                >
                                    <component :is="getFeedIcon(item.icon)" class="size-4.5" />
                                </div>

                                <!-- Content -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-2">
                                        <h4 class="text-sm font-semibold text-foreground truncate">{{ item.title }}</h4>
                                        <span class="text-[10px] text-muted-foreground shrink-0 font-medium font-mono">{{ item.time }}</span>
                                    </div>
                                    <p class="mt-1 text-xs text-muted-foreground leading-relaxed">{{ item.description }}</p>
                                </div>

                                <!-- Link button or details arrow -->
                                <div class="flex items-center justify-center shrink-0 self-center opacity-0 group-hover:opacity-100 transition-opacity">
                                    <Link :href="item.link" class="p-1 rounded-lg hover:bg-muted text-muted-foreground hover:text-foreground transition-colors">
                                        <ChevronRight class="size-4" />
                                    </Link>
                                </div>
                            </div>
                        </div>
                        <div v-else class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-muted/20 py-16 text-center">
                            <Activity class="size-10 text-muted-foreground/30 mb-2.5 animate-pulse" />
                            <p class="text-sm font-medium text-muted-foreground">Chưa ghi nhận hoạt động vận hành nào hôm nay</p>
                        </div>
                    </div>

                    <!-- TAB Content: Live Table Grid Monitor -->
                    <div v-else-if="activeTab === 'tables'" class="space-y-4 animate-in fade-in-50 duration-200">
                        <div v-if="tablesList.length > 0" class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-3">
                            <div 
                                v-for="table in tablesList" 
                                :key="table.id"
                                class="relative flex flex-col gap-2 p-3 rounded-2xl border border-border bg-card shadow-sm hover:shadow-md hover:border-primary/20 transition-all group cursor-pointer"
                            >
                                <div class="flex items-start justify-between gap-1.5">
                                    <span class="text-[10px] text-muted-foreground truncate font-medium">{{ table.area }}</span>
                                    <!-- Status dot/indicator -->
                                    <span class="h-2 w-2 rounded-full shrink-0 mt-1"
                                          :class="{
                                              'bg-emerald-500 animate-pulse': table.status === 'available',
                                              'bg-indigo-500': table.status === 'occupied',
                                              'bg-violet-500': table.status === 'reserved',
                                              'bg-amber-500': table.status === 'cleaning'
                                          }"
                                    ></span>
                                </div>

                                <div class="mt-0.5">
                                    <h4 class="text-sm font-bold text-foreground group-hover:text-primary transition-colors flex items-center gap-1.5">
                                        <Utensils class="size-3.5 text-muted-foreground" />
                                        {{ table.name }}
                                    </h4>
                                </div>

                                <div class="mt-auto flex items-center justify-between gap-2 pt-1.5 border-t border-border/40">
                                    <span class="flex items-center gap-1 text-[10px] text-muted-foreground">
                                        <Users class="size-3" />
                                        {{ table.capacity }} chỗ
                                    </span>
                                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full border"
                                          :class="getTableStatusInfo(table.status).class"
                                    >
                                        {{ getTableStatusInfo(table.status).label }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div v-else class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-muted/20 py-16 text-center">
                            <BarChart3 class="size-10 text-muted-foreground/30 mb-2.5" />
                            <p class="text-sm font-medium text-muted-foreground">Không tìm thấy thông tin bàn ăn nào</p>
                        </div>
                    </div>

                    <!-- TAB Content: Low Stock Alerts -->
                    <div v-else-if="activeTab === 'inventory'" class="space-y-3 animate-in fade-in-50 duration-200">
                        <div v-if="lowStockList.length > 0" class="grid gap-3 sm:grid-cols-2">
                            <div 
                                v-for="item in lowStockList" 
                                :key="item.id"
                                class="flex flex-col gap-2 p-3.5 rounded-2xl border border-rose-100 bg-rose-50/10 dark:border-rose-950/20 dark:bg-rose-950/5 shadow-sm hover:shadow-md transition-all"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <h4 class="text-sm font-bold text-foreground truncate flex items-center gap-2">
                                            <Package class="size-4 text-rose-500" />
                                            {{ item.ingredient_name }}
                                        </h4>
                                        <p class="text-xs text-muted-foreground mt-0.5">Mức tối thiểu: {{ item.min_stock_level }} {{ item.unit_name }}</p>
                                    </div>
                                    <Link href="/inventory" class="shrink-0 text-xs font-semibold text-rose-600 hover:text-rose-700 hover:underline flex items-center gap-0.5">
                                        Nhập hàng <ChevronRight class="size-3" />
                                    </Link>
                                </div>

                                <!-- Custom Progress Bar representing Stock levels -->
                                <div class="mt-2.5 space-y-1">
                                    <div class="flex justify-between text-[10px] text-muted-foreground font-semibold">
                                        <span>Tồn kho hiện tại</span>
                                        <span class="text-rose-600 dark:text-rose-400 font-bold font-mono">{{ item.quantity_on_hand }} / {{ item.reorder_level }} {{ item.unit_name }}</span>
                                    </div>
                                    <div class="h-2 rounded-full bg-muted overflow-hidden">
                                        <div class="h-full rounded-full bg-rose-500 transition-all duration-500"
                                             :style="{ width: `${Math.min((item.quantity_on_hand / Math.max(item.reorder_level, 1)) * 100, 100)}%` }"
                                        ></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-emerald-500/5 py-12 text-center">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-950/20 text-emerald-600 dark:text-emerald-400 mb-3 animate-bounce">
                                <CheckCircle2 class="size-6" />
                            </div>
                            <h4 class="text-sm font-bold text-foreground">Tất cả nguyên liệu an toàn!</h4>
                            <p class="text-xs text-muted-foreground mt-1">Không ghi nhận nguyên liệu nào đang ở mức báo động tồn kho</p>
                        </div>
                    </div>
                </div>

                <!-- Right sidebar (1/3) -->
                <div class="flex flex-col gap-6">

                    <!-- Onboarding widget -->
                    <template v-if="!props.onboardingComplete">
                        <div>
                            <h2 class="text-base font-semibold">Hoàn thiện thiết lập</h2>
                            <Card class="mt-4">
                                <CardHeader class="pb-3">
                                    <CardTitle class="text-sm font-semibold">
                                        {{ onboardingProgress }}/{{ onboardingSteps.length }} bước hoàn thành
                                    </CardTitle>
                                    <div class="mt-2 h-1.5 rounded-full bg-muted">
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
                                        :class="step.done ? 'text-muted-foreground' : 'text-foreground'"
                                    >
                                        <span
                                            class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border text-xs"
                                            :class="step.done
                                                ? 'border-emerald-500 bg-emerald-500 text-white'
                                                : 'border-border bg-background'"
                                        >
                                            <ShieldCheck v-if="step.done" class="size-3" />
                                            <span v-else></span>
                                        </span>
                                        <span :class="step.done ? 'line-through opacity-60' : ''">{{ step.label }}</span>
                                    </Link>
                                </CardContent>
                            </Card>
                        </div>
                    </template>

                    <!-- ── 1. Đơn hàng gần đây ───────────────────── -->
                    <div>
                        <div class="flex items-center justify-between">
                            <h2 class="text-base font-semibold">Đơn hàng gần đây</h2>
                            <Link href="/orders" class="flex items-center gap-1 text-xs text-primary hover:underline">
                                Xem tất cả <ChevronRight class="size-3.5" />
                            </Link>
                        </div>

                        <Card class="mt-4">
                            <CardContent class="p-0">
                                <!-- Có đơn -->
                                <div v-if="recentOrdersList.length > 0" class="divide-y divide-border">
                                    <Link
                                        v-for="order in recentOrdersList"
                                        :key="order.id"
                                        href="/orders"
                                        class="flex items-center gap-3 px-4 py-3 hover:bg-muted/40 transition-colors group"
                                    >
                                        <!-- Icon channel -->
                                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-muted">
                                            <Utensils class="size-3.5 text-muted-foreground" />
                                        </div>

                                        <!-- Info -->
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-1.5">
                                                <span class="text-sm font-semibold truncate">#{{ order.order_number }}</span>
                                                <span v-if="order.table_name" class="text-xs text-muted-foreground truncate">· {{ order.table_name }}</span>
                                            </div>
                                            <div class="flex items-center gap-1.5 mt-0.5">
                                                <span class="text-xs text-muted-foreground">{{ channelMap[order.channel] ?? order.channel }}</span>
                                                <span class="text-xs text-muted-foreground">·</span>
                                                <span class="text-xs text-muted-foreground">{{ order.created_at }}</span>
                                            </div>
                                        </div>

                                        <!-- Right: badge + price -->
                                        <div class="flex flex-col items-end gap-1 shrink-0">
                                            <span
                                                class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium"
                                                :class="orderStatusMap[order.status]?.class ?? 'bg-muted text-muted-foreground'"
                                            >
                                                {{ orderStatusMap[order.status]?.label ?? order.status }}
                                            </span>
                                            <span class="text-xs font-semibold">{{ formatMoneyFull(order.total_amount) }}</span>
                                        </div>
                                    </Link>
                                </div>

                                <!-- Không có đơn -->
                                <div v-else class="flex flex-col items-center justify-center py-8 text-center">
                                    <ShoppingCart class="size-8 text-muted-foreground/40 mb-2" />
                                    <p class="text-sm text-muted-foreground">Chưa có đơn hàng nào hôm nay</p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- ── 2. Hiệu suất hôm nay ───────────────────── -->
                    <div v-if="props.stats">
                        <h2 class="text-base font-semibold">Hiệu suất hôm nay</h2>
                        <Card class="mt-4">
                            <CardContent class="pt-4 pb-4 space-y-4">

                                <!-- Tỉ lệ hoàn thành -->
                                <div>
                                    <div class="flex items-center justify-between text-sm mb-1.5">
                                        <span class="text-muted-foreground flex items-center gap-1.5">
                                            <CheckCircle2 class="size-3.5 text-emerald-500" />
                                            Tỉ lệ hoàn thành
                                        </span>
                                        <span class="font-semibold text-emerald-600 dark:text-emerald-400">
                                            {{ completionRate }}%
                                        </span>
                                    </div>
                                    <div class="h-2 rounded-full bg-muted overflow-hidden">
                                        <div
                                            class="h-full rounded-full bg-emerald-500 transition-all duration-500"
                                            :style="`width: ${completionRate}%`"
                                        />
                                    </div>
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        {{ props.stats.orders_completed }} / {{ props.stats.orders_today }} đơn
                                    </p>
                                </div>

                                <!-- Tỉ lệ huỷ -->
                                <div>
                                    <div class="flex items-center justify-between text-sm mb-1.5">
                                        <span class="text-muted-foreground flex items-center gap-1.5">
                                            <XCircle class="size-3.5 text-rose-500" />
                                            Tỉ lệ huỷ đơn
                                        </span>
                                        <span
                                            class="font-semibold"
                                            :class="cancellationRate > 20 ? 'text-rose-600 dark:text-rose-400' : 'text-muted-foreground'"
                                        >
                                            {{ cancellationRate }}%
                                        </span>
                                    </div>
                                    <div class="h-2 rounded-full bg-muted overflow-hidden">
                                        <div
                                            class="h-full rounded-full transition-all duration-500"
                                            :class="cancellationRate > 20 ? 'bg-rose-500' : 'bg-rose-300'"
                                            :style="`width: ${cancellationRate}%`"
                                        />
                                    </div>
                                    <p class="mt-1 text-xs text-muted-foreground">
                                        {{ props.stats.orders_cancelled ?? 0 }} đơn đã huỷ
                                    </p>
                                </div>

                                <!-- Đang xử lý / pending -->
                                <div class="flex items-center justify-between rounded-lg bg-muted/50 px-3 py-2.5">
                                    <span class="flex items-center gap-2 text-sm text-muted-foreground">
                                        <Clock class="size-3.5 text-amber-500" />
                                        Đơn đang xử lý
                                    </span>
                                    <span class="text-sm font-bold" :class="pendingCount > 5 ? 'text-amber-600' : ''">
                                        {{ pendingCount }}
                                    </span>
                                </div>

                                <!-- Doanh thu -->
                                <div class="flex items-center justify-between rounded-lg bg-emerald-50 dark:bg-emerald-950/20 px-3 py-2.5">
                                    <span class="flex items-center gap-2 text-sm text-emerald-700 dark:text-emerald-400">
                                        <Banknote class="size-3.5" />
                                        Doanh thu
                                    </span>
                                    <span class="text-sm font-bold text-emerald-700 dark:text-emerald-400">
                                        {{ formatMoneyFull(props.stats.revenue_today) }}
                                    </span>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- ── 3. Cảnh báo & Nhắc nhở ─────────────────── -->
                    <div v-if="alertsList.length > 0">
                        <div class="flex items-center gap-2">
                            <h2 class="text-base font-semibold">Cảnh báo & Nhắc nhở</h2>
                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-rose-500 text-white text-[10px] font-bold">
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
                                    class="size-4 shrink-0 mt-0.5"
                                />
                                <span class="leading-snug">{{ alert.message }}</span>
                                <ChevronRight class="size-3.5 ml-auto shrink-0 mt-0.5 opacity-60" />
                            </Link>
                        </div>
                    </div>

                </div>
                <!-- /right sidebar -->

            </div>
        </section>
    </AppTopbarLayout>
</template>
