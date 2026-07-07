<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    BadgeDollarSign,
    PlusCircle,
    Calendar,
    User,
    AlertTriangle,
    Clock,
    TrendingUp,
    TrendingDown,
    CheckCircle2,
    Edit2,
    FileText,
    LayoutDashboard,
    ListFilter,
    X,
    Users,
    Receipt,
    Settings,
    ArrowUpRight,
    ArrowDownLeft,
    Check
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
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
    status: 'unpaid' | 'partially_paid' | 'paid';
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
    status: 'unpaid' | 'partially_paid' | 'paid';
    notes: string | null;
    customer?: Customer;
    order?: Order;
};

type AgingReport = {
    current: number;
    '1_30': number;
    '31_60': number;
    '61_90': number;
    'over_90': number;
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
    filters: {
        payable_status: string;
        receivable_status: string;
        customer_search: string;
    };
}>();

// --- Active Tab State ---
const activeTab = ref<'overview' | 'payables' | 'receivables' | 'credit'>('overview');

// --- VND Formatter ---
const vnd = (v: number) =>
    new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(v);

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
    router.get('/debts', {
        payable_status: filtersForm.value.payable_status,
        receivable_status: filtersForm.value.receivable_status,
        customer_search: filtersForm.value.customer_search,
    }, {
        preserveState: true,
        preserveScroll: true
    });
}

function resetFilters() {
    filtersForm.value = { payable_status: '', receivable_status: '', customer_search: '' };
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

    const remaining = selectedPayable.value.amount - selectedPayable.value.paid_amount;

    if (payForm.amount <= 0 || payForm.amount > remaining) {
        toast.error(`Số tiền thanh toán phải lớn hơn 0 và không vượt quá ${vnd(remaining)}`);

        return;
    }

    payForm.post(`/debts/payables/${selectedPayable.value.id}/pay`, {
        onSuccess: () => {
            showPayModal.value = false;
            toast.success('Đã ghi nhận thanh toán nợ nhà cung cấp thành công!');
        },
        onError: (err: any) => {
            toast.error(Object.values(err)[0] as string || 'Có lỗi xảy ra');
        }
    });
}

function openCollectModal(r: AccountReceivable) {
    selectedReceivable.value = r;
    collectForm.amount = r.amount - r.received_amount;
    collectForm.payment_method = 'cash';
    collectForm.notes = '';
    showCollectModal.value = true;
}

