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
    ChevronsLeft,
    ChevronsRight,
    ChevronUp,
    ClipboardCheck,
    Clock,
    CreditCard,
    Info,
    Loader2,
    MapPin,
    ReceiptText,
    ShieldCheck,
    Store,
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

type ShiftClosing = {
    id: number;
    closing_date: string;
    closing_date_raw: string;
    shift_name: string;
    shift_code: string;
    shift_start: string;
    shift_end: string;
    period_start_at: string | null;
    area_name?: string;
    order_count?: number;
    total_order_count?: number;
    cash_order_count?: number;
    transfer_order_count?: number;
    cancelled_order_count?: number;
    cancelled_total_amount?: number;
    refunded_order_count?: number;
    refunded_total_amount?: number;
    cashier_name: string;
    status: Status;
    expected_cash: number;
    cash_sales_amount: number;
    actual_cash: number;
    cash_difference: number;
    transfer_amount: number;
    actual_transfer_amount: number;
    transfer_difference: number;
    gross_revenue_amount: number;
    discount_amount: number;
    net_revenue_amount: number;
    total_difference: number;
    responsibility_amount: number;
    responsibility_note: string | null;
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

type AreaBreakdownItem = {
    area_id: number | null;
    area_name: string;
    total_order_count: number;
    order_count: number;
    cash_order_count: number;
    expected_cash: number | null;
    transfer_order_count: number;
    transfer_amount: number;
    cancelled_order_count: number;
    cancelled_total_amount: number;
    refunded_order_count: number;
    refunded_total_amount: number;
    gross_revenue: number;
    discount_total: number;
    net_revenue: number;
};

type Preview = {
    shift_name: string;
    shift_code: string;
    start_time: string;
    end_time: string;
    is_overnight: boolean;
    order_count: number;
    total_order_count: number;
    cash_order_count: number;
    transfer_order_count: number;
    cancelled_order_count: number;
    cancelled_total_amount: number;
    refunded_order_count: number;
    refunded_total_amount: number;
    gross_revenue: number;
    discount_total: number;
    net_revenue: number;
    // null khi chế độ đếm mù đang bật và thu ngân chưa nộp phiếu đếm.
    cash_sales_amount: number | null;
    expected_cash: number | null;
    bank_transfer: number;
    card: number;
    ewallet: number;
    mixed: number;
    transfer_amount: number;
    pending_orders: number;
    already_closed: boolean;
    areas_breakdown?: AreaBreakdownItem[];
    opening_balance?: number | null;
    other_cash_in?: number | null;
    other_cash_out?: number | null;
    blind_count_required?: boolean;
    cash_count_id?: number | null;
    counted_cash?: number | null;
    variance_threshold?: number;
    evidence_threshold?: number;
    has_register?: boolean;
    period_start_at: string;
    period_end_at: string;
    area_name: string;
    closing_at: string;
};

const props = defineProps<{
    closings: ShiftClosing[];
    shifts: Shift[];
    areas?: Array<{ id: number; name: string }>;
    kpi: KPI;
    filters: { status: string; month: string };
    canConfirm: boolean;
    isOwner?: boolean;
    cashControl?: {
        blind_cash_count_enabled: boolean;
        cash_variance_threshold: number;
        cash_evidence_threshold: number;
        cash_handover_required: boolean;
    };
}>();

// ── Cấu hình kiểm soát tiền mặt (Chủ) ─────────────────────────────────────────
const showCashControl = ref(false);
const cashControlForm = useForm({
    blind_cash_count_enabled: props.cashControl?.blind_cash_count_enabled ?? true,
    cash_variance_threshold: props.cashControl?.cash_variance_threshold ?? 20000,
    cash_evidence_threshold: props.cashControl?.cash_evidence_threshold ?? 200000,
    cash_handover_required: props.cashControl?.cash_handover_required ?? false,
});
function saveCashControl() {
    if (cashControlForm.processing) {
        return;
    }

    cashControlForm.post('/shift-closings/cash-control', {
        preserveScroll: true,
        onSuccess: () => {
            showCashControl.value = false;
        },
    });
}

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

// Nhận null vì các con số tiền mặt bị giấu khi chế độ đếm mù đang chờ phiếu đếm.
const vnd = (v: number | null | undefined) =>
    new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(v ?? 0);

const compact = (v: number | null | undefined) =>
    new Intl.NumberFormat('vi-VN', {
        notation: 'compact',
        maximumFractionDigits: 1,
    }).format(v ?? 0) + 'đ';

// ── Tenant & Branch info ───────────────────────────────────────────────────────

const page = usePage();
const tenant = computed(() => (page.props as any).tenant);
const restaurantName = computed(() => tenant.value?.name || 'Cửa hàng');
const activeBranchName = computed(() => {
    if (!tenant.value?.branches || tenant.value?.branches.length === 0) {
        return 'Chi nhánh mặc định';
    }

    if (!tenant.value?.active_branch_id) {
        return 'Toàn bộ chi nhánh';
    }

    const branch = tenant.value.branches.find(
        (b: any) => b.id === tenant.value.active_branch_id,
    );

    return branch ? branch.name : 'Chi nhánh mặc định';
});

// ── Create dialog ─────────────────────────────────────────────────────────────

const showDialog = ref(false);
const dialogStep = ref<1 | 2>(1);
const previewData = ref<Preview | null>(null);
const previewLoading = ref(false);
const previewError = ref('');

function formatLocalDate(d: Date): string {
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

const form = useForm({
    shift_id: null as number | null,
    closing_date: formatLocalDate(new Date()),
    area_id: null as number | string | null,
    actual_cash: 0,
    cash_count_id: null as number | null,
    variance_explanation: '',
    actual_transfer_amount: 0,
    responsibility_amount: 0,
    responsibility_note: '',
    other_expense_amount: 0,
    notes: '',
});

// ── Đếm tiền mù ───────────────────────────────────────────────────────────────
// Thu ngân nhập số tờ theo từng mệnh giá TRƯỚC khi hệ thống lộ số kỳ vọng.
// Nhập theo mệnh giá khó bịa cho khớp hơn nhiều so với gõ thẳng một con số tổng.
const DENOMINATIONS = [
    500000, 200000, 100000, 50000, 20000, 10000, 5000, 2000, 1000, 500,
];

const denominationCounts = ref<Record<number, number>>({});
const countSubmitting = ref(false);
const countError = ref('');

const countedTotal = computed(() =>
    DENOMINATIONS.reduce(
        (sum, d) => sum + d * (Number(denominationCounts.value[d]) || 0),
        0,
    ),
);

/** Số kỳ vọng đã bị giấu cho tới khi nộp phiếu đếm. */
const needsBlindCount = computed(
    () => previewData.value?.blind_count_required === true,
);

function resetCount() {
    denominationCounts.value = {};
    countError.value = '';
    form.cash_count_id = null;
}

/** Nộp phiếu đếm; server trả về số kỳ vọng và chênh lệch. */
async function submitCount() {
    if (countSubmitting.value || !form.shift_id) {
        return;
    }

    const filled = Object.fromEntries(
        DENOMINATIONS.filter(
            (d) => Number(denominationCounts.value[d]) > 0,
        ).map((d) => [String(d), Number(denominationCounts.value[d])]),
    );

    if (Object.keys(filled).length === 0) {
        countError.value = 'Nhập số tờ của ít nhất một mệnh giá.';

        return;
    }

    countSubmitting.value = true;
    countError.value = '';

    try {
        const res = await fetch('/shift-closings/count', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN':
                    document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute('content') ?? '',
            },
            body: JSON.stringify({
                shift_id: form.shift_id,
                closing_date: form.closing_date,
                area_id: form.area_id,
                denominations: filled,
            }),
        });

        const data = await res.json();

        if (!res.ok) {
            countError.value = data.message ?? 'Không ghi nhận được phiếu đếm.';

            return;
        }

        form.cash_count_id = data.cash_count_id;
        form.actual_cash = data.total_counted;

        // Đã đếm xong thì tải lại preview, lúc này server mới trả số kỳ vọng.
        await loadPreview({ keepCount: true });
    } catch {
        countError.value = 'Lỗi kết nối. Thử lại.';
    } finally {
        countSubmitting.value = false;
    }
}

const isSubmitting = ref(false);
const isProcessing = ref(false);
const responsibilityAuto = ref(true);

const cashDifference = computed(() => {
    if (!previewData.value || previewData.value.expected_cash === null) {
        return 0;
    }

    return form.actual_cash - previewData.value.expected_cash;
});

/** Chênh lệch vượt ngưỡng thì bắt buộc giải trình mới chốt được. */
const varianceNeedsExplanation = computed(() => {
    const threshold = previewData.value?.variance_threshold ?? 0;

    return Math.abs(cashDifference.value) > threshold;
});

const transferDifference = computed(() => {
    if (!previewData.value) {
        return 0;
    }

    return form.actual_transfer_amount - previewData.value.transfer_amount;
});

const totalDifference = computed(
    () => cashDifference.value + transferDifference.value,
);

watch([() => form.actual_cash, () => form.actual_transfer_amount], () => {
    if (responsibilityAuto.value) {
        form.responsibility_amount = totalDifference.value;
    }
});

function openDialog() {
    form.reset();
    form.closing_date = todayStr;
    form.shift_id = null;
    form.area_id = null;
    form.actual_cash = 0;
    form.actual_transfer_amount = 0;
    form.responsibility_amount = 0;
    form.responsibility_note = '';
    form.other_expense_amount = 0;
    form.notes = '';
    responsibilityAuto.value = true;
    previewData.value = null;
    previewError.value = '';
    resetCount();
    form.variance_explanation = '';
    dialogStep.value = 1;
    isSubmitting.value = false;
    showDialog.value = true;
}

