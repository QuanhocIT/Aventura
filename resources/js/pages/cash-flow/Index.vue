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
    Loader2
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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

const activeTab = ref<'active' | 'history' | 'analytics' | 'forecast'>('active');

const vnd = (v: number) =>
    new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(v);

// Modals
const showOpenModal = ref(false);
const showTransactionModal = ref(false);
const transactionModalType = ref<'in' | 'out'>('out');

// Open Register Form
const openForm = useForm({
    shift_id: '',
    opening_balance: 0,
    expense_budget: 0,
    notes: ''
});

// Transaction Form
const txForm = useForm({
    type: 'out',
    amount: 0,
    source: 'expense',
    notes: ''
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
            toast.error(Object.values(err)[0] as string || 'Có lỗi xảy ra');
        }
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
            toast.error(Object.values(err)[0] as string || 'Có lỗi xảy ra');
        }
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
        .filter(t => t.type === 'out')
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
    props.chartData.forEach(d => {
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

    <div class="flex flex-col gap-6 p-6 max-w-7xl mx-auto w-full">
        <!-- Page Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b pb-5">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 shadow-sm border border-indigo-100 dark:border-indigo-900/30">
                    <Wallet class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-800 dark:text-slate-100">Quỹ Tiền Mặt & Dòng Tiền</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Quản lý két tiền hàng ngày, theo dõi thu chi thực tế tại quầy, dự báo và cảnh báo ngân sách.</p>
                </div>
            </div>

            <!-- Open drawer button (only if no active drawer) -->
            <div v-if="!activeRegister" class="flex items-center gap-2">
                <Button 
                    @click="showOpenModal = true"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold shadow-sm text-xs h-9"
                >
                    <PlusCircle class="size-4 mr-1.5" />
                    Mở két đầu ca
                </Button>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="flex gap-2 border-b pb-1 overflow-x-auto shrink-0">
            <button 
                @click="activeTab = 'active'"
                :class="[
                    'px-4 py-2 text-xs font-bold transition-all border-b-2 whitespace-nowrap',
                    activeTab === 'active' 
                        ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' 
                        : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'
                ]"
            >
                Két tiền ca hiện tại
            </button>
            <button 
                @click="activeTab = 'history'"
                :class="[
                    'px-4 py-2 text-xs font-bold transition-all border-b-2 whitespace-nowrap',
                    activeTab === 'history' 
                        ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' 
                        : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'
                ]"
            >
                <span class="flex items-center gap-1.5"><History class="size-3.5" /> Lịch sử ca chốt két</span>
            </button>
            <button 
                @click="activeTab = 'analytics'"
                :class="[
                    'px-4 py-2 text-xs font-bold transition-all border-b-2 whitespace-nowrap',
                    activeTab === 'analytics' 
                        ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' 
                        : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'
                ]"
            >
                <span class="flex items-center gap-1.5"><BarChart3 class="size-3.5" /> Biểu đồ dòng tiền (30 ngày)</span>
            </button>
            <button 
                @click="activeTab = 'forecast'"
                :class="[
                    'px-4 py-2 text-xs font-bold transition-all border-b-2 whitespace-nowrap',
                    activeTab === 'forecast' 
                        ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400 dark:border-indigo-400' 
                        : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'
                ]"
            >
                <span class="flex items-center gap-1.5"><TrendingUp class="size-3.5" /> Dự báo thanh toán</span>
            </button>
        </div>

        <!-- tab: ACTIVE DRAWER -->
        <div v-if="activeTab === 'active'" class="space-y-6">
            <!-- Active register details -->
            <div v-if="activeRegister" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left panel: drawer stats card -->
                <div class="lg:col-span-1 space-y-6">
                    <Card class="shadow-sm border-indigo-100 dark:border-indigo-950/30 overflow-hidden">
                        <CardHeader class="pb-3 border-b bg-indigo-50/20 dark:bg-indigo-950/10">
                            <CardTitle class="text-sm font-bold flex items-center gap-1.5">
                                <Wallet class="size-4 text-indigo-600 dark:text-indigo-400" />
                                Thông tin két hiện tại
                            </CardTitle>
                            <CardDescription class="text-[11px]">Két đang mở & sẵn sàng giao dịch</CardDescription>
                        </CardHeader>
                        <CardContent class="p-5 space-y-4 text-xs">
                            <div class="flex justify-between items-center py-2 border-b dark:border-slate-800">
                                <span class="text-slate-400 font-bold flex items-center gap-1"><Clock class="size-3.5" /> Ca trực</span>
                                <span class="font-bold text-slate-700 dark:text-slate-200">{{ activeRegister.shift_name }}</span>
                            </div>
                            <div class="flex justify-between items-center py-2 border-b dark:border-slate-800">
                                <span class="text-slate-400 font-bold flex items-center gap-1"><User class="size-3.5" /> Cashier mở két</span>
                                <span class="font-bold text-slate-700 dark:text-slate-200">{{ activeRegister.opened_by_name }}</span>
                            </div>
                            <div class="flex justify-between items-center py-2 border-b dark:border-slate-800">
                                <span class="text-slate-400 font-bold flex items-center gap-1"><Calendar class="size-3.5" /> Ngày chốt</span>
                                <span class="font-bold text-slate-700 dark:text-slate-200">{{ new Date(activeRegister.closing_date).toLocaleDateString('vi-VN') }}</span>
                            </div>
                            <div class="flex justify-between items-center py-2 border-b dark:border-slate-800">
                                <span class="text-slate-400 font-bold">Mở lúc</span>
                                <span class="font-mono text-slate-600 dark:text-slate-300">{{ activeRegister.opened_at }}</span>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Budget & Alert Card -->
                    <Card class="shadow-sm overflow-hidden" :class="isBudgetExceeded ? 'border-rose-200 dark:border-rose-950/40' : 'border-slate-100 dark:border-slate-800'">
                        <CardHeader class="pb-3 border-b" :class="isBudgetExceeded ? 'bg-rose-50/20 dark:bg-rose-950/10' : 'bg-slate-50/20'">
                            <CardTitle class="text-xs font-bold uppercase tracking-wider flex items-center gap-1.5" :class="isBudgetExceeded ? 'text-rose-600 dark:text-rose-400' : 'text-slate-500'">
                                <AlertTriangle class="size-4" />
                                Ngân sách chi tiêu ca
                            </CardTitle>
                        </CardHeader>
                        <CardContent class="p-5 space-y-4">
                            <div class="flex justify-between items-center text-xs">
                                <span class="text-slate-400 font-bold">Hạn mức chi tiêu:</span>
                                <span class="font-mono font-bold text-slate-700 dark:text-slate-250">
                                    {{ activeRegister.expense_budget > 0 ? vnd(activeRegister.expense_budget) : 'Không hạn chế' }}
                                </span>
                            </div>
                            <div class="flex justify-between items-center text-xs">
                                <span class="text-slate-400 font-bold">Đã chi ngoài:</span>
                                <span class="font-mono font-bold text-slate-700 dark:text-slate-250">
                                    {{ vnd(activeExpensesTotal) }}
                                </span>
                            </div>
                            
                            <div v-if="isBudgetExceeded" class="bg-rose-50 dark:bg-rose-950/30 border border-rose-100 dark:border-rose-900/30 rounded-xl p-3 flex gap-2">
                                <ShieldAlert class="size-5 text-rose-500 shrink-0 mt-0.5" />
                                <div>
                                    <p class="text-xs font-bold text-rose-700 dark:text-rose-400">Vượt ngân sách ca!</p>
                                    <p class="text-[10px] text-rose-500 dark:text-rose-500 mt-0.5">Tổng chi tiêu ngoài hệ thống đã vượt quá hạn mức tối đa {{ vnd(activeRegister.expense_budget) }} được cấp đầu ca.</p>
                                </div>
                            </div>
                            <div v-else-if="activeRegister.expense_budget > 0" class="bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-100 dark:border-emerald-900/20 rounded-xl p-3 flex gap-2">
                                <CheckCircle class="size-5 text-emerald-500 shrink-0 mt-0.5" />
                                <div>
                                    <p class="text-xs font-bold text-emerald-700 dark:text-emerald-400">Trong tầm kiểm soát</p>
                                    <p class="text-[10px] text-emerald-500 dark:text-emerald-500 mt-0.5">Chi ngoài còn lại: {{ vnd(activeRegister.expense_budget - activeExpensesTotal) }}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Right panel: drawer cash balance and transactions log -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Balance Display & Quick Actions -->
                    <Card class="shadow-sm border-slate-100 dark:border-slate-800">
                        <CardContent class="p-6">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6 pb-6 border-b dark:border-slate-800">
                                <div>
                                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Tiền mặt thực tế kỳ vọng trong két</p>
                                    <p class="text-3xl font-black text-indigo-600 dark:text-indigo-400 mt-1.5 font-mono">
                                        {{ vnd(activeRegister.expected_cash) }}
                                    </p>
                                    <p class="text-[10px] text-slate-400 mt-1">Gồm: Mở két đầu ca ({{ vnd(activeRegister.opening_balance) }}) + Thu tại quầy - Chi ngoài ca</p>
                                </div>

                                <div class="flex flex-wrap gap-2 shrink-0">
                                    <Button 
                                        @click="openTxModal('out')"
                                        variant="outline"
                                        class="text-xs h-9 border-rose-100 text-rose-600 hover:bg-rose-50 font-bold active:scale-95 transition-transform"
                                    >
                                        <MinusCircle class="size-4 mr-1.5" />
                                        Chi tiền ngoài két
                                    </Button>
                                    <Button 
                                        @click="openTxModal('in')"
                                        variant="outline"
                                        class="text-xs h-9 border-emerald-100 text-emerald-600 hover:bg-emerald-55 font-bold active:scale-95 transition-transform"
                                    >
                                        <PlusCircle class="size-4 mr-1.5" />
                                        Thu tiền mặt khác
                                    </Button>
                                </div>
                            </div>

                            <!-- Live transactions log list -->
                            <div class="pt-6">
                                <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-4 flex items-center gap-1.5">
                                    <ArrowRightLeft class="size-4 text-slate-400" />
                                    Lịch sử giao dịch dòng tiền mặt trong ca
                                </h3>

                                <div v-if="activeTransactions.length === 0" class="text-center py-10 text-slate-400 text-xs">
                                    Chưa phát sinh giao dịch tiền mặt nào trong ca này.
                                </div>

                                <div v-else class="divide-y divide-slate-100 dark:divide-slate-800 max-h-[300px] overflow-y-auto pr-1">
                                    <div 
                                        v-for="tx in activeTransactions" 
                                        :key="tx.id" 
                                        class="flex items-center justify-between py-3 text-xs"
                                    >
                                        <div class="flex items-center gap-3">
                                            <div 
                                                :class="[
                                                    'h-8 w-8 rounded-full flex items-center justify-center border',
                                                    tx.type === 'in' 
                                                        ? 'bg-emerald-50 text-emerald-600 border-emerald-100 dark:bg-emerald-950/20 dark:text-emerald-450 dark:border-emerald-900/30' 
                                                        : 'bg-rose-50 text-rose-600 border-rose-100 dark:bg-rose-950/20 dark:text-rose-450 dark:border-rose-900/30'
                                                ]"
                                            >
                                                <component :is="tx.type === 'in' ? ArrowUpRight : ArrowDownRight" class="size-4" />
                                            </div>
                                            <div>
                                                <p class="font-bold text-slate-700 dark:text-slate-200">{{ tx.notes }}</p>
                                                <p class="text-[10px] text-slate-400 mt-0.5">
                                                    {{ tx.created_by_name }} · {{ tx.occurred_at }}
                                                </p>
                                            </div>
                                        </div>

                                        <span 
                                            :class="[
                                                'font-mono font-black text-sm',
                                                tx.type === 'in' ? 'text-emerald-600 dark:text-emerald-450' : 'text-rose-600 dark:text-rose-455'
                                            ]"
                                        >
                                            {{ tx.type === 'in' ? '+' : '-' }}{{ vnd(tx.amount) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <!-- Empty state when no register is active -->
            <div v-else class="flex flex-col items-center gap-4 py-24 text-center border-2 border-dashed rounded-2xl bg-white dark:bg-slate-950/30 dark:border-slate-800">
                <div class="flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-900 text-slate-400">
                    <Wallet class="size-8" />
                </div>
                <div>
                    <h2 class="text-base font-bold text-slate-800 dark:text-slate-200">Chưa mở két tiền mặt ca làm việc</h2>
                    <p class="text-xs text-slate-400 max-w-sm mx-auto mt-1">Để bắt đầu bán hàng bằng tiền mặt hoặc ghi nhận chi tiêu ngoài hệ thống trong ca trực, vui lòng mở két tiền mặt.</p>
                </div>
                <Button 
                    @click="showOpenModal = true"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold shadow-sm text-xs h-9 px-6 active:scale-95 transition-transform"
                >
                    <PlusCircle class="size-4 mr-1.5" />
                    Mở két đầu ca ngay
                </Button>
            </div>
        </div>

        <!-- tab: REGISTERS HISTORY -->
        <div v-if="activeTab === 'history'">
            <Card class="shadow-sm overflow-hidden">
                <CardHeader class="pb-3 border-b bg-slate-50/50 dark:bg-slate-900/10">
                    <CardTitle class="text-sm font-bold">Lịch sử ca chốt két tiền mặt</CardTitle>
                    <CardDescription class="text-xs">Danh sách các phiên đóng/mở két tiền mặt, đối soát và chênh lệch két.</CardDescription>
                </CardHeader>
                <CardContent class="p-0 overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50/20 dark:bg-slate-900/5 border-b font-bold text-slate-500">
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
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-600 dark:text-slate-350">
                            <tr v-if="registers.length === 0">
                                <td colspan="8" class="p-10 text-center text-slate-400">Chưa có lịch sử phiên két tiền mặt nào.</td>
                            </tr>
                            <tr 
                                v-for="r in registers" 
                                :key="r.id"
                                class="hover:bg-slate-50/40 dark:hover:bg-slate-900/30 transition-colors"
                            >
                                <td class="p-3 pl-5 font-bold">{{ r.closing_date }}</td>
                                <td class="p-3">{{ r.shift_name }}</td>
                                <td class="p-3">
                                    <p class="font-semibold">{{ r.opened_by_name }}</p>
                                    <p class="text-[10px] text-slate-400 mt-0.5">Đóng: {{ r.closed_by_name }}</p>
                                </td>
                                <td class="p-3 text-right font-mono font-semibold">{{ vnd(r.opening_balance) }}</td>
                                <td class="p-3 text-right font-mono font-semibold">
                                    {{ r.status === 'closed' ? vnd(r.expected_closing_balance) : '—' }}
                                </td>
                                <td class="p-3 text-right font-mono font-bold text-slate-700 dark:text-slate-200">
                                    {{ r.status === 'closed' ? vnd(r.closing_balance) : '—' }}
                                </td>
                                <td class="p-3 text-right font-mono font-black">
                                    <span 
                                        v-if="r.status === 'closed'"
                                        :class="r.difference > 0 ? 'text-emerald-600' : r.difference < 0 ? 'text-rose-600' : 'text-slate-400'"
                                    >
                                        {{ r.difference > 0 ? '+' : '' }}{{ vnd(r.difference) }}
                                    </span>
                                    <span v-else>—</span>
                                </td>
                                <td class="p-3 text-center">
                                    <span 
                                        :class="[
                                            'px-2 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider',
                                            r.status === 'open' 
                                                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400' 
                                                : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'
                                        ]"
                                    >
                                        {{ r.status === 'open' ? 'Đang mở' : 'Đã đóng' }}
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
            <Card class="shadow-sm border-slate-100 dark:border-slate-800">
                <CardHeader class="pb-3 border-b bg-slate-50/50 dark:bg-slate-900/10">
                    <CardTitle class="text-sm font-bold">Biến động Dòng tiền mặt hàng ngày (Thu vs Chi)</CardTitle>
                    <CardDescription class="text-xs">Theo dõi và so sánh lượng tiền mặt nạp vào (Bán hàng) và chi ra (Phát sinh) 30 ngày qua.</CardDescription>
                </CardHeader>
                <CardContent class="p-6">
                    <!-- Pure Tailwind Graph -->
                    <div class="space-y-4">
                        <div class="flex items-end justify-between h-48 border-b border-slate-200 dark:border-slate-800 pb-2 px-2 overflow-x-auto gap-4">
                            <div 
                                v-for="d in chartData" 
                                :key="d.date"
                                class="flex flex-col items-center gap-1 min-w-[30px] w-full group relative"
                            >
                                <!-- Bars wrapper -->
                                <div class="flex items-end gap-1 h-36 w-full justify-center">
                                    <!-- In bar (Green) -->
                                    <div 
                                        class="w-2.5 bg-emerald-500/80 hover:bg-emerald-500 rounded-t-sm transition-all duration-300"
                                        :style="`height: ${Math.max(3, (d.in / chartMaxVal) * 120)}px`"
                                    />
                                    <!-- Out bar (Red) -->
                                    <div 
                                        class="w-2.5 bg-rose-500/80 hover:bg-rose-500 rounded-t-sm transition-all duration-300"
                                        :style="`height: ${Math.max(3, (d.out / chartMaxVal) * 120)}px`"
                                    />
                                </div>

                                <!-- Tooltip popup -->
                                <div class="absolute bottom-full left-1/2 -translate-x-1/2 bg-slate-900 text-white text-[9px] rounded-lg p-2.5 hidden group-hover:block z-20 shadow-lg font-bold border border-slate-800 whitespace-nowrap">
                                    <p class="border-b pb-1 mb-1 border-slate-700 text-center">Ngày {{ d.date }}</p>
                                    <p class="text-emerald-400">Tổng thu: +{{ vnd(d.in) }}</p>
                                    <p class="text-rose-400">Tổng chi: -{{ vnd(d.out) }}</p>
                                    <p class="text-indigo-400 mt-0.5 border-t pt-1 border-slate-700">Dòng tiền ròng: {{ vnd(d.net) }}</p>
                                </div>

                                <span class="text-[9px] font-mono text-slate-400 shrink-0 scale-90">{{ d.date }}</span>
                            </div>
                        </div>

                        <!-- Legend indicators -->
                        <div class="flex justify-center gap-6 pt-4 text-xs font-semibold text-slate-500">
                            <span class="flex items-center gap-2">
                                <span class="size-3 bg-emerald-500 rounded-xs" /> Dòng tiền Thu (Sales & Khác)
                            </span>
                            <span class="flex items-center gap-2">
                                <span class="size-3 bg-rose-500 rounded-xs" /> Dòng tiền Chi (Expenses & Khác)
                            </span>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- tab: FORECASTING -->
        <div v-if="activeTab === 'forecast'" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Forecast Metrics -->
                <Card class="shadow-sm border-slate-100 dark:border-slate-800">
                    <CardHeader class="pb-3 border-b bg-slate-50/20">
                        <CardTitle class="text-xs font-bold uppercase tracking-wider text-slate-400">Doanh số kì vọng 7 ngày tới</CardTitle>
                    </CardHeader>
                    <CardContent class="p-5">
                        <p class="text-sm font-bold text-slate-400">Thu tiền mặt ước tính (7 ngày)</p>
                        <p class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-1 font-mono">
                            +{{ vnd(forecast.projected_in) }}
                        </p>
                        <p class="text-[10px] text-slate-400 mt-2">Dựa trên trung bình thu nhập tiền mặt thực tế {{ vnd(forecast.avg_daily_in) }}/ngày.</p>
                    </CardContent>
                </Card>

                <Card class="shadow-sm border-slate-100 dark:border-slate-800">
                    <CardHeader class="pb-3 border-b bg-slate-50/20">
                        <CardTitle class="text-xs font-bold uppercase tracking-wider text-slate-400">Chi tiêu dự kiến 7 ngày tới</CardTitle>
                    </CardHeader>
                    <CardContent class="p-5">
                        <p class="text-sm font-bold text-slate-400">Chi tiền mặt dự tính (7 ngày)</p>
                        <p class="text-2xl font-black text-rose-600 dark:text-rose-400 mt-1 font-mono">
                            -{{ vnd(forecast.projected_out) }}
                        </p>
                        <p class="text-[10px] text-slate-400 mt-2">Dựa trên trung bình chi tiêu ngoài hệ thống {{ vnd(forecast.avg_daily_out) }}/ngày.</p>
                    </CardContent>
                </Card>

                <Card class="shadow-sm border-slate-100 dark:border-slate-800">
                    <CardHeader class="pb-3 border-b bg-slate-50/20">
                        <CardTitle class="text-xs font-bold uppercase tracking-wider text-slate-400">Số dư dự tính cuối kỳ</CardTitle>
                    </CardHeader>
                    <CardContent class="p-5">
                        <p class="text-sm font-bold text-slate-400">Số dư két ước tính (7 ngày)</p>
                        <p 
                            class="text-2xl font-black mt-1 font-mono"
                            :class="forecast.projected_balance >= 0 ? 'text-indigo-600 dark:text-indigo-400' : 'text-rose-650'"
                        >
                            {{ vnd(forecast.projected_balance) }}
                        </p>
                        <p class="text-[10px] text-slate-400 mt-2">Tính toán từ số dư hiện tại trong két: {{ vnd(forecast.current_cash) }}</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Forecast Warning Callout -->
            <Card class="shadow-sm border overflow-hidden" :class="forecast.status === 'warning' ? 'border-rose-200 dark:border-rose-950/40 bg-rose-50/10' : forecast.status === 'low_reserve' ? 'border-amber-200 dark:border-amber-950/40 bg-amber-50/10' : 'border-emerald-100 dark:border-emerald-950/20 bg-emerald-50/10'">
                <CardContent class="p-6 flex items-start gap-4">
                    <div 
                        :class="[
                            'h-10 w-10 rounded-full flex items-center justify-center shrink-0 border',
                            forecast.status === 'warning' ? 'bg-rose-50 text-rose-500 border-rose-100' :
                            forecast.status === 'low_reserve' ? 'bg-amber-50 text-amber-500 border-amber-100' :
                            'bg-emerald-50 text-emerald-500 border-emerald-100'
                        ]"
                    >
                        <AlertTriangle v-if="forecast.status !== 'safe'" class="size-5" />
                        <CheckCircle v-else class="size-5" />
                    </div>
                    
                    <div>
                        <h3 
                            class="font-bold text-sm"
                            :class="forecast.status === 'warning' ? 'text-rose-700 dark:text-rose-400' : forecast.status === 'low_reserve' ? 'text-amber-700 dark:text-amber-450' : 'text-emerald-700 dark:text-emerald-400'"
                        >
                            {{ forecast.status === 'warning' ? 'Cảnh báo thâm hụt dòng tiền mặt!' : forecast.status === 'low_reserve' ? 'Lưu ý quỹ tiền mặt thấp' : 'Dòng tiền mặt an toàn' }}
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed mt-1.5">{{ forecast.message }}</p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- MODAL: Open Register -->
        <div v-if="showOpenModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
            <Card class="w-full max-w-md shadow-2xl animate-in fade-in zoom-in duration-200">
                <CardHeader class="pb-3 border-b">
                    <CardTitle class="text-sm font-bold flex items-center gap-1.5">
                        <Wallet class="size-5 text-indigo-600 dark:text-indigo-400" />
                        Mở Két Tiền Mặt Đầu Ca
                    </CardTitle>
                    <CardDescription class="text-xs">Thiết lập số dư két tiền ban đầu để cashier thực hiện giao dịch.</CardDescription>
                </CardHeader>
                <form @submit.prevent="handleOpenRegister">
                    <CardContent class="p-5 space-y-4">
                        <!-- Shift select -->
                        <div class="space-y-1.5">
                            <Label for="open-shift" class="text-xs font-bold text-slate-500">Ca làm việc chốt ca:</Label>
                            <select 
                                id="open-shift"
                                v-model="openForm.shift_id"
                                class="w-full text-xs font-semibold rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 px-3.5 py-2.5 outline-none focus:ring-2 focus:ring-indigo-500/25"
                            >
                                <option value="" disabled>-- Chọn ca trực --</option>
                                <option v-for="s in shifts" :key="s.id" :value="s.id">{{ s.name }} ({{ s.code }})</option>
                            </select>
                        </div>

                        <!-- Opening Balance -->
                        <div class="space-y-1.5">
                            <Label for="open-balance" class="text-xs font-bold text-slate-500">Số dư tiền mặt ban đầu (VND):</Label>
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
                            <Label for="open-budget" class="text-xs font-bold text-slate-500">Hạn mức chi tiêu ngoài két (VND - Tùy chọn):</Label>
                            <Input 
                                id="open-budget"
                                v-model.number="openForm.expense_budget"
                                type="number"
                                placeholder="Nhập ngân sách chi..."
                                class="w-full"
                            />
                            <p class="text-[10px] text-slate-400">Đặt hạn mức cảnh báo khi chi tiền đi chợ, sửa chữa vượt mức.</p>
                        </div>

                        <!-- Notes -->
                        <div class="space-y-1.5">
                            <Label for="open-notes" class="text-xs font-bold text-slate-500">Ghi chú mở két:</Label>
                            <Input 
                                id="open-notes"
                                v-model="openForm.notes"
                                type="text"
                                placeholder="Nhập ghi chú đầu ca..."
                                class="w-full"
                            />
                        </div>
                    </CardContent>
                    <div class="p-4 border-t flex justify-end gap-2 bg-slate-50/50 dark:bg-slate-900/10">
                        <Button type="button" variant="outline" @click="showOpenModal = false" class="text-xs h-9 font-semibold">Hủy</Button>
                        <Button 
                            type="submit" 
                            class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs h-9 flex items-center gap-1.5"
                            :disabled="openForm.processing"
                        >
                            <Loader2 v-if="openForm.processing" class="size-4 animate-spin" />
                            Xác nhận mở két
                        </Button>
                    </div>
                </form>
            </Card>
        </div>

        <!-- MODAL: Add Cash Transaction (In/Out) -->
        <div v-if="showTransactionModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
            <Card class="w-full max-w-md shadow-2xl animate-in fade-in zoom-in duration-200">
                <CardHeader class="pb-3 border-b">
                    <CardTitle class="text-sm font-bold flex items-center gap-1.5">
                        <component :is="transactionModalType === 'in' ? PlusCircle : MinusCircle" :class="transactionModalType === 'in' ? 'text-emerald-500' : 'text-rose-500'" class="size-5" />
                        {{ transactionModalType === 'in' ? 'Ghi Nhận Khoản Thu Tiền Mặt Khác' : 'Ghi Nhận Khoản Chi Ngoài Két' }}
                    </CardTitle>
                    <CardDescription class="text-xs">
                        {{ transactionModalType === 'in' ? 'Thu các nguồn tiền mặt ngoài hệ thống hóa đơn bán hàng.' : 'Ghi nhận các chi phí sửa chữa, đi chợ mua đồ phát sinh ngoài hệ thống.' }}
                    </CardDescription>
                </CardHeader>
                <form @submit.prevent="handleAddTransaction">
                    <CardContent class="p-5 space-y-4">
                        <!-- Amount -->
                        <div class="space-y-1.5">
                            <Label for="tx-amount" class="text-xs font-bold text-slate-500">Số tiền giao dịch (VND):</Label>
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
                            <Label for="tx-notes" class="text-xs font-bold text-slate-500">Nội dung chi tiết (ví dụ: Mua súp lơ đi chợ, Sửa vòi nước hỏng...):</Label>
                            <textarea 
                                id="tx-notes"
                                v-model="txForm.notes"
                                rows="3"
                                placeholder="Vui lòng nhập lý do cụ thể..."
                                class="w-full text-xs font-semibold rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 px-3.5 py-2.5 outline-none focus:ring-2 focus:ring-indigo-500/25 focus:border-indigo-500"
                            ></textarea>
                        </div>
                    </CardContent>
                    <div class="p-4 border-t flex justify-end gap-2 bg-slate-50/50 dark:bg-slate-900/10">
                        <Button type="button" variant="outline" @click="showTransactionModal = false" class="text-xs h-9 font-semibold">Hủy</Button>
                        <Button 
                            type="submit" 
                            :class="[
                                'text-white font-semibold text-xs h-9 flex items-center gap-1.5',
                                transactionModalType === 'in' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-rose-600 hover:bg-rose-700'
                            ]"
                            :disabled="txForm.processing"
                        >
                            <Loader2 v-if="txForm.processing" class="size-4 animate-spin" />
                            Ghi nhận giao dịch
                        </Button>
                    </div>
                </form>
            </Card>
        </div>
    </div>
</template>
