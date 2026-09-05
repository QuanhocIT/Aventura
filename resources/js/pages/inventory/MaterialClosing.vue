<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    ArrowLeft,
    CheckCircle2,
    ClipboardCheck,
    Eye,
    FileText,
    Printer,
    RefreshCw,
    RotateCcw,
    UserPlus,
    X,
    XCircle,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';

import { Badge } from '@/components/ui/badge';
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

interface Ingredient {
    id: number;
    name: string;
    sku?: string;
    average_cost?: number;
    unit?: { symbol?: string; name?: string };
}

interface ClosingItem {
    id: number;
    ingredient_id: number;
    opening_quantity: number;
    inbound_quantity: number;
    outbound_quantity: number;
    inbound_value: number;
    outbound_value: number;
    unit_cost: number;
    expected_quantity: number;
    expected_value: number;
    counted_quantity_1: number | null;
    counted_quantity_2: number | null;
    final_quantity: number | null;
    variance_quantity: number;
    variance_percent: number;
    variance_value: number;
    inventory_status?:
        | 'uncounted'
        | 'recount_required'
        | 'negative_stock'
        | 'shortage'
        | 'surplus'
        | 'matched';
    system_negative?: boolean;
    system_negative_quantity?: number;
    system_negative_value?: number;
    revision?: number;
    reconciliation_status?: string;
    reconciliation_notes?: string | null;
    reconciled_by?: number | null;
    notes?: string | null;
    ingredient?: Ingredient;
}

interface ClosingSession {
    id: number;
    branch_id: number;
    type: string;
    status: string;
    period_start: string;
    period_end: string;
    total_expected_quantity: number;
    total_counted_quantity: number;
    total_expected_value: number;
    total_counted_value: number;
    total_shortage_quantity: number;
    total_surplus_quantity: number;
    total_shortage_value: number;
    total_surplus_value: number;
    total_negative_quantity?: number;
    total_negative_value?: number;
    negative_item_count?: number;
    total_variance_value: number;
    variance_photo_path?: string | null;
    variance_proof_path?: string | null;
    stale_at?: string | null;
    stale_reason?: string | null;
    unit_breakdown?: Record<string, {
        expected_quantity: number;
        counted_quantity: number;
        variance_quantity: number;
    }>;
    counted_by: number;
    second_counted_by?: number | null;
    countedBy?: { id: number; name: string } | null;
    secondCountedBy?: { id: number; name: string } | null;
    approver?: { id: number; name: string } | null;
    items?: ClosingItem[];
    notes?: string | null;
    created_at?: string;
}

interface CounterCandidate {
    id: number;
    name: string;
    email?: string;
    job_title?: string;
}

interface ClosingTask {
    id: number;
    count_session_id: number;
    status: string;
    priority: string;
    due_at?: string | null;
    is_overdue?: boolean;
    assigned_to?: number | null;
    assignee?: { id: number; name: string } | null;
    notes?: string | null;
}

const props = defineProps<{
    mode: 'central' | 'branch';
    branch: { id: number; name: string; code?: string };
    branches?: Array<{ id: number; name: string; code?: string }>;
    selectedBranchId?: number;
    nextPeriodStart?: string | null;
    sessions: ClosingSession[];
    tasks: ClosingTask[];
    counterCandidates: CounterCandidate[];
    authUserId: number;
    canManage: boolean;
    canApprove: boolean;
    isOwnerOrSuperAdmin: boolean;
    isWarehouseStaff: boolean;
    scopeMessage: string;
}>();

const isBranchMode = computed(() => props.mode === 'branch');
const closingTitle = computed(() =>
    isBranchMode.value ? 'Chốt kho chi nhánh' : 'Chốt nguyên liệu Kho Tổng',
);
const closingBaseUrl = computed(() =>
    isBranchMode.value
        ? '/api/inventory/branch-closing'
        : '/api/inventory/central-warehouse/material-closing',
);
const backUrl = computed(() =>
    isBranchMode.value ? '/inventory' : '/inventory/central-warehouse',
);
const backLabel = computed(() =>
    isBranchMode.value ? 'Kho nguyên liệu' : 'Tổng quan Kho Tổng',
);
const branchLabel = computed(() =>
    isBranchMode.value ? 'Chi nhánh' : 'Kho Tổng',
);

const today = new Date().toISOString().slice(0, 10);
const firstOfMonth = new Date(
    new Date().getFullYear(),
    new Date().getMonth(),
    1,
)
    .toISOString()
    .slice(0, 10);

const periodForm = ref({
    from_date: props.nextPeriodStart || firstOfMonth,
    to_date: today,
});
const selectedSession = ref<ClosingSession | null>(null);
const receiptSession = ref<ClosingSession | null>(null);
const showReceiptModal = ref(false);
const showCreate = ref(false);
const showAssign = ref(false);
const isSubmitting = ref(false);
const search = ref('');
const assignForm = ref({
    assigned_to: '',
    priority: 'normal',
    due_at: '',
    notes: '',
});
const selectedBranchId = ref(props.selectedBranchId ?? props.branch.id);

const filteredSessions = computed(() => {
    const query = search.value.trim().toLowerCase();

    return props.sessions.filter((session) => {
        if (!query) {
            return true;
        }

        return (
            `#${session.id}`.includes(query) ||
            formatDateVietnamese(session.period_start).includes(query) ||
            formatDateVietnamese(session.period_end).includes(query) ||
            session.period_start?.includes(query) ||
            session.period_end?.includes(query) ||
            session.status?.toLowerCase().includes(query)
        );
    });
});

function taskFor(sessionId: number) {
    return props.tasks.find((task) => task.count_session_id === sessionId);
}

function formatNumber(value: number | string | null | undefined, digits = 3) {
    return new Intl.NumberFormat('vi-VN', {
        maximumFractionDigits: digits,
    }).format(Number(value || 0));
}

function formatCurrency(value: number | string | null | undefined) {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
    }).format(Number(value || 0));
}

function statusLabel(status: string) {
    if (status === 'stale') {
        return 'Snapshot lỗi thời';
    }

    return (
        {
            in_progress: 'Đang đối chiếu',
            pending_approval: 'Chờ phê duyệt',
            approved: 'Đã phê duyệt',
            rejected: 'Bị từ chối',
            cancelled: 'Đã hủy',
        }[status] || status
    );
}

function statusClass(status: string) {
    if (status === 'approved') {
        return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-400';
    }

    if (status === 'pending_approval') {
        return 'border-sky-500/30 bg-sky-500/10 text-sky-400';
    }

    if (status === 'in_progress') {
        return 'border-amber-500/30 bg-amber-500/10 text-amber-400';
    }

    if (status === 'rejected') {
        return 'border-rose-500/30 bg-rose-500/10 text-rose-400';
    }

    if (status === 'stale') {
        return 'border-orange-500/30 bg-orange-500/10 text-orange-400';
    }

    return 'border-slate-500/30 bg-slate-500/10 text-slate-400';
}

function sessionShortage(session: ClosingSession) {
    return Number(session.total_shortage_value || 0);
}

