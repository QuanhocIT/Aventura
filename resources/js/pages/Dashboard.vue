<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
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
} from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import NewsCard from '@/components/NewsCard.vue';
import AppTopbarLayout from '@/layouts/AppTopbarLayout.vue';

interface NewsPost {
    id: number;
    title: string;
    slug: string;
    excerpt: string | null;
    category: string;
    featured_image_url: string | null;
    is_featured: boolean;
    published_at: string;
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

interface FeaturedPost {
    id: number;
    title: string;
    slug: string;
    excerpt: string | null;
    category: string;
    featured_image_url: string | null;
    is_featured: boolean;
    published_at: string;
}

interface Alert {
    type: 'warning' | 'danger' | 'info';
    message: string;
    href: string;
}

const props = defineProps<{
    latestNews?: NewsPost[];
    featuredPost?: FeaturedPost | null;
    stats?: Stats | null;
    onboardingComplete?: boolean;
    recentOrders?: RecentOrder[];
    alerts?: Alert[];
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
    if (!plan.value?.code) return 0;
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

const latestNewsList = computed(() => props.latestNews ?? []);
const recentOrdersList = computed(() => props.recentOrders ?? []);
const alertsList = computed(() => props.alerts ?? []);

// Trial countdown
const isOnTrial = computed(() => tenant.value?.status === 'trial');
const trialDaysLeft = computed(() => {
    if (!tenant.value?.trial_ends_at) return 0;
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
    if (v === 0) return '—';
    return new Intl.NumberFormat('vi-VN', { notation: 'compact', maximumFractionDigits: 1 }).format(v) + 'đ';
}

function formatMoneyFull(v: number): string {
    if (v === 0) return '—';
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
    if (!props.stats?.orders_today) return 0;
    return Math.round((props.stats.orders_completed / props.stats.orders_today) * 100);
});

const cancellationRate = computed(() => {
    if (!props.stats?.orders_today) return 0;
    return Math.round(((props.stats.orders_cancelled ?? 0) / props.stats.orders_today) * 100);
});

const pendingCount = computed(() => {
    if (!props.stats) return 0;
    return props.stats.orders_today - props.stats.orders_completed - (props.stats.orders_cancelled ?? 0);
});

// Alert icon map
const alertIconMap = { warning: AlertTriangle, danger: XCircle, info: Info };
const alertColorMap = {
    warning: 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800/50 dark:bg-amber-950/30 dark:text-amber-300',
    danger:  'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-800/50 dark:bg-rose-950/30 dark:text-rose-300',
    info:    'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-800/50 dark:bg-sky-950/30 dark:text-sky-300',
};
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

        <!-- ── News + Right sidebar ──────────────────────────────── -->
        <section class="px-4 pb-14 lg:px-8">
            <div class="mx-auto max-w-7xl grid gap-6 lg:grid-cols-3">

                <!-- News (2/3) -->
                <div class="lg:col-span-2">
                    <div class="flex items-center justify-between">
                        <h2 class="text-base font-semibold">Tin tức & cập nhật</h2>
                        <Link href="/tin-tuc" class="flex items-center gap-1 text-xs text-primary hover:underline">
                            Xem tất cả <ChevronRight class="size-3.5" />
                        </Link>
                    </div>

                    <!-- ── Hero: Bài tin tức nổi bật ── -->
                    <Link
                        v-if="props.featuredPost"
                        :href="`/tin-tuc/${props.featuredPost.slug}`"
                        class="group mt-4 flex flex-col sm:flex-row overflow-hidden rounded-2xl border border-primary/30 bg-card shadow-sm transition hover:shadow-md hover:-translate-y-0.5"
                    >
                        <!-- Ảnh nối bật -->
                        <div class="relative sm:w-56 lg:w-64 shrink-0 aspect-video sm:aspect-auto overflow-hidden bg-muted">
                            <img
                                v-if="props.featuredPost.featured_image_url"
                                :src="props.featuredPost.featured_image_url"
                                :alt="props.featuredPost.title"
                                class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
                            />
                            <div v-else class="flex h-full w-full items-center justify-center bg-gradient-to-br from-primary/20 to-primary/5">
                                <Star class="size-10 text-primary/30" />
                            </div>
                            <!-- Badge nổi bật -->
                            <span class="absolute left-3 top-3 flex items-center gap-1 rounded-full bg-primary px-2.5 py-1 text-[11px] font-semibold text-primary-foreground shadow">
                                <Star class="size-3" /> Nổi bật
                            </span>
                        </div>

                        <!-- Nội dung -->
                        <div class="flex flex-1 flex-col gap-2 p-5">
                            <!-- Category badge -->
                            <span class="inline-flex w-fit items-center rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-medium text-primary">
                                {{ props.featuredPost.category }}
                            </span>

                            <h3 class="text-base font-bold leading-snug text-foreground group-hover:text-primary transition-colors line-clamp-2">
                                {{ props.featuredPost.title }}
                            </h3>

                            <p v-if="props.featuredPost.excerpt" class="text-sm text-muted-foreground line-clamp-3 leading-relaxed">
                                {{ props.featuredPost.excerpt }}
                            </p>

                            <div class="mt-auto flex items-center justify-between pt-2">
                                <span class="flex items-center gap-1.5 text-xs text-muted-foreground">
                                    <Calendar class="size-3.5" />
                                    {{ props.featuredPost.published_at }}
                                </span>
                                <span class="flex items-center gap-1 text-xs font-medium text-primary opacity-0 group-hover:opacity-100 transition-opacity">
                                    Đọc ngay <ChevronRight class="size-3.5" />
                                </span>
                            </div>
                        </div>
                    </Link>

                    <!-- Grid các bài còn lại -->
                    <div v-if="latestNewsList.length > 0" class="mt-4 grid gap-4 sm:grid-cols-2">
                        <NewsCard
                            v-for="post in latestNewsList"
                            :key="post.id"
                            :title="post.title"
                            :slug="post.slug"
                            :excerpt="post.excerpt"
                            :category="post.category"
                            :featured_image_url="post.featured_image_url"
                            :is_featured="post.is_featured"
                            :published_at="post.published_at"
                        />
                    </div>
                    <div v-else-if="!props.featuredPost" class="mt-4 flex items-center justify-center rounded-xl border border-border bg-muted/30 py-10 text-sm text-muted-foreground">
                        Chưa có bài viết nào.
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
