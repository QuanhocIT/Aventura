<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowDownCircle,
    ArrowUpCircle,
    BadgeDollarSign,
    CalendarDays,
    Check,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    ChevronUp,
    ClipboardCheck,
    Clock,
    CreditCard,
    Info,
    Loader2,
    ReceiptText,
    TriangleAlert,
    Wallet,
    X,
} from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import AppLayout from '@/layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

// ── Types ────────────────────────────────────────────────────────────────────

type Status = 'draft' | 'submitted' | 'confirmed' | 'disputed';

type ShiftClosing = {
    id: number;
    closing_date: string;
    closing_date_raw: string;
    shift_name: string;
    shift_code: string;
    shift_start: string;
    shift_end: string;
    cashier_name: string;
    status: Status;
    expected_cash: number;
    actual_cash: number;
    cash_difference: number;
    transfer_amount: number;
    gross_revenue: number;
    other_expense: number;
    notes: string | null;
    confirmed_by_name: string | null;
    closed_at: string | null;
};

type Shift = {
    id: number;
    name: string;
    code: string;
    start_time: string;
    end_time: string;
    is_overnight: boolean;
};

type KPI = {
    total_closings: number;
    total_gross: number;
    total_cash: number;
    total_transfer: number;
    total_difference: number;
};

type Preview = {
    shift_name: string;
    shift_code: string;
    start_time: string;
    end_time: string;
    is_overnight: boolean;
    order_count: number;
    gross_revenue: number;
    discount_total: number;
    net_revenue: number;
    expected_cash: number;
    bank_transfer: number;
    card: number;
    ewallet: number;
    mixed: number;
    transfer_amount: number;
    pending_orders: number;
    already_closed: boolean;
};

const props = defineProps<{
    closings: ShiftClosing[];
    shifts: Shift[];
    kpi: KPI;
    filters: { status: string; month: string };
    canConfirm: boolean;
}>();

// ── Filters ───────────────────────────────────────────────────────────────────

const activeStatus = ref(props.filters.status);
const activeMonth  = ref(props.filters.month);

function applyFilters() {
    router.get('/shift-closings', { status: activeStatus.value, month: activeMonth.value }, { preserveScroll: true });
}

watch([activeStatus, activeMonth], applyFilters);

// ── Status config ─────────────────────────────────────────────────────────────