function submitCollect() {
    if (!selectedReceivable.value) {
return;
}

    const remaining = selectedReceivable.value.amount - selectedReceivable.value.received_amount;

    if (collectForm.amount <= 0 || collectForm.amount > remaining) {
        toast.error(`Số tiền thu hồi phải lớn hơn 0 và không vượt quá ${vnd(remaining)}`);

        return;
    }

    collectForm.post(`/debts/receivables/${selectedReceivable.value.id}/collect`, {
        onSuccess: () => {
            showCollectModal.value = false;
            toast.success('Đã ghi nhận thu hồi nợ của khách hàng thành công!');
        },
        onError: (err: any) => {
            toast.error(Object.values(err)[0] as string || 'Có lỗi xảy ra');
        }
    });
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
            toast.success('Đã cập nhật hạn mức tín dụng khách hàng thành công.');
        },
        onError: (err: any) => {
            toast.error(Object.values(err)[0] as string || 'Có lỗi xảy ra');
        }
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

    <div class="flex flex-col gap-6 p-4 lg:p-6 max-w-7xl mx-auto w-full">
        <!-- Page Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b pb-5 border-border">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border border-indigo-500/20 shadow-xs">
                    <BadgeDollarSign class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-black tracking-tight text-slate-800 dark:text-slate-100">Quản Lý Công Nợ</h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Theo dõi công nợ nhà cung cấp (Accounts Payable) và công nợ mua hàng trả chậm của khách hàng VIP/B2B (Accounts Receivable).</p>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="flex gap-2 border-b pb-1 overflow-x-auto shrink-0 border-border">
            <button 
                @click="activeTab = 'overview'"
                :class="[
                    'px-4 py-2.5 text-xs font-bold transition-all border-b-2 whitespace-nowrap',
                    activeTab === 'overview' 
                        ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' 
                        : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'
                ]"
            >
                <span class="flex items-center gap-1.5"><LayoutDashboard class="size-3.5" /> Thống kê & Tuổi nợ</span>
            </button>
            <button 
                @click="activeTab = 'payables'"
                :class="[
                    'px-4 py-2.5 text-xs font-bold transition-all border-b-2 whitespace-nowrap',
                    activeTab === 'payables' 
                        ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' 
                        : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'
                ]"
            >
                <span class="flex items-center gap-1.5"><ArrowDownLeft class="size-3.5 text-rose-500" /> Nợ nhà cung cấp (Phải trả)</span>
            </button>
            <button 
                @click="activeTab = 'receivables'"
                :class="[
                    'px-4 py-2.5 text-xs font-bold transition-all border-b-2 whitespace-nowrap',
                    activeTab === 'receivables' 
                        ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' 
                        : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'
                ]"
            >
                <span class="flex items-center gap-1.5"><ArrowUpRight class="size-3.5 text-emerald-500" /> Nợ khách hàng VIP/B2B (Phải thu)</span>
            </button>
            <button 
                @click="activeTab = 'credit'"
                :class="[
                    'px-4 py-2.5 text-xs font-bold transition-all border-b-2 whitespace-nowrap',
                    activeTab === 'credit' 
                        ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' 
                        : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'
                ]"
            >
                <span class="flex items-center gap-1.5"><Users class="size-3.5" /> Hạn mức mua nợ CRM</span>
            </button>
        </div>

        <!-- ── TAB 1: OVERVIEW ── -->
        <div v-if="activeTab === 'overview'" class="space-y-6 animate-fade-in">
            <!-- Metric Cards -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <Card class="shadow-xs border-border bg-card">
                    <CardHeader class="pb-2">
                        <CardDescription class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Tổng công nợ phải thu</CardDescription>
                    </CardHeader>
                    <CardContent class="pb-5">
                        <p class="text-2xl font-black text-emerald-600 font-mono">{{ vnd(stats.total_receivable) }}</p>
                        <p class="text-[10px] text-slate-400 mt-2">Nợ từ các khách hàng VIP & B2B mua nợ tại quầy</p>
                    </CardContent>
                </Card>

                <Card class="shadow-xs border-border bg-card">
                    <CardHeader class="pb-2">
                        <CardDescription class="text-[10px] font-bold uppercase tracking-wider text-rose-500">Quá hạn phải thu</CardDescription>
                    </CardHeader>
                    <CardContent class="pb-5">
                        <p class="text-2xl font-black text-rose-600 font-mono">{{ vnd(stats.overdue_receivable) }}</p>
                        <p class="text-[10px] text-rose-400/90 mt-2 font-semibold">Cần liên hệ thu hồi sớm</p>
                    </CardContent>
                </Card>

                <Card class="shadow-xs border-border bg-card">
                    <CardHeader class="pb-2">
                        <CardDescription class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Tổng công nợ phải trả</CardDescription>
                    </CardHeader>
                    <CardContent class="pb-5">
                        <p class="text-2xl font-black text-rose-500 font-mono">{{ vnd(stats.total_payable) }}</p>
                        <p class="text-[10px] text-slate-400 mt-2">Tiền hàng nợ các nhà cung cấp nguyên liệu</p>
                    </CardContent>
                </Card>

                <Card class="shadow-xs border-border bg-card">
                    <CardHeader class="pb-2">
                        <CardDescription class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Quá hạn phải trả</CardDescription>
                    </CardHeader>
                    <CardContent class="pb-5">
                        <p class="text-2xl font-black text-slate-700 dark:text-slate-200 font-mono">{{ vnd(stats.overdue_payable) }}</p>
                        <p class="text-[10px] text-slate-400 mt-2">Cần thanh toán để duy trì SLA cung ứng</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Aging Report Block -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Receivables Aging -->
                <Card class="shadow-xs border-border">
                    <CardHeader class="pb-3 border-b bg-slate-50/40 dark:bg-slate-900/10">
                        <CardTitle class="text-sm font-bold flex items-center gap-1.5 text-slate-800 dark:text-slate-100">
                            📊 Báo cáo tuổi nợ phải thu (Khách hàng)
                        </CardTitle>
                        <CardDescription class="text-xs">Phân nhóm dư nợ phải thu theo chu kỳ quá hạn.</CardDescription>
                    </CardHeader>
                    <CardContent class="p-6 space-y-4">
                        <div v-if="stats.total_receivable === 0" class="text-center py-10 text-xs text-slate-450">
                            Không có công nợ khách hàng hiện tại.
                        </div>
                        <div v-else class="space-y-4">
                            <div 
                                v-for="(val, key) in stats.receivables_aging" 
                                :key="key" 
                                class="space-y-1.5"
                            >
                                <div class="flex justify-between items-center text-xs font-bold">
                                    <span class="text-slate-600 dark:text-slate-300">{{ agingLabels[key] }}</span>
                                    <span class="text-slate-800 dark:text-slate-200 font-mono">
                                        {{ vnd(val) }} 
                                        <span class="text-[10px] text-slate-400 font-normal">({{ getPercentage(val, stats.total_receivable) }}%)</span>
                                    </span>
                                </div>
                                <div class="h-2.5 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                                    <div 
                                        class="h-full transition-all rounded-full" 
                                        :class="agingColors[key]" 
                                        :style="`width: ${getPercentage(val, stats.total_receivable)}%`" 
                                    />
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Payables Aging -->
                <Card class="shadow-xs border-border">
                    <CardHeader class="pb-3 border-b bg-slate-50/40 dark:bg-slate-900/10">
                        <CardTitle class="text-sm font-bold flex items-center gap-1.5 text-slate-800 dark:text-slate-100">
                            📊 Báo cáo tuổi nợ phải trả (Nhà cung cấp)
                        </CardTitle>
                        <CardDescription class="text-xs">Phân nhóm dư nợ phải trả theo chu kỳ quá hạn.</CardDescription>
                    </CardHeader>
                    <CardContent class="p-6 space-y-4">
                        <div v-if="stats.total_payable === 0" class="text-center py-10 text-xs text-slate-450">
                            Không có công nợ nhà cung cấp hiện tại.
                        </div>
                        <div v-else class="space-y-4">
                            <div 
                                v-for="(val, key) in stats.payables_aging" 
                                :key="key" 
                                class="space-y-1.5"
                            >
                                <div class="flex justify-between items-center text-xs font-bold">
                                    <span class="text-slate-600 dark:text-slate-300">{{ agingLabels[key] }}</span>
                                    <span class="text-slate-800 dark:text-slate-200 font-mono">
                                        {{ vnd(val) }} 
                                        <span class="text-[10px] text-slate-400 font-normal">({{ getPercentage(val, stats.total_payable) }}%)</span>
                                    </span>
                                </div>
                                <div class="h-2.5 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                                    <div 
                                        class="h-full transition-all rounded-full" 
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
        <div v-if="activeTab === 'payables'" class="space-y-6 animate-fade-in">
            <!-- Filter box -->
            <Card class="shadow-xs border-border">
                <CardContent class="p-4 flex flex-col md:flex-row items-end gap-3 text-xs">
                    <div class="space-y-1.5 w-full md:w-1/3 text-left">
                        <Label class="text-[11px] font-bold text-slate-400">Trạng thái thanh toán:</Label>
                        <select 
                            v-model="filtersForm.payable_status"
                            class="w-full text-xs font-semibold rounded-lg border border-border bg-background px-3 py-2 outline-hidden focus:ring-2 focus:ring-indigo-500/25"
                        >
                            <option value="">-- Tất cả trạng thái --</option>
                            <option value="unpaid">Chưa trả</option>
                            <option value="partially_paid">Trả một phần</option>
                            <option value="paid">Đã thanh toán hết</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-2 shrink-0 w-full md:w-auto">
                        <Button @click="applyFilters" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs h-8 px-4">
                            <ListFilter class="size-3.5 mr-1" /> Lọc
                        </Button>
                        <Button @click="resetFilters" variant="outline" class="text-xs h-8 px-4 font-semibold">
                            Bỏ lọc
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Table -->
            <Card class="shadow-xs overflow-hidden border-border">
                <CardHeader class="pb-3 border-b bg-slate-50/40 dark:bg-slate-900/10">
                    <CardTitle class="text-sm font-bold">Danh sách công nợ phải trả nhà cung cấp</CardTitle>
                    <CardDescription class="text-xs">Theo dõi hạn thanh toán các đơn hàng PO nhập thầu.</CardDescription>
                </CardHeader>
                <CardContent class="p-0 overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50/20 dark:bg-slate-900/5 border-b font-bold text-slate-500">
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
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-600 dark:text-slate-350">
                            <tr v-if="payables.data.length === 0">
                                <td colspan="8" class="p-12 text-center text-slate-400 font-bold">Không tìm thấy công nợ nào.</td>
                            </tr>
                            <tr v-for="p in payables.data" :key="p.id" class="hover:bg-slate-50/40 dark:hover:bg-slate-900/20 transition-colors">
                                <td class="p-3 pl-5 font-bold font-mono">
                                    {{ p.purchase_order ? p.purchase_order.po_number : '—' }}
                                </td>
                                <td class="p-3 font-semibold text-slate-700 dark:text-slate-300">
                                    {{ p.supplier ? p.supplier.name : '—' }}
                                </td>
                                <td class="p-3 text-right font-mono">{{ vnd(p.amount) }}</td>
                                <td class="p-3 text-right text-emerald-600 font-mono">{{ vnd(p.paid_amount) }}</td>
                                <td class="p-3 text-right text-rose-500 font-bold font-mono">{{ vnd(p.amount - p.paid_amount) }}</td>
                                <td class="p-3 text-center font-bold font-mono">{{ p.due_date }}</td>
                                <td class="p-3 text-center">
                                    <span v-if="p.status === 'paid'" class="px-2.5 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-600 dark:bg-emerald-950/30">
                                        Đã trả hết
                                    </span>
                                    <span v-else-if="p.status === 'partially_paid'" class="px-2.5 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider bg-amber-50 text-amber-600 dark:bg-amber-950/20">
                                        Trả một phần
                                    </span>
                                    <span v-else class="px-2.5 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider bg-rose-50 text-rose-600 dark:bg-rose-950/30">
                                        Chưa thanh toán
                                    </span>
                                </td>
                                <td class="p-3 text-center">
                                    <Button 
                                        v-if="p.status !== 'paid'"
                                        @click="openPayModal(p)"
                                        size="sm"
                                        class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-[10px] h-7 px-2.5 rounded-md"
                                    >
                                        Trả nợ
                                    </Button>
                                    <span v-else class="text-slate-400 font-bold">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
                <!-- Pagination -->
                <div v-if="payables.last_page > 1" class="border-t px-5 py-3.5 flex justify-between items-center bg-slate-50/30 dark:bg-slate-900/5">
                    <span class="text-[11px] font-bold text-slate-400">Trang {{ payables.current_page }} / {{ payables.last_page }} · Tổng {{ payables.total }} dòng</span>
                    <div class="flex gap-1">
                        <Link 
                            v-for="link in payables.links" 
                            :key="link.label"
                            :href="link.url || '#'"
                            :class="[
                                'px-2.5 py-1 text-[11px] font-bold border rounded-lg transition-all',
                                link.active ? 'bg-indigo-600 border-indigo-600 text-white' : 'bg-background hover:bg-muted text-slate-600 dark:text-slate-350',
                                !link.url ? 'opacity-40 pointer-events-none' : ''
                            ]"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </Card>
        </div>

        <!-- ── TAB 3: ACCOUNTS RECEIVABLE ── -->
        <div v-if="activeTab === 'receivables'" class="space-y-6 animate-fade-in">
            <!-- Filter box -->
            <Card class="shadow-xs border-border">
                <CardContent class="p-4 flex flex-col md:flex-row items-end gap-3 text-xs">
                    <div class="space-y-1.5 w-full md:w-1/3 text-left">
                        <Label class="text-[11px] font-bold text-slate-400">Trạng thái công nợ phải thu:</Label>
                        <select 
                            v-model="filtersForm.receivable_status"
                            class="w-full text-xs font-semibold rounded-lg border border-border bg-background px-3 py-2 outline-hidden focus:ring-2 focus:ring-indigo-500/25"
                        >
                            <option value="">-- Tất cả trạng thái --</option>
                            <option value="unpaid">Chưa thu</option>
                            <option value="partially_paid">Thu một phần</option>
                            <option value="paid">Đã tất toán</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-2 shrink-0 w-full md:w-auto">
                        <Button @click="applyFilters" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs h-8 px-4">
                            <ListFilter class="size-3.5 mr-1" /> Lọc
                        </Button>
                        <Button @click="resetFilters" variant="outline" class="text-xs h-8 px-4 font-semibold">
                            Bỏ lọc
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Table -->
            <Card class="shadow-xs overflow-hidden border-border">
                <CardHeader class="pb-3 border-b bg-slate-50/40 dark:bg-slate-900/10">
                    <CardTitle class="text-sm font-bold">Danh sách công nợ phải thu (Khách hàng VIP/B2B)</CardTitle>
                    <CardDescription class="text-xs">Theo dõi quá trình thu hồi tiền hàng mua nợ của khách hàng tại POS.</CardDescription>
                </CardHeader>
                <CardContent class="p-0 overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50/20 dark:bg-slate-900/5 border-b font-bold text-slate-500">
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
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-600 dark:text-slate-350">
                            <tr v-if="receivables.data.length === 0">
                                <td colspan="8" class="p-12 text-center text-slate-400 font-bold">Không tìm thấy công nợ nào.</td>
                            </tr>
                            <tr v-for="r in receivables.data" :key="r.id" class="hover:bg-slate-50/40 dark:hover:bg-slate-900/20 transition-colors">
                                <td class="p-3 pl-5 font-bold font-mono">
                                    {{ r.order ? r.order.order_number : '—' }}
                                </td>
                                <td class="p-3 font-semibold text-slate-700 dark:text-slate-300">
                                    👤 {{ r.customer ? r.customer.full_name : '—' }}
                                    <span class="block text-[9px] text-slate-400">SĐT: {{ r.customer ? r.customer.phone : '—' }}</span>
                                </td>
                                <td class="p-3 text-right font-mono">{{ vnd(r.amount) }}</td>
                                <td class="p-3 text-right text-emerald-600 font-mono">{{ vnd(r.received_amount) }}</td>
                                <td class="p-3 text-right text-rose-500 font-bold font-mono">{{ vnd(r.amount - r.received_amount) }}</td>
                                <td class="p-3 text-center font-bold font-mono">{{ r.due_date }}</td>
                                <td class="p-3 text-center">
                                    <span v-if="r.status === 'paid'" class="px-2.5 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-600 dark:bg-emerald-950/30">
                                        Đã tất toán
                                    </span>
                                    <span v-else-if="r.status === 'partially_paid'" class="px-2.5 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider bg-amber-50 text-amber-600 dark:bg-amber-950/20">
                                        Thu một phần
                                    </span>
                                    <span v-else class="px-2.5 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider bg-rose-50 text-rose-600 dark:bg-rose-950/30">
                                        Chưa thu hồi
                                    </span>
                                </td>
                                <td class="p-3 text-center">
                                    <Button 
                                        v-if="r.status !== 'paid'"
                                        @click="openCollectModal(r)"
                                        size="sm"
                                        class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-[10px] h-7 px-2.5 rounded-md"
                                    >
                                        Thu nợ
                                    </Button>
                                    <span v-else class="text-slate-400 font-bold">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
                <!-- Pagination -->
                <div v-if="receivables.last_page > 1" class="border-t px-5 py-3.5 flex justify-between items-center bg-slate-50/30 dark:bg-slate-900/5">
                    <span class="text-[11px] font-bold text-slate-400">Trang {{ receivables.current_page }} / {{ receivables.last_page }} · Tổng {{ receivables.total }} dòng</span>
                    <div class="flex gap-1">
                        <Link 
                            v-for="link in receivables.links" 
                            :key="link.label"
                            :href="link.url || '#'"
                            :class="[
                                'px-2.5 py-1 text-[11px] font-bold border rounded-lg transition-all',
                                link.active ? 'bg-indigo-600 border-indigo-600 text-white' : 'bg-background hover:bg-muted text-slate-600 dark:text-slate-350',
                                !link.url ? 'opacity-40 pointer-events-none' : ''
                            ]"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </Card>
        </div>

        <!-- ── TAB 4: CREDIT SETUP (CRM) ── -->
        <div v-if="activeTab === 'credit'" class="space-y-6 animate-fade-in">
            <!-- Filter box -->
            <Card class="shadow-xs border-border">
                <CardContent class="p-4 flex flex-col md:flex-row items-end gap-3 text-xs">
                    <div class="space-y-1.5 w-full md:w-1/2 text-left">
                        <Label class="text-[11px] font-bold text-slate-400">Tìm kiếm khách hàng:</Label>
                        <Input 
                            v-model="filtersForm.customer_search"
                            placeholder="Nhập tên hoặc số điện thoại..."
                            class="w-full text-xs h-9"
                            @keyup.enter="applyFilters"
                        />
                    </div>

                    <div class="flex items-center gap-2 shrink-0 w-full md:w-auto">
                        <Button @click="applyFilters" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs h-9 px-4">
                            Tìm kiếm
                        </Button>
                        <Button @click="resetFilters" variant="outline" class="text-xs h-9 px-4 font-semibold">
                            Bỏ lọc
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Table -->
            <Card class="shadow-xs overflow-hidden border-border">
                <CardHeader class="pb-3 border-b bg-slate-50/40 dark:bg-slate-900/10">
                    <CardTitle class="text-sm font-bold">Cấu hình hạn mức mua nợ CRM</CardTitle>
                    <CardDescription class="text-xs">Chỉ định khách hàng VIP/B2B và gắn hạn mức nợ để thực hiện mua nợ tại POS.</CardDescription>
                </CardHeader>
                <CardContent class="p-0 overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50/20 dark:bg-slate-900/5 border-b font-bold text-slate-500">
                                <th class="p-3 pl-5">Tên khách hàng</th>
                                <th class="p-3">Số điện thoại</th>
                                <th class="p-3 text-center">Khách VIP</th>
                                <th class="p-3 text-center">Khách B2B</th>
                                <th class="p-3 text-right">Hạn mức nợ tối đa</th>
                                <th class="p-3 text-right">Dư nợ hiện tại</th>
                                <th class="p-3 text-right">Khả năng nợ còn lại</th>
                                <th class="p-3 text-center">Cấu hình</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-600 dark:text-slate-350">
                            <tr v-if="customers.data.length === 0">
                                <td colspan="8" class="p-12 text-center text-slate-400 font-bold">Không tìm thấy khách hàng nào.</td>
                            </tr>
                            <tr v-for="c in customers.data" :key="c.id" class="hover:bg-slate-50/40 dark:hover:bg-slate-900/20 transition-colors">
                                <td class="p-3 pl-5 font-bold text-slate-700 dark:text-slate-300">
                                    {{ c.full_name }}
                                </td>
                                <td class="p-3 font-mono font-semibold">{{ c.phone }}</td>
                                <td class="p-3 text-center">
                                    <span v-if="c.is_vip" class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-amber-50 text-amber-600 dark:bg-amber-950/20 border border-amber-100 dark:border-amber-900/20">VIP</span>
                                    <span v-else class="text-slate-400">—</span>
                                </td>
                                <td class="p-3 text-center">
                                    <span v-if="c.is_b2b" class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-indigo-50 text-indigo-600 dark:bg-indigo-950/30 border border-indigo-100 dark:border-indigo-900/20">B2B</span>
                                    <span v-else class="text-slate-400">—</span>
                                </td>
                                <td class="p-3 text-right font-mono font-bold">{{ vnd(c.credit_limit) }}</td>
                                <td class="p-3 text-right text-rose-500 font-mono font-bold">{{ vnd(c.current_debt) }}</td>
                                <td class="p-3 text-right text-slate-700 dark:text-slate-300 font-mono font-bold">{{ vnd(c.credit_limit - c.current_debt) }}</td>
                                <td class="p-3 text-center">
                                    <button 
                                        @click="openCreditModal(c)"
                                        class="p-1 rounded-sm text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-indigo-600 cursor-pointer"
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
                <div v-if="customers.last_page > 1" class="border-t px-5 py-3.5 flex justify-between items-center bg-slate-50/30 dark:bg-slate-900/5">
                    <span class="text-[11px] font-bold text-slate-400">Trang {{ customers.current_page }} / {{ customers.last_page }} · Tổng {{ customers.total }} dòng</span>
                    <div class="flex gap-1">
                        <Link 
                            v-for="link in customers.links" 
                            :key="link.label"
                            :href="link.url || '#'"
                            :class="[
                                'px-2.5 py-1 text-[11px] font-bold border rounded-lg transition-all',
                                link.active ? 'bg-indigo-600 border-indigo-600 text-white' : 'bg-background hover:bg-muted text-slate-600 dark:text-slate-350',
                                !link.url ? 'opacity-40 pointer-events-none' : ''
                            ]"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </Card>
        </div>

        <!-- ── MODALS ── -->

        <!-- Pay Supplier Modal -->
        <div v-if="showPayModal && selectedPayable" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white dark:bg-slate-900 rounded-3xl border shadow-2xl max-w-sm w-full overflow-hidden p-6 flex flex-col gap-5 animate-fade-in text-left">
                <div class="flex justify-between items-center">
                    <h3 class="font-black text-sm text-slate-800 dark:text-slate-100 flex items-center gap-2">
                        💳 Ghi nhận trả nợ nhà cung cấp
                    </h3>
                    <Button variant="ghost" size="icon" class="rounded-xl h-7 w-7" @click="showPayModal = false">
                        <X class="size-4" />
                    </Button>
                </div>

                <div class="space-y-4">
                    <div class="bg-slate-50 dark:bg-slate-800/40 p-3 rounded-xl space-y-1.5 text-xs text-slate-600 dark:text-slate-300">
                        <div>Nhà cung cấp: <strong>{{ selectedPayable.supplier?.name }}</strong></div>
                        <div>Đơn hàng: <code>{{ selectedPayable.purchase_order?.po_number }}</code></div>
                        <div class="border-t pt-1.5 flex justify-between font-bold">
                            <span>Còn nợ lại:</span>
                            <span class="text-rose-500">{{ vnd(selectedPayable.amount - selectedPayable.paid_amount) }}</span>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label class="text-[11px] font-bold text-slate-400">Số tiền thanh toán (đ):</Label>
                        <Input type="number" v-model="payForm.amount" class="text-xs h-9 font-mono" />
                    </div>

                    <div class="space-y-1.5">
                        <Label class="text-[11px] font-bold text-slate-400">Hình thức trả:</Label>
                        <select 
                            v-model="payForm.payment_method"
                            class="w-full text-xs font-semibold rounded-lg border border-border bg-background px-3 py-2 outline-hidden focus:ring-2 focus:ring-indigo-500/25"
                        >
                            <option value="bank_transfer">Chuyển khoản ngân hàng</option>
                            <option value="cash">Tiền mặt (Trích quỹ két)</option>
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <Label class="text-[11px] font-bold text-slate-400">Ghi chú giao dịch:</Label>
                        <Input v-model="payForm.notes" placeholder="VD: Trả nợ đợt 1..." class="text-xs h-9" />
                    </div>
                </div>

                <div class="flex gap-2">
                    <Button variant="outline" class="flex-1 rounded-xl text-xs" @click="showPayModal = false">
                        Hủy
                    </Button>
                    <Button class="flex-1 rounded-xl text-xs bg-indigo-600 hover:bg-indigo-700 text-white font-bold" @click="submitPay">
                        Xác nhận thanh toán
                    </Button>
                </div>
            </div>
        </div>

        <!-- Collect Customer Modal -->
        <div v-if="showCollectModal && selectedReceivable" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white dark:bg-slate-900 rounded-3xl border shadow-2xl max-w-sm w-full overflow-hidden p-6 flex flex-col gap-5 animate-fade-in text-left">
                <div class="flex justify-between items-center">
                    <h3 class="font-black text-sm text-slate-800 dark:text-slate-100 flex items-center gap-2">
                        💳 Ghi nhận thu hồi nợ khách hàng
                    </h3>
                    <Button variant="ghost" size="icon" class="rounded-xl h-7 w-7" @click="showCollectModal = false">
                        <X class="size-4" />
                    </Button>
                </div>

                <div class="space-y-4">
                    <div class="bg-slate-50 dark:bg-slate-800/40 p-3 rounded-xl space-y-1.5 text-xs text-slate-600 dark:text-slate-300">
                        <div>Khách hàng: <strong>{{ selectedReceivable.customer?.full_name }}</strong></div>
                        <div>Đơn hàng POS: <code>{{ selectedReceivable.order?.order_number }}</code></div>
                        <div class="border-t pt-1.5 flex justify-between font-bold">
                            <span>Còn nợ lại:</span>
                            <span class="text-rose-500">{{ vnd(selectedReceivable.amount - selectedReceivable.received_amount) }}</span>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label class="text-[11px] font-bold text-slate-400">Số tiền thu hồi (đ):</Label>
                        <Input type="number" v-model="collectForm.amount" class="text-xs h-9 font-mono" />
                    </div>

                    <div class="space-y-1.5">
                        <Label class="text-[11px] font-bold text-slate-400">Hình thức nhận:</Label>
                        <select 
                            v-model="collectForm.payment_method"
                            class="w-full text-xs font-semibold rounded-lg border border-border bg-background px-3 py-2 outline-hidden focus:ring-2 focus:ring-indigo-500/25"
                        >
                            <option value="cash">Tiền mặt (Cộng vào két)</option>
                            <option value="bank_transfer">Chuyển khoản</option>
                        </select>
                    </div>

                    <div class="space-y-1.5">
                        <Label class="text-[11px] font-bold text-slate-400">Ghi chú giao dịch:</Label>
                        <Input v-model="collectForm.notes" placeholder="VD: Khách trả nợ..." class="text-xs h-9" />
                    </div>
                </div>

                <div class="flex gap-2">
                    <Button variant="outline" class="flex-1 rounded-xl text-xs" @click="showCollectModal = false">
                        Hủy
                    </Button>
                    <Button class="flex-1 rounded-xl text-xs bg-emerald-600 hover:bg-emerald-700 text-white font-bold" @click="submitCollect">
                        Xác nhận thu nợ
                    </Button>
                </div>
            </div>
        </div>

        <!-- Edit Customer Credit Limit Modal -->
        <div v-if="showCreditModal && selectedCustomer" class="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
            <div class="bg-white dark:bg-slate-900 rounded-3xl border shadow-2xl max-w-sm w-full overflow-hidden p-6 flex flex-col gap-5 animate-fade-in text-left">
                <div class="flex justify-between items-center">
                    <h3 class="font-black text-sm text-slate-800 dark:text-slate-100 flex items-center gap-2">
                        ⚙️ Cấu hình hạn mức nợ CRM
                    </h3>
                    <Button variant="ghost" size="icon" class="rounded-xl h-7 w-7" @click="showCreditModal = false">
                        <X class="size-4" />
                    </Button>
                </div>

                <div class="space-y-4">
                    <div class="bg-slate-50 dark:bg-slate-800/40 p-3 rounded-xl space-y-1 text-xs text-slate-600 dark:text-slate-300">
                        <div>Tên khách hàng: <strong>{{ selectedCustomer.full_name }}</strong></div>
                        <div>Số điện thoại: <strong>{{ selectedCustomer.phone }}</strong></div>
                    </div>

                    <div class="flex items-center gap-6 py-2">
                        <div class="flex items-center gap-1.5">
                            <input type="checkbox" id="is_vip" v-model="creditForm.is_vip" class="h-4 w-4 rounded-md border-border text-indigo-600 outline-hidden" />
                            <Label for="is_vip" class="text-xs font-bold text-slate-600 dark:text-slate-300 cursor-pointer">Khách VIP</Label>
                        </div>

                        <div class="flex items-center gap-1.5">
                            <input type="checkbox" id="is_b2b" v-model="creditForm.is_b2b" class="h-4 w-4 rounded-md border-border text-indigo-600 outline-hidden" />
                            <Label for="is_b2b" class="text-xs font-bold text-slate-600 dark:text-slate-300 cursor-pointer">Khách B2B</Label>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <Label class="text-[11px] font-bold text-slate-400">Hạn mức ghi nợ tối đa (đ):</Label>
                        <Input type="number" v-model="creditForm.credit_limit" class="text-xs h-9 font-mono" />
                    </div>
                </div>

                <div class="flex gap-2">
                    <Button variant="outline" class="flex-1 rounded-xl text-xs" @click="showCreditModal = false">
                        Hủy
                    </Button>
                    <Button class="flex-1 rounded-xl text-xs bg-indigo-600 hover:bg-indigo-700 text-white font-bold" @click="submitCredit">
                        Lưu cấu hình
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
