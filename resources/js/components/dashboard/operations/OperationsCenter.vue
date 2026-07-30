<script setup lang="ts">
import { Link, Deferred } from '@inertiajs/vue3';
import {
    Activity,
    BarChart3,
    AlertTriangle,
    Crown,
    ChevronRight,
    ShoppingCart,
    Utensils,
    CheckCircle2,
    XCircle,
    Users,
    Building2,
    CalendarDays,
    Package,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { useFeatureGate } from '@/composables/useFeatureGate';
import ShiftHeatmap from './ShiftHeatmap.vue';

const { can, planCode } = useFeatureGate();
const activePlanCode = computed(() => planCode());

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
    status: string;
}

interface LowStockIngredient {
    id: number;
    ingredient_name: string;
    quantity_on_hand: number;
    min_stock_level: number;
    reorder_level: number;
    unit_name: string;
}

interface OwnerSummary {
    top_products_today: { name: string; qty: number; revenue: number }[];
    active_shifts: { name: string; shift: string; status: string }[];
    pending_over_20min: number;
    revenue_this_week: number;
    revenue_last_week: number;
}

interface ShiftRevenueRow {
    shift_name: string;
    days: { date: string; revenue: number }[];
}

const props = defineProps<{
    operationFeed: OperationFeedItem[] | undefined;
    tablesData: TableData[] | undefined;
    lowStockInventory: LowStockIngredient[] | undefined;
    ownerSummary: OwnerSummary | null | undefined;
    shiftRevenue: ShiftRevenueRow[] | undefined;
}>();

const activeTab = ref<'feed' | 'tables' | 'inventory' | 'owner'>('feed');

const operationFeedList = computed(() => props.operationFeed ?? []);
const tablesList = computed(() => props.tablesData ?? []);
const lowStockList = computed(() => props.lowStockInventory ?? []);

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
            return 'border-amber-100 bg-amber-50 text-amber-600 dark:border-amber-900/30 dark:bg-amber-950/20 dark:text-amber-400';
        case 'violet':
            return 'border-violet-100 bg-violet-50 text-violet-600 dark:border-violet-900/30 dark:bg-violet-950/20 dark:text-violet-400';
        case 'emerald':
            return 'border-emerald-100 bg-emerald-50 text-emerald-600 dark:border-emerald-900/30 dark:bg-emerald-950/20 dark:text-emerald-400';
        case 'rose':
            return 'border-rose-100 bg-rose-50 text-rose-600 dark:border-rose-900/30 dark:bg-rose-950/20 dark:text-rose-455';
        case 'sky':
            return 'border-sky-100 bg-sky-50 text-sky-650 text-sky-600 dark:border-sky-900/30 dark:bg-sky-950/20 dark:text-sky-400';
        default:
            return 'border-slate-100 bg-slate-50 text-slate-650 text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-450';
    }
}

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
</script>

