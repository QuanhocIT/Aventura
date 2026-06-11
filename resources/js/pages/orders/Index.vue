<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ShoppingCart,
    CheckCircle2,
    Clock,
    XCircle,
    ChefHat,
    Banknote,
    Filter,
    CalendarDays,
    RefreshCw,
    AlertCircle,
    Sparkles,
    Search,
    Layers,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import axios from 'axios';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type Order = {
    id: number;
    order_number: string;
    status: string;
    payment_status: string;
    channel: string;
    third_party_source: string | null;
    table_name: string | null;
    area_name: string | null;
    total_amount: number;
    items_count: number;
    created_at: string;
    completed_at: string | null;
    items?: { id: number; product_name: string; quantity: number; status: string; notes: string | null }[];
};

type Summary = {
    total: number;
    pending: number;
    preparing: number;
    completed: number;
    cancelled: number;
    revenue: number;
};

const props = defineProps<{
    orders: Order[];
    summary: Summary;
    filters: { status: string; date: string; search?: string; history?: boolean };
    activeShiftStats?: {
        shift_name: string;
        check_in_at: string;
        total_orders: number;
        total_revenue: number;
        cash_revenue: number;
        transfer_revenue: number;
    } | null;
}>();

const dateInput = ref(props.filters.date);
const activeStatus = ref(props.filters.status);
const searchQuery = ref(props.filters.search || '');
const viewMode = ref(props.filters.history ? 'history' : 'today');
const expandedOrderId = ref<number | null>(null);
const isSimulating = ref(false);
const updatingItemStatus = ref<number | null>(null);

const applyFilters = () => {
    router.get(
        '/orders',
        {
            status: activeStatus.value,
            date: dateInput.value,
            search: searchQuery.value,
            history: viewMode.value === 'history' ? 'true' : 'false',
        },
        { preserveScroll: true },
    );
};

const setStatus = (s: string) => {
    activeStatus.value = s;
    applyFilters();
};

const toggleViewMode = (mode: 'today' | 'history') => {
    viewMode.value = mode;
    applyFilters();
};

const clearSearch = () => {
    searchQuery.value = '';
    applyFilters();
};

const simulateThirdParty = async (source: 'GrabFood' | 'ShopeeFood') => {
    isSimulating.value = true;
    try {
        const response = await axios.post('/api/orders/third-party/simulate', { source });
        if (response.data.success) {
            toast.success(response.data.message);
            router.reload({ only: ['orders', 'summary'] });
        }
    } catch (e: any) {
        console.error(e);
        toast.error('Không thể mô phỏng đơn hàng.');
    } finally {
        isSimulating.value = false;
    }
};

const updateOrderItemStatus = async (item: any, newStatus: string) => {
    updatingItemStatus.value = item.id;
    try {
        const response = await axios.patch(`/orders/items/${item.id}/status`, { status: newStatus });
        if (response.data.success) {
            toast.success(response.data.message);
            router.reload({ only: ['orders'] });
        }
    } catch (e: any) {
        console.error(e);
        toast.error('Có lỗi xảy ra khi cập nhật trạng thái món ăn.');
    } finally {
        updatingItemStatus.value = null;
    }
};

const updateOrderStatus = (order: Order, newStatus: string) => {
    router.patch(
        `/orders/${order.id}/status`,
        { status: newStatus },
        { preserveScroll: true },
    );
};

const statusConfig: Record<
    string,
    { label: string; color: string; bg: string; icon: any }
> = {
    pending: {
        label: 'Chờ xác nhận',
        color: 'text-amber-700',
        bg: 'bg-amber-100 dark:bg-amber-950/40 dark:text-amber-400',
        icon: Clock,
    },
    confirmed: {
        label: 'Đã xác nhận',
        color: 'text-sky-700',
        bg: 'bg-sky-100 dark:bg-sky-950/40 dark:text-sky-400',
        icon: CheckCircle2,
    },
    preparing: {
        label: 'Đang chế biến',
        color: 'text-violet-700',
        bg: 'bg-violet-100 dark:bg-violet-950/40 dark:text-violet-400',
        icon: ChefHat,
    },
    completed: {
        label: 'Hoàn thành',
        color: 'text-emerald-700',
        bg: 'bg-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-400',
        icon: CheckCircle2,
    },
    cancelled: {
        label: 'Đã hủy',
        color: 'text-rose-700',
        bg: 'bg-rose-100 dark:bg-rose-950/40 dark:text-rose-400',
        icon: XCircle,
    },
};

