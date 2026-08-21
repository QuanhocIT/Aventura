<script setup lang="ts">
import { Head, usePage, router } from '@inertiajs/vue3';
import axios from 'axios';
import { Utensils, Sparkles, Clock, CheckIcon, XCircle } from 'lucide-vue-next';
import {
    ref,
    computed,
    onMounted,
    onUnmounted,
} from 'vue';

// TypeScript Types

// Composables

// Components
import CashierHeader from './components/CashierHeader.vue';
import OrderReviewPanel from './components/OrderReviewPanel.vue';
import PaymentModal from './components/PaymentModal.vue';
import ProductGrid from './components/ProductGrid.vue';
import QrOrdersPanel from './components/QrOrdersPanel.vue';
import SelfServiceModal from './components/SelfServiceModal.vue';
import SplitOrderModal from './components/SplitOrderModal.vue';
import TableActionModal from './components/TableActionModal.vue';
import TableGrid from './components/TableGrid.vue';
import { useCashierCart } from './composables/useCashierCart';
import { useCashierPayment } from './composables/useCashierPayment';
import { useCashierRealtime } from './composables/useCashierRealtime';
import { useCashierTables } from './composables/useCashierTables';
import type {
    TableItem,
    ProductItem,
    CategoryItem,
    OrderItem,
    ToastType,
    ToastItem,
} from './types';

const props = defineProps<{
    tablesData: TableItem[];
    products: ProductItem[];
    categories: CategoryItem[];
    shiftInfo: {
        active_shift: {
            id: number;
            shift_name: string;
            check_in_at: string;
        } | null;
        shift_revenue: number;
        total_orders?: number;
        channel_breakdown?: Record<string, { count: number; revenue: number }>;
    };
    qrOrders: any[];
    externalOrders: any[];
    kitchenReadyItems: Array<{
        id: number;
        product_name: string;
        quantity: number;
        notes?: string | null;
        prepared_at?: string | null;
        prepared_by_name?: string | null;
        table_name?: string | null;
        order_number?: string | null;
    }>;
    completedHistory: any[];
    weeklySchedules: any[];
    activeShifts: any[];
    pendingLeaves: any[];
    colleagues: Array<{
        id: number;
        full_name: string;
        job_title: string | null;
    }>;
    employee: { id: number; full_name: string } | null;
}>();

const page = usePage();

const can = (permission: string) => {
    const authUser = page.props.auth?.user as any;
    const userPermissions = authUser?.permissions ?? [];

    return userPermissions.includes(permission);
};

const restaurantId = computed(
    () => (page.props.auth?.user as any)?.restaurant_id as number | undefined,
);

// Toast System
const toasts = ref<ToastItem[]>([]);
let _toastId = 0;
const toast = (message: string, type: ToastType = 'success') => {
    const id = ++_toastId;
    toasts.value.push({ id, message, type });
    setTimeout(() => {
        toasts.value = toasts.value.filter((t) => t.id !== id);
    }, 3500);
};

// Realtime & Polling
const { wsConnected } = useCashierRealtime(() => restaurantId.value);

// Active Tab & Search State
const activeTab = ref<'tables' | 'qr' | 'history' | 'schedules'>('tables');

const mainTabs = [
    { id: 'tables' as const, label: 'Sơ đồ bàn phục vụ', icon: Utensils },
    { id: 'qr' as const, label: 'Đơn Ngoài & QR', icon: Sparkles },
    { id: 'history' as const, label: 'Lịch sử & Bếp', icon: Clock },
];

// Tables & Cart Composables
const cartItems = ref<OrderItem[]>([]);
const cartNote = ref('');
const isCartOpen = ref(false);
const isNotified = ref(false);

const tablesComposable = useCashierTables(
    () => props.tablesData,
    cartItems,
    cartNote,
    isCartOpen,
    isNotified,
    toast,
);

