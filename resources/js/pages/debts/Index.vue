<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    BadgeDollarSign,
    LayoutDashboard,
    ListFilter,
    X,
    Users,
    Settings,
    ArrowUpRight,
    ArrowDownLeft,
} from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import { Pagination } from '@/components/super-admin';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
    CardDescription,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

// --- Props ---
type Supplier = {
    id: number;
    name: string;
};

type PurchaseOrder = {
    id: number;
    po_number: string;
};

type Customer = {
    id: number;
    full_name: string;
    phone: string;
    is_vip: boolean;
    is_b2b: boolean;
    credit_limit: number;
    current_debt: number;
};

type Order = {
    id: number;
    order_number: string;
};

type AccountPayable = {
    id: number;
    restaurant_id: number;
    purchase_order_id: number | null;
    supplier_id: number;
    amount: number;
    paid_amount: number;
    due_date: string;
    status: 'unpaid' | 'partially_paid' | 'paid' | 'written_off';
    notes: string | null;
    supplier?: Supplier;
    purchase_order?: PurchaseOrder;
};

type AccountReceivable = {
    id: number;
    restaurant_id: number;
    order_id: number | null;
    customer_id: number;
    amount: number;
    received_amount: number;
    due_date: string;
    status: 'unpaid' | 'partially_paid' | 'paid' | 'written_off';
    notes: string | null;
    customer?: Customer;
    order?: Order;
};

type AgingReport = {
    current: number;
    '1_30': number;
    '31_60': number;
    '61_90': number;
    over_90: number;
};

type Stats = {
    total_receivable: number;
    total_payable: number;
    overdue_receivable: number;
    overdue_payable: number;
    receivables_aging: AgingReport;
    payables_aging: AgingReport;
};

type PaginatedList<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

const props = defineProps<{
    stats: Stats;
    payables: PaginatedList<AccountPayable>;
    receivables: PaginatedList<AccountReceivable>;
    customers: PaginatedList<Customer>;
    canManageDebt: boolean;
    filters: {
        payable_status: string;
        receivable_status: string;
        customer_search: string;
    };
}>();

// --- Active Tab State ---
const activeTab = ref<'overview' | 'payables' | 'receivables' | 'credit'>(
    'overview',
);

// --- VND Formatter ---
const vnd = (v: number) =>
    new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(v);

// --- Modals State ---
const showPayModal = ref(false);
const selectedPayable = ref<AccountPayable | null>(null);

const showCollectModal = ref(false);
const selectedReceivable = ref<AccountReceivable | null>(null);

const showCreditModal = ref(false);
const selectedCustomer = ref<Customer | null>(null);

// --- Filters State ---
const filtersForm = ref({
    payable_status: props.filters.payable_status || '',
    receivable_status: props.filters.receivable_status || '',
    customer_search: props.filters.customer_search || '',
});

