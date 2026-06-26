<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
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
    Package
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
        case 'amber':   return 'border-amber-100 bg-amber-50 text-amber-600 dark:border-amber-900/30 dark:bg-amber-950/20 dark:text-amber-400';
        case 'violet':  return 'border-violet-100 bg-violet-50 text-violet-600 dark:border-violet-900/30 dark:bg-violet-950/20 dark:text-violet-400';
        case 'emerald': return 'border-emerald-100 bg-emerald-50 text-emerald-600 dark:border-emerald-900/30 dark:bg-emerald-950/20 dark:text-emerald-400';
        case 'rose':    return 'border-rose-100 bg-rose-50 text-rose-600 dark:border-rose-900/30 dark:bg-rose-950/20 dark:text-rose-455';
        case 'sky':     return 'border-sky-100 bg-sky-50 text-sky-650 text-sky-600 dark:border-sky-900/30 dark:bg-sky-950/20 dark:text-sky-400';
        default:        return 'border-slate-100 bg-slate-50 text-slate-650 text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-450';
    }
}

const tableStatusMap = {
    available: { label: 'Bàn trống', class: 'bg-emerald-50 text-emerald-700 border-emerald-100 dark:bg-emerald-950/20 dark:text-emerald-400 dark:border-emerald-900/30' },
    occupied:  { label: 'Có khách', class: 'bg-indigo-50 text-indigo-700 border-indigo-100 dark:bg-indigo-950/20 dark:text-indigo-400 dark:border-indigo-900/30' },
    reserved:  { label: 'Đặt trước', class: 'bg-violet-50 text-violet-700 border-violet-100 dark:bg-violet-950/20 dark:text-violet-400 dark:border-violet-900/30' },
    cleaning:  { label: 'Dọn dẹp', class: 'bg-amber-50 text-amber-700 border-amber-100 dark:bg-amber-950/20 dark:text-amber-400 dark:border-amber-900/30' },
};