const {
    activeTable,
    drawerStep,
    selectedArea,
    selectedStatus,
    areaList,
    filteredTables,
    openTableOrder,
    showSplitModal,
    splitTableId,
    splitItems,
    isSubmittingSplit,
    splitProjection,
    openSplitOrder,
    processSplit,
    tableAction,
    availableTables,
    mergeCandidates,
    selectedMoveTableId,
    selectedMergeTargetOrderId,
    isSubmittingTableAction,
    openMoveTable,
    openMergeTable,
    closeTableAction,
    processMoveTable,
    processMergeTable,
    callPayment,
} = tablesComposable;

const cartComposable = useCashierCart(
    activeTable,
    () => props.products,
    () => props.tablesData,
    toast,
    cartItems,
    cartNote,
    isNotified,
    isCartOpen,
);

const {
    cartBounce,
    isSubmitting,
    totalCartAmount,
    totalCartQty,
    addToCart,
    getCartItemQty,
    handleProductCardClick,
    increaseProductQty,
    decreaseProductQty,
    increaseQty,
    decreaseQty,
    removeItem,
    submitOrder,
    sendToKitchen,
} = cartComposable;
void addToCart;
void cartBounce;

// Payment Composable
const paymentComposable = useCashierPayment(activeTable, isCartOpen, toast);

const {
    showPaymentModal,
    paymentMethod,
    cashReceived,
    searchCustomerPhone,
    isSearchingCustomer,
    foundCustomer,
    loyaltyPointsToRedeem,
    voucherCode,
    isApplyingVoucher,
    bypassRequired,
    bypassMessage,
    bypassCode,
    appliedVoucherName,
    availableVouchers,
    isLoadingVouchers,
    isPaying,
    paymentMethods,
    cashDenominations,
    changeAmount,
    multiPayments,
    multiTotalPaid,
    multiRemainingBalance,
    addMultiPayment,
    removeMultiPayment,
    searchCustomer,
    clearCustomerSelection,
    applyVoucher,
    openPayment,
    processPayment,
} = paymentComposable;

// QR Orders & External Order Management
const confirmingOrderId = ref<number | null>(null);
const updatingExternalId = ref<number | null>(null);

const confirmQrOrder = (orderInput: number | any) => {
    let orderId: number;
    let isTemporary = true;

    if (typeof orderInput === 'object' && orderInput !== null) {
        orderId = orderInput.id;
        isTemporary = orderInput.is_temporary !== false;
    } else {
        orderId = Number(orderInput);
        const found = props.qrOrders?.find((o) => o.id === orderId);

        if (found && found.is_temporary === false) {
            isTemporary = false;
        }
    }

    confirmingOrderId.value = orderId;

    const endpoint = isTemporary
        ? `/api/temporary-orders/${orderId}/confirm`
        : `/orders/${orderId}/confirm-qr`;

    axios
        .post(endpoint)
        .then((res) => {
            if (res.data.success) {
                toast('Đã duyệt đơn QR thành công!');
                router.reload({ only: ['qrOrders', 'tablesData'] });
            }
        })
        .catch((err) => {
            toast(
                err.response?.data?.message || 'Có lỗi khi duyệt đơn QR.',
                'error',
            );
        })
        .finally(() => {
            confirmingOrderId.value = null;
        });
};

const refreshQrOrders = (payload: { message: string; type: 'success' | 'error' }) => {
    toast(payload.message, payload.type);

    if (payload.type === 'success') {
        router.reload({ only: ['qrOrders', 'tablesData'] });
    }
};

const servingItemId = ref<number | null>(null);
const markItemServed = (itemId: number) => {
    servingItemId.value = itemId;
    axios
        .post(`/kitchen/items/${itemId}/serve`)
        .then((res) => {
            if (res.data?.success) {
                toast(res.data.message || 'Đã đánh dấu phục vụ món!');
            } else {
                toast('Đã đánh dấu phục vụ món!');
            }

            router.reload({ only: ['kitchenReadyItems', 'tablesData'] });
        })
        .catch((err) => {
            toast(
                err.response?.data?.message || 'Có lỗi khi đánh dấu phục vụ món.',
                'error',
            );
        })
        .finally(() => {
            servingItemId.value = null;
        });
};

