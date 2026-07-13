<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import {
    ShoppingCart,
    CheckCircle2,
    Clock,
    XCircle,
    ChefHat,
    CalendarDays,
    RefreshCw,
    AlertCircle,
    Bot,
    Sparkles,
    Trash2,
    Check,
    X,
    Utensils,
    Loader2,
} from 'lucide-vue-next';
import { computed, ref, onMounted } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/AppLayout.vue';

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

    return Array.isArray(raw)
        ? raw
        : Object.values(raw as Record<string, string>);
});
const isOwner = computed(() => roles.value.includes('owner'));
const canUpdateStatus = computed(() => !roles.value.includes('kitchen'));

const toggleAutoPay = () => {
    router.post('/settings/toggle-auto-pay', {}, { preserveScroll: true });
};

const dateInput = ref(props.filters.date);
const activeStatus = ref(props.filters.status);

const applyFilters = () => {
    router.get(
        '/orders',
        { status: activeStatus.value, date: dateInput.value },
        { preserveScroll: true },
    );
};

const setStatus = (s: string) => {
    activeStatus.value = s;
    router.get(
        '/orders',
        { status: s, date: dateInput.value },
        { preserveScroll: true },
    );
};

const statusUpdating = ref<Record<number, boolean>>({});

const updateOrderStatus = (order: Order, newStatus: string) => {
    if (statusUpdating.value[order.id]) {
        return;
    }
    statusUpdating.value[order.id] = true;
    router.patch(
        `/orders/${order.id}/status`,
        { status: newStatus },
        {
            preserveScroll: true,
            onFinish: () => {
                statusUpdating.value[order.id] = false;
            },
        },
    );
};

// ─── Tách bill / Chuyển bàn ───
type BillItem = {
    id: number;
    name: string;
    quantity: number;
    line_total: number;
};
type FreeTable = { id: number; name: string; capacity: number };

const actionOrder = ref<Order | null>(null);
const actionType = ref<'split' | 'move' | 'merge' | null>(null);
const selectedTargetOrderId = ref<number | null>(null);
const billItems = ref<BillItem[]>([]);
const freeTables = ref<FreeTable[]>([]);
const selectedItemIds = ref<number[]>([]);
const selectedTableId = ref<number | null>(null);
const actionLoading = ref(false);

const canModify = (o: Order) =>
    o.payment_status !== 'paid' &&
    !['completed', 'cancelled'].includes(o.status);

async function openSplit(o: Order) {
    actionOrder.value = o;
    actionType.value = 'split';
    selectedItemIds.value = [];
    actionLoading.value = true;

    try {
        const { data } = await axios.get(`/api/orders/${o.id}/items`);
        billItems.value = data;
    } catch {
        toast.error('Không tải được danh sách món.');
        actionType.value = null;
    } finally {
        actionLoading.value = false;
    }
}

async function openMove(o: Order) {
    actionOrder.value = o;
    actionType.value = 'move';
    selectedTableId.value = null;
    actionLoading.value = true;

    try {
        const { data } = await axios.get('/api/orders/available-tables');
        freeTables.value = data;
    } catch {
        toast.error('Không tải được danh sách bàn trống.');
        actionType.value = null;
    } finally {
        actionLoading.value = false;
    }
}

// Các đơn khác có thể nhận gộp (chưa thanh toán, đang hoạt động, khác đơn nguồn).
const mergeCandidates = computed(() =>
    props.orders.filter(
        (o) => canModify(o) && o.id !== actionOrder.value?.id,
    ),
);

function openMerge(o: Order) {
    actionOrder.value = o;
    actionType.value = 'merge';
    selectedTargetOrderId.value = null;
}

function toggleSplitItem(id: number) {
    const idx = selectedItemIds.value.indexOf(id);

    if (idx >= 0) {
        selectedItemIds.value.splice(idx, 1);
    } else {
        selectedItemIds.value.push(id);
    }
}

function submitAction() {
    if (!actionOrder.value || !actionType.value) {
        return;
    }

    actionLoading.value = true;
    const url =
        actionType.value === 'split'
            ? `/orders/${actionOrder.value.id}/split`
            : actionType.value === 'merge'
              ? `/orders/${actionOrder.value.id}/merge`
              : `/orders/${actionOrder.value.id}/move-table`;
    const payload =
        actionType.value === 'split'
            ? { item_ids: selectedItemIds.value }
            : actionType.value === 'merge'
              ? { target_order_id: selectedTargetOrderId.value }
              : { table_id: selectedTableId.value };

    router.post(url, payload, {
        preserveScroll: true,
        onSuccess: () => {
            actionType.value = null;
            actionOrder.value = null;
        },
        onError: (errs: any) => {
            toast.error(String(Object.values(errs)[0] ?? 'Có lỗi xảy ra.'));
        },
        onFinish: () => {
            actionLoading.value = false;
        },
    });
}

