<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import {
    Wallet,
    ArrowUpRight,
    ArrowDownRight,
    PlusCircle,
    MinusCircle,
    AlertTriangle,
    Clock,
    TrendingUp,
    ShieldAlert,
    CheckCircle,
    History,
    BarChart3,
    ArrowRightLeft,
    Loader2,
    CheckCircle2,
    Info,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

type Register = {
    id: number;
    closing_date: string;
    branch_id: number | null;
    branch_name: string;
    shift_name: string;
    opened_by_name: string;
    closed_by_name: string;
    opening_balance: number;
    closing_balance: number;
    expected_closing_balance: number;
    difference: number;
    expense_budget: number;
    status: 'open' | 'closed';
    opened_at: string;
    closed_at: string | null;
    notes: string | null;
};

type ActiveRegister = {
    id: number | null;
    branch_id: number | null;
    branch_name: string;
    opening_balance: number;
    expense_budget: number;
    opened_at: string;
    opened_by_name: string;
    shift_name: string;
    closing_date: string;
    expected_cash: number;
    is_aggregate: boolean;
    register_count: number;
};

type Transaction = {
    id: number;
    branch_id: number | null;
    branch_name: string;
    type: 'in' | 'out';
    amount: number;
    source: string;
    notes: string;
    created_by_name: string;
    occurred_at: string;
};

type Shift = {
    id: number;
    name: string;
    code: string;
};

type ChartData = {
    date: string;
    in: number;
    out: number;
    net: number;
};

type Forecast = {
    avg_daily_in: number;
    avg_daily_out: number;
    current_cash: number;
    projected_in: number;
    projected_out: number;
    projected_balance: number;
    status: 'safe' | 'warning' | 'low_reserve';
    message: string;
};

const props = defineProps<{
    isAllBranches: boolean;
    activeRegister: ActiveRegister | null;
    activeTransactions: Transaction[];
    registers: Register[];
    chartData: ChartData[];
    shifts: Shift[];
    forecast: Forecast;
}>();

const activeTab = ref<'active' | 'history' | 'analytics' | 'forecast'>(
    'active',
);

const vnd = (v: number) => {
    const val = Number(v);

    if (isNaN(val) || val === undefined || val === null) {
        return '0 ₫';
    }

    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(val);
};

// Pagination for Registers Table (10 rows per page)
const itemsPerPage = 10;
const currentPage = ref(1);

const totalPages = computed(() => {
    return Math.ceil(props.registers.length / itemsPerPage) || 1;
});

const paginatedRegisters = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;

    return props.registers.slice(start, start + itemsPerPage);
});

const goToPage = (p: number) => {
    if (p >= 1 && p <= totalPages.value) {
        currentPage.value = p;
    }
};

// Modals
const showOpenModal = ref(false);
const showTransactionModal = ref(false);
const transactionModalType = ref<'in' | 'out'>('out');

// Open Register Form
const openForm = useForm({
    shift_id: '',
    opening_balance: 0,
    expense_budget: 0,
    notes: '',
});

// Transaction Form
const txForm = useForm({
    type: 'out',
    amount: 0,
    source: 'expense',
    notes: '',
});

function handleOpenRegister() {
    if (!openForm.shift_id) {
        toast.error('Vui lòng chọn ca làm việc');

        return;
    }

    openForm.post('/cash-flow/registers', {
        onSuccess: () => {
            showOpenModal.value = false;
            openForm.reset();
            toast.success('Đã mở két đầu ca thành công!');
        },
        onError: (err: any) => {
            toast.error((Object.values(err)[0] as string) || 'Có lỗi xảy ra');
        },
    });
}

function handleAddTransaction() {
    if (txForm.amount <= 0) {
        toast.error('Số tiền phải lớn hơn 0');

        return;
    }

    if (!txForm.notes.trim()) {
        toast.error('Vui lòng nhập ghi chú chi tiết');

        return;
    }

    txForm.type = transactionModalType.value;
    txForm.source = transactionModalType.value === 'out' ? 'expense' : 'other';

    txForm.post('/cash-flow/transactions', {
        onSuccess: () => {
            showTransactionModal.value = false;
            txForm.reset();
            toast.success('Đã ghi nhận giao dịch dòng tiền!');
        },
        onError: (err: any) => {
            toast.error((Object.values(err)[0] as string) || 'Có lỗi xảy ra');
        },
    });
}

function openTxModal(type: 'in' | 'out') {
    transactionModalType.value = type;
    txForm.reset();
    txForm.type = type;
    showTransactionModal.value = true;
}

// Compute active register budget status
const activeExpensesTotal = computed(() => {
    return props.activeTransactions
        .filter((t) => t.type === 'out')
        .reduce((sum, t) => sum + t.amount, 0);
});

const isBudgetExceeded = computed(() => {
    if (!props.activeRegister || !props.activeRegister.expense_budget) {
        return false;
    }

    return activeExpensesTotal.value > props.activeRegister.expense_budget;
});

const budgetProgressPercent = computed(() => {
    if (!props.activeRegister || !props.activeRegister.expense_budget) {
        return 0;
    }

    const percent =
        (activeExpensesTotal.value / props.activeRegister.expense_budget) * 100;

    return Math.min(100, Math.max(0, percent));
});

// Pure Tailwind bar chart max value
const chartMaxVal = computed(() => {
    let max = 0;
    props.chartData.forEach((d) => {
        if (d.in > max) {
            max = d.in;
        }

        if (d.out > max) {
            max = d.out;
        }
    });

    return max || 1;
});
</script>