const updateExternalOrderStatus = ({
    orderId,
    status,
}: {
    orderId: number;
    status: string;
}) => {
    updatingExternalId.value = orderId;
    router.patch(
        `/orders/${orderId}/status`,
        { status },
        {
            preserveState: true,
            onSuccess: () => {
                toast('Đã cập nhật trạng thái đơn thành công!');
                router.reload({
                    only: [
                        'externalOrders',
                        'tablesData',
                        'completedHistory',
                        'shiftInfo',
                    ],
                });
            },
            onError: () => toast('Không thể cập nhật trạng thái.', 'error'),
            onFinish: () => {
                updatingExternalId.value = null;
            },
        },
    );
};

// Hủy món trực tiếp từ panel POS. Món đã bắt đầu chế biến sẽ được chuyển
// thành yêu cầu chờ quản lý duyệt ở phía máy chủ.
const cancelItemTarget = ref<OrderItem | null>(null);
const cancelItemReason = ref('Khách bận việc đột xuất');
const cancelItemCustomReason = ref('');
const isCancellingItem = ref(false);
const cancelReasonOptions = [
    'Khách bận việc đột xuất',
    'Khách đổi món',
    'Nhập nhầm món',
];
const customCancelReasonValue = '__custom__';
const effectiveCancelItemReason = computed(() =>
    cancelItemReason.value === customCancelReasonValue
        ? cancelItemCustomReason.value.trim()
        : cancelItemReason.value.trim(),
);

const openCancelItemModal = (item: OrderItem) => {
    if (!item.id) {
        return;
    }

    cancelItemTarget.value = item;
    cancelItemReason.value = cancelReasonOptions[0];
    cancelItemCustomReason.value = '';
};

const closeCancelItemModal = () => {
    cancelItemTarget.value = null;
    cancelItemCustomReason.value = '';
};

const submitCancelItem = () => {
    const orderId = activeTable.value?.active_order?.id;
    const item = cancelItemTarget.value;
    const reason = effectiveCancelItemReason.value;

    if (!orderId || !item?.id || reason.length < 3 || isCancellingItem.value) {
        return;
    }

    isCancellingItem.value = true;
    router.post(
        `/orders/${orderId}/items/${item.id}/cancel`,
        { reason },
        {
            preserveScroll: true,
            onSuccess: () => {
                closeCancelItemModal();
                toast('Đã xử lý yêu cầu hủy món và báo xuống bếp.');
            },
            onError: (errors: Record<string, string | string[]>) => {
                const message = Object.values(errors)[0];
                toast(
                    Array.isArray(message)
                        ? message[0]
                        : String(message || 'Không thể hủy món.'),
                    'error',
                );
            },
            onFinish: () => {
                isCancellingItem.value = false;
            },
        },
    );
};

// Self Service Admin Modals
const showSelfServiceModal = ref(false);
const selfServiceTab = ref<'schedule' | 'leave' | 'complaint'>('schedule');
const regDay = ref('Thứ 2');
const regShiftName = ref('');
const leaveType = ref('annual');
const leaveStart = ref('');
const leaveEnd = ref('');
const leaveReason = ref('');
const complaintTargetId = ref<number | null>(null);
const complaintType = ref('attitude');
const complaintDescription = ref('');

const openSelfService = (tab: 'schedule' | 'leave' | 'complaint') => {
    selfServiceTab.value = tab;
    showSelfServiceModal.value = true;
};

const handleRegisterSchedule = () => {
    if (!props.employee) {
        return;
    }

    router.post(
        '/employees/schedules',
        {
            day: regDay.value,
            employee_name: props.employee.full_name,
            shift_name: regShiftName.value,
        },
        {
            onSuccess: () => {
                toast('Đăng ký lịch làm việc thành công!');
                regShiftName.value = '';
                router.reload({ only: ['weeklySchedules'] });
            },
        },
    );
};

