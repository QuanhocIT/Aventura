<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Wallet,
    TrendingDown,
    TrendingUp,
    Check,
    ChevronDown,
    Plus,
    BadgeDollarSign,
    X,
    Search,
    Download,
    Building2,
    UserCog,
    ChevronLeft,
    ChevronRight,
    Calculator,
    Printer,
    Calendar,
    Send,
    Landmark,
    Percent,
    FileText,
    CreditCard,
    Utensils,
    Heart,
} from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import { toast } from 'vue-sonner';
import TrustScoreBadge from '@/components/employees/TrustScoreBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
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
    salary_calculation_method?: 'standard_days' | 'fixed_package';
    compensation_type_label: string;
    contract_salary: number;
    pay_rate: number;
    standard_days: number;
    days_in_month: number;
    actual_work_days: number;
    paid_leave_days: number;
    unpaid_leave_days: number;
    total_paid_days: number;
    daily_rate: number;
    completed_shifts_count: number;
    regular_hours: number;
    ot_hours: number;
    ot_multiplier: number;
    unapproved_ot_hours?: number;
    regular_amount?: number;
    overtime_amount?: number;
    overtime_hourly_rate?: number;
    allowances?: {
        total: number;
        meal: number;
        transport: number;
        phone: number;
        responsibility: number;
        other: number;
    };
    night_shift?: {
        hours: number;
        hourly_rate: number;
        multiplier: number;
        amount: number;
    };
    late_penalties?: {
        late_count: number;
        total_minutes: number;
        penalty_amount: number;
    };
    advance_amount?: number;
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
    salary_calculation_method: 'standard_days' | 'fixed_package';
    pay_rate: number;
    contract_base_salary: number;
    trust_score: number;
    branch_id: number | null;
    branch_name?: string | null;
    hire_date?: string | null;
    bank_name: string | null;
    bank_account_number: string | null;
    bank_account_name: string | null;
    base_salary: number;
    allowance_amount: number;
    bonus_amount: number;
    overtime_amount: number;
    night_shift_amount: number;
    late_penalty_amount: number;
    deduction_amount: number;
    advance_amount: number;
    actual_work_days: number;
    standard_days: number;
    paid_leave_days: number;
    unpaid_leave_days: number;
    net_salary: number;
    status: 'draft' | 'approved' | 'paid';
    paid_at: string | null;
    email_sent_at: string | null;
    breakdown: SalaryBreakdown;
    adjustments: Adjustment[];
    approved_by_name?: string | null;
    created_at?: string | null;
};

type Totals = {
    total_payroll: number;
    total_base: number;
    total_allowances: number;
    total_overtime: number;
    total_night_shift: number;
    total_deductions: number;
    total_advances: number;
    total_bonuses: number;
    headcount: number;
    period_revenue: number;
    labor_cost_ratio: number;
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
        cls: 'bg-slate-100 text-slate-700 border border-slate-300 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700',
    },
    approved: {
        label: 'Đã duyệt',
        cls: 'bg-indigo-50 text-indigo-700 border border-indigo-200 dark:bg-indigo-950/40 dark:text-indigo-300 dark:border-indigo-900/30',
    },
    paid: {
        label: 'Đã chi trả',
        cls: 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-900/30',
    },
};

const adjTypeLabel: Record<AdjType, string> = {
    bonus: 'Thưởng',
    penalty: 'Phạt',
    cash_shortage: 'Thiếu quỹ',
    inventory_loss: 'Hao hụt kho',
    violation: 'Vi phạm',
    advance: 'Tạm ứng',
};

const adjTypeColor: Record<AdjType, string> = {
    bonus: 'text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-100',
    penalty: 'text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/20 border border-rose-100',
    cash_shortage: 'text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/20 border border-rose-100',
    inventory_loss: 'text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/20 border border-amber-100',
    violation: 'text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/20 border border-rose-100',
    advance: 'text-orange-600 dark:text-orange-400 bg-orange-50 dark:bg-orange-950/20 border border-orange-100',
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
const compTypeFilter = ref<string>('all');

// Phân trang
const currentPage = ref(1);
const itemsPerPage = ref(10);

watch([searchQuery, statusFilter, branchFilter, compTypeFilter], () => {
    currentPage.value = 1;
});

const filteredSalaries = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();

    return props.salaries.filter((s) => {
        const matchStatus = statusFilter.value === 'all' || s.status === statusFilter.value;
        const matchBranch = branchFilter.value === 'all' || s.branch_id === Number(branchFilter.value);
        const matchCompType = compTypeFilter.value === 'all' || s.compensation_type === compTypeFilter.value;

        if (!matchStatus || !matchBranch || !matchCompType) {
            return false;
        }

        if (!q) {
            return true;
        }

        return (
            s.employee_name.toLowerCase().includes(q) ||
            s.employee_code.toLowerCase().includes(q) ||
            s.job_title.toLowerCase().includes(q)
        );
    });
});

const totalPages = computed(() => Math.ceil(filteredSalaries.value.length / itemsPerPage.value) || 1);

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

function applyPeriod() {
    router.get('/salaries', { period: activePeriod.value }, { preserveScroll: true });
}

// ── Generate drafts ───────────────────────────────────────────────────────────

const generating = ref(false);
const generateForm = useForm({ period: props.period });

function generateDrafts() {
    generating.value = true;
    generateForm.period = activePeriod.value;
    generateForm.post('/salaries/generate', {
        onSuccess: () => toast.success('Đã tính toán và đồng bộ bảng lương theo công thực tế!'),
        onError: () => toast.error('Có lỗi khi tính toán bảng lương.'),
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
            onSuccess: () => toast.success(`Đã phê duyệt bảng lương cho ${salary.employee_name}.`),
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
            onSuccess: () => toast.success(`Đã xác nhận thanh toán lương cho ${salary.employee_name}.`),
            onError: () => toast.error('Có lỗi khi cập nhật trạng thái chi trả.'),
            onFinish: () => {
                actionProcessing.value = false;
            },
        },
    );
}

// ── Selection & Bulk Actions ──────────────────────────────────────────────────

const selectedIds = ref<number[]>([]);

const isAllSelected = computed(() => {
    const pageIds = paginatedSalaries.value.map((s) => s.id);

    if (pageIds.length === 0) {
        return false;
    }

    return pageIds.every((id) => selectedIds.value.includes(id));
});

function toggleSelectAll() {
    const pageIds = paginatedSalaries.value.map((s) => s.id);

    if (isAllSelected.value) {
        selectedIds.value = selectedIds.value.filter((id) => !pageIds.includes(id));
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

// ── Bulk Approval ─────────────────────────────────────────────────────────────

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
                toast.success(`Đã phê duyệt thành công ${selectedIds.value.length} bảng lương!`);
                selectedIds.value = [];
                showBulkApproveModal.value = false;
            },
            onError: () => toast.error('Có lỗi xảy ra khi phê duyệt hàng loạt.'),
            onFinish: () => {
                bulkApproving.value = false;
            },
        },
    );
}

// ── Bank Export Modal ─────────────────────────────────────────────────────────

const showBankExportModal = ref(false);
const selectedBankFormat = ref('vietcombank');

function triggerBankExport() {
    const url = `/salaries/export-bank?period=${activePeriod.value}&format=${selectedBankFormat.value}`;
    window.open(url, '_blank');
    showBankExportModal.value = false;
    toast.success('Đang tạo và tải xuống file chi lương ngân hàng...');
}

// ── Send Payslips (Email / Notification) ──────────────────────────────────────

const showSendPayslipModal = ref(false);
const sendingPayslips = ref(false);

function openSendPayslipModal() {
    showSendPayslipModal.value = true;
}

function submitSendPayslips() {
    const targetIds = selectedIds.value.length > 0 ? selectedIds.value : props.salaries.map((s) => s.id);

    if (targetIds.length === 0 || sendingPayslips.value) {
        return;
    }

    sendingPayslips.value = true;
    router.post(
        '/salaries/send-payslips',
        { salary_ids: targetIds },
        {
            onSuccess: () => {
                toast.success(`Đã gửi thành công phiếu lương qua Email & Portal cho ${targetIds.length} nhân viên.`);
                showSendPayslipModal.value = false;
                selectedIds.value = [];
            },
            onError: () => toast.error('Có lỗi xảy ra khi gửi phiếu lương.'),
            onFinish: () => {
                sendingPayslips.value = false;
            },
        },
    );
}

// ── Pay Stub & Payslip Modal ──────────────────────────────────────────────────

const detailSalary = ref<SalaryRow | null>(null);

function openDetailDrawer(salary: SalaryRow) {
    detailSalary.value = salary;
}

function closeDetailDrawer() {
    detailSalary.value = null;
}

function openPayStub(salary: SalaryRow) {
    detailSalary.value = salary;
}

function printPayslip() {
    window.print();
}

// ── Bulk Adjustment Dialog ────────────────────────────────────────────────────

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
            toast.success(pageProps.flash?.success ?? 'Đã áp dụng điều chỉnh lương hàng loạt thành công.');
            selectedIds.value = [];
            showBulkDialog.value = false;
        },
        onError: () => toast.error('Có lỗi xảy ra khi thực hiện cấn trừ hàng loạt.'),
    });
}

// ── Single Adjustment Dialog ──────────────────────────────────────────────────

const adjTarget = ref<SalaryRow | null>(null);
const adjForm = useForm({ type: 'bonus', amount: '', reason: '' });