const splitTotal = computed(() =>
    billItems.value
        .filter((i) => selectedItemIds.value.includes(i.id))
        .reduce((sum, i) => sum + i.line_total, 0),
);

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

const formatCurrency = (v: number) =>
    new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(v);

const nextStatus: Record<string, string | null> = {
    pending: 'confirmed',
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

const confirmingOrders = ref<Record<number, boolean>>({});
const isCancellingQr = ref(false);

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
    if (confirmingOrders.value[orderId]) return;
    confirmingOrders.value[orderId] = true;

    try {
        const response = await axios.post(
            `/api/temporary-orders/${orderId}/confirm`,
        );

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
        toast.error(
            err.response?.data?.message || 'Có lỗi xảy ra khi xác nhận đơn.',
        );
    } finally {
        confirmingOrders.value[orderId] = false;
    }
};

const openCancelModal = (orderId: number) => {
    cancelOrderId.value = orderId;
    cancelReason.value = '';
    showCancelModal.value = true;
};

const submitCancel = async () => {
    if (
        !cancelOrderId.value ||
        !cancelReason.value.trim() ||
        isCancellingQr.value
    ) {
        return;
    }
    isCancellingQr.value = true;

    try {
        const response = await axios.post(
            `/api/temporary-orders/${cancelOrderId.value}/cancel`,
            {
                reason: cancelReason.value,
            },
        );

        if (response.data.success) {
            toast.success(response.data.message);
            showCancelModal.value = false;
            fetchPendingQr();
        }
    } catch {
        toast.error('Có lỗi xảy ra khi hủy yêu cầu.');
    } finally {
        isCancellingQr.value = false;
    }
};

