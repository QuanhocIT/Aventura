<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Wallet, Users, TrendingDown, TrendingUp, Check, ChevronDown, ChevronUp, Plus, BadgeDollarSign, AlertCircle, Clock, X, Sparkles, Search, Download, Building2, UserCog, AlertTriangle, ChevronLeft, ChevronRight, Calculator, Printer, Calendar } from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import { toast } from 'vue-sonner';
import TrustScoreBadge from '@/components/employees/TrustScoreBadge.vue';
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
    | 'violation'
    | 'advance';

type Adjustment = {
    id: number;
    type: AdjType;
    amount: number;
    reason: string;
    status: 'applied' | 'disputed' | 'waived';
    dispute_reason: string | null;
};

type SalaryBreakdown = {
    compensation_type: 'fixed' | 'hourly' | 'shift';
    compensation_type_label: string;
    contract_salary: number;
    pay_rate: number;
    standard_days: number;
    days_in_month: number;
    actual_work_days: number;
    paid_leave_days: number;
    total_paid_days: number;
    daily_rate: number;
    completed_shifts_count: number;
    regular_hours: number;
    ot_hours: number;
    ot_multiplier: number;
    unapproved_ot_hours?: number;
    unpaid_leave_days?: number;
    regular_amount?: number;
    overtime_amount?: number;
    formula_text: string;
    policy_note?: string;
};

type SalaryRow = {
    id: number;
    employee_id: number;
    employee_code: string;
    employee_name: string;
    job_title: string;
    employment_type: string;
    compensation_type: 'fixed' | 'hourly' | 'shift';
    pay_rate: number;
    contract_base_salary: number;
    trust_score: number;
    branch_id: number | null;
    base_salary: number;
    bonus_amount: number;
    overtime_amount: number;
    deduction_amount: number;
    net_salary: number;
    status: 'draft' | 'approved' | 'paid';
    paid_at: string | null;
    breakdown: SalaryBreakdown;
    adjustments: Adjustment[];
    approved_by_name?: string | null;
    created_at?: string | null;
};

type Totals = {
    total_payroll: number;
    total_deductions: number;
    total_bonuses: number;
    headcount: number;
};

type Branch = { id: number; name: string };
type GenerationMeta = {
    eligible_employees: number;
    salary_rows: number;
    status_counts: { draft: number; approved: number; paid: number };
    period_start: string;
    period_end: string;
};

const props = defineProps<{
    salaries: SalaryRow[];
    totals: Totals;
    period: string;
    branches: Branch[];
    canApprove: boolean;
    generation: GenerationMeta;
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
    advance: 'Tạm ứng',
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
    advance:
        'text-orange-600 dark:text-orange-400 bg-orange-50 dark:bg-orange-950/20 border border-orange-100',
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
const showMonthPicker = ref(false);
const pickerYear = ref<number>(
    parseInt((props.period || '').split('-')[0], 10) || new Date().getFullYear(),
);

function formatPeriodVietnamese(periodStr: string): string {
    if (!periodStr) {
        return '';
    }
    const parts = periodStr.split('-');

    if (parts.length !== 2) {
        return periodStr;
    }
    const year = parts[0];
    const month = parseInt(parts[1], 10);

    return `Tháng ${month < 10 ? '0' + month : month}/${year}`;
}

function toggleMonthPicker() {
    if (!showMonthPicker.value && activePeriod.value) {
        const parts = activePeriod.value.split('-');

        if (parts[0]) {
            pickerYear.value = parseInt(parts[0], 10) || new Date().getFullYear();
        }
    }
    showMonthPicker.value = !showMonthPicker.value;
}

function isCurrentSelected(m: number): boolean {
    const formattedMonth = m < 10 ? `0${m}` : `${m}`;

    return activePeriod.value === `${pickerYear.value}-${formattedMonth}`;
}

function selectMonth(m: number) {
    const formattedMonth = m < 10 ? `0${m}` : `${m}`;

    activePeriod.value = `${pickerYear.value}-${formattedMonth}`;
    showMonthPicker.value = false;
    applyPeriod();
}

function selectCurrentMonth() {
    const d = new Date();

    pickerYear.value = d.getFullYear();
    selectMonth(d.getMonth() + 1);
}

const searchQuery = ref('');
const statusFilter = ref<'all' | 'draft' | 'approved' | 'paid'>('all');
const branchFilter = ref<string>('all');
const employmentTypeFilter = ref<string>('all');

// Phân trang
const currentPage = ref(1);
const itemsPerPage = ref(10);

// Đổi bộ lọc thì về trang 1. TRƯỚC ĐÂY việc này nằm TRONG computed bên dưới —
// Vue cấm ghi ref trong computed (getter phải thuần); thứ tự chạy phụ thuộc vào
// lúc computed được đánh giá lại nên dễ reset sai thời điểm.
watch([searchQuery, statusFilter, branchFilter, employmentTypeFilter], () => {
    currentPage.value = 1;
});

const filteredSalaries = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();

    return props.salaries.filter((s) => {
        const matchStatus =
            statusFilter.value === 'all' || s.status === statusFilter.value;
        const matchBranch =
            branchFilter.value === 'all' ||
            s.branch_id === Number(branchFilter.value);
        const matchEmpType =
            employmentTypeFilter.value === 'all' ||
            s.employment_type === employmentTypeFilter.value;

        if (!matchStatus || !matchBranch || !matchEmpType) {
            return false;
        }

        if (!q) {
            return true;
        }

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
    if (currentPage.value > 1) {
        currentPage.value--;
    }
}
function nextPage() {
    if (currentPage.value < totalPages.value) {
        currentPage.value++;
    }
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
            statusConfig[s.status]?.label || s.status,
        ]),
    ];
    const bom = '\uFEFF';
    const csv =
        bom +
        rows
            .map((r) =>
                r
                    .map((v) => `"${String(v ?? '').replace(/"/g, '""')}"`)
                    .join(';'),
            )
            .join('\r\n');
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