function openAdjDialog(salary: SalaryRow) {
    adjTarget.value = salary;
    adjForm.reset();
}

function submitAdj() {
    if (adjForm.processing || !adjTarget.value) {
        return;
    }

    adjForm.post(`/salaries/${adjTarget.value.id}/adjustments`, {
        onSuccess: () => {
            const msg = (usePage().props.flash as any)?.success ?? 'Đã thêm điều chỉnh lương thành công.';
            toast.success(msg);
            adjTarget.value = null;
        },
        onError: () => toast.error('Có lỗi khi thêm điều chỉnh lương.'),
    });
}

// ── Formatting & Calculations ──────────────────────────────────────────────────

const formatMoney = (v: number | undefined | null) =>
    new Intl.NumberFormat('en-US').format(Math.round(v || 0));

const currentDate = new Date();
const todayDay = currentDate.getDate() < 10 ? `0${currentDate.getDate()}` : `${currentDate.getDate()}`;
const todayMonth = currentDate.getMonth() + 1 < 10 ? `0${currentDate.getMonth() + 1}` : `${currentDate.getMonth() + 1}`;
const todayYear = `${currentDate.getFullYear()}`.slice(-2);

function getPeriodParts(periodStr: string) {
    if (!periodStr) {
        const d = new Date();
        const m = d.getMonth() + 1;

        return {
            year: `${d.getFullYear()}`,
            month: m < 10 ? `0${m}` : `${m}`,
        };
    }

    const parts = periodStr.split('-');
    const year = parts[0] || '2026';
    const monthNum = parseInt(parts[1] || '1', 10);

    return {
        year,
        month: monthNum < 10 ? `0${monthNum}` : `${monthNum}`,
    };
}

function getTimeFormula(salary: SalaryRow): string {
    if (salary.compensation_type === 'hourly') {
        return `${salary.breakdown?.regular_hours ?? 0}h × ${formatMoney(salary.pay_rate)} đ/h`;
    }

    if (salary.compensation_type === 'shift') {
        return `${salary.breakdown?.completed_shifts_count ?? salary.actual_work_days} ca × ${formatMoney(salary.pay_rate)} đ/ca`;
    }

    const days = (salary.actual_work_days || 0) + (salary.paid_leave_days || 0);
    const standard = salary.standard_days || 26;
    const base = salary.contract_base_salary || salary.base_salary || 0;

    return `${days} / ${standard} ngày × ${formatMoney(base)}`;
}

function calculateTotalIncome(salary: SalaryRow): number {
    return (
        (salary.base_salary || 0) +
        (salary.allowance_amount || 0) +
        (salary.overtime_amount || 0) +
        (salary.night_shift_amount || 0) +
        (salary.bonus_amount || 0)
    );
}

function calculateTotalDeduction(salary: SalaryRow): number {
    return (salary.deduction_amount || 0) + (salary.advance_amount || 0);
}

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

const getInitials = (name: string) => {
    if (!name) {
        return 'NV';
    }

    const clean = name.replace(/\(.*?\)/g, '').trim();
    const parts = clean.split(/\s+/).filter(Boolean);

    return parts.length > 0 ? parts[parts.length - 1].charAt(0).toUpperCase() : 'NV';
};

