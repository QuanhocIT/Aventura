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
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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
}>();

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
                        <div class="flex shrink-0 items-center gap-2">
                            <span
                                class="rounded-full px-2 py-1 text-[10px] font-semibold"
                                :class="statusConfig[o.status]?.bg"
                            >
                                {{ statusConfig[o.status]?.label }}
                            </span>
                            <button
                                v-if="nextStatus[o.status]"
                                @click="
                                    updateOrderStatus(o, nextStatus[o.status]!)
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
    </div>
</template>