function parseDateParts(dateStr?: string | null) {
    if (!dateStr) {
        const now = new Date();

        return {
            day: String(now.getDate()).padStart(2, '0'),
            month: String(now.getMonth() + 1).padStart(2, '0'),
            year: String(now.getFullYear()),
        };
    }

    if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
        const [y, m, d] = dateStr.split('-');

        return { day: d, month: m, year: y };
    }

    const d = new Date(dateStr);

    if (isNaN(d.getTime())) {
        return { day: '.....', month: '.....', year: '2026' };
    }

    return {
        day: String(d.getDate()).padStart(2, '0'),
        month: String(d.getMonth() + 1).padStart(2, '0'),
        year: String(d.getFullYear()),
    };
}

function formatDateVietnamese(dateStr?: string | null) {
    if (!dateStr) {
        return '...../...../2026';
    }

    const parts = parseDateParts(dateStr);

    return `${parts.day}/${parts.month}/${parts.year}`;
}

function formatDateTimeVietnamese(dateStr?: string | null) {
    if (!dateStr) {
        return '..... ngày ...../...../2026';
    }

    const d = new Date(dateStr);

    if (isNaN(d.getTime())) {
        return formatDateVietnamese(dateStr);
    }

    const hours = String(d.getHours()).padStart(2, '0');
    const minutes = String(d.getMinutes()).padStart(2, '0');

    return `${hours}:${minutes} ngày ${formatDateVietnamese(dateStr)}`;
}

const receiptTotals = computed(() => {
    const session = receiptSession.value;
    const items = session?.items || [];

    const totalOpeningQty = items.reduce(
        (sum, i) => sum + Number(i.opening_quantity || 0),
        0,
    );
    const totalOpeningVal = items.reduce(
        (sum, i) =>
            sum +
            Number(i.opening_quantity || 0) * Number(i.unit_cost || 0),
        0,
    );

    const totalInboundQty = items.reduce(
        (sum, i) => sum + Number(i.inbound_quantity || 0),
        0,
    );
    const totalInboundVal = items.reduce(
        (sum, i) => sum + Number(i.inbound_value || 0),
        0,
    );

    const totalOutboundQty = items.reduce(
        (sum, i) => sum + Number(i.outbound_quantity || 0),
        0,
    );
    const totalOutboundVal = items.reduce(
        (sum, i) => sum + Number(i.outbound_value || 0),
        0,
    );

    const totalClosingQty = Number(
        session?.total_counted_quantity ?? session?.total_expected_quantity ?? 0,
    );
    const totalClosingVal = Number(
        session?.total_counted_value ?? session?.total_expected_value ?? 0,
    );

    const totalVarianceQty =
        totalClosingQty - (totalOpeningQty + totalInboundQty - totalOutboundQty);
    const totalVarianceVal = Number(session?.total_variance_value || 0);

    return {
        totalOpeningQty,
        totalOpeningVal,
        totalInboundQty,
        totalInboundVal,
        totalOutboundQty,
        totalOutboundVal,
        totalClosingQty,
        totalClosingVal,
        totalVarianceQty,
        totalVarianceVal,
    };
});

const receiptNumber = computed(() => {
    if (!receiptSession.value) {
        return 'PC-KCN/2026/0000';
    }

    const prefix = isBranchMode.value ? 'PC-KCN' : 'PC-KT';

    return `${prefix}/2026/${String(receiptSession.value.id).padStart(4, '0')}`;
});

const receiptDateInfo = computed(() => {
    return parseDateParts(
        receiptSession.value?.period_end || receiptSession.value?.created_at,
    );
});

function openReceipt(session: ClosingSession) {
    receiptSession.value = session;
    selectedSession.value = session;
    showReceiptModal.value = true;
}

function printReceipt() {
    window.print();
}

function openFromQuery() {
    const id = Number(
        new URLSearchParams(window.location.search).get('session'),
    );

    if (id) {
        const session = props.sessions.find((item) => item.id === id);

        if (session) {
            openReceipt(session);
        }
    }
}

async function createClosing() {
    if (!periodForm.value.from_date || !periodForm.value.to_date) {
        toast.error('Vui lòng chọn đủ ngày bắt đầu và ngày kết thúc.');

        return;
    }

    isSubmitting.value = true;

    try {
        const response = await axios.post(closingBaseUrl.value, {
            branch_id: selectedBranchId.value,
            ...periodForm.value,
        });
        toast.success(response.data.message || 'Đã tạo kỳ chốt.');
        showCreate.value = false;
        await router.reload();
    } catch (error: any) {
        toast.error(
            error.response?.data?.message ||
                'Không thể tạo kỳ chốt nguyên liệu.',
        );
    } finally {
        isSubmitting.value = false;
    }
}

function openCreate() {
    periodForm.value = {
        from_date: props.nextPeriodStart || firstOfMonth,
        to_date: today,
    };
    showCreate.value = true;
}

function openAssign(session: ClosingSession) {
    selectedSession.value = session;
    assignForm.value = {
        assigned_to: session.second_counted_by
            ? String(session.second_counted_by)
            : '',
        priority: 'normal',
        due_at: '',
        notes: 'Đối chiếu thực tế và ghi nhận số lượng từng nguyên liệu trong kỳ chốt.',
    };
    showAssign.value = true;
}

async function assignCounter() {
    if (!selectedSession.value || !assignForm.value.assigned_to) {
        toast.error('Vui lòng chọn nhân viên đối chiếu.');

        return;
    }

    isSubmitting.value = true;

    try {
        const response = await axios.post(
            `${closingBaseUrl.value}/${selectedSession.value.id}/assign`,
            assignForm.value,
        );
        toast.success(response.data.message || 'Đã giao việc đối chiếu.');
        showAssign.value = false;
        await router.reload();
    } catch (error: any) {
        toast.error(
            error.response?.data?.message || 'Không thể giao việc đối chiếu.',
        );
    } finally {
        isSubmitting.value = false;
    }
}

async function rejectSession() {
    const session = receiptSession.value || selectedSession.value;

    if (!session) {
        return;
    }

    const reason = window.prompt('Nhập lý do từ chối kỳ chốt:');

    if (reason === null || !reason.trim()) {
        return;
    }

    isSubmitting.value = true;

    try {
        await axios.post(
            `/api/inventory/count-sessions/${session.id}/reject`,
            { reason },
        );
        toast.success('Đã từ chối kỳ chốt.');
        showReceiptModal.value = false;
        await router.reload();
    } catch (error: any) {
        toast.error(error.response?.data?.message || 'Không thể từ chối kỳ chốt.');
    } finally {
        isSubmitting.value = false;
    }
}

async function reopenSession() {
    const session = receiptSession.value || selectedSession.value;

    if (!session) {
        return;
    }

    isSubmitting.value = true;

    try {
        await axios.post(
            `/api/inventory/count-sessions/${session.id}/reopen`,
        );
        toast.success('Đã mở lại kỳ chốt để điều chỉnh.');
        showReceiptModal.value = false;
        await router.reload();
    } catch (error: any) {
        toast.error(error.response?.data?.message || 'Không thể mở lại kỳ chốt.');
    } finally {
        isSubmitting.value = false;
    }
}

