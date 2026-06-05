<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    ShoppingCart, CheckCircle2, Clock, XCircle, ChefHat,
    Banknote, Filter, CalendarDays, RefreshCw, AlertCircle,
    Bot, Sparkles, Trash2, AlertTriangle, Check, X
} from 'lucide-vue-next';
import { computed, ref, onMounted } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import { toast } from 'vue-sonner';
import axios from 'axios';

defineOptions({ layout: AppLayout });

type Order = {
    id: number;
    order_number: string;
    status: string;
    payment_status: string;
    channel: string;
    table_name: string | null;
    area_name: string | null;
    total_amount: number;
    items_count: number;
    created_at: string;
    completed_at: string | null;
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
    filters: { status: string; date: string };
    autoPayEnabled: boolean;
}>();

const page = usePage();
const roles = computed(() => {
    const raw = (page.props as any).roles ?? [];
    return Array.isArray(raw) ? raw : Object.values(raw as Record<string, string>);
});
const isOwner = computed(() => roles.value.includes('owner'));
const canUpdateStatus = computed(() => !roles.value.includes('kitchen'));

const toggleAutoPay = () => {
    router.post('/settings/toggle-auto-pay', {}, { preserveScroll: true });
};

const dateInput  = ref(props.filters.date);
const activeStatus = ref(props.filters.status);

const applyFilters = () => {
    router.get('/orders', { status: activeStatus.value, date: dateInput.value }, { preserveScroll: true });
};

const setStatus = (s: string) => {
    activeStatus.value = s;
    router.get('/orders', { status: s, date: dateInput.value }, { preserveScroll: true });
};

const updateOrderStatus = (order: Order, newStatus: string) => {
    router.patch(`/orders/${order.id}/status`, { status: newStatus }, { preserveScroll: true });
};

const statusConfig: Record<string, { label: string; color: string; bg: string; icon: any }> = {
    pending:   { label: 'Chờ xác nhận', color: 'text-amber-700',  bg: 'bg-amber-100 dark:bg-amber-950/40 dark:text-amber-400',  icon: Clock },
    confirmed: { label: 'Đã xác nhận',  color: 'text-sky-700',    bg: 'bg-sky-100 dark:bg-sky-950/40 dark:text-sky-400',        icon: CheckCircle2 },
    preparing: { label: 'Đang chế biến', color: 'text-violet-700', bg: 'bg-violet-100 dark:bg-violet-950/40 dark:text-violet-400', icon: ChefHat },
    completed: { label: 'Hoàn thành',   color: 'text-emerald-700', bg: 'bg-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-400', icon: CheckCircle2 },
    cancelled: { label: 'Đã hủy',       color: 'text-rose-700',   bg: 'bg-rose-100 dark:bg-rose-950/40 dark:text-rose-400',    icon: XCircle },
};

const paymentConfig: Record<string, { label: string; color: string }> = {
    unpaid:   { label: 'Chưa TT', color: 'text-rose-600' },
    partial:  { label: 'TT 1 phần', color: 'text-amber-600' },
    paid:     { label: 'Đã TT', color: 'text-emerald-600' },
    refunded: { label: 'Hoàn tiền', color: 'text-slate-500' },
};

const channelLabel: Record<string, string> = {
    dine_in:  'Tại bàn',
    takeaway: 'Mang về',
    delivery: 'Giao hàng',
    qr:       'QR Scan',
};

const formatCurrency = (v: number) =>
    new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(v);

const nextStatus: Record<string, string | null> = {
    pending:   'confirmed',
    confirmed: 'preparing',
    preparing: 'completed',
    completed: null,
    cancelled: null,
};

interface QrOrderItem {
    product_id: number;
    name: string;
    quantity: number;
    notes: string | null;
    line_total: number;
}

interface PendingQrOrder {
    id: number;
    table_name: string;
    area_name: string;
    status: string;
    minutes_elapsed: number;
    created_at: string;
    items: QrOrderItem[];
    total_amount: number;
}

interface RejectedQrLog {
    id: number;
    table_name: string;
    area_name: string;
    cancelled_at: string;
    total_amount: number;
    items: QrOrderItem[];
    cancelled_by_name: string;
    cancellation_reason: string;
}

