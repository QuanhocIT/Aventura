<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { 
    ShoppingCart, 
    DollarSign, 
    CheckCircle, 
    XCircle, 
    Search, 
    TrendingUp, 
    Percent, 
    BarChart2, 
    Utensils, 
    Calendar,
    Eye,
    Clock,
    CreditCard,
    User,
    Phone,
    MapPin,
    ArrowUpRight
} from 'lucide-vue-next';
import { ref, watch, computed } from 'vue';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { PageHeader, FilterBar, StatusBadge, Pagination } from '@/components/super-admin';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

interface OrderItem {
    id: number;
    product_name: string;
    quantity: number;
    price: string;
    total: string;
}

interface OrderItemData {
    id: number;
    order_number: string;
    restaurant: string;
    restaurant_code: string;
    status: string;
    payment_status: string;
    total_amount: string;
    total_raw: number;
    channel: string;
    note: string | null;
    created_at: string;
    customer_name: string;
    customer_phone: string;
    table_name: string;
    items: OrderItem[];
}

interface RevenueTrendPoint {
    date: string;
    revenue: number;
}

interface Stats {
    total_today: number;
    revenue_today: number;
    completed_today: number;
    cancelled_today: number;
    pos_today: number;
    qr_today: number;
    online_today: number;
    delivery_today: number;
    revenue_trend: RevenueTrendPoint[];
}

const props = defineProps<{
    orders: {
        data: OrderItemData[];
        links: any[];
        total: number;
        last_page: number;
        current_page: number;
    };
    stats: Stats;
    restaurants: Array<{ id: number; name: string; code: string }>;
    filters: { restaurant_id?: string; status?: string; payment_status?: string; search?: string; date_from?: string; date_to?: string };
}>();

const restaurantId = ref(props.filters.restaurant_id ?? '');
const status = ref(props.filters.status ?? '');
const paymentStatus = ref(props.filters.payment_status ?? '');
const search = ref(props.filters.search ?? '');
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');

let timer: ReturnType<typeof setTimeout>;
watch(search, () => { clearTimeout(timer); timer = setTimeout(applyFilter, 400); });

function applyFilter() {
    router.get('/super-admin/orders', {
        restaurant_id: restaurantId.value || undefined,
        status: status.value || undefined,
        payment_status: paymentStatus.value || undefined,
        search: search.value || undefined,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
    }, { preserveState: true, replace: true });
}

function hasActiveFilter() {
    return !!(restaurantId.value || status.value || paymentStatus.value || search.value || dateFrom.value || dateTo.value);
}

function resetFilters() {
    restaurantId.value = '';
    status.value = '';
    paymentStatus.value = '';
    search.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    applyFilter();
}

function formatVND(val: number) {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(val);
}

// Compute Completion Rate & AOV
const completionRate = computed(() => {
    const total = props.stats.total_today;
    if (total === 0) return 0;
    return Math.round((props.stats.completed_today / total) * 100);
});

const averageOrderValue = computed(() => {
    const completed = props.stats.completed_today;
    if (completed === 0) return 0;
    return Math.round(props.stats.revenue_today / completed);
});

const statusLabel: Record<string, string> = {
    pending: 'Chờ xử lý', confirmed: 'Đã xác nhận', preparing: 'Đang chuẩn bị',
    ready: 'Sẵn sàng', completed: 'Hoàn thành', cancelled: 'Đã hủy',
};

const channelLabel: Record<string, string> = {
    pos: 'POS', qr: 'QR Order', online: 'Online', delivery: 'Giao hàng',
};

// Order Details Modal State
const selectedOrder = ref<OrderItemData | null>(null);

const openOrderDetails = (order: OrderItemData) => {
    selectedOrder.value = order;
};

const closeOrderDetails = () => {
    selectedOrder.value = null;
};

