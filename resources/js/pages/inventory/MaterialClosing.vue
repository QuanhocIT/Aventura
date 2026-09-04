<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    ArrowLeft,
    CheckCircle2,
    ClipboardCheck,
    RefreshCw,
    ShieldAlert,
    UserPlus,
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
    reconciliation_status?: string;
    reconciliation_notes?: string | null;
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
    total_variance_value: number;
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
    assigned_to?: number | null;
    assignee?: { id: number; name: string } | null;
    notes?: string | null;
}

const props = defineProps<{
    mode: 'central' | 'branch';
    branch: { id: number; name: string; code?: string };
    branches?: Array<{ id: number; name: string; code?: string }>;
    selectedBranchId?: number;
    sessions: ClosingSession[];
    tasks: ClosingTask[];
    counterCandidates: CounterCandidate[];
    authUserId: number;
    canManage: boolean;
    canApprove: boolean;
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
const countsBaseUrl = computed(() =>
    isBranchMode.value ? '/api/inventory/count-sessions' : closingBaseUrl.value,
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

const periodForm = ref({ from_date: firstOfMonth, to_date: today });
const selectedSession = ref<ClosingSession | null>(null);
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
const countRows = ref<
    Array<{ id: number; counted_quantity: string; notes: string }>
>([]);
const selectedBranchId = ref(props.selectedBranchId ?? props.branch.id);

const filteredSessions = computed(() => {
    const query = search.value.trim().toLowerCase();

    return props.sessions.filter((session) => {
        if (!query) {
            return true;
        }

        return (
            `#${session.id}`.includes(query) ||
            session.period_start?.includes(query) ||
            session.period_end?.includes(query) ||
            session.status?.toLowerCase().includes(query)
        );
    });
});

const activeTask = computed(() =>
    selectedSession.value
        ? props.tasks.find(
              (task) => task.count_session_id === selectedSession.value?.id,
          )
        : undefined,
);

function taskFor(sessionId: number) {
    return props.tasks.find((task) => task.count_session_id === sessionId);
}

const canEditSelectedCounts = computed(() => {
    const session = selectedSession.value;

    if (!session || session.status !== 'in_progress') {
        return false;
    }

    return (
        props.canManage ||
        Number(session.second_counted_by) === Number(props.authUserId)
    );
});

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

    return 'border-slate-500/30 bg-slate-500/10 text-slate-400';
}

function varianceLabel(item: ClosingItem) {
    if (item.reconciliation_status === 'pending') {
        return 'Cần đếm lại';
    }

    if (item.final_quantity === null) {
        return 'Chưa đếm';
    }

    if (Number(item.variance_quantity) < -0.0005) {
        return 'Thiếu';
    }

    if (Number(item.variance_quantity) > 0.0005) {
        return 'Thừa';
    }

    return 'Khớp';
}

function varianceClass(item: ClosingItem) {
    if (
        item.reconciliation_status === 'pending' ||
        Number(item.variance_quantity) < -0.0005
    ) {
        return 'text-rose-400';
    }

    if (Number(item.variance_quantity) > 0.0005) {
        return 'text-amber-400';
    }

    return 'text-emerald-400';
}

function sessionShortage(session: ClosingSession) {
    return Number(session.total_shortage_value || 0);
}

function openSession(session: ClosingSession) {
    selectedSession.value = session;
    const isSecondCounter =
        Number(session.second_counted_by) === Number(props.authUserId);
    countRows.value = (session.items || []).map((item) => ({
        id: item.id,
        counted_quantity:
            item.final_quantity !== null
                ? String(item.final_quantity)
                : isSecondCounter
                  ? item.counted_quantity_2 !== null
                      ? String(item.counted_quantity_2)
                      : ''
                  : item.counted_quantity_1 !== null
                    ? String(item.counted_quantity_1)
                    : '',
        notes: item.notes || '',
    }));
}

function openFromQuery() {
    const id = Number(
        new URLSearchParams(window.location.search).get('session'),
    );

    if (id) {
        const session = props.sessions.find((item) => item.id === id);

        if (session) {
            openSession(session);
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

async function submitCounts() {
    if (!selectedSession.value) {
        return;
    }

    const invalid = countRows.value.some(
        (row) =>
            row.counted_quantity === '' || Number(row.counted_quantity) < 0,
    );

    if (invalid) {
        toast.error('Vui lòng nhập số lượng thực tế cho tất cả nguyên liệu.');

        return;
    }

    isSubmitting.value = true;

    try {
        const response = await axios.post(
            `${countsBaseUrl.value}/${selectedSession.value.id}/counts`,
            {
                items: countRows.value.map((row) => ({
                    id: row.id,
                    counted_quantity: Number(row.counted_quantity),
                    notes: row.notes || null,
                })),
            },
        );
        toast.success(response.data.message || 'Đã lưu kết quả đối chiếu.');
        await router.reload();
    } catch (error: any) {
        toast.error(
            error.response?.data?.message || 'Không thể lưu kết quả đối chiếu.',
        );
    } finally {
        isSubmitting.value = false;
    }
}

async function submitForApproval() {
    if (!selectedSession.value) {
        return;
    }

    isSubmitting.value = true;

    try {
        const response = await axios.post(
            `/api/inventory/count-sessions/${selectedSession.value.id}/submit-approval`,
            {
                notes: 'Kết quả chốt nguyên liệu đã được đối chiếu trên hệ thống.',
            },
        );
        toast.success(response.data.message || 'Đã gửi kỳ chốt chờ phê duyệt.');
        await router.reload();
    } catch (error: any) {
        toast.error(error.response?.data?.message || 'Chưa thể gửi phê duyệt.');
    } finally {
        isSubmitting.value = false;
    }
}

async function approveSession() {
    if (
        !selectedSession.value ||
        !window.confirm(
            'Phê duyệt sẽ ghi điều chỉnh thiếu/thừa vào tồn kho. Tiếp tục?',
        )
    ) {
        return;
    }

    isSubmitting.value = true;

    try {
        const response = await axios.post(
            `/api/inventory/count-sessions/${selectedSession.value.id}/approve`,
        );
        toast.success(
            response.data.message || 'Đã phê duyệt và cập nhật tồn kho.',
        );
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
    if (!selectedSession.value) {
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
            `/api/inventory/count-sessions/${selectedSession.value.id}/cancel`,
            { reason },
        );
        toast.success('Đã hủy kỳ chốt.');
        await router.reload();
    } catch (error: any) {
        toast.error(error.response?.data?.message || 'Không thể hủy kỳ chốt.');
    } finally {
        isSubmitting.value = false;
    }
}

async function reconcileItem(item: ClosingItem) {
    if (!selectedSession.value || item.reconciliation_status !== 'pending') {
        return;
    }

    const finalQuantity = window.prompt(
        `Nhập số lượng cuối cùng cho ${item.ingredient?.name || 'nguyên liệu'}:`,
        String(item.counted_quantity_2 ?? item.counted_quantity_1 ?? ''),
    );

    if (finalQuantity === null) {
        return;
    }

    const notes = window.prompt(
        'Ghi chú bắt buộc cho việc đồng đếm:',
        `Đã kiểm tra lại thực tế tại ${branchLabel.value}`,
    );

    if (notes === null || !notes.trim()) {
        return;
    }

    try {
        await axios.post(
            `/api/inventory/count-sessions/${selectedSession.value.id}/items/${item.id}/reconcile`,
            { final_quantity: Number(finalQuantity), notes },
        );
        toast.success('Đã chốt dòng cần đồng đếm.');
        await router.reload();
    } catch (error: any) {
        toast.error(
            error.response?.data?.message || 'Không thể chốt dòng đối chiếu.',
        );
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
                        @click="showCreate = true"
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
                <Card class="border-border bg-card shadow-sm"
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
                <Card class="border-amber-200 bg-amber-50/70 shadow-sm dark:border-amber-500/20 dark:bg-amber-500/5"
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
                <Card class="border-sky-200 bg-sky-50/70 shadow-sm dark:border-sky-500/20 dark:bg-sky-500/5"
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
                <Card class="border-rose-200 bg-rose-50/70 shadow-sm dark:border-rose-500/20 dark:bg-rose-500/5"
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
                        <button
                            v-for="session in filteredSessions"
                            :key="session.id"
                            class="flex w-full flex-col gap-4 p-5 text-left transition hover:bg-muted/50 lg:flex-row lg:items-center lg:justify-between"
                            @click="openSession(session)"
                        >
                            <div class="min-w-0">
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
                                </div>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{ session.period_start }} →
                                    {{ session.period_end }} ·
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
                            <div
                                class="grid grid-cols-2 gap-x-6 gap-y-1 text-right text-xs sm:grid-cols-4"
                            >
                                <div>
                                    <p class="text-muted-foreground">Phải còn</p>
                                    <p class="font-bold text-foreground">
                                        {{
                                            formatNumber(
                                                session.total_expected_quantity,
                                            )
                                        }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-muted-foreground">Giá trị</p>
                                    <p class="font-bold text-foreground">
                                        {{
                                            formatCurrency(
                                                session.total_expected_value,
                                            )
                                        }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-muted-foreground">Thiếu</p>
                                    <p class="font-bold text-rose-600 dark:text-rose-400">
                                        {{
                                            formatCurrency(
                                                session.total_shortage_value,
                                            )
                                        }}
                                    </p>
                                </div>
                                <div v-if="!isBranchMode">
                                    <p class="text-muted-foreground">Task</p>
                                    <p
                                        class="font-bold"
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
                        </button>
                    </div>
                </CardContent>
            </Card>

            <Card
                v-if="selectedSession"
                class="border-amber-200 bg-card shadow-2xl dark:border-amber-500/30 dark:bg-slate-900/95"
            >
                <CardHeader class="border-b border-border">
                    <div
                        class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between"
                    >
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <CardTitle
                                    >Kỳ chốt #{{
                                        selectedSession.id
                                    }}</CardTitle
                                ><Badge
                                    variant="outline"
                                    :class="statusClass(selectedSession.status)"
                                    >{{
                                        statusLabel(selectedSession.status)
                                    }}</Badge
                                >
                            </div>
                            <CardDescription class="mt-1 text-muted-foreground"
                                >{{ selectedSession.period_start }} →
                                {{ selectedSession.period_end }} ·
                                {{ branch.name }}</CardDescription
                            >
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                v-if="
                                    canManage &&
                                    selectedSession.status === 'in_progress'
                                "
                                variant="outline"
                                class="gap-2 border-border"
                                @click="openAssign(selectedSession)"
                                ><UserPlus class="size-4" /> Giao đối
                                chiếu</Button
                            >
                            <Button
                                v-if="
                                    canManage &&
                                    selectedSession.status === 'in_progress'
                                "
                                variant="outline"
                                class="border-rose-300 text-rose-600 hover:bg-rose-50 dark:border-rose-500/30 dark:text-rose-300"
                                @click="cancelSession"
                                >Hủy kỳ</Button
                            >
                            <Button
                                variant="ghost"
                                class="text-muted-foreground hover:text-foreground"
                                @click="selectedSession = null"
                                >Đóng</Button
                            >
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="space-y-5 p-5">
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
                        <div
                            class="rounded-xl border border-border bg-muted/30 p-3"
                        >
                            <p class="text-[11px] text-muted-foreground uppercase">
                                Tồn đầu kỳ
                            </p>
                            <p class="mt-1 font-black text-foreground">
                                {{
                                    formatNumber(
                                        (selectedSession.items || []).reduce(
                                            (sum, i) =>
                                                sum +
                                                Number(i.opening_quantity || 0),
                                            0,
                                        ),
                                    )
                                }}
                            </p>
                        </div>
                        <div
                            class="rounded-xl border border-emerald-200 bg-emerald-50/70 p-3 dark:border-emerald-500/20 dark:bg-emerald-500/5"
                        >
                            <p
                                class="text-[11px] text-emerald-700 uppercase dark:text-emerald-400/70"
                            >
                                Nhập trong kỳ
                            </p>
                            <p class="mt-1 font-black text-emerald-900 dark:text-emerald-300">
                                {{
                                    formatNumber(
                                        (selectedSession.items || []).reduce(
                                            (sum, i) =>
                                                sum +
                                                Number(i.inbound_quantity || 0),
                                            0,
                                        ),
                                    )
                                }}
                            </p>
                        </div>
                        <div
                            class="rounded-xl border border-orange-200 bg-orange-50/70 p-3 dark:border-orange-500/20 dark:bg-orange-500/5"
                        >
                            <p class="text-[11px] text-orange-700 uppercase dark:text-orange-400/70">
                                Xuất trong kỳ
                            </p>
                            <p class="mt-1 font-black text-orange-900 dark:text-orange-300">
                                {{
                                    formatNumber(
                                        (selectedSession.items || []).reduce(
                                            (sum, i) =>
                                                sum +
                                                Number(
                                                    i.outbound_quantity || 0,
                                                ),
                                             0,
                                        ),
                                    )
                                }}
                            </p>
                        </div>
                        <div
                            class="rounded-xl border border-sky-200 bg-sky-50/70 p-3 dark:border-sky-500/20 dark:bg-sky-500/5"
                        >
                            <p class="text-[11px] text-sky-700 uppercase dark:text-sky-400/70">
                                Phải còn
                            </p>
                            <p class="mt-1 font-black text-sky-900 dark:text-sky-300">
                                {{
                                    formatNumber(
                                        selectedSession.total_expected_quantity,
                                    )
                                }}
                            </p>
                        </div>
                        <div
                            class="rounded-xl border border-rose-200 bg-rose-50/70 p-3 dark:border-rose-500/20 dark:bg-rose-500/5"
                        >
                            <p class="text-[11px] text-rose-700 uppercase dark:text-rose-400/70">
                                Thiếu
                            </p>
                            <p class="mt-1 font-black text-rose-900 dark:text-rose-300">
                                {{
                                    formatCurrency(
                                        selectedSession.total_shortage_value,
                                    )
                                }}
                            </p>
                        </div>
                        <div
                            class="rounded-xl border border-amber-200 bg-amber-50/70 p-3 dark:border-amber-500/20 dark:bg-amber-500/5"
                        >
                            <p class="text-[11px] text-amber-700 uppercase dark:text-amber-400/70">
                                Thừa
                            </p>
                            <p class="mt-1 font-black text-amber-900 dark:text-amber-300">
                                {{
                                    formatCurrency(
                                        selectedSession.total_surplus_value,
                                    )
                                }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="rounded-xl border border-border bg-muted/20 p-4 text-sm text-muted-foreground"
                    >
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <span
                                >Người mở kỳ:
                                <strong class="text-foreground">{{
                                    selectedSession.countedBy?.name || '—'
                                }}</strong></span
                            ><span v-if="selectedSession.secondCountedBy"
                                >Nhân viên đối chiếu:
                                <strong class="text-amber-700 dark:text-amber-300">{{
                                    selectedSession.secondCountedBy.name
                                }}</strong></span
                            ><span v-if="activeTask"
                                >Task:
                                <strong class="text-foreground">{{
                                    activeTask.status
                                }}</strong></span
                            >
                        </div>
                        <p class="mt-2 text-xs text-muted-foreground">
                            Số “Phải còn” là số hệ thống tính từ sổ giao dịch
                            tại thời điểm mở kỳ. Số thực tế chỉ được ghi vào tồn
                            kho sau bước phê duyệt.
                        </p>
                    </div>

                    <div
                        class="overflow-x-auto rounded-xl border border-border"
                    >
                        <table class="w-full min-w-[1080px] text-left text-xs">
                            <thead
                                class="bg-muted/40 text-[11px] tracking-wider text-muted-foreground uppercase"
                            >
                                <tr>
                                    <th class="px-3 py-3">Nguyên liệu</th>
                                    <th class="px-3 py-3 text-right">
                                        Tồn đầu
                                    </th>
                                    <th class="px-3 py-3 text-right">Nhập</th>
                                    <th class="px-3 py-3 text-right">Xuất</th>
                                    <th class="px-3 py-3 text-right">
                                        Phải còn
                                    </th>
                                    <th class="px-3 py-3 text-right">
                                        Giá vốn
                                    </th>
                                    <th class="px-3 py-3 text-right">
                                        Thực tế
                                    </th>
                                    <th class="px-3 py-3 text-right">Lệch</th>
                                    <th class="px-3 py-3">Kết luận</th>
                                    <th class="px-3 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr
                                    v-for="(
                                        item, index
                                    ) in selectedSession.items"
                                    :key="item.id"
                                    class="align-top"
                                >
                                    <td class="px-3 py-3">
                                        <p class="font-bold text-foreground">
                                            {{
                                                item.ingredient?.name ||
                                                `Nguyên liệu #${item.ingredient_id}`
                                            }}
                                        </p>
                                        <p class="mt-1 text-muted-foreground">
                                            {{ item.ingredient?.sku || '—' }} ·
                                            {{
                                                item.ingredient?.unit?.symbol ||
                                                ''
                                            }}
                                        </p>
                                    </td>
                                    <td
                                        class="px-3 py-3 text-right text-muted-foreground"
                                    >
                                        {{
                                            formatNumber(item.opening_quantity)
                                        }}
                                    </td>
                                    <td
                                        class="px-3 py-3 text-right text-emerald-600 dark:text-emerald-300"
                                    >
                                        {{
                                            formatNumber(item.inbound_quantity)
                                        }}
                                    </td>
                                    <td
                                        class="px-3 py-3 text-right text-orange-600 dark:text-orange-300"
                                    >
                                        {{
                                            formatNumber(item.outbound_quantity)
                                        }}
                                    </td>
                                    <td
                                        class="px-3 py-3 text-right font-bold text-sky-700 dark:text-sky-300"
                                    >
                                        {{
                                            formatNumber(item.expected_quantity)
                                        }}
                                    </td>
                                    <td
                                        class="px-3 py-3 text-right text-muted-foreground"
                                    >
                                        {{ formatCurrency(item.unit_cost) }}
                                    </td>
                                    <td class="px-3 py-3 text-right">
                                        <Input
                                            v-if="canEditSelectedCounts"
                                            v-model="
                                                countRows[index]
                                                    .counted_quantity
                                            "
                                            type="number"
                                            min="0"
                                            step="0.001"
                                            class="h-8 w-28 border-input bg-background text-right text-xs"
                                        />
                                        <span
                                            v-else
                                            class="font-bold text-foreground"
                                            >{{
                                                item.final_quantity === null
                                                    ? '—'
                                                    : formatNumber(
                                                          item.final_quantity,
                                                      )
                                            }}</span
                                        >
                                    </td>
                                    <td
                                        class="px-3 py-3 text-right font-bold"
                                        :class="varianceClass(item)"
                                    >
                                        {{
                                            item.final_quantity === null
                                                ? '—'
                                                : formatNumber(
                                                      item.variance_quantity,
                                                  )
                                        }}
                                    </td>
                                    <td class="px-3 py-3">
                                        <span
                                            class="font-bold"
                                            :class="varianceClass(item)"
                                            >{{ varianceLabel(item) }}</span
                                        >
                                        <p
                                            v-if="item.final_quantity !== null"
                                            class="mt-1 text-muted-foreground"
                                        >
                                            {{
                                                formatCurrency(
                                                    item.variance_value,
                                                )
                                            }}
                                        </p>
                                    </td>
                                    <td class="px-3 py-3">
                                        <Button
                                            v-if="
                                                item.reconciliation_status ===
                                                    'pending' && canManage
                                            "
                                            size="sm"
                                            variant="outline"
                                            class="border-rose-300 text-[11px] text-rose-600 hover:bg-rose-50 dark:border-rose-500/30 dark:text-rose-300"
                                            @click.stop="reconcileItem(item)"
                                            >Đồng đếm</Button
                                        >
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div
                        class="flex flex-col gap-3 border-t border-border pt-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div
                            class="flex items-center gap-2 text-xs text-muted-foreground"
                        >
                            <ShieldAlert class="size-4 text-amber-500 dark:text-amber-400" /> Chênh
                            lệch âm là thiếu thực tế so với số hệ thống phải
                            còn.
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                v-if="canEditSelectedCounts"
                                :disabled="isSubmitting"
                                class="gap-2 bg-amber-500 font-bold text-slate-950 hover:bg-amber-400 dark:bg-amber-500 dark:text-slate-950"
                                @click="submitCounts"
                                ><CheckCircle2 class="size-4" /> Lưu kết quả đối
                                chiếu</Button
                            >
                            <Button
                                v-if="
                                    canManage &&
                                    selectedSession.status === 'in_progress'
                                "
                                :disabled="isSubmitting"
                                variant="outline"
                                class="border-sky-300 text-sky-700 hover:bg-sky-50 dark:border-sky-500/30 dark:text-sky-300"
                                @click="submitForApproval"
                                >Gửi phê duyệt</Button
                            >
                            <Button
                                v-if="
                                    canApprove &&
                                    selectedSession.status ===
                                        'pending_approval'
                                "
                                :disabled="isSubmitting"
                                class="bg-emerald-600 font-bold text-white hover:bg-emerald-500"
                                @click="approveSession"
                                >Phê duyệt & cập nhật tồn</Button
                            >
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div
            v-if="showCreate"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
            @click.self="showCreate = false"
        >
            <Card
                class="w-full max-w-lg border-border bg-card shadow-2xl"
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
                            class="bg-amber-500 font-bold text-slate-950 hover:bg-amber-400 dark:bg-amber-500 dark:text-slate-950"
                            @click="createClosing"
                            >Tạo kỳ chốt</Button
                        >
                    </div>
                </CardContent></Card
            >
        </div>

        <div
            v-if="showAssign && selectedSession"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
            @click.self="showAssign = false"
        >
            <Card
                class="w-full max-w-lg border-border bg-card shadow-2xl"
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
                            class="gap-2 bg-amber-500 font-bold text-slate-950 hover:bg-amber-400 dark:bg-amber-500 dark:text-slate-950"
                            @click="assignCounter"
                            ><UserPlus class="size-4" /> Giao việc</Button
                        >
                    </div>
                </CardContent></Card
            >
        </div>
    </div>
</template>