const addUpsellItem = async () => {
    if (!currentOrderId.value || !upsellData.value?.recommended_item) {
        return;
    }

    try {
        await axios.patch(`/orders/${currentOrderId.value}`, {
            items: [
                {
                    product_id: null,
                    quantity: 1,
                    notes: 'Món mua thêm theo đề xuất AI',
                },
            ],
        });
        toast.success(
            `Đã thêm món đề xuất '${upsellData.value.recommended_item}' vào đơn hàng!`,
        );
        showUpsellModal.value = false;
    } catch {
        toast.info(
            `Vui lòng thêm thủ công món '${upsellData.value.recommended_item}' từ màn hình POS.`,
        );
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
            <!-- Date picker and settings -->
            <div class="flex flex-wrap items-center gap-2">
                <Button
                    v-if="isOwner"
                    variant="outline"
                    size="sm"
                    @click="toggleAutoPay"
                    class="flex h-10 cursor-pointer items-center gap-2 rounded-xl border px-4 transition-all"
                    :class="
                        props.autoPayEnabled
                            ? 'border-emerald-500 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 hover:text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/20 dark:text-emerald-400'
                            : 'border-slate-200 bg-white text-slate-500 hover:border-slate-300 dark:border-slate-800 dark:bg-slate-950'
                    "
                >
                    <span class="relative flex h-2 w-2">
                        <span
                            :class="[
                                'absolute inline-flex h-full w-full animate-ping rounded-full opacity-75',
                                props.autoPayEnabled
                                    ? 'bg-emerald-400'
                                    : 'bg-slate-400',
                            ]"
                        ></span>
                        <span
                            :class="[
                                'relative inline-flex h-2 w-2 rounded-full',
                                props.autoPayEnabled
                                    ? 'bg-emerald-500'
                                    : 'bg-slate-500',
                            ]"
                        ></span>
                    </span>
                    <span
                        >Tự động thanh toán ca cuối:
                        <strong class="uppercase">{{
                            props.autoPayEnabled ? 'Bật' : 'Tắt'
                        }}</strong></span
                    >
                </Button>

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

        <!-- Primary Tabs Navigation -->
        <div
            class="mt-2 flex shrink-0 gap-6 border-b border-slate-200 dark:border-slate-800"
        >
            <button
                @click="selectTab('official')"
                :class="[
                    'cursor-pointer border-b-2 pb-3 text-sm font-semibold transition-all',
                    activeTab === 'official'
                        ? 'border-violet-500 text-violet-600 dark:text-violet-400'
                        : 'border-transparent text-slate-500 hover:text-slate-700',
                ]"
            >
                Đơn hàng chính thức
            </button>
            <button
                @click="selectTab('pending_qr')"
                :class="[
                    'flex cursor-pointer items-center gap-1.5 border-b-2 pb-3 text-sm font-semibold transition-all',
                    activeTab === 'pending_qr'
                        ? 'border-violet-500 text-violet-600 dark:text-violet-400'
                        : 'border-transparent text-slate-500 hover:text-slate-700',
                ]"
            >
                <span>Yêu cầu QR đang chờ</span>
                <span
                    v-if="pendingQrOrders.length"
                    class="animate-pulse rounded-full bg-red-500 px-1.5 py-0.5 text-[10px] font-bold text-white"
                >
                    {{ pendingQrOrders.length }}
                </span>
            </button>
            <button
                v-if="isOwner || roles.includes('manager')"
                @click="selectTab('rejected_qr')"
                :class="[
                    'cursor-pointer border-b-2 pb-3 text-sm font-semibold transition-all',
                    activeTab === 'rejected_qr'
                        ? 'border-violet-500 text-violet-600 dark:text-violet-400'
                        : 'border-transparent text-slate-500 hover:text-slate-700',
                ]"
            >
                Nhật ký hủy QR hôm nay
            </button>
        </div>

        <!-- TAB 1: Official Orders -->
        <div v-if="activeTab === 'official'" class="flex flex-col space-y-4">
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
                        <span
                            class="ml-1 text-sm font-normal text-muted-foreground"
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
                            class="flex items-center gap-4 px-5 py-3.5 transition-colors hover:bg-slate-50/50 dark:hover:bg-slate-900/30"
                        >
                            <!-- Order info -->
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        class="font-mono text-sm font-bold text-slate-800 dark:text-slate-200"
                                        >{{ o.order_number }}</span
                                    >
                                    <span
                                        class="rounded-md border bg-slate-50 px-1.5 py-0.5 text-[9px] font-semibold text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400"
                                    >
                                        {{
                                            channelLabel[o.channel] ?? o.channel
                                        }}
                                    </span>
                                    <span
                                        v-if="o.table_name"
                                        class="text-[10px] text-slate-400"
                                    >
                                        {{ o.table_name
                                        }}{{
                                            o.area_name
                                                ? ` · ${o.area_name}`
                                                : ''
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
                                    :class="
                                        paymentConfig[o.payment_status]?.color
                                    "
                                >
                                    {{ paymentConfig[o.payment_status]?.label }}
                                </span>
                            </div>

                            <!-- Status + next action -->
                            <div class="flex shrink-0 items-center gap-2">
                                <span
                                    class="rounded-full px-2 py-1 text-[10px] font-semibold"
                                    :class="statusConfig[o.status]?.bg"
                                >
                                    {{ statusConfig[o.status]?.label }}
                                </span>
                                <button
                                    v-if="
                                        nextStatus[o.status] && canUpdateStatus
                                    "
                                    :disabled="statusUpdating[o.id]"
                                    @click="
                                        updateOrderStatus(
                                            o,
                                            nextStatus[o.status]!,
                                        )
                                    "
                                    class="flex h-7 cursor-pointer items-center gap-1 rounded-lg bg-violet-600 px-2.5 text-[10px] font-semibold text-white transition-colors hover:bg-violet-700 disabled:opacity-50"
                                >
                                    <RefreshCw
                                        v-if="statusUpdating[o.id]"
                                        class="size-3 animate-spin"
                                    />
                                    {{
                                        nextStatus[o.status] === 'confirmed'
                                            ? 'Xác nhận'
                                            : nextStatus[o.status] ===
                                                'preparing'
                                              ? 'Chuyển bếp'
                                              : nextStatus[o.status] ===
                                                  'completed'
                                                ? 'Hoàn thành'
                                                : ''
                                    }}
                                </button>

                                <!-- Tách bill / Chuyển bàn (đơn chưa thanh toán) -->
                                <template
                                    v-if="canModify(o) && canUpdateStatus"
                                >
                                    <button
                                        v-if="o.items_count > 1"
                                        @click="openSplit(o)"
                                        class="h-7 cursor-pointer rounded-lg border border-border px-2 text-[10px] font-semibold transition-colors hover:bg-accent"
                                        title="Tách bill để khách trả riêng"
                                    >
                                        Tách bill
                                    </button>
                                    <button
                                        v-if="o.table_name"
                                        @click="openMove(o)"
                                        class="h-7 cursor-pointer rounded-lg border border-border px-2 text-[10px] font-semibold transition-colors hover:bg-accent"
                                        title="Chuyển sang bàn khác"
                                    >
                                        Chuyển bàn
                                    </button>
                                    <button
                                        @click="openMerge(o)"
                                        class="h-7 cursor-pointer rounded-lg border border-border px-2 text-[10px] font-semibold transition-colors hover:bg-accent"
                                        title="Gộp bill này vào một đơn khác"
                                    >
                                        Gộp bill
                                    </button>
                                </template>

                                <!-- Tải hóa đơn điện tử (đơn đã thanh toán) -->
                                <a
                                    v-if="o.payment_status === 'paid'"
                                    :href="`/orders/${o.id}/e-invoice.xml`"
                                    class="inline-flex h-7 items-center gap-1 rounded-lg border border-amber-200 px-2 text-[10px] font-semibold text-amber-700 transition-colors hover:bg-amber-50 dark:border-amber-900/50 dark:text-amber-400 dark:hover:bg-amber-950/30"
                                    title="Tải XML hóa đơn điện tử (TT78) — BẢN NHÁP, CHƯA KÝ SỐ. Cần nộp lên cổng nhà cung cấp (MISA/VNPT) để ký & phát hành hợp lệ."
                                >
                                    HĐĐT
                                    <span class="rounded bg-amber-100 px-1 text-[8px] font-bold tracking-wide text-amber-700 uppercase dark:bg-amber-950/60 dark:text-amber-400">nháp</span>
                                </a>
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
                        <p class="text-sm font-semibold">
                            Không có đơn hàng nào
                        </p>
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
        </div>

        <!-- TAB 2: Pending QR Orders -->
        <div v-else-if="activeTab === 'pending_qr'" class="space-y-4">
            <Card class="shadow-sm">
                <CardHeader
                    class="flex flex-row items-center justify-between border-b pb-3"
                >
                    <CardTitle class="text-base">
                        Yêu cầu đặt món QR đang chờ xác thực
                        <span
                            class="ml-1 text-sm font-normal text-muted-foreground"
                            >({{ pendingQrOrders.length }} yêu cầu)</span
                        >
                    </CardTitle>
                    <Button
                        variant="outline"
                        size="sm"
                        @click="fetchPendingQr"
                        class="h-8"
                    >
                        <RefreshCw
                            :class="[
                                'size-3.5',
                                isLoadingQr ? 'animate-spin' : '',
                            ]"
                        />
                    </Button>
                </CardHeader>
                <CardContent class="p-0">
                    <div
                        v-if="pendingQrOrders.length"
                        class="divide-y divide-slate-100 dark:divide-slate-800"
                    >
                        <div
                            v-for="o in pendingQrOrders"
                            :key="o.id"
                            class="flex flex-col justify-between gap-4 px-6 py-5 transition-colors hover:bg-slate-50/50 md:flex-row md:items-center dark:hover:bg-slate-900/30"
                        >
                            <div class="flex-1 space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        class="text-sm font-bold text-slate-800 dark:text-slate-200"
                                        >{{ o.table_name }}</span
                                    >
                                    <span
                                        class="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-400 dark:bg-slate-900"
                                        >{{ o.area_name }}</span
                                    >
                                    <span
                                        v-if="o.status === 'escalated'"
                                        class="dark:bg-red-955/40 animate-pulse rounded border border-red-200 bg-red-100 px-2 py-0.5 text-[10px] font-bold text-red-500 dark:border-red-900/30"
                                    >
                                        🚨 QUÁ HẠN ({{ o.minutes_elapsed }}m)
                                    </span>
                                    <span
                                        v-else
                                        class="dark:bg-amber-955/40 rounded border border-amber-200 bg-amber-100 px-2 py-0.5 text-[10px] text-amber-500 dark:border-amber-900/30"
                                    >
                                        Chờ duyệt ({{ o.created_at }})
                                    </span>
                                </div>

                                <div
                                    class="space-y-1 rounded-xl border border-slate-100 bg-slate-50 p-3 text-xs dark:border-slate-800 dark:bg-slate-950"
                                >
                                    <div
                                        v-for="(item, idx) in o.items"
                                        :key="idx"
                                        class="text-slate-650 dark:text-slate-350 flex items-center justify-between"
                                    >
                                        <span
                                            >{{ item.quantity }}x
                                            {{ item.name }}</span
                                        >
                                        <span
                                            class="text-[10px] text-slate-400 italic"
                                            v-if="item.notes"
                                            >Ghi chú: {{ item.notes }}</span
                                        >
                                        <span
                                            class="font-mono text-slate-500"
                                            >{{
                                                formatCurrency(item.line_total)
                                            }}</span
                                        >
                                    </div>
                                    <div
                                        class="mt-2 flex justify-between border-t border-slate-200 pt-2.5 font-bold text-slate-800 dark:border-slate-900 dark:text-slate-200"
                                    >
                                        <span>Tổng thanh toán:</span>
                                        <span
                                            class="font-mono text-amber-500"
                                            >{{
                                                formatCurrency(o.total_amount)
                                            }}</span
                                        >
                                    </div>
                                </div>
                            </div>

                            <div
                                class="flex items-center gap-3 self-end md:self-center"
                            >
                                <Button
                                    variant="outline"
                                    size="sm"
                                    :disabled="
                                        confirmingOrders[o.id] || isCancellingQr
                                    "
                                    @click="openCancelModal(o.id)"
                                    class="text-red-655 flex h-9 cursor-pointer items-center gap-1.5 rounded-xl border-red-500/30 hover:bg-red-50 hover:text-red-700 disabled:opacity-50 dark:text-red-400 dark:hover:bg-red-950/20"
                                >
                                    <Trash2 class="size-4" /> Từ chối
                                </Button>
                                <Button
                                    size="sm"
                                    :disabled="
                                        confirmingOrders[o.id] || isCancellingQr
                                    "
                                    @click="confirmTempOrder(o.id)"
                                    class="flex h-9 cursor-pointer items-center gap-1.5 rounded-xl bg-amber-500 font-bold text-slate-950 shadow-md shadow-amber-500/10 hover:bg-amber-400 disabled:opacity-50"
                                >
                                    <Loader2
                                        v-if="confirmingOrders[o.id]"
                                        class="size-4 animate-spin"
                                    />
                                    <Check v-else class="size-4" />
                                    {{
                                        confirmingOrders[o.id]
                                            ? 'Đang xác nhận...'
                                            : 'Xác nhận & Đẩy Bếp'
                                    }}
                                </Button>
                            </div>
                        </div>
                    </div>

                    <div
                        v-else
                        class="flex flex-col items-center justify-center py-20 text-center text-muted-foreground"
                    >
                        <Utensils
                            class="mb-4 size-10 text-muted-foreground/30"
                        />
                        <p class="text-sm font-semibold">
                            Không có yêu cầu QR nào đang chờ
                        </p>
                        <p class="text-xs">
                            Khi khách hàng quét mã gọi món, các yêu cầu sẽ xuất
                            hiện tại đây.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- TAB 3: Rejected QR Logs -->
        <div v-else-if="activeTab === 'rejected_qr'" class="space-y-4">
            <Card class="shadow-sm">
                <CardHeader
                    class="flex flex-row items-center justify-between border-b pb-3"
                >
                    <CardTitle class="text-base">
                        Nhật ký hủy yêu cầu gọi món QR trong ngày
                        <span
                            class="ml-1 text-sm font-normal text-muted-foreground"
                            >({{ rejectedQrLogs.length }} đơn bị hủy)</span
                        >
                    </CardTitle>
                    <Button
                        variant="outline"
                        size="sm"
                        @click="fetchRejectedQr"
                        class="h-8"
                    >
                        <RefreshCw
                            :class="[
                                'size-3.5',
                                isLoadingQr ? 'animate-spin' : '',
                            ]"
                        />
                    </Button>
                </CardHeader>
                <CardContent class="p-0">
                    <div
                        v-if="rejectedQrLogs.length"
                        class="divide-y divide-slate-100 dark:divide-slate-800"
                    >
                        <div
                            v-for="l in rejectedQrLogs"
                            :key="l.id"
                            class="space-y-3 p-5 transition-colors hover:bg-slate-50/50 dark:hover:bg-slate-900/30"
                        >
                            <div
                                class="flex flex-wrap items-start justify-between gap-2"
                            >
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="text-sm font-bold text-slate-800 dark:text-slate-200"
                                            >{{ l.table_name }}</span
                                        >
                                        <span
                                            class="text-xxs rounded bg-slate-100 px-1.5 py-0.5 text-slate-400 dark:bg-slate-900"
                                            >{{ l.area_name }}</span
                                        >
                                    </div>
                                    <p class="text-xxs text-slate-500">
                                        Thời gian hủy: {{ l.cancelled_at }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <span
                                        class="font-mono text-xs font-bold text-slate-700 dark:text-slate-300"
                                        >Hủy trị giá:
                                        {{
                                            formatCurrency(l.total_amount)
                                        }}</span
                                    >
                                </div>
                            </div>

                            <div
                                class="text-xxs rounded-lg border border-slate-100 bg-slate-50 p-2.5 text-slate-500 dark:border-slate-800 dark:bg-slate-950/50"
                            >
                                Món bị hủy:
                                {{
                                    l.items
                                        .map((i) => `${i.quantity}x ${i.name}`)
                                        .join(', ')
                                }}
                            </div>

                            <div
                                class="flex items-center gap-2 rounded-xl border border-red-500/10 bg-red-500/5 p-3 text-xs"
                            >
                                <AlertCircle
                                    class="size-4 shrink-0 text-red-500"
                                />
                                <div>
                                    <span class="font-extrabold text-red-500"
                                        >Nhân viên hủy:
                                        {{ l.cancelled_by_name }}</span
                                    >
                                    <span class="ml-1.5 text-slate-400"
                                        >• Lý do: "{{
                                            l.cancellation_reason
                                        }}"</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-else
                        class="flex flex-col items-center justify-center py-20 text-center text-muted-foreground"
                    >
                        <Check class="mb-4 size-10 text-muted-foreground/30" />
                        <p class="text-sm font-semibold">
                            Chưa có nhật ký hủy nào hôm nay
                        </p>
                        <p class="text-xs">
                            Các yêu cầu đặt món QR bị nhân viên ấn từ chối trong
                            ngày sẽ được ghi nhận tại đây.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- ── AI Upsell proposal modal ────────────────────────────────────── -->
        <div
            v-if="showUpsellModal && upsellData"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-4 backdrop-blur-sm"
        >
            <div
                class="relative w-full max-w-sm overflow-hidden rounded-3xl border border-slate-800 bg-gradient-to-br from-slate-900 to-slate-950 text-slate-200 shadow-2xl"
            >
                <header
                    class="flex items-center justify-between border-b border-amber-500/10 bg-amber-500/10 p-4"
                >
                    <div class="flex items-center gap-2">
                        <Bot class="size-5 animate-pulse text-amber-400" />
                        <h3
                            class="flex items-center gap-1 text-xs font-bold tracking-wider text-amber-400 uppercase"
                        >
                            Smart Upselling Engine
                            <Sparkles class="size-3.5 text-amber-400" />
                        </h3>
                    </div>
                    <button
                        @click="showUpsellModal = false"
                        class="bg-slate-855 cursor-pointer rounded-lg p-1 text-slate-400 hover:bg-slate-800"
                    >
                        <X class="size-4" />
                    </button>
                </header>

                <div class="space-y-4 p-5">
                    <div
                        class="space-y-2 rounded-2xl border border-slate-800 bg-slate-950 p-4"
                    >
                        <p
                            class="text-slate-350 text-xs leading-relaxed font-medium"
                        >
                            {{ upsellData.suggestion }}
                        </p>
                        <div
                            class="flex items-center justify-between border-t border-slate-900 pt-2 text-[10px] text-slate-500"
                        >
                            <span
                                >Độ tin cậy:
                                {{
                                    Math.round(upsellData.confidence * 100)
                                }}%</span
                            >
                            <span>Chỉ số Lift: {{ upsellData.lift }}x</span>
                        </div>
                    </div>

                    <div
                        v-if="upsellData.recommended_item"
                        class="flex items-center justify-between rounded-xl border border-amber-500/10 bg-amber-500/5 p-3 text-xs"
                    >
                        <span class="font-bold text-slate-200"
                            >Gợi ý: Mời dùng
                            {{ upsellData.recommended_item }}</span
                        >
                        <span
                            class="text-xxs rounded bg-amber-500/10 px-1.5 py-0.5 font-bold text-amber-400"
                            >-10% combo</span
                        >
                    </div>
                </div>

                <footer
                    class="flex gap-2 border-t border-slate-800 bg-slate-950/20 p-4"
                >
                    <button
                        @click="showUpsellModal = false"
                        class="text-slate-450 hover:bg-slate-850 h-10 flex-1 cursor-pointer rounded-xl border border-slate-800 text-xs font-bold"
                    >
                        Bỏ qua
                    </button>
                    <button
                        @click="addUpsellItem"
                        class="flex h-10 flex-1 cursor-pointer items-center justify-center gap-1 rounded-xl bg-amber-500 text-xs font-extrabold text-slate-950 shadow-lg shadow-amber-500/10 hover:bg-amber-400"
                    >
                        <Check class="size-4" /> Thêm vào đơn
                    </button>
                </footer>
            </div>
        </div>

        <!-- ── Cancellation Reason Modal ────────────────────────────────────── -->
        <div
            v-if="showCancelModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-4 backdrop-blur-sm"
        >
            <div
                class="relative w-full max-w-xs overflow-hidden rounded-3xl border border-slate-800 bg-slate-900 text-slate-200 shadow-2xl"
            >
                <header
                    class="flex items-center justify-between border-b border-slate-800 p-4"
                >
                    <h3 class="text-xs font-bold text-slate-200">
                        Lý do từ chối yêu cầu QR
                    </h3>
                    <button
                        @click="showCancelModal = false"
                        class="bg-slate-850 hover:bg-slate-850 cursor-pointer rounded-lg p-1 text-slate-400"
                    >
                        <X class="size-4" />
                    </button>
                </header>

                <div class="space-y-3 p-4">
                    <textarea
                        v-model="cancelReason"
                        rows="3"
                        placeholder="Nhập lý do từ chối..."
                        class="w-full resize-none rounded-xl border border-slate-800 bg-slate-950 p-2.5 text-xs text-slate-300 focus:border-red-500 focus:outline-none"
                    ></textarea>
                </div>

                <footer
                    class="flex gap-2 border-t border-slate-800 bg-slate-950/20 p-4"
                >
                    <button
                        @click="showCancelModal = false"
                        class="border-slate-850 hover:bg-slate-850 h-9 flex-1 cursor-pointer rounded-lg border text-xs font-bold text-slate-400"
                    >
                        Quay lại
                    </button>
                    <button
                        @click="submitCancel"
                        :disabled="!cancelReason.trim() || isCancellingQr"
                        class="flex h-9 flex-1 cursor-pointer items-center justify-center gap-1.5 rounded-lg bg-red-500 text-xs font-bold text-slate-950 hover:bg-red-400 disabled:bg-slate-800 disabled:text-slate-600"
                    >
                        <Loader2
                            v-if="isCancellingQr"
                            class="size-3.5 animate-spin"
                        />
                        {{
                            isCancellingQr
                                ? 'Đang từ chối...'
                                : 'Từ chối đặt món'
                        }}
                    </button>
                </footer>
            </div>
        </div>
    </div>

    <!-- ─── Dialog Tách bill ─── -->
    <Dialog
        :open="actionType === 'split'"
        @update:open="
            (v: boolean) => {
                if (!v) actionType = null;
            }
        "
    >
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="text-base"
                    >Tách bill — {{ actionOrder?.order_number }}</DialogTitle
                >
                <DialogDescription class="text-xs"
                    >Chọn các món chuyển sang bill mới (bill gốc phải còn ít
                    nhất 1 món).</DialogDescription
                >
            </DialogHeader>
            <div
                v-if="actionLoading"
                class="py-6 text-center text-xs text-muted-foreground"
            >
                Đang tải danh sách món...
            </div>
            <div v-else class="max-h-72 space-y-1.5 overflow-y-auto">
                <label
                    v-for="item in billItems"
                    :key="item.id"
                    class="flex cursor-pointer items-center gap-3 rounded-xl border px-3 py-2.5 transition-colors hover:bg-accent"
                    :class="
                        selectedItemIds.includes(item.id)
                            ? 'border-primary bg-primary/5'
                            : ''
                    "
                >
                    <Checkbox
                        :model-value="selectedItemIds.includes(item.id)"
                        @update:model-value="toggleSplitItem(item.id)"
                    />
                    <span class="flex-1 truncate text-xs font-semibold"
                        >x{{ Math.round(item.quantity) }} {{ item.name }}</span
                    >
                    <span class="text-xs text-muted-foreground tabular-nums"
                        >{{ item.line_total.toLocaleString('vi-VN') }}đ</span
                    >
                </label>
            </div>
            <DialogFooter class="items-center gap-3 sm:justify-between">
                <span class="text-xs font-bold"
                    >Bill mới: {{ splitTotal.toLocaleString('vi-VN') }}đ</span
                >
                <div class="flex gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        class="text-xs"
                        @click="actionType = null"
                        >Hủy</Button
                    >
                    <Button
                        size="sm"
                        class="text-xs"
                        :disabled="
                            actionLoading ||
                            !selectedItemIds.length ||
                            selectedItemIds.length >= billItems.length
                        "
                        @click="submitAction"
                    >
                        Tách {{ selectedItemIds.length }} món
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- ─── Dialog Chuyển bàn ─── -->
    <Dialog
        :open="actionType === 'move'"
        @update:open="
            (v: boolean) => {
                if (!v) actionType = null;
            }
        "
    >
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="text-base"
                    >Chuyển bàn — {{ actionOrder?.order_number }}</DialogTitle
                >
                <DialogDescription class="text-xs"
                    >Đang ở: {{ actionOrder?.table_name }}. Chọn bàn trống muốn
                    chuyển đến.</DialogDescription
                >
            </DialogHeader>
            <div
                v-if="actionLoading"
                class="py-6 text-center text-xs text-muted-foreground"
            >
                Đang tải bàn trống...
            </div>
            <p
                v-else-if="!freeTables.length"
                class="py-6 text-center text-xs text-muted-foreground"
            >
                Không còn bàn trống nào.
            </p>
            <div v-else class="grid max-h-72 grid-cols-3 gap-2 overflow-y-auto">
                <button
                    v-for="t in freeTables"
                    :key="t.id"
                    class="cursor-pointer rounded-xl border px-2 py-3 text-center transition-all hover:border-primary/50"
                    :class="
                        selectedTableId === t.id
                            ? 'border-primary bg-primary/10 font-bold'
                            : ''
                    "
                    @click="selectedTableId = t.id"
                >
                    <span class="block truncate text-xs font-semibold">{{
                        t.name
                    }}</span>
                    <span class="mt-0.5 block text-[10px] text-muted-foreground"
                        >{{ t.capacity }} chỗ</span
                    >
                </button>
            </div>
            <DialogFooter>
                <Button
                    variant="outline"
                    size="sm"
                    class="text-xs"
                    @click="actionType = null"
                    >Hủy</Button
                >
                <Button
                    size="sm"
                    class="text-xs"
                    :disabled="actionLoading || !selectedTableId"
                    @click="submitAction"
                    >Chuyển bàn</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- ─── Dialog Gộp bill ─── -->
    <Dialog
        :open="actionType === 'merge'"
        @update:open="
            (v: boolean) => {
                if (!v) actionType = null;
            }
        "
    >
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="text-base"
                    >Gộp bill — {{ actionOrder?.order_number }}</DialogTitle
                >
                <DialogDescription class="text-xs"
                    >Chọn đơn đích để gộp toàn bộ món của
                    {{ actionOrder?.order_number }} sang. Đơn nguồn sẽ được đóng
                    lại.</DialogDescription
                >
            </DialogHeader>
            <p
                v-if="!mergeCandidates.length"
                class="py-6 text-center text-xs text-muted-foreground"
            >
                Không có đơn nào khác để gộp.
            </p>
            <div v-else class="flex max-h-72 flex-col gap-2 overflow-y-auto">
                <button
                    v-for="t in mergeCandidates"
                    :key="t.id"
                    class="cursor-pointer rounded-xl border px-3 py-2 text-left transition-all hover:border-primary/50"
                    :class="
                        selectedTargetOrderId === t.id
                            ? 'border-primary bg-primary/10 font-bold'
                            : ''
                    "
                    @click="selectedTargetOrderId = t.id"
                >
                    <span class="block truncate text-xs font-semibold"
                        >{{ t.order_number }}
                        <span
                            v-if="t.table_name"
                            class="font-normal text-muted-foreground"
                            >· {{ t.table_name }}</span
                        ></span
                    >
                    <span class="mt-0.5 block text-[10px] text-muted-foreground"
                        >{{ t.items_count }} món ·
                        {{ formatCurrency(t.total_amount) }}</span
                    >
                </button>
            </div>
            <DialogFooter>
                <Button
                    variant="outline"
                    size="sm"
                    class="text-xs"
                    @click="actionType = null"
                    >Hủy</Button
                >
                <Button
                    size="sm"
                    class="text-xs"
                    :disabled="actionLoading || !selectedTargetOrderId"
                    @click="submitAction"
                    >Gộp bill</Button
                >
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