// SVG Donut slices for channels breakdown
const channelSlices = computed(() => {
    const total = props.stats.pos_today + props.stats.qr_today + props.stats.online_today + props.stats.delivery_today;
    if (total === 0) return [];
    
    const posPct = (props.stats.pos_today / total) * 100;
    const qrPct = (props.stats.qr_today / total) * 100;
    const onlinePct = (props.stats.online_today / total) * 100;
    const deliveryPct = (props.stats.delivery_today / total) * 100;

    let currentOffset = 0;
    const slices = [];

    if (posPct > 0) {
        slices.push({
            percentage: posPct,
            dashArray: `${posPct} ${100 - posPct}`,
            dashOffset: 100 - currentOffset,
            color: 'stroke-blue-500',
            label: 'POS',
            value: props.stats.pos_today
        });
        currentOffset += posPct;
    }

    if (qrPct > 0) {
        slices.push({
            percentage: qrPct,
            dashArray: `${qrPct} ${100 - qrPct}`,
            dashOffset: 100 - currentOffset,
            color: 'stroke-purple-500',
            label: 'QR Order',
            value: props.stats.qr_today
        });
        currentOffset += qrPct;
    }

    if (onlinePct > 0) {
        slices.push({
            percentage: onlinePct,
            dashArray: `${onlinePct} ${100 - onlinePct}`,
            dashOffset: 100 - currentOffset,
            color: 'stroke-emerald-500',
            label: 'Online',
            value: props.stats.online_today
        });
        currentOffset += onlinePct;
    }

    if (deliveryPct > 0) {
        slices.push({
            percentage: deliveryPct,
            dashArray: `${deliveryPct} ${100 - deliveryPct}`,
            dashOffset: 100 - currentOffset,
            color: 'stroke-orange-500',
            label: 'Giao hàng',
            value: props.stats.delivery_today
        });
    }

    return slices;
});

// SVG Area Chart points for 7-day revenue trend
const chartPoints = computed(() => {
    const trend = props.stats.revenue_trend;
    if (!trend || trend.length === 0) return '';
    const maxRev = Math.max(...trend.map(p => p.revenue), 100000);
    
    return trend.map((p, i) => {
        const x = i * (500 / (trend.length - 1 || 1));
        const y = 90 - (p.revenue / maxRev) * 75;
        return `${x},${y}`;
    }).join(' ');
});

const chartAreaPath = computed(() => {
    const points = chartPoints.value;
    if (!points) return '';
    const trend = props.stats.revenue_trend;
    const lastX = 500;
    return `M 0,100 L ${points} L ${lastX},100 Z`;
});
</script>

