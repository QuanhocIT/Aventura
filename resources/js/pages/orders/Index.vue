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
    Sparkles,
    Search,
    Layers,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
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
    subtotal?: number;
    discount_amount?: number;
    total_amount: number;
    items_count: number;
    created_at: string;
    completed_at: string | null;
    items?: {
        id: number;
        product_name: string;
        quantity: number;
        status: string;
        notes: string | null;
    }[];
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
    filters: {
        status: string;
        date: string;
        search?: string;
        history?: boolean;
    };
    activeShiftStats?: {
        shift_name: string;
        check_in_at: string;
        total_orders: number;
        total_revenue: number;
        cash_revenue: number;
        transfer_revenue: number;
    } | null;
    tables: { id: number; name: string }[];
    cancelledLogs?: {
        id: number;
        order_number: string;
        cancelled_by: string;
        total_amount: number;
        table_name: string;
        area_name: string;
        items: { product_name: string; quantity: number; price: number }[];
        cancelled_at: string;
    }[];
}>();

const dateInput = ref(props.filters.date);
const activeStatus = ref(props.filters.status);
const searchQuery = ref(props.filters.search || '');
const viewMode = ref(props.filters.history ? 'history' : 'today');
const expandedOrderId = ref<number | null>(null);
const isSimulating = ref(false);
const updatingItemStatus = ref<number | null>(null);

const isSplitModalOpen = ref(false);
const selectedOrderToSplit = ref<Order | null>(null);
const selectedTableId = ref<number | null>(null);
const itemsToSplit = ref<
    {
        order_item_id: number;
        quantity: number;
        max_quantity: number;
        product_name: string;
    }[]
>([]);

const openSplitModal = (order: Order) => {
    selectedOrderToSplit.value = order;
    selectedTableId.value = null;
    itemsToSplit.value = (order.items || []).map((item) => ({
        order_item_id: item.id,
        quantity: 0,
        max_quantity: item.quantity,
        product_name: item.product_name,
    }));
    isSplitModalOpen.value = true;
};

const totalSplitItems = computed(() =>
    itemsToSplit.value.reduce((sum, item) => sum + item.quantity, 0),
);
const isSplitFormValid = computed(() => {
    return selectedTableId.value !== null && totalSplitItems.value > 0;
});

const handleSplitSubmit = () => {
    if (!selectedOrderToSplit.value || !selectedTableId.value) {
        return;
    }

    const payload = {
        table_id: selectedTableId.value,
        items: itemsToSplit.value
            .filter((item) => item.quantity > 0)
            .map((item) => ({
                order_item_id: item.order_item_id,
                quantity: item.quantity,
            })),
    };

    router.post(`/orders/${selectedOrderToSplit.value.id}/split`, payload, {
        onSuccess: () => {
            isSplitModalOpen.value = false;
            selectedOrderToSplit.value = null;
            selectedTableId.value = null;
            itemsToSplit.value = [];
            toast.success(
                'Đã tách đơn hàng thành công và cảnh báo đỏ đã được thiết lập!',
            );
        },
        onError: (err: any) => {
            toast.error(err.message || 'Có lỗi xảy ra khi tách đơn.');
        },
    });
};

const page = usePage();
const isCashier = computed(() => {
    const roles = (page.props.auth?.user as any)?.roles || [];

    return roles.includes('cashier');
});

const isOwnerOrManager = computed(() => {
    const roles = (page.props.auth?.user as any)?.roles || [];

    return roles.includes('owner') || roles.includes('manager');
});

const statusChips = computed(() => {
    const chips: Record<string, string> = {
        all: 'Tất cả',
        pending: 'Chờ xác nhận',
        confirmed: 'Đã xác nhận',
        preparing: 'Đang chế biến',
        completed: 'Hoàn thành',
        cancelled: 'Đã hủy',
    };
    if (isOwnerOrManager.value) {
        chips.cancelled_spam = 'Nhật ký hủy đơn ảo';
    }
    return chips;
});

const voucherCodes = ref<Record<number, string>>({});