const handleLeaveRequest = () => {
    if (!props.employee) {
        return;
    }

    router.post(
        '/employees/leaves',
        {
            employee_id: props.employee.id,
            leave_type: leaveType.value,
            start_date: leaveStart.value,
            end_date: leaveEnd.value,
            reason: leaveReason.value,
        },
        {
            onSuccess: () => {
                toast('Nộp đơn xin nghỉ thành công! Chờ phê duyệt.');
                leaveReason.value = '';
            },
        },
    );
};

const handleComplaint = () => {
    if (
        !complaintTargetId.value ||
        !complaintType.value ||
        !complaintDescription.value
    ) {
        return;
    }

    router.post(
        '/violations',
        {
            employee_id: complaintTargetId.value,
            violation_type: complaintType.value,
            description: complaintDescription.value,
            is_anonymous: true,
            occurred_at: new Date()
                .toISOString()
                .slice(0, 19)
                .replace('T', ' '),
        },
        {
            onSuccess: () => {
                toast('Gửi khiếu nại ẩn danh thành công!');
                complaintTargetId.value = null;
                complaintType.value = '';
                complaintDescription.value = '';
            },
        },
    );
};

// Clock
const currentTime = ref('');
const currentDate = ref('');
let timer: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
    const updateTime = () => {
        const now = new Date();
        currentTime.value = now.toLocaleTimeString('vi-VN', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        });
        currentDate.value = now.toLocaleDateString('vi-VN', {
            weekday: 'long',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
        });
    };
    updateTime();
    timer = setInterval(updateTime, 1000);
});

onUnmounted(() => {
    if (timer) {
        clearInterval(timer);
    }
});
</script>