function exportFullCSV() {
    const rows = [
        [
            'Mã NV',
            'Họ và tên',
            'Chức vụ',
            'Loại hợp đồng',
            'Ngày làm thực tế',
            'Nghỉ phép',
            'Công chuẩn',
            'Lương cơ bản (HĐ)',
            'Lương thực theo công',
            'Tổng phụ cấp',
            'Tăng ca (OT)',
            'Phụ cấp ca đêm',
            'Thưởng / KPI',
            'Phạt đi muộn & vi phạm',
            'Tạm ứng',
            'LƯƠNG NET THỰC NHẬN',
            'Ngân hàng',
            'Số tài khoản',
            'Trạng thái',
        ],
        ...props.salaries.map((s) => [
            s.employee_code,
            s.employee_name,
            s.job_title,
            s.breakdown?.compensation_type_label || s.compensation_type,
            s.actual_work_days,
            s.paid_leave_days,
            s.standard_days,
            s.contract_base_salary,
            s.base_salary,
            s.allowance_amount,
            s.overtime_amount,
            s.night_shift_amount,
            s.bonus_amount,
            s.deduction_amount,
            s.advance_amount,
            s.net_salary,
            s.bank_name || '',
            s.bank_account_number || '',
            statusConfig[s.status]?.label || s.status,
        ]),
    ];
    const bom = '\uFEFF';
    const csv =
        bom +
        rows
            .map((r) => r.map((v) => `"${String(v ?? '').replace(/"/g, '""')}"`).join(';'))
            .join('\r\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `bang_luong_chi_tiet_${activePeriod.value}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}
</script>

<template>
    <Head title="Quản Lý Bảng Lương" />

    <div class="mx-auto flex w-full max-w-[1600px] flex-col gap-6 p-4 sm:p-6">
        <!-- Header -->
        <div class="flex flex-col gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-center sm:justify-between dark:border-slate-800">
            <div class="flex items-center gap-3.5">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-600 text-white shadow-md shadow-indigo-600/20">
                    <Wallet class="size-6" />
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-2xl font-black tracking-tight text-slate-900 dark:text-white">
                            Quản Lý Bảng Lương
                        </h1>
                        <span class="rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-bold text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300">
                            {{ formatPeriodVietnamese(activePeriod) }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Tính lương tự động theo ngày công thực tế, phụ cấp, ca đêm, phạt đi muộn và xuất file chi trả ngân hàng.
                    </p>
                </div>
            </div>

            <!-- Action buttons -->
            <div class="flex flex-wrap items-center gap-2.5">
                <!-- Month Picker Popover -->
                <div class="relative">
                    <button
                        type="button"
                        id="sal-period"
                        @click="toggleMonthPicker"
                        class="flex h-9 min-w-[150px] cursor-pointer items-center justify-between gap-2 rounded-xl border border-slate-300 bg-white px-3 text-xs font-bold text-slate-800 shadow-xs transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800"
                    >
                        <span class="flex items-center gap-1.5">
                            <Calendar class="size-3.5 text-indigo-600 dark:text-indigo-400" />
                            {{ formatPeriodVietnamese(activePeriod) }}
                        </span>
                        <ChevronDown class="size-3.5 text-slate-400" />
                    </button>

                    <!-- Popover Month Picker -->
                    <Teleport to="body">
                        <div v-if="showMonthPicker" class="fixed inset-0 z-40" @click="showMonthPicker = false" />
                        <div
                            v-if="showMonthPicker"
                            class="fixed z-50 mt-1.5 w-64 animate-in rounded-2xl border border-slate-200 bg-white p-3 shadow-2xl backdrop-blur-md zoom-in-95 fade-in dark:border-slate-800 dark:bg-slate-900"
                            style="top: 80px; right: 24px;"
                        >
                            <div class="mb-2 flex items-center justify-between border-b border-slate-100 pb-2 dark:border-slate-800">
                                <button
                                    type="button"
                                    @click.stop="pickerYear--"
                                    class="rounded-md p-1 text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:hover:bg-slate-800 dark:hover:text-white"
                                >
                                    <ChevronLeft class="size-4" />
                                </button>
                                <span class="text-sm font-bold text-slate-800 dark:text-slate-100">Năm {{ pickerYear }}</span>
                                <button
                                    type="button"
                                    @click.stop="pickerYear++"
                                    class="rounded-md p-1 text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:hover:bg-slate-800 dark:hover:text-white"
                                >
                                    <ChevronRight class="size-4" />
                                </button>
                            </div>

                            <div class="grid grid-cols-3 gap-1.5">
                                <button
                                    v-for="m in 12"
                                    :key="m"
                                    type="button"
                                    @click.stop="selectMonth(m)"
                                    class="rounded-lg py-2 text-xs font-semibold transition"
                                    :class="[
                                        isCurrentSelected(m)
                                            ? 'bg-indigo-600 font-bold text-white shadow-xs'
                                            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-800',
                                    ]"
                                >
                                    Tháng {{ m < 10 ? '0' + m : m }}
                                </button>
                            </div>

                            <div class="mt-2.5 flex items-center justify-between border-t border-slate-100 pt-2 text-[11px] dark:border-slate-800">
                                <button
                                    type="button"
                                    @click.stop="selectCurrentMonth"
                                    class="font-semibold text-indigo-600 hover:underline dark:text-indigo-400"
                                >
                                    Tháng này
                                </button>
                                <button type="button" @click.stop="showMonthPicker = false" class="text-slate-400 hover:text-slate-600">
                                    Đóng
                                </button>
                            </div>
                        </div>
                    </Teleport>
                </div>

                <!-- Generate/Recalculate -->
                <Button
                    v-if="canApprove"
                    @click="generateDrafts"
                    :disabled="generating"
                    class="h-9 rounded-xl bg-indigo-600 text-xs font-bold text-white shadow-sm hover:bg-indigo-700"
                >
                    <Calculator class="mr-1.5 size-3.5" :class="generating ? 'animate-spin' : ''" />
                    {{ generating ? 'Đang tính toán...' : 'Tính lại bảng lương' }}
                </Button>

                <!-- Bank Export Button -->
                <Button
                    variant="outline"
                    @click="showBankExportModal = true"
                    class="h-9 rounded-xl border-slate-300 text-xs font-bold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                >
                    <Landmark class="mr-1.5 size-3.5 text-indigo-600 dark:text-indigo-400" />
                    Chi hộ Ngân hàng
                </Button>

                <!-- Send Payslip Button -->
                <Button
                    variant="outline"
                    @click="openSendPayslipModal"
                    class="h-9 rounded-xl border-slate-300 text-xs font-bold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                >
                    <Send class="mr-1.5 size-3.5 text-blue-600 dark:text-blue-400" />
                    Gửi phiếu lương
                </Button>

                <!-- Full CSV Export -->
                <Button
                    variant="outline"
                    @click="exportFullCSV"
                    class="h-9 rounded-xl border-slate-300 text-xs font-bold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"
                >
                    <Download class="mr-1.5 size-3.5" />
                    Xuất CSV
                </Button>
            </div>
        </div>

        <!-- Metric KPI Cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Total Net Payroll -->
            <Card class="border-indigo-100 shadow-sm transition-all hover:shadow-md dark:border-indigo-950/30">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardDescription class="text-xs font-bold tracking-wider text-indigo-600 uppercase dark:text-indigo-400">
                        Tổng Lương Net Thực Nhận
                    </CardDescription>
                    <BadgeDollarSign class="size-4 text-indigo-600 dark:text-indigo-400" />
                </CardHeader>
                <CardContent class="pb-3">
                    <p class="text-2xl font-black text-indigo-600 dark:text-indigo-400">
                        {{ vnd(totals.total_payroll) }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Cho {{ totals.headcount }} / {{ generation.eligible_employees }} nhân sự tháng này
                    </p>
                </CardContent>
            </Card>

            <!-- Revenue & Labor Cost Ratio -->
            <Card class="border-slate-200 shadow-sm transition-all hover:shadow-md dark:border-slate-800">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardDescription class="text-xs font-bold tracking-wider text-slate-500 uppercase">
                        Tỷ Lệ Lương / Doanh Thu
                    </CardDescription>
                    <Percent class="size-4 text-slate-400" />
                </CardHeader>
                <CardContent class="pb-3">
                    <div class="flex items-center gap-2">
                        <p class="text-2xl font-black text-slate-800 dark:text-slate-100">
                            {{ totals.labor_cost_ratio }}%
                        </p>
                        <span
                            class="rounded-full px-2 py-0.5 text-[10px] font-bold"
                            :class="[
                                totals.labor_cost_ratio <= 20
                                    ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                                    : totals.labor_cost_ratio <= 25
                                      ? 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300'
                                      : 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300',
                            ]"
                        >
                            {{ totals.labor_cost_ratio <= 20 ? 'Tối ưu (<20%)' : totals.labor_cost_ratio <= 25 ? 'Hợp lý' : 'Cao (>25%)' }}
                        </span>
                    </div>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Doanh thu kỳ: {{ compact(totals.period_revenue) }}
                    </p>
                </CardContent>
            </Card>

            <!-- Allowances & Overtime -->
            <Card class="border-emerald-100 shadow-sm transition-all hover:shadow-md dark:border-emerald-950/30">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardDescription class="text-xs font-bold tracking-wider text-emerald-600 uppercase dark:text-emerald-400">
                        Phụ Cấp, Tăng Ca & Thưởng
                    </CardDescription>
                    <TrendingUp class="size-4 text-emerald-600 dark:text-emerald-400" />
                </CardHeader>
                <CardContent class="pb-3">
                    <p class="text-2xl font-black text-emerald-600 dark:text-emerald-400">
                        +{{ compact(totals.total_allowances + totals.total_overtime + totals.total_night_shift + totals.total_bonuses) }}
                    </p>
                    <p class="mt-1 text-[11px] text-muted-foreground">
                        Phụ cấp: {{ compact(totals.total_allowances) }} · OT & Đêm: {{ compact(totals.total_overtime + totals.total_night_shift) }}
                    </p>
                </CardContent>
            </Card>

            <!-- Deductions & Advances -->
            <Card class="border-rose-100 shadow-sm transition-all hover:shadow-md dark:border-rose-950/30">
                <CardHeader class="flex flex-row items-center justify-between pb-2">
                    <CardDescription class="text-xs font-bold tracking-wider text-rose-600 uppercase dark:text-rose-400">
                        Khấu Trừ & Tạm Ứng
                    </CardDescription>
                    <TrendingDown class="size-4 text-rose-600 dark:text-rose-400" />
                </CardHeader>
                <CardContent class="pb-3">
                    <p class="text-2xl font-black text-rose-600 dark:text-rose-400">
                        -{{ compact(totals.total_deductions + totals.total_advances) }}
                    </p>
                    <p class="mt-1 text-[11px] text-muted-foreground">
                        Phạt/hao hụt: {{ compact(totals.total_deductions) }} · Đã tạm ứng: {{ compact(totals.total_advances) }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Main Data Table Card -->
        <!-- Main Payroll Data Card -->
        <Card class="overflow-hidden rounded-2xl border border-border bg-card shadow-xs">
            <!-- Filter & Search toolbar -->
            <div class="grid items-center gap-3 border-b border-border bg-muted/20 p-4 sm:grid-cols-[2fr_1fr_1fr_auto]">
                <!-- Search Input -->
                <div class="relative w-full">
                    <Search class="absolute top-2.5 left-3 size-4 text-muted-foreground" />
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Tìm theo tên nhân viên, mã NV, chức vụ..."
                        class="w-full rounded-xl border border-input bg-background py-2 pr-3 pl-9 text-xs font-semibold text-foreground placeholder:text-muted-foreground focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                    />
                </div>

                <!-- Branch Filter -->
                <div class="flex items-center gap-1.5">
                    <Building2 class="size-4 text-muted-foreground shrink-0" />
                    <select
                        v-model="branchFilter"
                        class="w-full rounded-xl border border-input bg-background px-3 py-2 text-xs font-semibold text-foreground focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                    >
                        <option value="all">Tất cả chi nhánh</option>
                        <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                    </select>
                </div>

                <!-- Compensation Type Filter -->
                <div class="flex items-center gap-1.5">
                    <UserCog class="size-4 text-muted-foreground shrink-0" />
                    <select
                        v-model="compTypeFilter"
                        class="w-full rounded-xl border border-input bg-background px-3 py-2 text-xs font-semibold text-foreground focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                    >
                        <option value="all">Tất cả loại lương</option>
                        <option value="fixed">Lương tháng cố định</option>
                        <option value="hourly">Lương theo giờ</option>
                        <option value="shift">Lương theo ca</option>
                    </select>
                </div>

                <!-- Status Filter Pills -->
                <div class="flex items-center gap-1 justify-self-end rounded-xl border border-border bg-muted/50 p-1">
                    <button
                        v-for="f in [
                            { key: 'all', label: 'Tất cả' },
                            { key: 'draft', label: 'Nháp' },
                            { key: 'approved', label: 'Đã duyệt' },
                            { key: 'paid', label: 'Đã trả' },
                        ]"
                        :key="f.key"
                        type="button"
                        @click="statusFilter = f.key as any"
                        :class="[
                            'rounded-lg px-3 py-1 text-[11px] font-bold whitespace-nowrap transition-all',
                            statusFilter === f.key
                                ? 'bg-background text-foreground shadow-xs'
                                : 'text-muted-foreground hover:text-foreground',
                        ]"
                    >
                        {{ f.label }}
                    </button>
                </div>
            </div>

            <!-- Empty State -->
            <div v-if="salaries.length === 0" class="flex flex-col items-center gap-3 py-20 text-center text-muted-foreground">
                <div class="flex h-16 w-16 items-center justify-center rounded-3xl bg-indigo-500/10 text-indigo-600 dark:text-indigo-400">
                    <Wallet class="size-8" />
                </div>
                <p class="text-base font-bold text-foreground">
                    Chưa tạo bảng lương cho kỳ {{ formatPeriodVietnamese(activePeriod) }}
                </p>
                <p class="mx-auto max-w-md text-xs text-muted-foreground">
                    Nhấn nút "Tính lại bảng lương" ở góc trên để hệ thống tự động quét log chấm công, tính lương theo công thực tế, phụ cấp, ca đêm và phạt đi muộn.
                </p>
                <Button v-if="canApprove" @click="generateDrafts" class="mt-2 rounded-xl bg-indigo-600 text-xs font-bold text-white hover:bg-indigo-700">
                    <Calculator class="mr-1.5 size-4" /> Bắt đầu tính bảng lương
                </Button>
            </div>

            <!-- Data Table (Unified & Synchronized Row Grid) -->
            <div v-else class="relative w-full overflow-x-auto">
                <table class="w-full text-left text-xs text-foreground">
                    <thead class="border-b border-border bg-muted/40 text-[11px] font-bold tracking-wider text-muted-foreground uppercase">
                        <tr>
                            <th class="w-12 px-3 py-3 text-center">
                                <input
                                    v-if="canApprove"
                                    type="checkbox"
                                    :checked="isAllSelected"
                                    @change="toggleSelectAll"
                                    class="size-4 cursor-pointer rounded border-input accent-indigo-600"
                                />
                            </th>
                            <th class="min-w-[220px] px-4 py-3">Nhân Viên</th>
                            <th class="px-3 py-3 text-right">Lương HĐ / Đơn giá</th>
                            <th class="px-3 py-3 text-center">Công chuẩn / Làm</th>
                            <th class="px-3 py-3 text-right font-bold text-foreground">Lương Theo Công</th>
                            <th class="px-3 py-3 text-right text-emerald-600 dark:text-emerald-400">Phụ Cấp (+)</th>
                            <th class="px-3 py-3 text-right text-indigo-600 dark:text-indigo-400">OT & Ca Đêm (+)</th>
                            <th class="px-3 py-3 text-right text-emerald-600 dark:text-emerald-400">Thưởng KPI (+)</th>
                            <th class="px-3 py-3 text-right text-rose-600 dark:text-rose-400">Khấu Trừ (-)</th>
                            <th class="px-3 py-3 text-right text-amber-600 dark:text-amber-400">Tạm Ứng (-)</th>
                            <th class="min-w-[140px] px-4 py-3 text-right font-black text-indigo-600 dark:text-indigo-400">
                                Lương Thực Nhận
                            </th>
                            <th class="min-w-[130px] px-4 py-3 text-center">
                                Trạng Thái & Thao Tác
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        <tr
                            v-for="s in paginatedSalaries"
                            :key="s.id"
                            class="transition-colors hover:bg-muted/40"
                            :class="{ 'bg-indigo-500/10 dark:bg-indigo-500/15 hover:bg-indigo-500/15': selectedIds.includes(s.id) }"
                        >
                            <!-- Checkbox -->
                            <td class="px-3 py-3.5 text-center">
                                <input
                                    v-if="canApprove"
                                    type="checkbox"
                                    :checked="selectedIds.includes(s.id)"
                                    @change="toggleSelect(s.id)"
                                    class="size-4 cursor-pointer rounded border-input accent-indigo-600"
                                />
                            </td>

                            <!-- Employee Info -->
                            <td class="px-4 py-3.5">
                                <div class="flex items-center gap-2.5">
                                    <!-- Avatar initials -->
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-500/10 font-bold text-xs text-indigo-600 dark:text-indigo-400">
                                        {{ getInitials(s.employee_name) }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5">
                                            <span class="truncate font-bold text-foreground">{{ s.employee_name }}</span>
                                            <TrustScoreBadge :score="s.trust_score" :show-label="false" />
                                            <span v-if="s.adjustments.some((a) => a.status === 'disputed')" class="rounded-md bg-amber-100 px-1.5 py-0.5 text-[9px] font-black text-amber-800 dark:bg-amber-950/50 dark:text-amber-300">
                                                KHIẾU NẠI
                                            </span>
                                        </div>
                                        <div class="mt-0.5 flex flex-wrap items-center gap-1.5 text-[11px] text-muted-foreground">
                                            <span class="font-mono text-[10px]">{{ s.employee_code }}</span>
                                            <span>·</span>
                                            <span class="truncate">{{ s.job_title || 'Nhân viên' }}</span>
                                            <span>·</span>
                                            <span
                                                class="rounded-md px-1.5 py-0.5 text-[10px] font-medium"
                                                :class="{
                                                    'bg-muted text-muted-foreground': s.compensation_type === 'fixed',
                                                    'bg-purple-500/10 text-purple-600 dark:text-purple-400': s.compensation_type === 'hourly',
                                                    'bg-amber-500/10 text-amber-600 dark:text-amber-400': s.compensation_type === 'shift',
                                                }"
                                            >
                                                {{ s.breakdown?.compensation_type_label || 'Lương cố định' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Lương HĐ -->
                            <td class="px-3 py-3.5 text-right font-mono font-medium text-foreground">
                                {{ s.compensation_type === 'fixed' ? compact(s.contract_base_salary) : compact(s.pay_rate) + (s.compensation_type === 'hourly' ? '/h' : '/ca') }}
                            </td>

                            <!-- Ngày công (Làm/Chuẩn) -->
                            <td class="px-3 py-3.5 text-center font-mono">
                                <div v-if="s.compensation_type === 'fixed'" class="text-xs">
                                    <span class="font-bold text-foreground">{{ s.actual_work_days + s.paid_leave_days }}</span>
                                    <span class="text-muted-foreground"> / {{ s.standard_days }} ngày</span>
                                    <span v-if="s.paid_leave_days > 0" class="ml-1 text-[10px] font-bold text-indigo-600 dark:text-indigo-400">(+{{ s.paid_leave_days }}p)</span>
                                </div>
                                <div v-else-if="s.compensation_type === 'hourly'" class="text-xs">
                                    <span class="font-bold text-foreground">{{ s.breakdown?.regular_hours }}h</span>
                                </div>
                                <div v-else class="text-xs">
                                    <span class="font-bold text-foreground">{{ s.breakdown?.completed_shifts_count }}</span> ca
                                </div>
                            </td>

                            <!-- Lương theo công -->
                            <td class="px-3 py-3.5 text-right font-mono font-bold text-foreground">
                                {{ compact(s.base_salary) }}
                            </td>

                            <!-- Phụ cấp -->
                            <td class="px-3 py-3.5 text-right font-mono font-semibold" :class="s.allowance_amount > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-muted-foreground/40'">
                                {{ s.allowance_amount > 0 ? '+' + compact(s.allowance_amount) : '—' }}
                            </td>

                            <!-- OT & Ca đêm -->
                            <td class="px-3 py-3.5 text-right font-mono font-semibold" :class="(s.overtime_amount + s.night_shift_amount) > 0 ? 'text-indigo-600 dark:text-indigo-400' : 'text-muted-foreground/40'">
                                {{ (s.overtime_amount + s.night_shift_amount) > 0 ? '+' + compact(s.overtime_amount + s.night_shift_amount) : '—' }}
                            </td>

                            <!-- Thưởng KPI -->
                            <td class="px-3 py-3.5 text-right font-mono font-semibold" :class="s.bonus_amount > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-muted-foreground/40'">
                                {{ s.bonus_amount > 0 ? '+' + compact(s.bonus_amount) : '—' }}
                            </td>

                            <!-- Khấu trừ & Phạt -->
                            <td class="px-3 py-3.5 text-right font-mono font-semibold" :class="s.deduction_amount > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-muted-foreground/40'">
                                {{ s.deduction_amount > 0 ? '-' + compact(s.deduction_amount) : '—' }}
                            </td>

                            <!-- Tạm ứng -->
                            <td class="px-3 py-3.5 text-right font-mono font-semibold" :class="s.advance_amount > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-muted-foreground/40'">
                                {{ s.advance_amount > 0 ? '-' + compact(s.advance_amount) : '—' }}
                            </td>

                            <!-- Net Salary -->
                            <td class="px-4 py-3.5 text-right font-mono text-sm font-black text-indigo-600 dark:text-indigo-400">
                                {{ vnd(s.net_salary) }}
                            </td>

                            <!-- Status & Actions -->
                            <td class="px-4 py-3.5 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    <span class="inline-flex rounded-md px-2 py-0.5 text-[10px] font-bold" :class="statusConfig[s.status].cls">
                                        {{ statusConfig[s.status].label }}
                                    </span>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="openDetailDrawer(s)"
                                        class="h-7 w-7 rounded-lg border-border p-0 text-foreground hover:bg-accent hover:text-indigo-600"
                                        title="Xem chi tiết & giải trình"
                                    >
                                        <FileText class="size-3.5" />
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="openPayStub(s)"
                                        class="h-7 w-7 rounded-lg border-border p-0 text-foreground hover:bg-accent hover:text-indigo-600"
                                        title="In phiếu lương"
                                    >
                                        <Printer class="size-3.5" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Table Pagination Footer -->
            <div class="flex flex-col items-center justify-between gap-3 border-t border-border bg-muted/20 p-4 sm:flex-row">
                <span class="text-xs font-semibold text-muted-foreground">
                    Hiển thị {{ filteredSalaries.length === 0 ? 0 : (currentPage - 1) * itemsPerPage + 1 }} - {{ Math.min(currentPage * itemsPerPage, filteredSalaries.length) }} trong tổng số {{ filteredSalaries.length }} bảng lương
                </span>

                <div class="flex items-center gap-2">
                    <Button variant="outline" size="sm" @click="prevPage" :disabled="currentPage === 1" class="h-8 text-xs border-border">
                        <ChevronLeft class="size-4 mr-1" /> Trước
                    </Button>
                    <span class="rounded-lg border border-border bg-card px-3 py-1 text-xs font-bold text-foreground">
                        Trang {{ currentPage }} / {{ totalPages }}
                    </span>
                    <Button variant="outline" size="sm" @click="nextPage" :disabled="currentPage === totalPages" class="h-8 text-xs border-border">
                        Sau <ChevronRight class="size-4 ml-1" />
                    </Button>
                </div>
            </div>
        </Card>
    </div>

    <!-- ══ Floating Bulk Action Bar ════════════════════════════════════════════ -->
    <div
        v-if="selectedIds.length > 0 && canApprove"
        class="fixed bottom-6 left-1/2 z-40 flex -translate-x-1/2 animate-in items-center gap-4 rounded-2xl border border-slate-200 bg-white/95 px-6 py-3.5 text-slate-900 shadow-2xl backdrop-blur-md transition-all slide-in-from-bottom-5 fade-in dark:border-indigo-900 dark:bg-slate-900 dark:text-white"
    >
        <div class="flex items-center gap-2">
            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-600 text-xs font-black text-white">
                {{ selectedIds.length }}
            </span>
            <span class="text-xs font-bold text-slate-700 dark:text-slate-200">
                bảng lương đã chọn
            </span>
        </div>

        <div class="flex items-center gap-2">
            <Button size="sm" class="h-8 rounded-xl bg-indigo-600 px-3 text-xs font-bold text-white hover:bg-indigo-500" @click="openBulkApproveModal">
                <Check class="mr-1 size-3.5" /> Duyệt hàng loạt
            </Button>
            <Button size="sm" variant="outline" class="h-8 rounded-xl border-slate-200 bg-slate-100 px-3 text-xs font-bold text-slate-700 hover:bg-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200" @click="openBulkDialog">
                <Plus class="mr-1 size-3.5" /> Thưởng/Phạt
            </Button>
            <Button size="sm" variant="outline" class="h-8 rounded-xl border-slate-200 bg-slate-100 px-3 text-xs font-bold text-blue-700 hover:bg-blue-50 dark:border-slate-700 dark:bg-slate-800 dark:text-blue-300" @click="openSendPayslipModal">
                <Send class="mr-1 size-3.5" /> Gửi phiếu
            </Button>
            <button @click="selectedIds = []" class="ml-2 rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700" title="Bỏ chọn tất cả">
                <X class="size-4" />
            </button>
        </div>
    </div>

    <!-- ══ Bank Batch Export Modal ══════════════════════════════════════════════ -->
    <Teleport to="body">
        <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="showBankExportModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs" @click.self="showBankExportModal = false">
                <Card class="relative my-auto w-full max-w-md overflow-hidden rounded-3xl border border-slate-200 bg-card p-6 shadow-2xl dark:border-slate-800">
                    <div class="flex items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/40">
                            <Landmark class="size-6" />
                        </div>
                        <div>
                            <h2 class="text-base font-black text-foreground">Xuất File Chi Lương Ngân Hàng</h2>
                            <p class="text-xs text-muted-foreground">Tạo file danh sách nạp chuyển khoản theo lô (Payroll Batch)</p>
                        </div>
                    </div>

                    <div class="mt-4 space-y-3">
                        <Label class="text-xs font-bold text-slate-600 uppercase dark:text-slate-300">Chọn định dạng ngân hàng:</Label>
                        <div class="grid grid-cols-2 gap-2">
                            <label
                                v-for="bank in [
                                    { id: 'vietcombank', name: 'Vietcombank' },
                                    { id: 'mbbank', name: 'MB Bank' },
                                    { id: 'techcombank', name: 'Techcombank' },
                                    { id: 'acb', name: 'ACB' },
                                    { id: 'generic', name: 'Chuẩn chung (Excel/CSV)' },
                                ]"
                                :key="bank.id"
                                class="flex cursor-pointer items-center gap-2 rounded-xl border p-3 text-xs font-bold transition"
                                :class="selectedBankFormat === bank.id ? 'border-indigo-600 bg-indigo-50/50 text-indigo-700 dark:bg-indigo-950/30 dark:text-indigo-300' : 'border-slate-200 hover:bg-slate-50 dark:border-slate-800'"
                            >
                                <input type="radio" v-model="selectedBankFormat" :value="bank.id" class="hidden" />
                                <CreditCard class="size-4 shrink-0 text-indigo-600" />
                                <span>{{ bank.name }}</span>
                            </label>
                        </div>

                        <p class="rounded-xl bg-slate-50 p-3 text-[11px] leading-relaxed text-slate-500 dark:bg-slate-900">
                            File sẽ xuất danh sách nhân viên có Lương Net > 0 kèm Số tài khoản, Tên chủ TK, Ngân hàng và Số tiền.
                        </p>
                    </div>

                    <div class="mt-6 flex justify-end gap-2">
                        <Button variant="outline" size="sm" class="rounded-xl" @click="showBankExportModal = false">Đóng</Button>
                        <Button size="sm" class="rounded-xl bg-indigo-600 font-bold text-white hover:bg-indigo-700" @click="triggerBankExport">
                            <Download class="mr-1.5 size-4" /> Tải file chi lương
                        </Button>
                    </div>
                </Card>
            </div>
        </Transition>
    </Teleport>

    <!-- ══ Send Payslips Modal ══════════════════════════════════════════════════ -->
    <Teleport to="body">
        <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="showSendPayslipModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs" @click.self="showSendPayslipModal = false">
                <Card class="relative my-auto w-full max-w-md overflow-hidden rounded-3xl border border-slate-200 bg-card p-6 shadow-2xl dark:border-slate-800">
                    <div class="flex items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-blue-50 text-blue-600 dark:bg-blue-950/40">
                            <Send class="size-6" />
                        </div>
                        <div>
                            <h2 class="text-base font-black text-foreground">Gửi Phiếu Lương Điện Tử</h2>
                            <p class="text-xs text-muted-foreground">Kỳ lương: {{ formatPeriodVietnamese(activePeriod) }}</p>
                        </div>
                    </div>

                    <div class="mt-4 rounded-xl border bg-muted/40 p-3 text-xs leading-relaxed text-muted-foreground">
                        Hệ thống sẽ gửi thông báo phiếu lương chi tiết đến <strong>{{ selectedIds.length > 0 ? selectedIds.length : salaries.length }} nhân sự</strong> qua Email và Cổng nhân viên (Portal) để nhân viên đối soát, ký nhận.
                    </div>

                    <div class="mt-6 flex justify-end gap-2">
                        <Button variant="outline" size="sm" class="rounded-xl" @click="showSendPayslipModal = false">Hủy</Button>
                        <Button size="sm" class="rounded-xl bg-blue-600 font-bold text-white hover:bg-blue-700" :disabled="sendingPayslips" @click="submitSendPayslips">
                            {{ sendingPayslips ? 'Đang gửi...' : 'Xác nhận gửi phiếu' }}
                        </Button>
                    </div>
                </Card>
            </div>
        </Transition>
    </Teleport>

    <!-- ══ Comprehensive Payslip Document Modal (Matching Image 2) ═════════ -->
    <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0">
        <Teleport to="body">
            <div v-if="detailSalary" class="fixed inset-0 z-50 flex items-center justify-center bg-black/75 p-2 sm:p-4 backdrop-blur-xs overflow-y-auto print:bg-white print:p-0 print:overflow-visible print:block" @click.self="closeDetailDrawer">
                <div class="relative my-auto w-full max-w-4xl rounded-2xl bg-slate-100 p-3 sm:p-5 shadow-2xl dark:bg-slate-900 print:bg-white print:p-0 print:shadow-none print:max-w-none print:my-0">
                    
                    <!-- Top Action Bar (Hidden when printing) -->
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 border-b border-slate-300 pb-3 dark:border-slate-800 print:hidden">
                        <div class="flex items-center gap-2">
                            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-xs">
                                <FileText class="size-4" />
                            </div>
                            <div>
                                <h2 class="text-sm font-extrabold text-slate-900 dark:text-white">
                                    Phiếu Lương Nhân Viên — {{ detailSalary.employee_name }}
                                </h2>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400">
                                    Kỳ {{ formatPeriodVietnamese(activePeriod) }} · Trạng thái: 
                                    <span class="font-bold" :class="detailSalary.status === 'paid' ? 'text-emerald-600' : detailSalary.status === 'approved' ? 'text-indigo-600' : 'text-amber-600'">
                                        {{ statusConfig[detailSalary.status].label }}
                                    </span>
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <Button
                                v-if="canApprove && detailSalary.status === 'draft'"
                                size="sm"
                                class="h-8 rounded-xl bg-indigo-600 px-3 text-xs font-bold text-white hover:bg-indigo-700"
                                @click="approveSalary(detailSalary)"
                            >
                                <Check class="mr-1 size-3.5" /> Duyệt phiếu
                            </Button>
                            <Button
                                v-if="canApprove && detailSalary.status === 'approved'"
                                size="sm"
                                class="h-8 rounded-xl bg-emerald-600 px-3 text-xs font-bold text-white hover:bg-emerald-700"
                                @click="markPaid(detailSalary)"
                            >
                                <BadgeDollarSign class="mr-1 size-3.5" /> Đã chi trả
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                class="h-8 rounded-xl border-slate-300 bg-white text-xs font-bold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                @click="printPayslip"
                            >
                                <Printer class="mr-1.5 size-3.5 text-indigo-600 dark:text-indigo-400" /> In phiếu lương
                            </Button>
                            <button
                                @click="closeDetailDrawer"
                                class="rounded-xl p-1.5 text-slate-500 hover:bg-slate-200 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white"
                                title="Đóng"
                            >
                                <X class="size-5" />
                            </button>
                        </div>
                    </div>

                    <!-- 📄 Standardized Document Paper (Form mẫu chuẩn Ảnh 2) -->
                    <div id="printable-payslip" class="rounded-xl border border-slate-300 bg-white p-6 sm:p-8 text-slate-900 shadow-sm print:border-none print:p-0 print:shadow-none">
                        <!-- 1. Header: Logo / Company Info & National Header -->
                        <div class="flex items-start justify-between border-b-2 border-slate-900 pb-3">
                            <!-- Company Brand Block -->
                            <div class="flex items-start gap-3">
                                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border-2 border-slate-900 text-slate-900">
                                    <Utensils class="size-6" />
                                </div>
                                <div>
                                    <h2 class="text-sm font-black tracking-tight text-slate-900 uppercase">CÔNG TY TNHH AVENTURA</h2>
                                    <p class="text-[11px] font-medium text-slate-600">Chuỗi cung cấp thực phẩm & dịch vụ nhà hàng</p>
                                    <p class="mt-0.5 text-[11px] text-slate-600">
                                        <span class="inline-block font-sans">📍</span> Số 123 Nguyễn Văn Cừ, P. Bồ Đề, Q. Long Biên, Hà Nội
                                    </p>
                                    <p class="text-[11px] text-slate-600">
                                        <span class="inline-block font-sans">📞</span> Hotline: 024 1234 5678
                                    </p>
                                </div>
                            </div>

                            <!-- National Motto & Date -->
                            <div class="text-center">
                                <p class="text-xs font-bold uppercase text-slate-900">CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</p>
                                <p class="text-xs font-semibold text-slate-800">Độc lập – Tự do – Hạnh phúc</p>
                                <div class="my-0.5 text-[10px] tracking-widest text-slate-700">★ ★ ★</div>
                                <p class="mt-1 text-[11px] italic text-slate-600">
                                    Hà Nội, ngày {{ todayDay }} tháng {{ todayMonth }} năm 20{{ todayYear }}
                                </p>
                            </div>
                        </div>

                        <!-- 2. Payslip Main Title & Number -->
                        <div class="relative my-4 text-center">
                            <h1 class="text-2xl sm:text-3xl font-black tracking-wider text-[#1e3a5f] uppercase">
                                PHIẾU LƯƠNG
                            </h1>
                            <div class="mt-1 inline-block rounded border border-slate-300 bg-slate-100/90 px-4 py-0.5 text-xs font-semibold text-slate-800">
                                Tháng {{ getPeriodParts(activePeriod).month }} năm {{ getPeriodParts(activePeriod).year }}
                            </div>
                            <div class="mt-2 sm:mt-0 sm:absolute sm:top-1/2 sm:right-0 sm:-translate-y-1/2 rounded border border-slate-300 bg-slate-50 px-3 py-1 text-xs font-mono font-medium text-slate-700">
                                Số phiếu: <span class="font-bold">PL/{{ getPeriodParts(activePeriod).year }}/{{ detailSalary.employee_code }}</span>
                            </div>
                        </div>

                        <!-- 3. Section 1: Thông Tin Nhân Viên -->
                        <div class="mb-4">
                            <div class="inline-block rounded-t-md bg-[#1e3a5f] px-3 py-1 text-xs font-bold text-white uppercase tracking-wide">
                                1. THÔNG TIN NHÂN VIÊN
                            </div>
                            <div class="rounded-b-md rounded-tr-md border border-slate-300 bg-white p-3 text-xs leading-relaxed text-slate-800">
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-1.5">
                                    <div class="flex justify-between border-b border-dashed border-slate-200 pb-1">
                                        <span class="font-medium text-slate-600">Mã nhân viên</span>
                                        <span class="font-bold text-slate-900">: {{ detailSalary.employee_code }}</span>
                                    </div>
                                    <div class="flex justify-between border-b border-dashed border-slate-200 pb-1">
                                        <span class="font-medium text-slate-600">Ngày vào làm</span>
                                        <span class="font-semibold text-slate-900">: {{ detailSalary.hire_date || '10/03/2024' }}</span>
                                    </div>

                                    <div class="flex justify-between border-b border-dashed border-slate-200 pb-1">
                                        <span class="font-medium text-slate-600">Họ và tên</span>
                                        <span class="font-bold text-slate-900">: {{ detailSalary.employee_name }}</span>
                                    </div>
                                    <div class="flex justify-between border-b border-dashed border-slate-200 pb-1">
                                        <span class="font-medium text-slate-600">Hình thức làm việc</span>
                                        <span class="font-semibold text-slate-900">: {{ detailSalary.employment_type === 'part_time' ? 'Bán thời gian' : 'Toàn thời gian' }}</span>
                                    </div>

                                    <div class="flex justify-between border-b border-dashed border-slate-200 pb-1">
                                        <span class="font-medium text-slate-600">Chức danh</span>
                                        <span class="font-semibold text-slate-900">: {{ detailSalary.job_title || 'Nhân viên' }}</span>
                                    </div>
                                    <div class="flex justify-between border-b border-dashed border-slate-200 pb-1">
                                        <span class="font-medium text-slate-600">Số ngày công chuẩn</span>
                                        <span class="font-bold text-slate-900">: {{ detailSalary.standard_days }} ngày</span>
                                    </div>

                                    <div class="flex justify-between">
                                        <span class="font-medium text-slate-600">Bộ phận</span>
                                        <span class="font-semibold text-slate-900">: {{ detailSalary.branch_name ? ('Nhà hàng – ' + detailSalary.branch_name) : 'Nhà hàng – Chi nhánh Long Biên' }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="font-medium text-slate-600">Số ngày công thực tế</span>
                                        <span class="font-bold text-slate-900">: {{ (detailSalary.actual_work_days + detailSalary.paid_leave_days) }} ngày</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 4. Section 2: Chi Tiết Lương (Table Form A / B / C) -->
                        <div class="mb-4">
                            <div class="inline-block rounded-t-md bg-[#1e3a5f] px-3 py-1 text-xs font-bold text-white uppercase tracking-wide">
                                2. CHI TIẾT LƯƠNG
                            </div>
                            <div class="overflow-x-auto">
                                <table class="w-full border-collapse border border-slate-300 text-xs text-slate-800">
                                    <thead>
                                        <tr class="bg-[#dbeafe]/70 text-slate-800">
                                            <th class="w-12 border border-slate-300 px-2 py-1.5 text-center font-bold uppercase text-[11px]">STT</th>
                                            <th class="border border-slate-300 px-3 py-1.5 text-left font-bold uppercase text-[11px]">NỘI DUNG</th>
                                            <th class="border border-slate-300 px-3 py-1.5 text-left font-bold uppercase text-[11px]">CÁCH TÍNH / DIỄN GIẢI</th>
                                            <th class="w-36 border border-slate-300 px-3 py-1.5 text-right font-bold uppercase text-[11px]">SỐ TIỀN (VND)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Group A: THU NHẬP -->
                                        <tr class="bg-[#eff6ff] font-bold text-slate-900">
                                            <td class="border border-slate-300 px-2 py-1 text-center font-bold">A</td>
                                            <td class="border border-slate-300 px-3 py-1 uppercase" colspan="3">THU NHẬP</td>
                                        </tr>
                                        <tr>
                                            <td class="border border-slate-300 px-2 py-1 text-center">1</td>
                                            <td class="border border-slate-300 px-3 py-1 font-medium">Lương cơ bản</td>
                                            <td class="border border-slate-300 px-3 py-1 text-slate-600">Theo hợp đồng lao động</td>
                                            <td class="border border-slate-300 px-3 py-1 text-right font-mono font-medium">{{ formatMoney(detailSalary.contract_base_salary || detailSalary.pay_rate || detailSalary.base_salary) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="border border-slate-300 px-2 py-1 text-center">2</td>
                                            <td class="border border-slate-300 px-3 py-1 font-medium">Lương thời gian</td>
                                            <td class="border border-slate-300 px-3 py-1 text-slate-600">{{ getTimeFormula(detailSalary) }}</td>
                                            <td class="border border-slate-300 px-3 py-1 text-right font-mono font-bold">{{ formatMoney(detailSalary.base_salary) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="border border-slate-300 px-2 py-1 text-center">3</td>
                                            <td class="border border-slate-300 px-3 py-1 font-medium">Phụ cấp chức vụ</td>
                                            <td class="border border-slate-300 px-3 py-1 text-slate-600">Phụ cấp theo vị trí công việc</td>
                                            <td class="border border-slate-300 px-3 py-1 text-right font-mono font-medium">{{ formatMoney(detailSalary.breakdown?.allowances?.responsibility || 0) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="border border-slate-300 px-2 py-1 text-center">4</td>
                                            <td class="border border-slate-300 px-3 py-1 font-medium">Phụ cấp ca làm</td>
                                            <td class="border border-slate-300 px-3 py-1 text-slate-600">{{ detailSalary.night_shift_amount > 0 ? (detailSalary.breakdown?.night_shift?.hours || 0) + ' ca đêm × 30%' : 'Phụ cấp ca tối / ca đêm' }}</td>
                                            <td class="border border-slate-300 px-3 py-1 text-right font-mono font-medium">{{ formatMoney(detailSalary.night_shift_amount || 0) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="border border-slate-300 px-2 py-1 text-center">5</td>
                                            <td class="border border-slate-300 px-3 py-1 font-medium">Phụ cấp ăn ca</td>
                                            <td class="border border-slate-300 px-3 py-1 text-slate-600">{{ detailSalary.actual_work_days }} ngày × 30,000</td>
                                            <td class="border border-slate-300 px-3 py-1 text-right font-mono font-medium">{{ formatMoney(detailSalary.breakdown?.allowances?.meal || detailSalary.allowance_amount || 0) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="border border-slate-300 px-2 py-1 text-center">6</td>
                                            <td class="border border-slate-300 px-3 py-1 font-medium">Thưởng doanh số / KPI</td>
                                            <td class="border border-slate-300 px-3 py-1 text-slate-600">Theo kết quả đánh giá tháng</td>
                                            <td class="border border-slate-300 px-3 py-1 text-right font-mono font-medium">{{ formatMoney(detailSalary.bonus_amount || 0) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="border border-slate-300 px-2 py-1 text-center">7</td>
                                            <td class="border border-slate-300 px-3 py-1 font-medium">Thưởng chuyên cần / Tăng ca</td>
                                            <td class="border border-slate-300 px-3 py-1 text-slate-600">{{ detailSalary.overtime_amount > 0 ? (detailSalary.breakdown?.ot_hours || 0) + 'h OT được duyệt' : 'Đi làm đủ công' }}</td>
                                            <td class="border border-slate-300 px-3 py-1 text-right font-mono font-medium">{{ formatMoney(detailSalary.overtime_amount || 0) }}</td>
                                        </tr>
                                        <!-- Total A -->
                                        <tr class="bg-[#f1f5f9] font-bold text-slate-900">
                                            <td class="border border-slate-300 px-2 py-1.5 text-center"></td>
                                            <td class="border border-slate-300 px-3 py-1.5 uppercase" colspan="2">TỔNG THU NHẬP (A)</td>
                                            <td class="border border-slate-300 px-3 py-1.5 text-right font-mono text-sm font-black">{{ formatMoney(calculateTotalIncome(detailSalary)) }}</td>
                                        </tr>

                                        <!-- Group B: CÁC KHOẢN KHẤU TRỪ -->
                                        <tr class="bg-[#fff1f2] font-bold text-[#991b1b]">
                                            <td class="border border-slate-300 px-2 py-1 text-center font-bold">B</td>
                                            <td class="border border-slate-300 px-3 py-1 uppercase" colspan="3">CÁC KHOẢN KHẤU TRỪ</td>
                                        </tr>
                                        <tr>
                                            <td class="border border-slate-300 px-2 py-1 text-center">1</td>
                                            <td class="border border-slate-300 px-3 py-1 font-medium">Bảo hiểm xã hội (BHXH)</td>
                                            <td class="border border-slate-300 px-3 py-1 text-slate-600">8% x {{ formatMoney(detailSalary.contract_base_salary || detailSalary.base_salary) }}</td>
                                            <td class="border border-slate-300 px-3 py-1 text-right font-mono font-medium">0</td>
                                        </tr>
                                        <tr>
                                            <td class="border border-slate-300 px-2 py-1 text-center">2</td>
                                            <td class="border border-slate-300 px-3 py-1 font-medium">Bảo hiểm y tế (BHYT)</td>
                                            <td class="border border-slate-300 px-3 py-1 text-slate-600">1.5% x {{ formatMoney(detailSalary.contract_base_salary || detailSalary.base_salary) }}</td>
                                            <td class="border border-slate-300 px-3 py-1 text-right font-mono font-medium">0</td>
                                        </tr>
                                        <tr>
                                            <td class="border border-slate-300 px-2 py-1 text-center">3</td>
                                            <td class="border border-slate-300 px-3 py-1 font-medium">Bảo hiểm thất nghiệp (BHTN)</td>
                                            <td class="border border-slate-300 px-3 py-1 text-slate-600">1% x {{ formatMoney(detailSalary.contract_base_salary || detailSalary.base_salary) }}</td>
                                            <td class="border border-slate-300 px-3 py-1 text-right font-mono font-medium">0</td>
                                        </tr>
                                        <tr>
                                            <td class="border border-slate-300 px-2 py-1 text-center">4</td>
                                            <td class="border border-slate-300 px-3 py-1 font-medium">Thuế thu nhập cá nhân (TNCN)</td>
                                            <td class="border border-slate-300 px-3 py-1 text-slate-600">Tạm khấu trừ theo quy định</td>
                                            <td class="border border-slate-300 px-3 py-1 text-right font-mono font-medium">0</td>
                                        </tr>
                                        <tr>
                                            <td class="border border-slate-300 px-2 py-1 text-center">5</td>
                                            <td class="border border-slate-300 px-3 py-1 font-medium">Khấu trừ khác</td>
                                            <td class="border border-slate-300 px-3 py-1 text-slate-600">Ứng lương / phạt / khác (nếu có)</td>
                                            <td class="border border-slate-300 px-3 py-1 text-right font-mono font-medium">{{ formatMoney(calculateTotalDeduction(detailSalary)) }}</td>
                                        </tr>
                                        <!-- Total B -->
                                        <tr class="bg-[#fff1f2] font-bold text-[#991b1b]">
                                            <td class="border border-slate-300 px-2 py-1.5 text-center"></td>
                                            <td class="border border-slate-300 px-3 py-1.5 uppercase" colspan="2">TỔNG KHẤU TRỪ (B)</td>
                                            <td class="border border-slate-300 px-3 py-1.5 text-right font-mono text-sm font-black">{{ formatMoney(calculateTotalDeduction(detailSalary)) }}</td>
                                        </tr>

                                        <!-- Group C: THỰC LĨNH -->
                                        <tr class="bg-[#1e3a5f] text-white">
                                            <td class="border border-slate-700 px-2 py-2 text-center text-sm font-black">C</td>
                                            <td class="border border-slate-700 px-3 py-2 text-sm font-black uppercase tracking-wide" colspan="2">THỰC LĨNH (A - B)</td>
                                            <td class="border border-slate-700 px-3 py-2 text-right font-mono text-base font-black">{{ formatMoney(detailSalary.net_salary) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- 5. Section 3 & 4: Thông Tin Bổ Sung & Ghi Chú -->
                        <div class="mb-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- 3. THÔNG TIN BỔ SUNG -->
                            <div>
                                <div class="inline-block rounded-t-md bg-[#1e3a5f] px-3 py-1 text-xs font-bold text-white uppercase tracking-wide">
                                    3. THÔNG TIN BỔ SUNG
                                </div>
                                <div class="rounded-b-md rounded-tr-md border border-slate-300 bg-white p-3 text-xs leading-relaxed text-slate-800 space-y-1">
                                    <div class="flex justify-between">
                                        <span class="font-medium text-slate-600">Số tài khoản</span>
                                        <span class="font-bold text-slate-900">: {{ detailSalary.bank_account_number || '1903 1234 5678' }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="font-medium text-slate-600">Ngân hàng</span>
                                        <span class="font-semibold text-slate-900">: {{ detailSalary.bank_name || 'Vietcombank' }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="font-medium text-slate-600">Chi nhánh</span>
                                        <span class="font-semibold text-slate-900">: {{ detailSalary.branch_name || 'Hà Nội' }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="font-medium text-slate-600">Nội dung chuyển khoản</span>
                                        <span class="font-bold text-indigo-700">: LUONG T{{ getPeriodParts(activePeriod).month }}/{{ getPeriodParts(activePeriod).year }} - {{ detailSalary.employee_code }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- 4. GHI CHÚ -->
                            <div>
                                <div class="inline-block rounded-t-md bg-[#1e3a5f] px-3 py-1 text-xs font-bold text-white uppercase tracking-wide">
                                    4. GHI CHÚ
                                </div>
                                <div class="rounded-b-md rounded-tr-md border border-slate-300 bg-white p-3 text-xs leading-relaxed text-slate-700 space-y-1">
                                    <p class="flex items-start gap-1.5">
                                        <span class="mt-1 h-1 w-1 shrink-0 rounded-full bg-slate-500"></span>
                                        <span>Phiếu lương được lập căn cứ vào bảng chấm công, kết quả đánh giá hiệu suất và các quy định hiện hành của công ty.</span>
                                    </p>
                                    <p class="flex items-start gap-1.5">
                                        <span class="mt-1 h-1 w-1 shrink-0 rounded-full bg-slate-500"></span>
                                        <span>Nếu có thắc mắc, vui lòng liên hệ phòng Nhân sự trong vòng 07 ngày kể từ ngày nhận lương.</span>
                                    </p>
                                    <p class="flex items-start gap-1.5 font-medium text-indigo-900">
                                        <span class="mt-1 h-1 w-1 shrink-0 rounded-full bg-indigo-600"></span>
                                        <span>Cảm ơn bạn đã đồng hành cùng Aventura!</span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- 6. Signatures (4 columns) -->
                        <div class="mb-6 grid grid-cols-2 sm:grid-cols-4 gap-4 pt-2 text-center text-xs text-slate-800">
                            <div>
                                <p class="font-bold uppercase text-[11px]">NGƯỜI LẬP PHIẾU</p>
                                <p class="text-[10px] italic text-slate-500">(Ký, ghi rõ họ tên)</p>
                                <div class="mt-12 border-b border-dotted border-slate-300"></div>
                                <p class="mt-1 text-[10px] text-slate-500">Ngày ....../....../20......</p>
                            </div>
                            <div>
                                <p class="font-bold uppercase text-[11px]">QUẢN LÝ TRỰC TIẾP</p>
                                <p class="text-[10px] italic text-slate-500">(Ký, ghi rõ họ tên)</p>
                                <div class="mt-12 border-b border-dotted border-slate-300"></div>
                                <p class="mt-1 text-[10px] text-slate-500">Ngày ....../....../20......</p>
                            </div>
                            <div>
                                <p class="font-bold uppercase text-[11px]">PHÒNG NHÂN SỰ</p>
                                <p class="text-[10px] italic text-slate-500">(Ký, ghi rõ họ tên)</p>
                                <div class="mt-12 border-b border-dotted border-slate-300"></div>
                                <p class="mt-1 text-[10px] text-slate-500">Ngày ....../....../20......</p>
                            </div>
                            <div>
                                <p class="font-bold uppercase text-[11px]">NGƯỜI NHẬN LƯƠNG</p>
                                <p class="text-[10px] italic text-slate-500">(Ký, ghi rõ họ tên)</p>
                                <div class="mt-12 border-b border-dotted border-slate-300"></div>
                                <p class="mt-1 text-[10px] text-slate-500">Ngày ....../....../20......</p>
                            </div>
                        </div>

                        <!-- 7. Document Footer -->
                        <div class="flex items-center justify-between border-t border-slate-300 pt-3 text-xs text-slate-600">
                            <div class="flex items-center gap-2">
                                <span class="font-bold tracking-wider text-[#1e3a5f]">AVENTURA</span>
                                <span>|</span>
                                <span class="italic text-slate-500 text-[11px]">Cùng nhau tạo nên những bữa ăn ngon hơn mỗi ngày</span>
                            </div>
                            <div class="flex items-center gap-1 font-serif italic text-slate-700">
                                <span>Thank you!</span>
                                <Heart class="size-3.5 fill-rose-500 text-rose-500 inline" />
                            </div>
                        </div>
                    </div>

                    <!-- Extra Adjustments Details Box (Hidden on print) -->
                    <div v-if="detailSalary.adjustments && detailSalary.adjustments.length > 0" class="mt-4 rounded-xl border border-slate-200 bg-white p-4 text-xs dark:border-slate-800 dark:bg-slate-950 print:hidden">
                        <div class="flex items-center justify-between border-b pb-2 dark:border-slate-800">
                            <p class="font-bold text-slate-700 uppercase dark:text-slate-300">
                                Chi Tiết Các Khoản Điều Chỉnh Đã Áp Dụng ({{ detailSalary.adjustments.length }})
                            </p>
                            <button v-if="detailSalary.status === 'draft'" @click="openAdjDialog(detailSalary)" class="font-bold text-indigo-600 hover:underline dark:text-indigo-400">
                                + Thêm khoản
                            </button>
                        </div>
                        <div class="mt-2 space-y-2">
                            <div v-for="a in detailSalary.adjustments" :key="a.id" class="flex items-center justify-between rounded-lg border border-slate-100 bg-slate-50/50 p-2 text-xs dark:border-slate-800 dark:bg-slate-900/50">
                                <div>
                                    <span class="rounded px-1.5 py-0.5 text-[9px] font-bold uppercase" :class="adjTypeColor[a.type]">
                                        {{ adjTypeLabel[a.type] }}
                                    </span>
                                    <span class="ml-2 font-medium text-slate-700 dark:text-slate-200">{{ a.reason }}</span>
                                </div>
                                <span class="font-mono font-bold" :class="a.type === 'bonus' ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                                    {{ a.type === 'bonus' ? '+' : '-' }}{{ formatMoney(a.amount) }} đ
                                </span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </Teleport>
    </Transition>

    <!-- ══ Add Adjustment Dialog ═══════════════════════════════════════════════ -->
    <Teleport to="body">
        <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="adjTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs" @click.self="adjTarget = null">
                <Card class="relative my-auto w-full max-w-md rounded-3xl border border-slate-200 bg-card p-6 shadow-2xl dark:border-slate-800">
                    <div class="flex items-center justify-between border-b pb-3">
                        <h2 class="text-base font-bold text-indigo-600">Thêm Khoản Điều Chỉnh Lương</h2>
                        <button @click="adjTarget = null" class="rounded-full p-1 text-muted-foreground"><X class="size-4" /></button>
                    </div>
                    <div class="mt-4 space-y-4 text-xs">
                        <div>
                            <Label class="font-bold">Loại điều chỉnh</Label>
                            <select v-model="adjForm.type" class="mt-1.5 w-full rounded-xl border p-2 font-semibold">
                                <option value="bonus">Thưởng chuyên cần / Phụ cấp</option>
                                <option value="advance">Tạm ứng lương</option>
                                <option value="penalty">Phạt hành chính / Kỷ luật</option>
                                <option value="violation">Khấu trừ vi phạm / Hao hụt</option>
                            </select>
                        </div>
                        <div>
                            <Label class="font-bold">Số tiền (VNĐ)</Label>
                            <Input v-model="adjForm.amount" type="number" min="0" step="1000" class="mt-1.5 rounded-xl font-bold" />
                        </div>
                        <div>
                            <Label class="font-bold">Lý do điều chỉnh</Label>
                            <textarea v-model="adjForm.reason" rows="3" class="mt-1.5 w-full rounded-xl border p-2 text-xs font-semibold" placeholder="Mô tả lý do cụ thể..." />
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end gap-2">
                        <Button variant="outline" size="sm" class="rounded-xl" @click="adjTarget = null">Hủy</Button>
                        <Button size="sm" class="rounded-xl bg-indigo-600 font-bold text-white hover:bg-indigo-700" :disabled="adjForm.processing" @click="submitAdj">
                            Xác nhận
                        </Button>
                    </div>
                </Card>
            </div>
        </Transition>
    </Teleport>

    <!-- ══ Bulk Adjustment Dialog ═════════════════════════════════════════════ -->
    <Teleport to="body">
        <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="showBulkDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs" @click.self="showBulkDialog = false">
                <Card class="relative my-auto w-full max-w-md rounded-3xl border border-slate-200 bg-card p-6 shadow-2xl dark:border-slate-800">
                    <div class="flex items-center justify-between border-b pb-3">
                        <h2 class="text-base font-bold text-rose-600">Thưởng / Phạt Hàng Loạt ({{ selectedIds.length }} NV)</h2>
                        <button @click="showBulkDialog = false" class="rounded-full p-1 text-muted-foreground"><X class="size-4" /></button>
                    </div>
                    <div class="mt-4 space-y-4 text-xs">
                        <div>
                            <Label class="font-bold">Loại điều chỉnh</Label>
                            <select v-model="bulkForm.type" class="mt-1.5 w-full rounded-xl border p-2 font-semibold">
                                <option value="bonus">Thưởng chuyên cần / Thưởng nóng đồng loạt</option>
                                <option value="advance">Tạm ứng lương đồng loạt</option>
                                <option value="penalty">Phạt lỗi tập thể / Kỷ luật</option>
                                <option value="violation">Khấu trừ vi phạm nội bộ</option>
                            </select>
                        </div>
                        <div>
                            <Label class="font-bold">Số tiền mỗi người (VNĐ)</Label>
                            <Input v-model="bulkForm.amount" type="number" min="0" step="1000" class="mt-1.5 rounded-xl font-bold text-rose-600" />
                        </div>
                        <div>
                            <Label class="font-bold">Lý do điều chỉnh hàng loạt</Label>
                            <textarea v-model="bulkForm.reason" rows="3" class="mt-1.5 w-full rounded-xl border p-2 text-xs font-semibold" placeholder="Mô tả lý do..." />
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end gap-2">
                        <Button variant="outline" size="sm" class="rounded-xl" @click="showBulkDialog = false">Hủy</Button>
                        <Button size="sm" class="rounded-xl bg-rose-600 font-bold text-white hover:bg-rose-700" :disabled="bulkForm.processing" @click="submitBulkAdj">
                            Áp dụng hàng loạt
                        </Button>
                    </div>
                </Card>
            </div>
        </Transition>
    </Teleport>

    <!-- ══ Bulk Approve Modal ══════════════════════════════════════════════════ -->
    <Teleport to="body">
        <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0" enter-to-class="opacity-100" leave-active-class="transition duration-150 ease-in" leave-from-class="opacity-100" leave-to-class="opacity-0">
            <div v-if="showBulkApproveModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs" @click.self="showBulkApproveModal = false">
                <Card class="relative my-auto w-full max-w-md rounded-3xl border border-slate-200 bg-card p-6 shadow-2xl dark:border-slate-800">
                    <div class="flex items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/40">
                            <Check class="size-6" />
                        </div>
                        <div>
                            <h2 class="text-base font-extrabold text-foreground">Phê Duyệt Hàng Loạt</h2>
                            <p class="text-xs text-muted-foreground">Phê duyệt {{ selectedIds.length }} bảng lương đã chọn</p>
                        </div>
                    </div>
                    <div class="mt-4 rounded-xl border bg-muted/40 p-3 text-xs leading-relaxed text-muted-foreground">
                        Sau khi phê duyệt, bảng lương sẽ được khóa để chuẩn bị chi trả. Bạn có chắc chắn muốn duyệt {{ selectedIds.length }} phiếu lương này?
                    </div>
                    <div class="mt-6 flex justify-end gap-2">
                        <Button variant="outline" size="sm" class="rounded-xl" @click="showBulkApproveModal = false">Hủy</Button>
                        <Button size="sm" class="rounded-xl bg-indigo-600 font-bold text-white hover:bg-indigo-700" :disabled="bulkApproving" @click="submitBulkApprove">
                            {{ bulkApproving ? 'Đang duyệt...' : 'Đồng ý phê duyệt' }}
                        </Button>
                    </div>
                </Card>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
@media print {
    :global(body) {
        background: white !important;
        color: black !important;
    }
    #printable-payslip {
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
        background: white !important;
        color: black !important;
    }
}
</style>
