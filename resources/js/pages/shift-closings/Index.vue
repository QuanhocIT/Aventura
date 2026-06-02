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
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
    draft:     { label: 'Bản nháp',    badgeClass: 'bg-slate-100 text-slate-600 border border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700/50',      dotClass: 'bg-slate-400' },
    submitted: { label: 'Chờ duyệt',   badgeClass: 'bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-900/30',   dotClass: 'bg-amber-500 animate-pulse' },
    confirmed: { label: 'Đã xác nhận', badgeClass: 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-900/30', dotClass: 'bg-emerald-500' },
    disputed:  { label: 'Tranh chấp',  badgeClass: 'bg-rose-50 text-rose-700 border border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-900/30',       dotClass: 'bg-rose-500' },
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
    form.transform((data: any) => ({ ...data, submit: isSubmit ? 1 : 0 }))
        .post('/shift-closings', {
        onSuccess: () => {
            showDialog.value = false;
            form.reset();
            previewData.value = null;
            dialogStep.value = 1;
            isSubmitting.value = false;
            toast.success(isSubmit ? 'Đã nộp phiếu chốt ca thành công!' : 'Đã lưu bản nháp.');
        },
        onError: (errors: any) => {
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
            onSuccess: () => toast.success('Đã xác nhận chốt ca thành công.'),
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

const counts = computed(() => {
    return {
        all:       props.closings.length,
        draft:     props.closings.filter(c => c.status === 'draft').length,
        submitted: props.closings.filter(c => c.status === 'submitted').length,
        confirmed: props.closings.filter(c => c.status === 'confirmed').length,
        disputed:  props.closings.filter(c => c.status === 'disputed').length,
    };
});

const statusOptions = computed(() => [
    { value: 'all',       label: 'Tất cả',     count: counts.value.all },
    { value: 'draft',     label: 'Bản nháp',    count: counts.value.draft },
    { value: 'submitted', label: 'Chờ duyệt',   count: counts.value.submitted },
    { value: 'confirmed', label: 'Đã xác nhận', count: counts.value.confirmed },
    { value: 'disputed',  label: 'Tranh chấp',  count: counts.value.disputed },
]);

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

    <div class="flex flex-col gap-6 p-6 max-w-7xl mx-auto w-full">

        <!-- ── Page Header ─────────────────────────────────────────────────── -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b pb-5">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 shadow-sm border border-indigo-100 dark:border-indigo-900/30">
                    <ClipboardCheck class="size-6" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-slate-800 dark:text-slate-100">Chốt Ca & Doanh Thu</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Quản lý phiếu chốt ca, đối soát tiền mặt và doanh thu chi tiết.</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <!-- Month picker -->
                <div class="flex items-center gap-1.5">
                    <Label for="shift-month" class="text-xs shrink-0 font-semibold text-slate-600">Chọn tháng:</Label>
                    <Input
                        id="shift-month"
                        v-model="activeMonth"
                        type="month"
                        class="h-9 w-36 text-xs font-semibold py-1 bg-white"
                    />
                </div>

                <!-- Chốt ca mới button -->
                <Button
                    @click="openDialog"
                    class="h-9 text-xs bg-indigo-600 hover:bg-indigo-700 text-white font-semibold flex items-center gap-1.5 shadow-sm active:scale-95 transition-transform"
                >
                    <ClipboardCheck class="size-4" />
                    Chốt ca mới
                </Button>
            </div>
        </div>

        <!-- ── KPI Cards ───────────────────────────────────────────────────── -->
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <!-- Tổng phiếu -->
            <Card class="shadow-xs hover:translate-y-[-2px] transition-transform duration-200">
                <CardHeader class="pb-2 flex flex-row items-center justify-between">
                    <CardDescription class="text-xs font-bold uppercase tracking-wider text-slate-400">Tổng phiếu</CardDescription>
                    <ReceiptText class="size-4 text-slate-400" />
                </CardHeader>
                <CardContent class="pb-3">
                    <p class="text-2xl font-black text-slate-800 dark:text-slate-100">{{ kpi.total_closings }}</p>
                    <p class="mt-0.5 text-xs text-muted-foreground">phiếu trong tháng</p>
                </CardContent>
            </Card>

            <!-- Tổng doanh thu gộp -->
            <Card class="shadow-xs border-emerald-100 dark:border-emerald-950/20 hover:translate-y-[-2px] transition-transform duration-200">
                <CardHeader class="pb-2 flex flex-row items-center justify-between">
                    <CardDescription class="text-xs font-bold uppercase tracking-wider text-emerald-500">Doanh thu gộp</CardDescription>
                    <BadgeDollarSign class="size-4 text-emerald-500" />
                </CardHeader>
                <CardContent class="pb-3">
                    <p class="text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ compact(kpi.total_gross) }}</p>
                    <p class="mt-0.5 text-xs text-muted-foreground">tiền mặt + chuyển khoản</p>
                </CardContent>
            </Card>

            <!-- Tiền mặt thực -->
            <Card class="shadow-xs border-blue-100 dark:border-blue-950/20 hover:translate-y-[-2px] transition-transform duration-200">
                <CardHeader class="pb-2 flex flex-row items-center justify-between">
                    <CardDescription class="text-xs font-bold uppercase tracking-wider text-blue-500">Tiền mặt thực</CardDescription>
                    <Wallet class="size-4 text-blue-500" />
                </CardHeader>
                <CardContent class="pb-3">
                    <p class="text-2xl font-black text-blue-600 dark:text-blue-400">{{ compact(kpi.total_cash) }}</p>
                    <p class="mt-0.5 text-xs text-muted-foreground">tổng actual cash</p>
                </CardContent>
            </Card>

            <!-- Chênh lệch tổng -->
            <Card class="shadow-xs hover:translate-y-[-2px] transition-transform duration-200"
                :class="kpi.total_difference >= 0 ? 'border-emerald-100 dark:border-emerald-950/20' : 'border-rose-100 dark:border-rose-950/20'">
                <CardHeader class="pb-2 flex flex-row items-center justify-between">
                    <CardDescription class="text-xs font-bold uppercase tracking-wider"
                        :class="kpi.total_difference >= 0 ? 'text-emerald-500' : 'text-rose-500'">Chênh lệch</CardDescription>
                    <component
                        :is="kpi.total_difference >= 0 ? ArrowUpCircle : ArrowDownCircle"
                        :class="kpi.total_difference >= 0 ? 'text-emerald-500' : 'text-rose-500'"
                        class="size-4"
                    />
                </CardHeader>
                <CardContent class="pb-3">
                    <p class="text-2xl font-black"
                        :class="kpi.total_difference >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'">
                        {{ kpi.total_difference >= 0 ? '+' : '' }}{{ compact(kpi.total_difference) }}
                    </p>
                    <p class="mt-0.5 text-xs text-muted-foreground">thừa/thiếu so sổ sách</p>
                </CardContent>
            </Card>
        </div>

        <!-- ── Table Card ───────────────────────────────────────────────────── -->
        <Card class="shadow-sm overflow-hidden">
            <CardHeader class="pb-3 border-b flex flex-row items-center justify-between bg-slate-50/50 dark:bg-slate-900/50">
                <div>
                    <CardTitle class="text-base flex items-center gap-1.5 font-bold">
                        <ClipboardCheck class="size-5 text-indigo-600 dark:text-indigo-400" />
                        Nhật Ký Phiếu Chốt Ca Chi Tiết
                    </CardTitle>
                    <CardDescription>Báo cáo doanh thu gộp, số tiền mặt đếm thực tế, và chi tiết chênh lệch két của từng ca trực.</CardDescription>
                </div>
            </CardHeader>

            <CardContent class="p-0">
                <!-- Filters Row -->
                <div class="p-4 border-b border-slate-100 dark:border-slate-800 bg-white dark:bg-slate-950 flex flex-wrap items-center justify-between gap-3">
                    <!-- Status Filter Tabs -->
                    <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-900 rounded-xl p-0.5 border border-slate-200/50 dark:border-slate-800 shrink-0">
                        <button
                            v-for="opt in statusOptions"
                            :key="opt.value"
                            type="button"
                            @click="activeStatus = opt.value"
                            :class="[
                                'inline-flex items-center gap-1.5 px-3 py-1.5 text-[10px] font-bold rounded-lg transition-colors whitespace-nowrap',
                                activeStatus === opt.value
                                    ? 'bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-100 shadow-sm border border-slate-200/10 dark:border-slate-700/20'
                                    : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'
                            ]"
                        >
                            {{ opt.label }}
                            <span
                                v-if="opt.count !== null"
                                :class="[
                                    'inline-flex items-center justify-center rounded-full text-[9px] font-black w-4.5 h-4.5 transition-colors',
                                    activeStatus === opt.value
                                        ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950/80 dark:text-indigo-300'
                                        : 'bg-slate-200/60 dark:bg-slate-800 text-slate-500 dark:text-slate-400'
                                ]"
                            >{{ opt.count }}</span>
                        </button>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-if="closings.length === 0" class="flex flex-col items-center gap-3 py-20 text-center text-muted-foreground">
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 shadow-sm border border-indigo-100 dark:border-indigo-900/30">
                        <ClipboardCheck class="size-7" />
                    </div>
                    <p class="font-bold text-slate-800 dark:text-slate-200">Chưa có phiếu chốt ca nào</p>
                    <p class="text-xs text-slate-500 max-w-sm mx-auto">Vui lòng nhấn nút "Chốt ca mới" ở trên để hệ thống tự động sinh dữ liệu nháp dựa trên công ca thực tế.</p>
                </div>

                <!-- Table Content -->
                <template v-else>
                    <!-- Table Header -->
                    <div class="hidden grid-cols-[auto_1fr_1fr_1fr_1fr_1fr_auto] gap-4 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/30 px-5 py-3 text-[10px] font-bold uppercase tracking-wider text-slate-500 lg:grid">
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
                        class="border-b border-slate-100 dark:border-slate-800 last:border-0"
                    >
                        <!-- Main Row -->
                        <div
                            class="grid cursor-pointer grid-cols-[auto_1fr_auto] items-center gap-3 px-4 py-4 transition hover:bg-slate-50/60 dark:hover:bg-slate-900/30 lg:grid-cols-[auto_1fr_1fr_1fr_1fr_1fr_auto] lg:gap-4 lg:px-5"
                            @click="toggleExpand(closing.id)"
                        >
                            <!-- Expand toggle -->
                            <component
                                :is="expandedId === closing.id ? ChevronUp : ChevronDown"
                                class="size-4 shrink-0 text-slate-400"
                            />

                            <!-- Ngày / Ca -->
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-sm">{{ closing.closing_date }}</p>
                                <div class="mt-0.5 flex items-center gap-1.5">
                                    <span class="rounded bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 text-[9px] font-bold text-slate-500 dark:text-slate-400 border">{{ closing.shift_code }}</span>
                                    <span class="text-xs text-slate-400 font-medium truncate">{{ closing.shift_name }}</span>
                                    <span class="hidden text-[10px] text-slate-400 lg:inline font-mono">({{ closing.shift_start }}–{{ closing.shift_end }})</span>
                                </div>
                            </div>

                            <!-- Cashier -->
                            <div class="hidden items-center gap-2 lg:flex">
                                <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-indigo-500/10 text-xs font-bold text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-400 border dark:border-indigo-900/30">
                                    {{ closing.cashier_name?.slice(0, 1) ?? '?' }}
                                </div>
                                <span class="truncate text-sm text-slate-500 font-semibold">{{ closing.cashier_name }}</span>
                            </div>

                            <!-- Doanh thu gộp -->
                            <div class="hidden text-right lg:block font-mono">
                                <p class="font-bold text-sm">{{ compact(closing.gross_revenue) }}</p>
                                <p class="text-[10px] text-slate-400 mt-0.5 font-medium">
                                    <span class="text-blue-500">TM: {{ compact(closing.expected_cash) }}</span>
                                    <span class="mx-1">·</span>
                                    <span class="text-violet-500">CK: {{ compact(closing.transfer_amount) }}</span>
                                </p>
                            </div>

                            <!-- Tiền mặt thực -->
                            <div class="hidden text-right lg:block font-mono">
                                <p class="font-bold text-sm text-slate-700 dark:text-slate-300">{{ compact(closing.actual_cash) }}</p>
                            </div>

                            <!-- Chênh lệch -->
                            <div class="hidden text-right lg:block font-mono">
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

                            <!-- Status badge -->
                            <div class="flex shrink-0 items-center gap-2">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider"
                                    :class="statusConfig[closing.status].badgeClass"
                                >
                                    <span class="size-1.5 rounded-full" :class="statusConfig[closing.status].dotClass"></span>
                                    {{ statusConfig[closing.status].label }}
                                </span>
                            </div>
                        </div>

                        <!-- Expanded Detail Row -->
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
                                class="overflow-hidden border-t border-slate-100 dark:border-slate-800 bg-slate-50/40 dark:bg-slate-900/20 px-5 py-5"
                            >
                                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                                    <!-- Revenue breakdown -->
                                    <div class="space-y-3 bg-white dark:bg-slate-950 p-4 border rounded-xl shadow-2xs">
                                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Doanh thu chi tiết</p>
                                        <div class="space-y-2 text-xs font-semibold">
                                            <div class="flex justify-between text-slate-600 dark:text-slate-300">
                                                <span class="font-medium">Doanh thu gộp</span>
                                                <span class="font-mono">{{ vnd(closing.gross_revenue) }}</span>
                                            </div>
                                            <div class="flex justify-between text-blue-500 border-t pt-2 mt-1">
                                                <span class="flex items-center gap-1 font-medium">
                                                    <Wallet class="size-3" /> Tiền mặt sổ sách
                                                </span>
                                                <span class="font-mono font-bold">{{ vnd(closing.expected_cash) }}</span>
                                            </div>
                                            <div class="flex justify-between text-violet-500">
                                                <span class="flex items-center gap-1 font-medium">
                                                    <CreditCard class="size-3" /> Chuyển khoản gộp
                                                </span>
                                                <span class="font-mono font-bold">{{ vnd(closing.transfer_amount) }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Cash reconciliation -->
                                    <div class="space-y-3 bg-white dark:bg-slate-950 p-4 border rounded-xl shadow-2xs">
                                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Đối soát tiền mặt</p>
                                        <div class="space-y-2 text-xs font-semibold">
                                            <div class="flex justify-between text-slate-600 dark:text-slate-300">
                                                <span class="font-medium">Kỳ vọng sổ sách</span>
                                                <span class="font-mono">{{ vnd(closing.expected_cash) }}</span>
                                            </div>
                                            <div class="flex justify-between text-slate-700 dark:text-slate-200">
                                                <span class="font-medium">Thực tế đếm két</span>
                                                <span class="font-mono font-bold">{{ vnd(closing.actual_cash) }}</span>
                                            </div>
                                            <div class="flex justify-between border-t border-slate-100 dark:border-slate-800 pt-2 text-slate-800 dark:text-slate-200">
                                                <span class="font-bold">Chênh lệch két</span>
                                                <span
                                                    class="font-mono font-black"
                                                    :class="closing.cash_difference >= 0 ? 'text-emerald-600' : 'text-rose-600'"
                                                >
                                                    {{ closing.cash_difference >= 0 ? '+' : '' }}{{ vnd(closing.cash_difference) }}
                                                </span>
                                            </div>
                                            <div v-if="closing.other_expense > 0" class="flex justify-between text-[10px] text-rose-500 font-medium">
                                                <span>Chi phí phát sinh trong ca</span>
                                                <span class="font-mono font-bold">-{{ vnd(closing.other_expense) }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Meta info -->
                                    <div class="space-y-3 bg-white dark:bg-slate-950 p-4 border rounded-xl shadow-2xs">
                                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Thông tin ca trực</p>
                                        <div class="space-y-2 text-xs font-semibold">
                                            <div class="flex items-center gap-2 text-slate-600 dark:text-slate-300">
                                                <Clock class="size-3 shrink-0 text-slate-400" />
                                                <span>Nộp lúc: {{ closing.closed_at ?? '—' }}</span>
                                            </div>
                                            <div v-if="closing.confirmed_by_name" class="flex items-center gap-2 text-slate-600 dark:text-slate-300">
                                                <Check class="size-3 shrink-0 text-emerald-500 font-bold" />
                                                <span>Duyệt bởi: <strong>{{ closing.confirmed_by_name }}</strong></span>
                                            </div>
                                            <div v-if="closing.notes" class="text-[11px] text-slate-400 font-semibold border-t pt-2 mt-1">
                                                <p class="font-bold text-slate-500">Ghi chú vận hành:</p>
                                                <p class="mt-0.5 whitespace-pre-line leading-relaxed">{{ closing.notes }}</p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Actions -->
                                    <div class="space-y-3 bg-white dark:bg-slate-950 p-4 border rounded-xl shadow-2xs flex flex-col justify-between">
                                        <div>
                                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-2">Hành động</p>
                                            <template v-if="canConfirm">
                                                <div class="flex flex-col gap-2">
                                                    <Button
                                                        v-if="closing.status === 'submitted'"
                                                        @click.stop="confirmClosing(closing)"
                                                        class="h-8 text-xs bg-emerald-600 hover:bg-emerald-700 text-white font-semibold flex items-center justify-center gap-1 shadow-sm active:scale-95 transition-transform"
                                                    >
                                                        <Check class="size-3.5" />
                                                        Duyệt & Chốt
                                                    </Button>
                                                    <Button
                                                        v-if="closing.status === 'submitted' || closing.status === 'confirmed'"
                                                        @click.stop="openDisputeDialog(closing)"
                                                        variant="outline"
                                                        class="h-8 text-xs text-rose-600 border-rose-100 hover:bg-rose-50 font-semibold flex items-center justify-center gap-1 shadow-sm active:scale-95 transition-transform"
                                                    >
                                                        <AlertTriangle class="size-3.5" />
                                                        Yêu cầu đối soát lại (Khiếu nại)
                                                    </Button>
                                                </div>
                                            </template>
                                            <span v-if="closing.status === 'draft'" class="text-xs text-slate-400 font-semibold italic">Nhân viên chưa nộp phiếu</span>
                                            <span v-if="closing.status === 'confirmed' && !canConfirm" class="flex items-center gap-1 text-xs text-emerald-600 dark:text-emerald-400 font-bold">
                                                <Check class="size-3" /> Đã duyệt chốt thành công
                                            </span>
                                            <span v-if="closing.status === 'disputed' && !canConfirm" class="flex items-center gap-1 text-xs text-rose-600 dark:text-rose-400 font-bold">
                                                <AlertTriangle class="size-3" /> Đang giải quyết tranh chấp
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </Transition>
                    </div>
                </template>
            </CardContent>
        </Card>
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
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
            @click.self="showDialog = false"
        >
            <Card class="w-full max-w-lg overflow-hidden animate-in fade-in zoom-in-95 duration-150 shadow-2xl flex flex-col" style="max-height: 90vh;">

                <!-- Dialog Header -->
                <CardHeader class="pb-3 border-b flex flex-row items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-indigo-50 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 border border-indigo-100 dark:border-indigo-900/30">
                            <ClipboardCheck class="size-5" />
                        </div>
                        <div>
                            <CardTitle class="text-base text-indigo-600 dark:text-indigo-400">Lập Phiếu Chốt Ca Mới</CardTitle>
                            <CardDescription>
                                Bước {{ dialogStep }} / 2 — {{ dialogStep === 1 ? 'Chọn ca và ngày chốt ca làm việc' : 'Nhập đối soát tiền thực tế đếm được' }}
                            </CardDescription>
                        </div>
                    </div>
                    <button @click="showDialog = false" class="p-1 rounded-lg hover:bg-muted text-muted-foreground hover:text-foreground">
                        <X class="size-4" />
                    </button>
                </CardHeader>

                <!-- Step Progress Bar -->
                <div class="flex h-1 bg-slate-100 dark:bg-slate-800">
                    <div
                        class="bg-indigo-600 transition-all duration-300"
                        :style="{ width: dialogStep === 1 ? '50%' : '100%' }"
                    />
                </div>

                <!-- Dialog Body -->
                <div class="flex-1 overflow-y-auto px-6 py-5">

                    <!-- ── Step 1: Chọn ca & ngày ───────────────────────── -->
                    <template v-if="dialogStep === 1">
                        <div class="space-y-4">
                            <!-- Chọn ca -->
                            <div class="space-y-1.5 flex flex-col">
                                <Label class="text-xs font-bold text-slate-500 uppercase tracking-wide">Ca làm việc cần chốt <span class="text-rose-500">*</span></Label>
                                <select
                                    v-model="form.shift_id"
                                    class="w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 font-semibold text-slate-700 mt-1.5"
                                >
                                    <option :value="null" disabled>Chọn ca...</option>
                                    <option v-for="shift in shifts" :key="shift.id" :value="shift.id">
                                        {{ shift.name }} ({{ shift.start_time }} – {{ shift.end_time }}{{ shift.is_overnight ? ' +1 ngày' : '' }})
                                    </option>
                                </select>
                            </div>

                            <!-- Chọn ngày — Custom Calendar Picker -->
                            <div class="space-y-1.5 flex flex-col">
                                <Label class="text-xs font-bold text-slate-500 uppercase tracking-wide">Ngày chốt ca <span class="text-rose-500">*</span></Label>

                                <!-- Trigger button -->
                                <button
                                    ref="calTriggerRef"
                                    type="button"
                                    @click="openCalendar"
                                    class="flex w-full cursor-pointer items-center justify-between rounded-md border border-slate-200 bg-background px-3 py-2 text-sm transition hover:border-indigo-500/60 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 font-semibold text-slate-700 mt-1.5"
                                    :class="showCalendar ? 'border-indigo-500 ring-2 ring-indigo-500/20' : ''"
                                >
                                    <span :class="form.closing_date ? 'text-foreground font-medium' : 'text-muted-foreground'">
                                        {{ displayDate || 'Chọn ngày...' }}
                                    </span>
                                    <CalendarDays class="size-4 text-indigo-500 shrink-0" />
                                </button>
                            </div>

                            <!-- Calendar Teleport -->
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
                                        class="fixed z-[9999] overflow-hidden rounded-xl border border-slate-200 bg-card shadow-2xl animate-in fade-in-50 zoom-in-95 duration-100"
                                        :style="{ top: calPos.top + 'px', left: calPos.left + 'px', width: '272px' }"
                                    >
                                        <!-- Header -->
                                        <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/50 px-3 py-2">
                                            <button type="button" @click="prevMonth"
                                                class="flex cursor-pointer items-center justify-center rounded-md p-1 text-muted-foreground hover:bg-muted hover:text-foreground">
                                                <ChevronLeft class="size-3.5" />
                                            </button>

                                            <!-- Click để mở month picker -->
                                            <button type="button" @click="showMonthPicker = !showMonthPicker"
                                                class="flex cursor-pointer items-center gap-1.5 rounded-md px-2 py-0.5 text-xs font-bold hover:bg-muted transition">
                                                <span>{{ viMonths[calView.month] }}</span>
                                                <span class="text-indigo-655 text-indigo-600 dark:text-indigo-400 font-extrabold">{{ calView.year }}</span>
                                                <ChevronDown class="size-3 text-muted-foreground" :class="showMonthPicker ? 'rotate-180' : ''" />
                                            </button>

                                            <button type="button" @click="nextMonth" :disabled="isNextMonthDisabled"
                                                class="flex cursor-pointer items-center justify-center rounded-md p-1 text-muted-foreground hover:bg-muted hover:text-foreground disabled:cursor-not-allowed disabled:opacity-25">
                                                <ChevronRight class="size-3.5" />
                                            </button>
                                        </div>

                                        <!-- Month picker panel -->
                                        <Transition
                                            enter-active-class="transition duration-150 ease-out"
                                            enter-from-class="opacity-0 -translate-y-2"
                                            enter-to-class="opacity-100 translate-y-0"
                                            leave-active-class="transition duration-100 ease-in"
                                            leave-from-class="opacity-100 translate-y-0"
                                            leave-to-class="opacity-0 -translate-y-2"
                                        >
                                            <div v-if="showMonthPicker" class="border-b border-slate-100 bg-slate-50/20 p-2">
                                                <!-- Year nav -->
                                                <div class="flex items-center justify-between mb-2 px-1">
                                                    <button type="button" @click="prevYear"
                                                        class="flex cursor-pointer items-center justify-center rounded p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground">
                                                        <ChevronLeft class="size-3" />
                                                    </button>
                                                    <span class="text-xs font-bold text-indigo-655 text-indigo-600 dark:text-indigo-400 font-extrabold">{{ calView.year }}</span>
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
                                                                ? 'bg-indigo-600 text-white shadow-sm'
                                                                : '',
                                                            !isMonthFuture(idx) && calView.month !== idx
                                                                ? 'hover:bg-indigo-500/15 hover:text-indigo-600 cursor-pointer text-foreground'
                                                                : '',
                                                            isMonthFuture(idx)
                                                                ? 'cursor-not-allowed text-muted-foreground/25'
                                                                : '',
                                                        ]"
                                                    >{{ m }}</button>
                                                </div>
                                            </div>
                                        </Transition>

                                        <!-- Day names -->
                                        <div class="grid grid-cols-7 border-b border-slate-100/50 bg-slate-50/10 px-1.5 pt-1.5 pb-1">
                                            <div v-for="d in viDays" :key="d"
                                                class="text-center text-[9px] font-bold tracking-widest"
                                                :class="d === 'CN' ? 'text-rose-500' : 'text-muted-foreground'">
                                                {{ d }}
                                            </div>
                                        </div>

                                        <!-- Day grid -->
                                        <div class="grid grid-cols-7 gap-0 p-1.5 bg-white">
                                            <button
                                                v-for="day in calDays"
                                                :key="day.date"
                                                type="button"
                                                @click="selectDate(day)"
                                                :disabled="day.isFuture"
                                                class="relative flex h-7 w-full items-center justify-center rounded-md text-[11px] font-medium transition-all"
                                                :class="[
                                                    day.isSelected
                                                        ? 'bg-indigo-600 text-white font-extrabold shadow-md shadow-indigo-500/30 scale-110'
                                                        : '',
                                                    day.isToday && !day.isSelected
                                                        ? 'border border-indigo-500 text-indigo-655 text-indigo-600 dark:text-indigo-400 font-bold'
                                                        : '',
                                                    day.inMonth && !day.isSelected && !day.isToday && !day.isFuture
                                                        ? 'text-foreground hover:bg-indigo-500/15 hover:text-indigo-600 cursor-pointer'
                                                        : '',
                                                    !day.inMonth && !day.isFuture
                                                        ? 'text-muted-foreground/25 cursor-pointer'
                                                        : '',
                                                    day.isFuture ? 'cursor-not-allowed text-muted-foreground/15' : '',
                                                ]"
                                            >
                                                {{ day.day }}
                                                <span v-if="day.isToday && !day.isSelected"
                                                    class="absolute bottom-0.5 left-1/2 h-0.5 w-0.5 -translate-x-1/2 rounded-full bg-indigo-500" />
                                            </button>
                                        </div>

                                        <!-- Footer: Hôm nay -->
                                        <div class="border-t border-slate-100 px-2 py-1.5 bg-white">
                                            <button type="button"
                                                @click="selectDate({ date: todayStr, day: today.getDate(), inMonth: true, isToday: true, isFuture: false, isSelected: form.closing_date === todayStr })"
                                                class="w-full cursor-pointer rounded-lg bg-indigo-500/10 py-1.5 text-[11px] font-semibold text-indigo-600 transition hover:bg-indigo-600 hover:text-white dark:text-indigo-400">
                                                Hôm nay · {{ new Date().toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' }) }}
                                            </button>
                                        </div>
                                    </div>
                                </Transition>
                            </Teleport>

                            <!-- Ca qua đêm notice -->
                            <div
                                v-if="selectedShift?.is_overnight"
                                class="flex items-start gap-2 rounded-xl border border-blue-100 bg-blue-50/50 p-3.5 text-xs text-blue-700 dark:border-blue-900 dark:bg-blue-900/20 dark:text-blue-300"
                            >
                                <Info class="mt-0.5 size-3.5 shrink-0" />
                                <span>Ca qua đêm — kết thúc vào rạng sáng ngày hôm sau. Hệ thống sẽ tự động tổng hợp đúng khung giờ.</span>
                            </div>

                            <!-- Error -->
                            <p v-if="previewError" class="text-xs text-rose-500 font-semibold mt-1">{{ previewError }}</p>
                        </div>
                    </template>

                    <!-- ── Step 2: Preview + Nhập tiền ─────────────────── -->
                    <template v-else-if="previewData">
                        <!-- Header ca -->
                        <div class="mb-4 flex items-center justify-between border-b pb-2">
                            <div>
                                <p class="font-bold text-sm text-slate-800 dark:text-slate-200">{{ previewData.shift_name }} ({{ previewData.shift_code }})</p>
                                <p class="text-xs text-slate-400 font-mono mt-0.5 font-medium">{{ previewData.start_time }} → {{ previewData.end_time }}</p>
                            </div>
                            <span class="rounded-full bg-emerald-50 border border-emerald-100 px-2.5 py-0.5 text-[11px] font-bold text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-300">
                                {{ previewData.order_count }} đơn hoàn thành
                            </span>
                        </div>

                        <!-- Revenue summary grid -->
                        <div class="mb-4 grid grid-cols-3 gap-2">
                            <div class="rounded-xl bg-slate-50/50 dark:bg-slate-900/50 p-3 text-center border">
                                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider mb-1">Doanh thu gộp</p>
                                <p class="font-extrabold text-sm text-emerald-600 dark:text-emerald-400">{{ compact(previewData.gross_revenue) }}</p>
                            </div>
                            <div class="rounded-xl bg-slate-50/50 dark:bg-slate-900/50 p-3 text-center border">
                                <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider mb-1">Giảm giá</p>
                                <p class="font-extrabold text-sm text-rose-500">-{{ compact(previewData.discount_total) }}</p>
                            </div>
                            <div class="rounded-xl bg-amber-500/5 border border-amber-500/10 p-3 text-center">
                                <p class="text-[9px] font-bold text-amber-700 dark:text-amber-400 uppercase tracking-wider mb-1">Doanh thu thuần</p>
                                <p class="font-extrabold text-sm text-amber-700 dark:text-amber-400">{{ compact(previewData.net_revenue) }}</p>
                            </div>
                        </div>

                        <!-- Payment breakdown -->
                        <div class="mb-4 rounded-xl border border-slate-100 dark:border-slate-800 bg-slate-50/30 p-4">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400 mb-3">Cơ cấu thanh toán</p>
                            <div class="space-y-2">
                                <div class="flex items-center justify-between text-xs">
                                    <span class="flex items-center gap-2">
                                        <span class="inline-block h-2 w-2 rounded-full bg-blue-500"></span>
                                        <span class="text-slate-500 font-semibold">Tiền mặt sổ sách (kỳ vọng)</span>
                                    </span>
                                    <span class="font-bold text-blue-600 dark:text-blue-400 font-mono">{{ vnd(previewData.expected_cash) }}</span>
                                </div>
                                <div v-if="previewData.bank_transfer > 0" class="flex items-center justify-between text-xs">
                                    <span class="flex items-center gap-2">
                                        <span class="inline-block h-2 w-2 rounded-full bg-violet-500"></span>
                                        <span class="text-slate-500 font-semibold">Chuyển khoản / Quét QR</span>
                                    </span>
                                    <span class="font-bold text-violet-600 dark:text-violet-400 font-mono">{{ vnd(previewData.bank_transfer) }}</span>
                                </div>
                                <div v-if="previewData.card > 0" class="flex items-center justify-between text-xs">
                                    <span class="flex items-center gap-2">
                                        <span class="inline-block h-2 w-2 rounded-full bg-sky-500"></span>
                                        <span class="text-slate-500 font-semibold">Quẹt thẻ ngân hàng</span>
                                    </span>
                                    <span class="font-bold text-sky-600 dark:text-sky-400 font-mono">{{ vnd(previewData.card) }}</span>
                                </div>
                                <div v-if="previewData.ewallet > 0" class="flex items-center justify-between text-xs">
                                    <span class="flex items-center gap-2">
                                        <span class="inline-block h-2 w-2 rounded-full bg-pink-500"></span>
                                        <span class="text-slate-500 font-semibold">Ví điện tử</span>
                                    </span>
                                    <span class="font-bold text-pink-600 dark:text-pink-400 font-mono">{{ vnd(previewData.ewallet) }}</span>
                                </div>
                                <div v-if="previewData.mixed > 0" class="flex items-center justify-between text-xs">
                                    <span class="flex items-center gap-2">
                                        <span class="inline-block h-2 w-2 rounded-full bg-orange-500"></span>
                                        <span class="text-slate-500 font-semibold">Giao dịch hỗn hợp</span>
                                    </span>
                                    <span class="font-bold text-orange-600 font-mono">{{ vnd(previewData.mixed) }}</span>
                                </div>
                                <div class="flex items-center justify-between border-t border-slate-100 dark:border-slate-800 pt-2 text-xs font-bold text-slate-700 dark:text-slate-300">
                                    <span>Tổng doanh thu (TM + CK)</span>
                                    <span class="font-mono">{{ vnd(previewData.expected_cash + previewData.transfer_amount) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Warnings -->
                        <div
                            v-if="previewData.already_closed"
                            class="mb-4 flex items-start gap-2.5 rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs text-rose-700 dark:border-rose-900 dark:bg-rose-900/20 dark:text-rose-300"
                        >
                            <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                            <span>Ca <strong>{{ previewData.shift_name }}</strong> ngày này đã được chốt. Vui lòng chọn ca khác.</span>
                        </div>
                        <div
                            v-else-if="previewData.pending_orders > 0"
                            class="mb-4 flex items-start gap-2.5 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-700 dark:border-amber-900 dark:bg-amber-900/20 dark:text-amber-300"
                        >
                            <AlertTriangle class="mt-0.5 size-4 shrink-0 animate-pulse text-amber-600" />
                            <span>Còn <strong>{{ previewData.pending_orders }}</strong> đơn chưa hoàn tất trong ca trực. Dữ liệu các đơn này sẽ tạm thời không được cộng vào tổng doanh thu chốt ca.</span>
                        </div>

                        <!-- Input actual cash -->
                        <div class="space-y-4">
                            <div class="space-y-1.5 flex flex-col">
                                <Label class="text-xs font-bold text-slate-500 uppercase tracking-wide">Tiền mặt thực tế trong két tiền <span class="text-rose-500">*</span></Label>
                                <div class="relative mt-1.5">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400 font-mono">₫</span>
                                    <Input
                                        v-model.number="form.actual_cash"
                                        type="number"
                                        min="0"
                                        step="1000"
                                        class="pl-8 text-xs font-bold h-9"
                                        :class="{ 'border-rose-400 focus-visible:ring-rose-400/20': form.errors.actual_cash }"
                                    />
                                </div>
                                <p v-if="form.errors.actual_cash" class="text-xs text-rose-500 font-semibold mt-1">{{ form.errors.actual_cash }}</p>
                            </div>

                            <!-- Chênh lệch live preview -->
                            <div
                                class="flex items-center justify-between rounded-xl border px-4 py-3 text-xs font-semibold transition-colors"
                                :class="cashDifference >= 0
                                    ? 'border-emerald-100 bg-emerald-50/50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-900/20 dark:text-emerald-300'
                                    : 'border-rose-100 bg-rose-50/50 text-rose-700 dark:border-rose-900 dark:bg-rose-900/20 dark:text-rose-300'"
                            >
                                <span class="flex items-center gap-2">
                                    <component :is="cashDifference >= 0 ? ArrowUpCircle : ArrowDownCircle" class="size-4 shrink-0" />
                                    Chênh lệch két tiền mặt thực tế
                                </span>
                                <span class="text-sm font-black font-mono">
                                    {{ cashDifference >= 0 ? '+' : '' }}{{ vnd(cashDifference) }}
                                </span>
                            </div>

                            <!-- Chi phí phát sinh -->
                            <div class="space-y-1.5 flex flex-col">
                                <Label class="text-xs font-bold text-slate-500 uppercase tracking-wide">Chi phí phát sinh trong ca (nếu có)</Label>
                                <div class="relative mt-1.5">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-xs font-bold text-slate-400 font-mono">₫</span>
                                    <Input
                                        v-model.number="form.other_expense_amount"
                                        type="number"
                                        min="0"
                                        step="1000"
                                        class="pl-8 text-xs font-bold h-9"
                                    />
                                </div>
                            </div>

                            <!-- Ghi chú -->
                            <div class="space-y-1.5 flex flex-col">
                                <Label class="text-xs font-bold text-slate-500 uppercase tracking-wide">Ghi chú vận hành ca</Label>
                                <textarea
                                    v-model="form.notes"
                                    rows="2"
                                    maxlength="1000"
                                    placeholder="Ghi rõ tình huống bất thường phát sinh, lý do chênh lệch két tiền (nếu có)..."
                                    class="w-full resize-none rounded-md border border-slate-200 bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 font-semibold text-slate-700 mt-1.5"
                                />
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Dialog Footer -->
                <div class="flex items-center justify-between border-t border-slate-100 px-6 py-4">
                    <Button
                        variant="outline"
                        @click="dialogStep === 1 ? (showDialog = false) : (dialogStep = 1)"
                        class="h-9 text-xs font-semibold"
                    >
                        {{ dialogStep === 1 ? 'Huỷ bỏ' : '← Quay lại' }}
                    </Button>

                    <div class="flex gap-2">
                        <!-- Step 1 action -->
                        <Button
                            v-if="dialogStep === 1"
                            @click="loadPreview"
                            :disabled="previewLoading || !form.shift_id"
                            class="h-9 text-xs bg-indigo-600 hover:bg-indigo-700 text-white font-semibold flex items-center gap-1.5 active:scale-95 transition-transform"
                        >
                            <Loader2 v-if="previewLoading" class="size-4 animate-spin" />
                            <span>{{ previewLoading ? 'Đang tổng hợp...' : 'Tổng hợp doanh thu →' }}</span>
                        </Button>

                        <!-- Step 2 actions -->
                        <template v-if="dialogStep === 2 && previewData && !previewData.already_closed">
                            <Button
                                variant="outline"
                                @click="submitForm(false)"
                                :disabled="form.processing"
                                class="h-9 text-xs font-semibold active:scale-95 transition-transform"
                            >
                                Lưu bản nháp
                            </Button>
                            <Button
                                @click="submitForm(true)"
                                :disabled="form.processing"
                                class="h-9 text-xs bg-emerald-600 hover:bg-emerald-700 text-white font-semibold flex items-center gap-1.5 active:scale-95 transition-transform"
                            >
                                <Loader2 v-if="form.processing" class="size-4 animate-spin" />
                                <Check v-else class="size-4" />
                                Nộp chốt ca
                            </Button>
                        </template>
                    </div>
                </div>
            </Card>
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
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
            @click.self="disputeTarget = null"
        >
            <Card class="w-full max-w-md overflow-hidden animate-in fade-in zoom-in-95 duration-150 shadow-2xl flex flex-col">
                <CardHeader class="pb-3 border-b flex flex-row items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400">
                            <AlertTriangle class="size-5" />
                        </div>
                        <div>
                            <CardTitle class="text-base text-rose-600">Yêu cầu đối soát lại ca</CardTitle>
                            <CardDescription>{{ disputeTarget.shift_name }} — Ngày {{ disputeTarget.closing_date }}</CardDescription>
                        </div>
                    </div>
                    <button @click="disputeTarget = null" class="p-1 rounded-lg hover:bg-muted text-muted-foreground hover:text-foreground">
                        <X class="size-4" />
                    </button>
                </CardHeader>

                <div class="px-6 py-5 flex flex-col gap-1.5">
                    <Label class="text-xs font-bold text-slate-500 uppercase tracking-wide">Lý do tranh chấp / Đối soát lại <span class="text-rose-500">*</span></Label>
                    <textarea
                        v-model="disputeNotes"
                        rows="3"
                        maxlength="1000"
                        placeholder="Mô tả cụ thể và chi tiết sai lệch két tiền mặt hoặc chuyển khoản để quản lý/owner thực hiện đối soát lại..."
                        class="w-full resize-none rounded-md border border-slate-200 bg-background px-3 py-2.5 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-rose-400 font-semibold text-slate-700 mt-1.5"
                    />
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-100 px-6 py-4 bg-slate-50/50 dark:bg-slate-900/50">
                    <Button
                        variant="outline"
                        @click="disputeTarget = null"
                        class="h-9 text-xs font-semibold"
                    >
                        Huỷ bỏ
                    </Button>
                    <Button
                        @click="submitDispute"
                        :disabled="disputeLoading"
                        class="h-9 text-xs bg-rose-600 hover:bg-rose-700 text-white font-semibold flex items-center gap-1.5 active:scale-95 transition-transform"
                    >
                        <Loader2 v-if="disputeLoading" class="size-4 animate-spin" />
                        Xác nhận khiếu nại
                    </Button>
                </div>
            </Card>
        </div>
    </Transition>
</template>
