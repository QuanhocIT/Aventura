<script setup lang="ts">
import { Head, router, useForm, Deferred } from '@inertiajs/vue3';
import {
    Receipt,
    PlusCircle,
    Calendar,
    User,
    AlertTriangle,
    Clock,
    TrendingUp,
    TrendingDown,
    CheckCircle2,
    Trash2,
    Edit2,
    Eye,
    Download,
    RefreshCw,
    FileText,
    LayoutDashboard,
    Settings,
    Layers,
    ListFilter,
    X,
    FileUp,
    Check,
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { toast } from 'vue-sonner';
import ProfitLossTab from '@/components/expenses/ProfitLossTab.vue';
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
import { confirmDialog } from '@/composables/useConfirm';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

// --- Types ---
type Category = {
    id: number;
    restaurant_id: number | null;
    name: string;
    description: string | null;
};

type UserCreator = {
    id: number;
    name: string;
    email: string;
};

type OperatingExpense = {
    id: number;
    category_id: number | null;
    recurring_expense_id: number | null;
    amount: number;
    expense_date: string;
    description: string | null;
    invoice_path: string | null;
    created_by: number | null;
    category?: Category | null;
    creator?: UserCreator | null;
};

type PaginatedExpenses = {
    data: OperatingExpense[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};

type RecurringExpense = {
    id: number;
    category_id: number;
    name: string;
    amount: number;
    frequency: 'weekly' | 'monthly' | 'quarterly' | 'yearly';
    start_date: string;
    end_date: string | null;
    last_triggered_at: string | null;
    is_active: boolean;
    description: string | null;
    category?: Category;
};

type Analytics = {
    total_this_month: number;
    total_last_month: number;
    mom_delta: number;
    recurring_ratio: number;
    six_months_mom: { month: string; label: string; amount: number }[];
    category_breakdown: {
        id: number | null;
        name: string;
        amount: number;
        percentage: number;
    }[];
};

const props = defineProps<{
    expenses: PaginatedExpenses;
    recurringExpenses: RecurringExpense[];
    categories: Category[];
    analytics: Analytics;
    filters: {
        start_date: string | null;
        end_date: string | null;
        category_id: string | null;
        year?: number;
        month?: number;
    };
    profitLossReport?: any;
}>();

// --- Active Tab State ---
const activeTab = ref<'analytics' | 'expenses' | 'recurring' | 'categories' | 'profit-loss'>(
    props.filters.year || props.filters.month ? 'profit-loss' : 'analytics',
);

// --- VND Formatter Helper ---
const vnd = (v: number) =>
    new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(v);

// --- MODALS STATE ---
const showExpenseModal = ref(false);
const editingExpense = ref<OperatingExpense | null>(null);
const invoicePreviewUrl = ref<string | null>(null);

const showRecurringModal = ref(false);
const editingRecurring = ref<RecurringExpense | null>(null);

const showCategoryModal = ref(false);

// --- FILTER FORM ---
const filterForm = ref({
    start_date: props.filters.start_date || '',
    end_date: props.filters.end_date || '',
    category_id: props.filters.category_id || '',
});

function applyFilters() {
    router.get(
        '/expenses',
        {
            ...filterForm.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
}

function resetFilters() {
    filterForm.value = { start_date: '', end_date: '', category_id: '' };
    router.get('/expenses', {}, { preserveState: true, preserveScroll: true });
}

// --- EXPENSE FORM ---
const expenseForm = useForm({
    category_id: '',
    amount: 0,
    expense_date: new Date().toISOString().substring(0, 10),
    description: '',
    invoice: null as File | null,
});

function openNewExpenseModal() {
    editingExpense.value = null;
    expenseForm.reset();
    expenseForm.category_id = props.categories[0]?.id.toString() || '';
    expenseForm.amount = 0;
    expenseForm.expense_date = new Date().toISOString().substring(0, 10);
    expenseForm.description = '';
    expenseForm.invoice = null;
    showExpenseModal.value = true;
}

function openEditExpenseModal(expense: OperatingExpense) {
    editingExpense.value = expense;
    expenseForm.category_id = expense.category_id?.toString() || '';
    expenseForm.amount = expense.amount;
    expenseForm.expense_date = expense.expense_date;
    expenseForm.description = expense.description || '';
    expenseForm.invoice = null;
    showExpenseModal.value = true;
}

function handleExpenseFileChange(e: Event) {
    const target = e.target as HTMLInputElement;

    if (target.files && target.files.length > 0) {
        expenseForm.invoice = target.files[0];
    }
}

function saveExpense() {
    if (expenseForm.amount <= 0) {
        toast.error('Số tiền chi phí phải lớn hơn 0');

        return;
    }

    if (!expenseForm.expense_date) {
        toast.error('Vui lòng chọn ngày chi phí');

        return;
    }

    if (editingExpense.value) {
        // Edit flow (Laravel handles files on PATCH/PUT only when wrapped in POST with _method override)
        const updateForm = useForm({
            _method: 'PATCH',
            category_id: expenseForm.category_id,
            amount: expenseForm.amount,
            expense_date: expenseForm.expense_date,
            description: expenseForm.description,
            invoice: expenseForm.invoice,
        });

        updateForm.post(`/expenses/${editingExpense.value.id}`, {
            onSuccess: () => {
                showExpenseModal.value = false;
                toast.success('Đã cập nhật chi phí thành công!');
            },
            onError: (err: any) => {
                toast.error(
                    (Object.values(err)[0] as string) || 'Đã có lỗi xảy ra',
                );
            },
        });
    } else {
        // Create flow
        expenseForm.post('/expenses', {
            onSuccess: () => {
                showExpenseModal.value = false;
                expenseForm.reset();
                toast.success('Đã ghi nhận chi phí vận hành mới!');
            },
            onError: (err: any) => {
                toast.error(
                    (Object.values(err)[0] as string) || 'Đã có lỗi xảy ra',
                );
            },
        });
    }
}

async function deleteExpense(expense: OperatingExpense) {
    if (
        await confirmDialog({
            title: 'Xác nhận thao tác',
            description: 'Bạn có chắc chắn muốn xóa khoản chi phí này không?',
        })
    ) {
        router.delete(`/expenses/${expense.id}`, {
            onSuccess: () => toast.success('Đã xóa khoản chi phí thành công.'),
            onError: () => toast.error('Có lỗi xảy ra khi xóa.'),
        });
    }
}

// --- RECURRING FORM ---
const recurringForm = useForm({
    category_id: '',
    name: '',
    amount: 0,
    frequency: 'monthly' as 'weekly' | 'monthly' | 'quarterly' | 'yearly',
    start_date: new Date().toISOString().substring(0, 10),
    end_date: '',
    description: '',
});

function openNewRecurringModal() {
    editingRecurring.value = null;
    recurringForm.reset();
    recurringForm.category_id = props.categories[0]?.id.toString() || '';
    recurringForm.name = '';
    recurringForm.amount = 0;
    recurringForm.frequency = 'monthly';
    recurringForm.start_date = new Date().toISOString().substring(0, 10);
    recurringForm.end_date = '';
    recurringForm.description = '';
    showRecurringModal.value = true;
}

function openEditRecurringModal(rec: RecurringExpense) {
    editingRecurring.value = rec;
    recurringForm.category_id = rec.category_id.toString();
    recurringForm.name = rec.name;
    recurringForm.amount = rec.amount;
    recurringForm.frequency = rec.frequency;
    recurringForm.start_date = rec.start_date;
    recurringForm.end_date = rec.end_date || '';
    recurringForm.description = rec.description || '';
    showRecurringModal.value = true;
}

function saveRecurring() {
    if (!recurringForm.name.trim()) {
        toast.error('Vui lòng nhập tên chi phí định kỳ');

        return;
    }

    if (recurringForm.amount <= 0) {
        toast.error('Số tiền phải lớn hơn 0');

        return;
    }

    if (!recurringForm.start_date) {
        toast.error('Vui lòng chọn ngày bắt đầu');

        return;
    }

    if (editingRecurring.value) {
        recurringForm.patch(
            `/expenses/recurring/${editingRecurring.value.id}`,
            {
                onSuccess: () => {
                    showRecurringModal.value = false;
                    toast.success('Đã cập nhật chi phí định kỳ.');
                },
                onError: (err: any) => {
                    toast.error(
                        (Object.values(err)[0] as string) || 'Có lỗi xảy ra',
                    );
                },
            },
        );
    } else {
        recurringForm.post('/expenses/recurring', {
            onSuccess: () => {
                showRecurringModal.value = false;
                recurringForm.reset();
                toast.success('Đã thêm cấu hình chi phí định kỳ mới.');
            },
            onError: (err: any) => {
                toast.error(
                    (Object.values(err)[0] as string) || 'Có lỗi xảy ra',
                );
            },
        });
    }
}

function toggleRecurringStatus(rec: RecurringExpense) {
    router.patch(
        `/expenses/recurring/${rec.id}`,
        {
            is_active: !rec.is_active,
        },
        {
            preserveScroll: true,
            onSuccess: () =>
                toast.success('Đã thay đổi trạng thái chi phí định kỳ.'),
        },
    );
}

async function deleteRecurring(rec: RecurringExpense) {
    if (
        await confirmDialog({
            title: 'Xác nhận thao tác',
            description:
                'Bạn có chắc chắn muốn xóa cấu hình chi phí định kỳ này? Lịch sử chi phí đã sinh ra vẫn được lưu giữ.',
        })
    ) {
        router.delete(`/expenses/recurring/${rec.id}`, {
            onSuccess: () =>
                toast.success('Đã xóa cấu hình chi phí định kỳ thành công.'),
            onError: () => toast.error('Có lỗi xảy ra khi xóa.'),
        });
    }
}

// --- CATEGORY FORM ---
const categoryForm = useForm({
    name: '',
    description: '',
});

function saveCategory() {
    if (!categoryForm.name.trim()) {
        toast.error('Tên danh mục không được để trống');

        return;
    }

    categoryForm.post('/expenses/categories', {
        onSuccess: () => {
            showCategoryModal.value = false;
            categoryForm.reset();
            toast.success('Đã thêm danh mục chi phí tùy chỉnh mới!');
        },
        onError: (err: any) => {
            toast.error((Object.values(err)[0] as string) || 'Có lỗi xảy ra');
        },
    });
}

async function deleteCategory(cat: Category) {
    if (
        await confirmDialog({
            title: 'Xác nhận thao tác',
            description: `Bạn có chắc chắn muốn xóa danh mục "${cat.name}"? Các chi phí liên kết sẽ bị xóa hoặc gán thành "Chưa phân loại".`,
        })
    ) {
        router.delete(`/expenses/categories/${cat.id}`, {
            onSuccess: () => toast.success('Đã xóa danh mục thành công.'),
            onError: () => toast.error('Có lỗi xảy ra khi xóa.'),
        });
    }
}

// --- Chart Max Value Helper ---
const chartMaxVal = computed(() => {
    const max = Math.max(
        ...props.analytics.six_months_mom.map((m) => m.amount),
        0,
    );

    return max || 1;
});
</script>

<template>
    <Head title="Quản Lý Chi Phí Vận Hành (OPEX)" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-4 lg:p-6">
        <!-- Page Header -->
        <div
            class="flex flex-col gap-4 border-b border-border pb-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-2xl border border-amber-500/20 bg-amber-500/10 text-amber-600 shadow-xs dark:text-amber-400"
                >
                    <Receipt class="size-6" />
                </div>
                <div>
                    <h1
                        class="text-2xl font-black tracking-tight text-slate-800 dark:text-slate-100"
                    >
                        Quản Lý Chi Phí Vận Hành (OPEX)
                    </h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Theo dõi, kiểm soát chi phí thực tế (mặt bằng, điện
                        nước, marketing) và đồng bộ vào báo cáo Lãi/Lỗ của nhà
                        hàng.
                    </p>
                </div>
            </div>

            <!-- Page Action Buttons based on active tab -->
            <div class="flex items-center gap-2">
                <Button
                    v-if="activeTab === 'expenses'"
                    @click="openNewExpenseModal"
                    class="h-9 bg-amber-600 text-xs font-bold text-white hover:bg-amber-700"
                >
                    <PlusCircle class="mr-1.5 size-4" />
                    Ghi nhận chi phí
                </Button>
                <Button
                    v-if="activeTab === 'recurring'"
                    @click="openNewRecurringModal"
                    class="h-9 bg-indigo-600 text-xs font-bold text-white hover:bg-indigo-700"
                >
                    <PlusCircle class="mr-1.5 size-4" />
                    Tạo chi phí định kỳ
                </Button>
                <Button
                    v-if="activeTab === 'categories'"
                    @click="showCategoryModal = true"
                    class="h-9 bg-slate-800 text-xs font-bold text-white hover:bg-slate-900 dark:bg-slate-700 dark:hover:bg-slate-600"
                >
                    <PlusCircle class="mr-1.5 size-4" />
                    Thêm danh mục
                </Button>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div
            class="flex shrink-0 gap-2 overflow-x-auto border-b border-border pb-1"
        >
            <button
                @click="activeTab = 'analytics'"
                :class="[
                    'border-b-2 px-4 py-2.5 text-xs font-bold whitespace-nowrap transition-all',
                    activeTab === 'analytics'
                        ? 'border-amber-500 text-amber-600 dark:text-amber-400'
                        : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200',
                ]"
            >
                <span class="flex items-center gap-1.5"
                    ><LayoutDashboard class="size-3.5" /> Phân tích & So
                    sánh</span
                >
            </button>
            <button
                @click="activeTab = 'expenses'"
                :class="[
                    'border-b-2 px-4 py-2.5 text-xs font-bold whitespace-nowrap transition-all',
                    activeTab === 'expenses'
                        ? 'border-amber-500 text-amber-600 dark:text-amber-400'
                        : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200',
                ]"
            >
                <span class="flex items-center gap-1.5"
                    ><Receipt class="size-3.5" /> Danh sách chi phí phát
                    sinh</span
                >
            </button>
            <button
                @click="activeTab = 'recurring'"
                :class="[
                    'border-b-2 px-4 py-2.5 text-xs font-bold whitespace-nowrap transition-all',
                    activeTab === 'recurring'
                        ? 'border-amber-500 text-amber-600 dark:text-amber-400'
                        : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200',
                ]"
            >
                <span class="flex items-center gap-1.5"
                    ><Clock class="size-3.5" /> Chi phí định kỳ tự động</span
                >
            </button>
            <button
                @click="activeTab = 'categories'"
                :class="[
                    'border-b-2 px-4 py-2.5 text-xs font-bold whitespace-nowrap transition-all',
                    activeTab === 'categories'
                        ? 'border-amber-500 text-amber-600 dark:text-amber-400'
                        : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200',
                ]"
            >
                <span class="flex items-center gap-1.5"
                    ><Layers class="size-3.5" /> Danh mục chi phí</span
                >
            </button>
            <button
                @click="activeTab = 'profit-loss'"
                :class="[
                    'border-b-2 px-4 py-2.5 text-xs font-bold whitespace-nowrap transition-all',
                    activeTab === 'profit-loss'
                        ? 'border-amber-500 text-amber-600 dark:text-amber-400'
                        : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200',
                ]"
            >
                <span class="flex items-center gap-1.5"
                    ><FileText class="size-3.5" /> Báo cáo Lãi/Lỗ</span
                >
            </button>
        </div>

        <!-- ── TAB 1: ANALYTICS ── -->
        <div v-if="activeTab === 'analytics'" class="animate-fade-in space-y-6">
            <!-- Metric Cards -->
            <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                <!-- Card 1: Total OPEX this month -->
                <Card class="border-border bg-card shadow-xs">
                    <CardHeader class="pb-2">
                        <CardDescription
                            class="text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                            >Tổng chi phí vận hành tháng này</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="pb-5">
                        <p
                            class="font-mono text-3xl font-black text-amber-600 dark:text-amber-400"
                        >
                            {{ vnd(analytics.total_this_month) }}
                        </p>
                        <p
                            class="mt-2 flex items-center gap-1 text-[10px] text-slate-400"
                        >
                            <Clock class="size-3" />
                            Tổng cộng các khoản chi thực tế phát sinh từ đầu
                            tháng
                        </p>
                    </CardContent>
                </Card>

                <!-- Card 2: MoM Change -->
                <Card class="border-border bg-card shadow-xs">
                    <CardHeader class="pb-2">
                        <CardDescription
                            class="text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                            >Biến động so với tháng trước (MoM)</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="pb-5">
                        <div class="flex items-center gap-2">
                            <div
                                :class="[
                                    'flex h-8 w-8 items-center justify-center rounded-full border',
                                    analytics.mom_delta > 0
                                        ? 'border-rose-100 bg-rose-50 text-rose-600 dark:border-rose-900/30 dark:bg-rose-950/20'
                                        : analytics.mom_delta < 0
                                          ? 'border-emerald-100 bg-emerald-50 text-emerald-600 dark:border-emerald-900/30 dark:bg-emerald-950/20'
                                          : 'border-slate-100 bg-slate-50 text-slate-500',
                                ]"
                            >
                                <component
                                    :is="
                                        analytics.mom_delta >= 0
                                            ? TrendingUp
                                            : TrendingDown
                                    "
                                    class="size-4"
                                />
                            </div>
                            <span
                                :class="[
                                    'font-mono text-2xl font-black',
                                    analytics.mom_delta > 0
                                        ? 'text-rose-600'
                                        : analytics.mom_delta < 0
                                          ? 'text-emerald-600'
                                          : 'text-slate-600',
                                ]"
                            >
                                {{ analytics.mom_delta >= 0 ? '+' : ''
                                }}{{ analytics.mom_delta }}%
                            </span>
                        </div>
                        <p class="mt-2 text-[10px] text-slate-400">
                            Tháng trước:
                            <span
                                class="dark:text-slate-350 font-bold text-slate-600"
                                >{{ vnd(analytics.total_last_month) }}</span
                            >
                        </p>
                    </CardContent>
                </Card>

                <!-- Card 3: Recurring share -->
                <Card class="border-border bg-card shadow-xs">
                    <CardHeader class="pb-2">
                        <CardDescription
                            class="text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                            >Tỷ lệ chi phí cố định (Định kỳ)</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="pb-5">
                        <p
                            class="font-mono text-3xl font-black text-indigo-600 dark:text-indigo-400"
                        >
                            {{ analytics.recurring_ratio }}%
                        </p>
                        <p class="mt-2 text-[10px] text-slate-400">
                            Tỷ trọng các khoản chi tự động (mặt bằng, phần mềm)
                            trong tổng chi phí
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Charts and Breakdown -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Chart card -->
                <Card class="border-border shadow-xs lg:col-span-2">
                    <CardHeader
                        class="border-b bg-slate-50/40 pb-3 dark:bg-slate-900/10"
                    >
                        <CardTitle class="text-sm font-bold"
                            >Biến động chi phí vận hành (6 tháng qua)</CardTitle
                        >
                        <CardDescription class="text-xs"
                            >Theo dõi lượng tiền chi tiêu vận hành nhà hàng
                            Month-over-Month.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="p-6">
                        <div class="space-y-4">
                            <!-- Visual Bars -->
                            <div
                                class="flex h-52 items-end justify-between gap-6 overflow-x-auto border-b border-border px-4 pb-2"
                            >
                                <div
                                    v-for="d in analytics.six_months_mom"
                                    :key="d.month"
                                    class="group relative flex w-full min-w-[50px] flex-col items-center gap-2"
                                >
                                    <!-- Amount value on top of bar -->
                                    <span
                                        class="pointer-events-none absolute bottom-full z-10 mb-1 rounded bg-slate-800 px-1.5 py-0.5 font-mono text-[9px] font-bold whitespace-nowrap text-slate-600 text-white opacity-0 transition-opacity group-hover:opacity-100"
                                    >
                                        {{ vnd(d.amount) }}
                                    </span>

                                    <!-- Bar wrapper -->
                                    <div
                                        class="flex h-40 w-full items-end justify-center"
                                    >
                                        <div
                                            class="w-8 rounded-t-sm bg-amber-500/80 shadow-xs transition-all duration-300 group-hover:bg-amber-500"
                                            :style="`height: ${Math.max(4, (d.amount / chartMaxVal) * 140)}px`"
                                        />
                                    </div>

                                    <!-- X Label -->
                                    <span
                                        class="font-mono text-[10px] font-bold whitespace-nowrap text-slate-500"
                                        >{{ d.month }}</span
                                    >
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Category Breakdown list -->
                <Card class="border-border shadow-xs lg:col-span-1">
                    <CardHeader
                        class="border-b bg-slate-50/40 pb-3 dark:bg-slate-900/10"
                    >
                        <CardTitle class="text-sm font-bold"
                            >Cơ cấu chi phí theo danh mục</CardTitle
                        >
                        <CardDescription class="text-xs"
                            >Phân bổ dòng tiền chi phí trong tháng
                            này.</CardDescription
                        >
                    </CardHeader>
                    <CardContent class="space-y-4 p-5">
                        <div
                            v-if="analytics.category_breakdown.length === 0"
                            class="py-10 text-center text-xs text-slate-400"
                        >
                            Chưa phát sinh giao dịch chi phí nào trong tháng
                            này.
                        </div>
                        <div
                            v-else
                            class="max-h-[300px] space-y-4 overflow-y-auto pr-1"
                        >
                            <div
                                v-for="c in analytics.category_breakdown"
                                :key="c.name"
                                class="space-y-1.5"
                            >
                                <div
                                    class="flex items-center justify-between text-xs font-bold"
                                >
                                    <span
                                        class="text-slate-700 dark:text-slate-200"
                                        >{{ c.name }}</span
                                    >
                                    <span
                                        class="font-mono text-slate-900 dark:text-slate-100"
                                        >{{ vnd(c.amount) }}
                                        <span
                                            class="text-[10px] font-normal text-slate-400"
                                            >({{ c.percentage }}%)</span
                                        ></span
                                    >
                                </div>
                                <!-- Progress bar -->
                                <div
                                    class="h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                                >
                                    <div
                                        class="h-full rounded-full bg-amber-500 transition-all"
                                        :style="`width: ${c.percentage}%`"
                                    />
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- ── TAB 2: EXPENSES LIST ── -->
        <div v-if="activeTab === 'expenses'" class="animate-fade-in space-y-6">
            <!-- Filters Bar -->
            <Card class="border-border shadow-xs">
                <CardContent
                    class="flex flex-col items-end gap-3 p-4 text-xs md:flex-row"
                >
                    <!-- Category filter -->
                    <div class="w-full space-y-1.5 md:w-1/4">
                        <Label class="text-[11px] font-bold text-slate-400"
                            >Danh mục:</Label
                        >
                        <select
                            v-model="filterForm.category_id"
                            class="w-full rounded-lg border border-border bg-background px-3 py-2 text-xs font-semibold outline-hidden focus:ring-2 focus:ring-amber-500/25"
                        >
                            <option value="">-- Tất cả danh mục --</option>
                            <option
                                v-for="c in categories"
                                :key="c.id"
                                :value="c.id"
                            >
                                {{ c.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Start date -->
                    <div class="w-full space-y-1.5 md:w-1/4">
                        <Label class="text-[11px] font-bold text-slate-400"
                            >Từ ngày:</Label
                        >
                        <Input
                            v-model="filterForm.start_date"
                            type="date"
                            class="h-8 w-full text-xs"
                        />
                    </div>

                    <!-- End date -->
                    <div class="w-full space-y-1.5 md:w-1/4">
                        <Label class="text-[11px] font-bold text-slate-400"
                            >Đến ngày:</Label
                        >
                        <Input
                            v-model="filterForm.end_date"
                            type="date"
                            class="h-8 w-full text-xs"
                        />
                    </div>

                    <!-- Actions -->
                    <div
                        class="flex w-full shrink-0 items-center gap-2 md:w-auto"
                    >
                        <Button
                            @click="applyFilters"
                            class="h-8 bg-amber-600 px-4 text-xs font-bold text-white hover:bg-amber-700"
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

            <!-- Table Card -->
            <Card class="overflow-hidden border-border shadow-xs">
                <CardHeader
                    class="border-b bg-slate-50/40 pb-3 dark:bg-slate-900/10"
                >
                    <CardTitle class="text-sm font-bold"
                        >Chi phí phát sinh thực tế</CardTitle
                    >
                    <CardDescription class="text-xs"
                        >Danh sách các hóa đơn chi phí đã được ghi nhận trong hệ
                        thống.</CardDescription
                    >
                </CardHeader>
                <CardContent class="overflow-x-auto p-0">
                    <table class="w-full border-collapse text-left text-xs">
                        <thead>
                            <tr
                                class="border-b bg-slate-50/20 font-bold text-slate-500 dark:bg-slate-900/5"
                            >
                                <th class="p-3 pl-5">Ngày chi</th>
                                <th class="p-3">Danh mục</th>
                                <th class="p-3">Nội dung ghi chú</th>
                                <th class="p-3 text-right">Số tiền</th>
                                <th class="p-3 text-center">Nguồn</th>
                                <th class="p-3 text-center">Chứng từ</th>
                                <th class="p-3 text-center">Người tạo</th>
                                <th class="p-3 text-center">Hành động</th>
                            </tr>
                        </thead>
                        <tbody
                            class="dark:text-slate-350 divide-y divide-slate-100 text-slate-600 dark:divide-slate-800"
                        >
                            <tr v-if="expenses.data.length === 0">
                                <td
                                    colspan="8"
                                    class="p-12 text-center font-bold text-slate-400"
                                >
                                    Không tìm thấy bản ghi chi phí nào.
                                </td>
                            </tr>
                            <tr
                                v-for="e in expenses.data"
                                :key="e.id"
                                class="transition-colors hover:bg-slate-50/40 dark:hover:bg-slate-900/20"
                            >
                                <td class="p-3 pl-5 font-mono font-bold">
                                    {{ e.expense_date }}
                                </td>
                                <td
                                    class="p-3 font-semibold text-slate-700 dark:text-slate-300"
                                >
                                    {{
                                        e.category
                                            ? e.category.name
                                            : 'Chưa phân loại'
                                    }}
                                </td>
                                <td
                                    class="max-w-xs truncate p-3"
                                    :title="e.description || ''"
                                >
                                    {{ e.description || '—' }}
                                </td>
                                <td
                                    class="p-3 text-right font-mono font-bold text-slate-800 dark:text-slate-200"
                                >
                                    {{ vnd(e.amount) }}
                                </td>
                                <td class="p-3 text-center">
                                    <span
                                        v-if="e.recurring_expense_id"
                                        class="rounded-full bg-indigo-50 px-2 py-0.5 text-[9px] font-bold tracking-wider text-indigo-600 uppercase dark:bg-indigo-950/30 dark:text-indigo-400"
                                    >
                                        Định kỳ
                                    </span>
                                    <span
                                        v-else
                                        class="rounded-full bg-amber-50 px-2 py-0.5 text-[9px] font-bold tracking-wider text-amber-700 uppercase dark:bg-amber-950/20 dark:text-amber-400"
                                    >
                                        Thủ công
                                    </span>
                                </td>
                                <td class="p-3 text-center">
                                    <button
                                        v-if="e.invoice_path"
                                        @click="
                                            invoicePreviewUrl = e.invoice_path
                                        "
                                        class="inline-flex cursor-pointer items-center gap-1 font-bold text-amber-600 hover:text-amber-700 hover:underline"
                                    >
                                        <FileText class="size-3.5" /> Xem
                                    </button>
                                    <span v-else class="text-slate-400">—</span>
                                </td>
                                <td class="p-3 text-center text-slate-400">
                                    {{
                                        e.creator ? e.creator.name : 'Hệ thống'
                                    }}
                                </td>
                                <td class="p-3 text-center">
                                    <div
                                        class="flex items-center justify-center gap-1.5"
                                    >
                                        <button
                                            @click="openEditExpenseModal(e)"
                                            class="dark:hover:text-slate-350 cursor-pointer rounded-sm p-1 text-slate-500 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800"
                                            title="Sửa chi phí"
                                        >
                                            <Edit2 class="size-3.5" />
                                        </button>
                                        <button
                                            @click="deleteExpense(e)"
                                            class="cursor-pointer rounded-sm p-1 text-rose-500 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/25"
                                            title="Xóa chi phí"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>

                <!-- Paginations -->
                <Pagination
                    :links="expenses.links"
                    :current-page="expenses.current_page"
                    :last-page="expenses.last_page"
                    :total="expenses.total"
                />
            </Card>
        </div>

        <!-- ── TAB 3: RECURRING EXPENSES ── -->
        <div v-if="activeTab === 'recurring'" class="animate-fade-in space-y-6">
            <Card class="overflow-hidden border-border shadow-xs">
                <CardHeader
                    class="border-b bg-slate-50/40 pb-3 dark:bg-slate-900/10"
                >
                    <CardTitle class="text-sm font-bold"
                        >Cấu hình chi phí định kỳ cố định</CardTitle
                    >
                    <CardDescription class="text-xs"
                        >Thiết lập các chi phí cố định (tiền thuê nhà, bảo hiểm,
                        phí dịch vụ...) tự động ghi nhận theo định
                        kỳ.</CardDescription
                    >
                </CardHeader>
                <CardContent class="overflow-x-auto p-0">
                    <table class="w-full border-collapse text-left text-xs">
                        <thead>
                            <tr
                                class="border-b bg-slate-50/20 font-bold text-slate-500 dark:bg-slate-900/5"
                            >
                                <th class="p-3 pl-5">Tên chi phí</th>
                                <th class="p-3">Danh mục</th>
                                <th class="p-3 text-center">Chu kỳ</th>
                                <th class="p-3 text-right">Số tiền</th>
                                <th class="p-3 text-center">Ngày bắt đầu</th>
                                <th class="p-3 text-center">Ngày kết thúc</th>
                                <th class="p-3 text-center">
                                    Ngày chạy gần nhất
                                </th>
                                <th class="p-3 text-center">Kích hoạt</th>
                                <th class="p-3 text-center">Hành động</th>
                            </tr>
                        </thead>
                        <tbody
                            class="dark:text-slate-350 divide-y divide-slate-100 text-slate-600 dark:divide-slate-800"
                        >
                            <tr v-if="recurringExpenses.length === 0">
                                <td
                                    colspan="9"
                                    class="p-12 text-center font-bold text-slate-400"
                                >
                                    Chưa tạo chi phí định kỳ nào.
                                </td>
                            </tr>
                            <tr
                                v-for="r in recurringExpenses"
                                :key="r.id"
                                class="transition-colors hover:bg-slate-50/40 dark:hover:bg-slate-900/20"
                            >
                                <td
                                    class="p-3 pl-5 font-bold text-slate-800 dark:text-slate-200"
                                >
                                    {{ r.name }}
                                </td>
                                <td class="p-3 font-semibold">
                                    {{ r.category ? r.category.name : '—' }}
                                </td>
                                <td
                                    class="p-3 text-center font-bold text-slate-500"
                                >
                                    <span v-if="r.frequency === 'weekly'"
                                        >Hàng tuần</span
                                    >
                                    <span v-else-if="r.frequency === 'monthly'"
                                        >Hàng tháng</span
                                    >
                                    <span
                                        v-else-if="r.frequency === 'quarterly'"
                                        >Hàng quý</span
                                    >
                                    <span v-else-if="r.frequency === 'yearly'"
                                        >Hàng năm</span
                                    >
                                </td>
                                <td
                                    class="dark:text-slate-250 p-3 text-right font-mono font-black text-slate-700"
                                >
                                    {{ vnd(r.amount) }}
                                </td>
                                <td
                                    class="p-3 text-center font-mono text-slate-500"
                                >
                                    {{ r.start_date }}
                                </td>
                                <td
                                    class="p-3 text-center font-mono text-slate-500"
                                >
                                    {{ r.end_date || 'Vô thời hạn' }}
                                </td>
                                <td
                                    class="p-3 text-center font-mono text-slate-400"
                                >
                                    {{ r.last_triggered_at || 'Chưa chạy' }}
                                </td>
                                <td class="p-3 text-center">
                                    <!-- Switch / Toggle Status -->
                                    <button
                                        @click="toggleRecurringStatus(r)"
                                        :class="[
                                            'relative h-5 w-10 cursor-pointer rounded-full p-0.5 outline-hidden transition-all',
                                            r.is_active
                                                ? 'bg-emerald-500'
                                                : 'bg-slate-300 dark:bg-slate-700',
                                        ]"
                                    >
                                        <span
                                            :class="[
                                                'block h-4 w-4 rounded-full bg-white shadow-xs transition-all',
                                                r.is_active
                                                    ? 'translate-x-5'
                                                    : 'translate-x-0',
                                            ]"
                                        />
                                    </button>
                                </td>
                                <td class="p-3 text-center">
                                    <div
                                        class="flex items-center justify-center gap-1.5"
                                    >
                                        <button
                                            @click="openEditRecurringModal(r)"
                                            class="cursor-pointer rounded-sm p-1 text-slate-500 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800"
                                            title="Sửa"
                                        >
                                            <Edit2 class="size-3.5" />
                                        </button>
                                        <button
                                            @click="deleteRecurring(r)"
                                            class="cursor-pointer rounded-sm p-1 text-rose-500 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/25"
                                            title="Xóa"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>
        </div>

        <!-- ── TAB 4: CATEGORIES MANAGEMENT ── -->
        <div v-if="activeTab === 'categories'" class="animate-fade-in space-y-6">
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                <!-- Loop over all categories -->
                <Card
                    v-for="c in categories"
                    :key="c.id"
                    class="overflow-hidden border-border shadow-xs"
                >
                    <CardHeader
                        class="flex flex-row items-start justify-between border-b bg-slate-50/30 pb-2 dark:bg-slate-900/5"
                    >
                        <div>
                            <CardTitle
                                class="text-sm font-bold text-slate-800 dark:text-slate-200"
                                >{{ c.name }}</CardTitle
                            >
                            <CardDescription class="mt-1 text-[10px] font-bold">
                                <span
                                    v-if="c.restaurant_id === null"
                                    class="dark:bg-slate-850 rounded-full bg-slate-100 px-2 py-0.5 font-bold text-slate-500 dark:text-slate-400"
                                >
                                    Hệ thống dùng chung
                                </span>
                                <span
                                    v-else
                                    class="rounded-full bg-amber-50 px-2 py-0.5 font-bold text-amber-700 dark:bg-amber-950/25 dark:text-amber-400"
                                >
                                    Tùy chỉnh riêng
                                </span>
                            </CardDescription>
                        </div>

                        <!-- Trash icon for custom category -->
                        <button
                            v-if="c.restaurant_id !== null"
                            @click="deleteCategory(c)"
                            class="cursor-pointer rounded-sm p-1 text-rose-500 transition-colors hover:bg-rose-50 dark:hover:bg-rose-950/30"
                            title="Xóa danh mục"
                        >
                            <Trash2 class="size-4" />
                        </button>
                    </CardHeader>
                    <CardContent
                        class="min-h-[50px] pt-3 text-xs leading-relaxed text-slate-500 dark:text-slate-400"
                    >
                        {{
                            c.description ||
                            'Chưa có mô tả chi tiết cho danh mục này.'
                        }}
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- ── TAB 5: PROFIT & LOSS REPORT ── -->
        <div v-if="activeTab === 'profit-loss'" class="animate-fade-in space-y-6">
            <Deferred data="profitLossReport">
                <template #fallback>
                    <div class="space-y-6">
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <div class="flex items-center gap-4">
                                <div class="h-10 w-10 animate-pulse rounded-xl bg-slate-100 dark:bg-slate-800"></div>
                                <div class="space-y-2">
                                    <div class="h-4 w-40 animate-pulse rounded-sm bg-slate-100 dark:bg-slate-800"></div>
                                    <div class="h-3 w-64 animate-pulse rounded-sm bg-slate-100 dark:bg-slate-800"></div>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <div class="h-9 w-28 animate-pulse rounded-md bg-slate-100 dark:bg-slate-800"></div>
                                <div class="h-9 w-24 animate-pulse rounded-md bg-slate-100 dark:bg-slate-800"></div>
                            </div>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div class="h-28 w-full animate-pulse rounded-2xl bg-slate-100 dark:bg-slate-800"></div>
                            <div class="h-28 w-full animate-pulse rounded-2xl bg-slate-100 dark:bg-slate-800"></div>
                            <div class="h-28 w-full animate-pulse rounded-2xl bg-slate-100 dark:bg-slate-800"></div>
                        </div>
                        <div class="h-56 w-full animate-pulse rounded-2xl bg-slate-100 dark:bg-slate-800"></div>
                    </div>
                </template>
                <ProfitLossTab
                    v-if="props.profitLossReport"
                    :report="props.profitLossReport"
                    :filters="{ year: props.filters.year || new Date().getFullYear(), month: props.filters.month || (new Date().getMonth() + 1) }"
                />
            </Deferred>
        </div>

        <!-- ── MODAL: CREATE/EDIT OPERATING EXPENSE ── -->
        <div
            v-if="showExpenseModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs"
        >
            <Card
                class="w-full max-w-lg animate-in border-border bg-card shadow-2xl duration-200 fade-in zoom-in"
            >
                <CardHeader class="border-b pb-3">
                    <CardTitle
                        class="flex items-center gap-1.5 text-sm font-bold"
                    >
                        <Receipt class="size-5 text-amber-600" />
                        {{
                            editingExpense
                                ? 'Sửa Chi Phí Vận Hành'
                                : 'Ghi Nhận Khoản Chi Phí Vận Hành'
                        }}
                    </CardTitle>
                    <CardDescription class="text-xs"
                        >Nhập hóa đơn chi phí (không bao gồm nguyên vật liệu
                        COGS và lương nhân viên).</CardDescription
                    >
                </CardHeader>
                <form @submit.prevent="saveExpense">
                    <CardContent class="space-y-4 p-5 text-xs">
                        <!-- Category field -->
                        <div class="space-y-1.5">
                            <Label
                                for="expense-cat"
                                class="text-xs font-bold text-slate-500"
                                >Danh mục chi phí:</Label
                            >
                            <select
                                id="expense-cat"
                                v-model="expenseForm.category_id"
                                class="w-full rounded-lg border border-border bg-background px-3 py-2.5 text-xs font-semibold outline-hidden focus:ring-2 focus:ring-amber-500/25"
                            >
                                <option
                                    v-for="c in categories"
                                    :key="c.id"
                                    :value="c.id"
                                >
                                    {{ c.name }}
                                </option>
                            </select>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <!-- Amount -->
                            <div class="space-y-1.5">
                                <Label
                                    for="expense-amount"
                                    class="text-xs font-bold text-slate-500"
                                    >Số tiền chi tiêu (VND):</Label
                                >
                                <Input
                                    id="expense-amount"
                                    v-model.number="expenseForm.amount"
                                    type="number"
                                    placeholder="Nhập số tiền..."
                                    class="w-full text-xs"
                                />
                            </div>

                            <!-- Date -->
                            <div class="space-y-1.5">
                                <Label
                                    for="expense-date"
                                    class="text-xs font-bold text-slate-500"
                                    >Ngày ghi nhận:</Label
                                >
                                <Input
                                    id="expense-date"
                                    v-model="expenseForm.expense_date"
                                    type="date"
                                    class="w-full text-xs"
                                />
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="space-y-1.5">
                            <Label
                                for="expense-desc"
                                class="text-xs font-bold text-slate-500"
                                >Ghi chú chi tiết:</Label
                            >
                            <textarea
                                id="expense-desc"
                                v-model="expenseForm.description"
                                rows="3"
                                placeholder="Mô tả lý do chi, thông tin nhà cung cấp dịch vụ..."
                                class="w-full rounded-lg border border-border bg-background px-3 py-2 text-xs font-semibold outline-hidden focus:ring-2 focus:ring-amber-500/25"
                            ></textarea>
                        </div>

                        <!-- Invoice Proof Upload -->
                        <div class="space-y-2">
                            <Label
                                class="block text-xs font-bold text-slate-500"
                                >Tải lên bill / hóa đơn chứng minh (Ảnh hoặc
                                PDF):</Label
                            >
                            <div class="flex items-center gap-3">
                                <label
                                    class="inline-flex w-full cursor-pointer items-center justify-center gap-1.5 rounded-lg border border-dashed border-amber-300 px-4 py-3 text-center text-xs font-bold text-amber-600 transition-all select-none hover:bg-amber-50/20 dark:border-amber-900/50"
                                >
                                    <FileUp class="size-4" />
                                    {{
                                        expenseForm.invoice
                                            ? 'Đã chọn: ' +
                                              expenseForm.invoice.name
                                            : 'Chọn File/Chụp Ảnh Hóa Đơn...'
                                    }}
                                    <input
                                        type="file"
                                        accept="image/*,application/pdf"
                                        class="hidden"
                                        @change="handleExpenseFileChange"
                                    />
                                </label>
                            </div>
                            <p class="mt-1 text-[10px] text-slate-400">
                                Dung lượng tối đa 5MB. Định dạng: JPG, PNG, WEBP
                                hoặc PDF.
                            </p>
                            <div
                                v-if="
                                    editingExpense &&
                                    editingExpense.invoice_path &&
                                    !expenseForm.invoice
                                "
                                class="flex items-center gap-1 rounded-lg bg-muted p-2 text-[10px] text-slate-500"
                            >
                                <FileText class="size-3.5 text-amber-600" />
                                Hóa đơn hiện có:
                                <a
                                    :href="editingExpense.invoice_path"
                                    target="_blank"
                                    class="font-bold text-amber-600 hover:underline"
                                    >Xem hóa đơn hiện tại</a
                                >
                            </div>
                        </div>
                    </CardContent>
                    <div
                        class="flex justify-end gap-2 border-t bg-slate-50/30 p-4 dark:bg-slate-900/10"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            @click="showExpenseModal = false"
                            class="h-9 text-xs font-semibold"
                            >Hủy</Button
                        >
                        <Button
                            type="submit"
                            class="flex h-9 items-center gap-1.5 bg-amber-600 text-xs font-bold text-white hover:bg-amber-700"
                            :disabled="expenseForm.processing"
                        >
                            <span
                                v-if="expenseForm.processing"
                                class="mr-1 size-3 animate-spin rounded-full border-2 border-white border-t-transparent"
                            ></span>
                            {{
                                editingExpense
                                    ? 'Lưu cập nhật'
                                    : 'Ghi nhận chi phí'
                            }}
                        </Button>
                    </div>
                </form>
            </Card>
        </div>

        <!-- ── MODAL: CREATE/EDIT RECURRING EXPENSE ── -->
        <div
            v-if="showRecurringModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs"
        >
            <Card
                class="w-full max-w-lg animate-in border-border bg-card shadow-2xl duration-200 fade-in zoom-in"
            >
                <CardHeader class="border-b pb-3">
                    <CardTitle
                        class="flex items-center gap-1.5 text-sm font-bold"
                    >
                        <Clock class="size-5 text-indigo-600" />
                        {{
                            editingRecurring
                                ? 'Cập Nhật Cấu Hình Định Kỳ'
                                : 'Tạo Mới Cấu Hình Chi Phí Định Kỳ'
                        }}
                    </CardTitle>
                    <CardDescription class="text-xs"
                        >Định nghĩa khoản tiền cố định sẽ tự động sinh hóa đơn
                        OPEX theo chu kỳ.</CardDescription
                    >
                </CardHeader>
                <form @submit.prevent="saveRecurring">
                    <CardContent class="space-y-4 p-5 text-xs">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <!-- Name -->
                            <div class="space-y-1.5">
                                <Label
                                    for="recurring-name"
                                    class="text-xs font-bold text-slate-500"
                                    >Tên chi phí (ví dụ: Thuê nhà mặt
                                    bằng):</Label
                                >
                                <Input
                                    id="recurring-name"
                                    v-model="recurringForm.name"
                                    type="text"
                                    placeholder="Tiền thuê mặt bằng..."
                                    class="w-full text-xs"
                                />
                            </div>

                            <!-- Category -->
                            <div class="space-y-1.5">
                                <Label
                                    for="recurring-cat"
                                    class="text-xs font-bold text-slate-500"
                                    >Danh mục chi phí:</Label
                                >
                                <select
                                    id="recurring-cat"
                                    v-model="recurringForm.category_id"
                                    class="w-full rounded-lg border border-border bg-background px-3 py-2.5 text-xs font-semibold outline-hidden focus:ring-2 focus:ring-indigo-500/25"
                                >
                                    <option
                                        v-for="c in categories"
                                        :key="c.id"
                                        :value="c.id"
                                    >
                                        {{ c.name }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <!-- Amount -->
                            <div class="space-y-1.5">
                                <Label
                                    for="recurring-amount"
                                    class="text-xs font-bold text-slate-500"
                                    >Số tiền mỗi chu kỳ (VND):</Label
                                >
                                <Input
                                    id="recurring-amount"
                                    v-model.number="recurringForm.amount"
                                    type="number"
                                    class="w-full text-xs"
                                />
                            </div>

                            <!-- Frequency -->
                            <div class="space-y-1.5">
                                <Label
                                    for="recurring-freq"
                                    class="text-xs font-bold text-slate-500"
                                    >Chu kỳ lập lại:</Label
                                >
                                <select
                                    id="recurring-freq"
                                    v-model="recurringForm.frequency"
                                    class="w-full rounded-lg border border-border bg-background px-3 py-2.5 text-xs font-semibold outline-hidden focus:ring-2 focus:ring-indigo-500/25"
                                >
                                    <option value="weekly">Hàng tuần</option>
                                    <option value="monthly">
                                        Hàng tháng (Thuê mặt bằng, bảo hiểm)
                                    </option>
                                    <option value="quarterly">Hàng quý</option>
                                    <option value="yearly">Hàng năm</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <!-- Start Date -->
                            <div class="space-y-1.5">
                                <Label
                                    for="recurring-start"
                                    class="text-xs font-bold text-slate-500"
                                    >Ngày bắt đầu áp dụng:</Label
                                >
                                <Input
                                    id="recurring-start"
                                    v-model="recurringForm.start_date"
                                    type="date"
                                    class="w-full text-xs"
                                />
                            </div>

                            <!-- End Date -->
                            <div class="space-y-1.5">
                                <Label
                                    for="recurring-end"
                                    class="text-xs font-bold text-slate-500"
                                    >Ngày kết thúc (Tùy chọn):</Label
                                >
                                <Input
                                    id="recurring-end"
                                    v-model="recurringForm.end_date"
                                    type="date"
                                    class="w-full text-xs"
                                />
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="space-y-1.5">
                            <Label
                                for="recurring-desc"
                                class="text-xs font-bold text-slate-500"
                                >Mô tả chi tiết:</Label
                            >
                            <textarea
                                id="recurring-desc"
                                v-model="recurringForm.description"
                                rows="3"
                                placeholder="Ghi chú chi tiết cho hoạt động tự động phát sinh này..."
                                class="w-full rounded-lg border border-border bg-background px-3 py-2 text-xs font-semibold outline-hidden focus:ring-2 focus:ring-indigo-500/25"
                            ></textarea>
                        </div>
                    </CardContent>
                    <div
                        class="flex justify-end gap-2 border-t bg-slate-50/30 p-4 dark:bg-slate-900/10"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            @click="showRecurringModal = false"
                            class="h-9 text-xs font-semibold"
                            >Hủy</Button
                        >
                        <Button
                            type="submit"
                            class="flex h-9 items-center gap-1.5 bg-indigo-600 text-xs font-bold text-white hover:bg-indigo-700"
                            :disabled="recurringForm.processing"
                        >
                            <span
                                v-if="recurringForm.processing"
                                class="mr-1 size-3 animate-spin rounded-full border-2 border-white border-t-transparent"
                            ></span>
                            {{ editingRecurring ? 'Lưu cập nhật' : 'Tạo mới' }}
                        </Button>
                    </div>
                </form>
            </Card>
        </div>

        <!-- ── MODAL: CREATE CUSTOM CATEGORY ── -->
        <div
            v-if="showCategoryModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs"
        >
            <Card
                class="w-full max-w-md animate-in border-border bg-card shadow-2xl duration-200 fade-in zoom-in"
            >
                <CardHeader class="border-b pb-3">
                    <CardTitle
                        class="flex items-center gap-1.5 text-sm font-bold"
                    >
                        <Layers class="size-5 text-slate-800" />
                        Thêm Danh Mục Chi Phí Mới
                    </CardTitle>
                    <CardDescription class="text-xs"
                        >Tạo danh mục chi phí riêng biệt của cửa hàng phục vụ
                        phân nhóm.</CardDescription
                    >
                </CardHeader>
                <form @submit.prevent="saveCategory">
                    <CardContent class="space-y-4 p-5 text-xs">
                        <!-- Name -->
                        <div class="space-y-1.5">
                            <Label
                                for="cat-name"
                                class="text-xs font-bold text-slate-500"
                                >Tên danh mục (ví dụ: Phí ship, Tiếp
                                khách...):</Label
                            >
                            <Input
                                id="cat-name"
                                v-model="categoryForm.name"
                                type="text"
                                placeholder="Nhập tên..."
                                class="w-full text-xs"
                            />
                        </div>

                        <!-- Description -->
                        <div class="space-y-1.5">
                            <Label
                                for="cat-desc"
                                class="text-xs font-bold text-slate-500"
                                >Mô tả danh mục:</Label
                            >
                            <Input
                                id="cat-desc"
                                v-model="categoryForm.description"
                                type="text"
                                placeholder="Nhập mô tả ngắn..."
                                class="w-full text-xs"
                            />
                        </div>
                    </CardContent>
                    <div
                        class="flex justify-end gap-2 border-t bg-slate-50/30 p-4 dark:bg-slate-900/10"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            @click="showCategoryModal = false"
                            class="h-9 text-xs font-semibold"
                            >Hủy</Button
                        >
                        <Button
                            type="submit"
                            class="h-9 bg-slate-800 text-xs font-bold text-white hover:bg-slate-900"
                            :disabled="categoryForm.processing"
                        >
                            Thêm danh mục
                        </Button>
                    </div>
                </form>
            </Card>
        </div>

        <!-- ── MODAL: DOCUMENT PREVIEW ── -->
        <div
            v-if="invoicePreviewUrl"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4 backdrop-blur-xs"
        >
            <Card
                class="flex h-[90vh] w-full max-w-4xl flex-col overflow-hidden border-border bg-card shadow-2xl"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between border-b pb-3"
                >
                    <div>
                        <CardTitle
                            class="flex items-center gap-1.5 text-sm font-bold"
                        >
                            <FileText class="size-5 text-amber-600" />
                            Xem Hóa Đơn Chứng Từ
                        </CardTitle>
                        <CardDescription class="text-xs"
                            >Chứng từ đính kèm cho giao dịch.</CardDescription
                        >
                    </div>
                    <button
                        @click="invoicePreviewUrl = null"
                        class="cursor-pointer rounded-lg p-1 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800"
                    >
                        <X class="size-5" />
                    </button>
                </CardHeader>
                <div
                    class="relative flex-1 overflow-hidden bg-slate-100 dark:bg-slate-900"
                >
                    <!-- PDF Embed -->
                    <embed
                        v-if="invoicePreviewUrl.toLowerCase().endsWith('.pdf')"
                        :src="invoicePreviewUrl"
                        type="application/pdf"
                        class="h-full w-full"
                    />
                    <!-- Image Preview -->
                    <div
                        v-else
                        class="flex h-full w-full items-center justify-center p-4"
                    >
                        <img
                            :src="invoicePreviewUrl"
                            alt="Hóa đơn chứng từ"
                            class="max-h-full max-w-full rounded-lg border object-contain shadow-md"
                        />
                    </div>
                </div>
                <div
                    class="flex justify-end gap-2 border-t bg-slate-50 p-4 dark:bg-slate-950"
                >
                    <a
                        :href="invoicePreviewUrl"
                        download
                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-border bg-background px-4 py-2 text-xs font-semibold transition hover:bg-muted"
                    >
                        <Download class="size-3.5" />
                        Tải xuống file
                    </a>
                    <Button
                        @click="invoicePreviewUrl = null"
                        class="text-xs font-semibold"
                        >Đóng</Button
                    >
                </div>
            </Card>
        </div>
    </div>
</template>
