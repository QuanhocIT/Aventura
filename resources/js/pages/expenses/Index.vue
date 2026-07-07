<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
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
    Check
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
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
    category_breakdown: { id: number | null; name: string; amount: number; percentage: number }[];
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
    };
}>();

// --- Active Tab State ---
const activeTab = ref<'analytics' | 'expenses' | 'recurring' | 'categories'>('analytics');

// --- VND Formatter Helper ---
const vnd = (v: number) =>
    new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(v);

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
    router.get('/expenses', {
        ...filterForm.value
    }, {
        preserveState: true,
        preserveScroll: true
    });
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
    invoice: null as File | null
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
            invoice: expenseForm.invoice
        });

        updateForm.post(`/expenses/${editingExpense.value.id}`, {
            onSuccess: () => {
                showExpenseModal.value = false;
                toast.success('Đã cập nhật chi phí thành công!');
            },
            onError: (err: any) => {
                toast.error(Object.values(err)[0] as string || 'Đã có lỗi xảy ra');
            }
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
                toast.error(Object.values(err)[0] as string || 'Đã có lỗi xảy ra');
            }
        });
    }
}

async function deleteExpense(expense: OperatingExpense) {
    if ((await confirmDialog({ title: 'Xác nhận thao tác', description: 'Bạn có chắc chắn muốn xóa khoản chi phí này không?' }))) {
        router.delete(`/expenses/${expense.id}`, {
            onSuccess: () => toast.success('Đã xóa khoản chi phí thành công.'),
            onError: () => toast.error('Có lỗi xảy ra khi xóa.')
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
        recurringForm.patch(`/expenses/recurring/${editingRecurring.value.id}`, {
            onSuccess: () => {
                showRecurringModal.value = false;
                toast.success('Đã cập nhật chi phí định kỳ.');
            },
            onError: (err: any) => {
                toast.error(Object.values(err)[0] as string || 'Có lỗi xảy ra');
            }
        });
    } else {
        recurringForm.post('/expenses/recurring', {
            onSuccess: () => {
                showRecurringModal.value = false;
                recurringForm.reset();
                toast.success('Đã thêm cấu hình chi phí định kỳ mới.');
            },
            onError: (err: any) => {
                toast.error(Object.values(err)[0] as string || 'Có lỗi xảy ra');
            }
        });
    }
}

function toggleRecurringStatus(rec: RecurringExpense) {
    router.patch(`/expenses/recurring/${rec.id}`, {
        is_active: !rec.is_active
    }, {
        preserveScroll: true,
        onSuccess: () => toast.success('Đã thay đổi trạng thái chi phí định kỳ.')
    });
}

async function deleteRecurring(rec: RecurringExpense) {
    if ((await confirmDialog({ title: 'Xác nhận thao tác', description: 'Bạn có chắc chắn muốn xóa cấu hình chi phí định kỳ này? Lịch sử chi phí đã sinh ra vẫn được lưu giữ.' }))) {
        router.delete(`/expenses/recurring/${rec.id}`, {
            onSuccess: () => toast.success('Đã xóa cấu hình chi phí định kỳ thành công.'),
            onError: () => toast.error('Có lỗi xảy ra khi xóa.')
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
            toast.error(Object.values(err)[0] as string || 'Có lỗi xảy ra');
        }
    });
}

async function deleteCategory(cat: Category) {
    if ((await confirmDialog({ title: 'Xác nhận thao tác', description: `Bạn có chắc chắn muốn xóa danh mục "${cat.name}"? Các chi phí liên kết sẽ bị xóa hoặc gán thành "Chưa phân loại".` }))) {
        router.delete(`/expenses/categories/${cat.id}`, {
            onSuccess: () => toast.success('Đã xóa danh mục thành công.'),
            onError: () => toast.error('Có lỗi xảy ra khi xóa.')
        });
    }
}

// --- Chart Max Value Helper ---
const chartMaxVal = computed(() => {
    const max = Math.max(...props.analytics.six_months_mom.map(m => m.amount), 0);

    return max || 1;
});
</script>

<template>
    <Head title="Quản Lý Chi Phí Vận Hành (OPEX)" />

    <div class="flex flex-col gap-6 p-4 lg:p-6 max-w-7xl mx-auto w-full">
        <!-- Page Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b pb-5 border-border">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 shadow-xs">
                    <Receipt class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-black tracking-tight text-slate-800 dark:text-slate-100">Quản Lý Chi Phí Vận Hành (OPEX)</h1>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Theo dõi, kiểm soát chi phí thực tế (mặt bằng, điện nước, marketing) và đồng bộ vào báo cáo Lãi/Lỗ của nhà hàng.</p>
                </div>
            </div>

            <!-- Page Action Buttons based on active tab -->
            <div class="flex items-center gap-2">
                <Button 
                    v-if="activeTab === 'expenses'"
                    @click="openNewExpenseModal"
                    class="bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs h-9"
                >
                    <PlusCircle class="size-4 mr-1.5" />
                    Ghi nhận chi phí
                </Button>
                <Button 
                    v-if="activeTab === 'recurring'"
                    @click="openNewRecurringModal"
                    class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs h-9"
                >
                    <PlusCircle class="size-4 mr-1.5" />
                    Tạo chi phí định kỳ
                </Button>
                <Button 
                    v-slot:default
                    v-if="activeTab === 'categories'"
                    @click="showCategoryModal = true"
                    class="bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs h-9 dark:bg-slate-700 dark:hover:bg-slate-600"
                >
                    <PlusCircle class="size-4 mr-1.5" />
                    Thêm danh mục
                </Button>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="flex gap-2 border-b pb-1 overflow-x-auto shrink-0 border-border">
            <button 
                @click="activeTab = 'analytics'"
                :class="[
                    'px-4 py-2.5 text-xs font-bold transition-all border-b-2 whitespace-nowrap',
                    activeTab === 'analytics' 
                        ? 'border-amber-500 text-amber-600 dark:text-amber-400' 
                        : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'
                ]"
            >
                <span class="flex items-center gap-1.5"><LayoutDashboard class="size-3.5" /> Phân tích & So sánh</span>
            </button>
            <button 
                @click="activeTab = 'expenses'"
                :class="[
                    'px-4 py-2.5 text-xs font-bold transition-all border-b-2 whitespace-nowrap',
                    activeTab === 'expenses' 
                        ? 'border-amber-500 text-amber-600 dark:text-amber-400' 
                        : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'
                ]"
            >
                <span class="flex items-center gap-1.5"><Receipt class="size-3.5" /> Danh sách chi phí phát sinh</span>
            </button>
            <button 
                @click="activeTab = 'recurring'"
                :class="[
                    'px-4 py-2.5 text-xs font-bold transition-all border-b-2 whitespace-nowrap',
                    activeTab === 'recurring' 
                        ? 'border-amber-500 text-amber-600 dark:text-amber-400' 
                        : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'
                ]"
            >
                <span class="flex items-center gap-1.5"><Clock class="size-3.5" /> Chi phí định kỳ tự động</span>
            </button>
            <button 
                @click="activeTab = 'categories'"
                :class="[
                    'px-4 py-2.5 text-xs font-bold transition-all border-b-2 whitespace-nowrap',
                    activeTab === 'categories' 
                        ? 'border-amber-500 text-amber-600 dark:text-amber-400' 
                        : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'
                ]"
            >
                <span class="flex items-center gap-1.5"><Layers class="size-3.5" /> Danh mục chi phí</span>
            </button>
        </div>

        <!-- ── TAB 1: ANALYTICS ── -->
        <div v-if="activeTab === 'analytics'" class="space-y-6">
            <!-- Metric Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Card 1: Total OPEX this month -->
                <Card class="shadow-xs border-border bg-card">
                    <CardHeader class="pb-2">
                        <CardDescription class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Tổng chi phí vận hành tháng này</CardDescription>
                    </CardHeader>
                    <CardContent class="pb-5">
                        <p class="text-3xl font-black text-amber-600 dark:text-amber-400 font-mono">{{ vnd(analytics.total_this_month) }}</p>
                        <p class="text-[10px] text-slate-400 mt-2 flex items-center gap-1">
                            <Clock class="size-3" />
                            Tổng cộng các khoản chi thực tế phát sinh từ đầu tháng
                        </p>
                    </CardContent>
                </Card>

                <!-- Card 2: MoM Change -->
                <Card class="shadow-xs border-border bg-card">
                    <CardHeader class="pb-2">
                        <CardDescription class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Biến động so với tháng trước (MoM)</CardDescription>
                    </CardHeader>
                    <CardContent class="pb-5">
                        <div class="flex items-center gap-2">
                            <div 
                                :class="[
                                    'h-8 w-8 rounded-full flex items-center justify-center border',
                                    analytics.mom_delta > 0 
                                        ? 'bg-rose-50 border-rose-100 text-rose-600 dark:bg-rose-950/20 dark:border-rose-900/30' 
                                        : analytics.mom_delta < 0
                                            ? 'bg-emerald-50 border-emerald-100 text-emerald-600 dark:bg-emerald-950/20 dark:border-emerald-900/30'
                                            : 'bg-slate-50 border-slate-100 text-slate-500'
                                ]"
                            >
                                <component :is="analytics.mom_delta >= 0 ? TrendingUp : TrendingDown" class="size-4" />
                            </div>
                            <span 
                                :class="[
                                    'text-2xl font-black font-mono',
                                    analytics.mom_delta > 0 ? 'text-rose-600' : analytics.mom_delta < 0 ? 'text-emerald-600' : 'text-slate-600'
                                ]"
                            >
                                {{ analytics.mom_delta >= 0 ? '+' : '' }}{{ analytics.mom_delta }}%
                            </span>
                        </div>
                        <p class="text-[10px] text-slate-400 mt-2">Tháng trước: <span class="font-bold text-slate-600 dark:text-slate-350">{{ vnd(analytics.total_last_month) }}</span></p>
                    </CardContent>
                </Card>

                <!-- Card 3: Recurring share -->
                <Card class="shadow-xs border-border bg-card">
                    <CardHeader class="pb-2">
                        <CardDescription class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Tỷ lệ chi phí cố định (Định kỳ)</CardDescription>
                    </CardHeader>
                    <CardContent class="pb-5">
                        <p class="text-3xl font-black text-indigo-600 dark:text-indigo-400 font-mono">{{ analytics.recurring_ratio }}%</p>
                        <p class="text-[10px] text-slate-400 mt-2">Tỷ trọng các khoản chi tự động (mặt bằng, phần mềm) trong tổng chi phí</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Charts and Breakdown -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Chart card -->
                <Card class="lg:col-span-2 shadow-xs border-border">
                    <CardHeader class="pb-3 border-b bg-slate-50/40 dark:bg-slate-900/10">
                        <CardTitle class="text-sm font-bold">Biến động chi phí vận hành (6 tháng qua)</CardTitle>
                        <CardDescription class="text-xs">Theo dõi lượng tiền chi tiêu vận hành nhà hàng Month-over-Month.</CardDescription>
                    </CardHeader>
                    <CardContent class="p-6">
                        <div class="space-y-4">
                            <!-- Visual Bars -->
                            <div class="flex items-end justify-between h-52 border-b border-border pb-2 px-4 overflow-x-auto gap-6">
                                <div 
                                    v-for="d in analytics.six_months_mom" 
                                    :key="d.month"
                                    class="flex flex-col items-center gap-2 min-w-[50px] w-full group relative"
                                >
                                    <!-- Amount value on top of bar -->
                                    <span class="text-[9px] font-bold font-mono text-slate-600 opacity-0 group-hover:opacity-100 transition-opacity absolute bottom-full mb-1 bg-slate-800 text-white rounded px-1.5 py-0.5 pointer-events-none whitespace-nowrap z-10">
                                        {{ vnd(d.amount) }}
                                    </span>

                                    <!-- Bar wrapper -->
                                    <div class="flex items-end h-40 w-full justify-center">
                                        <div 
                                            class="w-8 bg-amber-500/80 group-hover:bg-amber-500 rounded-t-sm transition-all duration-300 shadow-xs"
                                            :style="`height: ${Math.max(4, (d.amount / chartMaxVal) * 140)}px`"
                                        />
                                    </div>

                                    <!-- X Label -->
                                    <span class="text-[10px] font-bold text-slate-500 whitespace-nowrap font-mono">{{ d.month }}</span>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Category Breakdown list -->
                <Card class="lg:col-span-1 shadow-xs border-border">
                    <CardHeader class="pb-3 border-b bg-slate-50/40 dark:bg-slate-900/10">
                        <CardTitle class="text-sm font-bold">Cơ cấu chi phí theo danh mục</CardTitle>
                        <CardDescription class="text-xs">Phân bổ dòng tiền chi phí trong tháng này.</CardDescription>
                    </CardHeader>
                    <CardContent class="p-5 space-y-4">
                        <div v-if="analytics.category_breakdown.length === 0" class="text-center py-10 text-xs text-slate-400">
                            Chưa phát sinh giao dịch chi phí nào trong tháng này.
                        </div>
                        <div v-else class="space-y-4 max-h-[300px] overflow-y-auto pr-1">
                            <div 
                                v-for="c in analytics.category_breakdown" 
                                :key="c.name" 
                                class="space-y-1.5"
                            >
                                <div class="flex justify-between items-center text-xs font-bold">
                                    <span class="text-slate-700 dark:text-slate-200">{{ c.name }}</span>
                                    <span class="text-slate-900 dark:text-slate-100 font-mono">{{ vnd(c.amount) }} <span class="text-[10px] text-slate-400 font-normal">({{ c.percentage }}%)</span></span>
                                </div>
                                <!-- Progress bar -->
                                <div class="h-2 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                                    <div class="h-full bg-amber-500 transition-all rounded-full" :style="`width: ${c.percentage}%`" />
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- ── TAB 2: EXPENSES LIST ── -->
        <div v-if="activeTab === 'expenses'" class="space-y-6">
            <!-- Filters Bar -->
            <Card class="shadow-xs border-border">
                <CardContent class="p-4 flex flex-col md:flex-row items-end gap-3 text-xs">
                    <!-- Category filter -->
                    <div class="space-y-1.5 w-full md:w-1/4">
                        <Label class="text-[11px] font-bold text-slate-400">Danh mục:</Label>
                        <select 
                            v-model="filterForm.category_id"
                            class="w-full text-xs font-semibold rounded-lg border border-border bg-background px-3 py-2 outline-hidden focus:ring-2 focus:ring-amber-500/25"
                        >
                            <option value="">-- Tất cả danh mục --</option>
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>

                    <!-- Start date -->
                    <div class="space-y-1.5 w-full md:w-1/4">
                        <Label class="text-[11px] font-bold text-slate-400">Từ ngày:</Label>
                        <Input 
                            v-model="filterForm.start_date"
                            type="date"
                            class="w-full text-xs h-8"
                        />
                    </div>

                    <!-- End date -->
                    <div class="space-y-1.5 w-full md:w-1/4">
                        <Label class="text-[11px] font-bold text-slate-400">Đến ngày:</Label>
                        <Input 
                            v-model="filterForm.end_date"
                            type="date"
                            class="w-full text-xs h-8"
                        />
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center gap-2 shrink-0 w-full md:w-auto">
                        <Button 
                            @click="applyFilters"
                            class="bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs h-8 px-4"
                        >
                            <ListFilter class="size-3.5 mr-1" /> Lọc
                        </Button>
                        <Button 
                            @click="resetFilters"
                            variant="outline"
                            class="text-xs h-8 px-4 font-semibold"
                        >
                            Bỏ lọc
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- Table Card -->
            <Card class="shadow-xs overflow-hidden border-border">
                <CardHeader class="pb-3 border-b bg-slate-50/40 dark:bg-slate-900/10">
                    <CardTitle class="text-sm font-bold">Chi phí phát sinh thực tế</CardTitle>
                    <CardDescription class="text-xs">Danh sách các hóa đơn chi phí đã được ghi nhận trong hệ thống.</CardDescription>
                </CardHeader>
                <CardContent class="p-0 overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50/20 dark:bg-slate-900/5 border-b font-bold text-slate-500">
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
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-600 dark:text-slate-350">
                            <tr v-if="expenses.data.length === 0">
                                <td colspan="8" class="p-12 text-center text-slate-400 font-bold">Không tìm thấy bản ghi chi phí nào.</td>
                            </tr>
                            <tr 
                                v-for="e in expenses.data" 
                                :key="e.id"
                                class="hover:bg-slate-50/40 dark:hover:bg-slate-900/20 transition-colors"
                            >
                                <td class="p-3 pl-5 font-bold font-mono">{{ e.expense_date }}</td>
                                <td class="p-3 font-semibold text-slate-700 dark:text-slate-300">
                                    {{ e.category ? e.category.name : 'Chưa phân loại' }}
                                </td>
                                <td class="p-3 max-w-xs truncate" :title="e.description || ''">
                                    {{ e.description || '—' }}
                                </td>
                                <td class="p-3 text-right font-bold text-slate-800 dark:text-slate-200 font-mono">
                                    {{ vnd(e.amount) }}
                                </td>
                                <td class="p-3 text-center">
                                    <span 
                                        v-if="e.recurring_expense_id"
                                        class="px-2 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider bg-indigo-50 text-indigo-600 dark:bg-indigo-950/30 dark:text-indigo-400"
                                    >
                                        Định kỳ
                                    </span>
                                    <span 
                                        v-else
                                        class="px-2 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider bg-amber-50 text-amber-700 dark:bg-amber-950/20 dark:text-amber-400"
                                    >
                                        Thủ công
                                    </span>
                                </td>
                                <td class="p-3 text-center">
                                    <button 
                                        v-if="e.invoice_path"
                                        @click="invoicePreviewUrl = e.invoice_path"
                                        class="text-amber-600 hover:text-amber-700 font-bold inline-flex items-center gap-1 hover:underline cursor-pointer"
                                    >
                                        <FileText class="size-3.5" /> Xem
                                    </button>
                                    <span v-else class="text-slate-400">—</span>
                                </td>
                                <td class="p-3 text-center text-slate-400">
                                    {{ e.creator ? e.creator.name : 'Hệ thống' }}
                                </td>
                                <td class="p-3 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <button 
                                            @click="openEditExpenseModal(e)"
                                            class="p-1 rounded-sm text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-700 dark:hover:text-slate-350 cursor-pointer"
                                            title="Sửa chi phí"
                                        >
                                            <Edit2 class="size-3.5" />
                                        </button>
                                        <button 
                                            @click="deleteExpense(e)"
                                            class="p-1 rounded-sm text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/25 hover:text-rose-600 cursor-pointer"
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
                <div v-if="expenses.last_page > 1" class="border-t px-5 py-3.5 flex justify-between items-center bg-slate-50/30 dark:bg-slate-900/5">
                    <span class="text-[11px] font-bold text-slate-400">Trang {{ expenses.current_page }} / {{ expenses.last_page }} · Tổng {{ expenses.total }} dòng</span>
                    
                    <div class="flex gap-1">
                        <Link 
                            v-for="link in expenses.links" 
                            :key="link.label"
                            :href="link.url || '#'"
                            :class="[
                                'px-2.5 py-1 text-[11px] font-bold border rounded-lg transition-all',
                                link.active 
                                    ? 'bg-amber-600 border-amber-600 text-white' 
                                    : 'bg-background hover:bg-muted text-slate-600 dark:text-slate-350',
                                !link.url ? 'opacity-40 pointer-events-none' : ''
                            ]"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </Card>
        </div>

        <!-- ── TAB 3: RECURRING EXPENSES ── -->
        <div v-if="activeTab === 'recurring'" class="space-y-6">
            <Card class="shadow-xs overflow-hidden border-border">
                <CardHeader class="pb-3 border-b bg-slate-50/40 dark:bg-slate-900/10">
                    <CardTitle class="text-sm font-bold">Cấu hình chi phí định kỳ cố định</CardTitle>
                    <CardDescription class="text-xs">Thiết lập các chi phí cố định (tiền thuê nhà, bảo hiểm, phí dịch vụ...) tự động ghi nhận theo định kỳ.</CardDescription>
                </CardHeader>
                <CardContent class="p-0 overflow-x-auto">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50/20 dark:bg-slate-900/5 border-b font-bold text-slate-500">
                                <th class="p-3 pl-5">Tên chi phí</th>
                                <th class="p-3">Danh mục</th>
                                <th class="p-3 text-center">Chu kỳ</th>
                                <th class="p-3 text-right">Số tiền</th>
                                <th class="p-3 text-center">Ngày bắt đầu</th>
                                <th class="p-3 text-center">Ngày kết thúc</th>
                                <th class="p-3 text-center">Ngày chạy gần nhất</th>
                                <th class="p-3 text-center">Kích hoạt</th>
                                <th class="p-3 text-center">Hành động</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-600 dark:text-slate-350">
                            <tr v-if="recurringExpenses.length === 0">
                                <td colspan="9" class="p-12 text-center text-slate-400 font-bold">Chưa tạo chi phí định kỳ nào.</td>
                            </tr>
                            <tr 
                                v-for="r in recurringExpenses" 
                                :key="r.id"
                                class="hover:bg-slate-50/40 dark:hover:bg-slate-900/20 transition-colors"
                            >
                                <td class="p-3 pl-5 font-bold text-slate-800 dark:text-slate-200">{{ r.name }}</td>
                                <td class="p-3 font-semibold">{{ r.category ? r.category.name : '—' }}</td>
                                <td class="p-3 text-center font-bold text-slate-500">
                                    <span v-if="r.frequency === 'weekly'">Hàng tuần</span>
                                    <span v-else-if="r.frequency === 'monthly'">Hàng tháng</span>
                                    <span v-else-if="r.frequency === 'quarterly'">Hàng quý</span>
                                    <span v-else-if="r.frequency === 'yearly'">Hàng năm</span>
                                </td>
                                <td class="p-3 text-right font-black font-mono text-slate-700 dark:text-slate-250">
                                    {{ vnd(r.amount) }}
                                </td>
                                <td class="p-3 text-center font-mono text-slate-500">{{ r.start_date }}</td>
                                <td class="p-3 text-center font-mono text-slate-500">{{ r.end_date || 'Vô thời hạn' }}</td>
                                <td class="p-3 text-center font-mono text-slate-400">{{ r.last_triggered_at || 'Chưa chạy' }}</td>
                                <td class="p-3 text-center">
                                    <!-- Switch / Toggle Status -->
                                    <button 
                                        @click="toggleRecurringStatus(r)"
                                        :class="[
                                            'w-10 h-5 rounded-full p-0.5 transition-all outline-hidden cursor-pointer relative',
                                            r.is_active ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-700'
                                        ]"
                                    >
                                        <span 
                                            :class="[
                                                'w-4 h-4 rounded-full bg-white shadow-xs block transition-all',
                                                r.is_active ? 'translate-x-5' : 'translate-x-0'
                                            ]"
                                        />
                                    </button>
                                </td>
                                <td class="p-3 text-center">
                                    <div class="flex items-center justify-center gap-1.5">
                                        <button 
                                            @click="openEditRecurringModal(r)"
                                            class="p-1 rounded-sm text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-700 cursor-pointer"
                                            title="Sửa"
                                        >
                                            <Edit2 class="size-3.5" />
                                        </button>
                                        <button 
                                            @click="deleteRecurring(r)"
                                            class="p-1 rounded-sm text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/25 hover:text-rose-600 cursor-pointer"
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

        <!-- ── TAB 4: EXPENSE CATEGORIES ── -->
        <div v-if="activeTab === 'categories'" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Loop over all categories -->
                <Card 
                    v-for="c in categories" 
                    :key="c.id"
                    class="shadow-xs border-border overflow-hidden"
                >
                    <CardHeader class="pb-2 border-b bg-slate-50/30 dark:bg-slate-900/5 flex flex-row justify-between items-start">
                        <div>
                            <CardTitle class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ c.name }}</CardTitle>
                            <CardDescription class="text-[10px] mt-1 font-bold">
                                <span 
                                    v-if="c.restaurant_id === null"
                                    class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-500 dark:bg-slate-850 dark:text-slate-400 font-bold"
                                >
                                    Hệ thống dùng chung
                                </span>
                                <span 
                                    v-else
                                    class="px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 dark:bg-amber-950/25 dark:text-amber-400 font-bold"
                                >
                                    Tùy chỉnh riêng
                                </span>
                            </CardDescription>
                        </div>

                        <!-- Trash icon for custom category -->
                        <button 
                            v-if="c.restaurant_id !== null"
                            @click="deleteCategory(c)"
                            class="p-1 text-rose-500 hover:bg-rose-50 dark:hover:bg-rose-950/30 rounded-sm cursor-pointer transition-colors"
                            title="Xóa danh mục"
                        >
                            <Trash2 class="size-4" />
                        </button>
                    </CardHeader>
                    <CardContent class="pt-3 text-xs text-slate-500 dark:text-slate-400 leading-relaxed min-h-[50px]">
                        {{ c.description || 'Chưa có mô tả chi tiết cho danh mục này.' }}
                    </CardContent>
                </Card>
            </div>
        </div>

        <!-- ── MODAL: CREATE/EDIT OPERATING EXPENSE ── -->
        <div v-if="showExpenseModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
            <Card class="w-full max-w-lg shadow-2xl animate-in fade-in zoom-in duration-200 border-border bg-card">
                <CardHeader class="pb-3 border-b">
                    <CardTitle class="text-sm font-bold flex items-center gap-1.5">
                        <Receipt class="size-5 text-amber-600" />
                        {{ editingExpense ? 'Sửa Chi Phí Vận Hành' : 'Ghi Nhận Khoản Chi Phí Vận Hành' }}
                    </CardTitle>
                    <CardDescription class="text-xs">Nhập hóa đơn chi phí (không bao gồm nguyên vật liệu COGS và lương nhân viên).</CardDescription>
                </CardHeader>
                <form @submit.prevent="saveExpense">
                    <CardContent class="p-5 space-y-4 text-xs">
                        <!-- Category field -->
                        <div class="space-y-1.5">
                            <Label for="expense-cat" class="text-xs font-bold text-slate-500">Danh mục chi phí:</Label>
                            <select 
                                id="expense-cat"
                                v-model="expenseForm.category_id"
                                class="w-full text-xs font-semibold rounded-lg border border-border bg-background px-3 py-2.5 outline-hidden focus:ring-2 focus:ring-amber-500/25"
                            >
                                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Amount -->
                            <div class="space-y-1.5">
                                <Label for="expense-amount" class="text-xs font-bold text-slate-500">Số tiền chi tiêu (VND):</Label>
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
                                <Label for="expense-date" class="text-xs font-bold text-slate-500">Ngày ghi nhận:</Label>
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
                            <Label for="expense-desc" class="text-xs font-bold text-slate-500">Ghi chú chi tiết:</Label>
                            <textarea 
                                id="expense-desc"
                                v-model="expenseForm.description"
                                rows="3"
                                placeholder="Mô tả lý do chi, thông tin nhà cung cấp dịch vụ..."
                                class="w-full text-xs font-semibold rounded-lg border border-border bg-background px-3 py-2 outline-hidden focus:ring-2 focus:ring-amber-500/25"
                            ></textarea>
                        </div>

                        <!-- Invoice Proof Upload -->
                        <div class="space-y-2">
                            <Label class="text-xs font-bold text-slate-500 block">Tải lên bill / hóa đơn chứng minh (Ảnh hoặc PDF):</Label>
                            <div class="flex items-center gap-3">
                                <label 
                                    class="inline-flex cursor-pointer items-center justify-center gap-1.5 rounded-lg border border-dashed border-amber-300 dark:border-amber-900/50 hover:bg-amber-50/20 px-4 py-3 text-xs font-bold text-amber-600 transition-all select-none w-full text-center"
                                >
                                    <FileUp class="size-4" />
                                    {{ expenseForm.invoice ? 'Đã chọn: ' + expenseForm.invoice.name : 'Chọn File/Chụp Ảnh Hóa Đơn...' }}
                                    <input 
                                        type="file" 
                                        accept="image/*,application/pdf"
                                        class="hidden" 
                                        @change="handleExpenseFileChange"
                                    />
                                </label>
                            </div>
                            <p class="text-[10px] text-slate-400 mt-1">Dung lượng tối đa 5MB. Định dạng: JPG, PNG, WEBP hoặc PDF.</p>
                            <div v-if="editingExpense && editingExpense.invoice_path && !expenseForm.invoice" class="text-[10px] text-slate-500 flex items-center gap-1 bg-muted p-2 rounded-lg">
                                <FileText class="size-3.5 text-amber-600" />
                                Hóa đơn hiện có: 
                                <a :href="editingExpense.invoice_path" target="_blank" class="text-amber-600 font-bold hover:underline">Xem hóa đơn hiện tại</a>
                            </div>
                        </div>
                    </CardContent>
                    <div class="p-4 border-t flex justify-end gap-2 bg-slate-50/30 dark:bg-slate-900/10">
                        <Button type="button" variant="outline" @click="showExpenseModal = false" class="text-xs h-9 font-semibold">Hủy</Button>
                        <Button 
                            type="submit" 
                            class="bg-amber-600 hover:bg-amber-700 text-white font-bold text-xs h-9 flex items-center gap-1.5"
                            :disabled="expenseForm.processing"
                        >
                            <span v-if="expenseForm.processing" class="size-3 border-2 border-t-transparent border-white rounded-full animate-spin mr-1"></span>
                            {{ editingExpense ? 'Lưu cập nhật' : 'Ghi nhận chi phí' }}
                        </Button>
                    </div>
                </form>
            </Card>
        </div>

        <!-- ── MODAL: CREATE/EDIT RECURRING EXPENSE ── -->
        <div v-if="showRecurringModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
            <Card class="w-full max-w-lg shadow-2xl animate-in fade-in zoom-in duration-200 border-border bg-card">
                <CardHeader class="pb-3 border-b">
                    <CardTitle class="text-sm font-bold flex items-center gap-1.5">
                        <Clock class="size-5 text-indigo-600" />
                        {{ editingRecurring ? 'Cập Nhật Cấu Hình Định Kỳ' : 'Tạo Mới Cấu Hình Chi Phí Định Kỳ' }}
                    </CardTitle>
                    <CardDescription class="text-xs">Định nghĩa khoản tiền cố định sẽ tự động sinh hóa đơn OPEX theo chu kỳ.</CardDescription>
                </CardHeader>
                <form @submit.prevent="saveRecurring">
                    <CardContent class="p-5 space-y-4 text-xs">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Name -->
                            <div class="space-y-1.5">
                                <Label for="recurring-name" class="text-xs font-bold text-slate-500">Tên chi phí (ví dụ: Thuê nhà mặt bằng):</Label>
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
                                <Label for="recurring-cat" class="text-xs font-bold text-slate-500">Danh mục chi phí:</Label>
                                <select 
                                    id="recurring-cat"
                                    v-model="recurringForm.category_id"
                                    class="w-full text-xs font-semibold rounded-lg border border-border bg-background px-3 py-2.5 outline-hidden focus:ring-2 focus:ring-indigo-500/25"
                                >
                                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Amount -->
                            <div class="space-y-1.5">
                                <Label for="recurring-amount" class="text-xs font-bold text-slate-500">Số tiền mỗi chu kỳ (VND):</Label>
                                <Input 
                                    id="recurring-amount"
                                    v-model.number="recurringForm.amount"
                                    type="number"
                                    class="w-full text-xs"
                                />
                            </div>

                            <!-- Frequency -->
                            <div class="space-y-1.5">
                                <Label for="recurring-freq" class="text-xs font-bold text-slate-500">Chu kỳ lập lại:</Label>
                                <select 
                                    id="recurring-freq"
                                    v-model="recurringForm.frequency"
                                    class="w-full text-xs font-semibold rounded-lg border border-border bg-background px-3 py-2.5 outline-hidden focus:ring-2 focus:ring-indigo-500/25"
                                >
                                    <option value="weekly">Hàng tuần</option>
                                    <option value="monthly">Hàng tháng (Thuê mặt bằng, bảo hiểm)</option>
                                    <option value="quarterly">Hàng quý</option>
                                    <option value="yearly">Hàng năm</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Start Date -->
                            <div class="space-y-1.5">
                                <Label for="recurring-start" class="text-xs font-bold text-slate-500">Ngày bắt đầu áp dụng:</Label>
                                <Input 
                                    id="recurring-start"
                                    v-model="recurringForm.start_date"
                                    type="date"
                                    class="w-full text-xs"
                                />
                            </div>

                            <!-- End Date -->
                            <div class="space-y-1.5">
                                <Label for="recurring-end" class="text-xs font-bold text-slate-500">Ngày kết thúc (Tùy chọn):</Label>
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
                            <Label for="recurring-desc" class="text-xs font-bold text-slate-500">Mô tả chi tiết:</Label>
                            <textarea 
                                id="recurring-desc"
                                v-model="recurringForm.description"
                                rows="3"
                                placeholder="Ghi chú chi tiết cho hoạt động tự động phát sinh này..."
                                class="w-full text-xs font-semibold rounded-lg border border-border bg-background px-3 py-2 outline-hidden focus:ring-2 focus:ring-indigo-500/25"
                            ></textarea>
                        </div>
                    </CardContent>
                    <div class="p-4 border-t flex justify-end gap-2 bg-slate-50/30 dark:bg-slate-900/10">
                        <Button type="button" variant="outline" @click="showRecurringModal = false" class="text-xs h-9 font-semibold">Hủy</Button>
                        <Button 
                            type="submit" 
                            class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs h-9 flex items-center gap-1.5"
                            :disabled="recurringForm.processing"
                        >
                            <span v-if="recurringForm.processing" class="size-3 border-2 border-t-transparent border-white rounded-full animate-spin mr-1"></span>
                            {{ editingRecurring ? 'Lưu cập nhật' : 'Tạo mới' }}
                        </Button>
                    </div>
                </form>
            </Card>
        </div>

        <!-- ── MODAL: CREATE CUSTOM CATEGORY ── -->
        <div v-if="showCategoryModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
            <Card class="w-full max-w-md shadow-2xl animate-in fade-in zoom-in duration-200 border-border bg-card">
                <CardHeader class="pb-3 border-b">
                    <CardTitle class="text-sm font-bold flex items-center gap-1.5">
                        <Layers class="size-5 text-slate-800" />
                        Thêm Danh Mục Chi Phí Mới
                    </CardTitle>
                    <CardDescription class="text-xs">Tạo danh mục chi phí riêng biệt của cửa hàng phục vụ phân nhóm.</CardDescription>
                </CardHeader>
                <form @submit.prevent="saveCategory">
                    <CardContent class="p-5 space-y-4 text-xs">
                        <!-- Name -->
                        <div class="space-y-1.5">
                            <Label for="cat-name" class="text-xs font-bold text-slate-500">Tên danh mục (ví dụ: Phí ship, Tiếp khách...):</Label>
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
                            <Label for="cat-desc" class="text-xs font-bold text-slate-500">Mô tả danh mục:</Label>
                            <Input 
                                id="cat-desc"
                                v-model="categoryForm.description"
                                type="text"
                                placeholder="Nhập mô tả ngắn..."
                                class="w-full text-xs"
                            />
                        </div>
                    </CardContent>
                    <div class="p-4 border-t flex justify-end gap-2 bg-slate-50/30 dark:bg-slate-900/10">
                        <Button type="button" variant="outline" @click="showCategoryModal = false" class="text-xs h-9 font-semibold">Hủy</Button>
                        <Button 
                            type="submit" 
                            class="bg-slate-800 hover:bg-slate-900 text-white font-bold text-xs h-9"
                            :disabled="categoryForm.processing"
                        >
                            Thêm danh mục
                        </Button>
                    </div>
                </form>
            </Card>
        </div>

        <!-- ── MODAL: DOCUMENT PREVIEW ── -->
        <div v-if="invoicePreviewUrl" class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center z-50 p-4">
            <Card class="w-full max-w-4xl h-[90vh] shadow-2xl flex flex-col overflow-hidden border-border bg-card">
                <CardHeader class="pb-3 border-b flex flex-row items-center justify-between">
                    <div>
                        <CardTitle class="text-sm font-bold flex items-center gap-1.5">
                            <FileText class="size-5 text-amber-600" />
                            Xem Hóa Đơn Chứng Từ
                        </CardTitle>
                        <CardDescription class="text-xs">Chứng từ đính kèm cho giao dịch.</CardDescription>
                    </div>
                    <button 
                        @click="invoicePreviewUrl = null"
                        class="p-1 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg cursor-pointer text-slate-500"
                    >
                        <X class="size-5" />
                    </button>
                </CardHeader>
                <div class="flex-1 bg-slate-100 dark:bg-slate-900 overflow-hidden relative">
                    <!-- PDF Embed -->
                    <embed 
                        v-if="invoicePreviewUrl.toLowerCase().endsWith('.pdf')"
                        :src="invoicePreviewUrl" 
                        type="application/pdf"
                        class="w-full h-full"
                    />
                    <!-- Image Preview -->
                    <div v-else class="w-full h-full flex items-center justify-center p-4">
                        <img 
                            :src="invoicePreviewUrl" 
                            alt="Hóa đơn chứng từ"
                            class="max-w-full max-h-full object-contain shadow-md border rounded-lg"
                        />
                    </div>
                </div>
                <div class="p-4 border-t flex justify-end gap-2 bg-slate-50 dark:bg-slate-950">
                    <a 
                        :href="invoicePreviewUrl"
                        download
                        class="inline-flex items-center gap-1.5 rounded-lg border border-border bg-background px-4 py-2 text-xs font-semibold hover:bg-muted transition cursor-pointer"
                    >
                        <Download class="size-3.5" />
                        Tải xuống file
                    </a>
                    <Button @click="invoicePreviewUrl = null" class="text-xs font-semibold">Đóng</Button>
                </div>
            </Card>
        </div>
    </div>
</template>
