<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Wallet,
    Users,
    TrendingDown,
    TrendingUp,
    Check,
    ChevronDown,
    ChevronUp,
    Plus,
    BadgeDollarSign,
    AlertCircle,
    Clock,
    X,
    Sparkles,
    Search,
    Download,
    Building2,
    UserCog,
    ShieldAlert,
    AlertTriangle,
    ChevronLeft,
    ChevronRight,
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

// ── Types ─────────────────────────────────────────────────────────────────────

type AdjType =
    | 'bonus'
    | 'penalty'
    | 'cash_shortage'
    | 'inventory_loss'
    | 'violation';

type Adjustment = {
    id: number;
    type: AdjType;
    amount: number;
    reason: string;
    status: 'applied' | 'disputed' | 'waived';
    dispute_reason: string | null;
};

type SalaryRow = {
    id: number;
    employee_name: string;
    job_title: string;
    employment_type: string;
    branch_id: number | null;
    base_salary: number;
    bonus_amount: number;
    deduction_amount: number;
    net_salary: number;
    status: 'draft' | 'approved' | 'paid';
    paid_at: string | null;
    adjustments: Adjustment[];
};

type Totals = {
    total_payroll: number;
    total_deductions: number;
    total_bonuses: number;
    headcount: number;
};

type Branch = { id: number; name: string };

const props = defineProps<{
    salaries: SalaryRow[];
    totals: Totals;
    period: string;
    branches: Branch[];
    canApprove: boolean;
}>();

// ── Status config ─────────────────────────────────────────────────────────────

const statusConfig = {
    draft: {
        label: 'Bản nháp',
        cls: 'bg-slate-100 text-slate-600 border border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700/50',
    },
    approved: {
        label: 'Đã duyệt',
        cls: 'bg-indigo-50 text-indigo-700 border border-indigo-200 dark:bg-indigo-950/40 dark:text-indigo-300 dark:border-indigo-900/30',
    },
    paid: {
        label: 'Đã trả',
        cls: 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-900/30',
    },
};

const adjTypeLabel: Record<AdjType, string> = {
    bonus: 'Thưởng',
    penalty: 'Phạt',
    cash_shortage: 'Thiếu quỹ',
    inventory_loss: 'Mất hàng',
    violation: 'Vi phạm',
};

const adjTypeColor: Record<AdjType, string> = {
    bonus: 'text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-100',
    penalty:
        'text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/20 border border-rose-100',
    cash_shortage:
        'text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/20 border border-rose-100',
    inventory_loss:
        'text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/20 border border-amber-100',
    violation:
        'text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/20 border border-rose-100',
};

const adjStatusConfig = {
    applied: {
        label: 'Đã áp dụng',
        cls: 'text-slate-500 bg-slate-50 border border-slate-100',
    },
    disputed: {
        label: 'Đang khiếu nại',
        cls: 'text-amber-700 bg-amber-50 border border-amber-200 animate-pulse',
    },
    waived: {
        label: 'Đã miễn giảm',
        cls: 'text-emerald-600 bg-emerald-50 border border-emerald-200',
    },
};

// ── Search, Advanced Filters & Pagination ────────────────────────────────────

const activePeriod = ref(props.period);
const searchQuery = ref('');
const statusFilter = ref<'all' | 'draft' | 'approved' | 'paid'>('all');
const branchFilter = ref<string>('all');
const employmentTypeFilter = ref<string>('all');

// Phân trang
const currentPage = ref(1);
const itemsPerPage = ref(10);

const filteredSalaries = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();

    // Reset trang về 1 khi lọc
    currentPage.value = 1;

    return props.salaries.filter((s) => {
        const matchStatus =
            statusFilter.value === 'all' || s.status === statusFilter.value;
        const matchBranch =
            branchFilter.value === 'all' ||
            s.branch_id === Number(branchFilter.value);
        const matchEmpType =
            employmentTypeFilter.value === 'all' ||
            s.employment_type === employmentTypeFilter.value;

        if (!matchStatus || !matchBranch || !matchEmpType) return false;
        if (!q) return true;

        return (
            s.employee_name.toLowerCase().includes(q) ||
            s.job_title.toLowerCase().includes(q)
        );
    });
});

const totalPages = computed(
    () => Math.ceil(filteredSalaries.value.length / itemsPerPage.value) || 1,
);

const paginatedSalaries = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    const end = start + itemsPerPage.value;
    return filteredSalaries.value.slice(start, end);
});

function prevPage() {
    if (currentPage.value > 1) currentPage.value--;
}
function nextPage() {
    if (currentPage.value < totalPages.value) currentPage.value++;
}

