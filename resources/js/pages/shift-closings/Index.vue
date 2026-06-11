<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
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

// ── Types ────────────────────────────────────────────────────────────────────

type Status = 'draft' | 'submitted' | 'confirmed' | 'disputed';

type SplitOrder = {
    id: number;
    order_number: string;
    total_amount: number;
    is_override_split_penalty: boolean;
    is_red_flagged: boolean;
    status: string;
};

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
    split_orders?: SplitOrder[];
    split_penalty_total?: number;
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
    split_orders: SplitOrder[];
    split_penalty_total: number;
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
const activeMonth = ref(props.filters.month);

function applyFilters() {
    router.get(
        '/shift-closings',
        { status: activeStatus.value, month: activeMonth.value },
        { preserveScroll: true },
    );
}

watch([activeStatus, activeMonth], applyFilters);

// ── Status config ─────────────────────────────────────────────────────────────

const statusConfig: Record<
    Status,
    { label: string; badgeClass: string; dotClass: string }
> = {
    draft: {
        label: 'Bản nháp',
        badgeClass:
            'bg-slate-100 text-slate-600 border border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700/50',
        dotClass: 'bg-slate-400',
    },
    submitted: {
        label: 'Chờ duyệt',
        badgeClass:
            'bg-amber-50 text-amber-700 border border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-900/30',
        dotClass: 'bg-amber-500 animate-pulse',
    },
    confirmed: {
        label: 'Đã xác nhận',
        badgeClass:
            'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-900/30',
        dotClass: 'bg-emerald-500',
    },
    disputed: {
        label: 'Tranh chấp',
        badgeClass:
            'bg-rose-50 text-rose-700 border border-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-900/30',
        dotClass: 'bg-rose-500',
    },
};

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

// ── Create dialog ─────────────────────────────────────────────────────────────

const showDialog = ref(false);
const dialogStep = ref<1 | 2>(1);
const previewData = ref<Preview | null>(null);
const previewLoading = ref(false);
const previewError = ref('');

const form = useForm({
    shift_id: null as number | null,
    closing_date: new Date().toISOString().slice(0, 10),
    actual_cash: 0,
    other_expense_amount: 0,
    notes: '',
});

const isSubmitting = ref(false);

const cashDifference = computed(() => {
    if (!previewData.value) {
        return 0;
    }

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
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
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
    form.transform((data) => ({ ...data, submit: isSubmit ? 1 : 0 })).post(
        '/shift-closings',
        {
            onSuccess: () => {
                showDialog.value = false;
                form.reset();
                previewData.value = null;
                dialogStep.value = 1;
                isSubmitting.value = false;
                toast.success(
                    isSubmit
                        ? 'Đã nộp phiếu chốt ca thành công!'
                        : 'Đã lưu bản nháp.',
                );
            },
            onError: (errors) => {
                isSubmitting.value = false;
                const msg = Object.values(errors)[0];

                if (msg) {
                    toast.error(String(msg));
                }
            },
        },
    );
}

// ── Dispute dialog ────────────────────────────────────────────────────────────

const disputeTarget = ref<ShiftClosing | null>(null);
const disputeNotes = ref('');
const disputeLoading = ref(false);

function openDisputeDialog(closing: ShiftClosing) {
    disputeTarget.value = closing;
    disputeNotes.value = '';
}

function submitDispute() {
    if (!disputeTarget.value) {
        return;
    }

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
            onFinish: () => {
                disputeLoading.value = false;
            },
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

const page = usePage();
const isOwner = computed(() => {
    const roles = (page.props as any).roles ?? [];

    return roles.includes('owner');
});

function playPrintChime() {
    try {
        const audioCtx = new (
            window.AudioContext || (window as any).webkitAudioContext
        )();
        const now = audioCtx.currentTime;
        const osc = audioCtx.createOscillator();
        const gain = audioCtx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(660, now);
        gain.gain.setValueAtTime(0.2, now);
        gain.gain.exponentialRampToValueAtTime(0.001, now + 0.25);
        osc.connect(gain);
        gain.connect(audioCtx.destination);
        osc.start(now);
        osc.stop(now + 0.25);
    } catch (e) {
        console.error('Failed to play print chime', e);
    }
}

const handlePrintSplitOrder = (orderNumber: string) => {
    playPrintChime();
    toast.success(`Đang gửi lệnh in hóa đơn tách ${orderNumber} tới máy in...`);
};

const handleOverridePenalty = (orderId: number) => {
    router.patch(
        `/orders/${orderId}/override-split-penalty`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                toast.success(
                    'Đã phê duyệt đối soát đơn tách. Khoản phạt âm tiền đã được vô hiệu hóa.',
                );
            },
            onError: (err: any) => {
                toast.error(err.message || 'Có lỗi xảy ra.');
            },
        },
    );
};

// ── Derived totals for selected month ─────────────────────────────────────────

const counts = computed(() => {
    return {
        all: props.closings.length,
        draft: props.closings.filter((c) => c.status === 'draft').length,
        submitted: props.closings.filter((c) => c.status === 'submitted')
            .length,
        confirmed: props.closings.filter((c) => c.status === 'confirmed')
            .length,
        disputed: props.closings.filter((c) => c.status === 'disputed').length,
    };
});

const statusOptions = computed(() => [
    { value: 'all', label: 'Tất cả', count: counts.value.all },
    { value: 'draft', label: 'Bản nháp', count: counts.value.draft },
    { value: 'submitted', label: 'Chờ duyệt', count: counts.value.submitted },
    { value: 'confirmed', label: 'Đã xác nhận', count: counts.value.confirmed },
    { value: 'disputed', label: 'Tranh chấp', count: counts.value.disputed },
]);

const selectedShift = computed(
    () => props.shifts.find((s) => s.id === form.shift_id) ?? null,
);

// ── Custom Calendar Picker ─────────────────────────────────────────────────────

const showCalendar = ref(false);
const calTriggerRef = ref<HTMLElement | null>(null);
const calPos = ref({ top: 0, left: 0, width: 0 });

const today = new Date();
const todayStr = today.toISOString().slice(0, 10);

const calView = ref({ year: today.getFullYear(), month: today.getMonth() });

const viMonths = [
    'Tháng 1',
    'Tháng 2',
    'Tháng 3',
    'Tháng 4',
    'Tháng 5',
    'Tháng 6',
    'Tháng 7',
    'Tháng 8',
    'Tháng 9',
    'Tháng 10',
    'Tháng 11',
    'Tháng 12',
];
const viMonthsShort = [
    'T1',
    'T2',
    'T3',
    'T4',
    'T5',
    'T6',
    'T7',
    'T8',
    'T9',
    'T10',
    'T11',
    'T12',
];
const viDays = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'];

const showMonthPicker = ref(false);

function selectMonth(m: number) {
    calView.value = { year: calView.value.year, month: m };
    showMonthPicker.value = false;
}

function prevYear() {
    calView.value = {
        year: calView.value.year - 1,
        month: calView.value.month,
    };
}
function nextYear() {
    if (calView.value.year < today.getFullYear()) {
        calView.value = {
            year: calView.value.year + 1,
            month: calView.value.month,
        };
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
            top: rect.top + window.scrollY,
            left: rect.right + window.scrollX + 8,
            width: rect.width,
        };
    }

    showCalendar.value = true;
}

function prevMonth() {
    calView.value =
        calView.value.month === 0
            ? { year: calView.value.year - 1, month: 11 }
            : { year: calView.value.year, month: calView.value.month - 1 };
}

function nextMonth() {
    const nextY =
        calView.value.month === 11
            ? calView.value.year + 1
            : calView.value.year;
    const nextM = calView.value.month === 11 ? 0 : calView.value.month + 1;

    if (new Date(nextY, nextM, 1) <= today) {
        calView.value = { year: nextY, month: nextM };
    }
}

const isNextMonthDisabled = computed(() => {
    const nextY =
        calView.value.month === 11
            ? calView.value.year + 1
            : calView.value.year;
    const nextM = calView.value.month === 11 ? 0 : calView.value.month + 1;

    return new Date(nextY, nextM, 1) > today;
});

