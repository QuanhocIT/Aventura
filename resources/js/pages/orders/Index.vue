<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import {
    ShoppingCart,
    CheckCircle2,
    Clock,
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
    RotateCcw,
    Activity,
} from 'lucide-vue-next';
import { computed, ref, onMounted, onUnmounted, watch } from 'vue';
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
import { apiErrorMessage } from '@/composables/useApiCall';
import { confirmDialog } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type OrderItemDetail = {
    id: number;
    name: string;
    quantity: number;
    unit_price: number;
    line_total: number;
    notes: string | null;
    status: string | null;
    sent_to_kitchen_at?: string | null;
    started_preparing_at?: string | null;
    prepared_at?: string | null;
    prepared_at_formatted?: string | null;
    prepared_by_name?: string | null;
    served_at?: string | null;
    served_at_formatted?: string | null;
    served_by_name?: string | null;
    cancelled_at?: string | null;
    cancelled_by_name?: string | null;
    cancellation_reason?: string | null;
};

type DeliveryInfo = {
    customer_name: string;
    phone: string;
    address: string;
    fee: number;
    cod: number;
    status: string;
    notes: string | null;
};

type Order = {
    id: number;
    order_number: string;
    branch_id: number | null;
    branch_name: string;
    status: string;
    payment_status: string;
    fulfillment_status: 'pending' | 'preparing' | 'ready' | 'served';
    channel: string;
    table_name: string | null;
    area_name: string | null;
    total_amount: number;
    refund_amount?: number | null;
    refund_reason?: string | null;
    refunded_at?: string | null;
    refunded_by_name?: string | null;
    items_count: number;
    items_prepared_count?: number;
    items_served_count?: number;
    items_in_progress_count?: number;
    items_ready_count?: number;
    created_at: string;
    created_at_full?: string;
    created_at_formatted?: string;
    completed_at: string | null;
    completed_at_full?: string | null;
    completed_at_formatted?: string | null;
    note?: string | null;
    items?: OrderItemDetail[];
    delivery?: DeliveryInfo | null;
};

type Summary = {
    total: number;
    pending: number;
    preparing: number;
    ready?: number;
    completed: number;
    cancelled: number;
    revenue: number;
    refunded: number;
    refunded_amount: number;
};

type CancelledQrOrder = Order & {
    cancelled_at: string;
    cancelled_by_name: string;
    cancellation_reason: string | null;
};

const props = defineProps<{
    orders: Order[];
    cancelledQrOrders: CancelledQrOrder[];
    summary: Summary;
    filters: { status: string; date: string };
    autoPayEnabled: boolean;
}>();

const officialView = ref<'orders' | 'ready'>('orders');
const isOfficialReady = computed(() => officialView.value === 'ready');

// Khi mở tab "Chờ phục vụ", backend trả đúng tập đơn ready. Không lọc lại
// từ danh sách "Đang chế biến" vì hai trạng thái đã được tách ở server.
const readyOrders = computed(() => (isOfficialReady.value ? props.orders : []));

const officialOrders = computed(() =>
    isOfficialReady.value ? readyOrders.value : props.orders,
);

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
const datePickerRef = ref<HTMLInputElement | null>(null);

const showCancelledQrOrders = computed(
    () =>
        !isOfficialReady.value &&
        ['all', 'cancelled'].includes(activeStatus.value) &&
        props.cancelledQrOrders.length > 0,
);

const openDatePicker = () => {
    if (datePickerRef.value) {
        if (typeof datePickerRef.value.showPicker === 'function') {
            datePickerRef.value.showPicker();
        } else {
            datePickerRef.value.focus();
            datePickerRef.value.click();
        }
    }
};

const applyFilters = () => {
    officialView.value = 'orders';
    router.get(
        '/orders',
        { status: activeStatus.value, date: dateInput.value },
        { preserveScroll: true },
    );
};

const setStatus = (s: string) => {
    officialView.value = 'orders';
    activeStatus.value = s;
    router.get(
        '/orders',
        { status: s, date: dateInput.value },
        { preserveScroll: true },
    );
};

const selectOfficialView = (view: 'orders' | 'ready') => {
    officialView.value = view;

    if (view === 'ready' && activeStatus.value !== 'ready') {
        activeStatus.value = 'ready';
        router.get(
            '/orders',
            { status: 'ready', date: dateInput.value },
            { preserveScroll: true, preserveState: true },
        );
    }
};

const statusFilters = [
    { key: 'all', label: 'Tất cả' },
    { key: 'confirmed', label: 'Đã xác nhận' },
    { key: 'waiting_preparing', label: 'Chờ chế biến' },
    { key: 'preparing', label: 'Đang chế biến' },
    { key: 'ready', label: 'Chờ phục vụ' },
    { key: 'completed', label: 'Hoàn thành' },
    { key: 'refunded', label: 'Hoàn tiền' },
    { key: 'cancelled', label: 'Đã hủy' },
] as const;

const statusUpdating = ref<Record<number, boolean>>({});

const fulfillmentConfig: Record<
    Order['fulfillment_status'],
    { label: string; class: string; icon: any }
> = {
    pending: {
        label: 'Chờ chế biến',
        class: 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900/40 dark:bg-amber-950/40 dark:text-amber-300',
        icon: Clock,
    },
    preparing: {
        label: 'Đang chế biến',
        class: 'border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-900/40 dark:bg-violet-950/40 dark:text-violet-300',
        icon: ChefHat,
    },
    ready: {
        label: 'Chờ phục vụ',
        class: 'border-sky-200 bg-sky-50 text-sky-700 dark:border-sky-900/40 dark:bg-sky-950/40 dark:text-sky-300',
        icon: CheckCircle2,
    },
    served: {
        label: 'Đã phục vụ',
        class: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/40 dark:text-emerald-300',
        icon: CheckCircle2,
    },
};

// ─── Phân trang đơn hàng (10 đơn / trang) ───
const currentPage = ref(1);
const itemsPerPage = 10;

const totalPages = computed(() =>
    Math.ceil(officialOrders.value.length / itemsPerPage),
);

const paginatedOrders = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;

    return officialOrders.value.slice(start, start + itemsPerPage);
});

const visiblePages = computed(() => {
    const pages = [];
    const maxVisible = 5;
    let start = Math.max(1, currentPage.value - Math.floor(maxVisible / 2));
    const end = Math.min(totalPages.value, start + maxVisible - 1);

    if (end - start + 1 < maxVisible) {
        start = Math.max(1, end - maxVisible + 1);
    }

    for (let i = start; i <= end; i++) {
        pages.push(i);
    }

    return pages;
});

watch([activeStatus, dateInput, () => props.orders, officialView], () => {
    currentPage.value = 1;
});

// ─── Xem chi tiết đơn hàng (Modal) ───
const selectedOrder = ref<Order | null>(null);
const showDetailModal = ref(false);

const openOrderDetails = (order: Order) => {
    selectedOrder.value = order;
    showDetailModal.value = true;
};

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

function convertToTakeaway(o: Order) {
    if (actionLoading.value) {
        return;
    }

    actionLoading.value = true;
    router.post(
        `/orders/${o.id}/convert-to-takeaway`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => toast.success('Đã chuyển đơn sang mang về.'),
            onError: (errs: any) =>
                toast.error(
                    String(Object.values(errs)[0] ?? 'Không thể chuyển đơn.'),
                ),
            onFinish: () => {
                actionLoading.value = false;
            },
        },
    );
}