<template>
    <Head title="Đơn hàng toàn hệ thống" />

    <div class="flex flex-col gap-5 px-6 py-5">
        <!-- Header -->
        <PageHeader
            title="Đơn hàng toàn hệ thống"
            subtitle="Xem và theo dõi tất cả đơn hàng từ mọi nhà hàng trong hệ thống."
            :icon="ShoppingCart"
        />

        <!-- KPI Stats Cards (6 Columns Glassmorphism) -->
        <div class="grid gap-4 grid-cols-2 md:grid-cols-3 xl:grid-cols-6">
            <!-- Total orders today -->
            <Card class="bg-card/45 backdrop-blur-md border border-border/40 hover:shadow-md transition-all">
                <CardContent class="p-4 text-center space-y-1.5">
                    <p class="text-[10px] font-black text-sky-600 dark:text-sky-400 uppercase tracking-wider">Đơn hôm nay</p>
                    <div class="text-2xl font-black text-sky-600 dark:text-sky-400 font-mono">
                        {{ stats.total_today }}
                    </div>
                    <p class="text-[9px] text-muted-foreground">Phát sinh trong ngày</p>
                </CardContent>
            </Card>

            <!-- Revenue today -->
            <Card class="bg-card/45 backdrop-blur-md border border-border/40 hover:shadow-md transition-all">
                <CardContent class="p-4 text-center space-y-1.5">
                    <p class="text-[10px] font-black text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">Doanh thu hôm nay</p>
                    <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400 font-mono truncate px-1">
                        {{ formatVND(stats.revenue_today) }}
                    </div>
                    <p class="text-[9px] text-muted-foreground">Đơn đã hoàn thành</p>
                </CardContent>
            </Card>

            <!-- Completed today -->
            <Card class="bg-card/45 backdrop-blur-md border border-border/40 hover:shadow-md transition-all">
                <CardContent class="p-4 text-center space-y-1.5">
                    <p class="text-[10px] font-black text-violet-600 dark:text-violet-400 uppercase tracking-wider">Hoàn thành</p>
                    <div class="text-2xl font-black text-violet-600 dark:text-violet-400 font-mono flex items-center justify-center gap-1">
                        <CheckCircle class="size-5 text-violet-500" />
                        {{ stats.completed_today }}
                    </div>
                    <p class="text-[9px] text-muted-foreground">Đã giao hàng / Phục vụ</p>
                </CardContent>
            </Card>

            <!-- Cancelled today -->
            <Card class="bg-card/45 backdrop-blur-md border border-border/40 hover:shadow-md transition-all" :class="{ 'border-rose-500/20 bg-rose-500/[0.01]': stats.cancelled_today > 0 }">
                <CardContent class="p-4 text-center space-y-1.5">
                    <p class="text-[10px] font-black text-rose-600 dark:text-rose-400 uppercase tracking-wider">Đã hủy</p>
                    <div class="text-2xl font-black text-rose-600 dark:text-rose-400 font-mono flex items-center justify-center gap-1">
                        <XCircle class="size-5 text-rose-500" />
                        {{ stats.cancelled_today }}
                    </div>
                    <p class="text-[9px] text-muted-foreground">Đơn bị hủy trong ngày</p>
                </CardContent>
            </Card>

            <!-- Completion rate -->
            <Card class="bg-card/45 backdrop-blur-md border border-border/40 hover:shadow-md transition-all">
                <CardContent class="p-4 text-center space-y-1.5">
                    <p class="text-[10px] font-black text-amber-600 dark:text-amber-400 uppercase tracking-wider">Tỷ lệ hoàn thành</p>
                    <div class="text-2xl font-black text-amber-600 dark:text-amber-400 font-mono flex items-center justify-center gap-0.5">
                        <Percent class="size-4 text-amber-500" />
                        {{ completionRate }}%
                    </div>
                    <p class="text-[9px] text-muted-foreground">Hiệu suất vận hành</p>
                </CardContent>
            </Card>

            <!-- AOV today -->
            <Card class="bg-card/45 backdrop-blur-md border border-border/40 hover:shadow-md transition-all">
                <CardContent class="p-4 text-center space-y-1.5">
                    <p class="text-[10px] font-black text-indigo-600 dark:text-indigo-400 uppercase tracking-wider">Đơn trung bình (AOV)</p>
                    <div class="text-2xl font-black text-indigo-600 dark:text-indigo-400 font-mono truncate px-1">
                        {{ formatVND(averageOrderValue) }}
                    </div>
                    <p class="text-[9px] text-muted-foreground">Giá trị / đơn hoàn thành</p>
                </CardContent>
            </Card>
        </div>

        <!-- Charts Row (Revenue Trend + Channels Breakdown) -->
        <div class="grid gap-4 md:grid-cols-3">
            <!-- Revenue Trend 7 days (SVG Area Chart - Spans 2 Columns) -->
            <Card class="bg-card/50 backdrop-blur-md border border-border/40 shadow-xs md:col-span-2">
                <CardContent class="p-5 space-y-4">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider flex items-center gap-1">
                                <TrendingUp class="size-4 text-emerald-500 animate-pulse" />
                                Xu hướng Doanh thu 7 ngày gần đây
                            </h3>
                            <p class="text-[11px] text-muted-foreground">Biểu đồ tổng doanh thu các đơn hàng hoàn thành</p>
                        </div>
                        <div class="text-xs font-black text-slate-700 dark:text-slate-200 font-mono">
                            Max: {{ formatVND(Math.max(...stats.revenue_trend.map(p => p.revenue), 0)) }}
                        </div>
                    </div>

                    <!-- SVG Chart -->
                    <div class="relative w-full h-32 pt-2">
                        <svg viewBox="0 0 500 100" class="size-full overflow-visible">
                            <!-- Gradients definitions -->
                            <defs>
                                <linearGradient id="areaGrad" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="rgba(16, 185, 129, 0.25)" />
                                    <stop offset="100%" stop-color="rgba(16, 185, 129, 0.0)" />
                                </linearGradient>
                            </defs>
                            
                            <!-- Grid lines -->
                            <line x1="0" y1="20" x2="500" y2="20" stroke="rgba(226, 232, 240, 0.15)" stroke-width="0.5" />
                            <line x1="0" y1="50" x2="500" y2="50" stroke="rgba(226, 232, 240, 0.15)" stroke-width="0.5" />
                            <line x1="0" y1="80" x2="500" y2="80" stroke="rgba(226, 232, 240, 0.15)" stroke-width="0.5" />

                            <!-- Path area fill -->
                            <path :d="chartAreaPath" fill="url(#areaGrad)"></path>

                            <!-- Polyline border -->
                            <polyline :points="chartPoints" fill="none" stroke="#10b981" stroke-width="2" class="transition-all duration-500"></polyline>

                            <!-- Nodes & Tooltips -->
                            <circle 
                                v-for="(p, i) in stats.revenue_trend" 
                                :key="i"
                                :cx="i * (500 / (stats.revenue_trend.length - 1 || 1))" 
                                :cy="90 - (p.revenue / Math.max(...stats.revenue_trend.map(pt => pt.revenue), 1)) * 75" 
                                r="3.5" 
                                fill="#ffffff" 
                                stroke="#10b981" 
                                stroke-width="2"
                                class="hover:r-5 cursor-pointer transition-all"
                            >
                                <title>{{ p.date }}: {{ formatVND(p.revenue) }}</title>
                            </circle>
                        </svg>
                    </div>
                    <!-- X Axis Labels -->
                    <div class="flex justify-between text-[10px] font-bold text-slate-400 dark:text-slate-500 px-1 font-mono">
                        <span v-for="p in stats.revenue_trend" :key="p.date">{{ p.date }}</span>
                    </div>
                </CardContent>
            </Card>

            <!-- Channels Breakdown (SVG Donut Chart - Spans 1 Column) -->
            <Card class="bg-card/50 backdrop-blur-md border border-border/40 shadow-xs">
                <CardContent class="p-5 flex flex-col justify-between h-full min-h-[200px]">
                    <div class="space-y-1">
                        <h3 class="text-xs font-bold text-slate-700 dark:text-slate-200 uppercase tracking-wider flex items-center gap-1">
                            <BarChart2 class="size-4 text-primary" />
                            Cơ cấu Kênh đặt hàng
                        </h3>
                        <p class="text-[11px] text-muted-foreground">Tỷ trọng các kênh đơn phát sinh hôm nay</p>
                    </div>

                    <div class="flex items-center justify-between gap-4 pt-2">
                        <div class="space-y-1.5 text-xs font-semibold shrink-0">
                            <div v-for="slice in channelSlices" :key="slice.label" class="flex items-center gap-1.5">
                                <span class="size-2 rounded-full" :class="slice.color.replace('stroke-', 'bg-')"></span>
                                <span class="text-slate-500">{{ slice.label }}:</span>
                                <span class="font-mono text-slate-800 dark:text-slate-200 font-bold">{{ slice.value }}</span>
                            </div>
                        </div>

                        <!-- Donut SVG widget -->
                        <div class="relative size-24 shrink-0 flex items-center justify-center">
                            <svg viewBox="0 0 42 42" class="size-full transform -rotate-90">
                                <circle cx="21" cy="21" r="15.915" fill="transparent" stroke="rgba(226, 232, 240, 0.4)" stroke-width="4"></circle>
                                <circle 
                                    v-for="(slice, index) in channelSlices" 
                                    :key="index"
                                    cx="21" 
                                    cy="21" 
                                    r="15.915" 
                                    fill="transparent" 
                                    :class="slice.color" 
                                    stroke-width="4" 
                                    :stroke-dasharray="slice.dashArray" 
                                    :stroke-dashoffset="slice.dashOffset"
                                    class="transition-all duration-700"
                                ></circle>
                            </svg>
                            <div class="absolute text-center flex flex-col justify-center items-center">
                                <span class="text-base font-black text-slate-800 dark:text-slate-100 font-mono">
                                    {{ stats.total_today }}
                                </span>
                                <span class="text-[7px] uppercase font-bold text-slate-400">Đơn</span>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Filters Block -->
        <FilterBar>
            <div class="relative min-w-48 flex-1">
                <Search class="absolute left-3 top-2.5 size-4 text-muted-foreground" />
                <Input v-model="search" placeholder="Tìm mã đơn..." class="pl-9" />
            </div>
            <Select v-model="restaurantId" @update:model-value="applyFilter">
                <SelectTrigger class="w-[200px]">
                    <SelectValue placeholder="Tất cả nhà hàng" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="">Tất cả nhà hàng</SelectItem>
                    <SelectItem v-for="r in restaurants" :key="r.id" :value="String(r.id)">{{ r.name }}</SelectItem>
                </SelectContent>
            </Select>
            <Select v-model="status" @update:model-value="applyFilter">
                <SelectTrigger class="w-[160px]">
                    <SelectValue placeholder="Trạng thái" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="">Tất cả trạng thái</SelectItem>
                    <SelectItem value="pending">Chờ xử lý</SelectItem>
                    <SelectItem value="confirmed">Đã xác nhận</SelectItem>
                    <SelectItem value="preparing">Đang chuẩn bị</SelectItem>
                    <SelectItem value="completed">Hoàn thành</SelectItem>
                    <SelectItem value="cancelled">Đã hủy</SelectItem>
                </SelectContent>
            </Select>
            <Select v-model="paymentStatus" @update:model-value="applyFilter">
                <SelectTrigger class="w-[150px]">
                    <SelectValue placeholder="Thanh toán" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="">Tất cả thanh toán</SelectItem>
                    <SelectItem value="paid">Đã thanh toán</SelectItem>
                    <SelectItem value="unpaid">Chưa thanh toán</SelectItem>
                </SelectContent>
            </Select>
            <Input v-model="dateFrom" type="date" class="w-[150px]" @change="applyFilter" />
            <Input v-model="dateTo" type="date" class="w-[150px]" @change="applyFilter" />
            <template v-if="hasActiveFilter()" #actions>
                <Button variant="ghost" size="sm" @click="resetFilters" class="text-xs text-muted-foreground cursor-pointer">Xoá lọc</Button>
            </template>
        </FilterBar>

        <!-- Orders Table -->
        <Card class="overflow-hidden border border-border/60 shadow-xs">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-muted/40 border-b border-border/60 text-[10px] uppercase tracking-wider text-muted-foreground font-black">
                            <th class="p-4 w-[160px]">Mã đơn</th>
                            <th class="p-4">Nhà hàng</th>
                            <th class="p-4 w-[120px] text-center">Kênh</th>
                            <th class="p-4 w-[130px] text-center">Trạng thái</th>
                            <th class="p-4 w-[130px] text-center">Thanh toán</th>
                            <th class="p-4 w-[140px] text-right">Tổng tiền</th>
                            <th class="p-4 w-[160px] text-center">Thời gian</th>
                            <th class="p-4 w-[120px] text-right pr-6">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/40 text-xs">
                        <tr 
                            v-for="order in orders.data" 
                            :key="order.id" 
                            class="hover:bg-muted/20 transition-all duration-150 cursor-pointer"
                            @click="openOrderDetails(order)"
                        >
                            <!-- Order Number -->
                            <td class="p-4 font-mono font-bold text-slate-800 dark:text-slate-200">
                                #{{ order.order_number }}
                            </td>

                            <!-- Restaurant details -->
                            <td class="p-4">
                                <div class="font-bold text-slate-800 dark:text-slate-200">{{ order.restaurant }}</div>
                                <div class="text-[10px] text-muted-foreground font-mono mt-0.5">Code: {{ order.restaurant_code }}</div>
                            </td>

                            <!-- Channel -->
                            <td class="p-4 text-center">
                                <span 
                                    class="inline-block rounded-lg px-2 py-0.5 text-[10px] font-bold border uppercase"
                                    :class="[
                                        order.channel === 'pos' ? 'bg-blue-500/10 text-blue-600 border-blue-500/20' :
                                        order.channel === 'qr' ? 'bg-purple-500/10 text-purple-600 border-purple-500/20' :
                                        order.channel === 'online' ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20' :
                                        'bg-orange-500/10 text-orange-600 border-orange-500/20'
                                    ]"
                                >
                                    {{ channelLabel[order.channel] ?? order.channel }}
                                </span>
                            </td>

                            <!-- Order Status -->
                            <td class="p-4 text-center">
                                <span 
                                    class="inline-block rounded-lg px-2 py-0.5 text-[10px] font-bold border"
                                    :class="[
                                        order.status === 'completed' ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20' :
                                        order.status === 'cancelled' ? 'bg-rose-500/10 text-rose-600 border-rose-500/20 animate-pulse' :
                                        order.status === 'pending' ? 'bg-amber-500/10 text-amber-600 border-amber-500/20' :
                                        'bg-blue-500/10 text-blue-600 border-blue-500/20'
                                    ]"
                                >
                                    {{ statusLabel[order.status] ?? order.status }}
                                </span>
                            </td>

                            <!-- Payment status -->
                            <td class="p-4 text-center">
                                <span 
                                    class="inline-block rounded-lg px-2 py-0.5 text-[10px] font-bold border"
                                    :class="[
                                        order.payment_status === 'paid' ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20' : 'bg-rose-500/10 text-rose-600 border-rose-500/20 animate-pulse'
                                    ]"
                                >
                                    {{ order.payment_status === 'paid' ? 'Đã TT' : 'Chưa TT' }}
                                </span>
                            </td>

                            <!-- Total amount -->
                            <td class="p-4 text-right font-mono font-bold tabular-nums text-slate-800 dark:text-slate-100">
                                {{ order.total_amount }}đ
                            </td>

                            <!-- Order time -->
                            <td class="p-4 text-center text-muted-foreground font-mono text-[11px]">
                                {{ order.created_at }}
                            </td>

                            <!-- Actions -->
                            <td class="p-4 text-right pr-6" @click.stop>
                                <button 
                                    type="button" 
                                    @click="openOrderDetails(order)"
                                    class="inline-flex items-center justify-center rounded-lg border border-border/80 px-2.5 py-1 text-[11px] font-bold hover:bg-muted/70 cursor-pointer transition-all"
                                >
                                    <Eye class="size-3 mr-1" /> Chi tiết
                                </button>
                            </td>
                        </tr>
                        <tr v-if="orders.data.length === 0">
                            <td colspan="8" class="p-8 text-center text-muted-foreground italic font-medium">
                                Không tìm thấy đơn hàng nào phù hợp với bộ lọc.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination link slots -->
            <div v-if="orders.last_page > 1" class="bg-muted/20 border-t border-border/60 p-4 flex items-center justify-between">
                <p class="text-xs text-muted-foreground">
                    Đang hiển thị trang <strong>{{ orders.current_page }}</strong> trên tổng số <strong>{{ orders.last_page }}</strong> trang (Tổng số {{ orders.total }} đơn hàng)
                </p>
                <div class="flex gap-1">
                    <Link
                        v-for="(link, idx) in orders.links"
                        :key="idx"
                        :href="link.url ?? '#'"
                        v-html="link.label"
                        :class="[
                            'px-3 py-1 text-[11px] font-bold border rounded-lg transition-all',
                            link.active ? 'bg-primary border-primary text-primary-foreground' : 'bg-card border-border hover:bg-muted/50',
                            !link.url ? 'opacity-50 cursor-not-allowed pointer-events-none' : ''
                        ]"
                    />
                </div>
            </div>
        </Card>

        <!-- Order Detail Dialog -->
        <Dialog :open="selectedOrder !== null" @update:open="closeOrderDetails">
            <DialogContent class="sm:max-w-[500px] max-h-[90vh] flex flex-col p-6 overflow-hidden">
                <DialogHeader class="shrink-0">
                    <DialogTitle class="flex items-center gap-2 text-slate-800 dark:text-slate-100 font-mono">
                        <Utensils class="size-5 text-emerald-500" />
                        Đơn hàng: #{{ selectedOrder?.order_number }}
                    </DialogTitle>
                    <DialogDescription class="text-xs">
                        Chi tiết các món ăn, thông tin nhà hàng và khách hàng của đơn.
                    </DialogDescription>
                </DialogHeader>

                <div v-if="selectedOrder" class="flex-1 overflow-y-auto space-y-4 pt-4 pr-1">
                    <!-- General details summary grid -->
                    <div class="grid grid-cols-2 gap-3.5 bg-muted/40 p-3.5 rounded-xl border border-border/40 text-xs">
                        <div>
                            <span class="text-slate-400 font-bold block">Nhà hàng</span>
                            <span class="font-bold text-slate-700 dark:text-slate-300">{{ selectedOrder.restaurant }}</span>
                            <span class="font-mono text-[10px] text-muted-foreground block">Code: {{ selectedOrder.restaurant_code }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 font-bold block">Thời gian tạo</span>
                            <span class="font-mono font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1 mt-0.5">
                                <Clock class="size-3.5 text-slate-400" /> {{ selectedOrder.created_at }}
                            </span>
                        </div>
                        <div>
                            <span class="text-slate-400 font-bold block">Kênh đặt đơn</span>
                            <span class="font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1 mt-0.5">
                                <ArrowUpRight class="size-3.5 text-slate-400" />
                                {{ channelLabel[selectedOrder.channel] ?? selectedOrder.channel }}
                            </span>
                        </div>
                        <div>
                            <span class="text-slate-400 font-bold block">Bàn phục vụ</span>
                            <span class="font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1 mt-0.5">
                                <MapPin class="size-3.5 text-slate-400" /> Bàn: {{ selectedOrder.table_name }}
                            </span>
                        </div>
                        <div>
                            <span class="text-slate-400 font-bold block">Trạng thái</span>
                            <span 
                                class="inline-block rounded-md mt-1 px-2 py-0.5 text-[10px] font-bold border"
                                :class="selectedOrder.status === 'completed' ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20' : 'bg-amber-500/10 text-amber-600 border-amber-500/20'"
                            >
                                {{ statusLabel[selectedOrder.status] ?? selectedOrder.status }}
                            </span>
                        </div>
                        <div>
                            <span class="text-slate-400 font-bold block">Thanh toán</span>
                            <span 
                                class="inline-block rounded-md mt-1 px-2 py-0.5 text-[10px] font-bold border"
                                :class="selectedOrder.payment_status === 'paid' ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20' : 'bg-rose-500/10 text-rose-600 border-rose-500/20'"
                            >
                                {{ selectedOrder.payment_status === 'paid' ? 'Đã thanh toán' : 'Chưa thanh toán' }}
                            </span>
                        </div>
                    </div>

                    <!-- Customer Info Section -->
                    <div class="space-y-2 border-b border-border/40 pb-3">
                        <h4 class="font-bold text-xs uppercase text-slate-400 tracking-wider flex items-center gap-1.5">
                            <User class="size-3.5" /> Thông tin khách hàng
                        </h4>
                        <div class="flex items-center justify-between text-xs px-2">
                            <div class="flex items-center gap-1 font-semibold text-slate-700 dark:text-slate-300">
                                <span>{{ selectedOrder.customer_name }}</span>
                            </div>
                            <div class="flex items-center gap-0.5 text-slate-500 font-mono text-[11px]">
                                <Phone class="size-3" /> {{ selectedOrder.customer_phone }}
                            </div>
                        </div>
                    </div>

                    <!-- Items list -->
                    <div class="space-y-2.5">
                        <h4 class="font-bold text-xs uppercase text-slate-400 tracking-wider flex items-center gap-1.5">
                            <Utensils class="size-3.5" /> Danh sách món ăn đặt mua
                        </h4>

                        <div class="border border-border/40 rounded-xl overflow-hidden bg-slate-50/50 dark:bg-slate-900/30">
                            <div class="p-3 bg-muted/40 border-b border-border/40 grid grid-cols-12 text-[10px] font-black uppercase text-muted-foreground">
                                <div class="col-span-6">Sản phẩm</div>
                                <div class="col-span-2 text-center">SL</div>
                                <div class="col-span-2 text-right">Đơn giá</div>
                                <div class="col-span-2 text-right">Tổng</div>
                            </div>
                            <div class="divide-y divide-border/30 max-h-[160px] overflow-y-auto">
                                <div 
                                    v-for="item in selectedOrder.items" 
                                    :key="item.id"
                                    class="p-3 grid grid-cols-12 items-center text-xs text-slate-600 dark:text-slate-300"
                                >
                                    <div class="col-span-6 font-bold text-slate-700 dark:text-slate-200">
                                        {{ item.product_name }}
                                    </div>
                                    <div class="col-span-2 text-center font-mono font-bold">{{ item.quantity }}</div>
                                    <div class="col-span-2 text-right font-mono text-[11px]">{{ item.price }}đ</div>
                                    <div class="col-span-2 text-right font-mono font-bold text-slate-800 dark:text-slate-100">{{ item.total }}đ</div>
                                </div>
                                <div v-if="selectedOrder.items.length === 0" class="text-center py-4 text-xs italic text-muted-foreground">
                                    Không có thông tin món ăn chi tiết.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Order Total Calculation Summary -->
                    <div class="flex justify-between items-center p-3 rounded-xl bg-slate-950 text-white font-mono shrink-0">
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1">
                            <CreditCard class="size-4 text-emerald-400" /> Tổng tiền thanh toán
                        </span>
                        <span class="text-base font-black text-emerald-400">
                            {{ selectedOrder.total_amount }}đ
                        </span>
                    </div>

                    <!-- Customer Notes -->
                    <div v-if="selectedOrder.note" class="p-3 bg-amber-500/[0.04] border border-amber-500/20 rounded-xl space-y-1">
                        <span class="text-[10px] font-bold text-amber-500 uppercase tracking-widest block">Ghi chú đơn hàng</span>
                        <p class="text-xs text-amber-700 dark:text-amber-300 font-medium leading-relaxed italic">
                            "{{ selectedOrder.note }}"
                        </p>
                    </div>
                </div>

                <div class="flex justify-end pt-4 border-t border-border/50 shrink-0 mt-4">
                    <button
                        type="button"
                        @click="closeOrderDetails"
                        class="inline-flex h-9 items-center justify-center rounded-xl border border-border px-4 text-xs font-bold hover:bg-muted/50 cursor-pointer transition-all"
                    >
                        Đóng cửa sổ
                    </button>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>

<style scoped>
.transition-all {
    transition-property: all;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
}
</style>