type CalDay = {
    date: string;
    day: number;
    inMonth: boolean;
    isToday: boolean;
    isFuture: boolean;
    isSelected: boolean;
};

const calDays = computed<CalDay[]>(() => {
    const { year, month } = calView.value;
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    let startDow = firstDay.getDay();
    startDow = startDow === 0 ? 6 : startDow - 1;

    const days: CalDay[] = [];

    for (let i = startDow - 1; i >= 0; i--) {
        const d = new Date(year, month, -i);
        const str = d.toISOString().slice(0, 10);
        days.push({
            date: str,
            day: d.getDate(),
            inMonth: false,
            isToday: false,
            isFuture: d > today,
            isSelected: str === form.closing_date,
        });
    }

    for (let d = 1; d <= lastDay.getDate(); d++) {
        const dt = new Date(year, month, d);
        const str = dt.toISOString().slice(0, 10);
        days.push({
            date: str,
            day: d,
            inMonth: true,
            isToday: str === todayStr,
            isFuture: dt > today,
            isSelected: str === form.closing_date,
        });
    }

    const remaining = 42 - days.length;

    for (let i = 1; i <= remaining; i++) {
        const d = new Date(year, month + 1, i);
        const str = d.toISOString().slice(0, 10);
        days.push({
            date: str,
            day: i,
            inMonth: false,
            isToday: false,
            isFuture: true,
            isSelected: false,
        });
    }

    return days;
});

function selectDate(day: CalDay) {
    if (day.isFuture) {
        return;
    }

    form.closing_date = day.date;
    showCalendar.value = false;

    if (dialogStep.value === 2) {
        dialogStep.value = 1;
        previewData.value = null;
    }
}