function applyFilters() {
    router.get(
        '/debts',
        {
            payable_status: filtersForm.value.payable_status,
            receivable_status: filtersForm.value.receivable_status,
            customer_search: filtersForm.value.customer_search,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
}

function resetFilters() {
    filtersForm.value = {
        payable_status: '',
        receivable_status: '',
        customer_search: '',
    };
    router.get('/debts', {}, { preserveState: true, preserveScroll: true });
}

// --- Payment & Collection Forms ---
const payForm = useForm({
    amount: 0,
    payment_method: 'bank_transfer',
    notes: '',
});

const collectForm = useForm({
    amount: 0,
    payment_method: 'cash',
    notes: '',
});

const creditForm = useForm({
    is_vip: false,
    is_b2b: false,
    credit_limit: 0,
});

// --- Actions ---
function openPayModal(p: AccountPayable) {
    selectedPayable.value = p;
    payForm.amount = p.amount - p.paid_amount;
    payForm.payment_method = 'bank_transfer';
    payForm.notes = '';
    showPayModal.value = true;
}

function submitPay() {
    if (!selectedPayable.value) {
        return;
    }

    const remaining =
        selectedPayable.value.amount - selectedPayable.value.paid_amount;

    if (payForm.amount <= 0 || payForm.amount > remaining) {
        toast.error(
            `Số tiền thanh toán phải lớn hơn 0 và không vượt quá ${vnd(remaining)}`,
        );

        return;
    }

    payForm.post(`/debts/payables/${selectedPayable.value.id}/pay`, {
        onSuccess: () => {
            showPayModal.value = false;
            toast.success('Đã ghi nhận thanh toán nợ nhà cung cấp thành công!');
        },
        onError: (err: any) => {
            toast.error((Object.values(err)[0] as string) || 'Có lỗi xảy ra');
        },
    });
}

function openCollectModal(r: AccountReceivable) {
    selectedReceivable.value = r;
    collectForm.amount = r.amount - r.received_amount;
    collectForm.payment_method = 'cash';
    collectForm.notes = '';
    showCollectModal.value = true;
}

function writeOffPayable(p: AccountPayable) {
    const reason = window.prompt('Lý do xóa nợ phải trả:');

    if (!reason?.trim()) {
        return;
    }

    router.post(
        `/debts/payables/${p.id}/write-off`,
        { reason },
        { preserveScroll: true },
    );
}

function writeOffReceivable(r: AccountReceivable) {
    const reason = window.prompt('Lý do xóa nợ phải thu:');

    if (!reason?.trim()) {
        return;
    }

    router.post(
        `/debts/receivables/${r.id}/write-off`,
        { reason },
        { preserveScroll: true },
    );
}

function submitCollect() {
    if (!selectedReceivable.value) {
        return;
    }

    const remaining =
        selectedReceivable.value.amount -
        selectedReceivable.value.received_amount;

    if (collectForm.amount <= 0 || collectForm.amount > remaining) {
        toast.error(
            `Số tiền thu hồi phải lớn hơn 0 và không vượt quá ${vnd(remaining)}`,
        );

        return;
    }

    collectForm.post(
        `/debts/receivables/${selectedReceivable.value.id}/collect`,
        {
            onSuccess: () => {
                showCollectModal.value = false;
                toast.success(
                    'Đã ghi nhận thu hồi nợ của khách hàng thành công!',
                );
            },
            onError: (err: any) => {
                toast.error(
                    (Object.values(err)[0] as string) || 'Có lỗi xảy ra',
                );
            },
        },
    );
}

function openCreditModal(c: Customer) {
    selectedCustomer.value = c;
    creditForm.is_vip = c.is_vip;
    creditForm.is_b2b = c.is_b2b;
    creditForm.credit_limit = c.credit_limit;
    showCreditModal.value = true;
}

function submitCredit() {
    if (!selectedCustomer.value) {
        return;
    }

    creditForm.post(`/debts/customers/${selectedCustomer.value.id}/credit`, {
        onSuccess: () => {
            showCreditModal.value = false;
            toast.success(
                'Đã cập nhật hạn mức tín dụng khách hàng thành công.',
            );
        },
        onError: (err: any) => {
            toast.error((Object.values(err)[0] as string) || 'Có lỗi xảy ra');
        },
    });
}

// --- Aging Report Helpers ---
const agingLabels: Record<keyof AgingReport, string> = {
    current: 'Trong hạn',
    '1_30': '1 - 30 ngày',
    '31_60': '31 - 60 ngày',
    '61_90': '61 - 90 ngày',
    over_90: '> 90 ngày',
};

const agingColors: Record<keyof AgingReport, string> = {
    current: 'bg-emerald-500',
    '1_30': 'bg-amber-400',
    '31_60': 'bg-orange-500',
    '61_90': 'bg-rose-500',
    over_90: 'bg-rose-700',
};

function getPercentage(value: number, total: number) {
    if (total <= 0) {
        return 0;
    }

    return Math.round((value / total) * 100);
}
</script>

<template>
    <Head title="Quản Lý Công Nợ" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-6">
        <!-- Page Header -->
        <div
            class="flex flex-col gap-4 border-b border-border pb-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-2xl border border-indigo-500/20 bg-indigo-500/10 text-indigo-600 shadow-xs dark:text-indigo-400"
                >
                    <BadgeDollarSign class="size-6" />
                </div>
                <div>
                    <h1
                        class="text-2xl font-black tracking-tight text-slate-800 dark:text-slate-100"
                    >
                        Quản Lý Công Nợ
                    </h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Theo dõi công nợ nhà cung cấp (Accounts Payable) và công
                        nợ mua hàng trả chậm của khách hàng VIP/B2B (Accounts
                        Receivable).
                    </p>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div
            class="flex shrink-0 gap-2 overflow-x-auto border-b border-border pb-1"
        >
            <button
                @click="activeTab = 'overview'"
                :class="[
                    'border-b-2 px-4 py-2.5 text-xs font-bold whitespace-nowrap transition-all',
                    activeTab === 'overview'
                        ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                        : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200',
                ]"
            >
                <span class="flex items-center gap-1.5"
                    ><LayoutDashboard class="size-3.5" /> Thống kê & Tuổi
                    nợ</span
                >
            </button>
            <button
                @click="activeTab = 'payables'"
                :class="[
                    'border-b-2 px-4 py-2.5 text-xs font-bold whitespace-nowrap transition-all',
                    activeTab === 'payables'
                        ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                        : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200',
                ]"
            >
                <span class="flex items-center gap-1.5"
                    ><ArrowDownLeft class="size-3.5 text-rose-500" /> Nợ nhà
                    cung cấp (Phải trả)</span
                >
            </button>
            <button
                @click="activeTab = 'receivables'"
                :class="[
                    'border-b-2 px-4 py-2.5 text-xs font-bold whitespace-nowrap transition-all',
                    activeTab === 'receivables'
                        ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                        : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200',
                ]"
            >
                <span class="flex items-center gap-1.5"
                    ><ArrowUpRight class="size-3.5 text-emerald-500" /> Nợ khách
                    hàng VIP/B2B (Phải thu)</span
                >
            </button>
            <button
                @click="activeTab = 'credit'"
                :class="[
                    'border-b-2 px-4 py-2.5 text-xs font-bold whitespace-nowrap transition-all',
                    activeTab === 'credit'
                        ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                        : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200',
                ]"
            >
                <span class="flex items-center gap-1.5"
                    ><Users class="size-3.5" /> Hạn mức mua nợ CRM</span
                >
            </button>
        </div>

        <!-- ── TAB 1: OVERVIEW ── -->
        <div v-if="activeTab === 'overview'" class="animate-fade-in space-y-6">
            <!-- Metric Cards -->
            <div class="grid grid-cols-1 gap-6 md:grid-cols-4">
                <Card class="border-border bg-card shadow-xs">
                    <CardHeader class="pb-2">
                        <CardDescription
                            class="text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                            >Tổng công nợ phải thu</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="pb-5">
                        <p
                            class="font-mono text-2xl font-black text-emerald-600"
                        >
                            {{ vnd(stats.total_receivable) }}
                        </p>
                        <p class="mt-2 text-[10px] text-slate-400">
                            Nợ từ các khách hàng VIP & B2B mua nợ tại quầy
                        </p>
                    </CardContent>
                </Card>

                <Card class="border-border bg-card shadow-xs">
                    <CardHeader class="pb-2">
                        <CardDescription
                            class="text-[10px] font-bold tracking-wider text-rose-500 uppercase"
                            >Quá hạn phải thu</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="pb-5">
                        <p class="font-mono text-2xl font-black text-rose-600">
                            {{ vnd(stats.overdue_receivable) }}
                        </p>
                        <p
                            class="mt-2 text-[10px] font-semibold text-rose-400/90"
                        >
                            Cần liên hệ thu hồi sớm
                        </p>
                    </CardContent>
                </Card>

                <Card class="border-border bg-card shadow-xs">
                    <CardHeader class="pb-2">
                        <CardDescription
                            class="text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                            >Tổng công nợ phải trả</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="pb-5">
                        <p class="font-mono text-2xl font-black text-rose-500">
                            {{ vnd(stats.total_payable) }}
                        </p>
                        <p class="mt-2 text-[10px] text-slate-400">
                            Tiền hàng nợ các nhà cung cấp nguyên liệu
                        </p>
                    </CardContent>
                </Card>

                <Card class="border-border bg-card shadow-xs">
                    <CardHeader class="pb-2">
                        <CardDescription
                            class="text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                            >Quá hạn phải trả</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="pb-5">
                        <p
                            class="font-mono text-2xl font-black text-slate-700 dark:text-slate-200"
                        >
                            {{ vnd(stats.overdue_payable) }}
                        </p>
                        <p class="mt-2 text-[10px] text-slate-400">
                            Cần thanh toán để duy trì SLA cung ứng
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Aging Report Block -->
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                <!-- Receivables Aging -->
                <Card class="border-border shadow-xs">
                    <CardHeader
                        class="border-b bg-slate-50/40 pb-3 dark:bg-slate-900/10"
                    >
                        <CardTitle
                            class="flex items-center gap-1.5 text-sm font-bold text-slate-800 dark:text-slate-100"
                        >
                            📊 Báo cáo tuổi nợ phải thu (Khách hàng)
                        </CardTitle>
                        <CardDescription class="text-xs"
                            >Phân nhóm dư nợ phải thu theo chu kỳ quá
                            hạn.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="space-y-4 p-6">
                        <div
                            v-if="stats.total_receivable === 0"
                            class="text-slate-450 py-10 text-center text-xs"
                        >
                            Không có công nợ khách hàng hiện tại.
                        </div>
                        <div v-else class="space-y-4">
                            <div
                                v-for="(val, key) in stats.receivables_aging"
                                :key="key"
                                class="space-y-1.5"
                            >
                                <div
                                    class="flex items-center justify-between text-xs font-bold"
                                >
                                    <span
                                        class="text-slate-600 dark:text-slate-300"
                                        >{{ agingLabels[key] }}</span
                                    >
                                    <span
                                        class="font-mono text-slate-800 dark:text-slate-200"
                                    >
                                        {{ vnd(val) }}
                                        <span
                                            class="text-[10px] font-normal text-slate-400"
                                            >({{
                                                getPercentage(
                                                    val,
                                                    stats.total_receivable,
                                                )
                                            }}%)</span
                                        >
                                    </span>
                                </div>
                                <div
                                    class="h-2.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                                >
                                    <div
                                        class="h-full rounded-full transition-all"
                                        :class="agingColors[key]"
                                        :style="`width: ${getPercentage(val, stats.total_receivable)}%`"
                                    />
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Payables Aging -->
                <Card class="border-border shadow-xs">
                    <CardHeader
                        class="border-b bg-slate-50/40 pb-3 dark:bg-slate-900/10"
                    >
                        <CardTitle
                            class="flex items-center gap-1.5 text-sm font-bold text-slate-800 dark:text-slate-100"
                        >
                            📊 Báo cáo tuổi nợ phải trả (Nhà cung cấp)
                        </CardTitle>
                        <CardDescription class="text-xs"
                            >Phân nhóm dư nợ phải trả theo chu kỳ quá
                            hạn.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="space-y-4 p-6">
                        <div
                            v-if="stats.total_payable === 0"
                            class="text-slate-450 py-10 text-center text-xs"
                        >
                            Không có công nợ nhà cung cấp hiện tại.
                        </div>
                        <div v-else class="space-y-4">
                            <div
                                v-for="(val, key) in stats.payables_aging"
                                :key="key"
                                class="space-y-1.5"
                            >
                                <div
                                    class="flex items-center justify-between text-xs font-bold"
                                >
                                    <span
                                        class="text-slate-600 dark:text-slate-300"
                                        >{{ agingLabels[key] }}</span
                                    >
                                    <span
                                        class="font-mono text-slate-800 dark:text-slate-200"
                                    >
                                        {{ vnd(val) }}
                                        <span
                                            class="text-[10px] font-normal text-slate-400"
                                            >({{
                                                getPercentage(
                                                    val,
                                                    stats.total_payable,
                                                )
                                            }}%)</span
                                        >
                                    </span>
                                </div>
                                <div
                                    class="h-2.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                                >
                                    <div
                                        class="h-full rounded-full transition-all"
                                        :class="agingColors[key]"
                                        :style="`width: ${getPercentage(val, stats.total_payable)}%`"
                                    />
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- ── TAB 2: ACCOUNTS PAYABLE ── -->
        <div v-if="activeTab === 'payables'" class="animate-fade-in space-y-6">
            <!-- Filter box -->
            <Card class="border-border shadow-xs">
                <CardContent
                    class="flex flex-col items-end gap-3 p-4 text-xs md:flex-row"
                >
                    <div class="w-full space-y-1.5 text-left md:w-1/3">
                        <Label class="text-[11px] font-bold text-slate-400"
                            >Trạng thái thanh toán:</Label
                        >
                        <select
                            v-model="filtersForm.payable_status"
                            class="w-full rounded-lg border border-border bg-background px-3 py-2 text-xs font-semibold outline-hidden focus:ring-2 focus:ring-indigo-500/25"
                        >
                            <option value="">-- Tất cả trạng thái --</option>
                            <option value="unpaid">Chưa trả</option>
                            <option value="partially_paid">Trả một phần</option>
                            <option value="paid">Đã thanh toán hết</option>
                        </select>
                    </div>

                    <div
                        class="flex w-full shrink-0 items-center gap-2 md:w-auto"
                    >
                        <Button
                            @click="applyFilters"
                            class="h-8 bg-indigo-600 px-4 text-xs font-bold text-white hover:bg-indigo-700"
                        >
                            <ListFilter class="mr-1 size-3.5" /> Lọc
                        </Button>
                        <Button
                            @click="resetFilters"
                            variant="outline"
                            class="h-8 px-4 text-xs font-semibold"
                        >
                            Bỏ lọc
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Table -->
            <Card class="overflow-hidden border-border shadow-xs">
                <CardHeader
                    class="border-b bg-slate-50/40 pb-3 dark:bg-slate-900/10"
                >
                    <CardTitle class="text-sm font-bold"
                        >Danh sách công nợ phải trả nhà cung cấp</CardTitle
                    >
                    <CardDescription class="text-xs"
                        >Theo dõi hạn thanh toán các đơn hàng PO nhập
                        thầu.</CardDescription
                    >
                </CardHeader>
                <CardContent class="overflow-x-auto p-0">
                    <table class="w-full border-collapse text-left text-xs">
                        <thead>
                            <tr
                                class="border-b bg-slate-50/20 font-bold text-slate-500 dark:bg-slate-900/5"
                            >
                                <th class="p-3 pl-5">Đơn hàng PO</th>
                                <th class="p-3">Nhà cung cấp</th>
                                <th class="p-3 text-right">Tổng nợ</th>
                                <th class="p-3 text-right">Đã trả</th>
                                <th class="p-3 text-right">Còn nợ</th>
                                <th class="p-3 text-center">Hạn thanh toán</th>
                                <th class="p-3 text-center">Trạng thái</th>
                                <th class="p-3 text-center">Hành động</th>
                            </tr>
                        </thead>
                        <tbody
                            class="dark:text-slate-350 divide-y divide-slate-100 text-slate-600 dark:divide-slate-800"
                        >
                            <tr v-if="payables.data.length === 0">
                                <td
                                    colspan="8"
                                    class="p-12 text-center font-bold text-slate-400"
                                >
                                    Không tìm thấy công nợ nào.
                                </td>
                            </tr>
                            <tr
                                v-for="p in payables.data"
                                :key="p.id"
                                class="transition-colors hover:bg-slate-50/40 dark:hover:bg-slate-900/20"
                            >
                                <td class="p-3 pl-5 font-mono font-bold">
                                    {{
                                        p.purchase_order
                                            ? p.purchase_order.po_number
                                            : '—'
                                    }}
                                </td>
                                <td
                                    class="p-3 font-semibold text-slate-700 dark:text-slate-300"
                                >
                                    {{ p.supplier ? p.supplier.name : '—' }}
                                </td>
                                <td class="p-3 text-right font-mono">
                                    {{ vnd(p.amount) }}
                                </td>
                                <td
                                    class="p-3 text-right font-mono text-emerald-600"
                                >
                                    {{ vnd(p.paid_amount) }}
                                </td>
                                <td
                                    class="p-3 text-right font-mono font-bold text-rose-500"
                                >
                                    {{ vnd(p.amount - p.paid_amount) }}
                                </td>
                                <td class="p-3 text-center font-mono font-bold">
                                    {{ p.due_date }}
                                </td>
                                <td class="p-3 text-center">
                                    <span
                                        v-if="p.status === 'paid'"
                                        class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-[9px] font-bold tracking-wider text-emerald-600 uppercase dark:bg-emerald-950/30"
                                    >
                                        Đã trả hết
                                    </span>
                                    <span
                                        v-else-if="
                                            p.status === 'partially_paid'
                                        "
                                        class="rounded-full bg-amber-50 px-2.5 py-0.5 text-[9px] font-bold tracking-wider text-amber-600 uppercase dark:bg-amber-950/20"
                                    >
                                        Trả một phần
                                    </span>
                                    <span
                                        v-else
                                        class="rounded-full bg-rose-50 px-2.5 py-0.5 text-[9px] font-bold tracking-wider text-rose-600 uppercase dark:bg-rose-950/30"
                                    >
                                        {{
                                            p.status === 'written_off'
                                                ? 'Đã xóa nợ'
                                                : 'Chưa thanh toán'
                                        }}
                                    </span>
                                </td>
                                <td class="p-3 text-center">
                                    <Button
                                        v-if="
                                            canManageDebt &&
                                            !['paid', 'written_off'].includes(
                                                p.status,
                                            )
                                        "
                                        @click="openPayModal(p)"
                                        size="sm"
                                        class="h-7 rounded-md bg-indigo-600 px-2.5 text-[10px] font-bold text-white hover:bg-indigo-700"
                                    >
                                        Trả nợ
                                    </Button>
                                    <Button
                                        v-if="
                                            canManageDebt &&
                                            !['paid', 'written_off'].includes(
                                                p.status,
                                            )
                                        "
                                        @click="writeOffPayable(p)"
                                        size="sm"
                                        variant="outline"
                                        class="ml-1 h-7 rounded-md px-2.5 text-[10px] font-bold text-rose-600 hover:bg-rose-50"
                                    >
                                        Xóa nợ
                                    </Button>
                                    <span
                                        v-if="
                                            !canManageDebt ||
                                            ['paid', 'written_off'].includes(
                                                p.status,
                                            )
                                        "
                                        class="font-bold text-slate-400"
                                        >—</span
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
                <!-- Pagination -->
                <Pagination
                    :links="payables.links"
                    :current-page="payables.current_page"
                    :last-page="payables.last_page"
                    :total="payables.total"
                />
            </Card>
        </div>

        <!-- ── TAB 3: ACCOUNTS RECEIVABLE ── -->
        <div
            v-if="activeTab === 'receivables'"
            class="animate-fade-in space-y-6"
        >
            <!-- Filter box -->
            <Card class="border-border shadow-xs">
                <CardContent
                    class="flex flex-col items-end gap-3 p-4 text-xs md:flex-row"
                >
                    <div class="w-full space-y-1.5 text-left md:w-1/3">
                        <Label class="text-[11px] font-bold text-slate-400"
                            >Trạng thái công nợ phải thu:</Label
                        >
                        <select
                            v-model="filtersForm.receivable_status"
                            class="w-full rounded-lg border border-border bg-background px-3 py-2 text-xs font-semibold outline-hidden focus:ring-2 focus:ring-indigo-500/25"
                        >
                            <option value="">-- Tất cả trạng thái --</option>
                            <option value="unpaid">Chưa thu</option>
                            <option value="partially_paid">Thu một phần</option>
                            <option value="paid">Đã tất toán</option>
                        </select>
                    </div>

                    <div
                        class="flex w-full shrink-0 items-center gap-2 md:w-auto"
                    >
                        <Button
                            @click="applyFilters"
                            class="h-8 bg-indigo-600 px-4 text-xs font-bold text-white hover:bg-indigo-700"
                        >
                            <ListFilter class="mr-1 size-3.5" /> Lọc
                        </Button>
                        <Button
                            @click="resetFilters"
                            variant="outline"
                            class="h-8 px-4 text-xs font-semibold"
                        >
                            Bỏ lọc
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Table -->
            <Card class="overflow-hidden border-border shadow-xs">
                <CardHeader
                    class="border-b bg-slate-50/40 pb-3 dark:bg-slate-900/10"
                >
                    <CardTitle class="text-sm font-bold"
                        >Danh sách công nợ phải thu (Khách hàng
                        VIP/B2B)</CardTitle
                    >
                    <CardDescription class="text-xs"
                        >Theo dõi quá trình thu hồi tiền hàng mua nợ của khách
                        hàng tại POS.</CardDescription
                    >
                </CardHeader>
                <CardContent class="overflow-x-auto p-0">
                    <table class="w-full border-collapse text-left text-xs">
                        <thead>
                            <tr
                                class="border-b bg-slate-50/20 font-bold text-slate-500 dark:bg-slate-900/5"
                            >
                                <th class="p-3 pl-5">Đơn hàng POS</th>
                                <th class="p-3">Khách hàng</th>
                                <th class="p-3 text-right">Tổng nợ</th>
                                <th class="p-3 text-right">Đã thu hồi</th>
                                <th class="p-3 text-right">Còn nợ lại</th>
                                <th class="p-3 text-center">Hạn thu hồi</th>
                                <th class="p-3 text-center">Trạng thái</th>
                                <th class="p-3 text-center">Hành động</th>
                            </tr>
                        </thead>
                        <tbody
                            class="dark:text-slate-350 divide-y divide-slate-100 text-slate-600 dark:divide-slate-800"
                        >
                            <tr v-if="receivables.data.length === 0">
                                <td
                                    colspan="8"
                                    class="p-12 text-center font-bold text-slate-400"
                                >
                                    Không tìm thấy công nợ nào.
                                </td>
                            </tr>
                            <tr
                                v-for="r in receivables.data"
                                :key="r.id"
                                class="transition-colors hover:bg-slate-50/40 dark:hover:bg-slate-900/20"
                            >
                                <td class="p-3 pl-5 font-mono font-bold">
                                    {{ r.order ? r.order.order_number : '—' }}
                                </td>
                                <td
                                    class="p-3 font-semibold text-slate-700 dark:text-slate-300"
                                >
                                    👤
                                    {{
                                        r.customer ? r.customer.full_name : '—'
                                    }}
                                    <span
                                        class="block text-[9px] text-slate-400"
                                        >SĐT:
                                        {{
                                            r.customer ? r.customer.phone : '—'
                                        }}</span
                                    >
                                </td>
                                <td class="p-3 text-right font-mono">
                                    {{ vnd(r.amount) }}
                                </td>
                                <td
                                    class="p-3 text-right font-mono text-emerald-600"
                                >
                                    {{ vnd(r.received_amount) }}
                                </td>
                                <td
                                    class="p-3 text-right font-mono font-bold text-rose-500"
                                >
                                    {{ vnd(r.amount - r.received_amount) }}
                                </td>
                                <td class="p-3 text-center font-mono font-bold">
                                    {{ r.due_date }}
                                </td>
                                <td class="p-3 text-center">
                                    <span
                                        v-if="r.status === 'paid'"
                                        class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-[9px] font-bold tracking-wider text-emerald-600 uppercase dark:bg-emerald-950/30"
                                    >
                                        Đã tất toán
                                    </span>
                                    <span
                                        v-else-if="
                                            r.status === 'partially_paid'
                                        "
                                        class="rounded-full bg-amber-50 px-2.5 py-0.5 text-[9px] font-bold tracking-wider text-amber-600 uppercase dark:bg-amber-950/20"
                                    >
                                        Thu một phần
                                    </span>
                                    <span
                                        v-else
                                        class="rounded-full bg-rose-50 px-2.5 py-0.5 text-[9px] font-bold tracking-wider text-rose-600 uppercase dark:bg-rose-950/30"
                                    >
                                        {{
                                            r.status === 'written_off'
                                                ? 'Đã xóa nợ'
                                                : 'Chưa thu hồi'
                                        }}
                                    </span>
                                </td>
                                <td class="p-3 text-center">
                                    <Button
                                        v-if="
                                            canManageDebt &&
                                            !['paid', 'written_off'].includes(
                                                r.status,
                                            )
                                        "
                                        @click="openCollectModal(r)"
                                        size="sm"
                                        class="h-7 rounded-md bg-emerald-600 px-2.5 text-[10px] font-bold text-white hover:bg-emerald-700"
                                    >
                                        Thu nợ
                                    </Button>
                                    <Button
                                        v-if="
                                            canManageDebt &&
                                            !['paid', 'written_off'].includes(
                                                r.status,
                                            )
                                        "
                                        @click="writeOffReceivable(r)"
                                        size="sm"
                                        variant="outline"
                                        class="ml-1 h-7 rounded-md px-2.5 text-[10px] font-bold text-rose-600 hover:bg-rose-50"
                                    >
                                        Xóa nợ
                                    </Button>
                                    <span
                                        v-if="
                                            !canManageDebt ||
                                            ['paid', 'written_off'].includes(
                                                r.status,
                                            )
                                        "
                                        class="font-bold text-slate-400"
                                        >—</span
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
                <!-- Pagination -->
                <Pagination
                    :links="receivables.links"
                    :current-page="receivables.current_page"
                    :last-page="receivables.last_page"
                    :total="receivables.total"
                />
            </Card>
        </div>

        <!-- ── TAB 4: CREDIT SETUP (CRM) ── -->
        <div v-if="activeTab === 'credit'" class="animate-fade-in space-y-6">
            <!-- Filter box -->
            <Card class="border-border shadow-xs">
                <CardContent
                    class="flex flex-col items-end gap-3 p-4 text-xs md:flex-row"
                >
                    <div class="w-full space-y-1.5 text-left md:w-1/2">
                        <Label class="text-[11px] font-bold text-slate-400"
                            >Tìm kiếm khách hàng:</Label
                        >
                        <Input
                            v-model="filtersForm.customer_search"
                            placeholder="Nhập tên hoặc số điện thoại..."
                            class="h-9 w-full text-xs"
                            @keyup.enter="applyFilters"
                        />
                    </div>

                    <div
                        class="flex w-full shrink-0 items-center gap-2 md:w-auto"
                    >
                        <Button
                            @click="applyFilters"
                            class="h-9 bg-indigo-600 px-4 text-xs font-bold text-white hover:bg-indigo-700"
                        >
                            Tìm kiếm
                        </Button>
                        <Button
                            @click="resetFilters"
                            variant="outline"
                            class="h-9 px-4 text-xs font-semibold"
                        >
                            Bỏ lọc
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Table -->
            <Card class="overflow-hidden border-border shadow-xs">
                <CardHeader
                    class="border-b bg-slate-50/40 pb-3 dark:bg-slate-900/10"
                >
                    <CardTitle class="text-sm font-bold"
                        >Cấu hình hạn mức mua nợ CRM</CardTitle
                    >
                    <CardDescription class="text-xs"
                        >Chỉ định khách hàng VIP/B2B và gắn hạn mức nợ để thực
                        hiện mua nợ tại POS.</CardDescription
                    >
                </CardHeader>
                <CardContent class="overflow-x-auto p-0">
                    <table class="w-full border-collapse text-left text-xs">
                        <thead>
                            <tr
                                class="border-b bg-slate-50/20 font-bold text-slate-500 dark:bg-slate-900/5"
                            >
                                <th class="p-3 pl-5">Tên khách hàng</th>
                                <th class="p-3">Số điện thoại</th>
                                <th class="p-3 text-center">Khách VIP</th>
                                <th class="p-3 text-center">Khách B2B</th>
                                <th class="p-3 text-right">
                                    Hạn mức nợ tối đa
                                </th>
                                <th class="p-3 text-right">Dư nợ hiện tại</th>
                                <th class="p-3 text-right">
                                    Khả năng nợ còn lại
                                </th>
                                <th class="p-3 text-center">Cấu hình</th>
                            </tr>
                        </thead>
                        <tbody
                            class="dark:text-slate-350 divide-y divide-slate-100 text-slate-600 dark:divide-slate-800"
                        >
                            <tr v-if="customers.data.length === 0">
                                <td
                                    colspan="8"
                                    class="p-12 text-center font-bold text-slate-400"
                                >
                                    Không tìm thấy khách hàng nào.
                                </td>
                            </tr>
                            <tr
                                v-for="c in customers.data"
                                :key="c.id"
                                class="transition-colors hover:bg-slate-50/40 dark:hover:bg-slate-900/20"
                            >
                                <td
                                    class="p-3 pl-5 font-bold text-slate-700 dark:text-slate-300"
                                >
                                    {{ c.full_name }}
                                </td>
                                <td class="p-3 font-mono font-semibold">
                                    {{ c.phone }}
                                </td>
                                <td class="p-3 text-center">
                                    <span
                                        v-if="c.is_vip"
                                        class="rounded-full border border-amber-100 bg-amber-50 px-2 py-0.5 text-[9px] font-bold text-amber-600 dark:border-amber-900/20 dark:bg-amber-950/20"
                                        >VIP</span
                                    >
                                    <span v-else class="text-slate-400">—</span>
                                </td>
                                <td class="p-3 text-center">
                                    <span
                                        v-if="c.is_b2b"
                                        class="rounded-full border border-indigo-100 bg-indigo-50 px-2 py-0.5 text-[9px] font-bold text-indigo-600 dark:border-indigo-900/20 dark:bg-indigo-950/30"
                                        >B2B</span
                                    >
                                    <span v-else class="text-slate-400">—</span>
                                </td>
                                <td class="p-3 text-right font-mono font-bold">
                                    {{ vnd(c.credit_limit) }}
                                </td>
                                <td
                                    class="p-3 text-right font-mono font-bold text-rose-500"
                                >
                                    {{ vnd(c.current_debt) }}
                                </td>
                                <td
                                    class="p-3 text-right font-mono font-bold text-slate-700 dark:text-slate-300"
                                >
                                    {{ vnd(c.credit_limit - c.current_debt) }}
                                </td>
                                <td class="p-3 text-center">
                                    <button
                                        v-if="canManageDebt"
                                        @click="openCreditModal(c)"
                                        class="cursor-pointer rounded-sm p-1 text-slate-500 hover:bg-slate-100 hover:text-indigo-600 dark:hover:bg-slate-800"
                                        title="Sửa hạn mức"
                                    >
                                        <Settings class="size-4" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
                <!-- Pagination -->
                <Pagination
                    :links="customers.links"
                    :current-page="customers.current_page"
                    :last-page="customers.last_page"
                    :total="customers.total"
                />
            </Card>
        </div>

        <!-- ── MODALS ── -->

        <!-- Pay Supplier Modal -->
        <Teleport to="body">
            <div
                v-if="showPayModal && selectedPayable"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
            >
                <div
                    class="animate-fade-in flex w-full max-w-sm flex-col gap-5 overflow-hidden rounded-3xl border bg-white p-6 text-left shadow-2xl dark:bg-slate-900"
                >
                    <div class="flex items-center justify-between">
                        <h3
                            class="flex items-center gap-2 text-sm font-black text-slate-800 dark:text-slate-100"
                        >
                            💳 Ghi nhận trả nợ nhà cung cấp
                        </h3>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-7 w-7 rounded-xl"
                            @click="showPayModal = false"
                        >
                            <X class="size-4" />
                        </Button>
                    </div>

                    <div class="space-y-4">
                        <div
                            class="space-y-1.5 rounded-xl bg-slate-50 p-3 text-xs text-slate-600 dark:bg-slate-800/40 dark:text-slate-300"
                        >
                            <div>
                                Nhà cung cấp:
                                <strong>{{
                                    selectedPayable.supplier?.name
                                }}</strong>
                            </div>
                            <div>
                                Đơn hàng:
                                <code>{{
                                    selectedPayable.purchase_order?.po_number
                                }}</code>
                            </div>
                            <div
                                class="flex justify-between border-t pt-1.5 font-bold"
                            >
                                <span>Còn nợ lại:</span>
                                <span class="text-rose-500">{{
                                    vnd(
                                        selectedPayable.amount -
                                            selectedPayable.paid_amount,
                                    )
                                }}</span>
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <Label class="text-[11px] font-bold text-slate-400"
                                >Số tiền thanh toán (đ):</Label
                            >
                            <Input
                                type="number"
                                v-model="payForm.amount"
                                class="h-9 font-mono text-xs"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <Label class="text-[11px] font-bold text-slate-400"
                                >Hình thức trả:</Label
                            >
                            <select
                                v-model="payForm.payment_method"
                                class="w-full rounded-lg border border-border bg-background px-3 py-2 text-xs font-semibold outline-hidden focus:ring-2 focus:ring-indigo-500/25"
                            >
                                <option value="bank_transfer">
                                    Chuyển khoản ngân hàng
                                </option>
                                <option value="cash">
                                    Tiền mặt (Trích quỹ két)
                                </option>
                            </select>
                        </div>

                        <div class="space-y-1.5">
                            <Label class="text-[11px] font-bold text-slate-400"
                                >Ghi chú giao dịch:</Label
                            >
                            <Input
                                v-model="payForm.notes"
                                placeholder="VD: Trả nợ đợt 1..."
                                class="h-9 text-xs"
                            />
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <Button
                            variant="outline"
                            class="flex-1 rounded-xl text-xs"
                            @click="showPayModal = false"
                        >
                            Hủy
                        </Button>
                        <Button
                            class="flex-1 rounded-xl bg-indigo-600 text-xs font-bold text-white hover:bg-indigo-700"
                            @click="submitPay"
                        >
                            Xác nhận thanh toán
                        </Button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Collect Customer Modal -->

        <div
            v-if="showCollectModal && selectedReceivable"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
        >
            <div
                class="animate-fade-in flex w-full max-w-sm flex-col gap-5 overflow-hidden rounded-3xl border bg-white p-6 text-left shadow-2xl dark:bg-slate-900"
            >
                <div class="flex items-center justify-between">
                    <h3
                        class="flex items-center gap-2 text-sm font-black text-slate-800 dark:text-slate-100"
                    >
                        💳 Ghi nhận thu hồi nợ khách hàng
                    </h3>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="h-7 w-7 rounded-xl"
                        @click="showCollectModal = false"
                    >
                        <X class="size-4" />
                    </Button>
                </div>

                <div class="space-y-4">
                    <div
                        class="space-y-1.5 rounded-xl bg-slate-50 p-3 text-xs text-slate-600 dark:bg-slate-800/40 dark:text-slate-300"
                    >
                        <div>
                            Khách hàng:
                            <strong>{{
                                selectedReceivable.customer?.full_name
                            }}</strong>
                        </div>
                        <div>
                            Đơn hàng POS:
                            <code>{{
                                selectedReceivable.order?.order_number
                            }}</code>
                        </div>
                        <div
                            class="flex justify-between border-t pt-1.5 font-bold"
                        >
                            <span>Còn nợ lại:</span>
                            <span class="text-rose-500">{{
                                vnd(
                                    selectedReceivable.amount -
                                        selectedReceivable.received_amount,
                                )
                            }}</span>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label class="text-[11px] font-bold text-slate-400"
                            >Số tiền thu hồi (đ):</Label
                        >
                        <Input
                            type="number"
                            v-model="collectForm.amount"
                            class="h-9 font-mono text-xs"
                        />
                    </div>

                    <div class="space-y-1.5">
                        <Label class="text-[11px] font-bold text-slate-400"
                            >Hình thức nhận:</Label
                        >
                        <select
                            v-model="collectForm.payment_method"
                            class="w-full rounded-lg border border-border bg-background px-3 py-2 text-xs font-semibold outline-hidden focus:ring-2 focus:ring-indigo-500/25"
                        >
                            <option value="cash">
                                Tiền mặt (Cộng vào két)
                            </option>
                            <option value="bank_transfer">Chuyển khoản</option>
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <Label class="text-[11px] font-bold text-slate-400"
                            >Ghi chú giao dịch:</Label
                        >
                        <Input
                            v-model="collectForm.notes"
                            placeholder="VD: Khách trả nợ..."
                            class="h-9 text-xs"
                        />
                    </div>
                </div>

                <div class="flex gap-2">
                    <Button
                        variant="outline"
                        class="flex-1 rounded-xl text-xs"
                        @click="showCollectModal = false"
                    >
                        Hủy
                    </Button>
                    <Button
                        class="flex-1 rounded-xl bg-emerald-600 text-xs font-bold text-white hover:bg-emerald-700"
                        @click="submitCollect"
                    >
                        Xác nhận thu nợ
                    </Button>
                </div>
            </div>
        </div>

        <!-- Edit Customer Credit Limit Modal -->
        <Teleport to="body">
            <div
                v-if="showCreditModal && selectedCustomer"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
            >
                <div
                    class="animate-fade-in flex w-full max-w-sm flex-col gap-5 overflow-hidden rounded-3xl border bg-white p-6 text-left shadow-2xl dark:bg-slate-900"
                >
                    <div class="flex items-center justify-between">
                        <h3
                            class="flex items-center gap-2 text-sm font-black text-slate-800 dark:text-slate-100"
                        >
                            ⚙️ Cấu hình hạn mức nợ CRM
                        </h3>
                        <Button
                            variant="ghost"
                            size="icon"
                            class="h-7 w-7 rounded-xl"
                            @click="showCreditModal = false"
                        >
                            <X class="size-4" />
                        </Button>
                    </div>

                    <div class="space-y-4">
                        <div
                            class="space-y-1 rounded-xl bg-slate-50 p-3 text-xs text-slate-600 dark:bg-slate-800/40 dark:text-slate-300"
                        >
                            <div>
                                Tên khách hàng:
                                <strong>{{
                                    selectedCustomer.full_name
                                }}</strong>
                            </div>
                            <div>
                                Số điện thoại:
                                <strong>{{ selectedCustomer.phone }}</strong>
                            </div>
                        </div>

                        <div class="flex items-center gap-6 py-2">
                            <div class="flex items-center gap-1.5">
                                <input
                                    type="checkbox"
                                    id="is_vip"
                                    v-model="creditForm.is_vip"
                                    class="h-4 w-4 rounded-md border-border text-indigo-600 outline-hidden"
                                />
                                <Label
                                    for="is_vip"
                                    class="cursor-pointer text-xs font-bold text-slate-600 dark:text-slate-300"
                                    >Khách VIP</Label
                                >
                            </div>

                            <div class="flex items-center gap-1.5">
                                <input
                                    type="checkbox"
                                    id="is_b2b"
                                    v-model="creditForm.is_b2b"
                                    class="h-4 w-4 rounded-md border-border text-indigo-600 outline-hidden"
                                />
                                <Label
                                    for="is_b2b"
                                    class="cursor-pointer text-xs font-bold text-slate-600 dark:text-slate-300"
                                    >Khách B2B</Label
                                >
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <Label class="text-[11px] font-bold text-slate-400"
                                >Hạn mức ghi nợ tối đa (đ):</Label
                            >
                            <Input
                                type="number"
                                v-model="creditForm.credit_limit"
                                class="h-9 font-mono text-xs"
                            />
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <Button
                            variant="outline"
                            class="flex-1 rounded-xl text-xs"
                            @click="showCreditModal = false"
                        >
                            Hủy
                        </Button>
                        <Button
                            class="flex-1 rounded-xl bg-indigo-600 text-xs font-bold text-white hover:bg-indigo-700"
                            @click="submitCredit"
                        >
                            Lưu cấu hình
                        </Button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