// Các đơn khác có thể nhận gộp (chưa thanh toán, đang hoạt động, khác đơn nguồn).
const mergeCandidates = computed(() =>
    props.orders.filter((o) => canModify(o) && o.id !== actionOrder.value?.id),
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

// ─── Xử lý Hoàn tiền (Refund) ───
const showRefundModal = ref(false);
const refundOrderTarget = ref<Order | null>(null);
const refundType = ref<'full' | 'partial'>('full');
const refundCategory = ref<'compensation' | 'mistake' | ''>('');
const refundItemQuantities = ref<Record<number, number>>({});
const refundAmount = ref<number>(0);
const refundReason = ref<string>('');
const isSubmittingRefund = ref(false);

const openRefundModal = (o: Order) => {
    refundOrderTarget.value = o;
    refundType.value = 'full';
    refundCategory.value = '';
    refundReason.value = '';
    refundItemQuantities.value = {};

    if (o.items) {
        o.items.forEach((item) => {
            refundItemQuantities.value[item.id] = 0;
        });
    }

    refundAmount.value = Math.max(0, o.total_amount - (o.refund_amount ?? 0));
    showRefundModal.value = true;
};

const remainingRefundAmount = computed(() =>
    Math.max(
        0,
        (refundOrderTarget.value?.total_amount ?? 0) -
            (refundOrderTarget.value?.refund_amount ?? 0),
    ),
);

const computedPartialRefundAmount = computed(() => {
    if (!refundOrderTarget.value?.items) {
        return 0;
    }

    return refundOrderTarget.value.items.reduce((sum, item) => {
        const qty = refundItemQuantities.value[item.id] || 0;
        const unitPrice =
            item.unit_price ??
            (item.quantity > 0 ? item.line_total / item.quantity : 0);

        return sum + unitPrice * qty;
    }, 0);
});

const effectiveRefundAmount = computed(() => {
    if (refundType.value === 'full') {
        return remainingRefundAmount.value;
    }

    return computedPartialRefundAmount.value;
});

const submitRefund = () => {
    if (
        !refundOrderTarget.value ||
        !refundCategory.value ||
        refundReason.value.trim().length < 5 ||
        isSubmittingRefund.value
    ) {
        return;
    }

    const selectedItems: Array<{ order_item_id: number; quantity: number }> =
        [];

    if (refundType.value === 'partial' && refundOrderTarget.value.items) {
        for (const item of refundOrderTarget.value.items) {
            const qty = refundItemQuantities.value[item.id] || 0;

            if (qty > 0) {
                selectedItems.push({
                    order_item_id: item.id,
                    quantity: qty,
                });
            }
        }
    }

    isSubmittingRefund.value = true;
    router.post(
        `/orders/${refundOrderTarget.value.id}/refund`,
        {
            refund_type: refundType.value,
            refund_category: refundCategory.value,
            refund_amount: effectiveRefundAmount.value,
            reason: refundReason.value.trim(),
            items: selectedItems,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                showRefundModal.value = false;
                toast.success(
                    isOwner.value
                        ? 'Đã xử lý hoàn tiền thành công!'
                        : 'Đã gửi yêu cầu hoàn tiền đến chủ doanh nghiệp.',
                );
            },
            onError: (errs: any) => {
                toast.error(
                    String(Object.values(errs)[0] ?? 'Có lỗi khi hoàn tiền.'),
                );
            },
            onFinish: () => {
                isSubmittingRefund.value = false;
            },
        },
    );
};

// ── Hủy món / báo bếp ─────────────────────────────────────────────────────
const showCancelItemModal = ref(false);
const cancelItemTarget = ref<OrderItemDetail | null>(null);
const cancelItemReason = ref('Khách bận việc đột xuất');
const cancelItemCustomReason = ref('');
const isSubmittingItemCancel = ref(false);
const cancellationReasonOptions = [
    'Khách bận việc đột xuất',
    'Khách đổi món',
    'Khách không muốn nhận món',
    'Món hết nguyên liệu / không thể phục vụ',
];

const openCancelItemModal = (item: OrderItemDetail) => {
    cancelItemTarget.value = item;
    cancelItemReason.value = 'Khách bận việc đột xuất';
    cancelItemCustomReason.value = '';
    showCancelItemModal.value = true;
};

const effectiveCancelItemReason = computed(() =>
    cancelItemReason.value === '__custom__'
        ? cancelItemCustomReason.value
        : cancelItemReason.value,
);

const submitCancelItem = () => {
    if (
        !selectedOrder.value ||
        !cancelItemTarget.value ||
        effectiveCancelItemReason.value.trim().length < 3 ||
        isSubmittingItemCancel.value
    ) {
        return;
    }

    isSubmittingItemCancel.value = true;
    router.post(
        `/orders/${selectedOrder.value.id}/items/${cancelItemTarget.value.id}/cancel`,
        { reason: effectiveCancelItemReason.value.trim() },
        {
            preserveScroll: true,
            onSuccess: () => {
                showCancelItemModal.value = false;
                cancelItemTarget.value = null;
                toast.success('Đã gửi yêu cầu hủy món và báo xuống bếp.');
            },
            onError: (errs: any) => {
                toast.error(
                    String(Object.values(errs)[0] ?? 'Không thể hủy món.'),
                );
            },
            onFinish: () => {
                isSubmittingItemCancel.value = false;
            },
        },
    );
};

const splitTotal = computed(() =>
    billItems.value
        .filter((i) => selectedItemIds.value.includes(i.id))
        .reduce((sum, i) => sum + i.line_total, 0),
);

const paymentConfig: Record<string, { label: string; color: string }> = {
    unpaid: { label: 'Chưa TT', color: 'text-rose-600' },
    partial: { label: 'TT 1 phần', color: 'text-amber-600' },
    paid: { label: 'Đã TT', color: 'text-emerald-600' },
    partial_refund: { label: 'Hoàn một phần', color: 'text-amber-600' },
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
    } catch (e: unknown) {
        toast.error(
            apiErrorMessage(e, 'Không tải được danh sách đơn gọi món qua QR.'),
        );
    } finally {
        isLoadingQr.value = false;
    }
};

const isBatchApprovingQr = ref(false);

async function batchApproveQr() {
    if (
        !(await confirmDialog({
            title: 'Duyệt toàn bộ yêu cầu QR',
            description: `Duyệt ${pendingQrOrders.value.length} yêu cầu đặt món QR đang chờ? Các đơn sẽ được đẩy xuống bếp ngay.`,
        }))
    ) {
        return;
    }

    isBatchApprovingQr.value = true;

    try {
        const res = await axios.post('/orders/batch-approve-qr', {
            temporary_order_ids: pendingQrOrders.value.map((o) => o.id),
        });
        toast.success(res.data?.message ?? 'Đã duyệt các yêu cầu QR.');
        await fetchPendingQr();
        router.reload({ only: ['orders'] });
    } catch (e) {
        toast.error(apiErrorMessage(e, 'Không duyệt được các yêu cầu QR.'));
    } finally {
        isBatchApprovingQr.value = false;
    }
}

const fetchRejectedQr = async () => {
    isLoadingQr.value = true;

    try {
        const res = await axios.get('/api/temporary-orders/rejected-logs');
        rejectedQrLogs.value = res.data.rejected_logs;
    } catch (e: unknown) {
        toast.error(
            apiErrorMessage(e, 'Không tải được nhật ký đơn QR bị từ chối.'),
        );
    } finally {
        isLoadingQr.value = false;
    }
};

const selectTab = (tab: string) => {
    activeTab.value = tab;

    if (tab === 'official') {
        officialView.value = 'orders';
    }

    if (tab === 'pending_qr') {
        fetchPendingQr();
    } else if (tab === 'rejected_qr') {
        fetchRejectedQr();
    }
};

const confirmTempOrder = async (orderId: number) => {
    if (confirmingOrders.value[orderId]) {
        return;
    }

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
    }
};

const showAnalytics = ref(true);

const channelStats = computed(() => {
    const stats = { qr: 0, pos: 0, delivery: 0, other: 0 };
    const amounts = { qr: 0, pos: 0, delivery: 0, other: 0 };
    props.orders.forEach((o) => {
        const chan = o.channel?.toLowerCase() || 'other';

        if (chan in stats) {
            stats[chan as keyof typeof stats]++;
            amounts[chan as keyof typeof amounts] += o.total_amount;
        } else {
            stats.other++;
            amounts.other += o.total_amount;
        }
    });

    // Cancelled QR requests are temporary orders, so they are not included
    // in props.orders. Count them in the QR channel analytics as well.
    props.cancelledQrOrders.forEach((o) => {
        stats.qr++;
        amounts.qr += o.total_amount;
    });

    return { stats, amounts };
});

const aiEvaluation = computed(() => {
    if (!props.summary.total) {
        return {
            status: 'neutral',
            title: 'Chưa đủ dữ liệu phân tích',
            text: 'Hệ thống chưa ghi nhận đơn hàng nào trong ngày hôm nay để thực hiện đánh giá hiệu suất.',
            tips: [
                'Bật kênh QR Order để cho phép khách quét mã gọi món tự động.',
                'Đảm bảo thiết bị thu ngân (POS) đang trực tuyến để nhận đơn.',
                'Kiểm tra trạng thái ca làm việc của nhân sự trước giờ cao điểm.',
            ],
        };
    }

    const completionRate =
        props.summary.total > 0
            ? (props.summary.completed / props.summary.total) * 100
            : 0;
    void completionRate;
    const cancelRate =
        props.summary.total > 0
            ? (props.summary.cancelled / props.summary.total) * 100
            : 0;
    const pendingCount = props.summary.pending;

    let status = 'good';
    let title = 'Hiệu suất vận hành Xuất sắc';
    let text =
        'Hệ thống đang hoạt động với hiệu suất tối ưu. Tỷ lệ hoàn thành đơn hàng ở mức cao và tỷ lệ hủy thấp.';
    const tips = [];

    if (cancelRate > 15) {
        status = 'warning';
        title = 'Tỷ lệ hủy đơn hàng cao';
        text = `Cảnh báo: Tỷ lệ hủy đơn đạt ${cancelRate.toFixed(1)}%. Khách hàng hoặc nhà bếp đang hủy nhiều đơn.`;
        tips.push('Kiểm tra lại kho nguyên liệu để cập nhật thực đơn tức thì.');
        tips.push('Rà soát lại quy trình chuẩn bị để tránh quá tải bếp.');
    } else if (pendingCount > 4) {
        status = 'attention';
        title = 'Nhiều đơn chờ xác nhận';
        text = `Có ${pendingCount} đơn hàng chờ xác nhận. Thu ngân cần xử lý duyệt đơn để chuyển nhanh vào bếp.`;
        tips.push(
            'Đẩy nhanh tiến độ duyệt đơn để giảm thời gian chờ của khách.',
        );
        tips.push(
            'Bật tính năng tự động thanh toán ca cuối để tối ưu quy trình.',
        );
    } else {
        tips.push('Mọi chỉ số đều nằm trong ngưỡng an toàn.');
        tips.push(
            'Khuyến khích khách sử dụng QR Order để giảm tải trong giờ cao điểm.',
        );
    }

    // Kênh bán hàng
    const qrCount = channelStats.value.stats.qr;
    const posCount = channelStats.value.stats.pos;

    if (qrCount > posCount) {
        tips.push(
            'Kênh QR Order hoạt động vượt trội, tối ưu hóa năng suất phục vụ bàn.',
        );
    }

    return { status, title, text, tips };
});