function exportCSV() {
    const rows = [
        [
            'Nhân viên',
            'Chức vụ',
            'Loại HĐ',
            'Lương cơ bản',
            'Thưởng',
            'Khấu trừ',
            'Lương thực lãnh',
            'Trạng thái',
        ],
        ...props.salaries.map((s) => [
            s.employee_name,
            s.job_title,
            s.employment_type,
            s.base_salary,
            s.bonus_amount,
            s.deduction_amount,
            s.net_salary,
            statusConfig[s.status].label,
        ]),
    ];
    const bom = '\uFEFF';
    const csv =
        bom + rows.map((r) => r.map((v) => `"${v}"`).join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `bangluong_${activePeriod.value}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}

function applyPeriod() {
    router.get(
        '/salaries',
        { period: activePeriod.value },
        { preserveScroll: true },
    );
}

// ── Generate drafts ───────────────────────────────────────────────────────────

const generating = ref(false);
const generateForm = useForm({ period: props.period });

function generateDrafts() {
    generating.value = true;
    generateForm.period = activePeriod.value;
    generateForm.post('/salaries/generate', {
        onSuccess: () => toast.success('Đã tạo bảng lương bản nháp!'),
        onError: () => toast.error('Có lỗi khi tạo bảng lương.'),
        onFinish: () => {
            generating.value = false;
        },
    });
}

// ── Approve / Paid ────────────────────────────────────────────────────────────

function approveSalary(salary: SalaryRow) {
    router.patch(
        `/salaries/${salary.id}/approve`,
        {},
        {
            onSuccess: () =>
                toast.success(`Đã duyệt lương cho ${salary.employee_name}.`),
            onError: () => toast.error('Có lỗi khi duyệt lương.'),
        },
    );
}

function markPaid(salary: SalaryRow) {
    router.patch(
        `/salaries/${salary.id}/paid`,
        {},
        {
            onSuccess: () =>
                toast.success(
                    `Đã đánh dấu đã trả lương cho ${salary.employee_name}.`,
                ),
            onError: () => toast.error('Có lỗi khi cập nhật trạng thái.'),
        },
    );
}

// ── Selection & Bulk Actions ──────────────────────────────────────────────────

const selectedIds = ref<number[]>([]);

const isAllSelected = computed(() => {
    const pageIds = paginatedSalaries.value
        .filter((s) => s.status !== 'paid')
        .map((s) => s.id);
    if (pageIds.length === 0) return false;
    return pageIds.every((id) => selectedIds.value.includes(id));
});

function toggleSelectAll() {
    const pageIds = paginatedSalaries.value
        .filter((s) => s.status !== 'paid')
        .map((s) => s.id);
    if (isAllSelected.value) {
        selectedIds.value = selectedIds.value.filter(
            (id) => !pageIds.includes(id),
        );
    } else {
        const toAdd = pageIds.filter((id) => !selectedIds.value.includes(id));
        selectedIds.value.push(...toAdd);
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

const showBulkDialog = ref(false);
const bulkForm = useForm({
    salary_ids: [] as number[],
    type: 'bonus',
    amount: '',
    reason: '',
});

function openBulkDialog() {
    if (selectedIds.value.length === 0) return;
    bulkForm.reset();
    showBulkDialog.value = true;
}

function submitBulkAdj() {
    bulkForm.salary_ids = selectedIds.value;
    bulkForm.post('/salaries/adjustments/bulk', {
        onSuccess: () => {
            const pageProps = usePage().props as any;
            const msg =
                pageProps.flash?.success ??
                'Đã áp dụng điều chỉnh lương hàng loạt thành công.';
            toast.success(msg);
            selectedIds.value = [];
            showBulkDialog.value = false;
        },
        onError: () =>
            toast.error('Có lỗi xảy ra khi thực hiện cấn trừ hàng loạt.'),
    });
}

// ── Expanded row ──────────────────────────────────────────────────────────────

const expandedId = ref<number | null>(null);
const toggleExpand = (id: number) => {
    expandedId.value = expandedId.value === id ? null : id;
};

// ── Add adjustment dialog ─────────────────────────────────────────────────────

const adjTarget = ref<SalaryRow | null>(null);
const adjForm = useForm({ type: 'bonus', amount: '', reason: '' });

function openAdjDialog(salary: SalaryRow) {
    adjTarget.value = salary;
    adjForm.reset();
}

function submitAdj() {
    if (!adjTarget.value) return;
    adjForm.post(`/salaries/${adjTarget.value.id}/adjustments`, {
        onSuccess: () => {
            const msg =
                (usePage().props.flash as any)?.success ??
                'Đã thêm điều chỉnh lương thành công.';
            toast.success(msg);
            adjTarget.value = null;
        },
        onError: () => toast.error('Có lỗi khi thêm điều chỉnh lương.'),
    });
}

// ── Formatting ─────────────────────────────────────────────────────────────────

const vnd = (v: number) =>
    new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(v);
const compact = (v: number) =>
    new Intl.NumberFormat('vi-VN', {
        notation: 'compact',
        maximumFractionDigits: 1,
    }).format(v) + 'đ';
</script>

<template>
    <Head title="Quản Lý Bảng Lương" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-6">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400"
                >
                    <Wallet class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">
                        Hệ Thống Quản Lý Bảng Lương
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Quản lý lương cơ bản động theo chấm công, phụ cấp,
                        thưởng và các khiếu nại cấn trừ.
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <!-- Month picker -->
                <div class="flex items-center gap-1.5">
                    <Label
                        for="sal-period"
                        class="shrink-0 text-xs font-semibold text-slate-600"
                        >Chọn tháng:</Label
                    >
                    <Input
                        id="sal-period"
                        v-model="activePeriod"
                        type="month"
                        @change="applyPeriod"
                        class="h-9 w-36 bg-white py-1 text-xs font-semibold"
                    />
                </div>

                <!-- Generate drafts -->
                <Button
                    v-if="canApprove"
                    @click="generateDrafts"
                    :disabled="generating"
                    class="flex h-9 items-center gap-1.5 bg-indigo-600 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700"
                >
                    <Plus
                        class="size-4"
                        :class="generating ? 'animate-spin' : ''"
                    />
                    {{ generating ? 'Đang tạo...' : 'Tạo bảng lương' }}
                </Button>

                <!-- Bulk Adjustment button -->
                <Button
                    v-if="canApprove && selectedIds.length > 0"
                    @click="openBulkDialog"
                    class="flex h-9 animate-bounce items-center gap-1.5 bg-rose-600 text-xs font-semibold text-white shadow-sm hover:bg-rose-700"
                >
                    <Plus class="size-4" />
                    Thưởng/Phạt hàng loạt ({{ selectedIds.length }})
                </Button>

                <!-- Export CSV -->
                <Button
                    variant="outline"
                    @click="exportCSV"
                    class="flex h-9 items-center gap-1.5 text-xs font-semibold"
                >
                    <Download class="size-4" />
                    Xuất CSV
                </Button>
            </div>
        </div>

        <!-- KPI cards -->
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <!-- Headcount -->
            <Card
                class="shadow-xs transition-transform duration-200 hover:translate-y-[-2px]"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardDescription
                        class="text-xs font-bold tracking-wider text-slate-400 uppercase"
                        >Nhân viên</CardDescription
                    >
                    <Users class="size-4 text-slate-400" />
                </CardHeader>
                <CardContent class="pb-3">
                    <p
                        class="text-2xl font-black text-slate-800 dark:text-slate-100"
                    >
                        {{ totals.headcount }}
                    </p>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        có bảng lương tháng này
                    </p>
                </CardContent>
            </Card>

            <!-- Net Salary -->
            <Card
                class="border-indigo-100 shadow-xs transition-transform duration-200 hover:translate-y-[-2px] dark:border-indigo-950/20"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardDescription
                        class="text-xs font-bold tracking-wider text-indigo-500 uppercase"
                        >Tổng lương Net</CardDescription
                    >
                    <BadgeDollarSign
                        class="size-4 text-indigo-600 dark:text-indigo-400"
                    />
                </CardHeader>
                <CardContent class="pb-3">
                    <p
                        class="text-2xl font-black text-indigo-600 dark:text-indigo-400"
                    >
                        {{ compact(totals.total_payroll) }}
                    </p>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        sau khi khấu trừ
                    </p>
                </CardContent>
            </Card>

            <!-- Deductions -->
            <Card
                class="border-rose-100 shadow-xs transition-transform duration-200 hover:translate-y-[-2px] dark:border-rose-950/20"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardDescription
                        class="text-xs font-bold tracking-wider text-rose-500 uppercase"
                        >Tổng khấu trừ</CardDescription
                    >
                    <TrendingDown
                        class="size-4 text-rose-600 dark:text-rose-400"
                    />
                </CardHeader>
                <CardContent class="pb-3">
                    <p
                        class="text-2xl font-black text-rose-600 dark:text-rose-400"
                    >
                        {{ compact(totals.total_deductions) }}
                    </p>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        thiếu quỹ + hao hụt kho + phạt
                    </p>
                </CardContent>
            </Card>

            <!-- Bonuses -->
            <Card
                class="border-emerald-100 shadow-xs transition-transform duration-200 hover:translate-y-[-2px] dark:border-emerald-950/20"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardDescription
                        class="text-xs font-bold tracking-wider text-emerald-500 uppercase"
                        >Tổng thưởng</CardDescription
                    >
                    <TrendingUp
                        class="size-4 text-emerald-600 dark:text-emerald-400"
                    />
                </CardHeader>
                <CardContent class="pb-3">
                    <p
                        class="text-2xl font-black text-emerald-600 dark:text-emerald-400"
                    >
                        {{ compact(totals.total_bonuses) }}
                    </p>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        tiền thưởng tháng này
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Table Card -->
        <Card class="overflow-hidden shadow-sm">
            <CardHeader
                class="flex flex-row items-center justify-between border-b bg-slate-50/50 pb-3 dark:bg-slate-900/50 print:hidden"
            >
                <div>
                    <CardTitle class="flex items-center gap-1.5 text-base">
                        <Wallet class="size-5 text-indigo-600" />
                        Danh Sách Bảng Lương Chi Tiết
                    </CardTitle>
                    <CardDescription
                        >Danh sách lương cơ bản động dựa trên chấm công ca, cùng
                        các khoản khiếu nại cấn trừ.</CardDescription
                    >
                </div>
            </CardHeader>

            <CardContent class="p-0">
                <!-- Advanced Search & Filter Row -->
                <div
                    class="grid items-center gap-3 border-b border-slate-100 bg-white p-4 sm:grid-cols-[2fr_1fr_1fr_auto] dark:border-slate-800 dark:bg-slate-950"
                >
                    <!-- Search input -->
                    <div class="relative w-full">
                        <Search
                            class="absolute top-2.5 left-2.5 size-3.5 text-slate-400"
                        />
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Tìm theo tên nhân viên, chức vụ..."
                            class="w-full rounded-lg border border-slate-200 bg-white py-1.5 pr-3 pl-8 text-xs font-semibold focus:ring-2 focus:ring-indigo-400/40 focus:outline-none dark:border-slate-700 dark:bg-slate-800"
                        />
                    </div>

                    <!-- Branch filter -->
                    <div class="flex items-center gap-1.5">
                        <Building2 class="size-3.5 text-slate-400" />
                        <select
                            v-model="branchFilter"
                            class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs font-semibold text-slate-600 focus:ring-2 focus:ring-indigo-400/40 focus:outline-none dark:border-slate-700 dark:bg-slate-800"
                        >
                            <option value="all">Tất cả chi nhánh</option>
                            <option
                                v-for="b in branches"
                                :key="b.id"
                                :value="b.id"
                            >
                                {{ b.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Employment type filter -->
                    <div class="flex items-center gap-1.5">
                        <UserCog class="size-3.5 text-slate-400" />
                        <select
                            v-model="employmentTypeFilter"
                            class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs font-semibold text-slate-600 focus:ring-2 focus:ring-indigo-400/40 focus:outline-none dark:border-slate-700 dark:bg-slate-800"
                        >
                            <option value="all">Tất cả hợp đồng</option>
                            <option value="full_time">Full-time</option>
                            <option value="part_time">Part-time</option>
                            <option value="seasonal">Thời vụ</option>
                        </select>
                    </div>

                    <!-- Status Filter tab -->
                    <div
                        class="flex items-center gap-1 justify-self-end rounded-xl border border-slate-200/50 bg-slate-100 p-0.5 dark:border-slate-800 dark:bg-slate-900"
                    >
                        <button
                            v-for="f in [
                                { key: 'all', label: 'Tất cả' },
                                { key: 'draft', label: 'Nháp' },
                                { key: 'approved', label: 'Duyệt' },
                                { key: 'paid', label: 'Đã trả' },
                            ]"
                            :key="f.key"
                            type="button"
                            @click="statusFilter = f.key as any"
                            :class="[
                                'rounded-lg px-2.5 py-1 text-[10px] font-bold whitespace-nowrap transition-colors',
                                statusFilter === f.key
                                    ? 'bg-white text-slate-800 shadow-sm dark:bg-slate-800 dark:text-slate-100'
                                    : 'text-slate-400 hover:text-slate-600 dark:hover:text-slate-300',
                            ]"
                        >
                            {{ f.label }}
                        </button>
                    </div>
                </div>

                <div
                    v-if="salaries.length === 0"
                    class="flex flex-col items-center gap-3 py-20 text-center text-muted-foreground"
                >
                    <div
                        class="flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/40"
                    >
                        <Wallet class="size-7" />
                    </div>
                    <p class="font-bold text-slate-800 dark:text-slate-200">
                        Chưa có dữ liệu bảng lương tháng này
                    </p>
                    <p class="mx-auto max-w-sm text-xs text-slate-500">
                        Vui lòng nhấn nút "Tạo bảng lương" ở trên để hệ thống tự
                        động sinh dữ liệu nháp dựa trên công ca thực tế.
                    </p>
                </div>

                <template v-else>
                    <!-- Table header -->
                    <div
                        class="hidden grid-cols-[auto_auto_1fr_1fr_1fr_1fr_1fr_auto] gap-4 border-b border-slate-100 bg-slate-50 px-5 py-3 text-[10px] font-bold tracking-wider text-slate-500 uppercase lg:grid dark:border-slate-800 dark:bg-slate-900"
                    >
                        <div class="flex items-center justify-center">
                            <input
                                v-if="canApprove"
                                type="checkbox"
                                :checked="isAllSelected"
                                @change="toggleSelectAll"
                                class="size-3.5 cursor-pointer rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                            />
                        </div>
                        <div></div>
                        <div>Nhân viên</div>
                        <div class="text-right">Lương cơ bản</div>
                        <div class="text-right">Thưởng</div>
                        <div class="text-right">Khấu trừ</div>
                        <div class="text-right">Lương Net thực nhận</div>
                        <div class="text-right">Trạng thái</div>
                    </div>

                    <div
                        v-for="s in paginatedSalaries"
                        :key="s.id"
                        class="border-b border-slate-100 last:border-0 dark:border-slate-800"
                        :class="{
                            'bg-rose-50/10 dark:bg-rose-950/5':
                                selectedIds.includes(s.id),
                        }"
                    >
                        <!-- Main row -->
                        <div
                            class="grid cursor-pointer grid-cols-[auto_1fr_auto] items-center gap-3 px-4 py-4 transition hover:bg-muted/30 lg:grid-cols-[auto_auto_1fr_1fr_1fr_1fr_1fr_auto] lg:gap-4 lg:px-5"
                            @click="toggleExpand(s.id)"
                        >
                            <!-- Checkbox chọn nhiều (Bulk Action) -->
                            <div
                                class="hidden items-center justify-center lg:flex"
                                @click.stop
                            >
                                <input
                                    v-if="canApprove && s.status !== 'paid'"
                                    type="checkbox"
                                    :checked="selectedIds.includes(s.id)"
                                    @change="toggleSelect(s.id)"
                                    class="size-3.5 cursor-pointer rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                />
                            </div>

                            <component
                                :is="
                                    expandedId === s.id
                                        ? ChevronUp
                                        : ChevronDown
                                "
                                class="size-4 shrink-0 text-slate-400"
                            />

                            <!-- Nhân viên -->
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <p class="truncate text-sm font-semibold">
                                        {{ s.employee_name }}
                                    </p>
                                    <!-- Cảnh báo khi có khiếu nại (Disputed) -->
                                    <span
                                        v-if="
                                            s.adjustments.some(
                                                (a) => a.status === 'disputed',
                                            )
                                        "
                                        class="flex h-4 items-center gap-1 rounded border border-amber-200 bg-amber-100 px-1.5 text-[9px] font-black text-amber-700 dark:bg-amber-900/40 dark:text-amber-400"
                                    >
                                        <AlertTriangle
                                            class="size-2.5 shrink-0 text-amber-600"
                                        />
                                        CÓ KHIẾU NẠI
                                    </span>
                                </div>
                                <p
                                    class="mt-0.5 text-[10px] font-medium text-slate-400"
                                >
                                    {{ s.job_title }} |
                                    {{
                                        s.employment_type === 'full_time'
                                            ? 'Full-time'
                                            : s.employment_type === 'part_time'
                                              ? 'Part-time'
                                              : 'Thời vụ'
                                    }}
                                </p>
                            </div>

                            <!-- Lương cơ bản -->
                            <div
                                class="hidden text-right font-mono text-slate-600 lg:block dark:text-slate-300"
                            >
                                <p class="text-sm font-semibold">
                                    {{ compact(s.base_salary) }}
                                </p>
                            </div>

                            <!-- Thưởng -->
                            <div class="hidden text-right font-mono lg:block">
                                <p
                                    class="text-sm font-bold"
                                    :class="
                                        s.bonus_amount > 0
                                            ? 'text-emerald-600 dark:text-emerald-400'
                                            : 'text-slate-300 dark:text-slate-700'
                                    "
                                >
                                    {{
                                        s.bonus_amount > 0
                                            ? '+' + compact(s.bonus_amount)
                                            : '—'
                                    }}
                                </p>
                            </div>

                            <!-- Khấu trừ -->
                            <div class="hidden text-right font-mono lg:block">
                                <p
                                    class="text-sm font-bold"
                                    :class="
                                        s.deduction_amount > 0
                                            ? 'text-rose-600 dark:text-rose-400'
                                            : 'text-slate-300 dark:text-slate-700'
                                    "
                                >
                                    {{
                                        s.deduction_amount > 0
                                            ? '-' + compact(s.deduction_amount)
                                            : '—'
                                    }}
                                </p>
                            </div>

                            <!-- Lương net -->
                            <div class="hidden text-right font-mono lg:block">
                                <p
                                    class="text-sm font-black text-indigo-600 dark:text-indigo-400"
                                >
                                    {{ compact(s.net_salary) }}
                                </p>
                            </div>

                            <!-- Status + mobile amount -->
                            <div
                                class="flex shrink-0 flex-col items-end gap-1.5"
                            >
                                <span
                                    class="inline-flex rounded-full px-2.5 py-0.5 text-[10px] font-bold tracking-wider uppercase"
                                    :class="statusConfig[s.status].cls"
                                >
                                    {{ statusConfig[s.status].label }}
                                </span>
                                <span
                                    class="font-mono text-xs font-bold text-indigo-600 lg:hidden dark:text-indigo-400"
                                >
                                    {{ compact(s.net_salary) }}
                                </span>
                            </div>
                        </div>

                        <!-- Expanded detail -->
                        <Transition
                            enter-active-class="transition-all duration-200 ease-out"
                            enter-from-class="opacity-0 max-h-0"
                            enter-to-class="opacity-100 max-h-[600px]"
                            leave-active-class="transition-all duration-150 ease-in"
                            leave-from-class="opacity-100 max-h-[600px]"
                            leave-to-class="opacity-0 max-h-0"
                        >
                            <div
                                v-if="expandedId === s.id"
                                class="overflow-hidden border-t border-slate-100 bg-slate-50/40 px-5 py-5 dark:border-slate-800 dark:bg-slate-900/20"
                            >
                                <div class="grid gap-6 sm:grid-cols-2">
                                    <!-- Salary breakdown -->
                                    <div
                                        class="space-y-3 rounded-xl border bg-white p-4 shadow-2xs dark:bg-slate-950"
                                    >
                                        <p
                                            class="text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                                        >
                                            Chi tiết cơ cấu lương thực nhận
                                        </p>
                                        <div
                                            class="space-y-2 text-xs font-semibold"
                                        >
                                            <div
                                                class="flex justify-between text-slate-600 dark:text-slate-300"
                                            >
                                                <span class="font-medium"
                                                    >Lương gốc (Tính theo chấm
                                                    công)</span
                                                >
                                                <span class="font-mono">{{
                                                    vnd(s.base_salary)
                                                }}</span>
                                            </div>
                                            <div
                                                v-if="s.bonus_amount > 0"
                                                class="flex justify-between text-emerald-600"
                                            >
                                                <span class="font-medium"
                                                    >Tổng các khoản thưởng
                                                    (+)</span
                                                >
                                                <span
                                                    class="font-mono font-bold"
                                                    >+{{
                                                        vnd(s.bonus_amount)
                                                    }}</span
                                                >
                                            </div>
                                            <div
                                                v-if="s.deduction_amount > 0"
                                                class="flex justify-between text-rose-600"
                                            >
                                                <span class="font-medium"
                                                    >Tổng các khoản khấu trừ
                                                    (-)</span
                                                >
                                                <span
                                                    class="font-mono font-bold"
                                                    >-{{
                                                        vnd(s.deduction_amount)
                                                    }}</span
                                                >
                                            </div>
                                            <div
                                                class="flex justify-between border-t border-slate-100 pt-2 text-slate-800 dark:border-slate-800 dark:text-slate-200"
                                            >
                                                <span class="font-bold"
                                                    >Lương NET thực nhận</span
                                                >
                                                <span
                                                    class="font-mono text-sm font-black text-indigo-600 dark:text-indigo-400"
                                                    >{{
                                                        vnd(s.net_salary)
                                                    }}</span
                                                >
                                            </div>
                                            <div
                                                v-if="s.paid_at"
                                                class="mt-2 flex items-center gap-1 border-t pt-2 font-mono text-[10px] text-slate-400"
                                            >
                                                <Clock class="size-3" /> Đã chi
                                                trả thành công lúc:
                                                {{ s.paid_at }}
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Adjustments list -->
                                    <div
                                        class="space-y-3 rounded-xl border bg-white p-4 shadow-2xs dark:bg-slate-950"
                                    >
                                        <p
                                            class="text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                                        >
                                            Danh sách điều chỉnh lương & Trạng
                                            thái khiếu nại ({{
                                                s.adjustments.length
                                            }})
                                        </p>
                                        <div
                                            v-if="s.adjustments.length === 0"
                                            class="py-2 text-xs text-slate-400 italic"
                                        >
                                            Không có khoản ghi nhận điều chỉnh
                                            nào phát sinh trong tháng.
                                        </div>
                                        <div
                                            v-else
                                            class="max-h-[160px] space-y-2 overflow-y-auto pr-1"
                                        >
                                            <div
                                                v-for="a in s.adjustments"
                                                :key="a.id"
                                                class="flex flex-col gap-2 rounded-lg border border-slate-100 bg-slate-50/50 px-3 py-2 text-xs dark:border-slate-800 dark:bg-slate-900/30"
                                            >
                                                <div
                                                    class="flex items-start justify-between gap-3"
                                                >
                                                    <div class="min-w-0">
                                                        <span
                                                            class="rounded px-1.5 py-0.5 text-[9px] font-bold tracking-wide uppercase"
                                                            :class="
                                                                adjTypeColor[
                                                                    a.type as AdjType
                                                                ]
                                                            "
                                                        >
                                                            {{
                                                                adjTypeLabel[
                                                                    a.type as AdjType
                                                                ]
                                                            }}
                                                        </span>
                                                        <p
                                                            class="mt-1 truncate font-semibold text-slate-600 dark:text-slate-300"
                                                            :title="a.reason"
                                                        >
                                                            {{ a.reason }}
                                                        </p>
                                                    </div>
                                                    <span
                                                        class="shrink-0 font-mono font-bold"
                                                        :class="
                                                            a.type === 'bonus'
                                                                ? 'text-emerald-600'
                                                                : 'text-rose-600'
                                                        "
                                                    >
                                                        {{
                                                            a.type === 'bonus'
                                                                ? '+'
                                                                : '-'
                                                        }}{{ vnd(a.amount) }}
                                                    </span>
                                                </div>

                                                <!-- Hiển thị Trạng thái Khiếu nại -->
                                                <div
                                                    class="mt-0.5 flex items-center justify-between border-t border-slate-200/50 pt-1.5 dark:border-slate-800"
                                                >
                                                    <span
                                                        class="inline-flex rounded px-1.5 py-0.5 text-[9px] font-bold"
                                                        :class="
                                                            adjStatusConfig[
                                                                a.status
                                                            ].cls
                                                        "
                                                    >
                                                        {{
                                                            adjStatusConfig[
                                                                a.status
                                                            ].label
                                                        }}
                                                    </span>

                                                    <span
                                                        v-if="
                                                            a.status ===
                                                            'disputed'
                                                        "
                                                        class="max-w-[200px] truncate text-[9px] font-semibold text-amber-600 italic"
                                                        :title="
                                                            a.dispute_reason ??
                                                            undefined
                                                        "
                                                    >
                                                        Lý do:
                                                        {{ a.dispute_reason }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div
                                    v-if="canApprove"
                                    class="mt-5 flex flex-wrap gap-2 border-t border-slate-100 pt-3.5 dark:border-slate-800"
                                >
                                    <Button
                                        v-if="s.status === 'draft'"
                                        @click.stop="approveSalary(s)"
                                        class="flex h-8 items-center gap-1 bg-indigo-600 text-xs font-semibold text-white hover:bg-indigo-700"
                                    >
                                        <Check class="size-3.5" /> Duyệt bảng
                                        lương
                                    </Button>
                                    <Button
                                        v-if="s.status === 'approved'"
                                        @click.stop="markPaid(s)"
                                        class="flex h-8 items-center gap-1 bg-emerald-600 text-xs font-semibold text-white hover:bg-emerald-700"
                                    >
                                        <Check class="size-3.5" /> Đánh dấu đã
                                        trả lương
                                    </Button>
                                    <Button
                                        v-if="s.status !== 'paid'"
                                        @click.stop="openAdjDialog(s)"
                                        variant="outline"
                                        class="flex h-8 items-center gap-1 border-indigo-100 text-xs font-semibold text-indigo-600 hover:bg-indigo-50"
                                    >
                                        <Plus class="size-3.5" /> Thêm khoản
                                        điều chỉnh
                                    </Button>
                                </div>
                            </div>
                        </Transition>
                    </div>

                    <!-- Client-side Pagination Footer -->
                    <div
                        class="flex items-center justify-between border-t border-slate-100 bg-slate-50/50 p-4 dark:border-slate-800 dark:bg-slate-900/50"
                    >
                        <span class="text-xs font-semibold text-slate-500">
                            Hiển thị dòng
                            {{ (currentPage - 1) * itemsPerPage + 1 }} -
                            {{
                                Math.min(
                                    currentPage * itemsPerPage,
                                    filteredSalaries.length,
                                )
                            }}
                            trong tổng số {{ filteredSalaries.length }} nhân
                            viên
                        </span>

                        <div class="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                @click="prevPage"
                                :disabled="currentPage === 1"
                                class="flex h-8 items-center gap-1 px-2 text-xs"
                            >
                                <ChevronLeft class="size-4" /> Trước
                            </Button>

                            <span
                                class="rounded-lg border bg-white px-3 py-1 text-xs font-bold dark:bg-slate-800"
                            >
                                Trang {{ currentPage }} / {{ totalPages }}
                            </span>

                            <Button
                                variant="outline"
                                size="sm"
                                @click="nextPage"
                                :disabled="currentPage === totalPages"
                                class="flex h-8 items-center gap-1 px-2 text-xs"
                            >
                                Sau <ChevronRight class="size-4" />
                            </Button>
                        </div>
                    </div>
                </template>
            </CardContent>
        </Card>
    </div>

    <!-- ══ Add Adjustment Dialog ═══════════════════════════════════════════════ -->
    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="adjTarget"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
            @click.self="adjTarget = null"
        >
            <Card
                class="w-full max-w-md animate-in overflow-hidden shadow-2xl duration-150 zoom-in-95 fade-in"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between gap-4 border-b pb-3"
                >
                    <div>
                        <CardTitle
                            class="flex items-center gap-1.5 text-base text-indigo-600"
                        >
                            <Sparkles class="size-5" />
                            Thêm Khoản Điều Chỉnh Lương
                        </CardTitle>
                        <CardDescription
                            >Thêm thưởng, phạt hành chính hoặc khấu trừ hao hụt
                            kho cho nhân sự
                            <strong>{{ adjTarget.employee_name }}</strong
                            >.</CardDescription
                        >
                    </div>
                    <button
                        @click="adjTarget = null"
                        class="rounded-lg p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                    >
                        <X class="size-4" />
                    </button>
                </CardHeader>

                <CardContent class="space-y-4 pt-4">
                    <!-- Type Selection -->
                    <div class="grid gap-1.5">
                        <Label
                            for="adj-type"
                            class="text-xs font-bold tracking-wide text-slate-500 uppercase"
                            >Loại điều chỉnh</Label
                        >
                        <select
                            id="adj-type"
                            v-model="adjForm.type"
                            class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm font-semibold text-slate-700 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none"
                        >
                            <option value="bonus">
                                Thưởng chuyên cần / Phụ cấp
                            </option>
                            <option value="penalty">
                                Phạt hành chính / Kỷ luật
                            </option>
                            <option value="violation">
                                Khấu trừ vi phạm / Hao hụt
                            </option>
                        </select>
                    </div>

                    <!-- Amount Input -->
                    <div class="grid gap-1.5">
                        <Label
                            for="adj-amount"
                            class="text-xs font-bold tracking-wide text-slate-500 uppercase"
                            >Số tiền (VNĐ)
                            <span class="text-rose-500">*</span></Label
                        >
                        <Input
                            id="adj-amount"
                            v-model="adjForm.amount"
                            type="number"
                            min="0.01"
                            step="1000"
                            placeholder="Nhập số tiền..."
                            class="h-9 text-xs font-bold"
                            :class="{
                                'border-rose-400': adjForm.errors.amount,
                            }"
                        />
                        <p
                            v-if="adjForm.errors.amount"
                            class="text-[10px] font-semibold text-rose-500"
                        >
                            {{ adjForm.errors.amount }}
                        </p>
                    </div>

                    <!-- Reason Area -->
                    <div class="grid gap-1.5">
                        <Label
                            for="adj-reason"
                            class="text-xs font-bold tracking-wide text-slate-500 uppercase"
                            >Lý do điều chỉnh chi tiết
                            <span class="text-rose-500">*</span></Label
                        >
                        <textarea
                            id="adj-reason"
                            v-model="adjForm.reason"
                            rows="3"
                            maxlength="500"
                            placeholder="Mô tả lý do thưởng phạt cụ thể để ghi nhận pháp lý..."
                            class="w-full resize-none rounded-md border border-slate-200 bg-background px-3 py-2 text-sm font-semibold text-slate-700 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none"
                            :class="{
                                'border-rose-400': adjForm.errors.reason,
                            }"
                        />
                        <p
                            v-if="adjForm.errors.reason"
                            class="text-[10px] font-semibold text-rose-500"
                        >
                            {{ adjForm.errors.reason }}
                        </p>
                    </div>

                    <!-- Warning banner -->
                    <div
                        class="flex items-start gap-2 rounded-xl border border-amber-100 bg-amber-50/50 p-3 text-[10px] text-amber-700 dark:bg-amber-950/20 dark:text-amber-400"
                    >
                        <AlertCircle
                            class="mt-0.5 size-4 shrink-0 text-amber-600"
                        />
                        <p>
                            <strong>Duyệt điều chỉnh:</strong> Quản lý chỉ được
                            phép đề xuất điều chỉnh. Đơn điều chỉnh sẽ được tự
                            động chuyển đến trạng thái chờ duyệt nếu bạn không
                            phải là Chủ (Owner) nhà hàng.
                        </p>
                    </div>

                    <!-- Buttons -->
                    <div class="flex justify-end gap-2 border-t pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="adjTarget = null"
                            >Hủy</Button
                        >
                        <Button
                            type="button"
                            size="sm"
                            @click="submitAdj"
                            :disabled="adjForm.processing"
                            class="bg-indigo-600 font-semibold text-white hover:bg-indigo-700"
                        >
                            {{
                                adjForm.processing
                                    ? 'Đang lưu...'
                                    : 'Xác nhận điều chỉnh'
                            }}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </Transition>

    <!-- ══ Bulk Adjustment Dialog ═════════════════════════════════════════════ -->
    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="showBulkDialog"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
            @click.self="showBulkDialog = false"
        >
            <Card
                class="w-full max-w-md animate-in overflow-hidden shadow-2xl duration-150 zoom-in-95 fade-in"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between gap-4 border-b pb-3"
                >
                    <div>
                        <CardTitle
                            class="flex items-center gap-1.5 text-base text-rose-600"
                        >
                            <Sparkles class="size-5" />
                            Điều Chỉnh Lương Hàng Loạt
                        </CardTitle>
                        <CardDescription
                            >Áp dụng thưởng/phạt đồng loạt cho
                            <strong>{{ selectedIds.length }} nhân sự</strong>
                            đang được chọn.</CardDescription
                        >
                    </div>
                    <button
                        @click="showBulkDialog = false"
                        class="rounded-lg p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                    >
                        <X class="size-4" />
                    </button>
                </CardHeader>

                <CardContent class="space-y-4 pt-4">
                    <!-- Type Selection -->
                    <div class="grid gap-1.5">
                        <Label
                            for="bulk-type"
                            class="text-xs font-bold tracking-wide text-slate-500 uppercase"
                            >Loại điều chỉnh hàng loạt</Label
                        >
                        <select
                            id="bulk-type"
                            v-model="bulkForm.type"
                            class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm font-semibold text-slate-700 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none"
                        >
                            <option value="bonus">
                                Thưởng chuyên cần / Thưởng nóng đồng loạt
                            </option>
                            <option value="penalty">
                                Phạt lỗi tập thể / Kỷ luật
                            </option>
                            <option value="violation">
                                Khấu trừ vi phạm nội bộ
                            </option>
                        </select>
                    </div>

                    <!-- Amount Input -->
                    <div class="grid gap-1.5">
                        <Label
                            for="bulk-amount"
                            class="text-xs font-bold tracking-wide text-slate-500 uppercase"
                            >Số tiền mỗi nhân sự (VNĐ)
                            <span class="text-rose-500">*</span></Label
                        >
                        <Input
                            id="bulk-amount"
                            v-model="bulkForm.amount"
                            type="number"
                            min="0.01"
                            step="1000"
                            placeholder="Nhập số tiền áp dụng cho từng người..."
                            class="h-9 text-xs font-bold text-rose-600"
                            :class="{
                                'border-rose-400': bulkForm.errors.amount,
                            }"
                        />
                        <p
                            v-if="bulkForm.errors.amount"
                            class="text-[10px] font-semibold text-rose-500"
                        >
                            {{ bulkForm.errors.amount }}
                        </p>
                    </div>

                    <!-- Reason Area -->
                    <div class="grid gap-1.5">
                        <Label
                            for="bulk-reason"
                            class="text-xs font-bold tracking-wide text-slate-500 uppercase"
                            >Lý do điều chỉnh hàng loạt
                            <span class="text-rose-500">*</span></Label
                        >
                        <textarea
                            id="bulk-reason"
                            v-model="bulkForm.reason"
                            rows="3"
                            maxlength="500"
                            placeholder="Mô tả lý do điều chỉnh hàng loạt (ví dụ: Thưởng nóng dự án, Phạt đi trễ tập thể...)"
                            class="w-full resize-none rounded-md border border-slate-200 bg-background px-3 py-2 text-sm font-semibold text-slate-700 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none"
                            :class="{
                                'border-rose-400': bulkForm.errors.reason,
                            }"
                        />
                        <p
                            v-if="bulkForm.errors.reason"
                            class="text-[10px] font-semibold text-rose-500"
                        >
                            {{ bulkForm.errors.reason }}
                        </p>
                    </div>

                    <!-- Buttons -->
                    <div class="flex justify-end gap-2 border-t pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="showBulkDialog = false"
                            >Hủy</Button
                        >
                        <Button
                            type="button"
                            size="sm"
                            @click="submitBulkAdj"
                            :disabled="bulkForm.processing"
                            class="bg-rose-600 font-semibold text-white hover:bg-rose-700"
                        >
                            {{
                                bulkForm.processing
                                    ? 'Đang thực hiện...'
                                    : 'Xác nhận áp dụng hàng loạt'
                            }}
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </Transition>
</template>
