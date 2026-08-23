<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Check,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Clock,
    CreditCard,
    Eye,
    Landmark,
    RefreshCw,
    RotateCcw,
    Search,
    TrendingUp,
    X,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import FinancePageHeader from '@/components/finance/FinancePageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

interface Branch {
    id: number;
    name: string;
}

interface OrderItem {
    id: number;
    name: string;
    quantity: number;
    unit_price: number;
    total_price: number;
}

interface OrderDetail {
    id: number;
    order_code: string;
    total_amount: number;
    final_amount: number;
    status: string;
    items: OrderItem[];
}

interface PaymentRecord {
    id: number;
    order_id: number;
    order_code: string;
    paid_at: string;
    paid_date_raw: string;
    branch_id: number | null;
    branch_name: string;
    customer_name: string;
    customer_phone: string;
    processed_by_name: string;
    payment_method: string;
    payment_method_label: string;
    amount: number;
    transaction_code: string;
    status: string;
    is_reconciled: boolean;
    reconciled_at: string | null;
    reconciled_by_name: string | null;
    reconciliation_note: string | null;
    order: OrderDetail | null;
    items_summary: string;
}

interface SummaryStats {
    total_amount: number;
    total_count: number;
    reconciled_amount: number;
    reconciled_count: number;
    pending_amount: number;
    pending_count: number;
    reconciled_rate: number;
}

interface Filters {
    branch_id: number | null;
    date_from: string;
    date_to: string;
    status: string;
    search: string;
}

interface PaginatedPayments {
    data: PaymentRecord[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

const props = defineProps<{
    branches: Branch[];
    filters: Filters;
    summary: SummaryStats;
    payments: PaginatedPayments;
}>();

// Filter states
const branchIdFilter = ref<string>(props.filters.branch_id ? String(props.filters.branch_id) : 'all');
const dateFrom = ref<string>(props.filters.date_from);
const dateTo = ref<string>(props.filters.date_to);
const statusFilter = ref<string>(props.filters.status || 'all');
const searchQuery = ref<string>(props.filters.search || '');

// Selection state
const selectedIds = ref<number[]>([]);

// Modal state
const selectedPaymentForDetail = ref<PaymentRecord | null>(null);

function money(value: number) {
    return new Intl.NumberFormat('vi-VN').format(value) + ' đ';
}

function applyFilters() {
    router.get(
        '/bank-reconciliation',
        {
            branch_id: branchIdFilter.value,
            date_from: dateFrom.value,
            date_to: dateTo.value,
            status: statusFilter.value,
            search: searchQuery.value,
        },
        { preserveState: true, replace: true },
    );
}

function resetFilters() {
    branchIdFilter.value = 'all';
    statusFilter.value = 'all';
    searchQuery.value = '';
    const today = new Date().toISOString().split('T')[0];
    const past30 = new Date(Date.now() - 30 * 86400000).toISOString().split('T')[0];
    dateFrom.value = past30;
    dateTo.value = today;
    applyFilters();
}

function setDatePreset(days: number) {
    const today = new Date();
    dateTo.value = today.toISOString().split('T')[0];
    if (days === 0) {
        dateFrom.value = dateTo.value;
    } else if (days === 1) {
        const yesterday = new Date(Date.now() - 86400000);
        dateFrom.value = yesterday.toISOString().split('T')[0];
        dateTo.value = dateFrom.value;
    } else {
        const past = new Date(Date.now() - days * 86400000);
        dateFrom.value = past.toISOString().split('T')[0];
    }
    applyFilters();
}

function setThisMonthPreset() {
    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    dateFrom.value = firstDay.toISOString().split('T')[0];
    dateTo.value = today.toISOString().split('T')[0];
    applyFilters();
}

// Select all visible
const isAllSelected = computed(() => {
    if (props.payments.data.length === 0) return false;
    return props.payments.data.every((p) => selectedIds.value.includes(p.id));
});

function toggleSelectAll() {
    if (isAllSelected.value) {
        selectedIds.value = [];
    } else {
        selectedIds.value = props.payments.data.map((p) => p.id);
    }
}

function toggleSelect(id: number) {
    const idx = selectedIds.value.indexOf(id);
    if (idx > -1) {
        selectedIds.value.splice(idx, 1);
    } else {
        selectedIds.value.push(id);
    }
}

const selectedTotalAmount = computed(() => {
    return props.payments.data
        .filter((p) => selectedIds.value.includes(p.id))
        .reduce((sum, p) => sum + p.amount, 0);
});

// Actions
function reconcileSingle(payment: PaymentRecord) {
    router.post(
        `/bank-reconciliation/payments/${payment.id}/reconcile`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                if (selectedPaymentForDetail.value?.id === payment.id) {
                    selectedPaymentForDetail.value.is_reconciled = true;
                }
            },
        },
    );
}