// QR Temporary Orders State
const activeTab = ref('official'); // 'official', 'pending_qr', 'rejected_qr'
const pendingQrOrders = ref<PendingQrOrder[]>([]);
const rejectedQrLogs = ref<RejectedQrLog[]>([]);
const isLoadingQr = ref(false);

// Upsell proposal modal
const showUpsellModal = ref(false);
const upsellData = ref<any>(null);
const currentOrderId = ref<number | null>(null);

// Cancel reason modal
const showCancelModal = ref(false);
const cancelOrderId = ref<number | null>(null);
const cancelReason = ref('');

const fetchPendingQr = async () => {
    isLoadingQr.value = true;
    try {
        const res = await axios.get('/api/temporary-orders');
        pendingQrOrders.value = res.data.temporary_orders;
    } catch (e) {
        console.error(e);
    } finally {
        isLoadingQr.value = false;
    }
};

const fetchRejectedQr = async () => {
    isLoadingQr.value = true;
    try {
        const res = await axios.get('/api/temporary-orders/rejected-logs');
        rejectedQrLogs.value = res.data.rejected_logs;
    } catch (e) {
        console.error(e);
    } finally {
        isLoadingQr.value = false;
    }
};

const selectTab = (tab: string) => {
    activeTab.value = tab;
    if (tab === 'pending_qr') {
        fetchPendingQr();
    } else if (tab === 'rejected_qr') {
        fetchRejectedQr();
    }
};

const confirmTempOrder = async (orderId: number) => {
    try {
        const response = await axios.post(`/api/temporary-orders/${orderId}/confirm`);
        if (response.data.success) {
            toast.success(response.data.message);
            fetchPendingQr();
            
            // If upselling suggestions returned, trigger upsell modal
            if (response.data.upsell && response.data.upsell.recommended_item) {
                upsellData.value = response.data.upsell;
                currentOrderId.value = response.data.order_id;
                showUpsellModal.value = true;
            }
        }
    } catch (err: any) {
        toast.error(err.response?.data?.message || 'Có lỗi xảy ra khi xác nhận đơn.');
    }
};

const openCancelModal = (orderId: number) => {
    cancelOrderId.value = orderId;
    cancelReason.value = '';
    showCancelModal.value = true;
};

const submitCancel = async () => {
    if (!cancelOrderId.value || !cancelReason.value.trim()) return;
    try {
        const response = await axios.post(`/api/temporary-orders/${cancelOrderId.value}/cancel`, {
            reason: cancelReason.value
        });
        if (response.data.success) {
            toast.success(response.data.message);
            showCancelModal.value = false;
            fetchPendingQr();
        }
    } catch (err) {
        toast.error('Có lỗi xảy ra khi hủy yêu cầu.');
    }
};

const addUpsellItem = async () => {
    if (!currentOrderId.value || !upsellData.value?.recommended_item) return;
    try {
        await axios.patch(`/orders/${currentOrderId.value}`, {
            items: [
                {
                    product_id: null,
                    quantity: 1,
                    notes: 'Món mua thêm theo đề xuất AI'
                }
            ]
        });
        toast.success(`Đã thêm món đề xuất '${upsellData.value.recommended_item}' vào đơn hàng!`);
        showUpsellModal.value = false;
    } catch (e) {
        toast.info(`Vui lòng thêm thủ công món '${upsellData.value.recommended_item}' từ màn hình POS.`);
        showUpsellModal.value = false;
    }
};

onMounted(() => {
    // Check if there is an active tab parameter in the URL
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    if (tabParam && ['pending_qr', 'rejected_qr'].includes(tabParam)) {
        selectTab(tabParam);
    }
});
</script>