const handleApplyVoucher = async (orderId: number) => {
    const code = voucherCodes.value[orderId];

    if (!code || !code.trim()) {
        toast.error('Vui lòng nhập mã giảm giá.');

        return;
    }

    try {
        const response = await axios.post('/api/promotions/apply', {
            order_id: orderId,
            code: code.trim(),
        });
        toast.success(
            response.data.message || 'Áp dụng mã giảm giá thành công!',
        );
        voucherCodes.value[orderId] = '';
        router.reload({ only: ['orders'] });
    } catch (e: any) {
        console.error(e);
        const errorMsg =
            e.response?.data?.message ||
            'Có lỗi xảy ra khi áp dụng mã giảm giá.';
        toast.error(errorMsg);
    }
};

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
        const response = await axios.post('/api/orders/third-party/simulate', {
            source,
        });

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
        const response = await axios.patch(`/orders/items/${item.id}/status`, {
            status: newStatus,
        });

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
    pending: {
        label: 'Chờ chế biến',
        class: 'bg-amber-50 text-amber-700 border-amber-200/50 dark:bg-amber-950/20 dark:text-amber-400',
    },
    sent: {
        label: 'Đã gửi bếp',
        class: 'bg-sky-50 text-sky-700 border-sky-200/50 dark:bg-sky-950/20 dark:text-sky-400',
    },
    preparing: {
        label: 'Đang nấu',
        class: 'bg-violet-50 text-violet-750 border-violet-200/50 dark:bg-violet-950/20 dark:text-violet-400',
    },
    served: {
        label: 'Đã phục vụ',
        class: 'bg-emerald-50 text-emerald-700 border-emerald-200/50 dark:bg-emerald-950/20 dark:text-emerald-400',
    },
    cancelled: {
        label: 'Đã hủy',
        class: 'bg-rose-50 text-rose-700 border-rose-200/50 dark:bg-rose-950/20 dark:text-rose-400',
    },
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
        await axios.post(`/orders/${order.id}/confirm-qr`);

        const itemNames = order.items
            ? order.items.map((i) => i.product_name).filter(Boolean)
            : [];
        const response = await axios.post('/api/promotions/upsell-suggestion', {
            items: itemNames,
        });

        aiSuggestionText.value =
            response.data.suggestion ||
            'Hãy chọn thêm các món ăn đặc sắc từ thực đơn.';
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

const isCancellingSpam = ref<number | null>(null);