<template>
    <Deferred :data="['operationFeed', 'tablesData', 'lowStockInventory', 'ownerSummary', 'shiftRevenue']">
        <template #fallback>
            <div class="space-y-6">
                <!-- Simple header skeleton -->
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="h-6 w-48 animate-pulse bg-slate-100 dark:bg-slate-800 rounded-lg" />
                    <div class="h-10 w-64 animate-pulse bg-slate-100 dark:bg-slate-800 rounded-lg" />
                </div>
                <!-- Main skeleton card -->
                <div class="h-[400px] w-full animate-pulse rounded-2xl border border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/10 flex items-center justify-center">
                    <div class="flex flex-col items-center gap-2">
                        <Activity class="size-6 text-slate-350 dark:text-slate-650 animate-pulse" />
                        <span class="text-xs text-slate-400 font-bold tracking-tight">Đang tải trung tâm vận hành...</span>
                    </div>
                </div>
            </div>
        </template>
        <div class="space-y-6">
        <!-- Section Header and Tabs selector -->
        <div
            class="flex flex-col justify-between gap-4 border-b border-slate-100 pb-3 md:flex-row md:items-center dark:border-slate-800"
        >
            <div>
                <h2
                    class="flex items-center gap-2 text-lg font-extrabold text-slate-800 dark:text-slate-100"
                >
                    <span class="relative flex h-2.5 w-2.5">
                        <span
                            class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"
                        ></span>
                        <span
                            class="relative inline-flex h-2.5 w-2.5 rounded-full bg-emerald-500"
                        ></span>
                    </span>
                    Giám sát vận hành
                </h2>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    Theo dõi hoạt động, trạng thái bàn và cảnh báo thời gian
                    thực
                </p>
            </div>

            <!-- Tab Switcher -->
            <div
                v-if="activePlanCode !== 'free'"
                class="flex flex-wrap self-start rounded-xl border border-slate-200/50 bg-slate-100/80 p-1 text-xs text-muted-foreground shadow-inner md:self-auto dark:border-slate-800/40 dark:bg-slate-900/80"
            >
                <button
                    @click="activeTab = 'feed'"
                    :class="
                        activeTab === 'feed'
                            ? 'bg-white font-bold text-slate-900 shadow-sm dark:bg-slate-950 dark:text-white'
                            : 'font-medium hover:text-slate-900 dark:hover:text-white'
                    "
                    class="flex cursor-pointer items-center gap-1.5 rounded-lg px-3.5 py-2 transition-all"
                >
                    <Activity class="size-3.5" />
                    Nhật ký
                </button>
                <button
                    @click="activeTab = 'tables'"
                    :class="
                        activeTab === 'tables'
                            ? 'bg-white font-bold text-slate-900 shadow-sm dark:bg-slate-950 dark:text-white'
                            : 'font-medium hover:text-slate-900 dark:hover:text-white'
                    "
                    class="flex cursor-pointer items-center gap-1.5 rounded-lg px-3.5 py-2 transition-all"
                >
                    <BarChart3 class="size-3.5" />
                    Sơ đồ Bàn
                </button>
                <button
                    v-if="can('inventory_basic')"
                    @click="activeTab = 'inventory'"
                    :class="
                        activeTab === 'inventory'
                            ? 'bg-white font-bold text-slate-900 shadow-sm dark:bg-slate-950 dark:text-white'
                            : 'font-medium hover:text-slate-900 dark:hover:text-white'
                    "
                    class="flex cursor-pointer items-center gap-1.5 rounded-lg px-3.5 py-2 transition-all"
                >
                    <AlertTriangle class="size-3.5" />
                    Tồn kho
                    <span
                        v-if="lowStockList.length"
                        class="to-red-655 flex h-4.5 w-4.5 scale-90 animate-pulse items-center justify-center rounded-full bg-gradient-to-r from-rose-500 text-[9px] leading-none font-black text-white shadow-sm"
                    >
                        {{ lowStockList.length }}
                    </span>
                </button>
                <button
                    @click="activeTab = 'owner'"
                    :class="
                        activeTab === 'owner'
                            ? 'bg-white font-bold text-slate-900 shadow-sm dark:bg-slate-950 dark:text-white'
                            : 'font-medium hover:text-slate-900 dark:hover:text-white'
                    "
                    class="flex cursor-pointer items-center gap-1.5 rounded-lg px-3.5 py-2 transition-all"
                >
                    <Crown class="size-3.5 text-amber-500" />
                    Chủ quán
                </button>
            </div>
        </div>

        <!-- TAB Content: Live Activity Feed -->
        <div
            v-if="activeTab === 'feed'"
            class="animate-in space-y-3.5 duration-200 fade-in-50"
        >
            <div
                v-if="operationFeedList.length > 0"
                class="relative space-y-4 border-l-2 border-slate-100 py-2 pl-4 dark:border-slate-800"
            >
                <div
                    v-for="(item, idx) in operationFeedList"
                    :key="idx"
                    class="group relative flex gap-4 rounded-2xl border border-slate-100 bg-white p-4 shadow-sm transition-all duration-300 hover:border-slate-200 hover:shadow-md dark:border-slate-800/80 dark:bg-slate-900/40 dark:hover:border-slate-700/80"
                >
                    <!-- Line pointer dot -->
                    <div
                        class="absolute top-6.5 -left-[23px] h-3.5 w-3.5 rounded-full border-3 border-white dark:border-slate-950"
                        :class="{
                            'bg-amber-500 ring-4 ring-amber-500/10':
                                item.color === 'amber',
                            'bg-violet-500 ring-4 ring-violet-500/10':
                                item.color === 'violet',
                            'bg-emerald-500 ring-4 ring-emerald-500/10':
                                item.color === 'emerald',
                            'bg-rose-500 ring-4 ring-rose-500/10':
                                item.color === 'rose',
                            'bg-sky-500 ring-4 ring-sky-500/10':
                                item.color === 'sky',
                        }"
                    ></div>

                    <!-- Icon -->
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border transition-transform group-hover:scale-105"
                        :class="getFeedColorClasses(item.color)"
                    >
                        <component
                            :is="getFeedIcon(item.icon)"
                            class="size-5"
                        />
                    </div>

                    <!-- Content -->
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-2">
                            <h4
                                class="truncate text-sm font-bold text-slate-800 dark:text-slate-200"
                            >
                                {{ item.title }}
                            </h4>
                            <span
                                class="dark:bg-slate-850 shrink-0 rounded-md bg-slate-50 px-2 py-0.5 font-mono text-[10px] font-bold text-muted-foreground"
                                >{{ item.time }}</span
                            >
                        </div>
                        <p
                            class="mt-1.5 text-xs leading-relaxed font-medium text-slate-500 dark:text-slate-400"
                        >
                            {{ item.description }}
                        </p>
                    </div>

                    <!-- Link button or details arrow -->
                    <div
                        class="flex shrink-0 items-center justify-center self-center opacity-0 transition-opacity duration-300 group-hover:opacity-100"
                    >
                        <Link
                            :href="item.link"
                            class="rounded-lg p-1.5 text-slate-400 transition-colors hover:bg-slate-50 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                        >
                            <ChevronRight class="size-4" />
                        </Link>
                    </div>
                </div>
            </div>
            <div
                v-else
                class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50/50 py-16 text-center dark:border-slate-800 dark:bg-slate-900/5"
            >
                <Activity
                    class="mb-3 size-10 animate-pulse text-muted-foreground/30"
                />
                <p
                    class="text-sm font-semibold text-slate-500 dark:text-slate-400"
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
                class="grid grid-cols-2 gap-3.5 sm:grid-cols-3 xl:grid-cols-4"
            >
                <div
                    v-for="table in tablesList"
                    :key="table.id"
                    class="group relative flex cursor-pointer flex-col gap-3 rounded-2xl border border-slate-100 bg-white p-3.5 shadow-sm transition-all duration-300 hover:border-slate-200 hover:shadow-md dark:border-slate-800/80 dark:bg-slate-900/40 dark:hover:border-slate-700/80"
                >
                    <div class="flex items-start justify-between gap-1.5">
                        <span
                            class="truncate text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                            >{{ table.area }}</span
                        >
                        <span
                            class="h-2.5 w-2.5 shrink-0 rounded-full"
                            :class="{
                                'animate-pulse bg-emerald-500 ring-4 ring-emerald-500/10':
                                    table.status === 'available',
                                'bg-indigo-500 ring-4 ring-indigo-500/10':
                                    table.status === 'occupied',
                                'bg-violet-500 ring-4 ring-violet-500/10':
                                    table.status === 'reserved',
                                'bg-amber-500 ring-4 ring-amber-500/10':
                                    table.status === 'cleaning',
                            }"
                        ></span>
                    </div>

                    <div>
                        <h4
                            class="flex items-center gap-2 text-sm font-bold text-slate-800 transition-colors group-hover:text-teal-500 dark:text-slate-100 dark:group-hover:text-teal-400"
                        >
                            <Utensils class="size-4 text-slate-400" />
                            {{ table.name }}
                        </h4>
                    </div>

                    <div
                        class="mt-auto flex items-center justify-between gap-2 border-t border-slate-100 pt-2 dark:border-slate-800/40"
                    >
                        <span
                            class="flex items-center gap-1 text-[10px] font-bold text-slate-400"
                        >
                            <Users class="size-3" />
                            {{ table.capacity }} chỗ
                        </span>
                        <span
                            class="rounded-md border px-2 py-0.5 text-[9px] font-black tracking-wider uppercase"
                            :class="getTableStatusInfo(table.status).class"
                        >
                            {{ getTableStatusInfo(table.status).label }}
                        </span>
                    </div>
                </div>
            </div>
            <div
                v-else
                class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50/50 py-16 text-center dark:border-slate-800 dark:bg-slate-900/5"
            >
                <BarChart3 class="mb-3 size-10 text-muted-foreground/30" />
                <p
                    class="text-sm font-semibold text-slate-500 dark:text-slate-400"
                >
                    Không tìm thấy thông tin bàn ăn nào
                </p>
            </div>
        </div>

        <!-- TAB Content: Low Stock Alerts -->
        <div
            v-else-if="activeTab === 'inventory'"
            class="animate-in space-y-4 duration-200 fade-in-50"
        >
            <div
                v-if="lowStockList.length > 0"
                class="grid gap-4 sm:grid-cols-2"
            >
                <div
                    v-for="item in lowStockList"
                    :key="item.id"
                    class="group flex flex-col gap-3 rounded-2xl border border-rose-100 bg-rose-50/10 p-4 shadow-sm transition-all duration-300 hover:shadow-md dark:border-rose-950/20 dark:bg-rose-950/5"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h4
                                class="flex items-center gap-2 truncate text-sm font-bold text-slate-800 dark:text-slate-100"
                            >
                                <Package class="size-4.5 text-rose-500" />
                                {{ item.ingredient_name }}
                            </h4>
                            <p
                                class="mt-1 text-xs font-semibold text-slate-400 dark:text-slate-500"
                            >
                                Mức tối thiểu: {{ item.min_stock_level }}
                                {{ item.unit_name }}
                            </p>
                        </div>
                        <Link
                            href="/inventory"
                            class="dark:hover:text-rose-350 flex shrink-0 items-center gap-0.5 text-xs font-bold text-rose-600 hover:text-rose-700 hover:underline dark:text-rose-400"
                        >
                            Nhập hàng <ChevronRight class="size-3.5" />
                        </Link>
                    </div>

                    <div class="mt-1 space-y-1.5">
                        <div
                            class="flex justify-between text-[10px] font-bold tracking-wider text-slate-400 uppercase dark:text-slate-500"
                        >
                            <span>Tồn kho hiện tại</span>
                            <span
                                class="font-mono font-extrabold text-rose-600 dark:text-rose-400"
                                >{{ item.quantity_on_hand }} /
                                {{ item.reorder_level }}
                                {{ item.unit_name }}</span
                            >
                        </div>
                        <div
                            class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                        >
                            <div
                                class="to-red-650 h-full rounded-full bg-gradient-to-r from-rose-500 transition-all duration-500"
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
                class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-emerald-100 bg-emerald-500/5 py-12 text-center dark:border-emerald-950/20"
            >
                <div
                    class="mb-3 flex h-12 w-12 animate-bounce items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600 dark:bg-emerald-950/20 dark:text-emerald-400"
                >
                    <CheckCircle2 class="size-6.5" />
                </div>
                <h4
                    class="text-sm font-bold text-slate-800 dark:text-slate-100"
                >
                    Mức tồn kho an toàn!
                </h4>
                <p class="mt-1 text-xs text-muted-foreground">
                    Không ghi nhận nguyên liệu nào chạm mốc báo động tồn kho
                </p>
            </div>
        </div>

        <!-- TAB Content: Tổng quan Chủ quán -->
        <div
            v-else-if="activeTab === 'owner'"
            class="animate-in space-y-5 duration-200 fade-in-50"
        >
            <div v-if="ownerSummary" class="space-y-5">
                <!-- Doanh thu tuần này vs tuần trước -->
                <div class="grid grid-cols-2 gap-4">
                    <div
                        class="relative overflow-hidden rounded-2xl border border-indigo-100 bg-indigo-500/5 p-4 dark:border-indigo-950/30"
                    >
                        <div
                            class="pointer-events-none absolute -right-5 -bottom-5 text-indigo-500/5 dark:text-indigo-500/10"
                        >
                            <BarChart3 class="size-20" />
                        </div>
                        <p
                            class="text-[10px] font-extrabold tracking-widest text-indigo-500 uppercase dark:text-indigo-400"
                        >
                            Doanh thu tuần này
                        </p>
                        <p
                            class="dark:text-indigo-350 mt-1.5 text-2xl leading-none font-black text-indigo-700"
                        >
                            {{ formatMoney(ownerSummary.revenue_this_week) }}
                        </p>
                    </div>
                    <div
                        class="relative overflow-hidden rounded-2xl border border-slate-100 bg-slate-500/5 p-4 dark:border-slate-800"
                    >
                        <p
                            class="text-[10px] font-extrabold tracking-widest text-slate-400 uppercase dark:text-slate-500"
                        >
                            Tuần trước
                        </p>
                        <div class="mt-1.5 flex items-baseline gap-2">
                            <p
                                class="text-2xl leading-none font-black text-slate-700 dark:text-slate-300"
                            >
                                {{
                                    formatMoney(ownerSummary.revenue_last_week)
                                }}
                            </p>
                            <span
                                v-if="ownerSummary.revenue_last_week > 0"
                                :class="
                                    ownerSummary.revenue_this_week >=
                                    ownerSummary.revenue_last_week
                                        ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/30'
                                        : 'bg-rose-50 text-rose-500 dark:bg-rose-950/30'
                                "
                                class="flex items-center gap-0.5 rounded-md px-1.5 py-0.5 text-[9px] font-extrabold"
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
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Cảnh báo đơn chờ lâu -->
                <div
                    v-if="ownerSummary.pending_over_20min > 0"
                    class="flex items-center gap-3 rounded-2xl border border-amber-500/20 bg-amber-500/10 p-3.5 text-xs shadow-sm dark:border-amber-900/20 dark:bg-amber-950/10"
                >
                    <AlertTriangle class="size-5 shrink-0 text-amber-500" />
                    <span class="font-bold text-amber-800 dark:text-amber-300">
                        Hệ thống ghi nhận
                        {{ ownerSummary.pending_over_20min }} đơn chờ quá 20
                        phút chưa được xử lý!
                    </span>
                    <Link
                        href="/orders?status=pending"
                        class="ml-auto shrink-0 font-bold text-amber-600 hover:text-amber-700 hover:underline dark:text-amber-400"
                        >Xử lý ngay →</Link
                    >
                </div>

                <!-- Top 3 sản phẩm hôm nay -->
                <div
                    class="rounded-2xl border border-slate-100 bg-white p-4 dark:border-slate-800 dark:bg-slate-900/20"
                >
                    <p
                        class="mb-3 flex items-center gap-1.5 text-[10px] font-bold tracking-widest text-slate-400 uppercase dark:text-slate-500"
                    >
                        <span>🏆</span> Top sản phẩm hôm nay
                    </p>
                    <div
                        v-if="
                            ownerSummary.top_products_today &&
                            ownerSummary.top_products_today.length
                        "
                        class="space-y-3"
                    >
                        <div
                            v-for="(p, i) in ownerSummary.top_products_today"
                            :key="p.name"
                            class="flex items-center gap-3 text-xs"
                        >
                            <span
                                class="w-6 shrink-0 text-center text-base font-bold"
                                >{{ ['🥇', '🥈', '🥉'][i] ?? '▪️' }}</span
                            >
                            <div class="min-w-0 flex-1">
                                <p
                                    class="truncate font-bold text-slate-800 dark:text-slate-200"
                                >
                                    {{ p.name }}
                                </p>
                                <p
                                    class="mt-0.5 text-[10px] text-muted-foreground"
                                >
                                    {{ formatMoneyFull(p.revenue) }}
                                </p>
                            </div>
                            <span
                                class="rounded-md bg-slate-50 px-2 py-0.5 font-mono font-black text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                >{{ p.qty }} bán ra</span
                            >
                        </div>
                    </div>
                    <p v-else class="text-xs text-slate-400 italic">
                        Chưa có dữ liệu sản phẩm hôm nay
                    </p>
                </div>

                <!-- Nhân viên đang làm việc -->
                <div
                    v-if="can('hr_timekeeping')"
                    class="rounded-2xl border border-slate-100 bg-white p-4 dark:border-slate-800 dark:bg-slate-900/20"
                >
                    <p
                        class="mb-3 flex items-center gap-1.5 text-[10px] font-bold tracking-widest text-slate-400 uppercase dark:text-slate-500"
                    >
                        <span>👥</span> Nhân sự làm việc hôm nay
                    </p>
                    <div
                        v-if="
                            ownerSummary.active_shifts &&
                            ownerSummary.active_shifts.length
                        "
                        class="grid grid-cols-1 gap-2.5 sm:grid-cols-2"
                    >
                        <div
                            v-for="s in ownerSummary.active_shifts"
                            :key="s.name + s.shift"
                            class="flex items-center gap-2.5 rounded-xl border border-slate-100/50 bg-slate-50/50 p-2 text-xs dark:border-slate-800/40 dark:bg-slate-900/40"
                        >
                            <span
                                :class="[
                                    'h-2 w-2 shrink-0 animate-pulse rounded-full',
                                    s.status === 'checked_in'
                                        ? 'bg-emerald-500'
                                        : 'bg-amber-400',
                                ]"
                            />
                            <div class="min-w-0 flex-1">
                                <p
                                    class="truncate leading-snug font-bold text-slate-800 dark:text-slate-200"
                                >
                                    {{ s.name }}
                                </p>
                                <p class="mt-0.5 text-[10px] text-slate-400">
                                    {{ s.shift }}
                                </p>
                            </div>
                            <span
                                class="rounded-md border border-slate-100 bg-white px-2 py-0.5 text-[9px] font-black tracking-wider text-slate-500 uppercase dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-400"
                            >
                                {{
                                    s.status === 'checked_in'
                                        ? 'Đã Check-in'
                                        : 'Chưa Check-in'
                                }}
                            </span>
                        </div>
                    </div>
                    <p v-else class="text-xs text-slate-400 italic">
                        Chưa có nhân viên nào check-in hôm nay
                    </p>
                </div>
            </div>
            <div
                v-else
                class="flex flex-col items-center py-16 text-center text-muted-foreground"
            >
                <Crown class="mb-2.5 size-8 animate-pulse text-amber-400/30" />
                <p class="text-xs font-semibold">
                    Đang tổng hợp thông tin chủ quán...
                </p>
            </div>
        </div>

        <!-- Heatmap component below the tabs -->
        <div
            v-if="can('hr_timekeeping')"
            class="rounded-2xl border border-slate-100 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900/30"
        >
            <ShiftHeatmap :shift-revenue="shiftRevenue" />
        </div>
    </div>
    </Deferred>
</template>