const actionProcessing = ref(false);

function approveSalary(salary: SalaryRow) {
    if (actionProcessing.value) {
        return;
    }

    actionProcessing.value = true;
    router.patch(
        `/salaries/${salary.id}/approve`,
        {},
        {
            onSuccess: () =>
                toast.success(`Đã duyệt lương cho ${salary.employee_name}.`),
            onError: () => toast.error('Có lỗi khi duyệt lương.'),
            onFinish: () => {
                actionProcessing.value = false;
            },
        },
    );
}

function markPaid(salary: SalaryRow) {
    if (actionProcessing.value) {
        return;
    }

    actionProcessing.value = true;
    router.patch(
        `/salaries/${salary.id}/paid`,
        {},
        {
            onSuccess: () =>
                toast.success(
                    `Đã đánh dấu đã trả lương cho ${salary.employee_name}.`,
                ),
            onError: () => toast.error('Có lỗi khi cập nhật trạng thái.'),
            onFinish: () => {
                actionProcessing.value = false;
            },
        },
    );
}

// ── Selection & Bulk Actions ──────────────────────────────────────────────────

const selectedIds = ref<number[]>([]);

const isAllSelected = computed(() => {
    const pageIds = paginatedSalaries.value
        .filter((s) => s.status === 'draft')
        .map((s) => s.id);

    if (pageIds.length === 0) {
        return false;
    }

    return pageIds.every((id) => selectedIds.value.includes(id));
});