const paymentConfig: Record<string, { label: string; color: string }> = {
    unpaid: { label: 'Chưa TT', color: 'text-rose-600' },
    partial: { label: 'TT 1 phần', color: 'text-amber-600' },
    paid: { label: 'Đã TT', color: 'text-emerald-600' },
    refunded: { label: 'Hoàn tiền', color: 'text-slate-500' },
};

const channelLabel: Record<string, string> = {
    dine_in: 'Tại bàn',
    takeaway: 'Mang về',
    delivery: 'Giao hàng',
    qr: 'QR Scan',
};

const itemStatusConfig: Record<string, { label: string; class: string }> = {
    pending: { label: 'Chờ chế biến', class: 'bg-amber-50 text-amber-700 border-amber-200/50 dark:bg-amber-950/20 dark:text-amber-400' },
    sent: { label: 'Đã gửi bếp', class: 'bg-sky-50 text-sky-700 border-sky-200/50 dark:bg-sky-950/20 dark:text-sky-400' },
    preparing: { label: 'Đang nấu', class: 'bg-violet-50 text-violet-750 border-violet-200/50 dark:bg-violet-950/20 dark:text-violet-400' },
    served: { label: 'Đã phục vụ', class: 'bg-emerald-50 text-emerald-700 border-emerald-200/50 dark:bg-emerald-950/20 dark:text-emerald-400' },
    cancelled: { label: 'Đã hủy', class: 'bg-rose-50 text-rose-700 border-rose-200/50 dark:bg-rose-950/20 dark:text-rose-400' },
};

const formatCurrency = (v: number) =>
    new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(v);

const isAiModalOpen = ref(false);
const aiSuggestionText = ref('');
const aiRecommendedItem = ref('');
const aiSource = ref('');
const isFetchingAi = ref(false);
const currentVerifyingOrder = ref<Order | null>(null);

const confirmQrOrder = async (order: Order) => {
    currentVerifyingOrder.value = order;
    isFetchingAi.value = true;
    
    try {
        await axios.patch(`/orders/${order.id}/status`, { status: 'confirmed' });
        
        const itemNames = order.items ? order.items.map(i => i.product_name).filter(Boolean) : [];
        const response = await axios.post('/api/promotions/upsell-suggestion', {
            items: itemNames
        });
        
        aiSuggestionText.value = response.data.suggestion || 'Hãy chọn thêm các món ăn đặc sắc từ thực đơn.';
        aiRecommendedItem.value = response.data.recommended_item || '';
        aiSource.value = response.data.source || 'Hệ thống';
        
        isAiModalOpen.value = true;
    } catch (e: any) {
        console.error(e);
        toast.error('Có lỗi xảy ra khi xác nhận đơn hàng đệm.');
    } finally {
        isFetchingAi.value = false;
        router.reload({ only: ['orders', 'summary'] });
    }
};

const handleStatusUpdate = (order: Order, newStatus: string) => {
    if (order.channel === 'qr' && newStatus === 'confirmed') {
        confirmQrOrder(order);
    } else {
        updateOrderStatus(order, newStatus);
    }
};

const nextStatus: Record<string, string | null> = {
    pending: 'confirmed',
    confirmed: 'preparing',
    preparing: 'completed',
    completed: null,
    cancelled: null,
};
</script>