async function approveSession() {
    const session = receiptSession.value || selectedSession.value;

    if (
        !session ||
        !window.confirm(
            'Phê duyệt sẽ ghi điều chỉnh thiếu/thừa vào tồn kho. Tiếp tục?',
        )
    ) {
        return;
    }

    let overrideReason: string | null = null;

    if (
        props.isOwnerOrSuperAdmin &&
        (session.counted_by === props.authUserId ||
            session.second_counted_by === props.authUserId ||
            session.items?.some(
                (item) => item.reconciled_by === props.authUserId,
            ))
    ) {
        const reason = window.prompt(
            'Bạn đang phê duyệt kết quả do chính mình kiểm kê. Nhập lý do ngoại lệ:',
        );

        if (reason === null || !reason.trim()) {
            return;
        }

        overrideReason = reason.trim();
    }

    isSubmitting.value = true;

    try {
        const response = await axios.post(
            `/api/inventory/count-sessions/${session.id}/approve`,
            { override_reason: overrideReason },
        );
        toast.success(
            response.data.message || 'Đã phê duyệt và cập nhật tồn kho.',
        );
        showReceiptModal.value = false;
        await router.reload();
    } catch (error: any) {
        toast.error(
            error.response?.data?.message || 'Không thể phê duyệt kỳ chốt.',
        );
    } finally {
        isSubmitting.value = false;
    }
}

async function cancelSession() {
    const session = receiptSession.value || selectedSession.value;

    if (!session) {
        return;
    }

    const reason = window.prompt(
        'Nhập lý do hủy kỳ chốt:',
        'Tạo nhầm kỳ hoặc cần mở lại kỳ khác',
    );

    if (reason === null || !reason.trim()) {
        return;
    }

    isSubmitting.value = true;

    try {
        await axios.post(
            `/api/inventory/count-sessions/${session.id}/cancel`,
            { reason },
        );
        toast.success('Đã hủy kỳ chốt.');
        showReceiptModal.value = false;
        await router.reload();
    } catch (error: any) {
        toast.error(error.response?.data?.message || 'Không thể hủy kỳ chốt.');
    } finally {
        isSubmitting.value = false;
    }
}

onMounted(openFromQuery);
</script>