<template>
    <Head title="BepsoViet Operational POS" />

    <div
        class="mx-auto flex min-h-screen w-full max-w-none flex-col gap-6 bg-slate-50/50 p-5 xl:p-6 dark:bg-slate-900/40"
    >
        <!-- ── HEADER ────────────────────────────────────────────────── -->
        <CashierHeader
            :employee="props.employee"
            :shift-info="props.shiftInfo"
            :current-time="currentTime"
            :current-date="currentDate"
            :ws-connected="wsConnected"
            @open-self-service="openSelfService"
        />

        <!-- ── NAVIGATION TABS ────────────────────────────────────────── -->
        <div class="flex border-b border-slate-200 dark:border-slate-800">
            <button
                v-for="t in mainTabs"
                :key="t.id"
                @click="activeTab = t.id"
                class="relative flex items-center gap-2 border-b-2 px-6 py-3.5 text-xs font-bold transition-all"
                :class="
                    activeTab === t.id
                        ? 'border-indigo-600 bg-white/40 text-indigo-600 dark:bg-slate-900/40 dark:text-indigo-400'
                        : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300'
                "
            >
                <component :is="t.icon" class="size-4" />
                {{ t.label }}
                <span
                    v-if="t.id === 'qr' && props.qrOrders?.length > 0"
                    class="ml-1 animate-pulse rounded-full bg-rose-500 px-1.5 py-0.5 text-[9px] font-black text-white"
                >
                    {{ props.qrOrders.length }}
                </span>
            </button>
        </div>

        <!-- ── TAB CONTENT ────────────────────────────────────────────── -->
        <main class="flex-1">
            <!-- TAB 1: SƠ ĐỒ BÀN & THỰC ĐƠN MÓN -->
            <template v-if="activeTab === 'tables'">
                <div v-if="!isCartOpen" class="grid grid-cols-1 gap-6">
                    <TableGrid
                        :tables="filteredTables"
                        :area-list="areaList"
                        v-model:selected-area="selectedArea"
                        v-model:selected-status="selectedStatus"
                        :active-table-id="activeTable?.id"
                        :compact="false"
                        @select-table="openTableOrder"
                    />
                </div>

                <div v-else-if="drawerStep === 'select'" class="space-y-6">
                    <div
                        class="flex flex-wrap items-center justify-between gap-4 rounded-3xl border border-indigo-500/30 bg-indigo-950/20 p-5"
                    >
                        <div class="flex items-center gap-3 text-left">
                            <div
                                class="flex size-11 items-center justify-center rounded-2xl bg-indigo-600/20 text-indigo-300"
                            >
                                <Utensils class="size-5" />
                            </div>
                            <div>
                                <p class="text-sm font-black text-slate-100">
                                    Lên món cho Bàn
                                    {{ activeTable?.name || '' }}
                                </p>
                                <p class="text-xs text-slate-400">
                                    Chọn món trong thực đơn, sau đó xác nhận để
                                    xem lại đơn.
                                </p>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="rounded-xl px-3 py-2 text-xs font-bold text-slate-400 transition-colors hover:bg-slate-800 hover:text-slate-100"
                            @click="isCartOpen = false"
                        >
                            Quay lại chọn bàn
                        </button>
                    </div>

                    <ProductGrid
                        :products="props.products"
                        :categories="props.categories"
                        :get-cart-item-qty="getCartItemQty"
                        :compact="false"
                        @click-product="handleProductCardClick"
                        @increase-qty="increaseProductQty"
                        @decrease-qty="decreaseProductQty"
                    />

                    <div
                        class="sticky bottom-4 z-10 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-indigo-500/30 bg-slate-950/95 p-4 shadow-2xl backdrop-blur"
                    >
                        <div class="text-left">
                            <p class="text-sm font-black text-slate-100">
                                Đã chọn {{ totalCartQty }} món
                            </p>
                            <p class="text-xs text-slate-400">
                                {{ totalCartAmount.toLocaleString('vi-VN') }}đ
                            </p>
                        </div>
                        <button
                            type="button"
                            class="rounded-xl bg-indigo-600 px-5 py-3 text-xs font-black text-white shadow-lg shadow-indigo-600/20 transition-colors hover:bg-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="cartItems.length === 0"
                            @click="drawerStep = 'confirm'"
                        >
                            Xác nhận và xem món đã chọn
                        </button>
                    </div>
                </div>

                <OrderReviewPanel
                    v-if="isCartOpen && drawerStep === 'confirm'"
                    :active-table="activeTable"
                    :cart-items="cartItems"
                    v-model:cart-note="cartNote"
                    :total-cart-amount="totalCartAmount"
                    :total-cart-qty="totalCartQty"
                    :is-notified="isNotified"
                    :is-submitting="isSubmitting"
                    :can-process-payments="can('process_payments')"
                    :can-split-orders="can('split_orders')"
                    :can-manage-table-orders="
                        can('manage_orders') || can('split_orders')
                    "
                    @update:drawer-step="drawerStep = $event"
                    @increase-qty="increaseQty"
                    @decrease-qty="decreaseQty"
                    @remove-item="removeItem"
                    @cancel-item="openCancelItemModal"
                    @submit-order="submitOrder"
                    @open-payment="openPayment"
                    @call-payment="callPayment"
                    @send-to-kitchen="sendToKitchen"
                    @open-split-order="openSplitOrder"
                    @open-move-table="openMoveTable"
                    @open-merge-table="openMergeTable"
                />
            </template>

            <!-- TAB 2: ĐƠN HÀNG QR & NGOẠI SÀN -->
            <div v-else-if="activeTab === 'qr'">
                <QrOrdersPanel
                      :qr-orders="props.qrOrders"
                      :external-orders="props.externalOrders"
                      :products="props.products"
                      :confirming-order-id="confirmingOrderId"
                      :updating-external-id="updatingExternalId"
                      @confirm-qr-order="confirmQrOrder"
                      @update-external-order-status="updateExternalOrderStatus"
                      @refresh-qr-orders="refreshQrOrders"
                  />
            </div>

            <!-- TAB 3: LỊCH SỬ BẾP & HOÀN TẤT -->
            <div
                v-else-if="activeTab === 'history'"
                class="flex flex-col gap-6 text-left"
            >
                <!-- Thống báo Món Bếp đã làm xong (Chờ phục vụ) -->
                <div class="rounded-3xl border border-indigo-100 bg-white p-6 shadow-sm dark:border-indigo-900/40 dark:bg-slate-900">
                    <div class="mb-4 flex items-center justify-between border-b border-slate-100 pb-4 dark:border-slate-800">
                        <div class="flex items-center gap-2">
                            <Utensils class="size-5 animate-bounce text-emerald-500" />
                            <div>
                                <h3 class="text-base font-black text-slate-800 dark:text-slate-100">
                                    Thông báo món Bếp vừa chế biến xong
                                </h3>
                                <p class="text-xs text-muted-foreground">
                                    Danh sách món đã nấu xong, chờ nhân viên bưng lên bàn cho khách
                                </p>
                            </div>
                        </div>
                        <span class="rounded-xl bg-emerald-50 px-3 py-1 font-mono text-xs font-bold text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400">
                            {{ props.kitchenReadyItems?.length || 0 }} món sẵn sàng
                        </span>
                    </div>

                    <div
                        v-if="!props.kitchenReadyItems || props.kitchenReadyItems.length === 0"
                        class="py-8 text-center text-xs font-bold text-slate-400"
                    >
                        🍳 Hiện không có món nào đang chờ bưng lên bàn.
                    </div>

                    <div v-else class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="item in props.kitchenReadyItems"
                            :key="item.id"
                            class="flex flex-col justify-between rounded-2xl border border-emerald-200 bg-emerald-50/40 p-4 text-left dark:border-emerald-900/40 dark:bg-emerald-950/20"
                        >
                            <div>
                                <div class="flex items-center justify-between">
                                    <span class="rounded-lg bg-emerald-500 px-2 py-0.5 text-[10px] font-extrabold text-white">
                                        ĐÃ NẤU XONG
                                    </span>
                                    <span class="font-mono text-xs font-bold text-slate-500">
                                        {{ item.prepared_at || '' }}
                                    </span>
                                </div>

                                <div class="mt-2 text-sm font-black text-slate-900 dark:text-slate-100">
                                    Bàn {{ item.table_name || 'Mang về' }}
                                </div>

                                <div class="mt-1 text-xs font-extrabold text-emerald-700 dark:text-emerald-300">
                                    {{ item.quantity }}x {{ item.product_name }}
                                </div>

                                <p v-if="item.notes" class="mt-1 text-[11px] italic text-amber-600 dark:text-amber-400">
                                    Ghi chú: {{ item.notes }}
                                </p>

                                <div class="mt-2 text-[10px] text-muted-foreground">
                                    Bếp: {{ item.prepared_by_name || 'Bếp' }}
                                </div>
                            </div>

                            <Button
                                size="sm"
                                class="mt-3 w-full rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs"
                                :disabled="servingItemId === item.id"
                                @click="markItemServed(item.id)"
                            >
                                {{ servingItemId === item.id ? 'Đang cập nhật...' : '✓ Đã bưng lên bàn' }}
                            </Button>
                        </div>
                    </div>
                </div>

                <!-- Lịch sử đơn hàng hoàn tất -->
                <div class="rounded-3xl border bg-white p-6 shadow-sm dark:bg-slate-900">
                    <h3 class="mb-4 text-base font-black text-slate-800 dark:text-slate-100">
                        Lịch sử đơn hàng hoàn tất trong ca
                    </h3>
                    <div
                        v-if="!props.completedHistory || props.completedHistory.length === 0"
                        class="py-8 text-center text-xs font-bold text-slate-400"
                    >
                        Chưa có đơn hàng nào hoàn tất trong ca này.
                    </div>
                    <div v-else class="flex flex-col gap-2">
                        <div
                            v-for="order in props.completedHistory"
                            :key="order.id"
                            class="flex items-center justify-between border-b pb-2 text-xs"
                        >
                            <span class="font-bold">
                                #{{ order.order_number }} - Bàn {{ order.table_name || 'Mang về' }}
                            </span>
                            <span class="font-mono font-bold text-emerald-600">
                                {{ order.total_amount?.toLocaleString('vi-VN') }}đ
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- ── SIDE DRAWER GIỎ HÀNG BÊN PHẢI ──────────────────────────── -->
        <!-- ── MODAL THANH TOÁN ────────────────────────────────────────── -->
        <PaymentModal
            v-model:show-payment-modal="showPaymentModal"
            :active-table="activeTable"
            v-model:payment-method="paymentMethod"
            v-model:cash-received="cashReceived"
            :change-amount="changeAmount"
            v-model:search-customer-phone="searchCustomerPhone"
            :is-searching-customer="isSearchingCustomer"
            :found-customer="foundCustomer"
            v-model:loyalty-points-to-redeem="loyaltyPointsToRedeem"
            v-model:voucher-code="voucherCode"
            :available-vouchers="availableVouchers"
            :is-loading-vouchers="isLoadingVouchers"
            :is-applying-voucher="isApplyingVoucher"
            :bypass-required="bypassRequired"
            :bypass-message="bypassMessage"
            v-model:bypass-code="bypassCode"
            :applied-voucher-name="appliedVoucherName"
            :is-paying="isPaying"
            :payment-methods="paymentMethods"
            :cash-denominations="cashDenominations"
            :multi-payments="multiPayments"
            :multi-total-paid="multiTotalPaid"
            :multi-remaining-balance="multiRemainingBalance"
            @search-customer="searchCustomer"
            @clear-customer-selection="clearCustomerSelection"
            @apply-voucher="applyVoucher"
            @process-payment="processPayment"
            @add-multi-payment="addMultiPayment"
            @remove-multi-payment="removeMultiPayment"
        />

        <!-- ── MODAL TÁCH ĐƠN ──────────────────────────────────────────── -->
        <SplitOrderModal
            v-model:show-split-modal="showSplitModal"
            :active-table="activeTable"
            :tables-data="props.tablesData"
            v-model:split-table-id="splitTableId"
            :split-items="splitItems"
            :is-submitting-split="isSubmittingSplit"
            :split-projection="splitProjection"
            @process-split="processSplit"
        />

        <TableActionModal
            :open="tableAction !== null"
            :mode="tableAction || 'move'"
            :active-table="activeTable"
            :available-tables="availableTables"
            :merge-candidates="mergeCandidates"
            v-model:selected-table-id="selectedMoveTableId"
            v-model:selected-target-order-id="selectedMergeTargetOrderId"
            :is-submitting="isSubmittingTableAction"
            @update:open="
                (open: boolean) => {
                    if (!open) closeTableAction();
                }
            "
            @submit="
                tableAction === 'move'
                    ? processMoveTable()
                    : processMergeTable()
            "
        />

        <!-- MODAL HỦY MÓN TỪ POS -->
        <Teleport to="body">
        <div
            v-if="cancelItemTarget"
            class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm"
            @click.self="closeCancelItemModal"
        >
            <div
                class="w-full max-w-md rounded-3xl border border-rose-500/30 bg-slate-900 p-6 text-left shadow-2xl"
                role="dialog"
                aria-modal="true"
                aria-labelledby="cancel-item-title"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p
                            id="cancel-item-title"
                            class="text-lg font-black text-slate-100"
                        >
                            Hủy món
                        </p>
                        <p class="mt-1 text-xs leading-5 text-slate-400">
                            {{ cancelItemTarget.product_name }} ·
                            {{ cancelItemTarget.quantity }} món
                        </p>
                    </div>
                    <button
                        type="button"
                        class="rounded-xl p-2 text-slate-400 transition-colors hover:bg-slate-800 hover:text-white"
                        :disabled="isCancellingItem"
                        aria-label="Đóng"
                        @click="closeCancelItemModal"
                    >
                        <XCircle class="size-5" />
                    </button>
                </div>

                <div class="mt-5 space-y-3">
                    <label
                        for="cashier-cancel-reason"
                        class="block text-xs font-bold text-slate-300"
                    >
                        Lý do hủy món <span class="text-rose-400">*</span>
                    </label>
                    <select
                        id="cashier-cancel-reason"
                        v-model="cancelItemReason"
                        class="h-11 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 text-sm text-slate-100 outline-none transition-colors focus:border-rose-500"
                    >
                        <option
                            v-for="reason in cancelReasonOptions"
                            :key="reason"
                            :value="reason"
                        >
                            {{ reason }}
                        </option>
                        <option :value="customCancelReasonValue">Khác</option>
                    </select>
                    <textarea
                        v-if="cancelItemReason === customCancelReasonValue"
                        v-model="cancelItemCustomReason"
                        rows="3"
                        maxlength="500"
                        placeholder="Nhập lý do hủy..."
                        class="w-full resize-none rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-slate-100 outline-none placeholder:text-slate-500 focus:border-rose-500"
                    />
                    <p class="text-[11px] leading-5 text-slate-500">
                        Nếu món đã bắt đầu chế biến, yêu cầu sẽ chuyển đến quản lý
                        duyệt và ghi nhận tổn thất nếu được duyệt.
                    </p>
                </div>

                <div class="mt-6 grid grid-cols-2 gap-3">
                    <button
                        type="button"
                        class="h-11 rounded-xl border border-slate-700 text-xs font-black text-slate-300 transition-colors hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="isCancellingItem"
                        @click="closeCancelItemModal"
                    >
                        Để lại
                    </button>
                    <button
                        type="button"
                        class="h-11 rounded-xl bg-rose-600 text-xs font-black text-white transition-colors hover:bg-rose-500 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="effectiveCancelItemReason.length < 3 || isCancellingItem"
                        @click="submitCancelItem"
                    >
                        {{ isCancellingItem ? 'Đang xử lý...' : 'Xác nhận hủy món' }}
                    </button>
                </div>
            </div>
        </div>
        </Teleport>

        <!-- ── MODAL CỔNG HÀNH CHÍNH TỰ PHỤC VỤ ───────────────────────── -->
        <SelfServiceModal
            v-model:show-self-service-modal="showSelfServiceModal"
            v-model:self-service-tab="selfServiceTab"
            :employee="props.employee"
            :colleagues="props.colleagues"
            v-model:reg-day="regDay"
            v-model:reg-shift-name="regShiftName"
            v-model:leave-type="leaveType"
            v-model:leave-start="leaveStart"
            v-model:leave-end="leaveEnd"
            v-model:leave-reason="leaveReason"
            v-model:complaint-target-id="complaintTargetId"
            v-model:complaint-type="complaintType"
            v-model:complaint-description="complaintDescription"
            @handle-register-schedule="handleRegisterSchedule"
            @handle-leave-request="handleLeaveRequest"
            @handle-complaint="handleComplaint"
        />

        <!-- ── TOAST NOTIFICATIONS ────────────────────────────────────── -->
        <div
            class="pointer-events-none fixed top-6 left-1/2 z-[100] flex -translate-x-1/2 flex-col items-center gap-2"
        >
            <transition-group name="toast">
                <div
                    v-for="t in toasts"
                    :key="t.id"
                    class="animate-fade-in pointer-events-auto flex max-w-xs min-w-56 items-center gap-2.5 rounded-2xl px-4 py-3 text-xs font-bold shadow-xl"
                    :class="
                        t.type === 'success'
                            ? 'bg-emerald-600 text-white'
                            : 'bg-rose-600 text-white'
                    "
                >
                    <CheckIcon
                        v-if="t.type === 'success'"
                        class="size-4 shrink-0"
                    />
                    <XCircle v-else class="size-4 shrink-0" />
                    <span class="leading-tight">{{ t.message }}</span>
                </div>
            </transition-group>
        </div>
    </div>
</template>

<style scoped>
@keyframes fade-in {
    from {
        opacity: 0;
        transform: scale(0.95);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}
.animate-fade-in {
    animation: fade-in 0.2s ease-out forwards;
}
.toast-enter-active {
    animation: fade-in 0.2s ease-out;
}
.toast-leave-active {
    animation: fade-in 0.2s ease-in reverse;
}
.toast-move {
    transition: transform 0.2s ease;
}
</style>