<template>
    <Head title="Quản lý đơn hàng" />

    <div class="flex flex-col gap-6 p-6 max-w-7xl mx-auto w-full">
        <!-- Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b pb-5">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-violet-50 dark:bg-violet-950/60 text-violet-600 dark:text-violet-400">
                    <ShoppingCart class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">Quản Lý Đơn Hàng</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Theo dõi, xác nhận và cập nhật trạng thái đơn hàng theo thời gian thực.</p>
                </div>
            </div>
            <!-- Date picker and settings -->
            <div class="flex items-center gap-2 flex-wrap">
                <Button
                    v-if="isOwner"
                    variant="outline"
                    size="sm"
                    @click="toggleAutoPay"
                    class="h-10 px-4 flex items-center gap-2 border transition-all rounded-xl cursor-pointer"
                    :class="props.autoPayEnabled
                        ? 'border-emerald-500 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 hover:text-emerald-800 dark:bg-emerald-950/20 dark:text-emerald-400 dark:border-emerald-800'
                        : 'border-slate-200 bg-white text-slate-500 hover:border-slate-300 dark:border-slate-800 dark:bg-slate-950'"
                >
                    <span class="relative flex h-2 w-2">
                        <span :class="['animate-ping absolute inline-flex h-full w-full rounded-full opacity-75', props.autoPayEnabled ? 'bg-emerald-400' : 'bg-slate-400']"></span>
                        <span :class="['relative inline-flex rounded-full h-2 w-2', props.autoPayEnabled ? 'bg-emerald-500' : 'bg-slate-500']"></span>
                    </span>
                    <span>Tự động thanh toán ca cuối: <strong class="uppercase">{{ props.autoPayEnabled ? 'Bật' : 'Tắt' }}</strong></span>
                </Button>

                <div class="relative">
                    <CalendarDays class="absolute left-3 top-2.5 size-4 text-muted-foreground pointer-events-none" />
                    <input
                        type="date"
                        v-model="dateInput"
                        @change="applyFilters"
                        class="pl-9 h-10 rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    />
                </div>
                <Button variant="outline" size="sm" @click="applyFilters" class="h-10">
                    <RefreshCw class="size-4" />
                </Button>
            </div>
        </div>

        <!-- Summary KPIs -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <div class="rounded-xl border border-border bg-card p-3 text-center">
                <p class="text-2xl font-bold">{{ summary.total }}</p>
                <p class="text-xs text-muted-foreground mt-0.5">Tổng đơn</p>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50 dark:border-amber-900/40 dark:bg-amber-950/20 p-3 text-center">
                <p class="text-2xl font-bold text-amber-700 dark:text-amber-400">{{ summary.pending }}</p>
                <p class="text-xs text-amber-600/70 mt-0.5">Chờ xác nhận</p>
            </div>
            <div class="rounded-xl border border-violet-200 bg-violet-50 dark:border-violet-900/40 dark:bg-violet-950/20 p-3 text-center">
                <p class="text-2xl font-bold text-violet-700 dark:text-violet-400">{{ summary.preparing }}</p>
                <p class="text-xs text-violet-600/70 mt-0.5">Đang chế biến</p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 dark:border-emerald-900/40 dark:bg-emerald-950/20 p-3 text-center">
                <p class="text-2xl font-bold text-emerald-700 dark:text-emerald-400">{{ summary.completed }}</p>
                <p class="text-xs text-emerald-600/70 mt-0.5">Hoàn thành</p>
            </div>
            <div class="rounded-xl border border-rose-200 bg-rose-50 dark:border-rose-900/40 dark:bg-rose-950/20 p-3 text-center">
                <p class="text-2xl font-bold text-rose-700 dark:text-rose-400">{{ summary.cancelled }}</p>
                <p class="text-xs text-rose-600/70 mt-0.5">Đã hủy</p>
            </div>
            <div class="rounded-xl border border-emerald-300 bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-950/30 p-3 text-center">
                <p class="text-lg font-bold text-emerald-700 dark:text-emerald-400 leading-tight">
                    {{ new Intl.NumberFormat('vi-VN', { notation: 'compact', maximumFractionDigits: 1 }).format(summary.revenue) }}đ
                </p>
                <p class="text-xs text-emerald-600/70 mt-0.5">Doanh thu</p>
            </div>
        </div>

        <!-- Primary Tabs Navigation -->
        <div class="flex border-b border-slate-200 dark:border-slate-800 gap-6 mt-2 shrink-0">
            <button 
                @click="selectTab('official')"
                :class="['pb-3 text-sm font-semibold transition-all border-b-2 cursor-pointer', activeTab === 'official' ? 'border-violet-500 text-violet-600 dark:text-violet-400' : 'border-transparent text-slate-500 hover:text-slate-700']"
            >
                Đơn hàng chính thức
            </button>
            <button 
                @click="selectTab('pending_qr')"
                :class="['pb-3 text-sm font-semibold transition-all border-b-2 cursor-pointer flex items-center gap-1.5', activeTab === 'pending_qr' ? 'border-violet-500 text-violet-600 dark:text-violet-400' : 'border-transparent text-slate-500 hover:text-slate-700']"
            >
                <span>Yêu cầu QR đang chờ</span>
                <span v-if="pendingQrOrders.length" class="bg-red-500 text-white text-[10px] px-1.5 py-0.5 rounded-full font-bold animate-pulse">
                    {{ pendingQrOrders.length }}
                </span>
            </button>
            <button 
                v-if="isOwner || roles.includes('manager')"
                @click="selectTab('rejected_qr')"
                :class="['pb-3 text-sm font-semibold transition-all border-b-2 cursor-pointer', activeTab === 'rejected_qr' ? 'border-violet-500 text-violet-600 dark:text-violet-400' : 'border-transparent text-slate-500 hover:text-slate-700']"
            >
                Nhật ký hủy QR hôm nay
            </button>
        </div>

        <!-- TAB 1: Official Orders -->
        <div v-if="activeTab === 'official'" class="space-y-4 flex flex-col">
            <!-- Status filter chips -->
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="(label, key) in ({ all: 'Tất cả', pending: 'Chờ xác nhận', confirmed: 'Đã xác nhận', preparing: 'Đang chế biến', completed: 'Hoàn thành', cancelled: 'Đã hủy' } as Record<string, string>)"
                    :key="key"
                    @click="setStatus(key)"
                    class="px-3 py-1.5 rounded-full text-xs font-semibold border transition-all"
                    :class="activeStatus === key
                        ? 'border-violet-500 bg-violet-50 text-violet-700 dark:bg-violet-950/40 dark:text-violet-400 dark:border-violet-700'
                        : 'border-slate-200 bg-white text-slate-500 dark:border-slate-800 dark:bg-slate-950 hover:border-slate-300'"
                >
                    {{ label }}
                </button>
            </div>

            <!-- Orders table -->
            <Card class="shadow-sm">
                <CardHeader class="pb-3 border-b">
                    <CardTitle class="text-base">
                        Danh sách đơn hàng
                        <span class="text-muted-foreground font-normal text-sm ml-1">({{ orders.length }} đơn)</span>
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-0">
                    <div v-if="orders.length" class="divide-y divide-slate-100 dark:divide-slate-800">
                        <div
                            v-for="o in orders" :key="o.id"
                            class="flex items-center gap-4 px-5 py-3.5 hover:bg-slate-50/50 dark:hover:bg-slate-900/30 transition-colors"
                        >
                            <!-- Order info -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-mono font-bold text-sm text-slate-800 dark:text-slate-200">{{ o.order_number }}</span>
                                    <span class="text-[9px] px-1.5 py-0.5 rounded-md border font-semibold bg-slate-50 text-slate-600 dark:bg-slate-900 dark:text-slate-400 dark:border-slate-700">
                                        {{ channelLabel[o.channel] ?? o.channel }}
                                    </span>
                                    <span v-if="o.table_name" class="text-[10px] text-slate-400">
                                        {{ o.table_name }}{{ o.area_name ? ` · ${o.area_name}` : '' }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-3 mt-1 text-xs text-muted-foreground">
                                    <span>{{ o.created_at }}</span>
                                    <span>{{ o.items_count }} món</span>
                                    <span v-if="o.completed_at">Xong: {{ o.completed_at }}</span>
                                </div>
                            </div>

                            <!-- Amount + payment -->
                            <div class="text-right shrink-0">
                                <p class="font-mono font-bold text-sm text-slate-800 dark:text-slate-200">{{ formatCurrency(o.total_amount) }}</p>
                                <span class="text-[10px] font-semibold" :class="paymentConfig[o.payment_status]?.color">
                                    {{ paymentConfig[o.payment_status]?.label }}
                                </span>
                            </div>

                            <!-- Status + next action -->
                            <div class="flex items-center gap-2 shrink-0">
                                <span class="text-[10px] px-2 py-1 rounded-full font-semibold" :class="statusConfig[o.status]?.bg">
                                    {{ statusConfig[o.status]?.label }}
                                </span>
                                <button
                                    v-if="nextStatus[o.status] && canUpdateStatus"
                                    @click="updateOrderStatus(o, nextStatus[o.status]!)"
                                    class="h-7 px-2.5 rounded-lg text-[10px] font-semibold bg-violet-600 hover:bg-violet-700 text-white transition-colors cursor-pointer"
                                >
                                    {{ nextStatus[o.status] === 'confirmed' ? 'Xác nhận'
                                        : nextStatus[o.status] === 'preparing' ? 'Chuyển bếp'
                                        : nextStatus[o.status] === 'completed' ? 'Hoàn thành'
                                        : '' }}
                                </button>
                            </div>
                        </div>
                    </div>
                    <div v-else class="flex flex-col items-center justify-center py-14 text-center">
                        <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-muted text-muted-foreground/40 mb-4">
                            <ShoppingCart class="size-9" />
                        </div>
                        <p class="font-semibold text-sm">Không có đơn hàng nào</p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ filters.status !== 'all' ? 'Thử chọn trạng thái khác hoặc đổi ngày.' : 'Chưa có đơn nào trong ngày này.' }}
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- TAB 2: Pending QR Orders -->
        <div v-else-if="activeTab === 'pending_qr'" class="space-y-4">
            <Card class="shadow-sm">
                <CardHeader class="pb-3 border-b flex flex-row items-center justify-between">
                    <CardTitle class="text-base">
                        Yêu cầu đặt món QR đang chờ xác thực
                        <span class="text-muted-foreground font-normal text-sm ml-1">({{ pendingQrOrders.length }} yêu cầu)</span>
                    </CardTitle>
                    <Button variant="outline" size="sm" @click="fetchPendingQr" class="h-8">
                        <RefreshCw :class="['size-3.5', isLoadingQr ? 'animate-spin' : '']" />
                    </Button>
                </CardHeader>
                <CardContent class="p-0">
                    <div v-if="pendingQrOrders.length" class="divide-y divide-slate-100 dark:divide-slate-800">
                        <div
                            v-for="o in pendingQrOrders" :key="o.id"
                            class="flex flex-col md:flex-row md:items-center justify-between gap-4 px-6 py-5 hover:bg-slate-50/50 dark:hover:bg-slate-900/30 transition-colors"
                        >
                            <div class="flex-1 space-y-2">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-bold text-sm text-slate-800 dark:text-slate-200">{{ o.table_name }}</span>
                                    <span class="text-xs text-slate-400 bg-slate-100 dark:bg-slate-900 px-2 py-0.5 rounded">{{ o.area_name }}</span>
                                    <span v-if="o.status === 'escalated'" class="text-[10px] font-bold text-red-500 animate-pulse bg-red-100 dark:bg-red-955/40 px-2 py-0.5 rounded border border-red-200 dark:border-red-900/30">
                                        🚨 QUÁ HẠN ({{ o.minutes_elapsed }}m)
                                    </span>
                                    <span v-else class="text-[10px] text-amber-500 bg-amber-100 dark:bg-amber-955/40 px-2 py-0.5 rounded border border-amber-200 dark:border-amber-900/30">
                                        Chờ duyệt ({{ o.created_at }})
                                    </span>
                                </div>

                                <div class="bg-slate-50 dark:bg-slate-950 p-3 rounded-xl border border-slate-100 dark:border-slate-800 space-y-1 text-xs">
                                    <div v-for="(item, idx) in o.items" :key="idx" class="flex justify-between items-center text-slate-650 dark:text-slate-350">
                                        <span>{{ item.quantity }}x {{ item.name }}</span>
                                        <span class="text-[10px] italic text-slate-400" v-if="item.notes">Ghi chú: {{ item.notes }}</span>
                                        <span class="font-mono text-slate-500">{{ formatCurrency(item.line_total) }}</span>
                                    </div>
                                    <div class="flex justify-between font-bold text-slate-800 dark:text-slate-200 border-t border-slate-200 dark:border-slate-900 pt-2.5 mt-2">
                                        <span>Tổng thanh toán:</span>
                                        <span class="font-mono text-amber-500">{{ formatCurrency(o.total_amount) }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-3 self-end md:self-center">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="openCancelModal(o.id)"
                                    class="border-red-500/30 text-red-655 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950/20 hover:text-red-700 h-9 rounded-xl flex items-center gap-1.5 cursor-pointer"
                                >
                                    <Trash2 class="size-4" /> Từ chối
                                </Button>
                                <Button
                                    size="sm"
                                    @click="confirmTempOrder(o.id)"
                                    class="bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold h-9 rounded-xl flex items-center gap-1.5 shadow-md shadow-amber-500/10 cursor-pointer"
                                >
                                    <Check class="size-4" /> Xác nhận & Đẩy Bếp
                                </Button>
                            </div>
                        </div>
                    </div>
                    
                    <div v-else class="flex flex-col items-center justify-center py-20 text-center text-muted-foreground">
                        <Utensils class="size-10 text-muted-foreground/30 mb-4" />
                        <p class="font-semibold text-sm">Không có yêu cầu QR nào đang chờ</p>
                        <p class="text-xs">Khi khách hàng quét mã gọi món, các yêu cầu sẽ xuất hiện tại đây.</p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- TAB 3: Rejected QR Logs -->
        <div v-else-if="activeTab === 'rejected_qr'" class="space-y-4">
            <Card class="shadow-sm">
                <CardHeader class="pb-3 border-b flex flex-row items-center justify-between">
                    <CardTitle class="text-base">
                        Nhật ký hủy yêu cầu gọi món QR trong ngày
                        <span class="text-muted-foreground font-normal text-sm ml-1">({{ rejectedQrLogs.length }} đơn bị hủy)</span>
                    </CardTitle>
                    <Button variant="outline" size="sm" @click="fetchRejectedQr" class="h-8">
                        <RefreshCw :class="['size-3.5', isLoadingQr ? 'animate-spin' : '']" />
                    </Button>
                </CardHeader>
                <CardContent class="p-0">
                    <div v-if="rejectedQrLogs.length" class="divide-y divide-slate-100 dark:divide-slate-800">
                        <div
                            v-for="l in rejectedQrLogs" :key="l.id"
                            class="p-5 hover:bg-slate-50/50 dark:hover:bg-slate-900/30 transition-colors space-y-3"
                        >
                            <div class="flex justify-between items-start gap-2 flex-wrap">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-sm text-slate-800 dark:text-slate-200">{{ l.table_name }}</span>
                                        <span class="text-xxs text-slate-400 bg-slate-100 dark:bg-slate-900 px-1.5 py-0.5 rounded">{{ l.area_name }}</span>
                                    </div>
                                    <p class="text-xxs text-slate-500">Thời gian hủy: {{ l.cancelled_at }}</p>
                                </div>
                                <div class="text-right">
                                    <span class="font-mono text-xs font-bold text-slate-700 dark:text-slate-300">Hủy trị giá: {{ formatCurrency(l.total_amount) }}</span>
                                </div>
                            </div>

                            <div class="bg-slate-50 dark:bg-slate-950/50 p-2.5 rounded-lg text-xxs text-slate-500 border border-slate-100 dark:border-slate-800">
                                Món bị hủy: {{ l.items.map(i => `${i.quantity}x ${i.name}`).join(', ') }}
                            </div>

                            <div class="flex items-center gap-2 bg-red-500/5 p-3 rounded-xl border border-red-500/10 text-xs">
                                <AlertCircle class="size-4 text-red-500 shrink-0" />
                                <div>
                                    <span class="font-extrabold text-red-500">Nhân viên hủy: {{ l.cancelled_by_name }}</span>
                                    <span class="text-slate-400 ml-1.5">• Lý do: "{{ l.cancellation_reason }}"</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div v-else class="flex flex-col items-center justify-center py-20 text-center text-muted-foreground">
                        <Check class="size-10 text-muted-foreground/30 mb-4" />
                        <p class="font-semibold text-sm">Chưa có nhật ký hủy nào hôm nay</p>
                        <p class="text-xs">Các yêu cầu đặt món QR bị nhân viên ấn từ chối trong ngày sẽ được ghi nhận tại đây.</p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- ── AI Upsell proposal modal ────────────────────────────────────── -->
        <div v-if="showUpsellModal && upsellData" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-gradient-to-br from-slate-900 to-slate-950 border border-slate-800 rounded-3xl w-full max-w-sm overflow-hidden shadow-2xl relative text-slate-200">
                <header class="p-4 bg-amber-500/10 border-b border-amber-500/10 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <Bot class="size-5 text-amber-400 animate-pulse" />
                        <h3 class="text-xs font-bold text-amber-400 tracking-wider uppercase flex items-center gap-1">
                            Smart Upselling Engine <Sparkles class="size-3.5 text-amber-400" />
                        </h3>
                    </div>
                    <button @click="showUpsellModal = false" class="p-1 rounded-lg bg-slate-855 hover:bg-slate-800 text-slate-400 cursor-pointer">
                        <X class="size-4" />
                    </button>
                </header>
                
                <div class="p-5 space-y-4">
                    <div class="p-4 bg-slate-950 rounded-2xl border border-slate-800 space-y-2">
                        <p class="text-xs text-slate-350 font-medium leading-relaxed">
                            {{ upsellData.suggestion }}
                        </p>
                        <div class="flex justify-between items-center text-[10px] text-slate-500 pt-2 border-t border-slate-900">
                            <span>Độ tin cậy: {{ Math.round(upsellData.confidence * 100) }}%</span>
                            <span>Chỉ số Lift: {{ upsellData.lift }}x</span>
                        </div>
                    </div>

                    <div v-if="upsellData.recommended_item" class="p-3 bg-amber-500/5 rounded-xl border border-amber-500/10 flex items-center justify-between text-xs">
                        <span class="font-bold text-slate-200">Gợi ý: Mời dùng {{ upsellData.recommended_item }}</span>
                        <span class="text-xxs text-amber-400 font-bold px-1.5 py-0.5 rounded bg-amber-500/10">-10% combo</span>
                    </div>
                </div>
                
                <footer class="p-4 border-t border-slate-800 bg-slate-950/20 flex gap-2">
                    <button @click="showUpsellModal = false" class="flex-1 h-10 rounded-xl border border-slate-800 text-slate-450 text-xs font-bold hover:bg-slate-850 cursor-pointer">
                        Bỏ qua
                    </button>
                    <button 
                        @click="addUpsellItem"
                        class="flex-1 h-10 bg-amber-500 hover:bg-amber-400 text-slate-950 rounded-xl text-xs font-extrabold flex items-center justify-center gap-1 shadow-lg shadow-amber-500/10 cursor-pointer"
                    >
                        <Check class="size-4" /> Thêm vào đơn
                    </button>
                </footer>
            </div>
        </div>

        <!-- ── Cancellation Reason Modal ────────────────────────────────────── -->
        <div v-if="showCancelModal" class="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-3xl w-full max-w-xs overflow-hidden shadow-2xl relative text-slate-200">
                <header class="p-4 border-b border-slate-800 flex items-center justify-between">
                    <h3 class="text-xs font-bold text-slate-200">Lý do từ chối yêu cầu QR</h3>
                    <button @click="showCancelModal = false" class="p-1 rounded-lg bg-slate-850 hover:bg-slate-850 text-slate-400 cursor-pointer">
                        <X class="size-4" />
                    </button>
                </header>
                
                <div class="p-4 space-y-3">
                    <textarea 
                        v-model="cancelReason" 
                        rows="3"
                        placeholder="Nhập lý do từ chối..." 
                        class="w-full p-2.5 rounded-xl bg-slate-950 border border-slate-800 text-xs text-slate-300 focus:outline-none focus:border-red-500 resize-none"
                    ></textarea>
                </div>
                
                <footer class="p-4 border-t border-slate-800 bg-slate-950/20 flex gap-2">
                    <button @click="showCancelModal = false" class="flex-1 h-9 rounded-lg border border-slate-850 text-slate-400 text-xs font-bold hover:bg-slate-850 cursor-pointer">
                        Quay lại
                    </button>
                    <button 
                        @click="submitCancel"
                        :disabled="!cancelReason.trim()"
                        class="flex-1 h-9 bg-red-500 hover:bg-red-400 disabled:bg-slate-800 disabled:text-slate-600 text-slate-950 rounded-lg text-xs font-bold cursor-pointer"
                    >
                        Từ chối đặt món
                    </button>
                </footer>
            </div>
        </div>
    </div>
</template>