<template>
    <Head :title="closingTitle" />

    <div
        class="min-h-screen bg-background px-4 py-6 text-foreground sm:px-6 lg:px-8"
    >
        <div class="mx-auto flex max-w-[1500px] flex-col gap-6">
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
            >
                <div>
                    <Link
                        :href="backUrl"
                        class="mb-3 inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
                    >
                        <ArrowLeft class="size-4" /> {{ backLabel }}
                    </Link>
                    <div class="flex items-center gap-3">
                        <div
                            class="rounded-2xl bg-amber-100 p-3 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300"
                        >
                            <ClipboardCheck class="size-7" />
                        </div>
                        <div>
                            <p
                                class="text-xs font-bold tracking-[0.2em] text-amber-700 uppercase dark:text-amber-300"
                            >
                                {{ branchLabel }} · Đối chiếu định kỳ
                            </p>
                            <h1 class="mt-1 text-3xl font-black tracking-tight">
                                {{
                                    isBranchMode
                                        ? 'Chốt kho chi nhánh'
                                        : 'Chốt nguyên liệu'
                                }}
                            </h1>
                        </div>
                    </div>
                    <p class="mt-3 max-w-3xl text-sm leading-6 text-muted-foreground">
                        Chọn kỳ từ ngày đến ngày. Hệ thống khóa snapshot
                        {{ isBranchMode ? 'riêng cho chi nhánh' : 'Kho Tổng' }}:
                        tồn đầu kỳ + nhập − xuất = tồn phải còn, sau đó nhân
                        viên đối chiếu số thực tế để nhận diện thiếu/thừa.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <select
                        v-if="isBranchMode && (props.branches?.length || 0) > 1"
                        v-model="selectedBranchId"
                        class="h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground"
                        @change="
                            router.get(
                                '/inventory/branch-closing',
                                { branch_id: selectedBranchId },
                                { preserveState: false, replace: true },
                            )
                        "
                    >
                        <option
                            v-for="candidate in props.branches"
                            :key="candidate.id"
                            :value="candidate.id"
                        >
                            {{ candidate.name }}
                        </option>
                    </select>
                    <Button
                        variant="outline"
                        class="gap-2"
                        @click="router.reload()"
                    >
                        <RefreshCw class="size-4" /> Làm mới
                    </Button>
                    <Button
                        v-if="canManage"
                        class="gap-2 bg-amber-500 font-bold text-slate-950 hover:bg-amber-400 dark:bg-amber-500 dark:text-slate-950"
                        @click="openCreate"
                    >
                        <ClipboardCheck class="size-4" />
                        {{ isBranchMode ? 'Mở kỳ chốt kho' : 'Mở kỳ chốt mới' }}
                    </Button>
                </div>
            </div>

            <div
                class="rounded-2xl border border-amber-200 bg-amber-50/70 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/20 dark:bg-amber-500/5 dark:text-amber-100/80"
            >
                <span class="font-bold text-amber-700 dark:text-amber-300">Phạm vi an toàn:</span>
                {{ scopeMessage }}
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Card class="motion-card animate-fade-in-up stagger-1 border-border bg-card shadow-sm"
                    ><CardContent class="p-5"
                        ><p
                            class="text-xs font-bold tracking-wider text-muted-foreground uppercase"
                        >
                            Tổng kỳ chốt
                        </p>
                        <p class="mt-2 text-3xl font-black">
                            {{ props.sessions.length }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            đã lưu snapshot
                        </p></CardContent
                    ></Card
                >
                <Card class="motion-card animate-fade-in-up stagger-2 border-amber-200 bg-amber-50/70 shadow-sm dark:border-amber-500/20 dark:bg-amber-500/5"
                    ><CardContent class="p-5"
                        ><p
                            class="text-xs font-bold tracking-wider text-amber-700 uppercase dark:text-amber-300"
                        >
                            Đang đối chiếu
                        </p>
                        <p class="mt-2 text-3xl font-black text-slate-900 dark:text-amber-200">
                            {{
                                props.sessions.filter(
                                    (s) => s.status === 'in_progress',
                                ).length
                            }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            có thể giao nhân viên
                        </p></CardContent
                    ></Card
                >
                <Card class="motion-card animate-fade-in-up stagger-3 border-sky-200 bg-sky-50/70 shadow-sm dark:border-sky-500/20 dark:bg-sky-500/5"
                    ><CardContent class="p-5"
                        ><p
                            class="text-xs font-bold tracking-wider text-sky-700 uppercase dark:text-sky-300"
                        >
                            Chờ phê duyệt
                        </p>
                        <p class="mt-2 text-3xl font-black text-slate-900 dark:text-sky-200">
                            {{
                                props.sessions.filter(
                                    (s) => s.status === 'pending_approval',
                                ).length
                            }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            chưa ghi điều chỉnh
                        </p></CardContent
                    ></Card
                >
                <Card class="motion-card animate-fade-in-up stagger-4 border-rose-200 bg-rose-50/70 shadow-sm dark:border-rose-500/20 dark:bg-rose-500/5"
                    ><CardContent class="p-5"
                        ><p
                            class="text-xs font-bold tracking-wider text-rose-700 uppercase dark:text-rose-300"
                        >
                            Thiếu đã xác định
                        </p>
                        <p class="mt-2 text-xl font-black text-slate-900 dark:text-rose-200">
                            {{
                                formatCurrency(
                                    props.sessions.reduce(
                                        (sum, s) => sum + sessionShortage(s),
                                        0,
                                    ),
                                )
                            }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            theo các kỳ đã đối chiếu
                        </p></CardContent
                    ></Card
                >
            </div>

            <Card class="border-border bg-card shadow-sm">
                <CardHeader
                    class="flex flex-col gap-3 border-b border-border sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <CardTitle class="text-lg">{{
                            isBranchMode
                                ? 'Các kỳ chốt kho chi nhánh'
                                : 'Các kỳ chốt nguyên liệu'
                        }}</CardTitle
                        ><CardDescription class="text-muted-foreground"
                            >Mỗi kỳ lưu lại số liệu để truy vết và đối chiếu,
                            không phụ thuộc nhà cung cấp.</CardDescription
                        >
                    </div>
                    <Input
                        v-model="search"
                        placeholder="Tìm mã kỳ / ngày / trạng thái"
                        class="h-9 w-full border-input bg-background sm:w-64"
                    />
                </CardHeader>
                <CardContent class="p-0">
                    <div
                        v-if="filteredSessions.length === 0"
                        class="p-12 text-center text-sm text-muted-foreground"
                    >
                        Chưa có kỳ chốt nào trong phạm vi {{ branchLabel }}.
                    </div>
                    <div v-else class="divide-y divide-border">
                        <div
                            v-for="session in filteredSessions"
                            :key="session.id"
                            class="group motion-row flex w-full flex-col gap-4 p-5 text-left transition-all hover:bg-muted/40 lg:flex-row lg:items-center lg:justify-between cursor-pointer"
                            :class="{
                                'bg-primary/[0.04] border-l-4 border-l-primary pl-4': receiptSession?.id === session.id && showReceiptModal,
                            }"
                            @click="openReceipt(session)"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-black text-foreground"
                                        >Kỳ chốt #{{ session.id }}</span
                                    ><Badge
                                        variant="outline"
                                        :class="statusClass(session.status)"
                                        >{{
                                            statusLabel(session.status)
                                        }}</Badge
                                    >
                                    <Badge
                                        v-if="receiptSession?.id === session.id && showReceiptModal"
                                        variant="secondary"
                                        class="border-primary/30 bg-primary/10 text-primary text-[11px] font-medium"
                                    >
                                        Đang xem
                                    </Badge>
                                </div>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{ formatDateVietnamese(session.period_start) }} →
                                    {{ formatDateVietnamese(session.period_end) }} ·
                                    {{ session.items?.length || 0 }} nguyên liệu
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Đếm 1:
                                    {{
                                        session.countedBy?.name ||
                                        'Trưởng kho Tổng'
                                    }}
                                    <span v-if="session.secondCountedBy">
                                        · Đối chiếu:
                                        {{ session.secondCountedBy.name }}</span
                                    >
                                </p>
                            </div>
                            <div class="flex flex-col gap-4 sm:flex-row sm:items-center lg:gap-8 shrink-0">
                                <div
                                    class="flex items-center justify-between sm:justify-end gap-6 sm:gap-10 lg:gap-14 text-right"
                                >
                                    <div class="min-w-[70px] sm:min-w-[90px]">
                                        <p class="text-xs text-muted-foreground">Phải còn</p>
                                        <p class="font-bold text-sm text-foreground">
                                            {{
                                                formatNumber(
                                                    session.total_expected_quantity,
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <div class="min-w-[100px] sm:min-w-[130px]">
                                        <p class="text-xs text-muted-foreground">Giá trị</p>
                                        <p class="font-bold text-sm text-foreground">
                                            {{
                                                formatCurrency(
                                                    session.total_expected_value,
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <div class="min-w-[80px] sm:min-w-[100px]">
                                        <p class="text-xs text-muted-foreground">Thiếu</p>
                                        <p
                                            class="font-bold text-sm"
                                            :class="
                                                Number(session.total_shortage_value) > 0
                                                    ? 'text-rose-500 font-extrabold'
                                                    : 'text-rose-600 dark:text-rose-400'
                                            "
                                        >
                                            {{
                                                formatCurrency(
                                                    session.total_shortage_value,
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <div v-if="!isBranchMode" class="min-w-[70px] sm:min-w-[90px]">
                                        <p class="text-xs text-muted-foreground">Task</p>
                                        <p
                                            class="font-bold text-sm"
                                            :class="
                                                taskFor(session.id)?.status ===
                                                'completed'
                                                    ? 'text-emerald-600 dark:text-emerald-400'
                                                    : 'text-amber-600 dark:text-amber-300'
                                            "
                                        >
                                            {{
                                                taskFor(session.id)?.status ||
                                                'Chưa giao'
                                            }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center justify-end sm:border-l sm:border-border sm:pl-6">
                                    <Button
                                        type="button"
                                        size="sm"
                                        :variant="receiptSession?.id === session.id && showReceiptModal ? 'default' : 'outline'"
                                        class="h-9 gap-1.5 px-3 font-medium transition-all shadow-sm group-hover:border-primary/50"
                                        @click.stop="openReceipt(session)"
                                    >
                                        <Eye class="size-4" />
                                        <span>Xem chi tiết</span>
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Teleport to="body">
            <div
                v-if="showCreate"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs overflow-y-auto"
                @click.self="showCreate = false"
            >
                <Card
                    class="motion-card animate-fade-in-up w-full max-w-lg border-border bg-card shadow-2xl"
                    ><CardHeader
                        ><CardTitle>{{
                            isBranchMode
                                ? 'Mở kỳ chốt kho chi nhánh'
                                : 'Mở kỳ chốt nguyên liệu'
                        }}</CardTitle
                        ><CardDescription class="text-muted-foreground"
                            >Không dùng nhà cung cấp. Hệ thống đọc sổ giao dịch
                            {{ branchLabel }} theo khoảng ngày bạn
                            chọn.</CardDescription
                        ></CardHeader
                    ><CardContent class="space-y-4">
                        <div v-if="isBranchMode" class="space-y-2">
                            <Label>Chi nhánh</Label
                            ><select
                                v-model="selectedBranchId"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground"
                            >
                                <option
                                    v-for="candidate in props.branches"
                                    :key="candidate.id"
                                    :value="candidate.id"
                                >
                                    {{ candidate.name }}
                                </option>
                            </select>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label>Chốt từ ngày</Label
                                ><Input
                                    v-model="periodForm.from_date"
                                    type="date"
                                    :disabled="Boolean(props.nextPeriodStart)"
                                    class="border-input bg-background"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label>Đến ngày</Label
                                ><Input
                                    v-model="periodForm.to_date"
                                    type="date"
                                    class="border-input bg-background"
                                />
                            </div>
                        </div>
                        <div
                            v-if="props.nextPeriodStart"
                            class="rounded-xl border border-amber-200 bg-amber-50/70 p-3 text-xs leading-5 text-amber-900 dark:border-amber-500/20 dark:bg-amber-500/5 dark:text-amber-200"
                        >
                            Kỳ mới bắt buộc nối tiếp kỳ đã được xác nhận và bắt đầu từ
                            {{ props.nextPeriodStart }}. Mốc này không thể tự sửa để
                            tránh bỏ trống hoặc chồng lấn ngày.
                        </div>
                        <div
                            class="rounded-xl border border-sky-200 bg-sky-50/70 p-3 text-xs leading-5 text-sky-900 dark:border-sky-500/20 dark:bg-sky-500/5 dark:text-sky-200"
                        >
                            Sau khi mở kỳ, hệ thống sẽ hiển thị từng nguyên liệu:
                            tồn đầu kỳ, tổng nhập, tổng xuất, tồn phải còn và giá
                            trị quy đổi.
                            {{
                                isBranchMode
                                    ? 'Quản lý chi nhánh có thể giao nhân viên đối chiếu thực tế.'
                                    : 'Trưởng kho có thể giao nhân viên đối chiếu thực tế.'
                            }}
                        </div>
                        <div class="flex justify-end gap-2">
                            <Button
                                variant="outline"
                                class="border-border"
                                @click="showCreate = false"
                                >Hủy</Button
                            ><Button
                                :disabled="isSubmitting"
                                class="motion-btn-primary bg-amber-500 font-bold text-slate-950 hover:bg-amber-400 dark:bg-amber-500 dark:text-slate-950"
                                @click="createClosing"
                                >Tạo kỳ chốt</Button
                            >
                        </div>
                    </CardContent></Card
                >
            </div>
        </Teleport>

        <Teleport to="body">
            <div
                v-if="showAssign && selectedSession"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs overflow-y-auto"
                @click.self="showAssign = false"
            >
                <Card
                    class="motion-card animate-fade-in-up w-full max-w-lg border-border bg-card shadow-2xl"
                    ><CardHeader
                        ><CardTitle
                            >Giao việc đối chiếu #{{
                                selectedSession.id
                            }}</CardTitle
                        ><CardDescription class="text-muted-foreground"
                            >Nhân viên sẽ nhập số thực tế cho toàn bộ nguyên liệu và
                            kết quả được ghi vào lịch sử kỳ chốt.</CardDescription
                        ></CardHeader
                    ><CardContent class="space-y-4">
                        <div class="space-y-2">
                            <Label>Nhân viên {{ branchLabel }}</Label
                            ><select
                                v-model="assignForm.assigned_to"
                                class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground"
                            >
                                <option value="">Chọn nhân viên</option>
                                <option
                                    v-for="candidate in counterCandidates"
                                    :key="candidate.id"
                                    :value="String(candidate.id)"
                                >
                                    {{ candidate.name
                                    }}{{
                                        candidate.job_title
                                            ? ` · ${candidate.job_title}`
                                            : ''
                                    }}
                                </option>
                            </select>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="space-y-2">
                                <Label>Ưu tiên</Label
                                ><select
                                    v-model="assignForm.priority"
                                    class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm text-foreground"
                                >
                                    <option value="normal">Bình thường</option>
                                    <option value="high">Cao</option>
                                    <option value="urgent">Khẩn</option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <Label>Hạn hoàn thành</Label
                                ><Input
                                    v-model="assignForm.due_at"
                                    type="datetime-local"
                                    class="border-input bg-background"
                                />
                            </div>
                        </div>
                        <div class="space-y-2">
                            <Label>Hướng dẫn</Label
                            ><textarea
                                v-model="assignForm.notes"
                                rows="3"
                                class="w-full rounded-md border border-input bg-background p-3 text-sm text-foreground"
                            />
                        </div>
                        <div class="flex justify-end gap-2">
                            <Button
                                variant="outline"
                                class="border-border"
                                @click="showAssign = false"
                                >Hủy</Button
                            ><Button
                                :disabled="isSubmitting"
                                class="motion-btn-primary gap-2 bg-amber-500 font-bold text-slate-950 hover:bg-amber-400 dark:bg-amber-500 dark:text-slate-950"
                                @click="assignCounter"
                                ><UserPlus class="size-4" /> Giao việc</Button
                            >
                        </div>
                    </CardContent></Card
                >
            </div>
        </Teleport>

        <!-- OFFICIAL INVENTORY CLOSING RECEIPT MODAL -->
        <Teleport to="body">
            <div
                v-if="showReceiptModal && receiptSession"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-2 sm:p-6 backdrop-blur-sm overflow-y-auto"
                @click.self="showReceiptModal = false"
            >
                <div
                    class="relative w-full max-w-5xl my-auto bg-white text-slate-900 rounded-xl shadow-2xl border border-slate-300 overflow-hidden flex flex-col max-h-[95vh]"
                >
                    <!-- Modal Header Actions (No Print) -->
                    <div
                        class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-700 bg-slate-900 px-5 py-3 text-white no-print"
                    >
                        <div class="flex items-center gap-2">
                            <FileText class="size-5 text-amber-400" />
                            <span class="font-bold text-sm sm:text-base">
                                PHIẾU CHỐT KHO - KỲ CHỐT #{{ receiptSession.id }}
                            </span>
                            <Badge
                                variant="outline"
                                :class="statusClass(receiptSession.status)"
                            >
                                {{ statusLabel(receiptSession.status) }}
                            </Badge>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <Button
                                v-if="props.canManage && receiptSession.status === 'in_progress'"
                                size="sm"
                                variant="outline"
                                class="h-8 gap-1 border-amber-500/50 bg-amber-950/40 text-amber-300 hover:bg-amber-900/60 hover:text-amber-200"
                                @click="openAssign(receiptSession)"
                            >
                                <UserPlus class="size-3.5" />
                                <span>Giao việc</span>
                            </Button>

                            <Button
                                v-if="receiptSession.status === 'pending_approval'"
                                size="sm"
                                variant="outline"
                                class="h-8 gap-1 border-emerald-500/50 bg-emerald-950/40 text-emerald-300 hover:bg-emerald-900/60 hover:text-emerald-200"
                                :disabled="isSubmitting"
                                @click="approveSession"
                            >
                                <CheckCircle2 class="size-3.5" />
                                <span>Phê duyệt</span>
                            </Button>

                            <Button
                                v-if="receiptSession.status === 'pending_approval'"
                                size="sm"
                                variant="outline"
                                class="h-8 gap-1 border-rose-500/50 bg-rose-950/40 text-rose-300 hover:bg-rose-900/60 hover:text-rose-200"
                                :disabled="isSubmitting"
                                @click="rejectSession"
                            >
                                <XCircle class="size-3.5" />
                                <span>Từ chối</span>
                            </Button>

                            <Button
                                v-if="receiptSession.status === 'closed'"
                                size="sm"
                                variant="outline"
                                class="h-8 gap-1 border-amber-500/50 bg-amber-950/40 text-amber-300 hover:bg-amber-900/60 hover:text-amber-200"
                                :disabled="isSubmitting"
                                @click="reopenSession"
                            >
                                <RotateCcw class="size-3.5" />
                                <span>Mở lại</span>
                            </Button>

                            <Button
                                v-if="['draft', 'in_progress', 'pending_approval'].includes(receiptSession.status)"
                                size="sm"
                                variant="outline"
                                class="h-8 gap-1 border-slate-700 bg-slate-800 text-slate-300 hover:bg-rose-950/40 hover:text-rose-300 hover:border-rose-500/50"
                                :disabled="isSubmitting"
                                @click="cancelSession"
                            >
                                <span>Hủy kỳ</span>
                            </Button>

                            <Button
                                size="sm"
                                variant="outline"
                                class="h-8 gap-1.5 border-slate-700 bg-slate-800 text-white hover:bg-slate-700 hover:text-white"
                                @click="printReceipt"
                            >
                                <Printer class="size-4" />
                                <span>In phiếu</span>
                            </Button>

                            <Button
                                size="sm"
                                variant="ghost"
                                class="h-8 w-8 p-0 text-slate-400 hover:bg-slate-800 hover:text-white"
                                @click="showReceiptModal = false"
                            >
                                <X class="size-5" />
                            </Button>
                        </div>
                    </div>

                    <!-- Printable Receipt Document Body -->
                    <div
                        id="printable-receipt-modal"
                        class="overflow-y-auto p-6 sm:p-10 bg-white text-black font-sans leading-relaxed text-[13px]"
                    >
                        <!-- Document Top Header -->
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between pb-3 border-b-2 border-black">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-500 text-slate-950 font-black text-xl">
                                        A
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-base uppercase tracking-wider text-black">CÔNG TY TNHH AVENTURA</h3>
                                        <p class="text-xs text-slate-700 italic">Chuỗi cung cấp thực phẩm &amp; dịch vụ nhà hàng</p>
                                    </div>
                                </div>
                                <p class="text-xs text-slate-700">📍 Số 123 Nguyễn Văn Cừ, P. Bồ Đề, Q. Long Biên, Hà Nội</p>
                                <p class="text-xs text-slate-700">📞 Hotline: 024 1234 5678</p>
                            </div>

                            <div class="text-center sm:text-right space-y-1 sm:min-w-[280px]">
                                <h4 class="font-bold text-xs uppercase tracking-wide text-black">CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</h4>
                                <p class="text-xs font-semibold italic text-black">Độc lập – Tự do – Hạnh phúc</p>
                                <div class="mx-auto sm:ml-auto sm:mr-0 w-24 border-b border-black my-1"></div>
                                <p class="text-xs italic text-slate-700 pt-1">
                                    Hà Nội, ngày {{ receiptDateInfo.day }} tháng {{ receiptDateInfo.month }} năm {{ receiptDateInfo.year }}
                                </p>
                            </div>
                        </div>

                        <!-- Document Title & Number -->
                        <div class="my-6 text-center">
                            <h1 class="font-black text-2xl uppercase tracking-wider text-black">
                                PHIẾU CHỐT KHO {{ isBranchMode ? 'CHI NHÁNH' : 'NGUYÊN LIỆU KHO TỔNG' }}
                            </h1>
                            <div class="mt-2 inline-block border border-black px-4 py-1 text-xs font-bold text-black">
                                Số: {{ receiptNumber }}
                            </div>
                        </div>

                        <!-- 1. THÔNG TIN CHUNG -->
                        <div class="mb-6">
                            <h2 class="font-bold text-sm uppercase mb-2 text-black">1. THÔNG TIN CHUNG</h2>
                            <div class="border border-black p-4 grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-2 text-xs text-black">
                                <div class="space-y-1.5">
                                    <div><span class="font-semibold">Chi nhánh:</span> {{ branch.name }}</div>
                                    <div><span class="font-semibold">Kho:</span> {{ isBranchMode ? (branch.name + ' - Kho Chi nhánh') : 'Kho Tổng Aventura' }}</div>
                                    <div><span class="font-semibold">Ngày chốt:</span> {{ formatDateVietnamese(receiptSession.period_end) }}</div>
                                    <div><span class="font-semibold">Kỳ chốt:</span> Từ ngày {{ formatDateVietnamese(receiptSession.period_start) }} đến ngày {{ formatDateVietnamese(receiptSession.period_end) }}</div>
                                    <div class="flex flex-wrap items-center gap-3 pt-1">
                                        <span class="font-semibold">Lý do chốt:</span>
                                        <label class="inline-flex items-center gap-1"><span class="font-bold">☑</span> Định kỳ cuối tháng</label>
                                        <label class="inline-flex items-center gap-1"><span class="font-bold">☐</span> Định kỳ cuối ngày</label>
                                        <label class="inline-flex items-center gap-1"><span class="font-bold">☐</span> Khác: {{ receiptSession.notes || '..................' }}</label>
                                    </div>
                                </div>

                                <div class="space-y-1.5 md:border-l md:border-black md:pl-6">
                                    <div><span class="font-semibold">Người lập phiếu:</span> {{ receiptSession.countedBy?.name || 'Trưởng kho' }}</div>
                                    <div><span class="font-semibold">Chức vụ:</span> {{ isBranchMode ? 'Nhân viên kiểm kê chi nhánh' : 'Trưởng kho Tổng' }}</div>
                                    <div><span class="font-semibold">Giờ chốt kho:</span> {{ formatDateTimeVietnamese(receiptSession.created_at || receiptSession.period_end) }}</div>
                                </div>
                            </div>
                        </div>

                        <!-- 2. TỔNG HỢP GIÁ TRỊ TỒN KHO -->
                        <div class="mb-6">
                            <h2 class="font-bold text-sm uppercase mb-2 text-black">2. TỔNG HỢP GIÁ TRỊ TỒN KHO</h2>
                            <div class="overflow-x-auto">
                                <table class="w-full border-collapse border border-black text-center text-xs text-black">
                                    <thead>
                                        <tr class="bg-slate-100 font-bold border-b border-black">
                                            <th class="border border-black p-2 w-12">STT</th>
                                            <th class="border border-black p-2 text-left">Chỉ tiêu</th>
                                            <th class="border border-black p-2 w-28">Đơn vị tính</th>
                                            <th class="border border-black p-2 w-36 text-right">Số lượng</th>
                                            <th class="border border-black p-2 w-44 text-right">Giá trị (VND)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td class="border border-black p-2 font-medium">1</td>
                                            <td class="border border-black p-2 text-left font-medium">Tổng nhập trong kỳ</td>
                                            <td class="border border-black p-2">-</td>
                                            <td class="border border-black p-2 text-right font-bold">{{ formatNumber(receiptTotals.totalInboundQty) }}</td>
                                            <td class="border border-black p-2 text-right font-bold">{{ formatCurrency(receiptTotals.totalInboundVal) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="border border-black p-2 font-medium">2</td>
                                            <td class="border border-black p-2 text-left font-medium">Tổng xuất trong kỳ</td>
                                            <td class="border border-black p-2">-</td>
                                            <td class="border border-black p-2 text-right font-bold">{{ formatNumber(receiptTotals.totalOutboundQty) }}</td>
                                            <td class="border border-black p-2 text-right font-bold">{{ formatCurrency(receiptTotals.totalOutboundVal) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="border border-black p-2 font-medium">3</td>
                                            <td class="border border-black p-2 text-left font-medium">Tồn đầu kỳ</td>
                                            <td class="border border-black p-2">-</td>
                                            <td class="border border-black p-2 text-right font-bold">{{ formatNumber(receiptTotals.totalOpeningQty) }}</td>
                                            <td class="border border-black p-2 text-right font-bold">{{ formatCurrency(receiptTotals.totalOpeningVal) }}</td>
                                        </tr>
                                        <tr class="bg-slate-50 font-bold">
                                            <td class="border border-black p-2">4</td>
                                            <td class="border border-black p-2 text-left">Tồn cuối kỳ (Thực tế)</td>
                                            <td class="border border-black p-2">-</td>
                                            <td class="border border-black p-2 text-right">{{ formatNumber(receiptTotals.totalClosingQty) }}</td>
                                            <td class="border border-black p-2 text-right">{{ formatCurrency(receiptTotals.totalClosingVal) }}</td>
                                        </tr>
                                        <tr :class="receiptTotals.totalVarianceVal !== 0 ? 'bg-rose-50 text-rose-900 font-bold' : 'font-medium'">
                                            <td class="border border-black p-2">5</td>
                                            <td class="border border-black p-2 text-left">Chênh lệch (4 = 3 + 1 - 2)</td>
                                            <td class="border border-black p-2">-</td>
                                            <td class="border border-black p-2 text-right">{{ formatNumber(receiptTotals.totalVarianceQty) }}</td>
                                            <td class="border border-black p-2 text-right">{{ formatCurrency(receiptTotals.totalVarianceVal) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- 3. CHI TIẾT TỒN KHO THEO NGUYÊN LIỆU -->
                        <div class="mb-6">
                            <h2 class="font-bold text-sm uppercase mb-2 text-black">3. CHI TIẾT TỒN KHO THEO NGUYÊN LIỆU</h2>
                            <div class="overflow-x-auto">
                                <table class="w-full border-collapse border border-black text-center text-xs text-black">
                                    <thead>
                                        <tr class="bg-slate-100 font-bold border-b border-black">
                                            <th rowspan="2" class="border border-black p-2 w-10">STT</th>
                                            <th rowspan="2" class="border border-black p-2 text-left">Nguyên liệu</th>
                                            <th rowspan="2" class="border border-black p-2 w-14">ĐVT</th>
                                            <th colspan="2" class="border border-black p-1.5">Tồn đầu kỳ</th>
                                            <th colspan="2" class="border border-black p-1.5">Nhập trong kỳ</th>
                                            <th colspan="2" class="border border-black p-1.5">Xuất trong kỳ</th>
                                            <th colspan="2" class="border border-black p-1.5 bg-amber-50">Tồn cuối kỳ (Thực tế)</th>
                                            <th rowspan="2" class="border border-black p-2 w-28">Ghi chú</th>
                                        </tr>
                                        <tr class="bg-slate-100 font-bold border-b border-black">
                                            <th class="border border-black p-1.5 w-16 text-right">SL</th>
                                            <th class="border border-black p-1.5 w-24 text-right">Giá trị (VND)</th>
                                            <th class="border border-black p-1.5 w-16 text-right">SL</th>
                                            <th class="border border-black p-1.5 w-24 text-right">Giá trị (VND)</th>
                                            <th class="border border-black p-1.5 w-16 text-right">SL</th>
                                            <th class="border border-black p-1.5 w-24 text-right">Giá trị (VND)</th>
                                            <th class="border border-black p-1.5 w-16 text-right bg-amber-50">SL</th>
                                            <th class="border border-black p-1.5 w-24 text-right bg-amber-50">Giá trị (VND)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="(item, idx) in receiptSession.items || []"
                                            :key="item.id"
                                            class="hover:bg-slate-50"
                                        >
                                            <td class="border border-black p-1.5">{{ idx + 1 }}</td>
                                            <td class="border border-black p-1.5 text-left font-medium">
                                                {{ item.ingredient?.name || `Nguyên liệu #${item.ingredient_id}` }}
                                                <span v-if="item.ingredient?.sku" class="text-[10px] text-slate-500">({{ item.ingredient.sku }})</span>
                                            </td>
                                            <td class="border border-black p-1.5">{{ item.ingredient?.unit?.symbol || item.ingredient?.unit?.name || 'kg' }}</td>
                                            <td class="border border-black p-1.5 text-right">{{ formatNumber(item.opening_quantity) }}</td>
                                            <td class="border border-black p-1.5 text-right">{{ formatCurrency(Number(item.opening_quantity || 0) * Number(item.unit_cost || 0)) }}</td>
                                            <td class="border border-black p-1.5 text-right">{{ formatNumber(item.inbound_quantity) }}</td>
                                            <td class="border border-black p-1.5 text-right">{{ formatCurrency(item.inbound_value) }}</td>
                                            <td class="border border-black p-1.5 text-right">{{ formatNumber(item.outbound_quantity) }}</td>
                                            <td class="border border-black p-1.5 text-right">{{ formatCurrency(item.outbound_value) }}</td>
                                            <td class="border border-black p-1.5 text-right font-bold bg-amber-50/50">{{ formatNumber(item.final_quantity ?? item.counted_quantity_1 ?? item.expected_quantity) }}</td>
                                            <td class="border border-black p-1.5 text-right font-bold bg-amber-50/50">{{ formatCurrency(Number(item.final_quantity ?? item.counted_quantity_1 ?? item.expected_quantity) * Number(item.unit_cost || 0)) }}</td>
                                            <td class="border border-black p-1.5 text-left text-[11px]">
                                                {{ item.notes || (Number(item.variance_quantity) < 0 ? `Thiếu ${formatNumber(Math.abs(item.variance_quantity))}` : (Number(item.variance_quantity) > 0 ? `Thừa ${formatNumber(item.variance_quantity)}` : 'Khớp')) }}
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr class="bg-slate-100 font-bold border-t-2 border-black">
                                            <td colspan="3" class="border border-black p-2 text-center uppercase">TỔNG CỘNG</td>
                                            <td class="border border-black p-2 text-right">{{ formatNumber(receiptTotals.totalOpeningQty) }}</td>
                                            <td class="border border-black p-2 text-right">{{ formatCurrency(receiptTotals.totalOpeningVal) }}</td>
                                            <td class="border border-black p-2 text-right">{{ formatNumber(receiptTotals.totalInboundQty) }}</td>
                                            <td class="border border-black p-2 text-right">{{ formatCurrency(receiptTotals.totalInboundVal) }}</td>
                                            <td class="border border-black p-2 text-right">{{ formatNumber(receiptTotals.totalOutboundQty) }}</td>
                                            <td class="border border-black p-2 text-right">{{ formatCurrency(receiptTotals.totalOutboundVal) }}</td>
                                            <td class="border border-black p-2 text-right bg-amber-100">{{ formatNumber(receiptTotals.totalClosingQty) }}</td>
                                            <td class="border border-black p-2 text-right bg-amber-100">{{ formatCurrency(receiptTotals.totalClosingVal) }}</td>
                                            <td class="border border-black p-2"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <!-- 4. KIỂM TRA - ĐỐI CHIẾU -->
                        <div class="mb-6">
                            <h2 class="font-bold text-sm uppercase mb-2 text-black">4. KIỂM TRA – ĐỐI CHIẾU</h2>
                            <div class="border border-black p-4 space-y-2 text-xs text-black">
                                <div class="flex flex-col sm:flex-row sm:items-center gap-4">
                                    <div class="font-semibold sm:w-48">Đối chiếu số liệu với thủ kho:</div>
                                    <div class="flex items-center gap-4">
                                        <label class="inline-flex items-center gap-1.5"><span class="font-bold text-sm">{{ Math.abs(receiptTotals.totalVarianceVal) < 1 ? '☑' : '☐' }}</span> Khớp</label>
                                        <label class="inline-flex items-center gap-1.5"><span class="font-bold text-sm">{{ Math.abs(receiptTotals.totalVarianceVal) >= 1 ? '☑' : '☐' }}</span> Chênh lệch</label>
                                    </div>
                                    <div class="sm:flex-1 text-slate-700">
                                        Nếu chênh lệch, nguyên nhân: <span class="font-medium italic underline underline-offset-4">{{ Math.abs(receiptTotals.totalVarianceVal) >= 1 ? (receiptSession.stale_reason || receiptSession.notes || 'Hao hụt sơ chế, thất thoát nhiệt độ bảo quản và sai số thực tế trong kỳ chốt') : 'Không có chênh lệch' }}</span>
                                    </div>
                                </div>
                                <div class="flex flex-col sm:flex-row sm:items-center gap-4 border-t border-slate-200 pt-2">
                                    <div class="font-semibold sm:w-48">Đối chiếu số liệu với kế toán:</div>
                                    <div class="flex items-center gap-4">
                                        <label class="inline-flex items-center gap-1.5"><span class="font-bold text-sm">☑</span> Khớp</label>
                                        <label class="inline-flex items-center gap-1.5"><span class="font-bold text-sm">☐</span> Chênh lệch</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 5. KẾT LUẬN -->
                        <div class="mb-6">
                            <h2 class="font-bold text-sm uppercase mb-2 text-black">5. KẾT LUẬN</h2>
                            <div class="border border-black p-4 space-y-2 text-xs text-black">
                                <p class="italic">Chúng tôi đã kiểm tra, đối chiếu số liệu tồn kho tại Kho chi nhánh đến thời điểm chốt nêu trên.</p>
                                <div class="space-y-1 pt-1">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-sm">{{ Math.abs(receiptTotals.totalVarianceVal) < 1 ? '☑' : '☐' }}</span>
                                        <span>Số liệu tồn kho khớp đúng với sổ sách kế toán.</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-sm">{{ Math.abs(receiptTotals.totalVarianceVal) >= 1 ? '☑' : '☐' }}</span>
                                        <span>Số liệu tồn kho có chênh lệch, đề nghị xử lý theo nguyên nhân nêu trên.</span>
                                    </div>
                                </div>
                                <div class="pt-2">
                                    <span class="font-semibold">Kiến nghị / Ghi chú:</span>
                                    <p class="mt-1 italic underline underline-offset-4 text-slate-800">
                                        {{ receiptSession.notes || 'Số liệu kiểm kê thực tế đã được chốt và đồng bộ chính xác vào dữ liệu tồn kho Aventura.' }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- 6. CHỮ KÝ XÁC NHẬN -->
                        <div class="mb-6">
                            <h2 class="font-bold text-sm uppercase mb-2 text-black">6. CHỮ KÝ XÁC NHẬN</h2>
                            <div class="border border-black p-4">
                                <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 text-center text-xs text-black">
                                    <div class="space-y-1">
                                        <p class="font-bold uppercase">THỦ KHO</p>
                                        <p class="text-[11px] italic text-slate-500">(Ký, ghi rõ họ tên)</p>
                                        <div class="h-16 flex items-end justify-center font-semibold italic text-slate-800 pb-1">
                                            {{ receiptSession.countedBy?.name || 'Trưởng kho' }}
                                        </div>
                                        <p class="text-[10px] text-slate-500">Ngày ..... / ..... / 20.....</p>
                                    </div>

                                    <div class="space-y-1">
                                        <p class="font-bold uppercase">QC / KIỂM SOÁT CL</p>
                                        <p class="text-[11px] italic text-slate-500">(Ký, ghi rõ họ tên)</p>
                                        <div class="h-16 flex items-end justify-center font-semibold italic text-slate-800 pb-1">
                                            {{ receiptSession.secondCountedBy?.name || 'Nguyễn Kiểm Soát' }}
                                        </div>
                                        <p class="text-[10px] text-slate-500">Ngày ..... / ..... / 20.....</p>
                                    </div>

                                    <div class="space-y-1">
                                        <p class="font-bold uppercase">KẾ TOÁN CHI NHÁNH</p>
                                        <p class="text-[11px] italic text-slate-500">(Ký, ghi rõ họ tên)</p>
                                        <div class="h-16 flex items-end justify-center font-semibold italic text-slate-800 pb-1">
                                            Lê Thị Thu Ngân
                                        </div>
                                        <p class="text-[10px] text-slate-500">Ngày ..... / ..... / 20.....</p>
                                    </div>

                                    <div class="space-y-1">
                                        <p class="font-bold uppercase">QUẢN LÝ CHI NHÁNH</p>
                                        <p class="text-[11px] italic text-slate-500">(Ký, ghi rõ họ tên)</p>
                                        <div class="h-16 flex items-end justify-center font-semibold italic text-slate-800 pb-1">
                                            {{ receiptSession.approver?.name || 'Chủ Nhà Hàng / QL' }}
                                        </div>
                                        <p class="text-[10px] text-slate-500">Ngày ..... / ..... / 20.....</p>
                                    </div>

                                    <div class="space-y-1">
                                        <p class="font-bold uppercase">KẾ TOÁN TỔNG HỢP</p>
                                        <p class="text-[11px] italic text-slate-500">(Ký, ghi rõ họ tên)</p>
                                        <div class="h-16 flex items-end justify-center font-semibold italic text-slate-800 pb-1">
                                            Ban Kế Toán Aventura
                                        </div>
                                        <p class="text-[10px] text-slate-500">Ngày ..... / ..... / 20.....</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Footer notes -->
                        <div class="pt-2 text-xs italic text-slate-600 space-y-0.5">
                            <p><strong>Ghi chú:</strong></p>
                            <p>- Phiếu chốt kho chi nhánh được lập 02 bản: 01 bản lưu tại kho, 01 bản gửi về phòng Kế toán Tổng hợp.</p>
                            <p>- Liên: 1. Lưu tại kho chi nhánh; 2. Gửi Kế toán Tổng hợp.</p>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<style>
@media print {
    body * {
        visibility: hidden !important;
    }
    #printable-receipt-modal,
    #printable-receipt-modal * {
        visibility: visible !important;
    }
    #printable-receipt-modal {
        position: fixed !important;
        inset: 0 !important;
        margin: 0 !important;
        padding: 20px !important;
        background: white !important;
        color: black !important;
        max-height: none !important;
        overflow: visible !important;
        border: none !important;
        box-shadow: none !important;
        width: 100% !important;
        z-index: 999999 !important;
    }
    .no-print {
        display: none !important;
    }
}
</style>
