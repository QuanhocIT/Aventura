<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Wallet,
    ArrowUpRight,
    ArrowDownRight,
    PlusCircle,
    MinusCircle,
    Calendar,
    User,
    AlertTriangle,
    Clock,
    TrendingUp,
    ShieldAlert,
    CheckCircle,
    History,
    BarChart3,
    ArrowRightLeft,
    Loader2,
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
    id: number;
    opening_balance: number;
    expense_budget: number;
    opened_at: string;
    opened_by_name: string;
    shift_name: string;
    closing_date: string;
    expected_cash: number;
};

type Transaction = {
    id: number;
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

const vnd = (v: number) =>
    new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(v);

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

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-6">
        <!-- Page Header -->
        <div
            class="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-2xl border border-indigo-100 bg-indigo-50 text-indigo-600 shadow-sm dark:border-indigo-900/30 dark:bg-indigo-950/60 dark:text-indigo-400"
                >
                    <Wallet class="size-6" />
                </div>
                <div>
                    <h1
                        class="text-2xl font-bold tracking-tight text-slate-800 dark:text-slate-100"
                    >
                        Quỹ Tiền Mặt & Dòng Tiền
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Quản lý két tiền hàng ngày, theo dõi thu chi thực tế tại
                        quầy, dự báo và cảnh báo ngân sách.
                    </p>
                </div>
            </div>

            <!-- Open drawer button (only if no active drawer) -->
            <div v-if="!activeRegister" class="flex items-center gap-2">
                <Button
                    @click="showOpenModal = true"
                    class="h-9 bg-indigo-600 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700"
                >
                    <PlusCircle class="mr-1.5 size-4" />
                    Mở két đầu ca
                </Button>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="flex shrink-0 gap-2 overflow-x-auto border-b pb-1">
            <button
                @click="activeTab = 'active'"
                :class="[
                    'border-b-2 px-4 py-2 text-xs font-bold whitespace-nowrap transition-all',
                    activeTab === 'active'
                        ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400'
                        : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200',
                ]"
            >
                Két tiền ca hiện tại
            </button>
            <button
                @click="activeTab = 'history'"
                :class="[
                    'border-b-2 px-4 py-2 text-xs font-bold whitespace-nowrap transition-all',
                    activeTab === 'history'
                        ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400'
                        : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200',
                ]"
            >
                <span class="flex items-center gap-1.5"
                    ><History class="size-3.5" /> Lịch sử ca chốt két</span
                >
            </button>
            <button
                @click="activeTab = 'analytics'"
                :class="[
                    'border-b-2 px-4 py-2 text-xs font-bold whitespace-nowrap transition-all',
                    activeTab === 'analytics'
                        ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400'
                        : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200',
                ]"
            >
                <span class="flex items-center gap-1.5"
                    ><BarChart3 class="size-3.5" /> Biểu đồ dòng tiền (30
                    ngày)</span
                >
            </button>
            <button
                @click="activeTab = 'forecast'"
                :class="[
                    'border-b-2 px-4 py-2 text-xs font-bold whitespace-nowrap transition-all',
                    activeTab === 'forecast'
                        ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400'
                        : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200',
                ]"
            >
                <span class="flex items-center gap-1.5"
                    ><TrendingUp class="size-3.5" /> Dự báo thanh toán</span
                >
            </button>
        </div>

        <!-- tab: ACTIVE DRAWER -->
        <div v-if="activeTab === 'active'" class="space-y-6">
            <!-- Active register details -->
            <div
                v-if="activeRegister"
                class="grid grid-cols-1 gap-6 lg:grid-cols-3"
            >
                <!-- Left panel: drawer stats card -->
                <div class="space-y-6 lg:col-span-1">
                    <Card
                        class="overflow-hidden border-indigo-100 shadow-sm dark:border-indigo-950/30"
                    >
                        <CardHeader
                            class="border-b bg-indigo-50/20 pb-3 dark:bg-indigo-950/10"
                        >
                            <CardTitle
                                class="flex items-center gap-1.5 text-sm font-bold"
                            >
                                <Wallet
                                    class="size-4 text-indigo-600 dark:text-indigo-400"
                                />
                                Thông tin két hiện tại
                            </CardTitle>
                            <CardDescription class="text-[11px]"
                                >Két đang mở & sẵn sàng giao
                                dịch</CardDescription
                            >
                        </CardHeader>
                        <CardContent class="space-y-4 p-5 text-xs">
                            <div
                                class="flex items-center justify-between border-b py-2 dark:border-slate-800"
                            >
                                <span
                                    class="flex items-center gap-1 font-bold text-slate-400"
                                    ><Clock class="size-3.5" /> Ca trực</span
                                >
                                <span
                                    class="font-bold text-slate-700 dark:text-slate-200"
                                    >{{ activeRegister.shift_name }}</span
                                >
                            </div>
                            <div
                                class="flex items-center justify-between border-b py-2 dark:border-slate-800"
                            >
                                <span
                                    class="flex items-center gap-1 font-bold text-slate-400"
                                    ><User class="size-3.5" /> Cashier mở
                                    két</span
                                >
                                <span
                                    class="font-bold text-slate-700 dark:text-slate-200"
                                    >{{ activeRegister.opened_by_name }}</span
                                >
                            </div>
                            <div
                                class="flex items-center justify-between border-b py-2 dark:border-slate-800"
                            >
                                <span
                                    class="flex items-center gap-1 font-bold text-slate-400"
                                    ><Calendar class="size-3.5" /> Ngày
                                    chốt</span
                                >
                                <span
                                    class="font-bold text-slate-700 dark:text-slate-200"
                                    >{{
                                        new Date(
                                            activeRegister.closing_date,
                                        ).toLocaleDateString('vi-VN')
                                    }}</span
                                >
                            </div>
                            <div
                                class="flex items-center justify-between border-b py-2 dark:border-slate-800"
                            >
                                <span class="font-bold text-slate-400"
                                    >Mở lúc</span
                                >
                                <span
                                    class="font-mono text-slate-600 dark:text-slate-300"
                                    >{{ activeRegister.opened_at }}</span
                                >
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Budget & Alert Card -->
                    <Card
                        class="overflow-hidden shadow-sm"
                        :class="
                            isBudgetExceeded
                                ? 'border-rose-200 dark:border-rose-950/40'
                                : 'border-slate-100 dark:border-slate-800'
                        "
                    >
                        <CardHeader
                            class="border-b pb-3"
                            :class="
                                isBudgetExceeded
                                    ? 'bg-rose-50/20 dark:bg-rose-950/10'
                                    : 'bg-slate-50/20'
                            "
                        >
                            <CardTitle
                                class="flex items-center gap-1.5 text-xs font-bold tracking-wider uppercase"
                                :class="
                                    isBudgetExceeded
                                        ? 'text-rose-600 dark:text-rose-400'
                                        : 'text-slate-500'
                                "
                            >
                                <AlertTriangle class="size-4" />
                                Ngân sách chi tiêu ca
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-4 p-5">
                            <div
                                class="flex items-center justify-between text-xs"
                            >
                                <span class="font-bold text-slate-400"
                                    >Hạn mức chi tiêu:</span
                                >
                                <span
                                    class="dark:text-slate-250 font-mono font-bold text-slate-700"
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
                                <span class="font-bold text-slate-400"
                                    >Đã chi ngoài:</span
                                >
                                <span
                                    class="dark:text-slate-250 font-mono font-bold text-slate-700"
                                >
                                    {{ vnd(activeExpensesTotal) }}
                                </span>
                            </div>

                            <div
                                v-if="isBudgetExceeded"
                                class="flex gap-2 rounded-xl border border-rose-100 bg-rose-50 p-3 dark:border-rose-900/30 dark:bg-rose-950/30"
                            >
                                <ShieldAlert
                                    class="mt-0.5 size-5 shrink-0 text-rose-500"
                                />
                                <div>
                                    <p
                                        class="text-xs font-bold text-rose-700 dark:text-rose-400"
                                    >
                                        Vượt ngân sách ca!
                                    </p>
                                    <p
                                        class="mt-0.5 text-[10px] text-rose-500 dark:text-rose-500"
                                    >
                                        Tổng chi tiêu ngoài hệ thống đã vượt quá
                                        hạn mức tối đa
                                        {{ vnd(activeRegister.expense_budget) }}
                                        được cấp đầu ca.
                                    </p>
                                </div>
                            </div>
                            <div
                                v-else-if="activeRegister.expense_budget > 0"
                                class="flex gap-2 rounded-xl border border-emerald-100 bg-emerald-50 p-3 dark:border-emerald-900/20 dark:bg-emerald-950/20"
                            >
                                <CheckCircle
                                    class="mt-0.5 size-5 shrink-0 text-emerald-500"
                                />
                                <div>
                                    <p
                                        class="text-xs font-bold text-emerald-700 dark:text-emerald-400"
                                    >
                                        Trong tầm kiểm soát
                                    </p>
                                    <p
                                        class="mt-0.5 text-[10px] text-emerald-500 dark:text-emerald-500"
                                    >
                                        Chi ngoài còn lại:
                                        {{
                                            vnd(
                                                activeRegister.expense_budget -
                                                    activeExpensesTotal,
                                            )
                                        }}
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
                        class="border-slate-100 shadow-sm dark:border-slate-800"
                    >
                        <CardContent class="p-6">
                            <div
                                class="flex flex-col gap-6 border-b pb-6 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800"
                            >
                                <div>
                                    <p
                                        class="text-xs font-bold tracking-wider text-slate-400 uppercase"
                                    >
                                        Tiền mặt thực tế kỳ vọng trong két
                                    </p>
                                    <p
                                        class="mt-1.5 font-mono text-3xl font-black text-indigo-600 dark:text-indigo-400"
                                    >
                                        {{ vnd(activeRegister.expected_cash) }}
                                    </p>
                                    <p class="mt-1 text-[10px] text-slate-400">
                                        Gồm: Mở két đầu ca ({{
                                            vnd(activeRegister.opening_balance)
                                        }}) + Thu tại quầy - Chi ngoài ca
                                    </p>
                                </div>

                                <div class="flex shrink-0 flex-wrap gap-2">
                                    <Button
                                        @click="openTxModal('out')"
                                        variant="outline"
                                        class="h-9 border-rose-100 text-xs font-bold text-rose-600 transition-transform hover:bg-rose-50 active:scale-95"
                                    >
                                        <MinusCircle class="mr-1.5 size-4" />
                                        Chi tiền ngoài két
                                    </Button>
                                    <Button
                                        @click="openTxModal('in')"
                                        variant="outline"
                                        class="hover:bg-emerald-55 h-9 border-emerald-100 text-xs font-bold text-emerald-600 transition-transform active:scale-95"
                                    >
                                        <PlusCircle class="mr-1.5 size-4" />
                                        Thu tiền mặt khác
                                    </Button>
                                </div>
                            </div>

                            <!-- Live transactions log list -->
                            <div class="pt-6">
                                <h3
                                    class="mb-4 flex items-center gap-1.5 text-xs font-bold tracking-wider text-slate-500 uppercase"
                                >
                                    <ArrowRightLeft
                                        class="size-4 text-slate-400"
                                    />
                                    Lịch sử giao dịch dòng tiền mặt trong ca
                                </h3>

                                <div
                                    v-if="activeTransactions.length === 0"
                                    class="py-10 text-center text-xs text-slate-400"
                                >
                                    Chưa phát sinh giao dịch tiền mặt nào trong
                                    ca này.
                                </div>

                                <div
                                    v-else
                                    class="max-h-[300px] divide-y divide-slate-100 overflow-y-auto pr-1 dark:divide-slate-800"
                                >
                                    <div
                                        v-for="tx in activeTransactions"
                                        :key="tx.id"
                                        class="flex items-center justify-between py-3 text-xs"
                                    >
                                        <div class="flex items-center gap-3">
                                            <div
                                                :class="[
                                                    'flex h-8 w-8 items-center justify-center rounded-full border',
                                                    tx.type === 'in'
                                                        ? 'dark:text-emerald-450 border-emerald-100 bg-emerald-50 text-emerald-600 dark:border-emerald-900/30 dark:bg-emerald-950/20'
                                                        : 'dark:text-rose-450 border-rose-100 bg-rose-50 text-rose-600 dark:border-rose-900/30 dark:bg-rose-950/20',
                                                ]"
                                            >
                                                <component
                                                    :is="
                                                        tx.type === 'in'
                                                            ? ArrowUpRight
                                                            : ArrowDownRight
                                                    "
                                                    class="size-4"
                                                />
                                            </div>
                                            <div>
                                                <p
                                                    class="font-bold text-slate-700 dark:text-slate-200"
                                                >
                                                    {{ tx.notes }}
                                                </p>
                                                <p
                                                    class="mt-0.5 text-[10px] text-slate-400"
                                                >
                                                    {{ tx.created_by_name }} ·
                                                    {{ tx.occurred_at }}
                                                </p>
                                            </div>
                                        </div>

                                        <span
                                            :class="[
                                                'font-mono text-sm font-black',
                                                tx.type === 'in'
                                                    ? 'dark:text-emerald-450 text-emerald-600'
                                                    : 'dark:text-rose-455 text-rose-600',
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
                class="flex flex-col items-center gap-4 rounded-2xl border-2 border-dashed bg-white py-24 text-center dark:border-slate-800 dark:bg-slate-950/30"
            >
                <div
                    class="flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 text-slate-400 dark:bg-slate-900"
                >
                    <Wallet class="size-8" />
                </div>
                <div>
                    <h2
                        class="text-base font-bold text-slate-800 dark:text-slate-200"
                    >
                        Chưa mở két tiền mặt ca làm việc
                    </h2>
                    <p class="mx-auto mt-1 max-w-sm text-xs text-slate-400">
                        Để bắt đầu bán hàng bằng tiền mặt hoặc ghi nhận chi tiêu
                        ngoài hệ thống trong ca trực, vui lòng mở két tiền mặt.
                    </p>
                </div>
                <Button
                    @click="showOpenModal = true"
                    class="h-9 bg-indigo-600 px-6 text-xs font-semibold text-white shadow-sm transition-transform hover:bg-indigo-700 active:scale-95"
                >
                    <PlusCircle class="mr-1.5 size-4" />
                    Mở két đầu ca ngay
                </Button>
            </div>
        </div>

        <!-- tab: REGISTERS HISTORY -->
        <div v-if="activeTab === 'history'">
            <Card class="overflow-hidden shadow-sm">
                <CardHeader
                    class="border-b bg-slate-50/50 pb-3 dark:bg-slate-900/10"
                >
                    <CardTitle class="text-sm font-bold"
                        >Lịch sử ca chốt két tiền mặt</CardTitle
                    >
                    <CardDescription class="text-xs"
                        >Danh sách các phiên đóng/mở két tiền mặt, đối soát và
                        chênh lệch két.</CardDescription
                    >
                </CardHeader>
                <CardContent class="overflow-x-auto p-0">
                    <table class="w-full border-collapse text-left text-xs">
                        <thead>
                            <tr
                                class="border-b bg-slate-50/20 font-bold text-slate-500 dark:bg-slate-900/5"
                            >
                                <th class="p-3 pl-5">Ngày chốt</th>
                                <th class="p-3">Ca</th>
                                <th class="p-3">Người mở / đóng</th>
                                <th class="p-3 text-right">Số dư đầu</th>
                                <th class="p-3 text-right">Hệ thống kì vọng</th>
                                <th class="p-3 text-right">Thực tế đóng</th>
                                <th class="p-3 text-right">Chênh lệch</th>
                                <th class="p-3 text-center">Trạng thái</th>
                            </tr>
                        </thead>
                        <tbody
                            class="dark:text-slate-350 divide-y divide-slate-100 text-slate-600 dark:divide-slate-800"
                        >
                            <tr v-if="registers.length === 0">
                                <td
                                    colspan="8"
                                    class="p-10 text-center text-slate-400"
                                >
                                    Chưa có lịch sử phiên két tiền mặt nào.
                                </td>
                            </tr>
                            <tr
                                v-for="r in registers"
                                :key="r.id"
                                class="transition-colors hover:bg-slate-50/40 dark:hover:bg-slate-900/30"
                            >
                                <td class="p-3 pl-5 font-bold">
                                    {{ r.closing_date }}
                                </td>
                                <td class="p-3">{{ r.shift_name }}</td>
                                <td class="p-3">
                                    <p class="font-semibold">
                                        {{ r.opened_by_name }}
                                    </p>
                                    <p
                                        class="mt-0.5 text-[10px] text-slate-400"
                                    >
                                        Đóng: {{ r.closed_by_name }}
                                    </p>
                                </td>
                                <td
                                    class="p-3 text-right font-mono font-semibold"
                                >
                                    {{ vnd(r.opening_balance) }}
                                </td>
                                <td
                                    class="p-3 text-right font-mono font-semibold"
                                >
                                    {{
                                        r.status === 'closed'
                                            ? vnd(r.expected_closing_balance)
                                            : '—'
                                    }}
                                </td>
                                <td
                                    class="p-3 text-right font-mono font-bold text-slate-700 dark:text-slate-200"
                                >
                                    {{
                                        r.status === 'closed'
                                            ? vnd(r.closing_balance)
                                            : '—'
                                    }}
                                </td>
                                <td class="p-3 text-right font-mono font-black">
                                    <span
                                        v-if="r.status === 'closed'"
                                        :class="
                                            r.difference > 0
                                                ? 'text-emerald-600'
                                                : r.difference < 0
                                                  ? 'text-rose-600'
                                                  : 'text-slate-400'
                                        "
                                    >
                                        {{ r.difference > 0 ? '+' : ''
                                        }}{{ vnd(r.difference) }}
                                    </span>
                                    <span v-else>—</span>
                                </td>
                                <td class="p-3 text-center">
                                    <span
                                        :class="[
                                            'rounded-full px-2 py-0.5 text-[9px] font-bold tracking-wider uppercase',
                                            r.status === 'open'
                                                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400'
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
                </CardContent>
            </Card>
        </div>

        <!-- tab: CASH FLOW CHARTS -->
        <div v-if="activeTab === 'analytics'" class="space-y-6">
            <Card class="border-slate-100 shadow-sm dark:border-slate-800">
                <CardHeader
                    class="border-b bg-slate-50/50 pb-3 dark:bg-slate-900/10"
                >
                    <CardTitle class="text-sm font-bold"
                        >Biến động Dòng tiền mặt hàng ngày (Thu vs
                        Chi)</CardTitle
                    >
                    <CardDescription class="text-xs"
                        >Theo dõi và so sánh lượng tiền mặt nạp vào (Bán hàng)
                        và chi ra (Phát sinh) 30 ngày qua.</CardDescription
                    >
                </CardHeader>
                <CardContent class="p-6">
                    <!-- Pure Tailwind Graph -->
                    <div class="space-y-4">
                        <div
                            class="flex h-48 items-end justify-between gap-4 overflow-x-auto border-b border-slate-200 px-2 pb-2 dark:border-slate-800"
                        >
                            <div
                                v-for="d in chartData"
                                :key="d.date"
                                class="group relative flex w-full min-w-[30px] flex-col items-center gap-1"
                            >
                                <!-- Bars wrapper -->
                                <div
                                    class="flex h-36 w-full items-end justify-center gap-1"
                                >
                                    <!-- In bar (Green) -->
                                    <div
                                        class="w-2.5 rounded-t-sm bg-emerald-500/80 transition-all duration-300 hover:bg-emerald-500"
                                        :style="`height: ${Math.max(3, (d.in / chartMaxVal) * 120)}px`"
                                    />
                                    <!-- Out bar (Red) -->
                                    <div
                                        class="w-2.5 rounded-t-sm bg-rose-500/80 transition-all duration-300 hover:bg-rose-500"
                                        :style="`height: ${Math.max(3, (d.out / chartMaxVal) * 120)}px`"
                                    />
                                </div>

                                <!-- Tooltip popup -->
                                <div
                                    class="absolute bottom-full left-1/2 z-20 hidden -translate-x-1/2 rounded-lg border border-slate-800 bg-slate-900 p-2.5 text-[9px] font-bold whitespace-nowrap text-white shadow-lg group-hover:block"
                                >
                                    <p
                                        class="mb-1 border-b border-slate-700 pb-1 text-center"
                                    >
                                        Ngày {{ d.date }}
                                    </p>
                                    <p class="text-emerald-400">
                                        Tổng thu: +{{ vnd(d.in) }}
                                    </p>
                                    <p class="text-rose-400">
                                        Tổng chi: -{{ vnd(d.out) }}
                                    </p>
                                    <p
                                        class="mt-0.5 border-t border-slate-700 pt-1 text-indigo-400"
                                    >
                                        Dòng tiền ròng: {{ vnd(d.net) }}
                                    </p>
                                </div>

                                <span
                                    class="shrink-0 scale-90 font-mono text-[9px] text-slate-400"
                                    >{{ d.date }}</span
                                >
                            </div>
                        </div>

                        <!-- Legend indicators -->
                        <div
                            class="flex justify-center gap-6 pt-4 text-xs font-semibold text-slate-500"
                        >
                            <span class="flex items-center gap-2">
                                <span
                                    class="size-3 rounded-xs bg-emerald-500"
                                />
                                Dòng tiền Thu (Sales & Khác)
                            </span>
                            <span class="flex items-center gap-2">
                                <span class="size-3 rounded-xs bg-rose-500" />
                                Dòng tiền Chi (Expenses & Khác)
                            </span>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- tab: FORECASTING -->
        <div v-if="activeTab === 'forecast'" class="space-y-6">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                <!-- Forecast Metrics -->
                <Card class="border-slate-100 shadow-sm dark:border-slate-800">
                    <CardHeader class="border-b bg-slate-50/20 pb-3">
                        <CardTitle
                            class="text-xs font-bold tracking-wider text-slate-400 uppercase"
                            >Doanh số kì vọng 7 ngày tới</CardTitle
                        >
                    </CardHeader>
                    <CardContent class="p-5">
                        <p class="text-sm font-bold text-slate-400">
                            Thu tiền mặt ước tính (7 ngày)
                        </p>
                        <p
                            class="mt-1 font-mono text-2xl font-black text-emerald-600 dark:text-emerald-400"
                        >
                            +{{ vnd(forecast.projected_in) }}
                        </p>
                        <p class="mt-2 text-[10px] text-slate-400">
                            Dựa trên trung bình thu nhập tiền mặt thực tế
                            {{ vnd(forecast.avg_daily_in) }}/ngày.
                        </p>
                    </CardContent>
                </Card>

                <Card class="border-slate-100 shadow-sm dark:border-slate-800">
                    <CardHeader class="border-b bg-slate-50/20 pb-3">
                        <CardTitle
                            class="text-xs font-bold tracking-wider text-slate-400 uppercase"
                            >Chi tiêu dự kiến 7 ngày tới</CardTitle
                        >
                    </CardHeader>
                    <CardContent class="p-5">
                        <p class="text-sm font-bold text-slate-400">
                            Chi tiền mặt dự tính (7 ngày)
                        </p>
                        <p
                            class="mt-1 font-mono text-2xl font-black text-rose-600 dark:text-rose-400"
                        >
                            -{{ vnd(forecast.projected_out) }}
                        </p>
                        <p class="mt-2 text-[10px] text-slate-400">
                            Dựa trên trung bình chi tiêu ngoài hệ thống
                            {{ vnd(forecast.avg_daily_out) }}/ngày.
                        </p>
                    </CardContent>
                </Card>

                <Card class="border-slate-100 shadow-sm dark:border-slate-800">
                    <CardHeader class="border-b bg-slate-50/20 pb-3">
                        <CardTitle
                            class="text-xs font-bold tracking-wider text-slate-400 uppercase"
                            >Số dư dự tính cuối kỳ</CardTitle
                        >
                    </CardHeader>
                    <CardContent class="p-5">
                        <p class="text-sm font-bold text-slate-400">
                            Số dư két ước tính (7 ngày)
                        </p>
                        <p
                            class="mt-1 font-mono text-2xl font-black"
                            :class="
                                forecast.projected_balance >= 0
                                    ? 'text-indigo-600 dark:text-indigo-400'
                                    : 'text-rose-650'
                            "
                        >
                            {{ vnd(forecast.projected_balance) }}
                        </p>
                        <p class="mt-2 text-[10px] text-slate-400">
                            Tính toán từ số dư hiện tại trong két:
                            {{ vnd(forecast.current_cash) }}
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Forecast Warning Callout -->
            <Card
                class="overflow-hidden border shadow-sm"
                :class="
                    forecast.status === 'warning'
                        ? 'border-rose-200 bg-rose-50/10 dark:border-rose-950/40'
                        : forecast.status === 'low_reserve'
                          ? 'border-amber-200 bg-amber-50/10 dark:border-amber-950/40'
                          : 'border-emerald-100 bg-emerald-50/10 dark:border-emerald-950/20'
                "
            >
                <CardContent class="flex items-start gap-4 p-6">
                    <div
                        :class="[
                            'flex h-10 w-10 shrink-0 items-center justify-center rounded-full border',
                            forecast.status === 'warning'
                                ? 'border-rose-100 bg-rose-50 text-rose-500'
                                : forecast.status === 'low_reserve'
                                  ? 'border-amber-100 bg-amber-50 text-amber-500'
                                  : 'border-emerald-100 bg-emerald-50 text-emerald-500',
                        ]"
                    >
                        <AlertTriangle
                            v-if="forecast.status !== 'safe'"
                            class="size-5"
                        />
                        <CheckCircle v-else class="size-5" />
                    </div>

                    <div>
                        <h3
                            class="text-sm font-bold"
                            :class="
                                forecast.status === 'warning'
                                    ? 'text-rose-700 dark:text-rose-400'
                                    : forecast.status === 'low_reserve'
                                      ? 'dark:text-amber-450 text-amber-700'
                                      : 'text-emerald-700 dark:text-emerald-400'
                            "
                        >
                            {{
                                forecast.status === 'warning'
                                    ? 'Cảnh báo thâm hụt dòng tiền mặt!'
                                    : forecast.status === 'low_reserve'
                                      ? 'Lưu ý quỹ tiền mặt thấp'
                                      : 'Dòng tiền mặt an toàn'
                            }}
                        </h3>
                        <p
                            class="mt-1.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400"
                        >
                            {{ forecast.message }}
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- MODAL: Open Register -->
        <div
            v-if="showOpenModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs"
        >
            <Card
                class="w-full max-w-md animate-in shadow-2xl duration-200 fade-in zoom-in"
            >
                <CardHeader class="border-b pb-3">
                    <CardTitle
                        class="flex items-center gap-1.5 text-sm font-bold"
                    >
                        <Wallet
                            class="size-5 text-indigo-600 dark:text-indigo-400"
                        />
                        Mở Két Tiền Mặt Đầu Ca
                    </CardTitle>
                    <CardDescription class="text-xs"
                        >Thiết lập số dư két tiền ban đầu để cashier thực hiện
                        giao dịch.</CardDescription
                    >
                </CardHeader>
                <form @submit.prevent="handleOpenRegister">
                    <CardContent class="space-y-4 p-5">
                        <!-- Shift select -->
                        <div class="space-y-1.5">
                            <Label
                                for="open-shift"
                                class="text-xs font-bold text-slate-500"
                                >Ca làm việc chốt ca:</Label
                            >
                            <select
                                id="open-shift"
                                v-model="openForm.shift_id"
                                class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-semibold outline-none focus:ring-2 focus:ring-indigo-500/25 dark:border-slate-800 dark:bg-slate-950"
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
                                class="text-xs font-bold text-slate-500"
                                >Số dư tiền mặt ban đầu (VND):</Label
                            >
                            <Input
                                id="open-balance"
                                v-model.number="openForm.opening_balance"
                                type="number"
                                placeholder="Nhập số tiền..."
                                class="w-full"
                            />
                        </div>

                        <!-- Expense Budget -->
                        <div class="space-y-1.5">
                            <Label
                                for="open-budget"
                                class="text-xs font-bold text-slate-500"
                                >Hạn mức chi tiêu ngoài két (VND - Tùy
                                chọn):</Label
                            >
                            <Input
                                id="open-budget"
                                v-model.number="openForm.expense_budget"
                                type="number"
                                placeholder="Nhập ngân sách chi..."
                                class="w-full"
                            />
                            <p class="text-[10px] text-slate-400">
                                Đặt hạn mức cảnh báo khi chi tiền đi chợ, sửa
                                chữa vượt mức.
                            </p>
                        </div>

                        <!-- Notes -->
                        <div class="space-y-1.5">
                            <Label
                                for="open-notes"
                                class="text-xs font-bold text-slate-500"
                                >Ghi chú mở két:</Label
                            >
                            <Input
                                id="open-notes"
                                v-model="openForm.notes"
                                type="text"
                                placeholder="Nhập ghi chú đầu ca..."
                                class="w-full"
                            />
                        </div>
                    </CardContent>
                    <div
                        class="flex justify-end gap-2 border-t bg-slate-50/50 p-4 dark:bg-slate-900/10"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            @click="showOpenModal = false"
                            class="h-9 text-xs font-semibold"
                            >Hủy</Button
                        >
                        <Button
                            type="submit"
                            class="flex h-9 items-center gap-1.5 bg-indigo-600 text-xs font-semibold text-white hover:bg-indigo-700"
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

        <!-- MODAL: Add Cash Transaction (In/Out) -->
        <div
            v-if="showTransactionModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs"
        >
            <Card
                class="w-full max-w-md animate-in shadow-2xl duration-200 fade-in zoom-in"
            >
                <CardHeader class="border-b pb-3">
                    <CardTitle
                        class="flex items-center gap-1.5 text-sm font-bold"
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
                                ? 'Ghi Nhận Khoản Thu Tiền Mặt Khác'
                                : 'Ghi Nhận Khoản Chi Ngoài Két'
                        }}
                    </CardTitle>
                    <CardDescription class="text-xs">
                        {{
                            transactionModalType === 'in'
                                ? 'Thu các nguồn tiền mặt ngoài hệ thống hóa đơn bán hàng.'
                                : 'Ghi nhận các chi phí sửa chữa, đi chợ mua đồ phát sinh ngoài hệ thống.'
                        }}
                    </CardDescription>
                </CardHeader>
                <form @submit.prevent="handleAddTransaction">
                    <CardContent class="space-y-4 p-5">
                        <!-- Amount -->
                        <div class="space-y-1.5">
                            <Label
                                for="tx-amount"
                                class="text-xs font-bold text-slate-500"
                                >Số tiền giao dịch (VND):</Label
                            >
                            <Input
                                id="tx-amount"
                                v-model.number="txForm.amount"
                                type="number"
                                placeholder="Nhập số tiền..."
                                class="w-full"
                            />
                        </div>

                        <!-- Notes -->
                        <div class="space-y-1.5">
                            <Label
                                for="tx-notes"
                                class="text-xs font-bold text-slate-500"
                                >Nội dung chi tiết (ví dụ: Mua súp lơ đi chợ,
                                Sửa vòi nước hỏng...):</Label
                            >
                            <textarea
                                id="tx-notes"
                                v-model="txForm.notes"
                                rows="3"
                                placeholder="Vui lòng nhập lý do cụ thể..."
                                class="w-full rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-semibold outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/25 dark:border-slate-800 dark:bg-slate-950"
                            ></textarea>
                        </div>
                    </CardContent>
                    <div
                        class="flex justify-end gap-2 border-t bg-slate-50/50 p-4 dark:bg-slate-900/10"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            @click="showTransactionModal = false"
                            class="h-9 text-xs font-semibold"
                            >Hủy</Button
                        >
                        <Button
                            type="submit"
                            :class="[
                                'flex h-9 items-center gap-1.5 text-xs font-semibold text-white',
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
    </div>
</template>