const statusConfig: Record<Status, { label: string; badgeClass: string; dotClass: string }> = {
    draft:     { label: 'Bản nháp',    badgeClass: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',      dotClass: 'bg-slate-400' },
    submitted: { label: 'Chờ duyệt',   badgeClass: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',   dotClass: 'bg-amber-500 animate-pulse' },
    confirmed: { label: 'Đã xác nhận', badgeClass: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300', dotClass: 'bg-emerald-500' },
    disputed:  { label: 'Tranh chấp',  badgeClass: 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',       dotClass: 'bg-rose-500' },
};

// ── Formatting ─────────────────────────────────────────────────────────────────

const vnd = (v: number) =>
    new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(v);

const compact = (v: number) =>
    new Intl.NumberFormat('vi-VN', { notation: 'compact', maximumFractionDigits: 1 }).format(v) + 'đ';

// ── Create dialog ─────────────────────────────────────────────────────────────

const showDialog   = ref(false);
const dialogStep   = ref<1 | 2>(1);
const previewData  = ref<Preview | null>(null);
const previewLoading = ref(false);
const previewError   = ref('');

const form = useForm({
    shift_id: null as number | null,
    closing_date: new Date().toISOString().slice(0, 10),
    actual_cash: 0,
    other_expense_amount: 0,
    notes: '',
});

const isSubmitting = ref(false);

const cashDifference = computed(() => {
    if (!previewData.value) return 0;
    return form.actual_cash - previewData.value.expected_cash;
});

function openDialog() {
    form.reset();
    form.closing_date = todayStr;
    form.shift_id = null;
    form.actual_cash = 0;
    form.other_expense_amount = 0;
    form.notes = '';
    previewData.value = null;
    previewError.value = '';
    dialogStep.value = 1;
    isSubmitting.value = false;
    showDialog.value = true;
}

async function loadPreview() {
    if (!form.shift_id || !form.closing_date) {
        previewError.value = 'Vui lòng chọn ca và ngày.';
        return;
    }

    previewLoading.value = true;
    previewError.value = '';
    previewData.value = null;

    try {
        const url = `/shift-closings/preview?shift_id=${form.shift_id}&closing_date=${form.closing_date}`;
        const res = await fetch(url, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });

        if (!res.ok) {
            previewError.value = 'Không thể tải dữ liệu. Thử lại.';
            return;
        }

        const data: Preview = await res.json();
        previewData.value = data;
        form.actual_cash = data.expected_cash;
        dialogStep.value = 2;
    } catch {
        previewError.value = 'Lỗi kết nối. Thử lại.';
    } finally {
        previewLoading.value = false;
    }
}

function submitForm(isSubmit: boolean) {
    isSubmitting.value = isSubmit;
    form.transform(data => ({ ...data, submit: isSubmit ? 1 : 0 }))
        .post('/shift-closings', {
        onSuccess: () => {
            showDialog.value = false;
            form.reset();
            previewData.value = null;
            dialogStep.value = 1;
            isSubmitting.value = false;
            toast.success(isSubmit ? 'Đã nộp phiếu chốt ca thành công!' : 'Đã lưu bản nháp.');
        },
        onError: (errors) => {
            isSubmitting.value = false;
            const msg = Object.values(errors)[0];
            if (msg) toast.error(String(msg));
        },
    });
}

// ── Dispute dialog ────────────────────────────────────────────────────────────

const disputeTarget = ref<ShiftClosing | null>(null);
const disputeNotes  = ref('');
const disputeLoading = ref(false);

function openDisputeDialog(closing: ShiftClosing) {
    disputeTarget.value = closing;
    disputeNotes.value = '';
}

function submitDispute() {
    if (!disputeTarget.value) return;
    if (!disputeNotes.value.trim()) {
        toast.error('Vui lòng nhập lý do tranh chấp.');
        return;
    }

    disputeLoading.value = true;
    router.patch(
        `/shift-closings/${disputeTarget.value.id}/dispute`,
        { dispute_notes: disputeNotes.value },
        {
            onSuccess: () => {
                disputeTarget.value = null;
                disputeNotes.value = '';
                toast.success('Đã đánh dấu tranh chấp.');
            },
            onError: () => toast.error('Có lỗi xảy ra.'),
            onFinish: () => { disputeLoading.value = false; },
        },
    );
}

function confirmClosing(closing: ShiftClosing) {
    router.patch(
        `/shift-closings/${closing.id}/confirm`,
        {},
        {
            onSuccess: () => toast.success('Đã xác nhận chốt ca.'),
            onError: () => toast.error('Có lỗi xảy ra.'),
        },
    );
}

// ── Expanded row ──────────────────────────────────────────────────────────────

const expandedId = ref<number | null>(null);

function toggleExpand(id: number) {
    expandedId.value = expandedId.value === id ? null : id;
}

// ── Derived totals for selected month ─────────────────────────────────────────

const statusOptions = [
    { value: 'all',       label: 'Tất cả' },
    { value: 'draft',     label: 'Bản nháp' },
    { value: 'submitted', label: 'Chờ duyệt' },
    { value: 'confirmed', label: 'Đã xác nhận' },
    { value: 'disputed',  label: 'Tranh chấp' },
];

const selectedShift = computed(() =>
    props.shifts.find(s => s.id === form.shift_id) ?? null
);

// ── Custom Calendar Picker ─────────────────────────────────────────────────────

const showCalendar  = ref(false);
const calTriggerRef = ref<HTMLElement | null>(null);
const calPos        = ref({ top: 0, left: 0, width: 0 });

const today    = new Date();
const todayStr = today.toISOString().slice(0, 10);

const calView  = ref({ year: today.getFullYear(), month: today.getMonth() });

const viMonths  = ['Tháng 1','Tháng 2','Tháng 3','Tháng 4','Tháng 5','Tháng 6','Tháng 7','Tháng 8','Tháng 9','Tháng 10','Tháng 11','Tháng 12'];
const viMonthsShort = ['T1','T2','T3','T4','T5','T6','T7','T8','T9','T10','T11','T12'];
const viDays    = ['T2','T3','T4','T5','T6','T7','CN'];

const showMonthPicker = ref(false);

function selectMonth(m: number) {
    calView.value = { year: calView.value.year, month: m };
    showMonthPicker.value = false;
}

function prevYear() { calView.value = { year: calView.value.year - 1, month: calView.value.month }; }
function nextYear() {
    if (calView.value.year < today.getFullYear()) {
        calView.value = { year: calView.value.year + 1, month: calView.value.month };
    }
}
const isMonthFuture = (m: number) => new Date(calView.value.year, m, 1) > today;

function openCalendar() {
    if (form.closing_date) {
        const d = new Date(form.closing_date + 'T00:00:00');
        calView.value = { year: d.getFullYear(), month: d.getMonth() };
    } else {
        calView.value = { year: today.getFullYear(), month: today.getMonth() };
    }
    // Đặt calendar sang bên phải trigger
    if (calTriggerRef.value) {
        const rect = calTriggerRef.value.getBoundingClientRect();
        calPos.value = {
            top:   rect.top  + window.scrollY,
            left:  rect.right + window.scrollX + 8,
            width: rect.width,
        };
    }
    showCalendar.value = true;
}

function prevMonth() {
    calView.value = calView.value.month === 0
        ? { year: calView.value.year - 1, month: 11 }
        : { year: calView.value.year,     month: calView.value.month - 1 };
}

function nextMonth() {
    const nextY = calView.value.month === 11 ? calView.value.year + 1 : calView.value.year;
    const nextM = calView.value.month === 11 ? 0 : calView.value.month + 1;
    if (new Date(nextY, nextM, 1) <= today) {
        calView.value = { year: nextY, month: nextM };
    }
}

const isNextMonthDisabled = computed(() => {
    const nextY = calView.value.month === 11 ? calView.value.year + 1 : calView.value.year;
    const nextM = calView.value.month === 11 ? 0 : calView.value.month + 1;
    return new Date(nextY, nextM, 1) > today;
});

type CalDay = { date: string; day: number; inMonth: boolean; isToday: boolean; isFuture: boolean; isSelected: boolean };

const calDays = computed<CalDay[]>(() => {
    const { year, month } = calView.value;
    const firstDay = new Date(year, month, 1);
    const lastDay  = new Date(year, month + 1, 0);
    let startDow   = firstDay.getDay();
    startDow       = startDow === 0 ? 6 : startDow - 1;

    const days: CalDay[] = [];

    for (let i = startDow - 1; i >= 0; i--) {
        const d   = new Date(year, month, -i);
        const str = d.toISOString().slice(0, 10);
        days.push({ date: str, day: d.getDate(), inMonth: false, isToday: false, isFuture: d > today, isSelected: str === form.closing_date });
    }
    for (let d = 1; d <= lastDay.getDate(); d++) {
        const dt  = new Date(year, month, d);
        const str = dt.toISOString().slice(0, 10);
        days.push({ date: str, day: d, inMonth: true, isToday: str === todayStr, isFuture: dt > today, isSelected: str === form.closing_date });
    }
    const remaining = 42 - days.length;
    for (let i = 1; i <= remaining; i++) {
        const d   = new Date(year, month + 1, i);
        const str = d.toISOString().slice(0, 10);
        days.push({ date: str, day: i, inMonth: false, isToday: false, isFuture: true, isSelected: false });
    }
    return days;
});

function selectDate(day: CalDay) {
    if (day.isFuture) return;
    form.closing_date = day.date;
    showCalendar.value = false;
    if (dialogStep.value === 2) { dialogStep.value = 1; previewData.value = null; }
}

const displayDate = computed(() => {
    if (!form.closing_date) return '';
    const d = new Date(form.closing_date + 'T00:00:00');
    return d.toLocaleDateString('vi-VN', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' });
});

function handleOutsideClick(e: MouseEvent) {
    const target = e.target as Node;
    const trigger = calTriggerRef.value;
    const calEl   = document.getElementById('shift-cal-popup');
    if (trigger && !trigger.contains(target) && calEl && !calEl.contains(target)) {
        showCalendar.value = false;
    }
}
onMounted(() => document.addEventListener('mousedown', handleOutsideClick));
onUnmounted(() => document.removeEventListener('mousedown', handleOutsideClick));
</script>

<template>
    <Head title="Chốt Ca & Doanh Thu" />

    <div class="mx-auto max-w-7xl space-y-6 p-4 lg:p-6">

        <!-- ── Page Header ─────────────────────────────────────────────────── -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400">
                    <ClipboardCheck class="size-5" />
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-tight">Chốt Ca & Doanh Thu</h1>
                    <p class="text-sm text-muted-foreground">Quản lý phiếu chốt ca và đối soát tiền mặt</p>
                </div>
            </div>

            <button
                @click="openDialog"
                class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-amber-500 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-600 active:scale-95"
            >
                <ClipboardCheck class="size-4" />
                Chốt ca mới
            </button>
        </div>

        <!-- ── KPI Cards ───────────────────────────────────────────────────── -->
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <!-- Tổng phiếu -->
            <div class="rounded-2xl border border-border bg-card p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Tổng phiếu</span>
                    <ReceiptText class="size-4 text-muted-foreground" />
                </div>
                <p class="mt-2 text-2xl font-bold">{{ kpi.total_closings }}</p>
                <p class="mt-0.5 text-xs text-muted-foreground">phiếu trong tháng</p>
            </div>

            <!-- Tổng doanh thu gộp -->
            <div class="rounded-2xl border border-border bg-card p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Doanh thu gộp</span>
                    <BadgeDollarSign class="size-4 text-emerald-500" />
                </div>
                <p class="mt-2 text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ compact(kpi.total_gross) }}</p>
                <p class="mt-0.5 text-xs text-muted-foreground">tiền mặt + chuyển khoản</p>
            </div>

            <!-- Tiền mặt thực -->
            <div class="rounded-2xl border border-border bg-card p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Tiền mặt thực</span>
                    <Wallet class="size-4 text-blue-500" />
                </div>
                <p class="mt-2 text-2xl font-bold text-blue-600 dark:text-blue-400">{{ compact(kpi.total_cash) }}</p>
                <p class="mt-0.5 text-xs text-muted-foreground">tổng actual cash</p>
            </div>

            <!-- Chênh lệch tổng -->
            <div class="rounded-2xl border border-border bg-card p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-medium text-muted-foreground uppercase tracking-wider">Chênh lệch</span>
                    <component
                        :is="kpi.total_difference >= 0 ? ArrowUpCircle : ArrowDownCircle"
                        :class="kpi.total_difference >= 0 ? 'text-emerald-500' : 'text-rose-500'"
                        class="size-4"
                    />
                </div>
                <p
                    class="mt-2 text-2xl font-bold"
                    :class="kpi.total_difference >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'"
                >
                    {{ kpi.total_difference >= 0 ? '+' : '' }}{{ compact(kpi.total_difference) }}
                </p>
                <p class="mt-0.5 text-xs text-muted-foreground">thừa/thiếu so sổ sách</p>
            </div>
        </div>

        <!-- ── Filters ─────────────────────────────────────────────────────── -->
        <div class="flex flex-wrap items-center gap-3">
            <!-- Month picker -->
            <div class="flex items-center gap-2 rounded-xl border border-border bg-card px-3 py-2 text-sm shadow-sm">
                <CalendarDays class="size-4 text-muted-foreground" />
                <input
                    v-model="activeMonth"
                    type="month"
                    class="bg-transparent text-sm focus:outline-none"
                />
            </div>

            <!-- Status filter chips -->
            <div class="flex flex-wrap gap-1.5">
                <button
                    v-for="opt in statusOptions"
                    :key="opt.value"
                    @click="activeStatus = opt.value"
                    class="cursor-pointer rounded-full px-3 py-1.5 text-xs font-semibold transition-all"
                    :class="activeStatus === opt.value
                        ? 'bg-foreground text-background shadow-sm'
                        : 'bg-muted text-muted-foreground hover:bg-muted/70'"
                >
                    {{ opt.label }}
                </button>
            </div>
        </div>

        <!-- ── Table ────────────────────────────────────────────────────────── -->
        <div class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
            <!-- Empty state -->
            <div v-if="closings.length === 0" class="flex flex-col items-center gap-3 py-16 text-center text-muted-foreground">
                <ClipboardCheck class="size-12 opacity-30" />
                <p class="font-medium">Chưa có phiếu chốt ca nào</p>
                <p class="text-sm">Nhấn "Chốt ca mới" để tạo phiếu đầu tiên</p>
            </div>

            <template v-else>
                <!-- Table header -->
                <div class="hidden grid-cols-[auto_1fr_1fr_1fr_1fr_1fr_auto] gap-4 border-b border-border bg-muted/40 px-5 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground lg:grid">
                    <div></div>
                    <div>Ngày / Ca</div>
                    <div>Cashier</div>
                    <div class="text-right">Doanh thu gộp</div>
                    <div class="text-right">Tiền mặt thực</div>
                    <div class="text-right">Chênh lệch</div>
                    <div class="text-right">Trạng thái</div>
                </div>

                <!-- Rows -->
                <div
                    v-for="closing in closings"
                    :key="closing.id"
                    class="border-b border-border last:border-0"
                >
                    <!-- Main row -->
                    <div
                        class="grid cursor-pointer grid-cols-[auto_1fr_auto] items-center gap-3 px-4 py-3.5 transition hover:bg-muted/30 lg:grid-cols-[auto_1fr_1fr_1fr_1fr_1fr_auto] lg:gap-4 lg:px-5"
                        @click="toggleExpand(closing.id)"
                    >
                        <!-- Expand toggle -->
                        <component
                            :is="expandedId === closing.id ? ChevronUp : ChevronDown"
                            class="size-4 shrink-0 text-muted-foreground"
                        />

                        <!-- Ngày / Ca -->
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-sm">{{ closing.closing_date }}</p>
                            <div class="mt-0.5 flex items-center gap-1.5">
                                <span class="rounded-md bg-muted px-1.5 py-0.5 text-[10px] font-bold text-muted-foreground">{{ closing.shift_code }}</span>
                                <span class="text-xs text-muted-foreground truncate">{{ closing.shift_name }}</span>
                                <span class="hidden text-[10px] text-muted-foreground lg:inline">{{ closing.shift_start }}–{{ closing.shift_end }}</span>
                            </div>
                        </div>

                        <!-- Cashier (hidden mobile) -->
                        <div class="hidden items-center gap-2 lg:flex">
                            <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-amber-500/10 text-xs font-bold text-amber-600">
                                {{ closing.cashier_name?.slice(0, 1) ?? '?' }}
                            </div>
                            <span class="truncate text-sm text-muted-foreground">{{ closing.cashier_name }}</span>
                        </div>

                        <!-- Doanh thu gộp (hidden mobile) -->
                        <div class="hidden text-right lg:block">
                            <p class="font-semibold text-sm">{{ compact(closing.gross_revenue) }}</p>
                            <p class="text-xs text-muted-foreground">
                                <span class="text-blue-500">TM: {{ compact(closing.expected_cash) }}</span>
                                <span class="mx-1">·</span>
                                <span class="text-violet-500">CK: {{ compact(closing.transfer_amount) }}</span>
                            </p>
                        </div>

                        <!-- Tiền mặt thực (hidden mobile) -->
                        <div class="hidden text-right lg:block">
                            <p class="font-semibold text-sm">{{ compact(closing.actual_cash) }}</p>
                        </div>

                        <!-- Chênh lệch (hidden mobile) -->
                        <div class="hidden text-right lg:block">
                            <p
                                class="font-bold text-sm"
                                :class="closing.cash_difference > 0
                                    ? 'text-emerald-600 dark:text-emerald-400'
                                    : closing.cash_difference < 0
                                        ? 'text-rose-600 dark:text-rose-400'
                                        : 'text-muted-foreground'"
                            >
                                {{ closing.cash_difference > 0 ? '+' : '' }}{{ vnd(closing.cash_difference) }}
                            </p>
                        </div>

                        <!-- Status badge + actions -->
                        <div class="flex shrink-0 items-center gap-2">
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold"
                                :class="statusConfig[closing.status].badgeClass"
                            >
                                <span class="size-1.5 rounded-full" :class="statusConfig[closing.status].dotClass"></span>
                                {{ statusConfig[closing.status].label }}
                            </span>
                        </div>
                    </div>

                    <!-- Expanded detail row -->
                    <Transition
                        enter-active-class="transition-all duration-200 ease-out"
                        enter-from-class="opacity-0 max-h-0"
                        enter-to-class="opacity-100 max-h-[500px]"
                        leave-active-class="transition-all duration-150 ease-in"
                        leave-from-class="opacity-100 max-h-[500px]"
                        leave-to-class="opacity-0 max-h-0"
                    >
                        <div
                            v-if="expandedId === closing.id"
                            class="overflow-hidden border-t border-border/50 bg-muted/20 px-5 py-4"
                        >
                            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                <!-- Revenue breakdown -->
                                <div class="space-y-1.5">
                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Doanh thu</p>
                                    <div class="space-y-1 text-sm">
                                        <div class="flex justify-between">
                                            <span class="text-muted-foreground">Doanh thu gộp</span>
                                            <span class="font-medium">{{ vnd(closing.gross_revenue) }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="flex items-center gap-1 text-muted-foreground">
                                                <Wallet class="size-3" /> Tiền mặt kỳ vọng
                                            </span>
                                            <span class="font-medium">{{ vnd(closing.expected_cash) }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="flex items-center gap-1 text-muted-foreground">
                                                <CreditCard class="size-3" /> Chuyển khoản
                                            </span>
                                            <span class="font-medium">{{ vnd(closing.transfer_amount) }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Cash reconciliation -->
                                <div class="space-y-1.5">
                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Đối soát tiền mặt</p>
                                    <div class="space-y-1 text-sm">
                                        <div class="flex justify-between">
                                            <span class="text-muted-foreground">Kỳ vọng (sổ sách)</span>
                                            <span class="font-medium">{{ vnd(closing.expected_cash) }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-muted-foreground">Thực tế (đếm két)</span>
                                            <span class="font-semibold">{{ vnd(closing.actual_cash) }}</span>
                                        </div>
                                        <div class="flex justify-between border-t border-border pt-1">
                                            <span class="font-semibold">Chênh lệch</span>
                                            <span
                                                class="font-bold"
                                                :class="closing.cash_difference >= 0 ? 'text-emerald-600' : 'text-rose-600'"
                                            >
                                                {{ closing.cash_difference >= 0 ? '+' : '' }}{{ vnd(closing.cash_difference) }}
                                            </span>
                                        </div>
                                        <div v-if="closing.other_expense > 0" class="flex justify-between text-xs text-muted-foreground">
                                            <span>Chi phí phát sinh</span>
                                            <span>{{ vnd(closing.other_expense) }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Meta info -->
                                <div class="space-y-1.5">
                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Thông tin</p>
                                    <div class="space-y-1.5 text-sm">
                                        <div class="flex items-center gap-2 text-muted-foreground">
                                            <Clock class="size-3 shrink-0" />
                                            <span>{{ closing.closed_at ?? '—' }}</span>
                                        </div>
                                        <div v-if="closing.confirmed_by_name" class="flex items-center gap-2 text-muted-foreground">
                                            <Check class="size-3 shrink-0 text-emerald-500" />
                                            <span>Duyệt bởi: <strong>{{ closing.confirmed_by_name }}</strong></span>
                                        </div>
                                        <div v-if="closing.notes" class="text-xs text-muted-foreground">
                                            <p class="font-medium">Ghi chú:</p>
                                            <p class="mt-0.5 whitespace-pre-line">{{ closing.notes }}</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div class="flex flex-col gap-2">
                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Hành động</p>
                                    <template v-if="canConfirm">
                                        <button
                                            v-if="closing.status === 'submitted'"
                                            @click.stop="confirmClosing(closing)"
                                            class="flex cursor-pointer items-center justify-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-emerald-700 active:scale-95"
                                        >
                                            <Check class="size-3.5" />
                                            Xác nhận
                                        </button>
                                        <button
                                            v-if="closing.status === 'submitted' || closing.status === 'confirmed'"
                                            @click.stop="openDisputeDialog(closing)"
                                            class="flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 transition hover:bg-rose-100 active:scale-95 dark:border-rose-900 dark:bg-rose-900/20 dark:text-rose-300"
                                        >
                                            <AlertTriangle class="size-3.5" />
                                            Tranh chấp
                                        </button>
                                    </template>
                                    <span v-if="closing.status === 'draft'" class="text-xs text-muted-foreground">Cashier chưa nộp</span>
                                    <span v-if="closing.status === 'confirmed'" class="flex items-center gap-1 text-xs text-emerald-600 dark:text-emerald-400">
                                        <Check class="size-3" /> Đã xác nhận
                                    </span>
                                    <span v-if="closing.status === 'disputed'" class="flex items-center gap-1 text-xs text-rose-600 dark:text-rose-400">
                                        <AlertTriangle class="size-3" /> Đang tranh chấp
                                    </span>
                                </div>
                            </div>
                        </div>
                    </Transition>
                </div>
            </template>
        </div>
    </div>

    <!-- ══ Create Shift Closing Dialog ═══════════════════════════════════════ -->
    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="showDialog"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
            @click.self="showDialog = false"
        >
            <div class="flex w-full max-w-lg flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-2xl" style="max-height: 90vh;">

                <!-- Dialog header -->
                <div class="flex items-center justify-between border-b border-border px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-500/10 text-amber-600">
                            <ClipboardCheck class="size-5" />
                        </div>
                        <div>
                            <p class="font-semibold">Chốt ca mới</p>
                            <p class="text-xs text-muted-foreground">
                                Bước {{ dialogStep }} / 2 —
                                {{ dialogStep === 1 ? 'Chọn ca & ngày' : 'Nhập tiền thực tế' }}
                            </p>
                        </div>
                    </div>
                    <button @click="showDialog = false" class="cursor-pointer rounded-lg p-1.5 text-muted-foreground hover:bg-muted">
                        <X class="size-4" />
                    </button>
                </div>

                <!-- Step progress bar -->
                <div class="flex h-1 bg-muted">
                    <div
                        class="bg-amber-500 transition-all duration-300"
                        :style="{ width: dialogStep === 1 ? '50%' : '100%' }"
                    />
                </div>

                <!-- Dialog body -->
                <div class="flex-1 overflow-y-auto px-6 py-5">

                    <!-- ── Step 1: Chọn ca & ngày ───────────────────────── -->
                    <template v-if="dialogStep === 1">
                        <div class="space-y-4">
                            <!-- Chọn ca -->
                            <div class="space-y-1.5">
                                <label class="text-sm font-medium">Ca làm việc <span class="text-rose-500">*</span></label>
                                <select
                                    v-model="form.shift_id"
                                    class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20"
                                >
                                    <option :value="null" disabled>Chọn ca...</option>
                                    <option v-for="shift in shifts" :key="shift.id" :value="shift.id">
                                        {{ shift.name }} ({{ shift.start_time }} – {{ shift.end_time }}{{ shift.is_overnight ? ' +1 ngày' : '' }})
                                    </option>
                                </select>
                            </div>

                            <!-- Chọn ngày — Custom Calendar Picker -->
                            <div class="space-y-1.5">
                                <label class="text-sm font-medium">Ngày chốt ca <span class="text-rose-500">*</span></label>

                                <!-- Trigger button -->
                                <button
                                    ref="calTriggerRef"
                                    type="button"
                                    @click="openCalendar"
                                    class="flex w-full cursor-pointer items-center justify-between rounded-xl border border-border bg-background px-3 py-2.5 text-sm transition hover:border-amber-500/60 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20"
                                    :class="showCalendar ? 'border-amber-500 ring-2 ring-amber-500/20' : ''"
                                >
                                    <span :class="form.closing_date ? 'text-foreground font-medium' : 'text-muted-foreground'">
                                        {{ displayDate || 'Chọn ngày...' }}
                                    </span>
                                    <CalendarDays class="size-4 text-amber-500 shrink-0" />
                                </button>
                            </div>

                            <!-- Calendar — Teleport to body để tránh bị clip bởi overflow:hidden -->
                            <Teleport to="body">
                                <Transition
                                    enter-active-class="transition duration-150 ease-out"
                                    enter-from-class="opacity-0 scale-95 translate-y-1"
                                    enter-to-class="opacity-100 scale-100 translate-y-0"
                                    leave-active-class="transition duration-100 ease-in"
                                    leave-from-class="opacity-100 scale-100 translate-y-0"
                                    leave-to-class="opacity-0 scale-95 translate-y-1"
                                >
                                    <div
                                        v-if="showCalendar"
                                        id="shift-cal-popup"
                                        class="fixed z-[9999] overflow-hidden rounded-xl border border-border bg-card shadow-2xl"
                                        :style="{ top: calPos.top + 'px', left: calPos.left + 'px', width: '272px' }"
                                    >
                                        <!-- ── Header ── -->
                                        <div class="flex items-center justify-between border-b border-border bg-muted/50 px-3 py-2">
                                            <button type="button" @click="prevMonth"
                                                class="flex cursor-pointer items-center justify-center rounded-md p-1 text-muted-foreground hover:bg-muted hover:text-foreground">
                                                <ChevronLeft class="size-3.5" />
                                            </button>

                                            <!-- Click để mở month picker -->
                                            <button type="button" @click="showMonthPicker = !showMonthPicker"
                                                class="flex cursor-pointer items-center gap-1.5 rounded-md px-2 py-0.5 text-xs font-bold hover:bg-muted transition">
                                                <span>{{ viMonths[calView.month] }}</span>
                                                <span class="text-amber-500">{{ calView.year }}</span>
                                                <ChevronDown class="size-3 text-muted-foreground" :class="showMonthPicker ? 'rotate-180' : ''" />
                                            </button>

                                            <button type="button" @click="nextMonth" :disabled="isNextMonthDisabled"
                                                class="flex cursor-pointer items-center justify-center rounded-md p-1 text-muted-foreground hover:bg-muted hover:text-foreground disabled:cursor-not-allowed disabled:opacity-25">
                                                <ChevronRight class="size-3.5" />
                                            </button>
                                        </div>

                                        <!-- ── Month picker panel (12 tháng) ── -->
                                        <Transition
                                            enter-active-class="transition duration-150 ease-out"
                                            enter-from-class="opacity-0 -translate-y-2"
                                            enter-to-class="opacity-100 translate-y-0"
                                            leave-active-class="transition duration-100 ease-in"
                                            leave-from-class="opacity-100 translate-y-0"
                                            leave-to-class="opacity-0 -translate-y-2"
                                        >
                                            <div v-if="showMonthPicker" class="border-b border-border bg-muted/30 p-2">
                                                <!-- Year nav -->
                                                <div class="flex items-center justify-between mb-2 px-1">
                                                    <button type="button" @click="prevYear"
                                                        class="flex cursor-pointer items-center justify-center rounded p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground">
                                                        <ChevronLeft class="size-3" />
                                                    </button>
                                                    <span class="text-xs font-bold text-amber-500">{{ calView.year }}</span>
                                                    <button type="button" @click="nextYear" :disabled="calView.year >= today.getFullYear()"
                                                        class="flex cursor-pointer items-center justify-center rounded p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground disabled:opacity-25 disabled:cursor-not-allowed">
                                                        <ChevronRight class="size-3" />
                                                    </button>
                                                </div>
                                                <!-- 12 month grid -->
                                                <div class="grid grid-cols-4 gap-1">
                                                    <button
                                                        v-for="(m, idx) in viMonthsShort"
                                                        :key="idx"
                                                        type="button"
                                                        @click="!isMonthFuture(idx) && selectMonth(idx)"
                                                        :disabled="isMonthFuture(idx)"
                                                        class="rounded-lg py-1.5 text-[11px] font-semibold transition-all"
                                                        :class="[
                                                            calView.month === idx && !isMonthFuture(idx)
                                                                ? 'bg-amber-500 text-zinc-950 shadow-sm'
                                                                : '',
                                                            !isMonthFuture(idx) && calView.month !== idx
                                                                ? 'hover:bg-amber-500/15 hover:text-amber-600 cursor-pointer text-foreground'
                                                                : '',
                                                            isMonthFuture(idx)
                                                                ? 'cursor-not-allowed text-muted-foreground/25'
                                                                : '',
                                                        ]"
                                                    >{{ m }}</button>
                                                </div>
                                            </div>
                                        </Transition>

                                        <!-- ── Day names ── -->
                                        <div class="grid grid-cols-7 border-b border-border/50 bg-muted/20 px-1.5 pt-1.5 pb-1">
                                            <div v-for="d in viDays" :key="d"
                                                class="text-center text-[9px] font-bold tracking-widest"
                                                :class="d === 'CN' ? 'text-rose-500' : 'text-muted-foreground'">
                                                {{ d }}
                                            </div>
                                        </div>

                                        <!-- ── Day grid ── -->
                                        <div class="grid grid-cols-7 gap-0 p-1.5">
                                            <button
                                                v-for="day in calDays"
                                                :key="day.date"
                                                type="button"
                                                @click="selectDate(day)"
                                                :disabled="day.isFuture"
                                                class="relative flex h-7 w-full items-center justify-center rounded-md text-[11px] font-medium transition-all"
                                                :class="[
                                                    day.isSelected
                                                        ? 'bg-amber-500 text-zinc-950 font-extrabold shadow-md shadow-amber-500/30 scale-110'
                                                        : '',
                                                    day.isToday && !day.isSelected
                                                        ? 'border border-amber-500 text-amber-500 font-bold'
                                                        : '',
                                                    day.inMonth && !day.isSelected && !day.isToday && !day.isFuture
                                                        ? 'text-foreground hover:bg-amber-500/15 hover:text-amber-600 cursor-pointer'
                                                        : '',
                                                    !day.inMonth && !day.isFuture
                                                        ? 'text-muted-foreground/25 cursor-pointer'
                                                        : '',
                                                    day.isFuture ? 'cursor-not-allowed text-muted-foreground/15' : '',
                                                ]"
                                            >
                                                {{ day.day }}
                                                <span v-if="day.isToday && !day.isSelected"
                                                    class="absolute bottom-0.5 left-1/2 h-0.5 w-0.5 -translate-x-1/2 rounded-full bg-amber-500" />
                                            </button>
                                        </div>

                                        <!-- ── Footer: Hôm nay ── -->
                                        <div class="border-t border-border px-2 py-1.5">
                                            <button type="button"
                                                @click="selectDate({ date: todayStr, day: today.getDate(), inMonth: true, isToday: true, isFuture: false, isSelected: form.closing_date === todayStr })"
                                                class="w-full cursor-pointer rounded-lg bg-amber-500/10 py-1.5 text-[11px] font-semibold text-amber-600 transition hover:bg-amber-500 hover:text-zinc-950 dark:text-amber-400">
                                                Hôm nay · {{ new Date().toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' }) }}
                                            </button>
                                        </div>
                                    </div>
                                </Transition>
                            </Teleport>

                            <!-- Ca qua đêm notice -->
                            <div
                                v-if="selectedShift?.is_overnight"
                                class="flex items-start gap-2 rounded-xl border border-blue-200 bg-blue-50 p-3 text-xs text-blue-700 dark:border-blue-900 dark:bg-blue-900/20 dark:text-blue-300"
                            >
                                <Info class="mt-0.5 size-3.5 shrink-0" />
                                <span>Ca qua đêm — kết thúc vào rạng sáng ngày hôm sau. Hệ thống sẽ tự tính đúng khung giờ.</span>
                            </div>

                            <!-- Error -->
                            <p v-if="previewError" class="text-sm text-rose-500">{{ previewError }}</p>
                        </div>
                    </template>

                    <!-- ── Step 2: Preview + Nhập tiền ─────────────────── -->
                    <template v-else-if="previewData">
                        <!-- Header ca -->
                        <div class="mb-3 flex items-center justify-between">
                            <div>
                                <p class="font-semibold text-sm">{{ previewData.shift_name }}</p>
                                <p class="text-xs text-muted-foreground">{{ previewData.start_time }} → {{ previewData.end_time }}</p>
                            </div>
                            <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                                {{ previewData.order_count }} đơn hoàn thành
                            </span>
                        </div>

                        <!-- Revenue summary grid -->
                        <div class="mb-3 grid grid-cols-3 gap-2">
                            <div class="rounded-xl bg-muted/60 p-3 text-center">
                                <p class="text-[10px] text-muted-foreground uppercase tracking-wider mb-1">Doanh thu gộp</p>
                                <p class="font-bold text-sm text-emerald-600 dark:text-emerald-400">{{ compact(previewData.gross_revenue) }}</p>
                            </div>
                            <div class="rounded-xl bg-muted/60 p-3 text-center">
                                <p class="text-[10px] text-muted-foreground uppercase tracking-wider mb-1">Giảm giá</p>
                                <p class="font-bold text-sm text-rose-500">-{{ compact(previewData.discount_total) }}</p>
                            </div>
                            <div class="rounded-xl bg-amber-500/10 border border-amber-500/20 p-3 text-center">
                                <p class="text-[10px] text-amber-700 dark:text-amber-400 uppercase tracking-wider mb-1">Doanh thu thuần</p>
                                <p class="font-bold text-sm text-amber-700 dark:text-amber-400">{{ compact(previewData.net_revenue) }}</p>
                            </div>
                        </div>

                        <!-- Payment breakdown -->
                        <div class="mb-4 rounded-xl border border-border bg-muted/30 p-3">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-muted-foreground mb-2.5">Cơ cấu thanh toán</p>
                            <div class="space-y-1.5">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="flex items-center gap-2">
                                        <span class="inline-block h-2 w-2 rounded-full bg-blue-500"></span>
                                        <span class="text-muted-foreground">Tiền mặt</span>
                                    </span>
                                    <span class="font-semibold text-blue-600 dark:text-blue-400">{{ vnd(previewData.expected_cash) }}</span>
                                </div>
                                <div v-if="previewData.bank_transfer > 0" class="flex items-center justify-between text-sm">
                                    <span class="flex items-center gap-2">
                                        <span class="inline-block h-2 w-2 rounded-full bg-violet-500"></span>
                                        <span class="text-muted-foreground">Chuyển khoản</span>
                                    </span>
                                    <span class="font-semibold text-violet-600 dark:text-violet-400">{{ vnd(previewData.bank_transfer) }}</span>
                                </div>
                                <div v-if="previewData.card > 0" class="flex items-center justify-between text-sm">
                                    <span class="flex items-center gap-2">
                                        <span class="inline-block h-2 w-2 rounded-full bg-sky-500"></span>
                                        <span class="text-muted-foreground">Thẻ ngân hàng</span>
                                    </span>
                                    <span class="font-semibold text-sky-600 dark:text-sky-400">{{ vnd(previewData.card) }}</span>
                                </div>
                                <div v-if="previewData.ewallet > 0" class="flex items-center justify-between text-sm">
                                    <span class="flex items-center gap-2">
                                        <span class="inline-block h-2 w-2 rounded-full bg-pink-500"></span>
                                        <span class="text-muted-foreground">Ví điện tử</span>
                                    </span>
                                    <span class="font-semibold text-pink-600 dark:text-pink-400">{{ vnd(previewData.ewallet) }}</span>
                                </div>
                                <div v-if="previewData.mixed > 0" class="flex items-center justify-between text-sm">
                                    <span class="flex items-center gap-2">
                                        <span class="inline-block h-2 w-2 rounded-full bg-orange-500"></span>
                                        <span class="text-muted-foreground">Hỗn hợp</span>
                                    </span>
                                    <span class="font-semibold text-orange-600">{{ vnd(previewData.mixed) }}</span>
                                </div>
                                <div class="flex items-center justify-between border-t border-border pt-1.5 text-sm font-semibold">
                                    <span>Tổng thu</span>
                                    <span>{{ vnd(previewData.expected_cash + previewData.transfer_amount) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Warnings -->
                        <div
                            v-if="previewData.already_closed"
                            class="mb-4 flex items-start gap-2.5 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-900/20 dark:text-rose-300"
                        >
                            <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                            <span>Ca <strong>{{ previewData.shift_name }}</strong> ngày này đã được chốt. Không thể tạo lại.</span>
                        </div>
                        <div
                            v-else-if="previewData.pending_orders > 0"
                            class="mb-4 flex items-start gap-2.5 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700 dark:border-amber-900 dark:bg-amber-900/20 dark:text-amber-300"
                        >
                            <AlertTriangle class="mt-0.5 size-4 shrink-0" />
                            <span>Còn <strong>{{ previewData.pending_orders }}</strong> đơn chưa hoàn thành trong ca. Những đơn này sẽ không được tính vào doanh thu.</span>
                        </div>

                        <!-- Input actual cash -->
                        <div class="space-y-4">
                            <div class="space-y-1.5">
                                <label class="text-sm font-medium">
                                    Tiền mặt thực tế trong két <span class="text-rose-500">*</span>
                                </label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm font-bold text-muted-foreground">₫</span>
                                    <input
                                        v-model.number="form.actual_cash"
                                        type="number"
                                        min="0"
                                        step="1000"
                                        class="w-full rounded-xl border border-border bg-background py-2.5 pl-8 pr-3 text-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20"
                                        :class="{ 'border-rose-400': form.errors.actual_cash }"
                                    />
                                </div>
                                <p v-if="form.errors.actual_cash" class="text-xs text-rose-500">{{ form.errors.actual_cash }}</p>
                            </div>

                            <!-- Chênh lệch live preview -->
                            <div
                                class="flex items-center justify-between rounded-xl border px-4 py-3 text-sm font-semibold transition-colors"
                                :class="cashDifference >= 0
                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-900/20 dark:text-emerald-300'
                                    : 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900 dark:bg-rose-900/20 dark:text-rose-300'"
                            >
                                <span class="flex items-center gap-2">
                                    <component :is="cashDifference >= 0 ? ArrowUpCircle : ArrowDownCircle" class="size-4" />
                                    Chênh lệch tiền mặt
                                </span>
                                <span class="text-base font-bold">
                                    {{ cashDifference >= 0 ? '+' : '' }}{{ vnd(cashDifference) }}
                                </span>
                            </div>

                            <!-- Chi phí phát sinh -->
                            <div class="space-y-1.5">
                                <label class="text-sm font-medium text-muted-foreground">Chi phí phát sinh (nếu có)</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm font-bold text-muted-foreground">₫</span>
                                    <input
                                        v-model.number="form.other_expense_amount"
                                        type="number"
                                        min="0"
                                        step="1000"
                                        class="w-full rounded-xl border border-border bg-background py-2.5 pl-8 pr-3 text-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20"
                                    />
                                </div>
                            </div>

                            <!-- Ghi chú -->
                            <div class="space-y-1.5">
                                <label class="text-sm font-medium text-muted-foreground">Ghi chú</label>
                                <textarea
                                    v-model="form.notes"
                                    rows="2"
                                    maxlength="1000"
                                    placeholder="Tình huống bất thường trong ca, lý do chênh lệch..."
                                    class="w-full resize-none rounded-xl border border-border bg-background px-3 py-2.5 text-sm focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500/20"
                                />
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Dialog footer -->
                <div class="flex items-center justify-between border-t border-border px-6 py-4">
                    <button
                        @click="dialogStep === 1 ? (showDialog = false) : (dialogStep = 1)"
                        class="cursor-pointer rounded-xl border border-border px-4 py-2 text-sm font-medium text-muted-foreground transition hover:bg-muted"
                    >
                        {{ dialogStep === 1 ? 'Huỷ' : '← Quay lại' }}
                    </button>

                    <div class="flex gap-2">
                        <!-- Step 1 action -->
                        <button
                            v-if="dialogStep === 1"
                            @click="loadPreview"
                            :disabled="previewLoading || !form.shift_id"
                            class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-amber-500 px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-600 active:scale-95 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            <Loader2 v-if="previewLoading" class="size-4 animate-spin" />
                            <span>{{ previewLoading ? 'Đang tải...' : 'Tải dữ liệu →' }}</span>
                        </button>

                        <!-- Step 2 actions -->
                        <template v-if="dialogStep === 2 && previewData && !previewData.already_closed">
                            <button
                                @click="submitForm(false)"
                                :disabled="form.processing"
                                class="cursor-pointer rounded-xl border border-border px-4 py-2 text-sm font-medium transition hover:bg-muted active:scale-95 disabled:opacity-50"
                            >
                                Lưu nháp
                            </button>
                            <button
                                @click="submitForm(true)"
                                :disabled="form.processing"
                                class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-emerald-600 px-5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 active:scale-95 disabled:opacity-50"
                            >
                                <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                                <Check v-else class="size-4" />
                                Nộp chốt ca
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </Transition>

    <!-- ══ Dispute Dialog ════════════════════════════════════════════════════ -->
    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="disputeTarget"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm"
            @click.self="disputeTarget = null"
        >
            <div class="w-full max-w-md overflow-hidden rounded-2xl border border-border bg-card shadow-2xl">
                <div class="flex items-center gap-3 border-b border-border px-6 py-4">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-rose-500/10 text-rose-600">
                        <AlertTriangle class="size-5" />
                    </div>
                    <div>
                        <p class="font-semibold">Tranh chấp phiếu chốt ca</p>
                        <p class="text-xs text-muted-foreground">{{ disputeTarget.shift_name }} — {{ disputeTarget.closing_date }}</p>
                    </div>
                </div>
                <div class="px-6 py-4 space-y-3">
                    <label class="text-sm font-medium">Lý do tranh chấp <span class="text-rose-500">*</span></label>
                    <textarea
                        v-model="disputeNotes"
                        rows="3"
                        maxlength="1000"
                        placeholder="Mô tả cụ thể vấn đề cần xem xét lại..."
                        class="w-full resize-none rounded-xl border border-border bg-background px-3 py-2.5 text-sm focus:border-rose-400 focus:outline-none focus:ring-2 focus:ring-rose-400/20"
                    />
                </div>
                <div class="flex justify-end gap-2 border-t border-border px-6 py-4">
                    <button
                        @click="disputeTarget = null"
                        class="cursor-pointer rounded-xl border border-border px-4 py-2 text-sm font-medium text-muted-foreground hover:bg-muted"
                    >
                        Huỷ
                    </button>
                    <button
                        @click="submitDispute"
                        :disabled="disputeLoading"
                        class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-rose-600 px-5 py-2 text-sm font-semibold text-white transition hover:bg-rose-700 active:scale-95 disabled:opacity-50"
                    >
                        <Loader2 v-if="disputeLoading" class="size-4 animate-spin" />
                        Xác nhận tranh chấp
                    </button>
                </div>
            </div>
        </div>
    </Transition>
</template>