function getTableStatusInfo(status: string) {
    return tableStatusMap[status as keyof typeof tableStatusMap] ?? { label: status, class: 'bg-muted text-muted-foreground border-border' };
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
</script>

<template>
    <div class="space-y-6">
        <!-- Section Header and Tabs selector -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-3 border-b border-slate-100 dark:border-slate-800">
            <div>
                <h2 class="text-lg font-extrabold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                    <span class="relative flex h-2.5 w-2.5">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                    </span>
                    Giám sát vận hành
                </h2>
                <p class="text-xs text-muted-foreground mt-0.5">Theo dõi hoạt động, trạng thái bàn và cảnh báo thời gian thực</p>
            </div>
            
            <!-- Tab Switcher -->
            <div v-if="activePlanCode !== 'free'" class="flex flex-wrap p-1 rounded-xl bg-slate-100/80 dark:bg-slate-900/80 text-muted-foreground text-xs self-start md:self-auto shadow-inner border border-slate-200/50 dark:border-slate-800/40">
                <button
                    @click="activeTab = 'feed'"
                    :class="activeTab === 'feed' ? 'bg-white dark:bg-slate-950 text-slate-900 dark:text-white shadow-sm font-bold' : 'hover:text-slate-900 dark:hover:text-white font-medium'"
                    class="flex items-center gap-1.5 px-3.5 py-2 rounded-lg transition-all cursor-pointer"
                >
                    <Activity class="size-3.5" />
                    Nhật ký
                </button>
                <button
                    @click="activeTab = 'tables'"
                    :class="activeTab === 'tables' ? 'bg-white dark:bg-slate-950 text-slate-900 dark:text-white shadow-sm font-bold' : 'hover:text-slate-900 dark:hover:text-white font-medium'"
                    class="flex items-center gap-1.5 px-3.5 py-2 rounded-lg transition-all cursor-pointer"
                >
                    <BarChart3 class="size-3.5" />
                    Sơ đồ Bàn
                </button>
                <button v-if="can('inventory_basic')"
                    @click="activeTab = 'inventory'"
                    :class="activeTab === 'inventory' ? 'bg-white dark:bg-slate-950 text-slate-900 dark:text-white shadow-sm font-bold' : 'hover:text-slate-900 dark:hover:text-white font-medium'"
                    class="flex items-center gap-1.5 px-3.5 py-2 rounded-lg transition-all cursor-pointer"
                >
                    <AlertTriangle class="size-3.5" />
                    Tồn kho
                    <span v-if="lowStockList.length" class="flex h-4.5 w-4.5 items-center justify-center rounded-full bg-gradient-to-r from-rose-500 to-red-655 text-[9px] font-black text-white leading-none scale-90 shadow-sm animate-pulse">
                        {{ lowStockList.length }}
                    </span>
                </button>
                <button
                    @click="activeTab = 'owner'"
                    :class="activeTab === 'owner' ? 'bg-white dark:bg-slate-950 text-slate-900 dark:text-white shadow-sm font-bold' : 'hover:text-slate-900 dark:hover:text-white font-medium'"
                    class="flex items-center gap-1.5 px-3.5 py-2 rounded-lg transition-all cursor-pointer"
                >
                    <Crown class="size-3.5 text-amber-500" />
                    Chủ quán
                </button>
            </div>
        </div>

        <!-- TAB Content: Live Activity Feed -->
        <div v-if="activeTab === 'feed'" class="space-y-3.5 animate-in fade-in-50 duration-200">
            <div v-if="operationFeedList.length > 0" class="relative pl-4 border-l-2 border-slate-100 dark:border-slate-800 space-y-4 py-2">
                <div 
                    v-for="(item, idx) in operationFeedList" 
                    :key="idx"
                    class="group relative flex gap-4 p-4 rounded-2xl border border-slate-100 dark:border-slate-800/80 bg-white dark:bg-slate-900/40 shadow-sm hover:shadow-md hover:border-slate-200 dark:hover:border-slate-700/80 transition-all duration-300"
                >
                    <!-- Line pointer dot -->
                    <div class="absolute -left-[23px] top-6.5 w-3.5 h-3.5 rounded-full border-3 border-white dark:border-slate-950"
                         :class="{
                             'bg-amber-500 ring-4 ring-amber-500/10': item.color === 'amber',
                             'bg-violet-500 ring-4 ring-violet-500/10': item.color === 'violet',
                             'bg-emerald-500 ring-4 ring-emerald-500/10': item.color === 'emerald',
                             'bg-rose-500 ring-4 ring-rose-500/10': item.color === 'rose',
                             'bg-sky-500 ring-4 ring-sky-500/10': item.color === 'sky',
                         }"
                    ></div>

                    <!-- Icon -->
                    <div 
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border transition-transform group-hover:scale-105"
                        :class="getFeedColorClasses(item.color)"
                    >
                        <component :is="getFeedIcon(item.icon)" class="size-5" />
                    </div>

                    <!-- Content -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200 truncate">{{ item.title }}</h4>
                            <span class="text-[10px] text-muted-foreground shrink-0 font-bold font-mono bg-slate-50 dark:bg-slate-850 px-2 py-0.5 rounded-md">{{ item.time }}</span>
                        </div>
                        <p class="mt-1.5 text-xs text-slate-500 dark:text-slate-400 leading-relaxed font-medium">{{ item.description }}</p>
                    </div>

                    <!-- Link button or details arrow -->
                    <div class="flex items-center justify-center shrink-0 self-center opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                        <Link :href="item.link" class="p-1.5 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors">
                            <ChevronRight class="size-4" />
                        </Link>
                    </div>
                </div>
            </div>
            <div v-else class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/5 py-16 text-center">
                <Activity class="size-10 text-muted-foreground/30 mb-3 animate-pulse" />
                <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">Chưa ghi nhận hoạt động vận hành nào hôm nay</p>
            </div>
        </div>

        <!-- TAB Content: Live Table Grid Monitor -->
        <div v-else-if="activeTab === 'tables'" class="space-y-4 animate-in fade-in-50 duration-200">
            <div v-if="tablesList.length > 0" class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-3.5">
                <div 
                    v-for="table in tablesList" 
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
            <div v-else class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/5 py-16 text-center">
                <BarChart3 class="size-10 text-muted-foreground/30 mb-3" />
                <p class="text-sm font-semibold text-slate-500 dark:text-slate-400">Không tìm thấy thông tin bàn ăn nào</p>
            </div>
        </div>

        <!-- TAB Content: Low Stock Alerts -->
        <div v-else-if="activeTab === 'inventory'" class="space-y-4 animate-in fade-in-50 duration-200">
            <div v-if="lowStockList.length > 0" class="grid gap-4 sm:grid-cols-2">
                <div 
                    v-for="item in lowStockList" 
                    :key="item.id"
                    class="group flex flex-col gap-3 p-4 rounded-2xl border border-rose-100 dark:border-rose-950/20 bg-rose-50/10 dark:bg-rose-950/5 shadow-sm hover:shadow-md transition-all duration-300"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h4 class="text-sm font-bold text-slate-800 dark:text-slate-100 truncate flex items-center gap-2">
                                <Package class="size-4.5 text-rose-500" />
                                {{ item.ingredient_name }}
                            </h4>
                            <p class="text-xs font-semibold text-slate-400 dark:text-slate-500 mt-1">Mức tối thiểu: {{ item.min_stock_level }} {{ item.unit_name }}</p>
                        </div>
                        <Link href="/inventory" class="shrink-0 text-xs font-bold text-rose-600 dark:text-rose-400 hover:text-rose-700 dark:hover:text-rose-350 hover:underline flex items-center gap-0.5">
                            Nhập hàng <ChevronRight class="size-3.5" />
                        </Link>
                    </div>

                    <div class="mt-1 space-y-1.5">
                        <div class="flex justify-between text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
                            <span>Tồn kho hiện tại</span>
                            <span class="text-rose-600 dark:text-rose-400 font-extrabold font-mono">{{ item.quantity_on_hand }} / {{ item.reorder_level }} {{ item.unit_name }}</span>
                        </div>
                        <div class="h-2 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                            <div class="h-full rounded-full bg-gradient-to-r from-rose-500 to-red-650 transition-all duration-500"
                                 :style="{ width: `${Math.min((item.quantity_on_hand / Math.max(item.reorder_level, 1)) * 100, 100)}%` }"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-emerald-100 dark:border-emerald-950/20 bg-emerald-500/5 py-12 text-center">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-emerald-500/10 dark:bg-emerald-950/20 text-emerald-600 dark:text-emerald-400 mb-3 animate-bounce">
                    <CheckCircle2 class="size-6.5" />
                </div>
                <h4 class="text-sm font-bold text-slate-800 dark:text-slate-100">Mức tồn kho an toàn!</h4>
                <p class="text-xs text-muted-foreground mt-1">Không ghi nhận nguyên liệu nào chạm mốc báo động tồn kho</p>
            </div>
        </div>

        <!-- TAB Content: Tổng quan Chủ quán -->
        <div v-else-if="activeTab === 'owner'" class="space-y-5 animate-in fade-in-50 duration-200">
            <div v-if="ownerSummary" class="space-y-5">
                <!-- Doanh thu tuần này vs tuần trước -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="rounded-2xl border border-indigo-100 dark:border-indigo-950/30 bg-indigo-500/5 p-4 relative overflow-hidden">
                        <div class="absolute -right-5 -bottom-5 text-indigo-500/5 dark:text-indigo-500/10 pointer-events-none">
                            <BarChart3 class="size-20" />
                        </div>
                        <p class="text-[10px] text-indigo-500 dark:text-indigo-400 font-extrabold uppercase tracking-widest">Doanh thu tuần này</p>
                        <p class="text-2xl font-black text-indigo-700 dark:text-indigo-350 mt-1.5 leading-none">
                            {{ formatMoney(ownerSummary.revenue_this_week) }}
                        </p>
                    </div>
                    <div class="rounded-2xl border border-slate-100 dark:border-slate-800 bg-slate-500/5 p-4 relative overflow-hidden">
                        <p class="text-[10px] text-slate-400 dark:text-slate-500 font-extrabold uppercase tracking-widest">Tuần trước</p>
                        <div class="flex items-baseline gap-2 mt-1.5">
                            <p class="text-2xl font-black text-slate-700 dark:text-slate-300 leading-none">
                                {{ formatMoney(ownerSummary.revenue_last_week) }}
                            </p>
                            <span v-if="ownerSummary.revenue_last_week > 0" 
                                :class="ownerSummary.revenue_this_week >= ownerSummary.revenue_last_week ? 'text-emerald-600 bg-emerald-50 dark:bg-emerald-950/30' : 'text-rose-500 bg-rose-50 dark:bg-rose-950/30'"
                                class="text-[9px] font-extrabold px-1.5 py-0.5 rounded-md flex items-center gap-0.5"
                            >
                                {{ ownerSummary.revenue_this_week >= ownerSummary.revenue_last_week ? '↑' : '↓' }}
                                {{ Math.abs(Math.round((ownerSummary.revenue_this_week - ownerSummary.revenue_last_week) / ownerSummary.revenue_last_week * 100)) }}%
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Cảnh báo đơn chờ lâu -->
                <div v-if="ownerSummary.pending_over_20min > 0"
                    class="flex items-center gap-3 p-3.5 rounded-2xl bg-amber-500/10 dark:bg-amber-950/10 border border-amber-500/20 dark:border-amber-900/20 text-xs shadow-sm">
                    <AlertTriangle class="size-5 text-amber-500 shrink-0" />
                    <span class="text-amber-800 dark:text-amber-300 font-bold">
                        Hệ thống ghi nhận {{ ownerSummary.pending_over_20min }} đơn chờ quá 20 phút chưa được xử lý!
                    </span>
                    <Link href="/orders?status=pending" class="ml-auto text-amber-600 dark:text-amber-400 hover:text-amber-700 font-bold hover:underline shrink-0">Xử lý ngay →</Link>
                </div>

                <!-- Top 3 sản phẩm hôm nay -->
                <div class="rounded-2xl border border-slate-100 dark:border-slate-800 p-4 bg-white dark:bg-slate-900/20">
                    <p class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-3 flex items-center gap-1.5">
                        <span>🏆</span> Top sản phẩm hôm nay
                    </p>
                    <div v-if="ownerSummary.top_products_today && ownerSummary.top_products_today.length" class="space-y-3">
                        <div v-for="(p, i) in ownerSummary.top_products_today" :key="p.name"
                            class="flex items-center gap-3 text-xs">
                            <span class="text-base w-6 text-center shrink-0 font-bold">{{ ['🥇', '🥈', '🥉'][i] ?? '▪️' }}</span>
                            <div class="flex-1 min-w-0">
                                <p class="font-bold text-slate-800 dark:text-slate-200 truncate">{{ p.name }}</p>
                                <p class="text-[10px] text-muted-foreground mt-0.5">{{ formatMoneyFull(p.revenue) }}</p>
                            </div>
                            <span class="text-slate-700 dark:text-slate-300 font-black font-mono bg-slate-50 dark:bg-slate-800 px-2 py-0.5 rounded-md">{{ p.qty }} bán ra</span>
                        </div>
                    </div>
                    <p v-else class="text-xs text-slate-400 italic">Chưa có dữ liệu sản phẩm hôm nay</p>
                </div>

                <!-- Nhân viên đang làm việc -->
                <div v-if="can('hr_timekeeping')" class="rounded-2xl border border-slate-100 dark:border-slate-800 p-4 bg-white dark:bg-slate-900/20">
                    <p class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest mb-3 flex items-center gap-1.5">
                        <span>👥</span> Nhân sự làm việc hôm nay
                    </p>
                    <div v-if="ownerSummary.active_shifts && ownerSummary.active_shifts.length" class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        <div v-for="s in ownerSummary.active_shifts" :key="s.name + s.shift"
                            class="flex items-center gap-2.5 p-2 rounded-xl bg-slate-50/50 dark:bg-slate-900/40 border border-slate-100/50 dark:border-slate-800/40 text-xs">
                            <span :class="['h-2 w-2 rounded-full shrink-0 animate-pulse', s.status === 'checked_in' ? 'bg-emerald-500' : 'bg-amber-400']" />
                            <div class="min-w-0 flex-1">
                                <p class="font-bold text-slate-800 dark:text-slate-200 truncate leading-snug">{{ s.name }}</p>
                                <p class="text-[10px] text-slate-400 mt-0.5">{{ s.shift }}</p>
                            </div>
                            <span class="text-[9px] font-black uppercase tracking-wider px-2 py-0.5 rounded-md border border-slate-100 bg-white text-slate-500 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-400">
                                {{ s.status === 'checked_in' ? 'Đã Check-in' : 'Chưa Check-in' }}
                            </span>
                        </div>
                    </div>
                    <p v-else class="text-xs text-slate-400 italic">Chưa có nhân viên nào check-in hôm nay</p>
                </div>
            </div>
            <div v-else class="flex flex-col items-center py-16 text-muted-foreground text-center">
                <Crown class="size-8 text-amber-400/30 mb-2.5 animate-pulse" />
                <p class="text-xs font-semibold">Đang tổng hợp thông tin chủ quán...</p>
            </div>
        </div>

        <!-- Heatmap component below the tabs -->
        <div v-if="can('hr_timekeeping')" class="rounded-2xl border border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-900/30 p-4 shadow-sm">
            <ShiftHeatmap :shift-revenue="shiftRevenue" />
        </div>
    </div>
</template>