let orderRefreshInterval: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    // Check if there is an active tab parameter in the URL
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');

    if (tabParam === 'ready') {
        activeTab.value = 'official';
        selectOfficialView('ready');
    } else if (tabParam && ['pending_qr', 'rejected_qr'].includes(tabParam)) {
        selectTab(tabParam);
    }

    // Kitchen and service actions happen on another device. Refresh the
    // owner view periodically so the progress badges do not require F5.
    orderRefreshInterval = setInterval(() => {
        router.reload({
            only: ['orders', 'summary'],
            preserveState: true,
            preserveScroll: true,
        });
    }, 8000);
});

onUnmounted(() => {
    if (orderRefreshInterval) {
        clearInterval(orderRefreshInterval);
        orderRefreshInterval = null;
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

                <div
                    class="relative flex cursor-pointer items-center"
                    @click="openDatePicker"
                >
                    <CalendarDays
                        class="absolute top-2.5 left-3 size-4 cursor-pointer text-muted-foreground transition-colors hover:text-foreground"
                        @click.stop="openDatePicker"
                    />
                    <input
                        ref="datePickerRef"
                        type="date"
                        v-model="dateInput"
                        @change="applyFilters"
                        @click="openDatePicker"
                        class="h-10 cursor-pointer rounded-md border border-input bg-background px-3 py-2 pl-9 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
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
        <div
            class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-8"
        >
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
                <p class="mt-0.5 text-xs text-amber-600/70">Chờ chế biến</p>
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
                class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-center dark:border-slate-700 dark:bg-slate-900/60"
            >
                <p
                    class="text-2xl font-bold text-slate-700 dark:text-slate-200"
                >
                    {{ summary.refunded }}
                </p>
                <p class="mt-0.5 text-xs text-slate-600/70 dark:text-slate-400">
                    Đơn hoàn tiền
                </p>
                <p
                    class="mt-1 text-[10px] font-semibold text-slate-500 dark:text-slate-400"
                >
                    {{ formatCurrency(summary.refunded_amount) }}
                </p>
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

        <!-- AI Analytics & Data Evaluation Panel -->
        <Card
            class="overflow-hidden rounded-2xl border border-slate-100 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900/40"
        >
            <CardHeader
                class="flex flex-row items-center justify-between border-b border-slate-100 pb-3 dark:border-slate-800"
            >
                <div class="flex items-center gap-2">
                    <Bot class="size-5 animate-bounce text-indigo-500" />
                    <div>
                        <CardTitle class="text-sm font-bold"
                            >Phân tích Hiệu suất & Đánh giá Vận hành
                            AI</CardTitle
                        >
                        <p class="text-[10px] text-muted-foreground">
                            Tự động phân tích biểu đồ cơ cấu và chẩn đoán hoạt
                            động dựa trên dữ liệu thực tế
                        </p>
                    </div>
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="text-indigo-650 h-8 text-xs dark:text-indigo-400"
                    @click="showAnalytics = !showAnalytics"
                >
                    {{ showAnalytics ? 'Thu gọn' : 'Mở rộng' }}
                </Button>
            </CardHeader>

            <CardContent v-if="showAnalytics" class="space-y-4 p-5">
                <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <!-- Column 1: Sales Channel Breakdown (Chart) -->
                    <div class="space-y-3">
                        <h4
                            class="dark:text-slate-350 flex items-center gap-1 text-xs font-bold text-slate-700"
                        >
                            <Sparkles class="size-3.5 text-indigo-500" /> Cơ cấu
                            Kênh bán hàng (Đơn)
                        </h4>

                        <!-- Mini SVG Donut Chart -->
                        <div class="flex items-center gap-4">
                            <div
                                class="relative flex h-24 w-24 items-center justify-center"
                            >
                                <svg
                                    viewBox="0 0 36 36"
                                    class="h-full w-full -rotate-90 transform"
                                >
                                    <circle
                                        cx="18"
                                        cy="18"
                                        r="15.915"
                                        fill="none"
                                        stroke="#f1f5f9"
                                        stroke-width="4"
                                        class="dark:stroke-slate-800"
                                    />
                                    <!-- QR Segment -->
                                    <circle
                                        cx="18"
                                        cy="18"
                                        r="15.915"
                                        fill="none"
                                        stroke="#6366f1"
                                        stroke-width="4"
                                        :stroke-dasharray="`${(channelStats.stats.qr / (summary.total || 1)) * 100} ${100 - (channelStats.stats.qr / (summary.total || 1)) * 100}`"
                                        stroke-dashoffset="0"
                                    />
                                    <!-- POS Segment -->
                                    <circle
                                        cx="18"
                                        cy="18"
                                        r="15.915"
                                        fill="none"
                                        stroke="#10b981"
                                        stroke-width="4"
                                        :stroke-dasharray="`${(channelStats.stats.pos / (summary.total || 1)) * 100} ${100 - (channelStats.stats.pos / (summary.total || 1)) * 100}`"
                                        :stroke-dashoffset="`-${(channelStats.stats.qr / (summary.total || 1)) * 100}`"
                                    />
                                    <!-- Delivery Segment -->
                                    <circle
                                        cx="18"
                                        cy="18"
                                        r="15.915"
                                        fill="none"
                                        stroke="#f59e0b"
                                        stroke-width="4"
                                        :stroke-dasharray="`${(channelStats.stats.delivery / (summary.total || 1)) * 100} ${100 - (channelStats.stats.delivery / (summary.total || 1)) * 100}`"
                                        :stroke-dashoffset="`-${((channelStats.stats.qr + channelStats.stats.pos) / (summary.total || 1)) * 100}`"
                                    />
                                    <!-- Other Segment -->
                                    <circle
                                        cx="18"
                                        cy="18"
                                        r="15.915"
                                        fill="none"
                                        stroke="#64748b"
                                        stroke-width="4"
                                        :stroke-dasharray="`${(channelStats.stats.other / (summary.total || 1)) * 100} ${100 - (channelStats.stats.other / (summary.total || 1)) * 100}`"
                                        :stroke-dashoffset="`-${((channelStats.stats.qr + channelStats.stats.pos + channelStats.stats.delivery) / (summary.total || 1)) * 100}`"
                                    />
                                </svg>
                                <div
                                    class="absolute flex flex-col items-center justify-center"
                                >
                                    <span
                                        class="text-sm font-black text-slate-800 dark:text-slate-100"
                                        >{{ summary.total }}</span
                                    >
                                    <span
                                        class="text-[8px] text-muted-foreground uppercase"
                                        >Tổng đơn</span
                                    >
                                </div>
                            </div>

                            <div class="flex-1 space-y-1.5 text-[11px]">
                                <div class="flex items-center justify-between">
                                    <span class="flex items-center gap-1"
                                        ><span
                                            class="h-2 w-2 rounded-full bg-indigo-500"
                                        ></span>
                                        QR Code</span
                                    >
                                    <span class="font-bold"
                                        >{{ channelStats.stats.qr }} đơn</span
                                    >
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="flex items-center gap-1"
                                        ><span
                                            class="h-2 w-2 rounded-full bg-emerald-500"
                                        ></span>
                                        Tại quầy (POS)</span
                                    >
                                    <span class="font-bold"
                                        >{{ channelStats.stats.pos }} đơn</span
                                    >
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="flex items-center gap-1"
                                        ><span
                                            class="h-2 w-2 rounded-full bg-amber-500"
                                        ></span>
                                        Giao hàng</span
                                    >
                                    <span class="font-bold"
                                        >{{
                                            channelStats.stats.delivery
                                        }}
                                        đơn</span
                                    >
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="flex items-center gap-1"
                                        ><span
                                            class="h-2 w-2 rounded-full bg-slate-500"
                                        ></span>
                                        Khác</span
                                    >
                                    <span class="font-bold"
                                        >{{
                                            channelStats.stats.other
                                        }}
                                        đơn</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Column 2: Order Conversion & Completion (KPI Progress) -->
                    <div
                        class="space-y-3 border-l border-slate-100 pl-0 md:pl-6 dark:border-slate-800"
                    >
                        <h4
                            class="dark:text-slate-350 flex items-center gap-1 text-xs font-bold text-slate-700"
                        >
                            <Activity class="size-3.5 text-emerald-500" /> Tỷ lệ
                            Hoàn thành & Chuyển đổi
                        </h4>
                        <div class="space-y-3 pt-1">
                            <div>
                                <div
                                    class="mb-1 flex items-center justify-between text-[11px]"
                                >
                                    <span class="text-muted-foreground"
                                        >Tỷ lệ hoàn thành (Completion
                                        Rate)</span
                                    >
                                    <span class="font-bold text-emerald-600"
                                        >{{
                                            summary.total > 0
                                                ? (
                                                      (summary.completed /
                                                          summary.total) *
                                                      100
                                                  ).toFixed(1)
                                                : 0
                                        }}%</span
                                    >
                                </div>
                                <div
                                    class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                                >
                                    <div
                                        class="h-full rounded-full bg-emerald-500"
                                        :style="{
                                            width: `${summary.total > 0 ? (summary.completed / summary.total) * 100 : 0}%`,
                                        }"
                                    ></div>
                                </div>
                            </div>

                            <div>
                                <div
                                    class="mb-1 flex items-center justify-between text-[11px]"
                                >
                                    <span class="text-muted-foreground"
                                        >Tỷ lệ hủy đơn (Cancellation Rate)</span
                                    >
                                    <span class="font-bold text-rose-500"
                                        >{{
                                            summary.total > 0
                                                ? (
                                                      (summary.cancelled /
                                                          summary.total) *
                                                      100
                                                  ).toFixed(1)
                                                : 0
                                        }}%</span
                                    >
                                </div>
                                <div
                                    class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                                >
                                    <div
                                        class="h-full rounded-full bg-rose-500"
                                        :style="{
                                            width: `${summary.total > 0 ? (summary.cancelled / summary.total) * 100 : 0}%`,
                                        }"
                                    ></div>
                                </div>
                            </div>

                            <div
                                class="rounded-lg bg-slate-50 p-2 text-[10px] text-muted-foreground dark:bg-slate-900/50"
                            >
                                Doanh thu trung bình mỗi đơn hoàn thành:
                                <strong
                                    class="font-mono text-slate-800 dark:text-slate-200"
                                    >{{
                                        formatCurrency(
                                            summary.completed > 0
                                                ? summary.revenue /
                                                      summary.completed
                                                : 0,
                                        )
                                    }}</strong
                                >
                            </div>
                        </div>
                    </div>

                    <!-- Column 3: AI Diagnosis and Recommendations -->
                    <div
                        class="space-y-3 border-l border-slate-100 pl-0 md:pl-6 dark:border-slate-800"
                    >
                        <div class="flex items-center gap-1.5">
                            <span
                                class="flex h-2 w-2 rounded-full"
                                :class="{
                                    'bg-emerald-500':
                                        aiEvaluation.status === 'good',
                                    'bg-amber-500':
                                        aiEvaluation.status === 'attention',
                                    'bg-rose-500':
                                        aiEvaluation.status === 'warning',
                                    'bg-slate-400':
                                        aiEvaluation.status === 'neutral',
                                }"
                            ></span>
                            <h4
                                class="dark:text-slate-350 text-xs font-bold text-slate-700"
                            >
                                {{ aiEvaluation.title }}
                            </h4>
                        </div>
                        <p
                            class="text-[11px] leading-relaxed text-muted-foreground"
                        >
                            {{ aiEvaluation.text }}
                        </p>

                        <div class="space-y-1.5 pt-1">
                            <div
                                v-for="(tip, idx) in aiEvaluation.tips"
                                :key="idx"
                                class="flex items-start gap-1.5 text-[10px] text-slate-700 dark:text-slate-400"
                            >
                                <span class="text-indigo-500 select-none"
                                    >•</span
                                >
                                <span>{{ tip }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

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
        <div
            v-if="activeTab === 'official'"
            class="animate-fade-in flex flex-col space-y-4"
        >
            <!-- Status filter chips -->
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="filter in statusFilters"
                    :key="filter.key"
                    @click="
                        filter.key === 'ready'
                            ? selectOfficialView('ready')
                            : setStatus(filter.key)
                    "
                    class="rounded-full border px-3 py-1.5 text-xs font-semibold transition-all"
                    :class="
                        filter.key === 'ready'
                            ? isOfficialReady
                                ? 'border-emerald-500 bg-emerald-50 text-emerald-700 dark:border-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                                : 'border-slate-200 bg-white text-slate-500 hover:border-emerald-300 dark:border-slate-800 dark:bg-slate-950'
                            : activeStatus === filter.key && !isOfficialReady
                              ? 'border-violet-500 bg-violet-50 text-violet-700 dark:border-violet-700 dark:bg-violet-950/40 dark:text-violet-400'
                              : 'border-slate-200 bg-white text-slate-500 hover:border-slate-300 dark:border-slate-800 dark:bg-slate-950'
                    "
                >
                    {{ filter.label }}
                    <span
                        v-if="
                            filter.key === 'waiting_preparing' &&
                            summary.pending > 0
                        "
                        class="rounded-full bg-amber-500 px-1.5 py-0.5 text-[10px] font-bold text-white"
                    >
                        {{ summary.pending }}
                    </span>
                    <span
                        v-if="
                            filter.key === 'ready' &&
                            (isOfficialReady
                                ? readyOrders.length
                                : (summary.ready ?? 0)) > 0
                        "
                        class="rounded-full bg-emerald-500 px-1.5 py-0.5 text-[10px] font-bold text-white"
                    >
                        {{
                            isOfficialReady
                                ? readyOrders.length
                                : (summary.ready ?? 0)
                        }}
                    </span>
                </button>
            </div>

            <!-- Orders table -->
            <Card
                v-if="officialOrders.length || !showCancelledQrOrders"
                class="shadow-sm"
            >
                <CardHeader class="border-b pb-3">
                    <CardTitle class="text-base">
                        {{
                            isOfficialReady
                                ? 'Đơn chờ phục vụ'
                                : 'Danh sách đơn hàng'
                        }}
                        <span
                            class="ml-1 text-sm font-normal text-muted-foreground"
                            >({{ officialOrders.length }} đơn)</span
                        >
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-0">
                    <div
                        v-if="officialOrders.length"
                        class="divide-y divide-slate-100 dark:divide-slate-800"
                    >
                        <div
                            v-for="o in paginatedOrders"
                            :key="o.id"
                            @click="openOrderDetails(o)"
                            class="group flex cursor-pointer items-center gap-4 px-5 py-3.5 transition-colors hover:bg-violet-50/50 dark:hover:bg-violet-950/20"
                        >
                            <!-- Order info -->
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        class="font-mono text-sm font-bold text-slate-800 transition-colors group-hover:text-violet-600 dark:text-slate-200 dark:group-hover:text-violet-400"
                                        >{{ o.order_number }}</span
                                    >
                                    <span
                                        class="text-[10px] font-medium text-muted-foreground"
                                    >
                                        {{ o.branch_name }}
                                    </span>
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
                                    class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground"
                                >
                                    <span
                                        class="inline-flex items-center gap-1"
                                    >
                                        <Clock class="size-3 text-slate-400" />
                                        Tạo đơn:
                                        <strong
                                            class="font-mono text-slate-700 dark:text-slate-300"
                                            >{{
                                                o.created_at_formatted ||
                                                o.created_at_full ||
                                                o.created_at
                                            }}</strong
                                        >
                                    </span>
                                    <span
                                        class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-400"
                                    >
                                        {{ o.items_count }} món
                                    </span>
                                    <!-- Tiến độ Bếp & Phục vụ -->
                                    <span
                                        v-if="
                                            o.items_prepared_count &&
                                            o.items_prepared_count > 0
                                        "
                                        class="inline-flex items-center gap-1 rounded-md border border-sky-200 bg-sky-50 px-1.5 py-0.5 text-[10px] font-bold text-sky-700 dark:border-sky-900/40 dark:bg-sky-950/40 dark:text-sky-300"
                                    >
                                        <ChefHat class="size-3 text-sky-500" />
                                        Bếp xong {{ o.items_prepared_count }}/{{
                                            o.items_count
                                        }}
                                    </span>
                                    <span
                                        v-if="
                                            o.items_served_count &&
                                            o.items_served_count > 0
                                        "
                                        class="inline-flex items-center gap-1 rounded-md border border-emerald-200 bg-emerald-50 px-1.5 py-0.5 text-[10px] font-bold text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/40 dark:text-emerald-300"
                                    >
                                        <Utensils
                                            class="size-3 text-emerald-500"
                                        />
                                        Đã phục vụ {{ o.items_served_count }}/{{
                                            o.items_count
                                        }}
                                    </span>
                                    <span
                                        v-if="o.completed_at"
                                        class="inline-flex items-center gap-1"
                                    >
                                        <CheckCircle2
                                            class="size-3 text-emerald-500"
                                        />
                                        Thanh toán:
                                        <strong
                                            class="font-mono text-emerald-600 dark:text-emerald-400"
                                            >{{
                                                o.completed_at_formatted ||
                                                o.completed_at_full ||
                                                o.completed_at
                                            }}</strong
                                        >
                                    </span>
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
                                <span
                                    v-if="(o.refund_amount ?? 0) > 0"
                                    class="mt-0.5 block text-[10px] font-semibold text-slate-500 dark:text-slate-400"
                                >
                                    Đã hoàn
                                    {{ formatCurrency(o.refund_amount ?? 0) }}
                                </span>
                            </div>

                            <!-- Status + next action -->
                            <div
                                class="flex shrink-0 items-center gap-2"
                                @click.stop
                            >
                                <span
                                    class="inline-flex items-center gap-1 rounded-full border px-2 py-1 text-[10px] font-bold"
                                    :class="
                                        fulfillmentConfig[o.fulfillment_status]
                                            .class
                                    "
                                    :title="`Bếp ${o.items_prepared_count ?? 0}/${o.items_count} · Phục vụ ${o.items_served_count ?? 0}/${o.items_count}`"
                                >
                                    <component
                                        :is="
                                            fulfillmentConfig[
                                                o.fulfillment_status
                                            ].icon
                                        "
                                        class="size-3"
                                    />
                                    {{
                                        fulfillmentConfig[o.fulfillment_status]
                                            .label
                                    }}
                                    <span class="font-mono opacity-80">
                                        {{ o.items_served_count ?? 0 }}/{{
                                            o.items_count
                                        }}
                                    </span>
                                </span>
                                <button
                                    v-if="
                                        nextStatus[o.status] && canUpdateStatus
                                    "
                                    :disabled="statusUpdating[o.id]"
                                    @click.stop="
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
                                        @click.stop="openSplit(o)"
                                        class="h-7 cursor-pointer rounded-lg border border-border px-2 text-[10px] font-semibold transition-colors hover:bg-accent"
                                        title="Tách bill để khách trả riêng"
                                    >
                                        Tách bill
                                    </button>
                                    <button
                                        v-if="o.table_name"
                                        @click.stop="openMove(o)"
                                        class="h-7 cursor-pointer rounded-lg border border-border px-2 text-[10px] font-semibold transition-colors hover:bg-accent"
                                        title="Chuyển sang bàn khác"
                                    >
                                        Chuyển bàn
                                    </button>
                                    <button
                                        v-if="
                                            o.table_name &&
                                            o.channel === 'dine_in'
                                        "
                                        @click.stop="convertToTakeaway(o)"
                                        class="h-7 cursor-pointer rounded-lg border border-amber-200 bg-amber-50 px-2 text-[10px] font-semibold text-amber-700 transition-colors hover:bg-amber-100 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-400"
                                        title="Chuyển nhanh đơn tại bàn sang mang về"
                                    >
                                        Mang về
                                    </button>
                                    <button
                                        @click.stop="openMerge(o)"
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
                                    @click.stop
                                    class="inline-flex h-7 items-center gap-1 rounded-lg border border-amber-200 px-2 text-[10px] font-semibold text-amber-700 transition-colors hover:bg-amber-50 dark:border-amber-900/50 dark:text-amber-400 dark:hover:bg-amber-950/30"
                                    title="Tải XML hóa đơn điện tử (TT78) — BẢN NHÁP, CHƯA KÝ SỐ. Cần nộp lên cổng nhà cung cấp (MISA/VNPT) để ký & phát hành hợp lệ."
                                >
                                    HĐĐT
                                    <span
                                        class="rounded bg-amber-100 px-1 text-[8px] font-bold tracking-wide text-amber-700 uppercase dark:bg-amber-950/60 dark:text-amber-400"
                                        >nháp</span
                                    >
                                </a>

                                <!-- Hoàn tiền (đơn đã thanh toán) -->
                                <button
                                    v-if="
                                        ['paid', 'partial_refund'].includes(
                                            o.payment_status,
                                        )
                                    "
                                    @click.stop="openRefundModal(o)"
                                    class="inline-flex h-7 cursor-pointer items-center gap-1 rounded-lg border border-rose-200 bg-rose-50 px-2 text-[10px] font-semibold text-rose-700 transition-colors hover:bg-rose-100 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-400 dark:hover:bg-rose-950/60"
                                    title="Xử lý hoàn tiền cho đơn hàng"
                                >
                                    <RotateCcw class="size-3" />
                                    Hoàn tiền
                                </button>
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

                    <!-- Pagination -->
                    <div
                        v-if="totalPages > 1"
                        class="flex flex-col gap-3 border-t border-border/80 bg-muted/20 p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div
                            class="text-xs font-semibold text-muted-foreground"
                        >
                            Hiển thị
                            {{ (currentPage - 1) * itemsPerPage + 1 }} -
                            {{
                                Math.min(
                                    currentPage * itemsPerPage,
                                    officialOrders.length,
                                )
                            }}
                            trong tổng số {{ officialOrders.length }} đơn hàng
                        </div>
                        <div class="flex items-center gap-1.5">
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="currentPage === 1"
                                @click="currentPage--"
                                class="h-8 cursor-pointer rounded-xl text-xs"
                            >
                                Trước
                            </Button>
                            <Button
                                v-for="page in visiblePages"
                                :key="page"
                                variant="outline"
                                size="sm"
                                @click="currentPage = page"
                                :class="[
                                    'h-8 w-8 cursor-pointer rounded-xl p-0 text-xs font-bold',
                                    currentPage === page
                                        ? 'border-0 bg-violet-600 text-white shadow-xs hover:bg-violet-700 dark:bg-violet-600 dark:hover:bg-violet-700'
                                        : '',
                                ]"
                            >
                                {{ page }}
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                :disabled="currentPage === totalPages"
                                @click="currentPage++"
                                class="h-8 cursor-pointer rounded-xl text-xs"
                            >
                                Sau
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Cancelled QR requests are temporary orders until confirmed. -->
            <Card v-if="showCancelledQrOrders" class="shadow-sm">
                <CardHeader class="border-b pb-3">
                    <CardTitle class="text-base">
                        Đơn QR bị hủy
                        <span
                            class="ml-1 text-sm font-normal text-muted-foreground"
                        >
                            ({{ cancelledQrOrders.length }} đơn)
                        </span>
                    </CardTitle>
                </CardHeader>
                <CardContent class="p-0">
                    <div
                        class="divide-y divide-slate-100 dark:divide-slate-800"
                    >
                        <div
                            v-for="o in cancelledQrOrders"
                            :key="`cancelled-qr-${o.id}`"
                            class="flex flex-col gap-3 px-5 py-4 md:flex-row md:items-center md:justify-between"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        class="font-mono text-sm font-bold text-slate-800 dark:text-slate-200"
                                    >
                                        {{ o.order_number }}
                                    </span>
                                    <span
                                        class="rounded-md border border-indigo-200 bg-indigo-50 px-1.5 py-0.5 text-[9px] font-semibold text-indigo-700 dark:border-indigo-900/40 dark:bg-indigo-950/40 dark:text-indigo-300"
                                    >
                                        QR Scan
                                    </span>
                                    <span
                                        class="rounded-full border border-rose-200 bg-rose-50 px-2 py-1 text-[10px] font-bold text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/40 dark:text-rose-300"
                                    >
                                        Đã hủy
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
                                    class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground"
                                >
                                    <span
                                        >Tạo đơn:
                                        <strong class="font-mono">{{
                                            o.created_at_formatted
                                        }}</strong></span
                                    >
                                    <span
                                        >Hủy lúc:
                                        <strong class="font-mono">{{
                                            o.cancelled_at
                                        }}</strong></span
                                    >
                                    <span>{{ o.items_count }} món</span>
                                </div>
                                <p class="mt-2 text-xs text-slate-500">
                                    {{
                                        o.items
                                            ?.map(
                                                (item) =>
                                                    `${item.quantity}x ${item.name}`,
                                            )
                                            .join(', ')
                                    }}
                                </p>
                                <p
                                    class="mt-1 text-xs text-rose-600 dark:text-rose-400"
                                >
                                    Lý do:
                                    {{
                                        o.cancellation_reason ||
                                        'Không ghi lý do'
                                    }}
                                    · Người hủy: {{ o.cancelled_by_name }}
                                </p>
                            </div>
                            <div class="shrink-0 text-right">
                                <p
                                    class="font-mono text-sm font-bold text-slate-800 dark:text-slate-200"
                                >
                                    {{ formatCurrency(o.total_amount) }}
                                </p>
                                <p
                                    class="text-[10px] font-semibold text-slate-500"
                                >
                                    Không tính doanh thu
                                </p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- TAB 2: Pending QR orders -->
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
                    <div class="flex items-center gap-2">
                        <!-- Route orders.batch-approve-qr đã có sẵn nhưng chưa
                             từng có nút gọi: quán đông phải bấm duyệt từng đơn. -->
                        <Button
                            v-if="pendingQrOrders.length > 1"
                            size="sm"
                            class="h-8 bg-violet-600 text-white hover:bg-violet-700"
                            :disabled="isBatchApprovingQr"
                            @click="batchApproveQr"
                        >
                            <Check class="mr-1 size-3.5" />
                            {{
                                isBatchApprovingQr
                                    ? 'Đang duyệt...'
                                    : `Duyệt tất cả (${pendingQrOrders.length})`
                            }}
                        </Button>
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
                    </div>
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
        <Teleport to="body">
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
        </Teleport>

        <!-- ── Cancellation Reason Modal ────────────────────────────────────── -->
        <Teleport to="body">
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
        </Teleport>
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

    <!-- ─── Dialog Hoàn tiền ─── -->
    <Dialog :open="showRefundModal" @update:open="showRefundModal = $event">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle
                    class="flex items-center gap-2 text-base font-bold"
                >
                    <RotateCcw class="size-4 text-rose-600" />
                    Hoàn tiền đơn hàng — {{ refundOrderTarget?.order_number }}
                </DialogTitle>
                <DialogDescription class="text-xs">
                    Xác định loại lý do & chọn món hoàn để hệ thống tự động hạch
                    toán tồn kho và báo cáo tới Chủ doanh nghiệp.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4 py-2 text-xs">
                <!-- 1. PHÂN LOẠI LÝ DO HOÀN TIỀN -->
                <div>
                    <label
                        class="mb-1.5 block font-bold text-slate-800 dark:text-slate-200"
                    >
                        1. Loại lý do hoàn tiền (<span class="text-rose-500"
                            >* Bắt buộc chọn</span
                        >):
                    </label>
                    <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                        <label
                            class="flex cursor-pointer items-start gap-2.5 rounded-xl border p-3 transition"
                            :class="
                                refundCategory === 'compensation'
                                    ? 'border-amber-500/80 bg-amber-500/10 font-bold text-amber-900 dark:text-amber-200'
                                    : 'border-slate-200 hover:bg-slate-50 dark:border-white/10 dark:hover:bg-white/5'
                            "
                        >
                            <input
                                type="radio"
                                v-model="refundCategory"
                                value="compensation"
                                class="mt-0.5"
                            />
                            <div>
                                <span class="block text-xs font-bold"
                                    >Bồi thường khách hàng</span
                                >
                                <span
                                    class="mt-0.5 block text-[10px] leading-4 font-normal opacity-80"
                                >
                                    Sự cố món ăn, phục vụ kém... —
                                    <strong
                                        >Nguyên liệu trừ tồn bình thường</strong
                                    >.
                                </span>
                            </div>
                        </label>

                        <label
                            class="flex cursor-pointer items-start gap-2.5 rounded-xl border p-3 transition"
                            :class="
                                refundCategory === 'mistake'
                                    ? 'border-sky-500/80 bg-sky-500/10 font-bold text-sky-900 dark:text-sky-200'
                                    : 'border-slate-200 hover:bg-slate-50 dark:border-white/10 dark:hover:bg-white/5'
                            "
                        >
                            <input
                                type="radio"
                                v-model="refundCategory"
                                value="mistake"
                                class="mt-0.5"
                            />
                            <div>
                                <span class="block text-xs font-bold"
                                    >Nhầm lẫn / Lỗi nhập liệu</span
                                >
                                <span
                                    class="mt-0.5 block text-[10px] leading-4 font-normal opacity-80"
                                >
                                    Nhập nhầm đơn, ghi sai món... —
                                    <strong>Không trừ / Hoàn trả tồn kho</strong
                                    >.
                                </span>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- 2. HÌNH THỨC HOÀN TIỀN -->
                <div>
                    <label
                        class="mb-1.5 block font-bold text-slate-800 dark:text-slate-200"
                    >
                        2. Hình thức hoàn tiền:
                    </label>
                    <div class="flex gap-4">
                        <label
                            class="flex cursor-pointer items-center gap-1.5 font-medium"
                        >
                            <input
                                type="radio"
                                v-model="refundType"
                                value="full"
                            />
                            <span
                                >Hoàn toàn bộ phần còn lại ({{
                                    formatCurrency(remainingRefundAmount)
                                }})</span
                            >
                        </label>
                        <label
                            class="flex cursor-pointer items-center gap-1.5 font-medium"
                        >
                            <input
                                type="radio"
                                v-model="refundType"
                                value="partial"
                            />
                            <span>Hoàn một phần (Chọn món & số lượng)</span>
                        </label>
                    </div>
                </div>

                <!-- 3. CHỌN MÓN HOÀN KHI CHỌN HOÀN 1 PHẦN -->
                <div
                    v-if="refundType === 'partial'"
                    class="space-y-2 rounded-xl border border-slate-200 bg-slate-50/50 p-3 dark:border-white/10 dark:bg-white/5"
                >
                    <label
                        class="block font-bold text-slate-800 dark:text-slate-200"
                    >
                        Chọn các món cần hoàn tiền:
                    </label>
                    <div
                        v-if="refundOrderTarget?.items?.length"
                        class="max-h-48 space-y-2 overflow-y-auto pr-1"
                    >
                        <div
                            v-for="item in refundOrderTarget.items"
                            :key="item.id"
                            class="flex items-center justify-between gap-2 rounded-lg border bg-white p-2.5 dark:border-white/10 dark:bg-slate-900"
                        >
                            <div class="min-w-0 flex-1">
                                <p
                                    class="truncate text-xs font-bold text-slate-800 dark:text-slate-200"
                                >
                                    {{ item.name }}
                                </p>
                                <p
                                    class="text-[10px] text-slate-500 dark:text-slate-400"
                                >
                                    Đã đặt: {{ item.quantity }} x
                                    {{
                                        formatCurrency(
                                            item.unit_price ??
                                                (item.quantity > 0
                                                    ? item.line_total /
                                                      item.quantity
                                                    : 0),
                                        )
                                    }}
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center gap-1.5">
                                <button
                                    type="button"
                                    class="flex size-6 items-center justify-center rounded-md border border-slate-300 text-slate-600 hover:bg-slate-100 disabled:opacity-30 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                                    :disabled="
                                        (refundItemQuantities[item.id] || 0) <=
                                        0
                                    "
                                    @click="
                                        refundItemQuantities[item.id] =
                                            Math.max(
                                                0,
                                                (refundItemQuantities[
                                                    item.id
                                                ] || 0) - 1,
                                            )
                                    "
                                >
                                    -
                                </button>
                                <span class="w-7 text-center text-xs font-bold">
                                    {{ refundItemQuantities[item.id] || 0 }}
                                </span>
                                <button
                                    type="button"
                                    class="flex size-6 items-center justify-center rounded-md border border-slate-300 text-slate-600 hover:bg-slate-100 disabled:opacity-30 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                                    :disabled="
                                        (refundItemQuantities[item.id] || 0) >=
                                        item.quantity
                                    "
                                    @click="
                                        refundItemQuantities[item.id] =
                                            Math.min(
                                                item.quantity,
                                                (refundItemQuantities[
                                                    item.id
                                                ] || 0) + 1,
                                            )
                                    "
                                >
                                    +
                                </button>
                            </div>
                        </div>
                    </div>

                    <div
                        class="mt-2 flex items-center justify-between border-t border-slate-200/80 pt-2 dark:border-white/10"
                    >
                        <span
                            class="font-bold text-slate-700 dark:text-slate-300"
                            >Tổng tiền hoàn chọn được:</span
                        >
                        <span
                            class="text-sm font-extrabold text-rose-600 dark:text-rose-400"
                        >
                            {{ formatCurrency(computedPartialRefundAmount) }}
                        </span>
                    </div>
                </div>

                <!-- 4. GHI CHÚ LÝ DO CHI TIẾT -->
                <div>
                    <label
                        class="mb-1 block font-bold text-slate-800 dark:text-slate-200"
                    >
                        3. Ghi chú chi tiết lý do (<span class="text-rose-500"
                            >* tối thiểu 5 ký tự</span
                        >):
                    </label>
                    <textarea
                        v-model="refundReason"
                        rows="2.5"
                        placeholder="Nhập diễn giải chi tiết lý do hoàn tiền..."
                        class="w-full rounded-xl border border-input bg-background p-2.5 text-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    ></textarea>
                </div>

                <p
                    class="rounded-lg border border-amber-200 bg-amber-50 p-2.5 text-[11px] text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300"
                >
                    Yêu cầu hoàn tiền nhạy cảm sẽ được ghi nhật ký kiểm toán và
                    gửi thông báo cảnh báo trực tiếp đến Chủ doanh nghiệp phê
                    duyệt.
                </p>
            </div>

            <DialogFooter class="flex justify-end gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    class="text-xs"
                    @click="showRefundModal = false"
                >
                    Hủy bỏ
                </Button>
                <Button
                    size="sm"
                    class="bg-rose-600 text-xs font-bold text-white hover:bg-rose-700"
                    :disabled="
                        isSubmittingRefund ||
                        !refundCategory ||
                        refundReason.trim().length < 5 ||
                        effectiveRefundAmount < 1000 ||
                        effectiveRefundAmount > remainingRefundAmount
                    "
                    @click="submitRefund"
                >
                    <Loader2
                        v-if="isSubmittingRefund"
                        class="mr-1 size-3.5 animate-spin"
                    />
                    {{
                        isOwner
                            ? `Xác nhận hoàn ${formatCurrency(effectiveRefundAmount)}`
                            : `Gửi yêu cầu hoàn ${formatCurrency(effectiveRefundAmount)}`
                    }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Modal Hủy món -->
    <Dialog
        :open="showCancelItemModal"
        @update:open="showCancelItemModal = $event"
    >
        <DialogContent class="max-w-md rounded-2xl">
            <DialogHeader>
                <DialogTitle>Hủy món và báo xuống bếp</DialogTitle>
                <DialogDescription>
                    {{ cancelItemTarget?.name }} ·
                    {{ selectedOrder?.order_number }}
                </DialogDescription>
            </DialogHeader>
            <div class="space-y-3 py-2">
                <label class="text-xs font-bold text-foreground"
                    >Lý do hủy món <span class="text-rose-500">*</span></label
                >
                <select
                    v-model="cancelItemReason"
                    class="w-full rounded-xl border border-input bg-background p-2.5 text-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <option
                        v-for="reason in cancellationReasonOptions"
                        :key="reason"
                        :value="reason"
                    >
                        {{ reason }}
                    </option>
                    <option value="__custom__">Lý do khác...</option>
                </select>
                <textarea
                    v-if="cancelItemReason === '__custom__'"
                    v-model="cancelItemCustomReason"
                    placeholder="Nhập lý do hủy..."
                    rows="3"
                    class="w-full rounded-xl border border-input bg-background p-2.5 text-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                />
                <p
                    class="rounded-lg border border-amber-200 bg-amber-50 p-2.5 text-[11px] text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300"
                >
                    Nếu bếp đã bắt đầu làm, hệ thống sẽ yêu cầu quản lý duyệt và
                    ghi nhận món vào Hủy/Tổn thất.
                </p>
            </div>
            <DialogFooter class="gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    class="text-xs"
                    @click="showCancelItemModal = false"
                    >Đóng</Button
                >
                <Button
                    size="sm"
                    class="bg-rose-600 text-xs font-bold text-white hover:bg-rose-700"
                    :disabled="
                        isSubmittingItemCancel ||
                        effectiveCancelItemReason.trim().length < 3
                    "
                    @click="submitCancelItem"
                >
                    <Loader2
                        v-if="isSubmittingItemCancel"
                        class="mr-1 size-3.5 animate-spin"
                    />
                    Xác nhận hủy món
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <!-- Modal Chi Tiết Đơn Hàng -->
    <Dialog :open="showDetailModal" @update:open="showDetailModal = $event">
        <DialogContent
            class="flex max-h-[90vh] max-w-2xl flex-col overflow-hidden rounded-2xl border-slate-200 p-0 dark:border-slate-800"
        >
            <!-- Header -->
            <DialogHeader
                class="border-b border-border/80 bg-slate-50/50 px-6 py-4 dark:bg-slate-900/50"
            >
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2.5">
                        <DialogTitle
                            class="font-mono text-lg font-black text-slate-900 dark:text-slate-100"
                        >
                            {{ selectedOrder?.order_number }}
                        </DialogTitle>
                        <span
                            class="rounded-md border border-slate-200 bg-white px-2 py-0.5 text-xs font-bold text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                        >
                            {{
                                selectedOrder
                                    ? (channelLabel[selectedOrder.channel] ??
                                      selectedOrder.channel)
                                    : ''
                            }}
                        </span>
                        <span
                            v-if="selectedOrder"
                            class="inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-bold"
                            :class="
                                fulfillmentConfig[
                                    selectedOrder.fulfillment_status
                                ].class
                            "
                        >
                            <component
                                :is="
                                    fulfillmentConfig[
                                        selectedOrder.fulfillment_status
                                    ].icon
                                "
                                class="size-3"
                            />
                            {{
                                fulfillmentConfig[
                                    selectedOrder.fulfillment_status
                                ].label
                            }}
                        </span>
                    </div>
                </div>
                <DialogDescription class="mt-1 text-xs text-muted-foreground">
                    Chi tiết đơn hàng và danh sách món ăn đã gọi
                </DialogDescription>
            </DialogHeader>

            <div
                v-if="selectedOrder"
                class="flex-1 space-y-6 overflow-y-auto p-6"
            >
                <!-- Thông tin chung & Thời gian -->
                <div
                    class="grid grid-cols-2 gap-4 rounded-xl border border-border/60 bg-muted/20 p-4 text-xs"
                >
                    <div>
                        <span class="block text-[11px] text-muted-foreground"
                            >Vị trí / Bàn:</span
                        >
                        <span class="text-sm font-bold text-foreground">
                            {{
                                selectedOrder.table_name
                                    ? `${selectedOrder.table_name}${selectedOrder.area_name ? ' · ' + selectedOrder.area_name : ''}`
                                    : 'Không chọn bàn'
                            }}
                        </span>
                    </div>
                    <div>
                        <span class="block text-[11px] text-muted-foreground"
                            >Trạng thái thanh toán:</span
                        >
                        <span
                            class="text-xs font-bold"
                            :class="
                                paymentConfig[selectedOrder.payment_status]
                                    ?.color
                            "
                        >
                            {{
                                paymentConfig[selectedOrder.payment_status]
                                    ?.label
                            }}
                        </span>
                    </div>
                    <div>
                        <span class="block text-[11px] text-muted-foreground"
                            >Thời gian tạo:</span
                        >
                        <span class="font-semibold text-foreground">
                            {{
                                selectedOrder.created_at_full ||
                                selectedOrder.created_at
                            }}
                        </span>
                    </div>
                    <div>
                        <span class="block text-[11px] text-muted-foreground"
                            >Thời gian thanh toán:</span
                        >
                        <span
                            class="font-semibold text-emerald-600 dark:text-emerald-400"
                        >
                            {{
                                selectedOrder.completed_at_full ||
                                selectedOrder.completed_at_formatted ||
                                selectedOrder.completed_at ||
                                '—'
                            }}
                        </span>
                    </div>
                    <div
                        v-if="selectedOrder.note"
                        class="col-span-2 mt-1 border-t border-border/40 pt-2"
                    >
                        <span class="block text-[11px] text-muted-foreground"
                            >Ghi chú đơn hàng:</span
                        >
                        <span
                            class="font-medium text-amber-600 italic dark:text-amber-400"
                        >
                            "{{ selectedOrder.note }}"
                        </span>
                    </div>
                </div>

                <!-- Thông tin hoàn tiền -->
                <div
                    v-if="(selectedOrder.refund_amount ?? 0) > 0"
                    class="space-y-2 rounded-xl border border-slate-300 bg-slate-50 p-4 text-xs dark:border-slate-700 dark:bg-slate-900/60"
                >
                    <div class="flex items-center justify-between">
                        <h4
                            class="font-bold text-slate-700 dark:text-slate-200"
                        >
                            Lịch sử hoàn tiền
                        </h4>
                        <span
                            class="rounded-full bg-slate-200 px-2 py-0.5 text-[10px] font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                        >
                            {{
                                paymentConfig[selectedOrder.payment_status]
                                    ?.label
                            }}
                        </span>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <span class="text-muted-foreground">Đã hoàn:</span>
                            <strong
                                class="ml-1 text-slate-700 dark:text-slate-200"
                            >
                                {{
                                    formatCurrency(
                                        selectedOrder.refund_amount ?? 0,
                                    )
                                }}
                            </strong>
                        </div>
                        <div>
                            <span class="text-muted-foreground"
                                >Người xử lý:</span
                            >
                            <strong
                                class="ml-1 text-slate-700 dark:text-slate-200"
                            >
                                {{ selectedOrder.refunded_by_name || '—' }}
                            </strong>
                        </div>
                        <div>
                            <span class="text-muted-foreground"
                                >Thời điểm:</span
                            >
                            <strong
                                class="ml-1 text-slate-700 dark:text-slate-200"
                            >
                                {{ selectedOrder.refunded_at || '—' }}
                            </strong>
                        </div>
                    </div>
                    <p
                        v-if="selectedOrder.refund_reason"
                        class="border-t border-slate-200 pt-2 text-slate-600 dark:border-slate-700 dark:text-slate-400"
                    >
                        <span class="font-semibold">Lý do:</span>
                        {{ selectedOrder.refund_reason }}
                    </p>
                </div>

                <!-- Thông tin giao hàng (nếu channel === delivery) -->
                <div
                    v-if="selectedOrder.delivery"
                    class="space-y-2 rounded-xl border border-amber-500/20 bg-amber-500/5 p-4 text-xs"
                >
                    <div
                        class="flex items-center justify-between font-bold text-amber-700 dark:text-amber-400"
                    >
                        <span>📦 Thông tin giao hàng (Delivery)</span>
                        <span
                            class="rounded-full bg-amber-500/20 px-2 py-0.5 text-[10px] uppercase"
                        >
                            {{ selectedOrder.delivery.status }}
                        </span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-foreground">
                        <div>
                            <span class="text-muted-foreground"
                                >Khách hàng:</span
                            >
                            <strong>{{
                                selectedOrder.delivery.customer_name
                            }}</strong>
                        </div>
                        <div>
                            <span class="text-muted-foreground">SĐT:</span>
                            <strong>{{ selectedOrder.delivery.phone }}</strong>
                        </div>
                        <div class="col-span-2">
                            <span class="text-muted-foreground">Địa chỉ:</span>
                            {{ selectedOrder.delivery.address }}
                        </div>
                        <div>
                            <span class="text-muted-foreground">Phí ship:</span>
                            {{ formatCurrency(selectedOrder.delivery.fee) }}
                        </div>
                        <div>
                            <span class="text-muted-foreground"
                                >Thu hộ COD:</span
                            >
                            {{ formatCurrency(selectedOrder.delivery.cod) }}
                        </div>
                    </div>
                </div>

                <!-- Danh sách món ăn -->
                <div>
                    <h4
                        class="mb-3 flex items-center gap-1.5 text-xs font-bold tracking-wider text-muted-foreground uppercase"
                    >
                        <Utensils class="size-3.5 text-violet-500" />
                        Danh sách món ăn ({{
                            selectedOrder.items?.length ||
                            selectedOrder.items_count
                        }}
                        món)
                    </h4>
                    <div
                        class="divide-y divide-border/60 overflow-hidden rounded-xl border border-border/80"
                    >
                        <div
                            v-for="item in selectedOrder.items || []"
                            :key="item.id"
                            class="flex items-center justify-between p-3 text-xs transition-colors hover:bg-muted/30"
                        >
                            <div class="min-w-0 flex-1 pr-3">
                                <div
                                    class="flex flex-wrap items-center gap-2 font-bold text-foreground"
                                >
                                    <span>{{ item.name }}</span>
                                    <!-- Status badge cho từng món -->
                                    <span
                                        v-if="item.status === 'cancelled'"
                                        class="rounded bg-rose-100 px-2 py-0.5 text-[10px] font-bold text-rose-700 dark:bg-rose-950/60 dark:text-rose-400"
                                    >
                                        Đã hủy
                                    </span>
                                    <span
                                        v-else-if="item.served_at"
                                        class="inline-flex items-center gap-1 rounded bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300"
                                    >
                                        <CheckCircle2
                                            class="size-3 text-emerald-600 dark:text-emerald-400"
                                        />
                                        Đã phục vụ {{ item.served_at }}
                                        <span
                                            v-if="item.served_by_name"
                                            class="font-normal text-emerald-700/80 dark:text-emerald-400/80"
                                            >· {{ item.served_by_name }}</span
                                        >
                                    </span>
                                    <span
                                        v-else-if="item.prepared_at"
                                        class="inline-flex items-center gap-1 rounded bg-sky-100 px-2 py-0.5 text-[10px] font-bold text-sky-800 dark:bg-sky-950/60 dark:text-sky-300"
                                    >
                                        <ChefHat
                                            class="size-3 text-sky-600 dark:text-sky-400"
                                        />
                                        Bếp làm xong {{ item.prepared_at }} (Chờ
                                        phục vụ)
                                        <span
                                            v-if="item.prepared_by_name"
                                            class="font-normal text-sky-700/80 dark:text-sky-400/80"
                                            >· Bếp:
                                            {{ item.prepared_by_name }}</span
                                        >
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1 rounded bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800 dark:bg-amber-950/60 dark:text-amber-300"
                                    >
                                        <Clock
                                            class="size-3 text-amber-600 dark:text-amber-400"
                                        />
                                        {{
                                            item.started_preparing_at ||
                                            item.status === 'preparing'
                                                ? 'Bếp đang làm'
                                                : 'Chờ bếp làm'
                                        }}
                                    </span>
                                </div>
                                <div
                                    v-if="item.notes"
                                    class="mt-0.5 text-[11px] text-amber-600 italic dark:text-amber-400"
                                >
                                    Ghi chú: {{ item.notes }}
                                </div>
                            </div>
                            <div class="shrink-0 text-right">
                                <div
                                    class="font-mono font-bold text-foreground"
                                >
                                    {{ item.quantity }} x
                                    {{ formatCurrency(item.unit_price) }}
                                </div>
                                <div
                                    class="font-mono text-xs font-black text-violet-600 dark:text-violet-400"
                                >
                                    {{ formatCurrency(item.line_total) }}
                                </div>
                                <button
                                    v-if="
                                        item.status !== 'cancelled' &&
                                        !item.served_at
                                    "
                                    type="button"
                                    class="mt-1 inline-flex cursor-pointer items-center gap-1 rounded-lg border border-rose-200 bg-rose-50 px-2 py-1 text-[10px] font-bold text-rose-700 transition-colors hover:bg-rose-100 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-400"
                                    @click="openCancelItemModal(item)"
                                >
                                    <Trash2 class="size-3" />
                                    Hủy món
                                </button>
                            </div>
                        </div>
                        <div
                            v-if="!selectedOrder.items?.length"
                            class="p-4 text-center text-xs text-muted-foreground"
                        >
                            Không tìm thấy danh sách món chi tiết.
                        </div>
                    </div>
                </div>

                <!-- Tổng tiền -->
                <div
                    class="flex items-center justify-between rounded-xl border border-violet-500/20 bg-violet-600/10 p-4"
                >
                    <span
                        class="text-xs font-bold tracking-wide text-violet-900 uppercase dark:text-violet-200"
                    >
                        Tổng thanh toán
                    </span>
                    <span
                        class="font-mono text-xl font-black text-violet-700 dark:text-violet-300"
                    >
                        {{ formatCurrency(selectedOrder.total_amount) }}
                    </span>
                </div>
            </div>

            <!-- Footer -->
            <DialogFooter
                class="flex items-center justify-between border-t border-border/80 bg-slate-50/50 p-4 sm:justify-between dark:bg-slate-900/50"
            >
                <Button
                    variant="outline"
                    size="sm"
                    class="rounded-xl"
                    @click="showDetailModal = false"
                >
                    Đóng
                </Button>

                <div v-if="selectedOrder" class="flex items-center gap-2">
                    <button
                        v-if="
                            nextStatus[selectedOrder.status] && canUpdateStatus
                        "
                        :disabled="statusUpdating[selectedOrder.id]"
                        @click="
                            updateOrderStatus(
                                selectedOrder,
                                nextStatus[selectedOrder.status]!,
                            );
                            showDetailModal = false;
                        "
                        class="flex h-8 cursor-pointer items-center gap-1 rounded-xl bg-violet-600 px-3 text-xs font-semibold text-white transition-colors hover:bg-violet-700 disabled:opacity-50"
                    >
                        {{
                            nextStatus[selectedOrder.status] === 'confirmed'
                                ? 'Xác nhận đơn'
                                : nextStatus[selectedOrder.status] ===
                                    'preparing'
                                  ? 'Chuyển bếp'
                                  : nextStatus[selectedOrder.status] ===
                                      'completed'
                                    ? 'Hoàn thành'
                                    : ''
                        }}
                    </button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
