<script setup lang="ts">
import { Head, usePage, router } from '@inertiajs/vue3';
import axios from 'axios';
import { Utensils, Sparkles, Clock, CheckIcon, XCircle } from 'lucide-vue-next';
import {
    ref,
    computed,
    onMounted,
    onUnmounted,
    defineAsyncComponent,
} from 'vue';

// TypeScript Types

// Composables

// Components
import CartDrawer from './components/CartDrawer.vue';
import CashierHeader from './components/CashierHeader.vue';
import PaymentModal from './components/PaymentModal.vue';
import ProductGrid from './components/ProductGrid.vue';
import QrOrdersPanel from './components/QrOrdersPanel.vue';
import SelfServiceModal from './components/SelfServiceModal.vue';
import SplitOrderModal from './components/SplitOrderModal.vue';
import TableGrid from './components/TableGrid.vue';
import { useCashierCart } from './composables/useCashierCart';
import { useCashierPayment } from './composables/useCashierPayment';
import { useCashierRealtime } from './composables/useCashierRealtime';
import { useCashierTables } from './composables/useCashierTables';
import type {
    TableItem,
    ProductItem,
    CategoryItem,
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
const cartItems = ref([]);
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
} = tablesComposable;

const cartComposable = useCashierCart(
    activeTable,
    () => props.products,
    () => props.tablesData,
    toast,
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
    isPaying,
    paymentMethods,
    cashDenominations,
    changeAmount,
    searchCustomer,
    clearCustomerSelection,
    openPayment,
    processPayment,
} = paymentComposable;

// QR Orders & External Order Management
const confirmingOrderId = ref<number | null>(null);
const updatingExternalId = ref<number | null>(null);

const confirmQrOrder = (orderId: number) => {
    confirmingOrderId.value = orderId;
    axios
        .post(`/orders/${orderId}/confirm-qr`)
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
        class="mx-auto flex min-h-screen w-full max-w-7xl flex-col gap-6 bg-slate-50/50 p-6 dark:bg-slate-900/40"
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
            <div
                v-if="activeTab === 'tables'"
                class="grid grid-cols-1 gap-6 lg:grid-cols-12"
            >
                <div class="flex flex-col gap-6 lg:col-span-7">
                    <TableGrid
                        :tables="filteredTables"
                        :area-list="areaList"
                        v-model:selected-area="selectedArea"
                        :active-table-id="activeTable?.id"
                        @select-table="openTableOrder"
                    />

                    <ProductGrid
                        :products="props.products"
                        :categories="props.categories"
                        :get-cart-item-qty="getCartItemQty"
                        @click-product="handleProductCardClick"
                        @increase-qty="increaseProductQty"
                        @decrease-qty="decreaseProductQty"
                    />
                </div>
            </div>

            <!-- TAB 2: ĐƠN HÀNG QR & NGOẠI SÀN -->
            <div v-else-if="activeTab === 'qr'">
                <QrOrdersPanel
                    :qr-orders="props.qrOrders"
                    :external-orders="props.externalOrders"
                    :confirming-order-id="confirmingOrderId"
                    :updating-external-id="updatingExternalId"
                    @confirm-qr-order="confirmQrOrder"
                    @update-external-order-status="updateExternalOrderStatus"
                />
            </div>

            <!-- TAB 3: LỊCH SỬ BẾP & HOÀN TẤT -->
            <div
                v-else-if="activeTab === 'history'"
                class="rounded-3xl border bg-white p-6 shadow-sm dark:bg-slate-900"
            >
                <h3
                    class="mb-4 text-base font-black text-slate-800 dark:text-slate-100"
                >
                    Lịch sử đơn hàng hoàn tất trong ca
                </h3>
                <div
                    v-if="props.completedHistory?.length === 0"
                    class="py-12 text-center text-xs font-bold text-slate-400"
                >
                    Chưa có đơn hàng nào hoàn tất trong ca này.
                </div>
                <div v-else class="flex flex-col gap-2">
                    <div
                        v-for="order in props.completedHistory"
                        :key="order.id"
                        class="flex items-center justify-between border-b pb-2 text-xs"
                    >
                        <span class="font-bold"
                            >#{{ order.order_number }} - Bàn
                            {{ order.table_name || 'Mang về' }}</span
                        >
                        <span class="font-mono font-bold text-emerald-600"
                            >{{
                                order.total_amount?.toLocaleString('vi-VN')
                            }}đ</span
                        >
                    </div>
                </div>
            </div>
        </main>

        <!-- ── SIDE DRAWER GIỎ HÀNG BÊN PHẢI ──────────────────────────── -->
        <CartDrawer
            v-model:is-cart-open="isCartOpen"
            :active-table="activeTable"
            v-model:drawer-step="drawerStep"
            :cart-items="cartItems"
            v-model:cart-note="cartNote"
            :total-cart-amount="totalCartAmount"
            :total-cart-qty="totalCartQty"
            :is-notified="isNotified"
            :is-submitting="isSubmitting"
            :can-process-payments="can('process_payments')"
            :can-split-orders="can('split_orders')"
            @increase-qty="increaseQty"
            @decrease-qty="decreaseQty"
            @remove-item="removeItem"
            @submit-order="submitOrder"
            @open-payment="openPayment"
            @send-to-kitchen="sendToKitchen"
            @open-split-order="openSplitOrder"
        />

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
            :is-paying="isPaying"
            :payment-methods="paymentMethods"
            :cash-denominations="cashDenominations"
            @search-customer="searchCustomer"
            @clear-customer-selection="clearCustomerSelection"
            @process-payment="processPayment"
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
            class="pointer-events-none fixed right-6 bottom-6 z-[70] flex flex-col gap-2"
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
