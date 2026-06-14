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
    CalendarDays
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import ShiftHeatmap from './ShiftHeatmap.vue';

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
    <div class="space-y-4">
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
            
            <!-- Tab Switcher -->
            <div class="flex flex-wrap p-0.5 rounded-xl border border-border bg-muted/50 text-muted-foreground text-xs self-start sm:self-auto shadow-inner">
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
                <button
                    @click="activeTab = 'owner'"
                    :class="activeTab === 'owner' ? 'bg-background text-foreground shadow-sm font-semibold' : 'hover:text-foreground'"
                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg transition-all"
                >
                    <Crown class="size-3.5 text-amber-500" />
                    Chủ quán
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

        <!-- TAB Content: Tổng quan Chủ quán -->
        <div v-if="activeTab === 'owner'" class="space-y-4 animate-in fade-in-50 duration-200">
            <div v-if="ownerSummary" class="space-y-4">
                <!-- Doanh thu tuần này vs tuần trước -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-xl border border-indigo-200 bg-indigo-50 dark:bg-indigo-950/20 dark:border-indigo-800/40 p-3">
                        <p class="text-[10px] text-indigo-500 font-bold uppercase tracking-wider">Tuần này</p>
                        <p class="text-xl font-black text-indigo-700 dark:text-indigo-300 mt-1">
                            {{ formatMoney(ownerSummary.revenue_this_week) }}
                        </p>
                    </div>
                    <div class="rounded-xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/40 p-3">
                        <p class="text-[10px] text-slate-500 font-bold uppercase tracking-wider">Tuần trước</p>
                        <p class="text-xl font-black text-slate-600 dark:text-slate-400 mt-1">
                            {{ formatMoney(ownerSummary.revenue_last_week) }}
                        </p>
                        <p v-if="ownerSummary.revenue_last_week > 0" class="text-[10px] mt-0.5"
                            :class="ownerSummary.revenue_this_week >= ownerSummary.revenue_last_week ? 'text-emerald-600' : 'text-rose-500'">
                            {{ ownerSummary.revenue_this_week >= ownerSummary.revenue_last_week ? '↑' : '↓' }}
                            {{ Math.abs(Math.round((ownerSummary.revenue_this_week - ownerSummary.revenue_last_week) / ownerSummary.revenue_last_week * 100)) }}%
                        </p>
                    </div>
                </div>

                <!-- Cảnh báo đơn chờ lâu -->
                <div v-if="ownerSummary.pending_over_20min > 0"
                    class="flex items-center gap-2 p-3 rounded-xl bg-amber-50 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-800/40 text-xs">
                    <AlertTriangle class="size-4 text-amber-500 shrink-0" />
                    <span class="text-amber-700 dark:text-amber-400 font-semibold">
                        {{ ownerSummary.pending_over_20min }} đơn chờ quá 20 phút chưa xử lý
                    </span>
                    <Link href="/orders?status=pending" class="ml-auto text-amber-600 hover:underline shrink-0">Xem →</Link>
                </div>

                <!-- Top 3 sản phẩm hôm nay -->
                <div>
                    <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider mb-2">🏆 Top sản phẩm hôm nay</p>
                    <div v-if="ownerSummary.top_products_today.length" class="space-y-2">
                        <div v-for="(p, i) in ownerSummary.top_products_today" :key="p.name"
                            class="flex items-center gap-2 text-xs">
                            <span class="text-sm w-5 shrink-0">{{ ['🥇', '🥈', '🥉'][i] ?? '▪️' }}</span>
                            <span class="flex-1 truncate font-medium">{{ p.name }}</span>
                            <span class="text-muted-foreground font-mono">{{ p.qty }} đơn</span>
                        </div>
                    </div>
                    <p class="text-xs text-muted-foreground italic">Chưa có đơn hàng hoàn thành hôm nay</p>
                </div>

                <!-- Nhân viên đang làm việc -->
                <div>
                    <p class="text-[10px] font-bold text-muted-foreground uppercase tracking-wider mb-2">👥 Nhân sự hôm nay</p>
                    <div v-if="ownerSummary.active_shifts.length" class="space-y-1.5">
                        <div v-for="s in ownerSummary.active_shifts" :key="s.name + s.shift"
                            class="flex items-center gap-2 text-xs">
                            <span :class="['h-1.5 w-1.5 rounded-full shrink-0', s.status === 'checked_in' ? 'bg-emerald-500' : 'bg-amber-400']" />
                            <span class="flex-1 truncate">{{ s.name }}</span>
                            <span class="text-muted-foreground text-[10px]">{{ s.shift }}</span>
                        </div>
                    </div>
                    <p class="text-xs text-muted-foreground italic">Chưa có nhân viên check-in hôm nay</p>
                </div>
            </div>
            <div v-else class="flex flex-col items-center py-12 text-muted-foreground text-center">
                <Crown class="size-8 text-amber-400/30 mb-2" />
                <p class="text-xs">Dữ liệu đang được tải...</p>
            </div>
        </div>

        <!-- Heatmap component below the tabs -->
        <ShiftHeatmap :shift-revenue="shiftRevenue" />
    </div>
</template>