async function loadPreview(options: { keepCount?: boolean } = {}) {
    if (!form.shift_id || !form.closing_date || !form.area_id) {
        previewError.value = 'Vui lòng chọn ca, khu vực và ngày.';

        return;
    }

    previewLoading.value = true;
    previewError.value = '';
    previewData.value = null;

    try {
        const areaParam = form.area_id ? `&area_id=${form.area_id}` : '';
        const url = `/shift-closings/preview?shift_id=${form.shift_id}&closing_date=${form.closing_date}${areaParam}`;
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

        // KHÔNG điền sẵn actual_cash bằng expected_cash. Trước đây dòng này làm
        // chênh lệch luôn bằng 0, nên việc chốt ca không phát hiện được thất thoát.
        if (!options.keepCount) {
            form.actual_cash = data.counted_cash ?? 0;
            form.cash_count_id = data.cash_count_id ?? null;
            denominationCounts.value = {};
        }

        form.actual_transfer_amount = data.transfer_amount;
        responsibilityAuto.value = true;
        form.responsibility_amount = 0;
        dialogStep.value = 2;
    } catch {
        previewError.value = 'Lỗi kết nối. Thử lại.';
    } finally {
        previewLoading.value = false;
    }
}

function submitForm(isSubmit: boolean) {
    if (isProcessing.value) {
        return;
    }

    isProcessing.value = true;
    isSubmitting.value = isSubmit;
    form.transform((data: any) => ({ ...data, submit: isSubmit ? 1 : 0 })).post(
        '/shift-closings',
        {
            onSuccess: () => {
                showDialog.value = false;
                form.reset();
                previewData.value = null;
                dialogStep.value = 1;
                toast.success(
                    isSubmit
                        ? 'Đã nộp phiếu chốt ca thành công!'
                        : 'Đã lưu bản nháp.',
                );
            },
            onError: (errors: any) => {
                const msg = Object.values(errors)[0];

                if (msg) {
                    toast.error(String(msg));
                }
            },
            onFinish: () => {
                isSubmitting.value = false;
                isProcessing.value = false;
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

// ── Phân trang danh sách phiếu chốt ca (10 dòng / trang) ──────────────────────
const currentPage = ref(1);
const itemsPerPage = 10;

const totalItems = computed(() => props.closings.length);
const totalPages = computed(() => Math.max(1, Math.ceil(totalItems.value / itemsPerPage)));

const paginatedClosings = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;

    return props.closings.slice(start, start + itemsPerPage);
});

const startItemIndex = computed(() => {
    if (totalItems.value === 0) {
        return 0;
    }

    return (currentPage.value - 1) * itemsPerPage + 1;
});

const endItemIndex = computed(() => {
    return Math.min(currentPage.value * itemsPerPage, totalItems.value);
});

function setPage(pageNumber: number) {
    if (pageNumber >= 1 && pageNumber <= totalPages.value) {
        currentPage.value = pageNumber;
        expandedId.value = null;
    }
}

// Tự động về trang 1 khi đổi tab lọc, tháng hoặc dữ liệu thay đổi
watch([() => props.closings, activeStatus, activeMonth], () => {
    currentPage.value = 1;
    expandedId.value = null;
});

const selectedShift = computed(
    () => props.shifts.find((s) => s.id === form.shift_id) ?? null,
);

// ── Custom Calendar Picker & Quick Dates ──────────────────────────────────────

const showCalendar = ref(false);
const calTriggerRef = ref<HTMLElement | null>(null);
const calPos = ref({ top: 0, left: 0, width: 330 });

const today = new Date();
const todayStr = formatLocalDate(today);
const yesterdayStr = formatLocalDate(new Date(Date.now() - 86400000));
const twoDaysAgoStr = formatLocalDate(new Date(Date.now() - 86400000 * 2));

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

    if (calTriggerRef.value) {
        const rect = calTriggerRef.value.getBoundingClientRect();
        const calWidth = Math.min(340, window.innerWidth - 32);
        const calHeight = 350; // Chiều cao thực tế của popup lịch

        let leftPos = rect.left;

        if (leftPos + calWidth > window.innerWidth - 16) {
            leftPos = window.innerWidth - calWidth - 16;
        }

        if (leftPos < 16) {
            leftPos = 16;
        }

        // Kiểm tra khoảng trống trên và dưới viewport
        const spaceBelow = window.innerHeight - rect.bottom;
        const spaceAbove = rect.top;

        let topPos: number;

        if (spaceBelow < calHeight + 12 && spaceAbove >= calHeight + 12) {
            // Đưa bảng lịch lên trên ô input
            topPos = rect.top - calHeight - 6;
        } else if (spaceBelow < calHeight + 12 && spaceAbove > spaceBelow) {
            // Không đủ chỗ cả 2 phía nhưng phía trên thoáng hơn -> đẩy lên
            topPos = Math.max(16, rect.top - calHeight - 6);
        } else {
            // Mở xuống dưới
            topPos = rect.bottom + 6;
        }

        // Đảm bảo không bao giờ bị tràn ra ngoài cạnh trên/dưới màn hình
        topPos = Math.max(
            12,
            Math.min(topPos, window.innerHeight - calHeight - 12),
        );

        calPos.value = {
            top: topPos,
            left: leftPos,
            width: calWidth,
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
    const nowLocalDate = formatLocalDate(new Date());

    for (let i = startDow - 1; i >= 0; i--) {
        const d = new Date(year, month, -i);
        const str = formatLocalDate(d);
        days.push({
            date: str,
            day: d.getDate(),
            inMonth: false,
            isToday: str === todayStr,
            isFuture: str > nowLocalDate,
            isSelected: str === form.closing_date,
        });
    }

    for (let d = 1; d <= lastDay.getDate(); d++) {
        const dt = new Date(year, month, d);
        const str = formatLocalDate(dt);
        days.push({
            date: str,
            day: d,
            inMonth: true,
            isToday: str === todayStr,
            isFuture: str > nowLocalDate,
            isSelected: str === form.closing_date,
        });
    }

    const remaining = 42 - days.length;

    for (let i = 1; i <= remaining; i++) {
        const d = new Date(year, month + 1, i);
        const str = formatLocalDate(d);
        days.push({
            date: str,
            day: i,
            inMonth: false,
            isToday: str === todayStr,
            isFuture: str > nowLocalDate,
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

function setQuickDate(dateStr: string) {
    form.closing_date = dateStr;
    const d = new Date(dateStr + 'T00:00:00');
    calView.value = { year: d.getFullYear(), month: d.getMonth() };
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

                <!-- Cấu hình kiểm soát tiền mặt (chỉ Chủ) -->
                <Button
                    v-if="props.isOwner"
                    variant="outline"
                    @click="showCashControl = !showCashControl"
                    class="flex h-9 items-center gap-1.5 text-xs font-semibold"
                >
                    <ShieldCheck class="size-4" />
                    Kiểm soát tiền mặt
                </Button>

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

        <!-- ── Panel cấu hình kiểm soát tiền mặt (Chủ) ─────────────────────── -->
        <div
            v-if="props.isOwner && showCashControl"
            class="rounded-2xl border border-indigo-100 bg-indigo-50/40 p-5 dark:border-indigo-950/40 dark:bg-indigo-950/10"
        >
            <div class="mb-3 flex items-center gap-2 text-sm font-bold text-indigo-800 dark:text-indigo-300">
                <ShieldCheck class="size-4" /> Kiểm soát tiền mặt cuối ca
                <span class="text-[11px] font-normal text-slate-500">(áp cho chi nhánh đang xem)</span>
            </div>
            <form @submit.prevent="saveCashControl" class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="flex items-center gap-2 text-xs font-semibold text-slate-700 dark:text-slate-300">
                    <input v-model="cashControlForm.blind_cash_count_enabled" type="checkbox" class="rounded" />
                    Bắt buộc đếm tiền mù (đếm trước khi lộ số kỳ vọng)
                </label>
                <label class="flex items-center gap-2 text-xs font-semibold text-slate-700 dark:text-slate-300">
                    <input v-model="cashControlForm.cash_handover_required" type="checkbox" class="rounded" />
                    Bắt buộc bàn giao tiền có chữ ký hai bên
                </label>
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-bold text-slate-600 dark:text-slate-400">Ngưỡng chênh lệch phải giải trình (đ)</label>
                    <input v-model="cashControlForm.cash_variance_threshold" type="number" min="0" step="1000"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm" />
                </div>
                <div class="flex flex-col gap-1.5">
                    <label class="text-xs font-bold text-slate-600 dark:text-slate-400">Ngưỡng chênh lệch phải kèm ảnh (đ)</label>
                    <input v-model="cashControlForm.cash_evidence_threshold" type="number" min="0" step="1000"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm" />
                </div>
                <div class="sm:col-span-2 flex justify-end gap-2">
                    <Button type="button" variant="outline" @click="showCashControl = false" class="h-9 text-xs">Đóng</Button>
                    <Button type="submit" :disabled="cashControlForm.processing"
                        class="h-9 bg-indigo-600 text-xs font-bold text-white hover:bg-indigo-700">
                        Lưu cấu hình
                    </Button>
                </div>
            </form>
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
                        class="hidden grid-cols-[auto_1.1fr_1.1fr_1fr_1.1fr_1.1fr_1fr_1fr_1.2fr_auto] gap-2.5 border-b border-slate-100 bg-slate-50/50 px-5 py-3 text-[10px] font-bold tracking-wider text-slate-500 uppercase lg:grid dark:border-slate-800 dark:bg-slate-900/30"
                    >
                        <div></div>
                        <div>Ngày / Ca</div>
                        <div>Khu vực</div>
                        <div>Đơn vào</div>
                        <div class="text-right">Thanh toán TM</div>
                        <div class="text-right">Thanh toán CK</div>
                        <div class="text-right">Đơn hủy</div>
                        <div class="text-right">Hoàn tiền</div>
                        <div class="text-right">Tổng doanh thu</div>
                        <div class="text-right">Trạng thái</div>
                    </div>

                    <!-- Rows -->
                    <div
                        v-for="closing in paginatedClosings"
                        :key="closing.id"
                        class="border-b border-slate-100 last:border-0 dark:border-slate-800"
                    >
                        <!-- Main Row -->
                        <div
                            class="grid cursor-pointer grid-cols-[auto_1fr_auto] items-center gap-3 px-4 py-4 transition hover:bg-slate-50/60 lg:grid-cols-[auto_1.1fr_1.1fr_1fr_1.1fr_1.1fr_1fr_1fr_1.2fr_auto] lg:gap-2.5 lg:px-5 dark:hover:bg-slate-900/30"
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
                                </div>
                            </div>

                            <!-- Khu vực -->
                            <div class="hidden items-center lg:flex">
                                <span
                                    class="inline-flex items-center rounded-lg border border-indigo-200/60 bg-indigo-50/70 px-2.5 py-1 text-xs font-bold text-indigo-700 dark:border-indigo-900/40 dark:bg-indigo-950/50 dark:text-indigo-300"
                                >
                                    {{ closing.area_name || 'Khu vực chung' }}
                                </span>
                            </div>

                            <!-- Đơn vào khu vực -->
                            <div class="hidden flex-col lg:flex">
                                <span
                                    class="text-xs font-extrabold text-slate-800 dark:text-slate-200"
                                >
                                    {{
                                        closing.total_order_count ??
                                        closing.order_count ??
                                        0
                                    }}
                                    đơn
                                </span>
                                <span
                                    class="text-[10px] font-medium text-slate-400"
                                >
                                    ({{ closing.order_count ?? 0 }} xong)
                                </span>
                            </div>

                            <!-- Thanh toán TM -->
                            <div class="hidden text-right font-mono lg:block">
                                <p
                                    class="text-xs font-bold text-blue-600 dark:text-blue-400"
                                >
                                    {{ compact(closing.expected_cash) }}
                                </p>
                                <p class="text-[10px] text-slate-400">
                                    {{ closing.cash_order_count ?? 0 }} đơn TM
                                </p>
                            </div>

                            <!-- Thanh toán CK -->
                            <div class="hidden text-right font-mono lg:block">
                                <p
                                    class="text-xs font-bold text-violet-600 dark:text-violet-400"
                                >
                                    {{ compact(closing.transfer_amount) }}
                                </p>
                                <p class="text-[10px] text-slate-400">
                                    {{ closing.transfer_order_count ?? 0 }} đơn
                                    CK
                                </p>
                            </div>

                            <!-- Đơn hủy -->
                            <div class="hidden text-right font-mono lg:block">
                                <p class="text-xs font-bold text-rose-500">
                                    {{
                                        compact(
                                            closing.cancelled_total_amount ?? 0,
                                        )
                                    }}
                                </p>
                                <p class="text-[10px] text-slate-400">
                                    {{ closing.cancelled_order_count ?? 0 }} đơn
                                    hủy
                                </p>
                            </div>

                            <!-- Hoàn tiền -->
                            <div class="hidden text-right font-mono lg:block">
                                <p class="text-xs font-bold text-amber-600">
                                    {{
                                        compact(
                                            closing.refunded_total_amount ?? 0,
                                        )
                                    }}
                                </p>
                                <p class="text-[10px] text-slate-400">
                                    {{ closing.refunded_order_count ?? 0 }} đơn
                                    hoàn
                                </p>
                            </div>

                            <!-- Tổng doanh thu (= TM + CK - Hoàn tiền) -->
                            <div class="hidden text-right font-mono lg:block">
                                <p
                                    class="text-sm font-black text-emerald-600 dark:text-emerald-400"
                                >
                                    {{ compact(closing.gross_revenue) }}
                                </p>
                                <p class="text-[9px] text-slate-400">
                                    TM+CK-Hoàn
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
                                                class="flex justify-between text-violet-600 dark:text-violet-400"
                                            >
                                                <span class="font-medium"
                                                    >CK thực nhận</span
                                                >
                                                <span
                                                    class="font-mono font-bold"
                                                    >{{
                                                        vnd(
                                                            closing.actual_transfer_amount,
                                                        )
                                                    }}</span
                                                >
                                            </div>
                                            <div
                                                class="flex justify-between text-violet-600 dark:text-violet-400"
                                            >
                                                <span class="font-medium"
                                                    >Lệch CK</span
                                                >
                                                <span
                                                    class="font-mono font-bold"
                                                    >{{
                                                        closing.transfer_difference >=
                                                        0
                                                            ? '+'
                                                            : ''
                                                    }}{{
                                                        vnd(
                                                            closing.transfer_difference,
                                                        )
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
                                                class="flex justify-between border-t border-amber-100 pt-2 text-amber-700 dark:border-amber-900/40 dark:text-amber-300"
                                            >
                                                <span class="font-bold"
                                                    >Quy trách nhiệm</span
                                                >
                                                <span
                                                    class="font-mono font-black"
                                                    >{{
                                                        closing.responsibility_amount >=
                                                        0
                                                            ? '+'
                                                            : ''
                                                    }}{{
                                                        vnd(
                                                            closing.responsibility_amount,
                                                        )
                                                    }}</span
                                                >
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
                            </div>
                        </Transition>
                    </div>

                    <!-- Pagination Toolbar (10 rows/page) -->
                    <div
                        v-if="totalItems > 0"
                        class="flex flex-col items-center justify-between gap-3 border-t border-slate-100 bg-slate-50/50 px-5 py-3.5 sm:flex-row dark:border-slate-800 dark:bg-slate-900/30"
                    >
                        <!-- Left: Record summary -->
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Hiển thị
                            <span class="font-bold text-slate-800 dark:text-slate-200"
                                >{{ startItemIndex }} - {{ endItemIndex }}</span
                            >
                            trong tổng số
                            <span class="font-bold text-slate-800 dark:text-slate-200"
                                >{{ totalItems }}</span
                            >
                            phiếu chốt ca
                        </p>

                        <!-- Right: Pagination Buttons -->
                        <div
                            v-if="totalPages > 1"
                            class="flex items-center gap-1"
                        >
                            <!-- First page -->
                            <button
                                type="button"
                                :disabled="currentPage === 1"
                                @click="setPage(1)"
                                class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-600 shadow-2xs transition hover:bg-slate-50 hover:text-slate-900 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
                                title="Trang đầu"
                            >
                                <ChevronsLeft class="size-3.5" />
                            </button>

                            <!-- Previous page -->
                            <button
                                type="button"
                                :disabled="currentPage === 1"
                                @click="setPage(currentPage - 1)"
                                class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-600 shadow-2xs transition hover:bg-slate-50 hover:text-slate-900 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
                                title="Trang trước"
                            >
                                <ChevronLeft class="size-3.5" />
                            </button>

                            <!-- Page numbers -->
                            <button
                                v-for="p in totalPages"
                                :key="p"
                                type="button"
                                @click="setPage(p)"
                                :class="[
                                    'inline-flex h-8 min-w-[32px] cursor-pointer items-center justify-center rounded-lg px-2 text-xs font-bold transition shadow-2xs',
                                    currentPage === p
                                        ? 'border border-indigo-600 bg-indigo-600 text-white dark:border-indigo-500 dark:bg-indigo-600'
                                        : 'border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 hover:text-slate-900 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800',
                                ]"
                            >
                                {{ p }}
                            </button>

                            <!-- Next page -->
                            <button
                                type="button"
                                :disabled="currentPage === totalPages"
                                @click="setPage(currentPage + 1)"
                                class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-600 shadow-2xs transition hover:bg-slate-50 hover:text-slate-900 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
                                title="Trang kế"
                            >
                                <ChevronRight class="size-3.5" />
                            </button>

                            <!-- Last page -->
                            <button
                                type="button"
                                :disabled="currentPage === totalPages"
                                @click="setPage(totalPages)"
                                class="inline-flex h-8 w-8 cursor-pointer items-center justify-center rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-600 shadow-2xs transition hover:bg-slate-50 hover:text-slate-900 disabled:cursor-not-allowed disabled:opacity-40 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
                                title="Trang cuối"
                            >
                                <ChevronsRight class="size-3.5" />
                            </button>
                        </div>
                    </div>
                </template>
            </CardContent>
        </Card>
    </div>

    <!-- ══ Create Shift Closing Dialog ═══════════════════════════════════════ -->
    <Teleport to="body">
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
                    class="flex w-full animate-in flex-col overflow-hidden shadow-2xl transition-all duration-150 duration-200 zoom-in-95 fade-in"
                    :class="dialogStep === 1 ? 'max-w-lg' : 'max-w-5xl'"
                    style="max-height: 94vh"
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
                            :style="{
                                width: dialogStep === 1 ? '50%' : '100%',
                            }"
                        />
                    </div>

                    <!-- Dialog Body -->
                    <div class="flex-1 overflow-y-auto px-6 py-5">
                        <!-- ── Step 1: Chọn ca & ngày ───────────────────────── -->
                        <template v-if="dialogStep === 1">
                            <div class="space-y-4">
                                <!-- Banner thông tin Cửa hàng & Chi nhánh đang chốt ca -->
                                <div
                                    class="flex items-center gap-3 rounded-xl border border-indigo-100 bg-indigo-50/70 p-3 text-xs dark:border-indigo-900/40 dark:bg-indigo-950/40"
                                >
                                    <div
                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-indigo-600 text-white dark:bg-indigo-500"
                                    >
                                        <Store class="size-4" />
                                    </div>
                                    <div class="flex min-w-0 flex-col gap-0.5">
                                        <div
                                            class="flex flex-wrap items-center gap-1.5"
                                        >
                                            <span
                                                class="truncate font-bold text-slate-800 dark:text-slate-200"
                                                >{{ restaurantName }}</span
                                            >
                                            <span
                                                class="text-slate-300 dark:text-slate-600"
                                                >•</span
                                            >
                                            <span
                                                class="inline-flex items-center gap-1 font-bold text-indigo-600 dark:text-indigo-400"
                                            >
                                                <MapPin
                                                    class="size-3 shrink-0"
                                                />
                                                {{ activeBranchName }}
                                            </span>
                                        </div>
                                        <p
                                            class="text-[11px] text-slate-500 dark:text-slate-400"
                                        >
                                            Đang lập phiếu chốt ca cho cửa hàng
                                            và chi nhánh này
                                        </p>
                                    </div>
                                </div>

                                <!-- Chọn ca -->
                                <div class="flex flex-col space-y-1.5">
                                    <Label
                                        class="text-xs font-bold tracking-wide text-slate-500 uppercase"
                                        >Ca làm việc cần chốt
                                        <span class="text-rose-500"
                                            >*</span
                                        ></Label
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

                                <!-- Chọn khu vực -->
                                <div class="flex flex-col space-y-1.5">
                                    <Label
                                        class="text-xs font-bold tracking-wide text-slate-500 uppercase"
                                        >Khu vực cần chốt
                                        <span class="text-rose-500"
                                            >*</span
                                        ></Label
                                    >
                                    <select
                                        v-model="form.area_id"
                                        class="mt-1.5 w-full rounded-md border border-slate-200 bg-background px-3 py-2 text-sm font-semibold text-slate-700 focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:outline-none"
                                    >
                                        <option :value="null" disabled>
                                            Chọn khu vực...
                                        </option>
                                        <option
                                            v-for="area in areas"
                                            :key="area.id"
                                            :value="area.id"
                                        >
                                            {{ area.name }}
                                        </option>
                                        <option value="takeaway">
                                            Mang về / Giao hàng
                                        </option>
                                    </select>
                                </div>

                                <!-- Chọn ngày — Custom Calendar Picker & Quick Presets -->
                                <div class="flex flex-col space-y-2">
                                    <div class="flex items-center justify-between">
                                        <Label
                                            class="text-xs font-bold tracking-wide text-slate-500 uppercase"
                                        >
                                            Ngày chốt ca
                                            <span class="text-rose-500">*</span>
                                        </Label>

                                        <!-- Quick pills: Hôm nay, Hôm qua, Hôm kia -->
                                        <div class="flex items-center gap-1.5">
                                            <button
                                                type="button"
                                                @click="setQuickDate(todayStr)"
                                                class="cursor-pointer rounded-md px-2 py-0.5 text-xs font-semibold transition"
                                                :class="
                                                    form.closing_date === todayStr
                                                        ? 'bg-indigo-600 font-bold text-white shadow-xs'
                                                        : 'bg-slate-100 text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-indigo-950/50 dark:hover:text-indigo-400'
                                                "
                                            >
                                                Hôm nay
                                            </button>
                                            <button
                                                type="button"
                                                @click="setQuickDate(yesterdayStr)"
                                                class="cursor-pointer rounded-md px-2 py-0.5 text-xs font-semibold transition"
                                                :class="
                                                    form.closing_date === yesterdayStr
                                                        ? 'bg-indigo-600 font-bold text-white shadow-xs'
                                                        : 'bg-slate-100 text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-indigo-950/50 dark:hover:text-indigo-400'
                                                "
                                            >
                                                Hôm qua
                                            </button>
                                            <button
                                                type="button"
                                                @click="setQuickDate(twoDaysAgoStr)"
                                                class="cursor-pointer rounded-md px-2 py-0.5 text-xs font-semibold transition"
                                                :class="
                                                    form.closing_date === twoDaysAgoStr
                                                        ? 'bg-indigo-600 font-bold text-white shadow-xs'
                                                        : 'bg-slate-100 text-slate-600 hover:bg-indigo-50 hover:text-indigo-600 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-indigo-950/50 dark:hover:text-indigo-400'
                                                "
                                            >
                                                Hôm kia
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Trigger button -->
                                    <button
                                        ref="calTriggerRef"
                                        type="button"
                                        @click="openCalendar"
                                        class="flex w-full cursor-pointer items-center justify-between rounded-lg border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-semibold text-slate-800 shadow-xs transition hover:border-indigo-500 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                                        :class="
                                            showCalendar
                                                ? 'border-indigo-500 ring-2 ring-indigo-500/20'
                                                : ''
                                        "
                                    >
                                        <span class="font-medium text-slate-900 dark:text-slate-100">
                                            {{ displayDate || 'Chọn ngày...' }}
                                        </span>
                                        <div class="flex items-center gap-1.5 text-indigo-600 dark:text-indigo-400">
                                            <CalendarDays class="size-4 shrink-0" />
                                            <ChevronDown class="size-3.5 opacity-60" />
                                        </div>
                                    </button>
                                </div>

                                <!-- Calendar Teleport -->
                                <Teleport to="body">
                                    <div
                                        v-if="showCalendar"
                                        class="fixed inset-0 z-[9998]"
                                        @click="showCalendar = false"
                                    />
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
                                            class="fixed z-[9999] animate-in overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl duration-100 fade-in-50 zoom-in-95 dark:border-slate-700 dark:bg-slate-900"
                                            :style="{
                                                top: calPos.top + 'px',
                                                left: calPos.left + 'px',
                                                width: calPos.width + 'px',
                                            }"
                                        >
                                            <!-- Header -->
                                            <div
                                                class="flex items-center justify-between border-b border-slate-100 bg-slate-50/80 px-3 py-2.5 dark:border-slate-800 dark:bg-slate-800/80"
                                            >
                                                <button
                                                    type="button"
                                                    @click="prevMonth"
                                                    class="flex cursor-pointer items-center justify-center rounded-lg p-1.5 text-slate-600 hover:bg-slate-200 hover:text-slate-900 dark:text-slate-300 dark:hover:bg-slate-700 dark:hover:text-white"
                                                >
                                                    <ChevronLeft class="size-4" />
                                                </button>

                                                <!-- Click để mở month picker -->
                                                <button
                                                    type="button"
                                                    @click="
                                                        showMonthPicker =
                                                            !showMonthPicker
                                                    "
                                                    class="flex cursor-pointer items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-bold text-slate-800 transition hover:bg-slate-200/70 dark:text-slate-100 dark:hover:bg-slate-700/70"
                                                >
                                                    <span>{{
                                                        viMonths[calView.month]
                                                    }}</span>
                                                    <span
                                                        class="font-extrabold text-indigo-600 dark:text-indigo-400"
                                                        >{{
                                                            calView.year
                                                        }}</span
                                                    >
                                                    <ChevronDown
                                                        class="size-3.5 text-slate-500 transition-transform"
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
                                                    :disabled="
                                                        isNextMonthDisabled
                                                    "
                                                    class="flex cursor-pointer items-center justify-center rounded-lg p-1.5 text-slate-600 hover:bg-slate-200 hover:text-slate-900 disabled:cursor-not-allowed disabled:opacity-25 dark:text-slate-300 dark:hover:bg-slate-700 dark:hover:text-white"
                                                >
                                                    <ChevronRight class="size-4" />
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
                                                    class="border-b border-slate-100 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-800/90"
                                                >
                                                    <!-- Year nav -->
                                                    <div
                                                        class="mb-2.5 flex items-center justify-between px-1"
                                                    >
                                                        <button
                                                            type="button"
                                                            @click="prevYear"
                                                            class="flex cursor-pointer items-center justify-center rounded-md p-1 text-slate-600 hover:bg-slate-200 dark:text-slate-300 dark:hover:bg-slate-700"
                                                        >
                                                            <ChevronLeft class="size-3.5" />
                                                        </button>
                                                        <span
                                                            class="text-xs font-extrabold text-indigo-600 dark:text-indigo-400"
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
                                                            class="flex cursor-pointer items-center justify-center rounded-md p-1 text-slate-600 hover:bg-slate-200 disabled:cursor-not-allowed disabled:opacity-25 dark:text-slate-300 dark:hover:bg-slate-700"
                                                        >
                                                            <ChevronRight class="size-3.5" />
                                                        </button>
                                                    </div>
                                                    <!-- 12 month grid -->
                                                    <div
                                                        class="grid grid-cols-4 gap-1.5"
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
                                                                isMonthFuture(
                                                                    idx,
                                                                )
                                                            "
                                                            class="rounded-lg py-1.5 text-xs font-semibold transition-all"
                                                            :class="[
                                                                calView.month ===
                                                                    idx &&
                                                                !isMonthFuture(
                                                                    idx,
                                                                )
                                                                    ? 'bg-indigo-600 font-bold text-white shadow-sm'
                                                                    : '',
                                                                !isMonthFuture(
                                                                    idx,
                                                                ) &&
                                                                calView.month !==
                                                                    idx
                                                                    ? 'cursor-pointer text-slate-700 hover:bg-indigo-50 hover:text-indigo-600 dark:text-slate-200 dark:hover:bg-indigo-950/60 dark:hover:text-indigo-400'
                                                                    : '',
                                                                isMonthFuture(
                                                                    idx,
                                                                )
                                                                    ? 'cursor-not-allowed text-slate-400/30 dark:text-slate-600/30'
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
                                                class="grid grid-cols-7 border-b border-slate-100 bg-slate-50/50 px-2 py-1.5 dark:border-slate-800 dark:bg-slate-800/40"
                                            >
                                                <div
                                                    v-for="d in viDays"
                                                    :key="d"
                                                    class="text-center text-[11px] font-bold tracking-wider"
                                                    :class="
                                                        d === 'CN'
                                                            ? 'font-extrabold text-rose-500'
                                                            : 'text-slate-500 dark:text-slate-400'
                                                    "
                                                >
                                                    {{ d }}
                                                </div>
                                            </div>

                                            <!-- Day grid -->
                                            <div
                                                class="grid grid-cols-7 gap-1 bg-white p-2.5 dark:bg-slate-900"
                                            >
                                                <button
                                                    v-for="day in calDays"
                                                    :key="day.date"
                                                    type="button"
                                                    @click="selectDate(day)"
                                                    :disabled="day.isFuture"
                                                    class="relative flex h-8 w-full items-center justify-center rounded-lg text-xs font-semibold transition-all sm:h-9"
                                                    :class="[
                                                        day.isSelected
                                                            ? 'scale-105 bg-indigo-600 font-extrabold text-white shadow-md shadow-indigo-500/40 ring-2 ring-indigo-400'
                                                            : '',
                                                        day.isToday &&
                                                        !day.isSelected
                                                            ? 'border-2 border-indigo-500 font-bold text-indigo-600 dark:text-indigo-400'
                                                            : '',
                                                        day.inMonth &&
                                                        !day.isSelected &&
                                                        !day.isToday &&
                                                        !day.isFuture
                                                            ? 'cursor-pointer text-slate-800 hover:bg-indigo-50 hover:text-indigo-600 dark:text-slate-100 dark:hover:bg-indigo-950/60 dark:hover:text-indigo-400'
                                                            : '',
                                                        !day.inMonth &&
                                                        !day.isFuture
                                                            ? 'cursor-pointer text-slate-400/40 hover:text-slate-600 dark:text-slate-600/50 dark:hover:text-slate-400'
                                                            : '',
                                                        day.isFuture
                                                            ? 'pointer-events-none cursor-not-allowed text-slate-300/30 dark:text-slate-700/30'
                                                            : '',
                                                    ]"
                                                >
                                                    {{ day.day }}
                                                    <span
                                                        v-if="
                                                            day.isToday &&
                                                            !day.isSelected
                                                        "
                                                        class="absolute bottom-1 left-1/2 h-1 w-1 -translate-x-1/2 rounded-full bg-indigo-500"
                                                    />
                                                </button>
                                            </div>

                                            <!-- Footer: Hôm nay & Hôm qua & Đóng -->
                                            <div
                                                class="flex items-center justify-between border-t border-slate-100 bg-slate-50/80 px-2.5 py-2 dark:border-slate-800 dark:bg-slate-800/80"
                                            >
                                                <div class="flex items-center gap-1">
                                                    <button
                                                        type="button"
                                                        @click="setQuickDate(todayStr)"
                                                        class="cursor-pointer rounded-md bg-indigo-50 px-2 py-1 text-[11px] font-bold text-indigo-600 transition hover:bg-indigo-600 hover:text-white dark:bg-indigo-950/60 dark:text-indigo-400 dark:hover:bg-indigo-600 dark:hover:text-white"
                                                    >
                                                        Hôm nay
                                                    </button>
                                                    <button
                                                        type="button"
                                                        @click="setQuickDate(yesterdayStr)"
                                                        class="cursor-pointer rounded-md bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-700 transition hover:bg-indigo-600 hover:text-white dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-indigo-600 dark:hover:text-white"
                                                    >
                                                        Hôm qua
                                                    </button>
                                                </div>
                                                <button
                                                    type="button"
                                                    @click="showCalendar = false"
                                                    class="cursor-pointer rounded-md px-2 py-1 text-[11px] font-medium text-slate-500 hover:text-slate-800 dark:hover:text-slate-200"
                                                >
                                                    Đóng
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
                                        >Ca qua đêm — kết thúc vào rạng sáng
                                        ngày hôm sau. Hệ thống sẽ tự động tổng
                                        hợp đúng khung giờ.</span
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
                            <!-- Phiếu tổng duy nhất của ca đang chọn -->
                            <div
                                class="overflow-hidden rounded-xl border border-slate-200 bg-white text-slate-800 shadow-sm dark:border-slate-800 dark:bg-slate-900 dark:text-slate-100"
                            >
                                <div
                                    class="border-b border-slate-200 bg-slate-50/80 px-4 py-3.5 sm:px-5 dark:border-slate-800 dark:bg-slate-800/60"
                                >
                                    <div
                                        class="flex flex-col justify-between gap-3 sm:flex-row sm:items-start"
                                    >
                                        <div>
                                            <p
                                                class="flex items-center gap-1.5 text-[11px] font-bold tracking-wider text-indigo-600 uppercase dark:text-indigo-400"
                                            >
                                                <Store class="size-3.5 shrink-0" />
                                                {{ restaurantName }} ·
                                                {{ activeBranchName }}
                                            </p>
                                            <h2
                                                class="mt-1 text-xl font-black tracking-wide text-slate-900 dark:text-white"
                                            >
                                                PHIẾU CHỐT CA
                                            </h2>
                                            <p
                                                class="text-xs text-slate-500 dark:text-slate-400"
                                            >
                                                Tổng hợp từ lúc bắt đầu ca đến
                                                thời điểm bấm chốt
                                            </p>
                                        </div>
                                        <div
                                            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs leading-5 shadow-2xs dark:border-slate-700 dark:bg-slate-950"
                                        >
                                            <p class="text-slate-600 dark:text-slate-400">
                                                Ngày:
                                                <strong class="text-slate-900 dark:text-slate-100">{{
                                                    form.closing_date
                                                }}</strong>
                                            </p>
                                            <p class="text-slate-600 dark:text-slate-400">
                                                Ca:
                                                <strong class="text-indigo-600 dark:text-indigo-400">{{
                                                    previewData.shift_name
                                                }}</strong>
                                            </p>
                                            <p class="text-slate-600 dark:text-slate-400">
                                                Thời gian:
                                                <strong class="text-slate-900 dark:text-slate-100">{{
                                                    previewData.end_time
                                                }}</strong>
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div class="grid gap-px bg-slate-200 dark:bg-slate-800 sm:grid-cols-2 lg:grid-cols-4">
                                    <div class="bg-white p-3.5 dark:bg-slate-900">
                                        <p
                                            class="text-[10px] font-bold tracking-wider text-slate-400 uppercase dark:text-slate-500"
                                        >
                                            1. NGÀY / CA
                                        </p>
                                        <p class="mt-1 text-xs font-bold text-slate-800 dark:text-slate-200">
                                            {{ previewData.start_time }} →
                                            {{ previewData.end_time }}
                                        </p>
                                    </div>
                                    <div class="bg-white p-3.5 dark:bg-slate-900">
                                        <p
                                            class="text-[10px] font-bold tracking-wider text-slate-400 uppercase dark:text-slate-500"
                                        >
                                            2. KHU VỰC
                                        </p>
                                        <p class="mt-1 text-xs font-bold text-slate-800 dark:text-slate-200">
                                            Toàn bộ khu vực
                                        </p>
                                    </div>
                                    <div class="bg-white p-3.5 dark:bg-slate-900">
                                        <p
                                            class="text-[10px] font-bold tracking-wider text-slate-400 uppercase dark:text-slate-500"
                                        >
                                            3. ĐƠN VÀO
                                        </p>
                                        <p class="mt-1 text-xs font-bold text-slate-800 dark:text-slate-200">
                                            {{
                                                previewData.total_order_count
                                            }}
                                            đơn vào ·
                                            <span class="text-emerald-600 dark:text-emerald-400">{{ previewData.order_count }} hoàn tất</span>
                                        </p>
                                    </div>
                                    <div class="bg-white p-3.5 dark:bg-slate-900">
                                        <p
                                            class="text-[10px] font-bold tracking-wider text-slate-400 uppercase dark:text-slate-500"
                                        >
                                            4. THANH TOÁN TIỀN MẶT
                                        </p>
                                        <p class="mt-1 text-xs font-extrabold text-indigo-600 dark:text-indigo-400">
                                            {{
                                                previewData.cash_order_count
                                            }}
                                            đơn ·
                                            {{
                                                vnd(
                                                    previewData.cash_sales_amount,
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <div class="bg-white p-3.5 dark:bg-slate-900">
                                        <p
                                            class="text-[10px] font-bold tracking-wider text-slate-400 uppercase dark:text-slate-500"
                                        >
                                            5. THANH TOÁN CHUYỂN KHOẢN
                                        </p>
                                        <p class="mt-1 text-xs font-extrabold text-sky-600 dark:text-sky-400">
                                            {{
                                                previewData.transfer_order_count
                                            }}
                                            đơn ·
                                            {{
                                                vnd(previewData.transfer_amount)
                                            }}
                                        </p>
                                    </div>
                                    <div class="bg-white p-3.5 dark:bg-slate-900">
                                        <p
                                            class="text-[10px] font-bold tracking-wider text-slate-400 uppercase dark:text-slate-500"
                                        >
                                            6. ĐƠN HỦY / HOÀN TIỀN
                                        </p>
                                        <p class="mt-1 text-xs font-bold text-rose-500 dark:text-rose-400">
                                            Hủy
                                            {{
                                                previewData.cancelled_order_count
                                            }}
                                            đơn ({{
                                                vnd(
                                                    previewData.cancelled_total_amount,
                                                )
                                            }})
                                            <span class="text-slate-300 dark:text-slate-600"
                                                >·</span
                                            >
                                            Hoàn
                                            {{
                                                previewData.refunded_order_count
                                            }}
                                            đơn
                                        </p>
                                    </div>
                                    <div class="bg-white p-3.5 dark:bg-slate-900">
                                        <p
                                            class="text-[10px] font-bold tracking-wider text-slate-400 uppercase dark:text-slate-500"
                                        >
                                            7. TỔNG DOANH THU THUẦN
                                        </p>
                                        <p
                                            class="mt-1 text-sm font-black text-emerald-600 dark:text-emerald-400"
                                        >
                                            {{ vnd(previewData.net_revenue) }}
                                        </p>
                                    </div>
                                    <div class="bg-white p-3.5 dark:bg-slate-900">
                                        <p
                                            class="text-[10px] font-bold tracking-wider text-slate-400 uppercase dark:text-slate-500"
                                        >
                                            8. KỲ VỌNG KÉT TIỀN MẶT
                                        </p>
                                        <p
                                            class="mt-1 text-sm font-black text-indigo-600 dark:text-indigo-400"
                                        >
                                            {{ vnd(previewData.expected_cash) }}
                                        </p>
                                    </div>
                                </div>

                                <div class="border-t border-slate-200 bg-slate-50/80 px-4 py-2.5 text-xs text-slate-600 dark:border-slate-800 dark:bg-slate-800/40 dark:text-slate-400">
                                    <div
                                        class="flex justify-between gap-3 font-semibold"
                                    >
                                        <span>Khung giờ chốt: {{ previewData.start_time }} → {{ previewData.end_time }}</span>
                                        <span v-if="previewData.discount_total > 0" class="text-rose-500 dark:text-rose-400"
                                            >Giảm giá: -{{
                                                vnd(previewData.discount_total)
                                            }}</span
                                        >
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                                <!-- Box 1: Đối soát tiền thực nhận -->
                                <div
                                    class="rounded-xl border border-indigo-500/25 bg-indigo-50/40 p-4.5 shadow-2xs dark:border-indigo-900/40 dark:bg-slate-900"
                                >
                                    <div class="flex items-center gap-2">
                                        <div class="flex h-6 w-6 items-center justify-center rounded-md bg-indigo-100 text-indigo-700 dark:bg-indigo-950 dark:text-indigo-400">
                                            <Wallet class="size-3.5" />
                                        </div>
                                        <p
                                            class="text-xs font-black tracking-wider text-indigo-700 uppercase dark:text-indigo-400"
                                        >
                                            Đối soát tiền thực nhận
                                        </p>
                                    </div>
                                    <div class="mt-3.5 grid gap-3 sm:grid-cols-2">
                                        <div>
                                            <Label
                                                class="text-xs font-bold text-slate-700 dark:text-slate-200"
                                                >Tiền mặt thực nhận
                                                <span class="text-rose-500"
                                                    >*</span
                                                ></Label
                                            >
                                            <Input
                                                v-model.number="
                                                    form.actual_cash
                                                "
                                                type="number"
                                                min="0"
                                                step="1000"
                                                class="mt-1.5 h-10 border-slate-300 bg-white font-bold text-slate-900 shadow-2xs focus:ring-2 focus:ring-indigo-500/30 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                                            />
                                            <p
                                                class="mt-1.5 text-[11px] font-medium text-slate-500 dark:text-slate-400"
                                            >
                                                Kỳ vọng:
                                                <strong class="text-slate-800 dark:text-slate-200">{{
                                                    vnd(
                                                        previewData.expected_cash,
                                                    )
                                                }}</strong>
                                            </p>
                                        </div>
                                        <div>
                                            <Label
                                                class="text-xs font-bold text-slate-700 dark:text-slate-200"
                                                >Chuyển khoản thực nhận
                                                <span class="text-rose-500"
                                                    >*</span
                                                ></Label
                                            >
                                            <Input
                                                v-model.number="
                                                    form.actual_transfer_amount
                                                "
                                                type="number"
                                                min="0"
                                                step="1000"
                                                class="mt-1.5 h-10 border-slate-300 bg-white font-bold text-slate-900 shadow-2xs focus:ring-2 focus:ring-indigo-500/30 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                                            />
                                            <p
                                                class="mt-1.5 text-[11px] font-medium text-slate-500 dark:text-slate-400"
                                            >
                                                Kỳ vọng:
                                                <strong class="text-slate-800 dark:text-slate-200">{{
                                                    vnd(
                                                        previewData.transfer_amount,
                                                    )
                                                }}</strong>
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Box 2: Quy trách nhiệm tính lương -->
                                <div
                                    class="rounded-xl border border-amber-500/25 bg-amber-50/40 p-4.5 shadow-2xs dark:border-amber-900/40 dark:bg-slate-900"
                                >
                                    <div class="flex items-center gap-2">
                                        <div class="flex h-6 w-6 items-center justify-center rounded-md bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-400">
                                            <ShieldCheck class="size-3.5" />
                                        </div>
                                        <p
                                            class="text-xs font-black tracking-wider text-amber-700 uppercase dark:text-amber-400"
                                        >
                                            Quy trách nhiệm tính lương
                                        </p>
                                    </div>
                                    <Input
                                        v-model.number="
                                            form.responsibility_amount
                                        "
                                        @input="responsibilityAuto = false"
                                        type="number"
                                        step="1000"
                                        class="mt-3.5 h-10 border-slate-300 bg-white font-black text-slate-900 shadow-2xs focus:ring-2 focus:ring-amber-500/30 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                                    />
                                    <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">
                                        Số âm sẽ trừ lương, số dương sẽ cộng
                                        lương. Mặc định theo tổng chênh lệch.
                                    </p>
                                    <div
                                        class="mt-3 space-y-1.5 border-t border-slate-200 pt-2.5 text-xs dark:border-slate-800"
                                    >
                                        <div class="flex justify-between text-slate-600 dark:text-slate-400">
                                            <span>Lệch tiền mặt</span>
                                            <strong
                                                :class="cashDifference >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'"
                                                >{{
                                                    cashDifference >= 0
                                                        ? '+'
                                                        : ''
                                                }}{{
                                                    vnd(cashDifference)
                                                }}</strong>
                                        </div>
                                        <div class="flex justify-between text-slate-600 dark:text-slate-400">
                                            <span>Lệch chuyển khoản</span>
                                            <strong
                                                :class="transferDifference >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'"
                                                >{{
                                                    transferDifference >= 0
                                                        ? '+'
                                                        : ''
                                                }}{{
                                                    vnd(transferDifference)
                                                }}</strong>
                                        </div>
                                        <div
                                            class="flex items-center justify-between border-t border-slate-200 pt-2 text-xs font-black dark:border-slate-800"
                                        >
                                            <span class="text-slate-800 dark:text-slate-200">Tổng chênh lệch</span>
                                            <span
                                                class="rounded-md px-2 py-0.5 font-extrabold"
                                                :class="[
                                                    totalDifference === 0
                                                        ? 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'
                                                        : '',
                                                    totalDifference > 0
                                                        ? 'bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800'
                                                        : '',
                                                    totalDifference < 0
                                                        ? 'bg-rose-50 text-rose-700 border border-rose-200 dark:bg-rose-950/60 dark:text-rose-300 dark:border-rose-800'
                                                        : '',
                                                ]"
                                                >{{
                                                    totalDifference >= 0
                                                        ? '+'
                                                        : ''
                                                }}{{
                                                    vnd(totalDifference)
                                                }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                                <div>
                                    <Label
                                        class="text-xs font-bold text-slate-700 uppercase tracking-wider dark:text-slate-300"
                                        >Ghi chú quy trách nhiệm</Label
                                    >
                                    <textarea
                                        v-model="form.responsibility_note"
                                        rows="2"
                                        maxlength="1000"
                                        placeholder="Ví dụ: Thiếu tiền mặt do... / Dư chuyển khoản do..."
                                        class="mt-1.5 w-full resize-none rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-medium text-slate-800 placeholder:text-slate-400 shadow-2xs focus-visible:ring-2 focus-visible:ring-indigo-500/30 focus-visible:outline-none dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                                    />
                                </div>
                                <div>
                                    <Label
                                        class="text-xs font-bold text-slate-700 uppercase tracking-wider dark:text-slate-300"
                                        >Ghi chú vận hành ca</Label
                                    >
                                    <textarea
                                        v-model="form.notes"
                                        rows="2"
                                        maxlength="1000"
                                        placeholder="Ghi chú thêm cho phiếu chốt ca..."
                                        class="mt-1.5 w-full resize-none rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-medium text-slate-800 placeholder:text-slate-400 shadow-2xs focus-visible:ring-2 focus-visible:ring-indigo-500/30 focus-visible:outline-none dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100"
                                    />
                                </div>
                            </div>

                            <div v-if="false">
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
                                        {{ previewData.order_count }} đơn hoàn
                                        thành
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
                                            {{
                                                compact(
                                                    previewData.gross_revenue,
                                                )
                                            }}
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
                                        <p
                                            class="text-sm font-extrabold text-rose-500"
                                        >
                                            -{{
                                                compact(
                                                    previewData.discount_total,
                                                )
                                            }}
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
                                            {{
                                                compact(previewData.net_revenue)
                                            }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Area Breakdown Table -->
                                <div
                                    v-if="
                                        previewData.areas_breakdown &&
                                        previewData.areas_breakdown.length > 0
                                    "
                                    class="mb-4 rounded-xl border border-indigo-100 bg-indigo-50/20 p-3.5 dark:border-indigo-900/30 dark:bg-indigo-950/20"
                                >
                                    <p
                                        class="mb-2 flex items-center justify-between text-xs font-bold tracking-wide text-indigo-700 uppercase dark:text-indigo-300"
                                    >
                                        <span
                                            >📍 Báo cáo Chi tiết Chốt ca theo
                                            Khu vực</span
                                        >
                                        <span
                                            class="rounded bg-indigo-100 px-2 py-0.5 text-[10px] font-bold text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200"
                                        >
                                            {{
                                                previewData.areas_breakdown
                                                    .length
                                            }}
                                            khu vực
                                        </span>
                                    </p>
                                    <div
                                        class="overflow-x-auto rounded-lg border border-slate-200/70 bg-white dark:border-slate-800 dark:bg-slate-900"
                                    >
                                        <table class="w-full text-left text-xs">
                                            <thead
                                                class="bg-slate-50 text-[10px] font-bold text-slate-500 uppercase dark:bg-slate-800/60 dark:text-slate-400"
                                            >
                                                <tr>
                                                    <th class="px-3 py-2">
                                                        Khu vực
                                                    </th>
                                                    <th
                                                        class="px-3 py-2 text-center"
                                                    >
                                                        Đơn vào
                                                    </th>
                                                    <th
                                                        class="px-3 py-2 text-right"
                                                    >
                                                        Thanh toán TM
                                                    </th>
                                                    <th
                                                        class="px-3 py-2 text-right"
                                                    >
                                                        Thanh toán CK
                                                    </th>
                                                    <th
                                                        class="px-3 py-2 text-right"
                                                    >
                                                        Đơn hủy
                                                    </th>
                                                    <th
                                                        class="px-3 py-2 text-right"
                                                    >
                                                        Hoàn tiền
                                                    </th>
                                                    <th
                                                        class="px-3 py-2 text-right"
                                                    >
                                                        Tổng doanh thu
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody
                                                class="divide-y divide-slate-100 dark:divide-slate-800"
                                            >
                                                <tr
                                                    v-for="(
                                                        areaItem, idx
                                                    ) in previewData.areas_breakdown"
                                                    :key="idx"
                                                    class="hover:bg-slate-50/50"
                                                >
                                                    <td
                                                        class="px-3 py-2 font-bold text-slate-800 dark:text-slate-200"
                                                    >
                                                        {{ areaItem.area_name }}
                                                    </td>
                                                    <td
                                                        class="px-3 py-2 text-center font-bold text-slate-600 dark:text-slate-400"
                                                    >
                                                        {{
                                                            areaItem.total_order_count ||
                                                            areaItem.order_count
                                                        }}
                                                        đơn
                                                    </td>
                                                    <td
                                                        class="px-3 py-2 text-right font-mono font-bold text-blue-600 dark:text-blue-400"
                                                    >
                                                        <span>{{
                                                            compact(
                                                                areaItem.expected_cash,
                                                            )
                                                        }}</span>
                                                        <div
                                                            class="text-[9px] font-normal text-slate-400"
                                                        >
                                                            ({{
                                                                areaItem.cash_order_count ||
                                                                0
                                                            }}
                                                            đơn)
                                                        </div>
                                                    </td>
                                                    <td
                                                        class="px-3 py-2 text-right font-mono font-bold text-violet-600 dark:text-violet-400"
                                                    >
                                                        <span>{{
                                                            compact(
                                                                areaItem.transfer_amount,
                                                            )
                                                        }}</span>
                                                        <div
                                                            class="text-[9px] font-normal text-slate-400"
                                                        >
                                                            ({{
                                                                areaItem.transfer_order_count ||
                                                                0
                                                            }}
                                                            đơn)
                                                        </div>
                                                    </td>
                                                    <td
                                                        class="px-3 py-2 text-right font-mono font-bold text-rose-500"
                                                    >
                                                        <span>{{
                                                            compact(
                                                                areaItem.cancelled_total_amount ||
                                                                    0,
                                                            )
                                                        }}</span>
                                                        <div
                                                            class="text-[9px] font-normal text-slate-400"
                                                        >
                                                            ({{
                                                                areaItem.cancelled_order_count ||
                                                                0
                                                            }}
                                                            đơn)
                                                        </div>
                                                    </td>
                                                    <td
                                                        class="px-3 py-2 text-right font-mono font-bold text-amber-600"
                                                    >
                                                        <span>{{
                                                            compact(
                                                                areaItem.refunded_total_amount ||
                                                                    0,
                                                            )
                                                        }}</span>
                                                        <div
                                                            class="text-[9px] font-normal text-slate-400"
                                                        >
                                                            ({{
                                                                areaItem.refunded_order_count ||
                                                                0
                                                            }}
                                                            đơn)
                                                        </div>
                                                    </td>
                                                    <td
                                                        class="px-3 py-2 text-right font-mono font-black text-emerald-600 dark:text-emerald-400"
                                                    >
                                                        <span>{{
                                                            compact(
                                                                areaItem.gross_revenue,
                                                            )
                                                        }}</span>
                                                        <div
                                                            class="text-[9px] font-normal text-slate-400"
                                                        >
                                                            (TM+CK-Hoàn)
                                                        </div>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
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
                                            <span
                                                class="flex items-center gap-2"
                                            >
                                                <span
                                                    class="inline-block h-2 w-2 rounded-full bg-blue-500"
                                                ></span>
                                                <span
                                                    class="font-semibold text-slate-500"
                                                    >Tiền mặt sổ sách (kỳ
                                                    vọng)</span
                                                >
                                            </span>
                                            <span
                                                class="font-mono font-bold text-blue-600 dark:text-blue-400"
                                                >{{
                                                    vnd(
                                                        previewData.expected_cash,
                                                    )
                                                }}</span
                                            >
                                        </div>
                                        <div
                                            v-if="previewData.bank_transfer > 0"
                                            class="flex items-center justify-between text-xs"
                                        >
                                            <span
                                                class="flex items-center gap-2"
                                            >
                                                <span
                                                    class="inline-block h-2 w-2 rounded-full bg-violet-500"
                                                ></span>
                                                <span
                                                    class="font-semibold text-slate-500"
                                                    >Chuyển khoản / Quét
                                                    QR</span
                                                >
                                            </span>
                                            <span
                                                class="font-mono font-bold text-violet-600 dark:text-violet-400"
                                                >{{
                                                    vnd(
                                                        previewData.bank_transfer,
                                                    )
                                                }}</span
                                            >
                                        </div>
                                        <div
                                            v-if="previewData.card > 0"
                                            class="flex items-center justify-between text-xs"
                                        >
                                            <span
                                                class="flex items-center gap-2"
                                            >
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
                                                >{{
                                                    vnd(previewData.card)
                                                }}</span
                                            >
                                        </div>
                                        <div
                                            v-if="previewData.ewallet > 0"
                                            class="flex items-center justify-between text-xs"
                                        >
                                            <span
                                                class="flex items-center gap-2"
                                            >
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
                                                >{{
                                                    vnd(previewData.ewallet)
                                                }}</span
                                            >
                                        </div>
                                        <div
                                            v-if="previewData.mixed > 0"
                                            class="flex items-center justify-between text-xs"
                                        >
                                            <span
                                                class="flex items-center gap-2"
                                            >
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
                                                >{{
                                                    vnd(previewData.mixed)
                                                }}</span
                                            >
                                        </div>
                                        <div
                                            class="flex items-center justify-between border-t border-slate-100 pt-2 text-xs font-bold text-slate-700 dark:border-slate-800 dark:text-slate-300"
                                        >
                                            <span
                                                >Tổng doanh thu (TM + CK)</span
                                            >
                                            <span class="font-mono">{{
                                                vnd(
                                                    (previewData.expected_cash ??
                                                        0) +
                                                        previewData.transfer_amount,
                                                )
                                            }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Warnings -->
                                <div
                                    v-if="previewData.already_closed"
                                    class="mb-4 flex items-start gap-2.5 rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs text-rose-700 dark:border-rose-900 dark:bg-rose-900/20 dark:text-rose-300"
                                >
                                    <TriangleAlert
                                        class="mt-0.5 size-4 shrink-0"
                                    />
                                    <span
                                        >Ca
                                        <strong>{{
                                            previewData.shift_name
                                        }}</strong>
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
                                        đơn chưa hoàn tất trong ca trực. Dữ liệu
                                        các đơn này sẽ tạm thời không được cộng
                                        vào tổng doanh thu chốt ca.</span
                                    >
                                </div>

                                <!-- Cash Register Reconciliation Details -->
                                <div
                                    v-if="previewData.has_register"
                                    class="mb-4 space-y-2 rounded-xl border border-indigo-100 bg-indigo-50/10 p-3.5 text-xs"
                                >
                                    <p
                                        class="flex items-center gap-1 font-bold text-indigo-700"
                                    >
                                        <Wallet class="size-3.5" /> Đối soát két
                                        tiền mặt đầu/cuối ca
                                    </p>
                                    <div
                                        class="text-slate-650 space-y-1.5 font-semibold"
                                    >
                                        <div class="flex justify-between">
                                            <span>1. Số dư két mở đầu ca:</span>
                                            <span
                                                class="font-mono text-slate-700"
                                                >{{
                                                    vnd(
                                                        previewData.opening_balance ||
                                                            0,
                                                    )
                                                }}</span
                                            >
                                        </div>
                                        <div class="flex justify-between">
                                            <span
                                                >2. Doanh thu tiền mặt từ đơn
                                                hàng:</span
                                            >
                                            <span
                                                class="font-mono text-emerald-600"
                                                >+{{
                                                    vnd(
                                                        (previewData.expected_cash ??
                                                            0) -
                                                            (previewData.opening_balance ||
                                                                0) -
                                                            (previewData.other_cash_in ||
                                                                0) +
                                                            (previewData.other_cash_out ||
                                                                0),
                                                    )
                                                }}</span
                                            >
                                        </div>
                                        <div
                                            v-if="
                                                (previewData.other_cash_in ??
                                                    0) > 0
                                            "
                                            class="flex justify-between"
                                        >
                                            <span>3. Các khoản thu khác:</span>
                                            <span
                                                class="font-mono text-emerald-600"
                                                >+{{
                                                    vnd(
                                                        previewData.other_cash_in ??
                                                            0,
                                                    )
                                                }}</span
                                            >
                                        </div>
                                        <div
                                            v-if="
                                                (previewData.other_cash_out ??
                                                    0) > 0
                                            "
                                            class="flex justify-between text-rose-600"
                                        >
                                            <span
                                                >4. Các khoản chi ngoài (đi
                                                chợ/sửa chữa):</span
                                            >
                                            <span class="font-mono"
                                                >-{{
                                                    vnd(
                                                        previewData.other_cash_out ??
                                                            0,
                                                    )
                                                }}</span
                                            >
                                        </div>
                                        <div
                                            class="flex justify-between border-t pt-2 font-bold text-indigo-700"
                                        >
                                            <span
                                                >Kỳ vọng thực tế trong két
                                                (1+2+3-4):</span
                                            >
                                            <span class="font-mono text-sm">{{
                                                vnd(previewData.expected_cash)
                                            }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div
                                    v-else
                                    class="border-amber-250 mb-4 flex items-start gap-2.5 rounded-xl border bg-amber-50 p-3.5 text-xs text-amber-700"
                                >
                                    <AlertTriangle
                                        class="mt-0.5 size-4 shrink-0 text-amber-600"
                                    />
                                    <span
                                        ><strong>Lưu ý:</strong> Ca trực này
                                        chưa được mở két đầu ca trong hệ thống
                                        Quản lý Dòng tiền. Dữ liệu đối soát kì
                                        vọng sẽ tạm thời tính từ doanh thu đơn
                                        hàng thanh toán tiền mặt với số dư ban
                                        đầu mặc định là 0đ.</span
                                    >
                                </div>

                                <!-- Đếm tiền mù: nhập số tờ trước, hệ thống lộ số kỳ vọng sau -->
                                <div
                                    v-if="needsBlindCount"
                                    class="space-y-3 rounded-xl border border-indigo-200 bg-indigo-50/40 p-4 dark:border-indigo-900 dark:bg-indigo-950/20"
                                >
                                    <div class="flex items-start gap-2">
                                        <AlertTriangle
                                            class="mt-0.5 size-4 shrink-0 text-indigo-600"
                                        />
                                        <p
                                            class="text-xs font-semibold text-indigo-800 dark:text-indigo-300"
                                        >
                                            Đếm tiền trong két theo từng mệnh
                                            giá. Số tiền hệ thống kỳ vọng chỉ
                                            hiện ra sau khi bạn nộp phiếu đếm.
                                        </p>
                                    </div>

                                    <div
                                        class="grid grid-cols-2 gap-2 sm:grid-cols-3"
                                    >
                                        <div
                                            v-for="d in DENOMINATIONS"
                                            :key="d"
                                            class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 dark:border-slate-700 dark:bg-slate-900"
                                        >
                                            <span
                                                class="w-16 shrink-0 font-mono text-[11px] font-bold text-slate-500 tabular-nums"
                                                >{{ compact(d) }}</span
                                            >
                                            <Input
                                                v-model.number="
                                                    denominationCounts[d]
                                                "
                                                type="number"
                                                min="0"
                                                step="1"
                                                placeholder="0"
                                                class="h-8 text-xs font-bold"
                                            />
                                        </div>
                                    </div>

                                    <div
                                        class="flex items-center justify-between border-t border-indigo-200 pt-3 dark:border-indigo-900"
                                    >
                                        <span
                                            class="text-xs font-bold tracking-wide text-slate-500 uppercase"
                                            >Tổng đếm được</span
                                        >
                                        <span
                                            class="font-mono text-base font-black text-slate-900 tabular-nums dark:text-slate-100"
                                            >{{ vnd(countedTotal) }}</span
                                        >
                                    </div>

                                    <p
                                        v-if="countError"
                                        class="text-xs font-semibold text-rose-500"
                                    >
                                        {{ countError }}
                                    </p>

                                    <Button
                                        type="button"
                                        class="w-full"
                                        :disabled="
                                            countSubmitting || countedTotal <= 0
                                        "
                                        @click="submitCount()"
                                    >
                                        {{
                                            countSubmitting
                                                ? 'Đang ghi nhận...'
                                                : 'Chốt số đếm & xem đối chiếu'
                                        }}
                                    </Button>
                                </div>

                                <!-- Input actual cash -->
                                <div v-else class="space-y-4">
                                    <div class="flex flex-col space-y-1.5">
                                        <Label
                                            class="text-xs font-bold tracking-wide text-slate-500 uppercase"
                                            >Tiền mặt thực tế trong két tiền
                                            <span class="text-rose-500"
                                                >*</span
                                            ></Label
                                        >
                                        <div class="relative mt-1.5">
                                            <span
                                                class="absolute top-1/2 left-3 -translate-y-1/2 font-mono text-xs font-bold text-slate-400"
                                                >₫</span
                                            >
                                            <Input
                                                v-model.number="
                                                    form.actual_cash
                                                "
                                                type="number"
                                                min="0"
                                                step="1000"
                                                :readonly="
                                                    form.cash_count_id !== null
                                                "
                                                class="h-9 pl-8 text-xs font-bold"
                                                :class="{
                                                    'border-rose-400 focus-visible:ring-rose-400/20':
                                                        form.errors.actual_cash,
                                                    'bg-slate-50 dark:bg-slate-900':
                                                        form.cash_count_id !==
                                                        null,
                                                }"
                                            />
                                        </div>
                                        <p
                                            v-if="form.cash_count_id !== null"
                                            class="mt-1 text-[11px] font-medium text-slate-400"
                                        >
                                            Số này lấy từ phiếu đếm đã nộp nên
                                            không sửa được. Cần sửa thì đếm lại.
                                        </p>
                                        <p
                                            v-if="form.errors.actual_cash"
                                            class="mt-1 text-xs font-semibold text-rose-500"
                                        >
                                            {{ form.errors.actual_cash }}
                                        </p>
                                    </div>

                                    <!-- Giải trình chênh lệch: bắt buộc khi vượt ngưỡng -->
                                    <div
                                        v-if="varianceNeedsExplanation"
                                        class="flex flex-col space-y-1.5"
                                    >
                                        <Label
                                            class="text-xs font-bold tracking-wide text-amber-600 uppercase"
                                            >Giải trình chênh lệch
                                            <span class="text-rose-500"
                                                >*</span
                                            ></Label
                                        >
                                        <textarea
                                            v-model="form.variance_explanation"
                                            rows="2"
                                            placeholder="Vì sao két lệch so với doanh thu ghi nhận?"
                                            class="mt-1 w-full rounded-lg border border-amber-300 bg-amber-50/40 px-3 py-2 text-xs font-medium dark:border-amber-900 dark:bg-amber-950/20"
                                        ></textarea>
                                        <p
                                            v-if="
                                                form.errors.variance_explanation
                                            "
                                            class="text-xs font-semibold text-rose-500"
                                        >
                                            {{
                                                form.errors.variance_explanation
                                            }}
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
                                        <span
                                            class="font-mono text-sm font-black"
                                        >
                                            {{ cashDifference >= 0 ? '+' : ''
                                            }}{{ vnd(cashDifference) }}
                                        </span>
                                    </div>

                                    <!-- Cảnh báo cấn trừ lương khi lệch âm quỹ -->
                                    <div
                                        v-if="cashDifference < 0"
                                        class="border-rose-250 rounded-xl border bg-rose-50/30 p-3 text-xs font-medium text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/20 dark:text-rose-400"
                                    >
                                        <div class="flex items-start gap-2">
                                            <AlertTriangle
                                                class="mt-0.5 size-4 shrink-0 text-rose-500"
                                            />
                                            <span>
                                                <strong
                                                    >Lưu ý vi phạm tài
                                                    chính:</strong
                                                >
                                                Số tiền mặt đếm két thực tế đang
                                                thiếu hụt so với sổ sách là
                                                <strong class="font-mono">{{
                                                    vnd(
                                                        Math.abs(
                                                            cashDifference,
                                                        ),
                                                    )
                                                }}</strong
                                                >. Khoản thiếu hụt này sẽ tự
                                                động đề xuất tạo cấn trừ phạt
                                                trực tiếp vào bảng lương nháp
                                                tháng này của bạn sau khi Quản
                                                lý hoặc Chủ cửa hàng phê duyệt
                                                phiếu chốt ca.
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Chi phí phát sinh -->
                                    <div class="flex flex-col space-y-1.5">
                                        <Label
                                            class="text-xs font-bold tracking-wide text-slate-500 uppercase"
                                            >Chi phí phát sinh trong ca (nếu
                                            có)</Label
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
                                :disabled="
                                    previewLoading ||
                                    !form.shift_id ||
                                    !form.area_id
                                "
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
    </Teleport>

    <!-- ══ Dispute Dialog ════════════════════════════════════════════════════ -->
    <Teleport to="body">
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
    </Teleport>
</template>