<template>
    <Head title="Quản lý Két tiền & Dòng tiền" />

    <div
        class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 text-slate-800 lg:p-6 dark:text-slate-100"
    >
        <!-- Modern Premium Page Header -->
        <div
            class="relative overflow-hidden rounded-3xl border border-slate-100 bg-linear-to-r from-indigo-50/40 via-purple-50/10 to-transparent p-6 shadow-xs dark:border-slate-800/80 dark:from-slate-900/60 dark:via-slate-900/10 dark:to-transparent"
        >
            <!-- Background mesh accent -->
            <div
                class="absolute -top-16 -right-16 h-36 w-36 rounded-full bg-indigo-500/10 blur-3xl"
            />
            <div
                class="absolute -top-8 -right-8 h-20 w-20 rounded-full bg-purple-500/10 blur-2xl"
            />

            <div
                class="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-center gap-4">
                    <div
                        class="relative flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-gradient-to-tr from-indigo-600 to-violet-500 text-white shadow-md shadow-indigo-500/20"
                    >
                        <Wallet class="size-7" />
                        <span class="absolute -top-1 -right-1 flex h-3.5 w-3.5">
                            <span
                                class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"
                            ></span>
                            <span
                                class="relative inline-flex h-3.5 w-3.5 rounded-full border border-white bg-emerald-500 dark:border-slate-900"
                            ></span>
                        </span>
                    </div>
                    <div>
                        <h1
                            class="bg-gradient-to-r from-slate-900 via-slate-800 to-indigo-950 bg-clip-text text-2xl font-black tracking-tight text-transparent dark:from-slate-100 dark:via-slate-200 dark:to-indigo-300"
                        >
                            Quỹ Tiền Mặt & Dòng Tiền
                        </h1>
                        <p
                            class="mt-1 text-xs font-semibold text-slate-500 dark:text-slate-400"
                        >
                            Quản lý két tiền ca, kiểm soát thu chi tại quầy, dự
                            báo dòng tiền an toàn.
                        </p>
                    </div>
                </div>

                <!-- Open drawer button (only if no active drawer) -->
                <div v-if="!activeRegister && !isAllBranches" class="shrink-0">
                    <Button
                        @click="showOpenModal = true"
                        class="h-10 cursor-pointer rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 px-5 text-xs font-bold text-white shadow-lg shadow-indigo-500/20 transition-all hover:from-indigo-700 hover:to-violet-700 hover:shadow-indigo-600/30 active:scale-95"
                    >
                        <PlusCircle class="mr-2 size-4" />
                        Mở két đầu ca
                    </Button>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs with modern Pill design -->
        <div
            class="border-slate-150 flex shrink-0 gap-2 overflow-x-auto border-b pb-1.5 dark:border-slate-800/80"
        >
            <button
                @click="activeTab = 'active'"
                :class="[
                    'flex cursor-pointer items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition-all duration-300 active:scale-95',
                    activeTab === 'active'
                        ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/15 dark:bg-indigo-500'
                        : 'text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-900/60 dark:hover:text-slate-200',
                ]"
            >
                <Wallet class="size-4" />
                Két tiền ca hiện tại
            </button>
            <button
                @click="activeTab = 'history'"
                :class="[
                    'flex cursor-pointer items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition-all duration-300 active:scale-95',
                    activeTab === 'history'
                        ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/15 dark:bg-indigo-500'
                        : 'text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-900/60 dark:hover:text-slate-200',
                ]"
            >
                <History class="size-4" />
                Lịch sử chốt két
            </button>
            <button
                @click="activeTab = 'analytics'"
                :class="[
                    'flex cursor-pointer items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition-all duration-300 active:scale-95',
                    activeTab === 'analytics'
                        ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/15 dark:bg-indigo-500'
                        : 'text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-900/60 dark:hover:text-slate-200',
                ]"
            >
                <BarChart3 class="size-4" />
                Biểu đồ dòng tiền (30d)
            </button>
            <button
                @click="activeTab = 'forecast'"
                :class="[
                    'flex cursor-pointer items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition-all duration-300 active:scale-95',
                    activeTab === 'forecast'
                        ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/15 dark:bg-indigo-500'
                        : 'text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:text-slate-400 dark:hover:bg-slate-900/60 dark:hover:text-slate-200',
                ]"
            >
                <TrendingUp class="size-4" />
                Dự báo dòng tiền
            </button>
        </div>

        <!-- tab: ACTIVE DRAWER -->
        <div v-if="activeTab === 'active'" class="animate-fade-in space-y-6">
            <div
                v-if="activeRegister"
                class="grid grid-cols-1 gap-6 lg:grid-cols-3"
            >
                <!-- Left panel: drawer stats card -->
                <div class="space-y-6 lg:col-span-1">
                    <!-- Shift info card -->
                    <Card
                        class="overflow-hidden border-slate-100 bg-card shadow-md shadow-slate-200/50 transition-all duration-300 hover:shadow-lg dark:border-slate-800/80 dark:shadow-none"
                    >
                        <CardHeader
                            class="dark:border-slate-850 border-b border-slate-100 bg-slate-50/40 pb-3 dark:bg-slate-900/30"
                        >
                            <CardTitle
                                class="flex items-center gap-2 text-xs font-black tracking-wider text-slate-500 uppercase dark:text-slate-400"
                            >
                                <Clock class="size-4 text-indigo-500" />
                                Chi tiết phiên trực
                            </CardTitle>
                        </CardHeader>
                        <CardContent
                            class="divide-y divide-slate-100 p-5 text-xs dark:divide-slate-800/80"
                        >
                            <div
                                class="flex items-center justify-between py-2.5"
                            >
                                <span class="font-semibold text-slate-400"
                                    >Chi nhánh</span
                                >
                                <span
                                    class="rounded-md bg-indigo-50 px-2 py-0.5 font-extrabold text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-400"
                                >
                                    {{ activeRegister.branch_name }}
                                </span>
                            </div>
                            <div
                                class="flex items-center justify-between py-2.5"
                            >
                                <span class="font-semibold text-slate-400"
                                    >Ca đang hoạt động</span
                                >
                                <span
                                    class="rounded-md bg-indigo-50 px-2 py-0.5 font-extrabold text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-400"
                                >
                                    {{ activeRegister.shift_name }}
                                </span>
                            </div>
                            <div
                                class="flex items-center justify-between py-2.5"
                            >
                                <span class="font-semibold text-slate-400"
                                    >Người mở két</span
                                >
                                <span
                                    class="font-bold text-slate-700 dark:text-slate-200"
                                >
                                    {{ activeRegister.opened_by_name }}
                                </span>
                            </div>
                            <div
                                class="flex items-center justify-between py-2.5"
                            >
                                <span class="font-semibold text-slate-400"
                                    >Ngày làm việc</span
                                >
                                <span
                                    class="font-bold text-slate-700 dark:text-slate-200"
                                >
                                    {{
                                        new Date(
                                            activeRegister.closing_date,
                                        ).toLocaleDateString('vi-VN')
                                    }}
                                </span>
                            </div>
                            <div
                                class="flex items-center justify-between py-2.5"
                            >
                                <span class="font-semibold text-slate-400"
                                    >Mở két lúc</span
                                >
                                <span
                                    class="font-mono font-bold text-slate-600 dark:text-slate-300"
                                >
                                    {{ activeRegister.opened_at }}
                                </span>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Budget & Alert Card with Progress Bar -->
                    <Card
                        class="overflow-hidden border-slate-100 shadow-md shadow-slate-200/50 dark:border-slate-800/80 dark:shadow-none"
                    >
                        <CardHeader
                            class="dark:border-slate-850 border-b border-slate-100 bg-slate-50/40 pb-3 dark:bg-slate-900/30"
                        >
                            <CardTitle
                                class="flex items-center justify-between text-xs font-black tracking-wider text-slate-500 uppercase dark:text-slate-400"
                            >
                                <span class="flex items-center gap-2">
                                    <AlertTriangle
                                        class="size-4"
                                        :class="
                                            isBudgetExceeded
                                                ? 'animate-pulse text-rose-500'
                                                : 'text-amber-500'
                                        "
                                    />
                                    Ngân sách chi tiêu ca
                                </span>
                                <span
                                    v-if="activeRegister.expense_budget > 0"
                                    class="font-mono text-[10px] font-bold text-slate-400"
                                >
                                    {{ Math.round(budgetProgressPercent) }}%
                                </span>
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-5 p-5">
                            <div
                                class="flex items-center justify-between text-xs"
                            >
                                <span class="font-semibold text-slate-400"
                                    >Hạn mức tối đa:</span
                                >
                                <span
                                    class="font-mono font-bold text-slate-700 dark:text-slate-200"
                                >
                                    {{
                                        activeRegister.expense_budget > 0
                                            ? vnd(activeRegister.expense_budget)
                                            : 'Không hạn chế'
                                    }}
                                </span>
                            </div>
                            <div
                                class="flex items-center justify-between text-xs"
                            >
                                <span class="font-semibold text-slate-400"
                                    >Đã chi ngoài:</span
                                >
                                <span
                                    class="font-mono font-bold text-slate-700 dark:text-slate-200"
                                >
                                    {{ vnd(activeExpensesTotal) }}
                                </span>
                            </div>

                            <!-- Budget progress indicator bar -->
                            <div
                                v-if="activeRegister.expense_budget > 0"
                                class="space-y-1"
                            >
                                <div
                                    class="relative h-2 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                                >
                                    <div
                                        class="h-full rounded-full transition-all duration-500"
                                        :class="[
                                            isBudgetExceeded
                                                ? 'bg-rose-550 shadow-[0_0_10px_rgba(239,68,68,0.3)]'
                                                : budgetProgressPercent > 80
                                                  ? 'bg-amber-500'
                                                  : 'bg-emerald-500',
                                        ]"
                                        :style="{
                                            width: budgetProgressPercent + '%',
                                        }"
                                    />
                                </div>
                                <div
                                    class="flex justify-between text-[9px] font-bold text-slate-400 uppercase"
                                >
                                    <span>An toàn</span>
                                    <span
                                        :class="
                                            isBudgetExceeded
                                                ? 'text-rose-500'
                                                : ''
                                        "
                                        >Hết hạn mức</span
                                    >
                                </div>
                            </div>

                            <!-- Budget Alert callouts -->
                            <div
                                v-if="isBudgetExceeded"
                                class="flex gap-3 rounded-2xl border border-rose-100 bg-rose-50/50 p-4 dark:border-rose-950/20 dark:bg-rose-950/20"
                            >
                                <ShieldAlert
                                    class="mt-0.5 size-5 shrink-0 animate-bounce text-rose-500"
                                />
                                <div>
                                    <p
                                        class="text-xs font-bold text-rose-700 dark:text-rose-400"
                                    >
                                        Đã vượt ngân sách ca!
                                    </p>
                                    <p
                                        class="mt-1 text-[10px] leading-relaxed text-rose-500/90 dark:text-rose-500/80"
                                    >
                                        Tổng chi tiêu ngoài két đã vượt quá hạn
                                        mức tối đa
                                        {{ vnd(activeRegister.expense_budget) }}
                                        quy định ban đầu.
                                    </p>
                                </div>
                            </div>
                            <div
                                v-else-if="activeRegister.expense_budget > 0"
                                class="flex gap-3 rounded-2xl border border-emerald-100 bg-emerald-50/50 p-4 dark:border-emerald-950/20 dark:bg-emerald-950/20"
                            >
                                <CheckCircle2
                                    class="text-emerald-550 mt-0.5 size-5 shrink-0"
                                />
                                <div>
                                    <p
                                        class="text-xs font-bold text-emerald-700 dark:text-emerald-400"
                                    >
                                        Trong tầm kiểm soát
                                    </p>
                                    <p
                                        class="mt-1 text-[10px] leading-relaxed text-emerald-600 dark:text-emerald-500/80"
                                    >
                                        Ngân quỹ chi tiêu ngoài còn lại:
                                        <strong
                                            class="font-mono text-emerald-700 dark:text-emerald-400"
                                            >{{
                                                vnd(
                                                    activeRegister.expense_budget -
                                                        activeExpensesTotal,
                                                )
                                            }}</strong
                                        >
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Right panel: drawer cash balance and transactions log -->
                <div class="space-y-6 lg:col-span-2">
                    <!-- Balance Display & Quick Actions -->
                    <Card
                        class="relative overflow-hidden border-slate-100 shadow-md shadow-slate-200/50 dark:border-slate-800/80 dark:shadow-none"
                    >
                        <!-- Spot glow effect in top corner -->
                        <div
                            class="absolute -top-24 -right-24 h-48 w-48 rounded-full bg-indigo-500/5 blur-3xl"
                        />

                        <CardContent class="p-6">
                            <div
                                class="flex flex-col gap-6 border-b border-slate-100 pb-6 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800/80"
                            >
                                <div>
                                    <p
                                        class="text-xs font-black tracking-wider text-slate-400 uppercase"
                                    >
                                        Tiền mặt thực tế dự kiến trong két
                                    </p>
                                    <div class="mt-2 flex items-baseline gap-2">
                                        <p
                                            class="font-mono text-4xl font-black tracking-tight text-indigo-600 dark:text-indigo-400"
                                        >
                                            {{
                                                vnd(
                                                    activeRegister.expected_cash,
                                                )
                                            }}
                                        </p>
                                        <span
                                            class="rounded-md bg-indigo-50 px-1.5 py-0.5 text-[10px] font-bold text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-400"
                                        >
                                            Trong két
                                        </span>
                                    </div>
                                    <p
                                        class="mt-2 text-[10px] font-medium text-slate-400"
                                    >
                                        Gồm: Số dư mở đầu ca ({{
                                            vnd(activeRegister.opening_balance)
                                        }}) + Thu tiền ca - Chi tiền ca
                                    </p>
                                </div>

                                <div
                                    v-if="!isAllBranches"
                                    class="flex shrink-0 flex-wrap gap-2.5"
                                >
                                    <Button
                                        @click="openTxModal('out')"
                                        variant="outline"
                                        class="h-10 cursor-pointer rounded-xl border-rose-100 text-xs font-extrabold text-rose-600 shadow-xs transition-all hover:bg-rose-50 hover:text-rose-700 active:scale-95 dark:border-rose-950/30 dark:hover:bg-rose-950/20"
                                    >
                                        <MinusCircle class="mr-2 size-4.5" />
                                        Chi tiền ngoài két
                                    </Button>
                                    <Button
                                        @click="openTxModal('in')"
                                        variant="outline"
                                        class="h-10 cursor-pointer rounded-xl border-emerald-100 text-xs font-extrabold text-emerald-600 shadow-xs transition-all hover:bg-emerald-50 hover:text-emerald-700 active:scale-95 dark:border-emerald-950/30 dark:hover:bg-emerald-950/20"
                                    >
                                        <PlusCircle class="mr-2 size-4.5" />
                                        Thu tiền mặt khác
                                    </Button>
                                </div>
                                <p
                                    v-else
                                    class="max-w-xs text-right text-[10px] font-semibold text-amber-600 dark:text-amber-400"
                                >
                                    Chuyển sang một chi nhánh cụ thể để ghi nhận
                                    giao dịch tiền mặt.
                                </p>
                            </div>

                            <!-- Live transactions log list -->
                            <div class="pt-6">
                                <h3
                                    class="mb-4 flex items-center gap-2 text-xs font-black tracking-wider text-slate-500 uppercase"
                                >
                                    <ArrowRightLeft
                                        class="size-4.5 text-slate-400"
                                    />
                                    Biến động két tiền ca trực
                                </h3>

                                <div
                                    v-if="activeTransactions.length === 0"
                                    class="flex flex-col items-center justify-center py-12 text-center text-slate-400"
                                >
                                    <Info class="mb-2 size-8 opacity-20" />
                                    <p class="text-xs font-bold text-slate-400">
                                        Chưa phát sinh giao dịch tiền mặt
                                    </p>
                                    <p
                                        class="mt-0.5 text-[10px] text-slate-400/80"
                                    >
                                        Nhấn nút bên trên để bắt đầu ghi nhận
                                        các khoản thu chi ngoài hệ thống
                                    </p>
                                </div>

                                <div
                                    v-else
                                    class="max-h-[350px] divide-y divide-slate-100 overflow-y-auto pr-1 dark:divide-slate-800/80"
                                >
                                    <div
                                        v-for="tx in activeTransactions"
                                        :key="tx.id"
                                        class="flex items-center justify-between py-3.5 transition-colors hover:bg-slate-50/20 dark:hover:bg-slate-900/10"
                                    >
                                        <div class="flex items-center gap-3">
                                            <div
                                                :class="[
                                                    'flex h-9 w-9 items-center justify-center rounded-xl border-2 shadow-xs',
                                                    tx.type === 'in'
                                                        ? 'border-emerald-50 bg-emerald-50 text-emerald-600 dark:border-emerald-950/30 dark:bg-emerald-950/40 dark:text-emerald-400'
                                                        : 'dark:text-rose-450 border-rose-50 bg-rose-50 text-rose-600 dark:border-rose-950/30 dark:bg-rose-950/40',
                                                ]"
                                            >
                                                <component
                                                    :is="
                                                        tx.type === 'in'
                                                            ? ArrowUpRight
                                                            : ArrowDownRight
                                                    "
                                                    class="size-4.5"
                                                />
                                            </div>
                                            <div>
                                                <p
                                                    class="text-xs font-bold text-slate-700 dark:text-slate-200"
                                                >
                                                    {{ tx.notes }}
                                                </p>
                                                <p
                                                    class="mt-1 text-[10px] font-semibold text-slate-400/90"
                                                >
                                                    Chi nhánh:
                                                    <strong
                                                        class="text-slate-500"
                                                        >{{
                                                            tx.branch_name
                                                        }}</strong
                                                    >
                                                    · Nhân viên:
                                                    <strong
                                                        class="text-slate-500"
                                                        >{{
                                                            tx.created_by_name
                                                        }}</strong
                                                    >
                                                    · lúc {{ tx.occurred_at }}
                                                </p>
                                            </div>
                                        </div>

                                        <span
                                            :class="[
                                                'font-mono text-sm font-black',
                                                tx.type === 'in'
                                                    ? 'text-emerald-600 dark:text-emerald-400'
                                                    : 'text-rose-600 dark:text-rose-400',
                                            ]"
                                        >
                                            {{ tx.type === 'in' ? '+' : '-'
                                            }}{{ vnd(tx.amount) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- Empty state when no register is active -->
            <div
                v-else
                class="flex flex-col items-center justify-center rounded-3xl border-2 border-dashed border-slate-200 bg-white/40 py-20 text-center dark:border-slate-800 dark:bg-slate-950/20"
            >
                <div
                    class="relative mb-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/30 dark:text-indigo-400"
                >
                    <Wallet class="size-8" />
                    <span
                        class="absolute -right-1.5 -bottom-1.5 rounded-full bg-amber-500 p-0.5 text-white shadow-xs"
                    >
                        <AlertTriangle class="size-4" />
                    </span>
                </div>
                <div>
                    <h2
                        class="text-base font-black text-slate-800 dark:text-slate-200"
                    >
                        Chưa mở két tiền mặt ca trực
                    </h2>
                    <p
                        v-if="!isAllBranches"
                        class="mx-auto mt-1 max-w-sm text-xs font-semibold text-slate-400"
                    >
                        Để ghi nhận doanh thu tiền mặt tại quầy hoặc các khoản
                        chi lặt vặt phát sinh trong ca, vui lòng mở két tiền
                        mặt.
                    </p>
                    <p
                        v-else
                        class="mx-auto mt-1 max-w-sm text-xs font-semibold text-slate-400"
                    >
                        Đang xem toàn chuỗi. Hãy chọn một chi nhánh cụ thể để mở
                        két hoặc ghi nhận giao dịch tiền mặt.
                    </p>
                </div>
                <Button
                    v-if="!isAllBranches"
                    @click="showOpenModal = true"
                    class="mt-6 h-10 cursor-pointer rounded-xl bg-indigo-600 px-6 text-xs font-bold text-white shadow-lg shadow-indigo-500/15 hover:bg-indigo-700 active:scale-95 dark:bg-indigo-500"
                >
                    <PlusCircle class="mr-2 size-4.5" />
                    Mở két đầu ca ngay
                </Button>
            </div>
        </div>

        <!-- tab: REGISTERS HISTORY -->
        <div v-if="activeTab === 'history'" class="animate-fade-in space-y-6">
            <Card
                class="overflow-hidden border-slate-100 shadow-md shadow-slate-200/50 dark:border-slate-800/80 dark:shadow-none"
            >
                <CardHeader
                    class="dark:border-slate-850 border-b border-slate-100 bg-slate-50/40 pb-3 dark:bg-slate-900/30"
                >
                    <CardTitle
                        class="text-sm font-black tracking-wider text-slate-500 uppercase dark:text-slate-400"
                    >
                        Nhật ký chốt ca két tiền mặt
                    </CardTitle>
                    <CardDescription class="text-xs">
                        Lịch sử đối soát bàn giao két cuối mỗi ca làm việc, theo
                        dõi chênh lệch ngân quỹ thực tế.
                    </CardDescription>
                </CardHeader>
                <CardContent class="overflow-x-auto p-0">
                    <table class="w-full border-collapse text-left text-xs">
                        <thead>
                            <tr
                                class="border-b border-slate-100 bg-slate-50/20 font-bold text-slate-500 dark:border-slate-800 dark:bg-slate-900/5"
                            >
                                <th class="p-3.5 pl-6">Ngày chốt</th>
                                <th class="p-3.5">Chi nhánh</th>
                                <th class="p-3.5">Ca trực</th>
                                <th class="p-3.5">Thủ quỹ mở / đóng</th>
                                <th
                                    class="cursor-help p-3.5 text-right"
                                    title="Số tiền có sẵn trong két trước khi bắt đầu ca."
                                >
                                    Số dư đầu ca
                                </th>
                                <th
                                    class="cursor-help p-3.5 text-right"
                                    title="Công thức: Số dư đầu ca + Tổng tiền vào - Tổng tiền ra."
                                >
                                    Tiền chốt ca
                                </th>
                                <th
                                    class="cursor-help p-3.5 text-right"
                                    title="Số tiền mặt thực tế được kiểm đếm và nhập khi chốt ca."
                                >
                                    Thực tế đóng
                                </th>
                                <th
                                    class="cursor-help p-3.5 text-right"
                                    title="Công thức: Thực tế đóng - Tiền chốt ca. Số âm là thiếu, số dương là dư."
                                >
                                    Chênh lệch
                                </th>
                                <th class="p-3.5 text-center">Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-100 text-slate-600 dark:divide-slate-800/80 dark:text-slate-300"
                        >
                            <tr v-if="registers.length === 0">
                                <td
                                    colspan="9"
                                    class="p-12 text-center text-slate-400"
                                >
                                    <Info
                                        class="mx-auto mb-2 size-8 opacity-25"
                                    />
                                    Chưa ghi nhận phiên làm việc chốt ca nào.
                                </td>
                            </tr>
                            <tr
                                v-for="r in paginatedRegisters"
                                :key="r.id"
                                class="transition-colors hover:bg-slate-50/40 dark:hover:bg-slate-900/30"
                            >
                                <td class="p-3.5 pl-6 font-black">
                                    {{ r.closing_date }}
                                </td>
                                <td class="p-3.5 font-bold">
                                    <span
                                        class="rounded bg-indigo-50 px-2 py-0.5 text-[10px] font-bold text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-400"
                                    >
                                        {{ r.branch_name }}
                                    </span>
                                </td>
                                <td class="p-3.5">
                                    <span
                                        class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-400"
                                    >
                                        {{ r.shift_name }}
                                    </span>
                                </td>
                                <td class="p-3.5">
                                    <p
                                        class="font-bold text-slate-700 dark:text-slate-200"
                                    >
                                        {{ r.opened_by_name }}
                                    </p>
                                    <p
                                        class="mt-0.5 text-[10px] text-slate-400/90"
                                    >
                                        Đóng két: {{ r.closed_by_name }}
                                    </p>
                                </td>
                                <td
                                    class="p-3.5 text-right font-mono font-bold"
                                >
                                    {{ vnd(r.opening_balance) }}
                                </td>
                                <td
                                    class="p-3.5 text-right font-mono font-bold"
                                >
                                    {{
                                        r.status === 'closed'
                                            ? vnd(r.expected_closing_balance)
                                            : '—'
                                    }}
                                </td>
                                <td
                                    class="p-3.5 text-right font-mono font-black text-slate-700 dark:text-slate-100"
                                >
                                    {{
                                        r.status === 'closed'
                                            ? vnd(r.closing_balance)
                                            : '—'
                                    }}
                                </td>
                                <td
                                    class="p-3.5 text-right font-mono font-black"
                                >
                                    <span
                                        v-if="r.status === 'closed'"
                                        :class="[
                                            'rounded-md px-1.5 py-0.5 text-xs',
                                            r.difference > 0
                                                ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-950/20 dark:text-emerald-400'
                                                : r.difference < 0
                                                  ? 'dark:text-rose-455 bg-rose-50 text-rose-600 dark:bg-rose-950/20'
                                                  : 'text-slate-400',
                                        ]"
                                    >
                                        {{ r.difference > 0 ? '+' : ''
                                        }}{{
                                            r.difference !== 0
                                                ? vnd(r.difference)
                                                : 'Khớp'
                                        }}
                                    </span>
                                    <span v-else>—</span>
                                </td>
                                <td class="p-3.5 text-center">
                                    <span
                                        :class="[
                                            'rounded-full px-2.5 py-0.5 text-[9px] font-black tracking-wider uppercase shadow-2xs',
                                            r.status === 'open'
                                                ? 'bg-emerald-500 text-white'
                                                : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
                                        ]"
                                    >
                                        {{
                                            r.status === 'open'
                                                ? 'Đang mở'
                                                : 'Đã đóng'
                                        }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <!-- Pagination Bar (10 items per page) -->
                    <div
                        v-if="registers.length > itemsPerPage"
                        class="flex flex-col items-center justify-between gap-3 border-t border-slate-100 bg-slate-50/50 p-4 text-xs sm:flex-row dark:border-slate-800 dark:bg-slate-900/20"
                    >
                        <p class="text-slate-500 dark:text-slate-400">
                            Hiển thị
                            <span
                                class="font-bold text-slate-700 dark:text-slate-200"
                            >
                                {{ (currentPage - 1) * itemsPerPage + 1 }}
                            </span>
                            -
                            <span
                                class="font-bold text-slate-700 dark:text-slate-200"
                            >
                                {{
                                    Math.min(
                                        currentPage * itemsPerPage,
                                        registers.length,
                                    )
                                }}
                            </span>
                            trên tổng số
                            <span
                                class="font-bold text-slate-700 dark:text-slate-200"
                            >
                                {{ registers.length }}
                            </span>
                            dòng (10 dòng / trang)
                        </p>

                        <div class="flex items-center gap-1.5">
                            <button
                                @click="goToPage(currentPage - 1)"
                                :disabled="currentPage === 1"
                                class="inline-flex cursor-pointer items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 shadow-sm transition-all hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
                            >
                                Trước
                            </button>

                            <button
                                v-for="p in totalPages"
                                :key="p"
                                @click="goToPage(p)"
                                :class="[
                                    'inline-flex size-8 cursor-pointer items-center justify-center rounded-lg text-xs font-bold transition-all',
                                    currentPage === p
                                        ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                                        : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800',
                                ]"
                            >
                                {{ p }}
                            </button>

                            <button
                                @click="goToPage(currentPage + 1)"
                                :disabled="currentPage === totalPages"
                                class="inline-flex cursor-pointer items-center justify-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 shadow-sm transition-all hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
                            >
                                Sau
                            </button>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- tab: CASH FLOW CHARTS -->
        <div v-if="activeTab === 'analytics'" class="animate-fade-in space-y-6">
            <Card
                class="border-slate-100 shadow-md shadow-slate-200/50 dark:border-slate-800/80 dark:shadow-none"
            >
                <CardHeader
                    class="dark:border-slate-850 border-b border-slate-100 bg-slate-50/40 pb-3 dark:bg-slate-900/30"
                >
                    <div class="flex items-center justify-between">
                        <div>
                            <CardTitle
                                class="text-sm font-black tracking-wider text-slate-500 uppercase dark:text-slate-400"
                            >
                                Biến động Dòng tiền mặt hàng ngày (Thu vs Chi)
                            </CardTitle>
                            <CardDescription class="text-xs">
                                So sánh và theo dõi biến động dòng tiền nạp vào
                                (inflow) và chi tiền mặt ngoài két (outflow)
                                trong 30 ngày qua.
                            </CardDescription>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="p-6">
                    <!-- Dynamic Interactive CSS/Tailwind Chart -->
                    <div class="space-y-6">
                        <div
                            class="relative flex h-56 items-end justify-between gap-2 overflow-x-auto border-b border-slate-200 px-2 pb-2 dark:border-slate-800/80"
                        >
                            <!-- Background Grid Lines -->
                            <div
                                class="pointer-events-none absolute inset-0 flex flex-col justify-between pb-8 text-[9px] font-bold text-slate-400/50"
                            >
                                <div
                                    class="w-full border-b border-dashed border-slate-100 dark:border-slate-800/40"
                                />
                                <div
                                    class="w-full border-b border-dashed border-slate-100 dark:border-slate-800/40"
                                />
                                <div
                                    class="w-full border-b border-dashed border-slate-100 dark:border-slate-800/40"
                                />
                                <div class="w-full" />
                            </div>

                            <div
                                v-for="d in chartData"
                                :key="d.date"
                                class="group relative flex w-full min-w-[32px] flex-col items-center gap-2"
                            >
                                <!-- Columns Graphic representation -->
                                <div
                                    class="relative flex h-40 w-full items-end justify-center gap-1.5"
                                >
                                    <!-- In bar (Green Gradient) -->
                                    <div
                                        class="w-2.5 rounded-t-full bg-gradient-to-t from-emerald-500/60 to-emerald-500 shadow-sm transition-all duration-300 group-hover:from-emerald-500 group-hover:to-emerald-400 group-hover:shadow-[0_0_8px_rgba(16,185,129,0.3)]"
                                        :style="`height: ${Math.max(4, (d.in / chartMaxVal) * 140)}px`"
                                    />
                                    <!-- Out bar (Red Gradient) -->
                                    <div
                                        class="w-2.5 rounded-t-full bg-gradient-to-t from-rose-500/60 to-rose-500 shadow-sm transition-all duration-300 group-hover:from-rose-500 group-hover:to-rose-400 group-hover:shadow-[0_0_8px_rgba(244,63,94,0.3)]"
                                        :style="`height: ${Math.max(4, (d.out / chartMaxVal) * 140)}px`"
                                    />
                                </div>

                                <!-- Premium glassmorphic tooltip -->
                                <div
                                    class="dark:text-slate-150 absolute bottom-full left-1/2 z-30 mb-2 hidden -translate-x-1/2 animate-in rounded-2xl border border-slate-200/80 bg-white/95 p-3 text-[10px] font-bold text-slate-800 shadow-xl backdrop-blur-md transition-all fade-in slide-in-from-bottom-2 group-hover:block dark:border-slate-800 dark:bg-slate-950/95"
                                >
                                    <p
                                        class="mb-1.5 border-b border-slate-100 pb-1 text-center font-black text-slate-500 dark:border-slate-800/80"
                                    >
                                        Ngày {{ d.date }}
                                    </p>
                                    <div class="space-y-1">
                                        <p
                                            class="flex justify-between gap-4 text-emerald-600 dark:text-emerald-400"
                                        >
                                            <span>Thu:</span>
                                            <span>+{{ vnd(d.in) }}</span>
                                        </p>
                                        <p
                                            class="dark:text-rose-450 flex justify-between gap-4 text-rose-600"
                                        >
                                            <span>Chi:</span>
                                            <span>-{{ vnd(d.out) }}</span>
                                        </p>
                                        <p
                                            class="text-indigo-650 dark:border-slate-850 flex justify-between gap-4 border-t border-slate-100 pt-1 font-black dark:text-indigo-400"
                                        >
                                            <span>Ròng:</span>
                                            <span>{{ vnd(d.net) }}</span>
                                        </p>
                                    </div>
                                </div>

                                <!-- Date labels -->
                                <span
                                    class="shrink-0 scale-90 font-mono text-[9px] font-bold text-slate-400"
                                >
                                    {{ d.date }}
                                </span>
                            </div>
                        </div>

                        <!-- Legend indicators -->
                        <div
                            class="flex justify-center gap-6 pt-2 text-xs font-bold text-slate-500"
                        >
                            <span class="flex items-center gap-2">
                                <span
                                    class="h-3 w-3 rounded-full bg-emerald-500"
                                />
                                Thu tiền mặt (Inflow)
                            </span>
                            <span class="flex items-center gap-2">
                                <span
                                    class="h-3 w-3 rounded-full bg-rose-500"
                                />
                                Chi tiền mặt (Outflow)
                            </span>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- tab: FORECAST & AI -->
        <div v-if="activeTab === 'forecast'" class="animate-fade-in space-y-6">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                <!-- Forecast Metrics -->
                <Card
                    class="border-slate-100 shadow-md shadow-slate-200/50 dark:border-slate-800/80 dark:shadow-none"
                >
                    <CardHeader
                        class="dark:border-slate-850 border-b border-slate-50 bg-slate-50/20 pb-3 dark:bg-slate-900/10"
                    >
                        <CardTitle
                            class="text-[10px] font-black tracking-wider text-slate-400 uppercase"
                        >
                            Dự báo Thu (7 ngày tiếp theo)
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="p-5">
                        <p class="text-xs font-bold text-slate-400">
                            Ước tính thu tiền mặt (7 ngày)
                        </p>
                        <p
                            class="mt-1.5 font-mono text-2xl font-black text-emerald-600 dark:text-emerald-400"
                        >
                            +{{ vnd(forecast.projected_in) }}
                        </p>
                        <p class="mt-2 text-[10px] font-medium text-slate-400">
                            Dựa trên trung bình doanh số thực tế:
                            <strong class="dark:text-slate-350 text-slate-500"
                                >{{ vnd(forecast.avg_daily_in) }}/ngày</strong
                            >.
                        </p>
                    </CardContent>
                </Card>

                <Card
                    class="border-slate-100 shadow-md shadow-slate-200/50 dark:border-slate-800/80 dark:shadow-none"
                >
                    <CardHeader
                        class="dark:border-slate-850 border-b border-slate-50 bg-slate-50/20 pb-3 dark:bg-slate-900/10"
                    >
                        <CardTitle
                            class="text-[10px] font-black tracking-wider text-slate-400 uppercase"
                        >
                            Dự báo Chi (7 ngày tiếp theo)
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="p-5">
                        <p class="text-xs font-bold text-slate-400">
                            Dự chi tiền mặt ngoài két (7 ngày)
                        </p>
                        <p
                            class="dark:text-rose-455 mt-1.5 font-mono text-2xl font-black text-rose-600"
                        >
                            -{{ vnd(forecast.projected_out) }}
                        </p>
                        <p class="mt-2 text-[10px] font-medium text-slate-400">
                            Dựa trên trung bình chi tiêu ngoài ca:
                            <strong class="dark:text-slate-350 text-slate-500"
                                >{{ vnd(forecast.avg_daily_out) }}/ngày</strong
                            >.
                        </p>
                    </CardContent>
                </Card>

                <Card
                    class="border-slate-100 shadow-md shadow-slate-200/50 dark:border-slate-800/80 dark:shadow-none"
                >
                    <CardHeader
                        class="dark:border-slate-850 border-b border-slate-50 bg-slate-50/20 pb-3 dark:bg-slate-900/10"
                    >
                        <CardTitle
                            class="text-[10px] font-black tracking-wider text-slate-400 uppercase"
                        >
                            Số dư dự tính cuối kỳ 7 ngày
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="p-5">
                        <p class="text-xs font-bold text-slate-400">
                            Số dư két ước tính cuối kỳ
                        </p>
                        <p
                            class="mt-1.5 font-mono text-2xl font-black"
                            :class="
                                forecast.projected_balance >= 0
                                    ? 'text-indigo-600 dark:text-indigo-400'
                                    : 'text-rose-600'
                            "
                        >
                            {{ vnd(forecast.projected_balance) }}
                        </p>
                        <p class="mt-2 text-[10px] font-medium text-slate-400">
                            Số dư tiền mặt khả dụng hiện tại:
                            <strong
                                class="dark:text-slate-350 text-slate-500"
                                >{{ vnd(forecast.current_cash) }}</strong
                            >.
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Forecast Warning Callout with premium look -->
            <Card
                class="overflow-hidden border shadow-md shadow-slate-200/40 dark:shadow-none"
                :class="[
                    forecast.status === 'warning'
                        ? 'border-rose-200 bg-rose-50/40 dark:border-rose-950/20 dark:bg-rose-950/20'
                        : forecast.status === 'low_reserve'
                          ? 'border-amber-200 bg-amber-50/40 dark:border-amber-950/20 dark:bg-amber-950/20'
                          : 'border-emerald-100 bg-emerald-50/40 dark:border-emerald-950/10 dark:bg-emerald-950/10',
                ]"
            >
                <CardContent class="flex items-start gap-4 p-6">
                    <div
                        :class="[
                            'flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border-2 shadow-xs',
                            forecast.status === 'warning'
                                ? 'text-rose-550 border-rose-100 bg-rose-50 dark:border-rose-900/30 dark:bg-rose-950/60'
                                : forecast.status === 'low_reserve'
                                  ? 'border-amber-100 bg-amber-50 text-amber-500 dark:border-amber-900/30 dark:bg-amber-950/60'
                                  : 'border-emerald-100 bg-emerald-50 text-emerald-600 dark:border-emerald-900/30 dark:bg-emerald-950/60',
                        ]"
                    >
                        <AlertTriangle
                            v-if="forecast.status !== 'safe'"
                            class="size-5.5"
                        />
                        <CheckCircle v-else class="size-5.5" />
                    </div>

                    <div class="space-y-1">
                        <h3
                            class="text-sm font-black tracking-wider uppercase"
                            :class="[
                                forecast.status === 'warning'
                                    ? 'text-rose-700 dark:text-rose-400'
                                    : forecast.status === 'low_reserve'
                                      ? 'text-amber-700 dark:text-amber-400'
                                      : 'text-emerald-700 dark:text-emerald-400',
                            ]"
                        >
                            {{
                                forecast.status === 'warning'
                                    ? 'Cảnh báo thâm hụt dòng tiền mặt!'
                                    : forecast.status === 'low_reserve'
                                      ? 'Lưu ý: Quỹ dự trữ tiền mặt thấp'
                                      : 'Độ an toàn dòng tiền: An toàn cao'
                            }}
                        </h3>
                        <p
                            class="text-xs leading-relaxed font-semibold text-slate-500 dark:text-slate-400"
                        >
                            {{ forecast.message }}
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- MODAL: Open Register -->
        <Teleport to="body">
        <div
            v-if="showOpenModal"
            class="fixed inset-0 z-50 flex animate-in items-center justify-center bg-slate-900/60 p-4 backdrop-blur-md duration-200 fade-in"
        >
            <Card
                class="w-full max-w-md animate-in border-slate-100 shadow-2xl duration-200 zoom-in-95 dark:border-slate-800/80"
            >
                <CardHeader
                    class="border-b border-slate-100 pb-3 dark:border-slate-800"
                >
                    <CardTitle
                        class="dark:text-slate-350 flex items-center gap-2 text-sm font-black text-slate-600 uppercase"
                    >
                        <Wallet
                            class="size-5 text-indigo-600 dark:text-indigo-400"
                        />
                        Mở Két Tiền Mặt Đầu Ca
                    </CardTitle>
                    <CardDescription class="text-xs">
                        Khai báo số dư két tiền ban đầu để cashier thực hiện
                        thanh toán & đối soát.
                    </CardDescription>
                </CardHeader>
                <form @submit.prevent="handleOpenRegister">
                    <CardContent class="space-y-4.5 p-5">
                        <!-- Shift select -->
                        <div class="space-y-1.5">
                            <Label
                                for="open-shift"
                                class="text-xs font-bold tracking-wider text-slate-400 uppercase"
                            >
                                Chọn ca làm việc:
                            </Label>
                            <select
                                id="open-shift"
                                v-model="openForm.shift_id"
                                class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-3 text-xs font-bold text-slate-700 outline-hidden transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/10 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200"
                            >
                                <option value="" disabled>
                                    -- Chọn ca trực --
                                </option>
                                <option
                                    v-for="s in shifts"
                                    :key="s.id"
                                    :value="s.id"
                                >
                                    {{ s.name }} ({{ s.code }})
                                </option>
                            </select>
                        </div>

                        <!-- Opening Balance -->
                        <div class="space-y-1.5">
                            <Label
                                for="open-balance"
                                class="text-xs font-bold tracking-wider text-slate-400 uppercase"
                            >
                                Số dư tiền mặt mở ca (VND):
                            </Label>
                            <Input
                                id="open-balance"
                                v-model.number="openForm.opening_balance"
                                type="number"
                                placeholder="Ví dụ: 2000000"
                                class="h-11 w-full rounded-xl text-xs font-bold focus:ring-2 focus:ring-indigo-500/10"
                            />
                        </div>

                        <!-- Expense Budget -->
                        <div class="space-y-1.5">
                            <Label
                                for="open-budget"
                                class="text-xs font-bold tracking-wider text-slate-400 uppercase"
                            >
                                Hạn mức chi ngoài két (VND - Tùy chọn):
                            </Label>
                            <Input
                                id="open-budget"
                                v-model.number="openForm.expense_budget"
                                type="number"
                                placeholder="Ví dụ: 1000000"
                                class="h-11 w-full rounded-xl text-xs font-bold focus:ring-2 focus:ring-indigo-500/10"
                            />
                            <p
                                class="text-[10px] font-medium text-slate-400/90"
                            >
                                Cảnh báo thâm hụt khi chi tiền mua gas, đá viên
                                đi chợ gấp trong ca.
                            </p>
                        </div>

                        <!-- Notes -->
                        <div class="space-y-1.5">
                            <Label
                                for="open-notes"
                                class="text-xs font-bold tracking-wider text-slate-400 uppercase"
                            >
                                Ghi chú mở đầu ca:
                            </Label>
                            <Input
                                id="open-notes"
                                v-model="openForm.notes"
                                type="text"
                                placeholder="Ghi chú số dư bàn giao (nếu có)..."
                                class="h-11 w-full rounded-xl text-xs font-bold focus:ring-2 focus:ring-indigo-500/10"
                            />
                        </div>
                    </CardContent>
                    <div
                        class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50/50 p-4 dark:border-slate-800 dark:bg-slate-900/10"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            @click="showOpenModal = false"
                            class="h-10 cursor-pointer rounded-xl text-xs font-bold"
                        >
                            Hủy
                        </Button>
                        <Button
                            type="submit"
                            class="flex h-10 cursor-pointer items-center gap-1.5 rounded-xl bg-indigo-600 px-5 text-xs font-bold text-white hover:bg-indigo-700 disabled:opacity-50"
                            :disabled="openForm.processing"
                        >
                            <Loader2
                                v-if="openForm.processing"
                                class="size-4 animate-spin"
                            />
                            Xác nhận mở két
                        </Button>
                    </div>
                </form>
            </Card>
        </div>
        </Teleport>

        <!-- MODAL: Add Cash Transaction (In/Out) -->
        <Teleport to="body">
        <div
            v-if="showTransactionModal"
            class="fixed inset-0 z-50 flex animate-in items-center justify-center bg-slate-900/60 p-4 backdrop-blur-md duration-200 fade-in"
        >
            <Card
                class="w-full max-w-md animate-in border-slate-100 shadow-2xl duration-200 zoom-in-95 dark:border-slate-800/80"
            >
                <CardHeader
                    class="border-b border-slate-100 pb-3 dark:border-slate-800"
                >
                    <CardTitle
                        class="dark:text-slate-350 flex items-center gap-2 text-sm font-black text-slate-600 uppercase"
                    >
                        <component
                            :is="
                                transactionModalType === 'in'
                                    ? PlusCircle
                                    : MinusCircle
                            "
                            :class="
                                transactionModalType === 'in'
                                    ? 'text-emerald-500'
                                    : 'text-rose-500'
                            "
                            class="size-5"
                        />
                        {{
                            transactionModalType === 'in'
                                ? 'Ghi Nhận Khoản Thu Tiền Mặt'
                                : 'Ghi Nhận Khoản Chi Ngoài Két'
                        }}
                    </CardTitle>
                    <CardDescription class="text-xs">
                        {{
                            transactionModalType === 'in'
                                ? 'Ghi nhận nguồn nạp tiền mặt khác ngoài doanh thu bàn ăn.'
                                : 'Ghi nhận chi phí mua sắm lặt vặt trực tiếp bằng tiền mặt két.'
                        }}
                    </CardDescription>
                </CardHeader>
                <form @submit.prevent="handleAddTransaction">
                    <CardContent class="space-y-4.5 p-5">
                        <!-- Amount -->
                        <div class="space-y-1.5">
                            <Label
                                for="tx-amount"
                                class="text-xs font-bold tracking-wider text-slate-400 uppercase"
                            >
                                Số tiền giao dịch (VND):
                            </Label>
                            <Input
                                id="tx-amount"
                                v-model.number="txForm.amount"
                                type="number"
                                placeholder="Nhập số tiền..."
                                class="h-11 w-full rounded-xl text-xs font-bold focus:ring-2 focus:ring-indigo-500/10"
                            />
                        </div>

                        <!-- Notes -->
                        <div class="space-y-1.5">
                            <Label
                                for="tx-notes"
                                class="text-xs font-bold tracking-wider text-slate-400 uppercase"
                            >
                                Nội dung/Lý do chi tiết:
                            </Label>
                            <textarea
                                id="tx-notes"
                                v-model="txForm.notes"
                                rows="3"
                                placeholder="Ví dụ: Chi tiền mua rau thơm đi chợ, Khách trả nợ tiền mặt..."
                                class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-3 text-xs font-bold text-slate-700 outline-hidden transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/10 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200"
                            ></textarea>
                        </div>
                    </CardContent>
                    <div
                        class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50/50 p-4 dark:border-slate-800 dark:bg-slate-900/10"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            @click="showTransactionModal = false"
                            class="h-10 cursor-pointer rounded-xl text-xs font-bold"
                        >
                            Hủy
                        </Button>
                        <Button
                            type="submit"
                            :class="[
                                'flex h-10 cursor-pointer items-center gap-1.5 rounded-xl px-5 text-xs font-bold text-white disabled:opacity-50',
                                transactionModalType === 'in'
                                    ? 'bg-emerald-600 hover:bg-emerald-700'
                                    : 'bg-rose-600 hover:bg-rose-700',
                            ]"
                            :disabled="txForm.processing"
                        >
                            <Loader2
                                v-if="txForm.processing"
                                class="size-4 animate-spin"
                            />
                            Ghi nhận giao dịch
                        </Button>
                    </div>
                </form>
            </Card>
        </div>
        </Teleport>
    </div>
</template>