function toggleSelectAll() {
    const pageIds = paginatedSalaries.value
        .filter((s) => s.status === 'draft')
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

// ── Bulk Salary Approval ──────────────────────────────────────────────────────

const bulkApproving = ref(false);
const showBulkApproveModal = ref(false);

function openBulkApproveModal() {
    if (selectedIds.value.length === 0) {
        return;
    }

    showBulkApproveModal.value = true;
}

function submitBulkApprove() {
    if (selectedIds.value.length === 0 || bulkApproving.value) {
        return;
    }

    bulkApproving.value = true;
    router.post(
        '/salaries/approve-bulk',
        { salary_ids: selectedIds.value },
        {
            onSuccess: () => {
                toast.success(
                    `Đã phê duyệt thành công ${selectedIds.value.length} bảng lương!`,
                );
                selectedIds.value = [];
                showBulkApproveModal.value = false;
            },
            onError: () =>
                toast.error('Có lỗi xảy ra khi phê duyệt hàng loạt.'),
            onFinish: () => {
                bulkApproving.value = false;
            },
        },
    );
}

// ── Pay Stub Preview Modal ─────────────────────────────────────────────────────

const payStubTarget = ref<SalaryRow | null>(null);

function openPayStub(salary: SalaryRow) {
    payStubTarget.value = salary;
}

function closePayStub() {
    payStubTarget.value = null;
}

function printPayStub() {
    window.print();
}

const showBulkDialog = ref(false);
const bulkForm = useForm({
    salary_ids: [] as number[],
    type: 'bonus',
    amount: '',
    reason: '',
});

function openBulkDialog() {
    if (selectedIds.value.length === 0) {
        return;
    }

    bulkForm.reset();
    showBulkDialog.value = true;
}

function submitBulkAdj() {
    if (bulkForm.processing) {
        return;
    }

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
    if (adjForm.processing) {
        return;
    }

    if (!adjTarget.value) {
        return;
    }

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
                <div class="relative flex items-center gap-2">
                    <Label
                        for="sal-period"
                        class="shrink-0 text-xs font-semibold text-slate-600 dark:text-slate-300"
                    >
                        Chọn tháng:
                    </Label>

                    <div class="relative">
                        <button
                            type="button"
                            id="sal-period"
                            @click="toggleMonthPicker"
                            class="flex h-9 min-w-[150px] items-center justify-between gap-2 rounded-lg border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800 shadow-xs transition hover:bg-slate-50 focus:border-indigo-500 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800 cursor-pointer"
                        >
                            <span class="flex items-center gap-1.5">
                                <Calendar class="size-3.5 text-indigo-600 dark:text-indigo-400" />
                                {{ formatPeriodVietnamese(activePeriod) }}
                            </span>
                            <ChevronDown class="size-3.5 text-slate-400" />
                        </button>

                        <!-- Overlay backdrop -->
                        <Teleport to="body">
                        <div
                            v-if="showMonthPicker"
                            class="fixed inset-0 z-40"
                            @click="showMonthPicker = false"
                        />

                        <!-- Popover Month Picker Panel -->
                        <div
                            v-if="showMonthPicker"
                            class="absolute right-0 top-full z-50 mt-1.5 w-64 rounded-xl border border-slate-200 bg-white p-3 shadow-xl backdrop-blur-md dark:border-slate-800 dark:bg-slate-900 animate-in fade-in zoom-in-95"
                        >
                            <!-- Year Header -->
                            <div class="mb-2 flex items-center justify-between border-b border-slate-100 pb-2 dark:border-slate-800">
                                <button
                                    type="button"
                                    @click.stop="pickerYear--"
                                    class="rounded-md p-1 text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white cursor-pointer"
                                >
                                    <ChevronLeft class="size-4" />
                                </button>
                                <span class="text-sm font-bold text-slate-800 dark:text-slate-100">Năm {{ pickerYear }}</span>
                                <button
                                    type="button"
                                    @click.stop="pickerYear++"
                                    class="rounded-md p-1 text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white cursor-pointer"
                                >
                                    <ChevronRight class="size-4" />
                                </button>
                            </div>

                            <!-- Months Grid -->
                            <div class="grid grid-cols-3 gap-1.5">
                                <button
                                    v-for="m in 12"
                                    :key="m"
                                    type="button"
                                    @click.stop="selectMonth(m)"
                                    class="rounded-lg py-2 text-xs font-semibold transition cursor-pointer"
                                    :class="[
                                        isCurrentSelected(m)
                                            ? 'bg-indigo-600 font-bold text-white shadow-xs'
                                            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white'
                                    ]"
                                >
                                    Tháng {{ m < 10 ? '0' + m : m }}
                                </button>
                            </div>

                            <!-- Bottom Fast Action -->
                            <div class="mt-2.5 flex items-center justify-between border-t border-slate-100 pt-2 text-[11px] dark:border-slate-800">
                                <button
                                    type="button"
                                    @click.stop="selectCurrentMonth"
                                    class="font-semibold text-indigo-600 hover:underline dark:text-indigo-400 cursor-pointer"
                                >
                                    Tháng này
                                </button>
                                <button
                                    type="button"
                                    @click.stop="showMonthPicker = false"
                                    class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 cursor-pointer"
                                >
                                    Đóng
                                </button>
                            </div>
                        </div>
                        </Teleport>
                    </div>
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
                    {{ generating ? 'Đang tính...' : 'Tạo / tính lại bảng lương' }}
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
                        {{ totals.headcount }} / {{ generation.eligible_employees }}
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

        <!-- Payroll status & calculation sources -->
        <div class="grid gap-4 lg:grid-cols-[1.1fr_1fr]">
            <Card class="border-indigo-100 bg-indigo-50/40 shadow-xs dark:border-indigo-950/30 dark:bg-indigo-950/10">
                <CardContent class="p-4">
                    <div class="flex items-start gap-3">
                        <Calculator class="mt-0.5 size-5 shrink-0 text-indigo-600 dark:text-indigo-400" />
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-indigo-950 dark:text-indigo-100">Cách hệ thống tính kỳ {{ formatPeriodVietnamese(period) }}</p>
                            <p class="mt-1 text-xs leading-5 text-indigo-900/70 dark:text-indigo-200/70">
                                Lương gốc lấy từ hợp đồng, chấm công ca hoàn thành, OT đã duyệt và loại trả công của từng nhân viên.
                                Thưởng/khấu trừ chỉ được tính khi khoản điều chỉnh ở trạng thái đã áp dụng.
                            </p>
                            <div class="mt-3 flex flex-wrap gap-2 text-[10px] font-semibold text-indigo-900/80 dark:text-indigo-100/80">
                                <span class="rounded-full bg-white/70 px-2 py-1 dark:bg-indigo-950/40">Chấm công</span>
                                <span class="rounded-full bg-white/70 px-2 py-1 dark:bg-indigo-950/40">Nghỉ phép</span>
                                <span class="rounded-full bg-white/70 px-2 py-1 dark:bg-indigo-950/40">OT đã duyệt</span>
                                <span class="rounded-full bg-white/70 px-2 py-1 dark:bg-indigo-950/40">KPI & điều chỉnh</span>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card class="shadow-xs">
                <CardContent class="p-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-bold">Trạng thái kỳ lương</p>
                            <p class="mt-1 text-xs text-muted-foreground">{{ generation.salary_rows }} / {{ generation.eligible_employees }} nhân viên đã có bảng lương</p>
                        </div>
                        <Clock class="size-5 text-muted-foreground" />
                    </div>
                    <div class="mt-4 grid grid-cols-3 gap-2 text-center text-xs">
                        <div class="rounded-lg bg-slate-100 p-2 dark:bg-slate-800"><p class="font-bold text-slate-700 dark:text-slate-200">{{ generation.status_counts.draft }}</p><p class="mt-0.5 text-muted-foreground">Nháp</p></div>
                        <div class="rounded-lg bg-indigo-50 p-2 dark:bg-indigo-950/30"><p class="font-bold text-indigo-700 dark:text-indigo-300">{{ generation.status_counts.approved }}</p><p class="mt-0.5 text-muted-foreground">Đã duyệt</p></div>
                        <div class="rounded-lg bg-emerald-50 p-2 dark:bg-emerald-950/30"><p class="font-bold text-emerald-700 dark:text-emerald-300">{{ generation.status_counts.paid }}</p><p class="mt-0.5 text-muted-foreground">Đã trả</p></div>
                    </div>
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
                        {{ generation.eligible_employees > 0 ? 'Chưa tạo bảng lương cho kỳ này' : 'Chưa có nhân viên đủ điều kiện tính lương' }}
                    </p>
                    <p class="mx-auto max-w-sm text-xs text-slate-500">
                        {{ generation.eligible_employees > 0
                            ? `Có ${generation.eligible_employees} nhân viên đang hoạt động. Nhấn “Tạo / tính lại bảng lương” để sinh bản nháp từ dữ liệu chấm công, OT và điều chỉnh đã duyệt.`
                            : 'Hãy kiểm tra trạng thái nhân viên, chi nhánh đang chọn và kỳ lương trước khi tạo bảng.' }}
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
                                    v-if="canApprove && s.status === 'draft'"
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
                                    <p
                                        class="truncate text-sm font-bold text-slate-900 dark:text-white"
                                    >
                                        {{ s.employee_name }}
                                    </p>
                                    <TrustScoreBadge
                                        :score="s.trust_score"
                                        :show-label="false"
                                    />
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
                                        KHIẾU NẠI
                                    </span>
                                </div>
                                <div
                                    class="mt-1 flex items-center gap-2 text-[10px] font-medium text-slate-400"
                                >
                                    <span>{{
                                        s.job_title || 'Nhân viên'
                                    }}</span>
                                    <span>·</span>
                                    <span
                                        class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 font-bold"
                                        :class="{
                                            'bg-indigo-50 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300':
                                                s.compensation_type === 'fixed',
                                            'bg-purple-50 text-purple-700 dark:bg-purple-950/40 dark:text-purple-300':
                                                s.compensation_type ===
                                                'hourly',
                                            'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300':
                                                s.compensation_type === 'shift',
                                        }"
                                    >
                                        {{
                                            s.breakdown
                                                ?.compensation_type_label ||
                                            'Lương cố định'
                                        }}
                                    </span>
                                </div>
                            </div>

                            <!-- Lương cơ bản -->
                            <div
                                class="hidden text-right font-mono text-slate-600 lg:block dark:text-slate-300"
                            >
                                <p class="text-sm font-bold">
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
                            enter-to-class="opacity-100 max-h-[800px]"
                            leave-active-class="transition-all duration-150 ease-in"
                            leave-from-class="opacity-100 max-h-[800px]"
                            leave-to-class="opacity-0 max-h-0"
                        >
                            <div
                                v-if="expandedId === s.id"
                                class="overflow-hidden border-t border-slate-100 bg-slate-50/50 px-5 py-5 dark:border-slate-800 dark:bg-slate-900/30"
                            >
                                <div class="grid gap-5 lg:grid-cols-3">
                                    <!-- KHỐI 1: Bảng Giải Trình Công Thức Tính Lương Gốc -->
                                    <div
                                        class="flex flex-col justify-between space-y-3 rounded-2xl border border-indigo-100 bg-white p-4 shadow-sm dark:border-indigo-900/30 dark:bg-slate-950"
                                    >
                                        <div>
                                            <div
                                                class="flex items-center justify-between border-b border-slate-100 pb-2 dark:border-slate-800"
                                            >
                                                <p
                                                    class="flex items-center gap-1.5 text-xs font-bold text-indigo-700 dark:text-indigo-300"
                                                >
                                                    <Calculator
                                                        class="size-4 text-indigo-600"
                                                    />
                                                    Giải Trình Công Thức Lương
                                                    Gốc
                                                </p>
                                                <span
                                                    class="rounded bg-indigo-50 px-2 py-0.5 text-[10px] font-black text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300"
                                                >
                                                    {{
                                                        s.breakdown
                                                            ?.compensation_type_label
                                                    }}
                                                </span>
                                            </div>

                                            <!-- Formular text callout -->
                                            <div
                                                class="mt-3 rounded-xl border border-indigo-200/60 bg-indigo-50/50 p-3 font-mono text-xs font-bold text-indigo-900 dark:border-indigo-900/40 dark:bg-indigo-950/30 dark:text-indigo-200"
                                            >
                                                <p
                                                    class="text-[10px] font-bold tracking-wider text-indigo-500 uppercase"
                                                >
                                                    Công thức thực tính:
                                                </p>
                                                <p
                                                    class="mt-1 text-xs leading-relaxed"
                                                >
                                                    {{
                                                        s.breakdown
                                                            ?.formula_text ||
                                                        'Chưa có thông tin giải trình'
                                                    }}
                                                </p>
                                            </div>

                                            <p v-if="s.breakdown?.policy_note" class="mt-2 text-[10px] leading-4 text-muted-foreground">
                                                {{ s.breakdown.policy_note }}
                                            </p>

                                            <!-- Mini indicators -->
                                            <div
                                                class="mt-3 grid grid-cols-2 gap-2 text-xs"
                                            >
                                                <div
                                                    class="rounded-lg bg-slate-50 p-2.5 dark:bg-slate-900"
                                                >
                                                    <p
                                                        class="text-[10px] font-medium text-slate-400"
                                                    >
                                                        Mức lương / Đơn giá
                                                    </p>
                                                    <p
                                                        class="mt-0.5 font-mono font-bold text-slate-800 dark:text-slate-200"
                                                    >
                                                        {{
                                                            s.compensation_type ===
                                                            'fixed'
                                                                ? vnd(
                                                                      s.contract_base_salary,
                                                                  )
                                                                : vnd(
                                                                      s.pay_rate,
                                                                  ) +
                                                                  (s.compensation_type ===
                                                                  'hourly'
                                                                      ? '/h'
                                                                      : '/ca')
                                                        }}
                                                    </p>
                                                </div>
                                                <div
                                                    v-if="
                                                        s.compensation_type ===
                                                        'fixed'
                                                    "
                                                    class="rounded-lg bg-slate-50 p-2.5 dark:bg-slate-900"
                                                >
                                                    <p
                                                        class="text-[10px] font-medium text-slate-400"
                                                    >
                                                        Công tính lương
                                                    </p>
                                                    <p
                                                        class="mt-0.5 font-mono font-bold text-slate-800 dark:text-slate-200"
                                                    >
                                                        {{
                                                            s.breakdown
                                                                ?.total_paid_days
                                                        }}/{{
                                                            s.breakdown
                                                                ?.standard_days
                                                        }}
                                                        ngày
                                                        <span
                                                            class="text-[9px] font-normal text-muted-foreground"
                                                            >({{
                                                                s.breakdown
                                                                    ?.actual_work_days
                                                            }}
                                                            làm +
                                                            {{
                                                            s.breakdown
                                                                    ?.paid_leave_days
                                                            }}
                                                            phép)</span
                                                        >
                                                        <span
                                                            v-if="s.breakdown?.unpaid_leave_days"
                                                            class="mt-1 block text-[9px] font-normal text-rose-500"
                                                        >
                                                            {{ s.breakdown.unpaid_leave_days }} ngày nghỉ không lương
                                                        </span>
                                                    </p>
                                                </div>
                                                <div
                                                    v-else-if="
                                                        s.compensation_type ===
                                                        'hourly'
                                                    "
                                                    class="rounded-lg bg-slate-50 p-2.5 dark:bg-slate-900"
                                                >
                                                    <p
                                                        class="text-[10px] font-medium text-slate-400"
                                                    >
                                                        Giờ làm & OT
                                                    </p>
                                                    <p
                                                        class="mt-0.5 font-mono font-bold text-slate-800 dark:text-slate-200"
                                                    >
                                                        {{
                                                            s.breakdown
                                                                ?.regular_hours
                                                        }}h
                                                        <span
                                                            class="text-amber-600"
                                                            >(+{{
                                                                s.breakdown
                                                                    ?.ot_hours
                                                            }}h OT duyệt<span v-if="s.breakdown?.unapproved_ot_hours">, {{ s.breakdown.unapproved_ot_hours }}h ngoài ca chưa duyệt</span>)</span
                                                        >
                                                    </p>
                                                    <p
                                                        v-if="s.breakdown?.unpaid_leave_days"
                                                        class="mt-1 text-[9px] font-normal text-rose-500"
                                                    >
                                                        {{ s.breakdown.unpaid_leave_days }} ngày nghỉ không lương
                                                    </p>
                                                </div>
                                                <div
                                                    v-else
                                                    class="rounded-lg bg-slate-50 p-2.5 dark:bg-slate-900"
                                                >
                                                    <p
                                                        class="text-[10px] font-medium text-slate-400"
                                                    >
                                                        Số ca hoàn thành
                                                    </p>
                                                    <p
                                                        class="mt-0.5 font-mono font-bold text-slate-800 dark:text-slate-200"
                                                    >
                                                        {{
                                                            s.breakdown
                                                                ?.completed_shifts_count
                                                        }}
                                                        ca
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- KHỐI 2: Cơ cấu lương thực nhận -->
                                    <div
                                        class="space-y-3 rounded-2xl border bg-white p-4 shadow-sm dark:bg-slate-950"
                                    >
                                        <p
                                            class="border-b pb-2 text-xs font-bold text-slate-700 dark:text-slate-300"
                                        >
                                            Cơ Cấu Lương Thực Nhận
                                        </p>
                                        <div
                                            class="space-y-2 text-xs font-semibold"
                                        >
                                            <div
                                                class="flex justify-between text-slate-600 dark:text-slate-300"
                                            >
                                                <span class="font-medium"
                                                    >Lương gốc thực tính</span
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
                                                    >Tổng thưởng (+)</span
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
                                                    >Tổng khấu trừ (-)</span
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
                                                    class="font-mono text-base font-black text-indigo-600 dark:text-indigo-400"
                                                >
                                                    {{ vnd(s.net_salary) }}
                                                </span>
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

                                    <!-- KHỐI 3: Điều chỉnh & Thao tác -->
                                    <div
                                        class="flex flex-col justify-between space-y-3 rounded-2xl border bg-white p-4 shadow-sm dark:bg-slate-950"
                                    >
                                        <div>
                                            <div
                                                class="flex items-center justify-between border-b pb-2"
                                            >
                                                <p
                                                    class="text-xs font-bold text-slate-700 dark:text-slate-300"
                                                >
                                                    Điều Chỉnh Lương ({{
                                                        s.adjustments.length
                                                    }})
                                                </p>
                                                <button
                                                    v-if="s.status === 'draft'"
                                                    @click="openAdjDialog(s)"
                                                    class="inline-flex items-center gap-1 text-[10px] font-bold text-indigo-600 hover:underline"
                                                >
                                                    <Plus class="size-3" /> Thêm
                                                    khoản
                                                </button>
                                            </div>

                                            <div
                                                v-if="
                                                    s.adjustments.length === 0
                                                "
                                                class="py-3 text-xs text-slate-400 italic"
                                            >
                                                Không có khoản điều chỉnh nào
                                                trong tháng.
                                            </div>
                                            <div
                                                v-else
                                                class="mt-2 max-h-[140px] space-y-2 overflow-y-auto pr-1"
                                            >
                                                <div
                                                    v-for="a in s.adjustments"
                                                    :key="a.id"
                                                    class="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50/50 px-2.5 py-1.5 text-xs dark:border-slate-800 dark:bg-slate-900/30"
                                                >
                                                    <div class="min-w-0 pr-2">
                                                        <span
                                                            class="rounded px-1.5 py-0.5 text-[9px] font-bold uppercase"
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
                                                            class="mt-0.5 truncate text-[11px] font-medium text-slate-600 dark:text-slate-300"
                                                            :title="a.reason"
                                                        >
                                                            {{ a.reason }}
                                                        </p>
                                                        <span
                                                            v-if="a.status !== 'applied'"
                                                            class="mt-1 inline-flex rounded px-1.5 py-0.5 text-[9px] font-semibold"
                                                            :class="adjStatusConfig[a.status].cls"
                                                        >
                                                            {{ adjStatusConfig[a.status].label }}
                                                        </span>
                                                    </div>
                                                    <span
                                                        class="shrink-0 font-mono text-xs font-bold"
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
                                            </div>
                                        </div>

                                        <!-- Action Buttons inside expanded -->
                                        <div
                                            class="flex items-center gap-2 border-t pt-3"
                                        >
                                            <Button
                                                v-if="
                                                    canApprove &&
                                                    s.status === 'draft'
                                                "
                                                size="sm"
                                                class="h-8 flex-1 rounded-xl bg-indigo-600 text-xs font-bold text-white hover:bg-indigo-700"
                                                @click="approveSalary(s)"
                                            >
                                                <Check class="mr-1 size-3.5" />
                                                Duyệt bảng lương
                                            </Button>
                                            <Button
                                                v-if="
                                                    canApprove &&
                                                    s.status === 'approved'
                                                "
                                                size="sm"
                                                class="h-8 flex-1 rounded-xl bg-emerald-600 text-xs font-bold text-white hover:bg-emerald-700"
                                                @click="markPaid(s)"
                                            >
                                                <BadgeDollarSign
                                                    class="mr-1 size-3.5"
                                                />
                                                Đánh dấu đã chi trả
                                            </Button>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                class="h-8 rounded-xl border-slate-200 text-xs font-bold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300"
                                                @click="openPayStub(s)"
                                            >
                                                <Printer
                                                    class="mr-1 size-3.5"
                                                />
                                                Phiếu lương
                                            </Button>
                                        </div>
                                    </div>
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
                            <option value="advance">
                                Tạm ứng lương (tối đa 50% lương tích lũy)
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
                            <option value="advance">
                                Tạm ứng lương đồng loạt (tối đa 50% lương tích lũy)
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

    <!-- ══ Floating Bulk Action Bar ════════════════════════════════════════════ -->
    <div
        v-if="selectedIds.length > 0 && canApprove"
        class="fixed bottom-6 left-1/2 z-40 flex -translate-x-1/2 animate-in items-center gap-4 rounded-2xl border border-indigo-200 bg-slate-900 px-6 py-3.5 text-white shadow-2xl transition-all slide-in-from-bottom-5 fade-in dark:border-indigo-900"
    >
        <div class="flex items-center gap-2">
            <span
                class="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-600 text-xs font-black"
            >
                {{ selectedIds.length }}
            </span>
            <span class="text-xs font-bold text-slate-200">
                bảng lương đã chọn
            </span>
        </div>

        <div class="flex items-center gap-2">
            <Button
                size="sm"
                class="h-8 rounded-xl bg-indigo-600 px-3 text-xs font-bold text-white hover:bg-indigo-500"
                @click="openBulkApproveModal"
            >
                <Check class="mr-1 size-3.5" />
                Phê duyệt hàng loạt
            </Button>
            <Button
                size="sm"
                variant="outline"
                class="h-8 rounded-xl border-slate-700 bg-slate-800 px-3 text-xs font-bold text-slate-200 hover:bg-slate-700"
                @click="openBulkDialog"
            >
                <Plus class="mr-1 size-3.5" />
                Điều chỉnh hàng loạt
            </Button>
            <button
                @click="selectedIds = []"
                class="ml-2 rounded-lg p-1.5 text-slate-400 hover:bg-slate-800 hover:text-white"
                title="Bỏ chọn tất cả"
            >
                <X class="size-4" />
            </button>
        </div>
    </div>

    <!-- ══ Bulk Approve Modal ══════════════════════════════════════════════════ -->
    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        
        <div
            v-if="showBulkApproveModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
            @click.self="showBulkApproveModal = false"
        >
            <Card
                class="w-full max-w-md overflow-hidden rounded-3xl border border-indigo-200 bg-card p-6 shadow-2xl"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/40"
                    >
                        <Check class="size-6 text-indigo-600" />
                    </div>
                    <div>
                        <h2 class="text-base font-extrabold text-foreground">
                            Xác Nhận Phê Duyệt Hàng Loạt
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            Phê duyệt {{ selectedIds.length }} bảng lương đã
                            chọn
                        </p>
                    </div>
                </div>

                <div
                    class="mt-4 rounded-xl border bg-muted/40 p-3 text-xs leading-relaxed text-muted-foreground"
                >
                    Sau khi phê duyệt, thông báo xác nhận sẽ được gửi tới tài
                    khoản từng nhân viên. Bạn có chắc chắn muốn duyệt
                    {{ selectedIds.length }} phiếu lương này?
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <Button
                        variant="outline"
                        size="sm"
                        class="rounded-xl"
                        @click="showBulkApproveModal = false"
                    >
                        Hủy
                    </Button>
                    <Button
                        size="sm"
                        class="rounded-xl bg-indigo-600 font-bold text-white hover:bg-indigo-700"
                        :disabled="bulkApproving"
                        @click="submitBulkApprove"
                    >
                        {{
                            bulkApproving ? 'Đang duyệt...' : 'Đồng ý phê duyệt'
                        }}
                    </Button>
                </div>
            </Card>
        </div>
        
    </Transition>

    <!-- ══ Pay Stub Preview Modal ═══════════════════════════════════════════════ -->
    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <Teleport to="body">
        <div
            v-if="payStubTarget"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs print:bg-white print:p-0"
            @click.self="closePayStub"
        >
            <div
                class="relative w-full max-w-2xl overflow-hidden rounded-3xl border bg-card p-6 shadow-2xl dark:border-slate-800 print:border-0 print:p-8 print:shadow-none"
            >
                <!-- Close & Print buttons -->
                <div
                    class="flex items-center justify-between border-b pb-4 print:hidden"
                >
                    <div class="flex items-center gap-2">
                        <Printer class="size-5 text-indigo-600" />
                        <h2 class="text-base font-extrabold text-foreground">
                            Phiếu Lương Chi Tiết — Kỳ {{ period }}
                        </h2>
                    </div>
                    <div class="flex items-center gap-2">
                        <Button
                            size="sm"
                            variant="outline"
                            class="h-8 rounded-xl text-xs font-bold"
                            @click="printPayStub"
                        >
                            <Printer class="mr-1 size-3.5" /> In phiếu lương
                        </Button>
                        <button
                            @click="closePayStub"
                            class="rounded-full p-1 text-muted-foreground hover:bg-muted"
                        >
                            <X class="size-5" />
                        </button>
                    </div>
                </div>

                <!-- Pay Stub Content -->
                <div class="mt-4 space-y-5 text-xs text-foreground">
                    <!-- Title banner -->
                    <div class="border-b pb-4 text-center">
                        <h1
                            class="text-xl font-black tracking-tight text-indigo-950 uppercase dark:text-indigo-200"
                        >
                            PHIẾU LƯƠNG NHÂN VIÊN
                        </h1>
                        <p class="mt-1 font-semibold text-muted-foreground">
                            Kỳ lương: Tháng {{ period }}
                        </p>
                    </div>

                    <!-- Employee metadata -->
                    <div
                        class="grid grid-cols-2 gap-4 rounded-2xl border bg-muted/30 p-4"
                    >
                        <div>
                            <p
                                class="text-[10px] font-bold text-muted-foreground uppercase"
                            >
                                Họ và tên nhân viên:
                            </p>
                            <p
                                class="mt-0.5 text-sm font-black text-foreground"
                            >
                                {{ payStubTarget.employee_name }}
                            </p>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                Mã NV: {{ payStubTarget.employee_code }}
                            </p>
                        </div>
                        <div>
                            <p
                                class="text-[10px] font-bold text-muted-foreground uppercase"
                            >
                                Chức vụ / Hợp đồng:
                            </p>
                            <p class="mt-0.5 text-sm font-bold text-foreground">
                                {{ payStubTarget.job_title || 'Nhân viên' }}
                            </p>
                            <p
                                class="mt-0.5 text-xs font-bold text-indigo-600 dark:text-indigo-400"
                            >
                                {{
                                    payStubTarget.breakdown
                                        ?.compensation_type_label
                                }}
                            </p>
                        </div>
                    </div>

                    <!-- Formula breakdown box -->
                    <div
                        class="rounded-2xl border border-indigo-200 bg-indigo-50/50 p-4 dark:border-indigo-900/40 dark:bg-indigo-950/20"
                    >
                        <p
                            class="text-[10px] font-bold tracking-wider text-indigo-600 uppercase"
                        >
                            Giải trình công thức tính lương gốc:
                        </p>
                        <p
                            class="mt-1 font-mono text-xs leading-relaxed font-bold text-indigo-950 dark:text-indigo-200"
                        >
                            {{ payStubTarget.breakdown?.formula_text }}
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-2 rounded-2xl border bg-card p-4 text-[11px] sm:grid-cols-4">
                        <div><p class="text-muted-foreground">Ngày làm</p><p class="mt-1 font-bold">{{ payStubTarget.breakdown?.actual_work_days }}</p></div>
                        <div><p class="text-muted-foreground">Nghỉ hưởng lương</p><p class="mt-1 font-bold">{{ payStubTarget.breakdown?.paid_leave_days }} ngày</p></div>
                        <div><p class="text-muted-foreground">Nghỉ không lương</p><p class="mt-1 font-bold">{{ payStubTarget.breakdown?.unpaid_leave_days ?? 0 }} ngày</p></div>
                        <div><p class="text-muted-foreground">OT được duyệt</p><p class="mt-1 font-bold">{{ payStubTarget.breakdown?.ot_hours ?? 0 }} giờ</p></div>
                    </div>

                    <!-- Earnings & Deductions Table -->
                    <div class="space-y-2 rounded-2xl border bg-card p-4">
                        <p
                            class="text-xs font-bold tracking-wider text-foreground uppercase"
                        >
                            Chi Tiết Thu Nhập & Khấu Trừ
                        </p>
                        <div class="space-y-1.5 pt-2">
                            <div
                                class="flex justify-between border-b border-dashed py-1"
                            >
                                <span>Lương gốc thực tính</span>
                                <span class="font-mono font-bold">{{
                                    vnd(payStubTarget.base_salary)
                                }}</span>
                            </div>
                            <div
                                v-if="payStubTarget.bonus_amount > 0"
                                class="flex justify-between border-b border-dashed py-1 text-emerald-600"
                            >
                                <span>Tổng cộng các khoản thưởng (+)</span>
                                <span class="font-mono font-bold"
                                    >+{{
                                        vnd(payStubTarget.bonus_amount)
                                    }}</span
                                >
                            </div>
                            <div
                                v-if="payStubTarget.overtime_amount > 0"
                                class="flex justify-between border-b border-dashed py-1 text-indigo-600"
                            >
                                <span>{{ payStubTarget.breakdown?.compensation_type === 'hourly' ? 'Tiền tăng ca được duyệt (đã nằm trong lương gốc)' : 'Tiền tăng ca được duyệt (+)' }}</span>
                                <span class="font-mono font-bold">{{ payStubTarget.breakdown?.compensation_type === 'hourly' ? '' : '+' }}{{ vnd(payStubTarget.overtime_amount) }}</span>
                            </div>
                            <div
                                v-if="payStubTarget.deduction_amount > 0"
                                class="flex justify-between border-b border-dashed py-1 text-rose-600"
                            >
                                <span>Tổng cộng các khoản khấu trừ (-)</span>
                                <span class="font-mono font-bold"
                                    >-{{
                                        vnd(payStubTarget.deduction_amount)
                                    }}</span
                                >
                            </div>
                            <div
                                class="flex justify-between py-2 text-sm font-black text-indigo-600 dark:text-indigo-400"
                            >
                                <span>THỰC LÃNH (NET SALARY)</span>
                                <span class="font-mono text-base">{{
                                    vnd(payStubTarget.net_salary)
                                }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Signature line -->
                    <div class="grid grid-cols-2 pt-8 text-center print:pt-12">
                        <div>
                            <p class="text-xs font-bold">Người Duyệt Lương</p>
                            <p class="mt-1 text-[10px] text-muted-foreground">
                                (Ký & ghi rõ họ tên)
                            </p>
                        </div>
                        <div>
                            <p class="text-xs font-bold">Người Nhận Lương</p>
                            <p class="mt-1 text-[10px] text-muted-foreground">
                                (Ký & ghi rõ họ tên)
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </Teleport>
    </Transition>
</template>