const displayDate = computed(() => {
    if (!form.closing_date) {
        return '';
    }

    const d = new Date(form.closing_date + 'T00:00:00');

    return d.toLocaleDateString('vi-VN', {
        weekday: 'long',
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
});

function handleOutsideClick(e: MouseEvent) {
    const target = e.target as Node;
    const trigger = calTriggerRef.value;
    const calEl = document.getElementById('shift-cal-popup');

    if (
        trigger &&
        !trigger.contains(target) &&
        calEl &&
        !calEl.contains(target)
    ) {
        showCalendar.value = false;
    }
}
onMounted(() => document.addEventListener('mousedown', handleOutsideClick));
onUnmounted(() =>
    document.removeEventListener('mousedown', handleOutsideClick),
);
</script>

<template>
    <Head title="Chốt Ca & Doanh Thu" />

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-6 p-6">
        <!-- ── Page Header ─────────────────────────────────────────────────── -->
        <div
            class="flex flex-col gap-4 border-b pb-5 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="flex items-center gap-3">
                <div
                    class="flex h-12 w-12 items-center justify-center rounded-2xl border border-indigo-100 bg-indigo-50 text-indigo-600 shadow-sm dark:border-indigo-900/30 dark:bg-indigo-950/60 dark:text-indigo-400"
                >
                    <ClipboardCheck class="size-6" />
                </div>
                <div>
                    <h1
                        class="text-2xl font-bold tracking-tight text-slate-800 dark:text-slate-100"
                    >
                        Chốt Ca & Doanh Thu
                    </h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        Quản lý phiếu chốt ca, đối soát tiền mặt và doanh thu
                        chi tiết.
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <!-- Month picker -->
                <div class="flex items-center gap-1.5">
                    <Label
                        for="shift-month"
                        class="shrink-0 text-xs font-semibold text-slate-600"
                        >Chọn tháng:</Label
                    >
                    <Input
                        id="shift-month"
                        v-model="activeMonth"
                        type="month"
                        class="h-9 w-36 bg-white py-1 text-xs font-semibold"
                    />
                </div>

                <!-- Chốt ca mới button -->
                <Button
                    @click="openDialog"
                    class="flex h-9 items-center gap-1.5 bg-indigo-600 text-xs font-semibold text-white shadow-sm transition-transform hover:bg-indigo-700 active:scale-95"
                >
                    <ClipboardCheck class="size-4" />
                    Chốt ca mới
                </Button>
            </div>
        </div>

        <!-- ── KPI Cards ───────────────────────────────────────────────────── -->
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <!-- Tổng phiếu -->
            <Card
                class="shadow-xs transition-transform duration-200 hover:translate-y-[-2px]"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardDescription
                        class="text-xs font-bold tracking-wider text-slate-400 uppercase"
                        >Tổng phiếu</CardDescription
                    >
                    <ReceiptText class="size-4 text-slate-400" />
                </CardHeader>
                <CardContent class="pb-3">
                    <p
                        class="text-2xl font-black text-slate-800 dark:text-slate-100"
                    >
                        {{ kpi.total_closings }}
                    </p>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        phiếu trong tháng
                    </p>
                </CardContent>
            </Card>

            <!-- Tổng doanh thu gộp -->
            <Card
                class="border-emerald-100 shadow-xs transition-transform duration-200 hover:translate-y-[-2px] dark:border-emerald-950/20"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardDescription
                        class="text-xs font-bold tracking-wider text-emerald-500 uppercase"
                        >Doanh thu gộp</CardDescription
                    >
                    <BadgeDollarSign class="size-4 text-emerald-500" />
                </CardHeader>
                <CardContent class="pb-3">
                    <p
                        class="text-2xl font-black text-emerald-600 dark:text-emerald-400"
                    >
                        {{ compact(kpi.total_gross) }}
                    </p>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        tiền mặt + chuyển khoản
                    </p>
                </CardContent>
            </Card>

            <!-- Tiền mặt thực -->
            <Card
                class="border-blue-100 shadow-xs transition-transform duration-200 hover:translate-y-[-2px] dark:border-blue-950/20"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardDescription
                        class="text-xs font-bold tracking-wider text-blue-500 uppercase"
                        >Tiền mặt thực</CardDescription
                    >
                    <Wallet class="size-4 text-blue-500" />
                </CardHeader>
                <CardContent class="pb-3">
                    <p
                        class="text-2xl font-black text-blue-600 dark:text-blue-400"
                    >
                        {{ compact(kpi.total_cash) }}
                    </p>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        tổng actual cash
                    </p>
                </CardContent>
            </Card>

            <!-- Chênh lệch tổng -->
            <Card
                class="shadow-xs transition-transform duration-200 hover:translate-y-[-2px]"
                :class="
                    kpi.total_difference >= 0
                        ? 'border-emerald-100 dark:border-emerald-950/20'
                        : 'border-rose-100 dark:border-rose-950/20'
                "
            >
                <CardHeader
                    class="flex flex-row items-center justify-between pb-2"
                >
                    <CardDescription
                        class="text-xs font-bold tracking-wider uppercase"
                        :class="
                            kpi.total_difference >= 0
                                ? 'text-emerald-500'
                                : 'text-rose-500'
                        "
                        >Chênh lệch</CardDescription
                    >
                    <component
                        :is="
                            kpi.total_difference >= 0
                                ? ArrowUpCircle
                                : ArrowDownCircle
                        "
                        :class="
                            kpi.total_difference >= 0
                                ? 'text-emerald-500'
                                : 'text-rose-500'
                        "
                        class="size-4"
                    />
                </CardHeader>
                <CardContent class="pb-3">
                    <p
                        class="text-2xl font-black"
                        :class="
                            kpi.total_difference >= 0
                                ? 'text-emerald-600 dark:text-emerald-400'
                                : 'text-rose-600 dark:text-rose-400'
                        "
                    >
                        {{ kpi.total_difference >= 0 ? '+' : ''
                        }}{{ compact(kpi.total_difference) }}
                    </p>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        thừa/thiếu so sổ sách
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- ── Table Card ───────────────────────────────────────────────────── -->
        <Card class="overflow-hidden shadow-sm">
            <CardHeader
                class="flex flex-row items-center justify-between border-b bg-slate-50/50 pb-3 dark:bg-slate-900/50"
            >
                <div>
                    <CardTitle
                        class="flex items-center gap-1.5 text-base font-bold"
                    >
                        <ClipboardCheck
                            class="size-5 text-indigo-600 dark:text-indigo-400"
                        />
                        Nhật Ký Phiếu Chốt Ca Chi Tiết
                    </CardTitle>
                    <CardDescription
                        >Báo cáo doanh thu gộp, số tiền mặt đếm thực tế, và chi
                        tiết chênh lệch két của từng ca trực.</CardDescription
                    >
                </div>
            </CardHeader>

            <CardContent class="p-0">
                <!-- Filters Row -->
                <div
                    class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-white p-4 dark:border-slate-800 dark:bg-slate-950"
                >
                    <!-- Status Filter Tabs -->
                    <div
                        class="flex shrink-0 items-center gap-1 rounded-xl border border-slate-200/50 bg-slate-100 p-0.5 dark:border-slate-800 dark:bg-slate-900"
                    >
                        <button
                            v-for="opt in statusOptions"
                            :key="opt.value"
                            type="button"
                            @click="activeStatus = opt.value"
                            :class="[
                                'inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-[10px] font-bold whitespace-nowrap transition-colors',
                                activeStatus === opt.value
                                    ? 'border border-slate-200/10 bg-white text-slate-800 shadow-sm dark:border-slate-700/20 dark:bg-slate-800 dark:text-slate-100'
                                    : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300',
                            ]"
                        >
                            {{ opt.label }}
                            <span
                                v-if="opt.count !== null"
                                :class="[
                                    'inline-flex h-4.5 w-4.5 items-center justify-center rounded-full text-[9px] font-black transition-colors',
                                    activeStatus === opt.value
                                        ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950/80 dark:text-indigo-300'
                                        : 'bg-slate-200/60 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
                                ]"
                                >{{ opt.count }}</span
                            >
                        </button>
                    </div>
                </div>

                <!-- Empty State -->
                <div
                    v-if="closings.length === 0"
                    class="flex flex-col items-center gap-3 py-20 text-center text-muted-foreground"
                >
                    <div
                        class="flex h-14 w-14 items-center justify-center rounded-2xl border border-indigo-100 bg-indigo-50 text-indigo-600 shadow-sm dark:border-indigo-900/30 dark:bg-indigo-950/60 dark:text-indigo-400"
                    >
                        <ClipboardCheck class="size-7" />
                    </div>
                    <p class="font-bold text-slate-800 dark:text-slate-200">
                        Chưa có phiếu chốt ca nào
                    </p>
                    <p class="mx-auto max-w-sm text-xs text-slate-500">
                        Vui lòng nhấn nút "Chốt ca mới" ở trên để hệ thống tự
                        động sinh dữ liệu nháp dựa trên công ca thực tế.
                    </p>
                </div>

                <!-- Table Content -->
                <template v-else>
                    <!-- Table Header -->
                    <div
                        class="hidden grid-cols-[auto_1fr_1fr_1fr_1fr_1fr_auto] gap-4 border-b border-slate-100 bg-slate-50/50 px-5 py-3 text-[10px] font-bold tracking-wider text-slate-500 uppercase lg:grid dark:border-slate-800 dark:bg-slate-900/30"
                    >
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
                        class="border-b border-slate-100 last:border-0 dark:border-slate-800"
                    >
                        <!-- Main Row -->
                        <div
                            class="grid cursor-pointer grid-cols-[auto_1fr_auto] items-center gap-3 px-4 py-4 transition hover:bg-slate-50/60 lg:grid-cols-[auto_1fr_1fr_1fr_1fr_1fr_auto] lg:gap-4 lg:px-5 dark:hover:bg-slate-900/30"
                            @click="toggleExpand(closing.id)"
                        >
                            <!-- Expand toggle -->
                            <component
                                :is="
                                    expandedId === closing.id
                                        ? ChevronUp
                                        : ChevronDown
                                "
                                class="size-4 shrink-0 text-slate-400"
                            />

                            <!-- Ngày / Ca -->
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold">
                                    {{ closing.closing_date }}
                                </p>
                                <div class="mt-0.5 flex items-center gap-1.5">
                                    <span
                                        class="rounded border bg-slate-100 px-1.5 py-0.5 text-[9px] font-bold text-slate-500 dark:bg-slate-800 dark:text-slate-400"
                                        >{{ closing.shift_code }}</span
                                    >
                                    <span
                                        class="truncate text-xs font-medium text-slate-400"
                                        >{{ closing.shift_name }}</span
                                    >
                                    <span
                                        class="hidden font-mono text-[10px] text-slate-400 lg:inline"
                                        >({{ closing.shift_start }}–{{
                                            closing.shift_end
                                        }})</span
                                    >
                                </div>
                            </div>

                            <!-- Cashier -->
                            <div class="hidden items-center gap-2 lg:flex">
                                <div
                                    class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border bg-indigo-500/10 text-xs font-bold text-indigo-600 dark:border-indigo-900/30 dark:bg-indigo-950/40 dark:text-indigo-400"
                                >
                                    {{
                                        closing.cashier_name?.slice(0, 1) ?? '?'
                                    }}
                                </div>
                                <span
                                    class="truncate text-sm font-semibold text-slate-500"
                                    >{{ closing.cashier_name }}</span
                                >
                            </div>

                            <!-- Doanh thu gộp -->
                            <div class="hidden text-right font-mono lg:block">
                                <p class="text-sm font-bold">
                                    {{ compact(closing.gross_revenue) }}
                                </p>
                                <p
                                    class="mt-0.5 text-[10px] font-medium text-slate-400"
                                >
                                    <span class="text-blue-500"
                                        >TM:
                                        {{
                                            compact(closing.expected_cash)
                                        }}</span
                                    >
                                    <span class="mx-1">·</span>
                                    <span class="text-violet-500"
                                        >CK:
                                        {{
                                            compact(closing.transfer_amount)
                                        }}</span
                                    >
                                </p>
                            </div>

                            <!-- Tiền mặt thực -->
                            <div class="hidden text-right font-mono lg:block">
                                <p
                                    class="text-sm font-bold text-slate-700 dark:text-slate-300"
                                >
                                    {{ compact(closing.actual_cash) }}
                                </p>
                            </div>

                            <!-- Chênh lệch -->
                            <div class="hidden text-right font-mono lg:block">
                                <p
                                    class="text-sm font-bold"
                                    :class="
                                        closing.cash_difference > 0
                                            ? 'text-emerald-600 dark:text-emerald-400'
                                            : closing.cash_difference < 0
                                              ? 'text-rose-600 dark:text-rose-400'
                                              : 'text-muted-foreground'
                                    "
                                >
                                    {{ closing.cash_difference > 0 ? '+' : ''
                                    }}{{ vnd(closing.cash_difference) }}
                                </p>
                            </div>

                            <!-- Status badge -->
                            <div class="flex shrink-0 items-center gap-2">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[10px] font-bold tracking-wider uppercase"
                                    :class="
                                        statusConfig[closing.status].badgeClass
                                    "
                                >
                                    <span
                                        class="size-1.5 rounded-full"
                                        :class="
                                            statusConfig[closing.status]
                                                .dotClass
                                        "
                                    ></span>
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
                                class="overflow-hidden border-t border-slate-100 bg-slate-50/40 px-5 py-5 dark:border-slate-800 dark:bg-slate-900/20"
                            >
                                <div
                                    class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4"
                                >
                                    <!-- Revenue breakdown -->
                                    <div
                                        class="space-y-3 rounded-xl border bg-white p-4 shadow-2xs dark:bg-slate-950"
                                    >
                                        <p
                                            class="text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                                        >
                                            Doanh thu chi tiết
                                        </p>
                                        <div
                                            class="space-y-2 text-xs font-semibold"
                                        >
                                            <div
                                                class="flex justify-between text-slate-600 dark:text-slate-300"
                                            >
                                                <span class="font-medium"
                                                    >Doanh thu gộp</span
                                                >
                                                <span class="font-mono">{{
                                                    vnd(closing.gross_revenue)
                                                }}</span>
                                            </div>
                                            <div
                                                class="mt-1 flex justify-between border-t pt-2 text-blue-500"
                                            >
                                                <span
                                                    class="flex items-center gap-1 font-medium"
                                                >
                                                    <Wallet class="size-3" />
                                                    Tiền mặt sổ sách
                                                </span>
                                                <span
                                                    class="font-mono font-bold"
                                                    >{{
                                                        vnd(
                                                            closing.expected_cash,
                                                        )
                                                    }}</span
                                                >
                                            </div>
                                            <div
                                                class="flex justify-between text-violet-500"
                                            >
                                                <span
                                                    class="flex items-center gap-1 font-medium"
                                                >
                                                    <CreditCard
                                                        class="size-3"
                                                    />
                                                    Chuyển khoản gộp
                                                </span>
                                                <span
                                                    class="font-mono font-bold"
                                                    >{{
                                                        vnd(
                                                            closing.transfer_amount,
                                                        )
                                                    }}</span
                                                >
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Cash reconciliation -->
                                    <div
                                        class="space-y-3 rounded-xl border bg-white p-4 shadow-2xs dark:bg-slate-950"
                                    >
                                        <p
                                            class="text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                                        >
                                            Đối soát tiền mặt
                                        </p>
                                        <div
                                            class="space-y-2 text-xs font-semibold"
                                        >
                                            <div
                                                class="flex justify-between text-slate-600 dark:text-slate-300"
                                            >
                                                <span class="font-medium"
                                                    >Kỳ vọng sổ sách</span
                                                >
                                                <span class="font-mono">{{
                                                    vnd(closing.expected_cash)
                                                }}</span>
                                            </div>
                                            <div
                                                class="flex justify-between text-slate-700 dark:text-slate-200"
                                            >
                                                <span class="font-medium"
                                                    >Thực tế đếm két</span
                                                >
                                                <span
                                                    class="font-mono font-bold"
                                                    >{{
                                                        vnd(closing.actual_cash)
                                                    }}</span
                                                >
                                            </div>
                                            <div
                                                class="flex justify-between border-t border-slate-100 pt-2 text-slate-800 dark:border-slate-800 dark:text-slate-200"
                                            >
                                                <span class="font-bold"
                                                    >Chênh lệch két</span
                                                >
                                                <span
                                                    class="font-mono font-black"
                                                    :class="
                                                        closing.cash_difference >=
                                                        0
                                                            ? 'text-emerald-600'
                                                            : 'text-rose-600'
                                                    "
                                                >
                                                    {{
                                                        closing.cash_difference >=
                                                        0
                                                            ? '+'
                                                            : ''
                                                    }}{{
                                                        vnd(
                                                            closing.cash_difference,
                                                        )
                                                    }}
                                                </span>
                                            </div>
                                            <div
                                                v-if="closing.other_expense > 0"
                                                class="flex justify-between text-[10px] font-medium text-rose-500"
                                            >
                                                <span
                                                    >Chi phí phát sinh trong
                                                    ca</span
                                                >
                                                <span
                                                    class="font-mono font-bold"
                                                    >-{{
                                                        vnd(
                                                            closing.other_expense,
                                                        )
                                                    }}</span
                                                >
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Meta info -->
                                    <div
                                        class="space-y-3 rounded-xl border bg-white p-4 shadow-2xs dark:bg-slate-950"
                                    >
                                        <p
                                            class="text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                                        >
                                            Thông tin ca trực
                                        </p>
                                        <div
                                            class="space-y-2 text-xs font-semibold"
                                        >
                                            <div
                                                class="flex items-center gap-2 text-slate-600 dark:text-slate-300"
                                            >
                                                <Clock
                                                    class="size-3 shrink-0 text-slate-400"
                                                />
                                                <span
                                                    >Nộp lúc:
                                                    {{
                                                        closing.closed_at ?? '—'
                                                    }}</span
                                                >
                                            </div>
                                            <div
                                                v-if="closing.confirmed_by_name"
                                                class="flex items-center gap-2 text-slate-600 dark:text-slate-300"
                                            >
                                                <Check
                                                    class="size-3 shrink-0 font-bold text-emerald-500"
                                                />
                                                <span
                                                    >Duyệt bởi:
                                                    <strong>{{
                                                        closing.confirmed_by_name
                                                    }}</strong></span
                                                >
                                            </div>
                                            <div
                                                v-if="closing.notes"
                                                class="mt-1 border-t pt-2 text-[11px] font-semibold text-slate-400"
                                            >
                                                <p
                                                    class="font-bold text-slate-500"
                                                >
                                                    Ghi chú vận hành:
                                                </p>
                                                <p
                                                    class="mt-0.5 leading-relaxed whitespace-pre-line"
                                                >
                                                    {{ closing.notes }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Actions -->
                                    <div
                                        class="flex flex-col justify-between space-y-3 rounded-xl border bg-white p-4 shadow-2xs dark:bg-slate-950"
                                    >
                                        <div>
                                            <p
                                                class="mb-2 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                                            >
                                                Hành động
                                            </p>
                                            <template v-if="canConfirm">
                                                <div
                                                    class="flex flex-col gap-2"
                                                >
                                                    <Button
                                                        v-if="
                                                            closing.status ===
                                                            'submitted'
                                                        "
                                                        @click.stop="
                                                            confirmClosing(
                                                                closing,
                                                            )
                                                        "
                                                        class="flex h-8 items-center justify-center gap-1 bg-emerald-600 text-xs font-semibold text-white shadow-sm transition-transform hover:bg-emerald-700 active:scale-95"
                                                    >
                                                        <Check
                                                            class="size-3.5"
                                                        />
                                                        Duyệt & Chốt
                                                    </Button>
                                                    <Button
                                                        v-if="
                                                            closing.status ===
                                                                'submitted' ||
                                                            closing.status ===
                                                                'confirmed'
                                                        "
                                                        @click.stop="
                                                            openDisputeDialog(
                                                                closing,
                                                            )
                                                        "
                                                        variant="outline"
                                                        class="flex h-8 items-center justify-center gap-1 border-rose-100 text-xs font-semibold text-rose-600 shadow-sm transition-transform hover:bg-rose-50 active:scale-95"
                                                    >
                                                        <AlertTriangle
                                                            class="size-3.5"
                                                        />
                                                        Yêu cầu đối soát lại
                                                        (Khiếu nại)
                                                    </Button>
                                                </div>
                                            </template>
                                            <span
                                                v-if="
                                                    closing.status === 'draft'
                                                "
                                                class="text-xs font-semibold text-slate-400 italic"
                                                >Nhân viên chưa nộp phiếu</span
                                            >
                                            <span
                                                v-if="
                                                    closing.status ===
                                                        'confirmed' &&
                                                    !canConfirm
                                                "
                                                class="flex items-center gap-1 text-xs font-bold text-emerald-600 dark:text-emerald-400"
                                            >
                                                <Check class="size-3" /> Đã
                                                duyệt chốt thành công
                                            </span>
                                            <span
                                                v-if="
                                                    closing.status ===
                                                        'disputed' &&
                                                    !canConfirm
                                                "
                                                class="flex items-center gap-1 text-xs font-bold text-rose-600 dark:text-rose-400"
                                            >
                                                <AlertTriangle class="size-3" />
                                                Đang giải quyết tranh chấp
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Audit Split Orders inside Expanded Row -->
                                <div
                                    v-if="
                                        closing.split_orders &&
                                        closing.split_orders.length > 0
                                    "
                                    class="mt-6 border-t border-slate-100 pt-5 dark:border-slate-800"
                                >
                                    <h5
                                        class="mb-3 flex items-center gap-1.5 text-xs font-bold tracking-wider text-slate-800 uppercase dark:text-slate-200"
                                    >
                                        <AlertTriangle
                                            class="size-4 text-rose-500"
                                        />
                                        Danh sách đơn bị tách (Audit đối soát
                                        rủi ro)
                                    </h5>
                                    <div
                                        class="overflow-x-auto rounded-xl border border-slate-100 dark:border-slate-800"
                                    >
                                        <table
                                            class="w-full border-collapse text-left text-xs"
                                        >
                                            <thead>
                                                <tr
                                                    class="border-b border-slate-100 bg-slate-50 font-bold text-slate-500 dark:border-slate-800 dark:bg-slate-900"
                                                >
                                                    <th class="p-3">Mã đơn</th>
                                                    <th class="p-3">Số tiền</th>
                                                    <th class="p-3">
                                                        Trạng thái
                                                    </th>
                                                    <th class="p-3">
                                                        Cảnh báo/Bỏ phạt
                                                    </th>
                                                    <th class="p-3 text-right">
                                                        Thao tác
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr
                                                    v-for="order in closing.split_orders"
                                                    :key="order.id"
                                                    class="dark:border-slate-850 border-b border-slate-100 hover:bg-slate-50/50 dark:hover:bg-slate-900/10"
                                                >
                                                    <td
                                                        class="p-3 font-mono font-bold"
                                                    >
                                                        {{ order.order_number }}
                                                    </td>
                                                    <td class="p-3 font-mono">
                                                        {{
                                                            vnd(
                                                                order.total_amount,
                                                            )
                                                        }}
                                                    </td>
                                                    <td class="p-3">
                                                        <span
                                                            class="rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                                            :class="
                                                                order.status ===
                                                                'completed'
                                                                    ? 'dark:text-emerald-450 bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40'
                                                                    : 'dark:bg-amber-955/40 dark:text-amber-450 bg-amber-100 text-amber-800'
                                                            "
                                                        >
                                                            {{
                                                                order.status ===
                                                                'completed'
                                                                    ? 'Đã thanh toán'
                                                                    : 'Chưa thanh toán'
                                                            }}
                                                        </span>
                                                    </td>
                                                    <td class="p-3">
                                                        <span
                                                            v-if="
                                                                order.is_override_split_penalty
                                                            "
                                                            class="flex items-center gap-1 text-[10px] font-bold text-emerald-600"
                                                        >
                                                            <Check
                                                                class="size-3"
                                                            />
                                                            Đã bỏ phạt (Đã đối
                                                            soát)
                                                        </span>
                                                        <span
                                                            v-else-if="
                                                                order.status !==
                                                                'completed'
                                                            "
                                                            class="flex items-center gap-1 text-[10px] font-bold text-rose-600"
                                                        >
                                                            <AlertTriangle
                                                                class="size-3"
                                                            />
                                                            Chờ thu hồi / Tính
                                                            phạt âm
                                                        </span>
                                                        <span
                                                            v-else
                                                            class="text-slate-500"
                                                            >Bình thường</span
                                                        >
                                                    </td>
                                                    <td class="p-3 text-right">
                                                        <div
                                                            class="flex justify-end gap-2"
                                                        >
                                                            <Button
                                                                v-if="
                                                                    order.status !==
                                                                    'completed'
                                                                "
                                                                type="button"
                                                                size="sm"
                                                                variant="outline"
                                                                class="dark:text-slate-350 h-6 rounded-lg border-slate-200 text-[9px] text-slate-700 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-950"
                                                                @click="
                                                                    handlePrintSplitOrder(
                                                                        order.order_number,
                                                                    )
                                                                "
                                                            >
                                                                In đơn bị tách
                                                            </Button>
                                                            <Button
                                                                v-if="
                                                                    !order.is_override_split_penalty &&
                                                                    order.status !==
                                                                        'completed' &&
                                                                    isOwner
                                                                "
                                                                type="button"
                                                                size="sm"
                                                                class="h-6 rounded-lg bg-rose-600 text-[9px] font-semibold text-white shadow-sm transition-transform hover:bg-rose-700 active:scale-95"
                                                                @click="
                                                                    handleOverridePenalty(
                                                                        order.id,
                                                                    )
                                                                "
                                                            >
                                                                Bỏ phạt
                                                            </Button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
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
            <Card
                class="flex w-full max-w-lg animate-in flex-col overflow-hidden shadow-2xl duration-150 zoom-in-95 fade-in"
                style="max-height: 90vh"
            >
                <!-- Dialog Header -->
                <CardHeader
                    class="flex flex-row items-center justify-between gap-4 border-b pb-3"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-2xl border border-indigo-100 bg-indigo-50 text-indigo-600 dark:border-indigo-900/30 dark:bg-indigo-950/60 dark:text-indigo-400"
                        >
                            <ClipboardCheck class="size-5" />
                        </div>
                        <div>
                            <CardTitle
                                class="text-base text-indigo-600 dark:text-indigo-400"
                                >Lập Phiếu Chốt Ca Mới</CardTitle
                            >
                            <CardDescription>
                                Bước {{ dialogStep }} / 2 —
                                {{
                                    dialogStep === 1
                                        ? 'Chọn ca và ngày chốt ca làm việc'
                                        : 'Nhập đối soát tiền thực tế đếm được'
                                }}
                            </CardDescription>
                        </div>
                    </div>
                    <button
                        @click="showDialog = false"
                        class="rounded-lg p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                    >
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
                            <div class="flex flex-col space-y-1.5">
                                <Label
                                    class="text-xs font-bold tracking-wide text-slate-500 uppercase"
                                    >Ca làm việc cần chốt
                                    <span class="text-rose-500">*</span></Label
                                >
                                <select
                                    v-model="form.shift_id"
                                    class="mt-1.5 w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm font-semibold text-slate-700 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none"
                                >
                                    <option :value="null" disabled>
                                        Chọn ca...
                                    </option>
                                    <option
                                        v-for="shift in shifts"
                                        :key="shift.id"
                                        :value="shift.id"
                                    >
                                        {{ shift.name }} ({{
                                            shift.start_time
                                        }}
                                        – {{ shift.end_time
                                        }}{{
                                            shift.is_overnight
                                                ? ' +1 ngày'
                                                : ''
                                        }})
                                    </option>
                                </select>
                            </div>

                            <!-- Chọn ngày — Custom Calendar Picker -->
                            <div class="flex flex-col space-y-1.5">
                                <Label
                                    class="text-xs font-bold tracking-wide text-slate-500 uppercase"
                                    >Ngày chốt ca
                                    <span class="text-rose-500">*</span></Label
                                >

                                <!-- Trigger button -->
                                <button
                                    ref="calTriggerRef"
                                    type="button"
                                    @click="openCalendar"
                                    class="mt-1.5 flex w-full cursor-pointer items-center justify-between rounded-md border border-slate-200 bg-background px-3 py-2 text-sm font-semibold text-slate-700 transition hover:border-indigo-500/60 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none"
                                    :class="
                                        showCalendar
                                            ? 'border-indigo-500 ring-2 ring-indigo-500/20'
                                            : ''
                                    "
                                >
                                    <span
                                        :class="
                                            form.closing_date
                                                ? 'font-medium text-foreground'
                                                : 'text-muted-foreground'
                                        "
                                    >
                                        {{ displayDate || 'Chọn ngày...' }}
                                    </span>
                                    <CalendarDays
                                        class="size-4 shrink-0 text-indigo-500"
                                    />
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
                                        class="fixed z-[9999] animate-in overflow-hidden rounded-xl border border-slate-200 bg-card shadow-2xl duration-100 fade-in-50 zoom-in-95"
                                        :style="{
                                            top: calPos.top + 'px',
                                            left: calPos.left + 'px',
                                            width: '272px',
                                        }"
                                    >
                                        <!-- Header -->
                                        <div
                                            class="flex items-center justify-between border-b border-slate-100 bg-slate-50/50 px-3 py-2"
                                        >
                                            <button
                                                type="button"
                                                @click="prevMonth"
                                                class="flex cursor-pointer items-center justify-center rounded-md p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                                            >
                                                <ChevronLeft class="size-3.5" />
                                            </button>

                                            <!-- Click để mở month picker -->
                                            <button
                                                type="button"
                                                @click="
                                                    showMonthPicker =
                                                        !showMonthPicker
                                                "
                                                class="flex cursor-pointer items-center gap-1.5 rounded-md px-2 py-0.5 text-xs font-bold transition hover:bg-muted"
                                            >
                                                <span>{{
                                                    viMonths[calView.month]
                                                }}</span>
                                                <span
                                                    class="text-indigo-655 font-extrabold text-indigo-600 dark:text-indigo-400"
                                                    >{{ calView.year }}</span
                                                >
                                                <ChevronDown
                                                    class="size-3 text-muted-foreground"
                                                    :class="
                                                        showMonthPicker
                                                            ? 'rotate-180'
                                                            : ''
                                                    "
                                                />
                                            </button>

                                            <button
                                                type="button"
                                                @click="nextMonth"
                                                :disabled="isNextMonthDisabled"
                                                class="flex cursor-pointer items-center justify-center rounded-md p-1 text-muted-foreground hover:bg-muted hover:text-foreground disabled:cursor-not-allowed disabled:opacity-25"
                                            >
                                                <ChevronRight
                                                    class="size-3.5"
                                                />
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
                                            <div
                                                v-if="showMonthPicker"
                                                class="border-b border-slate-100 bg-slate-50/20 p-2"
                                            >
                                                <!-- Year nav -->
                                                <div
                                                    class="mb-2 flex items-center justify-between px-1"
                                                >
                                                    <button
                                                        type="button"
                                                        @click="prevYear"
                                                        class="flex cursor-pointer items-center justify-center rounded p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                                                    >
                                                        <ChevronLeft
                                                            class="size-3"
                                                        />
                                                    </button>
                                                    <span
                                                        class="text-indigo-655 text-xs font-bold font-extrabold text-indigo-600 dark:text-indigo-400"
                                                        >{{
                                                            calView.year
                                                        }}</span
                                                    >
                                                    <button
                                                        type="button"
                                                        @click="nextYear"
                                                        :disabled="
                                                            calView.year >=
                                                            today.getFullYear()
                                                        "
                                                        class="flex cursor-pointer items-center justify-center rounded p-0.5 text-muted-foreground hover:bg-muted hover:text-foreground disabled:cursor-not-allowed disabled:opacity-25"
                                                    >
                                                        <ChevronRight
                                                            class="size-3"
                                                        />
                                                    </button>
                                                </div>
                                                <!-- 12 month grid -->
                                                <div
                                                    class="grid grid-cols-4 gap-1"
                                                >
                                                    <button
                                                        v-for="(
                                                            m, idx
                                                        ) in viMonthsShort"
                                                        :key="idx"
                                                        type="button"
                                                        @click="
                                                            !isMonthFuture(
                                                                idx,
                                                            ) &&
                                                            selectMonth(idx)
                                                        "
                                                        :disabled="
                                                            isMonthFuture(idx)
                                                        "
                                                        class="rounded-lg py-1.5 text-[11px] font-semibold transition-all"
                                                        :class="[
                                                            calView.month ===
                                                                idx &&
                                                            !isMonthFuture(idx)
                                                                ? 'bg-indigo-600 text-white shadow-sm'
                                                                : '',
                                                            !isMonthFuture(
                                                                idx,
                                                            ) &&
                                                            calView.month !==
                                                                idx
                                                                ? 'cursor-pointer text-foreground hover:bg-indigo-500/15 hover:text-indigo-600'
                                                                : '',
                                                            isMonthFuture(idx)
                                                                ? 'cursor-not-allowed text-muted-foreground/25'
                                                                : '',
                                                        ]"
                                                    >
                                                        {{ m }}
                                                    </button>
                                                </div>
                                            </div>
                                        </Transition>

                                        <!-- Day names -->
                                        <div
                                            class="grid grid-cols-7 border-b border-slate-100/50 bg-slate-50/10 px-1.5 pt-1.5 pb-1"
                                        >
                                            <div
                                                v-for="d in viDays"
                                                :key="d"
                                                class="text-center text-[9px] font-bold tracking-widest"
                                                :class="
                                                    d === 'CN'
                                                        ? 'text-rose-500'
                                                        : 'text-muted-foreground'
                                                "
                                            >
                                                {{ d }}
                                            </div>
                                        </div>

                                        <!-- Day grid -->
                                        <div
                                            class="grid grid-cols-7 gap-0 bg-white p-1.5"
                                        >
                                            <button
                                                v-for="day in calDays"
                                                :key="day.date"
                                                type="button"
                                                @click="selectDate(day)"
                                                :disabled="day.isFuture"
                                                class="relative flex h-7 w-full items-center justify-center rounded-md text-[11px] font-medium transition-all"
                                                :class="[
                                                    day.isSelected
                                                        ? 'scale-110 bg-indigo-600 font-extrabold text-white shadow-md shadow-indigo-500/30'
                                                        : '',
                                                    day.isToday &&
                                                    !day.isSelected
                                                        ? 'text-indigo-655 border border-indigo-500 font-bold text-indigo-600 dark:text-indigo-400'
                                                        : '',
                                                    day.inMonth &&
                                                    !day.isSelected &&
                                                    !day.isToday &&
                                                    !day.isFuture
                                                        ? 'cursor-pointer text-foreground hover:bg-indigo-500/15 hover:text-indigo-600'
                                                        : '',
                                                    !day.inMonth &&
                                                    !day.isFuture
                                                        ? 'cursor-pointer text-muted-foreground/25'
                                                        : '',
                                                    day.isFuture
                                                        ? 'cursor-not-allowed text-muted-foreground/15'
                                                        : '',
                                                ]"
                                            >
                                                {{ day.day }}
                                                <span
                                                    v-if="
                                                        day.isToday &&
                                                        !day.isSelected
                                                    "
                                                    class="absolute bottom-0.5 left-1/2 h-0.5 w-0.5 -translate-x-1/2 rounded-full bg-indigo-500"
                                                />
                                            </button>
                                        </div>

                                        <!-- Footer: Hôm nay -->
                                        <div
                                            class="border-t border-slate-100 bg-white px-2 py-1.5"
                                        >
                                            <button
                                                type="button"
                                                @click="
                                                    selectDate({
                                                        date: todayStr,
                                                        day: today.getDate(),
                                                        inMonth: true,
                                                        isToday: true,
                                                        isFuture: false,
                                                        isSelected:
                                                            form.closing_date ===
                                                            todayStr,
                                                    })
                                                "
                                                class="w-full cursor-pointer rounded-lg bg-indigo-500/10 py-1.5 text-[11px] font-semibold text-indigo-600 transition hover:bg-indigo-600 hover:text-white dark:text-indigo-400"
                                            >
                                                Hôm nay ·
                                                {{
                                                    new Date().toLocaleDateString(
                                                        'vi-VN',
                                                        {
                                                            day: '2-digit',
                                                            month: '2-digit',
                                                            year: 'numeric',
                                                        },
                                                    )
                                                }}
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
                                <span
                                    >Ca qua đêm — kết thúc vào rạng sáng ngày
                                    hôm sau. Hệ thống sẽ tự động tổng hợp đúng
                                    khung giờ.</span
                                >
                            </div>

                            <!-- Error -->
                            <p
                                v-if="previewError"
                                class="mt-1 text-xs font-semibold text-rose-500"
                            >
                                {{ previewError }}
                            </p>
                        </div>
                    </template>

                    <!-- ── Step 2: Preview + Nhập tiền ─────────────────── -->
                    <template v-else-if="previewData">
                        <!-- Header ca -->
                        <div
                            class="mb-4 flex items-center justify-between border-b pb-2"
                        >
                            <div>
                                <p
                                    class="text-sm font-bold text-slate-800 dark:text-slate-200"
                                >
                                    {{ previewData.shift_name }} ({{
                                        previewData.shift_code
                                    }})
                                </p>
                                <p
                                    class="mt-0.5 font-mono text-xs font-medium text-slate-400"
                                >
                                    {{ previewData.start_time }} →
                                    {{ previewData.end_time }}
                                </p>
                            </div>
                            <span
                                class="rounded-full border border-emerald-100 bg-emerald-50 px-2.5 py-0.5 text-[11px] font-bold text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-300"
                            >
                                {{ previewData.order_count }} đơn hoàn thành
                            </span>
                        </div>

                        <!-- Revenue summary grid -->
                        <div class="mb-4 grid grid-cols-3 gap-2">
                            <div
                                class="rounded-xl border bg-slate-50/50 p-3 text-center dark:bg-slate-900/50"
                            >
                                <p
                                    class="mb-1 text-[9px] font-bold tracking-wider text-slate-400 uppercase"
                                >
                                    Doanh thu gộp
                                </p>
                                <p
                                    class="text-sm font-extrabold text-emerald-600 dark:text-emerald-400"
                                >
                                    {{ compact(previewData.gross_revenue) }}
                                </p>
                            </div>
                            <div
                                class="rounded-xl border bg-slate-50/50 p-3 text-center dark:bg-slate-900/50"
                            >
                                <p
                                    class="mb-1 text-[9px] font-bold tracking-wider text-slate-400 uppercase"
                                >
                                    Giảm giá
                                </p>
                                <p class="text-sm font-extrabold text-rose-500">
                                    -{{ compact(previewData.discount_total) }}
                                </p>
                            </div>
                            <div
                                class="rounded-xl border border-amber-500/10 bg-amber-500/5 p-3 text-center"
                            >
                                <p
                                    class="mb-1 text-[9px] font-bold tracking-wider text-amber-700 uppercase dark:text-amber-400"
                                >
                                    Doanh thu thuần
                                </p>
                                <p
                                    class="text-sm font-extrabold text-amber-700 dark:text-amber-400"
                                >
                                    {{ compact(previewData.net_revenue) }}
                                </p>
                            </div>
                        </div>

                        <!-- Payment breakdown -->
                        <div
                            class="mb-4 rounded-xl border border-slate-100 bg-slate-50/30 p-4 dark:border-slate-800"
                        >
                            <p
                                class="mb-3 text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                            >
                                Cơ cấu thanh toán
                            </p>
                            <div class="space-y-2">
                                <div
                                    class="flex items-center justify-between text-xs"
                                >
                                    <span class="flex items-center gap-2">
                                        <span
                                            class="inline-block h-2 w-2 rounded-full bg-blue-500"
                                        ></span>
                                        <span
                                            class="font-semibold text-slate-500"
                                            >Tiền mặt sổ sách (kỳ vọng)</span
                                        >
                                    </span>
                                    <span
                                        class="font-mono font-bold text-blue-600 dark:text-blue-400"
                                        >{{
                                            vnd(previewData.expected_cash)
                                        }}</span
                                    >
                                </div>
                                <div
                                    v-if="previewData.bank_transfer > 0"
                                    class="flex items-center justify-between text-xs"
                                >
                                    <span class="flex items-center gap-2">
                                        <span
                                            class="inline-block h-2 w-2 rounded-full bg-violet-500"
                                        ></span>
                                        <span
                                            class="font-semibold text-slate-500"
                                            >Chuyển khoản / Quét QR</span
                                        >
                                    </span>
                                    <span
                                        class="font-mono font-bold text-violet-600 dark:text-violet-400"
                                        >{{
                                            vnd(previewData.bank_transfer)
                                        }}</span
                                    >
                                </div>
                                <div
                                    v-if="previewData.card > 0"
                                    class="flex items-center justify-between text-xs"
                                >
                                    <span class="flex items-center gap-2">
                                        <span
                                            class="inline-block h-2 w-2 rounded-full bg-sky-500"
                                        ></span>
                                        <span
                                            class="font-semibold text-slate-500"
                                            >Quẹt thẻ ngân hàng</span
                                        >
                                    </span>
                                    <span
                                        class="font-mono font-bold text-sky-600 dark:text-sky-400"
                                        >{{ vnd(previewData.card) }}</span
                                    >
                                </div>
                                <div
                                    v-if="previewData.ewallet > 0"
                                    class="flex items-center justify-between text-xs"
                                >
                                    <span class="flex items-center gap-2">
                                        <span
                                            class="inline-block h-2 w-2 rounded-full bg-pink-500"
                                        ></span>
                                        <span
                                            class="font-semibold text-slate-500"
                                            >Ví điện tử</span
                                        >
                                    </span>
                                    <span
                                        class="font-mono font-bold text-pink-600 dark:text-pink-400"
                                        >{{ vnd(previewData.ewallet) }}</span
                                    >
                                </div>
                                <div
                                    v-if="previewData.mixed > 0"
                                    class="flex items-center justify-between text-xs"
                                >
                                    <span class="flex items-center gap-2">
                                        <span
                                            class="inline-block h-2 w-2 rounded-full bg-orange-500"
                                        ></span>
                                        <span
                                            class="font-semibold text-slate-500"
                                            >Giao dịch hỗn hợp</span
                                        >
                                    </span>
                                    <span
                                        class="font-mono font-bold text-orange-600"
                                        >{{ vnd(previewData.mixed) }}</span
                                    >
                                </div>
                                <div
                                    class="flex items-center justify-between border-t border-slate-100 pt-2 text-xs font-bold text-slate-700 dark:border-slate-800 dark:text-slate-300"
                                >
                                    <span>Tổng doanh thu (TM + CK)</span>
                                    <span class="font-mono">{{
                                        vnd(
                                            previewData.expected_cash +
                                                previewData.transfer_amount,
                                        )
                                    }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Split orders penalty preview -->
                        <div
                            v-if="
                                previewData.split_orders &&
                                previewData.split_orders.length > 0
                            "
                            class="mt-4 rounded-xl border border-rose-100 bg-rose-50/20 p-4 dark:border-rose-950/20 dark:bg-rose-950/5"
                        >
                            <div class="mb-2 flex items-center justify-between">
                                <p
                                    class="flex items-center gap-1 text-[10px] font-bold tracking-wider text-rose-600 uppercase"
                                >
                                    <TriangleAlert class="size-3.5" />
                                    Cảnh báo: Đơn bị tách trong ca
                                </p>
                                <span
                                    class="font-mono text-xs font-bold text-rose-600"
                                >
                                    -{{ vnd(previewData.split_penalty_total) }}
                                </span>
                            </div>
                            <p class="mb-3 text-[10px] text-muted-foreground">
                                Các đơn bị tách nếu chưa hoàn thành hoặc chưa
                                được Owner bỏ phạt sẽ bị tính âm trực tiếp vào
                                quỹ tiền mặt sổ sách của thu ngân.
                            </p>
                            <div
                                class="max-h-48 space-y-2 overflow-y-auto pr-1"
                            >
                                <div
                                    v-for="order in previewData.split_orders"
                                    :key="order.id"
                                    class="flex items-center justify-between rounded-lg border border-slate-100 bg-white p-2 text-xs dark:border-slate-800 dark:bg-slate-900"
                                >
                                    <div>
                                        <p
                                            class="font-bold text-slate-800 dark:text-slate-200"
                                        >
                                            {{ order.order_number }}
                                        </p>
                                        <div
                                            class="mt-0.5 flex items-center gap-2 text-[10px] text-slate-500"
                                        >
                                            <span
                                                >Số tiền:
                                                {{
                                                    vnd(order.total_amount)
                                                }}</span
                                            >
                                            <span
                                                :class="
                                                    order.status === 'completed'
                                                        ? 'font-medium text-emerald-600'
                                                        : 'font-bold text-rose-600'
                                                "
                                            >
                                                {{
                                                    order.status === 'completed'
                                                        ? 'Đã thanh toán'
                                                        : 'Chưa thanh toán'
                                                }}
                                            </span>
                                        </div>
                                    </div>
                                    <div
                                        class="flex gap-1"
                                        v-if="order.status !== 'completed'"
                                    >
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            class="border-slate-250 dark:border-slate-750 dark:text-slate-350 h-6 rounded-lg text-[9px] text-slate-700 hover:bg-slate-50 dark:hover:bg-slate-950"
                                            @click="
                                                handlePrintSplitOrder(
                                                    order.order_number,
                                                )
                                            "
                                        >
                                            In đơn bị tách
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Warnings -->
                        <div
                            v-if="previewData.already_closed"
                            class="mb-4 flex items-start gap-2.5 rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs text-rose-700 dark:border-rose-900 dark:bg-rose-900/20 dark:text-rose-300"
                        >
                            <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                            <span
                                >Ca
                                <strong>{{ previewData.shift_name }}</strong>
                                ngày này đã được chốt. Vui lòng chọn ca
                                khác.</span
                            >
                        </div>
                        <div
                            v-else-if="previewData.pending_orders > 0"
                            class="mb-4 flex items-start gap-2.5 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-700 dark:border-amber-900 dark:bg-amber-900/20 dark:text-amber-300"
                        >
                            <AlertTriangle
                                class="mt-0.5 size-4 shrink-0 animate-pulse text-amber-600"
                            />
                            <span
                                >Còn
                                <strong>{{
                                    previewData.pending_orders
                                }}</strong>
                                đơn chưa hoàn tất trong ca trực. Dữ liệu các đơn
                                này sẽ tạm thời không được cộng vào tổng doanh
                                thu chốt ca.</span
                            >
                        </div>

                        <!-- Input actual cash -->
                        <div class="space-y-4">
                            <div class="flex flex-col space-y-1.5">
                                <Label
                                    class="text-xs font-bold tracking-wide text-slate-500 uppercase"
                                    >Tiền mặt thực tế trong két tiền
                                    <span class="text-rose-500">*</span></Label
                                >
                                <div class="relative mt-1.5">
                                    <span
                                        class="absolute top-1/2 left-3 -translate-y-1/2 font-mono text-xs font-bold text-slate-400"
                                        >₫</span
                                    >
                                    <Input
                                        v-model.number="form.actual_cash"
                                        type="number"
                                        min="0"
                                        step="1000"
                                        class="h-9 pl-8 text-xs font-bold"
                                        :class="{
                                            'border-rose-400 focus-visible:ring-rose-400/20':
                                                form.errors.actual_cash,
                                        }"
                                    />
                                </div>
                                <p
                                    v-if="form.errors.actual_cash"
                                    class="mt-1 text-xs font-semibold text-rose-500"
                                >
                                    {{ form.errors.actual_cash }}
                                </p>
                            </div>

                            <!-- Chênh lệch live preview -->
                            <div
                                class="flex items-center justify-between rounded-xl border px-4 py-3 text-xs font-semibold transition-colors"
                                :class="
                                    cashDifference >= 0
                                        ? 'border-emerald-100 bg-emerald-50/50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-900/20 dark:text-emerald-300'
                                        : 'border-rose-100 bg-rose-50/50 text-rose-700 dark:border-rose-900 dark:bg-rose-900/20 dark:text-rose-300'
                                "
                            >
                                <span class="flex items-center gap-2">
                                    <component
                                        :is="
                                            cashDifference >= 0
                                                ? ArrowUpCircle
                                                : ArrowDownCircle
                                        "
                                        class="size-4 shrink-0"
                                    />
                                    Chênh lệch két tiền mặt thực tế
                                </span>
                                <span class="font-mono text-sm font-black">
                                    {{ cashDifference >= 0 ? '+' : ''
                                    }}{{ vnd(cashDifference) }}
                                </span>
                            </div>

                            <!-- Chi phí phát sinh -->
                            <div class="flex flex-col space-y-1.5">
                                <Label
                                    class="text-xs font-bold tracking-wide text-slate-500 uppercase"
                                    >Chi phí phát sinh trong ca (nếu có)</Label
                                >
                                <div class="relative mt-1.5">
                                    <span
                                        class="absolute top-1/2 left-3 -translate-y-1/2 font-mono text-xs font-bold text-slate-400"
                                        >₫</span
                                    >
                                    <Input
                                        v-model.number="
                                            form.other_expense_amount
                                        "
                                        type="number"
                                        min="0"
                                        step="1000"
                                        class="h-9 pl-8 text-xs font-bold"
                                    />
                                </div>
                            </div>

                            <!-- Ghi chú -->
                            <div class="flex flex-col space-y-1.5">
                                <Label
                                    class="text-xs font-bold tracking-wide text-slate-500 uppercase"
                                    >Ghi chú vận hành ca</Label
                                >
                                <textarea
                                    v-model="form.notes"
                                    rows="2"
                                    maxlength="1000"
                                    placeholder="Ghi rõ tình huống bất thường phát sinh, lý do chênh lệch két tiền (nếu có)..."
                                    class="mt-1.5 w-full resize-none rounded-md border border-slate-200 bg-background px-3 py-2 text-sm font-semibold text-slate-700 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none"
                                />
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Dialog Footer -->
                <div
                    class="flex items-center justify-between border-t border-slate-100 px-6 py-4"
                >
                    <Button
                        variant="outline"
                        @click="
                            dialogStep === 1
                                ? (showDialog = false)
                                : (dialogStep = 1)
                        "
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
                            class="flex h-9 items-center gap-1.5 bg-indigo-600 text-xs font-semibold text-white transition-transform hover:bg-indigo-700 active:scale-95"
                        >
                            <Loader2
                                v-if="previewLoading"
                                class="size-4 animate-spin"
                            />
                            <span>{{
                                previewLoading
                                    ? 'Đang tổng hợp...'
                                    : 'Tổng hợp doanh thu →'
                            }}</span>
                        </Button>

                        <!-- Step 2 actions -->
                        <template
                            v-if="
                                dialogStep === 2 &&
                                previewData &&
                                !previewData.already_closed
                            "
                        >
                            <Button
                                variant="outline"
                                @click="submitForm(false)"
                                :disabled="form.processing"
                                class="h-9 text-xs font-semibold transition-transform active:scale-95"
                            >
                                Lưu bản nháp
                            </Button>
                            <Button
                                @click="submitForm(true)"
                                :disabled="form.processing"
                                class="flex h-9 items-center gap-1.5 bg-emerald-600 text-xs font-semibold text-white transition-transform hover:bg-emerald-700 active:scale-95"
                            >
                                <Loader2
                                    v-if="form.processing"
                                    class="size-4 animate-spin"
                                />
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
            <Card
                class="flex w-full max-w-md animate-in flex-col overflow-hidden shadow-2xl duration-150 zoom-in-95 fade-in"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between gap-4 border-b pb-3"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-2xl bg-rose-50 text-rose-600 dark:bg-rose-950/60 dark:text-rose-400"
                        >
                            <AlertTriangle class="size-5" />
                        </div>
                        <div>
                            <CardTitle class="text-base text-rose-600"
                                >Yêu cầu đối soát lại ca</CardTitle
                            >
                            <CardDescription
                                >{{ disputeTarget.shift_name }} — Ngày
                                {{
                                    disputeTarget.closing_date
                                }}</CardDescription
                            >
                        </div>
                    </div>
                    <button
                        @click="disputeTarget = null"
                        class="rounded-lg p-1 text-muted-foreground hover:bg-muted hover:text-foreground"
                    >
                        <X class="size-4" />
                    </button>
                </CardHeader>

                <div class="flex flex-col gap-1.5 px-6 py-5">
                    <Label
                        class="text-xs font-bold tracking-wide text-slate-500 uppercase"
                        >Lý do tranh chấp / Đối soát lại
                        <span class="text-rose-500">*</span></Label
                    >
                    <textarea
                        v-model="disputeNotes"
                        rows="3"
                        maxlength="1000"
                        placeholder="Mô tả cụ thể và chi tiết sai lệch két tiền mặt hoặc chuyển khoản để quản lý/owner thực hiện đối soát lại..."
                        class="mt-1.5 w-full resize-none rounded-md border border-slate-200 bg-background px-3 py-2.5 text-sm font-semibold text-slate-700 focus-visible:ring-2 focus-visible:ring-rose-400 focus-visible:outline-none"
                    />
                </div>

                <div
                    class="flex justify-end gap-2 border-t border-slate-100 bg-slate-50/50 px-6 py-4 dark:bg-slate-900/50"
                >
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
                        class="flex h-9 items-center gap-1.5 bg-rose-600 text-xs font-semibold text-white transition-transform hover:bg-rose-700 active:scale-95"
                    >
                        <Loader2
                            v-if="disputeLoading"
                            class="size-4 animate-spin"
                        />
                        Xác nhận khiếu nại
                    </Button>
                </div>
            </Card>
        </div>
    </Transition>
</template>