function unreconcileSingle(payment: PaymentRecord) {
    if (!confirm('Bạn có chắc chắn muốn hủy trạng thái đối soát của đơn hàng này?')) return;
    router.post(
        `/bank-reconciliation/payments/${payment.id}/unreconcile`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                if (selectedPaymentForDetail.value?.id === payment.id) {
                    selectedPaymentForDetail.value.is_reconciled = false;
                }
            },
        },
    );
}

function executeBatchReconcile() {
    if (selectedIds.value.length === 0) return;
    router.post(
        '/bank-reconciliation/batch-reconcile',
        { payment_ids: selectedIds.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                selectedIds.value = [];
            },
        },
    );
}
</script>

<template>
    <Head title="Đối soát chuyển khoản ngân hàng" />
    <div class="space-y-6 p-4 sm:p-6">
        <!-- Header -->
        <FinancePageHeader
            title="Đối soát chuyển khoản ngân hàng"
            description="Theo dõi danh sách các đơn hàng thanh toán chuyển khoản & VietQR để chủ doanh nghiệp đối chiếu tiền vào ngân hàng."
            :icon="Landmark"
        >
            <template #actions>
                <Button variant="outline" size="sm" class="gap-2" @click="applyFilters">
                    <RefreshCw class="size-4" /> Làm mới
                </Button>
            </template>
        </FinancePageHeader>

        <!-- Summary Stat Cards -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Total Card -->
            <div class="rounded-2xl border border-border/60 bg-card p-4 shadow-xs transition-all hover:shadow-md">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Tổng CK phát sinh</span>
                    <div class="flex size-9 items-center justify-center rounded-xl bg-blue-500/10 text-blue-600 dark:bg-blue-500/20">
                        <CreditCard class="size-5" />
                    </div>
                </div>
                <div class="mt-3 text-2xl font-bold tracking-tight text-foreground">
                    {{ money(summary.total_amount) }}
                </div>
                <div class="mt-1 flex items-center gap-1.5 text-xs text-muted-foreground">
                    <span>{{ summary.total_count }} đơn chuyển khoản</span>
                </div>
            </div>

            <!-- Reconciled Card -->
            <div class="rounded-2xl border border-emerald-500/20 bg-emerald-500/5 p-4 shadow-xs transition-all hover:shadow-md">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-emerald-700 dark:text-emerald-400 uppercase tracking-wider">Đã đối soát tiền về</span>
                    <div class="flex size-9 items-center justify-center rounded-xl bg-emerald-500/20 text-emerald-600">
                        <CheckCircle2 class="size-5" />
                    </div>
                </div>
                <div class="mt-3 text-2xl font-bold tracking-tight text-emerald-700 dark:text-emerald-400">
                    {{ money(summary.reconciled_amount) }}
                </div>
                <div class="mt-1 flex items-center gap-1.5 text-xs text-emerald-600/80">
                    <span>{{ summary.reconciled_count }} đơn đã khớp ngân hàng</span>
                </div>
            </div>

            <!-- Pending Card -->
            <div class="rounded-2xl border border-amber-500/20 bg-amber-500/5 p-4 shadow-xs transition-all hover:shadow-md">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-amber-700 dark:text-amber-400 uppercase tracking-wider">Chưa đối soát</span>
                    <div class="flex size-9 items-center justify-center rounded-xl bg-amber-500/20 text-amber-600">
                        <Clock class="size-5" />
                    </div>
                </div>
                <div class="mt-3 text-2xl font-bold tracking-tight text-amber-700 dark:text-amber-400">
                    {{ money(summary.pending_amount) }}
                </div>
                <div class="mt-1 flex items-center gap-1.5 text-xs text-amber-600/80">
                    <span>{{ summary.pending_count }} đơn chờ chủ xem lại</span>
                </div>
            </div>

            <!-- Rate Card -->
            <div class="rounded-2xl border border-purple-500/20 bg-purple-500/5 p-4 shadow-xs transition-all hover:shadow-md">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-purple-700 dark:text-purple-400 uppercase tracking-wider">Tỷ lệ tiền khớp</span>
                    <div class="flex size-9 items-center justify-center rounded-xl bg-purple-500/20 text-purple-600">
                        <TrendingUp class="size-5" />
                    </div>
                </div>
                <div class="mt-3 text-2xl font-bold tracking-tight text-purple-700 dark:text-purple-400">
                    {{ summary.reconciled_rate }}%
                </div>
                <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-purple-200 dark:bg-purple-900/40">
                    <div
                        class="h-full rounded-full bg-purple-600 transition-all duration-500"
                        :style="{ width: `${Math.min(100, summary.reconciled_rate)}%` }"
                    ></div>
                </div>
            </div>
        </div>

        <!-- Filter & Search Bar -->
        <div class="rounded-2xl border border-border/60 bg-card p-4 shadow-xs space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <!-- Preset buttons -->
                <div class="flex items-center gap-1.5">
                    <span class="text-xs font-medium text-muted-foreground mr-1">Lọc nhanh:</span>
                    <Button variant="outline" size="xs" class="rounded-lg text-xs" @click="setDatePreset(0)">Hôm nay</Button>
                    <Button variant="outline" size="xs" class="rounded-lg text-xs" @click="setDatePreset(1)">Hôm qua</Button>
                    <Button variant="outline" size="xs" class="rounded-lg text-xs" @click="setDatePreset(7)">7 ngày qua</Button>
                    <Button variant="outline" size="xs" class="rounded-lg text-xs" @click="setThisMonthPreset">Tháng này</Button>
                </div>

                <!-- Status Filter Tabs -->
                <div class="flex items-center rounded-xl border border-border/60 bg-muted/40 p-1 text-xs">
                    <button
                        class="rounded-lg px-3 py-1 font-medium transition-all"
                        :class="statusFilter === 'all' ? 'bg-background text-foreground shadow-2xs' : 'text-muted-foreground hover:text-foreground'"
                        @click="statusFilter = 'all'; applyFilters()"
                    >
                        Tất cả ({{ summary.total_count }})
                    </button>
                    <button
                        class="rounded-lg px-3 py-1 font-medium transition-all"
                        :class="statusFilter === 'unreconciled' ? 'bg-amber-500/10 text-amber-700 dark:text-amber-400 font-semibold shadow-2xs' : 'text-muted-foreground hover:text-foreground'"
                        @click="statusFilter = 'unreconciled'; applyFilters()"
                    >
                        Chưa đối soát ({{ summary.pending_count }})
                    </button>
                    <button
                        class="rounded-lg px-3 py-1 font-medium transition-all"
                        :class="statusFilter === 'reconciled' ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 font-semibold shadow-2xs' : 'text-muted-foreground hover:text-foreground'"
                        @click="statusFilter = 'reconciled'; applyFilters()"
                    >
                        Đã đối soát ({{ summary.reconciled_count }})
                    </button>
                </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-5">
                <!-- Date From -->
                <div>
                    <label class="mb-1 block text-[11px] font-medium text-muted-foreground">Từ ngày</label>
                    <Input v-model="dateFrom" type="date" class="h-9 text-xs" @change="applyFilters" />
                </div>

                <!-- Date To -->
                <div>
                    <label class="mb-1 block text-[11px] font-medium text-muted-foreground">Đến ngày</label>
                    <Input v-model="dateTo" type="date" class="h-9 text-xs" @change="applyFilters" />
                </div>

                <!-- Branch filter -->
                <div>
                    <label class="mb-1 block text-[11px] font-medium text-muted-foreground">Chi nhánh</label>
                    <select
                        v-model="branchIdFilter"
                        class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-xs shadow-2xs outline-none focus-visible:border-ring focus-visible:ring-ring/50"
                        @change="applyFilters"
                    >
                        <option value="all">Tất cả chi nhánh</option>
                        <option v-for="b in branches" :key="b.id" :value="String(b.id)">
                            {{ b.name }}
                        </option>
                    </select>
                </div>

                <!-- Search -->
                <div class="lg:col-span-2">
                    <label class="mb-1 block text-[11px] font-medium text-muted-foreground">Tìm kiếm</label>
                    <div class="relative">
                        <Search class="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                        <Input
                            v-model="searchQuery"
                            placeholder="Mã đơn hàng, mã giao dịch, SĐT khách..."
                            class="h-9 pl-8 text-xs"
                            @keyup.enter="applyFilters"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Batch Action Toolbar (When selection > 0) -->
        <div
            v-if="selectedIds.length > 0"
            class="flex items-center justify-between rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm"
        >
            <div class="flex items-center gap-2 text-emerald-800 dark:text-emerald-300 font-medium">
                <CheckCircle2 class="size-4 text-emerald-600" />
                <span>Đã chọn {{ selectedIds.length }} đơn hàng (Tổng {{ money(selectedTotalAmount) }})</span>
            </div>
            <div class="flex items-center gap-2">
                <Button size="sm" class="bg-emerald-600 hover:bg-emerald-700 text-white gap-2" @click="executeBatchReconcile">
                    <Check class="size-4" /> Xác nhận đối soát ({{ selectedIds.length }} đơn)
                </Button>
                <Button variant="ghost" size="sm" @click="selectedIds = []">Hủy chọn</Button>
            </div>
        </div>

        <!-- Data Table -->
        <div class="overflow-hidden rounded-2xl border border-border/60 bg-card shadow-xs">
            <div class="border-b border-border/60 px-5 py-4 flex items-center justify-between">
                <div>
                    <h3 class="font-semibold text-foreground">Danh sách đơn hàng Chuyển khoản</h3>
                    <p class="text-xs text-muted-foreground">Hiển thị {{ payments.data.length }} trên tổng số {{ payments.total }} đơn phát sinh</p>
                </div>
                <div class="text-xs text-muted-foreground">
                    Trang {{ payments.current_page }} / {{ payments.last_page }}
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead class="bg-muted/40 font-semibold uppercase tracking-wider text-muted-foreground">
                        <tr>
                            <th class="w-10 px-4 py-3 text-center">
                                <input
                                    type="checkbox"
                                    class="rounded border-input text-emerald-600 focus:ring-emerald-500 size-3.5 cursor-pointer"
                                    :checked="isAllSelected"
                                    @change="toggleSelectAll"
                                />
                            </th>
                            <th class="px-4 py-3">Thời gian</th>
                            <th class="px-4 py-3">Mã đơn hàng & Món</th>
                            <th class="px-4 py-3">Chi nhánh</th>
                            <th class="px-4 py-3">Khách hàng</th>
                            <th class="px-4 py-3">Mã GD / Nội dung</th>
                            <th class="px-4 py-3 text-right">Số tiền</th>
                            <th class="px-4 py-3 text-center">Trạng thái đối soát</th>
                            <th class="px-4 py-3 text-right">Thao tác</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        <tr
                            v-for="p in payments.data"
                            :key="p.id"
                            class="transition-colors hover:bg-muted/30"
                            :class="selectedIds.includes(p.id) ? 'bg-emerald-500/5' : ''"
                        >
                            <!-- Checkbox -->
                            <td class="px-4 py-3.5 text-center">
                                <input
                                    type="checkbox"
                                    class="rounded border-input text-emerald-600 focus:ring-emerald-500 size-3.5 cursor-pointer"
                                    :checked="selectedIds.includes(p.id)"
                                    @change="toggleSelect(p.id)"
                                />
                            </td>

                            <!-- Time -->
                            <td class="whitespace-nowrap px-4 py-3.5">
                                <div class="font-medium text-foreground">{{ p.paid_at }}</div>
                                <div class="text-[11px] text-muted-foreground">TN: {{ p.processed_by_name }}</div>
                            </td>

                            <!-- Order code & items -->
                            <td class="px-4 py-3.5 max-w-[220px]">
                                <div class="flex items-center gap-1.5">
                                    <button
                                        class="font-mono font-semibold text-primary hover:underline"
                                        @click="selectedPaymentForDetail = p"
                                    >
                                        {{ p.order_code }}
                                    </button>
                                    <span
                                        class="rounded-md px-1.5 py-0.5 text-[10px] font-medium"
                                        :class="p.payment_method === 'vietqr' ? 'bg-indigo-500/10 text-indigo-600' : 'bg-blue-500/10 text-blue-600'"
                                    >
                                        {{ p.payment_method_label }}
                                    </span>
                                </div>
                                <div class="mt-0.5 truncate text-[11px] text-muted-foreground" :title="p.items_summary">
                                    {{ p.items_summary }}
                                </div>
                            </td>

                            <!-- Branch -->
                            <td class="whitespace-nowrap px-4 py-3.5">
                                <span class="rounded-lg bg-muted/60 px-2 py-1 text-[11px] font-medium text-muted-foreground">
                                    {{ p.branch_name }}
                                </span>
                            </td>

                            <!-- Customer -->
                            <td class="whitespace-nowrap px-4 py-3.5">
                                <div class="font-medium text-foreground">{{ p.customer_name }}</div>
                                <div v-if="p.customer_phone" class="text-[11px] text-muted-foreground">{{ p.customer_phone }}</div>
                            </td>

                            <!-- Ref / Transaction code -->
                            <td class="px-4 py-3.5 font-mono text-[11px] text-muted-foreground max-w-[150px] truncate" :title="p.transaction_code">
                                {{ p.transaction_code }}
                            </td>

                            <!-- Amount -->
                            <td class="whitespace-nowrap px-4 py-3.5 text-right font-bold text-foreground text-sm tabular-nums">
                                {{ money(p.amount) }}
                            </td>

                            <!-- Status -->
                            <td class="whitespace-nowrap px-4 py-3.5 text-center">
                                <div v-if="p.is_reconciled" class="inline-flex flex-col items-center">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2.5 py-1 text-[11px] font-semibold text-emerald-700 dark:text-emerald-400 ring-1 ring-emerald-500/20">
                                        <CheckCircle2 class="size-3" /> Đã đối soát
                                    </span>
                                    <span v-if="p.reconciled_at" class="mt-0.5 text-[10px] text-muted-foreground">
                                        {{ p.reconciled_at }} {{ p.reconciled_by_name ? `(${p.reconciled_by_name})` : '' }}
                                    </span>
                                </div>
                                <div v-else>
                                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-2.5 py-1 text-[11px] font-medium text-amber-700 dark:text-amber-400 ring-1 ring-amber-500/20">
                                        <Clock class="size-3" /> Chưa đối soát
                                    </span>
                                </div>
                            </td>

                            <!-- Actions -->
                            <td class="whitespace-nowrap px-4 py-3.5 text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <Button
                                        v-if="!p.is_reconciled"
                                        size="xs"
                                        class="bg-emerald-600 hover:bg-emerald-500 active:scale-[0.98] text-white font-medium text-xs px-3 shadow-xs hover:shadow-emerald-500/20 transition-all duration-200 gap-1.5"
                                        @click="reconcileSingle(p)"
                                    >
                                        <Check class="size-3.5 stroke-[2.5]" /> Xác nhận tiền về
                                    </Button>
                                    <Button
                                        v-else
                                        variant="ghost"
                                        size="xs"
                                        class="text-amber-600 dark:text-amber-400 hover:bg-amber-500/10 hover:text-amber-700 dark:hover:text-amber-300 gap-1 text-xs"
                                        @click="unreconcileSingle(p)"
                                    >
                                        <RotateCcw class="size-3.5" /> Hủy đối soát
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="icon-xs"
                                        title="Xem chi tiết đơn hàng"
                                        class="border-border/80 hover:bg-accent"
                                        @click="selectedPaymentForDetail = p"
                                    >
                                        <Eye class="size-3.5 text-muted-foreground" />
                                    </Button>
                                </div>
                            </td>
                        </tr>

                        <!-- Empty state -->
                        <tr v-if="payments.data.length === 0">
                            <td colspan="9" class="px-4 py-12 text-center text-muted-foreground">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <Landmark class="size-8 text-muted-foreground/40" />
                                    <p class="text-sm font-medium">Không tìm thấy đơn hàng chuyển khoản nào trong khoảng thời gian này.</p>
                                    <Button variant="outline" size="sm" @click="resetFilters">Xóa bộ lọc</Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <div v-if="payments.last_page > 1" class="flex items-center justify-between border-t border-border/60 px-5 py-3 text-xs">
                <div class="text-muted-foreground">
                    Hiển thị từ {{ (payments.current_page - 1) * payments.per_page + 1 }} đến {{ Math.min(payments.current_page * payments.per_page, payments.total) }} trên {{ payments.total }} đơn
                </div>
                <div class="flex items-center gap-1.5">
                    <Button
                        variant="outline"
                        size="xs"
                        :disabled="!payments.prev_page_url"
                        @click="router.get(payments.prev_page_url!, {}, { preserveState: true })"
                    >
                        <ChevronLeft class="size-3.5" /> Trang trước
                    </Button>
                    <span class="px-2 font-medium">Trang {{ payments.current_page }} / {{ payments.last_page }}</span>
                    <Button
                        variant="outline"
                        size="xs"
                        :disabled="!payments.next_page_url"
                        @click="router.get(payments.next_page_url!, {}, { preserveState: true })"
                    >
                        Trang sau <ChevronRight class="size-3.5" />
                    </Button>
                </div>
            </div>
        </div>

        <!-- Order Detail Modal -->
        <div
            v-if="selectedPaymentForDetail"
            class="fixed inset-0 z-50 grid place-items-center bg-background/80 p-4 backdrop-blur-xs"
        >
            <div class="w-full max-w-lg overflow-hidden rounded-2xl border border-border/60 bg-card shadow-2xl">
                <!-- Modal Header -->
                <div class="flex items-center justify-between border-b border-border/60 px-6 py-4">
                    <div>
                        <h3 class="font-semibold text-foreground flex items-center gap-2">
                            <span>Chi tiết đơn hàng</span>
                            <span class="font-mono text-primary">#{{ selectedPaymentForDetail.order_code }}</span>
                        </h3>
                        <p class="text-xs text-muted-foreground">Thanh toán chuyển khoản lúc {{ selectedPaymentForDetail.paid_at }}</p>
                    </div>
                    <Button variant="ghost" size="icon-sm" @click="selectedPaymentForDetail = null">
                        <X class="size-4" />
                    </Button>
                </div>

                <!-- Modal Body -->
                <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto">
                    <!-- General info grid -->
                    <div class="grid grid-cols-2 gap-3 text-xs bg-muted/30 p-3.5 rounded-xl border border-border/40">
                        <div>
                            <span class="text-muted-foreground block text-[10px]">Chi nhánh:</span>
                            <span class="font-medium text-foreground">{{ selectedPaymentForDetail.branch_name }}</span>
                        </div>
                        <div>
                            <span class="text-muted-foreground block text-[10px]">Khách hàng:</span>
                            <span class="font-medium text-foreground">{{ selectedPaymentForDetail.customer_name }}</span>
                            <span v-if="selectedPaymentForDetail.customer_phone" class="text-muted-foreground block">({{ selectedPaymentForDetail.customer_phone }})</span>
                        </div>
                        <div>
                            <span class="text-muted-foreground block text-[10px]">Thu ngân:</span>
                            <span class="font-medium text-foreground">{{ selectedPaymentForDetail.processed_by_name }}</span>
                        </div>
                        <div>
                            <span class="text-muted-foreground block text-[10px]">Mã GD / Tham chiếu:</span>
                            <span class="font-mono font-medium text-foreground truncate block" :title="selectedPaymentForDetail.transaction_code">
                                {{ selectedPaymentForDetail.transaction_code }}
                            </span>
                        </div>
                    </div>

                    <!-- Items Table -->
                    <div>
                        <h4 class="text-xs font-semibold text-muted-foreground mb-2">Danh sách món ăn / sản phẩm</h4>
                        <div v-if="selectedPaymentForDetail.order?.items && selectedPaymentForDetail.order.items.length > 0" class="rounded-xl border border-border/60 overflow-hidden text-xs">
                            <table class="w-full text-left">
                                <thead class="bg-muted/40 font-medium text-muted-foreground">
                                    <tr>
                                        <th class="px-3 py-2">Tên món</th>
                                        <th class="px-3 py-2 text-center">SL</th>
                                        <th class="px-3 py-2 text-right">Đơn giá</th>
                                        <th class="px-3 py-2 text-right">Thành tiền</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border/40">
                                    <tr v-for="item in selectedPaymentForDetail.order.items" :key="item.id">
                                        <td class="px-3 py-2 font-medium">{{ item.name }}</td>
                                        <td class="px-3 py-2 text-center">{{ item.quantity }}</td>
                                        <td class="px-3 py-2 text-right text-muted-foreground">{{ money(item.unit_price) }}</td>
                                        <td class="px-3 py-2 text-right font-medium">{{ money(item.total_price) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div v-else class="text-xs text-muted-foreground italic py-2">
                            Không có thông tin chi tiết các món trong đơn hàng.
                        </div>
                    </div>

                    <!-- Total amount summary -->
                    <div class="flex items-center justify-between border-t border-border/60 pt-3 text-sm">
                        <span class="font-semibold text-foreground">Số tiền chuyển khoản:</span>
                        <span class="text-lg font-bold text-emerald-600">{{ money(selectedPaymentForDetail.amount) }}</span>
                    </div>

                    <!-- Reconciliation Status Card -->
                    <div class="rounded-xl p-3.5 border text-xs" :class="selectedPaymentForDetail.is_reconciled ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-800 dark:text-emerald-300' : 'bg-amber-500/10 border-amber-500/20 text-amber-800 dark:text-amber-300'">
                        <div class="flex items-center justify-between">
                            <span class="font-semibold flex items-center gap-1.5">
                                <component :is="selectedPaymentForDetail.is_reconciled ? CheckCircle2 : Clock" class="size-4" />
                                {{ selectedPaymentForDetail.is_reconciled ? 'Đã đối soát tiền về ngân hàng' : 'Chưa đối soát' }}
                            </span>
                            <span v-if="selectedPaymentForDetail.reconciled_at" class="text-[11px] opacity-80">
                                {{ selectedPaymentForDetail.reconciled_at }}
                            </span>
                        </div>
                        <p v-if="selectedPaymentForDetail.reconciled_by_name" class="mt-1 text-[11px] opacity-80">
                            Xác nhận bởi: <strong>{{ selectedPaymentForDetail.reconciled_by_name }}</strong>
                        </p>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="flex items-center justify-between border-t border-border/60 px-6 py-4 bg-muted/20">
                    <Button variant="outline" size="sm" @click="selectedPaymentForDetail = null">Đóng</Button>
                    <div>
                        <Button
                            v-if="!selectedPaymentForDetail.is_reconciled"
                            size="sm"
                            class="bg-emerald-600 hover:bg-emerald-700 text-white gap-1.5"
                            @click="reconcileSingle(selectedPaymentForDetail)"
                        >
                            <Check class="size-4" /> Xác nhận tiền về ngân hàng
                        </Button>
                        <Button
                            v-else
                            variant="destructive"
                            size="sm"
                            @click="unreconcileSingle(selectedPaymentForDetail)"
                        >
                            Hủy đối soát
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