<template>
    <Head title="Quản lý đơn hàng" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-6">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-2xl bg-violet-50 text-violet-600 dark:bg-violet-950/60 dark:text-violet-400"
                >
                    <ShoppingCart class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">
                        Quản Lý Đơn Hàng
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Theo dõi, xác nhận và cập nhật trạng thái đơn hàng theo
                        thời gian thực.
                    </p>
                </div>
            </div>
            <!-- Date picker -->
            <div class="flex items-center gap-2">
                <div class="relative">
                    <CalendarDays
                        class="pointer-events-none absolute top-2.5 left-3 size-4 text-muted-foreground"
                    />
                    <input
                        type="date"
                        v-model="dateInput"
                        @change="applyFilters"
                        class="h-10 rounded-md border border-input bg-background px-3 py-2 pl-9 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    />
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    @click="applyFilters"
                    class="h-10"
                >
                    <RefreshCw class="size-4" />
                </Button>
            </div>
        </div>

        <!-- Developer Sandbox / Simulator & Shift Performance Widget -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            
            <!-- Shift Performance Widget -->
            <Card class="md:col-span-2 rounded-2xl border border-violet-100/50 bg-white/60 dark:border-slate-800 dark:bg-slate-900/40 shadow-xs relative overflow-hidden backdrop-blur-md">
                <CardHeader class="pb-3 flex flex-row items-center justify-between">
                    <div>
                        <CardTitle class="text-sm font-bold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                            <Sparkles class="size-4 text-violet-600 animate-pulse" />
                            Hiệu Suất Ca Làm Việc
                        </CardTitle>
                        <CardDescription class="text-[10px] text-slate-400">
                            Số liệu doanh thu và năng suất làm việc của bạn trong ca trực hiện tại.
                        </CardDescription>
                    </div>
                    <span 
                        v-if="activeShiftStats"
                        class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2 py-0.5 text-[9px] font-bold text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-400"
                    >
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-ping"></span>
                        Ca: {{ activeShiftStats.shift_name }}
                    </span>
                </CardHeader>
                <CardContent class="pb-4">
                    <div v-if="activeShiftStats" class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div class="rounded-xl bg-slate-50/50 p-2.5 border border-slate-100 dark:bg-slate-950/20 dark:border-slate-850">
                            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Đã Check-in</p>
                            <p class="text-base font-black text-slate-800 dark:text-slate-200 mt-1 font-mono">
                                {{ activeShiftStats.check_in_at }}
                            </p>
                        </div>
                        <div class="rounded-xl bg-slate-50/50 p-2.5 border border-slate-100 dark:bg-slate-950/20 dark:border-slate-850">
                            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Đơn Hoàn Thành</p>
                            <p class="text-base font-black text-slate-800 dark:text-slate-200 mt-1 font-mono">
                                {{ activeShiftStats.total_orders }}
                            </p>
                        </div>
                        <div class="rounded-xl bg-slate-50/50 p-2.5 border border-slate-100 dark:bg-slate-950/20 dark:border-slate-850">
                            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Tiền Mặt</p>
                            <p class="text-xs font-bold text-emerald-600 dark:text-emerald-400 mt-1.5 font-mono">
                                {{ formatCurrency(activeShiftStats.cash_revenue) }}
                            </p>
                        </div>
                        <div class="rounded-xl bg-slate-50/50 p-2.5 border border-slate-100 dark:bg-slate-950/20 dark:border-slate-850">
                            <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">Chuyển Khoản</p>
                            <p class="text-xs font-bold text-violet-600 dark:text-violet-400 mt-1.5 font-mono">
                                {{ formatCurrency(activeShiftStats.transfer_revenue) }}
                            </p>
                        </div>
                    </div>
                    <div v-else class="flex flex-col items-center justify-center py-4 text-center">
                        <AlertCircle class="size-6 text-slate-300 dark:text-slate-700" />
                        <p class="text-xs font-semibold text-slate-500 mt-1 dark:text-slate-400">Bạn chưa check-in ca trực hôm nay.</p>
                        <p class="text-[10px] text-slate-400">Chuyển sang tab Lịch biểu/Chấm công để check-in và bắt đầu theo dõi doanh thu.</p>
                    </div>
                </CardContent>
            </Card>

            <!-- Developer Sandbox / Simulator -->
            <Card class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/20 p-4 dark:border-slate-800 dark:bg-slate-950/5 flex flex-col justify-between shadow-xs">
                <div>
                    <h3 class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                        <Layers class="size-3.5 text-violet-500" />
                        Simulator Sandbox
                    </h3>
                    <p class="text-[9px] text-slate-400 mt-1 leading-relaxed">
                        Mô phỏng đơn hàng từ ứng dụng bên thứ ba (GrabFood, ShopeeFood) được gửi đến hệ thống để nhân viên tiếp nhận.
                    </p>
                </div>
                <div class="grid grid-cols-2 gap-2 mt-4">
                    <Button 
                        size="sm" 
                        variant="outline" 
                        :disabled="isSimulating"
                        @click="simulateThirdParty('GrabFood')"
                        class="rounded-xl text-[10px] py-1.5 font-bold border-emerald-200 text-emerald-700 bg-emerald-50/20 hover:bg-emerald-50 hover:text-emerald-800 dark:border-emerald-900/40 dark:text-emerald-400"
                    >
                        + GrabFood
                    </Button>
                    <Button 
                        size="sm" 
                        variant="outline" 
                        :disabled="isSimulating"
                        @click="simulateThirdParty('ShopeeFood')"
                        class="rounded-xl text-[10px] py-1.5 font-bold border-orange-200 text-orange-700 bg-orange-50/20 hover:bg-orange-50 hover:text-orange-800 dark:border-orange-900/40 dark:text-orange-400"
                    >
                        + ShopeeFood
                    </Button>
                </div>
            </Card>

        </div>

        <!-- Summary KPIs -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            <div
                class="rounded-xl border border-border bg-card p-3 text-center"
            >
                <p class="text-2xl font-bold">{{ summary.total }}</p>
                <p class="mt-0.5 text-xs text-muted-foreground">Tổng đơn</p>
            </div>
            <div
                class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-center dark:border-amber-900/40 dark:bg-amber-950/20"
            >
                <p
                    class="text-2xl font-bold text-amber-700 dark:text-amber-400"
                >
                    {{ summary.pending }}
                </p>
                <p class="mt-0.5 text-xs text-amber-600/70">Chờ xác nhận</p>
            </div>
            <div
                class="rounded-xl border border-violet-200 bg-violet-50 p-3 text-center dark:border-violet-900/40 dark:bg-violet-950/20"
            >
                <p
                    class="text-2xl font-bold text-violet-700 dark:text-violet-400"
                >
                    {{ summary.preparing }}
                </p>
                <p class="mt-0.5 text-xs text-violet-600/70">Đang chế biến</p>
            </div>
            <div
                class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-center dark:border-emerald-900/40 dark:bg-emerald-950/20"
            >
                <p
                    class="text-2xl font-bold text-emerald-700 dark:text-emerald-400"
                >
                    {{ summary.completed }}
                </p>
                <p class="mt-0.5 text-xs text-emerald-600/70">Hoàn thành</p>
            </div>
            <div
                class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-center dark:border-rose-900/40 dark:bg-rose-950/20"
            >
                <p class="text-2xl font-bold text-rose-700 dark:text-rose-400">
                    {{ summary.cancelled }}
                </p>
                <p class="mt-0.5 text-xs text-rose-600/70">Đã hủy</p>
            </div>
            <div
                class="rounded-xl border border-emerald-300 bg-emerald-100 p-3 text-center dark:border-emerald-800 dark:bg-emerald-950/30"
            >
                <p
                    class="text-lg leading-tight font-bold text-emerald-700 dark:text-emerald-400"
                >
                    {{
                        new Intl.NumberFormat('vi-VN', {
                            notation: 'compact',
                            maximumFractionDigits: 1,
                        }).format(summary.revenue)
                    }}đ
                </p>
                <p class="mt-0.5 text-xs text-emerald-600/70">Doanh thu</p>
            </div>
        </div>

        <!-- Filter bar for Search and History -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between bg-card border border-border p-4 rounded-xl shadow-sm">
            <div class="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :class="viewMode === 'today' ? 'bg-violet-50 text-violet-750 border-violet-200/50 hover:bg-violet-100 hover:text-violet-850 dark:bg-violet-950/20 dark:text-violet-400' : ''"
                    @click="toggleViewMode('today')"
                >
                    Đơn Hôm Nay
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    :class="viewMode === 'history' ? 'bg-violet-50 text-violet-750 border-violet-200/50 hover:bg-violet-100 hover:text-violet-850 dark:bg-violet-950/20 dark:text-violet-400' : ''"
                    @click="toggleViewMode('history')"
                >
                    Lịch Sử Đơn Hàng
                </Button>
            </div>
            
            <div class="relative flex-1 max-w-sm">
                <Search class="absolute left-3 top-3 size-4 text-muted-foreground" />
                <input
                    type="text"
                    v-model="searchQuery"
                    placeholder="Tìm theo mã đơn, bàn, đối tác..."
                    @keyup.enter="applyFilters"
                    class="h-10 w-full rounded-md border border-input bg-background pl-9 pr-8 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                />
                <button
                    v-if="searchQuery"
                    @click="clearSearch"
                    class="absolute right-3 top-3 text-muted-foreground hover:text-foreground text-xs font-semibold"
                >
                    Xóa
                </button>
            </div>
        </div>

        <!-- Status filter chips -->
        <div class="flex flex-wrap gap-2">
            <button
                v-for="(label, key) in {
                    all: 'Tất cả',
                    pending: 'Chờ xác nhận',
                    confirmed: 'Đã xác nhận',
                    preparing: 'Đang chế biến',
                    completed: 'Hoàn thành',
                    cancelled: 'Đã hủy',
                } as Record<string, string>"
                :key="key"
                @click="setStatus(key)"
                class="rounded-full border px-3 py-1.5 text-xs font-semibold transition-all"
                :class="
                    activeStatus === key
                        ? 'border-violet-500 bg-violet-50 text-violet-700 dark:border-violet-700 dark:bg-violet-950/40 dark:text-violet-400'
                        : 'border-slate-200 bg-white text-slate-500 hover:border-slate-300 dark:border-slate-800 dark:bg-slate-950'
                "
            >
                {{ label }}
            </button>
        </div>

        <!-- Orders table -->
        <Card class="shadow-sm">
            <CardHeader class="border-b pb-3">
                <CardTitle class="text-base">
                    Danh sách đơn hàng
                    <span class="ml-1 text-sm font-normal text-muted-foreground"
                        >({{ orders.length }} đơn)</span
                    >
                </CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <div
                    v-if="orders.length"
                    class="divide-y divide-slate-100 dark:divide-slate-800"
                >
                    <div
                        v-for="o in orders"
                        :key="o.id"
                        class="border-b border-slate-100 dark:border-slate-800 last:border-0"
                    >
                        <!-- Order Main Row -->
                        <div
                            @click="expandedOrderId = expandedOrderId === o.id ? null : o.id"
                            class="flex cursor-pointer items-center gap-4 px-5 py-3.5 transition-colors hover:bg-slate-50/50 dark:hover:bg-slate-900/30"
                        >
                            <!-- Order info -->
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        class="font-mono text-sm font-bold text-slate-800 dark:text-slate-200"
                                        >{{ o.order_number }}</span
                                    >
                                    <span
                                        v-if="o.third_party_source"
                                        class="rounded-md border border-violet-200 bg-violet-50 px-1.5 py-0.5 text-[9px] font-bold text-violet-700 dark:border-violet-900/40 dark:bg-violet-950/20 dark:text-violet-400"
                                    >
                                        {{ o.third_party_source }}
                                    </span>
                                    <span
                                        v-else
                                        class="rounded-md border bg-slate-50 px-1.5 py-0.5 text-[9px] font-semibold text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400"
                                    >
                                        {{ channelLabel[o.channel] ?? o.channel }}
                                    </span>
                                    <span
                                        v-if="o.table_name"
                                        class="text-[10px] text-slate-400"
                                    >
                                        {{ o.table_name
                                        }}{{
                                            o.area_name ? ` · ${o.area_name}` : ''
                                        }}
                                    </span>
                                </div>
                                <div
                                    class="mt-1 flex items-center gap-3 text-xs text-muted-foreground"
                                >
                                    <span>{{ o.created_at }}</span>
                                    <span>{{ o.items_count }} món</span>
                                    <span v-if="o.completed_at"
                                        >Xong: {{ o.completed_at }}</span
                                    >
                                </div>
                            </div>

                            <!-- Amount + payment -->
                            <div class="shrink-0 text-right">
                                <p
                                    class="font-mono text-sm font-bold text-slate-800 dark:text-slate-200"
                                >
                                    {{ formatCurrency(o.total_amount) }}
                                </p>
                                <span
                                    class="text-[10px] font-semibold"
                                    :class="paymentConfig[o.payment_status]?.color"
                                >
                                    {{ paymentConfig[o.payment_status]?.label }}
                                </span>
                            </div>

                            <!-- Status + next action -->
                            <div class="flex shrink-0 items-center gap-2" @click.stop>
                                <span
                                    class="rounded-full px-2 py-1 text-[10px] font-semibold"
                                    :class="statusConfig[o.status]?.bg"
                                >
                                    {{ statusConfig[o.status]?.label }}
                                </span>
                                <button
                                    v-if="nextStatus[o.status]"
                                    @click="
                                        handleStatusUpdate(o, nextStatus[o.status]!)
                                    "
                                    class="h-7 rounded-lg bg-violet-600 px-2.5 text-[10px] font-semibold text-white transition-colors hover:bg-violet-700"
                                >
                                    {{
                                        nextStatus[o.status] === 'confirmed'
                                            ? 'Xác nhận'
                                            : nextStatus[o.status] === 'preparing'
                                              ? 'Chuyển bếp'
                                              : nextStatus[o.status] === 'completed'
                                                ? 'Hoàn thành'
                                                : ''
                                    }}
                                </button>
                            </div>
                        </div>

                        <!-- Expanded Items List -->
                        <div
                            v-if="expandedOrderId === o.id"
                            class="bg-slate-50/40 p-4 border-t border-slate-100 dark:bg-slate-900/10 dark:border-slate-800"
                        >
                            <h4 class="text-xs font-bold text-slate-400 mb-2 uppercase tracking-wider">Chi tiết món ăn</h4>
                            <div class="space-y-2">
                                <div
                                    v-for="item in o.items"
                                    :key="item.id"
                                    class="flex items-center justify-between p-2.5 rounded-xl border border-slate-100 bg-white dark:border-slate-800 dark:bg-slate-900 shadow-xs"
                                >
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-bold text-slate-800 dark:text-slate-200">
                                            {{ item.product_name }}
                                        </span>
                                        <span class="text-xs text-slate-500 font-mono">
                                            x{{ item.quantity }}
                                        </span>
                                        <span
                                            v-if="item.notes"
                                            class="text-[10px] text-amber-600 bg-amber-50 px-1.5 py-0.5 rounded-md dark:text-amber-400 dark:bg-amber-950/20"
                                        >
                                            Ghi chú: {{ item.notes }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <!-- Item Status Badge -->
                                        <span
                                            class="text-[9px] font-bold px-2 py-0.5 rounded-full border"
                                            :class="itemStatusConfig[item.status]?.class || 'bg-slate-50 text-slate-700'"
                                        >
                                            {{ itemStatusConfig[item.status]?.label || item.status }}
                                        </span>
                                        
                                        <!-- Item Status Actions -->
                                        <div class="flex gap-1" v-if="item.status !== 'served' && item.status !== 'cancelled' && o.status !== 'cancelled' && o.status !== 'completed'">
                                            <Button
                                                v-if="item.status === 'pending' || item.status === 'sent'"
                                                size="sm"
                                                variant="outline"
                                                :disabled="updatingItemStatus === item.id"
                                                @click="updateOrderItemStatus(item, 'preparing')"
                                                class="h-6 rounded-lg text-[9px] px-2 font-semibold border-violet-200 text-violet-750 hover:bg-violet-50 dark:border-violet-900/45 dark:text-violet-400"
                                            >
                                                Chế biến
                                            </Button>
                                            <Button
                                                v-if="item.status === 'preparing'"
                                                size="sm"
                                                variant="outline"
                                                :disabled="updatingItemStatus === item.id"
                                                @click="updateOrderItemStatus(item, 'served')"
                                                class="h-6 rounded-lg text-[9px] px-2 font-semibold border-emerald-250 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-900/45 dark:text-emerald-400"
                                            >
                                                Phục vụ
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div
                    v-else
                    class="flex flex-col items-center justify-center py-14 text-center"
                >
                    <div
                        class="mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-muted text-muted-foreground/40"
                    >
                        <ShoppingCart class="size-9" />
                    </div>
                    <p class="text-sm font-semibold">Không có đơn hàng nào</p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{
                            filters.status !== 'all'
                                ? 'Thử chọn trạng thái khác hoặc đổi ngày.'
                                : 'Chưa có đơn nào trong ngày này.'
                        }}
                    </p>
                </div>
            </CardContent>
        </Card>

        <!-- AI Upselling Dialog -->
        <Dialog v-model:open="isAiModalOpen">
            <DialogContent class="sm:max-w-[480px] rounded-2xl border border-violet-100 bg-white/95 backdrop-blur-md dark:border-slate-800 dark:bg-slate-900/95 shadow-2xl">
                <DialogHeader class="pb-3 flex flex-col items-center text-center">
                    <div class="h-14 w-14 rounded-2xl bg-violet-50 text-violet-600 dark:bg-violet-950/50 dark:text-violet-400 flex items-center justify-center mb-3 shadow-inner border border-violet-100/50 animate-pulse">
                        <Sparkles class="size-7" />
                    </div>
                    <DialogTitle class="text-xl font-bold tracking-tight text-slate-900 dark:text-slate-100">
                        Trợ Lý AI Kích Cầu (Upselling)
                    </DialogTitle>
                    <DialogDescription class="text-xs text-slate-500 mt-1 dark:text-slate-400">
                        Gợi ý tư vấn thêm đồ uống/món ăn kèm cho khách hàng dựa trên phân tích giỏ hàng.
                    </DialogDescription>
                </DialogHeader>

                <div class="py-4 space-y-4">
                    <!-- Info box -->
                    <div class="rounded-xl border border-slate-100 bg-slate-50/50 p-4 dark:border-slate-800 dark:bg-slate-950/30">
                        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider">ĐƠN HÀNG XÁC NHẬN</p>
                        <p class="text-sm font-bold text-slate-800 dark:text-slate-200 mt-1">
                            Bàn {{ currentVerifyingOrder?.table_name || '—' }} ({{ currentVerifyingOrder?.order_number }})
                        </p>
                        
                        <div class="h-px bg-slate-200 dark:bg-slate-800 my-2.5"></div>
                        
                        <p class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">MÓN ĐÃ GỌI</p>
                        <div class="flex flex-wrap gap-1.5 mt-1">
                            <span 
                                v-for="item in currentVerifyingOrder?.items" 
                                :key="item.id"
                                class="inline-block px-2.5 py-1 bg-white border border-slate-200 text-xs rounded-lg text-slate-700 font-medium dark:bg-slate-900 dark:border-slate-800 dark:text-slate-300"
                            >
                                {{ item.product_name }} x{{ item.quantity }}
                            </span>
                        </div>
                    </div>

                    <!-- Suggestion content -->
                    <div class="rounded-2xl bg-gradient-to-br from-violet-600 to-indigo-700 text-white p-5 shadow-md relative overflow-hidden">
                        <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-white/10 rounded-full blur-lg"></div>
                        
                        <div class="flex items-start gap-3.5">
                            <div class="shrink-0 p-2 bg-white/20 rounded-xl mt-0.5">
                                <Sparkles class="size-5 text-white" />
                            </div>
                            <div class="space-y-1.5">
                                <p class="text-[10px] text-white/70 font-bold tracking-wider uppercase">GỢI Ý TỪ AI</p>
                                <p class="text-sm font-semibold leading-relaxed tracking-wide italic">
                                    "{{ aiSuggestionText }}"
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="text-[10px] text-right text-slate-400">
                        Thuật toán: <span class="font-semibold text-slate-500 dark:text-slate-300">{{ aiSource }}</span>
                    </div>
                </div>

                <DialogFooter class="sm:justify-end gap-2">
                    <Button 
                        @click="isAiModalOpen = false"
                        class="rounded-xl px-5 bg-slate-900 hover:bg-slate-800 text-white font-semibold dark:bg-slate-800 dark:hover:bg-slate-750"
                    >
                        Đóng gợi ý
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