const handleCancelSpamOrder = async (order: Order) => {
    if (!confirm('Bạn có chắc chắn muốn hủy và báo cáo đơn hàng ảo này không? Đơn này sẽ bị xóa khỏi hệ thống.')) {
        return;
    }

    isCancellingSpam.value = order.id;
    try {
        const response = await axios.post(`/orders/${order.id}/cancel-spam`);
        if (response.data.success) {
            toast.warning('Đã hủy đơn hàng ảo và báo cáo lên Quản lý thành công.');
            router.reload({ only: ['orders', 'summary'] });
        } else {
            toast.error(response.data.message || 'Không thể hủy đơn.');
        }
    } catch (e: any) {
        console.error(e);
        toast.error('Có lỗi xảy ra khi hủy đơn hàng ảo.');
    } finally {
        isCancellingSpam.value = null;
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
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <!-- Shift Performance Widget -->
            <Card
                class="relative overflow-hidden rounded-2xl border border-violet-100/50 bg-white/60 shadow-xs backdrop-blur-md md:col-span-2 dark:border-slate-800 dark:bg-slate-900/40"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-3"
                >
                    <div>
                        <CardTitle
                            class="flex items-center gap-2 text-sm font-bold text-slate-800 dark:text-slate-100"
                        >
                            <Sparkles
                                class="size-4 animate-pulse text-violet-600"
                            />
                            Hiệu Suất Ca Làm Việc
                        </CardTitle>
                        <CardDescription class="text-[10px] text-slate-400">
                            Số liệu doanh thu và năng suất làm việc của bạn
                            trong ca trực hiện tại.
                        </CardDescription>
                    </div>
                    <span
                        v-if="activeShiftStats"
                        class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2 py-0.5 text-[9px] font-bold text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-400"
                    >
                        <span
                            class="h-1.5 w-1.5 animate-ping rounded-full bg-emerald-500"
                        ></span>
                        Ca: {{ activeShiftStats.shift_name }}
                    </span>
                </CardHeader>
                <CardContent class="pb-4">
                    <div
                        v-if="activeShiftStats"
                        class="grid grid-cols-2 gap-4 sm:grid-cols-4"
                    >
                        <div
                            class="dark:border-slate-850 rounded-xl border border-slate-100 bg-slate-50/50 p-2.5 dark:bg-slate-950/20"
                        >
                            <p
                                class="text-[9px] font-bold tracking-wider text-slate-400 uppercase"
                            >
                                Đã Check-in
                            </p>
                            <p
                                class="mt-1 font-mono text-base font-black text-slate-800 dark:text-slate-200"
                            >
                                {{ activeShiftStats.check_in_at }}
                            </p>
                        </div>
                        <div
                            class="dark:border-slate-850 rounded-xl border border-slate-100 bg-slate-50/50 p-2.5 dark:bg-slate-950/20"
                        >
                            <p
                                class="text-[9px] font-bold tracking-wider text-slate-400 uppercase"
                            >
                                Đơn Hoàn Thành
                            </p>
                            <p
                                class="mt-1 font-mono text-base font-black text-slate-800 dark:text-slate-200"
                            >
                                {{ activeShiftStats.total_orders }}
                            </p>
                        </div>
                        <div
                            class="dark:border-slate-850 rounded-xl border border-slate-100 bg-slate-50/50 p-2.5 dark:bg-slate-950/20"
                        >
                            <p
                                class="text-[9px] font-bold tracking-wider text-slate-400 uppercase"
                            >
                                Tiền Mặt
                            </p>
                            <p
                                class="mt-1.5 font-mono text-xs font-bold text-emerald-600 dark:text-emerald-400"
                            >
                                {{
                                    formatCurrency(
                                        activeShiftStats.cash_revenue,
                                    )
                                }}
                            </p>
                        </div>
                        <div
                            class="dark:border-slate-850 rounded-xl border border-slate-100 bg-slate-50/50 p-2.5 dark:bg-slate-950/20"
                        >
                            <p
                                class="text-[9px] font-bold tracking-wider text-slate-400 uppercase"
                            >
                                Chuyển Khoản
                            </p>
                            <p
                                class="mt-1.5 font-mono text-xs font-bold text-violet-600 dark:text-violet-400"
                            >
                                {{
                                    formatCurrency(
                                        activeShiftStats.transfer_revenue,
                                    )
                                }}
                            </p>
                        </div>
                    </div>
                    <div
                        v-else
                        class="flex flex-col items-center justify-center py-4 text-center"
                    >
                        <AlertCircle
                            class="size-6 text-slate-300 dark:text-slate-700"
                        />
                        <p
                            class="mt-1 text-xs font-semibold text-slate-500 dark:text-slate-400"
                        >
                            Bạn chưa check-in ca trực hôm nay.
                        </p>
                        <p class="text-[10px] text-slate-400">
                            Chuyển sang tab Lịch biểu/Chấm công để check-in và
                            bắt đầu theo dõi doanh thu.
                        </p>
                    </div>
                </CardContent>
            </Card>

            <!-- Developer Sandbox / Simulator -->
            <Card
                class="flex flex-col justify-between rounded-2xl border border-dashed border-slate-200 bg-slate-50/20 p-4 shadow-xs dark:border-slate-800 dark:bg-slate-950/5"
            >
                <div>
                    <h3
                        class="flex items-center gap-1.5 text-[10px] font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400"
                    >
                        <Layers class="size-3.5 text-violet-500" />
                        Simulator Sandbox
                    </h3>
                    <p class="mt-1 text-[9px] leading-relaxed text-slate-400">
                        Mô phỏng đơn hàng từ ứng dụng bên thứ ba (GrabFood,
                        ShopeeFood) được gửi đến hệ thống để nhân viên tiếp
                        nhận.
                    </p>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-2">
                    <Button
                        size="sm"
                        variant="outline"
                        :disabled="isSimulating"
                        @click="simulateThirdParty('GrabFood')"
                        class="rounded-xl border-emerald-200 bg-emerald-50/20 py-1.5 text-[10px] font-bold text-emerald-700 hover:bg-emerald-50 hover:text-emerald-800 dark:border-emerald-900/40 dark:text-emerald-400"
                    >
                        + GrabFood
                    </Button>
                    <Button
                        size="sm"
                        variant="outline"
                        :disabled="isSimulating"
                        @click="simulateThirdParty('ShopeeFood')"
                        class="rounded-xl border-orange-200 bg-orange-50/20 py-1.5 text-[10px] font-bold text-orange-700 hover:bg-orange-50 hover:text-orange-800 dark:border-orange-900/40 dark:text-orange-400"
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
        <div
            class="flex flex-col gap-4 rounded-xl border border-border bg-card p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :class="
                        viewMode === 'today'
                            ? 'text-violet-750 hover:text-violet-850 border-violet-200/50 bg-violet-50 hover:bg-violet-100 dark:bg-violet-950/20 dark:text-violet-400'
                            : ''
                    "
                    @click="toggleViewMode('today')"
                >
                    Đơn Hôm Nay
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    :class="
                        viewMode === 'history'
                            ? 'text-violet-750 hover:text-violet-850 border-violet-200/50 bg-violet-50 hover:bg-violet-100 dark:bg-violet-950/20 dark:text-violet-400'
                            : ''
                    "
                    @click="toggleViewMode('history')"
                >
                    Lịch Sử Đơn Hàng
                </Button>
            </div>

            <div class="relative max-w-sm flex-1">
                <Search
                    class="absolute top-3 left-3 size-4 text-muted-foreground"
                />
                <input
                    type="text"
                    v-model="searchQuery"
                    placeholder="Tìm theo mã đơn, bàn, đối tác..."
                    @keyup.enter="applyFilters"
                    class="h-10 w-full rounded-md border border-input bg-background pr-8 pl-9 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                />
                <button
                    v-if="searchQuery"
                    @click="clearSearch"
                    class="absolute top-3 right-3 text-xs font-semibold text-muted-foreground hover:text-foreground"
                >
                    Xóa
                </button>
            </div>
        </div>

        <!-- Status filter chips -->
        <div class="flex flex-wrap gap-2">
            <button
                v-for="(label, key) in statusChips"
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
                    {{ activeStatus === 'cancelled_spam' ? 'Nhật ký hủy đơn ảo hôm nay' : 'Danh sách đơn hàng' }}
                    <span class="ml-1 text-sm font-normal text-muted-foreground">
                        ({{ activeStatus === 'cancelled_spam' ? (cancelledLogs?.length || 0) : orders.length }} đơn)
                    </span>
                </CardTitle>
            </CardHeader>
            <CardContent class="p-0">
                <div v-if="activeStatus === 'cancelled_spam'" class="p-0">
                    <div v-if="cancelledLogs && cancelledLogs.length" class="divide-y divide-slate-100 dark:divide-slate-800">
                        <div v-for="log in cancelledLogs" :key="log.id" class="px-5 py-4 transition-colors hover:bg-slate-50/50 dark:hover:bg-slate-900/30">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-md bg-rose-50 px-2 py-0.5 text-xs font-bold text-rose-700 border border-rose-150 dark:bg-rose-950/20 dark:text-rose-400 dark:border-rose-900/40">
                                            {{ log.table_name }}
                                        </span>
                                        <span class="text-xs text-slate-400 font-medium">
                                            {{ log.area_name }}
                                        </span>
                                        <span class="font-mono text-xs font-semibold text-slate-500">
                                            #{{ log.order_number }}
                                        </span>
                                    </div>
                                    <div class="mt-1 flex items-center gap-3 text-xs text-muted-foreground">
                                        <span>Thời gian hủy: <strong class="text-slate-700 dark:text-slate-300">{{ log.cancelled_at }}</strong></span>
                                        <span>•</span>
                                        <span>Người hủy: <strong class="text-slate-700 dark:text-slate-300">{{ log.cancelled_by }}</strong></span>
                                    </div>
                                </div>

                                <div class="text-left sm:text-right shrink-0">
                                    <p class="font-mono text-sm font-black text-rose-600 dark:text-rose-400">
                                        {{ formatCurrency(log.total_amount) }}
                                    </p>
                                    <span class="text-[9px] font-bold uppercase tracking-wider text-slate-400 block mt-0.5">
                                        HỦY BỞI NHÂN VIÊN
                                    </span>
                                </div>
                            </div>

                            <div class="mt-3 rounded-xl border bg-slate-50/50 p-3 dark:bg-slate-900/50 dark:border-slate-850">
                                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Món ăn bị hủy</span>
                                <div class="grid grid-cols-1 gap-1.5 sm:grid-cols-2 text-xs">
                                    <div v-for="(item, idx) in log.items" :key="idx" class="flex justify-between border-b border-dashed pb-1 last:border-0 last:pb-0 dark:border-slate-800">
                                        <span class="font-semibold text-slate-750 dark:text-slate-300">
                                            {{ item.product_name }}
                                        </span>
                                        <span class="font-mono font-bold text-slate-500 pr-1">
                                            x{{ item.quantity }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="flex flex-col items-center justify-center py-12 px-5 text-center text-muted-foreground">
                        <p class="text-sm font-semibold">Chưa có nhật ký hủy đơn nào trong ngày hôm nay.</p>
                        <p class="text-xs text-slate-400 mt-0.5">Tất cả đơn hàng QR bị nhân viên từ chối sẽ hiển thị tại đây để đối chiếu kiểm toán.</p>
                    </div>
                </div>

                <div
                    v-else-if="orders.length"
                    class="divide-y divide-slate-100 dark:divide-slate-800"
                >
                    <div
                        v-for="o in orders"
                        :key="o.id"
                        class="border-b border-slate-100 last:border-0 dark:border-slate-800"
                    >
                        <!-- Order Main Row -->
                        <div
                            @click="
                                expandedOrderId =
                                    expandedOrderId === o.id ? null : o.id
                            "
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
                            <div
                                class="flex shrink-0 items-center gap-2"
                                @click.stop
                            >
                                <span
                                    class="rounded-full px-2 py-1 text-[10px] font-semibold"
                                    :class="statusConfig[o.status]?.bg"
                                >
                                    {{ statusConfig[o.status]?.label }}
                                </span>
                                <button
                                    v-if="
                                        isCashier &&
                                        o.status !== 'completed' &&
                                        o.status !== 'cancelled'
                                    "
                                    @click="openSplitModal(o)"
                                    class="border-slate-250 dark:border-slate-750 dark:hover:bg-slate-805 mr-1 h-7 rounded-lg border bg-white px-2.5 text-[10px] font-semibold text-slate-700 transition-colors hover:bg-slate-50 dark:bg-slate-900 dark:text-slate-300"
                                >
                                    Tách đơn
                                </button>
                                <button
                                    v-if="o.status === 'pending'"
                                    @click="handleCancelSpamOrder(o)"
                                    :disabled="isCancellingSpam === o.id || isFetchingAi"
                                    class="mr-1.5 h-7 rounded-lg border border-rose-200 bg-rose-50 px-2.5 text-[10px] font-semibold text-rose-700 transition-colors hover:bg-rose-100 disabled:opacity-50 dark:border-rose-950 dark:bg-rose-950/20 dark:text-rose-400"
                                >
                                    {{ isCancellingSpam === o.id ? 'Đang hủy...' : 'Hủy đơn ảo' }}
                                </button>
                                <button
                                    v-if="
                                        nextStatus[o.status] &&
                                        (nextStatus[o.status] !== 'completed' ||
                                            isCashier)
                                    "
                                    @click="
                                        handleStatusUpdate(
                                            o,
                                            nextStatus[o.status]!,
                                        )
                                    "
                                    :disabled="isFetchingAi || isCancellingSpam === o.id"
                                    class="h-7 rounded-lg bg-violet-600 px-2.5 text-[10px] font-semibold text-white transition-colors hover:bg-violet-700 disabled:opacity-50"
                                >
                                    {{
                                        nextStatus[o.status] === 'confirmed'
                                            ? 'Xác nhận'
                                            : nextStatus[o.status] ===
                                                'preparing'
                                              ? 'Chuyển bếp'
                                              : nextStatus[o.status] ===
                                                  'completed'
                                                ? 'Thanh toán'
                                                : ''
                                    }}
                                </button>
                            </div>
                        </div>

                        <!-- Expanded Items List -->
                        <div
                            v-if="expandedOrderId === o.id"
                            class="border-t border-slate-100 bg-slate-50/40 p-4 dark:border-slate-800 dark:bg-slate-900/10"
                        >
                            <h4
                                class="mb-2 text-xs font-bold tracking-wider text-slate-400 uppercase"
                            >
                                Chi tiết món ăn
                            </h4>
                            <div class="space-y-2">
                                <div
                                    v-for="item in o.items"
                                    :key="item.id"
                                    class="flex items-center justify-between rounded-xl border border-slate-100 bg-white p-2.5 shadow-xs dark:border-slate-800 dark:bg-slate-900"
                                >
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="text-xs font-bold text-slate-800 dark:text-slate-200"
                                        >
                                            {{ item.product_name }}
                                        </span>
                                        <span
                                            class="font-mono text-xs text-slate-500"
                                        >
                                            x{{ item.quantity }}
                                        </span>
                                        <span
                                            v-if="item.notes"
                                            class="rounded-md bg-amber-50 px-1.5 py-0.5 text-[10px] text-amber-600 dark:bg-amber-950/20 dark:text-amber-400"
                                        >
                                            Ghi chú: {{ item.notes }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <!-- Item Status Badge -->
                                        <span
                                            class="rounded-full border px-2 py-0.5 text-[9px] font-bold"
                                            :class="
                                                itemStatusConfig[item.status]
                                                    ?.class ||
                                                'bg-slate-50 text-slate-700'
                                            "
                                        >
                                            {{
                                                itemStatusConfig[item.status]
                                                    ?.label || item.status
                                            }}
                                        </span>

                                        <!-- Item Status Actions -->
                                        <div
                                            class="flex gap-1"
                                            v-if="
                                                item.status !== 'served' &&
                                                item.status !== 'cancelled' &&
                                                o.status !== 'cancelled' &&
                                                o.status !== 'completed'
                                            "
                                        >
                                            <Button
                                                v-if="
                                                    item.status === 'pending' ||
                                                    item.status === 'sent'
                                                "
                                                size="sm"
                                                variant="outline"
                                                :disabled="
                                                    updatingItemStatus ===
                                                    item.id
                                                "
                                                @click="
                                                    updateOrderItemStatus(
                                                        item,
                                                        'preparing',
                                                    )
                                                "
                                                class="text-violet-750 h-6 rounded-lg border-violet-200 px-2 text-[9px] font-semibold hover:bg-violet-50 dark:border-violet-900/45 dark:text-violet-400"
                                            >
                                                Chế biến
                                            </Button>
                                            <Button
                                                v-if="
                                                    item.status === 'preparing'
                                                "
                                                size="sm"
                                                variant="outline"
                                                :disabled="
                                                    updatingItemStatus ===
                                                    item.id
                                                "
                                                @click="
                                                    updateOrderItemStatus(
                                                        item,
                                                        'served',
                                                    )
                                                "
                                                class="border-emerald-250 h-6 rounded-lg px-2 text-[9px] font-semibold text-emerald-700 hover:bg-emerald-50 dark:border-emerald-900/45 dark:text-emerald-400"
                                            >
                                                Phục vụ
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Voucher & Order Summary Section for Cashiers or if Discount exists -->
                            <div
                                class="mt-4 flex flex-col gap-4 border-t border-slate-200 pt-4 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800"
                            >
                                <div class="flex flex-col gap-1 text-xs">
                                    <div
                                        class="flex w-48 justify-between text-slate-500"
                                    >
                                        <span>Tạm tính:</span>
                                        <span class="font-mono font-semibold">{{
                                            formatCurrency(
                                                o.subtotal || o.total_amount,
                                            )
                                        }}</span>
                                    </div>
                                    <div
                                        v-if="o.discount_amount"
                                        class="flex w-48 justify-between font-mono font-semibold text-emerald-600 dark:text-emerald-400"
                                    >
                                        <span>Giảm giá:</span>
                                        <span
                                            >-{{
                                                formatCurrency(
                                                    o.discount_amount,
                                                )
                                            }}</span
                                        >
                                    </div>
                                    <div
                                        class="flex w-48 justify-between font-bold text-slate-800 dark:text-slate-200"
                                    >
                                        <span>Tổng cộng:</span>
                                        <span
                                            class="font-mono text-sm text-violet-600 dark:text-violet-400"
                                            >{{
                                                formatCurrency(o.total_amount)
                                            }}</span
                                        >
                                    </div>
                                </div>

                                <!-- Apply Voucher input for cashiers on non-completed/non-cancelled orders -->
                                <div
                                    v-if="
                                        isCashier &&
                                        o.status !== 'completed' &&
                                        o.status !== 'cancelled'
                                    "
                                    class="flex w-full max-w-sm items-center gap-2 sm:w-auto"
                                >
                                    <input
                                        type="text"
                                        v-model="voucherCodes[o.id]"
                                        placeholder="Nhập mã giảm giá..."
                                        class="h-8 w-full rounded-lg border border-input bg-background px-2.5 text-xs focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none sm:w-40"
                                        @keyup.enter="handleApplyVoucher(o.id)"
                                    />
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        class="text-violet-750 h-8 rounded-lg border-violet-200 px-3 text-xs font-semibold hover:bg-violet-50 dark:border-violet-900/40 dark:text-violet-400"
                                        @click="handleApplyVoucher(o.id)"
                                    >
                                        Áp dụng
                                    </Button>
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
            <DialogContent
                class="rounded-2xl border border-violet-100 bg-white/95 shadow-2xl backdrop-blur-md sm:max-w-[480px] dark:border-slate-800 dark:bg-slate-900/95"
            >
                <DialogHeader
                    class="flex flex-col items-center pb-3 text-center"
                >
                    <div
                        class="mb-3 flex h-14 w-14 animate-pulse items-center justify-center rounded-2xl border border-violet-100/50 bg-violet-50 text-violet-600 shadow-inner dark:bg-violet-950/50 dark:text-violet-400"
                    >
                        <Sparkles class="size-7" />
                    </div>
                    <DialogTitle
                        class="text-xl font-bold tracking-tight text-slate-900 dark:text-slate-100"
                    >
                        Trợ Lý AI Kích Cầu (Upselling)
                    </DialogTitle>
                    <DialogDescription
                        class="mt-1 text-xs text-slate-500 dark:text-slate-400"
                    >
                        Gợi ý tư vấn thêm đồ uống/món ăn kèm cho khách hàng dựa
                        trên phân tích giỏ hàng.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-4">
                    <!-- Info box -->
                    <div
                        class="rounded-xl border border-slate-100 bg-slate-50/50 p-4 dark:border-slate-800 dark:bg-slate-950/30"
                    >
                        <p
                            class="text-xs font-semibold tracking-wider text-slate-400 uppercase"
                        >
                            ĐƠN HÀNG XÁC NHẬN
                        </p>
                        <p
                            class="mt-1 text-sm font-bold text-slate-800 dark:text-slate-200"
                        >
                            Bàn
                            {{ currentVerifyingOrder?.table_name || '—' }} ({{
                                currentVerifyingOrder?.order_number
                            }})
                        </p>

                        <div
                            class="my-2.5 h-px bg-slate-200 dark:bg-slate-800"
                        ></div>

                        <p
                            class="mb-1 text-xs font-semibold tracking-wider text-slate-400 uppercase"
                        >
                            MÓN ĐÃ GỌI
                        </p>
                        <div class="mt-1 flex flex-wrap gap-1.5">
                            <span
                                v-for="item in currentVerifyingOrder?.items"
                                :key="item.id"
                                class="inline-block rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-medium text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300"
                            >
                                {{ item.product_name }} x{{ item.quantity }}
                            </span>
                        </div>
                    </div>

                    <!-- Suggestion content -->
                    <div
                        class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-violet-600 to-indigo-700 p-5 text-white shadow-md"
                    >
                        <div
                            class="absolute -right-6 -bottom-6 h-24 w-24 rounded-full bg-white/10 blur-lg"
                        ></div>

                        <div class="flex items-start gap-3.5">
                            <div
                                class="mt-0.5 shrink-0 rounded-xl bg-white/20 p-2"
                            >
                                <Sparkles class="size-5 text-white" />
                            </div>
                            <div class="space-y-1.5">
                                <p
                                    class="text-[10px] font-bold tracking-wider text-white/70 uppercase"
                                >
                                    GỢI Ý TỪ AI
                                </p>
                                <p
                                    class="text-sm leading-relaxed font-semibold tracking-wide italic"
                                >
                                    "{{ aiSuggestionText }}"
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="text-right text-[10px] text-slate-400">
                        Thuật toán:
                        <span
                            class="font-semibold text-slate-500 dark:text-slate-300"
                            >{{ aiSource }}</span
                        >
                    </div>
                </div>

                <DialogFooter class="gap-2 sm:justify-end">
                    <Button
                        @click="isAiModalOpen = false"
                        class="dark:hover:bg-slate-750 rounded-xl bg-slate-900 px-5 font-semibold text-white hover:bg-slate-800 dark:bg-slate-800"
                    >
                        Đóng gợi ý
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- Split Order Dialog -->
        <Dialog v-model:open="isSplitModalOpen">
            <DialogContent
                class="rounded-2xl border border-slate-100 bg-white/95 shadow-2xl backdrop-blur-md sm:max-w-[500px] dark:border-slate-800 dark:bg-slate-900/95"
            >
                <DialogHeader>
                    <DialogTitle
                        class="text-lg font-bold text-slate-900 dark:text-slate-100"
                    >
                        Tách Đơn Hàng
                    </DialogTitle>
                    <DialogDescription
                        class="text-xs text-slate-500 dark:text-slate-400"
                    >
                        Chọn bàn trống để tách các món được chọn sang. Hành động
                        này sẽ được ghi nhận và cảnh báo.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-4 py-4" v-if="selectedOrderToSplit">
                    <!-- Original Order Info -->
                    <div
                        class="rounded-xl border border-slate-100 bg-slate-50 p-3 text-xs dark:border-slate-800 dark:bg-slate-950/30"
                    >
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Đơn gốc:</span>
                            <span class="font-bold">{{
                                selectedOrderToSplit.order_number
                            }}</span>
                        </div>
                        <div class="mt-1 flex justify-between">
                            <span class="text-muted-foreground">Bàn gốc:</span>
                            <span class="font-bold">{{
                                selectedOrderToSplit.table_name || '—'
                            }}</span>
                        </div>
                    </div>

                    <!-- Target Table Select -->
                    <div class="space-y-1.5">
                        <label
                            class="text-slate-650 dark:text-slate-350 text-xs font-bold"
                        >
                            Bàn trống mục tiêu
                        </label>
                        <select
                            v-model="selectedTableId"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-800 focus:ring-2 focus:ring-violet-500 focus:outline-none dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200"
                        >
                            <option :value="null" disabled>
                                -- Chọn bàn trống --
                            </option>
                            <option
                                v-for="t in tables"
                                :key="t.id"
                                :value="t.id"
                            >
                                Bàn {{ t.name }}
                            </option>
                        </select>
                        <p
                            class="text-[10px] text-amber-600 dark:text-amber-400"
                            v-if="tables.length === 0"
                        >
                            Không tìm thấy bàn trống nào.
                        </p>
                    </div>

                    <!-- Items List with Quantity selection -->
                    <div class="space-y-2">
                        <label
                            class="text-slate-650 dark:text-slate-350 text-xs font-bold"
                        >
                            Chọn số lượng món cần tách
                        </label>
                        <div class="max-h-60 space-y-2 overflow-y-auto pr-1">
                            <div
                                v-for="item in itemsToSplit"
                                :key="item.order_item_id"
                                class="dark:border-slate-850 flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50/50 p-2.5 dark:bg-slate-950/20"
                            >
                                <div class="flex min-w-0 flex-col pr-2">
                                    <span
                                        class="truncate text-xs font-bold text-slate-800 dark:text-slate-200"
                                    >
                                        {{ item.product_name }}
                                    </span>
                                    <span
                                        class="text-[10px] text-muted-foreground"
                                    >
                                        Tối đa có sẵn: {{ item.max_quantity }}
                                    </span>
                                </div>

                                <div class="flex shrink-0 items-center gap-1.5">
                                    <button
                                        type="button"
                                        @click="
                                            item.quantity = Math.max(
                                                0,
                                                item.quantity - 1,
                                            )
                                        "
                                        class="flex h-6 w-6 items-center justify-center rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
                                    >
                                        -
                                    </button>
                                    <input
                                        type="number"
                                        v-model.number="item.quantity"
                                        min="0"
                                        :max="item.max_quantity"
                                        class="border-slate-250 dark:border-slate-750 h-6 w-10 rounded-lg border bg-white text-center text-xs font-semibold focus:ring-1 focus:ring-violet-500 focus:outline-none dark:bg-slate-950"
                                    />
                                    <button
                                        type="button"
                                        @click="
                                            item.quantity = Math.min(
                                                item.max_quantity,
                                                item.quantity + 1,
                                            )
                                        "
                                        class="flex h-6 w-6 items-center justify-center rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
                                    >
                                        +
                                    </button>
                                    <button
                                        type="button"
                                        @click="
                                            item.quantity = item.max_quantity
                                        "
                                        class="ml-1 text-[9px] font-bold text-violet-600 hover:underline dark:text-violet-400"
                                    >
                                        Tất cả
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter class="gap-2 sm:justify-end">
                    <Button
                        variant="outline"
                        @click="isSplitModalOpen = false"
                        class="dark:text-slate-350 rounded-xl border-slate-200 text-slate-700 dark:border-slate-800"
                    >
                        Hủy
                    </Button>
                    <Button
                        @click="handleSplitSubmit"
                        :disabled="!isSplitFormValid"
                        class="rounded-xl bg-violet-600 font-semibold text-white hover:bg-violet-700"
                    >
                        Xác nhận tách
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
